<?php

namespace App\Support\AccountImport;

use App\Models\ProgramStudi;
use App\Models\RuanganPetugas;
use App\Models\User;
use App\Support\AccountActivation;
use App\Support\FingerIdAllocator;
use App\Support\MahasiswaProfileOptions;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import akun: pratinjau (tanpa menulis user) → konfirmasi → buat akun.
 *
 * - Mahasiswa: langsung aktif (approved + is_active = 1) dengan password sementara unik (must_change_password = 1).
 * - Dosen & laboran: menunggu aktivasi mandiri (approval_status = menunggu_aktivasi + is_active = 0).
 * Akun existing tidak pernah diubah. Kredensial sementara hanya ada di kartu sekali unduh.
 */
class AccountImportService
{
    public const DIR = 'account-imports';

    public const KEEP_DAYS = 7;

    public const CARDS_TTL_MINUTES = 30;

    public const STATUS_LABELS = [
        'siap' => 'Siap dibuat',
        'dibuat' => 'Dibuat — aktif (password sementara)',
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
            && in_array($actor->role, ['admin', 'laboran'], true)
            && PermissionRegistry::can($actor->role, 'users', 'create');
    }

    /** Hak menerbitkan ulang kode aktivasi / mereset password sementara = laboran aktif + users.update. */
    public static function canManageTemporaryCredentials(?User $actor, User $target): bool
    {
        if ($actor === null || !$actor->is_active || ! in_array($actor->role, ['admin', 'laboran'], true)) {
            return false;
        }
        if ($actor->id === $target->id) {
            return false;
        }

        return PermissionRegistry::can($actor->role, 'users', 'update');
    }

    public function preview(string $type, string $path, string $originalName, User $actor): array
    {
        if (!self::canImport($actor, $type)) {
            throw new ImportFileException('Anda tidak berwenang mengimpor akun '.$type.'.');
        }
        $this->purgeOld();

        $table = $this->reader->read($path, $originalName, $this->expectedHeaders($type));
        [$map, $notices] = $this->mapHeaders($type, $table['headers']);

        $rows = [];
        foreach ($table['rows'] as $row) {
            $rows[] = $this->validateRow($type, $map, $row);
        }
        $this->markDuplicates($type, $rows);

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
                $rowType = $values['role'] ?? $type;

                // Petugas/CS: bukan akun portal — record ruangan_petugas untuk akses mesin.
                if ($type === 'petugas') {
                    try {
                        $created = DB::transaction(function () use ($values) {
                            $exists = RuanganPetugas::query()
                                ->whereRaw('LOWER(id_internal) = ?', [mb_strtolower($values['id_internal'])])
                                ->lockForUpdate()
                                ->exists();
                            if ($exists) {
                                return false;
                            }
                            RuanganPetugas::create([
                                'nama' => $values['nama_lengkap'],
                                'id_internal' => $values['id_internal'],
                                'tipe_kredensial' => $values['tipe_kredensial'],
                                'kredensial_hash' => $this->hashPetugasKredensial($values['tipe_kredensial'], $values['kredensial']),
                                'status' => 'aktif',
                                'berlaku_mulai' => $values['berlaku_mulai'] !== '' ? $values['berlaku_mulai'] : null,
                                'berlaku_selesai' => $values['berlaku_selesai'] !== '' ? $values['berlaku_selesai'] : null,
                                'jadwal' => [],
                            ]);

                            return true;
                        });
                    } catch (\Throwable $e) {
                        Log::warning('Petugas import row failed', ['batch' => $batch, 'line' => $row['line'], 'error' => $e->getMessage()]);
                        $payload['rows'][$i] = $this->outcome($row, 'gagal', 'Gagal disimpan ke database; baris lain tetap diproses.');
                        continue;
                    }

                    $payload['rows'][$i] = $created
                        ? $this->outcome($row, 'dibuat', 'Petugas dibuat; tanpa akun portal — jadwal ruangan diatur di halaman Petugas Ruangan.')
                        : $this->outcome($row, 'duplikat', 'ID internal sudah dipakai petugas lain (dibuat setelah pratinjau); data tidak diubah.');
                    continue;
                }

                if ($rowType === 'mahasiswa' && !in_array((int) $values['program_studi_id'], $prodiIds, true)) {
                    $payload['rows'][$i] = $this->outcome($row, 'gagal', 'Program studi sudah tidak aktif sejak pratinjau.');
                    continue;
                }

                $createdUser = null;
                try {
                    $card = DB::transaction(function () use ($rowType, $values, $fresh, $request, $batch, &$createdUser) {
                        $exists = User::query()
                            ->whereRaw('LOWER(nitk_nidn) = ?', [mb_strtolower($values['nitk_nidn'])])
                            ->lockForUpdate()
                            ->exists();
                        if ($exists) {
                            return null;
                        }

                        $user = new User;
                        $user->forceFill($this->userAttributes($rowType, $values));
                        $user->save();

                        if ($rowType === 'mahasiswa') {
                            $user = FingerIdAllocator::ensureForMahasiswa($user);
                        }
                        $user = \App\Support\KartuAksesAllocator::ensure($user);
                        AccountActivation::log(
                            $user,
                            $fresh,
                            $request,
                            'admin_created',
                            "Dibuat lewat import akun (batch {$batch}); aktif dengan password sementara (wajib ganti password saat login pertama)."
                        );
                        $issued = $rowType === 'mahasiswa'
                            ? AccountActivation::issueTemporaryPasswordForMahasiswa(
                                $user,
                                $fresh,
                                $request,
                                "Password sementara awal dari import (batch {$batch}).",
                                false
                            )
                            : AccountActivation::issueTemporaryPassword(
                                $user,
                                $fresh,
                                $request,
                                "Password sementara awal dari import (batch {$batch}).",
                                false
                            );

                        $createdUser = $user;

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
                $reason = 'Akun dibuat; login pertama memakai password sementara (wajib ganti password). Kendala login dilaporkan ke laboran/dosen.';

                // Tautkan tim hasil import ke proyek PBL yang cocok (nama/kode) → PblTeamMember.
                if ($createdUser && $createdUser->role === 'mahasiswa' && ($values['tim'] ?? '') !== '') {
                    $project = \App\Models\PblProject::query()
                        ->where('nama', $values['tim'])
                        ->orWhere('kode', $values['tim'])
                        ->first();
                    if ($project) {
                        \App\Models\PblTeamMember::firstOrCreate(
                            ['pbl_project_id' => $project->id, 'user_id' => $createdUser->id],
                            ['role' => \App\Models\PblTeamMember::ROLE_ANGGOTA, 'assigned_by' => $fresh->id]
                        );
                        $createdUser->tim = $project->nama;
                        $createdUser->shift = $project->shift_sesi ?: $createdUser->shift;
                        $createdUser->save();
                        $reason .= " Masuk tim PBL '{$project->nama}'.";
                    } else {
                        $reason .= " Tim '{$values['tim']}' belum ada sebagai proyek PBL — buat proyeknya di menu Proyek PBL lalu assign ulang.";
                    }
                }

                $payload['rows'][$i] = $this->outcome($row, 'dibuat', $reason);
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
            $rowRole = $row['role'] ?? $payload['type'];
            $statusLabel = ($row['status'] ?? '') === 'dibuat' && $rowRole === 'petugas'
                ? 'Dibuat — petugas ruangan'
                : (($row['status'] ?? '') === 'dibuat'
                    ? 'Dibuat — aktif (wajib ganti password)'
                    : (self::STATUS_LABELS[$row['status']] ?? $row['status']));
            fputcsv($out, array_map([self::class, 'safeCell'], [
                (string) $row['line'],
                (string) ($row['identifier'] ?? ''),
                (string) ($row['nama'] ?? ''),
                $rowRole,
                $statusLabel,
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

    /**
     * @param  list<array<string, string>>  $cards
     * @param  array<string, string>  $loginUrls  peta role => URL halaman login ('default' sebagai fallback)
     */
    public function cardsCsv(array $cards, array $loginUrls): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['nitk_nidn', 'nama_lengkap', 'role', 'password_sementara', 'berlaku_sampai', 'halaman_login'], ',', '"', '');
        foreach ($cards as $card) {
            $url = $loginUrls[$card['role'] ?? ''] ?? $loginUrls['default'] ?? '';
            fputcsv($out, array_map([self::class, 'safeCell'], [
                $card['nitk_nidn'], $card['nama_lengkap'], $card['role'], $card['kode'], $card['berlaku_sampai'], $url,
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
    /** Nama header yang dikenali untuk deteksi sheet & baris header pada file ber-format bebas. */
    private function expectedHeaders(string $type): array
    {
        $names = array_merge(
            array_keys(AccountImportTemplate::columns($type)),
            array_keys(AccountImportTemplate::aliases($type)),
            AccountImportTemplate::IGNORED_SENSITIVE,
            ['nim', 'nidn', 'nitk', 'id']
        );

        return array_fill_keys($names, true);
    }

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
            if (isset($columns[$name])) {
                if (isset($map[$name])) {
                    throw new ImportFileException("Kolom \"{$name}\" muncul lebih dari sekali di header.");
                }
                $map[$name] = $index;
            } elseif (in_array($name, $otherIds, true)) {
                throw new ImportFileException("Kolom \"{$raw}\" bukan untuk import {$type}. Gunakan template ".AccountImportTemplate::label($type).'.');
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
        if ($type === 'semua' && ! isset($map['nim']) && ! isset($map['nidn']) && ! isset($map['nitk'])) {
            throw new ImportFileException('Sediakan minimal satu kolom identitas: nim, nidn, atau nitk.');
        }

        $notices = [];
        if ($sensitive !== []) {
            $notices[] = 'Kolom '.implode(', ', $sensitive).' diabaikan: email, nomor HP, dan password diisi sendiri oleh pemilik akun; status dan finger ID tidak bisa diatur lewat import.';
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
        $nama = $raw['nama_lengkap'] ?? '';
        $base = ['line' => $row['line'], 'identifier' => '', 'nama' => mb_substr($nama, 0, 120), 'values' => []];

        $allEmpty = $issues === [] && implode('', $raw) === '';
        if ($allEmpty) {
            return $base + ['status' => 'dilewati', 'reason' => 'Baris kosong.'];
        }
        // Baris penanda template yang hanya mengisi kolom role (tanpa data lain) bukan data akun.
        $nonRoleFilled = array_diff_key(array_filter($raw, fn ($v) => $v !== ''), ['role' => true]);
        if ($issues === [] && $nonRoleFilled === []) {
            return $base + ['status' => 'dilewati', 'reason' => 'Baris penanda template (hanya kolom role terisi) — dilewati.'];
        }
        // Baris contoh template (nama diawali "CONTOH") tidak pernah dibuatkan akun.
        if (str_starts_with(mb_strtolower(trim($nama)), 'contoh')) {
            return $base + ['status' => 'dilewati', 'reason' => 'Baris contoh dari template — dilewati otomatis.'];
        }
        if ($issues !== []) {
            return $base + ['status' => 'gagal', 'reason' => ucfirst(implode('; ', $issues)).'.'];
        }

        // Semua Role: kolom identitas dipilih dari yang terisi (nim=mahasiswa, nidn=dosen, nitk=laboran).
        if ($type === 'semua') {
            $filledId = [];
            foreach (['nim' => 'mahasiswa', 'nidn' => 'dosen', 'nitk' => 'laboran'] as $c => $r) {
                if (($raw[$c] ?? '') !== '') {
                    $filledId[$c] = $r;
                }
            }
            if (count($filledId) !== 1) {
                return $base + ['status' => 'gagal', 'reason' => count($filledId) === 0
                    ? 'Isi salah satu kolom identitas (nim, nidn, atau nitk).'
                    : 'Isi tepat satu kolom identitas — baris ini mengisi '.count($filledId).' kolom.', 'role' => 'semua'];
            }
            $idCol = array_key_first($filledId);
        }

        $identifier = $raw[$idCol] ?? '';
        $base['identifier'] = mb_substr($identifier, 0, 60);

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

        if ($type === 'petugas') {
            return $this->validatePetugas($raw, $base, $errors);
        }

        $role = mb_strtolower($raw['role'] ?? '');
        $rowType = $type;
        if ($type === 'semua') {
            $rowType = match ($idCol) {
                'nim' => 'mahasiswa',
                'nidn' => 'dosen',
                'nitk' => 'laboran',
                default => 'semua',
            };
            if ($role !== '' && $role !== $rowType) {
                $errors[] = "kolom role \"{$raw['role']}\" tidak cocok dengan kolom identitas {$idCol} ({$rowType})";
            }
        } elseif ($role !== '' && $role !== $type) {
            $errors[] = "kolom role \"{$raw['role']}\" tidak sesuai jenis import {$type} (role di file tidak memberi hak akses)";
        }

        $values = ['nitk_nidn' => $identifier, 'nama_lengkap' => $nama, 'role' => $rowType];

        if ($rowType === 'mahasiswa') {
            $values += $this->validateMahasiswa($raw, $errors);
        }

        if ($errors !== []) {
            return $base + ['status' => 'gagal', 'reason' => ucfirst(implode('; ', $errors)).'.', 'role' => $rowType];
        }

        $readyReason = 'Akan dibuat aktif dengan password sementara (wajib ganti password saat login pertama).';

        return array_merge($base, ['values' => $values, 'status' => 'siap', 'reason' => $readyReason, 'role' => $rowType]);
    }

    /** Validasi baris petugas/CS — record ruangan_petugas, bukan akun portal. */
    private function validatePetugas(array $raw, array $base, array $errors): array
    {
        $tipe = mb_strtolower($raw['tipe_kredensial'] ?? '');
        $autoPin = false;
        if ($tipe === '') {
            $tipe = 'pin';
            $autoPin = true;
        } elseif (! in_array($tipe, ['pin', 'rfid', 'fingerprint'], true)) {
            $errors[] = 'tipe_kredensial harus pin, rfid, atau fingerprint';
        }
        $kredensial = $raw['kredensial'] ?? '';
        if ($kredensial === '') {
            if (! $autoPin && $tipe !== 'pin') {
                $errors[] = 'kredensial wajib diisi untuk tipe '.$tipe;
            } else {
                $kredensial = (string) random_int(100000, 999999);
                $autoPin = true;
            }
        } elseif (mb_strlen($kredensial) > 128) {
            $errors[] = 'kredensial lebih dari 128 karakter';
        }
        if ($base['nama'] !== '' && mb_strlen($base['nama']) > 120) {
            $errors[] = 'nama_lengkap lebih dari 120 karakter';
        }
        foreach (['berlaku_mulai', 'berlaku_selesai'] as $f) {
            $v = $raw[$f] ?? '';
            if ($v !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $errors[] = "{$f} harus format YYYY-MM-DD";
            }
        }
        if (($raw['berlaku_mulai'] ?? '') !== '' && ($raw['berlaku_selesai'] ?? '') !== ''
            && $raw['berlaku_selesai'] < $raw['berlaku_mulai']) {
            $errors[] = 'berlaku_selesai harus sama atau setelah berlaku_mulai';
        }

        if ($errors !== []) {
            return $base + ['status' => 'gagal', 'reason' => ucfirst(implode('; ', $errors)).'.', 'role' => 'petugas'];
        }

        return array_merge($base, [
            'values' => [
                'id_internal' => $base['identifier'],
                'nama_lengkap' => $base['nama'],
                'tipe_kredensial' => $tipe,
                'kredensial' => $kredensial,
                'berlaku_mulai' => $raw['berlaku_mulai'] ?? '',
                'berlaku_selesai' => $raw['berlaku_selesai'] ?? '',
            ],
            'status' => 'siap',
            'reason' => 'Akan dibuat sebagai petugas ruangan (tanpa akun portal); jadwal diatur di halaman Petugas Ruangan.'
                .($autoPin ? ' Kredensial kosong — PIN acak dibuat otomatis, atur ulang di halaman Petugas Ruangan.' : ''),
            'role' => 'petugas',
        ]);
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

        // Normalisasi tim: terima "KODE | Nama" dan petakan kode → nama proyek resmi.
        if (($out['tim'] ?? '') !== '') {
            if (str_contains($out['tim'], '|')) {
                $out['tim'] = trim((string) preg_replace('/^[^|]+\|\s*/', '', $out['tim']));
            }
            static $pblByNama = null;
            static $pblByKode = null;
            if ($pblByNama === null) {
                $pblByNama = [];
                $pblByKode = [];
                foreach (\App\Models\PblProject::query()->get(['kode', 'nama']) as $proj) {
                    $pblByNama[mb_strtolower((string) $proj->nama)] = (string) $proj->nama;
                    $pblByKode[mb_strtolower((string) $proj->kode)] = (string) $proj->nama;
                }
            }
            $key = mb_strtolower($out['tim']);
            $out['tim'] = $pblByNama[$key] ?? $pblByKode[$key] ?? $out['tim'];
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
            // Terima kode, nama, atau format gabungan "kode - nama" dari dropdown template.
            $candidates = [mb_strtolower($prodiInput)];
            if (str_contains($prodiInput, ' - ')) {
                foreach (explode(' - ', $prodiInput, 2) as $part) {
                    $part = mb_strtolower(trim($part));
                    if ($part !== '') {
                        $candidates[] = $part;
                    }
                }
            }
            $prodiId = ProgramStudi::query()
                ->where('aktif', true)
                ->where(function ($q) use ($candidates) {
                    foreach (array_unique($candidates) as $needle) {
                        $q->orWhereRaw('LOWER(kode) = ?', [$needle])
                            ->orWhereRaw('LOWER(nama) = ?', [$needle]);
                    }
                })
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

    private function rowKey(array $row): string
    {
        return mb_strtolower($row['values']['nitk_nidn'] ?? $row['values']['id_internal'] ?? '');
    }

    private function markDuplicates(string $type, array &$rows): void
    {
        $byKey = [];
        foreach ($rows as $i => $row) {
            if ($row['status'] === 'siap') {
                $byKey[$this->rowKey($row)][] = $i;
            }
        }

        foreach ($byKey as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $lines = implode(', ', array_map(fn ($i) => $rows[$i]['line'], $indexes));
            foreach ($indexes as $i) {
                $rows[$i]['status'] = 'duplikat';
                $rows[$i]['reason'] = "Identitas \"{$rows[$i]['identifier']}\" muncul lebih dari sekali di file (baris {$lines}); semua dilewati agar tidak salah pilih.";
                $rows[$i]['values'] = [];
            }
        }

        // Cek ke database: baris 'siap' MAUPUN 'gagal' — akun yang sudah ada
        // tetap ditandai duplikat walau barisnya punya masalah validasi lain.
        $keys = [];
        foreach ($rows as $row) {
            if (in_array($row['status'], ['siap', 'gagal'], true)) {
                $key = mb_strtolower($row['identifier'] ?? '');
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
        $existing = [];
        if ($type === 'petugas') {
            foreach (array_chunk($keys, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                foreach (RuanganPetugas::query()->whereRaw("LOWER(id_internal) IN ({$placeholders})", $chunk)->get(['id_internal', 'nama']) as $p) {
                    $existing[mb_strtolower($p->id_internal)] = ['label' => 'petugas', 'nama' => (string) $p->nama];
                }
            }
        } else {
            foreach (array_chunk($keys, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                foreach (User::query()->whereRaw("LOWER(nitk_nidn) IN ({$placeholders})", $chunk)->get(['nitk_nidn', 'nama_lengkap', 'role']) as $u) {
                    $existing[mb_strtolower($u->nitk_nidn)] = ['label' => (string) $u->role, 'nama' => (string) $u->nama_lengkap];
                }
            }
        }
        $idLabel = strtoupper(AccountImportTemplate::identifierColumn($type));
        foreach ($rows as $i => $row) {
            if (! in_array($row['status'], ['siap', 'gagal'], true)) {
                continue;
            }
            $key = mb_strtolower($row['identifier'] ?? '');
            if ($key === '' || ! isset($existing[$key])) {
                continue;
            }
            $hit = $existing[$key];
            $who = $type === 'petugas'
                ? "ID {$row['identifier']} sudah dipakai petugas {$hit['nama']}"
                : "{$idLabel} {$row['identifier']} sudah terdaftar sebagai {$hit['label']} ({$hit['nama']})";
            $rows[$i]['status'] = 'duplikat';
            $rows[$i]['reason'] = "{$who}; data tidak diubah.";
            $rows[$i]['values'] = [];
        }

        // Peringatan nama sama + identitas beda — kemungkinan salah ketik NIM/NIDN.
        // Tidak memblokir (nama kembar itu sah), hanya menandai agar admin memeriksa.
        if ($type !== 'petugas') {
            $names = [];
            foreach ($rows as $row) {
                if ($row['status'] === 'siap') {
                    $n = mb_strtolower(trim($row['nama'] ?? ''));
                    if ($n !== '') {
                        $names[] = $n;
                    }
                }
            }
            if ($names !== []) {
                $sameName = [];
                foreach (array_chunk(array_unique($names), 500) as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    foreach (User::query()->whereRaw("LOWER(nama_lengkap) IN ({$placeholders})", $chunk)->get(['nitk_nidn', 'nama_lengkap', 'role', 'kelas', 'angkatan']) as $u) {
                        $sameName[mb_strtolower($u->nama_lengkap)][] = $u;
                    }
                }
                foreach ($rows as $i => $row) {
                    if ($row['status'] !== 'siap') {
                        continue;
                    }
                    $idKey = mb_strtolower($row['identifier'] ?? '');
                    $others = array_filter(
                        $sameName[mb_strtolower(trim($row['nama'] ?? ''))] ?? [],
                        fn ($u) => mb_strtolower($u->nitk_nidn) !== $idKey
                    );
                    if ($others === []) {
                        continue;
                    }

                    // Nama + kelas + angkatan sama persis → hampir pasti orang yang sama
                    // dengan identitas salah ketik: tolak sebagai duplikat.
                    $kelasIn = mb_strtolower(trim((string) ($row['values']['kelas'] ?? '')));
                    $angkatanIn = (string) ($row['values']['angkatan'] ?? '');
                    $twin = $kelasIn !== '' ? array_filter($others, fn ($u) =>
                        mb_strtolower(trim((string) $u->kelas)) === $kelasIn
                        && (string) $u->angkatan === $angkatanIn
                    ) : [];
                    if ($twin !== []) {
                        $t = reset($twin);
                        $rows[$i]['status'] = 'duplikat';
                        $rows[$i]['reason'] = "{$row['identifier']} ditolak: nama + kelas + angkatan sama persis dengan akun {$t->nitk_nidn} ({$t->role}) — kemungkinan orang yang sama dengan identitas salah ketik; data tidak diubah. Jika memang beda orang, buat akun lewat menu pengguna secara manual.";
                        $rows[$i]['values'] = [];
                        continue;
                    }

                    $list = implode(', ', array_map(fn ($u) => "{$u->nitk_nidn} ({$u->role})", $others));
                    $rows[$i]['reason'] .= " PERINGATAN: nama yang sama sudah terdaftar dengan identitas berbeda ({$list}) — pastikan bukan orang yang sama sebelum lanjut.";
                }
            }
        }
    }

    private function userAttributes(string $type, array $values): array
    {
        $rowType = $values['role'] ?? $type;
        $isMahasiswa = $rowType === 'mahasiswa';

        $attrs = [
            'nama_lengkap' => $values['nama_lengkap'],
            'nitk_nidn' => $values['nitk_nidn'],
            'email' => null,
            'role' => $rowType,
            'is_active' => true,
            'approval_status' => 'approved',
            'approved_by' => null,
            'approved_at' => now(),
            'email_verified_at' => null,
            'finger_id' => null,
            'password' => Str::random(64),
            'must_change_password' => true,
        ];

        if ($isMahasiswa) {
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

    private function hashPetugasKredensial(string $tipe, string $nilai): string
    {
        return match ($tipe) {
            'rfid' => hash('sha256', Str::upper(trim($nilai))),
            'fingerprint' => $nilai,
            default => Hash::make($nilai),
        };
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
