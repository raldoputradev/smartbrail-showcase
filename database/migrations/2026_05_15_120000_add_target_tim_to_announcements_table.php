<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        if (!Schema::hasColumn('announcements', 'target_tim')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('target_tim', 80)->nullable()->after('target')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'target_tim')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('target_tim');
            });
        }
    }
};
