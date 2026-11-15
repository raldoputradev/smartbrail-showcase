<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('absensi_kalender_libur', function (Blueprint $table) {
            // Tingkat kepentingan agenda — menentukan warna penanda di kalender.
            $table->string('prioritas', 16)->default('normal')->after('dampak_absensi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensi_kalender_libur', function (Blueprint $table) {
            $table->dropColumn('prioritas');
        });
    }
};
