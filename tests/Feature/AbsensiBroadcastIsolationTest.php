<?php

namespace Tests\Feature;

use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\TapReceipt;
use App\Models\User;
use App\Support\AbsensiSnapshotSignals;
use App\Support\FingerprintTemplateCodec;
use Carbon\Carbon;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Kegagalan soket tidak boleh mengubah keputusan, receipt, atau respons tap.
 */
class AbsensiBroadcastIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'kunci-uji-soket';

    private const MESIN_ID = 'ABSEN-UJI-SOKET';

    private object $socket;

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Tes soket dibatalkan. Koneksi '.$default.' database ['.$database.'] bukan sqlite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
        Cache::flush();

        AbsensiSesiSetting::query()->updateOrCreate(
            ['shift' => 'pagi'],
            [
                'jam_masuk' => '07:50:00',
                'jam_mulai_tap_masuk' => '06:30:00',
                'jam_pulang' => '17:00:00',
                'toleransi_masuk_menit' => 15,
                'toleransi_pulang_menit' => 15,
                'grace_alfa_menit' => 120,
                'aktif' => true,
                'timezone' => 'Asia/Jakarta',
            ]
        );

        $this->socket = (object) ['mode' => 'down', 'calls' => 0, 'sent' => 0];
        $state = $this->socket;
        app(BroadcastManager::class)->extend('uji-soket', function () use ($state) {
            return new class($state) extends NullBroadcaster
            {
                public function __construct(private object $state) {}

                public function broadcast(array $channels, $event, array $payload = []): void
                {
                    $this->state->calls++;
                    if ($this->state->mode === 'down') {
                        throw new BroadcastException('Pusher error: cURL error 7: Failed to connect to 127.0.0.1 port 8080 for http://127.0.0.1:8080/apps/1/events?auth_key=k&auth_signature=s.');
                    }
                    if ($this->state->mode === 'bug') {
                        throw new \TypeError('bug event');
                    }
                    $this->state->sent++;
                }
            };
        });
        config([
            'broadcasting.connections.uji-soket' => ['driver' => 'uji-soket'],
            'broadcasting.default' => 'uji-soket',
        ]);
        Broadcast::forgetDrivers();
        Log::spy();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reverb_mati_tap_event_id_tetap_sesuai_keputusan_dan_replay_idempoten(): void
    {
        $this->seedFixture();
        $eventId = (string) Str::uuid();

        $first = $this->tap(['finger_id' => 7, 'event_id' => $eventId]);
        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('terminal', true)
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('code', 'MASUK_OK');

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertTrue((bool) TapReceipt::query()->value('success'));
        $this->assertSame(1, $this->socket->calls);
        $this->assertTrue(Cache::has(AbsensiSnapshotSignals::BREAKER_KEY));

        $replay = $this->tap(['finger_id' => 7, 'event_id' => $eventId]);
        $replay->assertOk()
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('code', 'MASUK_OK')
            ->assertJsonPath('data.log_id', $first->json('data.log_id'));

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertSame(1, $this->socket->calls);
    }

    public function test_jeda_soket_melewati_broadcast_berikutnya_dan_log_tidak_berulang(): void
    {
        $this->seedFixture();

        foreach ([8, 9] as $finger) {
            User::query()->create([
                'nama_lengkap' => 'Mahasiswa Uji Soket '.$finger,
                'nitk_nidn' => 'UJISOKET00'.$finger,
                'email' => 'uji-soket-'.$finger.'@example.test',
                'password' => 'bukan-password-mesin',
                'role' => 'mahasiswa',
                'is_active' => true,
                'approval_status' => 'approved',
                'shift' => 'pagi',
                'finger_id' => $finger,
                'finger_template' => base64_encode(str_repeat('B', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
            ]);
        }

        $this->tap(['finger_id' => 7, 'event_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('code', 'MASUK_OK');

        Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:10', 'Asia/Jakarta'));
        $this->tap(['finger_id' => 8, 'event_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('code', 'MASUK_OK');

        $this->assertSame(2, LogAbsensi::query()->count());
        $this->assertSame(1, $this->socket->calls);

        Carbon::setTestNow(Carbon::parse('2026-06-04 07:41:30', 'Asia/Jakarta'));
        $this->tap(['finger_id' => 9, 'event_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('code', 'MASUK_OK');
        $this->assertSame(3, LogAbsensi::query()->count());
        $this->assertSame(2, $this->socket->calls);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => str_starts_with((string) $message, 'Soket Reverb tidak terjangkau')
                && ! str_contains((string) ($context['pesan'] ?? ''), 'auth_signature'))
            ->once();
    }

    public function test_reverb_mati_firmware_lama_tetap_sukses(): void
    {
        $this->seedFixture();

        $this->tap(['finger_id' => 7])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'MASUK_OK')
            ->assertJsonPath('idempotency', 'unsupported');

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame(0, TapReceipt::query()->count());
        $this->assertSame(1, $this->socket->calls);
    }

    public function test_error_tak_terduga_saat_broadcast_tidak_menjadi_500(): void
    {
        $this->seedFixture();
        $this->socket->mode = 'bug';

        $this->tap(['finger_id' => 7, 'event_id' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('code', 'MASUK_OK');

        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertSame(1, $this->socket->calls);
    }

    public function test_penolakan_bisnis_tidak_broadcast(): void
    {
        $this->seedFixture();

        $this->tap(['finger_id' => 99, 'event_id' => (string) Str::uuid()])
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('terminal', true);

        $this->assertSame(0, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertSame(0, $this->socket->calls);
    }

    public function test_rollback_tidak_broadcast_dan_soket_pulih_dicatat_sekali(): void
    {
        DB::beginTransaction();
        AbsensiSnapshotSignals::committed(['log'], 'staff');
        DB::rollBack();
        $this->assertSame(0, $this->socket->calls);

        DB::transaction(fn () => AbsensiSnapshotSignals::committed(['log'], 'staff'));
        $this->assertSame(1, $this->socket->calls);
        $this->assertTrue(Cache::has(AbsensiSnapshotSignals::BREAKER_KEY));

        Cache::forget(AbsensiSnapshotSignals::BREAKER_KEY);
        $this->socket->mode = 'up';
        DB::transaction(fn () => AbsensiSnapshotSignals::committed(['log'], 'staff'));
        DB::transaction(fn () => AbsensiSnapshotSignals::committed(['log'], 'staff'));

        $this->assertSame(2, $this->socket->sent);
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => $message === 'Soket Reverb tersambung kembali.')
            ->once();
    }

    private function seedFixture(): User
    {
        MesinIot::query()->create([
            'id_mesin' => self::MESIN_ID,
            'nama_mesin' => 'Mesin uji soket',
            'tipe' => 'absensi',
            'api_key' => Hash::make(self::API_KEY),
            'is_active' => true,
        ]);

        return User::query()->create([
            'nama_lengkap' => 'Mahasiswa Uji Soket',
            'nitk_nidn' => 'UJISOKET001',
            'email' => 'uji-soket@example.test',
            'password' => 'bukan-password-mesin',
            'role' => 'mahasiswa',
            'is_active' => true,
            'approval_status' => 'approved',
            'shift' => 'pagi',
            'finger_id' => 7,
            'finger_template' => base64_encode(str_repeat('A', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
        ]);
    }

    private function tap(array $body)
    {
        return $this->withHeaders([
            'X-API-KEY' => self::API_KEY,
            'X-ID-MESIN' => self::MESIN_ID,
            'Accept' => 'application/json',
        ])->postJson('/api/mesin/tap', $body);
    }
}
