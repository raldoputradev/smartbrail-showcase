<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('absensi_kalender_libur')) {
            Schema::table('absensi_kalender_libur', function (Blueprint $table) {
                if (! Schema::hasColumn('absensi_kalender_libur', 'tanggal_selesai')) {
                    $table->date('tanggal_selesai')->nullable()->after('tanggal');
                }
                if (! Schema::hasColumn('absensi_kalender_libur', 'jenis')) {
                    $table->string('jenis', 30)->default('libur')->after('keterangan');
                }
                if (! Schema::hasColumn('absensi_kalender_libur', 'dampak_absensi')) {
                    $table->string('dampak_absensi', 30)->default('batal_wajib')->after('jenis');
                }
                if (! Schema::hasColumn('absensi_kalender_libur', 'jam_masuk_khusus')) {
                    $table->time('jam_masuk_khusus')->nullable()->after('dampak_absensi');
                }
                if (! Schema::hasColumn('absensi_kalender_libur', 'jam_pulang_khusus')) {
                    $table->time('jam_pulang_khusus')->nullable()->after('jam_masuk_khusus');
                }
            });
        }

        if (Schema::hasTable('log_absensi')) {
            Schema::table('log_absensi', function (Blueprint $table) {
                if (! Schema::hasColumn('log_absensi', 'menit_pulang_cepat')) {
                    $table->unsignedSmallInteger('menit_pulang_cepat')->default(0)->after('menit_terlambat');
                }
                if (! Schema::hasColumn('log_absensi', 'pulang_cepat')) {
                    $table->boolean('pulang_cepat')->default(false)->after('pulang_lembur');
                }
            });
        }

        if (Schema::hasTable('absensi_harian')) {
            Schema::table('absensi_harian', function (Blueprint $table) {
                if (! Schema::hasColumn('absensi_harian', 'menit_pulang_cepat')) {
                    $table->unsignedSmallInteger('menit_pulang_cepat')->default(0)->after('menit_terlambat_pulang');
                }
                if (! Schema::hasColumn('absensi_harian', 'pulang_cepat')) {
                    $table->boolean('pulang_cepat')->default(false)->after('pulang_lembur');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absensi_kalender_libur')) {
            Schema::table('absensi_kalender_libur', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['tanggal_selesai', 'jenis', 'dampak_absensi', 'jam_masuk_khusus', 'jam_pulang_khusus'],
                    fn (string $c) => Schema::hasColumn('absensi_kalender_libur', $c)
                ));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('log_absensi')) {
            Schema::table('log_absensi', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['menit_pulang_cepat', 'pulang_cepat'],
                    fn (string $c) => Schema::hasColumn('log_absensi', $c)
                ));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('absensi_harian')) {
            Schema::table('absensi_harian', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['menit_pulang_cepat', 'pulang_cepat'],
                    fn (string $c) => Schema::hasColumn('absensi_harian', $c)
                ));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
