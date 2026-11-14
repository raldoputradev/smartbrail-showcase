<?php

namespace App\Console\Commands;

use App\Support\PblDosenLinker;
use Illuminate\Console\Command;

class LinkPblDosenCommand extends Command
{
    protected $signature = 'simalas:link-pbl-dosen';

    protected $description = 'Hubungkan kolom manpro_user_id / co_manpro_user_id dari teks manpro di tabel PBL';

    public function handle(): int
    {
        $before = 0;
        $after = 0;

        \App\Models\PblProject::query()->chunkById(50, function ($projects) use (&$before, &$after) {
            foreach ($projects as $project) {
                if ($project->manpro_user_id || $project->co_manpro_user_id) {
                    $before++;
                }
                if (PblDosenLinker::linkProject($project)) {
                    $after++;
                    $this->line("✓ {$project->kode} → manpro #{$project->manpro_user_id}" . ($project->co_manpro_user_id ? ", co #{$project->co_manpro_user_id}" : ''));
                }
            }
        });

        $this->info("Selesai. Proyek diperbarui: {$after}. (Sudah terhubung sebelumnya: {$before})");

        return self::SUCCESS;
    }
}
