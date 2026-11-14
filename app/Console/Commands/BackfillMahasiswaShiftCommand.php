<?php

namespace App\Console\Commands;

use App\Support\MahasiswaShiftResolver;
use Illuminate\Console\Command;

class BackfillMahasiswaShiftCommand extends Command
{
    protected $signature = 'simalas:backfill-shift';

    protected $description = 'Isi shift pagi/malam untuk mahasiswa yang belum punya shift';

    public function handle(): int
    {
        $count = MahasiswaShiftResolver::backfillMissing();
        $this->info("Shift diperbarui untuk {$count} mahasiswa.");

        return self::SUCCESS;
    }
}
