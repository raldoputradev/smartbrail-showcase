<?php

namespace App\Services\AdminAi;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AdminAiOrchestrator
{
    private const MAX_TOOL_ROUNDS = 6;

    public function __construct(
        private AdminAiContextBuilder $context,
        private AdminAiToolRunner $tools,
        private GeminiSseClient $sse,
    ) {}

    /**
     * Jawaban bertahap. Teks/blok dikirim lewat $turn begitu tersedia.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  callable(): bool  $alive  false = klien sudah putus
     * @param  list<array{name: string, path: string, mime: string, image: bool}>  $attachments
     * @return string sumber jawaban: gemini | local
     *
     * @throws CopilotStreamAborted
     * @throws CopilotProviderError
     */
    public function streamChat(string $message, array $history, User $user, CopilotTurn $turn, callable $alive, array $attachments = []): string
    {
        $message = trim(Str::limit($message, 2000, ''));

        $key = 'admin-ai-chat:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw new CopilotProviderError('rate_limit');
        }
        RateLimiter::hit($key, 60);

        // Slash command (/help, /menu …) dan pencarian nama/NIM dijawab lokal
        // — instan, tanpa menunggu provider, dan identitas tidak dikirim ke provider.
        $q = Str::lower($message);
        if (! $this->isGeminiConfigured()
            || ($attachments === [] && (
                $this->directSearchQuery($message) !== null
                || str_starts_with($q, '/')
                || in_array($q, ['help', 'bantuan', 'apa yang bisa kamu lakukan', 'kemampuan'], true)
            ))
        ) {
            $this->streamLocal($message, $user, $turn, $attachments);

            return 'local';
        }

        // Circuit breaker: provider sedang padam → langsung mode lokal
        // (tool lokal masih bisa menyusun laporan/grafik tanpa menunggu timeout).
        if (Cache::has('admin-ai-provider-down')) {
            $this->streamLocal($message, $user, $turn, $attachments);

            return 'local';
        }

        try {
            $this->streamGemini($message, $history, $user, $turn, $alive, $attachments);
            $this->ensureChartBlock($message, $user, $turn);
            Cache::forget('admin-ai-provider-down');
        } catch (CopilotStreamAborted $e) {
            throw $e;
        } catch (CopilotProviderError $e) {
            Cache::put('admin-ai-provider-down', 1, now()->addMinutes(10));
            if ($turn->started) {
                // Kartu/teks sudah terkirim — jangan hanguskan; tandai jawaban terpotong.
                $turn->text("\n\n_(Jawaban terpotong — layanan AI sedang padam. Data kartu di atas tetap valid; coba beberapa menit lagi untuk analisis teks.)_");

                return 'gemini';
            }
            // Provider padam sebelum ada jawaban — jawab lokal agar pengguna tidak menunggu sia-sia.
            Log::warning('Copilot: provider gagal, fallback lokal', ['error' => $e->getMessage()]);
            $this->streamLocal($message, $user, $turn, $attachments);

            return 'local';
        }

        return 'gemini';
    }

    /**
     * @param  list<array{name: string, path: string, mime: string, image: bool}>  $attachments
     */
    private function streamLocal(string $message, User $user, CopilotTurn $turn, array $attachments = []): void
    {
        $proposals = [];
        $charts = [];
        $blocks = [];
        if ($attachments !== []) {
            $names = implode(', ', array_column($attachments, 'name'));
            $hasImage = count(array_filter($attachments, fn ($a) => $a['image'])) > 0;
            $turn->text($hasImage
                ? "📎 Lampiran diterima ({$names}). Mode lokal tidak bisa membaca isi gambar — analisis gambar membutuhkan AI online. Jawaban di bawah berdasarkan teks pertanyaan Anda; kirim ulang nanti saat provider aktif untuk analisis gambar.\n\n"
                : "📎 Lampiran teks diterima ({$names}). Mode lokal tidak merangkum file — jawaban berdasarkan teks pertanyaan Anda.\n\n");
        }
        $reply = $this->localFallback($message, $user, $proposals, $charts, $blocks);
        $turn->text($reply);
        foreach ($blocks as $block) {
            $turn->block($block);
        }
        foreach ($charts as $chart) {
            $turn->block(['type' => 'chart', 'chart' => $chart]);
        }
        foreach ($proposals as $p) {
            $turn->block(['type' => 'proposal', 'proposal' => $p]);
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  list<array{name: string, path: string, mime: string, image: bool}>  $attachments
     */
    private function streamGemini(string $message, array $history, User $user, CopilotTurn $turn, callable $alive, array $attachments = []): void
    {
        $apiKey = trim((string) config('services.gemini.api_key', ''));
        $fallbacks = config('services.gemini.fallback_models', []);
        $models = array_values(array_unique(array_filter(array_merge(
            [(string) config('services.gemini.model', 'gemini-flash-latest')],
            is_array($fallbacks) ? $fallbacks : []
        ))));

        $privacy = app(CopilotPrivacyFilter::class);
        $contents = [];
        foreach (array_slice($history, -8) as $row) {
            $text = trim((string) ($row['content'] ?? ''));
            if ($text !== '') {
                $contents[] = ['role' => ($row['role'] ?? '') === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $privacy->redact($text)]]];
            }
        }
        $userParts = [['text' => $privacy->redact($message)]];
        foreach ($attachments as $att) {
            if ($att['image']) {
                $raw = @file_get_contents($att['path']);
                if ($raw !== false) {
                    $userParts[] = ['inlineData' => ['mimeType' => $att['mime'], 'data' => base64_encode($raw)]];
                }
            } else {
                $raw = @file_get_contents($att['path']);
                if ($raw !== false) {
                    $userParts[] = ['text' => "[Isi lampiran: {$att['name']}]\n".$privacy->redact(Str::limit($raw, 6000, '…'))];
                }
            }
        }
        $contents[] = ['role' => 'user', 'parts' => $userParts];

        $declarations = AdminAiToolRegistry::geminiDeclarationsForUser($user);
        $context = $user->isLaboran()
            ? $this->context->snapshot()
            : ['generated_at' => now()->toDateTimeString(), 'timezone' => config('app.timezone')];
        $system = $this->systemPrompt($privacy->redactValue($context), $user);

        $last = null;
        foreach ($models as $model) {
            try {
                $turn->status('Menghubungi Gemini ('.$model.')…');
                $this->streamGeminiModel($model, $apiKey, $contents, $declarations, $system, $user, $turn, $alive, $privacy);

                return;
            } catch (CopilotStreamAborted $e) {
                throw $e;
            } catch (\Throwable $e) {
                $last = $e;
                Log::warning('Copilot: model gagal', ['model' => $model, 'error' => $e->getMessage()]);
                // Jangan pindah model setelah sebagian jawaban terkirim (cegah teks ganda).
                if ($turn->started) {
                    break;
                }
            }
        }

        throw $last instanceof CopilotProviderError
            ? $last
            : new CopilotProviderError('unknown', $last?->getMessage() ?? 'Gemini unavailable', $last);
    }

    /**
     * @param  list<array<string, mixed>>  $contents
     * @param  list<array<string, mixed>>  $declarations
     */
    private function streamGeminiModel(
        string $model,
        string $apiKey,
        array $contents,
        array $declarations,
        string $system,
        User $user,
        CopilotTurn $turn,
        callable $alive,
        CopilotPrivacyFilter $privacy
    ): void {
        $url = rtrim((string) config('services.gemini.base_url'), '/').'/models/'.rawurlencode($model).':streamGenerateContent?alt=sse';

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $payload = [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => $contents,
                'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1500],
            ];
            if ($declarations !== []) {
                $payload['tools'] = [['functionDeclarations' => $declarations]];
            }

            $text = '';
            $signature = null;
            $calls = [];
            $needsGap = $turn->reply !== '';

            $this->sse->stream($url, $apiKey, $payload, function (array $chunk) use (&$text, &$signature, &$calls, &$needsGap, $turn, $privacy): void {
                if (isset($chunk['error'])) {
                    throw new CopilotProviderError('http', 'Gemini stream error: '.Str::limit(json_encode($chunk['error']), 200));
                }
                foreach ((array) data_get($chunk, 'candidates.0.content.parts', []) as $part) {
                    if (! is_array($part)) {
                        continue;
                    }
                    if (isset($part['functionCall'])) {
                        $calls[] = $part;

                        continue;
                    }
                    if (! empty($part['thought'])) {
                        continue;
                    }
                    if (isset($part['thoughtSignature'])) {
                        $signature = $part['thoughtSignature'];
                    }
                    $delta = (string) ($part['text'] ?? '');
                    if ($delta === '') {
                        continue;
                    }
                    if ($needsGap) {
                        $turn->text("\n\n");
                        $needsGap = false;
                    }
                    $text .= $delta;
                    $shown = $privacy->restoreStream($delta);
                    if ($shown !== '') {
                        $turn->text($shown);
                    }
                }
            }, $alive);
            $tail = $privacy->flushStream();
            if ($tail !== '') {
                $turn->text($tail);
            }

            if ($calls === []) {
                if (trim($turn->reply) === '' && $turn->blocks === []) {
                    throw new CopilotProviderError('empty', 'Gemini empty reply');
                }

                return;
            }

            $modelParts = [];
            if ($text !== '') {
                $modelParts[] = array_filter(['text' => $text, 'thoughtSignature' => $signature]);
            }
            $contents[] = ['role' => 'model', 'parts' => $this->sanitizeGeminiParts(array_merge($modelParts, $calls))];

            $responses = [];
            foreach ($calls as $part) {
                $name = (string) ($part['functionCall']['name'] ?? '');
                $args = $part['functionCall']['args'] ?? [];
                $args = is_array($args) ? $args : (array) $args;
                $meta = AdminAiToolRegistry::get($name);
                $turn->status('Mengambil data: '.Str::lower((string) ($meta['title'] ?? $name)));

                $result = in_array($name, AdminAiToolRegistry::copilotToolsFor($user->role), true)
                    ? $this->tools->run($name, $privacy->restoreValue($args), $user)
                    : ['ok' => false, 'kind' => 'error', 'error' => 'Tidak punya izin untuk tool ini'];
                foreach ($result['blocks'] ?? [] as $block) {
                    $turn->block($block);
                }
                if (($result['kind'] ?? '') === 'proposal' && isset($result['proposal'])) {
                    $turn->block(['type' => 'proposal', 'proposal' => $result['proposal']]);
                }
                if (isset($result['chart']) && is_array($result['chart'])) {
                    $turn->block(['type' => 'chart', 'chart' => $result['chart']]);
                }
                $responses[] = ['functionResponse' => ['name' => $name, 'response' => $privacy->redactValue($this->geminiToolResponsePayload($result))]];
            }
            $contents[] = ['role' => 'user', 'parts' => $responses];
            $turn->status('Menyusun jawaban');
        }

        throw new CopilotProviderError('unknown', 'Terlalu banyak tool round');
    }

    private function ensureChartBlock(string $message, User $user, CopilotTurn $turn): void
    {
        $q = Str::lower($message);
        if (! preg_match('/\b(grafik|chart|tren)\b/u', $q) || ! preg_match('/\b(absen\w*|tap|kehadiran)\b/u', $q)) {
            return;
        }
        foreach ($turn->blocks as $block) {
            if (($block['type'] ?? '') === 'chart') {
                return;
            }
        }
        $r = $this->tools->run('chart_absensi', $this->parseChartArgsFromMessage($message), $user);
        if (isset($r['chart'])) {
            $turn->block(['type' => 'chart', 'chart' => $r['chart']]);
        }
    }

    /**
     * "cari mahasiswa X", "carikan Budi", atau NIM murni → kata kunci; selain itu null.
     */
    private function directSearchQuery(string $message): ?string
    {
        $m = trim($message);
        if (preg_match('/^\d{5,20}$/', $m)) {
            return $m;
        }
        if (preg_match('/^(?:tolong\s+)?(?:cari|carikan|temukan)\s+(?:data\s+)?(?:mahasiswa\s+)?(?:bernama\s+|dengan\s+nim\s+|nim\s+)?(.{2,60})$/iu', $m, $hit)) {
            $kw = trim($hit[1], " \t.?!");
            if (mb_strlen($kw) >= 2 && ! preg_match('/^(mahasiswa|dosen|laboran|semua|data)$/iu', $kw)) {
                return $kw;
            }
        }

        return null;
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{
     *   reply: string,
     *   source: string,
     *   proposals: list<array<string, mixed>>,
     *   charts: list<array<string, mixed>>,
     *   ai_enabled: bool
     * }
     */
    public function chat(string $message, array $history, User $admin): array
    {
        $message = trim(Str::limit($message, 2000, ''));
        if ($message === '') {
            return [
                'reply' => 'Ketik pertanyaan, misalnya: ringkas absensi hari ini atau buat laporan absensi bulan ini.',
                'source' => 'local',
                'proposals' => [],
                'charts' => [],
                'ai_enabled' => $this->isGeminiConfigured(),
            ];
        }

        $key = 'admin-ai-chat:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return [
                'reply' => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
                'source' => 'rate_limit',
                'proposals' => [],
                'charts' => [],
                'ai_enabled' => $this->isGeminiConfigured(),
            ];
        }
        RateLimiter::hit($key, 60);

        // Endpoint lama (non-stream) tidak lagi dipakai UI dan tidak melewati filter privasi:
        // selalu dijawab lokal, tidak pernah ke provider.
        $proposals = [];
        $charts = [];
        $fallback = $this->localFallback($message, $admin, $proposals, $charts);
        $this->ensureChartIfRequested($message, $admin, $charts, $fallback);

        return [
            'reply' => $fallback,
            'source' => 'local',
            'proposals' => $proposals,
            'charts' => $charts,
            'ai_enabled' => $this->isGeminiConfigured(),
        ];
    }

    /**
     * Pastikan permintaan grafik selalu mengirim payload chart ke UI
     * (Gemini kadang hanya menulis tabel markdown tanpa tool).
     *
     * @param  list<array<string, mixed>>  $charts
     */
    private function ensureChartIfRequested(string $message, User $admin, array &$charts, string &$reply): void
    {
        $q = Str::lower($message);
        $wantsAbsensi = (bool) preg_match('/\b(grafik|chart|tren|visual)\b/u', $q)
            && (bool) preg_match('/\b(absen|tap|kehadiran)\b/u', $q);
        // Frasa umum: "tampilkan grafik absensi yang ada data"
        if (! $wantsAbsensi && str_contains($q, 'grafik') && (str_contains($q, 'absen') || str_contains($q, 'kehadiran'))) {
            $wantsAbsensi = true;
        }
        if (! $wantsAbsensi) {
            return;
        }

        $args = $this->parseChartArgsFromMessage($message);
        $needAbsensi = $wantsAbsensi && ! $this->chartsContain($charts, 'absensi');
        if ($needAbsensi) {
            $r = $this->tools->run('chart_absensi', $args, $admin);
            if (isset($r['chart'])) {
                $this->pushChart($charts, $r['chart']);
                $periode = (string) data_get($r, 'data.periode', '');
                $total = (int) data_get($r, 'data.total', 0);
                $extra = "\n\n_(Grafik visual absensi ditampilkan di bawah — periode {$periode}, total {$total} tap.)_";
                if (! str_contains($reply, 'Grafik visual absensi ditampilkan')) {
                    $reply .= $extra;
                }
            }
        }
    }

    /**
     * Infer chart tool args dari bahasa natural (tahun/bulan/custom + kelas/tim).
     *
     * @return array<string, mixed>
     */
    private function parseChartArgsFromMessage(string $message): array
    {
        $q = Str::lower($message);
        $args = [
            'chart_type' => 'line',
        ];

        $bulanNama = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
            'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];
        $bn = implode('|', array_keys($bulanNama));

        // Rentang custom: YYYY-MM-DD … YYYY-MM-DD
        if (preg_match('/(\d{4}-\d{2}-\d{2}).{0,24}?(\d{4}-\d{2}-\d{2})/u', $message, $m)) {
            $args['mode'] = 'custom';
            $args['tanggal_mulai'] = $m[1];
            $args['tanggal_akhir'] = $m[2];
            $args['prefer_data'] = false;
        } elseif (preg_match('/\b('.$bn.')\s+(\d{4})\s*(?:sampai|hingga|ke|s\.d\.?|\-)\s*('.$bn.')\s+(\d{4})/u', $q, $mm)) {
            // Rentang nama bulan: "grafik absensi mei 2026 sampai juli 2026"
            $start = Carbon::create((int) $mm[2], $bulanNama[$mm[1]], 1)->startOfMonth();
            $end = Carbon::create((int) $mm[4], $bulanNama[$mm[3]], 1)->endOfMonth();
            $args['mode'] = 'custom';
            $args['tanggal_mulai'] = $start->toDateString();
            $args['tanggal_akhir'] = $end->toDateString();
            $args['prefer_data'] = false;
        } elseif (preg_match('/\b('.$bn.')\s+(\d{4})/u', $q, $mm)) {
            // Satu bulan bernama: "grafik absensi juli 2026"
            $args['mode'] = 'custom';
            $args['tanggal_mulai'] = Carbon::create((int) $mm[2], $bulanNama[$mm[1]], 1)->startOfMonth()->toDateString();
            $args['tanggal_akhir'] = Carbon::create((int) $mm[2], $bulanNama[$mm[1]], 1)->endOfMonth()->toDateString();
            $args['prefer_data'] = false;
        } elseif (preg_match('/\b(setahun|tahunan|tahun\s*ini|1\s*tahun|satu\s*tahun)\b/u', $q)
            || preg_match('/\bgrafik\b.{0,40}\btahun\b/u', $q)) {
            $args['mode'] = 'tahun';
            $args['prefer_data'] = false;
        } elseif (preg_match('/\b(bulan\s*ini|sebulan|1\s*bulan|satu\s*bulan)\b/u', $q)
            || preg_match('/\bgrafik\b.{0,40}\bbulan\b/u', $q)) {
            $args['mode'] = 'bulan';
            $args['prefer_data'] = false;
        } elseif (preg_match('/\b(minggu\s*ini|seminggu|1\s*minggu)\b/u', $q)
            || preg_match('/\bgrafik\b.{0,40}\bminggu\b/u', $q)) {
            $args['mode'] = 'minggu';
            $args['prefer_data'] = false;
        } else {
            $args['mode'] = '7hari';
            $args['prefer_data'] = (bool) preg_match('/\b(ada\s*data|yang\s*ada|berdata)\b/u', $q);
        }

        if (preg_match('/\bkelas\s+([a-z0-9][a-z0-9\s\-]{0,40}?)(?=\s*(?:tim|pbl|tahun|bulan|minggu|dari|sampai|antara|,|\.|$))/iu', $message, $km)) {
            $args['kelas'] = trim($km[1]);
            $args['prefer_data'] = false;
        } elseif (preg_match('/\b(robotika\s*[abc])\b/iu', $message, $km)) {
            $args['kelas'] = 'Robotika '.strtoupper(substr(trim($km[1]), -1));
            $args['prefer_data'] = false;
        }

        if (preg_match('/\b(?:tim|pbl)\s+([a-z0-9][a-z0-9\s\-:\(\)\.]{1,60}?)(?=\s*(?:kelas|tahun|bulan|minggu|dari|sampai|antara|,|\.|$))/iu', $message, $tm)) {
            $args['tim'] = trim($tm[1]);
            $args['prefer_data'] = false;
        }

        return $args;
    }

    /**
     * @param  list<array<string, mixed>>  $charts
     */
    private function chartsContain(array $charts, string $needle): bool
    {
        $needle = Str::lower($needle);
        foreach ($charts as $c) {
            if (str_contains(Str::lower((string) ($c['title'] ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $charts
     * @param  array<string, mixed>  $chart
     */
    private function pushChart(array &$charts, array $chart): void
    {
        $title = (string) ($chart['title'] ?? '');
        foreach ($charts as $existing) {
            if ((string) ($existing['title'] ?? '') === $title && $title !== '') {
                return;
            }
        }
        $charts[] = $chart;
    }

    public function isGeminiConfigured(): bool
    {
        return trim((string) config('services.gemini.api_key', '')) !== '';
    }

    /**
     * PHP json_decode mengubah {} menjadi [] — Gemini Struct menolak list untuk args.
     *
     * @param  list<mixed>  $parts
     * @return list<array<string, mixed>>
     */
    private function sanitizeGeminiParts(array $parts): array
    {
        $out = [];
        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                $args = $part['functionCall']['args'] ?? null;
                if ($args === null || $args === [] || (is_array($args) && array_is_list($args))) {
                    $part['functionCall']['args'] = new \stdClass();
                } elseif (is_array($args)) {
                    $part['functionCall']['args'] = $this->assocArrayToObject($args);
                }
            }
            $out[] = $part;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assocArrayToObject(array $data): object
    {
        $obj = new \stdClass();
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $obj->{$k} = $v === [] || array_is_list($v)
                    ? $v
                    : $this->assocArrayToObject($v);
            } else {
                $obj->{$k} = $v;
            }
        }

        return $obj;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function geminiToolResponsePayload(array $result): array
    {
        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'kind' => $result['kind'] ?? 'error',
            'data' => $result['data'] ?? null,
            'error' => $result['error'] ?? null,
            'proposal' => $result['proposal'] ?? null,
            'note' => ($result['kind'] ?? '') === 'proposal'
                ? 'Aksi belum dieksekusi — admin harus konfirmasi di UI.'
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function systemPrompt(array $snapshot, User $user): string
    {
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $peran = $user->isLaboran()
            ? 'staf lab (admin/laboran)'
            : 'dosen/KPS — mahasiswa & tim PBL bimbingan, absensi, rekap, dan laporan; tidak ada data operasional admin (akun/approval, mesin, fingerprint, pengumuman)';
        $toolLain = $user->isLaboran()
            ? ' Jumlah akun → hitung_pengguna. Hal yang perlu perhatian → attention_today.'
            : ' Tim PBL & anggota → tim_pbl_saya. Semua mahasiswa bimbingan → mahasiswa_bimbingan. Siapa alfa → list_alfa. Log tap → log_absensi_cari. Pengajuan ruangan tim → pengajuan_ruangan_saya. Pengumuman → list_announcements.';

        return <<<TXT
Kamu SIMALAS Copilot untuk {$peran} di AIoT SIMALAS (absensi lab BRAIL Polibatam).

AKSI TULIS
- Tool bertipe write (approve_user, bulk_approve_ready, create_ruangan, draft_pengumuman, create_announcement, toggle_mesin, dll) TIDAK langsung mengeksekusi — hasilnya kartu konfirmasi yang diputuskan pengguna di UI.
- Saat pengguna minta aksi (setujui akun, daftarkan ruangan, buat pengumuman, nyalakan/matikan mesin), panggil tool write yang sesuai, lalu jawab SATU kalimat bahwa kartu konfirmasi sudah dibuat. Jangan mengeklaim aksi sudah terjadi.
- Jika data untuk tool write kurang (mis. nama ruangan belum ada), tanya dulu — jangan mengarang argumen.

GAYA
- Bahasa Indonesia. Langsung ke jawaban: kalimat pertama berisi inti, rincian sesudahnya bila perlu.
- Tanpa sapaan, tanpa perkenalan diri, tanpa daftar kemampuan kecuali diminta.
- Markdown sederhana boleh (paragraf pendek, list, **tebal**, tabel kecil). Jangan menulis HTML.
- Jika tool mengembalikan kartu/tabel, angka dan catatannya sudah terlihat pengguna: JANGAN membacakan ulang angka atau catatan kartu, dan jangan menulis kalimat seperti "sudah tampil di layar Anda" atau "lihat kartu di bawah".
- Cukup 1–3 kalimat: pengantar singkat (periode + filter), lalu hanya hal penting yang TIDAK ada di kartu (keterbatasan data lain, langkah lanjut).

DATA
- Angka hanya dari hasil tool. Kutip apa adanya; jangan menghitung ulang persentase atau membuat rumus hadir/alfa sendiri. Jangan mengarang.
- Satuan: jumlah mahasiswa = orang; agregat hadir/tidak lengkap/alfa di laporan periode = sesi-mahasiswa (bukan orang).
- Laporan periode punya dua angka mahasiswa: "dalam cakupan" (semua akun sesuai filter) dan "dalam laporan" (punya catatan hadir/tidak lengkap/alfa di periode, = baris ekspor). Alasan selisihnya sudah tertulis di catatan kartu; jelaskan dari field selisih_cakupan hanya bila pengguna menanyakannya.
- Data kosong: katakan apa adanya (mis. "Belum ada data tidak lengkap hari ini").
- Nama/NIM/email mahasiswa tidak dikirim kepadamu. Teks seperti [NAMA_1], [NIM_1], [EMAIL_1] adalah identitas yang disamarkan sistem: pakai apa adanya sebagai argumen tool (mis. cari_mahasiswa q="[NAMA_1]"), jangan menebak isinya.
- Hari ini → ringkasan_absensi_hari_ini atau daftar_absensi_hari_ini (status tidak_lengkap / alfa / hadir / semua).
- Minggu/bulan/rentang tanggal → laporan_absensi_periode. Jika periode penting tetapi belum jelas, panggil tool itu tanpa periode lalu ajukan SATU pertanyaan singkat.
- Cari nama/NIM → cari_mahasiswa. Grafik/tren → chart_absensi.{$toolLain}
- Hanya tool yang dideklarasikan yang tersedia. Jika diminta data di luar itu, katakan tidak tersedia untuk akun ini.
- Jangan menulis URL sendiri; tautan unduh muncul otomatis bila pengguna berhak.

LAMPIRAN
- Pengguna bisa melampirkan gambar (JPG/PNG/WebP) dan file teks (TXT/CSV/LOG) lewat tombol klip; isinya tiba sebagai bagian pesanmu (gambar inline, teks sebagai kutipan "[Isi lampiran: …]").
- Jika ditanya apakah kamu bisa membaca gambar/foto/file, jawab BISA lewat lampiran (maks 4 MB, 3 file) — jangan pernah menjawab tidak bisa. Analisislah isi lampiran yang benar-benar tiba; jika tidak ada lampiran di pesan, minta pengguna mengklik ikon klip dulu.
- Impor akun massal (Excel template) tetap di halaman Impor Akun, bukan lewat lampiran.

BATAS
- Kamu tidak mengeksekusi perubahan sendiri — aksi tulis hanya lewat tool write, hasilnya kartu konfirmasi untuk pengguna.
- Loker bukan bagian aplikasi ini.
- Jangan menampilkan atau meminta password, token, API key, OTP, atau template sidik jari.
- Di luar absensi/operasional lab: tolak singkat dan beri satu contoh pertanyaan yang bisa dijawab.

KONTEKS AGREGAT (JSON, tanpa data pribadi):
{$json}
TXT;
    }

    /**
     * @param  list<array<string, mixed>>  $proposals
     * @param  list<array<string, mixed>>  $charts
     * @param  list<array<string, mixed>>  $blocks
     */
    private function localFallback(string $message, User $admin, array &$proposals, array &$charts, array &$blocks = []): string
    {
        $q = Str::lower($message);

        $search = $this->directSearchQuery($message);
        if ($search !== null) {
            return $this->absensiToolReply('cari_mahasiswa', ['q' => $search], $admin, $blocks);
        }

        // Kendala upload/paste lampiran — panduan singkat, tidak membebani.
        if (preg_match('/(upload|unggah|lampir|paste|tempel|kirim)\b.{0,25}(foto|gambar|file|lampiran|screenshot|ss|berkas)/iu', $q)
            || preg_match('/(foto|gambar|file|lampiran|screenshot|berkas)\b.{0,25}(gagal|gabisa|tidak bisa|gak bisa|ga bisa|error|terkirim|terupload|diupload|diunggah|dikirim|dilampirkan|masuk)/iu', $q)) {
            return 'Kendala lampiran hampir selalu soal format atau ukuran. Aturannya: gambar JPG/PNG/WebP atau teks TXT/CSV/LOG, **maks 4 MB per file, maks 3 file per pesan**. Cara: klik ikon klip 📎 atau paste screenshot (Ctrl+V) di kolom pesan — tunggu chip "Mengunggah…" selesai lalu kirim. Jika masih gagal: kompres/perkecil gambar, coba format PNG, atau muat ulang halaman lalu ulangi. Sampaikan pesan error yang muncul bila tetap bermasalah.';
        }

        // Pertanyaan kemampuan lampiran — jawaban tetap benar walau provider mati.
        if (preg_match('/(gambar|foto|png|jpe?g|webp|file|berkas|lampiran|dokumen)\b.{0,30}(bisa|dapat|dibaca|diproses|dianalisis|dilihat)/iu', $q)
            || preg_match('/(bisa|dapat)\s+(kamu\s+)?(baca|membaca|memproses|proses|lihat|melihat|analisis|menganalisis)\b.{0,30}(gambar|foto|png|jpe?g|webp|file|berkas|lampiran|dokumen)/iu', $q)) {
            return 'Ya, bisa. Klik ikon klip 📎 di kolom pesan untuk melampirkan gambar (JPG/PNG/WebP) atau file teks (TXT/CSV/LOG), maks 4 MB per file, 3 file per pesan — lalu kirim pertanyaan Anda. Gambar saya analisis saat provider AI aktif; file teks saya baca langsung. Impor akun massal (template Excel) tetap lewat halaman Impor Akun.';
        }

        if (preg_match('/^\/help\b/i', $q) || in_array($q, ['help', 'bantuan', 'apa yang bisa kamu lakukan', 'kemampuan'], true)) {
            return $this->helpReply($admin, $blocks);
        }
        if (preg_match('/^\/menu(?:\s+([a-z]+))?\s*$/', $q, $mm)) {
            return $this->menuReply($mm[1] ?? '', $admin, $blocks);
        }
        if (preg_match('/^\/grafik(?:\s+(.+))?\s*$/', $q, $gm)) {
            $arg = trim((string) ($gm[1] ?? ''));
            if ($arg === '') {
                $blocks[] = ['type' => 'options', 'title' => 'Pilih periode grafik', 'items' => [
                    ['label' => 'Hari ini', 'message' => 'grafik absensi hari ini'],
                    ['label' => 'Minggu ini', 'message' => 'grafik absensi minggu ini'],
                    ['label' => 'Bulan ini', 'message' => 'grafik absensi bulan ini'],
                    ['label' => 'Tahun ini', 'message' => 'grafik absensi tahun ini'],
                ]];

                return 'Grafik absensi untuk periode mana?'
                    ."\nBisa juga sebutkan bulan/rentang, misalnya:"
                    ."\n- `grafik absensi juli 2026`"
                    ."\n- `grafik absensi mei 2026 sampai juli 2026`"
                    ."\n- `grafik absensi 2026-11-01 sampai 2026-11-15`";
            }
            $r = $this->tools->run('chart_absensi', $this->parseChartArgsFromMessage('grafik absensi '.$arg), $admin);
            if (isset($r['chart'])) {
                $blocks[] = ['type' => 'chart', 'chart' => $r['chart']];

                return 'Grafik siap — ini tren absensinya:';
            }

            return (string) ($r['error'] ?? 'Grafik tidak bisa dibuat.');
        }
        if (preg_match('/^\/pengguna(?:\s+(.+))?\s*$/', $q, $pm)) {
            $name = trim((string) ($pm[1] ?? ''));
            if ($name !== '') {
                $r = $this->tools->run('cari_pengguna', ['q' => $name], $admin);
                foreach ((array) ($r['blocks'] ?? []) as $b) {
                    $blocks[] = $b;
                }
                if (! ($r['ok'] ?? false)) {
                    return (string) ($r['error'] ?? "Tidak ada pengguna cocok dengan \"{$name}\".");
                }

                return "Hasil pencarian pengguna untuk \"{$name}\":";
            }
            $r = $this->tools->run('hitung_pengguna', ['role' => 'semua'], $admin);
            $d = (array) ($r['data'] ?? []);
            $grand = (array) ($d['grand_total'] ?? []);
            $items = [];
            foreach (['total' => 'Total akun', 'aktif' => 'Aktif', 'pending' => 'Menunggu approval', 'nonaktif' => 'Nonaktif'] as $k => $lbl) {
                if (isset($grand[$k])) {
                    $items[] = ['label' => $lbl, 'value' => number_format((int) $grand[$k])];
                }
            }
            foreach ((array) ($d['by_role'] ?? []) as $role => $c) {
                $items[] = [
                    'label' => Str::headline((string) $role).' aktif',
                    'value' => number_format((int) ($c['aktif'] ?? 0)),
                    'meta' => number_format((int) ($c['total'] ?? 0)).' total',
                ];
            }
            if ($items !== []) {
                $blocks[] = ['type' => 'stats', 'title' => 'Ringkasan pengguna', 'items' => $items];
                $blocks[] = ['type' => 'options', 'title' => 'Mau cari siapa?', 'items' => [
                    ['label' => 'Cari nama/NIM — ketik: /pengguna <nama>', 'message' => '/help'],
                    ['label' => 'Antrian approval', 'message' => 'siapa yang menunggu persetujuan akun'],
                ]];

                return 'Ini ringkasan data pengguna saat ini. Untuk mencari seseorang: `/pengguna <nama atau NIM>`';
            }

            return (string) ($r['error'] ?? 'Data pengguna tidak tersedia.');
        }
        if (preg_match('/^\/impor?t\b/', $q)) {
            $blocks[] = ['type' => 'links', 'items' => [
                ['label' => 'Buka halaman Impor Akun', 'href' => route('admin.users.import'), 'kind' => 'open'],
            ]];

            return 'Impor akun massal tetap lewat halaman Impor Akun di bawah. Kalau hanya ingin saya membaca isi file (CSV/teks/gambar) sebagai referensi, lampirkan lewat ikon klip 📎 di kolom pesan:';
        }

        if (Str::contains($q, ['status ruangan', 'daftar ruangan', 'ruangan sekarang', 'cek ruangan'])) {
            $r = $this->tools->run('ruangan_status', [], $admin);

            return $this->formatItemsReply($r, 'ruangan', fn ($i) => sprintf(
                '%s%s%s (%s)',
                $i['nama'] ?? '-',
                isset($i['kode']) && $i['kode'] ? ' '.$i['kode'] : '',
                isset($i['kapasitas']) && $i['kapasitas'] ? ' kap.'.$i['kapasitas'] : '',
                ($i['aktif'] ?? false) ? 'aktif' : 'nonaktif'
            ));
        }
        if (Str::contains($q, ['pengajuan menunggu', 'pengajuan ruangan', 'pengajuan pending', 'antrian pengajuan'])) {
            $r = $this->tools->run('pengajuan_pending', [], $admin);

            return $this->formatItemsReply($r, 'pengajuan', fn ($i) => sprintf(
                '%s — %s, %s %s (%s)',
                $i['pemohon'] ?? '-', $i['ruangan'] ?? '-', $i['tanggal'] ?? '', $i['jam'] ?? '', $i['status'] ?? ''
            ));
        }
        if (Str::contains($q, ['daftar petugas', 'petugas ruangan', 'petugas cs', 'petugas kebersihan'])) {
            $r = $this->tools->run('petugas_list', [], $admin);

            return $this->formatItemsReply($r, 'petugas', fn ($i) => sprintf(
                '%s (%s)%s', $i['nama'] ?? '-', $i['status'] ?? '-',
                ! empty($i['ruangan_akses']) ? ' — akses: '.implode(', ', (array) $i['ruangan_akses']) : ''
            ));
        }
        if (Str::contains($q, ['agenda kalender', 'agenda bulan ini', 'kalender agenda', 'jadwal libur', 'kalender sekarang'])) {
            $r = $this->tools->run('kalender_agenda', [], $admin);

            return $this->formatItemsReply($r, 'agenda', fn ($i) => sprintf(
                '%s — %s (%s)', $i['tanggal'] ?? '', $i['nama'] ?? '-', $i['jenis'] ?? ''
            ));
        }
        if (Str::contains($q, ['log tap', 'log absensi', 'tap terbaru', 'log tap terbaru'])) {
            $r = $this->tools->run('log_absensi_cari', ['limit' => 8], $admin);

            return $this->formatItemsReply($r, 'log', fn ($i) => is_string($i) ? $i : json_encode($i));
        }
        if (Str::contains($q, ['riwayat aksi', 'aksi yang pernah', 'laporan aksi ai', 'riwayat aksiku'])) {
            $r = $this->tools->run('riwayat_aksi_ai', ['limit' => 10], $admin);

            return $this->formatItemsReply($r, 'aksi', fn ($i) => sprintf(
                '%s — %s (%s)', $i['judul'] ?? '-', $i['status'] ?? '-', $i['waktu'] ?? ''
            ));
        }

        if ($this->looksLikeWriteRequest($q)) {
            $local = $this->localWriteRoute($message, $q, $admin, $proposals);
            if ($local !== null) {
                return $local;
            }
            if (! $admin->isLaboran()) {
                return 'Copilot hanya baca-saja, jadi tidak bisa mengubah data. Lakukan perubahan dari menu SIMALAS terkait (Absensi Mahasiswa atau Tim PBL), sesuai izin akunmu.';
            }
        }

        $filters = $this->parseFiltersFromMessage($message);
        $period = $this->parsePeriodFromMessage($message);
        $today = (bool) preg_match('/\bhari\s*ini\b/u', $q);
        $usePeriod = $period !== null && ! $today;

        if (preg_match('/\b(laporan|report)\b/u', $q)) {
            $rekor = str_contains($q, 'alfa') ? 'alfa' : (str_contains($q, 'tidak lengkap') ? 'tidak_lengkap' : 'terajin');

            return $this->absensiToolReply('laporan_absensi_periode', ($period ?? []) + $filters + ['rekor' => $rekor], $admin, $blocks);
        }
        if (Str::contains($q, ['tidak lengkap', 'belum pulang', 'hanya masuk'])) {
            return $usePeriod
                ? $this->absensiToolReply('laporan_absensi_periode', $period + $filters + ['rekor' => 'tidak_lengkap'], $admin, $blocks)
                : $this->absensiToolReply('daftar_absensi_hari_ini', $filters + ['status' => 'tidak_lengkap'], $admin, $blocks);
        }
        if (preg_match('/\balfa\b/u', $q) && ! preg_match('/\b(grafik|chart)\b/u', $q)) {
            return $usePeriod
                ? $this->absensiToolReply('laporan_absensi_periode', $period + $filters + ['rekor' => 'alfa'], $admin, $blocks)
                : $this->absensiToolReply('daftar_absensi_hari_ini', $filters + ['status' => 'alfa'], $admin, $blocks);
        }
        if (preg_match('/\b(ringkas|ringkasan|rekap)\b/u', $q) && preg_match('/\b(absen\w*|kehadiran)\b/u', $q)
            || Str::contains($q, ['absensi hari ini', 'kehadiran hari ini'])) {
            return $usePeriod
                ? $this->absensiToolReply('laporan_absensi_periode', $period + $filters, $admin, $blocks)
                : $this->absensiToolReply('ringkasan_absensi_hari_ini', $filters, $admin, $blocks);
        }

        // Dosen/KPS: hanya intent mahasiswa/absensi/laporan; tool operasional admin tidak pernah dipanggil.
        if (! $admin->isLaboran()) {
            if (Str::contains($q, ['ranking mahasiswa', 'mahasiswa terajin', 'peringkat'])) {
                return $this->absensiToolReply('laporan_absensi_periode', ($period ?? []) + $filters + ['rekor' => 'terajin'], $admin, $blocks);
            }
            if ($this->wantsChart($q)) {
                return $this->chartReply($message, $admin, $charts);
            }

            return $this->capabilitiesHelp($message);
        }

        // Hitung / total / berapa banyak — SEBELUM intent cari nama
        if ($this->looksLikeCountQuery($q)) {
            $role = str_contains($q, 'dosen') ? 'dosen'
                : (str_contains($q, 'mahasiswa') ? 'mahasiswa'
                    : (str_contains($q, 'laboran') ? 'laboran' : 'semua'));
            $r = $this->tools->run('hitung_pengguna', ['role' => $role], $admin);

            return $this->formatHitungPenggunaReply($r['data'] ?? [], $role);
        }

        // Cari pengguna / mahasiswa / dosen
        $looksLikeUserSearch =
            (preg_match('/\b(cari|carikan|tampilkan|lihat|data|profil|info|daftar)\b/u', $q)
                && preg_match('/\b(mahasiswa|dosen|user|akun|pengguna|orang)\b/u', $q))
            || (bool) preg_match('/\b(mahasiswa|dosen)\b.+\b[\p{L}]{3,}/u', $q);

        if ($looksLikeUserSearch) {
            $name = $this->normalizePersonQuery($this->extractSearchQuery($message));
            $role = str_contains($q, 'dosen') ? 'dosen' : (str_contains($q, 'mahasiswa') ? 'mahasiswa' : (str_contains($q, 'laboran') ? 'laboran' : 'semua'));
            // Tanpa nama → daftar role (asisten pribadi: "data dosen" / "list mahasiswa")
            if ($name === '' && in_array($role, ['dosen', 'mahasiswa', 'laboran'], true)) {
                $r = $this->tools->run('cari_pengguna', ['q' => 'list', 'role' => $role], $admin);

                return $this->formatCariPenggunaReply($r['data'] ?? [], 'daftar '.$role);
            }
            if ($name !== '') {
                $r = $this->tools->run('cari_pengguna', ['q' => $name, 'role' => $role], $admin);

                return $this->formatCariPenggunaReply($r['data'] ?? [], $name);
            }
        }

        // Nama orang eksplisit setelah "mahasiswa" / "data"
        if (preg_match('/(?:mahasiswa|data|cari|carikan)\s+(.+)$/iu', trim($message), $m)) {
            $name = trim(preg_replace('/[^\p{L}\p{N}\s\.\'\-]/u', '', $m[1]) ?? '');
            $name = trim(preg_replace('/^(mahasiswa|dosen|user|akun|pengguna|yang|bernama|atas nama)\s+/iu', '', $name) ?? '');
            if (Str::length($name) >= 3 && ! preg_match('/^(absensi|loker|mesin|antrian|saran|enroll|grafik|rekap|dosen|mahasiswa)/iu', $name)) {
                $r = $this->tools->run('cari_pengguna', ['q' => $name], $admin);

                return $this->formatCariPenggunaReply($r['data'] ?? [], $name);
            }
        }

        if (Str::contains($q, ['perhatian hari ini', 'perlu perhatian', 'yang perlu ditindak', 'attention today', 'prioritas hari ini', 'apa yang perlu'])) {
            $r = $this->tools->run('attention_today', [], $admin);

            return $this->formatAttentionReply($r['data'] ?? []);
        }
        if (Str::contains($q, ['ranking mahasiswa', 'mahasiswa terajin', 'peringkat'])) {
            return $this->absensiToolReply('laporan_absensi_periode', ($period ?? []) + $filters + ['rekor' => 'terajin'], $admin, $blocks);
        }
        if (preg_match('/\b(buat|draft)\b.*\bpengumuman\b/u', $q) || str_contains($q, 'pengumuman tentang')) {
            $topik = trim($message);
            if (preg_match('/pengumuman(?:\s+tentang|\s+soal|\s+:)?\s*(.+)$/iu', $message, $m)) {
                $topik = trim($m[1]);
            }
            $r = $this->tools->run('draft_pengumuman', ['topik' => $topik !== '' ? $topik : 'informasi operasional lab'], $admin);
            if (($r['kind'] ?? '') === 'proposal' && isset($r['proposal'])) {
                $proposals[] = $r['proposal'];

                return 'Draft pengumuman siap — cek kartu konfirmasi untuk menyimpan draft (belum publish).';
            }

            return (string) ($r['error'] ?? 'Tidak bisa membuat draft pengumuman.');
        }
        if (Str::contains($q, ['monitoring', 'snapshot live', 'pantau lab', 'aktivitas terbaru'])) {
            $r = $this->tools->run('monitoring_snapshot', [], $admin);

            return $this->formatMonitoringReply($r['data'] ?? []);
        }
        if (Str::contains($q, ['status loker', 'papan loker', 'slot loker', 'board loker'])) {
            return 'SIMALAS ini hanya menangani absensi. Loker bukan bagian dari aplikasi ini.';
        }
        if (Str::contains($q, ['pengumuman', 'list announcement'])) {
            $r = $this->tools->run('list_announcements', [], $admin);

            return $this->formatAnnouncementsReply($r['data'] ?? []);
        }
        if (Str::contains($q, ['sync fingerprint', 'laporan sync', 'fingerprint sync'])) {
            $r = $this->tools->run('fingerprint_sync_report', [], $admin);

            return $this->formatSyncReply($r['data'] ?? []);
        }

        if ($this->wantsChart($q)) {
            return $this->chartReply($message, $admin, $charts);
        }
        if (Str::contains($q, ['grafik loker', 'chart loker', 'tren loker', 'data grafik loker', 'grafik line loker'])) {
            return 'SIMALAS ini hanya menangani absensi. Grafik loker tidak tersedia.';
        }
        if (Str::contains($q, ['status mesin', 'mesin offline', 'mesin online', 'mesin iot', 'daftar mesin', 'mesin terdaftar', 'laporkan mesin', 'semua mesin', 'berapa mesin'])) {
            $r = $this->tools->run('mesin_status', [], $admin);

            return $this->formatMesinReply($r['data'] ?? []);
        }
        if (Str::contains($q, ['belum enroll', 'enroll', 'sidik jari'])) {
            $r = $this->tools->run('enroll_pending', ['limit' => 10], $admin);

            return $this->formatEnrollReply($r['data'] ?? []);
        }
        if (Str::contains($q, ['pending approval', 'antrian approval', 'siap di-approve', 'siap approve', 'menunggu persetujuan'])) {
            $r = $this->tools->run('list_pending_approvals', ['limit' => 10], $admin);

            return $this->formatPendingReply($r['data'] ?? []);
        }
        // Bulk / setujui semua
        if (
            (preg_match('/\b(setujui|approve|persetujui)\b/u', $q) && preg_match('/\b(semua|banyak|semuanya|all|antrian)\b/u', $q))
            || str_contains($q, 'bulk approve')
            || str_contains($q, 'setujui semua')
        ) {
            $r = $this->tools->run('bulk_approve_ready', ['all' => true], $admin);
            if (($r['kind'] ?? '') === 'proposal' && isset($r['proposal'])) {
                $proposals[] = $r['proposal'];
                $n = (int) ($r['proposal']['count'] ?? 0);

                return $n > 0
                    ? "Siap. Proposal menyetujui {$n} akun sudah dibuat — klik konfirmasi di kartu di bawah (HITL)."
                    : 'Tidak ada akun siap approval.';
            }

            return (string) ($r['error'] ?? 'Tidak bisa membuat proposal bulk approve.');
        }
        // Setujui satu: setujui user #95 / setujui akun 95
        if (preg_match('/\b(setujui|approve)\b.{0,40}#?\s*(\d{1,6})\b/u', $q, $m)) {
            $r = $this->tools->run('approve_user', ['user_id' => (int) $m[2]], $admin);
            if (($r['kind'] ?? '') === 'proposal' && isset($r['proposal'])) {
                $proposals[] = $r['proposal'];

                return 'Proposal setujui akun #'.$m[2].' siap — konfirmasi di kartu di bawah.';
            }

            return (string) ($r['error'] ?? 'Tidak bisa membuat proposal approve.');
        }
        if (Str::contains($q, ['pbl', 'readiness', 'kesiapan'])) {
            $r = $this->tools->run('pbl_readiness', [], $admin);
            $ok = (bool) data_get($r, 'data.ok', false);

            return $ok
                ? 'Checklist PBL terlihat siap untuk absensi Kamis (berdasarkan data sistem).'
                : 'Checklist PBL masih perlu perhatian. Buka menu Proyek PBL untuk detail item yang belum lengkap.';
        }
        if (Str::contains($q, ['ringkas antrian', 'ringkasan', 'ops summary', 'operasional hari ini', 'dashboard'])) {
            return $this->formatOpsReply($admin);
        }

        // Di luar konteks / tidak dikenali → jangan dump ringkasan acak
        return $this->capabilitiesHelp($message);
    }

    /**
     * Jalankan tool absensi/laporan lalu susun teks singkat dari angka backend.
     *
     * @param  array<string, mixed>  $args
     * @param  list<array<string, mixed>>  $blocks
     */
    private function absensiToolReply(string $tool, array $args, User $user, array &$blocks): string
    {
        $r = $this->tools->run($tool, $args, $user);
        if (! ($r['ok'] ?? false)) {
            return str_starts_with((string) ($r['error'] ?? ''), 'Tidak punya izin')
                ? 'Akun kamu tidak punya izin untuk data ini.'
                : (string) ($r['error'] ?? 'Data belum bisa diambil.');
        }
        array_push($blocks, ...($r['blocks'] ?? []));
        $d = $r['data'] ?? [];

        if (($d['perlu_klarifikasi'] ?? '') === 'periode') {
            return 'Laporan untuk periode apa? Pilih di bawah, atau tulis rentang tanggal (mis. 2026-09-01 s/d 2026-09-30).';
        }
        if (($d['perlu_klarifikasi'] ?? '') === 'kata_kunci') {
            return (string) $d['pertanyaan'];
        }

        $filter = (string) ($d['filter'] ?? '');
        $scope = $filter !== '' && ! str_starts_with($filter, 'semua') ? ' ('.$filter.')' : '';

        return match ($tool) {
            'ringkasan_absensi_hari_ini' => (function () use ($d, $scope, $user): string {
                $s = $d['ringkasan'];
                if ((int) $s['mahasiswa_aktif'] === 0) {
                    return 'Belum ada mahasiswa aktif yang cocok untuk rekap hari ini'.$scope.'.';
                }

                return 'Rekap absensi hari ini'.$scope.' dari halaman '.CopilotAbsensiData::rekapPage($user).'.'
                    .((int) $s['belum_tap'] > 0 ? ' Angka masih bisa berubah selama masih ada yang belum tap.' : '');
            })(),
            'daftar_absensi_hari_ini' => (function () use ($d, $scope): string {
                $label = match ($d['status']) {
                    'alfa' => 'alfa',
                    'hadir' => 'sudah tap masuk',
                    'all' => 'tercatat di rekap',
                    default => 'tidak lengkap atau hanya masuk',
                };
                if ((int) $d['jumlah'] === 0) {
                    return 'Tidak ada mahasiswa '.$label.' hari ini'.$scope.'.';
                }

                return 'Mahasiswa '.$label.' hari ini'.$scope.'.';
            })(),
            'laporan_absensi_periode' => (function () use ($d, $scope, $user): string {
                $s = $d['ringkasan'];
                $rentang = Str::lower($d['periode']).$scope.' ('.$d['tanggal_mulai'].' s/d '.$d['tanggal_akhir'].')';
                if ((int) $s['hadir_lengkap'] + (int) $s['tidak_lengkap'] + (int) $s['alfa'] === 0) {
                    return 'Belum ada data absensi untuk '.$rentang.'.';
                }
                return 'Laporan '.$rentang.', sumber menu '.CopilotAbsensiData::laporanPage($user).'.';
            })(),
            'cari_mahasiswa' => (function () use ($d): string {
                if ((int) ($d['jumlah'] ?? 0) > 0) {
                    return 'Hasil pencarian mahasiswa.';
                }
                if (! empty($d['di_luar_scope'])) {
                    return 'Akun dengan kata kunci itu ada, tapi bukan mahasiswa bimbingan Anda — datanya tidak saya tampilkan. Periksa kembali ejaan atau tanyakan mahasiswa di tim/kelas Anda.';
                }
                $saran = (array) ($d['saran_mirip'] ?? []);
                if ($saran !== []) {
                    return 'Tidak ada persisnya — mungkin maksud Anda: '.implode(', ', array_slice($saran, 0, 3)).'?';
                }

                return 'Tidak ada mahasiswa yang cocok. Coba nama yang lebih pendek atau NIM lengkap.';
            })(),
            default => '',
        };
    }

    /**
     * Format hasil tool read menjadi jawaban bullet singkat.
     *
     * @param  array<string, mixed>  $r
     */
    private function formatItemsReply(array $r, string $label, callable $fmt): string
    {
        if (! ($r['ok'] ?? false)) {
            return (string) ($r['error'] ?? 'Data tidak bisa diambil.');
        }
        $items = collect((array) data_get($r, 'data.items', []))->map($fmt)->filter()->take(12)->values();
        if ($items->isEmpty()) {
            return "Tidak ada {$label} saat ini.";
        }
        $total = (int) data_get($r, 'data.total', $items->count());
        $more = $total > $items->count() ? "\nMenampilkan {$items->count()} dari {$total} — persempit lewat menu terkait untuk detail." : '';

        return "Ditemukan {$total} {$label}:\n".$items->map(fn ($s) => "- {$s}")->implode("\n").$more;
    }

    /**
     * Routing fallback lokal untuk permintaan tulis: selesaikan akun/ruangan
     * lalu buat proposal (tidak ada eksekusi langsung — HITL tetap berlaku).
     *
     * @param  list<array<string, mixed>>  $proposals
     */
    private function localWriteRoute(string $message, string $q, User $admin, array &$proposals): ?string
    {
        $propose = function (string $tool, array $args) use ($admin, &$proposals): ?string {
            $r = $this->tools->run($tool, $args, $admin);
            if (($r['kind'] ?? '') === 'proposal' && isset($r['proposal'])) {
                $proposals[] = $r['proposal'];

                return 'Proposal siap — klik konfirmasi pada kartu untuk menjalankan.';
            }

            return (string) ($r['error'] ?? 'Permintaan tidak bisa dijadikan proposal.');
        };

        $resolveUserId = function (string $ref): ?int {
            $ref = trim($ref, " \t\n\r#.");
            if ($ref === '') {
                return null;
            }
            if (is_numeric($ref)) {
                return (int) $ref;
            }
            $u = User::query()->where('nama_lengkap', 'like', "%{$ref}%")->orWhere('nitk_nidn', $ref)->first();

            return $u?->id;
        };

        // nonaktifkan/aktifkan akun X | #12 | NIM
        if (preg_match('/\b(nonaktifkan|aktifkan|matikan|blokir)\b.{0,40}?(?:akun|user|mahasiswa)?\s*#?([A-Za-z0-9 .\-]{2,40})$/u', $message, $m)) {
            $id = $resolveUserId($m[2]);
            if (! $id) {
                return 'Akun tidak ditemukan — sebutkan ID akun (#12) atau NIM yang tepat.';
            }
            $off = in_array(Str::lower($m[1]), ['nonaktifkan', 'matikan', 'blokir'], true);

            return $propose('toggle_user_active', ['user_id' => $id, 'set_active' => ! $off]);
        }
        // tolak/reject akun #12
        if (preg_match('/\b(tolak|reject)\b.{0,40}?(?:akun|user|mahasiswa)?\s*#?(\d{1,6})\b/u', $message, $m)) {
            return $propose('reject_user', ['user_id' => (int) $m[2]]);
        }
        // daftarkan/tambah ruangan X
        if (preg_match('/\b(daftarkan|tambah|buat)\b.{0,20}?\bruangan\b\s+(?:baru\s+)?(?:bernama\s+|bern?ama\s+)?["\']?([A-Za-z0-9 .\-]{2,60})["\']?\s*$/u', $message, $m)) {
            return $propose('create_ruangan', ['nama' => trim($m[2])]);
        }
        if (Str::contains($q, ['sync fingerprint'])) {
            return 'Sinkronisasi burst fingerprint belum dibuka untuk asisten — gunakan menu Fingerprint.';
        }

        return null;
    }

    /**
     * /help — ringkasan kemampuan + tombol opsi yang bisa diklik.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function helpReply(User $admin, array &$blocks): string
    {
        $opts = $admin->isLaboran()
            ? [
                ['label' => 'Rekap absensi', 'message' => '/menu rekap'],
                ['label' => 'Akun & approval', 'message' => '/menu akun'],
                ['label' => 'Ruangan & pengajuan', 'message' => '/menu ruangan'],
                ['label' => 'Perangkat mesin', 'message' => '/menu perangkat'],
                ['label' => 'Laporan & ekspor', 'message' => '/menu laporan'],
                ['label' => 'Aksi yang bisa saya jalankan', 'message' => '/menu aksi'],
            ]
            : [
                ['label' => 'Rekap absensi', 'message' => '/menu rekap'],
                ['label' => 'Tim PBL saya', 'message' => 'tampilkan tim PBL yang saya bimbing'],
                ['label' => 'Mahasiswa bimbingan', 'message' => 'daftar mahasiswa bimbingan saya'],
                ['label' => 'Pengajuan ruangan', 'message' => 'status pengajuan ruangan tim saya'],
                ['label' => 'Cari mahasiswa', 'message' => '/menu akun'],
                ['label' => 'Laporan & ekspor', 'message' => '/menu laporan'],
            ];
        $blocks[] = ['type' => 'options', 'title' => 'Pilih untuk langsung mencoba', 'items' => $opts];

        $bisa = $admin->isLaboran()
            ? "- **Baca data**: rekap & grafik absensi, cari mahasiswa/pengguna, status mesin, ruangan, pengajuan pending, petugas, agenda kalender, log tap.\n"
            ."- **Aksi nyata (butuh konfirmasi Anda)**: setujui/tolak akun, aktif/nonaktifkan akun, unlock login, daftarkan ruangan, buat pengumuman, nyalakan/matikan mesin, koreksi absensi, batalkan alfa.\n"
            ."- **Laporan**: rekap periode + tautan unduh Excel/Word/cetak.\n"
            : "- **Baca data**: rekap & grafik absensi mahasiswa bimbingan, daftar & cari mahasiswa, siapa yang alfa, log tap, tim PBL & anggotanya, status pengajuan ruangan tim, pengumuman, agenda kalender.\n"
            ."- **Laporan**: rekap periode + tautan unduh Excel/Word.\n";

        return "Ini yang bisa saya bantu:\n".$bisa
            ."\nSetiap aksi tulis saya siapkan sebagai **kartu konfirmasi** — Anda yang memutuskan jalankan/batal.\n"
            ."Ketik `/help` kapan saja untuk melihat daftar ini lagi.";
    }

    /**
     * /menu <topik> — jawaban langsung + opsi polling yang bisa diklik.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function menuReply(string $menu, User $admin, array &$blocks): string
    {
        $laboran = $admin->isLaboran();
        $sharedMap = [
            'rekap' => [
                'Pilih rekap yang mau dilihat:',
                [
                    ['label' => 'Hari ini', 'message' => 'ringkas absensi hari ini'],
                    ['label' => 'Minggu ini', 'message' => 'rekap absensi minggu ini'],
                    ['label' => 'Bulan ini', 'message' => 'rekap absensi bulan ini'],
                    ['label' => 'Yang tidak lengkap hari ini', 'message' => 'siapa yang tidak lengkap hari ini'],
                    ['label' => 'Grafik tren', 'message' => 'grafik absensi minggu ini'],
                ],
            ],
            'laporan' => [
                'Laporan bisa saya susun lalu siapkan tombol unduh:',
                [
                    ['label' => 'Laporan bulan ini', 'message' => 'buat laporan absensi bulan ini'],
                    ['label' => 'Laporan minggu ini', 'message' => 'buat laporan absensi minggu ini'],
                    ['label' => 'Mahasiswa paling rajin', 'message' => 'ranking mahasiswa terajin bulan ini'],
                    ['label' => 'Daftar alfa', 'message' => 'daftar mahasiswa alfa minggu ini'],
                ],
            ],
        ];
        $map = $laboran ? $sharedMap + [
            '' => [
                'Pilih topik menu:',
                [
                    ['label' => 'Rekap absensi', 'message' => '/menu rekap'],
                    ['label' => 'Akun & approval', 'message' => '/menu akun'],
                    ['label' => 'Ruangan & pengajuan', 'message' => '/menu ruangan'],
                    ['label' => 'Perangkat mesin', 'message' => '/menu perangkat'],
                    ['label' => 'Laporan & ekspor', 'message' => '/menu laporan'],
                    ['label' => 'Aksi konfirmasi', 'message' => '/menu aksi'],
                ],
            ],
            'akun' => [
                'Tentang akun & pengguna:',
                [
                    ['label' => 'Antrian approval', 'message' => 'siapa yang menunggu persetujuan akun'],
                    ['label' => 'Setujui semua yang siap', 'message' => 'setujui semua akun yang siap'],
                    ['label' => 'Cari mahasiswa', 'message' => 'cari mahasiswa'],
                    ['label' => 'Setujui akun tertentu', 'message' => '/help'],
                ],
            ],
            'ruangan' => [
                'Tentang ruangan:',
                [
                    ['label' => 'Status semua ruangan', 'message' => 'status ruangan sekarang'],
                    ['label' => 'Pengajuan menunggu', 'message' => 'pengajuan ruangan yang menunggu'],
                    ['label' => 'Daftar petugas', 'message' => 'daftar petugas ruangan'],
                    ['label' => 'Agenda kalender', 'message' => 'agenda kalender bulan ini'],
                ],
            ],
            'perangkat' => [
                'Tentang perangkat:',
                [
                    ['label' => 'Status mesin sekarang', 'message' => 'status mesin IoT sekarang'],
                    ['label' => 'Belum enroll fingerprint', 'message' => 'siapa yang belum enroll fingerprint'],
                    ['label' => 'Log tap terbaru', 'message' => 'log absensi terbaru'],
                ],
            ],
            'aksi' => [
                'Aksi yang bisa saya siapkan (Anda tetap yang konfirmasi):',
                [
                    ['label' => 'Setujui akun — tulis: setujui akun #ID', 'message' => 'siapa yang menunggu persetujuan akun'],
                    ['label' => 'Daftarkan ruangan — tulis: daftarkan ruangan <nama>', 'message' => 'daftarkan ruangan '],
                    ['label' => 'Buat pengumuman — tulis: buat pengumuman tentang <topik>', 'message' => 'buat pengumuman tentang '],
                    ['label' => 'Riwayat aksi saya', 'message' => 'riwayat aksi yang pernah kamu jalankan'],
                ],
            ],
        ] : $sharedMap + [
            '' => [
                'Pilih topik menu:',
                [
                    ['label' => 'Rekap absensi', 'message' => '/menu rekap'],
                    ['label' => 'Mahasiswa bimbingan', 'message' => '/menu akun'],
                    ['label' => 'Tim PBL', 'message' => '/menu pbl'],
                    ['label' => 'Pengajuan ruangan', 'message' => '/menu ruangan'],
                    ['label' => 'Laporan & ekspor', 'message' => '/menu laporan'],
                ],
            ],
            'akun' => [
                'Tentang mahasiswa bimbingan:',
                [
                    ['label' => 'Semua mahasiswa bimbingan', 'message' => 'daftar mahasiswa bimbingan saya'],
                    ['label' => 'Cari mahasiswa', 'message' => 'cari mahasiswa'],
                    ['label' => 'Paling sering alfa', 'message' => 'daftar mahasiswa alfa minggu ini'],
                ],
            ],
            'pbl' => [
                'Tentang tim PBL yang Anda bimbing:',
                [
                    ['label' => 'Daftar tim PBL saya', 'message' => 'tampilkan tim PBL yang saya bimbing'],
                    ['label' => 'Anggota tim tertentu', 'message' => 'tampilkan anggota tim '],
                    ['label' => 'Pengajuan ruangan tim', 'message' => 'status pengajuan ruangan tim saya'],
                ],
            ],
            'ruangan' => [
                'Tentang ruangan & pengajuan tim:',
                [
                    ['label' => 'Status pengajuan tim saya', 'message' => 'status pengajuan ruangan tim saya'],
                    ['label' => 'Agenda kalender', 'message' => 'agenda kalender bulan ini'],
                ],
            ],
        ];
        $item = $map[$menu] ?? null;
        if ($item === null) {
            return $this->helpReply($admin, $blocks);
        }
        [$text, $opts] = $item;
        $blocks[] = ['type' => 'options', 'title' => 'Klik salah satu', 'items' => $opts];

        return $text;
    }

    private function looksLikeWriteRequest(string $q): bool
    {
        return (bool) preg_match(
            '/\b(setujui|approve|tolak|reject|hapus|delete|aktifkan|nonaktifkan|ubah|ganti|edit|koreksi|batalkan|reset|toggle|unlock|buka\s+kunci|kirim\s+email|publish|terbitkan|balas\s+saran)\b'
            .'|\b(buat|draft)\b.*\bpengumuman\b|\bsync\s+fingerprint\b/u',
            $q
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseFiltersFromMessage(string $message): array
    {
        $args = $this->parseChartArgsFromMessage($message);

        return array_filter([
            'kelas' => (string) ($args['kelas'] ?? ''),
            'tim' => (string) ($args['tim'] ?? ''),
        ], fn ($v) => $v !== '');
    }

    /**
     * @return array<string, string>|null
     */
    private function parsePeriodFromMessage(string $message): ?array
    {
        $q = Str::lower($message);
        if (preg_match('/(\d{4}-\d{2}-\d{2}).{0,24}?(\d{4}-\d{2}-\d{2})/u', $message, $m)) {
            return ['periode' => 'custom', 'tanggal_mulai' => $m[1], 'tanggal_akhir' => $m[2]];
        }
        foreach (['minggu ini' => 'minggu_ini', 'minggu lalu' => 'minggu_lalu', 'bulan ini' => 'bulan_ini', 'bulan lalu' => 'bulan_lalu'] as $phrase => $periode) {
            if (str_contains($q, $phrase)) {
                return ['periode' => $periode];
            }
        }
        $bulan = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
            'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];
        if (preg_match('/\b('.implode('|', array_keys($bulan)).')\b(?:\s+(\d{4}))?/u', $q, $m)) {
            $year = isset($m[2]) ? (int) $m[2] : (int) now()->year;

            return ['periode' => 'tahun_bulan', 'bulan' => sprintf('%04d-%02d', $year, $bulan[$m[1]])];
        }

        return null;
    }

    private function extractSearchQuery(string $message): string
    {
        $clean = trim($message);

        // Prioritas: setelah kata role (mahasiswa/dosen/user/…)
        if (preg_match('/\b(?:mahasiswa|dosen|user|akun|pengguna)\s+(.+)$/iu', $clean, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/^(yang|bernama|atas nama)\s+/iu', '', $name) ?? $name;

            return trim(preg_replace('/[^\p{L}\p{N}\s\.\'\-]/u', '', $name) ?? '');
        }

        // Fallback: setelah kata aksi (cari/carikan/tampilkan/data/…)
        if (preg_match('/\b(?:carikan|cari|tampilkan|lihat|data|profil|info|daftar)\s+(.+)$/iu', $clean, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/^(mahasiswa|dosen|user|akun|pengguna|yang|bernama|atas nama|data)\s+/iu', '', $name) ?? $name;

            return trim(preg_replace('/[^\p{L}\p{N}\s\.\'\-]/u', '', $name) ?? '');
        }

        return '';
    }

    /** Buang sisa yang hanya kata role / filler, bukan nama orang. */
    private function normalizePersonQuery(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        if (preg_match('/^(mahasiswa|dosen|laboran|user|akun|pengguna|semua|list|daftar)$/iu', $name)) {
            return '';
        }
        // Hindari frase kuantitas yang salah dianggap nama
        if (preg_match('/\b(berapa|banyak|jumlah|total|ada berapa)\b/iu', $name)) {
            return '';
        }

        return $name;
    }

    private function looksLikeCountQuery(string $q): bool
    {
        $hasCount = (bool) preg_match('/\b(berapa|berapa banyak|jumlah|total|seberapa banyak|hitung|count)\b/u', $q)
            || str_contains($q, 'ada berapa')
            || str_contains($q, 'berapa banyak')
            || str_contains($q, 'berapa total')
            || str_contains($q, 'total data')
            || str_contains($q, 'banyak data')
            || str_contains($q, 'banyak mahasiswa')
            || str_contains($q, 'banyak dosen')
            || str_contains($q, 'berapa mahasiswa')
            || str_contains($q, 'berapa dosen');

        if (! $hasCount) {
            return false;
        }

        // Harus menyentuh konteks user/lab, atau murni "berapa banyak mahasiswa"
        return (bool) preg_match('/\b(mahasiswa|dosen|laboran|user|akun|pengguna|data)\b/u', $q)
            || str_contains($q, 'berapa banyak');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatHitungPenggunaReply(array $data, string $role): string
    {
        $by = $data['by_role'] ?? [];
        $at = (string) ($data['generated_at'] ?? now()->toDateTimeString());

        if ($role !== 'semua' && isset($by[$role])) {
            $row = $by[$role];
            $label = ucfirst($role);

            return "Total data {$label} (per {$at}):\n"
                .'- Total: '.($row['total'] ?? 0)."\n"
                .'- Aktif: '.($row['aktif'] ?? 0)."\n"
                .'- Pending: '.($row['pending'] ?? 0)."\n"
                .'- Nonaktif: '.($row['nonaktif'] ?? 0);
        }

        $lines = ["Ringkasan jumlah pengguna (per {$at}):"];
        foreach (['mahasiswa', 'dosen', 'laboran', 'admin'] as $r) {
            if (! isset($by[$r])) {
                continue;
            }
            $lines[] = '- '.ucfirst($r).': '.($by[$r]['total'] ?? 0)
                .' (aktif '.($by[$r]['aktif'] ?? 0)
                .', pending '.($by[$r]['pending'] ?? 0).')';
        }
        $g = $data['grand_total'] ?? [];
        $lines[] = '- Grand total: '.($g['total'] ?? 0);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatCariPenggunaReply(array $data, string $q): string
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            return "Tidak menemukan pengguna yang cocok dengan \"{$q}\".\n\n"
                ."Coba nama lebih pendek, NITK/NIDN, atau email.\n"
                .'Contoh: `cari mahasiswa Aldo` atau `data user 2201...`';
        }

        $lines = ["Hasil pencarian \"{$q}\" (".count($items)."):"];
        foreach ($items as $i => $row) {
            $n = $i + 1;
            $lines[] = "{$n}. #{$row['id']} {$row['nama']} · {$row['role']}"
                .($row['kelas'] ? " · kelas {$row['kelas']}" : '')
                .($row['tim'] ? " · tim {$row['tim']}" : '')
                .' · status: '.($row['approval_status'] ?? '-')
                .($row['fingerprint_enrolled'] === null ? '' : ($row['fingerprint_enrolled'] ? ' · fingerprint OK' : ' · belum enroll'));
        }
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $data */
    private function formatMesinReply(array $data): string
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            return 'Belum ada data mesin IoT.';
        }
        $lines = ['Status mesin IoT:'];
        foreach ($items as $m) {
            $state = ! ($m['is_active'] ?? false) ? 'nonaktif' : (($m['online'] ?? false) ? 'online' : 'offline');
            $lines[] = "- #{$m['id']} {$m['nama']} ({$m['id_mesin']}) · {$m['tipe']} · {$state}";
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $data */
    private function formatEnrollReply(array $data): string
    {
        $total = (int) ($data['total'] ?? 0);
        $sample = $data['sample'] ?? [];
        $lines = ["Mahasiswa aktif belum enroll fingerprint: {$total}"];
        foreach ($sample as $row) {
            $lines[] = "- #{$row['id']} {$row['nama']}".($row['kelas'] ? " · {$row['kelas']}" : '');
        }
        if ($total > count($sample)) {
            $lines[] = '… dan lainnya. Buka menu Fingerprint untuk daftar lengkap.';
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $data */
    private function formatPendingReply(array $data): string
    {
        $total = (int) ($data['total'] ?? 0);
        $items = $data['items'] ?? [];
        $lines = ["Akun siap approval: {$total}"];
        foreach ($items as $row) {
            $lines[] = "- #{$row['id']} {$row['nama']} · {$row['role']}";
        }
        if ($total === 0) {
            return "Tidak ada akun di antrian siap approval saat ini.";
        }
        $lines[] = "\nPersetujuan akun dilakukan dari menu Manajemen User.";

        return implode("\n", $lines);
    }

    private function formatOpsReply(User $admin): string
    {
        $r = $this->tools->run('ops_summary', [], $admin);
        $data = $r['data'] ?? [];
        $ops = $data['ops'] ?? [];
        $mesin = $data['mesin'] ?? [];
        $minggu = $data['minggu_ini'] ?? [];

        $at = (string) ($data['generated_at'] ?? now()->toDateTimeString());

        return "Ringkasan operasional ({$at}):\n"
            .'- Pending approval: '.($ops['pending_approval'] ?? 0)."\n"
            .'- Saran baru: '.($ops['suggestions_baru'] ?? 0)."\n"
            .'- Belum enroll: '.($ops['enroll_belum'] ?? 0)."\n"
            .'- Mesin online/aktif: '.($mesin['online'] ?? 0).'/'.($mesin['aktif'] ?? 0)."\n"
            .'- Tap minggu ini: '.($minggu['tap_absensi'] ?? 0);
    }

    private function wantsChart(string $q): bool
    {
        return Str::contains($q, ['grafik absen', 'chart absen', 'tren absen', 'tampilkan grafik absen', 'grafik tap', 'data grafik absen', 'grafik kehadiran', 'grafik line absen']);
    }

    /**
     * @param  list<array<string, mixed>>  $charts
     */
    private function chartReply(string $message, User $user, array &$charts): string
    {
        $r = $this->tools->run('chart_absensi', $this->parseChartArgsFromMessage($message), $user);
        if (! ($r['ok'] ?? false)) {
            return 'Akun kamu tidak punya izin untuk data ini.';
        }
        if (isset($r['chart'])) {
            $this->pushChart($charts, $r['chart']);
        }
        $total = (int) data_get($r, 'data.total', 0);
        $periode = (string) data_get($r, 'data.periode', 'periode');
        $filter = trim((string) data_get($r, 'data.filter', ''));
        $note = data_get($r, 'data.catatan');

        return 'Grafik line tap absensi periode '.$periode
            .($filter !== '' ? ' ('.$filter.')' : '')
            .' — total '.$total.' tap.'
            .($note ? "\n".$note : '');
    }

    private function capabilitiesHelp(string $message): string
    {
        $preview = Str::limit(trim($message), 80, '…');

        return "Belum bisa menjawab \"{$preview}\" di mode lokal. Coba salah satu:\n"
            ."- `ringkas absensi hari ini`\n"
            ."- `siapa yang tidak lengkap hari ini`\n"
            ."- `buat laporan absensi bulan ini`\n"
            ."- `cari mahasiswa <nama atau NIM>`";
    }

    /** @param array<string, mixed> $data */
    private function formatAttentionReply(array $data): string
    {
        $items = $data['prioritas'] ?? [];
        $at = (string) ($data['generated_at'] ?? now()->toDateTimeString());
        if ($items === []) {
            return "Perhatian hari ini ({$at}): tidak ada item prioritas. Lab relatif tenang.";
        }
        $lines = ["Yang perlu perhatian ({$at}):"];
        foreach ($items as $i => $row) {
            $lines[] = ($i + 1).'. ['.($row['level'] ?? '-').'] '.($row['teks'] ?? '');
        }
        $act = $data['aktivitas_hari_ini'] ?? [];
        $lines[] = "\nTap absensi hari ini: ".($act['tap_absensi'] ?? 0).'.';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $data */
    private function formatMonitoringReply(array $data): string
    {
        $mesin = $data['mesin'] ?? [];
        $hari = $data['hari_ini'] ?? [];
        $lines = [
            'Snapshot monitoring ('.($data['generated_at'] ?? now()).'):',
            '- Mesin online/aktif: '.($mesin['online'] ?? 0).'/'.($mesin['aktif'] ?? 0),
            '- Tap absensi hari ini: '.($hari['tap_absensi'] ?? 0),
            '',
            'Aktivitas terbaru:',
        ];
        foreach (array_slice($data['aktivitas_terbaru'] ?? [], 0, 6) as $a) {
            $lines[] = '- '.($a['waktu'] ?? '-').' · '.($a['jenis'] ?? '').' · '.($a['nama'] ?? '-');
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $data */
    private function formatAnnouncementsReply(array $data): string
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            return 'Belum ada pengumuman.';
        }
        $lines = ['Pengumuman terbaru:'];
        foreach ($items as $a) {
            $lines[] = '- #'.$a['id'].' '.($a['aktif'] ? '[aktif]' : '[draft]').' '.$a['judul'].' → '.$a['target'];
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $data */
    private function formatSyncReply(array $data): string
    {
        $s = $data['stats'] ?? [];
        $lines = [
            'Laporan sync fingerprint:',
            '- Total log: '.($s['total'] ?? 0),
            '- Hari ini: '.($s['today'] ?? 0).' (pull: '.($s['pull_today'] ?? 0).', templates: '.($s['templates_today'] ?? 0).')',
        ];
        foreach (array_slice($data['items'] ?? [], 0, 5) as $row) {
            $lines[] = '- '.($row['waktu'] ?? '-').' · '.$row['mesin'].' · '.$row['aksi'].' · '.(($row['sukses'] ?? false) ? 'OK' : 'gagal');
        }

        return implode("\n", $lines);
    }
}
