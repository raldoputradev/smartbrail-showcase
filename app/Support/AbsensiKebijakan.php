<?php

namespace App\Support;

use App\Models\AbsensiKalenderLibur;
use App\Models\SystemSetting;
use Carbon\Carbon;

/**
 * Kebijakan hari wajib absen: harian (setiap hari) atau hanya Kamis (default lab).
 */
class AbsensiKebijakan
{
    public const MODE_HARIAN = 'harian';

    public const MODE_KAMIS = 'kamis';

    /** @return array{mode: string, hari_wajib: list<int>} */
    public static function config(): array
    {
        $defaults = [
            'mode' => self::MODE_KAMIS,
            'hari_wajib' => [Carbon::THURSDAY],
        ];

        $stored = SystemSetting::valueFor('absensi_kebijakan', $defaults);

        return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
    }

    public static function mode(): string
    {
        $mode = (string) (self::config()['mode'] ?? self::MODE_KAMIS);

        return in_array($mode, [self::MODE_HARIAN, self::MODE_KAMIS], true) ? $mode : self::MODE_KAMIS;
    }

    public static function isHarian(): bool
    {
        return self::mode() === self::MODE_HARIAN;
    }

    /**
     * Hari terjadwal menurut kebijakan (tanpa cek libur).
     * Mode kamis → hanya Kamis; mode harian → setiap hari.
     */
    public static function isHariTerjadwal(string|Carbon $date): bool
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        if (self::isHarian()) {
            return true;
        }

        $hariWajib = array_map(
            'intval',
            (array) (self::config()['hari_wajib'] ?? [Carbon::THURSDAY])
        );

        return in_array((int) $date->dayOfWeek, $hariWajib, true);
    }

    /**
     * Hari ini (atau tanggal tertentu) wajib absen & bisa kena alfa jika tidak tap.
     */
    public static function isHariWajib(string|Carbon $date): bool
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        if (AbsensiKalenderLibur::isLibur($date->toDateString())) {
            return false;
        }

        return self::isHariTerjadwal($date);
    }

    public static function labelHariWajib(): string
    {
        if (self::isHarian()) {
            return 'setiap hari';
        }

        $map = [
            Carbon::SUNDAY => 'Minggu',
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
        ];

        $hari = array_map(
            'intval',
            (array) (self::config()['hari_wajib'] ?? [Carbon::THURSDAY])
        );

        $labels = [];
        foreach ($hari as $d) {
            if (isset($map[$d])) {
                $labels[] = $map[$d];
            }
        }

        return $labels !== [] ? implode(', ', $labels) : 'Kamis';
    }

    public static function labelMode(): string
    {
        return self::isHarian()
            ? 'Absensi wajib setiap hari (harian)'
            : 'Absensi wajib hanya hari Kamis';
    }

    public static function setMode(string $mode): void
    {
        $mode = $mode === self::MODE_HARIAN ? self::MODE_HARIAN : self::MODE_KAMIS;
        $cfg = self::config();
        $cfg['mode'] = $mode;
        SystemSetting::setValue('absensi_kebijakan', $cfg);
    }
}
