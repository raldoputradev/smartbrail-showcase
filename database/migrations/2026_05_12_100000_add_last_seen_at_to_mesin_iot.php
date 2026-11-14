<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mesin_iot')) {
            return;
        }
        if (Schema::hasColumn('mesin_iot', 'last_seen_at')) {
            return;
        }

        Schema::table('mesin_iot', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->index()->after('is_active');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('mesin_iot') && Schema::hasColumn('mesin_iot', 'last_seen_at')) {
            Schema::table('mesin_iot', function (Blueprint $table) {
                $table->dropIndex(['last_seen_at']);
                $table->dropColumn('last_seen_at');
            });
        }
    }
};
