<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;

class PblTeamAssignmentNotifier
{
    public function notifyKicked(
        User $mahasiswa,
        User $actor,
        PblProject $project,
        ?string $prevTim,
        ?string $prevShift,
    ): void {
        if ($mahasiswa->role !== 'mahasiswa') {
            return;
        }

        $actorLabel = $this->adminLabel($actor);
        $timLabel = $prevTim ?: $project->nama;

        $this->publish(
            $mahasiswa,
            $actor,
            'Anda dikeluarkan dari tim PBL',
            "{$actorLabel} mengeluarkan Anda dari tim PBL {$timLabel}. "
            . 'Status Anda sekarang: belum punya tim PBL. '
            . 'Untuk penempatan ulang, hubungi dosen pengampu atau admin '
            . '(nanti bisa lewat chat di aplikasi; sementara bisa lewat WhatsApp).',
        );
    }

    public function notifyChange(
        User $mahasiswa,
        User $admin,
        ?string $prevTim,
        ?string $prevShift,
        ?string $newTim,
        ?string $newShift,
    ): void {
        if ($mahasiswa->role !== 'mahasiswa') {
            return;
        }

        $prevTimNorm = $this->normTim($prevTim);
        $newTimNorm = $this->normTim($newTim);
        $prevShiftNorm = $this->normShift($prevShift);
        $newShiftNorm = $this->normShift($newShift);

        if ($prevTimNorm === $newTimNorm && $prevShiftNorm === $newShiftNorm) {
            return;
        }

        $adminLabel = $this->adminLabel($admin);
        $parts = [];

        if ($prevTimNorm !== $newTimNorm) {
            if ($newTimNorm === '') {
                $parts[] = 'tim PBL Anda dikosongkan (sebelumnya: ' . ($prevTim ?: '—') . ')';
            } elseif ($prevTimNorm === '') {
                $parts[] = "Anda ditempatkan ke tim PBL {$newTim}";
            } else {
                $parts[] = "tim PBL berubah dari {$prevTim} ke {$newTim}";
            }
        }

        if ($prevShiftNorm !== $newShiftNorm && $newShiftNorm !== '') {
            $parts[] = 'shift sesi: ' . ucfirst($newShiftNorm);
        }

        if ($parts === []) {
            return;
        }

        $this->publish(
            $mahasiswa,
            $admin,
            "{$adminLabel} memperbarui penempatan PBL Anda",
            implode('. ', $parts) . '. Buka aplikasi mobile untuk melihat detail tim.',
        );
    }

    /**
     * @param  list<array{user: User, prev_tim: ?string, prev_shift: ?string, new_tim: ?string, new_shift: ?string}>  $changes
     */
    public function notifyBatch(array $changes, User $admin): void
    {
        foreach ($changes as $change) {
            $this->notifyChange(
                $change['user'],
                $admin,
                $change['prev_tim'] ?? null,
                $change['prev_shift'] ?? null,
                $change['new_tim'] ?? null,
                $change['new_shift'] ?? null,
            );
        }
    }

    private function publish(User $mahasiswa, User $admin, string $judul, string $isi): void
    {
        $ref = 'PBL-MHS:' . $mahasiswa->id . ':' . now()->timestamp;

        Announcement::create([
            'judul' => "[{$ref}] {$judul}",
            'isi' => $isi,
            'target' => 'mahasiswa',
            'target_tim' => (string) $mahasiswa->id,
            'channel' => Announcement::CHANNEL_NOTIFIKASI_AKUN,
            'prioritas' => 'penting',
            'dibuat_oleh' => $admin->id,
            'expires_at' => now()->addDays(60),
            'is_active' => true,
        ]);
    }

    private function normTim(?string $value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }

    private function normShift(?string $value): string
    {
        $s = strtolower(trim((string) ($value ?? '')));

        return in_array($s, ['pagi', 'malam'], true) ? $s : '';
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
