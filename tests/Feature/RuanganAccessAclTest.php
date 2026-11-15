<?php

namespace Tests\Feature;

use App\Models\MesinIot;
use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\Ruangan;
use App\Models\RuanganAccessEvent;
use App\Models\RuanganIzin;
use App\Models\RuanganPengajuan;
use App\Models\RuanganPetugas;
use App\Models\User;
use App\Support\PermissionRegistry;
use App\Support\RuanganBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Kontrak perangkat pintu: endpoint ACL untuk keputusan offline di ESP32,
 * alasan penolakan yang eksplisit, dan jaminan tidak ada secret hash bocor.
 */
class RuanganAccessAclTest extends TestCase
{
    use RefreshDatabase;

    private array $u = [];

    private Ruangan $room;

    private PblProject $timA;

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes akses hanya untuk sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));
        PermissionRegistry::clearCache();

        $this->timA = PblProject::create(['kode' => 'PBL-A', 'nama' => 'Tim Alfa', 'aktif' => true]);

        $this->u['laboran'] = $this->buatUser('Laboran Uji', 'LAB-01', 'laboran');
        $this->u['dosen'] = $this->buatUser('Dosen Uji', 'DSN-01', 'dosen');
        $this->u['admin'] = $this->buatUser('Admin Uji', 'ADM-01', 'admin');
        $this->u['ketua'] = $this->buatUser('Ketua Alfa', 'MHS-01', 'mahasiswa', 'TI-1A', 'Tim Alfa');

        $this->timA->update(['manpro_user_id' => $this->u['dosen']->id]);
        PblTeamMember::create(['pbl_project_id' => $this->timA->id, 'user_id' => $this->u['ketua']->id, 'role' => 'ketua']);

        $this->room = Ruangan::create(['kode' => 'RM-1', 'nama' => 'Ruang Meeting 1', 'aktif' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatUser(string $nama, string $nidn, string $role, ?string $kelas = null, ?string $tim = null): User
    {
        return User::query()->create([
            'nama_lengkap' => $nama,
            'nitk_nidn' => $nidn,
            'email' => strtolower($nidn).'@example.test',
            'password' => 'bukan-sandi',
            'role' => $role,
            'is_active' => 1,
            'approval_status' => 'approved',
            'kelas' => $kelas,
            'tim' => $tim,
            'shift' => $role === 'mahasiswa' ? 'pagi' : null,
        ]);
    }

    private function mesinRuangan(): MesinIot
    {
        return MesinIot::create([
            'id_mesin' => 'MESIN_RUANG_01', 'nama_mesin' => 'Door RM-1', 'tipe' => 'ruangan',
            'api_key' => Hash::make('kunci-ruang-uji'), 'is_active' => true, 'ruangan_id' => $this->room->id,
        ]);
    }

    private function aksesCheck(array $body, string $idMesin = 'MESIN_RUANG_01'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-API-KEY' => 'kunci-ruang-uji', 'X-ID-MESIN' => $idMesin])
            ->postJson('/api/mesin/ruangan/access/check', $body);
    }

    private function aclGet(string $idMesin = 'MESIN_RUANG_01'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-API-KEY' => 'kunci-ruang-uji', 'X-ID-MESIN' => $idMesin])
            ->getJson('/api/mesin/ruangan/access/acl');
    }

    private function buatIzinDisetujui(string $jamMulai = '09:00', string $jamSelesai = '10:30', string $tanggal = '2026-11-12'): RuanganPengajuan
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), [
            'ruangan_id' => $this->room->id,
            'tanggal' => $tanggal,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'tujuan' => 'Review progres',
            'peserta' => [$this->u['ketua']->id],
        ]);
        $p = RuanganPengajuan::latest('id')->firstOrFail();
        RuanganBookingService::setujui($p, $this->u['laboran']);

        return $p->fresh();
    }

    // ---------- Override staf & alasan eksplisit ----------

    public function test_staf_diizinkan_tanpa_booking_dengan_alasan_akses_staf(): void
    {
        $this->mesinRuangan();
        foreach (['admin' => 81, 'laboran' => 82, 'dosen' => 83] as $role => $finger) {
            $this->u[$role]->update(['finger_id' => $finger]);
            $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => (string) $finger])
                ->assertOk()
                ->assertJson(['diizinkan' => true, 'alasan' => 'akses_staf']);
        }
    }

    public function test_izin_belum_mulai_ditolak_dengan_alasan_benar(): void
    {
        // Regresi: izin status 'aktif' tetapi mulai di masa depan dulu menjawab
        // diizinkan:false + alasan 'izin_aktif' — kontradiktif bagi firmware.
        $this->mesinRuangan();
        $this->u['ketua']->update(['finger_id' => 84]);
        $this->buatIzinDisetujui();

        Carbon::setTestNow('2026-11-12 07:00:00'); // sebelum jam_mulai 09:00
        $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '84'])
            ->assertOk()
            ->assertJson(['diizinkan' => false, 'alasan' => 'izin_belum_mulai']);
    }

    public function test_kredensial_tidak_dikenal_ditolak_dan_tercatat_tolak(): void
    {
        $this->mesinRuangan();
        $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '999'])
            ->assertOk()
            ->assertJson(['diizinkan' => false, 'alasan' => 'identitas_tidak_dikenal']);

        $ev = RuanganAccessEvent::where('jenis', 'tolak')->latest('id')->firstOrFail();
        $this->assertSame($this->room->id, $ev->ruangan_id);
        $this->assertSame('identitas_tidak_dikenal', $ev->meta['alasan'] ?? null);
    }

    // ---------- Endpoint ACL ----------

    public function test_acl_butuh_autentikasi_dan_binding_ruangan(): void
    {
        $this->mesinRuangan();
        // Tanpa API key → 401; key ada tapi salah → 403; tanpa id mesin → 422.
        $this->getJson('/api/mesin/ruangan/access/acl')->assertStatus(401);
        $this->withHeaders(['X-API-KEY' => 'kunci-salah', 'X-ID-MESIN' => 'MESIN_RUANG_01'])
            ->getJson('/api/mesin/ruangan/access/acl')->assertStatus(403);
        // Header X-ID-MESIN kosong → 422 (id_mesin wajib).
        $this->withHeaders(['X-API-KEY' => 'kunci-ruang-uji', 'X-ID-MESIN' => ''])
            ->getJson('/api/mesin/ruangan/access/acl')->assertStatus(422);

        MesinIot::create([
            'id_mesin' => 'MESIN_BEBAS', 'nama_mesin' => 'X', 'tipe' => 'ruangan',
            'api_key' => Hash::make('kunci-ruang-uji'), 'is_active' => true,
        ]);
        $this->aclGet('MESIN_BEBAS')->assertStatus(403)->assertJson(['error' => 'mesin_tidak_terikat_ruangan']);
    }

    public function test_acl_berisi_staf_izin_dan_petugas_sesuai_kontrak(): void
    {
        $this->mesinRuangan();
        $this->u['admin']->update(['finger_id' => 90]);
        $this->u['ketua']->update(['finger_id' => 91]);
        $this->u['ketua']->rfid_uid = 'a1b2'; // kolom akses bukan fillable — assignment langsung
        $this->u['ketua']->save();
        $this->buatIzinDisetujui(); // 2026-11-12 09:00–10:30, PIN izin auto-dibuat

        RuanganPetugas::create([
            'nama' => 'CS Fingerprint', 'id_internal' => 'CS-F1', 'tipe_kredensial' => 'fingerprint',
            'kredensial_hash' => '55', 'status' => 'aktif',
            'berlaku_mulai' => '2026-11-01', 'berlaku_selesai' => '2026-12-31',
            'jadwal' => [['ruangan_id' => $this->room->id, 'hari' => [4], 'jam_mulai' => '06:00', 'jam_selesai' => '08:00']],
        ]);

        $entries = $this->aclGet()->assertOk()->json('entries');
        $this->assertNotEmpty($entries);

        // Staf: tanpa jendela waktu.
        $staf = collect($entries)->first(fn ($e) => $e['t'] === 'finger' && $e['v'] === '90');
        $this->assertSame('staf', $staf['why'] ?? null);
        $this->assertArrayNotHasKey('s', $staf);

        // Izin: finger + RFID (dinormalisasi kapital) + PIN per-izin, semuanya berjendela.
        $izin = collect($entries)->where('why', 'izin');
        $this->assertTrue($izin->contains(fn ($e) => $e['t'] === 'finger' && $e['v'] === '91' && isset($e['s'], $e['e'])));
        $this->assertTrue($izin->contains(fn ($e) => $e['t'] === 'rfid' && $e['v'] === 'A1B2'));
        $this->assertTrue($izin->contains(fn ($e) => $e['t'] === 'pin' && preg_match('/^\d+$/', $e['v'])));

        // Petugas fingerprint: rules jadwal untuk ruangan terikat.
        $cs = collect($entries)->first(fn ($e) => ($e['why'] ?? null) === 'petugas');
        $this->assertSame('finger', $cs['t']);
        $this->assertSame('55', $cs['v']);
        $this->assertSame('06:00', $cs['rules'][0]['jm']);
        $this->assertSame([4], $cs['rules'][0]['hari']);
    }

    public function test_acl_hanya_memuat_izin_ruangan_terikat(): void
    {
        $this->mesinRuangan();
        $ruanganLain = Ruangan::create(['kode' => 'RM-2', 'nama' => 'Ruang Lain', 'aktif' => true]);
        $this->u['ketua']->update(['finger_id' => 92]);

        // Pengajuan ke ruangan lain — tidak boleh muncul di ACL mesin RM-1.
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), [
            'ruangan_id' => $ruanganLain->id, 'tanggal' => '2026-11-12',
            'jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'tujuan' => 'x',
            'peserta' => [$this->u['ketua']->id],
        ]);
        RuanganBookingService::setujui(RuanganPengajuan::latest('id')->firstOrFail(), $this->u['laboran']);

        $entries = $this->aclGet()->assertOk()->json('entries');
        $this->assertFalse(collect($entries)->contains(fn ($e) => $e['t'] === 'finger' && $e['v'] === '92'));
    }

    public function test_acl_tidak_membocorkan_hash_bcrypt_atau_pin_petugas(): void
    {
        $this->mesinRuangan();
        RuanganPetugas::create([
            'nama' => 'CS PIN', 'id_internal' => 'CS-P1', 'tipe_kredensial' => 'pin',
            'kredensial_hash' => Hash::make('4321'), 'status' => 'aktif',
            'berlaku_mulai' => '2026-11-01', 'berlaku_selesai' => '2026-12-31',
            'jadwal' => [['ruangan_id' => $this->room->id, 'hari' => [1, 2, 3, 4, 5], 'jam_mulai' => '06:00', 'jam_selesai' => '08:00']],
        ]);

        $res = $this->aclGet()->assertOk();

        // Petugas ber-PIN bcrypt tidak boleh masuk ACL sama sekali.
        $this->assertFalse(collect($res->json('entries'))->contains(fn ($e) => ($e['why'] ?? null) === 'petugas'));
        // Tidak ada hash bcrypt ($2y$...) di seluruh payload.
        $this->assertStringNotContainsString('$2y$', $res->getContent());
    }
}
