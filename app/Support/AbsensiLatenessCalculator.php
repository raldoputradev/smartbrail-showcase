<?php

namespace App\Support;

use App\Models\AbsensiSesiSetting;
use Carbon\Carbon;

class AbsensiLatenessCalculator
{
    public static function menitTerlambatMasuk(AbsensiSesiSetting $setting, Carbon $tap): int
    {
        if ($setting->shift === 'pagi') {
            $slot = AbsensiLabSlotResolver::resolveForShift($setting, $tap);
            if ($slot === 1) {
                return 0;
            }
        }

        $batasAman = $setting->batasAmanMasukOn($tap);

        return $tap->greaterThan($batasAman) ? (int) $batasAman->diffInMinutes($tap) : 0;
    }

    /**
     * @return array{menit_terlambat: int, pulang_lembur: bool}
     */
    public static function pulangMetrics(AbsensiSesiSetting $setting, Carbon $tap): array
    {
        $batasAmanPulang = $setting->batasAmanPulangOn($tap);
        $tutupSesi = $setting->tutupSesiOn($tap);
        $pulangLembur = $tap->greaterThan($tutupSesi);
        $menitTerlambat = 0;
        if (!$pulangLembur && $tap->greaterThan($batasAmanPulang)) {
            $menitTerlambat = (int) $batasAmanPulang->diffInMinutes($tap);
        }

        return [
            'menit_terlambat' => $menitTerlambat,
            'pulang_lembur' => $pulangLembur,
        ];
    }
}
