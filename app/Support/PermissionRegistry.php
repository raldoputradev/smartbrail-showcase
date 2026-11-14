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
            'role_access'    => ['Kelola Akses Role', 'Admin'],
            'suggestions'    => ['Saran & Masukan', 'Komunikasi'],
            'notifications'  => ['Notifikasi', 'Komunikasi'],
            'monitoring'     => ['Monitoring', 'Monitoring'],
            'attendance'     => ['Cek Absensi', 'Monitoring'],
            'fingerprint'    => ['Fingerprint Enroll', 'Admin'],
            'reports'        => ['Laporan', 'Monitoring'],
            'settings'       => ['Pengaturan', 'Admin'],
            'ai_assistant'   => ['Asisten AI', 'Umum'],
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
            'role_access'    => [true, true, true, true],
            'suggestions'    => [true, false, true, true],
            'notifications'  => [true, false, false, false],
            'monitoring'     => [true, false, false, false],
            'attendance'     => [true, false, true, false],
            'fingerprint'    => [true, true, true, true],
            'reports'        => [true, false, false, false],
            'settings'       => [true, true, true, false],
            'ai_assistant'   => [true, false, false, false],
        ]);

        $defaults['dosen'] = array_replace($defaults['dosen'], [
            'dashboard'       => [true, false, false, false],
            'announcements'   => [true, false, false, false],
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

    public static function roleLabels(): array
    {
        return [
            'laboran' => 'Admin Laboran',
            'dosen' => 'Dosen',
            'mahasiswa' => 'Mahasiswa Mobile',
        ];
    }

    public static function activeMenuLabels(string $role): array
    {
        $menus = self::menus();
        $items = [];

        foreach ($menus as $menuKey => [$label]) {
            if (self::can($role, $menuKey)) {
                $items[$menuKey] = $label;
            }
        }

        return $items;
    }

    public static function roleAccessCards(?string $activeRole = null): array
    {
        $cards = [
            'laboran' => [
                'title' => 'Admin Laboran',
                'subtitle' => 'Pusat kontrol IoT SIMALAS',
                'accent' => 'blue',
                'summary' => 'Mengelola user, mesin IoT, pengumuman, monitoring, dan saran masuk.',
                'benefits' => [
                    'Kelola data dosen, mahasiswa, dan aktivasi akun.',
                    'Atur mesin absensi, fingerprint, dan API key perangkat.',
                    'Publikasikan pengumuman dan pantau log real-time.',
                    'Opsional: batasi menu web dosen lewat konfigurasi lanjutan.',
                ],
                'limitations' => [
                    'Akses tetap mengikuti centang permission yang dibuat superadmin/laboran.',
                ],
            ],
            'dosen' => [
                'title' => 'Dosen',
                'subtitle' => 'Monitoring akademik dan rekap mahasiswa',
                'accent' => 'green',
                'summary' => 'Melihat dashboard, pengumuman, rekap absensi, dan data mahasiswa sesuai izin admin.',
                'benefits' => [
                    'Melihat grafik dan rekap absensi mahasiswa.',
                    'Membaca pengumuman dari admin laboran.',
                    'Akses menu dapat dibatasi atau dibuka oleh admin.',
                ],
                'limitations' => [
                    'Tidak dapat mengubah data mesin, user, atau role access.',
                    'Menu yang dicabut admin akan hilang dan URL langsung ikut diblokir.',
                ],
            ],
            'mahasiswa' => [
                'title' => 'Mahasiswa Mobile',
                'subtitle' => 'Akses pribadi lewat aplikasi Flutter',
                'accent' => 'amber',
                'summary' => 'Mahasiswa tidak login ke web. Data pribadi, riwayat absensi, dan pengumuman dibuka dari aplikasi mobile.',
                'benefits' => [
                    'Melihat riwayat absensi sendiri dari aplikasi mobile.',
                    'Menerima pengumuman yang ditargetkan ke mahasiswa.',
                    'Menggunakan fingerprint/finger_id untuk integrasi mesin.',
                ],
                'limitations' => [
                    'Tidak memakai dashboard web admin/dosen.',
                    'Menu aplikasi mobile tidak dikontrol dari halaman permission.',
                ],
            ],
        ];

        foreach ($cards as $role => $card) {
            $cards[$role]['is_active'] = $activeRole === $role;
            $cards[$role]['active_menus'] = self::activeMenuLabels($role);
        }

        return $cards;
    }
}
