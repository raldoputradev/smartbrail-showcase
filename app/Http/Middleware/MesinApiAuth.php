<?php

namespace App\Http\Middleware;

use App\Support\MesinApiAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MesinApiAuth
{
    /**
     * @param  string|null  $tipe  absensi|loker — kosong = semua tipe mesin (heartbeat)
     */
    public function handle(Request $request, Closure $next, ?string $tipe = null): Response
    {
        [$mesin, $error] = MesinApiAuthenticator::resolve(
            $request,
            $tipe !== null && $tipe !== '' ? $tipe : null
        );

        if ($error !== null) {
            return $error;
        }

        $request->attributes->set('mesin_iot', $mesin);

        return $next($request);
    }
}
