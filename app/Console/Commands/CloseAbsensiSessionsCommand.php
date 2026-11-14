<?php

namespace App\Console\Commands;

use App\Support\AbsensiSchedulerHeartbeat;
use App\Support\AbsensiSessionCloser;
use Illuminate\Console\Command;

class CloseAbsensiSessionsCommand extends Command
{
    protected $signature = 'absensi:close-sessions {--date= : Paksa tutup untuk tanggal Y-m-d (opsional)}';

    protected $description = 'Tutup sesi absensi yang sudah lewat jam pulang + grace, lalu hitung alfa & tidak lengkap';

    public function handle(AbsensiSessionCloser $closer): int
    {
        $date = $this->option('date');
        $results = $date
            ? $closer->closeForDate($date, force: true)
            : $closer->closeDueSessions();

        if ($results === []) {
            $this->info('Tidak ada sesi yang perlu ditutup saat ini.');
            AbsensiSchedulerHeartbeat::touch('absensi:close-sessions', ['sessions_closed' => 0]);

            return self::SUCCESS;
        }

        foreach ($results as $row) {
            $this->line(sprintf(
                '- %s %s: %d alfa (tanpa tap ~%s, tidak lengkap ~%s)',
                $row['tanggal'],
                $row['shift'],
                $row['alfa'],
                $row['alfa_pada'] ?? '-',
                $row['ditutup_pada'] ?? '-'
            ));
        }

        $this->info('Penutupan sesi selesai.');

        AbsensiSchedulerHeartbeat::touch('absensi:close-sessions', [
            'sessions_closed' => count($results),
            'alfa_total' => array_sum(array_column($results, 'alfa')),
        ]);

        return self::SUCCESS;
    }
}
