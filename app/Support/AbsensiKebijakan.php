<?php

namespace App\Support;

use App\Models\AbsensiKalenderLibur;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Kebijakan absensi lab:
 * - Default mengikuti jadwal kuliah lab (mis. hanya Kamis).
 * - Mode mingguan per hari (mis. 9 SKS / 3 Hari: Rabu 07:50–17:00, Kamis 07:50–17:00, Jumat 07:50–12:00)
 *   dengan tanggal mulai berlaku (effective_from) agar riwayat lama tidak berubah otomatis.
 */
class AbsensiKebijakan
{
    public const SETTING_KEY = 'absensi_kebijakan';

    public const MODE_KAMIS = 'kamis';
    public const MODE_HARIAN = 'harian';
    public const MODE_MINGGUAN = 'jadwal_mingguan';

    public static function defaultConfig(): array
    {
        return [
            // Default = pola target 9 SKS (Rabu, Kamis, Jumat). Karena effective_from = null,
            // mode ini berstatus "menunggu tanggal efektif" sehingga kebijakan sebelumnya
            // (legacy) tetap dipakai sampai admin menetapkan tanggal mulai berlaku.
            'mode' => self::MODE_MINGGUAN,
            // Carbon dayOfWeek: 0=Minggu .. 4=Kamis .. 6=Sabtu
            'hari_wajib' => [4],
            'effective_from' => null,
            'legacy_mode' => self::MODE_KAMIS,
            'legacy_hari_wajib' => [4],
            'jadwal_mingguan' => AbsensiEffectiveSchedule::defaultWeeklyDays(),
        ];
    }

    public static function config(): array
    {
        $saved = SystemSetting::valueFor(self::SETTING_KEY, self::defaultConfig());
        if (! is_array($saved)) {
            return self::defaultConfig();
        }

        $merged = array_merge(self::defaultConfig(), $saved);
        $merged['jadwal_mingguan'] = AbsensiEffectiveSchedule::normalizeWeeklyDays(
            is_array($merged['jadwal_mingguan'] ?? null) ? $merged['jadwal_mingguan'] : []
        );

        if ($merged['mode'] === self::MODE_MINGGUAN) {
            $activeDays = [];
            foreach ($merged['jadwal_mingguan'] as $dow => $rule) {
                if (! empty($rule['aktif'])) {
                    $activeDays[] = (int) $dow;
                }
            }
            $merged['hari_wajib'] = $activeDays !== [] ? $activeDays : [3, 4, 5];
        }

        return $merged;
    }

    public static function mode(): string
    {
        $m = self::config()['mode'] ?? self::MODE_KAMIS;

        return in_array($m, [self::MODE_KAMIS, self::MODE_HARIAN, self::MODE_MINGGUAN], true)
            ? $m
            : self::MODE_KAMIS;
    }

    public static function setMode(string $mode): array
    {
        return self::savePolicy([
            'mode' => $mode,
        ]);
    }

    /**
     * Save attendance policy including optional weekly per-day rules and effective_from date.
     *
     * @param  array{
     *   mode?: string,
     *   effective_from?: ?string,
     *   jadwal_mingguan?: array<mixed>
     * }  $input
     */
    public static function savePolicy(array $input): array
    {
        $current = self::config();
        $mode = $input['mode'] ?? $current['mode'] ?? self::MODE_KAMIS;
        if (! in_array($mode, [self::MODE_KAMIS, self::MODE_HARIAN, self::MODE_MINGGUAN], true)) {
            $mode = self::MODE_KAMIS;
        }

        $jadwalMingguan = isset($input['jadwal_mingguan']) && is_array($input['jadwal_mingguan'])
            ? AbsensiEffectiveSchedule::normalizeWeeklyDays($input['jadwal_mingguan'])
            : $current['jadwal_mingguan'];

        $effectiveFrom = array_key_exists('effective_from', $input)
            ? ($input['effective_from'] ? Carbon::parse((string) $input['effective_from'])->toDateString() : null)
            : ($current['effective_from'] ?? null);

        if ($mode === self::MODE_MINGGUAN) {
            $hariWajib = [];
            foreach ($jadwalMingguan as $dow => $rule) {
                if (! empty($rule['aktif'])) {
                    $hariWajib[] = (int) $dow;
                }
            }
            if ($hariWajib === []) {
                $hariWajib = [3, 4, 5];
            }
        } elseif ($mode === self::MODE_HARIAN) {
            $hariWajib = [1, 2, 3, 4, 5];
        } else {
            $hariWajib = [4];
        }

        $legacyMode = $current['legacy_mode'] ?? self::MODE_KAMIS;
        $legacyHariWajib = $current['legacy_hari_wajib'] ?? [4];
        if (($current['mode'] ?? self::MODE_KAMIS) !== self::MODE_MINGGUAN && $mode === self::MODE_MINGGUAN) {
            $legacyMode = $current['mode'] ?? self::MODE_KAMIS;
            $legacyHariWajib = $current['hari_wajib'] ?? [4];
        }

        $cfg = [
            'mode' => $mode,
            'hari_wajib' => $hariWajib,
            'effective_from' => $effectiveFrom,
            'legacy_mode' => $legacyMode,
            'legacy_hari_wajib' => $legacyHariWajib,
            'jadwal_mingguan' => $jadwalMingguan,
        ];

        SystemSetting::setValue(self::SETTING_KEY, $cfg);

        return $cfg;
    }

    /**
     * Apakah tanggal ini hari terjadwal absensi (mengikuti effective_from, mengabaikan libur)?
     */
    public static function isHariTerjadwal(CarbonInterface|string $date): bool
    {
        $c = $date instanceof CarbonInterface ? Carbon::instance($date) : Carbon::parse((string) $date);
        $dateStr = $c->toDateString();
        $dow = (int) $c->dayOfWeek;

        $cfg = self::config();
        $effectiveFrom = ! empty($cfg['effective_from']) ? (string) $cfg['effective_from'] : null;
        // Jadwal mingguan tanpa tanggal efektif = menunggu penetapan; kebijakan lama tetap berlaku.
        $usePreEffectiveLegacy = AbsensiEffectiveSchedule::weeklyPending($cfg)
            || ($effectiveFrom !== null && $dateStr < $effectiveFrom);
        $activeMode = $usePreEffectiveLegacy ? ($cfg['legacy_mode'] ?? self::MODE_KAMIS) : ($cfg['mode'] ?? self::MODE_KAMIS);

        if ($activeMode === self::MODE_MINGGUAN) {
            $days = AbsensiEffectiveSchedule::normalizeWeeklyDays($cfg['jadwal_mingguan'] ?? []);
            if (! empty($days[$dow]['aktif'])) {
                return true;
            }
        } else {
            $hariWajib = $usePreEffectiveLegacy
                ? ($cfg['legacy_hari_wajib'] ?? [4])
                : ($cfg['hari_wajib'] ?? [4]);
            if (in_array($dow, array_map('intval', $hariWajib), true)) {
                return true;
            }
        }

        // Jika ada pengecualian perubahan jam pada tanggal tersebut, maka tanggal itu terjadwal
        foreach (AbsensiKalenderLibur::entriesForDate($dateStr) as $entry) {
            if ($entry->effectiveDampak() === 'ubah_jam') {
                return true;
            }
        }

        return false;
    }

    /**
     * Apakah tanggal ini wajib absen? (hari terjadwal DAN bukan hari libur).
     */
    public static function isHariWajib(CarbonInterface|string $date): bool
    {
        return AbsensiEffectiveSchedule::resolve($date)['is_wajib'];
    }

    public static function labelMode(): string
    {
        $cfg = self::config();
        $mode = $cfg['mode'] ?? self::MODE_KAMIS;

        if ($mode === self::MODE_MINGGUAN) {
            $days = AbsensiEffectiveSchedule::normalizeWeeklyDays($cfg['jadwal_mingguan'] ?? []);
            $parts = [];
            foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
                if (! empty($days[$dow]['aktif'])) {
                    $nama = AbsensiEffectiveSchedule::DAY_NAMES[$dow];
                    $parts[] = "{$nama} ({$days[$dow]['jam_masuk']}–{$days[$dow]['jam_pulang']})";
                }
            }
            $summary = $parts !== [] ? implode(', ', $parts) : 'Jadwal mingguan kustom';
            if (! empty($cfg['effective_from'])) {
                $summary .= ' · berlaku mulai ' . Carbon::parse($cfg['effective_from'])->translatedFormat('d M Y');
            } else {
                $summary .= ' · menunggu tanggal efektif';
            }

            return $summary;
        }

        return $mode === self::MODE_HARIAN
            ? 'Setiap hari kerja (Senin–Jumat)'
            : 'Hanya Kamis (sesuai jadwal kuliah lab)';
    }
}
