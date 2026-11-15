<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Kanal operasional ruangan — laboran dan admin sama-sama berwenang
// memutuskan pengajuan, jadi keduanya menerima sinyal pengajuan.updated.
Broadcast::channel('absensi.staff.laboran', function (User $user) {
    return ($user->isLaboran() || $user->isAdmin())
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

// Kanal bersama ketersediaan ruangan — sinyal tipis "ada slot berubah" agar grid
// jadwal di halaman pengajuan ikut segar tanpa refresh (mis. tim lain membatalkan).
// Payload hanya metadata pengajuan; detail slot tetap diambil dari endpoint.
Broadcast::channel('ruangan.ketersediaan', function (User $user) {
    return (bool) $user->is_active
        && $user->approval_status === 'approved';
});
