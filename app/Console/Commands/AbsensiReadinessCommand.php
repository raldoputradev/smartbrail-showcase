<?php

namespace App\Console\Commands;

use App\Support\AbsensiReadinessReport;
use Illuminate\Console\Command;

class AbsensiReadinessCommand extends Command
{
    protected $signature = 'simalas:absensi-readiness';

    protected $description = 'Cek kesiapan operasional absensi (scheduler, PBL, shift)';

    public function handle(): int
    {
        $report = AbsensiReadinessReport::build();

        $this->info('=== SIMALAS Absensi Readiness ===');
        $this->line('Kebijakan: ' . $report['kebijakan_label']);
        $this->line('Hari ini wajib absen: ' . ($report['hari_wajib_hari_ini'] ? 'Ya' : 'Tidak'));

        $hb = $report['heartbeat'];
        if ($hb['is_ok']) {
            $this->info('Scheduler: OK (' . ($hb['minutes_ago'] ?? 0) . ' menit lalu, ' . ($hb['command'] ?? '-') . ')');
        } else {
            $this->error('Scheduler: BELUM TERDETEKSI — jalankan start-simalas-kamis.bat');
        }

        foreach ($report['pbl']['issues'] as $issue) {
            $line = "[{$issue['level']}] {$issue['title']} ({$issue['count']}) — {$issue['detail']}";
            $issue['level'] === 'danger' ? $this->error($line) : $this->warn($line);
        }

        $this->table(
            ['Metrik', 'Nilai'],
            collect($report['stats'])->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        return ($report['kamis_ready'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
