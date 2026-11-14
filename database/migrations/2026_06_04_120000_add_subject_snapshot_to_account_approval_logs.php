<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_approval_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('account_approval_logs', 'subject_name')) {
                $table->string('subject_name', 120)->nullable()->after('user_id');
                $table->string('subject_email', 120)->nullable()->after('subject_name');
                $table->string('subject_nitk_nidn', 30)->nullable()->after('subject_email');
                $table->string('subject_role', 20)->nullable()->after('subject_nitk_nidn');
                $table->json('subject_meta')->nullable()->after('subject_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_approval_logs', function (Blueprint $table) {
            $table->dropColumn([
                'subject_name',
                'subject_email',
                'subject_nitk_nidn',
                'subject_role',
                'subject_meta',
            ]);
        });
    }
};
