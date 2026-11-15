<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil ruangan untuk kartu pemilihan mahasiswa:
 * kapasitas (batas peserta + tampilan), fasilitas (JSON array string), foto (path upload).
 *
 * BELUM dijalankan di database operasional — menunggu izin pemilik.
 * Aman untuk SQLite test (Schema::hasColumn guard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruangan', function (Blueprint $table) {
            if (!Schema::hasColumn('ruangan', 'kapasitas')) {
                $table->unsignedSmallInteger('kapasitas')->nullable()->after('lokasi');
            }
            if (!Schema::hasColumn('ruangan', 'fasilitas')) {
                $table->json('fasilitas')->nullable()->after('kapasitas');
            }
            if (!Schema::hasColumn('ruangan', 'foto')) {
                $table->string('foto', 255)->nullable()->after('fasilitas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ruangan', function (Blueprint $table) {
            foreach (['kapasitas', 'fasilitas', 'foto'] as $col) {
                if (Schema::hasColumn('ruangan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
