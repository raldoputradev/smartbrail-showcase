<?php

namespace App\Console\Commands;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiKomentar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeAbsensiAlfaCommand extends Command
{
    protected $signature = 'absensi:purge-alfa
        {--dry-run : Tampilkan jumlah tanpa menghapus}
        {--force : Hapus tanpa konfirmasi}';

    protected $description = 'Hapus semua data alfa (absensi_alfa, ringkasan harian status alfa, komentar terkait)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $alfaIds = AbsensiAlfa::query()->pluck('id');
        $komentarCount = AbsensiKomentar::query()
            ->where('target_type', 'alfa')
            ->when($alfaIds->isNotEmpty(), fn ($q) => $q->whereIn('target_id', $alfaIds))
            ->count();

        $alfaCount = $alfaIds->count();
        $harianCount = AbsensiHarian::query()->where('status', 'alfa')->count();

        $this->table(
            ['Tabel / data', 'Jumlah'],
            [
                ['absensi_komentar (target alfa)', $komentarCount],
                ['absensi_alfa', $alfaCount],
                ['absensi_harian (status=alfa)', $harianCount],
            ]
        );

        if ($dryRun) {
            $this->warn('Mode dry-run — tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Hapus semua data alfa di atas?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($alfaIds) {
            if ($alfaIds->isNotEmpty()) {
                AbsensiKomentar::query()
                    ->where('target_type', 'alfa')
                    ->whereIn('target_id', $alfaIds)
                    ->delete();
            }

            AbsensiAlfa::query()->delete();

            AbsensiHarian::query()->where('status', 'alfa')->delete();
        });

        $this->info('Semua data alfa berhasil dihapus.');

        return self::SUCCESS;
    }
}
