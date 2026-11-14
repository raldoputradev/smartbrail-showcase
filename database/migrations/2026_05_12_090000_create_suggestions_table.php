<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suggestions')) {
            return;
        }

        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120);
            $table->string('email', 150)->nullable();
            $table->string('subjek', 180)->nullable();
            $table->text('pesan');
            $table->enum('status', ['baru', 'dibaca', 'ditanggapi'])->default('baru')->index();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
