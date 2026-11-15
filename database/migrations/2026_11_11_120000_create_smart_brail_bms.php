<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMART BRAIL — modul BMS ruangan.
 * Hanya menambah tabel/kolom baru; tidak mengubah/menghapus data absensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ruangan')) {
            Schema::create('ruangan', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 40)->unique();
                $table->string('nama', 120);
                $table->string('lokasi', 160)->nullable();
                $table->boolean('aktif')->default(true);
                // Aturan per ruangan (default di Ruangan::aturan()):
                // hari[], jam_buka, jam_tutup, max_advance_days=2,
                // max_duration_minutes=120, max_pending_per_team=1
                $table->json('aturan')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ruangan_pengajuan')) {
            Schema::create('ruangan_pengajuan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ruangan_id')->constrained('ruangan')->cascadeOnDelete();
                $table->foreignId('pengaju_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pbl_project_id')->nullable()->constrained('pbl_projects')->nullOnDelete();
                $table->foreignId('pembimbing_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tujuan', 500);
                $table->date('tanggal');
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->json('peserta')->nullable();      // [user_id, ...]
                $table->json('snapshot')->nullable();     // nama pengaju/tim/pembimbing/peserta saat diajukan
                $table->string('status', 32)->default('diajukan');
                // diajukan|menunggu_penerimaan|disetujui|ditolak|dibatalkan|selesai|kedaluwarsa
                $table->dateTime('pengganti_mulai')->nullable();
                $table->dateTime('pengganti_selesai')->nullable();
                $table->string('pengganti_catatan', 500)->nullable();
                $table->foreignId('diputus_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('diputus_pada')->nullable();
                $table->string('alasan_keputusan', 500)->nullable();
                $table->timestamps();
                $table->index(['ruangan_id', 'tanggal', 'status'], 'rp_room_date_status');
                $table->index(['pengaju_id', 'status'], 'rp_pengaju_status');
                $table->index(['pbl_project_id', 'status'], 'rp_project_status');
            });
        }

        if (!Schema::hasTable('ruangan_izin')) {
            Schema::create('ruangan_izin', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pengajuan_id')->constrained('ruangan_pengajuan')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('ruangan_id')->constrained('ruangan')->cascadeOnDelete();
                $table->dateTime('mulai');
                $table->dateTime('selesai');
                $table->string('status', 20)->default('aktif'); // aktif|dicabut|kedaluwarsa
                $table->timestamps();
                $table->index(['ruangan_id', 'user_id', 'status', 'mulai', 'selesai'], 'ri_access_check');
            });
        }

        if (!Schema::hasTable('ruangan_petugas')) {
            Schema::create('ruangan_petugas', function (Blueprint $table) {
                $table->id();
                $table->string('nama', 120);
                $table->string('id_internal', 60)->unique();
                $table->string('tipe_kredensial', 20)->default('pin'); // pin|rfid|fingerprint
                $table->string('kredensial_hash');   // pin: hash; rfid: uid hash; fingerprint: finger ref
                $table->string('status', 20)->default('aktif'); // aktif|nonaktif
                $table->date('berlaku_mulai')->nullable();
                $table->date('berlaku_selesai')->nullable();
                // [{ruangan_id, hari:[1..7], jam_mulai, jam_selesai}] — juga memblokir pemesanan
                $table->json('jadwal')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ruangan_access_events')) {
            Schema::create('ruangan_access_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mesin_iot_id')->constrained('mesin_iot')->cascadeOnDelete();
                $table->foreignId('ruangan_id')->constrained('ruangan')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('petugas_id')->nullable()->constrained('ruangan_petugas')->nullOnDelete();
                $table->foreignId('izin_id')->nullable()->constrained('ruangan_izin')->nullOnDelete();
                $table->string('jenis', 30);      // check|buka|tolak|telemetri
                $table->string('hasil', 30)->nullable(); // diizinkan|ditolak
                $table->json('meta')->nullable();
                $table->timestamp('terjadi_pada')->index();
                $table->timestamps();
                $table->index(['ruangan_id', 'terjadi_pada'], 'rae_room_time');
            });
        }

        if (Schema::hasTable('mesin_iot') && !Schema::hasColumn('mesin_iot', 'ruangan_id')) {
            Schema::table('mesin_iot', function (Blueprint $table) {
                $table->foreignId('ruangan_id')->nullable()->after('tipe')->constrained('ruangan')->nullOnDelete();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'is_admin')) {
                    $table->boolean('is_admin')->default(false)->after('role');
                }
                if (!Schema::hasColumn('users', 'rfid_uid')) {
                    $table->string('rfid_uid', 64)->nullable()->unique()->after('finger_id');
                }
                if (!Schema::hasColumn('users', 'akses_pin')) {
                    $table->string('akses_pin')->nullable()->after('rfid_uid'); // hashed PIN pribadi
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruangan_access_events');
        Schema::dropIfExists('ruangan_petugas');
        Schema::dropIfExists('ruangan_izin');
        Schema::dropIfExists('ruangan_pengajuan');
        Schema::dropIfExists('ruangan');
        if (Schema::hasTable('mesin_iot') && Schema::hasColumn('mesin_iot', 'ruangan_id')) {
            Schema::table('mesin_iot', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ruangan_id');
            });
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['is_admin', 'rfid_uid', 'akses_pin'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
