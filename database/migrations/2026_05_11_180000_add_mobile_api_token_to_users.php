<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mobile_api_token')) {
                $table->string('mobile_api_token', 191)->nullable()->unique()->after('finger_template');
            }
            if (!Schema::hasColumn('users', 'mobile_token_expires_at')) {
                $table->timestamp('mobile_token_expires_at')->nullable()->after('mobile_api_token');
            }
            if (!Schema::hasColumn('users', 'mobile_last_login_at')) {
                $table->timestamp('mobile_last_login_at')->nullable()->after('mobile_token_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['mobile_last_login_at', 'mobile_token_expires_at', 'mobile_api_token'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
