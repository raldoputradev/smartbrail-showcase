<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemisahan role: 'admin' menjadi role tersendiri (bukan lagi laboran + is_admin).
 *
 * Dampak:
 * - users.role & role_permissions.role diperluas dengan nilai 'admin'.
 * - Akun id 17 ("Admin brail") dipromosikan laboran -> admin. Data lain tidak berubah.
 * - Kolom users.is_admin DIPERTAHANKAN (masih dibaca sebagai transisi di User::isAdmin()).
 *
 * Idempotent: update role id 17 hanya bila masih 'laboran'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: ALTER ENUM. SQLite: Laravel rebuild tabel untuk memperbarui CHECK constraint.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'laboran', 'dosen', 'mahasiswa'])->default('dosen')->change();
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->enum('role', ['admin', 'laboran', 'dosen', 'mahasiswa'])->change();
        });

        DB::table('users')
            ->where('id', 17)
            ->where('role', 'laboran')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('id', 17)
            ->where('role', 'admin')
            ->update(['role' => 'laboran']);

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->enum('role', ['laboran', 'dosen', 'mahasiswa'])->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['laboran', 'dosen', 'mahasiswa'])->default('dosen')->change();
        });
    }
};
