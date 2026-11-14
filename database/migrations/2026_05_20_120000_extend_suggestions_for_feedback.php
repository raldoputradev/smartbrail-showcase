<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suggestions')) {
            return;
        }

        Schema::table('suggestions', function (Blueprint $table) {
            if (!Schema::hasColumn('suggestions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('suggestions', 'source')) {
                $table->string('source', 20)->default('public')->after('user_id')->index();
            }
            if (!Schema::hasColumn('suggestions', 'admin_reply')) {
                $table->text('admin_reply')->nullable()->after('pesan');
            }
            if (!Schema::hasColumn('suggestions', 'admin_replied_at')) {
                $table->timestamp('admin_replied_at')->nullable()->after('admin_reply');
            }
            if (!Schema::hasColumn('suggestions', 'admin_replied_by')) {
                $table->foreignId('admin_replied_by')->nullable()->after('admin_replied_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('suggestions', 'user_read_reply_at')) {
                $table->timestamp('user_read_reply_at')->nullable()->after('admin_replied_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('suggestions')) {
            return;
        }

        Schema::table('suggestions', function (Blueprint $table) {
            foreach (['user_read_reply_at', 'admin_replied_by', 'admin_replied_at', 'admin_reply', 'source', 'user_id'] as $col) {
                if (Schema::hasColumn('suggestions', $col)) {
                    if ($col === 'user_id' || $col === 'admin_replied_by') {
                        $table->dropForeign(['suggestions_' . $col . '_foreign']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
