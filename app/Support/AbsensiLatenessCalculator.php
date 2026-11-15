<?php

namespace App\Support;

use App\Models\AbsensiSesiSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Hitung keterlambatan tap masuk & pulang (menit) dan pulang lebih awal terhadap jadwal sesi.
 */
class AbsensiLatenessCalculator
{
    public static function menitTerlambatMasuk(
        CarbonInterface $tap,
        ?AbsensiSesiSetting $setting = null,
        string $shift = 'pagi'
    ): int {
        $setting ??= AbsensiEffectiveSchedule::effectiveSesiSetting($tap, $shift);
        if (! $setting->aktif) {
            return 0;
        }

        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $local = Carbon::instance($tap)->timezone($tz);

        [$jamMasuk] = self::sessionDateTimes($local, $setting);
        $batasAman = $jamMasuk->copy()->addMinutes((int) $setting->toleransi_masuk_menit);

        if ($local->lessThanOrEqualTo($batasAman)) {
            return 0;
        }

        return (int) $jamMasuk->diffInMinutes($local);
    }

    /**
     * Keterlambatan tap pulang terhadap jam_pulang + toleransi_pulang_menit.
     * Tap lebih awal dari jam_pulang tidak dihitung terlambat (lihat menitPulangCepat).
     */
    public static function menitTerlambatPulang(
        CarbonInterface $tap,
        ?AbsensiSesiSetting $setting = null,
        string $shift = 'pagi'
    ): int {
        $setting ??= AbsensiEffectiveSchedule::effectiveSesiSetting($tap, $shift);
        if (! $setting->aktif) {
            return 0;
        }

        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $local = Carbon::instance($tap)->timezone($tz);

        [, $jamPulang] = self::sessionDateTimes($local, $setting);
        $toleransi = (int) ($setting->toleransi_pulang_menit ?? $setting->toleransi_masuk_menit ?? 10);
        $batasAman = $jamPulang->copy()->addMinutes($toleransi);

        if ($local->lessThanOrEqualTo($batasAman)) {
            return 0;
        }

        return (int) $jamPulang->diffInMinutes($local);
    }

    /**
     * Selisih menit ketika tap pulang dilakukan sebelum jam_pulang terjadwal (pulang lebih awal / pulang cepat).
     */
    public static function menitPulangCepat(
        CarbonInterface $tap,
        ?AbsensiSesiSetting $setting = null,
        string $shift = 'pagi'
    ): int {
        $setting ??= AbsensiEffectiveSchedule::effectiveSesiSetting($tap, $shift);
        if (! $setting->aktif) {
            return 0;
        }

        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $local = Carbon::instance($tap)->timezone($tz);

        [, $jamPulang] = self::sessionDateTimes($local, $setting);

        if ($local->greaterThanOrEqualTo($jamPulang)) {
            return 0;
        }

        return (int) $local->diffInMinutes($jamPulang);
    }

    public static function isPulangCepat(
        CarbonInterface $tap,
        ?AbsensiSesiSetting $setting = null,
        string $shift = 'pagi'
    ): bool {
        return self::menitPulangCepat($tap, $setting, $shift) > 0;
    }

    /**
     * Apakah tap pulang melewati jam_pulang + toleransi_pulang_menit (di permukaan mahasiswa ditampilkan sebagai "Melewati jam jadwal").
     */
    public static function isPulangLembur(
        CarbonInterface $tap,
        ?AbsensiSesiSetting $setting = null,
        string $shift = 'pagi'
    ): bool {
        return self::menitTerlambatPulang($tap, $setting, $shift) > 0;
    }

    /**
     * Hitung rentang datetime sesi [jamMasuk, jamPulang] pada tanggal lokal tap.
     * Untuk shift malam yang melewati tengah malam (jam_pulang < jam_masuk),
     * jam_pulang digeser +1 hari.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function sessionDateTimes(CarbonInterface $referenceLocal, AbsensiSesiSetting $setting): array
    {
        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $local = Carbon::instance($referenceLocal)->timezone($tz);
        $dateStr = $local->format('Y-m-d');

        $jamMasuk = Carbon::parse($dateStr . ' ' . $setting->jam_masuk, $tz);
        $jamPulang = Carbon::parse($dateStr . ' ' . $setting->jam_pulang, $tz);

        if ($jamPulang->lessThanOrEqualTo($jamMasuk)) {
            if ($local->hour < 12) {
                $jamMasuk->subDay();
            } else {
                $jamPulang->addDay();
            }
        }

        return [$jamMasuk, $jamPulang];
    }

    /**
     * Waktu paling awal tap masuk diizinkan (pagi: jam_mulai_tap_masuk; malam: 60 menit sebelum jam_masuk).
     */
    public static function tapMasukBukaAt(CarbonInterface $referenceLocal, AbsensiSesiSetting $setting): Carbon
    {
        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $local = Carbon::instance($referenceLocal)->timezone($tz);
        [$jamMasuk] = self::sessionDateTimes($local, $setting);

        if ($setting->shift === 'pagi' && ! empty($setting->jam_mulai_tap_masuk)) {
            $dateStr = $jamMasuk->format('Y-m-d');

            return Carbon::parse($dateStr . ' ' . $setting->jam_mulai_tap_masuk, $tz);
        }

        return $jamMasuk->copy()->subMinutes(60);
    }

    /**
     * Batas waktu sesi ditutup penuh (setelah ini tap tidak diterima dan mahasiswa tanpa tap -> alfa).
     */
    public static function sessionCloseAt(CarbonInterface $referenceLocal, AbsensiSesiSetting $setting): Carbon
    {
        [, $jamPulang] = self::sessionDateTimes($referenceLocal, $setting);

        return $jamPulang->copy()->addMinutes((int) $setting->grace_alfa_menit);
    }

    public static function menitTerlambat(
        CarbonInterface $tap,
        ?AbsensiSesiSetting $setting = null,
        string $shift = 'pagi'
    ): int {
        return self::menitTerlambatMasuk($tap, $setting, $shift);
    }
}
