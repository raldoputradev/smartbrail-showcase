<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pbl_activity_logs')) {
            return;
        }

        Schema::create('pbl_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pbl_project_id')->nullable()->constrained('pbl_projects')->nullOnDelete();
            $table->string('project_snapshot')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nama')->nullable();
            $table->string('actor_role', 24)->nullable();
            $table->string('aksi', 48);
            $table->string('ringkasan');
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pbl_project_id', 'created_at']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbl_activity_logs');
    }
};
