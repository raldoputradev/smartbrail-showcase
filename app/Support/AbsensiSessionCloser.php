<?php

namespace App\Support;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penutup sesi harian + generator alfa otomatis (dipanggil scheduler & tombol manual laboran).
 */
class AbsensiSessionCloser
{
    /**
     * Tutup sesi pada tanggal & shift tertentu bila sudah melewati waktu tutup sesi:
     * - belum_lengkap -> tidak_lengkap
     * - mahasiswa aktif pada shift tsb yang sama sekali belum punya record absensi_harian -> absensi_harian (alfa) + absensi_alfa
     *
     * @return array{closed_incomplete: int, generated_alfa: int, skipped_reason: ?string}
     */
    public function closeSessionForDate(Carbon $tanggal, string $shift, ?Carbon $now = null, bool $force = false): array
    {
        $setting = AbsensiEffectiveSchedule::effectiveSesiSetting($tanggal, $shift);
        if (! $setting->aktif) {
            return ['closed_incomplete' => 0, 'generated_alfa' => 0, 'skipped_reason' => 'sesi_nonaktif'];
        }

        $tz = $setting->timezone ?: 'Asia/Jakarta';
        $now = ($now ? $now->copy() : now())->timezone($tz);
        $dateRef = Carbon::parse($tanggal->format('Y-m-d') . ' 12:00:00', $tz);
        $closeAt = AbsensiLatenessCalculator::sessionCloseAt($dateRef, $setting);

        if (! $force && $now->lessThanOrEqualTo($closeAt)) {
            return ['closed_incomplete' => 0, 'generated_alfa' => 0, 'skipped_reason' => 'belum_waktu_tutup'];
        }

        $dateStr = $tanggal->format('Y-m-d');

        return DB::transaction(function () use ($dateStr, $shift, $now) {
            $closed = AbsensiHarian::query()
                ->whereDate('tanggal', $dateStr)
                ->where('sesi', $shift)
                ->where('status', 'masuk')
                ->update([
                    'status' => 'tidak_lengkap',
                    'updated_at' => $now,
                ]);

            $alfaCount = $this->generateAlfaForDate($dateStr, $shift, $now);

            return [
                'closed_incomplete' => $closed,
                'generated_alfa' => $alfaCount,
                'skipped_reason' => null,
            ];
        });
    }

    /**
     * Generate record alfa untuk semua mahasiswa aktif yang belum punya record harian pada tanggal & sesi tsb.
     * Hanya berjalan pada hari wajib sesuai jadwal efektif & kalender pengecualian.
     */
    public function generateAlfaForDate(string $dateStr, string $shift, ?Carbon $now = null): int
    {
        $now ??= now();

        if (! AbsensiKebijakan::isHariWajib($dateStr)) {
            return 0;
        }

        $existingUserIds = AbsensiHarian::query()
            ->whereDate('tanggal', $dateStr)
            ->where('sesi', $shift)
            ->pluck('user_id')
            ->all();

        $candidates = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->where(function ($q) use ($shift) {
                if ($shift === 'pagi') {
                    $q->where('shift', 'pagi')->orWhereNull('shift');
                } else {
                    $q->where('shift', $shift);
                }
            })
            ->whereDate('created_at', '<=', $dateStr)
            ->when(! empty($existingUserIds), fn ($q) => $q->whereNotIn('id', $existingUserIds))
            ->get(['id']);

        $count = 0;
        foreach ($candidates as $mhs) {
            AbsensiHarian::firstOrCreate(
                [
                    'user_id' => $mhs->id,
                    'tanggal' => $dateStr,
                    'sesi' => $shift,
                ],
                [
                    'status' => 'alfa',
                ]
            );

            $alfa = AbsensiAlfa::firstOrCreate(
                [
                    'user_id' => $mhs->id,
                    'tanggal' => $dateStr,
                    'sesi' => $shift,
                ],
                [
                    'kategori' => 'alfa',
                    'sumber' => 'otomatis',
                    'aktif' => true,
                ]
            );

            if ($alfa->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sapu beberapa hari ke belakang (default 3 hari) agar sesi yang terlewat saat server mati tetap tertutup.
     *
     * @return array<string, array{closed_incomplete: int, generated_alfa: int, skipped_reason: ?string}>
     */
    public function sweepRecentDays(int $daysBack = 3, ?Carbon $now = null): array
    {
        $report = [];
        $shifts = AbsensiSesiSetting::query()->where('aktif', true)->pluck('shift')->all();
        if (empty($shifts)) {
            $shifts = ['pagi', 'malam'];
        }

        $baseNow = $now ? $now->copy() : now('Asia/Jakarta');

        for ($i = 0; $i <= max(0, $daysBack); $i++) {
            $day = $baseNow->copy()->subDays($i);
            foreach ($shifts as $shift) {
                $key = $day->format('Y-m-d') . ':' . $shift;
                $report[$key] = $this->closeSessionForDate($day, $shift, $baseNow, false);
            }
        }

        return $report;
    }

    /**
     * Tutup semua shift aktif untuk satu tanggal dan kembalikan daftar ringkasan per shift.
     *
     * @return array<int, array{tanggal: string, shift: string, closed_incomplete: int, alfa: int}>
     */
    public function closeForDate(Carbon|string $date, bool $force = false): array
    {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse((string) $date, 'Asia/Jakarta');
        $dateStr = $carbon->toDateString();
        $shifts = AbsensiSesiSetting::query()->where('aktif', true)->pluck('shift')->all();
        if ($shifts === []) {
            $shifts = ['pagi', 'malam'];
        }

        $results = [];
        foreach ($shifts as $shift) {
            $res = $this->closeSessionForDate($carbon, (string) $shift, null, $force);
            if ($res['skipped_reason'] !== null && ! $force) {
                continue;
            }
            $results[] = [
                'tanggal' => $dateStr,
                'shift' => (string) $shift,
                'closed_incomplete' => $res['closed_incomplete'],
                'alfa' => $res['generated_alfa'],
            ];
        }

        return $results;
    }

    /**
     * Tutup sesi yang sudah melewati jam tutup pada hari ini dan kemarin.
     *
     * @return array<int, array{tanggal: string, shift: string, closed_incomplete: int, alfa: int}>
     */
    public function closeDueSessions(?Carbon $now = null): array
    {
        $baseNow = $now ? $now->copy() : now('Asia/Jakarta');
        $results = [];

        foreach ([0, 1] as $daysAgo) {
            $day = $baseNow->copy()->subDays($daysAgo);
            if (! AbsensiKebijakan::isHariWajib($day)) {
                continue;
            }
            foreach ($this->closeForDate($day, false) as $row) {
                $results[] = $row;
            }
        }

        return $results;
    }
}
