<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Suggestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCommunicationsCommand extends Command
{
    protected $signature = 'simalas:reset-communications
        {--dry-run : Tampilkan jumlah tanpa menghapus}
        {--force : Hapus tanpa konfirmasi}';

    protected $description = 'Hapus semua pengumuman/notifikasi uji coba, balasan saran, dan reset hitungan baca untuk dosen & mahasiswa';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $announcementCount = Announcement::query()->count();
        $readCount = AnnouncementRead::query()->count();
        $suggestionCount = Suggestion::query()->count();

        $byChannel = Announcement::query()
            ->selectRaw('COALESCE(channel, \'(legacy)\') as channel, COUNT(*) as total')
            ->groupBy('channel')
            ->orderBy('channel')
            ->pluck('total', 'channel');

        $rows = [
            ['announcements', $announcementCount],
            ['announcement_reads', $readCount],
            ['suggestions', $suggestionCount],
        ];

        foreach ($byChannel as $channel => $total) {
            $rows[] = ["  ↳ channel: {$channel}", $total];
        }

        $this->table(['Data', 'Jumlah'], $rows);

        if ($dryRun) {
            $this->warn('Mode dry-run — tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Hapus semua pengumuman, status baca, dan saran di atas?', false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            AnnouncementRead::query()->delete();
            Suggestion::query()->delete();
            Announcement::query()->delete();
        });

        $this->info('Data komunikasi berhasil dibersihkan. Dosen & mahasiswa mulai dari nol.');

        return self::SUCCESS;
    }
}
