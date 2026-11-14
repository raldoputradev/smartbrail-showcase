<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('log_loker')) {
            Schema::create('log_loker', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('id_mesin', 100)->index();
                $table->enum('aksi', ['buka', 'tutup'])->default('buka');
                $table->boolean('berhasil')->default(true);
                $table->string('catatan', 255)->nullable();
                $table->timestamp('waktu_akses')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('log_loker');
    }
};
