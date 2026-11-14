<?php

namespace App\Console\Commands;

use App\Support\AbsensiTapEvaluator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateAbsensiAlfa extends Command
{
    protected $signature = 'absensi:generate-alfa {--date= : Tanggal Y-m-d (default kemarin)}';

    protected $description = 'Generate alfa otomatis dan finalisasi tidak lengkap per sesi';

    public function handle(AbsensiTapEvaluator $evaluator): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        $total = 0;
        foreach (['pagi', 'malam'] as $sesi) {
            $count = $evaluator->generateAlfaForDate($date, $sesi);
            $this->info("{$date} [{$sesi}]: {$count} alfa");
            $total += $count;
        }

        $this->info("Selesai. Total alfa: {$total}");

        return self::SUCCESS;
    }
}
