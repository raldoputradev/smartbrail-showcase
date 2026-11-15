<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\Carbon;

class FingerprintSyncPolicy
{
    public const KEY = 'fingerprint_sync';

    /**
     * @return array{mode: string, burst_until: ?string}
     */
    public static function config(): array
    {
        $raw = SystemSetting::valueFor(self::KEY, []);
        if (! is_array($raw)) {
            $raw = [];
        }

        return [
            'mode' => ($raw['mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
            'burst_until' => $raw['burst_until'] ?? null,
        ];
    }

    public static function isAuto(): bool
    {
        return self::config()['mode'] === 'auto';
    }

    public static function isManual(): bool
    {
        return ! self::isAuto();
    }

    public static function burstActive(): bool
    {
        $until = self::config()['burst_until'];
        if (! $until) {
            return false;
        }

        try {
            return Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Mesin boleh menerima blob template pada request sync pull. */
    public static function allowsDistribution(): bool
    {
        return self::isAuto() || self::burstActive();
    }

    public static function setMode(string $mode): void
    {
        $cfg = self::config();
        $cfg['mode'] = $mode === 'manual' ? 'manual' : 'auto';
        SystemSetting::setValue(self::KEY, $cfg);
    }

    public static function triggerBurst(int $minutes = 20): void
    {
        $cfg = self::config();
        $cfg['burst_until'] = now()->addMinutes(max(1, $minutes))->toIso8601String();
        SystemSetting::setValue(self::KEY, $cfg);
    }

    public static function statusLabel(): string
    {
        if (self::isAuto()) {
            return 'Otomatis — mesin absensi pull template dari server';
        }

        if (self::burstActive()) {
            return 'Manual + distribusi aktif sementara';
        }

        return 'Manual — template tidak dikirim sampai Anda paksa distribusi';
    }
}
