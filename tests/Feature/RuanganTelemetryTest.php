<?php

namespace Tests\Feature;

use App\Models\MesinIot;
use App\Models\Ruangan;
use App\Models\RuanganTelemetri;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * Telemetri kondisi ruangan: endpoint edge → snapshot per ruangan → panel
 * "Kondisi Ruangan" di Monitoring Operasional. Ruangan selalu dari binding
 * mesin; kunci metrik bebas (apd/listrik/printer/solder dikenali UI).
 */
class RuanganTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private Ruangan $room;

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes telemetri hanya untuk sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Jakarta'));
        PermissionRegistry::clearCache();
        $this->room = Ruangan::create(['kode' => 'LAB-3DP', 'nama' => 'Ruang 3D Print', 'aktif' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatUser(string $nama, string $nidn, string $role): User
    {
        return User::query()->create([
            'nama_lengkap' => $nama,
            'nitk_nidn' => $nidn,
            'email' => strtolower($nidn).'@example.test',
            'password' => 'bukan-sandi',
            'role' => $role,
            'is_active' => 1,
            'approval_status' => 'approved',
        ]);
    }

    private function mesinRuangan(): MesinIot
    {
        return MesinIot::create([
            'id_mesin' => 'MESIN_3DP_01', 'nama_mesin' => 'Edge 3DP', 'tipe' => 'ruangan',
            'api_key' => Hash::make('kunci-3dp-uji'), 'is_active' => true, 'ruangan_id' => $this->room->id,
        ]);
    }

    private function telemetriPost(array $body, string $idMesin = 'MESIN_3DP_01'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-API-KEY' => 'kunci-3dp-uji', 'X-ID-MESIN' => $idMesin])
            ->postJson('/api/mesin/ruangan/telemetry', $body);
    }

    public function test_telemetri_ditolak_tanpa_kunci_mesin(): void
    {
        $this->mesinRuangan();
        $this->postJson('/api/mesin/ruangan/telemetry', ['metrics' => ['suhu_c' => 24]])
            ->assertStatus(401);
    }

    public function test_mesin_tanpa_ikatan_ruangan_ditolak(): void
    {
        MesinIot::create([
            'id_mesin' => 'MESIN_LIAR', 'nama_mesin' => 'Liar', 'tipe' => 'ruangan',
            'api_key' => Hash::make('kunci-3dp-uji'), 'is_active' => true, 'ruangan_id' => null,
        ]);
        $this->telemetriPost(['metrics' => ['suhu_c' => 24]], 'MESIN_LIAR')
            ->assertStatus(403)
            ->assertJson(['ok' => false, 'error' => 'mesin_tidak_terikat_ruangan']);
    }

    public function test_snapshot_upsert_dan_panel_kondisi_ruangan(): void
    {
        $this->mesinRuangan();
        $this->telemetriPost([
            'metrics' => [
                'apd_ok' => 4, 'apd_total' => 5, 'listrik_watt' => 340,
                'printer_state' => 'printing', 'printer_nozzle_c' => 212,
                'printer_bed_c' => 60, 'printer_progress_pct' => 62,
                'printer_eta_s' => 4800, 'solder_active' => false,
            ],
            'alerts' => ['apd_tidak_dipakai'],
        ])->assertOk()->assertJson(['ok' => true]);

        $t = RuanganTelemetri::query()->where('ruangan_id', $this->room->id)->sole();
        $this->assertSame('MESIN_3DP_01', $t->id_mesin);
        $this->assertSame(62, $t->metrics['printer_progress_pct']);
        $this->assertSame(['apd_tidak_dipakai'], $t->alerts);
        $this->assertFalse($t->isStale());

        // Upsert — satu baris per ruangan, metrik terakhir menang.
        $this->telemetriPost(['metrics' => ['printer_state' => 'idle', 'printer_progress_pct' => 100]])
            ->assertOk();
        $this->assertSame(1, RuanganTelemetri::count());
        $this->assertSame('idle', RuanganTelemetri::sole()->metrics['printer_state']);

        // Panel admin: status warn (alert aktif hilang → setelah upsert tanpa alerts → ok;
        // upsert kedua membersihkan alerts menjadi null → status 'ok').
        $admin = $this->buatUser('Admin Uji', 'ADM-09', 'admin');
        $resp = $this->actingAs($admin)->get(route('admin.monitoring', ['tab' => 'kondisi-ruangan']));
        $resp->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MonitoringOperasional', false)
            ->where('tab', 'kondisi-ruangan')
            ->has('room_panels', 1)
            ->where('room_panels.0.nama', 'Ruang 3D Print')
            ->where('room_panels.0.status', 'ok')
            ->where('room_panels.0.metrics.printer_state', 'idle')
            ->etc());
    }

    public function test_panel_status_stale_dan_tanpa_sensor(): void
    {
        $this->mesinRuangan();
        RuanganTelemetri::create([
            'ruangan_id' => $this->room->id,
            'id_mesin' => 'MESIN_3DP_01',
            'metrics' => ['suhu_c' => 24],
            'alerts' => null,
            'recorded_at' => Carbon::parse('2026-11-16 09:40:00', 'Asia/Jakarta'), // 20 mnt > STALE 10
        ]);
        $kosong = Ruangan::create(['kode' => 'LAB-SLD', 'nama' => 'Ruang Solder', 'aktif' => true]);

        $admin = $this->buatUser('Admin Uji', 'ADM-10', 'admin');
        $resp = $this->actingAs($admin)->get(route('admin.monitoring', ['tab' => 'kondisi-ruangan']));
        $resp->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('room_panels.0.status', 'stale')
            ->where('room_panels.1.status', 'nosensor')
            ->etc());
    }
}
