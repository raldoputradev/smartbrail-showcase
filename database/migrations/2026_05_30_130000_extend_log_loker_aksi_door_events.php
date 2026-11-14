<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('log_loker')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE log_loker MODIFY aksi ENUM('buka', 'tutup', 'pintu_terbuka', 'pintu_tertutup') NOT NULL DEFAULT 'buka'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('log_loker') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE log_loker MODIFY aksi ENUM('buka', 'tutup') NOT NULL DEFAULT 'buka'");
    }
};
