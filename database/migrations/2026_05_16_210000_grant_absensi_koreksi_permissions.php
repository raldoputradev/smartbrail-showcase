<?php

use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('role', 'laboran')
            ->where('menu_key', 'attendance')
            ->update(['can_update' => true, 'updated_at' => now()]);

        DB::table('role_permissions')
            ->where('role', 'dosen')
            ->where('menu_key', 'dosen_absensi')
            ->update(['can_update' => true, 'updated_at' => now()]);

        PermissionRegistry::clearCache();
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('role', 'laboran')
            ->where('menu_key', 'attendance')
            ->update(['can_update' => false, 'updated_at' => now()]);

        DB::table('role_permissions')
            ->where('role', 'dosen')
            ->where('menu_key', 'dosen_absensi')
            ->update(['can_update' => false, 'updated_at' => now()]);

        PermissionRegistry::clearCache();
    }
};
