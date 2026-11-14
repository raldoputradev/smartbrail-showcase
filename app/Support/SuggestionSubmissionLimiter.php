<?php

namespace App\Support;

use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SuggestionSubmissionLimiter
{
    /**
     * @return string|null Pesan error untuk pengguna, atau null jika boleh lanjut.
     */
    public static function checkPublic(Request $request, string $email, string $pesan): ?string
    {
        $cfg = config('suggestions.public', []);
        $ipMax = max(1, (int) ($cfg['ip_max_per_hour'] ?? 3));
        $emailMax = max(1, (int) ($cfg['email_max_per_day'] ?? 2));
        $dupHours = max(1, (int) ($cfg['duplicate_within_hours'] ?? 24));

        if ($msg = self::rateLimited('suggest-public-ip:' . self::ipKey($request), $ipMax, 3600)) {
            return $msg;
        }

        $emailNorm = self::normalizeEmail($email);
        if ($msg = self::rateLimited('suggest-public-email:' . hash('sha256', $emailNorm), $emailMax, 86_400)) {
            return $msg;
        }

        if (self::hasRecentDuplicatePublic($emailNorm, $pesan, $dupHours)) {
            return 'Pesan yang sama sudah dikirim baru-baru ini. Tunggu beberapa jam atau ubah isi pesan.';
        }

        return null;
    }

    public static function recordPublicHit(Request $request, string $email): void
    {
        RateLimiter::hit('suggest-public-ip:' . self::ipKey($request), 3600);
        RateLimiter::hit(
            'suggest-public-email:' . hash('sha256', self::normalizeEmail($email)),
            86_400
        );
    }

    /**
     * @return string|null
     */
    public static function checkAccount(int $userId, string $pesan): ?string
    {
        $cfg = config('suggestions.account', []);
        $hourMax = max(1, (int) ($cfg['max_per_hour'] ?? 8));
        $dayMax = max(1, (int) ($cfg['max_per_day'] ?? 15));
        $dupHours = max(1, (int) ($cfg['duplicate_within_hours'] ?? 12));

        if ($msg = self::rateLimited('suggest-account-hour:' . $userId, $hourMax, 3600)) {
            return $msg;
        }

        if ($msg = self::rateLimited('suggest-account-day:' . $userId, $dayMax, 86_400)) {
            return $msg;
        }

        if (self::hasRecentDuplicateAccount($userId, $pesan, $dupHours)) {
            return 'Pesan yang sama sudah Anda kirim baru-baru ini. Tunggu atau ubah isi pesan.';
        }

        return null;
    }

    public static function recordAccountHit(int $userId): void
    {
        RateLimiter::hit('suggest-account-hour:' . $userId, 3600);
        RateLimiter::hit('suggest-account-day:' . $userId, 86_400);
    }

    private static function rateLimited(string $key, int $maxAttempts, int $decaySeconds): ?string
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return null;
        }

        return 'Terlalu banyak pengiriman. Coba lagi dalam ' . self::formatWait(RateLimiter::availableIn($key)) . '.';
    }

    private static function hasRecentDuplicatePublic(string $emailNorm, string $pesan, int $hours): bool
    {
        $body = self::normalizeMessage($pesan);

        return Suggestion::query()
            ->where('source', 'public')
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
            ->whereRaw('TRIM(pesan) = ?', [$body])
            ->exists();
    }

    private static function hasRecentDuplicateAccount(int $userId, string $pesan, int $hours): bool
    {
        $body = self::normalizeMessage($pesan);

        return Suggestion::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereRaw('TRIM(pesan) = ?', [$body])
            ->exists();
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private static function normalizeMessage(string $pesan): string
    {
        return preg_replace('/\s+/u', ' ', trim($pesan)) ?? trim($pesan);
    }

    private static function ipKey(Request $request): string
    {
        return hash('sha256', (string) ($request->ip() ?: 'anon'));
    }

    private static function formatWait(int $seconds): string
    {
        if ($seconds >= 3600) {
            $h = (int) ceil($seconds / 3600);

            return $h . ' jam';
        }
        if ($seconds >= 60) {
            $m = (int) ceil($seconds / 60);

            return $m . ' menit';
        }

        return max(1, $seconds) . ' detik';
    }
}
