<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'no_hp')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('no_hp', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'no_hp')) {
            return;
        }

        // Nomor HP diisi sendiri oleh pemilik akun saat aktivasi; jangan hilang otomatis.
        if (DB::table('users')->whereNotNull('no_hp')->exists()) {
            throw new RuntimeException(
                'Rollback dibatalkan: kolom users.no_hp sudah berisi nomor HP pengguna. '
                .'Backup dan putuskan penanganannya secara manual sebelum menghapus kolom.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('no_hp');
        });
    }
};
