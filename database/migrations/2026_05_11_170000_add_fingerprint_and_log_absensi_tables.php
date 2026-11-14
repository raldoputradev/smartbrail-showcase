<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'finger_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('finger_id')->nullable()->unique()->after('role');
            });
        }

        if (!Schema::hasColumn('users', 'finger_template')) {
            Schema::table('users', function (Blueprint $table) {
                $table->longText('finger_template')->nullable()->after('finger_id');
            });
        }

        if (!Schema::hasTable('log_absensi')) {
            Schema::create('log_absensi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('id_mesin', 100)->index();
                $table->timestamp('waktu_tap')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('log_absensi')) {
            Schema::dropIfExists('log_absensi');
        }

        if (Schema::hasColumn('users', 'finger_template')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('finger_template');
            });
        }

        if (Schema::hasColumn('users', 'finger_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_finger_id_unique');
                $table->dropColumn('finger_id');
            });
        }
    }
};
