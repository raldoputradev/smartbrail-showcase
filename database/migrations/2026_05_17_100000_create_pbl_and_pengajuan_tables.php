<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pbl_projects')) {
            Schema::create('pbl_projects', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 40)->unique();
                $table->string('nama', 200);
                $table->string('manpro', 120)->nullable();
                $table->string('co_manpro', 120)->nullable();
                $table->enum('shift_sesi', ['pagi', 'malam'])->default('pagi');
                $table->foreignId('manpro_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('co_manpro_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('absensi_pengajuan')) {
            Schema::create('absensi_pengajuan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('tanggal_mulai');
                $table->date('tanggal_akhir');
                $table->enum('jenis', ['sakit', 'izin']);
                $table->text('alasan');
                $table->string('bukti_path', 500)->nullable();
                $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
                $table->text('catatan_reviewer')->nullable();
                $table->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('ditinjau_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'tanggal_mulai']);
            });
        }

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (\Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'mahasiswa_web_enabled')->doesntExist()) {
            \Illuminate\Support\Facades\DB::table('system_settings')->insert([
                'key' => 'mahasiswa_web_enabled',
                'value' => json_encode(['enabled' => false]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pengajuan');
        Schema::dropIfExists('pbl_projects');
    }
};
