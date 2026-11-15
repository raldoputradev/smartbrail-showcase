<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountEmailVerification;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengamanan autentikasi: enumerasi, injeksi, throttling, fiksasi sesi,
 * cookie remember, batas tebakan OTP, dan kedaluwarsa kunci admin.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = 'Tidak dapat masuk. Periksa data login Anda.';

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes keamanan auth dibatalkan: koneksi bukan sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        PermissionRegistry::seedDefaults();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Bus::fake();
        Http::fake();
        Http::preventStrayRequests();
        config(['services.recaptcha.site_key' => '', 'services.recaptcha.secret_key' => '']);
    }

    private function buatUser(string $role, string $nitk, array $extra = []): User
    {
        return User::query()->forceCreate(array_merge([
            'nama_lengkap' => 'Uji ' . $role,
            'nitk_nidn' => $nitk,
            'email' => strtolower($nitk) . '@example.test',
            'password' => 'Sandi#Uji2026',
            'role' => $role,
            'is_active' => true,
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ], $extra));
    }

    private function pesanLogin(array $payload): array
    {
        $response = $this->post('/masuk', $payload);
        $response->assertSessionHasErrors('nitk_nidn');
        $errors = session('errors')->getBag('default')->get('nitk_nidn');

        return $errors;
    }

    public function test_pesan_kegagalan_seragam_tidak_membocorkan_akun_atau_role(): void
    {
        $this->buatUser('mahasiswa', 'MHS-SEC1');
        $this->buatUser('laboran', 'LAB-SEC1', ['is_active' => false]);

        // Akun tidak ada, password salah, role salah, akun nonaktif → pesan sama.
        $cases = [
            ['nitk_nidn' => 'TIDAK-ADA', 'password' => 'apa-saja', 'role' => 'mahasiswa'],
            ['nitk_nidn' => 'MHS-SEC1', 'password' => 'salah-total', 'role' => 'mahasiswa'],
            ['nitk_nidn' => 'MHS-SEC1', 'password' => 'Sandi#Uji2026', 'role' => 'dosen'],
            ['nitk_nidn' => 'LAB-SEC1', 'password' => 'Sandi#Uji2026', 'role' => 'laboran'],
        ];

        $messages = [];
        foreach ($cases as $case) {
            $messages[] = $this->pesanLogin($case);
            $this->assertGuest();
        }

        // Semua harus persis sama — tidak ada petunjuk kolom mana yang gagal.
        foreach ($messages as $m) {
            $this->assertSame([self::GENERIC], $m);
        }
    }

    public function test_payload_sql_tidak_bypass_login_tidak_bocor_error_dan_tidak_mengubah_data(): void
    {
        $user = $this->buatUser('mahasiswa', 'MHS-SEC2');
        $jumlahSebelum = User::count();

        $payloads = [
            "' OR '1'='1",
            "' OR 1=1 --",
            "'; DROP TABLE users; --",
            '2400001\' UNION SELECT password FROM users --',
            "' OR nitk_nidn LIKE '%",
        ];

        foreach ($payloads as $injection) {
            foreach (['nitk_nidn', 'password'] as $field) {
                $response = $this->post('/masuk', [
                    'nitk_nidn' => $field === 'nitk_nidn' ? $injection : 'MHS-SEC2',
                    'password' => $field === 'password' ? $injection : 'Sandi#Uji2026',
                    'role' => 'mahasiswa',
                ]);

                $this->assertGuest();
                $response->assertDontSee('SQLSTATE', false);
                $response->assertDontSee('Illuminate\\Database', false);
            }
        }

        // Injeksi pada field role juga tidak boleh bypass.
        $this->post('/masuk', [
            'nitk_nidn' => 'MHS-SEC2',
            'password' => 'Sandi#Uji2026',
            'role' => "' OR '1'='1",
        ]);
        $this->assertGuest();

        $this->assertSame($jumlahSebelum, User::count());
        $this->assertSame('MHS-SEC2', $user->fresh()->nitk_nidn);
    }

    public function test_login_gagal_tidak_menerbitkan_sesi_recall_atau_token_dan_parameter_tersembunyi_diabaikan(): void
    {
        $this->buatUser('laboran', 'LAB-SEC3');

        // Parameter manipulatif tidak boleh mengubah hasil atau menaikkan hak.
        $res = $this->post('/masuk', [
            'nitk_nidn' => 'LAB-SEC3',
            'password' => 'salah',
            'role' => 'admin',
            'is_admin' => '1',
            'remember' => '1',
        ]);
        $res->assertCookieMissing('remember_until');
        $this->assertGuest();

        // Login sukses sebagai laboran biasa (dropdown hanya label; izin dari DB).
        $res = $this->post('/masuk', [
            'nitk_nidn' => 'LAB-SEC3',
            'password' => 'Sandi#Uji2026',
            'role' => 'laboran',
            'is_admin' => '1',
        ]);
        $res->assertRedirect('/admin/dashboard');
        $user = User::where('nitk_nidn', 'LAB-SEC3')->first();
        $this->assertFalse((bool) $user->is_admin);
        $this->assertSame('laboran', $user->role);
        // Laboran boleh membaca daftar akun (perm users.view) tetapi tidak
        // berubah jadi admin — mutasi akun tetap admin.only dan tertolak.
        $this->get('/admin/users')->assertOk();
        $this->post('/admin/users', ['nama_lengkap' => 'X', 'nitk_nidn' => 'SEC-X1', 'role' => 'mahasiswa'])
            ->assertForbidden();
    }

    public function test_sesi_diregenerasi_setelah_login_dan_diinvalidasi_saat_logout(): void
    {
        $this->buatUser('mahasiswa', 'MHS-SEC4');

        // Fiksasi: sesi tamu yang sudah ada tidak boleh dipakai ulang.
        $this->get('/masuk');
        $oldSessionId = $this->app['session']->getId();

        $this->post('/masuk', [
            'nitk_nidn' => 'MHS-SEC4',
            'password' => 'Sandi#Uji2026',
            'role' => 'mahasiswa',
        ])->assertRedirect();

        $newSessionId = $this->app['session']->getId();
        $this->assertNotSame($oldSessionId, $newSessionId);
        $this->assertAuthenticated();

        // Logout → tamu lagi, dan URL terproteksi menolak.
        $this->post('/logout');
        $this->assertGuest();
        $this->get('/mahasiswa/beranda')->assertRedirect(route('login'));
    }

    public function test_throttle_berlaku_per_id_termasuk_percobaan_berganti_role(): void
    {
        $this->buatUser('mahasiswa', 'MHS-SEC5');

        // 10 percobaan berganti role tetap terhitung pada ID yang sama.
        $roles = ['mahasiswa', 'dosen', 'laboran', 'admin'];
        for ($i = 0; $i < 10; $i++) {
            $this->post('/masuk', [
                'nitk_nidn' => 'MHS-SEC5',
                'password' => 'salah-' . $i,
                'role' => $roles[$i % 4],
            ]);
        }

        $errors = $this->pesanLogin([
            'nitk_nidn' => 'MHS-SEC5',
            'password' => 'Sandi#Uji2026',
            'role' => 'mahasiswa',
        ]);
        $this->assertStringContainsString('percobaan', implode(' ', $errors));
    }

    public function test_flag_admin_tidak_memblokir_tidak_bocor_dan_kedaluwarsa_24_jam(): void
    {
        $service = app(\App\Support\LoginSecurityService::class);

        // Flag baru (<24 jam): bukan pemblokir — pesan tetap generik, password benar tetap masuk.
        $flagged = $this->buatUser('mahasiswa', 'MHS-SEC6', [
            'login_requires_admin_unlock' => true,
            'login_suspicious_at' => now()->subHour(),
        ]);
        $this->assertNull($service->accountBlockedMessage($flagged));

        $errors = $this->pesanLogin([
            'nitk_nidn' => 'MHS-SEC6',
            'password' => 'salah',
            'role' => 'mahasiswa',
        ]);
        $this->assertSame([self::GENERIC], $errors);

        $this->post('/masuk', [
            'nitk_nidn' => 'MHS-SEC6',
            'password' => 'Sandi#Uji2026',
            'role' => 'mahasiswa',
        ])->assertRedirect();
        $this->assertAuthenticated();
        $this->post('/logout');

        // Flag lama (>24 jam) dibersihkan otomatis saat dicek.
        $stale = $this->buatUser('mahasiswa', 'MHS-SEC6B', [
            'login_requires_admin_unlock' => true,
            'login_suspicious_at' => now()->subHours(25),
        ]);
        $service->accountBlockedMessage($stale);
        $this->assertFalse((bool) $stale->fresh()->login_requires_admin_unlock);
    }

    public function test_otp_reset_password_dibatasi_dan_token_dibatalkan_setelah_banyak_tebakan(): void
    {
        $user = $this->buatUser('mahasiswa', 'MHS-SEC7');

        DB::table('email_verification_tokens')->insert([
            'email' => strtolower((string) $user->email),
            'token' => '',
            'otp_code' => '123456',
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(10),
            'verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $email = strtolower((string) $user->email);

        // Ganti IP di tengah serangan tidak melewati batas — kunci diikat ke token, bukan IP.
        for ($i = 0; $i < 8; $i++) {
            $ip = '10.0.0.' . ($i + 1);
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->withSession(['password_reset_flow_email' => $email])
                ->post('/verify-otp', ['email' => $email, 'otp_code' => '00000' . $i]);
        }

        // Tebakan berikutnya → token INI dihapus, tidak ada lagi kesempatan menebak.
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->withSession(['password_reset_flow_email' => $email])
            ->post('/verify-otp', ['email' => $email, 'otp_code' => '123456'])
            ->assertSessionHasErrors('otp_code');

        $this->assertSame(0, DB::table('email_verification_tokens')
            ->where('email', strtolower((string) $user->email))
            ->where('type', 'password_reset')
            ->count());
    }

    public function test_token_otp_baru_tidak_ikut_terhapus_oleh_percobaan_pada_token_lama(): void
    {
        $user = $this->buatUser('mahasiswa', 'MHS-SEC7B');
        $email = strtolower((string) $user->email);

        $buatToken = fn (string $kode) => DB::table('email_verification_tokens')->insertGetId([
            'email' => $email, 'token' => '', 'otp_code' => $kode, 'type' => 'password_reset',
            'expires_at' => now()->addMinutes(10), 'verified' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $tokenLama = $buatToken('111111');
        for ($i = 0; $i < 8; $i++) {
            $this->withSession(['password_reset_flow_email' => $email])
                ->post('/verify-otp', ['email' => $email, 'otp_code' => '00000' . $i]);
        }
        $this->withSession(['password_reset_flow_email' => $email])
            ->post('/verify-otp', ['email' => $email, 'otp_code' => '999999']);
        $this->assertNull(DB::table('email_verification_tokens')->find($tokenLama));

        // Korban minta kode baru — token baru utuh meski token lama diserang.
        $tokenBaru = $buatToken('222222');
        $this->withSession(['password_reset_flow_email' => $email])
            ->post('/verify-otp', ['email' => $email, 'otp_code' => '222222'])
            ->assertSessionHas('success');

        $this->assertTrue((bool) DB::table('email_verification_tokens')
            ->where('id', $tokenBaru)->where('verified', true)->exists());
    }

    public function test_resend_otp_tidak_membocorkan_keberadaan_akun(): void
    {
        $this->buatUser('mahasiswa', 'MHS-SEC8');

        // Email terdaftar maupun tidak → respons, pesan, dan panel yang sama.
        $res1 = $this->post('/resend-otp', ['email' => 'tidakada@example.test']);
        $res2 = $this->post('/resend-otp', ['email' => 'mhs-sec8@example.test']);

        foreach ([$res1, $res2] as $res) {
            $res->assertSessionHasNoErrors();
            $res->assertSessionHas('success', 'Jika email Anda terdaftar dan terverifikasi, kode OTP akan dikirim ke email tersebut.')
                ->assertSessionHas('otp_sent', true);
        }
    }

    public function test_empat_role_url_langsung_dan_admin_only_tetap_ditegakkan(): void
    {
        $admin = $this->buatUser('admin', 'ADM-SEC9');
        $laboran = $this->buatUser('laboran', 'LAB-SEC9');
        $dosen = $this->buatUser('dosen', 'DSN-SEC9');
        $mahasiswa = $this->buatUser('mahasiswa', 'MHS-SEC9');

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        // Laboran punya perm users.view → boleh membaca, tetapi mutasi tetap admin.only.
        $this->actingAs($laboran)->get('/admin/users')->assertOk();
        $this->actingAs($laboran)->post('/admin/users', ['nama_lengkap' => 'X', 'nitk_nidn' => 'SEC-X2', 'role' => 'mahasiswa'])
            ->assertForbidden();
        $this->actingAs($dosen)->get('/admin/users')->assertStatus(403);
        $this->actingAs($mahasiswa)->get('/admin/users')->assertStatus(403);

        $this->actingAs($dosen)->get('/dosen/dashboard')->assertOk();
        $this->actingAs($mahasiswa)->get('/dosen/dashboard')->assertStatus(403);
    }

    public function test_halaman_data_akun_per_role_hanya_untuk_admin(): void
    {
        $admin = $this->buatUser('admin', 'ADM-SEC10');
        $laboran = $this->buatUser('laboran', 'LAB-SEC10');
        $dosen = $this->buatUser('dosen', 'DSN-SEC10');
        $mahasiswa = $this->buatUser('mahasiswa', 'MHS-SEC10');
        $this->buatUser('mahasiswa', 'MHS-SEC10B');

        foreach (['mahasiswa', 'dosen', 'laboran'] as $role) {
            $props = $this->inertiaProps(
                $this->actingAs($admin)->get("/admin/users/{$role}")->assertOk()->getContent(),
                'Users'
            );
            $this->assertSame($role, $props['role_page']);

            // Laboran users.view → halaman per-role boleh dibaca; mutasi tetap admin.only.
            $this->actingAs($laboran)->get("/admin/users/{$role}")->assertOk();
            $this->actingAs($dosen)->get("/admin/users/{$role}")->assertStatus(403);
            $this->actingAs($mahasiswa)->get("/admin/users/{$role}")->assertStatus(403);
        }

        // Role tidak dikenal → bukan halaman valid (404/405 dari whereIn + {user} verbs).
        $this->assertContains(
            $this->actingAs($admin)->get('/admin/users/superuser')->status(),
            [404, 405]
        );

        // Halaman mahasiswa hanya menampilkan akun mahasiswa.
        $page = $this->actingAs($admin)->get('/admin/users/mahasiswa');
        $props = $this->inertiaProps($page->assertOk()->getContent(), 'Users');
        $rows = collect($props['users']['data'] ?? []);
        $this->assertTrue($rows->contains('nitk_nidn', 'MHS-SEC10B'));
        $this->assertTrue($rows->every(fn ($u) => $u['role'] === 'mahasiswa'));
        $this->assertFalse($rows->contains('email', 'dsn-sec10@example.test'));
        $this->assertFalse($rows->contains('email', 'lab-sec10@example.test'));
    }

    /** Ekstrak props Inertia dari payload data-page dan pastikan komponen cocok. */
    private function inertiaProps(string $html, string $component): array
    {
        $this->assertSame(
            1,
            preg_match('#<script data-page="app"[^>]*>(.*?)</script>#s', $html, $m),
            'Halaman tidak memuat payload Inertia'
        );
        $page = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($component, $page['component'] ?? null);

        return $page['props'] ?? [];
    }
}
