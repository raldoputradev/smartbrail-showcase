<?php

namespace Tests\Feature;

use App\Models\AbsensiHarian;
use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\TapReceipt;
use App\Models\User;
use App\Support\AbsensiTapEvaluator;
use App\Support\FingerprintTemplateCodec;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Idempotensi tap hanya di sqlite pengujian.
 * migrate:fresh dari RefreshDatabase berjalan setelah penjaga menolak koneksi selain :memory:.
 */
class AbsensiTapIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'kunci-uji-idempotensi';

    private const MESIN_ID = 'ABSEN-UJI';

    protected function beforeRefreshingDatabase()
    {
        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Tes idempotensi dibatalkan sebelum migration. Koneksi '.$default.' database ['.$database.'] bukan sqlite :memory:.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));

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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_database_pengujian_bukan_operasional(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame('simalas', config('database.connections.sqlite.database'));
    }

    public function test_replay_event_sama_menghasilkan_satu_log_dan_keputusan_pertama(): void
    {
        $this->seedMesinDanMahasiswa();
        $eventId = (string) Str::uuid();
        $body = $this->body($eventId, '2026-06-04T07:40:00+07:00');

        $first = $this->tap($body);
        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('terminal', true)
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('idempotency', 'enforced')
            ->assertJsonPath('code', 'MASUK_OK')
            ->assertJsonPath('data.jenis', 'masuk')
            ->assertJsonPath('event_id', $eventId);

        $logId = $first->json('data.log_id');

        $replay = $this->tap($body);
        $replay->assertOk()
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('code', 'MASUK_OK')
            ->assertJsonPath('data.jenis', 'masuk')
            ->assertJsonPath('data.log_id', $logId);

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertSame('masuk', LogAbsensi::query()->value('jenis'));
        $this->assertNull(AbsensiHarian::query()->value('pulang_log_id'));
        $this->assertSame($eventId, LogAbsensi::query()->value('event_id'));
        $this->assertSame(self::MESIN_ID, LogAbsensi::query()->value('id_mesin'));
    }

    public function test_replay_tidak_berubah_menjadi_pulang_saat_jam_server_bergeser(): void
    {
        $this->seedMesinDanMahasiswa();
        $eventId = (string) Str::uuid();
        $body = [
            'finger_id' => 7,
            'event_id' => $eventId,
        ];

        $this->tap($body)->assertOk()->assertJsonPath('code', 'MASUK_OK');

        Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));

        $replay = $this->tap($body);
        $replay->assertOk()
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('code', 'MASUK_OK')
            ->assertJsonPath('data.jenis', 'masuk');

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame('masuk', LogAbsensi::query()->value('jenis'));
        $this->assertNull(AbsensiHarian::query()->value('pulang_log_id'));
        $this->assertSame('2026-06-04 07:40:00', LogAbsensi::query()->first()->waktu_tap->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_payload_berbeda_pada_event_id_yang_sama_konflik(): void
    {
        $this->seedMesinDanMahasiswa();
        $eventId = (string) Str::uuid();

        $this->tap($this->body($eventId, '2026-06-04T07:40:00+07:00'))
            ->assertOk()
            ->assertJsonPath('code', 'MASUK_OK');

        $conflict = $this->tap($this->body($eventId, '2026-06-04T07:41:00+07:00'));
        $conflict->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('terminal', true)
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('code', 'EVENT_CONFLICT');

        $this->tap(array_merge($this->body($eventId, '2026-06-04T07:40:00+07:00'), [
            'finger_id' => 8,
        ]))->assertStatus(409)->assertJsonPath('code', 'EVENT_CONFLICT');

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertSame('MASUK_OK', TapReceipt::query()->value('code'));
        $this->assertTrue((bool) TapReceipt::query()->value('terminal'));
    }

    public function test_penolakan_bisnis_tersimpan_dan_replay_tetap_konsisten(): void
    {
        $user = $this->seedMesinDanMahasiswa();
        $user->forceFill(['is_active' => false])->save();
        $eventId = (string) Str::uuid();
        $body = $this->body($eventId, '2026-06-04T07:40:00+07:00');

        $first = $this->tap($body);
        $first->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('terminal', true)
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('code', 'AKUN_TIDAK_AKTIF');

        $user->forceFill(['is_active' => true])->save();

        $replay = $this->tap($body);
        $replay->assertStatus(403)
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('code', 'AKUN_TIDAK_AKTIF')
            ->assertJsonPath('message', $first->json('message'));

        $this->assertSame(0, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
        $this->assertNull(TapReceipt::query()->value('log_absensi_id'));
    }

    public function test_kegagalan_internal_tidak_menjadi_keputusan_terminal(): void
    {
        $this->seedMesinDanMahasiswa();
        $eventId = (string) Str::uuid();
        $body = $this->body($eventId, '2026-06-04T07:40:00+07:00');

        $evaluator = new class extends AbsensiTapEvaluator
        {
            public bool $armed = true;

            public function evaluate(User $user, string $idMesin, ?Carbon $at = null): array
            {
                $result = parent::evaluate($user, $idMesin, $at);
                if ($this->armed) {
                    throw new RuntimeException('simulated internal');
                }

                return $result;
            }
        };
        $this->app->instance(AbsensiTapEvaluator::class, $evaluator);

        try {
            $failed = $this->tap($body);
            $failed->assertStatus(500)
                ->assertJsonPath('success', false)
                ->assertJsonPath('terminal', false)
                ->assertJsonPath('idempotent', false)
                ->assertJsonPath('code', 'INTERNAL');

            $this->assertSame(0, LogAbsensi::query()->count());
            $this->assertSame(0, TapReceipt::query()->count());

            $evaluator->armed = false;

            $this->tap($body)
                ->assertOk()
                ->assertJsonPath('idempotent', false)
                ->assertJsonPath('terminal', true)
                ->assertJsonPath('code', 'MASUK_OK');

            $this->assertSame(1, LogAbsensi::query()->count());
            $this->assertSame(1, TapReceipt::query()->count());
        } finally {
            $this->app->forgetInstance(AbsensiTapEvaluator::class);
        }
    }

    public function test_waktu_di_depan_jam_server_bukan_receipt_terminal(): void
    {
        $this->seedMesinDanMahasiswa();
        $eventId = (string) Str::uuid();
        $body = $this->body($eventId, '2026-06-04T09:40:00+07:00');

        $this->tap($body)
            ->assertStatus(422)
            ->assertJsonPath('terminal', false)
            ->assertJsonPath('code', 'WAKTU_TAP_INVALID');

        $this->assertSame(0, TapReceipt::query()->count());

        Carbon::setTestNow(Carbon::parse('2026-06-04 09:40:00', 'Asia/Jakarta'));

        $this->tap($body)
            ->assertOk()
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('code', 'MASUK_OK');

        $this->assertSame(1, LogAbsensi::query()->count());
        $this->assertSame(1, TapReceipt::query()->count());
    }

    public function test_firmware_lama_tanpa_event_id_tetap_didukung_tanpa_idempotensi(): void
    {
        $this->seedMesinDanMahasiswa();

        $first = $this->tap(['finger_id' => 7]);
        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'MASUK_OK')
            ->assertJsonPath('idempotency', 'unsupported')
            ->assertJsonPath('terminal', false)
            ->assertJsonPath('idempotent', false);

        Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));

        $second = $this->tap(['finger_id' => 7]);
        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('idempotency', 'unsupported')
            ->assertJsonPath('terminal', false)
            ->assertJsonPath('data.jenis', 'pulang');

        $this->assertSame(0, TapReceipt::query()->count());
        $this->assertSame(2, LogAbsensi::query()->count());
        $this->assertSame(['masuk', 'pulang'], LogAbsensi::query()->orderBy('id')->pluck('jenis')->all());
        $this->assertNull(LogAbsensi::query()->whereNotNull('event_id')->first());
    }

    public function test_event_id_berbeda_tetap_mencatat_pulang(): void
    {
        $this->seedMesinDanMahasiswa();
        Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));

        $this->tap($this->body((string) Str::uuid(), '2026-06-04T07:40:00+07:00'))
            ->assertOk()
            ->assertJsonPath('data.jenis', 'masuk');

        $this->tap($this->body((string) Str::uuid(), '2026-06-04T17:10:00+07:00'))
            ->assertOk()
            ->assertJsonPath('data.jenis', 'pulang');

        $this->assertSame(2, LogAbsensi::query()->count());
        $this->assertSame(2, TapReceipt::query()->count());
    }

    public function test_identitas_mesin_berasal_dari_autentikasi(): void
    {
        $this->seedMesinDanMahasiswa();
        $eventId = (string) Str::uuid();

        $this->tap([
            'finger_id' => 7,
            'event_id' => $eventId,
            'id_mesin' => 'MESIN-LAIN',
            'waktu_tap' => '2026-06-04T07:40:00+07:00',
        ])->assertStatus(422)->assertJsonPath('code', 'ID_MESIN_MISMATCH');

        $this->assertSame(0, LogAbsensi::query()->count());
        $this->assertSame(0, TapReceipt::query()->count());

        $this->tap([
            'finger_id' => 7,
            'event_id' => $eventId,
            'waktu_tap' => '2026-06-04T07:40:00+07:00',
        ])->assertOk()->assertJsonPath('code', 'MASUK_OK');

        $this->assertSame(self::MESIN_ID, LogAbsensi::query()->value('id_mesin'));
        $this->assertSame(self::MESIN_ID, TapReceipt::query()->value('id_mesin'));
    }

    public function test_replay_konkuren_tidak_membuat_log_atau_receipt_ganda(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'simalas-idempotency-'.bin2hex(random_bytes(4));
        $database = $dir.DIRECTORY_SEPARATOR.'simalas-idempotency-test.sqlite';
        $fixture = $dir.DIRECTORY_SEPARATOR.'fixture.json';
        $go = $dir.DIRECTORY_SEPARATOR.'go';
        $readyA = $dir.DIRECTORY_SEPARATOR.'a.ready';
        $readyB = $dir.DIRECTORY_SEPARATOR.'b.ready';

        mkdir($dir);
        try {
            config([
                'database.connections.idem' => [
                    'driver' => 'sqlite',
                    'database' => $database,
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                    'busy_timeout' => 8000,
                    'journal_mode' => 'wal',
                ],
            ]);

            if (! str_contains($database, 'simalas-idempotency-test') || config('database.default') !== 'sqlite') {
                $this->fail('Koneksi uji konkuren tidak aman.');
            }

            $this->artisan('migrate', [
                '--database' => 'idem',
                '--force' => true,
            ]);

            $this->assertTrue(Schema::connection('idem')->hasTable('tap_receipts'));

            $realNow = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'));
            $waktu = $realNow->modify('-1 minute')->format('Y-m-d\TH:i:sP');
            $eventId = (string) Str::uuid();
            $this->seedConcurrentFixture($waktu);

            file_put_contents($fixture, json_encode([
                'api_key' => self::API_KEY,
                'id_mesin' => self::MESIN_ID,
                'body' => [
                    'id_mesin' => self::MESIN_ID,
                    'finger_id' => 7,
                    'event_id' => $eventId,
                    'waktu_tap' => $waktu,
                ],
            ], JSON_THROW_ON_ERROR));

            DB::disconnect('idem');

            $first = $this->startWorker($database, $fixture, $readyA, $go);
            $second = $this->startWorker($database, $fixture, $readyB, $go);
            $this->waitUntilReady([$readyA, $readyB]);
            file_put_contents($go, 'go');

            $first->wait();
            $second->wait();

            $responses = [
                $this->decodeWorker($first),
                $this->decodeWorker($second),
            ];

            $this->assertSame(1, DB::connection('idem')->table('log_absensi')->count());
            $this->assertSame(1, DB::connection('idem')->table('tap_receipts')->count());
            $this->assertSame('masuk', DB::connection('idem')->table('log_absensi')->value('jenis'));
            $this->assertNull(DB::connection('idem')->table('absensi_harian')->value('pulang_log_id'));

            $ok = 0;
            foreach ($responses as $response) {
                $this->assertNotSame('pulang', $response['body']['data']['jenis'] ?? null);
                if (($response['body']['code'] ?? null) === 'MASUK_OK') {
                    $ok++;
                    $this->assertTrue($response['body']['terminal']);
                    $this->assertSame($eventId, $response['body']['event_id']);
                }
            }
            $this->assertGreaterThanOrEqual(1, $ok);
        } finally {
            DB::disconnect('idem');
            $this->deleteTree($dir);
        }
    }

    private function seedMesinDanMahasiswa(): User
    {
        MesinIot::query()->create([
            'id_mesin' => self::MESIN_ID,
            'nama_mesin' => 'Mesin uji',
            'tipe' => 'absensi',
            'api_key' => Hash::make(self::API_KEY),
            'is_active' => true,
        ]);

        return User::query()->create([
            'nama_lengkap' => 'Mahasiswa Uji',
            'nitk_nidn' => 'UJIIDEMP001',
            'email' => 'uji-idem@example.test',
            'password' => 'bukan-password-mesin',
            'role' => 'mahasiswa',
            'is_active' => true,
            'approval_status' => 'approved',
            'shift' => 'pagi',
            'finger_id' => 7,
            'finger_template' => base64_encode(str_repeat('A', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
        ]);
    }

    /**
     * @return array{finger_id: int, event_id: string, waktu_tap: string, id_mesin: string}
     */
    private function body(string $eventId, string $waktuTap): array
    {
        return [
            'id_mesin' => self::MESIN_ID,
            'finger_id' => 7,
            'event_id' => $eventId,
            'waktu_tap' => $waktuTap,
        ];
    }

    private function tap(array $body)
    {
        return $this->withHeaders([
            'X-API-KEY' => self::API_KEY,
            'X-ID-MESIN' => self::MESIN_ID,
            'Accept' => 'application/json',
        ])->postJson('/api/mesin/tap', $body);
    }

    private function seedConcurrentFixture(string $waktu): void
    {
        DB::connection('idem')->table('absensi_sesi_settings')->updateOrInsert(
            ['shift' => 'pagi'],
            [
                'jam_masuk' => '00:00:00',
                'jam_mulai_tap_masuk' => '00:00:00',
                'jam_pulang' => '23:59:59',
                'toleransi_masuk_menit' => 15,
                'toleransi_pulang_menit' => 15,
                'grace_alfa_menit' => 120,
                'aktif' => 1,
                'timezone' => 'Asia/Jakarta',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $mesin = new MesinIot([
            'id_mesin' => self::MESIN_ID,
            'nama_mesin' => 'Mesin uji',
            'tipe' => 'absensi',
            'api_key' => Hash::make(self::API_KEY),
            'is_active' => true,
        ]);
        $mesin->setConnection('idem');
        $mesin->save();

        $user = new User([
            'nama_lengkap' => 'Mahasiswa Uji',
            'nitk_nidn' => 'UJIIDEMP001',
            'email' => 'uji-idem@example.test',
            'password' => 'bukan-password-mesin',
            'role' => 'mahasiswa',
            'is_active' => true,
            'approval_status' => 'approved',
            'shift' => 'pagi',
            'finger_id' => 7,
            'finger_template' => base64_encode(str_repeat('A', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
        ]);
        $user->setConnection('idem');
        $user->save();

        $this->assertNotSame('', $waktu);
    }

    private function startWorker(string $database, string $fixture, string $ready, string $go): Process
    {
        $env = getenv();
        $clean = [];
        if (is_array($env)) {
            foreach ($env as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $clean[$key] = $value;
                }
            }
        }

        $clean['APP_ENV'] = 'testing';
        $clean['APP_KEY'] = (string) config('app.key');
        $clean['DB_CONNECTION'] = 'sqlite';
        $clean['DB_DATABASE'] = $database;
        $clean['DB_URL'] = '';
        $clean['SIMALAS_TAP_FIXTURE'] = $fixture;
        $clean['SIMALAS_TAP_READY'] = $ready;
        $clean['SIMALAS_TAP_GO'] = $go;
        $clean['SIMALAS_TAP_RACE_DELAY_US'] = '250000';
        $clean['BCRYPT_ROUNDS'] = '4';
        $clean['CACHE_STORE'] = 'array';
        $clean['QUEUE_CONNECTION'] = 'sync';
        $clean['SESSION_DRIVER'] = 'array';

        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/idempotent_tap_worker.php'),
        ], base_path());
        $process->setEnv($clean);
        $process->setTimeout(60);
        $process->start();

        return $process;
    }

    /**
     * @param  list<string>  $paths
     */
    private function waitUntilReady(array $paths): void
    {
        $deadline = microtime(true) + 20;
        do {
            $ready = true;
            foreach ($paths as $path) {
                if (! is_file($path)) {
                    $ready = false;
                }
            }
            if ($ready) {
                return;
            }
            usleep(2000);
        } while (microtime(true) < $deadline);

        $this->fail('Worker tidak siap.');
    }

    /**
     * @return array{http: int, body: array<string, mixed>}
     */
    private function decodeWorker(Process $process): array
    {
        $raw = $process->getOutput();
        $pos = strpos($raw, '{');
        $decoded = $pos === false ? null : json_decode(substr($raw, $pos), true);
        if (! is_array($decoded) || ! isset($decoded['http'], $decoded['body']) || ! is_array($decoded['body'])) {
            $this->fail("Worker gagal ({$process->getExitCode()}): {$raw}\n".$process->getErrorOutput());
        }

        return $decoded;
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
