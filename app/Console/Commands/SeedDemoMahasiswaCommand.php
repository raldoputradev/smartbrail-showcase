<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\FingerIdAllocator;
use App\Support\MahasiswaProfileOptions;
use App\Support\MahasiswaShiftResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedDemoMahasiswaCommand extends Command
{
    protected $signature = 'simalas:seed-demo-mahasiswa
                            {count=15 : Jumlah mahasiswa demo}
                            {--nim-base=2024001 : NIM awal (increment otomatis)}
                            {--semester=2 : Semester (1-8)}
                            {--semester-type=ganjil : ganjil atau genap}
                            {--password=password123 : Password login}
                            {--angkatan=2024 : Angkatan}';

    protected $description = 'Buat mahasiswa demo aktif & disetujui untuk uji absensi';

    /** @var list<string> */
    private const DEMO_NAMES = [
        'Alyssa Putri Maharani',
        'Budi Santoso',
        'Citra Dewi Lestari',
        'Dimas Pratama',
        'Eka Surya Wijaya',
        'Fajar Nugroho',
        'Gita Anindya',
        'Hadi Wicaksono',
        'Indah Permata Sari',
        'Joko Susilo',
        'Kartika Ayu',
        'Lukman Hakim',
        'Maya Rizki',
        'Nanda Prasetyo',
        'Oki Ramadhan',
        'Putri Anggraini',
        'Qori Sandi',
        'Rizki Maulana',
        'Siti Nurhaliza',
        'Taufik Hidayat',
    ];

    public function handle(): int
    {
        $count = max(1, (int) $this->argument('count'));
        $nimBase = (int) $this->option('nim-base');
        $semester = max(1, min(8, (int) $this->option('semester')));
        $semesterType = strtolower((string) $this->option('semester-type'));
        $password = (string) $this->option('password');
        $angkatan = (int) $this->option('angkatan');

        if (!in_array($semesterType, ['ganjil', 'genap'], true)) {
            $this->error('Opsi --semester-type harus ganjil atau genap.');

            return self::FAILURE;
        }

        $kelasOptions = MahasiswaProfileOptions::kelasOptions();
        $prodiList = MahasiswaProfileOptions::programStudiList();
        $teamOptions = MahasiswaProfileOptions::pblTeamOptions();

        if ($prodiList === []) {
            $this->error('Tabel program_studi kosong. Jalankan seeder prodi terlebih dahulu.');

            return self::FAILURE;
        }

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $nim = (string) ($nimBase + $i);
            if (User::query()->where('nitk_nidn', $nim)->exists()) {
                $this->warn("Lewati NIM {$nim} — sudah terdaftar.");

                continue;
            }

            $kelas = $kelasOptions[array_rand($kelasOptions)];
            $prodiId = MahasiswaProfileOptions::inferProgramStudiId($kelas)
                ?? (int) $prodiList[array_rand($prodiList)]['id'];
            $tim = $teamOptions[array_rand($teamOptions)];
            $nama = self::DEMO_NAMES[$i % count(self::DEMO_NAMES)];
            if ($count > count(self::DEMO_NAMES)) {
                $nama .= ' ' . ($i + 1);
            }
            $email = 'demo' . $nim . '@simalas.test';

            if (User::query()->where('email', $email)->exists()) {
                $email = 'demo' . $nim . '.' . uniqid() . '@simalas.test';
            }

            $user = User::create(array_merge([
                'nama_lengkap' => $nama,
                'nitk_nidn' => $nim,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'mahasiswa',
                'kelas' => $kelas,
                'tim' => $tim,
                'program_studi_id' => $prodiId,
                'angkatan' => $angkatan,
                'semester' => $semester,
                'semester_type' => $semesterType,
                'profile_completed_at' => now(),
                'finger_id' => FingerIdAllocator::assignNext(),
                'is_active' => true,
                'approval_status' => 'approved',
                'approved_at' => now(),
                'email_verified_at' => now(),
            ], MahasiswaProfileOptions::syncKelasMetadata($kelas)));

            MahasiswaShiftResolver::ensure($user, null, true);

            $created[] = [
                'nim' => $nim,
                'nama' => $nama,
                'email' => $email,
                'kelas' => $kelas,
                'tim' => $tim,
                'finger_id' => $user->fresh()->finger_id,
            ];
        }

        if ($created === []) {
            $this->warn('Tidak ada mahasiswa baru dibuat (semua NIM sudah ada).');

            return self::SUCCESS;
        }

        $this->info('Mahasiswa demo dibuat: ' . count($created));
        $this->table(
            ['NIM', 'Nama', 'Email', 'Kelas', 'Tim PBL', 'Finger ID'],
            array_map(
                fn (array $row) => [
                    $row['nim'],
                    $row['nama'],
                    $row['email'],
                    $row['kelas'],
                    $row['tim'],
                    $row['finger_id'],
                ],
                $created
            )
        );
        $this->newLine();
        $this->line("Password semua akun: <info>{$password}</info>");
        $this->line('Semester: ' . $semester . ' ' . ucfirst($semesterType));

        return self::SUCCESS;
    }
}
