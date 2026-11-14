<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Membersihkan kolom legacy di tabel `announcements` yang tersisa
     * dari versi sebelumnya. Kolom-kolom ini bersifat NOT NULL tanpa
     * default value sehingga menyebabkan error 1364 saat insert baris baru.
     *
     * Strategi:
     *  1) Salin data dari kolom legacy ke kolom baru bila kolom baru kosong.
     *  2) Hapus foreign key & index lalu drop kolom legacy.
     */
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        $hasAuthorId = Schema::hasColumn('announcements', 'author_id');
        $hasTitle    = Schema::hasColumn('announcements', 'title');
        $hasBody     = Schema::hasColumn('announcements', 'body');
        $hasAudience = Schema::hasColumn('announcements', 'audience');

        $hasJudul       = Schema::hasColumn('announcements', 'judul');
        $hasIsi         = Schema::hasColumn('announcements', 'isi');
        $hasTarget      = Schema::hasColumn('announcements', 'target');
        $hasDibuatOleh  = Schema::hasColumn('announcements', 'dibuat_oleh');

        // 1) Migrasikan data legacy ke kolom baru bila kolom baru masih kosong.
        try {
            if ($hasTitle && $hasJudul) {
                DB::statement("UPDATE announcements SET judul = title WHERE (judul IS NULL OR judul = '') AND title IS NOT NULL");
            }
            if ($hasBody && $hasIsi) {
                DB::statement("UPDATE announcements SET isi = body WHERE (isi IS NULL OR isi = '') AND body IS NOT NULL");
            }
            if ($hasAudience && $hasTarget) {
                DB::statement("UPDATE announcements SET target = CASE WHEN audience = 'all' THEN 'semua' ELSE audience END WHERE target IS NULL OR target = ''");
            }
            if ($hasAuthorId && $hasDibuatOleh) {
                DB::statement("UPDATE announcements SET dibuat_oleh = author_id WHERE dibuat_oleh IS NULL AND author_id IS NOT NULL");
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal (mis. data type mismatch); fokus utama tetap drop kolom.
        }

        // 2) Hapus foreign key terlebih dulu (jika ada) sebelum drop kolom author_id.
        if ($hasAuthorId) {
            try {
                Schema::table('announcements', function (Blueprint $table) {
                    // Nama FK default Laravel: announcements_author_id_foreign
                    $table->dropForeign(['author_id']);
                });
            } catch (\Throwable $e) {
                // Kalau memang belum punya FK, lanjut saja.
            }
            try {
                Schema::table('announcements', function (Blueprint $table) {
                    $table->dropIndex(['author_id']);
                });
            } catch (\Throwable $e) {
                // Lewati kalau index tidak ada.
            }
        }

        // 3) Drop kolom legacy yang tersisa.
        Schema::table('announcements', function (Blueprint $table) use ($hasAuthorId, $hasTitle, $hasBody, $hasAudience) {
            $drops = [];
            if ($hasAuthorId) $drops[] = 'author_id';
            if ($hasTitle)    $drops[] = 'title';
            if ($hasBody)     $drops[] = 'body';
            if ($hasAudience) $drops[] = 'audience';

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan kolom legacy: skema baru sudah cukup.
    }
};
