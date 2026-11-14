<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('log_loker')) {
            return;
        }

        Schema::table('log_loker', function (Blueprint $table) {
            if (! Schema::hasColumn('log_loker', 'photo_path')) {
                $table->string('photo_path', 255)->nullable()->after('catatan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('log_loker')) {
            return;
        }

        Schema::table('log_loker', function (Blueprint $table) {
            if (Schema::hasColumn('log_loker', 'photo_path')) {
                $table->dropColumn('photo_path');
            }
        });
    }
};
