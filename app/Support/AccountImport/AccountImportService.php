<?php

namespace App\Support\AccountImport;

use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\AccountActivation;
use App\Support\MahasiswaProfileOptions;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import akun: pratinjau (tanpa menulis user) → konfirmasi → buat akun menunggu aktivasi.
 *
 * Status baris: siap | dibuat | duplikat | gagal | dilewati.
 * Akun existing tidak pernah diubah. Kode aktivasi hanya ada di kartu sekali unduh (terenkripsi
 * di cache singkat), tidak pernah di laporan maupun file pratinjau.
 */
class AccountImportService
{
    public const DIR = 'account-imports';

    public const KEEP_DAYS = 7;

    public const CARDS_TTL_MINUTES = 30;

    public const STATUS_LABELS = [
        'siap' => 'Siap dibuat',
        'dibuat' => 'Dibuat — menunggu aktivasi',
        'duplikat' => 'Duplikat',
        'gagal' => 'Gagal validasi',
        'dilewati' => 'Dilewati',
    ];

    public function __construct(private readonly TabularFileReader $reader = new TabularFileReader)
    {
    }

    /** Hak membuat role lewat import = hak "Tambah User" yang sudah ada (laboran + users.create). */
    public static function canImport(?User $actor, string $type): bool
    {
        return $actor !== null
            && in_array($type, AccountImportTemplate::TYPES, true)
            && $actor->is_active
            && $actor->role === 'laboran'
            && PermissionRegistry::can($actor->role, 'users', 'create');
    }

    public function preview(string $type, string $path, string $originalName, User $actor): array
    {
        if (!self::canImport($actor, $type)) {
            throw new ImportFileException('Anda tidak berwenang mengimpor akun '.$type.'.');
        }
        $this->purgeOld();

        $table = $this->reader->read($path, $originalName);
        [$map, $notices] = $this->mapHeaders($type, $table['headers']);

        $rows = [];
        foreach ($table['rows'] as $row) {
            $rows[] = $this->validateRow($type, $map, $row);
        }
        $this->markDuplicates($rows);

        $payload = [
            'token' => Str::random(40),
            'type' => $type,
            'actor_id' => $actor->id,
            'file_name' => mb_substr(basename($originalName), 0, 120),
            'created_at' => now()->toIso8601String(),
            'state' => 'preview',
            'applied_at' => null,
            'notices' => $notices,
            'rows' => $rows,
        ];
        $payload['totals'] = $this->totals($rows);
        $this->store($payload);

        return $payload;
    }

    public function load(string $token, User $actor): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }
        $path = self::DIR.'/'.$token.'.json';
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }
        $payload = json_decode((string) Storage::disk('local')->get($path), true);
        if (!is_array($payload) || (int) ($payload['actor_id'] ?? 0) !== $actor->id) {
            return null;
        }

        return $payload;
    }

    /**
     * Terapkan pratinjau. Validasi diulang per baris; akun existing tidak disentuh.
     *
     * @return array{payload: array, created: int}
     */
    public function apply(string $token, User $actor, Request $request): array
    {
        $lock = Cache::lock('account-import-apply:'.$token, 120);
        if (!$lock->get()) {
            throw new ImportFileException('Import ini sedang diproses. Tunggu sebentar lalu muat ulang halaman.');
        }

        try {
            $payload = $this->load($token, $actor);
            if (!$payload) {
                throw new ImportFileException('Pratinjau tidak ditemukan atau sudah kedaluwarsa. Unggah ulang file.');
            }
            if ($payload['state'] !== 'preview') {
                return ['payload' => $payload, 'created' => 0];
            }

            $type = $payload['type'];
            $prodiIds = array_map('intval', ProgramStudi::query()->where('aktif', true)->pluck('id')->all());
            $cards = [];
            $batch = substr($token, 0, 8);

            foreach ($payload['rows'] as $i => $row) {
                if ($row['status'] !== 'siap') {
                    continue;
                }

                $fresh = User::query()->find($actor->id);
                if (!self::canImport($fresh, $type)) {
                    $payload['rows'][$i] = $this->outcome($row, 'gagal', "Pengimpor tidak lagi berwenang membuat akun {$type}.");
                    continue;
                }

                $values = $row['values'];
                if ($type === 'mahasiswa' && !in_array((int) $values['program_studi_id'], $prodiIds, true)) {
                    $payload['rows'][$i] = $this->outcome($row, 'gagal', 'Program studi sudah tidak aktif sejak pratinjau.');
                    continue;
                }

                try {
                    $card = DB::transaction(function () use ($type, $values, $fresh, $request, $batch) {
                        $exists = User::query()
                            ->whereRaw('LOWER(nitk_nidn) = ?', [mb_strtolower($values['nitk_nidn'])])
                            ->lockForUpdate()
                            ->exists();
                        if ($exists) {
                            return null;
                        }

                        $user = new User;
                        $user->forceFill($this->userAttributes($type, $values));
                        $user->save();

                        AccountActivation::log($user, $fresh, $request, 'admin_created',
                            "Dibuat lewat import akun (batch {$batch}); menunggu aktivasi mandiri oleh pemilik.");
                        $issued = AccountActivation::issueCode($user, $fresh, $request, "Kode aktivasi awal dari import (batch {$batch}).");

                        return [
                            'nitk_nidn' => $user->nitk_nidn,
                            'nama_lengkap' => $user->nama_lengkap,
                            'role' => $user->role,
                            'kode' => $issued['code'],
                            'berlaku_sampai' => $issued['expires_at']->format('Y-m-d H:i'),
                        ];
                    });
                } catch (\Throwable $e) {
                    if (app()->runningUnitTests()) { fwrite(STDERR, $e->getMessage()); }
                    Log::warning('Account import row failed', ['batch' => $batch, 'line' => $row['line'], 'error' => $e->getMessage()]);
                    $payload['rows'][$i] = $this->outcome($row, 'gagal', 'Gagal disimpan ke database; baris lain tetap diproses.');
                    continue;
                }

                if ($card === null) {
                    $payload['rows'][$i] = $this->outcome($row, 'duplikat', 'Sudah terdaftar (dibuat setelah pratinjau); akun existing tidak diubah.');
                    continue;
                }

                $cards[] = $card;
                $payload['rows'][$i] = $this->outcome($row, 'dibuat', 'Akun dibuat; pemilik perlu aktivasi mandiri.');
            }

            $payload['state'] = 'applied';
            $payload['applied_at'] = now()->toIso8601String();
            $payload['totals'] = $this->totals($payload['rows']);
            $payload['cards_available'] = $cards !== [];
            $this->store($payload);

            if ($cards !== []) {
                Cache::put($this->cardsKey($token), Crypt::encryptString(json_encode($cards)), now()->addMinutes(self::CARDS_TTL_MINUTES));
            }

            return ['payload' => $payload, 'created' => count($cards)];
        } finally {
            $lock->release();
        }
    }

    /**
     * Ambil kartu aktivasi satu kali; setelah itu dihapus dari cache dan ditandai di laporan.
     *
     * @return list<array<string, string>>|null
     */
    public function pullCards(string $token, User $actor): ?array
    {
        $payload = $this->load($token, $actor);
        if (!$payload || ($payload['state'] ?? '') !== 'applied') {
            return null;
        }
        $encrypted = Cache::pull($this->cardsKey($token));
        $payload['cards_available'] = false;
        $payload['cards_downloaded_at'] = $payload['cards_downloaded_at'] ?? ($encrypted ? now()->toIso8601String() : null);
        $this->store($payload);
        if (!is_string($encrypted)) {
            return null;
        }

        return json_decode(Crypt::decryptString($encrypted), true) ?: null;
    }

    public function cardsStillAvailable(string $token): bool
    {
        return Cache::has($this->cardsKey($token));
    }

    public function reportCsv(array $payload): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        $idLabel = strtoupper(AccountImportTemplate::identifierColumn($payload['type']));
        fputcsv($out, ['baris_file', $idLabel, 'nama_lengkap', 'role', 'status', 'alasan'], ',', '"', '');
        foreach ($payload['rows'] as $row) {
            fputcsv($out, array_map([self::class, 'safeCell'], [
                (string) $row['line'],
                (string) ($row['identifier'] ?? ''),
                (string) ($row['nama'] ?? ''),
                $payload['type'],
                self::STATUS_LABELS[$row['status']] ?? $row['status'],
                (string) $row['reason'],
            ]), ',', '"', '');
        }
        $t = $payload['totals'];
        fputcsv($out, [], ',', '"', '');
        fputcsv($out, ['TOTAL', (string) $t['total'], 'dibuat='.$t['dibuat'], 'duplikat='.$t['duplikat'], 'gagal='.$t['gagal'], 'dilewati='.$t['dilewati']], ',', '"', '');
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** @param  list<array<string, string>>  $cards */
    public function cardsCsv(array $cards, string $activationUrl): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['nitk_nidn', 'nama_lengkap', 'role', 'kode_aktivasi', 'berlaku_sampai', 'halaman_aktivasi'], ',', '"', '');
        foreach ($cards as $card) {
            fputcsv($out, array_map([self::class, 'safeCell'], [
                $card['nitk_nidn'], $card['nama_lengkap'], $card['role'], $card['kode'], $card['berlaku_sampai'], $activationUrl,
            ]), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Cegah CSV injection saat laporan dibuka di Excel. */
    public static function safeCell(string $value): string
    {
        return ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) ? "'".$value : $value;
    }

    // ------------------------------------------------------------------ internals

    /**
     * @param  list<string>  $headers
     * @return array{0: array<string, int>, 1: list<string>}
     */
    private function mapHeaders(string $type, array $headers): array
    {
        $columns = AccountImportTemplate::columns($type);
        $aliases = AccountImportTemplate::aliases($type);
        $map = [];
        $ignored = [];
        $sensitive = [];
        $otherIds = array_diff(['nim', 'nidn', 'nitk'], [AccountImportTemplate::identifierColumn($type)]);

        foreach ($headers as $index => $raw) {
            $name = AccountImportTemplate::normalizeHeader($raw);
            if ($name === '') {
                continue;
            }
            $name = $aliases[$name] ?? $name;
            if (in_array($name, $otherIds, true)) {
                throw new ImportFileException("Kolom \"{$raw}\" bukan untuk import {$type}. Gunakan template ".AccountImportTemplate::label($type).'.');
            }
            if (isset($columns[$name])) {
                if (isset($map[$name])) {
                    throw new ImportFileException("Kolom \"{$name}\" muncul lebih dari sekali di header.");
                }
                $map[$name] = $index;
            } elseif (in_array($name, AccountImportTemplate::IGNORED_SENSITIVE, true)) {
                $sensitive[] = $raw;
            } else {
                $ignored[] = $raw;
            }
        }

        $missing = [];
        foreach ($columns as $name => [$required]) {
            if ($required && !isset($map[$name])) {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw new ImportFileException('Kolom wajib tidak ada: '.implode(', ', $missing).'. Gunakan template '.AccountImportTemplate::label($type).'.');
        }

        $notices = [];
        if ($sensitive !== []) {
            $notices[] = 'Kolom '.implode(', ', $sensitive).' diabaikan: email, nomor HP, dan password diisi sendiri oleh pemilik akun saat aktivasi; status dan finger ID tidak bisa diatur lewat import.';
        }
        if ($ignored !== []) {
            $notices[] = 'Kolom tidak dikenal diabaikan: '.implode(', ', $ignored).'.';
        }

        return [$map, $notices];
    }

    /**
     * @param  array<string, int>  $map
     * @param  array{line: int, cells: list<array{v: string, issue: ?string}>}  $row
     */
    private function validateRow(string $type, array $map, array $row): array
    {
        $raw = [];
        $issues = [];
        foreach ($map as $name => $index) {
            $cell = $row['cells'][$index] ?? ['v' => '', 'issue' => null];
            $raw[$name] = trim($cell['v']);
            if ($cell['issue'] !== null) {
                $issues[] = "kolom {$name} {$cell['issue']}";
            }
        }

        $idCol = AccountImportTemplate::identifierColumn($type);
        $identifier = $raw[$idCol] ?? '';
        $nama = $raw['nama_lengkap'] ?? '';
        $base = ['line' => $row['line'], 'identifier' => mb_substr($identifier, 0, 60), 'nama' => mb_substr($nama, 0, 120), 'values' => []];

        $allEmpty = $issues === [] && implode('', $raw) === '';
        if ($allEmpty) {
            return $base + ['status' => 'dilewati', 'reason' => 'Baris kosong.'];
        }
        if ($issues !== []) {
            return $base + ['status' => 'gagal', 'reason' => ucfirst(implode('; ', $issues)).'.'];
        }

        $errors = [];
        $idLabel = strtoupper($idCol);
        if ($identifier === '') {
            $errors[] = "{$idLabel} wajib diisi";
        } elseif (mb_strlen($identifier) > 50) {
            $errors[] = "{$idLabel} lebih dari 50 karakter";
        } elseif (preg_match('/[\x00-\x1F\x7F]/u', $identifier)) {
            $errors[] = "{$idLabel} berisi karakter kontrol";
        }
        if ($nama === '') {
            $errors[] = 'nama_lengkap wajib diisi';
        } elseif (mb_strlen($nama) > 255) {
            $errors[] = 'nama_lengkap lebih dari 255 karakter';
        }

        $role = mb_strtolower($raw['role'] ?? '');
        if ($role !== '' && $role !== $type) {
            $errors[] = "kolom role \"{$raw['role']}\" tidak sesuai jenis import {$type} (role di file tidak memberi hak akses)";
        }

        $values = ['nitk_nidn' => $identifier, 'nama_lengkap' => $nama];

        if ($type === 'mahasiswa') {
            $values += $this->validateMahasiswa($raw, $errors);
        }

        if ($errors !== []) {
            return $base + ['status' => 'gagal', 'reason' => ucfirst(implode('; ', $errors)).'.'];
        }

        return array_merge($base, ['values' => $values, 'status' => 'siap', 'reason' => 'Akan dibuat dengan status menunggu aktivasi.']);
    }

    private function validateMahasiswa(array $raw, array &$errors): array
    {
        $out = [];
        foreach (['kelas', 'tim'] as $field) {
            $value = $raw[$field] ?? '';
            if ($value === '') {
                $errors[] = "{$field} wajib diisi";
            } elseif (mb_strlen($value) > 80) {
                $errors[] = "{$field} lebih dari 80 karakter";
            }
            $out[$field] = $value;
        }

        $maxYear = now()->year + 1;
        $angkatan = $raw['angkatan'] ?? '';
        if (!preg_match('/^\d{4}$/', $angkatan) || (int) $angkatan < 2020 || (int) $angkatan > $maxYear) {
            $errors[] = "angkatan harus tahun 2020–{$maxYear}";
        }
        $out['angkatan'] = (int) $angkatan;

        $semester = $raw['semester'] ?? '';
        if (!preg_match('/^[1-8]$/', $semester)) {
            $errors[] = 'semester harus angka 1–8';
        }
        $out['semester'] = (int) $semester;

        $semesterType = mb_strtolower($raw['semester_type'] ?? '');
        if (!in_array($semesterType, ['ganjil', 'genap'], true)) {
            $errors[] = 'semester_type harus ganjil atau genap';
        }
        $out['semester_type'] = $semesterType;

        $shift = mb_strtolower($raw['shift'] ?? '');
        if (!in_array($shift, ['pagi', 'malam'], true)) {
            $errors[] = 'shift harus pagi atau malam';
        }
        $out['shift'] = $shift;

        $prodiInput = $raw['program_studi'] ?? '';
        $prodiId = null;
        if ($prodiInput !== '') {
            $needle = mb_strtolower($prodiInput);
            $prodiId = ProgramStudi::query()
                ->where('aktif', true)
                ->where(fn ($q) => $q->whereRaw('LOWER(kode) = ?', [$needle])->orWhereRaw('LOWER(nama) = ?', [$needle]))
                ->value('id');
            if (!$prodiId) {
                $errors[] = "program_studi \"{$prodiInput}\" tidak ditemukan/aktif";
            }
        } elseif (($out['kelas'] ?? '') !== '') {
            $prodiId = MahasiswaProfileOptions::inferProgramStudiId($out['kelas']);
            if (!$prodiId) {
                $errors[] = 'program studi tidak bisa disimpulkan dari kelas; isi kolom program_studi';
            }
        }
        $out['program_studi_id'] = $prodiId;

        return $out;
    }

    private function markDuplicates(array &$rows): void
    {
        $byKey = [];
        foreach ($rows as $i => $row) {
            if ($row['status'] === 'siap') {
                $byKey[mb_strtolower($row['values']['nitk_nidn'])][] = $i;
            }
        }

        foreach ($byKey as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $lines = implode(', ', array_map(fn ($i) => $rows[$i]['line'], $indexes));
            foreach ($indexes as $i) {
                $rows[$i]['status'] = 'duplikat';
                $rows[$i]['reason'] = "Identitas sama muncul lebih dari sekali di file (baris {$lines}); semua dilewati agar tidak salah pilih.";
                $rows[$i]['values'] = [];
            }
        }

        $keys = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'siap') {
                $keys[] = mb_strtolower($row['values']['nitk_nidn']);
            }
        }
        $existing = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            foreach (User::query()->whereRaw("LOWER(nitk_nidn) IN ({$placeholders})", $chunk)->pluck('nitk_nidn') as $id) {
                $existing[mb_strtolower($id)] = true;
            }
        }
        foreach ($rows as $i => $row) {
            if ($row['status'] === 'siap' && isset($existing[mb_strtolower($row['values']['nitk_nidn'])])) {
                $rows[$i]['status'] = 'duplikat';
                $rows[$i]['reason'] = 'Sudah terdaftar di SIMALAS; akun existing tidak diubah.';
                $rows[$i]['values'] = [];
            }
        }
    }

    private function userAttributes(string $type, array $values): array
    {
        $attrs = [
            'nama_lengkap' => $values['nama_lengkap'],
            'nitk_nidn' => $values['nitk_nidn'],
            'email' => null,
            'role' => $type,
            'is_active' => false,
            'approval_status' => AccountActivation::STATUS,
            'approved_by' => null,
            'approved_at' => null,
            'email_verified_at' => null,
            'finger_id' => null,
            'password' => Str::random(64),
        ];

        if ($type === 'mahasiswa') {
            $meta = MahasiswaProfileOptions::syncKelasMetadata($values['kelas']);
            $attrs += [
                'kelas' => $values['kelas'],
                'tim' => $values['tim'],
                'angkatan' => $values['angkatan'],
                'semester' => $values['semester'],
                'semester_type' => $values['semester_type'],
                'program_studi_id' => $values['program_studi_id'],
                'shift' => $values['shift'],
                'kelas_paralel' => $meta['kelas_paralel'] ?? null,
                'profile_completed_at' => now(),
            ];
        }

        return $attrs;
    }

    private function outcome(array $row, string $status, string $reason): array
    {
        $row['status'] = $status;
        $row['reason'] = $reason;
        $row['values'] = [];

        return $row;
    }

    /** @return array{total: int, siap: int, dibuat: int, duplikat: int, gagal: int, dilewati: int} */
    private function totals(array $rows): array
    {
        $totals = ['total' => count($rows), 'siap' => 0, 'dibuat' => 0, 'duplikat' => 0, 'gagal' => 0, 'dilewati' => 0];
        foreach ($rows as $row) {
            $totals[$row['status']]++;
        }

        return $totals;
    }

    private function store(array $payload): void
    {
        Storage::disk('local')->put(self::DIR.'/'.$payload['token'].'.json', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function cardsKey(string $token): string
    {
        return 'account-import-cards:'.$token;
    }

    private function purgeOld(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();
        foreach ($disk->files(self::DIR) as $file) {
            if (str_ends_with($file, '.json') && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
