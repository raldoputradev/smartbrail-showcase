<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        if (! Schema::hasColumn('announcements', 'channel')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('channel', 32)->default('pengumuman')->index()->after('target_tim');
            });
        }

        DB::table('announcements')
            ->where('judul', 'like', '[LOKER:%')
            ->update(['channel' => 'notifikasi_loker']);

        // Baris lama tanpa target_tim terlihat semua mahasiswa — nonaktifkan.
        DB::table('announcements')
            ->where('judul', 'like', '[LOKER:%')
            ->where(function ($q) {
                $q->whereNull('target_tim')->orWhere('target_tim', '');
            })
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'channel')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }
    }
};
