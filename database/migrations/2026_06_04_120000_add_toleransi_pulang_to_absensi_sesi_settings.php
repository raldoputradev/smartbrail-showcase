<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absensi_sesi_settings')) {
            return;
        }

        if (!Schema::hasColumn('absensi_sesi_settings', 'toleransi_pulang_menit')) {
            Schema::table('absensi_sesi_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('toleransi_pulang_menit')
                    ->nullable()
                    ->after('toleransi_masuk_menit');
            });
        }

        DB::table('absensi_sesi_settings')
            ->whereNull('toleransi_pulang_menit')
            ->update([
                'toleransi_pulang_menit' => DB::raw('toleransi_masuk_menit'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('absensi_sesi_settings', 'toleransi_pulang_menit')) {
            Schema::table('absensi_sesi_settings', function (Blueprint $table) {
                $table->dropColumn('toleransi_pulang_menit');
            });
        }
    }
};
