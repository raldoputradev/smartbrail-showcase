<?php

namespace App\Console\Commands;

use App\Models\AbsensiHarian;
use App\Support\AbsensiKebijakan;
use Illuminate\Console\Command;

class BersihkanJejakKosongCommand extends Command
{
    protected $signature = 'absensi:bersihkan-jejak-kosong
        {--days=365 : Batas umur baris yang diperiksa}
        {--terapkan : Hapus baris yang memenuhi syarat. Tanpa opsi ini perintah hanya menghitung.}';

    protected $description = 'Hitung jejak absensi kosong di hari non-terjadwal. Penghapusan hanya lewat --terapkan, tidak dari GET riwayat.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $ids = AbsensiHarian::query()
            ->whereNull('masuk_log_id')
            ->whereNull('pulang_log_id')
            ->whereIn('status', ['belum', 'alfa'])
            ->where('tanggal', '>=', now()->subDays($days)->toDateString())
            ->orderBy('id')
            ->get()
            ->filter(fn (AbsensiHarian $row) => ! AbsensiKebijakan::isHariTerjadwal($row->tanggal))
            ->pluck('id');

        $this->info('Jejak kosong non-terjadwal: '.$ids->count().' baris.');

        if (! $this->option('terapkan')) {
            $this->line('Tidak ada baris yang dihapus.');

            return self::SUCCESS;
        }

        $deleted = $ids->isEmpty()
            ? 0
            : AbsensiHarian::query()->whereIn('id', $ids->all())->delete();
        $this->info('Baris dihapus: '.$deleted.'.');

        return self::SUCCESS;
    }
}
