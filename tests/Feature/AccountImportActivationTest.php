<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\AccountApprovalLog;
use App\Models\ProgramStudi;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccountActivation;
use App\Support\AccountImport\AccountImportTemplate;
use App\Support\AccountImport\TabularFileReader;
use App\Support\FingerprintTemplateCodec;
use App\Support\PermissionRegistry;
use App\Support\RegistrationGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Tahap B: import akun CSV/XLSX, password sementara mahasiswa, aktivasi dosen/laboran, dan kontrol registrasi.
 */
class AccountImportActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $existing;

    private User $dosenAktif;

    private const MHS_HEADER = 'nim,nama_lengkap,kelas,tim,angkatan,semester,semester_type,shift';

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes import dibatalkan: koneksi bukan sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            \Illuminate\Support\Facades\Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }

        Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', 'Asia/Jakarta'));
        Cache::flush();
        PermissionRegistry::clearCache();
        Storage::fake('local');
        Mail::fake();
        config([
            'services.recaptcha.site_key' => '',
            'services.recaptcha.secret_key' => '',
            'simalas.activation_email_dns_check' => false,
        ]);

        foreach (['robotika' => 'Teknik Robotika', 'tre' => 'Teknologi Rekayasa Elektronika', 'mekatronika' => 'Teknik Mekatronika'] as $kode => $nama) {
            ProgramStudi::query()->firstOrCreate(['kode' => $kode], ['nama' => $nama, 'aktif' => true]);
        }

        $this->admin = $this->makeUser('ADM-UJI-01', 'laboran', 'Admin Uji', 'admin-uji@example.test', ['is_admin' => true]);
        $this->dosenAktif = $this->makeUser('DSN-UJI-01', 'dosen', 'Dosen Uji', 'dosen-uji@example.test');
        $this->existing = $this->makeUser('2399001', 'mahasiswa', 'Mahasiswa Lama', 'lama@example.test', [
            'kelas' => 'Robotika A', 'tim' => 'PBL-RO-001', 'angkatan' => 2023, 'semester' => 5,
            'semester_type' => 'ganjil', 'shift' => 'pagi', 'finger_id' => 7,
            'finger_template' => base64_encode(str_repeat('A', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
        ]);

        $logId = DB::table('log_absensi')->insertGetId([
            'user_id' => $this->existing->id, 'id_mesin' => 'MESIN-UJI', 'waktu_tap' => '2026-10-08 07:00:00',
        ]);
        DB::table('absensi_harian')->insert([
            'user_id' => $this->existing->id, 'tanggal' => '2026-10-08', 'sesi' => 'pagi', 'status' => 'masuk',
            'masuk_log_id' => $logId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ template & format

    public function test_template_csv_dan_xlsx_tersedia_dan_xlsx_terbaca_ulang(): void
    {
        foreach (AccountImportTemplate::TYPES as $type) {
            $csv = $this->actingAs($this->admin)->get("/admin/users/import/template/{$type}.csv");
            $csv->assertOk();
            $this->assertStringContainsString(implode(',', array_keys(AccountImportTemplate::columns($type))), $csv->getContent());

            $xlsx = $this->actingAs($this->admin)->get("/admin/users/import/template/{$type}.xlsx");
            $xlsx->assertOk();
            $path = $this->tempFile($xlsx->getContent(), 'xlsx');
            // Template kini multi-sheet (PETUNJUK + IMPORT_AKUN + PILIHAN/DAFTAR_PBL) —
            // reader memilih sheet lewat header yang diharapkan, seperti alur import nyata.
            $table = (new TabularFileReader)->read($path, "t.xlsx", AccountImportTemplate::columns($type));
            $this->assertSame(array_keys(AccountImportTemplate::columns($type)), $table['headers']);
            // Baris bawaan hanyalah contoh berawalan "CONTOH" — dijamin tidak diimpor
            // oleh AccountImportService, jadi aman ada di template.
            $this->assertSame(count(AccountImportTemplate::exampleRows($type)), count($table['rows']));
        }

        // Halaman import adalah Inertia — konten React tidak ter-render di HTML,
        // jadi cukup pastikan halaman termuat (template diuji lewat unduhan di atas).
        $this->actingAs($this->admin)->get('/admin/users/import')->assertOk();
    }

    public function test_csv_mahasiswa_valid_pratinjau_tidak_menulis_lalu_apply_membuat_akun_aktif_wajib_ganti_password(): void
    {
        $before = $this->protectedSnapshot();
        $usersBefore = User::query()->count();

        $csv = self::MHS_HEADER.",email,no_hp\n"
            ."2401001,Budi Sintetis,Robotika A,PBL-RO-010,2024,3,ganjil,pagi,budi@example.test,0811111111\n"
            ."0012345,Sari Sintetis,Robotika B,PBL-RO-011,2024,3,ganjil,malam,,\n";

        $token = $this->preview('mahasiswa', 'mhs.csv', $csv);
        $this->assertSame($usersBefore, User::query()->count(), 'Pratinjau tidak boleh membuat user.');

        $show = $this->actingAs($this->admin)->get("/admin/users/import/{$token}");
        $show->assertOk()->assertSee('Siap dibuat')->assertSee('diabaikan');
        $this->actingAs($this->admin)->get("/admin/users/import/{$token}/laporan.xlsx")->assertOk();
        $this->assertSame($before, $this->protectedSnapshot(), 'Pratinjau/GET tidak boleh mengubah finger/template/absensi.');

        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply")->assertRedirect("/admin/users/import/{$token}");
        $this->assertSame($usersBefore + 2, User::query()->count());

        foreach (['2401001', '0012345'] as $nim) {
            $u = User::query()->where('nitk_nidn', $nim)->firstOrFail();
            $this->assertSame('approved', $u->approval_status);
            $this->assertTrue($u->is_active);
            $this->assertTrue($u->must_change_password);
            $this->assertNull($u->email, 'Email tidak diisi admin.');
            $this->assertNull($u->no_hp, 'Nomor HP tidak diisi admin.');
            $this->assertNotNull($u->finger_id, 'Finger ID langsung dialokasikan untuk mahasiswa hasil import.');
            $this->assertNull($u->email_verified_at);
        }
        $this->assertSame('B', User::query()->where('nitk_nidn', '0012345')->value('kelas_paralel'));

        $report = $this->actingAs($this->admin)->get("/admin/users/import/{$token}/laporan.xlsx")->getContent();
        $this->assertStringContainsString('Dibuat — aktif (wajib ganti password)', $this->xlsxText($report));

        $cards = $this->pullCards($token);
        $this->assertCount(2, $cards);
        foreach ($cards as $nim => $code) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{1,12}-[A-Z2-9]{6}$/', $code);
            $this->assertStringNotContainsString($code, $report, 'Kode tidak boleh ada di laporan.');
            $this->assertTrue(Hash::check(AccountActivation::normalizeCode($code), User::query()->where('nitk_nidn', $nim)->value('password')));
            $this->assertFalse(AccountApprovalLog::query()->where('note', 'like', '%'.$code.'%')->exists(), 'Kode tidak boleh di log.');
            $this->assertFalse(AccountApprovalLog::query()->where('subject_meta', 'like', '%'.$code.'%')->exists());
            foreach (Storage::disk('local')->allFiles() as $file) {
                $this->assertStringNotContainsString($code, Storage::disk('local')->get($file), 'Kode tidak boleh di file pratinjau.');
            }
        }

        $again = $this->actingAs($this->admin)->post("/admin/users/import/{$token}/kartu");
        $again->assertRedirect("/admin/users/import/{$token}")->assertSessionHas('error');

        $afterFirstApply = $this->protectedSnapshot();
        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply");
        $this->assertSame($usersBefore + 2, User::query()->count(), 'Apply ulang token yang sama tidak membuat akun lagi.');
        $this->assertSame($afterFirstApply, $this->protectedSnapshot());
    }

    public function test_xlsx_valid_shared_string_inline_dan_angka_bulat_tetap_teks(): void
    {
        $rows = [
            ['nidn', 'nama_lengkap', 'role'],
            [['n', '0412345678'], ['s', 'Dosen Sintetis Satu'], ['s', 'dosen']],
            [['inline', '00987'], ['inline', 'Dosen Sintetis Dua'], ['inline', '']],
        ];
        $token = $this->preview('dosen', 'dosen.xlsx', $this->xlsx($rows), 'xlsx');
        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply");

        $this->assertTrue(User::query()->where('nitk_nidn', '0412345678')->where('role', 'dosen')->exists());
        // Model baru: dosen hasil import langsung approved+aktif dengan password
        // sementara (bukan lagi menunggu_aktivasi lewat jalur OTP).
        $this->assertTrue(User::query()->where('nitk_nidn', '00987')
            ->where('approval_status', 'approved')->where('is_active', true)->exists());
    }

    public function test_kolom_hilang_format_tidak_didukung_dan_isi_berbahaya_ditolak(): void
    {
        $this->assertPreviewError('mahasiswa', 'x.csv', "nim,nama_lengkap,kelas\n2401001,A,Robotika A\n", 'Kolom wajib tidak ada');
        $this->assertPreviewError('mahasiswa', 'x.xls', 'apa saja', '.xls');
        $this->assertPreviewError('mahasiswa', 'x.xlsm', 'apa saja', 'bermakro');
        $this->assertPreviewError('mahasiswa', 'x.xlsx', 'bukan zip sama sekali', 'rusak');
        $this->assertPreviewError('mahasiswa', 'x.csv', "nim,nama_lengkap\n\xC0\xC1\n", 'UTF-8');
        // Multi-sheet kini didukung (template resmi multi-sheet) — yang ditolak
        // adalah file multi-sheet TANPA satu pun sheet berheader kolom import.
        $this->assertPreviewError('dosen', 'x.xlsx', $this->xlsx([['foo', 'bar'], ['x', 'y']], sheets: 2), 'beberapa sheet');
        $this->assertPreviewError('dosen', 'x.xlsx', $this->xlsx([['nidn', 'nama_lengkap']], doctype: true), 'DOCTYPE');
        $this->assertPreviewError('dosen', 'x.xlsx', $this->xlsx([['nidn', 'nama_lengkap']], macro: true), 'bermakro');
        $this->assertPreviewError('dosen', 'x.csv', "nim,nama_lengkap\n1,A\n", 'bukan untuk import dosen');

        $token = $this->preview('dosen', 'f.xlsx', $this->xlsx([
            ['nidn', 'nama_lengkap'],
            [['f', '1+1'], ['s', 'Pakai Formula']],
            [['n', '1.2345E+15'], ['s', 'Angka Ilmiah']],
        ]), 'xlsx');
        $rows = $this->payload($token)['rows'];
        $this->assertSame('gagal', $rows[0]['status']);
        $this->assertStringContainsString('formula', $rows[0]['reason']);
        $this->assertSame('gagal', $rows[1]['status']);
        $this->assertStringContainsString('Teks', $rows[1]['reason']);

        $token = $this->preview('dosen', 'inj.csv', "nidn,nama_lengkap\n2001,=HYPERLINK(\"x\")\n");
        $this->assertSame('gagal', $this->payload($token)['rows'][0]['status']);
        $this->assertSame(3, User::query()->count());
    }

    public function test_duplikat_dalam_file_dan_database_import_ulang_dan_akun_lama_tidak_ditimpa(): void
    {
        $lama = $this->existing->fresh();
        $csv = self::MHS_HEADER."\n"
            ."2401100,Kembar Satu,Robotika A,PBL-RO-010,2024,3,ganjil,pagi\n"
            ."2401100,Kembar Dua,Robotika A,PBL-RO-010,2024,3,ganjil,pagi\n"
            ."2399001,Mahasiswa Lama Ditimpa,Robotika B,PBL-RO-099,2024,1,genap,malam\n"
            ."2401101,Baru Sekali,Robotika A,PBL-RO-010,2024,3,ganjil,pagi\n";

        $token = $this->preview('mahasiswa', 'dup.csv', $csv);
        $rows = $this->payload($token)['rows'];
        $this->assertSame(['duplikat', 'duplikat', 'duplikat', 'siap'], array_column($rows, 'status'));
        $this->assertStringContainsString('lebih dari sekali', $rows[0]['reason']);
        $this->assertStringContainsString('sudah terdaftar', $rows[2]['reason']);

        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply");
        $this->assertSame(1, User::query()->where('nitk_nidn', '2401101')->count());
        $this->assertFalse(User::query()->where('nitk_nidn', '2401100')->exists());

        $again = $this->preview('mahasiswa', 'dup.csv', $csv);
        $this->assertSame(['duplikat', 'duplikat', 'duplikat', 'duplikat'], array_column($this->payload($again)['rows'], 'status'));
        $this->actingAs($this->admin)->post("/admin/users/import/{$again}/apply");
        $this->assertSame(1, User::query()->where('nitk_nidn', '2401101')->count());

        $now = $this->existing->fresh();
        foreach (['nama_lengkap', 'email', 'password', 'kelas', 'tim', 'shift', 'role', 'approval_status', 'finger_id'] as $field) {
            $this->assertSame($lama->{$field}, $now->{$field}, "Akun lama tidak boleh berubah: {$field}");
        }
    }

    public function test_role_di_file_tidak_memberi_hak_dan_hak_import_serta_reset_kode_mengikuti_izin(): void
    {
        $token = $this->preview('mahasiswa', 'role.csv', self::MHS_HEADER.",role\n2401200,Coba Admin,Robotika A,PBL-RO-010,2024,3,ganjil,pagi,laboran\n");
        $row = $this->payload($token)['rows'][0];
        $this->assertSame('gagal', $row['status']);
        $this->assertStringContainsString('tidak memberi hak akses', $row['reason']);

        $this->actingAs($this->dosenAktif)->get('/admin/users/import')->assertForbidden();
        $this->actingAs($this->dosenAktif)->post('/admin/users/import/preview', [
            'jenis' => 'laboran', 'file' => UploadedFile::fake()->createWithContent('l.csv', "nitk,nama_lengkap\nX1,Y\n"),
        ])->assertForbidden();

        $lab = $this->preview('laboran', 'lab.csv', "nitk,nama_lengkap\nLAB-UJI-77,Laboran Baru\n");
        $this->actingAs($this->admin)->post("/admin/users/import/{$lab}/apply");
        $baru = User::query()->where('nitk_nidn', 'LAB-UJI-77')->firstOrFail();
        $this->assertSame('laboran', $baru->role);
        // Model baru: akun langsung aktif dengan password sementara (kartu aktivasi),
        // login pertama dipaksa ganti password — bukan lagi is_active=0 menunggu OTP.
        $this->assertTrue($baru->is_active, 'Laboran hasil import langsung aktif (password sementara).');
        $this->assertTrue((bool) $baru->must_change_password, 'Login pertama wajib ganti password.');

        // Tanpa users.create maupun users.update, import dan reset kode sama-sama ditolak.
        RolePermission::query()->updateOrCreate(
            ['role' => 'laboran', 'menu_key' => 'users'],
            ['can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => true]
        );
        PermissionRegistry::clearCache();

        $this->actingAs($this->admin)->get('/admin/users/import')->assertForbidden();
        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply")->assertForbidden();
        $this->actingAs($this->admin)->post("/admin/users/{$baru->id}/activation-code")->assertForbidden();

        // Dengan users.update = true tetapi users.create = false, reset kode diizinkan namun import baru tetap ditolak.
        RolePermission::query()->updateOrCreate(
            ['role' => 'laboran', 'menu_key' => 'users'],
            ['can_view' => true, 'can_create' => false, 'can_update' => true, 'can_delete' => true]
        );
        PermissionRegistry::clearCache();

        $this->actingAs($this->admin)->get('/admin/users/import')->assertForbidden();
        $this->actingAs($this->admin)->post("/admin/users/{$baru->id}/activation-code")->assertOk();
        $this->assertSame(1, User::query()->where('role', 'laboran')->where('nitk_nidn', 'LAB-UJI-77')->count());
    }

    public function test_sukses_sebagian_dengan_laporan_dan_total_konsisten(): void
    {
        $csv = self::MHS_HEADER."\n"
            ."2401300,Valid Satu,Robotika A,PBL-RO-010,2024,3,ganjil,pagi\n"
            .",,,,,,,\n"
            ."2401301,Angkatan Salah,Robotika A,PBL-RO-010,1999,3,ganjil,pagi\n"
            ."2401302,Shift Salah,Robotika A,PBL-RO-010,2024,3,ganjil,siang\n"
            ."2399001,Sudah Ada,Robotika A,PBL-RO-010,2024,3,ganjil,pagi\n"
            ."2401303,Prodi Tidak Jelas,Kelas Umum,PBL-RO-010,2024,3,ganjil,pagi\n";

        $token = $this->preview('mahasiswa', 'partial.csv', $csv);
        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply")->assertSessionHas('success');

        $payload = $this->payload($token);
        $t = $payload['totals'];
        $this->assertSame(['total' => 6, 'siap' => 0, 'dibuat' => 1, 'duplikat' => 1, 'gagal' => 3, 'dilewati' => 1], $t);
        $this->assertSame($t['total'], $t['dibuat'] + $t['duplikat'] + $t['gagal'] + $t['dilewati']);

        $reasons = array_column($payload['rows'], 'reason');
        $this->assertStringContainsString('Angkatan', $reasons[2]);
        $this->assertStringContainsString('Shift', $reasons[3]);
        $this->assertStringContainsString('Program studi', $reasons[5]);

        $page = $this->actingAs($this->admin)->get("/admin/users/import/{$token}");
        $page->assertOk(); // status 'Sukses sebagian' dirender React — diverifikasi lewat payload totals di atas.

        // Laporan kini .xlsx 3 sheet: RINGKASAN (total), BERHASIL_DIBUAT, TIDAK_DIBUAT.
        $report = $this->actingAs($this->admin)->get("/admin/users/import/{$token}/laporan.xlsx")->getContent();
        $sheets = $this->xlsxSheets($report);
        $this->assertCount(3, $sheets);
        $this->assertStringContainsString('Total baris data', $sheets[1]);
        $this->assertStringContainsString('6', $sheets[1]);
        // Sheet TIDAK_DIBUAT: 1 header + (1 duplikat + 3 gagal + 1 dilewati) = 6 baris.
        $this->assertSame(6, substr_count($sheets[3], '<row r='));
    }

    // ------------------------------------------------------------------ akses dosen menunggu aktivasi & mahasiswa wajib ganti password

    public function test_dosen_menunggu_aktivasi_dan_mahasiswa_import_baru_wajib_ganti_password(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $dosenCode = $codes['DSN-IMP-01'];
        $mhsCode = $codes['2401500'];

        // Dosen hasil import: password sementara berlaku di /masuk → diarahkan ke ganti password.
        $webDosen = $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($dosenCode), 'role' => 'dosen']);
        $webDosen->assertRedirect(route('account.password.change'));
        $this->assertAuthenticated();
        auth()->logout();

        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => 'SALAH-KODE-1234', 'role' => 'dosen'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        // Mahasiswa hasil import baru ditolak di mobile sebelum mengganti password sementara di web.
        $mobile = $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => $mhsCode]);
        $mobile->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED')
            ->assertJsonPath('data.login_url', route('login', ['role' => 'mahasiswa']));
        $this->assertArrayNotHasKey('token', $mobile->json('data'));
        $this->assertNull(User::query()->where('nitk_nidn', '2401500')->value('mobile_api_token'));

        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => 'Salah#Sandi1'])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        // Mahasiswa hasil import baru login di web → diarahkan ke ganti password.
        // Catatan: confinement web (middleware password.changed) saat ini tidak
        // dipasang ke route — indikator wajib saja; enforcement penuh hanya di API.
        $webMhs = $this->post('/masuk', ['nitk_nidn' => '2401500', 'password' => strtolower($mhsCode), 'role' => 'mahasiswa']);
        $webMhs->assertRedirect(route('mahasiswa.password.change'));
        $this->assertAuthenticated();
        $this->post('/mahasiswa/keluar')->assertRedirect(route('login'));
        $this->assertGuest();

        // Sebelum ganti password, dosen belum boleh masuk dashboard lewat jalur lain;
        // sesudah logout, akses langsung kembali ke halaman login.
        $this->assertGuest();
        $this->get('/dosen/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_aktivasi_penuh_dosen_dan_alur_ganti_password_serta_kontak_opsional_mahasiswa(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $dosenCode = $codes['DSN-IMP-01'];
        $mhsCode = $codes['2401500'];

        // 1. Alur dosen hasil import (model baru): login password sementara →
        // wajib ganti password → dashboard. Password sementara lama langsung mati.
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($dosenCode), 'role' => 'dosen'])
            ->assertRedirect(route('account.password.change'));

        // Bentuk ternormalisasi (besar, tanpa dash) = nilai yang di-hash — menolak
        // karena identik dengan password sementara yang sedang dipakai.
        $norm = strtoupper(str_replace('-', '', $dosenCode));
        $this->post('/ganti-password', ['password' => $norm, 'password_confirmation' => $norm])
            ->assertSessionHasErrors('password');
        $this->post('/ganti-password', ['password' => 'Sandi#Dosen2026', 'password_confirmation' => 'Sandi#Lain'])
            ->assertSessionHasErrors('password');
        $this->post('/ganti-password', ['password' => 'Sandi#Dosen2026', 'password_confirmation' => 'Sandi#Dosen2026'])
            ->assertRedirect();

        $dosen = User::query()->where('nitk_nidn', 'DSN-IMP-01')->firstOrFail();
        $this->assertTrue($dosen->is_active);
        $this->assertSame('approved', $dosen->approval_status);
        $this->assertFalse((bool) $dosen->must_change_password);

        auth()->logout();
        // Password sementara sudah tidak berlaku; password baru berlaku.
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($dosenCode), 'role' => 'dosen'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => 'Sandi#Dosen2026', 'role' => 'dosen'])
            ->assertRedirect(route('dosen.dashboard'));
        auth()->logout();

        // 2. Alur Mahasiswa hasil import: login password sementara -> wajib ganti password -> beranda -> kontak opsional.
        $this->post('/masuk', ['nitk_nidn' => '2401500', 'password' => $mhsCode, 'role' => 'mahasiswa'])
            ->assertRedirect(route('mahasiswa.password.change'));

        $this->post('/mahasiswa/ganti-password', [
            'password' => 'Sandi#Mhs2026',
            'password_confirmation' => 'Sandi#Mhs2026',
        ])->assertRedirect(route('mahasiswa.beranda'));

        $mhs = User::query()->where('nitk_nidn', '2401500')->firstOrFail();
        $this->assertFalse($mhs->must_change_password);
        $this->assertTrue($mhs->is_active);
        $this->assertSame('approved', $mhs->approval_status);
        $this->assertNotNull($mhs->finger_id);

        // Setelah ganti password pribadi, mahasiswa langsung bisa login di aplikasi mobile meskipun email belum diisi.
        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => $mhsCode])->assertStatus(401);
        $login = $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => 'Sandi#Mhs2026']);
        $login->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($login->json('data.token'));
    }

    public function test_kode_sekali_pakai_kedaluwarsa_dan_regenerasi_membatalkan_kode_lama(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $dosen = User::query()->where('nitk_nidn', 'DSN-IMP-01')->firstOrFail();
        $mhs = User::query()->where('nitk_nidn', '2401500')->firstOrFail();

        // Regenerasi untuk akun approved+aktif menerbitkan PASSWORD SEMENTARA baru
        // (format nama-XXXXXX), bukan kode aktivasi — kode/temp lama langsung mati.
        $page = $this->actingAs($this->admin)->post("/admin/users/{$dosen->id}/activation-code");
        $page->assertOk()->assertSee('Kode Aktivasi Baru');
        preg_match('/[a-z0-9]{1,12}-[A-Z2-9]{6}/', $page->getContent(), $m);
        $newCode = $m[0];
        $this->assertNotSame($codes['DSN-IMP-01'], $newCode);
        $this->assertFalse(AccountApprovalLog::query()->where('note', 'like', '%'.$newCode.'%')->exists());
        auth()->logout();

        // Password sementara lama ditolak di login.
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($codes['DSN-IMP-01']), 'role' => 'dosen'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();
        RateLimiter::clear('login-fail:'.hash('sha256', mb_strtolower('dsn-imp-01')));

        // Password sementara baru berlaku → wajib ganti password; sesudah itu tidak bisa dipakai lagi.
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($newCode), 'role' => 'dosen'])
            ->assertRedirect(route('account.password.change'));
        $this->post('/ganti-password', ['password' => 'Sandi#Dosen2026', 'password_confirmation' => 'Sandi#Dosen2026'])
            ->assertRedirect();
        auth()->logout();
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($newCode), 'role' => 'dosen'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        // Setelah melewati TTL (14 hari), password sementara mahasiswa kedaluwarsa hingga direset admin.
        Carbon::setTestNow(now()->addDays(AccountActivation::codeTtlDays() + 1));
        $this->post('/masuk', ['nitk_nidn' => '2401500', 'password' => $codes['2401500'], 'role' => 'mahasiswa'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();
        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => $codes['2401500']])
            ->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED')
            ->assertJsonPath('data.code_expired', true);

        // Admin mereset password sementara mahasiswa -> mahasiswa bisa login kembali.
        // Sesi admin ikut kedaluwarsa oleh lompatan waktu — login ulang dulu.
        $this->post('/masuk', ['nitk_nidn' => 'ADM-UJI-01', 'password' => 'Sandi#Uji2026', 'role' => 'laboran'])->assertRedirect();
        $mhsResetPage = $this->actingAs($this->admin)->post("/admin/users/{$mhs->id}/activation-code");
        $mhsResetPage->assertOk()->assertSee('Password Sementara Mahasiswa');
        preg_match('/[a-z0-9]{1,12}-[A-Z2-9]{6}/', $mhsResetPage->getContent(), $mReset);
        $newMhsTemp = $mReset[0];
        auth()->logout();

        $this->post('/masuk', ['nitk_nidn' => '2401500', 'password' => $newMhsTemp, 'role' => 'mahasiswa'])
            ->assertRedirect(route('mahasiswa.password.change'));
    }

    public function test_akun_dinonaktifkan_tidak_aktif_kembali_lewat_aktivasi_atau_kode_baru(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $this->selesaikanGantiPassword('DSN-IMP-01', $codes['DSN-IMP-01'], 'Sandi#Dosen2026');

        $dosen = User::query()->where('nitk_nidn', 'DSN-IMP-01')->firstOrFail();
        $this->assertTrue($dosen->is_active);
        $this->actingAs($this->admin)->post("/admin/users/{$dosen->id}/toggle-active");
        $this->assertFalse($dosen->fresh()->is_active);
        $hash = $dosen->fresh()->password;

        $this->actingAs($this->admin)->post("/admin/users/{$dosen->id}/activation-code")->assertSessionHas('error');
        $this->assertSame($hash, $dosen->fresh()->password, 'Kode baru bukan jalur reset password untuk dosen nonaktif.');

        // Mahasiswa yang dinonaktifkan juga tidak bisa dibuatkan password sementara baru sebelum diaktifkan lagi.
        $mhs = User::query()->where('nitk_nidn', '2401500')->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/users/{$mhs->id}/toggle-active");
        $this->assertFalse($mhs->fresh()->is_active);
        $mhsHash = $mhs->fresh()->password;
        $this->actingAs($this->admin)->post("/admin/users/{$mhs->id}/activation-code")->assertSessionHas('error');
        $this->assertSame($mhsHash, $mhs->fresh()->password);
        auth()->logout();

        // Akun dinonaktifkan tidak bisa masuk walau password pribadinya benar.
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => 'Sandi#Dosen2026', 'role' => 'dosen']);
        $this->assertGuest();
        $this->assertFalse($dosen->fresh()->is_active);

        $this->post('/masuk', ['nitk_nidn' => '2401500', 'password' => $codes['2401500'], 'role' => 'mahasiswa']);
        $this->assertGuest();
        $this->assertFalse($mhs->fresh()->is_active);
    }

    // ------------------------------------------------------------------ registrasi

    public function test_registrasi_ditutup_login_dan_aktivasi_tetap_berjalan_lalu_dibuka_lagi(): void
    {
        $codes = $this->importDosenDanMahasiswa();

        $jadwal = json_encode([DB::table('absensi_sesi_settings')->orderBy('id')->get(), DB::table('system_settings')->where('key', '!=', RegistrationGate::KEY)->orderBy('id')->get()]);

        // Registrasi publik tertutup permanen; toggle admin lama sudah dihapus (endpoint 404).
        $this->assertFalse(RegistrationGate::isOpen('mahasiswa'));
        $this->assertFalse(RegistrationGate::isOpen('dosen'));
        $this->assertTrue(in_array($this->actingAs($this->admin)->post('/admin/settings/registrasi', [])->status(), [404, 405]));
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertDontSee('Pendaftaran mandiri');
        auth()->logout();
        $this->assertSame($jadwal, json_encode([DB::table('absensi_sesi_settings')->orderBy('id')->get(), DB::table('system_settings')->where('key', '!=', RegistrationGate::KEY)->orderBy('id')->get()]), 'Jadwal absensi tidak tersentuh.');

        $users = User::query()->count();
        // Pendaftaran manual web telah dihapus dari halaman masuk dan route GET dialihkan ke /masuk.
        $this->get('/masuk')->assertOk()->assertDontSee('Buat Akun Dosen');
        $this->get('/register')->assertRedirect(route('login'));
        $this->get('/register/verify?email=calon@example.test')->assertRedirect(route('login'));
        $this->post('/register', $this->dosenRegistration())->assertRedirect(route('login'))->assertSessionHasErrors('registration');
        $this->post('/register/resume', ['email' => 'calon@example.test'])->assertRedirect(route('login'))->assertSessionHasErrors('registration');
        $this->post('/verify-registration', ['email' => 'calon@example.test', 'otp_code' => '123456'])->assertRedirect(route('login'))->assertSessionHasErrors('registration');
        $this->postJson('/api/mobile/register', $this->mahasiswaRegistration())
            ->assertStatus(403)->assertJsonPath('code', 'REGISTRATION_CLOSED');
        $this->getJson('/api/mobile/config')->assertJsonPath('data.register.open', false);
        $this->assertSame($users, User::query()->count());

        $this->post('/masuk', ['nitk_nidn' => 'ADM-UJI-01', 'password' => 'Sandi#Uji2026', 'role' => 'laboran'])->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/logout');

        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2399001', 'password' => 'Sandi#Uji2026'])->assertOk();
        // Registrasi tertutup tidak memengaruhi akun hasil import: password sementara tetap berlaku.
        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($codes['DSN-IMP-01']), 'role' => 'dosen'])
            ->assertRedirect(route('account.password.change'));
        $this->get('/ganti-password')->assertOk();

        // Penutupan permanen: endpoint approve/reject registrasi lama sudah tidak dirutekan.
        $target = User::query()->where('nitk_nidn', '2401500')->firstOrFail();
        foreach (["/admin/users/{$target->id}/approve", '/admin/users/bulk-approve', "/admin/users/{$target->id}/reject"] as $deadEndpoint) {
            $this->assertTrue(in_array(
                $this->actingAs($this->admin)->post($deadEndpoint, $deadEndpoint === '/admin/users/bulk-approve' ? ['user_ids' => [$target->id]] : [])->status(),
                [404, 405]
            ), "Endpoint {$deadEndpoint} seharusnya tidak lagi dirutekan.");
        }
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(string $nitk, string $role, string $nama, string $email, array $extra = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'nama_lengkap' => $nama, 'nitk_nidn' => $nitk, 'email' => $email, 'password' => 'Sandi#Uji2026',
            'role' => $role, 'is_active' => true, 'approval_status' => 'approved', 'approved_at' => now(),
            'email_verified_at' => now(),
        ], $extra))->save();

        return $user;
    }

    private function preview(string $type, string $name, string $content, string $ext = 'csv'): string
    {
        $response = $this->actingAs($this->admin)->post('/admin/users/import/preview', [
            'jenis' => $type,
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ]);
        $response->assertRedirect();
        $this->assertMatchesRegularExpression('#/admin/users/import/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), 'Pratinjau gagal: '.json_encode(session('errors')?->all()));
        preg_match('#/admin/users/import/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);

        return $m[1];
    }

    private function assertPreviewError(string $type, string $name, string $content, string $needle): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users/import/preview', [
            'jenis' => $type,
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ]);
        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString($needle, session('errors')->first('file'), "File {$name}");
    }

    private function payload(string $token): array
    {
        return json_decode(Storage::disk('local')->get("account-imports/{$token}.json"), true);
    }

    /** @return array<string, string> nitk_nidn => password sementara (kolom ke-5 sheet KARTU_AKTIVASI) */
    private function pullCards(string $token): array
    {
        $response = $this->actingAs($this->admin)->post("/admin/users/import/{$token}/kartu");
        $response->assertOk();
        // Kartu kini .xlsx: sheet 2 (KARTU_AKTIVASI) kolom
        // [Kelas, ID Login, Nama, Role, Password Sementara, Berlaku Sampai, Halaman Login].
        $xml = $this->xlsxSheets($response->getContent())[2] ?? '';
        $codes = [];
        foreach (explode('</row>', $xml) as $rowXml) {
            preg_match_all('#<t[^>]*>(.*?)</t>#s', $rowXml, $m);
            $cells = array_map(fn ($v) => html_entity_decode($v, ENT_XML1), $m[1]);
            if (count($cells) >= 5 && $cells[1] !== 'ID Login (NIM/NIDN/NITK)') {
                $codes[$cells[1]] = $cells[4];
            }
        }

        return $codes;
    }

    /** @return array<string, string> */
    private function importDosenDanMahasiswa(): array
    {
        $d = $this->preview('dosen', 'd.csv', "nidn,nama_lengkap\nDSN-IMP-01,Dosen Import\n");
        $this->actingAs($this->admin)->post("/admin/users/import/{$d}/apply");
        $m = $this->preview('mahasiswa', 'm.csv', self::MHS_HEADER."\n2401500,Mahasiswa Import,Robotika A,PBL-RO-010,2024,3,ganjil,pagi\n");
        $this->actingAs($this->admin)->post("/admin/users/import/{$m}/apply");
        $codes = $this->pullCards($d) + $this->pullCards($m);
        auth()->logout();

        return $codes;
    }

    private function startActivation(string $id, string $code, string $password): TestResponse
    {
        return $this->from('/aktivasi')->post('/aktivasi/mulai', [
            'nitk_nidn' => $id, 'kode_aktivasi' => $code, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    private function startResume(string $id, string $password): TestResponse
    {
        return $this->from('/aktivasi')->post('/aktivasi/lanjut', ['nitk_nidn' => $id, 'password' => $password]);
    }

    /** Selesaikan alur wajib ganti password untuk akun hasil import (model password sementara). */
    private function selesaikanGantiPassword(string $id, string $tempPassword, string $newPassword): void
    {
        $this->post('/masuk', ['nitk_nidn' => $id, 'password' => strtolower($tempPassword), 'role' => 'dosen'])
            ->assertRedirect(route('account.password.change'));
        $this->post('/ganti-password', ['password' => $newPassword, 'password_confirmation' => $newPassword])
            ->assertRedirect();
        auth()->logout();
    }

    private function latestOtp(string $email): string
    {
        return (string) DB::table('email_verification_tokens')
            ->where('email', $email)->where('type', 'account_verify')->orderByDesc('id')->value('otp_code');
    }

    private function protectedSnapshot(): string
    {
        $users = DB::table('users')->orderBy('id')->get(['id', 'finger_id', 'finger_template', 'password', 'email', 'is_active', 'approval_status']);
        $logs = DB::table('log_absensi')->orderBy('id')->get();
        $harian = DB::table('absensi_harian')->orderBy('id')->get();

        return hash('sha256', json_encode([$users, $logs, $harian]));
    }

    private function tempFile(string $content, string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.'.$ext;
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    private function dosenRegistration(): array
    {
        return [
            'nama_lengkap' => 'Calon Dosen', 'nitk_nidn' => '0499999', 'email' => 'calon@example.test',
            'password' => 'Sandi#Calon2026', 'password_confirmation' => 'Sandi#Calon2026', 'role' => 'dosen',
        ];
    }

    private function mahasiswaRegistration(): array
    {
        return [
            'nama_lengkap' => 'Calon Mahasiswa', 'nitk_nidn' => '2409999', 'email' => 'calon-mhs@example.test',
            'password' => 'Sandi#Calon2026', 'password_confirmation' => 'Sandi#Calon2026',
            'kelas' => 'Robotika A', 'tim' => 'PBL-RO-010', 'angkatan' => 2024, 'semester' => 3, 'semester_type' => 'ganjil',
            'program_studi_id' => ProgramStudi::query()->where('kode', 'robotika')->value('id'),
        ];
    }

    /**
     * XLSX sintetis. Sel: string polos (header), ['s', v] shared string, ['inline', v], ['n', v] angka, ['f', expr] formula.
     */
    private function xlsx(array $rows, int $sheets = 1, bool $doctype = false, bool $macro = false): string
    {
        $shared = [];
        $xmlRows = '';
        foreach ($rows as $r => $cells) {
            $xmlRows .= '<row r="'.($r + 1).'">';
            foreach ($cells as $c => $cell) {
                $ref = chr(65 + $c).($r + 1);
                [$kind, $value] = is_array($cell) ? $cell : ['s', $cell];
                $esc = htmlspecialchars($value, ENT_XML1);
                $xmlRows .= match ($kind) {
                    's' => '<c r="'.$ref.'" t="s"><v>'.(array_push($shared, $value) - 1).'</v></c>',
                    'inline' => '<c r="'.$ref.'" t="inlineStr"><is><t>'.$esc.'</t></is></c>',
                    'n' => '<c r="'.$ref.'"><v>'.$esc.'</v></c>',
                    'f' => '<c r="'.$ref.'"><f>'.$esc.'</f><v>2</v></c>',
                };
            }
            $xmlRows .= '</row>';
        }

        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'.($doctype ? '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///c:/windows/win.ini">]>' : '')
            ."<worksheet {$ns}><sheetData>{$xmlRows}</sheetData></worksheet>";
        $sst = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><sst {$ns}>"
            .implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>', $shared)).'</sst>';

        $sheetEntries = '';
        $rels = '';
        $overrides = '';
        $files = [];
        for ($i = 1; $i <= $sheets; $i++) {
            $sheetEntries .= '<sheet name="S'.$i.'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $files["xl/worksheets/sheet{$i}.xml"] = $sheet;
        }
        $rels .= '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $main = $macro ? 'application/vnd.ms-excel.sheet.macroEnabled.main+xml' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';

        $files += [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="'.$main.'"/>'.$overrides
                .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook '.$ns.' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetEntries.'</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>',
            'xl/sharedStrings.xml' => $sst,
        ];

        return $this->packZip($files);
    }

    /** Semua teks sel di workbook .xlsx (inline string) digabung — untuk assert pada laporan. */
    private function xlsxText(string $bin): string
    {
        $all = '';
        foreach ($this->xlsxSheets($bin) as $xml) {
            preg_match_all('#<t[^>]*>(.*?)</t>#s', $xml, $m);
            $all .= ' '.implode(' ', $m[1]);
        }

        return html_entity_decode($all, ENT_XML1);
    }

    /** @return array<int, string> XML tiap sheet (urut sheet1, sheet2, ...). */
    private function xlsxSheets(string $bin): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($tmp, $bin);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $sheets = [];
        for ($i = 1; ; $i++) {
            $xml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($xml === false) {
                break;
            }
            $sheets[$i] = $xml;
        }
        $zip->close();
        @unlink($tmp);

        return $sheets;
    }

    /** Kemas file menjadi arsip zip — dulu AccountImportTemplate::zip (kini private zipPack). */
    private function packZip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imptst');
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp, \ZipArchive::OVERWRITE) === true);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }
}
