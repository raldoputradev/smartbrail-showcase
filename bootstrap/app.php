<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Bukti cron VPS jalan (heartbeat readiness admin)
        $schedule->call(function () {
            \App\Support\AbsensiSchedulerHeartbeat::touch('schedule:run');
        })->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

        // Setelah jam pulang + grace per sesi (lihat Atur Absensi)
        $schedule->command('absensi:close-sessions')->everyFifteenMinutes();
        // Cadangan: tutup hari kemarin jika ada sesi terlewat
        $schedule->command('absensi:generate-alfa')->dailyAt('00:30');
        $schedule->command('fingerprint:prune-sync-logs --days=30')->weeklyOn(0, '03:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\DismissStaffNotifFromQuery::class,
            \App\Http\Middleware\EnforceWebSessionTimeout::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'perm' => \App\Http\Middleware\PermissionMiddleware::class,
            'approved' => \App\Http\Middleware\EnsureAccountApproved::class,
            'mobile.auth' => \App\Http\Middleware\EnsureMobileToken::class,
            'mesin.auth' => \App\Http\Middleware\MesinApiAuth::class,
            'no.back' => \App\Http\Middleware\PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Kode aktivasi tidak boleh ikut tersimpan sebagai old input di sesi.
        $exceptions->dontFlash(['kode_aktivasi']);

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($e instanceof \Illuminate\Contracts\Encryption\DecryptException
                && ! $request->is('api/*')
                && ! $request->expectsJson()) {
                try {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                } catch (\Throwable) {
                    // Cookie sesi rusak — abaikan jika session belum terbaca.
                }

                return redirect()->route('login')
                    ->with('error', 'Sesi Anda kedaluwarsa. Silakan masuk kembali.');
            }

            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $msg = $e->getMessage();
            if ($e instanceof \Illuminate\Database\QueryException
                || $e instanceof \PDOException
                || str_contains($msg, 'SQLSTATE[HY000] [2002]')
                || str_contains($msg, 'actively refused')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database MySQL tidak aktif. Buka XAMPP Control Panel → Start MySQL, lalu coba lagi.',
                ], 503);
            }

            return null;
        });
    })->create();
