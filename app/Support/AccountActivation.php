<?php

namespace App\Support;

use App\Models\AccountApprovalLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Aktivasi mandiri akun hasil import & pengelolaan password sementara mahasiswa.
 *
 * - Dosen & laboran hasil import: approval_status = menunggu_aktivasi + is_active = 0 (alur /aktivasi).
 * - Mahasiswa hasil import: aktif (approval_status = approved + is_active = 1) dengan must_change_password = 1
 *   dan password sementara unik sekali pakai.
 */
class AccountActivation
{
    public const STATUS = 'menunggu_aktivasi';

    public const SESSION_KEY = 'account_activation';

    public const SESSION_IDLE_MINUTES = 30;

    public const OTP_MAX_ATTEMPTS = 5;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 12;

    public static function codeTtlDays(): int
    {
        return max(1, (int) config('simalas.activation_code_ttl_days', 14));
    }

    public static function generateCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $raw = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return implode('-', str_split($raw, 4));
    }

    public static function normalizeCode(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $input));
    }

    /**
     * Password sementara mahasiswa yang mudah diketik: nama depan + '-' + 6 karakter acak
     * (contoh: budi-X4K2M9). Unik per akun; bukan nama+NIM; tetap kompatibel normalizeCode.
     */
    public static function generateTemporaryPassword(string $namaLengkap): string
    {
        $first = mb_strtolower(trim((string) (explode(' ', trim($namaLengkap))[0] ?? '')));
        $first = (string) preg_replace('/[^a-z0-9]/', '', $first);
        if ($first === '') {
            $first = 'simalas';
        }
        $first = mb_substr($first, 0, 12);

        $max = strlen(self::ALPHABET) - 1;
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::ALPHABET[random_int(0, $max)];
        }

        return $first . '-' . $suffix;
    }

    /**
     * Buat kode baru untuk akun yang belum aktif (menunggu_aktivasi). Kode lama otomatis tidak berlaku.
     *
     * @return array{code: string, expires_at: CarbonInterface}
     */
    public static function issueCode(User $user, ?User $actor, ?Request $request, string $note): array
    {
        if (!$user->isAwaitingActivation()) {
            throw new \RuntimeException('Kode aktivasi hanya untuk akun yang belum menyelesaikan aktivasi.');
        }

        $code = self::generateCode();
        $expiresAt = now()->addDays(self::codeTtlDays());

        $user->forceFill([
            'password' => self::normalizeCode($code),
            'remember_token' => Str::random(60),
            'mobile_api_token' => null,
            'mobile_token_expires_at' => null,
        ])->save();

        self::revokeActiveTokensAndSessions($user);

        self::log($user, $actor, $request, 'activation_code_issued', $note, [
            'activation_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * Terbitkan password sementara unik untuk mahasiswa (saat import baru atau reset oleh admin).
     *
     * @return array{code: string, expires_at: CarbonInterface}
     */
    public static function issueTemporaryPasswordForMahasiswa(
        User $user,
        ?User $actor,
        ?Request $request,
        string $note,
        bool $isReset = false
    ): array {
        if ($user->role !== 'mahasiswa') {
            throw new \RuntimeException('Password sementara langsung hanya untuk akun mahasiswa.');
        }

        return self::issueTemporaryPassword($user, $actor, $request, $note, $isReset);
    }

    /**
     * Password sementara (semua role hasil import): login langsung aktif, wajib ganti
     * password saat login pertama — pemilik mengamankan akunnya sendiri.
     */
    public static function issueTemporaryPassword(
        User $user,
        ?User $actor,
        ?Request $request,
        string $note,
        bool $isReset = false
    ): array {
        if ($isReset && (!$user->is_active || $user->approval_status !== 'approved')) {
            throw new \RuntimeException('Reset password sementara hanya untuk akun yang aktif dan sudah disetujui.');
        }

        $code = self::generateTemporaryPassword((string) $user->nama_lengkap);
        $expiresAt = now()->addDays(self::codeTtlDays());

        $payload = [
            'password' => self::normalizeCode($code),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
            'mobile_api_token' => null,
            'mobile_token_expires_at' => null,
        ];

        if (Schema::hasColumn('users', 'failed_login_attempts')) {
            $payload['failed_login_attempts'] = 0;
        }
        if (Schema::hasColumn('users', 'login_locked_until')) {
            $payload['login_locked_until'] = null;
        }
        if (Schema::hasColumn('users', 'login_requires_admin_unlock')) {
            $payload['login_requires_admin_unlock'] = false;
        }
        if (Schema::hasColumn('users', 'login_suspicious_at')) {
            $payload['login_suspicious_at'] = null;
        }

        $user->forceFill($payload)->save();

        self::revokeActiveTokensAndSessions($user);

        self::log($user, $actor, $request, 'activation_code_issued', $note, [
            'activation_expires_at' => $expiresAt->toIso8601String(),
            'temporary_password_mode' => $isReset ? 'admin_reset' : 'import',
        ]);

        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * Cabut token OTP/reset email yang menggantung dan hapus baris tabel sessions (bila ada).
     */
    public static function revokeActiveTokensAndSessions(User $user): void
    {
        if (filled($user->email) && Schema::hasTable('email_verification_tokens')) {
            DB::table('email_verification_tokens')
                ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $user->email))])
                ->whereIn('type', ['password_reset', 'account_verify'])
                ->delete();
        }

        $sessionTable = (string) config('session.table', 'sessions');
        if ($sessionTable !== '' && Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Cocokkan password sementara untuk akun yang sedang berstatus must_change_password = true.
     * Normalisasi tanda hubung HANYA berlaku pada mode password sementara ini.
     */
    public static function temporaryPasswordMatches(User $user, string $secret): bool
    {
        if (!$user->mustChangePassword() || $secret === '') {
            return false;
        }

        return Hash::check(self::normalizeCode($secret), (string) $user->password);
    }

    public static function temporaryPasswordExpired(User $user): bool
    {
        if (!$user->mustChangePassword()) {
            return false;
        }

        $expires = self::codeExpiresAt($user);
        if ($expires !== null) {
            return $expires->isPast();
        }

        // Fallback jika riwayat log diarsipkan: hitung dari updated_at akun.
        return $user->updated_at !== null
            && $user->updated_at->lt(now()->subDays(self::codeTtlDays()));
    }

    /**
     * Periksa kelayakan & sub-status akun mahasiswa hasil import lama (approval_status = menunggu_aktivasi).
     *
     * @return array{eligible: bool, mode: 'temp_code'|'personal_password'|'ambiguous'|'ineligible', reason: ?string}
     */
    public static function inspectLegacyStudentImportState(User $user): array
    {
        if ($user->role !== 'mahasiswa' || !$user->isAwaitingActivation() || $user->is_active) {
            return [
                'eligible' => false,
                'mode' => 'ineligible',
                'reason' => self::blockedReason($user),
            ];
        }

        $logs = AccountApprovalLog::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'action', 'created_at', 'subject_meta']);

        if ($logs->isEmpty()) {
            return [
                'eligible' => false,
                'mode' => 'ambiguous',
                'reason' => 'Bukti asal import akun tidak ditemukan. Hubungi admin laboran.',
            ];
        }

        $actions = $logs->pluck('action')->all();
        if (array_intersect($actions, ['registered', 'rejected', 'deactivated']) !== []) {
            return [
                'eligible' => false,
                'mode' => 'ambiguous',
                'reason' => 'Status asal akun tidak konsisten untuk aktivasi otomatis. Hubungi admin laboran.',
            ];
        }

        if (!in_array('admin_created', $actions, true) || !in_array('activation_code_issued', $actions, true)) {
            return [
                'eligible' => false,
                'mode' => 'ambiguous',
                'reason' => 'Bukti asal import akun tidak lengkap. Hubungi admin laboran.',
            ];
        }

        /** @var AccountApprovalLog $latestIssue */
        $latestIssue = $logs->where('action', 'activation_code_issued')->last();

        foreach ($logs->where('action', 'activation_password_set') as $pwdLog) {
            if ($pwdLog->id > $latestIssue->id && $pwdLog->created_at && $latestIssue->created_at && $pwdLog->created_at->lt($latestIssue->created_at)) {
                return [
                    'eligible' => false,
                    'mode' => 'ambiguous',
                    'reason' => 'Urutan waktu riwayat aktivasi akun tidak konsisten. Hubungi admin laboran.',
                ];
            }
            if ($pwdLog->id < $latestIssue->id && $pwdLog->created_at && $latestIssue->created_at && $pwdLog->created_at->gt($latestIssue->created_at)) {
                return [
                    'eligible' => false,
                    'mode' => 'ambiguous',
                    'reason' => 'Urutan waktu riwayat aktivasi akun tidak konsisten. Hubungi admin laboran.',
                ];
            }
        }

        $passwordSetAfter = $logs
            ->where('action', 'activation_password_set')
            ->filter(fn (AccountApprovalLog $log) => $log->id > $latestIssue->id);

        if ($passwordSetAfter->isNotEmpty()) {
            return [
                'eligible' => true,
                'mode' => 'personal_password',
                'reason' => null,
            ];
        }

        return [
            'eligible' => true,
            'mode' => 'temp_code',
            'reason' => null,
        ];
    }

    /**
     * Transisikan akun mahasiswa hasil import lama secara atomik setelah kredensial terverifikasi.
     */
    public static function transitionLegacyImportedMahasiswa(User $user, string $mode, Request $request): User
    {
        return DB::transaction(function () use ($user, $mode, $request) {
            /** @var User $fresh */
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);

            $state = self::inspectLegacyStudentImportState($fresh);
            if (!$state['eligible'] || $state['mode'] !== $mode) {
                throw new \RuntimeException($state['reason'] ?? 'Akun tidak memenuhi syarat transisi otomatis.');
            }

            $mustChange = $mode === 'temp_code';

            $fresh->forceFill([
                'is_active' => true,
                'approval_status' => 'approved',
                'approval_note' => null,
                'approved_by' => null,
                'approved_at' => now(),
                'rejected_at' => null,
                'must_change_password' => $mustChange,
                'remember_token' => Str::random(60),
            ])->save();

            $fresh = FingerIdAllocator::ensureForMahasiswa($fresh);

            if ($mustChange) {
                self::log(
                    $fresh,
                    null,
                    $request,
                    'activation_transitioned',
                    'Akun mahasiswa import lama diaktifkan otomatis saat login dengan password sementara; wajib ganti password.'
                );
            } else {
                self::log(
                    $fresh,
                    null,
                    $request,
                    'activation_completed',
                    'Akun mahasiswa import lama diaktifkan otomatis saat login dengan password pribadi yang sudah dibuat.'
                );
            }

            return $fresh;
        });
    }

    public static function latestIssueLog(User $user): ?AccountApprovalLog
    {
        return AccountApprovalLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'activation_code_issued')
            ->orderByDesc('id')
            ->first();
    }

    /** Pemilik sudah menukar kode terakhir dengan password sendiri. */
    public static function passwordSetSinceLastCode(User $user): bool
    {
        $issued = self::latestIssueLog($user);

        return AccountApprovalLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'activation_password_set')
            ->when($issued, fn ($q) => $q->where('id', '>', $issued->id))
            ->exists();
    }

    public static function codeExpiresAt(User $user): ?Carbon
    {
        $raw = self::latestIssueLog($user)?->subject_meta['activation_expires_at'] ?? null;

        return $raw ? Carbon::parse($raw) : null;
    }

    public static function codeExpired(User $user): bool
    {
        if (self::passwordSetSinceLastCode($user)) {
            return false;
        }
        $expires = self::codeExpiresAt($user);

        return $expires === null || $expires->isPast();
    }

    /**
     * Cocokkan rahasia login untuk akun menunggu aktivasi: kode (huruf besar/kecil, dengan/tanpa
     * tanda hubung) sebelum password dibuat, atau password pemilik setelahnya.
     */
    public static function secretMatches(User $user, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }
        if (self::passwordSetSinceLastCode($user)) {
            return Hash::check($secret, (string) $user->password);
        }

        return Hash::check(self::normalizeCode($secret), (string) $user->password);
    }

    /**
     * Pesan untuk akun dengan kredensial benar yang tidak bisa (lagi) diaktivasi.
     */
    public static function blockedReason(User $user): ?string
    {
        if ($user->isAwaitingActivation()) {
            return null;
        }
        if ($user->approval_status === 'approved' && $user->is_active) {
            return 'Akun ini sudah aktif. Silakan login seperti biasa.';
        }
        if ($user->approval_status === 'approved') {
            return 'Akun ini dinonaktifkan admin. Aktivasi tidak tersedia; hubungi admin laboran.';
        }
        if ($user->approval_status === 'rejected') {
            return 'Akun ini ditolak admin. Hubungi admin laboran.';
        }

        return 'Akun ini berasal dari pendaftaran mandiri dan diproses lewat persetujuan admin, bukan aktivasi.';
    }

    /** Tukar kode aktivasi dengan password milik pemilik akun (kode sekali pakai). */
    public static function setPasswordFromCode(User $user, string $password, Request $request): void
    {
        $user->forceFill([
            'password' => $password,
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        self::log($user, null, $request, 'activation_password_set', 'Pemilik akun membuat password; kode aktivasi tidak berlaku lagi.');
    }

    public static function startSession(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'touched_at' => now()->getTimestamp(),
        ]);
    }

    public static function sessionUser(Request $request): ?User
    {
        $state = $request->session()->get(self::SESSION_KEY);
        if (!is_array($state) || empty($state['user_id'])) {
            return null;
        }

        $idle = now()->getTimestamp() - (int) ($state['touched_at'] ?? 0);
        if ($idle > self::SESSION_IDLE_MINUTES * 60) {
            self::endSession($request);

            return null;
        }

        $user = User::query()->find($state['user_id']);
        if (!$user || !$user->isAwaitingActivation()) {
            self::endSession($request);

            return null;
        }

        $state['touched_at'] = now()->getTimestamp();
        $request->session()->put(self::SESSION_KEY, $state);

        return $user;
    }

    public static function endSession(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** Langkah yang perlu diselesaikan: profil (email + HP) atau otp. */
    public static function nextStep(User $user): string
    {
        if (!filled($user->email) || !filled($user->no_hp)) {
            return 'profil';
        }

        return $user->email_verified_at ? 'selesai' : 'otp';
    }

    /**
     * Akun aktif penuh setelah password, email terverifikasi, dan nomor HP terisi.
     * Tidak ada persetujuan admin tambahan; akun yang dinonaktifkan/ditolak tidak tersentuh.
     */
    public static function complete(User $user, Request $request): User
    {
        return DB::transaction(function () use ($user, $request) {
            /** @var User $fresh */
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);

            if (!$fresh->isAwaitingActivation()) {
                throw new \RuntimeException(self::blockedReason($fresh) ?? 'Akun tidak dalam status menunggu aktivasi.');
            }
            if (!self::passwordSetSinceLastCode($fresh) || !$fresh->email_verified_at || !filled($fresh->no_hp)) {
                throw new \RuntimeException('Lengkapi password, email terverifikasi, dan nomor HP terlebih dahulu.');
            }

            $fresh->forceFill([
                'is_active' => true,
                'approval_status' => 'approved',
                'approval_note' => null,
                'approved_by' => null,
                'approved_at' => now(),
                'rejected_at' => null,
                'must_change_password' => false,
            ])->save();

            if ($fresh->role === 'mahasiswa') {
                $fresh = FingerIdAllocator::ensureForMahasiswa($fresh);
            }

            self::log($fresh, null, $request, 'activation_completed', 'Aktivasi mandiri selesai; akun aktif penuh.');

            return $fresh;
        });
    }

    public static function log(User $user, ?User $actor, ?Request $request, string $action, string $note, array $extraMeta = []): void
    {
        AccountApprovalLog::query()->create([
            'user_id' => $user->id,
            'subject_name' => $user->nama_lengkap,
            'subject_email' => $user->email,
            'subject_nitk_nidn' => $user->nitk_nidn,
            'subject_role' => $user->role,
            'subject_meta' => array_merge([
                'finger_id' => $user->finger_id,
                'kelas' => $user->kelas,
                'tim' => $user->tim,
                'angkatan' => $user->angkatan,
                'semester' => $user->semester,
                'semester_type' => $user->semester_type,
            ], $extraMeta),
            'actor_id' => $actor?->id,
            'action' => $action,
            'note' => $note,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) $request->userAgent() : null,
        ]);
    }

    /** Normalisasi nomor HP: buang spasi, titik, tanda hubung, dan kurung. */
    public static function normalizePhone(?string $input): string
    {
        return (string) preg_replace('/[\s.\-()]+/', '', trim((string) $input));
    }

    public static function phoneIsValid(string $normalized): bool
    {
        return (bool) preg_match('/^\+?[0-9]{9,15}$/', $normalized);
    }
}
