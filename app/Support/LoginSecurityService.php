<?php

namespace App\Support;

use App\Models\AccountApprovalLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class LoginSecurityService
{
    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public const IP_MAX_PER_MINUTE = 20;

    public const NITK_MAX_PER_MINUTE = 10;

    public const SUSPICIOUS_FAILS_24H = 15;

    public const SUSPICIOUS_LOCKOUTS_24H = 3;

    /** Maksimum durasi kunci wajib-unlock admin — mencegah DoS permanen pada akun. */
    public const SUSPICIOUS_UNLOCK_HOURS = 24;

    public function ipBlockedMessage(Request $request): ?string
    {
        $key = $this->ipKey($request);

        if (RateLimiter::tooManyAttempts($key, self::IP_MAX_PER_MINUTE)) {
            return 'Terlalu banyak percobaan login dari jaringan ini. Coba lagi dalam '
                . $this->formatWait(RateLimiter::availableIn($key)) . '.';
        }

        return null;
    }

    public function nitkBlockedMessage(string $nitkNidn): ?string
    {
        $key = $this->nitkThrottleKey($nitkNidn);

        if (RateLimiter::tooManyAttempts($key, self::NITK_MAX_PER_MINUTE)) {
            return 'Terlalu banyak percobaan untuk ID ini. Coba lagi dalam '
                . $this->formatWait(RateLimiter::availableIn($key)) . '.';
        }

        return null;
    }

    public function accountBlockedMessage(User $user): ?string
    {
        // `login_requires_admin_unlock` adalah tanda tinjauan untuk admin, BUKAN
        // pemblokir login — bila memblokir, penyerang anonim bisa mengunci akun
        // korban dan membocorkan keberadaan akun lewat pesan khusus.
        if ($user->login_requires_admin_unlock) {
            $markedAt = $user->login_suspicious_at;
            if ($markedAt && $markedAt->diffInHours(now(), true) >= self::SUSPICIOUS_UNLOCK_HOURS) {
                $user->forceFill(['login_requires_admin_unlock' => false])->save();
            }

            return null;
        }

        if ($user->login_locked_until && $user->login_locked_until->isFuture()) {
            return 'Terlalu banyak percobaan login gagal. Coba lagi dalam '
                . $this->formatWait($user->login_locked_until->diffInSeconds(now())) . '.';
        }

        return null;
    }

    public function nitkCooldownMessage(string $nitkNidn): ?string
    {
        $key = $this->failCountKey($nitkNidn);

        if (!RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            return null;
        }

        return 'Terlalu banyak percobaan login gagal untuk ID ini. Coba lagi dalam '
            . $this->formatWait(RateLimiter::availableIn($key)) . '.';
    }

    /**
     * @return array{user: ?User, message: ?string}
     */
    public function guardBeforeAttempt(Request $request, string $nitkNidn): array
    {
        if ($message = $this->ipBlockedMessage($request)) {
            return ['user' => null, 'message' => $message];
        }

        if ($message = $this->nitkBlockedMessage($nitkNidn)) {
            return ['user' => null, 'message' => $message];
        }

        if ($message = $this->nitkCooldownMessage($nitkNidn)) {
            return ['user' => null, 'message' => $message];
        }

        $user = User::query()->where('nitk_nidn', $nitkNidn)->first();

        if ($user && ($message = $this->accountBlockedMessage($user))) {
            return ['user' => $user, 'message' => $message];
        }

        return ['user' => $user, 'message' => null];
    }

    /**
     * Catat gagal login; kembalikan pesan untuk ditampilkan ke pengguna.
     */
    public function recordFailedAttempt(Request $request, ?User $user, string $nitkNidn): string
    {
        RateLimiter::hit($this->ipKey($request), 60);
        RateLimiter::hit($this->nitkThrottleKey($nitkNidn), 60);

        $failKey = $this->failCountKey($nitkNidn);
        RateLimiter::hit($failKey, 60 * self::LOCK_MINUTES);
        $attempts = RateLimiter::attempts($failKey);

        if ($user && $this->hasLoginSecurityColumns()) {
            $user->refresh();

            if (!$user->login_requires_admin_unlock) {
                $payload = ['failed_login_attempts' => min($attempts, self::MAX_FAILED_ATTEMPTS)];

                if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
                    $payload['failed_login_attempts'] = 0;
                    $payload['login_locked_until'] = now()->addMinutes(self::LOCK_MINUTES);
                    $this->recordLockoutStrike($user);
                }

                $user->forceFill($payload)->save();

                $fail24h = 'login-fail-24h:' . $user->id;
                RateLimiter::hit($fail24h, 86_400);

                if (RateLimiter::attempts($fail24h) >= self::SUSPICIOUS_FAILS_24H) {
                    $this->markSuspicious($user, 'Banyak percobaan login gagal dalam 24 jam terakhir.');
                }
            }
        }

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $wait = $this->formatWait(RateLimiter::availableIn($failKey));

            return 'Terlalu banyak percobaan login gagal. Akun / ID ini dikunci sementara. Coba lagi dalam '
                . $wait . '.';
        }

        $remaining = self::MAX_FAILED_ATTEMPTS - $attempts;

        if ($remaining <= 2) {
            return "NIM / NITK / NIDN atau password salah. Peringatan: sisa {$remaining} percobaan "
                . 'sebelum dikunci ' . self::LOCK_MINUTES . ' menit.';
        }

        return 'NIM / NITK / NIDN atau password salah.';
    }

    public function recordSuccessfulLogin(User $user): void
    {
        if ($this->hasLoginSecurityColumns()) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'login_locked_until' => null,
            ])->save();
        }

        RateLimiter::clear($this->failCountKey((string) $user->nitk_nidn));
        RateLimiter::clear('login-fail-24h:' . $user->id);
    }

    public function adminUnlock(User $target, User $actor, Request $request): void
    {
        if ($this->hasLoginSecurityColumns()) {
            $target->forceFill([
                'failed_login_attempts' => 0,
                'login_locked_until' => null,
                'login_requires_admin_unlock' => false,
                'login_suspicious_at' => null,
            ])->save();
        }

        RateLimiter::clear($this->failCountKey((string) $target->nitk_nidn));
        RateLimiter::clear('login-fail-24h:' . $target->id);
        RateLimiter::clear('login-lockouts-24h:' . $target->id);

        AccountApprovalLog::query()->create([
            'user_id' => $target->id,
            'actor_id' => $actor->id,
            'action' => 'login_unlocked',
            'note' => 'Kunci login dibuka oleh admin.',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    public function isLoginSecurityActive(User $user): bool
    {
        if ($user->login_requires_admin_unlock) {
            return true;
        }

        return $user->login_locked_until && $user->login_locked_until->isFuture();
    }

    private function markSuspicious(User $user, string $reason): void
    {
        if (!$this->hasLoginSecurityColumns() || $user->login_requires_admin_unlock) {
            return;
        }

        $user->forceFill([
            'login_requires_admin_unlock' => true,
            'login_suspicious_at' => now(),
            'login_locked_until' => null,
            'failed_login_attempts' => 0,
        ])->save();

        AccountApprovalLog::query()->create([
            'user_id' => $user->id,
            'actor_id' => null,
            'action' => 'login_suspicious',
            'note' => $reason,
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }

    private function recordLockoutStrike(User $user): void
    {
        $key = 'login-lockouts-24h:' . $user->id;
        RateLimiter::hit($key, 86_400);

        if (RateLimiter::attempts($key) >= self::SUSPICIOUS_LOCKOUTS_24H) {
            $this->markSuspicious(
                $user,
                'Akun terkunci sementara berulang kali dalam 24 jam terakhir.'
            );
        }
    }

    private function hasLoginSecurityColumns(): bool
    {
        static $ok = null;

        if ($ok === null) {
            $ok = Schema::hasColumn('users', 'failed_login_attempts')
                && Schema::hasColumn('users', 'login_locked_until');
        }

        return $ok;
    }

    private function ipKey(Request $request): string
    {
        return 'login-ip:' . $request->ip();
    }

    private function nitkThrottleKey(string $nitkNidn): string
    {
        return 'login-nitk-throttle:' . hash('sha256', mb_strtolower(trim($nitkNidn)));
    }

    private function failCountKey(string $nitkNidn): string
    {
        return 'login-fail:' . hash('sha256', mb_strtolower(trim($nitkNidn)));
    }

    private function formatWait(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds < 60) {
            return $seconds . ' detik';
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes . ' menit';
    }
}
