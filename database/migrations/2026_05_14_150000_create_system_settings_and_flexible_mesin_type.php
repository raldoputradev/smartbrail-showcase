<?php

use App\Support\MachineAccessPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('mesin_iot') && Schema::hasColumn('mesin_iot', 'tipe') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mesin_iot MODIFY tipe VARCHAR(50) NOT NULL DEFAULT 'absensi'");
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'machine_access'],
            [
                'value' => json_encode(MachineAccessPolicy::defaults()),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->where('key', 'machine_access')->delete();
        }
    }
};
