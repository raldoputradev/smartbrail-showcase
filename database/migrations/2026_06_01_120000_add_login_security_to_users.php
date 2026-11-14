<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('mobile_last_login_at');
            }
            if (!Schema::hasColumn('users', 'login_locked_until')) {
                $table->timestamp('login_locked_until')->nullable()->after('failed_login_attempts');
            }
            if (!Schema::hasColumn('users', 'login_requires_admin_unlock')) {
                $table->boolean('login_requires_admin_unlock')->default(false)->after('login_locked_until');
            }
            if (!Schema::hasColumn('users', 'login_suspicious_at')) {
                $table->timestamp('login_suspicious_at')->nullable()->after('login_requires_admin_unlock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'login_suspicious_at',
                'login_requires_admin_unlock',
                'login_locked_until',
                'failed_login_attempts',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
