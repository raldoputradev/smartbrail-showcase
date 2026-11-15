<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->mustChangePassword()) {
            return $next($request);
        }

        if ($request->routeIs([
            'mahasiswa.password.change',
            'mahasiswa.password.update',
            'account.password.change',
            'account.password.update',
            'logout',
            'mahasiswa.logout',
            'session.ping',
        ])) {
            return $next($request);
        }

        $changeRoute = $user->isMahasiswa() ? 'mahasiswa.password.change' : 'account.password.change';
        $message = 'Wajib mengganti password sementara Anda sebelum mengakses halaman lain.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'code' => 'PASSWORD_CHANGE_REQUIRED',
                'message' => $message,
                'redirect' => route($changeRoute),
            ], 403);
        }

        return redirect()
            ->route($changeRoute)
            ->with('info', $message);
    }
}
