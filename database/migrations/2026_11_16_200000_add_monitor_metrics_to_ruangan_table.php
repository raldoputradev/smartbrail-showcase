<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruangan', function (Blueprint $table) {
            // Daftar kunci metrik yang dipantau untuk panel "Kondisi Ruangan".
            // 'pintu' selalu ditampilkan implisit; kolom menyimpan metrik tambahan:
            // apd, listrik, lingkungan, printer, solder.
            $table->json('monitor_metrics')->nullable()->after('aturan');
        });
    }

    public function down(): void
    {
        Schema::table('ruangan', function (Blueprint $table) {
            $table->dropColumn('monitor_metrics');
        });
    }
};
