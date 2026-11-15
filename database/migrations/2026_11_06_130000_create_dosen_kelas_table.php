<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan dosen ↔ kelas (many-to-many) sebagai cakupan akses operasional dosen.
 * Dampak: hanya tabel baru; tidak mengubah/menghapus data users, PBL, atau absensi.
 * Tanpa baris pemetaan → dosen tidak melihat data kelas mana pun (deny-by-default).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dosen_kelas')) {
            return;
        }

        Schema::create('dosen_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kelas', 80);
            $table->timestamps();

            $table->unique(['user_id', 'kelas']);
            $table->index('kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen_kelas');
    }
};
