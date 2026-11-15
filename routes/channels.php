<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('absensi.staff.laboran', function (User $user) {
    return $user->isLaboran()
        && (bool) $user->is_active
        && $user->approval_status === 'approved';
});

Broadcast::channel('absensi.staff.dosen', function (User $user) {
    return $user->isDosen()
        && (bool) $user->is_active
        && $user->approval_status === 'approved';
});

Broadcast::channel('mahasiswa.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId
        && (bool) $user->is_active
        && $user->approval_status === 'approved';
});

// Kanal bersama notifikasi — semua pengguna portal yang aktif & disetujui.
// Dipakai untuk sinyal pengumuman baru; isi notif tetap difilter per-user server-side.
Broadcast::channel('notifikasi.semua', function (User $user) {
    return (bool) $user->is_active
        && $user->approval_status === 'approved';
});
