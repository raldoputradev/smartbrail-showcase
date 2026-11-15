<?php

namespace App\Support;

use App\Models\AbsensiKalenderLibur;
use App\Models\AbsensiSesiSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AbsensiEffectiveSchedule
{
    public const DAY_NAMES = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        0 => 'Minggu',
    ];

    /**
     * Default weekly schedule rules (supports 9 SKS / 3 Hari: Rabu, Kamis, Jumat).
     *
     * @return array<int, array{aktif: bool, jam_masuk: string, jam_pulang: string, jam_mulai_tap_masuk: string, keterangan: string}>
     */
    public static function defaultWeeklyDays(): array
    {
        return [
            1 => ['aktif' => false, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Tidak wajib'],
            2 => ['aktif' => false, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Tidak wajib'],
            3 => ['aktif' => true,  'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Sesi penuh (Rabu)'],
            4 => ['aktif' => true,  'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Sesi penuh (Kamis)'],
            5 => ['aktif' => true,  'jam_masuk' => '07:50', 'jam_pulang' => '12:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Sesi pendek (Jumat)'],
            6 => ['aktif' => false, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Akhir pekan'],
            0 => ['aktif' => false, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30', 'keterangan' => 'Akhir pekan'],
        ];
    }

    /**
     * Normalize stored weekly days configuration.
     *
     * @param  array<mixed>  $rawDays
     * @return array<int, array{aktif: bool, jam_masuk: string, jam_pulang: string, jam_mulai_tap_masuk: string, keterangan: string}>
     */
    public static function normalizeWeeklyDays(array $rawDays): array
    {
        $defaults = self::defaultWeeklyDays();
        $result = [];

        foreach ($defaults as $dow => $def) {
            $item = $rawDays[$dow] ?? $rawDays[(string) $dow] ?? [];
            if (! is_array($item)) {
                $item = [];
            }

            $jamMasuk = isset($item['jam_masuk']) && preg_match('/^\d{2}:\d{2}/', (string) $item['jam_masuk'])
                ? substr((string) $item['jam_masuk'], 0, 5)
                : $def['jam_masuk'];

            $jamPulang = isset($item['jam_pulang']) && preg_match('/^\d{2}:\d{2}/', (string) $item['jam_pulang'])
                ? substr((string) $item['jam_pulang'], 0, 5)
                : $def['jam_pulang'];

            $jamMulaiTap = isset($item['jam_mulai_tap_masuk']) && preg_match('/^\d{2}:\d{2}/', (string) $item['jam_mulai_tap_masuk'])
                ? substr((string) $item['jam_mulai_tap_masuk'], 0, 5)
                : $def['jam_mulai_tap_masuk'];

            $result[$dow] = [
                'aktif' => array_key_exists('aktif', $item) ? (bool) $item['aktif'] : $def['aktif'],
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
                'jam_mulai_tap_masuk' => $jamMulaiTap,
                'keterangan' => isset($item['keterangan']) && trim((string) $item['keterangan']) !== ''
                    ? trim((string) $item['keterangan'])
                    : $def['keterangan'],
            ];
        }

        return $result;
    }

    /**
     * Apakah mode jadwal mingguan masih "menunggu tanggal efektif"?
     * Bila true, kebijakan sebelumnya (legacy) tetap berlaku untuk semua tanggal.
     *
     * @param  array<string, mixed>|null  $config
     */
    public static function weeklyPending(?array $config = null): bool
    {
        $cfg = $config ?? AbsensiKebijakan::config();

        return ($cfg['mode'] ?? AbsensiKebijakan::MODE_KAMIS) === AbsensiKebijakan::MODE_MINGGUAN
            && empty($cfg['effective_from']);
    }

    /**
     * Resolve effective attendance schedule and calendar agenda for a specific date and shift.
     *
     * Priority:
     * 1. Applicable calendar exception on that date (batal_wajib / ubah_jam / tetap_wajib)
     * 2. Effective weekly schedule (when mode=jadwal_mingguan and date >= effective_from)
     * 3. Pre-effective / legacy policy (kamis / harian + AbsensiSesiSetting)
     *
     * @return array{
     *   tanggal: string,
     *   hari_index: int,
     *   hari_nama: string,
     *   shift: string,
     *   is_wajib: bool,
     *   is_libur: bool,
     *   status_hari: string,
     *   jam_masuk: string,
     *   jam_pulang: string,
     *   jam_mulai_tap_masuk: string,
     *   toleransi_masuk_menit: int,
     *   toleransi_pulang_menit: int,
     *   grace_alfa_menit: int,
     *   timezone: string,
     *   label_jadwal: string,
     *   label_status_hari: string,
     *   sumber_aturan: string,
     *   agenda: array<int, array{id: int, jenis: string, dampak_absensi: string, keterangan: string, tanggal: string, tanggal_selesai: ?string, jam_masuk_khusus: ?string, jam_pulang_khusus: ?string}>
     * }
     */
    public static function resolve(CarbonInterface|string $date, string $shift = 'pagi'): array
    {
        $sesi = AbsensiSesiSetting::forShift($shift);
        $tz = $sesi->timezone ?: 'Asia/Jakarta';

        $carbon = $date instanceof CarbonInterface
            ? Carbon::parse($date->toDateString(), $tz)
            : Carbon::parse((string) $date, $tz);

        $dateStr = $carbon->toDateString();
        $dow = (int) $carbon->dayOfWeek;
        $hariNama = self::DAY_NAMES[$dow] ?? $carbon->translatedFormat('l');

        $config = AbsensiKebijakan::config();
        $mode = (string) ($config['mode'] ?? AbsensiKebijakan::MODE_KAMIS);
        $effectiveFrom = ! empty($config['effective_from']) ? (string) $config['effective_from'] : null;
        $legacyMode = (string) ($config['legacy_mode'] ?? AbsensiKebijakan::MODE_KAMIS);
        $legacyHariWajib = is_array($config['legacy_hari_wajib'] ?? null)
            ? array_map('intval', $config['legacy_hari_wajib'])
            : ($legacyMode === AbsensiKebijakan::MODE_HARIAN ? [1, 2, 3, 4, 5] : [4]);

        // Jadwal mingguan tanpa tanggal efektif = "menunggu tanggal efektif":
        // kebijakan sebelumnya tetap berlaku untuk SEMUA tanggal agar riwayat tidak dinilai ulang.
        $weeklyPending = self::weeklyPending($config);
        $usePreEffectiveLegacy = $weeklyPending || ($effectiveFrom !== null && $dateStr < $effectiveFrom);
        $activeMode = $usePreEffectiveLegacy ? $legacyMode : $mode;

        $baseJamMasuk = substr((string) $sesi->jam_masuk, 0, 5);
        $baseJamPulang = substr((string) $sesi->jam_pulang, 0, 5);
        $baseMulaiTap = substr((string) ($sesi->jam_mulai_tap_masuk ?? '06:30'), 0, 5);

        $isWajib = false;
        $sumberAturan = 'kebijakan_sesi';

        if ($activeMode === AbsensiKebijakan::MODE_MINGGUAN) {
            $days = self::normalizeWeeklyDays($config['jadwal_mingguan'] ?? []);
            $dayRule = $days[$dow] ?? self::defaultWeeklyDays()[$dow];
            $isWajib = (bool) ($dayRule['aktif'] ?? false) && (bool) $sesi->aktif;
            if ($shift === 'pagi') {
                $baseJamMasuk = $dayRule['jam_masuk'];
                $baseJamPulang = $dayRule['jam_pulang'];
                $baseMulaiTap = $dayRule['jam_mulai_tap_masuk'];
            }
            $sumberAturan = 'jadwal_mingguan';
        } else {
            $hariWajibList = $usePreEffectiveLegacy
                ? $legacyHariWajib
                : array_map('intval', $config['hari_wajib'] ?? ($activeMode === AbsensiKebijakan::MODE_HARIAN ? [1, 2, 3, 4, 5] : [4]));
            $isWajib = in_array($dow, $hariWajibList, true) && (bool) $sesi->aktif;
            $sumberAturan = $weeklyPending
                ? 'menunggu_tanggal_efektif'
                : ($usePreEffectiveLegacy ? 'sebelum_tanggal_efektif' : 'kebijakan_hari_wajib');
        }

        $entries = AbsensiKalenderLibur::entriesForDate($dateStr);
        $agenda = [];
        $liburEntry = null;
        $ubahJamEntry = null;
        $infoEntry = null;

        foreach ($entries as $entry) {
            $jenis = $entry->effectiveJenis();
            $dampak = $entry->effectiveDampak();
            $item = [
                'id' => (int) $entry->id,
                'jenis' => $jenis,
                'dampak_absensi' => $dampak,
                'keterangan' => (string) ($entry->keterangan ?: 'Agenda akademik'),
                'tanggal' => $entry->tanggal?->toDateString() ?? $dateStr,
                'tanggal_selesai' => $entry->tanggal_selesai?->toDateString(),
                'jam_masuk_khusus' => $entry->jam_masuk_khusus ? substr((string) $entry->jam_masuk_khusus, 0, 5) : null,
                'jam_pulang_khusus' => $entry->jam_pulang_khusus ? substr((string) $entry->jam_pulang_khusus, 0, 5) : null,
            ];
            $agenda[] = $item;

            if ($dampak === 'batal_wajib' && $liburEntry === null) {
                $liburEntry = $item;
            } elseif ($dampak === 'ubah_jam' && $ubahJamEntry === null) {
                $ubahJamEntry = $item;
            } elseif ($infoEntry === null) {
                $infoEntry = $item;
            }
        }

        $isLibur = false;
        $statusHari = $isWajib ? 'wajib' : 'tidak_wajib';
        $jamMasuk = $baseJamMasuk;
        $jamPulang = $baseJamPulang;
        $jamMulaiTap = $baseMulaiTap;

        if ($liburEntry !== null) {
            $isLibur = true;
            $isWajib = false;
            $statusHari = 'libur';
            $sumberAturan = 'pengecualian_libur';
        } elseif ($ubahJamEntry !== null) {
            $isWajib = (bool) $sesi->aktif;
            $statusHari = 'perubahan_jam';
            $sumberAturan = 'pengecualian_jam';
            if (! empty($ubahJamEntry['jam_masuk_khusus'])) {
                $jamMasuk = $ubahJamEntry['jam_masuk_khusus'];
            }
            if (! empty($ubahJamEntry['jam_pulang_khusus'])) {
                $jamPulang = $ubahJamEntry['jam_pulang_khusus'];
            }
        } elseif ($infoEntry !== null) {
            // Agenda informasi / ujian dengan dampak tetap_wajib: tidak membatalkan kewajiban absensi
            $statusHari = $isWajib ? 'wajib_agenda' : 'tidak_wajib_agenda';
        }

        if ($isLibur) {
            $labelJadwal = 'Libur / Pengecualian';
            $labelStatusHari = 'Libur: ' . ($liburEntry['keterangan'] ?? 'Tidak wajib absensi');
        } elseif (! $isWajib) {
            $labelJadwal = 'Tidak ada kewajiban absensi';
            $labelStatusHari = $infoEntry
                ? 'Hari tidak wajib · ' . $infoEntry['keterangan']
                : 'Hari tidak wajib absensi';
        } else {
            $labelJadwal = "{$jamMasuk} – {$jamPulang} WIB";
            if ($statusHari === 'perubahan_jam') {
                $labelStatusHari = 'Perubahan jam: ' . ($ubahJamEntry['keterangan'] ?? "{$jamMasuk}–{$jamPulang}");
            } elseif ($infoEntry !== null) {
                $labelStatusHari = 'Wajib absensi · ' . $infoEntry['keterangan'];
            } else {
                $labelStatusHari = "Wajib absensi ({$jamMasuk}–{$jamPulang} WIB)";
            }
        }

        return [
            'tanggal' => $dateStr,
            'hari_index' => $dow,
            'hari_nama' => $hariNama,
            'shift' => $shift,
            'is_wajib' => $isWajib,
            'is_libur' => $isLibur,
            'status_hari' => $statusHari,
            'jam_masuk' => $jamMasuk,
            'jam_pulang' => $jamPulang,
            'jam_mulai_tap_masuk' => $jamMulaiTap,
            'toleransi_masuk_menit' => (int) $sesi->toleransi_masuk_menit,
            'toleransi_pulang_menit' => (int) ($sesi->toleransi_pulang_menit ?? $sesi->toleransi_masuk_menit ?? 10),
            'grace_alfa_menit' => (int) $sesi->grace_alfa_menit,
            'timezone' => $tz,
            'label_jadwal' => $labelJadwal,
            'label_status_hari' => $labelStatusHari,
            'sumber_aturan' => $sumberAturan,
            'agenda' => $agenda,
        ];
    }

    /**
     * Build a date-aware AbsensiSesiSetting clone with effective hours for that date.
     */
    public static function effectiveSesiSetting(CarbonInterface|string $date, string $shift = 'pagi'): AbsensiSesiSetting
    {
        $base = AbsensiSesiSetting::forShift($shift);
        $resolved = self::resolve($date, $shift);

        $clone = clone $base;
        if ($resolved['jam_masuk'] !== substr((string) $base->jam_masuk, 0, 5)) {
            $clone->jam_masuk = $resolved['jam_masuk'] . ':00';
        }
        if ($resolved['jam_pulang'] !== substr((string) $base->jam_pulang, 0, 5)) {
            $clone->jam_pulang = $resolved['jam_pulang'] . ':00';
        }
        if ($resolved['jam_mulai_tap_masuk'] !== substr((string) ($base->jam_mulai_tap_masuk ?? '06:30:00'), 0, 5)) {
            $clone->jam_mulai_tap_masuk = $resolved['jam_mulai_tap_masuk'] . ':00';
        }

        return $clone;
    }

    /**
     * Apakah menit terlambat tersimpan pada record konsisten dengan jadwal yang ditampilkan?
     * Riwayat tidak menyimpan salinan jadwal saat tap; bila menit terlambat tersimpan tidak
     * cocok dengan jadwal hasil resolve(), penilaian kemungkinan berasal dari pengaturan lama
     * (mis. jam masuk/toleransi sebelumnya). Kita tidak menilai ulang — pemanggil cukup
     * memberi tanda asal penilaian pada tampilan.
     *
     * @param  array<string, mixed>  $schedule  Result of self::resolve()
     * @param  array<string, mixed>  $record  jam_masuk + menit_terlambat_masuk (opsional)
     */
    public static function scheduleConsistentWithRecord(array $schedule, array $record): bool
    {
        $jamMasuk = (string) ($record['jam_masuk'] ?? '');
        if ($jamMasuk === '' || ! preg_match('/^\d{2}:\d{2}/', $jamMasuk)) {
            return true;
        }

        try {
            $tz = $schedule['timezone'] ?? 'Asia/Jakarta';
            $dateStr = $schedule['tanggal'] ?? now($tz)->toDateString();
            $tap = Carbon::parse($dateStr . ' ' . substr($jamMasuk, 0, 5), $tz);
            $target = Carbon::parse($dateStr . ' ' . ($schedule['jam_masuk'] ?? '07:50'), $tz);
            $selisih = intdiv($tap->getTimestamp() - $target->getTimestamp(), 60);
            $tersimpan = max(0, (int) ($record['menit_terlambat_masuk'] ?? 0));

            return $tersimpan === 0
                ? $selisih <= (int) ($schedule['toleransi_masuk_menit'] ?? 0)
                : abs($tersimpan - $selisih) <= 1;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Separate completeness (kelengkapan) and punctuality (ketepatan waktu) assessment for a daily attendance record.
     *
     * @param  array<string, mixed>  $schedule  Result of self::resolve()
     * @param  array{
     *   status?: ?string,
     *   jam_masuk?: ?string,
     *   jam_pulang?: ?string,
     *   menit_terlambat_masuk?: int,
     *   menit_terlambat_pulang?: int,
     *   menit_pulang_cepat?: int,
     *   pulang_cepat?: bool,
     *   pulang_lembur?: bool,
     *   slot_lab_masuk?: ?int
     * }  $record
     * @return array{
     *   kelengkapan_kode: string,
     *   kelengkapan_label: string,
     *   kelengkapan_tone: string,
     *   kelengkapan_desc: string,
     *   ketepatan_masuk_kode: ?string,
     *   ketepatan_masuk_label: string,
     *   ketepatan_masuk_tone: string,
     *   ketepatan_pulang_kode: ?string,
     *   ketepatan_pulang_label: string,
     *   ketepatan_pulang_tone: string,
     *   ringkasan_ketepatan: string,
     *   ringkasan_ketepatan_tone: string,
     *   menit_terlambat_masuk: int,
     *   menit_pulang_cepat: int,
     *   has_punctuality_note: bool
     * }
     */
    public static function assessRecord(array $schedule, array $record): array
    {
        $status = (string) ($record['status'] ?? '');
        $jamMasuk = $record['jam_masuk'] ?? null;
        $jamPulang = $record['jam_pulang'] ?? null;
        $telatMasuk = max(0, (int) ($record['menit_terlambat_masuk'] ?? 0));
        $telatPulang = max(0, (int) ($record['menit_terlambat_pulang'] ?? 0));
        $pulangCepatMenit = max(0, (int) ($record['menit_pulang_cepat'] ?? 0));
        $pulangCepatFlag = (bool) ($record['pulang_cepat'] ?? false);
        $pulangLemburFlag = (bool) ($record['pulang_lembur'] ?? false);

        $tz = $schedule['timezone'] ?? 'Asia/Jakarta';
        $dateStr = $schedule['tanggal'] ?? now($tz)->toDateString();
        $jadwalMasukStr = $schedule['jam_masuk'] ?? '07:50';
        $jadwalPulangStr = $schedule['jam_pulang'] ?? '17:00';

        // If pulang time is before jadwal_pulang and menit_pulang_cepat wasn't stored yet, compute it accurately.
        if ($jamPulang && $pulangCepatMenit === 0) {
            try {
                $tapPulang = Carbon::parse("{$dateStr} {$jamPulang}", $tz);
                $targetPulang = Carbon::parse("{$dateStr} {$jadwalPulangStr}", $tz);
                if ($tapPulang->lessThan($targetPulang)) {
                    $pulangCepatMenit = (int) $tapPulang->diffInMinutes($targetPulang);
                    $pulangCepatFlag = $pulangCepatMenit > 0;
                }
            } catch (\Throwable) {
            }
        }

        // 1. Kelengkapan
        [$kelKode, $kelLabel, $kelTone, $kelDesc] = match ($status) {
            'lengkap' => ['lengkap', 'Lengkap', 'ok', 'Tap masuk & pulang tercatat'],
            'tidak_lengkap' => ['tidak_lengkap', 'Tidak Lengkap', 'warn', 'Hanya tap masuk (tidak ada tap pulang)'],
            'masuk', 'belum_lengkap' => ['masuk', 'Hanya Masuk', 'info', 'Tap masuk tercatat, menunggu tap pulang'],
            'alfa' => ['alfa', 'Alfa', 'danger', 'Tidak ada tap masuk & pulang pada jadwal wajib'],
            'izin' => ['izin', 'Izin', 'info', 'Status kehadiran: Izin'],
            'sakit' => ['sakit', 'Sakit', 'info', 'Status kehadiran: Sakit'],
            'dispensasi' => ['dispensasi', 'Dispensasi', 'info', 'Status kehadiran: Dispensasi'],
            default => $schedule['is_libur'] ?? false
                ? ['libur', 'Libur', 'neutral', $schedule['label_status_hari'] ?? 'Hari libur']
                : (! ($schedule['is_wajib'] ?? true)
                    ? ['tidak_wajib', 'Tidak Wajib', 'neutral', 'Tidak ada jadwal wajib pada hari ini']
                    : ['belum_tap', 'Belum Tap', 'neutral', 'Belum ada tap tercatat hari ini']),
        };

        // 2. Ketepatan Masuk
        $masukKode = null;
        $masukLabel = '—';
        $masukTone = 'neutral';

        if ($jamMasuk) {
            if ($telatMasuk > 0) {
                $masukKode = 'terlambat';
                $masukLabel = "Terlambat {$telatMasuk} menit";
                $masukTone = 'warn';
            } else {
                $isEarly = false;
                try {
                    $tapMasuk = Carbon::parse("{$dateStr} {$jamMasuk}", $tz);
                    $targetMasuk = Carbon::parse("{$dateStr} {$jadwalMasukStr}", $tz);
                    $isEarly = $tapMasuk->lessThan($targetMasuk);
                } catch (\Throwable) {
                }

                if ($isEarly) {
                    $masukKode = 'datang_awal';
                    $masukLabel = 'Datang lebih awal';
                    $masukTone = 'ok';
                } else {
                    $masukKode = 'tepat_waktu';
                    $masukLabel = 'Tepat waktu';
                    $masukTone = 'ok';
                }
            }
        }

        // 3. Ketepatan Pulang (Never call staying past schedule "Lembur" in student surface)
        $pulangKode = null;
        $pulangLabel = '—';
        $pulangTone = 'neutral';

        if ($jamPulang) {
            if ($pulangCepatFlag || $pulangCepatMenit > 0) {
                $pulangKode = 'pulang_cepat';
                $pulangLabel = $pulangCepatMenit > 0
                    ? "Pulang lebih awal {$pulangCepatMenit} menit"
                    : 'Pulang lebih awal';
                $pulangTone = 'warn';
            } elseif ($pulangLemburFlag || $telatPulang > 0) {
                $pulangKode = 'melewati_jadwal';
                $pulangLabel = 'Melewati jam jadwal';
                $pulangTone = 'info';
            } else {
                $pulangKode = 'tepat_waktu';
                $pulangLabel = 'Pulang sesuai jadwal';
                $pulangTone = 'ok';
            }
        } elseif (in_array($status, ['tidak_lengkap', 'belum_lengkap'], true)) {
            $pulangKode = 'belum_tap_pulang';
            $pulangLabel = $status === 'tidak_lengkap' ? 'Tap pulang tidak tercatat' : 'Belum tap pulang';
            $pulangTone = $status === 'tidak_lengkap' ? 'warn' : 'info';
        }

        // 4. Ringkasan ketepatan waktu (agar badge "Lengkap" tidak menyembunyikan terlambat / pulang cepat)
        $notes = [];
        $ringkasanTone = 'ok';

        if ($masukKode === 'terlambat') {
            $notes[] = $masukLabel;
            $ringkasanTone = 'warn';
        }
        if ($pulangKode === 'pulang_cepat') {
            $notes[] = $pulangLabel;
            $ringkasanTone = 'warn';
        }
        if ($pulangKode === 'melewati_jadwal' && $notes === []) {
            $notes[] = $pulangLabel;
            $ringkasanTone = 'info';
        }

        $hasPunctualityNote = $masukKode === 'terlambat' || $pulangKode === 'pulang_cepat';

        if ($notes !== []) {
            $ringkasanKetepatan = implode(' · ', $notes);
        } elseif ($jamMasuk && $jamPulang) {
            $ringkasanKetepatan = 'Tepat waktu';
            $ringkasanTone = 'ok';
        } elseif ($jamMasuk) {
            $ringkasanKetepatan = $masukLabel;
            $ringkasanTone = $masukTone;
        } else {
            $ringkasanKetepatan = '—';
            $ringkasanTone = 'neutral';
        }

        return [
            'kelengkapan_kode' => $kelKode,
            'kelengkapan_label' => $kelLabel,
            'kelengkapan_tone' => $kelTone,
            'kelengkapan_desc' => $kelDesc,
            'ketepatan_masuk_kode' => $masukKode,
            'ketepatan_masuk_label' => $masukLabel,
            'ketepatan_masuk_tone' => $masukTone,
            'ketepatan_pulang_kode' => $pulangKode,
            'ketepatan_pulang_label' => $pulangLabel,
            'ketepatan_pulang_tone' => $pulangTone,
            'ringkasan_ketepatan' => $ringkasanKetepatan,
            'ringkasan_ketepatan_tone' => $ringkasanTone,
            'menit_terlambat_masuk' => $telatMasuk,
            'menit_pulang_cepat' => $pulangCepatMenit,
            'has_punctuality_note' => $hasPunctualityNote,
        ];
    }
}
