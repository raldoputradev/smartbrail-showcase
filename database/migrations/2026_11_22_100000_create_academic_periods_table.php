<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('academic_periods')) {
            Schema::create('academic_periods', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 40)->unique();
                $table->string('label');
                $table->string('jenis', 10); // ganjil | genap
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->boolean('is_active')->default(false)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('users', 'academic_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('academic_status', 20)->default('aktif')->after('semester_type')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
        if (Schema::hasColumn('users', 'academic_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('academic_status');
            });
        }
    }
};
