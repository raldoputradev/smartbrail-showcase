<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mesin_iot') && !Schema::hasColumn('mesin_iot', 'locker_slots')) {
            Schema::table('mesin_iot', function (Blueprint $table) {
                $table->json('locker_slots')->nullable()->after('allowed_teams');
            });
        }

        if (Schema::hasTable('log_loker') && !Schema::hasColumn('log_loker', 'slot')) {
            Schema::table('log_loker', function (Blueprint $table) {
                $table->unsignedTinyInteger('slot')->nullable()->after('id_mesin');
            });
        }

        if (!Schema::hasTable('mesin_iot')) {
            return;
        }

        $defaultSlots = [];
        for ($i = 1; $i <= 6; $i++) {
            $defaultSlots[(string) $i] = ['allowed_team' => null, 'note' => null];
        }
        $json = json_encode($defaultSlots);

        DB::table('mesin_iot')->where('tipe', 'loker')->whereNull('locker_slots')->update(['locker_slots' => $json]);
    }

    public function down(): void
    {
        if (Schema::hasTable('log_loker') && Schema::hasColumn('log_loker', 'slot')) {
            Schema::table('log_loker', function (Blueprint $table) {
                $table->dropColumn('slot');
            });
        }

        if (Schema::hasTable('mesin_iot') && Schema::hasColumn('mesin_iot', 'locker_slots')) {
            Schema::table('mesin_iot', function (Blueprint $table) {
                $table->dropColumn('locker_slots');
            });
        }
    }
};
