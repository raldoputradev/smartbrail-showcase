<?php

namespace App\Http\Middleware;

use App\Support\WebSessionGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceWebSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && WebSessionGuard::isAbsoluteExpired($request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi login sudah berakhir. Silakan login ulang.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            return redirect()
                ->route('login', ['expired' => 1])
                ->with('session_expired', 'Sesi login sudah berakhir (batas waktu maksimal). Silakan login ulang.');
        }

        return $next($request);
    }
}
