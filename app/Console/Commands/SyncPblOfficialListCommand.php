<?php

namespace App\Console\Commands;

use App\Models\PblProject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncPblOfficialListCommand extends Command
{
    protected $signature = 'simalas:sync-pbl-official
                            {--only-projects : Hanya proyek, tanpa ubah mahasiswa}
                            {--nim= : Sinkron satu mahasiswa: NIM,nama_pbl (contoh: 4222301063,AIoT (Si Malas))}';

    protected $description = 'Isi tabel pbl_projects dari daftar resmi PDF & samakan tim mahasiswa';

    /**
     * Label tim lama (dropdown uji) → nama PBL resmi di PDF.
     *
     * @var array<string, string>
     */
    private array $timAliasMap = [
        'aiot' => 'AIoT (Si Malas)',
        'si malas' => 'AIoT (Si Malas)',
        'embedded' => 'KRAI',
        'robotika' => 'Introduction to Robotic A',
        'vision' => 'Visual Servoing for Robot Manipulators',
    ];

    /**
     * NIM uji / khusus → nama PBL (override alias).
     *
     * @var array<string, string>
     */
    private array $nimTimOverride = [
        '4222301063' => 'AIoT (Si Malas)',
        '4222301088' => 'AIoT (Si Malas)',
        '4222301067' => 'Introduction to Robotic A',
        '4222301061' => 'KRAI',
        '4222301093' => 'Visual Servoing for Robot Manipulators',
    ];

    public function handle(): int
    {
        $path = database_path('data/pbl_official_list.php');
        if (!is_file($path)) {
            $this->error('File tidak ditemukan: ' . $path);

            return self::FAILURE;
        }

        /** @var list<array{kode: string, nama: string, manpro: string, co_manpro: ?string, shift_sesi: string}> $rows */
        $rows = require $path;
        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $manproId = $this->findDosenId($row['manpro']);
            $coId = $this->findDosenId($row['co_manpro'] ?? null);

            $existing = PblProject::query()->where('kode', $row['kode'])->exists();

            PblProject::query()->updateOrCreate(
                ['kode' => $row['kode']],
                [
                    'nama' => $row['nama'],
                    'manpro' => $row['manpro'],
                    'co_manpro' => $row['co_manpro'],
                    'shift_sesi' => $row['shift_sesi'],
                    'manpro_user_id' => $manproId,
                    'co_manpro_user_id' => $coId,
                    'aktif' => true,
                ]
            );

            $existing ? $updated++ : $imported++;
        }

        $this->info("Proyek PBL: {$imported} baru, {$updated} diperbarui (total " . count($rows) . ').');

        if ($this->option('only-projects')) {
            return self::SUCCESS;
        }

        $nimArg = $this->option('nim');
        if ($nimArg !== null && $nimArg !== '') {
            $parts = array_map('trim', explode(',', (string) $nimArg, 2));
            if (count($parts) < 2) {
                $this->error('Format --nim=NIM,nama_pbl');

                return self::FAILURE;
            }
            $this->assignMahasiswa($parts[0], $parts[1]);

            return self::SUCCESS;
        }

        $synced = 0;
        $users = User::query()->where('role', 'mahasiswa')->where('is_active', true)->get();
        foreach ($users as $user) {
            $namaPbl = $this->resolveTimForUser($user);
            if ($namaPbl === null) {
                $this->warn("Lewati {$user->nitk_nidn} — tim kosong / tidak dikenali: " . ($user->tim ?? '-'));

                continue;
            }
            $this->assignMahasiswa($user->nitk_nidn, $namaPbl);
            $synced++;
        }

        $this->info("Mahasiswa diselaraskan: {$synced} akun.");

        return self::SUCCESS;
    }

    private function assignMahasiswa(string $nim, string $namaPbl): void
    {
        $user = User::query()->where('role', 'mahasiswa')->where('nitk_nidn', $nim)->first();
        if (!$user) {
            $this->warn("NIM {$nim} tidak ditemukan.");

            return;
        }

        $project = PblProject::query()
            ->where('aktif', true)
            ->where(function ($q) use ($namaPbl) {
                $q->where('nama', $namaPbl)->orWhere('kode', $namaPbl);
            })
            ->first();

        if (!$project) {
            $this->warn("PBL tidak ditemukan: {$namaPbl}");

            return;
        }

        $user->tim = $project->nama;
        $user->shift = $project->shift_sesi;
        $user->save();

        $this->line("  {$nim} → {$project->kode} · {$project->nama} ({$project->shift_sesi})");
    }

    private function resolveTimForUser(User $user): ?string
    {
        $nim = (string) $user->nitk_nidn;
        if (isset($this->nimTimOverride[$nim])) {
            return $this->nimTimOverride[$nim];
        }

        $tim = trim((string) ($user->tim ?? ''));
        if ($tim === '') {
            return null;
        }

        $project = PblProject::query()
            ->where('aktif', true)
            ->where(function ($q) use ($tim) {
                $q->where('nama', $tim)->orWhere('kode', $tim);
            })
            ->first();
        if ($project) {
            return $project->nama;
        }

        $key = Str::lower($tim);
        if (isset($this->timAliasMap[$key])) {
            return $this->timAliasMap[$key];
        }

        return null;
    }

    private function findDosenId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $user = User::query()
            ->where('role', 'dosen')
            ->where(function ($q) use ($name) {
                $q->where('nama_lengkap', $name)
                    ->orWhere('nama_lengkap', 'like', $name . '%');
            })
            ->first();

        return $user?->id;
    }
}
