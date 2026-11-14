<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        // Ensure rows exist for all menus per role.
        $this->ensureRows();

        $menus = PermissionRegistry::menus();
        $rows = RolePermission::query()->get()->groupBy('role');

        // Re-organize for view: [role => [menu_key => RolePermission]]
        $matrix = [];
        foreach ($rows as $role => $perms) {
            foreach ($perms as $p) {
                $matrix[$role][$p->menu_key] = $p;
            }
        }

        return view('admin.role_permissions', [
            'menus' => $menus,
            'matrix' => $matrix,
            'roles' => ['laboran', 'dosen', 'mahasiswa'],
        ]);
    }

    public function update(Request $request)
    {
        $payload = $request->input('access', []);
        if (!is_array($payload)) {
            return back()->withErrors(['access' => 'Format data tidak valid.']);
        }

        $allowedRoles = ['laboran', 'dosen', 'mahasiswa'];
        $allowedMenus = array_keys(PermissionRegistry::menus());
        $defaults = PermissionRegistry::defaults();

        foreach ($payload as $role => $menus) {
            if (!in_array($role, $allowedRoles, true) || !is_array($menus)) continue;
            foreach ($menus as $menuKey => $enabled) {
                if (!in_array($menuKey, $allowedMenus, true)) continue;

                $canView = (bool) $enabled;
                $existing = RolePermission::query()
                    ->where('role', $role)
                    ->where('menu_key', $menuKey)
                    ->first();
                [, $defaultCreate, $defaultUpdate, $defaultDelete] = $defaults[$role][$menuKey] ?? [false, false, false, false];

                // Halaman ini hanya mengatur akses tampilan. Aksi CRUD internal tetap mengikuti
                // default/izin yang sudah ada, tetapi otomatis dimatikan jika tampilan dicabut.
                RolePermission::query()->updateOrCreate(
                    ['role' => $role, 'menu_key' => $menuKey],
                    [
                        'can_view' => $canView,
                        'can_create' => $canView ? (bool) ($existing?->can_create ?? $defaultCreate) : false,
                        'can_update' => $canView ? (bool) ($existing?->can_update ?? $defaultUpdate) : false,
                        'can_delete' => $canView ? (bool) ($existing?->can_delete ?? $defaultDelete) : false,
                    ]
                );
            }
        }

        PermissionRegistry::clearCache();

        return back()->with('success', 'Konfigurasi akses role berhasil disimpan.');
    }

    public function resetDefaults()
    {
        PermissionRegistry::seedDefaults();
        return back()->with('success', 'Semua akses role dikembalikan ke nilai default.');
    }

    private function ensureRows(): void
    {
        foreach (PermissionRegistry::defaults() as $role => $menus) {
            foreach ($menus as $menuKey => [$view, $create, $update, $delete]) {
                RolePermission::query()->firstOrCreate(
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
    }
}
