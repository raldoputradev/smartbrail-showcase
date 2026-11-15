<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot telemetri kondisi ruangan terakhir — satu baris per ruangan.
 * Diisi edge machine (sensor APD/vision, listrik, printer 3D, solder) lewat
 * POST /api/mesin/ruangan/telemetry; dibaca halaman Monitoring Operasional.
 * BUKAN histori — hanya keadaan terakhir (histori/alert masuk log event).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruangan_telemetri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruangan_id')->unique()->constrained('ruangan')->cascadeOnDelete();
            $table->string('id_mesin', 64)->nullable();
            $table->json('metrics');
            $table->json('alerts')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruangan_telemetri');
    }
};
