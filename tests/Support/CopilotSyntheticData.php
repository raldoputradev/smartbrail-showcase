<?php

namespace Tests\Support;

use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Data sintetis Copilot (nama/NIM fiktif). Hanya untuk SQLite uji / lab.
 */
final class CopilotSyntheticData
{
    public const MESIN = 'ABSEN-UJI-COPILOT';

    /**
     * @return array{laboran: int, dosen: int, mahasiswa: int, students: list<int>}
     */
    public static function seed(Carbon $today, string $password = 'sandi-uji-copilot'): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            throw new \RuntimeException('Seeder Copilot hanya untuk SQLite uji.');
        }

        foreach (['pagi' => ['07:50:00', '06:30:00', '17:00:00'], 'malam' => ['17:30:00', '16:30:00', '21:00:00']] as $shift => [$masuk, $mulai, $pulang]) {
            AbsensiSesiSetting::query()->updateOrCreate(['shift' => $shift], [
                'jam_masuk' => $masuk,
                'jam_mulai_tap_masuk' => $mulai,
                'jam_pulang' => $pulang,
                'toleransi_masuk_menit' => 15,
                'toleransi_pulang_menit' => 15,
                'grace_alfa_menit' => 120,
                'aktif' => true,
                'timezone' => 'Asia/Jakarta',
            ]);
        }

        MesinIot::query()->updateOrCreate(['id_mesin' => self::MESIN], [
            'nama_mesin' => 'Mesin uji Copilot',
            'tipe' => 'absensi',
            'api_key' => Hash::make('kunci-uji-copilot'),
            'is_active' => true,
        ]);

        $hash = Hash::make($password);
        $ids = [
            'laboran' => self::user('Laboran Contoh', 'LAB-UJI-01', 'laboran.contoh@example.test', 'laboran', $hash),
            'dosen' => self::user('Dosen Contoh', 'DSN-UJI-01', 'dosen.contoh@example.test', 'dosen', $hash),
            'mahasiswa' => self::user('Mahasiswa Login Contoh', '99009999', 'mhs.login@example.test', 'mahasiswa', $hash, 'TI-1A', 'Tim Alfa'),
            'students' => [],
        ];

        $kelas = ['TI-1A', 'TI-1B'];
        $tim = ['Tim Alfa', 'Tim Beta', 'Tim Gamma'];
        for ($i = 1; $i <= 12; $i++) {
            $ids['students'][] = self::user(
                sprintf('Mahasiswa Contoh %02d', $i),
                sprintf('990%05d', $i),
                sprintf('contoh%02d@example.test', $i),
                'mahasiswa',
                $hash,
                $kelas[$i % 2],
                $tim[$i % 3],
            );
        }

        // Hari ini: 1-4 lengkap, 5 lengkap terlambat, 6-7 hanya masuk, 8 tidak lengkap, 9-10 alfa, 11-12 belum tap.
        $todayPlan = [1 => 'lengkap', 2 => 'lengkap', 3 => 'lengkap', 4 => 'lengkap', 5 => 'telat', 6 => 'masuk', 7 => 'masuk', 8 => 'tidak_lengkap', 9 => 'alfa', 10 => 'alfa'];
        foreach ($todayPlan as $n => $plan) {
            self::day($ids['students'][$n - 1], $today, $plan);
        }

        $statuses = ['lengkap', 'lengkap', 'lengkap', 'masuk', 'lengkap', 'tidak_lengkap', 'lengkap', 'alfa'];
        $date = $today->copy()->subDay();
        $days = 0;
        while ($days < 15) {
            if (! $date->isWeekend()) {
                foreach ($ids['students'] as $idx => $uid) {
                    self::day($uid, $date, $statuses[($idx + $days) % count($statuses)]);
                }
                $days++;
            }
            $date->subDay();
        }

        return $ids;
    }

    private static function user(string $nama, string $nitk, string $email, string $role, string $hash, ?string $kelas = null, ?string $tim = null): int
    {
        $now = now()->format('Y-m-d H:i:s');
        $row = [
            'nama_lengkap' => $nama,
            'nitk_nidn' => $nitk,
            'email' => $email,
            'password' => $hash,
            'role' => $role,
            'is_admin' => $role === 'laboran' ? 1 : 0,
            'is_active' => 1,
            'approval_status' => 'approved',
            'email_verified_at' => $now,
            'kelas' => $kelas,
            'tim' => $tim,
            'shift' => $role === 'mahasiswa' ? 'pagi' : null,
            'finger_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'failed_login_attempts' => 0,
            'login_requires_admin_unlock' => 0,
        ];
        $insert = [];
        foreach ($row as $key => $value) {
            if (Schema::hasColumn('users', $key)) {
                $insert[$key] = $value;
            }
        }

        return (int) DB::table('users')->insertGetId($insert);
    }

    private static function day(int $userId, Carbon $date, string $plan): void
    {
        $d = $date->toDateString();
        $late = $plan === 'telat' ? 12 : 0;
        $status = $plan === 'telat' ? 'lengkap' : $plan;
        $masukId = null;
        $pulangId = null;

        if ($status !== 'alfa') {
            $masukId = LogAbsensi::query()->insertGetId([
                'user_id' => $userId,
                'id_mesin' => self::MESIN,
                'sesi' => 'pagi',
                'jenis' => 'masuk',
                'menit_terlambat' => $late,
                'slot_lab' => 1,
                'pulang_lembur' => 0,
                'waktu_tap' => $d.($late ? ' 08:02:00' : ' 07:42:00'),
            ]);
        }
        if ($status === 'lengkap') {
            $pulangId = LogAbsensi::query()->insertGetId([
                'user_id' => $userId,
                'id_mesin' => self::MESIN,
                'sesi' => 'pagi',
                'jenis' => 'pulang',
                'menit_terlambat' => 0,
                'slot_lab' => 1,
                'pulang_lembur' => 0,
                'waktu_tap' => $d.' 17:04:00',
            ]);
        }

        AbsensiHarian::query()->insert([
            'user_id' => $userId,
            'tanggal' => $d,
            'sesi' => 'pagi',
            'status' => $status,
            'masuk_log_id' => $masukId,
            'pulang_log_id' => $pulangId,
            'menit_terlambat_masuk' => $late,
            'menit_terlambat_pulang' => 0,
            'slot_lab_masuk' => $masukId ? 1 : null,
            'pulang_lembur' => 0,
            'lengkap_at' => $pulangId ? $d.' 17:04:00' : null,
            'created_at' => $d.' 07:42:00',
            'updated_at' => $d.' 17:04:00',
        ]);
    }
}
