<?php

namespace App\Services\AdminAi;

use App\Models\User;

/**
 * Menyamarkan identitas mahasiswa sebelum teks dikirim ke provider AI.
 *
 * Satu instance = satu giliran percakapan: placeholder konsisten di pesan, history,
 * dan hasil tool. Nilai aslinya hanya disimpan di memori server untuk dikembalikan
 * ke argumen tool lokal dan ke teks yang tampil ke pengguna.
 */
final class CopilotPrivacyFilter
{
    private const FAILED = '[TEKS_DISAMARKAN]';

    private const STOPWORDS = [
        'mahasiswa', 'dosen', 'laboran', 'admin', 'staf', 'absensi', 'absen', 'hadir', 'kehadiran', 'lengkap',
        'tidak', 'alfa', 'masuk', 'pulang', 'terlambat', 'belum', 'tap', 'laporan', 'rekap', 'ringkas',
        'ringkasan', 'bulan', 'minggu', 'hari', 'ini', 'itu', 'lalu', 'kemarin', 'besok', 'tim', 'kelas',
        'sesi', 'pagi', 'malam', 'data', 'cari', 'carikan', 'nama', 'bernama', 'yang', 'dan', 'atau',
        'dengan', 'untuk', 'dari', 'pada', 'berapa', 'siapa', 'apa', 'bagaimana', 'kenapa', 'mengapa',
        'tolong', 'buat', 'buatkan', 'tampilkan', 'lihat', 'cek', 'status', 'semua', 'periode', 'tanggal',
        'januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober',
        'november', 'desember', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'simalas',
        'copilot', 'gemini', 'excel', 'word', 'cetak', 'unduh', 'grafik', 'tren', 'mesin', 'lab', 'sakit',
        'izin', 'libur', 'nim', 'email', 'akun', 'aktif', 'nonaktif', 'jumlah', 'total', 'orang', 'dia',
        'mereka', 'saja', 'juga', 'lagi', 'sudah', 'bisa', 'apakah', 'mohon', 'detail', 'daftar',
    ];

    /** @var array<string, string> placeholder => nilai asli */
    private array $map = [];

    /** @var array<string, string> kunci normalisasi => placeholder */
    private array $byValue = [];

    /** @var array<string, int> */
    private array $counter = [];

    private ?string $namePattern = null;

    /** @var list<string> */
    private array $knownIds = [];

    private string $pending = '';

    private bool $booted = false;

    /**
     * @param  list<string>|null  $names  nama mahasiswa (null = ambil dari database)
     * @param  list<string>|null  $ids  NIM/email yang dikenal (null = ambil dari database)
     * @param  list<string>|null  $extraStopwords  token kelas/tim yang bukan nama
     */
    public function __construct(private ?array $names = null, private ?array $ids = null, private ?array $extraStopwords = null) {}

    public function redact(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        $this->boot();

        $steps = [
            fn (string $t) => preg_replace_callback(
                '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
                fn ($m) => $this->placeholder('EMAIL', $m[0]),
                $t
            ),
            fn (string $t) => $this->knownIds === [] ? $t : preg_replace_callback(
                '/(?<![\p{L}\p{N}_])(?:'.implode('|', $this->knownIds).')(?![\p{L}\p{N}_])/iu',
                fn ($m) => $this->placeholder(preg_match('/@/', $m[0]) ? 'EMAIL' : 'NIM', $m[0]),
                $t
            ),
            fn (string $t) => preg_replace_callback(
                '/(?<![\p{L}\p{N}_\-])\d{5,20}(?![\p{N}])/u',
                fn ($m) => $this->placeholder('NIM', $m[0]),
                $t
            ),
            fn (string $t) => $this->namePattern === null ? $t : preg_replace_callback(
                $this->namePattern,
                fn ($m) => $this->placeholder('NAMA', $m[0]),
                $t
            ),
            fn (string $t) => preg_replace_callback(
                '/\b(bernama|atas\s+nama|namanya|nama\s*:)(\s+)([\p{L}\']+(?:\s+[\p{L}\']+){0,2})/iu',
                fn ($m) => $m[1].$m[2].$this->leadingName($m[3]),
                $t
            ),
            fn (string $t) => preg_replace_callback(
                '/\b(mahasiswa|saudara|saudari|sdr\.?|si)(\s+)(\p{Lu}[\p{L}\']+(?:\s+\p{Lu}[\p{L}\']+){0,3})(?![\p{N}\-])/u',
                fn ($m) => $m[1].$m[2].$this->leadingName($m[3]),
                $t
            ),
        ];

        foreach ($steps as $step) {
            $next = $step($text);
            if (! is_string($next)) {
                return self::FAILED;
            }
            $text = $next;
        }

        return $text;
    }

    /**
     * Samarkan semua string di dalam struktur (hasil tool, argumen, dll). Angka tetap.
     */
    public function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redact($value);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->redactValue($v), $value);
        }
        if ($value instanceof \stdClass) {
            $out = new \stdClass();
            foreach (get_object_vars($value) as $k => $v) {
                $out->{$k} = $this->redactValue($v);
            }

            return $out;
        }

        return $value;
    }

    public function restore(string $text): string
    {
        return $this->map === [] ? $text : strtr($text, $this->map);
    }

    public function restoreValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->restore($value);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->restoreValue($v), $value);
        }

        return $value;
    }

    /**
     * Kembalikan placeholder pada teks bertahap; potongan "[NAMA_" yang belum lengkap ditahan.
     */
    public function restoreStream(string $delta): string
    {
        $buffer = $this->pending.$delta;
        $this->pending = '';
        if ($this->map !== [] && preg_match('/\[[A-Z_0-9]{0,14}$/', $buffer, $m)) {
            $this->pending = $m[0];
            $buffer = substr($buffer, 0, -strlen($m[0]));
        }

        return $this->restore($buffer);
    }

    public function flushStream(): string
    {
        $rest = $this->pending;
        $this->pending = '';

        return $this->restore($rest);
    }

    /**
     * @return array<string, int> jumlah placeholder per jenis (untuk log/uji, tanpa nilai asli)
     */
    public function stats(): array
    {
        return $this->counter;
    }

    private function leadingName(string $words): string
    {
        $parts = preg_split('/(\s+)/u', $words, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $name = '';
        $i = 0;
        for (; $i < count($parts); $i += 2) {
            $word = $parts[$i];
            if (str_starts_with($word, '[') || in_array(mb_strtolower($word), $this->stopwords(), true)) {
                break;
            }
            $name .= ($name === '' ? '' : ($parts[$i - 1] ?? ' ')).$word;
        }
        if ($name === '') {
            return $words;
        }

        return $this->placeholder('NAMA', $name).implode('', array_slice($parts, $i - 1));
    }

    private function placeholder(string $kind, string $original): string
    {
        $key = $kind.':'.mb_strtolower(preg_replace('/\s+/u', ' ', trim($original)) ?? $original);
        if (isset($this->byValue[$key])) {
            return $this->byValue[$key];
        }
        $n = ($this->counter[$kind] ?? 0) + 1;
        $this->counter[$kind] = $n;
        $ph = '['.$kind.'_'.$n.']';
        $this->byValue[$key] = $ph;
        $this->map[$ph] = $original;

        return $ph;
    }

    /**
     * @return list<string>
     */
    private function stopwords(): array
    {
        return array_merge(self::STOPWORDS, $this->extraStopwords ?? []);
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        if ($this->names === null || $this->ids === null || $this->extraStopwords === null) {
            $rows = User::query()->where('role', 'mahasiswa')->get(['nama_lengkap', 'nitk_nidn', 'email', 'kelas', 'tim']);
            $this->names ??= $rows->pluck('nama_lengkap')->filter()->map(fn ($v) => (string) $v)->values()->all();
            $this->ids ??= $rows->flatMap(fn ($u) => [(string) $u->nitk_nidn, (string) $u->email])->filter()->values()->all();
            $this->extraStopwords ??= $rows->flatMap(fn ($u) => preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($u->kelas.' '.$u->tim)) ?: [])
                ->filter()->unique()->values()->all();
        }

        $this->knownIds = collect($this->ids)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => mb_strlen($v) >= 4)
            ->unique()
            ->sortByDesc(fn ($v) => mb_strlen($v))
            ->map(fn ($v) => preg_quote($v, '/'))
            ->values()
            ->all();

        $stop = $this->stopwords();
        $full = collect($this->names)
            ->map(fn ($n) => trim(preg_replace('/\s+/u', ' ', (string) $n) ?? ''))
            ->filter(fn ($n) => mb_strlen($n) >= 3);
        $tokens = $full
            ->flatMap(fn ($n) => preg_split('/[^\p{L}\']+/u', mb_strtolower($n)) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, $stop, true));
        $alternatives = $full
            ->map(fn ($n) => implode('\s+', array_map(fn ($w) => preg_quote($w, '/'), explode(' ', $n))))
            ->merge($tokens->map(fn ($t) => preg_quote($t, '/')))
            ->unique()
            ->sortByDesc(fn ($a) => strlen($a))
            ->values();

        $this->namePattern = $alternatives->isEmpty()
            ? null
            : '/(?<![\p{L}\p{N}_\[])(?:'.$alternatives->implode('|').')(?:[ \t]+(?:'.$alternatives->implode('|').'))*(?![\p{L}\p{N}_])/iu';
    }
}
