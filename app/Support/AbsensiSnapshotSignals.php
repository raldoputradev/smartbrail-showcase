<?php

namespace App\Support;

use App\Events\AbsensiSnapshotWake;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Client\ClientExceptionInterface;
use Pusher\PusherException;
use Throwable;

/**
 * Wake soket dikirim setelah commit dan tidak pernah menggagalkan absensi.
 * Saat Reverb tidak terjangkau, pengiriman dilewati sementara dan halaman memakai polling.
 */
class AbsensiSnapshotSignals
{
    public const BREAKER_KEY = 'simalas:soket:jeda';

    public const LOG_KEY = 'simalas:soket:log';

    public const SINCE_KEY = 'simalas:soket:gagal-sejak';

    public const BREAKER_SECONDS = 30;

    public const LOG_SECONDS = 300;

    /**
     * @param  list<string>  $surfaces
     */
    public static function committed(array $surfaces, string $audience, ?string $eventId = null): void
    {
        $wake = new AbsensiSnapshotWake(
            (string) Str::uuid(),
            array_values($surfaces),
            now('Asia/Jakarta')->toIso8601String(),
            $eventId,
            $audience,
        );

        DB::afterCommit(function () use ($wake): void {
            self::deliver($wake);
        });
    }

    /**
     * @return 'sent'|'skipped'|'failed'
     */
    public static function deliver(AbsensiSnapshotWake $wake): string
    {
        try {
            if (self::cacheHas(self::BREAKER_KEY)) {
                return 'skipped';
            }

            broadcast($wake);
            self::recovered();

            return 'sent';
        } catch (BroadcastException|PusherException|ClientExceptionInterface $e) {
            self::transportFailed($e);

            return 'failed';
        } catch (Throwable $e) {
            self::unexpectedFailed($e);

            return 'failed';
        }
    }

    private static function transportFailed(Throwable $e): void
    {
        self::cachePut(self::BREAKER_KEY, true, self::BREAKER_SECONDS);
        self::cacheAdd(self::SINCE_KEY, now('Asia/Jakarta')->toIso8601String(), 86400);

        if (self::cacheAdd(self::LOG_KEY, true, self::LOG_SECONDS)) {
            Log::warning('Soket Reverb tidak terjangkau; halaman absensi memakai polling sampai tersambung lagi.', [
                'exception' => $e::class,
                'pesan' => self::safeMessage($e),
                'jeda_detik' => self::BREAKER_SECONDS,
            ]);
        }
    }

    private static function unexpectedFailed(Throwable $e): void
    {
        self::cachePut(self::BREAKER_KEY, true, self::BREAKER_SECONDS);

        if (self::cacheAdd(self::LOG_KEY.':tak-terduga', true, self::LOG_SECONDS)) {
            report($e);
        }
    }

    private static function recovered(): void
    {
        $since = self::cachePull(self::SINCE_KEY);
        if ($since !== null) {
            self::cacheForget(self::LOG_KEY);
            Log::info('Soket Reverb tersambung kembali.', ['gagal_sejak' => $since]);
        }
    }

    private static function safeMessage(Throwable $e): string
    {
        $message = preg_replace('/\?\S*/', '?…', $e->getMessage()) ?? '';

        return Str::limit($message, 200);
    }

    private static function cacheHas(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (Throwable) {
            return false;
        }
    }

    private static function cachePut(string $key, mixed $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (Throwable) {
        }
    }

    private static function cacheAdd(string $key, mixed $value, int $seconds): bool
    {
        try {
            return Cache::add($key, $value, $seconds);
        } catch (Throwable) {
            return false;
        }
    }

    private static function cachePull(string $key): mixed
    {
        try {
            return Cache::pull($key);
        } catch (Throwable) {
            return null;
        }
    }

    private static function cacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable) {
        }
    }
}
