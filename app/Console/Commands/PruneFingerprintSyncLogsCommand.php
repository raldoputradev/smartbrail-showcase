<?php

namespace App\Console\Commands;

use App\Support\FingerprintSyncLogService;
use Illuminate\Console\Command;

class PruneFingerprintSyncLogsCommand extends Command
{
    protected $signature = 'fingerprint:prune-sync-logs {--days=30 : Hapus log lebih tua dari N hari}';

    protected $description = 'Bersihkan log sync sidik jari lama agar history tidak membengkak';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = FingerprintSyncLogService::pruneOlderThan($days);

        $this->info("Log sync sidik jari dihapus: {$deleted} baris (> {$days} hari).");

        return self::SUCCESS;
    }
}
