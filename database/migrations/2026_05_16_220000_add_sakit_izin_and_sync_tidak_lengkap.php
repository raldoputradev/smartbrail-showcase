<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('absensi_harian') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE absensi_harian MODIFY status ENUM(
                'belum','masuk','lengkap','tidak_lengkap','alfa','libur','sakit','izin'
            ) NOT NULL DEFAULT 'belum'");
        }

        if (Schema::hasTable('absensi_alfa') && !Schema::hasColumn('absensi_alfa', 'kategori')) {
            Schema::table('absensi_alfa', function ($table) {
                $table->string('kategori', 20)->default('alfa')->after('sesi')->index();
            });
        }

        // Sinkronkan harian tidak_lengkap yang belum punya baris antrian
        if (Schema::hasTable('absensi_tidak_lengkap') && Schema::hasTable('absensi_harian')) {
            $rows = DB::table('absensi_harian')->where('status', 'tidak_lengkap')->get();
            foreach ($rows as $row) {
                DB::table('absensi_tidak_lengkap')->updateOrInsert(
                    [
                        'user_id' => $row->user_id,
                        'tanggal' => $row->tanggal,
                        'sesi' => $row->sesi,
                        'jenis' => 'tanpa_pulang',
                    ],
                    [
                        'selesai' => false,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('absensi_alfa', 'kategori')) {
            Schema::table('absensi_alfa', function ($table) {
                $table->dropColumn('kategori');
            });
        }
    }
};
