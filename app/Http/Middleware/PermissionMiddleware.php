<?php

namespace App\Http\Middleware;

use App\Support\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Enforce menu/action permissions from role_permissions.
     */
    public function handle(Request $request, Closure $next, string $menuKey, string $action = 'view'): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($action !== 'view' && !PermissionRegistry::can($user->role, $menuKey, 'view')) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses fitur ini.');
        }

        if (!PermissionRegistry::can($user->role, $menuKey, $action)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses fitur ini.');
        }

        return $next($request);
    }
}
