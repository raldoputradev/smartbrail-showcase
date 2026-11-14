<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->enum('role', ['laboran', 'dosen', 'mahasiswa']);
                $table->string('menu_key', 100);
                $table->boolean('can_view')->default(true);
                $table->boolean('can_create')->default(false);
                $table->boolean('can_update')->default(false);
                $table->boolean('can_delete')->default(false);
                $table->timestamps();

                $table->unique(['role', 'menu_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
