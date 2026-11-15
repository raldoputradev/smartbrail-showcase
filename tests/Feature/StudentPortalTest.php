<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\AccountApprovalLog;
use App\Models\Announcement;
use App\Models\LogAbsensi;
use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\User;
use App\Support\AccountActivation;
use App\Support\PermissionRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $dosen;
    private User $student;
    private User $other;
    private PblProject $project;
    private PblProject $otherProject;
    private Announcement $announcement;

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes portal dibatalkan: koneksi bukan sqlite :memory:.');
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

        Carbon::setTestNow(Carbon::parse('2026-10-24 09:00:00', 'Asia/Jakarta'));
        PermissionRegistry::seedDefaults();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Bus::fake();
        Http::fake();
        Http::preventStrayRequests();
        config([
            'services.gemini.api_key' => '',
            'services.recaptcha.site_key' => '',
            'services.recaptcha.secret_key' => '',
            'mobile.fcm.server_key' => null,
            'mobile.fcm.service_account_path' => storage_path('app/testing-no-fcm.json'),
            'simalas.activation_email_dns_check' => false,
        ]);

        AbsensiSesiSetting::query()->updateOrCreate([
            'shift' => 'pagi',
        ], [
            'jam_masuk' => '07:50:00', 'jam_pulang' => '17:00:00',
            'toleransi_masuk_menit' => 15, 'grace_alfa_menit' => 60, 'aktif' => true,
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->admin = $this->makeUser('ADM-PORTAL', 'laboran', 'Admin Portal', ['is_admin' => true]);
        $this->dosen = $this->makeUser('DSN-PORTAL', 'dosen', 'Dosen Portal');
        $this->student = $this->makeUser('2400001', 'mahasiswa', 'Mahasiswa Portal', ['finger_id' => 1, 'kelas' => 'Robotika A', 'tim' => 'PBL-A']);
        $this->other = $this->makeUser('2400002', 'mahasiswa', 'Mahasiswa Lain', ['finger_id' => 2, 'kelas' => 'Robotika B', 'tim' => 'PBL-B']);

        $this->project = PblProject::query()->create([
            'kode' => 'PBL-A', 'nama' => 'Proyek Portal A', 'shift_sesi' => 'pagi',
            'manpro_user_id' => $this->dosen->id, 'aktif' => true,
        ]);
        $this->otherProject = PblProject::query()->create([
            'kode' => 'PBL-B', 'nama' => 'Proyek Portal B', 'shift_sesi' => 'malam',
            'manpro_user_id' => $this->dosen->id, 'aktif' => true,
        ]);
        PblTeamMember::query()->create([
            'pbl_project_id' => $this->project->id, 'user_id' => $this->student->id,
            'role' => PblTeamMember::ROLE_KETUA, 'assigned_by' => $this->admin->id,
        ]);
        PblTeamMember::query()->create([
            'pbl_project_id' => $this->otherProject->id, 'user_id' => $this->other->id,
            'role' => PblTeamMember::ROLE_ANGGOTA, 'assigned_by' => $this->admin->id,
        ]);

        $this->createAttendance($this->student, 'lengkap', '07:55:00', '17:05:00');
        $this->createAttendance($this->other, 'alfa', null, null);

        $this->announcement = Announcement::query()->create([
            'judul' => 'Pengumuman portal bersama', 'isi' => 'Isi pengumuman untuk semua.',
            'target' => 'semua', 'channel' => Announcement::CHANNEL_PENGUMUMAN,
            'prioritas' => 'normal', 'dibuat_oleh' => $this->admin->id, 'is_active' => true,
        ]);
        Announcement::query()->create([
            'judul' => 'Pengumuman khusus mahasiswa lain', 'isi' => 'Tidak boleh tampil ke mahasiswa pertama.',
            'target' => 'mahasiswa', 'target_tim' => 'PBL-B', 'channel' => Announcement::CHANNEL_PENGUMUMAN,
            'prioritas' => 'normal', 'dibuat_oleh' => $this->admin->id, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_login_gabungan_dan_url_lama_mahasiswa_serta_ketidaksesuaian_role(): void
    {
        $oldLogin = $this->get('/mahasiswa/masuk');
        $oldLogin->assertRedirect(route('login', ['role' => 'mahasiswa']));

        $page = $this->get('/masuk');
        $page->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login', false)
                ->where('selectedRole', 'mahasiswa')
                ->where('panel', null)
            )
            ->assertDontSee('Buat Akun Dosen');

        $this->post('/masuk', [
            'nitk_nidn' => '2400001', 'password' => 'Sandi#Portal2026', 'role' => 'dosen',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        $this->post('/mahasiswa/masuk', [
            'nitk_nidn' => 'ADM-PORTAL', 'password' => 'Sandi#Portal2026',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        $this->post('/masuk', [
            'nitk_nidn' => '2400001', 'password' => 'Sandi#Portal2026', 'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));
        $this->assertAuthenticatedAs($this->student);

        $this->post('/mahasiswa/keluar')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/mahasiswa/masuk', [
            'nitk_nidn' => '2400001', 'password' => 'Sandi#Portal2026',
        ])->assertRedirect(route('mahasiswa.beranda'));
        $this->assertAuthenticatedAs($this->student);
        $this->post('/logout')->assertRedirect(route('login'));

        $this->post('/masuk', [
            'nitk_nidn' => 'DSN-PORTAL', 'password' => 'Sandi#Portal2026', 'role' => 'dosen',
        ])->assertRedirect('/dosen/dashboard');
        $this->assertAuthenticatedAs($this->dosen);
        $this->post('/logout');

        $this->post('/masuk', [
            'nitk_nidn' => 'ADM-PORTAL', 'password' => 'Sandi#Portal2026', 'role' => 'laboran',
        ])->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/logout');

        // Pemisahan 5 role: pilihan dropdown harus sama persis dengan role akun.
        // Akun laboran (ADM-PORTAL role 'laboran' + is_admin legacy) tidak bisa
        // menyamar lewat opsi "Admin"; nilai gabungan lama tidak lagi diterima.
        $this->post('/masuk', [
            'nitk_nidn' => 'ADM-PORTAL', 'password' => 'Sandi#Portal2026', 'role' => 'admin',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        $this->post('/masuk', [
            'nitk_nidn' => 'DSN-PORTAL', 'password' => 'Sandi#Portal2026', 'role' => 'admin',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        // "cs" bukan role login — petugas CS adalah identitas non-portal
        // (data petugas disinkron ke mesin, bukan akun web).
        $this->post('/masuk', [
            'nitk_nidn' => 'ADM-PORTAL', 'password' => 'Sandi#Portal2026', 'role' => 'cs',
        ])->assertSessionHasErrors('role');
        $this->assertGuest();

        // Nilai gabungan lama "admin_laboran" tidak lolos validasi.
        $this->post('/masuk', [
            'nitk_nidn' => 'ADM-PORTAL', 'password' => 'Sandi#Portal2026', 'role' => 'admin_laboran',
        ])->assertSessionHasErrors('role');
        $this->assertGuest();

        $this->postJson('/masuk', [
            'nitk_nidn' => '2400001', 'password' => 'Sandi#Portal2026', 'role' => 'mahasiswa',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', route('mahasiswa.beranda'));
        $this->post('/logout');
    }

    public function test_pemulihan_password_via_tautan_email_sekali_pakai(): void
    {
        $email = $this->student->email;
        \Illuminate\Support\Facades\Mail::fake();

        // Respons generik: tidak membocorkan ada/tidaknya akun.
        $res = $this->post('/forgot-password', ['identifier' => 'NIM-TIDAK-ADA-9999']);
        $res->assertRedirect(route('login'))->assertSessionHas('reset_link_sent', true);

        $res2 = $this->post('/forgot-password', ['identifier' => $this->student->nitk_nidn]);
        $res2->assertRedirect(route('login'))->assertSessionHas('reset_link_sent', true);

        $this->assertDatabaseMissing('email_verification_tokens', [
            'email' => 'nim-tidak-ada-9999',
            'type' => 'password_reset',
        ]);
        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => $email,
            'type' => 'password_reset',
        ]);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordResetLinkMail::class, fn ($m) => $m->hasTo($email));

        // Token plain diterbitkan lewat mekanisme yang sama.
        $plain = \App\Support\PasswordResetLink::issue($this->student->fresh());
        $this->get(route('password.reset.form', ['token' => $plain]))->assertOk()->assertSee('Atur Ulang Password', false);
        $this->get(route('password.reset.form', ['token' => 'token-salah-'.str_repeat('x', 50)]))
            ->assertOk()->assertSee('Tautan Tidak Berlaku', false);

        $this->post(route('password.reset.update'), [
            'token' => $plain,
            'password' => 'Sandi#Baru2026',
            'password_confirmation' => 'Sandi#Baru2026',
        ])->assertRedirect(route('password.reset.done'));

        // Token sekali pakai: resolve kedua gagal.
        $this->assertNull(\App\Support\PasswordResetLink::resolve($plain));
        // Password lama sudah tidak berlaku, sesi/token dicabut.
        $fresh = $this->student->fresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Sandi#Baru2026', $fresh->password));
        $this->assertNull($fresh->mobile_api_token);
        $this->assertFalse((bool) $fresh->must_change_password);
        $this->assertNotNull($fresh->email_verified_at);

        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Baru2026',
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_tetap_masuk_30_hari_menerbitkan_penanda_cookie_dan_logout_membersihkannya(): void
    {
        // remember=1 → recaller + penanda remember_until (batas 30 hari) terpasang.
        $res = $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
            'remember' => '1',
        ]);
        $res->assertRedirect(route('mahasiswa.beranda'));
        $res->assertCookie('remember_until');
        // Recaller Laravel juga terbit (nama cookie memakai nama guard).
        $this->assertNotNull($this->student->fresh()->remember_token);

        // Tanpa remember → tidak ada penanda.
        $this->post('/mahasiswa/keluar');
        // Bersihkan antrian cookie yang tersisa di container test (artefak test, bukan produksi).
        app('cookie')->unqueue('remember_until');
        $res2 = $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
        ]);
        $res2->assertCookieMissing('remember_until');
    }

    public function test_pengguna_tanpa_email_tetap_diarahkan_ke_laboran_tanpa_bocorkan_akun(): void
    {
        $this->student->forceFill(['email' => null, 'email_verified_at' => null])->save();
        $res = $this->post('/forgot-password', ['identifier' => $this->student->nitk_nidn]);
        $res->assertRedirect(route('login'))->assertSessionHas('reset_link_sent', true);
        $this->assertEquals(0, DB::table('email_verification_tokens')
            ->where('type', 'password_reset')->where('token', '!=', '')->count());
    }

    public function test_role_staff_dan_mahasiswa_tidak_dapat_saling_membuka_area(): void
    {
        $this->actingAs($this->student)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->student)->get('/dosen/dashboard')->assertForbidden();
        $this->actingAs($this->admin)->get('/mahasiswa/beranda')->assertForbidden();
        $this->actingAs($this->dosen)->get('/mahasiswa/beranda')->assertForbidden();
    }

    public function test_beranda_hanya_menampilkan_data_mahasiswa_sendiri_menyembunyikan_tim_pbl_dan_jujur_status_biometrik(): void
    {
        $response = $this->actingAs($this->student)->get('/mahasiswa/beranda');
        $response->assertOk()
            ->assertSee('Mahasiswa Portal')
            ->assertSee('Proyek Portal A')
            ->assertDontSee('Mahasiswa Lain')
            ->assertDontSee('Proyek Portal B')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard', false)
                ->where('auth.user.finger_id', 1)
                ->where('auth.user.has_fingerprint', false)
            );
    }

    public function test_beranda_kalender_dan_panduan_memakai_kebijakan_aktif_dan_terbatas_pada_mahasiswa(): void
    {
        // Kebijakan default (Hanya Kamis): Rabu bukan hari wajib, dan Beranda menyebut sumbernya.
        $this->actingAs($this->student)->get('/mahasiswa/beranda?periode=30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard', false)
                ->has('periodSummary')
                ->where('periodSummary.days', '30')
                ->has('todayState.judul')
                ->has('scheduleMeta')
            );

        $month = $this->actingAs($this->student)->getJson('/mahasiswa/kalender/bulan?bulan=2026-11');
        $month->assertOk()->assertJsonPath('success', true)->assertJsonPath('calendar.month_value', '2026-11');
        $days = collect($month->json('calendar.days'))->keyBy('tanggal');
        $this->assertFalse($days['2026-11-04']['is_wajib']); // Rabu
        $this->assertTrue($days['2026-11-05']['is_wajib']);  // Kamis

        $this->actingAs($this->student)->getJson('/mahasiswa/kalender/bulan?bulan=2026-13')->assertStatus(422);
        $this->actingAs($this->admin)->getJson('/mahasiswa/kalender/bulan?bulan=2026-11')->assertForbidden();

        $this->actingAs($this->student)->get('/mahasiswa/kalender')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Calendar', false)
                ->has('calendar.days')
                ->has('scheduleMeta')
            );
        $this->actingAs($this->student)->get('/mahasiswa/panduan')->assertOk()->assertSee('Arti status absensi');
    }

    public function test_absensi_dan_filter_hanya_memakai_data_mahasiswa_sendiri(): void
    {
        $this->actingAs($this->student)->get('/mahasiswa/absensi')
            ->assertOk()->assertSee('Lengkap')->assertDontSee('student-status--alfa');

        $this->actingAs($this->student)->get('/mahasiswa/absensi?status=alfa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Attendance', false)
                ->has('rows', 0)
            );
    }

    public function test_profil_dan_tim_tetap_dibatasi_pemiliknya(): void
    {
        $this->actingAs($this->student)->get('/mahasiswa/profil')->assertOk()->assertSee('Mahasiswa Portal');
        $this->actingAs($this->student)->get('/profiles/'.$this->other->id)->assertForbidden();
        $this->actingAs($this->student)->get('/mahasiswa/tim')
            ->assertOk()->assertSee('Proyek Portal A')
            ->assertDontSee('Proyek Portal B')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Team', false)
                ->where('team.project.nama', 'Proyek Portal A')
                ->where('team.role', 'ketua')
            );
    }

    public function test_notifikasi_audience_dan_read_state_tidak_berpindah_pemilik(): void
    {
        $this->actingAs($this->student)->get('/mahasiswa/notifikasi')
            ->assertOk()->assertSee('Pengumuman portal bersama')
            ->assertDontSee('Pengumuman khusus mahasiswa lain');

        $this->actingAs($this->student)->post('/mahasiswa/notifikasi/'.$this->announcement->id.'/baca')
            ->assertSessionHas('success');
        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $this->announcement->id,
            'user_id' => $this->student->id,
        ]);
        $this->assertDatabaseMissing('announcement_reads', [
            'announcement_id' => $this->announcement->id,
            'user_id' => $this->other->id,
        ]);
    }

    public function test_rekap_dan_ekspor_staf_mengikuti_filter_dan_metadata_yang_sama(): void
    {
        $snapshot = $this->actingAs($this->admin)->getJson('/admin/attendance/snapshot?kelas=Robotika+A&tab=all');
        $snapshot->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('rows.0.nama', 'Mahasiswa Portal')
            ->assertJsonStructure(['generated_at', 'summary', 'rows']);
        $this->assertStringNotContainsString('Mahasiswa Lain', $snapshot->getContent());

        $export = $this->actingAs($this->admin)->get('/admin/attendance/export?kelas=Robotika+A');
        $export->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString('Mahasiswa Portal', $content);
        $this->assertStringContainsString('Tanggal', $content);
        $this->assertStringContainsString('Diekspor:', $content);
        $this->assertStringContainsString('24/10/2026', $content);
        $this->assertStringNotContainsString('Mahasiswa Lain', $content);

        $this->actingAs($this->student)->get('/admin/attendance/export')->assertForbidden();
    }

    public function test_saran_memiliki_referensi_status_dan_balasan_hanya_untuk_pemilik(): void
    {
        $milikOrang = \App\Models\Suggestion::query()->create([
            'user_id' => $this->other->id, 'source' => 'mahasiswa',
            'nama' => $this->other->nama_lengkap, 'email' => $this->other->email,
            'subjek' => 'Saran mahasiswa lain yang dirahasiakan', 'pesan' => 'Tidak boleh terlihat.',
            'status' => 'baru',
        ]);

        $this->actingAs($this->student)->post('/mahasiswa/saran', [
            'subjek' => 'Perlu panduan absensi',
            'pesan' => 'Mohon panduan penggunaan mesin fingerprint.',
        ])->assertRedirect(route('mahasiswa.suggestions'));

        $suggestion = \App\Models\Suggestion::query()->where('user_id', $this->student->id)->firstOrFail();
        $this->actingAs($this->student)->get('/mahasiswa/saran')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/ReportHistory', false)
                ->has('items.data', 1)
                ->where('items.total', 1)
                ->where('items.data.0.id', $suggestion->id)
                ->where('items.data.0.judul', 'Perlu panduan absensi')
                ->where('items.data.0.ref', '#'.str_pad((string) $suggestion->id, 4, '0', STR_PAD_LEFT))
                ->where('stats.total', 1));

        // Akses detail lintas pemilik lewat ?selected= harus ditolak (selected → null).
        $this->actingAs($this->student)->get('/mahasiswa/saran?selected='.$milikOrang->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selected.id', $suggestion->id));

        // Filter status/kategori/pencarian tetap membatasi pemilik.
        $this->actingAs($this->student)->get('/mahasiswa/saran?q=rahasia')
            ->assertInertia(fn ($page) => $page->where('items.total', 0));

        $suggestion->forceFill([
            'admin_reply' => 'Panduan akan diberikan petugas.',
            'admin_replied_at' => now(),
            'admin_replied_by' => $this->admin->id,
        ])->save();
        $this->actingAs($this->student)->get('/mahasiswa/saran')
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.admin_reply', 'Panduan akan diberikan petugas.'));
        $this->actingAs($this->student)->post('/mahasiswa/saran/'.$suggestion->id.'/baca')
            ->assertSessionHas('success');
        $this->assertNotNull($suggestion->fresh()->user_read_reply_at);
    }

    // ------------------------------------------------------------------ pengujian paket akun mahasiswa baru & transisi

    public function test_akun_lama_aktif_dengan_log_activation_code_issued_menggantung_tidak_dipaksa_ganti_password(): void
    {
        // Akun lama sudah aktif dan must_change_password = false, namun punya log activation_code_issued historis.
        AccountActivation::log(
            $this->student,
            $this->admin,
            null,
            'activation_code_issued',
            'Log historis menggantung.'
        );
        $this->assertFalse($this->student->fresh()->must_change_password);

        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));

        $this->get('/mahasiswa/beranda')->assertOk()->assertSee('Mahasiswa Portal');
        $this->post('/mahasiswa/keluar');

        // Mobile API juga tidak dikunci oleh log menggantung tersebut.
        $this->postJson('/api/mobile/login', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_transisi_mahasiswa_import_lama_dengan_kode_sementara_dan_password_pribadi_serta_penolakan_akun_ambigu(): void
    {
        // 1. Mahasiswa import lama (menunggu_aktivasi) yang masih memegang kode sementara (belum pernah set password pribadi).
        $legacyTemp = new User;
        $legacyTemp->forceFill([
            'nama_lengkap' => 'Mahasiswa Legacy Kode',
            'nitk_nidn' => '2408001',
            'email' => null,
            'password' => 'placeholder',
            'role' => 'mahasiswa',
            'is_active' => false,
            'approval_status' => AccountActivation::STATUS,
            'must_change_password' => false,
        ])->save();
        AccountActivation::log($legacyTemp, $this->admin, null, 'admin_created', 'Import akun mahasiswa.');
        $tempIssued = AccountActivation::issueCode($legacyTemp, $this->admin, null, 'Kode aktivasi import.');

        $this->post('/masuk', [
            'nitk_nidn' => '2408001',
            'password' => strtolower($tempIssued['code']),
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.password.change'));

        $legacyTemp->refresh();
        $this->assertTrue($legacyTemp->is_active);
        $this->assertSame('approved', $legacyTemp->approval_status);
        $this->assertTrue($legacyTemp->must_change_password);
        $this->assertNotNull($legacyTemp->finger_id);
        $this->post('/mahasiswa/keluar');

        // 2. Mahasiswa import lama (menunggu_aktivasi) yang SUDAH set password pribadi di /aktivasi/mulai tetapi belum selesai OTP email.
        $legacyPersonal = new User;
        $legacyPersonal->forceFill([
            'nama_lengkap' => 'Mahasiswa Legacy Pribadi',
            'nitk_nidn' => '2408002',
            'email' => 'legacy-unverified@example.test',
            'email_verified_at' => null,
            'password' => 'placeholder',
            'role' => 'mahasiswa',
            'is_active' => false,
            'approval_status' => AccountActivation::STATUS,
            'must_change_password' => false,
        ])->save();
        AccountActivation::log($legacyPersonal, $this->admin, null, 'admin_created', 'Import akun mahasiswa.');
        $oldIssued = AccountActivation::issueCode($legacyPersonal, $this->admin, null, 'Kode aktivasi import.');

        // Simulasikan mahasiswa sudah menjalankan tahap 1 (/aktivasi/mulai) 20 hari lalu (melebihi TTL 14 hari kode awal).
        Carbon::setTestNow(now()->addMinutes(5));
        $legacyPersonal->forceFill(['password' => 'Sandi-Pribadi-Lama#2026'])->save();
        AccountActivation::log(
            $legacyPersonal,
            $legacyPersonal,
            null,
            'activation_password_set',
            'Kode aktivasi diverifikasi dan password baru disimpan.'
        );
        Carbon::setTestNow(now()->addDays(20));

        // Login menggunakan kode sementara lama harus ditolak.
        $this->post('/masuk', [
            'nitk_nidn' => '2408002',
            'password' => $oldIssued['code'],
            'role' => 'mahasiswa',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        // Login menggunakan password pribadi yang sudah dibuat berhasil (tidak terblokir TTL kode awal) dan langsung masuk ke beranda tanpa wajib ganti password ulang!
        $this->post('/masuk', [
            'nitk_nidn' => '2408002',
            'password' => 'Sandi-Pribadi-Lama#2026',
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));

        $legacyPersonal->refresh();
        $this->assertTrue($legacyPersonal->is_active);
        $this->assertSame('approved', $legacyPersonal->approval_status);
        $this->assertFalse($legacyPersonal->must_change_password);
        $this->assertNotNull($legacyPersonal->finger_id);
        $this->post('/mahasiswa/keluar');

        // 3. Akun mahasiswa menunggu_aktivasi dengan bukti log ambigu (tanpa admin_created atau ada log rejected) TIDAK boleh diaktifkan otomatis.
        $ambiguous = new User;
        $ambiguous->forceFill([
            'nama_lengkap' => 'Mahasiswa Ambigu',
            'nitk_nidn' => '2408003',
            'email' => null,
            'password' => AccountActivation::normalizeCode('ABCD-2345-EFGH'),
            'role' => 'mahasiswa',
            'is_active' => false,
            'approval_status' => AccountActivation::STATUS,
        ])->save();
        // Tanpa log admin_created / activation_code_issued yang sah:
        $this->post('/masuk', [
            'nitk_nidn' => '2408003',
            'password' => 'ABCD-2345-EFGH',
            'role' => 'mahasiswa',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();
        $this->assertFalse($ambiguous->fresh()->is_active);
        $this->assertSame(AccountActivation::STATUS, $ambiguous->fresh()->approval_status);
    }

    public function test_reset_password_sementara_admin_mencabut_sesi_web_aktif_remember_token_dan_mobile_token(): void
    {
        // Mahasiswa login di mobile dan mendapat token aktif.
        $mobileLogin = $this->postJson('/api/mobile/login', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
        ]);
        $mobileLogin->assertOk();
        $mobileToken = (string) $mobileLogin->json('data.token');
        $this->assertNotEmpty($mobileToken);

        // Mahasiswa login di web dengan remember_token dan sesi web aktif (WebSessionGuard mencatat web_password_hash).
        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
            'remember' => '1',
        ])->assertRedirect(route('mahasiswa.beranda'));
        $this->get('/mahasiswa/beranda')->assertOk();

        $oldRememberToken = $this->student->fresh()->remember_token;
        $oldPasswordHash = $this->student->fresh()->password;
        $this->assertNotNull($oldRememberToken);

        // Admin melakukan reset password sementara pada akun mahasiswa tersebut.
        $issued = AccountActivation::issueTemporaryPasswordForMahasiswa($this->student->fresh(), $this->admin, null, 'Reset password sementara oleh admin.');
        $freshStudent = $this->student->fresh();

        $this->assertTrue($freshStudent->must_change_password);
        $this->assertNotSame($oldPasswordHash, $freshStudent->password);
        $this->assertNotSame($oldRememberToken, $freshStudent->remember_token);
        $this->assertNull($freshStudent->mobile_api_token);
        $this->assertNull($freshStudent->mobile_token_expires_at);

        // Sesi web lama mahasiswa yang masih membawa web_password_hash lama otomatis gugur pada request berikutnya!
        $this->get('/mahasiswa/beranda')
            ->assertRedirect(route('login', ['expired' => 1]))
            ->assertSessionHas('session_expired');
        $this->assertGuest();

        // Token mobile lama juga ditolak (401).
        $this->withHeader('Authorization', 'Bearer '.$mobileToken)
            ->getJson('/api/mobile/me')
            ->assertStatus(401);

        // Mahasiswa login kembali dengan password sementara baru -> diarahkan ke wajib ganti password.
        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => $issued['code'],
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.password.change'));
    }

    public function test_password_pribadi_dengan_tanda_hubung_diverifikasi_apa_adanya_tanpa_normalisasi_strip(): void
    {
        $this->student->forceFill([
            'password' => 'Sandi-Pribadi-2026',
            'must_change_password' => false,
        ])->save();

        // Input tanpa tanda hubung harus ditolak untuk password pribadi.
        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'SandiPribadi2026',
            'role' => 'mahasiswa',
        ])->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        $this->postJson('/api/mobile/login', [
            'nitk_nidn' => '2400001',
            'password' => 'SandiPribadi2026',
        ])->assertStatus(401);

        // Input dengan tanda hubung yang tepat berhasil.
        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi-Pribadi-2026',
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_pengingat_kontak_opsional_tidak_bocor_antar_mahasiswa_dan_pemulihan_password_wajib_email_terverifikasi(): void
    {
        // Kosongkan email kedua mahasiswa untuk menguji banner pengingat kontak.
        $this->student->forceFill(['email' => null, 'no_hp' => null, 'email_verified_at' => null])->save();
        $this->other->forceFill(['email' => null, 'no_hp' => null, 'email_verified_at' => null])->save();

        // Mahasiswa 1 login -> melihat pengingat kontak -> menutupnya untuk sesi ini.
        $this->post('/masuk', [
            'nitk_nidn' => '2400001',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));

        $this->get('/mahasiswa/beranda')->assertOk()->assertInertia(fn (Assert $page) => $page->where('showContactPrompt', true));
        $this->post('/mahasiswa/kontak/tunda')->assertRedirect();
        $this->get('/mahasiswa/beranda')->assertOk()->assertInertia(fn (Assert $page) => $page->where('showContactPrompt', false));

        // Mahasiswa 1 logout, lalu Mahasiswa 2 login di browser yang sama -> pengingat kontak tetap tampil untuk Mahasiswa 2!
        $this->post('/mahasiswa/keluar')->assertRedirect(route('login'));
        $this->post('/masuk', [
            'nitk_nidn' => '2400002',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
        ])->assertRedirect(route('mahasiswa.beranda'));
        $this->get('/mahasiswa/beranda')->assertOk()->assertInertia(fn (Assert $page) => $page->where('showContactPrompt', true));

        // Mahasiswa 2 mengisi email & nomor HP di halaman Profil, tetapi belum verifikasi OTP.
        $this->post('/mahasiswa/profil/kontak', [
            'email' => 'mhs2-kontak@example.test',
            'no_hp' => '081234567890',
        ])->assertRedirect(route('mahasiswa.profile'));

        Mail::assertSent(OtpMail::class, 1);
        $this->assertNull($this->other->fresh()->email_verified_at);

        // Web: lupa password mengirim tautan ke email terikat (tautan itu sendiri yang membuktikan
        // kepemilikan saat reset sukses). Mobile tetap menolak email yang belum terverifikasi.
        $this->post('/mahasiswa/keluar');
        $this->post('/forgot-password', ['identifier' => 'mhs2-kontak@example.test'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('reset_link_sent', true);
        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'mhs2-kontak@example.test',
            'type' => 'password_reset',
        ]);
        $this->postJson('/api/mobile/forgot-password', ['email' => 'mhs2-kontak@example.test'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');

        // Mahasiswa 2 login lagi dan memverifikasi OTP email di halaman Profil.
        $this->post('/masuk', [
            'nitk_nidn' => '2400002',
            'password' => 'Sandi#Portal2026',
            'role' => 'mahasiswa',
        ]);
        $otp = (string) DB::table('email_verification_tokens')
            ->where('email', 'mhs2-kontak@example.test')
            ->where('type', 'account_verify')
            ->orderByDesc('id')
            ->value('otp_code');
        $this->assertSame(6, strlen($otp));

        $this->post('/mahasiswa/profil/email/verifikasi', ['otp_code' => $otp])
            ->assertRedirect(route('mahasiswa.profile'));
        $this->assertNotNull($this->other->fresh()->email_verified_at);

        // Banner pengingat kontak otomatis hilang karena email & no_hp sudah lengkap dan terverifikasi.
        $this->get('/mahasiswa/beranda')->assertOk()->assertInertia(fn (Assert $page) => $page->where('showContactPrompt', false));

        // Sekarang lupa password mandiri mengirim tautan reset ke email terikat akun.
        $this->post('/mahasiswa/keluar');
        $this->post('/forgot-password', ['identifier' => 'mhs2-kontak@example.test'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('reset_link_sent', true);
    }

    public function test_jadwal_3_hari_rabu_kamis_berbeda_dari_jumat_dan_hari_tidak_wajib_tanpa_alfa(): void
    {
        $this->actingAs($this->admin)->post('/admin/absensi/kebijakan', [
            'mode' => 'jadwal_mingguan',
            'effective_from' => '2026-07-01',
            'jadwal_mingguan' => [
                1 => ['aktif' => 0, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30'],
                2 => ['aktif' => 0, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30'],
                3 => ['aktif' => 1, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30'],
                4 => ['aktif' => 1, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30'],
                5 => ['aktif' => 1, 'jam_masuk' => '07:50', 'jam_pulang' => '12:00', 'jam_mulai_tap_masuk' => '06:30'],
                6 => ['aktif' => 0, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30'],
                0 => ['aktif' => 0, 'jam_masuk' => '07:50', 'jam_pulang' => '17:00', 'jam_mulai_tap_masuk' => '06:30'],
            ],
        ])->assertRedirect();

        // Rabu (2026-07-08), Kamis (2026-07-09), Jumat (2026-07-10), Senin (2026-07-06)
        $rabu = \App\Support\AbsensiEffectiveSchedule::resolve('2026-07-08', 'pagi');
        $kamis = \App\Support\AbsensiEffectiveSchedule::resolve('2026-07-09', 'pagi');
        $jumat = \App\Support\AbsensiEffectiveSchedule::resolve('2026-07-10', 'pagi');
        $senin = \App\Support\AbsensiEffectiveSchedule::resolve('2026-07-06', 'pagi');

        $this->assertTrue($rabu['is_wajib']);
        $this->assertSame('07:50', $rabu['jam_masuk']);
        $this->assertSame('17:00', $rabu['jam_pulang']);

        $this->assertTrue($kamis['is_wajib']);
        $this->assertSame('07:50', $kamis['jam_masuk']);
        $this->assertSame('17:00', $kamis['jam_pulang']);

        $this->assertTrue($jumat['is_wajib']);
        $this->assertSame('07:50', $jumat['jam_masuk']);
        $this->assertSame('12:00', $jumat['jam_pulang']);

        $this->assertFalse($senin['is_wajib']);

        // Hari tidak wajib (Senin 2026-07-06) tidak boleh menghasilkan alfa
        $closer = app(\App\Support\AbsensiSessionCloser::class);
        $this->assertSame(0, $closer->generateAlfaForDate('2026-07-06', 'pagi'));

        // Riwayat sebelum effective_from (Rabu 2026-06-24 saat kebijakan lama masih 'kamis') tidak berubah menjadi wajib!
        $rabuSebelumEfektif = \App\Support\AbsensiEffectiveSchedule::resolve('2026-06-24', 'pagi');
        $this->assertFalse($rabuSebelumEfektif['is_wajib']);
        $this->assertSame('sebelum_tanggal_efektif', $rabuSebelumEfektif['sumber_aturan']);
    }

    public function test_tap_awal_terlambat_dan_pulang_cepat_tetap_tersimpan_serta_kelengkapan_terpisah_dari_ketepatan(): void
    {
        \App\Support\AbsensiKebijakan::savePolicy([
            'mode' => 'jadwal_mingguan',
            'effective_from' => '2026-07-01',
            'jadwal_mingguan' => \App\Support\AbsensiEffectiveSchedule::defaultWeeklyDays(),
        ]);

        $evaluator = app(\App\Support\AbsensiTapEvaluator::class);

        // 1. Rabu 2026-07-08 (Jadwal 07:50 - 17:00): Tap masuk terlambat (08:20) & tap pulang lebih awal / pulang cepat (14:00)
        $masukRabu = $evaluator->evaluate($this->student, 'MESIN-UJI', Carbon::parse('2026-07-08 08:20:00', 'Asia/Jakarta'));
        $this->assertTrue($masukRabu['ok']);
        $this->assertSame('masuk', $masukRabu['data']['jenis']);
        $this->assertSame(30, $masukRabu['data']['menit_terlambat']);

        $pulangRabu = $evaluator->evaluate($this->student, 'MESIN-UJI', Carbon::parse('2026-07-08 14:00:00', 'Asia/Jakarta'));
        $this->assertTrue($pulangRabu['ok']);
        $this->assertSame('pulang', $pulangRabu['data']['jenis']);
        $this->assertSame('lengkap', $pulangRabu['data']['status_harian']);
        $this->assertTrue($pulangRabu['data']['pulang_cepat']);
        $this->assertSame(180, $pulangRabu['data']['menit_pulang_cepat']);

        // 2. Jumat 2026-07-10 (Jadwal 07:50 - 12:00): Tap masuk lebih awal (07:20) & tap pulang melewati jam jadwal (12:40)
        $masukJumat = $evaluator->evaluate($this->student, 'MESIN-UJI', Carbon::parse('2026-07-10 07:20:00', 'Asia/Jakarta'));
        $this->assertTrue($masukJumat['ok']);
        $this->assertSame(0, $masukJumat['data']['menit_terlambat']);

        $pulangJumat = $evaluator->evaluate($this->student, 'MESIN-UJI', Carbon::parse('2026-07-10 12:40:00', 'Asia/Jakarta'));
        $this->assertTrue($pulangJumat['ok']);
        $this->assertFalse($pulangJumat['data']['pulang_cepat']);
        $this->assertTrue($pulangJumat['data']['pulang_lembur']);

        // Verifikasi tampilan Absensi Saya memisahkan Kelengkapan ("Lengkap") dengan Ketepatan Waktu ("Terlambat 30 menit", "Pulang lebih awal 180 menit", "Melewati jam jadwal") dan tidak memakai istilah "Lembur"
        $page = $this->actingAs($this->student)->get('/mahasiswa/absensi');
        $page->assertOk()
            ->assertSee('Lengkap')
            ->assertSee('Terlambat 30 menit')
            ->assertSee('Pulang lebih awal 180 menit')
            ->assertSee('Datang lebih awal')
            ->assertSee('Melewati jam jadwal')
            ->assertDontSee('Lembur');
    }

    public function test_pengecualian_libur_perubahan_jam_dan_agenda_ujian_informasi_tetap_wajib(): void
    {
        \App\Support\AbsensiKebijakan::savePolicy([
            'mode' => 'jadwal_mingguan',
            'effective_from' => '2026-07-01',
            'jadwal_mingguan' => \App\Support\AbsensiEffectiveSchedule::defaultWeeklyDays(),
        ]);

        // 1. Kamis 2026-11-12: Libur (batal_wajib) -> tidak wajib dan tidak menghasilkan alfa
        $this->actingAs($this->admin)->post('/admin/absensi/libur', [
            'tanggal' => '2026-11-12',
            'jenis' => 'libur',
            'dampak_absensi' => 'batal_wajib',
            'keterangan' => 'Libur Akademik',
        ])->assertRedirect();

        $liburResolved = \App\Support\AbsensiEffectiveSchedule::resolve('2026-11-12', 'pagi');
        $this->assertTrue($liburResolved['is_libur']);
        $this->assertFalse($liburResolved['is_wajib']);
        $this->assertSame(0, app(\App\Support\AbsensiSessionCloser::class)->generateAlfaForDate('2026-11-12', 'pagi'));

        // 2. Rabu 2026-11-18: Perubahan jam khusus (09:00 - 14:00)
        $this->actingAs($this->admin)->post('/admin/absensi/libur', [
            'tanggal' => '2026-11-18',
            'jenis' => 'perubahan_jam',
            'dampak_absensi' => 'ubah_jam',
            'jam_masuk_khusus' => '09:00',
            'jam_pulang_khusus' => '14:00',
            'keterangan' => 'Jadwal Khusus Rapat Lab',
        ])->assertRedirect();

        $ubahJamResolved = \App\Support\AbsensiEffectiveSchedule::resolve('2026-11-18', 'pagi');
        $this->assertTrue($ubahJamResolved['is_wajib']);
        $this->assertFalse($ubahJamResolved['is_libur']);
        $this->assertSame('09:00', $ubahJamResolved['jam_masuk']);
        $this->assertSame('14:00', $ubahJamResolved['jam_pulang']);

        // 3. Kamis 2026-11-19: Agenda Ujian / Informasi (tetap_wajib) -> TIDAK otomatis menjadi libur
        $this->actingAs($this->admin)->post('/admin/absensi/libur', [
            'tanggal' => '2026-11-19',
            'jenis' => 'ujian',
            'dampak_absensi' => 'tetap_wajib',
            'keterangan' => 'Evaluasi Proyek PBL',
        ])->assertRedirect();

        $ujianResolved = \App\Support\AbsensiEffectiveSchedule::resolve('2026-11-19', 'pagi');
        $this->assertTrue($ujianResolved['is_wajib']);
        $this->assertFalse($ujianResolved['is_libur']);
        $this->assertCount(1, $ujianResolved['agenda']);
        $this->assertGreaterThan(0, app(\App\Support\AbsensiSessionCloser::class)->generateAlfaForDate('2026-11-19', 'pagi'));
    }

    public function test_kalender_agenda_tandai_tanggal_multi_membuat_entri_terpisah(): void
    {
        // Mode tandai tanggal: beberapa tanggal → satu entri per tanggal, tanpa key "tanggal".
        $this->actingAs($this->admin)->post('/admin/kalender/agenda', [
            'tanggal_list' => ['2026-11-24', '2026-11-26', '2026-11-24'],
            'jenis' => 'agenda',
            'keterangan' => 'Workshop IoT',
        ])->assertRedirect();

        $rows = \App\Models\AbsensiKalenderLibur::query()
            ->where('keterangan', 'Workshop IoT')->orderBy('tanggal')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-11-24', $rows[0]->tanggal->toDateString());
        $this->assertSame('2026-11-26', $rows[1]->tanggal->toDateString());
        $this->assertSame('2026-11-26', $rows[1]->tanggal_selesai->toDateString());
        $this->assertSame('tetap_wajib', $rows[0]->effectiveDampak());

        // Terlihat di resolver kalender pengguna.
        $resolved = \App\Support\AbsensiEffectiveSchedule::resolve('2026-11-24', 'pagi');
        $this->assertContains('Workshop IoT', collect($resolved['agenda'])->pluck('keterangan'));

        // Validasi: tanpa tanggal maupun tanggal_list → error.
        $this->actingAs($this->admin)->post('/admin/kalender/agenda', [
            'jenis' => 'agenda', 'keterangan' => 'X',
        ])->assertSessionHasErrors('tanggal');
    }

    public function test_notifikasi_panel_ai_bantuan_ganti_password_saat_login_dan_fallback_foto_profil(): void
    {
        // 1. Foto profil yang file-nya tidak ada di disk harus fallback ke null agar inisial tampil bersih tanpa broken image
        $this->student->forceFill(['profile_photo_path' => 'uploads/avatars/file-tidak-ada-404.jpg'])->save();
        $this->assertNull($this->student->fresh()->profile_photo_url);

        // 2. Feed panel notifikasi tidak otomatis menandai semua pengumuman dibaca
        $feed = $this->actingAs($this->student)->getJson('/mahasiswa/notifikasi/feed?filter=all');
        $feed->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 1);
        $this->assertDatabaseMissing('announcement_reads', [
            'announcement_id' => $this->announcement->id,
            'user_id' => $this->student->id,
        ]);

        // 3. Chat AI bantuan mahasiswa bersifat informatif, komunikatif, dan menegaskan tidak mengirim laporan otomatis
        $aiRes = $this->actingAs($this->student)->postJson('/mahasiswa/bantuan/ai', [
            'message' => 'Saya mau lapor absensi kemarin salah status alfa',
        ]);
        $aiRes->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString('tidak mengirim laporan', (string) $aiRes->json('reply'));
        $this->assertStringNotContainsString('tab Kirim Laporan', (string) $aiRes->json('reply'));
        $this->assertSame(0, \App\Models\Suggestion::query()->where('user_id', $this->student->id)->count());

        // Tanya kemampuan chatbot ("kamu bisa apa saja?")
        $aiCapabilities = $this->actingAs($this->student)->postJson('/mahasiswa/bantuan/ai', [
            'message' => 'kamu bisa apa saja?',
        ]);
        $aiCapabilities->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString('asisten virtual', (string) $aiCapabilities->json('reply'));
        $this->assertStringNotContainsString('tab Kirim Laporan', (string) $aiCapabilities->json('reply'));

        // Tanya kendala absensi dengan typo ("bisa bantu masaalh absen?") -> otomatis sediakan suggest_report kartu konfirmasi
        $aiIssue = $this->actingAs($this->student)->postJson('/mahasiswa/bantuan/ai', [
            'message' => 'bisa bantu masaalh absen?',
        ]);
        $aiIssue->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('suggest_report', true)
            ->assertJsonStructure(['report_data' => ['kategori', 'subjek', 'pesan']]);
        $this->assertStringContainsString('membantu kendala absensi', (string) $aiIssue->json('reply'));

        // 4. Pengiriman laporan kendala absensi via JSON menghasilkan nomor referensi tiket (#ID)
        $reportRes = $this->actingAs($this->student)->postJson('/mahasiswa/saran', [
            'kategori' => 'absensi',
            'tanggal_absensi' => '2026-10-08',
            'referensi_absensi' => 'Hanya Masuk (07:55)',
            'subjek' => 'Sudah tap pulang di mesin utama',
            'pesan' => 'Saya sudah melakukan tap pulang pukul 17:02 namun status masih hanya masuk.',
        ]);
        $reportRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['ticket_id', 'ticket_ref', 'status', 'created_at']);

        // 5. Ganti password mandiri saat sudah login (must_change_password = false) wajib verifikasi current_password
        $this->actingAs($this->student)->get('/mahasiswa/ganti-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/ChangePassword', false)
                ->where('isMandatory', false)
            );

        $this->actingAs($this->student)->post('/mahasiswa/ganti-password', [
            'current_password' => 'Sandi-Salah#999',
            'password' => 'Sandi#MandiriBaru2026',
            'password_confirmation' => 'Sandi#MandiriBaru2026',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($this->student)->post('/mahasiswa/ganti-password', [
            'current_password' => 'Sandi#Portal2026',
            'password' => 'Sandi#MandiriBaru2026',
            'password_confirmation' => 'Sandi#MandiriBaru2026',
        ])->assertRedirect(route('mahasiswa.beranda'));

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Sandi#MandiriBaru2026', $this->student->fresh()->password));
    }

    private function makeUser(string $nitk, string $role, string $name, array $extra = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'nama_lengkap' => $name,
            'nitk_nidn' => $nitk,
            'email' => strtolower($nitk).'@example.test',
            'password' => 'Sandi#Portal2026',
            'role' => $role,
            'is_active' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'email_verified_at' => now(),
            'failed_login_attempts' => 0,
            'login_requires_admin_unlock' => false,
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ], $extra))->save();

        return $user;
    }

    private function createAttendance(User $user, string $status, ?string $masuk, ?string $pulang): void
    {
        $logMasuk = $masuk ? LogAbsensi::query()->create([
            'user_id' => $user->id, 'id_mesin' => 'MESIN-PORTAL', 'sesi' => 'pagi',
            'jenis' => 'masuk', 'waktu_tap' => '2026-10-08 '.$masuk,
        ]) : null;
        $logPulang = $pulang ? LogAbsensi::query()->create([
            'user_id' => $user->id, 'id_mesin' => 'MESIN-PORTAL', 'sesi' => 'pagi',
            'jenis' => 'pulang', 'waktu_tap' => '2026-10-08 '.$pulang,
        ]) : null;
        AbsensiHarian::query()->create([
            'user_id' => $user->id, 'tanggal' => '2026-10-08', 'sesi' => 'pagi', 'status' => $status,
            'masuk_log_id' => $logMasuk?->id, 'pulang_log_id' => $logPulang?->id,
            'lengkap_at' => $pulang ? '2026-10-08 '.$pulang : null,
        ]);
    }

    public function test_konteks_asisten_dan_pengajuan_ruangan_json_terisolasi(): void
    {
        $room = \App\Models\Ruangan::query()->create([
            'kode' => 'BR-01', 'nama' => 'Ruang Meeting BRAIL', 'aktif' => true,
        ]);

        // Pengajuan sukses via JSON (alur chatbot) — respons data nyata, bukan redirect.
        $res = $this->actingAs($this->student)->postJson('/mahasiswa/ruangan', [
            'ruangan_id' => $room->id,
            'tanggal' => '2026-10-26',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'tujuan' => 'Diskusi progres mingguan',
            'peserta' => [$this->student->id],
        ]);
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pengajuan.ruangan', 'Ruang Meeting BRAIL')
            ->assertJsonPath('pengajuan.status', 'diajukan');

        // Mahasiswa tanpa tim → 422 JSON dengan pesan jelas (bukan redirect error).
        $bebas = $this->makeUser('MHS-BEBAS', 'mahasiswa', 'Tanpa Tim');
        $this->actingAs($bebas)->postJson('/mahasiswa/ruangan', [
            'ruangan_id' => $room->id,
            'tanggal' => '2026-10-26',
            'jam_mulai' => '14:00',
            'jam_selesai' => '15:00',
            'tujuan' => 'Coba saja',
        ])->assertStatus(422)->assertJsonPath('success', false);

        // Pengajuan tim lain di jam berbeda (tidak bocor ke konteks mahasiswa pertama).
        $this->actingAs($this->other)->postJson('/mahasiswa/ruangan', [
            'ruangan_id' => $room->id,
            'tanggal' => '2026-10-26',
            'jam_mulai' => '14:00',
            'jam_selesai' => '15:00',
            'tujuan' => 'Rapat tim lain',
            'peserta' => [$this->other->id],
        ])->assertOk();

        \App\Models\Suggestion::query()->create([
            'user_id' => $this->student->id, 'source' => 'mahasiswa',
            'nama' => $this->student->nama_lengkap, 'subjek' => '[Kendala Absensi 2026-10-08] Tap pulang tidak tercatat',
            'pesan' => 'Uraian kendala milik mahasiswa pertama.', 'status' => 'baru',
        ]);
        \App\Models\Suggestion::query()->create([
            'user_id' => $this->other->id, 'source' => 'mahasiswa',
            'nama' => $this->other->nama_lengkap, 'subjek' => 'Laporan rahasia tim lain',
            'pesan' => 'Tidak boleh bocor ke konteks mahasiswa pertama.', 'status' => 'baru',
        ]);

        $ctx = $this->actingAs($this->student)->getJson('/mahasiswa/bantuan/konteks');
        $ctx->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.nama_pendek', 'Mahasiswa')
            ->assertJsonPath('tim.nama', 'Proyek Portal A')
            ->assertJsonStructure(['jadwal' => ['hari_nama', 'is_wajib'], 'ruangan' => [['id', 'nama', 'jam_buka', 'jam_tutup', 'durasi_opsi']]]);
        // Ruangan aktif tersedia untuk alur pengajuan.
        $this->assertSame('Ruang Meeting BRAIL', collect($ctx->json('ruangan'))->firstWhere('id', $room->id)['nama'] ?? null);
        // Pengajuan konteks hanya milik tim mahasiswa pertama (bukan tim lain).
        $ctx->assertJsonCount(1, 'pengajuan')
            ->assertJsonPath('pengajuan.0.ruangan', 'Ruang Meeting BRAIL');
        // Laporan konteks hanya milik mahasiswa pertama.
        $ctx->assertJsonCount(1, 'laporan')
            ->assertJsonPath('laporan.0.judul', 'Tap pulang tidak tercatat');
        // Anggota tim tidak memuat mahasiswa dari tim lain.
        $anggotaIds = collect($ctx->json('anggota'))->pluck('id')->all();
        $this->assertContains($this->student->id, $anggotaIds);
        $this->assertNotContains($this->other->id, $anggotaIds);
    }

    public function test_kontak_terverifikasi_atau_tersimpan_tidak_dapat_diubah_sendiri(): void
    {
        Mail::fake();

        $this->student->forceFill([
            'email' => 'terverifikasi@example.test',
            'email_verified_at' => now(),
            'no_hp' => '08120000111',
        ])->save();
        $this->actingAs($this->student);

        // Email terverifikasi tidak dapat diganti — nilai lama dipertahankan.
        $this->post('/mahasiswa/profil/kontak', [
            'email' => 'baru@example.test',
            'no_hp' => '08120000111',
        ])->assertSessionHasErrors('email');
        $this->assertSame('terverifikasi@example.test', $this->student->refresh()->email);

        // No. HP yang sudah tersimpan tidak dapat diganti maupun dikosongkan.
        $this->post('/mahasiswa/profil/kontak', [
            'email' => 'terverifikasi@example.test',
            'no_hp' => '08999999999',
        ])->assertSessionHasErrors('no_hp');
        $this->assertSame('08120000111', $this->student->refresh()->no_hp);

        // Email yang belum terverifikasi masih boleh diperbaiki (OTP dikirim ulang).
        $this->student->forceFill(['email_verified_at' => null])->save();
        $this->post('/mahasiswa/profil/kontak', [
            'email' => 'ganti@example.test',
            'no_hp' => '08120000111',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame('ganti@example.test', $this->student->refresh()->email);
        $this->assertNull($this->student->email_verified_at);
    }

    public function test_lima_role_login_redirect_dan_pembatasan_akses_route(): void
    {
        $realAdmin = $this->makeUser('ADM-REAL', 'admin', 'Admin Sistem');
        $laboran = $this->makeUser('LAB-0001', 'laboran', 'Laboran Satu');

        // Setiap role diarahkan ke portalnya masing-masing.
        $this->post('/masuk', ['nitk_nidn' => 'ADM-REAL', 'password' => 'Sandi#Portal2026', 'role' => 'admin'])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($realAdmin);
        $this->post('/logout');

        $this->post('/masuk', ['nitk_nidn' => 'LAB-0001', 'password' => 'Sandi#Portal2026', 'role' => 'laboran'])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($laboran);

        // Laboran boleh masuk area admin (role:laboran,admin) tetapi route
        // admin.only (manajemen user) tetap tertutup untuknya.
        $this->get('/admin/users')->assertForbidden();
        $this->post('/logout');

        // Admin murni tidak bisa masuk lewat opsi "Laboran" — pilihan harus persis.
        $this->post('/masuk', ['nitk_nidn' => 'ADM-REAL', 'password' => 'Sandi#Portal2026', 'role' => 'laboran'])
            ->assertSessionHasErrors('nitk_nidn');
        $this->assertGuest();

        // Lintas role di route langsung: mahasiswa & dosen tertolak dari area admin,
        // mahasiswa juga tertolak dari area dosen.
        $this->actingAs($this->student)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->student)->get('/dosen/dashboard')->assertForbidden();
        $this->actingAs($this->dosen)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($laboran)->get('/dosen/dashboard')->assertForbidden();
    }
}
