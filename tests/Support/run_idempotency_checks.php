<?php

/**
 * Menjalankan acceptance idempotensi pada sqlite.
 * PHPUnit 12 di proyek ini membutuhkan PHP 8.3; mesin ini memakai PHP 8.2,
 * jadi harness ini mem-boot Laravel langsung tanpa menyentuh MySQL operasional.
 */

use App\Models\AbsensiSesiSetting;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\TapReceipt;
use App\Models\User;
use App\Support\AbsensiTapEvaluator;
use App\Support\FingerprintTemplateCodec;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

const API_KEY = 'kunci-uji-idempotensi';
const MESIN_ID = 'ABSEN-UJI';

$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "PASS  {$label}\n";

        return;
    }

    $failures++;
    echo "FAIL  {$label}\n";
}

function bootApp(): Illuminate\Foundation\Application
{
    require_once __DIR__.'/../../vendor/autoload.php';

    foreach ([
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'BCRYPT_ROUNDS' => '4',
    ] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

function postJson($app, string $uri, array $body = [], array $headers = []): array
{
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    $request = Request::create($uri, 'POST', $body, [], [], $server, json_encode($body));
    if ($uri === '/api/mesin/tap' && $body === [] && ! isset($headers['X-API-KEY'])) {
        $request = Request::create($uri, 'POST', $body);
    }

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $decoded = json_decode($response->getContent(), true);

    return [
        'http' => $response->getStatusCode(),
        'body' => is_array($decoded) ? $decoded : [],
    ];
}

function getJson($app, string $uri): array
{
    $request = Request::create($uri, 'GET');
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $decoded = json_decode($response->getContent(), true);

    return [
        'http' => $response->getStatusCode(),
        'body' => is_array($decoded) ? $decoded : [],
    ];
}

function postTap($app, array $body): array
{
    return postJson($app, '/api/mesin/tap', $body, [
        'X-API-KEY' => API_KEY,
        'X-ID-MESIN' => MESIN_ID,
    ]);
}

function seedLab(): User
{
    MesinIot::query()->updateOrCreate(
        ['id_mesin' => MESIN_ID],
        [
            'nama_mesin' => 'Mesin uji',
            'tipe' => 'absensi',
            'api_key' => Hash::make(API_KEY),
            'is_active' => true,
        ]
    );

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

    return User::query()->updateOrCreate(
        ['nitk_nidn' => 'UJIIDEMP001'],
        [
            'nama_lengkap' => 'Mahasiswa Uji',
            'email' => 'uji-idem@example.test',
            'password' => 'bukan-password-mesin',
            'role' => 'mahasiswa',
            'is_active' => true,
            'approval_status' => 'approved',
            'shift' => 'pagi',
            'finger_id' => 7,
            'finger_template' => base64_encode(str_repeat('A', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
        ]
    );
}

function scenario(string $name, callable $callback): void
{
    DB::beginTransaction();
    try {
        $callback();
        check(true, $name);
    } catch (Throwable $e) {
        check(false, $name.' — '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}

function expect(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$app = bootApp();

$default = (string) config('database.default');
$database = (string) config('database.connections.'.$default.'.database');
if ($default !== 'sqlite' || $database !== ':memory:') {
    fwrite(STDERR, "Dibatalkan. Koneksi {$default} [{$database}] bukan sqlite :memory:.\n");
    exit(2);
}

echo "DB  {$default} {$database}\n";

$missingKey = postJson($app, '/api/mesin/tap', ['id_mesin' => 'MESIN_ABSENSI_01']);
check($missingKey['http'] === 401 && ($missingKey['body']['success'] ?? null) === false, 'auth tap tanpa API key');

$missingId = postJson($app, '/api/mesin/tap', [], ['X-API-KEY' => 'kunci-yang-tidak-pernah-terdaftar']);
check($missingId['http'] === 422 && ($missingId['body']['success'] ?? null) === false, 'auth tap tanpa id mesin');

$loker = postJson($app, '/api/mesin/loker', ['id_mesin' => 'MESIN_LOKER_01']);
check($loker['http'] === 404, 'route loker sudah dilepas');

$sync = getJson($app, '/api/mesin/fingerprint/sync?id_mesin=MESIN_ABSENSI_01');
check($sync['http'] === 401, 'auth sync sidik jari tanpa API key');

$migrate = Artisan::call('migrate', ['--force' => true]);
if ($migrate !== 0) {
    fwrite(STDERR, Artisan::output()."\n");
    exit(3);
}
expect(Schema::hasTable('tap_receipts'), 'tabel tap_receipts tidak terbentuk di sqlite');
echo "PASS  migration sqlite :memory:\n";

Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));

scenario('replay event sama satu log dan keputusan pertama', function () use ($app) {
    seedLab();
    $eventId = (string) Str::uuid();
    $body = [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ];
    $first = postTap($app, $body);
    expect($first['http'] === 200, 'http pertama '.$first['http'].' '.json_encode($first['body']));
    expect(($first['body']['code'] ?? null) === 'MASUK_OK', 'kode pertama');
    expect(($first['body']['idempotent'] ?? null) === false, 'idempotent pertama');
    expect(($first['body']['terminal'] ?? null) === true, 'terminal pertama');
    $replay = postTap($app, $body);
    expect($replay['http'] === 200 && ($replay['body']['idempotent'] ?? null) === true, 'replay');
    expect(($replay['body']['code'] ?? null) === 'MASUK_OK', 'kode replay');
    expect(($replay['body']['data']['log_id'] ?? null) === ($first['body']['data']['log_id'] ?? null), 'log id replay');
    expect(LogAbsensi::query()->count() === 1, 'jumlah log');
    expect(TapReceipt::query()->count() === 1, 'jumlah receipt');
    expect(LogAbsensi::query()->value('jenis') === 'masuk', 'jenis');
    expect(DB::table('absensi_harian')->value('pulang_log_id') === null, 'bukan pulang');
});

scenario('replay tidak menjadi pulang', function () use ($app) {
    seedLab();
    $eventId = (string) Str::uuid();
    $body = ['finger_id' => 7, 'event_id' => $eventId];
    $first = postTap($app, $body);
    expect(($first['body']['code'] ?? null) === 'MASUK_OK', 'masuk '.json_encode($first['body']));
    Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));
    $replay = postTap($app, $body);
    expect(($replay['body']['code'] ?? null) === 'MASUK_OK', 'replay kode '.json_encode($replay['body']));
    expect(($replay['body']['idempotent'] ?? null) === true, 'replay idempotent');
    expect(LogAbsensi::query()->count() === 1, 'satu log');
    expect(LogAbsensi::query()->value('jenis') === 'masuk', 'tetap masuk');
    expect(DB::table('log_absensi')->value('waktu_tap') === '2026-06-04 07:40:00', 'waktu asli '.DB::table('log_absensi')->value('waktu_tap'));
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
});

scenario('payload berbeda adalah konflik', function () use ($app) {
    seedLab();
    $eventId = (string) Str::uuid();
    postTap($app, [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ]);
    $conflict = postTap($app, [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:41:00+07:00',
    ]);
    expect($conflict['http'] === 409 && ($conflict['body']['code'] ?? null) === 'EVENT_CONFLICT', json_encode($conflict));
    $finger = postTap($app, [
        'id_mesin' => MESIN_ID,
        'finger_id' => 8,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ]);
    expect($finger['http'] === 409, 'konflik finger');
    expect(LogAbsensi::query()->count() === 1 && TapReceipt::query()->count() === 1, 'tidak bertambah');
    expect(TapReceipt::query()->value('code') === 'MASUK_OK', 'receipt asli');
});

scenario('penolakan bisnis tersimpan dan konsisten', function () use ($app) {
    $user = seedLab();
    $user->forceFill(['is_active' => false])->save();
    $eventId = (string) Str::uuid();
    $body = [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ];
    $first = postTap($app, $body);
    expect($first['http'] === 403 && ($first['body']['code'] ?? null) === 'AKUN_TIDAK_AKTIF', json_encode($first['body']));
    expect(($first['body']['terminal'] ?? null) === true, 'terminal bisnis');
    $user->forceFill(['is_active' => true])->save();
    $replay = postTap($app, $body);
    expect($replay['http'] === 403 && ($replay['body']['idempotent'] ?? null) === true, json_encode($replay['body']));
    expect(($replay['body']['code'] ?? null) === 'AKUN_TIDAK_AKTIF', 'kode beku');
    expect(LogAbsensi::query()->count() === 0 && TapReceipt::query()->count() === 1, 'receipt tanpa log');
});

scenario('kegagalan internal bukan keputusan terminal', function () use ($app) {
    seedLab();
    $eventId = (string) Str::uuid();
    $body = [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ];
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
    $app->instance(AbsensiTapEvaluator::class, $evaluator);
    try {
        $failed = postTap($app, $body);
        expect($failed['http'] === 500 && ($failed['body']['terminal'] ?? null) === false, json_encode($failed['body']));
        expect(($failed['body']['code'] ?? null) === 'INTERNAL', 'kode internal');
        expect(LogAbsensi::query()->count() === 0 && TapReceipt::query()->count() === 0, 'tidak ada sisa');
        $evaluator->armed = false;
        $ok = postTap($app, $body);
        expect($ok['http'] === 200 && ($ok['body']['code'] ?? null) === 'MASUK_OK', json_encode($ok['body']));
        expect(LogAbsensi::query()->count() === 1 && TapReceipt::query()->count() === 1, 'retry berhasil');
    } finally {
        $app->forgetInstance(AbsensiTapEvaluator::class);
    }
});

scenario('waktu di depan jam server tidak membekukan receipt', function () use ($app) {
    seedLab();
    $eventId = (string) Str::uuid();
    $body = [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T09:40:00+07:00',
    ];
    $early = postTap($app, $body);
    expect($early['http'] === 422 && ($early['body']['terminal'] ?? null) === false, json_encode($early['body']));
    expect(TapReceipt::query()->count() === 0, 'belum ada receipt');
    Carbon::setTestNow(Carbon::parse('2026-06-04 09:40:00', 'Asia/Jakarta'));
    $later = postTap($app, $body);
    expect($later['http'] === 200 && ($later['body']['code'] ?? null) === 'MASUK_OK', json_encode($later['body']));
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
});

scenario('firmware lama tanpa event_id tetap jalan dan tidak idempoten', function () use ($app) {
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    seedLab();
    $first = postTap($app, ['finger_id' => 7]);
    expect($first['http'] === 200 && ($first['body']['code'] ?? null) === 'MASUK_OK', json_encode($first['body']));
    expect(($first['body']['idempotency'] ?? null) === 'unsupported', 'penanda jalur lama');
    expect(($first['body']['terminal'] ?? null) === false, 'tanpa ack tersimpan');
    Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));
    $second = postTap($app, ['finger_id' => 7]);
    expect(($second['body']['data']['jenis'] ?? null) === 'pulang', json_encode($second['body']));
    expect(TapReceipt::query()->count() === 0, 'tanpa receipt');
    expect(LogAbsensi::query()->count() === 2, 'dua log');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
});

scenario('event_id berbeda tetap mencatat pulang', function () use ($app) {
    seedLab();
    Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));
    $masuk = postTap($app, [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => (string) Str::uuid(),
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ]);
    $pulang = postTap($app, [
        'id_mesin' => MESIN_ID,
        'finger_id' => 7,
        'event_id' => (string) Str::uuid(),
        'waktu_tap' => '2026-06-04T17:10:00+07:00',
    ]);
    expect(($masuk['body']['data']['jenis'] ?? null) === 'masuk', json_encode($masuk['body']));
    expect(($pulang['body']['data']['jenis'] ?? null) === 'pulang', json_encode($pulang['body']));
    expect(LogAbsensi::query()->count() === 2 && TapReceipt::query()->count() === 2, 'dua keputusan');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
});

scenario('identitas mesin dari autentikasi', function () use ($app) {
    seedLab();
    $eventId = (string) Str::uuid();
    $mismatch = postTap($app, [
        'finger_id' => 7,
        'event_id' => $eventId,
        'id_mesin' => 'MESIN-LAIN',
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ]);
    expect($mismatch['http'] === 422 && ($mismatch['body']['code'] ?? null) === 'ID_MESIN_MISMATCH', json_encode($mismatch['body']));
    expect(LogAbsensi::query()->count() === 0 && TapReceipt::query()->count() === 0, 'mismatch tanpa tulis');
    $ok = postTap($app, [
        'finger_id' => 7,
        'event_id' => $eventId,
        'waktu_tap' => '2026-06-04T07:40:00+07:00',
    ]);
    expect($ok['http'] === 200, json_encode($ok['body']));
    expect(LogAbsensi::query()->value('id_mesin') === MESIN_ID, 'id dari auth');
    expect(TapReceipt::query()->value('id_mesin') === MESIN_ID, 'receipt dari auth');
});

echo "PASS  skenario berurutan selesai\n";

$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'simalas-idempotency-'.bin2hex(random_bytes(4));
$sqlite = $dir.DIRECTORY_SEPARATOR.'simalas-idempotency-test.sqlite';
$fixture = $dir.DIRECTORY_SEPARATOR.'fixture.json';
$go = $dir.DIRECTORY_SEPARATOR.'go';
mkdir($dir);

try {
    config([
        'database.connections.idem' => [
            'driver' => 'sqlite',
            'database' => $sqlite,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 8000,
            'journal_mode' => 'wal',
        ],
    ]);
    $exit = Artisan::call('migrate', ['--database' => 'idem', '--force' => true]);
    expect($exit === 0, 'migrate file sqlite gagal '.Artisan::output());
    expect(Schema::connection('idem')->hasTable('tap_receipts'), 'receipt file');

    $realNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $waktu = $realNow->modify('-1 minute')->format('Y-m-d\TH:i:sP');
    $eventId = (string) Str::uuid();

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
        'id_mesin' => MESIN_ID,
        'nama_mesin' => 'Mesin uji',
        'tipe' => 'absensi',
        'api_key' => Hash::make(API_KEY),
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

    file_put_contents($fixture, json_encode([
        'api_key' => API_KEY,
        'id_mesin' => MESIN_ID,
        'body' => [
            'id_mesin' => MESIN_ID,
            'finger_id' => 7,
            'event_id' => $eventId,
            'waktu_tap' => $waktu,
        ],
    ], JSON_THROW_ON_ERROR));

    DB::disconnect('idem');

    $spawn = function (string $ready) use ($sqlite, $fixture, $go, $app): Process {
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
        $clean['DB_DATABASE'] = $sqlite;
        $clean['DB_URL'] = '';
        $clean['SIMALAS_TAP_FIXTURE'] = $fixture;
        $clean['SIMALAS_TAP_READY'] = $ready;
        $clean['SIMALAS_TAP_GO'] = $go;
        $clean['SIMALAS_TAP_RACE_DELAY_US'] = '250000';
        $clean['BCRYPT_ROUNDS'] = '4';
        $process = new Process([PHP_BINARY, __DIR__.'/idempotent_tap_worker.php'], dirname(__DIR__, 2));
        $process->setEnv($clean);
        $process->setTimeout(60);
        $process->start();

        return $process;
    };

    $readyA = $dir.DIRECTORY_SEPARATOR.'a.ready';
    $readyB = $dir.DIRECTORY_SEPARATOR.'b.ready';
    $first = $spawn($readyA);
    $second = $spawn($readyB);
    $deadline = microtime(true) + 20;
    while (! is_file($readyA) || ! is_file($readyB)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('worker tidak siap '.$first->getErrorOutput().$second->getErrorOutput());
        }
        usleep(2000);
    }
    file_put_contents($go, 'go');
    $first->wait();
    $second->wait();

    $decode = function (Process $process): array {
        $raw = $process->getOutput();
        $pos = strpos($raw, '{');
        $decoded = $pos === false ? null : json_decode(substr($raw, $pos), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('worker '.$process->getExitCode().' '.$raw.' '.$process->getErrorOutput());
        }

        return $decoded;
    };

    $responses = [$decode($first), $decode($second)];
    expect(DB::connection('idem')->table('log_absensi')->count() === 1, 'log konkuren');
    expect(DB::connection('idem')->table('tap_receipts')->count() === 1, 'receipt konkuren');
    expect(DB::connection('idem')->table('log_absensi')->value('jenis') === 'masuk', 'jenis konkuren');
    expect(DB::connection('idem')->table('absensi_harian')->value('pulang_log_id') === null, 'pulang konkuren');
    $ok = 0;
    foreach ($responses as $response) {
        expect(($response['body']['data']['jenis'] ?? null) !== 'pulang', 'respons bukan pulang');
        if (($response['body']['code'] ?? null) === 'MASUK_OK') {
            $ok++;
        }
    }
    expect($ok >= 1, 'tidak ada MASUK_OK '.json_encode($responses));
    check(true, 'replay konkuren satu log dan satu receipt');
} catch (Throwable $e) {
    check(false, 'replay konkuren — '.$e->getMessage());
} finally {
    DB::disconnect('idem');
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        @unlink($dir.DIRECTORY_SEPARATOR.$item);
    }
    @rmdir($dir);
}

echo $failures === 0 ? "HASIL lulus\n" : "HASIL gagal {$failures}\n";
exit($failures === 0 ? 0 : 1);
