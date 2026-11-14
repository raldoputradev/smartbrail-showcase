<?php

namespace App\Http\Middleware;

use App\Support\StaffNotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DismissStaffNotifFromQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $key = trim((string) $request->query('dismiss_notif', ''));

        if ($user && $key !== '' && ($user->isLaboran() || $user->isDosen()) && $key !== 'ops-suggest') {
            StaffNotificationService::dismissByKey($user, $key);
        }

        return $next($request);
    }
}
