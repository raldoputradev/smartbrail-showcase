<?php

namespace App\Console\Commands;

use App\Support\AbsensiSessionCloser;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Alias legacy yang didelegasikan penuh ke AbsensiSessionCloser agar tidak berbenturan
 * dengan command utama `absensi:generate-alfa` (GenerateAbsensiAlfaCommand).
 */
class GenerateAbsensiAlfa extends Command
{
    protected $signature = 'absensi:generate-alfa-legacy {--date= : Tanggal target YYYY-MM-DD (default: kemarin)}';

    protected $description = 'Alias legacy: generate alfa absensi melalui AbsensiSessionCloser';

    public function handle(AbsensiSessionCloser $closer): int
    {
        $dateOpt = $this->option('date');
        $date = $dateOpt ? Carbon::parse((string) $dateOpt, 'Asia/Jakarta') : now('Asia/Jakarta')->subDay();

        foreach (['pagi', 'malam'] as $shift) {
            $res = $closer->closeSessionForDate($date, $shift, null, true);
            $this->line(sprintf(
                '[%s:%s] tidak_lengkap=%d alfa=%d',
                $date->toDateString(),
                $shift,
                $res['closed_incomplete'],
                $res['generated_alfa']
            ));
        }

        return self::SUCCESS;
    }
}
