<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mesin_iot')) {
            Schema::create('mesin_iot', function (Blueprint $table) {
                $table->id();
                $table->string('id_mesin', 100)->unique();
                $table->string('nama_mesin', 150)->nullable();
                $table->enum('tipe', ['absensi', 'loker'])->default('absensi');
                $table->string('api_key', 191);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mesin_iot');
    }
};
