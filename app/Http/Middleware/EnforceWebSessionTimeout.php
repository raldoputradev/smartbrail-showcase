<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\WebSessionGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceWebSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $freshUser = User::query()->find(Auth::id());
            $absoluteExpired = WebSessionGuard::isAbsoluteExpired($request);
            $passwordChanged = $freshUser && WebSessionGuard::hasPasswordHashMismatch($request, $freshUser);

            // "Tetap masuk" maksimal 30 hari: sesi yang dibuat dari cookie recaller
            // wajib memiliki penanda remember_until yang masih berlaku.
            $rememberExpired = Auth::viaRemember()
                && (int) $request->cookies->get('remember_until', '0') <= now()->timestamp;
            if ($rememberExpired && $freshUser) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('info', 'Masa "Tetap masuk" 30 hari telah berakhir. Silakan login ulang.')
                    ->withCookies([
                        \Illuminate\Support\Facades\Cookie::forget('remember_until'),
                    ]);
            }

            if (! $freshUser || $absoluteExpired || $passwordChanged) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = $passwordChanged
                    ? 'Sesi berakhir karena kredensial akun telah diperbarui atau direset. Silakan login ulang.'
                    : 'Sesi login sudah berakhir (batas waktu maksimal). Silakan login ulang.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'code' => 'SESSION_EXPIRED',
                    ], 401);
                }

                return redirect()
                    ->route('login', ['expired' => 1])
                    ->with('session_expired', $message);
            }

            Auth::setUser($freshUser);

            // Presence: sentuh last_seen_at paling lambat 1x/60 dtk — tanpa query
            // tambahan per request (freshUser sudah diambil di atas).
            $lastSeen = $freshUser->last_seen_at;
            if ($lastSeen === null || $lastSeen->lt(now()->subSeconds(60))) {
                $freshUser->timestamps = false;
                $freshUser->forceFill(['last_seen_at' => now()])->save();
                $freshUser->timestamps = true;
            }
        }

        return $next($request);
    }
}
