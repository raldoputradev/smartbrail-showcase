<?php

namespace App\Support;

use App\Models\PblActivityLog;
use App\Models\PblProject;
use App\Models\User;

/**
 * Pencatat jejak audit PBL. Selalu append-only — jangan tambahkan
 * penghapusan baris di sini; riwayat adalah bukti transparan.
 */
class PblActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $sebelum
     * @param  array<string, mixed>|null  $sesudah
     */
    public static function record(
        ?PblProject $project,
        ?User $actor,
        string $aksi,
        string $ringkasan,
        ?array $sebelum = null,
        ?array $sesudah = null,
        ?string $projectSnapshot = null,
    ): void {
        try {
            PblActivityLog::query()->create([
                'pbl_project_id' => $project?->id,
                'project_snapshot' => $projectSnapshot ?? ($project ? $project->kode.' · '.$project->nama : null),
                'actor_id' => $actor?->id,
                'actor_nama' => $actor?->nama_lengkap,
                'actor_role' => $actor?->role,
                'aksi' => $aksi,
                'ringkasan' => mb_substr($ringkasan, 0, 240),
                'sebelum' => $sebelum,
                'sesudah' => $sesudah,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable) {
            // Audit tidak boleh menggagalkan aksi utama.
        }
    }
}
