<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('program_studi')) {
            Schema::create('program_studi', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 40)->unique();
                $table->string('nama', 120);
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'program_studi_id')) {
                $table->foreignId('program_studi_id')->nullable()->after('tim')->constrained('program_studi')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'kelas_paralel')) {
                $table->char('kelas_paralel', 1)->nullable()->after('program_studi_id')->index();
            }
            if (!Schema::hasColumn('users', 'shift')) {
                $table->enum('shift', ['pagi', 'malam'])->nullable()->after('kelas_paralel')->index();
            }
        });

        if (!Schema::hasTable('absensi_sesi_settings')) {
            Schema::create('absensi_sesi_settings', function (Blueprint $table) {
                $table->id();
                $table->enum('shift', ['pagi', 'malam'])->unique();
                $table->time('jam_masuk')->default('07:50:00');
                $table->time('jam_pulang')->default('17:00:00');
                $table->unsignedSmallInteger('toleransi_masuk_menit')->default(15);
                $table->unsignedSmallInteger('grace_alfa_menit')->default(120);
                $table->boolean('aktif')->default(true);
                $table->string('timezone', 50)->default('Asia/Jakarta');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('absensi_kalender_libur')) {
            Schema::create('absensi_kalender_libur', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal')->unique();
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();
            });
        }

        Schema::table('log_absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('log_absensi', 'sesi')) {
                $table->enum('sesi', ['pagi', 'malam'])->nullable()->after('id_mesin')->index();
            }
            if (!Schema::hasColumn('log_absensi', 'jenis')) {
                $table->enum('jenis', ['masuk', 'pulang'])->nullable()->after('sesi')->index();
            }
            if (!Schema::hasColumn('log_absensi', 'menit_terlambat')) {
                $table->unsignedSmallInteger('menit_terlambat')->default(0)->after('jenis');
            }
        });

        if (!Schema::hasTable('absensi_harian')) {
            Schema::create('absensi_harian', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('tanggal')->index();
                $table->enum('sesi', ['pagi', 'malam']);
                $table->enum('status', ['belum', 'masuk', 'lengkap', 'tidak_lengkap', 'alfa', 'libur'])->default('belum');
                $table->foreignId('masuk_log_id')->nullable()->constrained('log_absensi')->nullOnDelete();
                $table->foreignId('pulang_log_id')->nullable()->constrained('log_absensi')->nullOnDelete();
                $table->unsignedSmallInteger('menit_terlambat_masuk')->default(0);
                $table->unsignedSmallInteger('menit_terlambat_pulang')->default(0);
                $table->timestamp('lengkap_at')->nullable();
                $table->text('catatan_koreksi')->nullable();
                $table->foreignId('koreksi_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('koreksi_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'tanggal', 'sesi']);
            });
        }

        if (!Schema::hasTable('absensi_alfa')) {
            Schema::create('absensi_alfa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('tanggal')->index();
                $table->enum('sesi', ['pagi', 'malam']);
                $table->enum('sumber', ['otomatis', 'manual'])->default('otomatis');
                $table->boolean('aktif')->default(true);
                $table->text('catatan')->nullable();
                $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('dibatalkan_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'tanggal', 'sesi']);
            });
        }

        if (!Schema::hasTable('absensi_tidak_lengkap')) {
            Schema::create('absensi_tidak_lengkap', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('tanggal')->index();
                $table->enum('sesi', ['pagi', 'malam']);
                $table->enum('jenis', ['tanpa_masuk', 'tanpa_pulang']);
                $table->boolean('selesai')->default(false);
                $table->text('catatan_koreksi')->nullable();
                $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('ditangani_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'tanggal', 'sesi', 'jenis']);
            });
        }

        if (!Schema::hasTable('absensi_komentar')) {
            Schema::create('absensi_komentar', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('target_type', 40);
                $table->unsignedBigInteger('target_id');
                $table->text('isi');
                $table->timestamps();

                $table->index(['target_type', 'target_id']);
            });
        }

        $this->seedProgramStudi();
        $this->seedSesiSettings();
        $this->backfillUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_komentar');
        Schema::dropIfExists('absensi_tidak_lengkap');
        Schema::dropIfExists('absensi_alfa');
        Schema::dropIfExists('absensi_harian');
        Schema::dropIfExists('absensi_kalender_libur');
        Schema::dropIfExists('absensi_sesi_settings');

        if (Schema::hasTable('log_absensi')) {
            Schema::table('log_absensi', function (Blueprint $table) {
                foreach (['menit_terlambat', 'jenis', 'sesi'] as $col) {
                    if (Schema::hasColumn('log_absensi', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['shift', 'kelas_paralel', 'program_studi_id'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    if ($col === 'program_studi_id') {
                        $table->dropConstrainedForeignId('program_studi_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::dropIfExists('program_studi');
    }

    private function seedProgramStudi(): void
    {
        $rows = [
            ['kode' => 'robotika', 'nama' => 'Robotika', 'aktif' => true],
            ['kode' => 'mekatronika', 'nama' => 'Mekatronika', 'aktif' => true],
            ['kode' => 'tre', 'nama' => 'Teknik Rekayasa Elektro', 'aktif' => true],
        ];

        foreach ($rows as $row) {
            DB::table('program_studi')->updateOrInsert(['kode' => $row['kode']], array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedSesiSettings(): void
    {
        $defaults = [
            [
                'shift' => 'pagi',
                'jam_masuk' => '07:50:00',
                'jam_pulang' => '17:00:00',
                'toleransi_masuk_menit' => 15,
                'grace_alfa_menit' => 120,
                'aktif' => true,
                'timezone' => 'Asia/Jakarta',
            ],
            [
                'shift' => 'malam',
                'jam_masuk' => '19:00:00',
                'jam_pulang' => '22:00:00',
                'toleransi_masuk_menit' => 15,
                'grace_alfa_menit' => 60,
                'aktif' => true,
                'timezone' => 'Asia/Jakarta',
            ],
        ];

        foreach ($defaults as $row) {
            DB::table('absensi_sesi_settings')->updateOrInsert(['shift' => $row['shift']], array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function backfillUsers(): void
    {
        if (!Schema::hasTable('program_studi')) {
            return;
        }

        $prodiMap = DB::table('program_studi')->pluck('id', 'kode');

        User::query()
            ->where('role', 'mahasiswa')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($prodiMap) {
                foreach ($users as $user) {
                    $kelas = strtolower((string) $user->kelas);
                    $updates = [];

                    if (!$user->program_studi_id) {
                        if (str_contains($kelas, 'mekatronika')) {
                            $updates['program_studi_id'] = $prodiMap['mekatronika'] ?? null;
                        } elseif (str_contains($kelas, 'rekayasa') || str_contains($kelas, 'tre') || str_contains($kelas, 'elektro')) {
                            $updates['program_studi_id'] = $prodiMap['tre'] ?? null;
                        } else {
                            $updates['program_studi_id'] = $prodiMap['robotika'] ?? null;
                        }
                    }

                    if (!$user->kelas_paralel && preg_match('/\b([abc])\b/i', (string) $user->kelas, $m)) {
                        $updates['kelas_paralel'] = strtoupper($m[1]);
                    }

                    if (!$user->shift) {
                        $updates['shift'] = str_contains($kelas, 'malam') ? 'malam' : 'pagi';
                    }

                    if ($updates !== []) {
                        DB::table('users')->where('id', $user->id)->update($updates);
                    }
                }
            });
    }
};
