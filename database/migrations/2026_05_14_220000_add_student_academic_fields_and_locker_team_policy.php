<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'angkatan')) {
                $table->unsignedSmallInteger('angkatan')->nullable()->after('tim')->index();
            }
            if (!Schema::hasColumn('users', 'semester')) {
                $table->unsignedTinyInteger('semester')->nullable()->after('angkatan')->index();
            }
            if (!Schema::hasColumn('users', 'semester_type')) {
                $table->string('semester_type', 20)->nullable()->after('semester')->index();
            }
            if (!Schema::hasColumn('users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable()->after('semester_type');
            }
        });

        Schema::table('mesin_iot', function (Blueprint $table) {
            if (!Schema::hasColumn('mesin_iot', 'allowed_teams')) {
                $table->json('allowed_teams')->nullable()->after('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesin_iot', function (Blueprint $table) {
            if (Schema::hasColumn('mesin_iot', 'allowed_teams')) {
                $table->dropColumn('allowed_teams');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['profile_completed_at', 'semester_type', 'semester', 'angkatan'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
