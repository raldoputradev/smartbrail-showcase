<?php

namespace App\Support;

/**
 * Overlay data contoh untuk grafik/KPI dashboard.
 *
 * DEMO-DASHBOARD — hapus class ini + pemanggilan di controller
 * setelah data asli dipakai (atau set SIMALAS_DASHBOARD_DEMO=false).
 */
final class DashboardDemoData
{
    public static function enabled(): bool
    {
        return (bool) config('simalas.dashboard_demo', false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function overlayAdmin(array $payload): array
    {
        if (! self::enabled()) {
            return $payload;
        }

        $mode = (string) ($payload['mode'] ?? 'minggu');
        $labels = self::labelsForMode($mode);
        $n = count($labels);

        $absensi = self::wave($n, 210, 55, 0.55);

        $tap = array_sum($absensi);
        $tapPrev = (int) round($tap * 0.78);

        $payload['demo'] = true;

        $payload['stats'] = array_merge($payload['stats'] ?? [], [
            'total_user' => 248,
            'total_mahasiswa' => 220,
            'total_dosen' => 18,
            'total_laboran' => 10,
            'user_aktif' => 231,
            'user_pending' => 17,
            'mesin_total' => 12,
            'mesin_aktif' => 12,
            'mesin_online' => 10,
            'mesin_offline' => 2,
            'mesin_nonaktif' => 0,
            'mesin_meta' => '10 online · 2 offline',
            'tap_absensi' => $tap,
            'tap_prev' => $tapPrev,
            'tap_delta' => $tap - $tapPrev,
            'tap_meta' => DashboardStatLabels::tapAbsensiMeta($tap - $tapPrev, $tapPrev, $mode),
        ]);

        $payload['ops'] = [
            'pending_approval' => 18,
            'new_suggestions' => 27,
            'enroll_belum' => 64,
        ];

        $payload['line'] = [
            'labels' => $labels,
            'subtitle' => ($payload['line']['subtitle'] ?? self::subtitleForMode($mode)).' · contoh',
            'chart_type' => 'multi_line',
            'absensi' => $absensi,
            'series' => [
                ['key' => 'absensi', 'label' => 'Absensi', 'color' => '#0284c7', 'data' => $absensi],
            ],
        ];

        $payload['role_donut'] = [
            'labels' => ['Mahasiswa', 'Dosen', 'Laboran'],
            'values' => [220, 18, 10],
        ];

        $payload['mesin_bar'] = [
            'labels' => ['Online', 'Offline', 'Nonaktif'],
            'values' => [10, 2, 0],
            'colors' => ['#22c55e', '#94a3b8', '#f43f5e'],
            'title' => 'Status mesin IoT',
            'subtitle' => 'Contoh kesiapan perangkat lab',
        ];

        $payload['kelas_bar'] = [
            'labels' => ['Robotika A', 'Robotika B', 'Robotika C', 'Mekatronika'],
            'values' => [412, 368, 455, 290],
            'colors' => ['#0284c7', '#38bdf8', '#7c3aed', '#14b8a6'],
            'title' => 'Tap absensi per kelas',
            'subtitle' => 'Contoh distribusi aktivitas kelas',
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function overlayDosen(array $payload): array
    {
        if (! self::enabled()) {
            return $payload;
        }

        $mode = (string) ($payload['mode'] ?? 'minggu');
        $labels = self::labelsForMode($mode);
        $n = count($labels);

        // % kehadiran unik mahasiswa (0–100)
        $pctSeries = self::wave($n, 84, 10, 0.4, true);

        $totalMhs = 186;
        $pct = 87.5;
        $hadir = (int) round($totalMhs * ($pct / 100));
        $belum = max(0, $totalMhs - $hadir);

        $taps = 942;
        $tapsPrev = 781;

        $payload['demo'] = true;

        $payload['stats'] = array_merge($payload['stats'] ?? [], [
            'total_mahasiswa' => $totalMhs,
            'persen_hadir' => $pct,
            'persen_delta' => 5.8,
            'persen_meta' => DashboardStatLabels::persenHadirMeta(5.8),
            'tap_absensi' => $taps,
            'tap_prev' => $tapsPrev,
            'tap_delta' => $taps - $tapsPrev,
            'tap_meta' => DashboardStatLabels::tapAbsensiMeta($taps - $tapsPrev, $tapsPrev, $mode),
        ]);

        $payload['line'] = [
            'labels' => $labels,
            'subtitle' => ($payload['line']['subtitle'] ?? self::subtitleForMode($mode)).' · contoh',
            'values' => $pctSeries,
            'absensi' => $pctSeries,
        ];

        $payload['donut'] = [
            'labels' => ['Sudah hadir', 'Belum hadir'],
            'values' => [$hadir, $belum],
            'persen' => $pct,
        ];

        $payload['tim_bar'] = [
            'labels' => ['KRAI', 'AGV Sumitomo', 'Humanoid', 'Delivery'],
            'values' => [88, 76, 81, 69],
            'colors' => ['#f59e0b', '#0284c7', '#7c3aed', '#14b8a6'],
            'title' => '% kehadiran per tim PBL',
            'subtitle' => 'Contoh pantauan manpro / co-manpro',
        ];

        $payload['sesi_bar'] = [
            'labels' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'],
            'values' => [18, 21, 16, 24, 19],
            'colors' => ['#38bdf8', '#38bdf8', '#38bdf8', '#0ea5e9', '#38bdf8'],
            'title' => 'Kehadiran tim Anda',
            'subtitle' => 'Contoh aktivitas kehadiran harian',
        ];

        return $payload;
    }

    /**
     * Rekap, alfa, tidak lengkap, cetak, dan ekspor memakai angka absensi asli.
     *
     * @param  array<string, mixed>  $chart
     * @param  array<string, mixed>  $summary
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function overlayRekap(array $chart, array $summary): array
    {
        return [$chart, $summary];
    }

    /**
     * Laporan periodik memakai agregat periode yang diminta.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function overlayPeriodic(array $payload): array
    {
        return $payload;
    }

    /**
     * @return list<string>
     */
    private static function labelsForMode(string $mode): array
    {
        return match ($mode) {
            'hari' => ['06', '08', '10', '12', '14', '16', '18', '20', '22'],
            'bulan' => array_map(fn ($d) => (string) $d, range(1, 14)),
            'tahun' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            default => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        };
    }

    private static function subtitleForMode(string $mode): string
    {
        return match ($mode) {
            'hari' => 'Per jam hari ini',
            'bulan' => '14 hari pertama bulan',
            'tahun' => 'Per bulan tahun ini',
            default => 'Senin – Minggu',
        };
    }

    /**
     * @return list<int|float>
     */
    private static function wave(int $n, float $base, float $amp, float $phase, bool $asPercent = false): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $v = $base + $amp * sin(($i / max(1, $n - 1)) * M_PI * 2 + $phase * M_PI);
            // weekend dip for weekly
            if ($n === 7 && $i >= 5) {
                $v *= 0.35;
            }
            if ($asPercent) {
                $out[] = round(max(35, min(98, $v)), 1);
            } else {
                $out[] = (int) max(0, round($v));
            }
        }

        return $out;
    }
}
