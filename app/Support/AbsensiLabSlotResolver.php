<?php

namespace App\Support;

use App\Models\AbsensiSesiSetting;
use Carbon\Carbon;

/**
 * Menentukan slot lab pagi (1–10) dari waktu tap masuk.
 * Slot 1 dimulai jam standar masuk (mis. 07:50); tap lebih awal tetap slot 1.
 */
class AbsensiLabSlotResolver
{
    public const SLOT_COUNT = 10;

    public const SLOT_DURATION_MENIT = 55;

    public static function resolveForShift(AbsensiSesiSetting $setting, Carbon $tap): ?int
    {
        if ($setting->shift !== 'pagi') {
            return null;
        }

        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $tap = $tap->copy()->timezone($tz);

        $jamMasuk = $setting->jamMasukOn($tap);
        $jamPulang = $setting->jamPulangOn($tap);

        if ($tap->greaterThanOrEqualTo($jamPulang)) {
            return null;
        }

        if ($tap->lessThan($jamMasuk)) {
            return 1;
        }

        $menitSetelahStandar = (int) $jamMasuk->diffInMinutes($tap);
        $slot = (int) floor($menitSetelahStandar / self::SLOT_DURATION_MENIT) + 1;

        return min(self::SLOT_COUNT, max(1, $slot));
    }

    /**
     * @return list<array{no: int, mulai: string, selesai: string}>
     */
    public static function slotScheduleForPagi(AbsensiSesiSetting $setting, ?Carbon $onDate = null): array
    {
        $anchor = $setting->timezoneCarbon($onDate);
        $jamMasuk = $setting->jamMasukOn($anchor);
        $rows = [];

        for ($n = 1; $n <= self::SLOT_COUNT; $n++) {
            $mulai = $jamMasuk->copy()->addMinutes(($n - 1) * self::SLOT_DURATION_MENIT);
            $selesai = $mulai->copy()->addMinutes(self::SLOT_DURATION_MENIT - 1);
            $rows[] = [
                'no' => $n,
                'mulai' => $mulai->format('H:i'),
                'selesai' => $selesai->format('H:i'),
            ];
        }

        return $rows;
    }
}
