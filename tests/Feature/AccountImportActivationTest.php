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
 * Tahap B: import akun CSV/XLSX, aktivasi mandiri, dan kontrol registrasi. Data sintetis, OTP palsu.
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

        Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', 'Asia/Jakarta'));
        Cache::flush();
        PermissionRegistry::clearCache();
        Storage::fake('local');
        Mail::fake();
        config(['services.recaptcha.site_key' => '', 'services.recaptcha.secret_key' => '']);

        foreach (['robotika' => 'Teknik Robotika', 'tre' => 'Teknologi Rekayasa Elektronika', 'mekatronika' => 'Teknik Mekatronika'] as $kode => $nama) {
            ProgramStudi::query()->firstOrCreate(['kode' => $kode], ['nama' => $nama, 'aktif' => true]);
        }

        $this->admin = $this->makeUser('ADM-UJI-01', 'laboran', 'Admin Uji', 'admin-uji@example.test');
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
            $table = (new TabularFileReader)->read($path, "t.xlsx");
            $this->assertSame(array_keys(AccountImportTemplate::columns($type)), $table['headers']);
            $this->assertSame([], $table['rows']);
        }

        $this->actingAs($this->admin)->get('/admin/users/import')->assertOk()->assertSee('Template resmi');
    }

    public function test_csv_valid_pratinjau_tidak_menulis_lalu_apply_membuat_akun_menunggu_aktivasi(): void
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
        $this->actingAs($this->admin)->get("/admin/users/import/{$token}/laporan.csv")->assertOk();
        $this->assertSame($before, $this->protectedSnapshot(), 'Pratinjau/GET tidak boleh mengubah finger/template/absensi.');

        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply")->assertRedirect("/admin/users/import/{$token}");
        fwrite(STDERR, json_encode(array_column($this->payload($token)['rows'], 'reason')).json_encode(\Illuminate\Support\Facades\Log::getFacadeRoot() ? 1 : 0));
        $this->assertSame($usersBefore + 2, User::query()->count());

        foreach (['2401001', '0012345'] as $nim) {
            $u = User::query()->where('nitk_nidn', $nim)->firstOrFail();
            $this->assertSame(AccountActivation::STATUS, $u->approval_status);
            $this->assertFalse($u->is_active);
            $this->assertNull($u->email, 'Email tidak diisi admin.');
            $this->assertNull($u->no_hp, 'Nomor HP tidak diisi admin.');
            $this->assertNull($u->finger_id, 'Finger ID baru dialokasikan saat aktivasi selesai.');
            $this->assertNull($u->email_verified_at);
        }
        $this->assertSame('B', User::query()->where('nitk_nidn', '0012345')->value('kelas_paralel'));

        $report = $this->actingAs($this->admin)->get("/admin/users/import/{$token}/laporan.csv")->getContent();
        $this->assertStringContainsString('Dibuat — menunggu aktivasi', $report);

        $cards = $this->pullCards($token);
        $this->assertCount(2, $cards);
        foreach ($cards as $nim => $code) {
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
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

        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply");
        $this->assertSame($usersBefore + 2, User::query()->count(), 'Apply ulang token yang sama tidak membuat akun lagi.');
        $this->assertSame($before, $this->protectedSnapshot());
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
        $this->assertTrue(User::query()->where('nitk_nidn', '00987')->where('approval_status', AccountActivation::STATUS)->exists());
    }

    public function test_kolom_hilang_format_tidak_didukung_dan_isi_berbahaya_ditolak(): void
    {
        $this->assertPreviewError('mahasiswa', 'x.csv', "nim,nama_lengkap,kelas\n2401001,A,Robotika A\n", 'Kolom wajib tidak ada');
        $this->assertPreviewError('mahasiswa', 'x.xls', 'apa saja', '.xls');
        $this->assertPreviewError('mahasiswa', 'x.xlsm', 'apa saja', 'bermakro');
        $this->assertPreviewError('mahasiswa', 'x.xlsx', 'bukan zip sama sekali', 'rusak');
        $this->assertPreviewError('mahasiswa', 'x.csv', "nim,nama_lengkap\n\xC0\xC1\n", 'UTF-8');
        $this->assertPreviewError('dosen', 'x.xlsx', $this->xlsx([['nidn', 'nama_lengkap']], sheets: 2), 'tepat satu sheet');
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
        $this->assertStringContainsString('Sudah terdaftar', $rows[2]['reason']);

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

    public function test_role_di_file_tidak_memberi_hak_dan_hak_import_mengikuti_tambah_user(): void
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
        $this->assertFalse($baru->is_active, 'Laboran hasil import belum punya akses sebelum aktivasi.');

        RolePermission::query()->updateOrCreate(
            ['role' => 'laboran', 'menu_key' => 'users'],
            ['can_view' => true, 'can_create' => false, 'can_update' => true, 'can_delete' => true]
        );
        PermissionRegistry::clearCache();

        $this->actingAs($this->admin)->get('/admin/users/import')->assertForbidden();
        $this->actingAs($this->admin)->post("/admin/users/import/{$token}/apply")->assertForbidden();
        $this->actingAs($this->admin)->post("/admin/users/{$baru->id}/activation-code")->assertForbidden();
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
        $this->assertStringContainsString('angkatan', $reasons[2]);
        $this->assertStringContainsString('shift', $reasons[3]);
        $this->assertStringContainsString('program studi', $reasons[5]);

        $page = $this->actingAs($this->admin)->get("/admin/users/import/{$token}");
        $page->assertOk()->assertSee('Sukses sebagian');

        $report = $this->actingAs($this->admin)->get("/admin/users/import/{$token}/laporan.csv")->getContent();
        $lines = array_values(array_filter(explode("\n", trim($report))));
        $this->assertCount(1 + 6 + 1, $lines);
        $this->assertStringContainsString('TOTAL', end($lines));
    }

    // ------------------------------------------------------------------ akses sebelum aktivasi

    public function test_akun_menunggu_aktivasi_hanya_bisa_ke_area_aktivasi_web_dan_mobile(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $dosenCode = $codes['DSN-IMP-01'];
        $mhsCode = $codes['2401500'];

        $web = $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => strtolower($dosenCode), 'role' => 'dosen']);
        $web->assertRedirect(route('activation.show'));
        $this->assertGuest();

        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => 'SALAH-KODE-1234', 'role' => 'dosen'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        $mobile = $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => $mhsCode]);
        $mobile->assertStatus(403)->assertJsonPath('code', 'ACTIVATION_REQUIRED')
            ->assertJsonPath('data.activation_url', route('activation.show'));
        $this->assertArrayNotHasKey('token', $mobile->json('data'));
        $this->assertNull(User::query()->where('nitk_nidn', '2401500')->value('mobile_api_token'));

        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => 'Salah#Sandi1'])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->postJson('/api/mobile/account-email', ['nitk_nidn' => '2401500', 'password' => $mhsCode, 'email' => 'x@example.test'])
            ->assertStatus(403)->assertJsonPath('code', 'ACTIVATION_REQUIRED');

        $this->startActivation('DSN-IMP-01', $dosenCode, 'Sandi#Dosen2026');
        $this->assertGuest();
        $this->get('/dosen/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));

        $mhs = User::query()->where('nitk_nidn', '2401500')->firstOrFail();
        $mhs->forceFill(['email' => 'mhs-imp@example.test'])->save();
        $this->postJson('/api/mobile/register/resume', ['email' => 'mhs-imp@example.test'])
            ->assertStatus(422)->assertJsonPath('code', 'ACTIVATION_REQUIRED');
        $this->postJson('/api/mobile/register/cancel', ['email' => 'mhs-imp@example.test'])->assertOk();
        $this->postJson('/api/mobile/forgot-password', ['email' => 'mhs-imp@example.test'])
            ->assertStatus(422)->assertJsonPath('code', 'ACTIVATION_REQUIRED');
        $this->assertTrue(User::query()->whereKey($mhs->id)->where('approval_status', AccountActivation::STATUS)->exists(), 'Alur registrasi tidak boleh menghapus/mengubah akun import.');

        $this->actingAs($this->admin)->post("/admin/users/{$mhs->id}/approve")->assertSessionHas('error');
        $this->assertSame(AccountActivation::STATUS, $mhs->fresh()->approval_status);
        $this->assertFalse($mhs->fresh()->is_active);
    }

    public function test_aktivasi_penuh_pemilik_isi_email_dan_hp_sendiri_otp_palsu(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $code = $codes['2401500'];

        $this->startActivation('2401500', $code, 'Sandi#Mhs2026')->assertRedirect(route('activation.show'));
        $this->get('/aktivasi')->assertOk()->assertSee('Lengkapi kontak Anda');

        $this->post('/aktivasi/profil', ['email' => 'mhs-baru@example.test', 'no_hp' => '0812'])->assertSessionHasErrors('no_hp');
        $this->post('/aktivasi/profil', ['email' => 'lama@example.test', 'no_hp' => '0812-3456-7890'])->assertSessionHasErrors('email');
        $this->post('/aktivasi/profil', ['email' => 'Mhs-Baru@Example.test', 'no_hp' => '0812-3456-7890'])->assertRedirect(route('activation.show'));

        Mail::assertSent(OtpMail::class, 1);
        $user = User::query()->where('nitk_nidn', '2401500')->firstOrFail();
        $this->assertSame('mhs-baru@example.test', $user->email);
        $this->assertSame('081234567890', $user->no_hp);
        $this->assertFalse($user->is_active);
        $this->assertSame(AccountActivation::STATUS, $user->approval_status, 'Mengisi profil tidak mengaktifkan akun.');

        $this->post('/aktivasi/otp', ['otp_code' => '000000'])->assertSessionHasErrors('otp_code');

        Carbon::setTestNow(now()->addMinutes(31));
        $this->post('/aktivasi/otp', ['otp_code' => $this->latestOtp('mhs-baru@example.test')])->assertSessionHasErrors('otp_code');
        $this->assertTrue(User::query()->whereKey($user->id)->exists(), 'OTP kedaluwarsa tidak menghapus akun.');

        $this->startResume('2401500', 'Sandi#Mhs2026')->assertRedirect(route('activation.show'));
        $this->post('/aktivasi/otp/kirim-ulang')->assertRedirect(route('activation.show'));
        Mail::assertSent(OtpMail::class, 2);

        $done = $this->post('/aktivasi/otp', ['otp_code' => $this->latestOtp('mhs-baru@example.test')]);
        $done->assertRedirect(route('activation.done'));
        $this->get('/aktivasi/selesai')->assertOk()->assertSee('Akun aktif')->assertSee('Kembali ke aplikasi');

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertSame('approved', $user->approval_status);
        $this->assertNull($user->approved_by, 'Tanpa persetujuan admin tambahan.');
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->finger_id, 'Finger ID dialokasikan saat aktivasi selesai.');
        $this->assertNull($user->finger_template);
        $this->assertTrue(AccountApprovalLog::query()->where('user_id', $user->id)->where('action', 'activation_completed')->exists());

        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => $code])->assertStatus(401);
        $login = $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => 'Sandi#Mhs2026']);
        $login->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($login->json('data.token'));
    }

    public function test_kode_sekali_pakai_kedaluwarsa_dan_regenerasi_membatalkan_kode_lama(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $dosen = User::query()->where('nitk_nidn', 'DSN-IMP-01')->firstOrFail();

        $page = $this->actingAs($this->admin)->post("/admin/users/{$dosen->id}/activation-code");
        $page->assertOk()->assertSee('Kode Aktivasi Baru');
        preg_match('/[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}/', $page->getContent(), $m);
        $newCode = $m[0];
        $this->assertNotSame($codes['DSN-IMP-01'], $newCode);
        $this->assertFalse(AccountApprovalLog::query()->where('note', 'like', '%'.$newCode.'%')->exists());
        auth()->logout();

        $this->startActivation('DSN-IMP-01', $codes['DSN-IMP-01'], 'Sandi#Dosen2026')->assertSessionHasErrors('kode_aktivasi');
        $this->assertNull(session()->getOldInput('kode_aktivasi'), 'Kode tidak tersimpan sebagai old input.');
        RateLimiter::clear('login-fail:'.hash('sha256', mb_strtolower('dsn-imp-01')));

        $this->startActivation('DSN-IMP-01', $newCode, 'Sandi#Dosen2026')->assertRedirect(route('activation.show'));
        $this->post('/aktivasi/keluar');
        $this->startActivation('DSN-IMP-01', $newCode, 'Sandi#Lain2026')->assertSessionHasErrors('kode_aktivasi');

        Carbon::setTestNow(now()->addDays(AccountActivation::codeTtlDays() + 1));
        $this->startActivation('2401500', $codes['2401500'], 'Sandi#Mhs2026')
            ->assertSessionHasErrors(['kode_aktivasi' => 'Kode aktivasi sudah kedaluwarsa. Minta admin laboran membuat kode baru.']);
        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2401500', 'password' => $codes['2401500']])
            ->assertStatus(403)->assertJsonPath('data.code_expired', true);
    }

    public function test_akun_dinonaktifkan_tidak_aktif_kembali_lewat_aktivasi_atau_kode_baru(): void
    {
        $codes = $this->importDosenDanMahasiswa();
        $this->activateFully('DSN-IMP-01', $codes['DSN-IMP-01'], 'Sandi#Dosen2026', 'dosen-imp@example.test');

        $dosen = User::query()->where('nitk_nidn', 'DSN-IMP-01')->firstOrFail();
        $this->assertTrue($dosen->is_active);
        $this->actingAs($this->admin)->post("/admin/users/{$dosen->id}/toggle-active");
        $this->assertFalse($dosen->fresh()->is_active);
        $hash = $dosen->fresh()->password;

        $this->actingAs($this->admin)->post("/admin/users/{$dosen->id}/activation-code")->assertSessionHas('error');
        $this->assertSame($hash, $dosen->fresh()->password, 'Kode baru bukan jalur reset password.');
        auth()->logout();

        $this->startResume('DSN-IMP-01', 'Sandi#Dosen2026')
            ->assertSessionHasErrors(['resume_password' => 'Akun ini dinonaktifkan admin. Aktivasi tidak tersedia; hubungi admin laboran.']);
        $this->assertFalse($dosen->fresh()->is_active);

        $this->post('/masuk', ['nitk_nidn' => 'DSN-IMP-01', 'password' => 'Sandi#Dosen2026', 'role' => 'dosen']);
        $this->assertGuest();
        $this->assertFalse($dosen->fresh()->is_active);
    }

    // ------------------------------------------------------------------ registrasi

    public function test_registrasi_ditutup_login_dan_aktivasi_tetap_berjalan_lalu_dibuka_lagi(): void
    {
        $codes = $this->importDosenDanMahasiswa();

        $jadwal = json_encode([DB::table('absensi_sesi_settings')->orderBy('id')->get(), DB::table('system_settings')->where('key', '!=', RegistrationGate::KEY)->orderBy('id')->get()]);

        $this->actingAs($this->dosenAktif)->post('/admin/settings/registrasi', [])->assertForbidden();
        $this->assertTrue(RegistrationGate::isOpen('mahasiswa'));

        $this->actingAs($this->admin)->post('/admin/settings/registrasi', [])->assertSessionHas('success');
        $this->assertFalse(RegistrationGate::isOpen('dosen'));
        $this->assertFalse(RegistrationGate::isOpen('mahasiswa'));
        $this->assertSame($jadwal, json_encode([DB::table('absensi_sesi_settings')->orderBy('id')->get(), DB::table('system_settings')->where('key', '!=', RegistrationGate::KEY)->orderBy('id')->get()]), 'Jadwal absensi tidak tersentuh.');
        auth()->logout();

        $users = User::query()->count();
        $this->get('/masuk')->assertOk()->assertSee('Pendaftaran ditutup');
        $this->post('/register', $this->dosenRegistration())->assertSessionHasErrors('registration');
        $this->postJson('/api/mobile/register', $this->mahasiswaRegistration())
            ->assertStatus(403)->assertJsonPath('code', 'REGISTRATION_CLOSED');
        $this->getJson('/api/mobile/config')->assertJsonPath('data.register.open', false);
        $this->assertSame($users, User::query()->count());

        $this->post('/masuk', ['nitk_nidn' => 'ADM-UJI-01', 'password' => 'Sandi#Uji2026', 'role' => 'laboran'])->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/logout');

        $this->postJson('/api/mobile/login', ['nitk_nidn' => '2399001', 'password' => 'Sandi#Uji2026'])->assertOk();
        $this->startActivation('2401500', $codes['2401500'], 'Sandi#Mhs2026')->assertRedirect(route('activation.show'));
        $this->get('/aktivasi')->assertOk()->assertSee('Lengkapi kontak Anda');

        $this->actingAs($this->admin)->post('/admin/settings/registrasi', ['dosen' => '1', 'mahasiswa' => '1']);
        auth()->logout();
        $this->assertTrue(RegistrationGate::isOpen('mahasiswa'));
        $this->getJson('/api/mobile/config')->assertJsonPath('data.register.open', true);
        $open = $this->postJson('/api/mobile/register', $this->mahasiswaRegistration());
        $this->assertNotSame('REGISTRATION_CLOSED', $open->json('code'));
        $open->assertStatus(201);
        $this->assertSame('pending', User::query()->where('nitk_nidn', '2409999')->value('approval_status'), 'Saat dibuka, aturan lama (pending + approval) berlaku.');
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

    /** @return array<string, string> nim => kode */
    private function pullCards(string $token): array
    {
        $response = $this->actingAs($this->admin)->post("/admin/users/import/{$token}/kartu");
        $response->assertOk();
        $lines = array_values(array_filter(explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $response->getContent())))));
        array_shift($lines);
        $codes = [];
        foreach ($lines as $line) {
            $cells = str_getcsv(trim($line), ',', '"', '');
            $codes[$cells[0]] = $cells[3];
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

    private function activateFully(string $id, string $code, string $password, string $email): void
    {
        $this->startActivation($id, $code, $password)->assertRedirect(route('activation.show'));
        $this->post('/aktivasi/profil', ['email' => $email, 'no_hp' => '+62 812 0000 1111']);
        $this->post('/aktivasi/otp', ['otp_code' => $this->latestOtp($email)])->assertRedirect(route('activation.done'));
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

        return AccountImportTemplate::zip($files);
    }
}
