<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'kelas')) {
                $table->string('kelas', 80)->nullable()->after('role')->index();
            }
            if (!Schema::hasColumn('users', 'tim')) {
                $table->string('tim', 80)->nullable()->after('kelas')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tim')) {
                $table->dropColumn('tim');
            }
            if (Schema::hasColumn('users', 'kelas')) {
                $table->dropColumn('kelas');
            }
        });
    }
};
