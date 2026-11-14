<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\PblProject;
use App\Models\User;

class PblAssignmentNotifier
{
    /**
     * Beri tahu dosen yang baru ditetapkan sebagai manpro / co-manpro.
     *
     * @param  array{manpro: int, co_manpro: ?int}  $assigned
     * @param  array{manpro: int, co_manpro: ?int}|null  $previous
     */
    public function notifyAssignments(
        PblProject $project,
        User $admin,
        array $assigned,
        ?array $previous = null,
    ): void {
        $adminLabel = $this->adminLabel($admin);
        $projectLabel = $this->projectLabel($project);

        $prevManpro = $previous['manpro'] ?? null;
        $prevCo = $previous['co_manpro'] ?? null;
        $newManpro = (int) $assigned['manpro'];
        $newCo = isset($assigned['co_manpro']) ? (int) $assigned['co_manpro'] : null;

        if ($newManpro > 0 && $newManpro !== $prevManpro) {
            $this->publish(
                project: $project,
                dosenUserId: $newManpro,
                judul: "{$adminLabel} menetapkan Anda sebagai Manpro",
                isi: "Anda ditugaskan sebagai Manpro pada proyek PBL {$projectLabel}. Buka menu PBL & Loker untuk mengelola tim.",
                prioritas: 'penting',
                admin: $admin,
                roleKey: 'manpro',
            );
        }

        if ($newCo > 0 && $newCo !== $prevCo) {
            $this->publish(
                project: $project,
                dosenUserId: $newCo,
                judul: "{$adminLabel} menetapkan Anda sebagai Co-Manpro",
                isi: "Anda ditugaskan sebagai Co-Manpro pada proyek PBL {$projectLabel}. Buka menu PBL & Loker untuk membantu pengelolaan tim.",
                prioritas: 'penting',
                admin: $admin,
                roleKey: 'co_manpro',
            );
        }
    }

    private function publish(
        PblProject $project,
        int $dosenUserId,
        string $judul,
        string $isi,
        string $prioritas,
        User $admin,
        string $roleKey,
    ): void {
        $dosen = User::query()
            ->where('id', $dosenUserId)
            ->where('role', 'dosen')
            ->first();

        if (!$dosen) {
            return;
        }

        $ref = $this->referenceKey($project->id, $dosenUserId, $roleKey);

        Announcement::query()
            ->where('channel', Announcement::CHANNEL_NOTIFIKASI_PBL)
            ->where('judul', 'like', "[{$ref}]%")
            ->where('is_active', true)
            ->update(['is_active' => false]);

        Announcement::create([
            'judul' => "[{$ref}] {$judul}",
            'isi' => $isi,
            'target' => 'dosen',
            'target_tim' => (string) $dosenUserId,
            'channel' => Announcement::CHANNEL_NOTIFIKASI_PBL,
            'prioritas' => $prioritas,
            'dibuat_oleh' => $admin->id,
            'expires_at' => now()->addDays(60),
            'is_active' => true,
        ]);
    }

    private function referenceKey(int $projectId, int $userId, string $roleKey): string
    {
        return 'PBL:' . $projectId . ':' . $userId . ':' . $roleKey;
    }

    private function projectLabel(PblProject $project): string
    {
        $kode = trim((string) $project->kode);
        $nama = trim((string) $project->nama);

        if ($kode !== '' && $nama !== '') {
            return "{$kode} — {$nama}";
        }

        return $nama !== '' ? $nama : ($kode !== '' ? $kode : 'PBL');
    }

    private function adminLabel(User $admin): string
    {
        $name = trim((string) $admin->nama_lengkap);
        $role = match ($admin->role) {
            'laboran' => 'Admin Laboran',
            'dosen' => 'Dosen',
            default => ucfirst((string) $admin->role),
        };

        return $name !== '' ? "{$name} ({$role})" : $role;
    }
}
