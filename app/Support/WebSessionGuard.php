<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class WebSessionGuard
{
    public static function stampLogin(Request $request, ?User $user = null): void
    {
        $request->session()->put('web_logged_in_at', now()->timestamp);

        $authUser = $user ?? $request->user();
        if ($authUser instanceof User) {
            $request->session()->put('web_password_user_id', (int) $authUser->getKey());
            $request->session()->put('web_password_hash', (string) $authUser->getAuthPassword());
        }

        $hours = (int) config('session_guard.absolute_hours', 8);
        if ($hours > 0) {
            $request->session()->put(
                'web_absolute_expires_at',
                now()->addHours($hours)->timestamp
            );
        } else {
            $request->session()->forget('web_absolute_expires_at');
        }
    }

    /**
     * Deteksi apakah password akun telah direset/diubah sejak sesi ini dimulai.
     * Bekerja di seluruh SESSION_DRIVER (database, file, array, cookie, redis).
     */
    public static function hasPasswordHashMismatch(Request $request, User $user): bool
    {
        $currentHash = (string) $user->getAuthPassword();
        $sessionUserId = $request->session()->get('web_password_user_id');
        $sessionHash = $request->session()->get('web_password_hash');

        if ((int) $sessionUserId !== (int) $user->getKey() || ! is_string($sessionHash) || $sessionHash === '') {
            $request->session()->put('web_password_user_id', (int) $user->getKey());
            $request->session()->put('web_password_hash', $currentHash);

            return false;
        }

        return ! hash_equals($sessionHash, $currentHash);
    }

    public static function isAbsoluteExpired(Request $request): bool
    {
        $expires = $request->session()->get('web_absolute_expires_at');

        return $expires !== null && now()->timestamp >= (int) $expires;
    }

    public static function absoluteExpiresAt(Request $request): ?\Carbon\Carbon
    {
        $expires = $request->session()->get('web_absolute_expires_at');

        return $expires ? \Carbon\Carbon::createFromTimestamp((int) $expires) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function pingPayload(Request $request): array
    {
        $absolute = self::absoluteExpiresAt($request);

        return [
            'ok' => true,
            'idle_minutes' => (int) config('session_guard.idle_minutes'),
            'absolute_expires_at' => $absolute?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
