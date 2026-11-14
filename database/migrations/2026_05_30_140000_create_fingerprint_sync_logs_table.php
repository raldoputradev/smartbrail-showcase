<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('id_mesin', 100);
            $table->string('mesin_tipe', 40)->nullable();
            $table->string('action', 32); // sync_pull, sync_status, enroll_upload
            $table->string('since', 40)->nullable();
            $table->unsignedSmallInteger('after_finger_id')->nullable();
            $table->unsignedSmallInteger('batch_limit')->nullable();
            $table->unsignedSmallInteger('templates_count')->default(0);
            $table->json('finger_ids')->nullable();
            $table->string('sync_token', 40)->nullable();
            $table->boolean('success')->default(true);
            $table->string('message', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->index(['id_mesin', 'requested_at']);
            $table->index(['action', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_sync_logs');
    }
};
