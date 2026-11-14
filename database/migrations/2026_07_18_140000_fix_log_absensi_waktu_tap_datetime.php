<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * waktu_tap harus datetime biasa — TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 * menimpa jam absen setiap kali kolom lain di-update (mis. menit_terlambat).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('log_absensi')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE log_absensi MODIFY waktu_tap DATETIME NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('log_absensi') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE log_absensi MODIFY waktu_tap TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};
