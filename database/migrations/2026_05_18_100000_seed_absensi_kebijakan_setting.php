<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('system_settings')->where('key', 'absensi_kebijakan')->exists()) {
            DB::table('system_settings')->insert([
                'key' => 'absensi_kebijakan',
                'value' => json_encode([
                    'mode' => 'kamis',
                    'hari_wajib' => [4],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'absensi_kebijakan')->delete();
    }
};
