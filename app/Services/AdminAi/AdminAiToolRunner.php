<?php

namespace App\Services\AdminAi;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiKalenderLibur;
use App\Models\AbsensiTidakLengkap;
use App\Models\AiActionProposal;
use App\Models\Announcement;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\Ruangan;
use App\Models\RuanganPengajuan;
use App\Models\RuanganPetugas;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\DosenKelasScope;
use App\Support\FingerprintSyncLogService;
use App\Support\PblDataReadiness;
use App\Support\PendingAccountApproval;
use App\Support\PermissionRegistry;
use App\Support\RuanganBookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminAiToolRunner
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, kind: string, data?: mixed, proposal?: array<string, mixed>, error?: string, chart?: array<string, mixed>, blocks?: list<array<string, mixed>>}
     */
    public function run(string $tool, array $args, User $admin): array
    {
        $meta = AdminAiToolRegistry::get($tool);
        if (! $meta) {
            return ['ok' => false, 'kind' => 'error', 'error' => 'Tool tidak dikenal: '.$tool];
        }

        $perm = AdminAiToolRegistry::permFor($tool, (string) $admin->role);
        if (! $admin->isLaboran() && ! in_array($tool, AdminAiToolRegistry::copilotToolsFor((string) $admin->role), true)) {
            $perm = null;
        }
        if ($perm === null) {
            return ['ok' => false, 'kind' => 'error', 'error' => 'Tidak punya izin untuk tool ini.'];
        }
        [$menu, $action] = $perm;
        if (! PermissionRegistry::can($admin->role, $menu, $action)) {
            return ['ok' => false, 'kind' => 'error', 'error' => "Tidak punya izin {$menu}.{$action}"];
        }

        if ($meta['kind'] === 'write') {
            if (AdminAiToolRegistry::READ_ONLY && ! AdminAiToolRegistry::writeEnabled($tool)) {
                return ['ok' => false, 'kind' => 'error', 'error' => 'Copilot hanya baca-saja. Lakukan perubahan dari menu SIMALAS terkait.'];
            }

            return $this->propose($tool, $meta, $args, $admin);
        }

        $absensi = app(CopilotAbsensiData::class);
        $withBlocks = match ($tool) {
            'ringkasan_absensi_hari_ini' => $absensi->ringkasanHariIni($args, $admin),
            'daftar_absensi_hari_ini' => $absensi->daftarHariIni($args, $admin),
            'laporan_absensi_periode' => $absensi->laporanPeriode($args, $admin),
            'cari_mahasiswa' => $absensi->cariMahasiswa($args, $admin),
            'tim_pbl_saya' => $absensi->timPblSaya($args, $admin),
            'mahasiswa_bimbingan' => $absensi->mahasiswaBimbingan($args, $admin),
            default => null,
        };
        if ($withBlocks !== null) {
            return ['ok' => true, 'kind' => 'read', 'data' => $withBlocks['provider'], 'blocks' => $withBlocks['blocks']];
        }

        return match ($tool) {
            'ops_summary' => $this->okRead((new AdminAiContextBuilder)->snapshot()),
            'mesin_status' => $this->okRead($this->mesinStatus()),
            'enroll_pending' => $this->okRead($this->enrollPending((int) ($args['limit'] ?? 15))),
            'rekap_absensi' => $this->okRead($this->rekapAbsensi($args)),
            'chart_absensi' => $this->okChart($this->chartSeries('absensi', $args), 'absensi'),
            'list_pending_approvals' => $this->okRead($this->listPendingApprovals((int) ($args['limit'] ?? 15))),
            'list_suggestions' => $this->okRead($this->listSuggestions()),
            'pbl_readiness' => $this->okRead(PblDataReadiness::report()),
            'cari_pengguna' => $this->okRead($this->cariPengguna($args)),
            'hitung_pengguna' => $this->okRead($this->hitungPengguna($args)),
            'monitoring_snapshot' => $this->okRead($this->monitoringSnapshot()),
            'log_absensi_cari' => $this->okRead($this->logAbsensiCari($args, $admin)),
            'list_alfa' => $this->okRead($this->listAlfa($args, $admin)),
            'riwayat_pengguna' => $this->okRead($this->riwayatPengguna($args)),
            'list_announcements' => $this->okRead($this->listAnnouncements($args, $admin)),
            'fingerprint_sync_report' => $this->okRead($this->fingerprintSyncReport($args)),
            'attention_today' => $this->okRead(AdminAiExtendedOps::attentionToday()),
            'laporan_periodik_ringkas' => $this->okRead(AdminAiExtendedOps::laporanPeriodikRingkas($args)),
            'ruangan_status' => $this->okRead($this->ruanganStatus($args)),
            'pengajuan_pending' => $this->okRead($this->pengajuanPending()),
            'pengajuan_ruangan_saya' => $this->okRead($this->pengajuanRuanganSaya($args, $admin)),
            'petugas_list' => $this->okRead($this->petugasList($args)),
            'kalender_agenda' => $this->okRead($this->kalenderAgenda($args)),
            'riwayat_aksi_ai' => $this->okRead($this->riwayatAksiAi($args, $admin)),
            default => ['ok' => false, 'kind' => 'error', 'error' => 'Tool read belum diimplementasi'],
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, kind: string, proposal?: array<string, mixed>, error?: string}
     */
    private function propose(string $tool, array $meta, array $args, User $admin): array
    {
        $validated = $this->validateWriteArgs($tool, $args);
        if (isset($validated['error'])) {
            return ['ok' => false, 'kind' => 'error', 'error' => $validated['error']];
        }

        [$menu, $action] = $meta['perm'];
        $summary = $validated['summary'];
        $payload = $validated['payload'];

        $proposal = AiActionProposal::query()->create([
            'requested_by' => $admin->id,
            'tool' => $tool,
            'title' => $meta['title'],
            'summary' => $summary,
            'payload' => $payload,
            'perm_menu' => $menu,
            'perm_action' => $action,
            'status' => 'pending',
            'dangerous' => (bool) ($meta['dangerous'] ?? false),
            'expires_at' => now()->addMinutes(15),
        ]);

        return [
            'ok' => true,
            'kind' => 'proposal',
            'proposal' => [
                'id' => $proposal->id,
                'tool' => $proposal->tool,
                'title' => $proposal->title,
                'summary' => $proposal->summary,
                'dangerous' => $proposal->dangerous,
                'expires_at' => $proposal->expires_at->toIso8601String(),
                'status' => 'pending',
                'items' => $validated['items'] ?? [],
                'count' => (int) ($validated['count'] ?? count($validated['items'] ?? [])),
                'action_label' => $validated['action_label'] ?? 'Konfirmasi aksi',
            ],
            'data' => [
                'message' => 'Aksi BELUM dieksekusi. Menunggu konfirmasi admin di UI.',
                'proposal_id' => $proposal->id,
                'summary' => $summary,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{payload?: array<string, mixed>, summary?: string, error?: string}
     */
    private function validateWriteArgs(string $tool, array $args): array
    {
        return match ($tool) {
            'approve_user' => $this->validateApprove($args),
            'reject_user' => $this->validateReject($args),
            'bulk_approve_ready' => $this->validateBulkApprove($args),
            'reply_suggestion' => $this->validateReplySuggestion($args),
            'create_announcement' => $this->validateAnnouncement($args),
            'toggle_mesin' => $this->validateToggleMesin($args),
            'unlock_user_login' => $this->validateUnlock($args),
            'fingerprint_sync_burst' => [
                'payload' => [],
                'summary' => 'Aktifkan distribusi template fingerprint ke mesin selama 20 menit',
            ],
            'toggle_user_active' => $this->validateToggleUserActive($args),
            'toggle_announcement' => $this->validateToggleAnnouncement($args),
            'koreksi_absensi_harian' => $this->validateKoreksiAbsensi($args),
            'batalkan_alfa' => $this->validateBatalkanAlfa($args),
            'selesai_tidak_lengkap' => $this->validateSelesaiTidakLengkap($args),
            'draft_pengumuman' => $this->validateDraftPengumuman($args),
            'create_ruangan' => $this->validateCreateRuangan($args),
            default => ['error' => 'Write tool tidak didukung'],
        };
    }

    /** @param array<string, mixed> $args */
    private function validateApprove(array $args): array
    {
        $id = (int) ($args['user_id'] ?? 0);
        $user = User::query()->find($id);
        if (! $user) {
            return ['error' => 'User tidak ditemukan'];
        }
        if (auth()->id() === $user->id) {
            return ['error' => 'Tidak bisa approve akun sendiri'];
        }
        $ready = PendingAccountApproval::query()->where('id', $user->id)->exists();
        if (! $ready) {
            return ['error' => 'User tidak dalam antrian siap approve (cek OTP / status)'];
        }

        return [
            'payload' => ['user_id' => $user->id],
            'summary' => "Setujui akun #{$user->id} {$user->nama_lengkap} ({$user->role})",
            'count' => 1,
            'action_label' => 'Setujui akun',
            'items' => [[
                'id' => $user->id,
                'nama' => $user->nama_lengkap,
                'role' => $user->role,
                'nitk_nidn' => $user->nitk_nidn,
                'email' => $user->email,
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateReject(array $args): array
    {
        $id = (int) ($args['user_id'] ?? 0);
        $note = trim((string) ($args['approval_note'] ?? ''));
        if ($note === '' || Str::length($note) > 500) {
            return ['error' => 'approval_note wajib (max 500)'];
        }
        $user = User::query()->find($id);
        if (! $user) {
            return ['error' => 'User tidak ditemukan'];
        }
        $status = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');
        if ($status === 'approved' || $status === 'rejected') {
            return ['error' => 'Status akun tidak bisa ditolak (sudah '.$status.')'];
        }

        return [
            'payload' => ['user_id' => $user->id, 'approval_note' => $note],
            'summary' => "Tolak akun #{$user->id} {$user->nama_lengkap}: {$note}",
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateBulkApprove(array $args): array
    {
        $all = (bool) ($args['all'] ?? false);
        $ids = array_values(array_unique(array_map('intval', (array) ($args['user_ids'] ?? []))));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));
        $ids = array_slice($ids, 0, 20);

        if ($all || $ids === []) {
            $readyIds = PendingAccountApproval::query()
                ->orderBy('id')
                ->limit(20)
                ->pluck('id')
                ->all();
        } else {
            $readyIds = PendingAccountApproval::query()->whereIn('id', $ids)->pluck('id')->all();
        }

        if ($readyIds === []) {
            return ['error' => 'Tidak ada akun siap approve di antrian'];
        }

        $users = User::query()
            ->whereIn('id', $readyIds)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'role', 'nitk_nidn', 'email']);

        $items = $users->map(fn (User $u) => [
            'id' => $u->id,
            'nama' => $u->nama_lengkap,
            'role' => $u->role,
            'nitk_nidn' => $u->nitk_nidn,
            'email' => $u->email,
        ])->all();

        $n = count($items);

        return [
            'payload' => ['user_ids' => $users->pluck('id')->all()],
            'summary' => $n === 1
                ? "Setujui akun #{$items[0]['id']} {$items[0]['nama']} ({$items[0]['role']})"
                : "Setujui {$n} akun siap approval".($all || $ids === [] ? ' (semua antrian, maks 20)' : ''),
            'count' => $n,
            'action_label' => $n > 1 ? "Setujui {$n} akun" : 'Setujui akun',
            'items' => $items,
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateReplySuggestion(array $args): array
    {
        $id = (int) ($args['suggestion_id'] ?? 0);
        $reply = trim((string) ($args['admin_reply'] ?? ''));
        if (Str::length($reply) < 2 || Str::length($reply) > 4000) {
            return ['error' => 'admin_reply 2–4000 karakter'];
        }
        $s = Suggestion::query()->find($id);
        if (! $s) {
            return ['error' => 'Saran tidak ditemukan'];
        }

        return [
            'payload' => ['suggestion_id' => $s->id, 'admin_reply' => $reply],
            'summary' => 'Balas saran #'.$s->id.': '.Str::limit($reply, 80),
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateAnnouncement(array $args): array
    {
        $judul = trim((string) ($args['judul'] ?? ''));
        $isi = trim((string) ($args['isi'] ?? ''));
        $target = (string) ($args['target'] ?? 'semua');
        $prioritas = (string) ($args['prioritas'] ?? 'normal');
        $action = (string) ($args['action'] ?? 'draft');
        if ($judul === '' || $isi === '') {
            return ['error' => 'judul dan isi wajib'];
        }
        if (! in_array($target, ['semua', 'dosen', 'mahasiswa'], true)) {
            return ['error' => 'target invalid'];
        }
        if (! in_array($prioritas, ['normal', 'penting', 'urgent'], true)) {
            return ['error' => 'prioritas invalid'];
        }
        if (! in_array($action, ['draft', 'publish'], true)) {
            $action = 'draft';
        }

        return [
            'payload' => compact('judul', 'isi', 'target', 'prioritas', 'action'),
            'summary' => ($action === 'publish' ? 'Publish' : 'Draft')." pengumuman \"{$judul}\" → {$target}",
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateToggleMesin(array $args): array
    {
        $id = (int) ($args['mesin_id'] ?? 0);
        $mesin = MesinIot::query()->find($id);
        if (! $mesin) {
            return ['error' => 'Mesin tidak ditemukan'];
        }
        $next = ! $mesin->is_active;

        return [
            'payload' => ['mesin_id' => $mesin->id, 'set_active' => $next],
            'summary' => ($next ? 'Aktifkan' : 'Nonaktifkan')." mesin #{$mesin->id} {$mesin->nama_mesin} ({$mesin->id_mesin})",
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateUnlock(array $args): array
    {
        $id = (int) ($args['user_id'] ?? 0);
        $user = User::query()->find($id);
        if (! $user) {
            return ['error' => 'User tidak ditemukan'];
        }

        return [
            'payload' => ['user_id' => $user->id],
            'summary' => "Buka kunci login #{$user->id} {$user->nama_lengkap}",
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateToggleUserActive(array $args): array
    {
        $id = (int) ($args['user_id'] ?? 0);
        $set = array_key_exists('set_active', $args) ? (bool) $args['set_active'] : null;
        $user = User::query()->find($id);
        if (! $user) {
            return ['error' => 'User tidak ditemukan'];
        }
        if ($set === null) {
            return ['error' => 'set_active wajib (true/false)'];
        }
        if (auth()->id() === $user->id) {
            return ['error' => 'Tidak bisa mengubah status aktif akun sendiri'];
        }
        if ((bool) $user->is_active === $set) {
            return ['error' => 'Akun sudah '.($set ? 'aktif' : 'nonaktif')];
        }

        return [
            'payload' => ['user_id' => $user->id, 'set_active' => $set],
            'summary' => ($set ? 'Aktifkan' : 'Nonaktifkan')." akun #{$user->id} {$user->nama_lengkap} ({$user->role})",
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateToggleAnnouncement(array $args): array
    {
        $id = (int) ($args['announcement_id'] ?? 0);
        $row = Announcement::query()->find($id);
        if (! $row) {
            return ['error' => 'Pengumuman tidak ditemukan'];
        }
        $next = ! (bool) $row->is_active;

        return [
            'payload' => ['announcement_id' => $row->id, 'set_active' => $next],
            'summary' => ($next ? 'Publish' : 'Unpublish').' pengumuman #'.$row->id.' "'.Str::limit((string) $row->judul, 60).'"',
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateKoreksiAbsensi(array $args): array
    {
        $status = (string) ($args['status'] ?? '');
        if (! in_array($status, ['lengkap', 'tidak_lengkap', 'alfa', 'masuk', 'belum'], true)) {
            return ['error' => 'status wajib: lengkap|tidak_lengkap|alfa|masuk|belum'];
        }
        $catatan = trim((string) ($args['catatan_koreksi'] ?? ''));
        if (Str::length($catatan) > 2000) {
            return ['error' => 'catatan_koreksi max 2000'];
        }

        $harian = null;
        $harianId = (int) ($args['harian_id'] ?? 0);
        if ($harianId > 0) {
            $harian = AbsensiHarian::query()->with('user:id,nama_lengkap')->find($harianId);
        } else {
            $userId = (int) ($args['user_id'] ?? 0);
            $tanggal = trim((string) ($args['tanggal'] ?? ''));
            $sesi = trim((string) ($args['sesi'] ?? ''));
            if ($userId < 1 || $tanggal === '' || $sesi === '') {
                return ['error' => 'Butuh harian_id atau (user_id + tanggal + sesi)'];
            }
            $harian = AbsensiHarian::query()
                ->with('user:id,nama_lengkap')
                ->where('user_id', $userId)
                ->whereDate('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->first();
        }
        if (! $harian) {
            return ['error' => 'Record absensi harian tidak ditemukan'];
        }

        $nama = $harian->user->nama_lengkap ?? ('user#'.$harian->user_id);

        return [
            'payload' => [
                'harian_id' => $harian->id,
                'status' => $status,
                'catatan_koreksi' => $catatan === '' ? null : $catatan,
            ],
            'summary' => "Koreksi absensi #{$harian->id} {$nama} ({$harian->tanggal} / {$harian->sesi}): {$harian->status} → {$status}",
            'action_label' => 'Simpan koreksi',
            'items' => [[
                'id' => $harian->id,
                'nama' => $nama,
                'role' => $status,
                'nitk_nidn' => (string) $harian->tanggal.' · '.$harian->sesi,
            ]],
            'count' => 1,
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateBatalkanAlfa(array $args): array
    {
        $catatan = trim((string) ($args['catatan'] ?? ''));
        if (Str::length($catatan) > 2000) {
            return ['error' => 'catatan max 2000'];
        }

        $alfa = null;
        $alfaId = (int) ($args['alfa_id'] ?? 0);
        if ($alfaId > 0) {
            $alfa = AbsensiAlfa::query()->with('user:id,nama_lengkap')->find($alfaId);
        } else {
            $userId = (int) ($args['user_id'] ?? 0);
            $tanggal = trim((string) ($args['tanggal'] ?? ''));
            $sesi = trim((string) ($args['sesi'] ?? ''));
            if ($userId < 1 || $tanggal === '' || $sesi === '') {
                return ['error' => 'Butuh alfa_id atau (user_id + tanggal + sesi)'];
            }
            $alfa = AbsensiAlfa::query()
                ->with('user:id,nama_lengkap')
                ->where('user_id', $userId)
                ->whereDate('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->where('aktif', true)
                ->first();
        }
        if (! $alfa || ! $alfa->aktif) {
            return ['error' => 'Alfa aktif tidak ditemukan'];
        }

        $nama = $alfa->user->nama_lengkap ?? ('user#'.$alfa->user_id);

        return [
            'payload' => [
                'alfa_id' => $alfa->id,
                'catatan' => $catatan === '' ? null : $catatan,
            ],
            'summary' => "Batalkan alfa #{$alfa->id} {$nama} ({$alfa->tanggal} / {$alfa->sesi})",
            'action_label' => 'Batalkan alfa',
            'items' => [[
                'id' => $alfa->id,
                'nama' => $nama,
                'role' => 'alfa',
                'nitk_nidn' => (string) $alfa->tanggal.' · '.$alfa->sesi,
            ]],
            'count' => 1,
        ];
    }

    /** @param array<string, mixed> $args */
    private function validateSelesaiTidakLengkap(array $args): array
    {
        $id = (int) ($args['harian_id'] ?? 0);
        $harian = AbsensiHarian::query()->with('user:id,nama_lengkap')->find($id);
        if (! $harian) {
            return ['error' => 'Absensi harian tidak ditemukan'];
        }
        if ($harian->status !== 'tidak_lengkap') {
            return ['error' => 'Status harian bukan tidak_lengkap'];
        }
        $catatan = trim((string) ($args['catatan_koreksi'] ?? ''));
        $nama = $harian->user->nama_lengkap ?? ('user#'.$harian->user_id);

        return [
            'payload' => [
                'harian_id' => $harian->id,
                'catatan_koreksi' => $catatan === '' ? null : $catatan,
            ],
            'summary' => "Selesaikan tidak lengkap #{$harian->id} {$nama} ({$harian->tanggal} / {$harian->sesi})",
            'action_label' => 'Tandai selesai',
            'count' => 1,
            'items' => [[
                'id' => $harian->id,
                'nama' => $nama,
                'role' => 'tidak_lengkap',
                'nitk_nidn' => (string) $harian->tanggal.' · '.$harian->sesi,
            ]],
        ];
    }


    /** @param array<string, mixed> $args */
    private function validateDraftPengumuman(array $args): array
    {
        $topik = trim((string) ($args['topik'] ?? ''));
        if ($topik === '' || Str::length($topik) < 3) {
            return ['error' => 'topik minimal 3 karakter'];
        }
        $target = (string) ($args['target'] ?? 'semua');
        $prioritas = (string) ($args['prioritas'] ?? 'normal');
        if (! in_array($target, ['semua', 'dosen', 'mahasiswa'], true)) {
            $target = 'semua';
        }
        if (! in_array($prioritas, ['normal', 'penting', 'urgent'], true)) {
            $prioritas = 'normal';
        }
        $draft = AdminAiExtendedOps::draftPengumumanFromTopik($topik, $target);

        return [
            'payload' => [
                'judul' => $draft['judul'],
                'isi' => $draft['isi'],
                'target' => $target,
                'prioritas' => $prioritas,
                'action' => 'draft',
            ],
            'summary' => 'Draft pengumuman dari topik: '.Str::limit($topik, 80).' → '.$target,
            'action_label' => 'Simpan draft',
            'count' => 1,
            'items' => [[
                'id' => 0,
                'nama' => $draft['judul'],
                'role' => $target,
                'nitk_nidn' => $prioritas,
            ]],
        ];
    }

    /** @return array{ok: bool, kind: string, data: mixed} */
    private function okRead(mixed $data): array
    {
        return ['ok' => true, 'kind' => 'read', 'data' => $data];
    }

    /**
     * @param  array{labels: list<string>, values: list<int|float>, periode?: string, total?: int, auto_shifted?: bool, filter_label?: string, granularity?: string, chart_type?: string}  $series
     * @return array{ok: bool, kind: string, data: mixed, chart: array<string, mixed>}
     */
    private function okChart(array $series, string $kind): array
    {
        $periode = (string) ($series['periode'] ?? 'periode');
        $filter = trim((string) ($series['filter_label'] ?? ''));
        $baseTitle = 'Tap absensi';
        $title = $baseTitle.' ('.$periode.')'.($filter !== '' ? ' · '.$filter : '');

        $points = count($series['labels'] ?? []);
        $chartType = (string) ($series['chart_type'] ?? '');
        if ($chartType === '') {
            $chartType = $points > 14 ? 'line' : 'bar';
        }

        $total = (int) ($series['total'] ?? array_sum($series['values'] ?? []));

        return [
            'ok' => true,
            'kind' => 'read',
            'data' => [
                'labels' => $series['labels'],
                'values' => $series['values'],
                'periode' => $periode,
                'total' => $total,
                'auto_shifted' => (bool) ($series['auto_shifted'] ?? false),
                'granularity' => $series['granularity'] ?? 'day',
                'filter' => $filter,
                'catatan' => ($series['auto_shifted'] ?? false)
                    ? '7 hari terakhir kosong — grafik digeser ke minggu terakhir yang punya data.'
                    : ($total === 0
                        ? 'Belum ada data pada rentang ini; grafik tetap ditampilkan (siap data ke depan).'
                        : null),
            ],
            'chart' => [
                'title' => $title,
                'labels' => $series['labels'],
                'values' => $series['values'],
                'color' => '#0284c7',
                'type' => $chartType,
                'total' => $total,
                'note' => ($series['auto_shifted'] ?? false)
                    ? 'Digeser ke periode ber-data'
                    : ($total === 0 ? 'Belum ada data — kerangka grafik siap' : null),
                'subtitle' => $filter,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function mesinStatus(): array
    {
        $rows = MesinIot::query()->orderBy('nama_mesin')->limit(30)->get();

        return [
            'items' => $rows->map(fn (MesinIot $m) => [
                'id' => $m->id,
                'id_mesin' => $m->id_mesin,
                'nama' => $m->nama_mesin,
                'tipe' => $m->tipe,
                'is_active' => (bool) $m->is_active,
                'online' => $m->isOnline(),
                'last_seen_at' => optional($m->last_seen_at)?->toDateTimeString(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function enrollPending(int $limit): array
    {
        $limit = max(1, min(25, $limit));
        $all = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();
        $pending = $all->filter(fn (User $u) => ! $u->hasFingerprintEnrolled())->values();

        return [
            'total' => $pending->count(),
            'sample' => $pending->take($limit)->map(fn (User $u) => [
                'id' => $u->id,
                'nama' => $u->nama_lengkap,
                'kelas' => $u->kelas,
                'tim' => $u->tim,
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $args @return array<string, mixed> */
    private function rekapAbsensi(array $args): array
    {
        [$start, $end, $label] = $this->periodBounds($args);

        $total = (int) DB::table('log_absensi')->whereBetween('waktu_tap', [$start, $end])->count();
        $unique = (int) DB::table('log_absensi')
            ->whereBetween('waktu_tap', [$start, $end])
            ->selectRaw('count(distinct user_id) as c')
            ->value('c');

        return [
            'periode' => $label,
            'dari' => $start->toDateTimeString(),
            'sampai' => $end->toDateTimeString(),
            'total_tap' => $total,
            'user_unik' => $unique,
        ];
    }


    /**
     * @param  array<string, mixed>  $args
     * @return array{labels: list<string>, values: list<int>, periode: string, total: int, auto_shifted: bool, filter_label: string, granularity: string, chart_type: string}
     */
    private function chartSeries(string $type, array $args): array
    {
        $col = 'waktu_tap';
        $table = 'log_absensi';
        $kelas = trim((string) ($args['kelas'] ?? ''));
        $tim = trim((string) ($args['tim'] ?? ''));
        $mode = Str::lower(trim((string) ($args['mode'] ?? '')));
        if ($mode === '') {
            // Infer from natural params
            if (! empty($args['tanggal_mulai']) || ! empty($args['tanggal_akhir'])) {
                $mode = 'custom';
            } elseif (! empty($args['tahun']) || str_contains(Str::lower((string) ($args['periode'] ?? '')), 'tahun')) {
                $mode = 'tahun';
            } else {
                $mode = '7hari';
            }
        }
        if (! in_array($mode, ['7hari', 'minggu', 'bulan', 'tahun', 'custom'], true)) {
            $mode = '7hari';
        }

        $anchor = ! empty($args['tanggal'])
            ? Carbon::parse((string) $args['tanggal'])->endOfDay()
            : now()->endOfDay();

        [$start, $end, $periodeLabel] = match ($mode) {
            'minggu' => [
                $anchor->copy()->startOfWeek(),
                $anchor->copy()->endOfWeek(),
                'minggu '.$anchor->translatedFormat('W Y'),
            ],
            'bulan' => [
                $anchor->copy()->startOfMonth(),
                $anchor->copy()->endOfMonth(),
                $anchor->translatedFormat('F Y'),
            ],
            'tahun' => [
                $anchor->copy()->startOfYear(),
                $anchor->copy()->endOfYear(),
                'tahun '.$anchor->year,
            ],
            'custom' => $this->chartCustomBounds($args, $anchor),
            default => [
                $anchor->copy()->subDays(6)->startOfDay(),
                $anchor->copy()->endOfDay(),
                '7 hari',
            ],
        };

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        // Cap custom/tahun span to avoid huge queries (max ~400 buckets)
        $days = max(1, $start->diffInDays($end) + 1);
        $granularity = 'day';
        if ($mode === 'tahun' || $days > 120) {
            $granularity = 'month';
        } elseif ($days > 45) {
            $granularity = 'week';
        }

        $preferData = array_key_exists('prefer_data', $args)
            ? (bool) $args['prefer_data']
            : ($mode === '7hari' && $kelas === '' && $tim === '');

        $built = $this->buildChartBuckets($table, $col, $start, $end, $granularity, $kelas, $tim);
        $autoShifted = false;

        if ($preferData && $built['total'] === 0 && $mode === '7hari') {
            $latestQ = DB::table($table);
            $this->applyUserFilterToLogQuery($latestQ, $table, $kelas, $tim);
            $latest = $latestQ->max($col);
            if ($latest) {
                $anchor2 = Carbon::parse($latest)->endOfDay();
                $start2 = $anchor2->copy()->subDays(6)->startOfDay();
                $built = $this->buildChartBuckets($table, $col, $start2, $anchor2, 'day', $kelas, $tim);
                $periodeLabel = $start2->translatedFormat('d M').' – '.$anchor2->translatedFormat('d M Y');
                $autoShifted = $built['total'] > 0;
            }
        } else {
            $periodeLabel = $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');
            if ($mode === 'tahun') {
                $periodeLabel = 'tahun '.$start->year;
            } elseif ($mode === 'bulan') {
                $periodeLabel = $start->translatedFormat('F Y');
            } elseif ($mode === 'minggu') {
                $periodeLabel = 'minggu '.$start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y');
            } elseif ($mode === '7hari' && ! $autoShifted) {
                $periodeLabel = $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y');
            }
        }

        $filterBits = [];
        if ($kelas !== '') {
            $filterBits[] = 'kelas '.$kelas;
        }
        if ($tim !== '') {
            $filterBits[] = 'tim '.$tim;
        }

        $chartType = (string) ($args['chart_type'] ?? '');
        if ($chartType === '') {
            $chartType = count($built['labels']) > 14 || in_array($mode, ['bulan', 'tahun', 'custom'], true)
                ? 'line'
                : 'bar';
        }

        return [
            'labels' => $built['labels'],
            'values' => $built['values'],
            'periode' => $periodeLabel,
            'total' => $built['total'],
            'auto_shifted' => $autoShifted,
            'filter_label' => implode(' · ', $filterBits),
            'granularity' => $granularity,
            'chart_type' => in_array($chartType, ['line', 'bar'], true) ? $chartType : 'line',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function chartCustomBounds(array $args, Carbon $anchor): array
    {
        $mulai = trim((string) ($args['tanggal_mulai'] ?? ''));
        $akhir = trim((string) ($args['tanggal_akhir'] ?? ''));
        $start = $mulai !== '' ? Carbon::parse($mulai)->startOfDay() : $anchor->copy()->subDays(29)->startOfDay();
        $end = $akhir !== '' ? Carbon::parse($akhir)->endOfDay() : $anchor->copy()->endOfDay();
        // Max 366 days for custom
        if ($start->diffInDays($end) > 366) {
            $start = $end->copy()->subDays(365)->startOfDay();
        }

        return [$start, $end, 'custom'];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, total: int}
     */
    private function buildChartBuckets(
        string $table,
        string $col,
        Carbon $start,
        Carbon $end,
        string $granularity,
        string $kelas,
        string $tim
    ): array {
        $labels = [];
        $values = [];

        if ($granularity === 'month') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $bStart = $cursor->copy()->startOfMonth()->max($start);
                $bEnd = $cursor->copy()->endOfMonth()->min($end);
                $labels[] = $cursor->translatedFormat('M Y');
                $values[] = $this->countLogInRange($table, $col, $bStart, $bEnd, $kelas, $tim);
                $cursor->addMonth();
            }
        } elseif ($granularity === 'week') {
            $cursor = $start->copy()->startOfWeek();
            $last = $end->copy()->startOfWeek();
            while ($cursor->lte($last)) {
                $bStart = $cursor->copy()->startOfWeek()->max($start);
                $bEnd = $cursor->copy()->endOfWeek()->min($end);
                $labels[] = 'M'.$cursor->isoWeek().' '.$cursor->translatedFormat('d/m');
                $values[] = $this->countLogInRange($table, $col, $bStart, $bEnd, $kelas, $tim);
                $cursor->addWeek();
            }
        } else {
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $labels[] = $cursor->translatedFormat('D d/m');
                $values[] = $this->countLogInRange(
                    $table,
                    $col,
                    $cursor->copy()->startOfDay(),
                    $cursor->copy()->endOfDay(),
                    $kelas,
                    $tim
                );
                $cursor->addDay();
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
        ];
    }

    private function countLogInRange(
        string $table,
        string $col,
        Carbon $start,
        Carbon $end,
        string $kelas,
        string $tim
    ): int {
        $q = DB::table($table)->whereBetween($col, [$start, $end]);
        $this->applyUserFilterToLogQuery($q, $table, $kelas, $tim);

        return (int) $q->count();
    }

    private function applyUserFilterToLogQuery(mixed $query, string $table, string $kelas, string $tim): void
    {
        if ($kelas === '' && $tim === '') {
            return;
        }
        $query->whereExists(function ($sub) use ($table, $kelas, $tim) {
            $sub->select(DB::raw(1))
                ->from('users')
                ->whereColumn('users.id', $table.'.user_id')
                ->where('users.role', 'mahasiswa');
            if ($kelas !== '') {
                $sub->where('users.kelas', $kelas);
            }
            if ($tim !== '') {
                $sub->where('users.tim', 'like', '%'.$tim.'%');
            }
        });
    }

    /** @return array<string, mixed> */
    private function listPendingApprovals(int $limit): array
    {
        $limit = max(1, min(20, $limit));
        $rows = PendingAccountApproval::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'nama_lengkap', 'role', 'email', 'kelas', 'created_at']);

        return [
            'total' => PendingAccountApproval::count(),
            'items' => $rows->map(fn (User $u) => [
                'id' => $u->id,
                'nama' => $u->nama_lengkap,
                'role' => $u->role,
                'email' => $u->email,
                'kelas' => $u->kelas,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function listSuggestions(): array
    {
        $rows = Suggestion::query()
            ->whereIn('status', ['baru', 'diproses'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'nama', 'subjek', 'status', 'created_at']);

        return [
            'items' => $rows->map(fn (Suggestion $s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'subjek' => Str::limit((string) ($s->subjek ?? $s->pesan ?? ''), 80),
                'status' => $s->status,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function cariPengguna(array $args): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $role = (string) ($args['role'] ?? 'semua');
        $listMode = $q === '' || in_array(Str::lower($q), ['list', 'semua', '*', 'daftar'], true);

        if ($listMode && ! in_array($role, ['admin', 'mahasiswa', 'dosen', 'laboran'], true)) {
            return [
                'error' => 'Untuk daftar tanpa kata kunci, tentukan role: mahasiswa / dosen / laboran. Atau beri nama minimal 2 karakter.',
                'items' => [],
                'total' => 0,
            ];
        }

        if (! $listMode && Str::length($q) < 2) {
            return ['error' => 'Kata kunci minimal 2 karakter', 'items' => [], 'total' => 0];
        }

        $query = User::query();
        if (! $listMode) {
            $query->where(function ($w) use ($q) {
                $w->where('nama_lengkap', 'like', '%'.$q.'%')
                    ->orWhere('nitk_nidn', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
            });
        }

        if (in_array($role, ['admin', 'mahasiswa', 'dosen', 'laboran'], true)) {
            $query->where('role', $role);
        }

        $limit = $listMode ? 15 : 10;
        $rows = $query->orderBy('nama_lengkap')->limit($limit)->get([
            'id', 'nama_lengkap', 'role', 'email', 'nitk_nidn', 'kelas', 'tim',
            'is_active', 'approval_status', 'finger_id', 'semester',
        ]);

        return [
            'query' => $listMode ? '(daftar '.$role.')' : $q,
            'list_mode' => $listMode,
            'total' => $rows->count(),
            'items' => $rows->map(function (User $u) {
                $enrolled = $u->role === 'mahasiswa' ? $u->hasFingerprintEnrolled() : null;

                return [
                    'id' => $u->id,
                    'nama' => $u->nama_lengkap,
                    'role' => $u->role,
                    'email' => $u->email,
                    'nitk_nidn' => $u->nitk_nidn,
                    'kelas' => $u->kelas,
                    'tim' => $u->tim,
                    'semester' => $u->semester,
                    'is_active' => (bool) $u->is_active,
                    'approval_status' => $u->approval_status ?? ($u->is_active ? 'approved' : 'pending'),
                    'finger_id' => $u->finger_id,
                    'fingerprint_enrolled' => $enrolled,
                ];
            })->all(),
            'catatan' => 'Data sensitif (password, API key, foto bukti) tidak ditampilkan.',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function hitungPengguna(array $args): array
    {
        $role = (string) ($args['role'] ?? 'semua');
        $roles = in_array($role, ['admin', 'mahasiswa', 'dosen', 'laboran'], true)
            ? [$role]
            : ['admin', 'mahasiswa', 'dosen', 'laboran'];

        $byRole = [];
        foreach ($roles as $r) {
            $base = User::query()->where('role', $r);
            $total = (clone $base)->count();
            $aktif = (clone $base)->where('is_active', true)->count();
            $pending = (clone $base)->where(function ($q) {
                $q->where('approval_status', 'pending')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('approval_status')->where('is_active', false);
                    });
            })->count();

            $byRole[$r] = [
                'total' => $total,
                'aktif' => $aktif,
                'pending' => $pending,
                'nonaktif' => max(0, $total - $aktif),
            ];
        }

        $grand = [
            'total' => collect($byRole)->sum('total'),
            'aktif' => collect($byRole)->sum('aktif'),
            'pending' => collect($byRole)->sum('pending'),
            'nonaktif' => collect($byRole)->sum('nonaktif'),
        ];

        return [
            'generated_at' => now()->toDateTimeString(),
            'fokus' => $role,
            'by_role' => $byRole,
            'grand_total' => $grand,
        ];
    }

    /** @return array<string, mixed> */
    private function monitoringSnapshot(): array
    {
        $machines = MesinIot::query()->orderBy('nama_mesin')->limit(20)->get();
        $online = $machines->filter(fn (MesinIot $m) => $m->is_active && $m->isOnline())->count();
        $aktif = $machines->where('is_active', true)->count();

        $recentAbsensi = LogAbsensi::query()
            ->with(['user:id,nama_lengkap'])
            ->latest('waktu_tap')
            ->limit(5)
            ->get()
            ->map(fn (LogAbsensi $r) => [
                'jenis' => 'absensi',
                'waktu' => optional($r->waktu_tap)?->toDateTimeString(),
                'nama' => $r->user->nama_lengkap ?? '-',
                'mesin' => $r->id_mesin,
            ])->all();

        $activities = collect($recentAbsensi)
            ->sortByDesc('waktu')
            ->take(8)
            ->values()
            ->all();

        return [
            'generated_at' => now()->toDateTimeString(),
            'mesin' => [
                'total' => $machines->count(),
                'aktif' => $aktif,
                'online' => $online,
                'offline' => max(0, $aktif - $online),
            ],
            'hari_ini' => [
                'tap_absensi' => (int) LogAbsensi::query()->whereDate('waktu_tap', today())->count(),
            ],
            'aktivitas_terbaru' => $activities,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function logAbsensiCari(array $args, User $admin): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $idMesin = trim((string) ($args['id_mesin'] ?? ''));
        $mulai = trim((string) ($args['tanggal_mulai'] ?? ''));
        $akhir = trim((string) ($args['tanggal_akhir'] ?? ''));
        if ($mulai === '' && $akhir === '') {
            $mulai = $akhir = now()->toDateString();
        }

        $query = LogAbsensi::query()->with(['user:id,nama_lengkap,nitk_nidn']);
        if ($admin->isDosen()) {
            $query->whereHas('user', fn ($u) => DosenKelasScope::applySupervisedMahasiswaQuery($u, $admin));
        }
        if ($idMesin !== '') {
            $query->where('id_mesin', $idMesin);
        }
        if ($mulai !== '') {
            $query->whereDate('waktu_tap', '>=', $mulai);
        }
        if ($akhir !== '') {
            $query->whereDate('waktu_tap', '<=', $akhir);
        }
        if ($q !== '') {
            $query->whereHas('user', function ($qb) use ($q) {
                $qb->where('nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('nitk_nidn', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('waktu_tap')->limit(20)->get();

        return [
            'filter' => compact('q', 'idMesin', 'mulai', 'akhir'),
            'total' => $rows->count(),
            'items' => $rows->map(fn (LogAbsensi $r) => [
                'waktu' => optional($r->waktu_tap)?->toDateTimeString(),
                'nama' => $r->user->nama_lengkap ?? '-',
                'nitk' => $r->user->nitk_nidn ?? null,
                'user_id' => $r->user_id,
                'mesin' => $r->id_mesin,
            ])->all(),
        ];
    }



    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listAlfa(array $args, User $admin): array
    {
        $tanggal = trim((string) ($args['tanggal'] ?? '')) ?: now()->toDateString();
        $kelas = trim((string) ($args['kelas'] ?? ''));
        $tim = trim((string) ($args['tim'] ?? ''));

        $query = AbsensiAlfa::query()
            ->with(['user:id,nama_lengkap,kelas,tim,nitk_nidn'])
            ->where('aktif', true)
            ->whereDate('tanggal', $tanggal);

        if ($admin->isDosen()) {
            $query->whereHas('user', fn ($u) => DosenKelasScope::applySupervisedMahasiswaQuery($u, $admin));
        }

        if ($kelas !== '') {
            $query->whereHas('user', fn ($q) => $q->where('kelas', $kelas));
        }
        if ($tim !== '') {
            $query->whereHas('user', fn ($q) => $q->where('tim', 'like', '%'.$tim.'%'));
        }

        $rows = $query->orderByDesc('id')->limit(25)->get();

        return [
            'tanggal' => $tanggal,
            'total' => $rows->count(),
            'items' => $rows->map(fn (AbsensiAlfa $a) => [
                'alfa_id' => $a->id,
                'user_id' => $a->user_id,
                'nama' => $a->user->nama_lengkap ?? '-',
                'kelas' => $a->user->kelas ?? null,
                'tim' => $a->user->tim ?? null,
                'sesi' => $a->sesi,
                'kategori' => $a->kategori,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function riwayatPengguna(array $args): array
    {
        $userId = (int) ($args['user_id'] ?? 0);
        $hari = max(1, min(31, (int) ($args['hari'] ?? 7)));
        $user = User::query()->find($userId);
        if (! $user) {
            return ['error' => 'User tidak ditemukan'];
        }

        $start = now()->subDays($hari - 1)->startOfDay();
        $absensi = LogAbsensi::query()
            ->where('user_id', $userId)
            ->where('waktu_tap', '>=', $start)
            ->orderByDesc('waktu_tap')
            ->limit(30)
            ->get(['waktu_tap', 'id_mesin']);
        return [
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama_lengkap,
                'role' => $user->role,
                'kelas' => $user->kelas,
                'tim' => $user->tim,
            ],
            'periode_hari' => $hari,
            'mulai' => $start->toDateString(),
            'absensi' => [
                'total' => $absensi->count(),
                'items' => $absensi->map(fn ($r) => [
                    'waktu' => optional($r->waktu_tap)?->toDateTimeString(),
                    'mesin' => $r->id_mesin,
                ])->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listAnnouncements(array $args, User $admin): array
    {
        $status = (string) ($args['status'] ?? 'semua');
        $query = Announcement::query()->latest('id');
        if ($admin->isDosen()) {
            $query->forAudience($admin);
        }
        if ($status === 'aktif') {
            $query->where('is_active', true);
        } elseif ($status === 'draft') {
            $query->where('is_active', false);
        }
        $rows = $query->limit(15)->get(['id', 'judul', 'target', 'prioritas', 'is_active', 'created_at']);

        return [
            'total' => $rows->count(),
            'items' => $rows->map(fn (Announcement $a) => [
                'id' => $a->id,
                'judul' => $a->judul,
                'target' => $a->target,
                'prioritas' => $a->prioritas,
                'aktif' => (bool) $a->is_active,
                'dibuat' => optional($a->created_at)?->toDateTimeString(),
            ])->all(),
        ];
    }

    /**
     * DOSEN: pengajuan ruangan dari tim PBL yang dibimbing.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function pengajuanRuanganSaya(array $args, User $admin): array
    {
        $timIds = RuanganBookingService::timBimbingan($admin)->pluck('id');
        if ($timIds->isEmpty()) {
            return ['total' => 0, 'items' => [], 'catatan' => 'Anda belum membimbing tim PBL manapun.'];
        }

        $status = (string) ($args['status'] ?? 'semua');
        $query = RuanganPengajuan::query()
            ->with(['ruangan:id,nama,kode', 'project:id,nama', 'pengaju:id,nama_lengkap'])
            ->whereIn('pbl_project_id', $timIds)
            ->latest('tanggal')->latest('id');
        if ($status !== 'semua') {
            $query->where('status', $status);
        }

        $rows = $query->limit(20)->get();

        return [
            'total' => $rows->count(),
            'items' => $rows->map(fn (RuanganPengajuan $p) => [
                'id' => $p->id,
                'ruangan' => $p->ruangan->nama ?? '-',
                'tim' => $p->project->nama ?? '-',
                'pemohon' => $p->pengaju->nama_lengkap ?? '-',
                'tujuan' => $p->tujuan,
                'tanggal' => optional($p->tanggal)?->toDateString(),
                'jam' => ($p->jam_mulai ?? '').' - '.($p->jam_selesai ?? ''),
                'status' => $p->status_efektif ?? $p->status,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function fingerprintSyncReport(array $args): array
    {
        $idMesin = trim((string) ($args['id_mesin'] ?? ''));
        $report = FingerprintSyncLogService::adminReport($idMesin !== '' ? $idMesin : null, 10);
        $rows = $report['rows'];

        return [
            'stats' => $report['stats'],
            'items' => collect($rows->items())->map(fn ($log) => [
                'waktu' => optional($log->requested_at)?->toDateTimeString(),
                'mesin' => $log->id_mesin,
                'aksi' => $log->action,
                'sukses' => (bool) $log->success,
                'templates' => (int) $log->templates_count,
                'pesan' => $log->message,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ruanganStatus(array $args): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $mesinMap = MesinIot::query()->whereNotNull('ruangan_id')->get(['id', 'id_mesin', 'ruangan_id', 'status'])
            ->groupBy('ruangan_id');
        $rows = Ruangan::query()
            ->when($q !== '', fn ($qb) => $qb->where('nama', 'like', "%{$q}%"))
            ->orderBy('nama')->limit(20)->get();

        return [
            'total' => $rows->count(),
            'items' => $rows->map(function (Ruangan $r) use ($mesinMap) {
                $mesin = $mesinMap->get($r->id) ?? collect();

                return [
                    'nama' => $r->nama,
                    'kode' => $r->kode,
                    'aktif' => (bool) $r->aktif,
                    'kapasitas' => $r->kapasitas,
                    'fasilitas' => $r->fasilitas,
                    'mesin' => $mesin->map(fn ($m) => ['id_mesin' => $m->id_mesin, 'status' => $m->status])->values()->all(),
                ];
            })->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function pengajuanPending(): array
    {
        $rows = RuanganPengajuan::query()->with(['ruangan:id,nama', 'pengaju:id,nama_lengkap'])
            ->whereIn('status', RuanganPengajuan::STATUS_AKTIF_MENUNGGU)
            ->orderBy('tanggal')->limit(20)->get();

        return [
            'total' => $rows->count(),
            'items' => $rows->map(fn (RuanganPengajuan $p) => [
                'pemohon' => $p->pengaju->nama_lengkap ?? '-',
                'ruangan' => $p->ruangan->nama ?? '-',
                'tanggal' => optional($p->tanggal)->toDateString(),
                'jam' => trim(($p->jam_mulai ?? '').' - '.($p->jam_selesai ?? '')),
                'status' => $p->status,
                'tujuan' => Str::limit((string) ($p->tujuan ?? ''), 80),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function petugasList(array $args): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $rows = RuanganPetugas::query()
            ->when($q !== '', fn ($qb) => $qb->where('nama', 'like', "%{$q}%"))
            ->orderBy('nama')->limit(20)->get();

        return [
            'total' => $rows->count(),
            'items' => $rows->map(fn (RuanganPetugas $p) => [
                'nama' => $p->nama,
                'status' => $p->status,
                'id_internal' => $p->id_internal,
                'ruangan_akses' => collect((array) ($p->jadwal ?? []))->pluck('ruangan')->filter()->unique()->values()->all(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function kalenderAgenda(array $args): array
    {
        $bulan = trim((string) ($args['bulan'] ?? ''));
        if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
            $to = $from->copy()->endOfMonth();
        } else {
            $from = now()->startOfDay();
            $to = now()->addDays(60)->endOfDay();
        }
        $rows = AbsensiKalenderLibur::query()
            ->where(function ($qb) use ($from, $to) {
                $qb->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('tanggal_selesai', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(fn ($q2) => $q2->where('tanggal', '<=', $from->toDateString())->where('tanggal_selesai', '>=', $to->toDateString()));
            })
            ->orderBy('tanggal')->limit(40)->get();

        return [
            'periode' => $from->toDateString().' s/d '.$to->toDateString(),
            'total' => $rows->count(),
            'items' => $rows->map(fn (AbsensiKalenderLibur $a) => [
                'nama' => $a->nama ?? $a->keterangan,
                'jenis' => $a->jenis,
                'tanggal' => optional($a->tanggal)->toDateString() ?? (string) $a->tanggal,
                'tanggal_selesai' => optional($a->tanggal_selesai)->toDateString(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function riwayatAksiAi(array $args, User $admin): array
    {
        $status = (string) ($args['status'] ?? '');
        $rows = AiActionProposal::query()->where('requested_by', $admin->id)
            ->when($status !== '', fn ($qb) => $qb->where('status', $status))
            ->latest()->limit(min(20, max(1, (int) ($args['limit'] ?? 10))))->get();

        return [
            'total' => $rows->count(),
            'items' => $rows->map(fn (AiActionProposal $p) => [
                'tool' => $p->tool,
                'judul' => $p->title,
                'ringkasan' => $p->summary,
                'status' => $p->status,
                'waktu' => optional($p->created_at)?->toDateTimeString(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function validateCreateRuangan(array $args): array
    {
        $nama = trim((string) ($args['nama'] ?? ''));
        if ($nama === '') {
            return ['error' => 'Nama ruangan wajib diisi.'];
        }
        $kode = trim((string) ($args['kode'] ?? ''));
        if ($kode !== '' && Ruangan::query()->where('kode', $kode)->exists()) {
            return ['error' => "Kode ruangan {$kode} sudah dipakai."];
        }

        return [
            'summary' => "Daftarkan ruangan baru \"{$nama}\"".($kode !== '' ? " ({$kode})" : ''),
            'payload' => [
                'nama' => $nama,
                'kode' => $kode !== '' ? $kode : null,
                'kapasitas' => isset($args['kapasitas']) ? (int) $args['kapasitas'] : null,
                'fasilitas' => collect(explode(',', (string) ($args['fasilitas'] ?? '')))
                    ->map(fn ($f) => trim($f))->filter()->values()->all(),
            ],
            'action_label' => 'Daftarkan ruangan',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function periodBounds(array $args): array
    {
        $mode = (string) ($args['mode'] ?? 'minggu');
        $anchor = ! empty($args['tanggal'])
            ? Carbon::parse((string) $args['tanggal'])->startOfDay()
            : now()->startOfDay();

        return match ($mode) {
            'hari' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay(), 'Harian '.$anchor->toDateString()],
            'bulan' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), 'Bulanan '.$anchor->translatedFormat('F Y')],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek(), 'Mingguan '.$anchor->copy()->startOfWeek()->toDateString().' – '.$anchor->copy()->endOfWeek()->toDateString()],
        };
    }
}
