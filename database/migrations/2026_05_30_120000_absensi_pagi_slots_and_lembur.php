<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('absensi_sesi_settings') && !Schema::hasColumn('absensi_sesi_settings', 'jam_mulai_tap_masuk')) {
            Schema::table('absensi_sesi_settings', function (Blueprint $table) {
                $table->time('jam_mulai_tap_masuk')->nullable()->after('jam_masuk');
            });

            DB::table('absensi_sesi_settings')
                ->where('shift', 'pagi')
                ->update(['jam_mulai_tap_masuk' => '06:30:00']);
        }

        if (Schema::hasTable('log_absensi')) {
            Schema::table('log_absensi', function (Blueprint $table) {
                if (!Schema::hasColumn('log_absensi', 'slot_lab')) {
                    $table->unsignedTinyInteger('slot_lab')->nullable()->after('menit_terlambat');
                }
                if (!Schema::hasColumn('log_absensi', 'pulang_lembur')) {
                    $table->boolean('pulang_lembur')->default(false)->after('slot_lab');
                }
            });
        }

        if (Schema::hasTable('absensi_harian')) {
            Schema::table('absensi_harian', function (Blueprint $table) {
                if (!Schema::hasColumn('absensi_harian', 'slot_lab_masuk')) {
                    $table->unsignedTinyInteger('slot_lab_masuk')->nullable()->after('menit_terlambat_pulang');
                }
                if (!Schema::hasColumn('absensi_harian', 'pulang_lembur')) {
                    $table->boolean('pulang_lembur')->default(false)->after('slot_lab_masuk');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absensi_harian')) {
            Schema::table('absensi_harian', function (Blueprint $table) {
                foreach (['pulang_lembur', 'slot_lab_masuk'] as $col) {
                    if (Schema::hasColumn('absensi_harian', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('log_absensi')) {
            Schema::table('log_absensi', function (Blueprint $table) {
                foreach (['pulang_lembur', 'slot_lab'] as $col) {
                    if (Schema::hasColumn('log_absensi', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('absensi_sesi_settings') && Schema::hasColumn('absensi_sesi_settings', 'jam_mulai_tap_masuk')) {
            Schema::table('absensi_sesi_settings', function (Blueprint $table) {
                $table->dropColumn('jam_mulai_tap_masuk');
            });
        }
    }
};
