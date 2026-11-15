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
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class RuanganBookingTest extends TestCase
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
            throw new RuntimeException('Tes ruangan hanya untuk sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));
        PermissionRegistry::clearCache();

        $this->timA = PblProject::create(['kode' => 'PBL-A', 'nama' => 'Tim Alfa', 'aktif' => true]);
        $timB = PblProject::create(['kode' => 'PBL-B', 'nama' => 'Tim Beta', 'aktif' => true]);

        $this->u['laboran'] = $this->buatUser('Laboran Uji', 'LAB-01', 'laboran');
        $this->u['dosen'] = $this->buatUser('Dosen Uji', 'DSN-01', 'dosen');
        $this->u['ketua'] = $this->buatUser('Ketua Alfa', 'MHS-01', 'mahasiswa', 'TI-1A', 'Tim Alfa');
        $this->u['anggota'] = $this->buatUser('Anggota Alfa', 'MHS-02', 'mahasiswa', 'TI-1A', 'Tim Alfa');
        $this->u['luar'] = $this->buatUser('Luar Tim', 'MHS-09', 'mahasiswa', 'TI-1A', 'Tim Beta');

        $this->timA->update(['manpro_user_id' => $this->u['dosen']->id]);
        PblTeamMember::create(['pbl_project_id' => $this->timA->id, 'user_id' => $this->u['ketua']->id, 'role' => 'ketua']);
        PblTeamMember::create(['pbl_project_id' => $this->timA->id, 'user_id' => $this->u['anggota']->id, 'role' => 'anggota']);
        PblTeamMember::create(['pbl_project_id' => $timB->id, 'user_id' => $this->u['luar']->id, 'role' => 'ketua']);

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

    private function payloadPengajuan(array $over = []): array
    {
        return array_merge([
            'ruangan_id' => $this->room->id,
            'tanggal' => '2026-11-12',
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:30',
            'tujuan' => 'Review progres',
            'peserta' => [$this->u['ketua']->id, $this->u['anggota']->id],
        ], $over);
    }

    // ---------- Pembuatan ----------

    public function test_mahasiswa_mengajukan_untuk_timnya(): void
    {
        $res = $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $res->assertRedirect();

        $p = RuanganPengajuan::firstOrFail();
        $this->assertSame('diajukan', $p->status);
        $this->assertSame($this->u['ketua']->id, $p->pengaju_id);
        $this->assertSame($this->u['dosen']->id, $p->pembimbing_id);
        $this->assertSame('Tim Alfa', $p->snapshot['tim']['nama']);
        $this->assertCount(2, $p->snapshot['peserta']);
    }

    public function test_peserta_di_luar_tim_ditolak(): void
    {
        $this->actingAs($this->u['ketua'])
            ->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan(['peserta' => [$this->u['luar']->id]]))
            ->assertSessionHasErrors('peserta');
        $this->assertSame(0, RuanganPengajuan::count());
    }

    public function test_mahasiswa_tanpa_tim_tidak_bisa_mengajukan(): void
    {
        $bebas = $this->buatUser('Tanpa Tim', 'MHS-99', 'mahasiswa', 'TI-1A', null);
        $this->actingAs($bebas)->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan())
            ->assertSessionHasErrors('pengajuan');
    }

    public function test_aturan_default_hplus2_dan_durasi_berlaku(): void
    {
        // H+3 melewati default max_advance_days=2
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['tanggal' => '2026-11-14']))->assertSessionHasErrors('pengajuan');
        // Durasi 150 menit melewati default 120
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_selesai' => '11:30']))->assertSessionHasErrors('pengajuan');
        // Di luar jam operasional
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '22:00', 'jam_selesai' => '23:00']))->assertSessionHasErrors('pengajuan');
    }

    public function test_satu_pending_per_tim_per_ruangan(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $this->actingAs($this->u['anggota'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '13:00', 'jam_selesai' => '14:00']))
            ->assertSessionHasErrors('pengajuan');
    }

    // ---------- Persetujuan & konflik ----------

    public function test_persetujuan_menerbitkan_izin_per_peserta_bukan_seluruh_tim(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['peserta' => [$this->u['ketua']->id]]));
        $p = RuanganPengajuan::firstOrFail();

        $this->actingAs($this->u['laboran'])->post(route('admin.pengajuan.setujui', $p))->assertRedirect();
        $this->assertSame('disetujui', $p->fresh()->status);
        $this->assertSame(1, RuanganIzin::count());
        $this->assertSame($this->u['ketua']->id, RuanganIzin::first()->user_id);
    }

    public function test_konflik_persetujuan_ditolak_atomik(): void
    {
        // Dua tim berbeda pada slot beririsan. Pengajuan kedua kini dibuat langsung
        // via model — validasi store sudah menolak slot yang diklaim, sehingga
        // konflik di level persetujuan hanya bisa muncul lewat data lama/race.
        $luar = $this->u['luar'];
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p2 = RuanganPengajuan::create([
            'ruangan_id' => $this->room->id, 'pengaju_id' => $luar->id,
            'pbl_project_id' => $luar->tim ? \App\Models\PblTeamMember::where('user_id', $luar->id)->value('pbl_project_id') : null,
            'tujuan' => 'Rapat tim', 'tanggal' => '2026-11-12',
            'jam_mulai' => '10:00', 'jam_selesai' => '11:00', 'status' => 'diajukan',
        ]);

        $p1 = RuanganPengajuan::where('pengaju_id', $this->u['ketua']->id)->orderBy('id')->firstOrFail();

        // Slot masih diklaim p2 ('diajukan' ikut memblokir) → setujui p1 pun tertahan.
        try {
            RuanganBookingService::setujui($p1, $this->u['laboran']);
            $this->fail('Konflik tidak terdeteksi saat p2 masih pending');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame('diajukan', $p1->fresh()->status);
        }

        // Setelah p2 diputuskan (ditolak), p1 bisa disetujui — dan bila p2
        // diajukan ulang pada slot yang kini disetujui, persetujuannya tertahan.
        RuanganBookingService::tolak($p2, $this->u['laboran'], 'uji');
        RuanganBookingService::setujui($p1, $this->u['laboran']);
        $p2 = $p2->fresh(); // wajib segar: atribut lama 'diajukan' membuat save() no-op
        $p2->status = 'diajukan';
        $p2->save();

        try {
            RuanganBookingService::setujui($p2->fresh(), $this->u['laboran']);
            $this->fail('Konflik tidak terdeteksi');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame('diajukan', $p2->fresh()->status);
        }
    }

    public function test_waktu_pengganti_butuh_penerimaan_pengaju(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();

        RuanganBookingService::tawarkanPengganti($p, $this->u['laboran'], Carbon::parse('2026-11-12 14:00'), Carbon::parse('2026-11-12 15:30'), 'Dipakai rapat lain');
        $this->assertSame('menunggu_penerimaan', $p->fresh()->status);
        $this->assertSame(0, RuanganIzin::count()); // izin belum aktif sebelum diterima

        // Di luar tim ditolak dengan error validasi (bukan 403 mentah)
        $this->actingAs($this->u['luar'])->post(route('mahasiswa.ruangan.terima-pengganti', $p))
            ->assertSessionHasErrors('pengajuan');

        // Anggota tim yang sama boleh menerima atas nama tim
        $this->actingAs($this->u['anggota'])->post(route('mahasiswa.ruangan.terima-pengganti', $p))->assertRedirect();
        $p = $p->fresh();
        $this->assertSame('disetujui', $p->status);
        $this->assertSame('14:00', substr($p->jam_mulai, 0, 5));
        $this->assertSame(2, RuanganIzin::where('status', 'aktif')->count());
    }

    public function test_pembatalan_mengakhiri_izin(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();
        RuanganBookingService::setujui($p, $this->u['laboran']);
        $this->assertSame(2, RuanganIzin::where('status', 'aktif')->count());

        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.batal', $p))->assertRedirect();
        $this->assertSame('dibatalkan', $p->fresh()->status);
        $this->assertSame(0, RuanganIzin::where('status', 'aktif')->count());
        $this->assertSame(2, RuanganIzin::where('status', 'dicabut')->count());
    }

    public function test_status_waktu_selesai_dan_kedaluwarsa(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();
        RuanganBookingService::setujui($p, $this->u['laboran']);

        Carbon::setTestNow('2026-11-12 11:00:00');

        // Disetujui + lewat TANPA bukti akses mesin => kedaluwarsa (tidak datang),
        // bukan selesai — kehadiran harus dibuktikan event izin/buka.
        $this->assertSame('kedaluwarsa', $p->fresh()->statusEfektif());

        // Bukti kedatangan dari mesin => selesai.
        $izin = RuanganIzin::where('pengajuan_id', $p->id)->firstOrFail();
        RuanganAccessEvent::create([
            'mesin_iot_id' => $this->mesinRuangan()->id, 'ruangan_id' => $this->room->id, 'user_id' => $this->u['ketua']->id,
            'izin_id' => $izin->id, 'jenis' => 'check', 'hasil' => 'diizinkan',
            'terjadi_pada' => '2026-11-12 09:05:00',
        ]);
        $this->assertSame('selesai', $p->fresh()->statusEfektif());

        $pending = RuanganPengajuan::create([
            'ruangan_id' => $this->room->id, 'pengaju_id' => $this->u['ketua']->id,
            'pbl_project_id' => $this->timA->id, 'tujuan' => 'x', 'tanggal' => '2026-11-12',
            'jam_mulai' => '08:00', 'jam_selesai' => '09:00', 'status' => 'diajukan',
        ]);
        $this->assertSame('kedaluwarsa', $pending->statusEfektif());
    }

    // ---------- Cakupan dosen ----------

    public function test_dosen_hanya_melihat_tim_bimbingannya(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $this->actingAs($this->u['luar'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan(['peserta' => [$this->u['luar']->id], 'jam_mulai' => '13:00', 'jam_selesai' => '14:00']));

        $html = $this->actingAs($this->u['dosen'])->get(route('dosen.ruangan'))->assertOk()->getContent();
        $this->assertStringContainsString('Tim Alfa', $html);
        $this->assertStringNotContainsString('Tim Beta', $html);
    }

    public function test_mahasiswa_tidak_bisa_membuka_antrean_laboran(): void
    {
        $this->actingAs($this->u['ketua'])->get(route('admin.pengajuan.index'))->assertStatus(403);
    }

    // ---------- Akses perangkat ----------

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

    public function test_akses_diizinkan_saat_izin_aktif_dan_ditolak_tanpa_izin(): void
    {
        $this->mesinRuangan();
        $this->u['ketua']->update(['finger_id' => 71]);

        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();
        RuanganBookingService::setujui($p, $this->u['laboran']);

        // Di dalam rentang izin
        Carbon::setTestNow('2026-11-12 09:30:00');
        $res = $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '71']);
        $res->assertOk()->assertJson(['diizinkan' => true]);

        // Identitas sama lewat RFID → pemeriksaan izin yang sama
        $this->u['ketua']->rfid_uid = 'AABBCC';  // kolom akses bukan fillable — assignment langsung
        $this->u['ketua']->save();
        $this->aksesCheck(['tipe' => 'rfid', 'nilai' => 'aabbcc'])->assertOk()->assertJson(['diizinkan' => true]);

        // Anggota tanpa izin (bukan peserta tunggal) ditolak
        $this->u['luar']->update(['finger_id' => 72]);
        $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '72'])->assertOk()->assertJson(['diizinkan' => false, 'alasan' => 'tidak_ada_izin']);
    }

    public function test_izin_kedaluwarsa_dan_dicabut_ditolak_perangkat(): void
    {
        $this->mesinRuangan();
        $this->u['ketua']->update(['finger_id' => 73]);

        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();
        RuanganBookingService::setujui($p, $this->u['laboran']);

        // Setelah waktu selesai → izin kedaluwarsa
        Carbon::setTestNow('2026-11-12 12:00:00');
        $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '73'])->assertJson(['diizinkan' => false]);

        // Dicabut sebelum waktunya → ditolak
        Carbon::setTestNow('2026-11-12 09:15:00');
        RuanganBookingService::cabut($p->fresh(), $this->u['laboran'], 'uji');
        $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '73'])->assertJson(['diizinkan' => false]);
    }

    public function test_mesin_tanpa_binding_ruangan_ditolak(): void
    {
        MesinIot::create([
            'id_mesin' => 'MESIN_BEBAS', 'nama_mesin' => 'X', 'tipe' => 'ruangan',
            'api_key' => Hash::make('kunci-ruang-uji'), 'is_active' => true,
        ]);
        $this->aksesCheck(['tipe' => 'fingerprint', 'nilai' => '1'], 'MESIN_BEBAS')
            ->assertStatus(403)->assertJson(['error' => 'mesin_tidak_terikat_ruangan']);
    }

    public function test_petugas_cs_tanpa_portal_diizinkan_di_jadwalnya(): void
    {
        $this->mesinRuangan();
        RuanganPetugas::create([
            'nama' => 'CS Pagi', 'id_internal' => 'CS-01', 'tipe_kredensial' => 'pin',
            'kredensial_hash' => Hash::make('4321'), 'status' => 'aktif',
            'berlaku_mulai' => '2026-11-01', 'berlaku_selesai' => '2026-12-31',
            'jadwal' => [['ruangan_id' => $this->room->id, 'hari' => [4], 'jam_mulai' => '06:00', 'jam_selesai' => '08:00']],
        ]);

        Carbon::setTestNow('2026-11-12 07:00:00'); // Kamis 07:00 — dalam jadwal
        $this->aksesCheck(['tipe' => 'pin', 'nilai' => '4321'])
            ->assertOk()->assertJson(['diizinkan' => true, 'identitas' => 'petugas']);

        Carbon::setTestNow('2026-11-12 09:00:00'); // di luar jadwal petugas
        $this->aksesCheck(['tipe' => 'pin', 'nilai' => '4321'])->assertJson(['diizinkan' => false]);
    }

    public function test_jadwal_petugas_memblokir_pemesanan(): void
    {
        RuanganPetugas::create([
            'nama' => 'CS Pagi', 'id_internal' => 'CS-02', 'tipe_kredensial' => 'pin',
            'kredensial_hash' => Hash::make('4321'), 'status' => 'aktif',
            'jadwal' => [['ruangan_id' => $this->room->id, 'hari' => [], 'jam_mulai' => '08:00', 'jam_selesai' => '10:00']],
        ]);
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan())
            ->assertSessionHasErrors('pengajuan');
    }

    public function test_event_perangkat_tercatat_terpisah_dari_tap_absensi(): void
    {
        $this->mesinRuangan();
        $this->withHeaders(['X-API-KEY' => 'kunci-ruang-uji', 'X-ID-MESIN' => 'MESIN_RUANG_01'])
            ->postJson('/api/mesin/ruangan/access/event', ['jenis' => 'buka', 'hasil' => 'ok', 'meta' => ['s' => 1]])
            ->assertOk();
        $ev = RuanganAccessEvent::firstOrFail();
        $this->assertSame($this->room->id, $ev->ruangan_id);
        $this->assertSame('buka', $ev->jenis);
        $this->assertSame(0, \App\Models\LogAbsensi::count());
    }

    public function test_binding_mesin_butuh_is_admin(): void
    {
        $mesin = $this->mesinRuangan();
        // Laboran tanpa is_admin → 403
        $this->actingAs($this->u['laboran'])->post(route('admin.mesin.bind-ruangan', $mesin), ['ruangan_id' => $this->room->id])
            ->assertStatus(403);
        // Laboran + is_admin → boleh
        $admin = $this->buatUser('Admin Uji', 'ADM-01', 'laboran');
        $admin->is_admin = true;   // flag hanya bisa diubah via assignment langsung, bukan mass-assignment
        $admin->save();
        $this->actingAs($admin)->post(route('admin.mesin.bind-ruangan', $mesin), ['ruangan_id' => $this->room->id])
            ->assertRedirect();
        $this->assertSame($this->room->id, $mesin->fresh()->ruangan_id);
    }

    public function test_aksi_admin_only_ditolak_untuk_laboran_biasa(): void
    {
        // Daftar akun boleh dilihat laboran (perm users,view) — yang digerbang
        // admin.only adalah aksi mutasinya: buat/ubah/hapus akun, import, pengaturan,
        // konfigurasi perangkat.
        $this->actingAs($this->u['laboran'])->get(route('admin.users'))->assertOk();
        $this->actingAs($this->u['laboran'])->post(route('admin.users.store'), [])->assertStatus(403);
        $this->actingAs($this->u['laboran'])->get(route('admin.settings'))->assertStatus(403);
        $this->actingAs($this->u['laboran'])->post(route('admin.mesin.store'), [])->assertStatus(403);
        $this->actingAs($this->u['laboran'])->post(route('admin.absensi.prodi'), [])->assertStatus(403);

        // Akses operasional laboran tetap terbuka: antrean pengajuan, ruangan, petugas, status perangkat.
        $this->actingAs($this->u['laboran'])->get(route('admin.pengajuan.index'))->assertOk();
        $this->actingAs($this->u['laboran'])->get(route('admin.ruangan.index'))->assertOk();
        $this->actingAs($this->u['laboran'])->get(route('admin.petugas.index'))->assertOk();
        $this->actingAs($this->u['laboran'])->get(route('admin.mesin.index'))->assertOk();
    }

    public function test_aksi_admin_only_boleh_untuk_akun_admin(): void
    {
        $admin = $this->buatUser('Admin Uji 2', 'ADM-02', 'laboran');
        $admin->is_admin = true;
        $admin->save();

        // Admin lolos gate (bukan 403) pada aksi khusus admin; halaman render OK.
        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
        // POST tanpa payload sah ditolak validasi (bukan 403) — membuktikan lolos admin.only.
        $this->actingAs($admin)->post(route('admin.mesin.store'), [])->assertSessionHasErrors();
        // Dan tetap punya akses operasional.
        $this->actingAs($admin)->get(route('admin.pengajuan.index'))->assertOk();
    }

    public function test_role_admin_murni_lolos_admin_only_dan_operasional(): void
    {
        // Role 'admin' = sumber kewenangan utama; flag is_admin tidak diperlukan lagi.
        $admin = $this->buatUser('Admin Murni', 'ADM-09', 'admin');
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse((bool) $admin->is_admin);

        // Aksi khusus admin — lolos (bukan 403).
        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
        $this->actingAs($admin)->post(route('admin.mesin.store'), [])->assertSessionHasErrors();
        // Editor "Akun & Peran" dihapus — URL lama tidak lagi membuka halaman
        // maupun menerima perubahan pengaturan akses.
        $this->actingAs($admin)->get('/admin/role-access')->assertNotFound();
        $this->actingAs($admin)->post('/admin/role-access', ['access' => []])->assertNotFound();
        $this->actingAs($admin)->post('/admin/role-access/reset')->assertNotFound();

        // Operasional laboran tetap dapat dijalankan admin.
        $this->actingAs($admin)->get(route('admin.pengajuan.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.ruangan.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.petugas.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.mesin.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fingerprint'))->assertOk();
    }

    public function test_role_admin_login_dan_dosen_mahasiswa_terblokir_dari_area_admin(): void
    {
        $admin = $this->buatUser('Admin Login', 'ADM-L1', 'admin');

        // Akun admin yang memilih role lain ditolak oleh pencocokan role.
        $this->post('/masuk', [
            'nitk_nidn' => 'ADM-L1', 'password' => 'bukan-sandi', 'role' => 'dosen',
        ])->assertSessionHasErrors('nitk_nidn');

        // Login sebagai role admin → dashboard admin (dropdown hanya label, izin dari role DB).
        $this->post('/masuk', [
            'nitk_nidn' => 'ADM-L1', 'password' => 'bukan-sandi', 'role' => 'admin',
        ])->assertRedirect('/admin/dashboard');

        // Dosen & mahasiswa tidak bisa membuka area admin secara langsung.
        $this->actingAs($this->u['dosen'])->get(route('admin.users'))->assertStatus(403);
        $this->actingAs($this->u['dosen'])->get(route('admin.pengajuan.index'))->assertStatus(403);
        $this->actingAs($this->u['ketua'])->get(route('admin.pengajuan.index'))->assertStatus(403);
        $this->actingAs($this->u['ketua'])->get(route('admin.users'))->assertStatus(403);

        // Dosen tidak memutus pengajuan (route putusan berada di grup admin).
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();
        $this->actingAs($this->u['dosen'])->post(route('admin.pengajuan.setujui', $p))->assertStatus(403);
        $this->assertSame('diajukan', $p->fresh()->status);
    }

    public function test_remember_recaller_valid_dan_kedaluwarsa_terisolasi(): void
    {
        // Recaller Laravel = id|remember_token|password; dikirim tanpa sesi → viaRemember().
        $user = $this->u['ketua'];
        $user->forceFill(['remember_token' => 'rectok-uji'])->save();
        $recaller = $user->id.'|rectok-uji|'.$user->password;
        $name = 'remember_web_'.sha1(\Illuminate\Auth\SessionGuard::class);

        // Recaller valid + remember_until masih berlaku → sesi bangkit, akses OK.
        $this->withCookie($name, $recaller)
            ->withCookie('remember_until', (string) now()->addDays(10)->timestamp)
            ->get(route('mahasiswa.beranda'))
            ->assertOk();

        // remember_until kedaluwarsa → sesi recaller ditolak, dialihkan ke login.
        $this->withCookie($name, $recaller)
            ->withCookie('remember_until', (string) now()->subMinute()->timestamp)
            ->get(route('mahasiswa.beranda'))
            ->assertRedirect(route('login'));
    }

    // ---------- Endpoint ketersediaan (pemilih waktu) ----------

    public function test_ketersediaan_json_terotorisasi_dan_tanpa_identitas_pemesan(): void
    {
        $url = route('mahasiswa.ruangan.ketersediaan', ['ruangan_id' => $this->room->id, 'tanggal' => '2026-11-12']);

        // Tamu dialihkan ke login; non-mahasiswa ditolak role gate.
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->u['laboran'])->getJson($url)->assertForbidden();
        $this->actingAs($this->u['dosen'])->getJson($url)->assertForbidden();

        $res = $this->actingAs($this->u['ketua'])->getJson($url . '&durasi=60');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ketersediaan.boleh', true)
            ->assertJsonPath('ketersediaan.tanggal', '2026-11-12');
        $this->assertNotEmpty($res->json('ketersediaan.slots'));

        // Payload tidak membawa identitas pemesan lain sama sekali.
        foreach (['Ketua Alfa', 'Luar Tim', 'nama_lengkap', 'nitk_nidn', 'pengaju_id', 'peserta'] as $kunci) {
            $this->assertStringNotContainsString($kunci, $res->getContent());
        }
    }

    public function test_slot_terhalang_booking_disetujui_dan_jadwal_petugas(): void
    {
        // Booking tim lain disetujui 09:00–10:30 pada 2026-11-12 (Kamis).
        $luar = $this->u['luar'];
        $this->actingAs($luar)->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '09:00', 'jam_selesai' => '10:30', 'peserta' => [$luar->id]]));
        RuanganBookingService::setujui(RuanganPengajuan::firstOrFail(), $this->u['laboran']);

        // Jadwal petugas 13:00–14:00 memblokir slot pada rentang itu.
        RuanganPetugas::create([
            'nama' => 'CS Pagi', 'id_internal' => 'CS-02', 'tipe_kredensial' => 'pin',
            'kredensial_hash' => Hash::make('4321'), 'status' => 'aktif',
            'jadwal' => [['ruangan_id' => $this->room->id, 'hari' => [], 'jam_mulai' => '13:00', 'jam_selesai' => '14:00']],
        ]);

        $res = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $this->room->id, 'tanggal' => '2026-11-12', 'durasi' => 60,
        ]))->assertOk();

        $slots = collect($res->json('ketersediaan.slots'))->keyBy('mulai');
        $this->assertSame('dipesan', $slots['09:00']['status']);
        $this->assertSame('dipesan', $slots['10:00']['status']); // 10:00–11:00 tetap beririsan s.d. 10:30
        $this->assertSame('tersedia', $slots['11:00']['status']);
        $this->assertSame('petugas', $slots['13:00']['status']);
        $this->assertSame('tersedia', $slots['14:00']['status']);

        // Durasi 120 menit memperluas penghalang di sekitar booking.
        $res2 = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $this->room->id, 'tanggal' => '2026-11-12', 'durasi' => 120,
        ]))->assertOk();
        $slots2 = collect($res2->json('ketersediaan.slots'))->keyBy('mulai');
        $this->assertSame('dipesan', $slots2['09:00']['status']);   // 09:00–11:00 overlap
        $this->assertSame('dipesan', $slots2['10:00']['status']);   // 10:00–12:00 overlap
        $this->assertSame('tersedia', $slots2['11:00']['status']);  // 11:00–13:00 bebas (petugas mulai 13:00)
    }

    public function test_ketersediaan_menolak_tanggal_di_luar_aturan(): void
    {
        // H+3 melewati default max_advance_days=2.
        $res = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $this->room->id, 'tanggal' => '2026-11-14',
        ]))->assertOk();
        $res->assertJsonPath('ketersediaan.boleh', false);
        $this->assertNotEmpty($res->json('ketersediaan.alasan'));
        $this->assertSame([], $res->json('ketersediaan.slots'));

        // Ruangan hanya Selasa — 2026-11-12 adalah Kamis.
        $selasa = Ruangan::create(['kode' => 'RM-2', 'nama' => 'Ruang Selasa', 'aktif' => true, 'aturan' => ['hari' => [2]]]);
        $res2 = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $selasa->id, 'tanggal' => '2026-11-12',
        ]))->assertOk();
        $res2->assertJsonPath('ketersediaan.boleh', false);
        $this->assertStringContainsString('beroperasi', (string) $res2->json('ketersediaan.alasan'));
        $this->assertStringContainsString('Kamis', (string) $res2->json('ketersediaan.alasan'));
        $this->assertSame('Selasa', $res2->json('ketersediaan.hari_label'));
        $this->assertSame('2026-11-17', $res2->json('ketersediaan.tanggal_tersedia_berikutnya'));

        // Ruangan nonaktif.
        $mati = Ruangan::create(['kode' => 'RM-3', 'nama' => 'Ruang Mati', 'aktif' => false]);
        $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $mati->id, 'tanggal' => '2026-11-12',
        ]))->assertOk()->assertJsonPath('ketersediaan.boleh', false);
    }

    public function test_halaman_menampilkan_ringkasan_terkirim_dan_timeline_riwayat(): void
    {
        // Form pengajuan + data tim/ruangan termuat di props halaman.
        $this->actingAs($this->u['ketua'])->get(route('mahasiswa.ruangan'))
            ->assertOk()
            ->assertSee('Ruang Meeting 1')
            ->assertSee('Tim Alfa')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/RoomRequest', false)
                ->has('ruanganList')
                ->has('anggota')
                ->where('tim.nama', 'Tim Alfa')
            );

        // Setelah kirim: ringkasan terkirim + status menunggu, bukan "disetujui".
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $this->actingAs($this->u['ketua'])->get(route('mahasiswa.ruangan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/RoomRequest', false)
                ->where('flash.terkirim.id', RuanganPengajuan::firstOrFail()->id)
                ->where('flash.terkirim.ruangan', 'Ruang Meeting 1')
                ->where('pengajuan.0.status', 'diajukan')
            );
    }

    // ---------- Multi-jam (maks. 5) & bentrok saat pengajuan ----------

    public function test_peminjaman_hingga_lima_jam_berurutan_dan_batasnya(): void
    {
        // Ruangan mengizinkan 8 jam — kebijakan tetap membatasi 5 jam per pengajuan.
        $this->room->update(['aturan' => ['max_duration_minutes' => 480]]);

        // Lebih dari 5 jam ditolak walau konfigurasi ruangan membolehkan lebih.
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '08:00', 'jam_selesai' => '14:00']))
            ->assertSessionHasErrors('pengajuan');
        $this->assertSame(0, RuanganPengajuan::count());

        // Tepat 5 jam berurutan diterima sebagai satu pengajuan 08:00–13:00.
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '08:00', 'jam_selesai' => '13:00']))
            ->assertRedirect();
        $p = RuanganPengajuan::firstOrFail();
        $this->assertSame('08:00', substr((string) $p->jam_mulai, 0, 5));
        $this->assertSame('13:00', substr((string) $p->jam_selesai, 0, 5));
    }

    public function test_pengajuan_menolak_rentang_yang_sudah_disetujui(): void
    {
        // Booking tim lain disetujui 09:00–10:00.
        $luar = $this->u['luar'];
        $this->actingAs($luar)->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'peserta' => [$luar->id]]));
        RuanganBookingService::setujui(RuanganPengajuan::firstOrFail(), $this->u['laboran']);

        // Overlap dengan yang disetujui ditolak saat pengajuan, bukan menunggu ACC.
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '09:30', 'jam_selesai' => '10:30']))
            ->assertSessionHasErrors('pengajuan');
        $this->assertSame(1, RuanganPengajuan::count());

        // Rentang bersebelahan tetap dapat diajukan tim lain (slot di luar pinjaman terbuka).
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'),
            $this->payloadPengajuan(['jam_mulai' => '10:00', 'jam_selesai' => '12:00']))
            ->assertRedirect();
        $this->assertSame(2, RuanganPengajuan::count());
    }

    public function test_ketersediaan_alasan_slot_habis_dan_semua_lewat(): void
    {
        $luar = $this->u['luar'];
        $timB = PblProject::where('kode', 'PBL-B')->firstOrFail();

        // Sehari penuh sudah dipesan → pesan khusus, bukan sekadar "tidak tersedia".
        RuanganPengajuan::create([
            'ruangan_id' => $this->room->id, 'pengaju_id' => $luar->id, 'pbl_project_id' => $timB->id,
            'tujuan' => 'x', 'tanggal' => '2026-11-12', 'jam_mulai' => '07:00', 'jam_selesai' => '21:00',
            'status' => 'disetujui', 'peserta' => [$luar->id],
        ]);
        $res = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $this->room->id, 'tanggal' => '2026-11-12', 'durasi' => 60,
        ]))->assertOk();
        $res->assertJsonPath('ketersediaan.boleh', true);
        $this->assertSame('Seluruh slot pada tanggal ini sudah dipesan.', $res->json('ketersediaan.habis_alasan'));

        // Semua slot hari ini sudah lewat → pesan "tidak ada lagi slot" (ruangan bebas booking).
        // min_advance_days=0 supaya ruangan ini tetap boleh diajukan untuk hari ini.
        $sore = Ruangan::create(['kode' => 'RM-9', 'nama' => 'Ruang Sore', 'aktif' => true,
            'aturan' => ['jam_buka' => '07:00', 'jam_tutup' => '12:00', 'min_advance_days' => 0]]);
        Carbon::setTestNow(Carbon::parse('2026-11-12 13:00:00', 'Asia/Jakarta'));
        $res2 = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.ruangan.ketersediaan', [
            'ruangan_id' => $sore->id, 'tanggal' => '2026-11-12', 'durasi' => 60,
        ]))->assertOk();
        $this->assertSame('Tidak ada lagi slot yang dapat diajukan pada tanggal ini.', $res2->json('ketersediaan.habis_alasan'));
    }

    // ---------- Notifikasi keputusan (regresi: closure $user + audit utuh) ----------

    public function test_notifikasi_mahasiswa_memuat_nama_pemutus_tanpa_error(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());
        $p = RuanganPengajuan::firstOrFail();

        $this->actingAs($this->u['laboran'])->post(route('admin.pengajuan.setujui', $p))->assertRedirect();

        // Feed notifikasi mahasiswa — jalur yang sebelumnya 500 (closure tanpa use $user).
        $res = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.notifications.feed'));
        $res->assertOk();
        $notif = collect($res->json('items'))->firstWhere('kind', 'pengajuan');
        $this->assertNotNull($notif, 'Item notifikasi pengajuan tidak ditemukan');
        $this->assertStringContainsString('disetujui', $notif['title']);
        $this->assertStringContainsString('Laboran Uji', $notif['excerpt']);
        $this->assertStringContainsString('Laboran', $notif['meta']);
        $this->assertFalse($notif['is_read']);

        // Peserta (bukan pengaju) juga menerima notifikasi yang sama.
        $res2 = $this->actingAs($this->u['anggota'])->getJson(route('mahasiswa.notifications.feed'));
        $res2->assertOk();
        $this->assertNotNull(collect($res2->json('items'))->firstWhere('kind', 'pengajuan'));

        // Tandai dibaca — audit pengajuan TIDAK boleh berubah.
        $sebelum = $p->fresh();
        $this->actingAs($this->u['ketua'])->postJson(route('mahasiswa.notifications.ops-read'), [
            'key' => 'pgj-' . $p->id,
            'fp' => 'pgj-' . $p->id . ':' . $sebelum->diputus_pada->timestamp,
        ])->assertOk();
        $fresh = $p->fresh();
        $this->assertSame('disetujui', $fresh->status);
        $this->assertSame($this->u['laboran']->id, $fresh->diputus_oleh);
        $this->assertSame(
            $sebelum->diputus_pada->toDateTimeString(),
            $fresh->diputus_pada->toDateTimeString()
        );

        // Setelah dibaca, item tetap ada tapi tak lagi dihitung unread.
        $res3 = $this->actingAs($this->u['ketua'])->getJson(route('mahasiswa.notifications.feed'));
        $item3 = collect($res3->json('items'))->firstWhere('kind', 'pengajuan');
        $this->assertNotNull($item3);
        $this->assertTrue($item3['is_read']);
    }

    public function test_badge_pengajuan_pending_untuk_staf(): void
    {
        $this->actingAs($this->u['ketua'])->post(route('mahasiswa.ruangan.store'), $this->payloadPengajuan());

        $ops = \App\Support\StaffNotificationService::operationalItems($this->u['laboran']);
        $item = $ops->firstWhere('id', 'ops-pengajuan');
        $this->assertNotNull($item);
        $this->assertStringContainsString('1 pengajuan', $item['title']);
        $this->assertSame(1, $item['count']);
    }
}

