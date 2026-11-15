<?php

namespace App\Support;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PermissionRegistry
{
    /**
     * Daftar menu yang bisa dikontrol per role.
     * Setiap entri: key => [label, default_per_role]
     * default_per_role: array per role 'laboran'/'dosen'/'mahasiswa' dengan flag [view,create,update,delete]
     */
    public static function menus(): array
    {
        // Format: 'menu_key' => ['label', 'group']
        return [
            'dashboard'      => ['Dashboard', 'Umum'],
            'users'          => ['Manajemen User', 'Admin'],
            'mesin'          => ['Mesin IoT', 'Admin'],
            'log_absensi'    => ['Log Absensi Real', 'Monitoring'],
            'announcements'  => ['Pengumuman', 'Komunikasi'],
            'suggestions'    => ['Saran & Masukan', 'Komunikasi'],
            'notifications'  => ['Notifikasi', 'Komunikasi'],
            'monitoring'     => ['Monitoring', 'Monitoring'],
            'attendance'     => ['Cek Absensi', 'Monitoring'],
            'fingerprint'    => ['Fingerprint Enroll', 'Admin'],
            'reports'        => ['Laporan', 'Monitoring'],
            'settings'       => ['Pengaturan', 'Admin'],
            'ai_assistant'   => ['Asisten AI', 'Umum'],
            // SMART BRAIL — BMS ruangan
            'ruangan'        => ['Ruangan & Aturan', 'BMS'],
            'ruangan_persetujuan' => ['Persetujuan Pengajuan', 'BMS'],
            'petugas'        => ['Petugas Ruangan', 'BMS'],
            // Khusus dosen
            'dosen_absensi'  => ['Absensi Mahasiswa', 'Dosen'],
            'dosen_mahasiswa'=> ['Data Mahasiswa', 'Dosen'],
            'dosen_pbl'      => ['Tim PBL', 'Dosen'],
            'dosen_profile'  => ['Profil Dosen', 'Dosen'],
        ];
    }

    /**
     * Default permissions per role (untuk seed awal & reset).
     */
    public static function defaults(): array
    {
        $menus = array_keys(self::menus());
        $empty = array_fill_keys($menus, [false, false, false, false]);

        $defaults = [
            'admin' => $empty,
            'laboran' => $empty,
            'dosen' => $empty,
            'mahasiswa' => $empty,
        ];

        $defaults['laboran'] = array_replace($defaults['laboran'], [
            // Full access untuk menu admin yang memang berada di area laboran.
            'dashboard'      => [true, true, true, true],
            'users'          => [true, true, true, true],
            'mesin'          => [true, true, true, true],
            'log_absensi'    => [true, false, false, false],
            'announcements'  => [true, true, true, true],
            'suggestions'    => [true, false, true, true],
            'notifications'  => [true, false, false, false],
            'monitoring'     => [true, false, false, false],
            'attendance'     => [true, false, true, false],
            'fingerprint'    => [true, true, true, true],
            'reports'        => [true, false, false, false],
            'settings'       => [true, true, true, false],
            'ai_assistant'   => [true, false, false, false],
            'ruangan'        => [true, true, true, false],
            'ruangan_persetujuan' => [true, false, true, false],
            'petugas'        => [true, true, true, false],
        ]);

        // Role admin mewarisi seluruh kapabilitas operasional laboran;
        // aksi khusus admin tetap digerbang middleware admin.only di level route.
        $defaults['admin'] = $defaults['laboran'];

        $defaults['dosen'] = array_replace($defaults['dosen'], [
            'dashboard'       => [true, false, false, false],
            'announcements'   => [true, true, false, false],
            'dosen_absensi'   => [true, false, true, false],
            'dosen_mahasiswa' => [true, false, false, false],
            'dosen_pbl'       => [true, true, true, true],
            'dosen_profile'   => [true, false, true, false],
            'ai_assistant'    => [true, false, false, false],
        ]);

        $defaults['mahasiswa'] = array_replace($defaults['mahasiswa'], [
            'announcements' => [true, false, false, false],
            'reports' => [true, true, false, false],
        ]);

        return $defaults;
    }

    public static function seedDefaults(): void
    {
        foreach (self::defaults() as $role => $menus) {
            // Role 'admin' tidak ditulis ke DB: izinnya berasal dari fallback defaults
            // (salinan laboran). Migration lama yang memanggil seedDefaults tetap aman
            // terhadap enum 'role' versi lama tanpa nilai 'admin'.
            if ($role === 'admin') {
                continue;
            }
            foreach ($menus as $menuKey => [$view, $create, $update, $delete]) {
                RolePermission::query()->updateOrCreate(
                    ['role' => $role, 'menu_key' => $menuKey],
                    [
                        'can_view' => $view,
                        'can_create' => $create,
                        'can_update' => $update,
                        'can_delete' => $delete,
                    ]
                );
            }
        }
        self::clearCache();
    }

    public static function clearCache(): void
    {
        Cache::forget('role_permissions_map');
    }

    public static function map(): array
    {
        return Cache::remember('role_permissions_map', 300, function () {
            $rows = RolePermission::query()->get();
            $map = [];
            foreach ($rows as $row) {
                $map[$row->role][$row->menu_key] = [
                    'view' => (bool) $row->can_view,
                    'create' => (bool) $row->can_create,
                    'update' => (bool) $row->can_update,
                    'delete' => (bool) $row->can_delete,
                ];
            }
            return $map;
        });
    }

    public static function can(string $role, string $menuKey, string $action = 'view'): bool
    {
        $map = self::map();
        if (array_key_exists($action, $map[$role][$menuKey] ?? [])) {
            return (bool) $map[$role][$menuKey][$action];
        }

        $defaults = self::defaults();
        $index = array_search($action, ['view', 'create', 'update', 'delete'], true);
        if ($index === false) {
            return false;
        }

        return (bool) ($defaults[$role][$menuKey][$index] ?? false);
    }

    public static function canCurrent(string $menuKey, string $action = 'view'): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return self::can($user->role, $menuKey, $action);
    }

}
