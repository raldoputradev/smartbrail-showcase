<?php

namespace App\Console\Commands;

use App\Models\LogAbsensi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Perbaiki tap yang tersimpan sebagai jam UTC tetapi dilabeli WIB
 * (contoh: 01:42 yang seharusnya 08:42).
 */
class FixAbsensiUtcSkewCommand extends Command
{
    protected $signature = 'absensi:fix-utc-skew
                            {--dry-run : Hanya tampilkan, jangan tulis}
                            {--before=2026-07-10 : Batas tanggal (exclusive) yang diperbaiki}';

    protected $description = 'Geser +7 jam pada log absensi pagi dini hari (UTC wall-clock → WIB)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $before = Carbon::parse((string) $this->option('before'), 'Asia/Jakarta')->startOfDay();

        $logs = LogAbsensi::query()
            ->where('sesi', 'pagi')
            ->where('waktu_tap', '<', $before->toDateTimeString())
            ->orderBy('id')
            ->get();

        $shifted = 0;

        DB::transaction(function () use ($logs, $dry, &$shifted) {
            foreach ($logs as $log) {
                /** @var Carbon $tap */
                $tap = $log->waktu_tap?->copy()->timezone('Asia/Jakarta');
                if (! $tap) {
                    continue;
                }

                $hour = (int) $tap->format('G');
                $shouldShift = false;

                // Hanya jam yang jelas UTC (dini hari / pagi terlalu awal untuk sesi 07:50).
                if ($log->jenis === 'masuk' && $hour < 6) {
                    $shouldShift = true;
                } elseif ($log->jenis === 'pulang' && $hour >= 8 && $hour < 13) {
                    $shouldShift = true;
                }

                // Cluster 2 Jul 2026: masuk 07–09 juga UTC (telat sangat besar).
                if ($log->jenis === 'masuk'
                    && $tap->toDateString() === '2026-07-02'
                    && $hour >= 6
                    && $hour < 10) {
                    $shouldShift = true;
                }

                if (! $shouldShift) {
                    continue;
                }

                $newTap = $tap->copy()->addHours(7);
                $this->line(sprintf(
                    'id=%d %s %s → %s',
                    $log->id,
                    $log->jenis,
                    $tap->format('Y-m-d H:i:s'),
                    $newTap->format('Y-m-d H:i:s')
                ));

                if (! $dry) {
                    // Update mentah agar tidak memicu ON UPDATE CURRENT_TIMESTAMP di skema lama.
                    DB::table('log_absensi')->where('id', $log->id)->update([
                        'waktu_tap' => $newTap->format('Y-m-d H:i:s'),
                    ]);
                }
                $shifted++;
            }
        });

        $this->info(($dry ? '[dry-run] ' : '')."Shifted {$shifted} log(s).");

        return self::SUCCESS;
    }
}
