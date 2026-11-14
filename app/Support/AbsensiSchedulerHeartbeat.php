<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\Carbon;

class AbsensiSchedulerHeartbeat
{
    public const SETTING_KEY = 'absensi_scheduler_heartbeat';

    public static function touch(string $command, array $meta = []): void
    {
        SystemSetting::setValue(self::SETTING_KEY, [
            'at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
            'command' => $command,
            'meta' => $meta,
        ]);
    }

    /**
     * @return array{at: ?Carbon, command: ?string, meta: array<string, mixed>, minutes_ago: ?int, is_stale: bool, is_ok: bool}
     */
    public static function status(int $staleAfterMinutes = 20): array
    {
        $raw = SystemSetting::valueFor(self::SETTING_KEY);
        $at = null;
        $command = null;
        $meta = [];

        if (is_array($raw)) {
            $command = isset($raw['command']) ? (string) $raw['command'] : null;
            $meta = is_array($raw['meta'] ?? null) ? $raw['meta'] : [];
            if (!empty($raw['at'])) {
                try {
                    $at = Carbon::parse($raw['at'])->timezone('Asia/Jakarta');
                } catch (\Throwable) {
                    $at = null;
                }
            }
        }

        $minutesAgo = $at ? (int) $at->diffInMinutes(now()->timezone('Asia/Jakarta')) : null;
        $isStale = $at === null || ($minutesAgo !== null && $minutesAgo > $staleAfterMinutes);

        return [
            'at' => $at,
            'command' => $command,
            'meta' => $meta,
            'minutes_ago' => $minutesAgo,
            'is_stale' => $isStale,
            'is_ok' => !$isStale,
        ];
    }
}
