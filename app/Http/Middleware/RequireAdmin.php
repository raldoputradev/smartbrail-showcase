<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kewenangan Admin (sistem/akun/perangkat) — terpisah dari role laboran operasional.
 * Satu akun boleh memegang keduanya: laboran + is_admin. Flag tidak bisa dinaikkan
 * lewat request/import (bukan fillable).
 */
class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Aksi ini memerlukan kewenangan admin.');
        }

        return $next($request);
    }
}
