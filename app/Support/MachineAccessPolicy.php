<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\Carbon;

class MachineAccessPolicy
{
    public static function defaults(): array
    {
        return [
            'active' => false,
            'timezone' => 'Asia/Jakarta',
            'absensi' => [
                'enabled' => true,
                'morning_start' => '06:00',
                'morning_end' => '12:00',
                'night_start' => '18:00',
                'night_end' => '22:00',
            ],
            'loker' => [
                'enabled' => true,
                'morning_start' => '06:00',
                'morning_end' => '12:00',
                'night_start' => '18:00',
                'night_end' => '22:00',
            ],
        ];
    }

    public static function config(): array
    {
        $saved = SystemSetting::valueFor('machine_access', []);

        return array_replace_recursive(self::defaults(), is_array($saved) ? $saved : []);
    }

    /**
     * @return array{allowed: bool, message: string, window: string|null, now: string}
     */
    public static function check(string $type, ?Carbon $now = null): array
    {
        $config = self::config();
        $timezone = $config['timezone'] ?: 'Asia/Jakarta';
        $now = ($now ?? now())->copy()->timezone($timezone);
        $current = $now->format('H:i');

        if (!($config['active'] ?? true)) {
            return [
                'allowed' => true,
                'message' => 'Kontrol jam akses mesin tidak aktif.',
                'window' => null,
                'now' => $current,
            ];
        }

        $rules = $config[$type] ?? null;
        if (!$rules || !($rules['enabled'] ?? true)) {
            return [
                'allowed' => false,
                'message' => 'Akses mesin sedang dinonaktifkan oleh admin.',
                'window' => null,
                'now' => $current,
            ];
        }

        $windows = [
            'pagi' => [$rules['morning_start'] ?? '06:00', $rules['morning_end'] ?? '12:00'],
            'malam' => [$rules['night_start'] ?? '18:00', $rules['night_end'] ?? '22:00'],
        ];

        foreach ($windows as $label => [$start, $end]) {
            if (self::timeInWindow($current, $start, $end)) {
                return [
                    'allowed' => true,
                    'message' => "Akses {$type} aktif pada sesi {$label}.",
                    'window' => "{$label} {$start}-{$end}",
                    'now' => $current,
                ];
            }
        }

        $windowText = collect($windows)
            ->map(fn ($range, $label) => "{$label} {$range[0]}-{$range[1]}")
            ->implode(', ');

        return [
            'allowed' => false,
            'message' => "Akses {$type} di luar jadwal aktif ({$windowText}).",
            'window' => $windowText,
            'now' => $current,
        ];
    }

    private static function timeInWindow(string $current, string $start, string $end): bool
    {
        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        return $current >= $start || $current <= $end;
    }
}
