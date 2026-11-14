<?php

namespace App\Console\Commands;

use App\Models\AbsensiSesiSetting;
use App\Support\AbsensiKebijakan;
use App\Support\AbsensiSchedulerHeartbeat;
use App\Support\AbsensiSessionCloser;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateAbsensiAlfaCommand extends Command
{
    protected $signature = 'absensi:generate-alfa
        {--days=3 : Cek N hari ke belakang termasuk kemarin}
        {--date= : Satu tanggal Y-m-d saja}';

    protected $description = 'Cadangan: hitung alfa untuk hari yang sesinya sudah lewat (jika cron 15 menit terlewat)';

    public function handle(AbsensiSessionCloser $closer): int
    {
        $dates = [];

        if ($single = $this->option('date')) {
            $dates[] = Carbon::parse($single)->toDateString();
        } else {
            $days = max(1, (int) $this->option('days'));
            for ($i = 1; $i <= $days; $i++) {
                $dates[] = Carbon::today()->subDays($i)->toDateString();
            }
        }

        $totalAlfa = 0;

        foreach ($dates as $tanggal) {
            if (!AbsensiKebijakan::isHariWajib($tanggal)) {
                $this->line("Lewati {$tanggal} (bukan hari wajib absen).");

                continue;
            }

            foreach ($closer->closeForDate($tanggal, force: true) as $row) {
                $totalAlfa += $row['alfa'];
                $this->line("{$tanggal} {$row['shift']}: {$row['alfa']} alfa");
            }
        }

        $this->info("Selesai. Total mahasiswa alfa baru: {$totalAlfa}.");

        AbsensiSchedulerHeartbeat::touch('absensi:generate-alfa', ['alfa_total' => $totalAlfa]);

        return self::SUCCESS;
    }
}
