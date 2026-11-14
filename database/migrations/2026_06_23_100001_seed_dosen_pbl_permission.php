<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RolePermission::query()->updateOrCreate(
            ['role' => 'dosen', 'menu_key' => 'dosen_pbl'],
            [
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ]
        );
    }

    public function down(): void
    {
        RolePermission::query()
            ->where('role', 'dosen')
            ->where('menu_key', 'dosen_pbl')
            ->delete();
    }
};
