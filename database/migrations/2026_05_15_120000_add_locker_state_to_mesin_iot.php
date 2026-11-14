<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mesin_iot')) {
            return;
        }

        Schema::table('mesin_iot', function (Blueprint $table) {
            if (!Schema::hasColumn('mesin_iot', 'locker_state')) {
                $table->string('locker_state', 20)->default('open')->after('allowed_teams');
            }
            if (!Schema::hasColumn('mesin_iot', 'locker_state_note')) {
                $table->string('locker_state_note', 255)->nullable()->after('locker_state');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mesin_iot')) {
            return;
        }

        Schema::table('mesin_iot', function (Blueprint $table) {
            if (Schema::hasColumn('mesin_iot', 'locker_state_note')) {
                $table->dropColumn('locker_state_note');
            }
            if (Schema::hasColumn('mesin_iot', 'locker_state')) {
                $table->dropColumn('locker_state');
            }
        });
    }
};
