<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_ops_dismissals')) {
            return;
        }

        Schema::create('staff_ops_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ops_key', 64);
            $table->string('context_fingerprint', 64);
            $table->timestamp('dismissed_at');
            $table->timestamps();

            $table->unique(['user_id', 'ops_key', 'context_fingerprint'], 'staff_ops_dismiss_unique');
            $table->index(['user_id', 'ops_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_ops_dismissals');
    }
};
