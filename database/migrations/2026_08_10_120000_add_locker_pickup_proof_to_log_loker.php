<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_loker', function (Blueprint $table) {
            // Bukti letak admin = photo_path (mesin CAM) — sudah ada.
            // Bukti ambil mahasiswa (wajib via APK):
            if (! Schema::hasColumn('log_loker', 'pickup_proof_path')) {
                $table->string('pickup_proof_path', 500)->nullable()->after('photo_path');
            }
            if (! Schema::hasColumn('log_loker', 'pickup_proof_status')) {
                $table->string('pickup_proof_status', 32)->nullable()->after('pickup_proof_path')
                    ->comment('pending|confirmed|no_proof');
            }
            if (! Schema::hasColumn('log_loker', 'pickup_proof_at')) {
                $table->timestamp('pickup_proof_at')->nullable()->after('pickup_proof_status');
            }
            if (! Schema::hasColumn('log_loker', 'pickup_proof_deadline_at')) {
                $table->timestamp('pickup_proof_deadline_at')->nullable()->after('pickup_proof_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('log_loker', function (Blueprint $table) {
            foreach (['pickup_proof_path', 'pickup_proof_status', 'pickup_proof_at', 'pickup_proof_deadline_at'] as $col) {
                if (Schema::hasColumn('log_loker', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
