<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\User;

class PblDosenLinker
{
    public static function resolveUserId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $lower = mb_strtolower($name);

        return User::query()
            ->where('role', 'dosen')
            ->where('is_active', true)
            ->where(function ($q) use ($name, $lower) {
                $q->where('nama_lengkap', $name)
                    ->orWhereRaw('LOWER(nama_lengkap) = ?', [$lower])
                    ->orWhere('nama_lengkap', 'like', $name . '%')
                    ->orWhereRaw('LOWER(nama_lengkap) LIKE ?', [$lower . '%']);
            })
            ->value('id');
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    public static function linkAllProjects(): array
    {
        $updated = 0;
        $skipped = 0;

        PblProject::query()->chunkById(50, function ($projects) use (&$updated, &$skipped) {
            foreach ($projects as $project) {
                if (!self::linkProject($project)) {
                    $skipped++;
                }
            }
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public static function linkProject(PblProject $project): bool
    {
        $dirty = false;

        if (!$project->manpro_user_id && $project->manpro) {
            $id = self::resolveUserId($project->manpro);
            if ($id) {
                $project->manpro_user_id = $id;
                $dirty = true;
            }
        }

        if (!$project->co_manpro_user_id && $project->co_manpro) {
            $id = self::resolveUserId($project->co_manpro);
            if ($id) {
                $project->co_manpro_user_id = $id;
                $dirty = true;
            }
        }

        if ($dirty) {
            $project->save();
        }

        return $dirty;
    }
}
