<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotensi tap mesin. Berjalan di database pengujian lewat PHPUnit.
 * Jangan menjalankan migration ini ke MySQL operasional sebelum rencana deploy disetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_absensi', function (Blueprint $table) {
            $table->uuid('event_id')->nullable()->unique();
            $table->dateTime('diterima_pada')->nullable();
        });

        Schema::create('tap_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('id_mesin', 100);
            $table->unsignedInteger('finger_id');
            $table->dateTime('waktu_tap')->nullable();
            $table->char('payload_hash', 64);
            $table->boolean('success');
            $table->boolean('terminal');
            $table->string('code', 64);
            $table->unsignedSmallInteger('http_status');
            $table->string('message', 500);
            $table->json('response_data')->nullable();
            $table->foreignId('log_absensi_id')->nullable()->constrained('log_absensi')->nullOnDelete();
            $table->dateTime('diterima_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tap_receipts');

        Schema::table('log_absensi', function (Blueprint $table) {
            $table->dropUnique('log_absensi_event_id_unique');
            $table->dropColumn(['event_id', 'diterima_pada']);
        });
    }
};
