<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('loker_slot_admin_logs');
    }
};
