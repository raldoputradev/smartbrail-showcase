<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruangan_izin', function (Blueprint $table) {
            // PIN akses per-izin untuk mahasiswa — terenkripsi, hanya berlaku selama izin aktif.
            $table->text('akses_pin_enc')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ruangan_izin', function (Blueprint $table) {
            $table->dropColumn('akses_pin_enc');
        });
    }
};
