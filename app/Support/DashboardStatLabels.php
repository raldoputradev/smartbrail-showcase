<?php

namespace App\Support;

/**
 * Teks keterangan kartu statistik dashboard (admin & dosen).
 */
class DashboardStatLabels
{
    public static function previousPeriodShort(string $mode): string
    {
        return match ($mode) {
            'hari' => 'kemarin',
            'minggu' => 'minggu lalu',
            'bulan' => 'bulan lalu',
            'tahun' => 'tahun lalu',
            default => 'periode sebelumnya',
        };
    }

    public static function currentPeriodShort(string $mode): string
    {
        return match ($mode) {
            'hari' => 'hari ini',
            'minggu' => 'minggu ini',
            'bulan' => 'bulan ini',
            'tahun' => 'tahun ini',
            default => 'periode ini',
        };
    }

    /**
     * Keterangan kartu tap absensi (angka besar = total tap periode aktif).
     */
    public static function tapAbsensiMeta(int $delta, int $previousCount, string $mode): string
    {
        $scope = self::currentPeriodShort($mode);

        return 'Tap sidik jari ' . $scope . ' · ' . self::countComparePhrase($delta, $previousCount, $mode, 'tap');
    }

    /**
     * Keterangan kartu % kehadiran (dosen).
     */
    public static function persenHadirMeta(float $delta): string
    {
        $abs = abs($delta);
        $formatted = rtrim(rtrim(number_format($abs, 1, '.', ''), '0'), '.');

        if ($delta == 0.0) {
            return 'Persentase sama dengan periode sebelumnya';
        }

        if ($delta > 0) {
            return 'Naik ' . $formatted . ' poin dibanding periode sebelumnya';
        }

        return 'Turun ' . $formatted . ' poin dibanding periode sebelumnya';
    }

    public static function deltaToneClass(int|float $delta): string
    {
        $n = (float) $delta;
        if ($n == 0.0) {
            return '';
        }

        return $n > 0 ? 'up' : 'down';
    }

    private static function countComparePhrase(int $delta, int $previousCount, string $mode, string $unit): string
    {
        $prevLabel = self::previousPeriodShort($mode);
        $abs = abs($delta);

        if ($delta === 0) {
            return 'sama seperti ' . $prevLabel . ' (' . $previousCount . ' ' . $unit . ')';
        }

        if ($delta > 0) {
            return 'naik ' . $abs . ' ' . $unit . ' dari ' . $prevLabel . ' (' . $previousCount . ' ' . $unit . ')';
        }

        return 'turun ' . $abs . ' ' . $unit . ' dari ' . $prevLabel . ' (' . $previousCount . ' ' . $unit . ')';
    }
}
