<?php

namespace App\Support;

use App\Models\AccountApprovalLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Aktivasi mandiri akun hasil import.
 *
 * Status akun: approval_status = menunggu_aktivasi + is_active = 0. Kode aktivasi hanya
 * disimpan sebagai hash di kolom password; kode dibatalkan saat pemilik membuat password
 * atau saat admin membuat kode baru. Kode tidak pernah ditulis ke log maupun laporan import.
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
     * Buat kode baru untuk akun yang belum aktif. Kode lama otomatis tidak berlaku.
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

        $user->forceFill(['password' => self::normalizeCode($code)])->save();

        self::log($user, $actor, $request, 'activation_code_issued', $note, [
            'activation_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return ['code' => $code, 'expires_at' => $expiresAt];
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
        $user->forceFill(['password' => $password])->save();
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
