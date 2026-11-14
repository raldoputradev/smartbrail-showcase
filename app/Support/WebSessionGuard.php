<?php

namespace App\Support;

use Illuminate\Http\Request;

class WebSessionGuard
{
    public static function stampLogin(Request $request): void
    {
        $request->session()->put('web_logged_in_at', now()->timestamp);

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
