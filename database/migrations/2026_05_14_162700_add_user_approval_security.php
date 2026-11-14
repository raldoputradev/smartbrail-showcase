<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'approval_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('approval_status', 20)->default('pending')->after('is_active');
                $table->text('approval_note')->nullable()->after('approval_status');
                $table->foreignId('approved_by')->nullable()->after('approval_note')->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            });

            DB::table('users')->update([
                'approval_status' => DB::raw("CASE WHEN is_active = 1 THEN 'approved' ELSE 'pending' END"),
                'approved_at' => DB::raw("CASE WHEN is_active = 1 THEN COALESCE(email_verified_at, created_at) ELSE NULL END"),
            ]);
        }

        if (!Schema::hasTable('account_approval_logs')) {
            Schema::create('account_approval_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40);
                $table->text('note')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_approval_logs');

        if (Schema::hasColumn('users', 'approval_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('approved_by');
                $table->dropColumn(['approval_status', 'approval_note', 'approved_at', 'rejected_at']);
            });
        }
    }
};
