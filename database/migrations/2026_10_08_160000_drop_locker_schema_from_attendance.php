<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melepas schema khusus locker dari database absensi.
 * Migration historis yang membuat schema itu tetap ada.
 * down() hanya mengembalikan bentuk tabel kosong, bukan data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('log_loker');
        Schema::dropIfExists('loker_slot_admin_logs');

        if (Schema::hasTable('mesin_iot')) {
            foreach (['locker_slots', 'locker_state', 'locker_state_note', 'allowed_teams'] as $column) {
                if (Schema::hasColumn('mesin_iot', $column)) {
                    Schema::table('mesin_iot', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }

            DB::table('mesin_iot')->where('tipe', 'loker')->delete();
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->whereIn('menu_key', ['lockers', 'log_loker', 'dosen_loker'])
                ->delete();
        }

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->whereIn('key', ['machine_access', 'locker_fulfillment'])
                ->delete();
        }

        if (Schema::hasTable('announcements')) {
            DB::table('announcements')
                ->where(function ($query) {
                    $query->where('channel', 'notifikasi_loker')
                        ->orWhere('judul', 'like', '[LOKER:%');
                })
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('log_loker')) {
            Schema::create('log_loker', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('id_mesin', 100)->index();
                $table->string('aksi', 32)->default('buka');
                $table->boolean('berhasil')->default(true);
                $table->string('catatan', 255)->nullable();
                $table->timestamp('waktu_akses')->nullable()->index();
                $table->unsignedTinyInteger('slot')->nullable();
                $table->string('photo_path', 255)->nullable();
                $table->string('pickup_proof_path', 255)->nullable();
                $table->string('pickup_proof_status', 32)->nullable();
                $table->timestamp('pickup_proof_at')->nullable();
                $table->timestamp('pickup_proof_deadline_at')->nullable();
                $table->string('place_photo_path', 255)->nullable();
            });
        }

        if (! Schema::hasTable('loker_slot_admin_logs')) {
            Schema::create('loker_slot_admin_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('id_mesin', 100);
                $table->unsignedTinyInteger('slot');
                $table->string('action', 20);
                $table->string('hasil', 20);
                $table->string('slot_state', 20)->nullable();
                $table->string('allowed_team', 80)->nullable();
                $table->text('note')->nullable();
                $table->string('pesan', 500)->nullable();
                $table->timestamps();
                $table->index(['id_mesin', 'created_at']);
            });
        }

        if (Schema::hasTable('mesin_iot')) {
            Schema::table('mesin_iot', function (Blueprint $table) {
                if (! Schema::hasColumn('mesin_iot', 'allowed_teams')) {
                    $table->json('allowed_teams')->nullable();
                }
                if (! Schema::hasColumn('mesin_iot', 'locker_state')) {
                    $table->string('locker_state', 20)->default('open');
                }
                if (! Schema::hasColumn('mesin_iot', 'locker_state_note')) {
                    $table->string('locker_state_note', 255)->nullable();
                }
                if (! Schema::hasColumn('mesin_iot', 'locker_slots')) {
                    $table->json('locker_slots')->nullable();
                }
            });
        }
    }
};
