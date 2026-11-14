<?php

namespace App\Support;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Models\User;
use Carbon\Carbon;

class AbsensiReadinessReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $today = now()->timezone('Asia/Jakarta');
        $todayStr = $today->toDateString();
        $yesterday = $today->copy()->subDay()->toDateString();

        $heartbeat = AbsensiSchedulerHeartbeat::status();
        $pbl = PblDataReadiness::report();

        $mhsAktif = User::query()->where('role', 'mahasiswa')->where('is_active', true)->count();
        $tanpaShift = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('shift')->orWhere('shift', '');
            })
            ->count();

        $tapsHariIni = LogAbsensi::query()->whereDate('waktu_tap', $todayStr)->count();
        $alfaKemarin = AbsensiAlfa::query()
            ->where('aktif', true)
            ->whereDate('tanggal', $yesterday)
            ->count();

        $belumTapHariIni = 0;
        if (AbsensiKebijakan::isHariWajib($todayStr)) {
            $sudahTap = (int) LogAbsensi::query()
                ->whereDate('waktu_tap', $todayStr)
                ->distinct()
                ->count('user_id');
            $belumTapHariIni = max(0, $mhsAktif - $sudahTap);
        }

        $sesi = AbsensiSesiSetting::query()->where('aktif', true)->get();
        $closeHints = $sesi->map(function (AbsensiSesiSetting $s) use ($today) {
            $day = $today->copy();
            $pulang = $s->jamPulangOn($day);
            $graceClose = $pulang->copy()->addMinutes((int) $s->grace_alfa_menit);

            return [
                'shift' => $s->shift,
                'alfa_at' => $pulang->format('H:i'),
                'tidak_lengkap_at' => $graceClose->format('H:i'),
                'timezone' => $s->timezone,
            ];
        })->values()->all();

        $issues = [];

        if ($heartbeat['is_stale']) {
            $issues[] = [
                'level' => 'danger',
                'title' => 'Scheduler belum terdeteksi',
                'detail' => 'Jalankan start-simalas-kamis.bat atau Task Scheduler (schedule:run tiap menit).',
            ];
        }

        if ($tanpaShift > 0) {
            $issues[] = [
                'level' => 'warning',
                'title' => "{$tanpaShift} mahasiswa tanpa shift",
                'detail' => 'Tap bisa ditolak SHIFT_BELUM_DIATUR. Gunakan backfill di Fingerprint.',
            ];
        }

        if (!$pbl['ok'] && $pbl['issues'] !== []) {
            $issues[] = [
                'level' => 'warning',
                'title' => 'Data PBL perlu dicek',
                'detail' => 'Lihat panel Proyek PBL / checklist di bawah.',
            ];
        }

        if (AbsensiKebijakan::isHariWajib($todayStr) && $tapsHariIni === 0 && $today->hour >= 8) {
            $issues[] = [
                'level' => 'info',
                'title' => 'Belum ada tap hari ini',
                'detail' => 'Normal jika praktikum belum mulai; pastikan mesin absensi online.',
            ];
        }

        $kamisReady = $heartbeat['is_ok'] && $tanpaShift === 0 && collect($pbl['issues'])->where('level', 'danger')->isEmpty();

        return [
            'today' => $todayStr,
            'yesterday' => $yesterday,
            'kebijakan_label' => AbsensiKebijakan::labelMode(),
            'hari_wajib_hari_ini' => AbsensiKebijakan::isHariWajib($todayStr),
            'is_kamis_today' => $today->isThursday(),
            'heartbeat' => $heartbeat,
            'pbl' => $pbl,
            'stats' => [
                'mahasiswa_aktif' => $mhsAktif,
                'tanpa_shift' => $tanpaShift,
                'taps_hari_ini' => $tapsHariIni,
                'alfa_kemarin' => $alfaKemarin,
                'belum_tap_hari_ini' => $belumTapHariIni,
                'lengkap_hari_ini' => AbsensiHarian::query()->whereDate('tanggal', $todayStr)->where('status', 'lengkap')->count(),
            ],
            'close_hints' => $closeHints,
            'issues' => $issues,
            'kamis_ready' => $kamisReady,
            'bat_path' => base_path('start-simalas-kamis.bat'),
        ];
    }
}
