<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Prop shell admin React (sidebar menu + lonceng notifikasi) — dipakai semua
 * halaman admin yang dirender lewat app-admin/AdminShell agar konsisten.
 */
trait AdminShellProps
{
    /**
     * Feed lonceng notifikasi untuk shell React — sumber sama dengan
     * NotificationComposer pada layout Blade (antrian operasional +
     * pengumuman belum dibaca).
     *
     * @return array<string, mixed>
     */
    private function adminNotifications(User $user): array
    {
        try {
            $opsItems = \App\Support\StaffNotificationService::operationalItems($user)
                ->map(fn ($it) => [
                    'id' => (string) ($it['id'] ?? ''),
                    'title' => (string) ($it['title'] ?? ''),
                    'body' => (string) ($it['body'] ?? ''),
                    'url' => (string) ($it['url'] ?? '#'),
                    'fingerprint' => $it['fingerprint'] ?? null,
                ])->values()->all();
            $opsCount = \App\Support\StaffNotificationService::operationalCount($user);
            $annQuery = \App\Models\Announcement::query()
                ->active()->bellFeed($user)->forAudience($user)->unreadBy($user);
            $announcements = (clone $annQuery)
                ->with('author:id,nama_lengkap,role')
                ->orderByDesc('created_at')->limit(10)->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'judul' => $a->judul,
                    'prioritas' => $a->prioritas ?: 'normal',
                    'isi' => Str::limit(strip_tags((string) $a->isi), 130),
                    'author' => optional($a->author)->nama_lengkap ?? 'Admin',
                    'waktu' => optional($a->created_at)->diffForHumans(),
                ])->all();
            $unread = $annQuery->count();
        } catch (\Throwable $e) {
            $opsItems = [];
            $opsCount = 0;
            $announcements = [];
            $unread = 0;
        }

        return [
            'ops' => $opsItems,
            'ops_count' => $opsCount,
            'announcements' => $announcements,
            'unread' => $unread,
            'total' => $unread + $opsCount,
            'urls' => [
                'read_all' => route('notifications.readAll'),
                'read_one' => url('/notifications/announcements'),
                'ops_dismiss' => route('notifications.ops.dismiss'),
                'announcements' => route('admin.announcements.index'),
                'notifications' => route('admin.notifications'),
            ],
        ];
    }

    /**
     * Menu sidebar admin — difilter permission backend (P::canCurrent) dan
     * batas admin (isAdmin). Frontend hanya merender daftar ini.
     *
     * @return array<int, array{title: ?string, nodes: array<int, array<string, mixed>>}>
     */
    private function adminSidebarMenu(User $user): array
    {
        // Tautan biasa; $match = pola routeIs() untuk status aktif.
        $link = function (string $perm, string $label, string $icon, string $routeName, array $params = [], ?int $badge = null, $match = null, ?string $feature = null): ?array {
            if (! \App\Support\PermissionRegistry::canCurrent($perm)) {
                return null;
            }
            $patterns = $match ?? [$routeName . '*'];
            return [
                'kind' => 'link',
                'label' => $label,
                'icon' => $icon,
                'url' => route($routeName, $params),
                'active' => request()->routeIs(...(array) $patterns),
                'badge' => $badge,
            ] + ($feature ? \App\Support\FeatureBadge::attrs($feature) : []);
        };

        // Grup collapsible; null bila semua anak terfilter permission.
        $group = function (string $key, string $label, string $icon, array $children): ?array {
            $children = array_values(array_filter($children));
            if ($children === []) {
                return null;
            }
            return [
                'kind' => 'group',
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'active' => collect($children)->contains(fn ($c) => ! empty($c['active'])),
                'children' => $children,
            ];
        };

        $roleLink = fn (string $role, string $label, string $icon): ?array =>
            \App\Support\PermissionRegistry::canCurrent('users')
                ? [
                    'kind' => 'link',
                    'label' => $label,
                    'icon' => $icon,
                    'url' => route('admin.users.role', $role),
                    'active' => request()->routeIs('admin.users.role') && request()->route('role') === $role,
                    'badge' => null,
                ]
                : null;

        $annBadge = 0;
        $opsBadge = 0;
        try {
            $annBadge = \App\Models\Announcement::query()
                ->active()->bellFeed($user)->forAudience($user)->unreadBy($user)->count();
            $opsBadge = \App\Support\StaffNotificationService::operationalCount($user);
        } catch (\Throwable $e) {
            // Badge opsional; kegagalan tidak boleh menggagalkan dashboard.
        }
        $sugBadge = (int) \App\Models\Suggestion::query()->where('status', 'baru')->count();
        $pengajuanBadge = 0;
        try {
            $pengajuanBadge = \App\Models\RuanganPengajuan::query()
                ->whereIn('status', \App\Models\RuanganPengajuan::STATUS_AKTIF_MENUNGGU)
                ->count();
        } catch (\Throwable $e) {
            // Badge opsional.
        }

        $sections = [
            [
                'title' => 'Menu Admin',
                'nodes' => array_values(array_filter([
                    $link('dashboard', 'Dashboard', 'layout-dashboard', 'admin.dashboard', [], null, 'admin.dashboard'),
                    $link('ai_assistant', 'Asisten AI', 'sparkles', 'admin.ai', [], null, 'admin.ai*', 'asisten-ai'),
                    $link('ai_assistant', 'Pelatihan AI', 'graduation-cap', 'admin.ai.training', [], null, 'admin.ai.training*', 'pelatihan-ai'),
                ])),
            ],
            [
                'title' => 'Operasional Laboratorium',
                'nodes' => array_values(array_filter([
                    $group('absensi-perangkat', 'Perangkat', 'clipboard-list', [
                        $link('mesin', 'Mesin', 'cpu', 'admin.mesin.index', [], null, 'admin.mesin.index'),
                        $link('mesin', 'Program Mesin', 'file-code', 'admin.mesin.panduan', [], null, 'admin.mesin.panduan', 'program-mesin'),
                        $link('monitoring', 'Monitoring Operasional', 'activity', 'admin.monitoring', [], null, 'admin.monitoring*'),
                        ($user->isAdmin() && \App\Support\PermissionRegistry::canCurrent('monitoring'))
                            ? [
                                'kind' => 'link',
                                'label' => 'Pusat Kendali',
                                'icon' => 'radar',
                                'url' => route('admin.pusat-kendali'),
                                'active' => request()->routeIs('admin.pusat-kendali*'),
                                'badge' => null,
                            ]
                            : null,
                        $link('settings', 'Atur Absensi', 'calendar', 'admin.absensi.settings', [], null, 'admin.absensi.settings'),
                        $link('attendance', 'Kalender & Agenda', 'calendar-days', 'admin.kalender', [], null, 'admin.kalender'),
                        $link('fingerprint', 'Fingerprint', 'fingerprint', 'admin.fingerprint', [], null, 'admin.fingerprint*'),
                        $link('log_absensi', 'Log Tap', 'scroll-text', 'admin.log.absensi', [], null, 'admin.log.absensi*'),
                    ]),
                    $group('manajemen-ruangan', 'Manajemen Ruangan', 'building-2', [
                        $link('ruangan', 'Ruangan', 'door-open', 'admin.ruangan.index', [], null, 'admin.ruangan*'),
                        $link('ruangan_persetujuan', 'Pengajuan Ruangan', 'calendar-check', 'admin.pengajuan.index', [], $pengajuanBadge ?: null, 'admin.pengajuan*'),
                        $link('petugas', 'Petugas Ruangan', 'brush', 'admin.petugas.index', [], null, 'admin.petugas*'),
                    ]),
                ])),
            ],
        ];

        // Data & Pengguna — lihat untuk semua pemilik perm users (laboran read-only);
        // mutasi akun & import tetap admin-only di level route.
        if (\App\Support\PermissionRegistry::canCurrent('users')) {
            $sections[] = [
                'title' => 'Data & Pengguna',
                'nodes' => array_values(array_filter([
                    $group('data-pengguna', 'Data Pengguna', 'users', [
                        $roleLink('mahasiswa', 'Mahasiswa', 'graduation-cap'),
                        $roleLink('dosen', 'Dosen', 'book-open'),
                        $roleLink('laboran', 'Laboran', 'flask-conical'),
                        \App\Support\PermissionRegistry::canCurrent('users')
                            ? [
                                'kind' => 'link',
                                'label' => 'Semua Pengguna',
                                'icon' => 'users',
                                'url' => route('admin.users'),
                                'active' => request()->routeIs('admin.users'),
                                'badge' => null,
                            ]
                            : null,
                        \App\Support\PermissionRegistry::canCurrent('users')
                            ? [
                                'kind' => 'link',
                                'label' => 'Akademik',
                                'icon' => 'graduation-cap',
                                'url' => route('admin.akademik'),
                                'active' => request()->routeIs('admin.akademik*'),
                                'badge' => null,
                            ]
                            : null,
                        ($user->isAdmin() && \App\Support\PermissionRegistry::canCurrent('users', 'create'))
                            ? [
                                'kind' => 'link',
                                'label' => 'Import Akun',
                                'icon' => 'file-up',
                                'url' => route('admin.users.import'),
                                'active' => request()->routeIs('admin.users.import*'),
                                'badge' => null,
                            ]
                            : null,
                    ]),
                    $group('proyek-pbl', 'Proyek PBL', 'clipboard-list', [
                        $link('users', 'Kelola Proyek', 'clipboard-list', 'admin.pbl.index', [], null, 'admin.pbl*'),
                        \App\Support\PermissionRegistry::canCurrent('users') ? [
                            'kind' => 'link',
                            'label' => 'Penilaian & Export',
                            'icon' => 'clipboard-check',
                            'url' => '#',
                            'active' => false,
                            'badge' => null,
                            'tag' => \App\Support\FeatureBadge::tag('penilaian-pbl'),
                            'soon' => true,
                        ] : null,
                        \App\Support\PermissionRegistry::canCurrent('users') ? [
                            'kind' => 'link',
                            'label' => 'Grup Chat Tim',
                            'icon' => 'messages-square',
                            'url' => '#',
                            'active' => false,
                            'badge' => null,
                            'tag' => \App\Support\FeatureBadge::tag('grup-chat-tim'),
                            'soon' => true,
                        ] : null,
                    ]),
                ])),
            ];
        }

        // Komunikasi & Laporan: grup top-level collapsible (tanpa judul seksi).
        $sections[] = [
            'title' => null,
            'nodes' => array_values(array_filter([
                $group('komunikasi', 'Komunikasi', 'megaphone', [
                    $link('announcements', 'Pengumuman', 'megaphone', 'admin.announcements.index', [], $annBadge ?: null, 'admin.announcements*'),
                    $link('suggestions', 'Saran & Masukan', 'message-square', 'admin.suggestions.index', [], $sugBadge ?: null, 'admin.suggestions*'),
                    $link('notifications', 'Antrian Notifikasi', 'list-todo', 'admin.notifications', [], $opsBadge ?: null, 'admin.notifications'),
                ]),
            ])),
        ];

        $sections[] = [
            'title' => null,
            'nodes' => array_values(array_filter([
                $group('laporan-audit', 'Laporan & Audit', 'file-text', [
                    $link('attendance', 'Rekap Harian', 'clipboard-check', 'admin.attendance', [], null, ['admin.attendance', 'admin.absensi.alfa', 'admin.absensi.tidak-lengkap*']),
                    $link('reports', 'Laporan Periodik', 'bar-chart-3', 'admin.reports', [], null, 'admin.reports*'),
                    $link('reports', 'Jejak Aktivitas', 'history', 'admin.jejak-aktivitas', [], null, 'admin.jejak-aktivitas*'),
                ]),
            ])),
        ];

        if ($user->isAdmin()) {
            $sections[] = [
                'title' => 'Administrasi',
                'nodes' => array_values(array_filter([
                    $link('settings', 'Pengaturan', 'settings', 'admin.settings', [], null, 'admin.settings'),
                ])),
            ];
        }

        // Akun — profil & keamanan untuk semua pemilik menu admin (admin + laboran).
        $sections[] = [
            'title' => 'Akun',
            'nodes' => [
                [
                    'kind' => 'link',
                    'label' => 'Profil Saya',
                    'icon' => 'user',
                    'url' => route('profiles.show', $user),
                    'active' => request()->routeIs('profiles.show'),
                    'badge' => null,
                ],
            ],
        ];

        return array_values(array_filter($sections, fn ($sec) => count($sec['nodes']) > 0));
    }

    /**
     * Menu sidebar dosen — dipakai halaman React dosen (Dashboard, Profil, dst)
     * lewat AdminShell. Struktur sama dengan partial Blade dosen._sidebar.
     *
     * @return array<int, array{title: ?string, nodes: array<int, array<string, mixed>>}>
     */
    private function dosenSidebarMenu(User $user): array
    {
        $link = function (string $perm, string $label, string $icon, string $routeName, array $params = [], ?int $badge = null, $match = null, ?string $feature = null): ?array {
            if (! \App\Support\PermissionRegistry::canCurrent($perm)) {
                return null;
            }
            $patterns = $match ?? [$routeName . '*'];
            return [
                'kind' => 'link',
                'label' => $label,
                'icon' => $icon,
                'url' => route($routeName, $params),
                'active' => request()->routeIs(...(array) $patterns),
                'badge' => $badge,
            ] + ($feature ? \App\Support\FeatureBadge::attrs($feature) : []);
        };
        $group = function (string $key, string $label, string $icon, array $children): ?array {
            $children = array_values(array_filter($children));
            if ($children === []) {
                return null;
            }
            return [
                'kind' => 'group',
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'active' => collect($children)->contains(fn ($c) => ! empty($c['active'])),
                'children' => $children,
            ];
        };

        $annBadge = 0;
        try {
            $annBadge = \App\Models\Announcement::query()
                ->active()->bellFeed($user)->forAudience($user)->unreadBy($user)->count();
        } catch (\Throwable $e) {
            // Badge opsional.
        }

        // Menu dosen: klaster accordion (key disamakan dengan sidebar Blade
        // dosen._sidebar agar status buka/tutup bertahan lintas React ↔ Blade).
        $keamananUrl = route('profiles.show', $user) . '#status-akun';

        $sections = [
            [
                'title' => 'Menu Dosen',
                'nodes' => array_values(array_filter([
                    $group('dosen-main', 'Utama', 'layout-dashboard', [
                        $link('dashboard', 'Dashboard', 'layout-dashboard', 'dosen.dashboard', [], null, 'dosen.dashboard'),
                        \App\Support\PermissionRegistry::canCurrent('ai_assistant')
                            && (\App\Support\PermissionRegistry::canCurrent('dosen_absensi') || \App\Support\PermissionRegistry::canCurrent('dosen_mahasiswa'))
                            ? [
                                'kind' => 'link',
                                'label' => 'Asisten AI',
                                'icon' => 'sparkles',
                                'url' => route('dosen.ai'),
                                'active' => request()->routeIs('dosen.ai*'),
                                'badge' => null,
                                'tag' => \App\Support\FeatureBadge::tag('asisten-ai'),
                            ]
                            : null,
                    ]),
                    $group('dosen-rekap', 'Rekap Absensi', 'bar-chart-3', [
                        $link('dosen_absensi', 'Rekap Harian', 'clipboard-check', 'dosen.absensi', [], null, ['dosen.absensi', 'dosen.absensi.alfa', 'dosen.absensi.tidak-lengkap*']),
                        $link('dosen_absensi', 'Laporan Periodik', 'bar-chart-3', 'dosen.reports', [], null, 'dosen.reports*'),
                    ]),
                    $group('dosen-academic', 'Akademik', 'graduation-cap', [
                        $link('dosen_mahasiswa', 'Data Mahasiswa', 'users', 'dosen.mahasiswa', [], null, 'dosen.mahasiswa'),
                        $link('dosen_pbl', 'Pengajuan Ruangan', 'door-open', 'dosen.ruangan', [], null, 'dosen.ruangan'),
                    ]),
                    $group('dosen-pbl', 'Proyek PBL', 'clipboard-list', [
                        $link('dosen_pbl', 'Proyek PBL Saya', 'clipboard-list', 'dosen.pbl.index', [], null, 'dosen.pbl*'),
                        \App\Support\PermissionRegistry::canCurrent('dosen_pbl') ? [
                            'kind' => 'link',
                            'label' => 'Manajemen Tim',
                            'icon' => 'users',
                            'url' => '#',
                            'active' => false,
                            'badge' => null,
                            'tag' => \App\Support\FeatureBadge::tag('manajemen-tim'),
                            'soon' => true,
                        ] : null,
                        \App\Support\PermissionRegistry::canCurrent('dosen_pbl') ? [
                            'kind' => 'link',
                            'label' => 'Penilaian PBL',
                            'icon' => 'clipboard-check',
                            'url' => '#',
                            'active' => false,
                            'badge' => null,
                            'tag' => \App\Support\FeatureBadge::tag('penilaian-pbl'),
                            'soon' => true,
                        ] : null,
                        \App\Support\PermissionRegistry::canCurrent('dosen_pbl') ? [
                            'kind' => 'link',
                            'label' => 'Grup Chat Tim',
                            'icon' => 'messages-square',
                            'url' => '#',
                            'active' => false,
                            'badge' => null,
                            'tag' => \App\Support\FeatureBadge::tag('grup-chat-tim'),
                            'soon' => true,
                        ] : null,
                    ]),
                    $group('dosen-comm', 'Komunikasi', 'megaphone', [
                        $link('announcements', 'Pengumuman', 'megaphone', 'dosen.announcements', [], $annBadge ?: null, 'dosen.announcements'),
                        [
                            'kind' => 'link',
                            'label' => 'Saran & Masukan',
                            'icon' => 'message-square',
                            'url' => route('dosen.feedback.index'),
                            'active' => request()->routeIs('dosen.feedback*'),
                            'badge' => null,
                        ],
                    ]),
                    $group('dosen-account', 'Akun', 'user', [
                        $link('dosen_profile', 'Profil Saya', 'user', 'dosen.profile', [], null, ['dosen.profile', 'profiles.show']),
                        \App\Support\PermissionRegistry::canCurrent('dosen_profile') ? [
                            'kind' => 'link',
                            'label' => 'Keamanan Akun',
                            'icon' => 'shield-check',
                            'url' => $keamananUrl,
                            'active' => request()->routeIs('profiles.show'),
                            'badge' => null,
                        ] : null,
                    ]),
                ])),
            ],
        ];

        return array_values(array_filter($sections, fn ($sec) => count($sec['nodes']) > 0));
    }

    /**
     * Feed lonceng notifikasi untuk dosen — pengumuman + item operasional,
     * URL mengarah ke halaman dosen (bukan admin.*).
     *
     * @return array<string, mixed>
     */
    private function dosenNotifications(User $user): array
    {
        try {
            $opsItems = \App\Support\StaffNotificationService::operationalItems($user)
                ->map(fn ($it) => [
                    'id' => (string) ($it['id'] ?? ''),
                    'title' => (string) ($it['title'] ?? ''),
                    'body' => (string) ($it['body'] ?? ''),
                    'url' => (string) ($it['url'] ?? '#'),
                    'fingerprint' => $it['fingerprint'] ?? null,
                ])->values()->all();
            $opsCount = \App\Support\StaffNotificationService::operationalCount($user);
            $annQuery = \App\Models\Announcement::query()
                ->active()->bellFeed($user)->forAudience($user)->unreadBy($user);
            $announcements = (clone $annQuery)
                ->with('author:id,nama_lengkap,role')
                ->orderByDesc('created_at')->limit(10)->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'judul' => $a->judul,
                    'prioritas' => $a->prioritas ?: 'normal',
                    'isi' => Str::limit(strip_tags((string) $a->isi), 130),
                    'author' => optional($a->author)->nama_lengkap ?? 'Admin',
                    'waktu' => optional($a->created_at)->diffForHumans(),
                ])->all();
            $unread = $annQuery->count();
        } catch (\Throwable $e) {
            $opsItems = [];
            $opsCount = 0;
            $announcements = [];
            $unread = 0;
        }

        return [
            'ops' => $opsItems,
            'ops_count' => $opsCount,
            'announcements' => $announcements,
            'unread' => $unread,
            'total' => $unread + $opsCount,
            'urls' => [
                'read_all' => route('notifications.readAll'),
                'read_one' => url('/notifications/announcements'),
                'ops_dismiss' => route('notifications.ops.dismiss'),
                'announcements' => route('dosen.announcements'),
                'notifications' => route('dosen.notifikasi'),
            ],
        ];
    }
}
