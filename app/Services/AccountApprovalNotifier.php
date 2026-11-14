<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Support\AsyncMailer;

class AccountApprovalNotifier
{
    public function notifyApproved(User $user, User $admin): void
    {
        $this->publishInApp($user, $admin, 'approved');
        $this->sendEmail($user, 'approved');
    }

    public function notifyRejected(User $user, User $admin, string $note): void
    {
        $this->publishInApp($user, $admin, 'rejected', $note);
        $this->sendEmail($user, 'rejected', $note);
    }

    private function publishInApp(User $user, User $admin, string $status, ?string $note = null): void
    {
        if (!in_array($user->role, ['mahasiswa', 'dosen'], true)) {
            return;
        }

        $ref = $this->referenceKey($user->id, $status);
        $adminLabel = $this->adminLabel($admin);

        if ($status === 'approved') {
            $judul = 'Akun Anda disetujui admin';
            $isi = "{$adminLabel} telah menyetujui pendaftaran akun Anda. {$this->loginHint($user)}";
            $prioritas = 'penting';
        } else {
            $judul = 'Pendaftaran akun ditolak admin';
            $reason = trim($note ?? '');
            $isi = "{$adminLabel} menolak pendaftaran akun Anda."
                . ($reason !== '' ? " Alasan: {$reason}" : '')
                . ' Hubungi admin laboran jika Anda perlu klarifikasi.';
            $prioritas = 'urgent';
        }

        Announcement::query()
            ->where('channel', Announcement::CHANNEL_NOTIFIKASI_AKUN)
            ->where('judul', 'like', "[{$ref}]%")
            ->where('is_active', true)
            ->update(['is_active' => false]);

        Announcement::create([
            'judul' => "[{$ref}] {$judul}",
            'isi' => $isi,
            'target' => $user->role,
            'target_tim' => (string) $user->id,
            'channel' => Announcement::CHANNEL_NOTIFIKASI_AKUN,
            'prioritas' => $prioritas,
            'dibuat_oleh' => $admin->id,
            'expires_at' => now()->addDays(90),
            'is_active' => true,
        ]);
    }

    private function sendEmail(User $user, string $status, ?string $note = null): void
    {
        if (!filled($user->email)) {
            return;
        }

        AsyncMailer::dispatchAccountStatusMail($user, $status, $note);
    }

    private function referenceKey(int $userId, string $status): string
    {
        return 'AKUN:' . $userId . ':' . $status;
    }

    private function loginHint(User $user): string
    {
        return match ($user->role) {
            'mahasiswa' => 'Anda sudah bisa login ke aplikasi mobile SIMALAS.',
            'dosen' => 'Anda sudah bisa login ke panel web dosen SIMALAS.',
            default => 'Anda sudah bisa login ke SIMALAS.',
        };
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
