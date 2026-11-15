<?php

/**
 * Uji perilaku idempotensi pada MySQL simalas_cleanup_test saja.
 * Menolak database simalas. Tidak mencetak NIM, email, template, atau API key.
 */

use App\Models\AbsensiSesiSetting;
use App\Models\MesinIot;
use App\Models\User;
use App\Support\AbsensiTapEvaluator;
use App\Support\FingerprintTemplateCodec;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

const TEST_DB = 'simalas_cleanup_test';
const FORBIDDEN_DB = 'simalas';
const MESIN_ID = 'ABSEN-UJI-MYSQL';
const NITK = 'UJI-IDEMP-MYSQL';
const FINGER = 9001;
const API_KEY = 'kunci-uji-mysql-idem';

$failures = 0;
$sesiSnapshot = null;

function fail(string $label): void
{
    global $failures;
    $failures++;
    echo "FAIL  {$label}\n";
}

function pass(string $label): void
{
    echo "PASS  {$label}\n";
}

function refuse(string $reason): void
{
    fwrite(STDERR, "Ditolak: {$reason}\n");
    exit(2);
}

function expect(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}

function assertTestDb(): void
{
    $configured = (string) config('database.connections.mysql.database');
    $host = (string) config('database.connections.mysql.host');
    $current = (string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '');
    if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
        refuse('host bukan lokal');
    }
    if ($configured === FORBIDDEN_DB || $current === FORBIDDEN_DB) {
        refuse('terhubung ke database operasional');
    }
    if ($configured !== TEST_DB || $current !== TEST_DB) {
        refuse('bukan database uji');
    }
}

function digest(string $table, array $columns, ?callable $restrict = null): array
{
    assertTestDb();
    $query = DB::table($table);
    if ($restrict) {
        $restrict($query);
    }
    $order = in_array('id', $columns, true) ? 'id' : $columns[0];
    $rows = $query->orderBy($order)->get($columns);
    $ctx = hash_init('sha256');
    foreach ($rows as $row) {
        foreach ($columns as $column) {
            $value = $row->{$column};
            hash_update($ctx, $value === null ? "\0" : (string) $value);
            hash_update($ctx, "\x1e");
        }
        hash_update($ctx, "\x1f");
    }

    return ['jumlah' => $rows->count(), 'sha256' => hash_final($ctx)];
}

function templateChecksum(): array
{
    assertTestDb();
    $rows = DB::table('users')->orderBy('id')->get(['id', 'finger_id', 'finger_template']);
    $ctx = hash_init('sha256');
    $withId = 0;
    $withTemplate = 0;
    foreach ($rows as $row) {
        if ($row->finger_id !== null && $row->finger_id !== '') {
            $withId++;
        }
        $raw = $row->finger_template;
        $present = $raw !== null && $raw !== '';
        if ($present) {
            $withTemplate++;
        }
        hash_update($ctx, (string) $row->id.'|'.($row->finger_id ?? '').'|');
        hash_update($ctx, $present ? hash('sha256', (string) $raw, true) : "\0");
        hash_update($ctx, "\x1f");
    }

    return [
        'jumlah' => $rows->count().'/'.$withId.'/'.$withTemplate,
        'sha256' => hash_final($ctx),
    ];
}

function postTap(array $body): array
{
    assertTestDb();
    $server = [
        'HTTP_X_API_KEY' => API_KEY,
        'HTTP_X_ID_MESIN' => MESIN_ID,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ];
    $request = Request::create('/api/mesin/tap', 'POST', [], [], [], $server, json_encode($body));
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $decoded = json_decode($response->getContent(), true);

    return [
        'http' => $response->getStatusCode(),
        'code' => is_array($decoded) ? ($decoded['code'] ?? null) : null,
        'idempotent' => is_array($decoded) ? ($decoded['idempotent'] ?? null) : null,
        'terminal' => is_array($decoded) ? ($decoded['terminal'] ?? null) : null,
        'idempotency' => is_array($decoded) ? ($decoded['idempotency'] ?? null) : null,
        'jenis' => is_array($decoded) ? ($decoded['data']['jenis'] ?? null) : null,
        'log_id' => is_array($decoded) ? ($decoded['data']['log_id'] ?? null) : null,
        'event_id' => is_array($decoded) ? ($decoded['event_id'] ?? null) : null,
    ];
}

function applyPagiWindow(string $masuk, string $mulai, string $pulang): void
{
    assertTestDb();
    DB::table('absensi_sesi_settings')->where('shift', 'pagi')->update([
        'jam_masuk' => $masuk,
        'jam_mulai_tap_masuk' => $mulai,
        'jam_pulang' => $pulang,
        'toleransi_masuk_menit' => 15,
        'toleransi_pulang_menit' => 15,
        'aktif' => 1,
        'timezone' => 'Asia/Jakarta',
    ]);
}

function seedLab(bool $active = true): User
{
    assertTestDb();
    MesinIot::query()->updateOrCreate(
        ['id_mesin' => MESIN_ID],
        [
            'nama_mesin' => 'Mesin uji mysql',
            'tipe' => 'absensi',
            'api_key' => Hash::make(API_KEY),
            'is_active' => true,
        ]
    );

    return User::query()->updateOrCreate(
        ['nitk_nidn' => NITK],
        [
            'nama_lengkap' => 'Mahasiswa Uji MySQL',
            'email' => 'uji-idemp-mysql@example.test',
            'password' => 'bukan-password-mesin',
            'role' => 'mahasiswa',
            'is_active' => $active,
            'approval_status' => 'approved',
            'shift' => 'pagi',
            'finger_id' => FINGER,
            'finger_template' => base64_encode(str_repeat('A', FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
            'email_verified_at' => now(),
        ]
    );
}

function cleanupSynthetic(): void
{
    assertTestDb();
    $userIds = DB::table('users')->where('nitk_nidn', NITK)->orWhere('finger_id', FINGER)->pluck('id');
    if ($userIds->isNotEmpty()) {
        $logIds = DB::table('log_absensi')->whereIn('user_id', $userIds)->pluck('id');
        if (SchemaHas('tap_receipts')) {
            DB::table('tap_receipts')->whereIn('log_absensi_id', $logIds)->orWhere('finger_id', FINGER)->orWhere('id_mesin', MESIN_ID)->delete();
        }
        foreach (['absensi_tidak_lengkap', 'absensi_alfa', 'absensi_harian', 'log_absensi'] as $table) {
            if (SchemaHas($table) && SchemaHasColumn($table, 'user_id')) {
                DB::table($table)->whereIn('user_id', $userIds)->delete();
            }
        }
        DB::table('users')->whereIn('id', $userIds)->delete();
    } elseif (SchemaHas('tap_receipts')) {
        DB::table('tap_receipts')->where('id_mesin', MESIN_ID)->orWhere('finger_id', FINGER)->delete();
    }
    DB::table('mesin_iot')->where('id_mesin', MESIN_ID)->delete();
}

function SchemaHas(string $table): bool
{
    return Illuminate\Support\Facades\Schema::hasTable($table);
}

function SchemaHasColumn(string $table, string $column): bool
{
    return Illuminate\Support\Facades\Schema::hasColumn($table, $column);
}

function scenario(string $name, callable $callback): void
{
    assertTestDb();
    DB::beginTransaction();
    try {
        $callback();
        pass($name);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+/i', $message)) {
            $message = 'detail disembunyikan';
        }
        fail($name.' — '.$message);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        Carbon::setTestNow();
        app()->forgetInstance(AbsensiTapEvaluator::class);
    }
}

function body(string $eventId, string $waktu, ?int $finger = null): array
{
    return [
        'id_mesin' => MESIN_ID,
        'finger_id' => $finger ?? FINGER,
        'event_id' => $eventId,
        'waktu_tap' => $waktu,
    ];
}

$root = dirname(__DIR__, 2);
$envPath = $root.'\\.env';
$envText = (string) file_get_contents($envPath);
$env = [];
foreach (preg_split("/\r\n|\n|\r/", preg_replace('/^\xEF\xBB\xBF/', '', $envText) ?? $envText) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $value = trim($value);
    if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
        $value = stripcslashes(substr($value, 1, -1));
    }
    $env[trim($key)] = $value;
}
if (! in_array($env['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true)) {
    refuse('host env bukan lokal');
}

foreach ([
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => (string) ($env['DB_PORT'] ?? '3306'),
    'DB_DATABASE' => TEST_DB,
    'DB_USERNAME' => (string) ($env['DB_USERNAME'] ?? ''),
    'DB_PASSWORD' => (string) ($env['DB_PASSWORD'] ?? ''),
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require_once $root.'\\vendor\\autoload.php';
$app = require $root.'\\bootstrap\\app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'mysql',
    'database.connections.mysql.url' => null,
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => (string) ($env['DB_PORT'] ?? '3306'),
    'database.connections.mysql.database' => TEST_DB,
    'database.connections.mysql.username' => (string) ($env['DB_USERNAME'] ?? ''),
    'database.connections.mysql.password' => (string) ($env['DB_PASSWORD'] ?? ''),
    'mail.default' => 'array',
]);
DB::purge('mysql');
DB::reconnect('mysql');
assertTestDb();
if (! Illuminate\Support\Facades\Schema::hasTable('tap_receipts')) {
    refuse('database uji belum punya tap_receipts');
}
echo 'TARGET '.TEST_DB."\n";
if (DB::table('users')->where('finger_id', FINGER)->where('nitk_nidn', '!=', NITK)->exists()) {
    refuse('slot sintetis sudah dipakai di database uji');
}
cleanupSynthetic();

$beforeUsers = digest('users', Illuminate\Support\Facades\Schema::getColumnListing('users'));
$beforeTemplate = templateChecksum();
$beforeLog = digest('log_absensi', array_values(array_diff(
    Illuminate\Support\Facades\Schema::getColumnListing('log_absensi'),
    ['event_id', 'diterima_pada']
)));
$beforeHarian = digest('absensi_harian', Illuminate\Support\Facades\Schema::getColumnListing('absensi_harian'));
$beforeSesi = digest('absensi_sesi_settings', Illuminate\Support\Facades\Schema::getColumnListing('absensi_sesi_settings'));
$sesiSnapshot = (array) DB::table('absensi_sesi_settings')->where('shift', 'pagi')->first();

scenario('replay event sama', function () {
    applyPagiWindow('07:50:00', '06:30:00', '17:00:00');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    $user = seedLab();
    $eventId = (string) Str::uuid();
    $payload = body($eventId, '2026-06-04T07:40:00+07:00');
    $first = postTap($payload);
    expect($first['http'] === 200 && $first['code'] === 'MASUK_OK', 'pertama '.$first['http'].' '.$first['code']);
    expect($first['idempotent'] === false && $first['terminal'] === true && $first['idempotency'] === 'enforced', 'bendera pertama');
    $replay = postTap($payload);
    expect($replay['http'] === 200 && $replay['idempotent'] === true && $replay['code'] === 'MASUK_OK', 'replay '.$replay['code']);
    expect($replay['log_id'] === $first['log_id'] && $replay['jenis'] === 'masuk', 'log replay');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->count() === 1, 'satu log');
    expect(DB::table('tap_receipts')->where('event_id', $eventId)->count() === 1, 'satu receipt');
});

scenario('replay tidak berubah saat jam server bergeser', function () {
    applyPagiWindow('07:50:00', '06:30:00', '17:00:00');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    $user = seedLab();
    $eventId = (string) Str::uuid();
    $payload = [
        'finger_id' => FINGER,
        'event_id' => $eventId,
    ];
    expect(postTap($payload)['code'] === 'MASUK_OK', 'masuk awal');
    Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));
    $replay = postTap($payload);
    expect($replay['idempotent'] === true && $replay['code'] === 'MASUK_OK' && $replay['jenis'] === 'masuk', 'tetap masuk');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->where('jenis', 'pulang')->count() === 0, 'bukan pulang');
});

scenario('konflik payload', function () {
    applyPagiWindow('07:50:00', '06:30:00', '17:00:00');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    $user = seedLab();
    $eventId = (string) Str::uuid();
    expect(postTap(body($eventId, '2026-06-04T07:40:00+07:00'))['code'] === 'MASUK_OK', 'asli');
    $conflict = postTap(body($eventId, '2026-06-04T07:41:00+07:00'));
    expect($conflict['http'] === 409 && $conflict['code'] === 'EVENT_CONFLICT' && $conflict['terminal'] === true, 'konflik waktu');
    $fingerConflict = postTap(body($eventId, '2026-06-04T07:40:00+07:00', FINGER + 1));
    expect($fingerConflict['http'] === 409 && $fingerConflict['code'] === 'EVENT_CONFLICT', 'konflik finger');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->count() === 1, 'log tetap satu');
    expect(DB::table('tap_receipts')->where('event_id', $eventId)->where('code', 'MASUK_OK')->count() === 1, 'receipt pertama');
});

scenario('penolakan bisnis tersimpan', function () {
    applyPagiWindow('07:50:00', '06:30:00', '17:00:00');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    $user = seedLab(false);
    $eventId = (string) Str::uuid();
    $payload = body($eventId, '2026-06-04T07:40:00+07:00');
    $first = postTap($payload);
    expect($first['http'] === 403 && $first['code'] === 'AKUN_TIDAK_AKTIF' && $first['terminal'] === true && $first['idempotent'] === false, 'tolak');
    $user->forceFill(['is_active' => true])->save();
    $replay = postTap($payload);
    expect($replay['http'] === 403 && $replay['idempotent'] === true && $replay['code'] === 'AKUN_TIDAK_AKTIF', 'replay tolak');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->count() === 0, 'tanpa log');
    expect(DB::table('tap_receipts')->where('event_id', $eventId)->whereNull('log_absensi_id')->count() === 1, 'receipt tanpa log');
});

scenario('rollback internal tidak terminal', function () {
    applyPagiWindow('07:50:00', '06:30:00', '17:00:00');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    $user = seedLab();
    $eventId = (string) Str::uuid();
    $payload = body($eventId, '2026-06-04T07:40:00+07:00');
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
    app()->instance(AbsensiTapEvaluator::class, $evaluator);
    $failed = postTap($payload);
    expect($failed['http'] === 500 && $failed['code'] === 'INTERNAL' && $failed['terminal'] === false && $failed['idempotent'] === false, 'internal');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->count() === 0, 'log tergulung');
    expect(DB::table('tap_receipts')->where('event_id', $eventId)->count() === 0, 'receipt tergulung');
    $evaluator->armed = false;
    $ok = postTap($payload);
    expect($ok['http'] === 200 && $ok['code'] === 'MASUK_OK' && $ok['idempotent'] === false && $ok['terminal'] === true, 'ulang setelah pulih');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->count() === 1, 'satu log setelah pulih');
});

scenario('firmware lama tanpa event_id', function () {
    applyPagiWindow('07:50:00', '06:30:00', '17:00:00');
    Carbon::setTestNow(Carbon::parse('2026-06-04 07:40:00', 'Asia/Jakarta'));
    $user = seedLab();
    $first = postTap(['finger_id' => FINGER]);
    expect($first['http'] === 200 && $first['code'] === 'MASUK_OK' && $first['idempotency'] === 'unsupported' && $first['terminal'] === false, 'masuk lama');
    Carbon::setTestNow(Carbon::parse('2026-06-04 17:10:00', 'Asia/Jakarta'));
    $second = postTap(['finger_id' => FINGER]);
    expect($second['http'] === 200 && $second['idempotency'] === 'unsupported' && $second['jenis'] === 'pulang' && $second['terminal'] === false, 'pulang lama');
    expect(DB::table('tap_receipts')->where('finger_id', FINGER)->count() === 0, 'tanpa receipt');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->orderBy('id')->pluck('jenis')->all() === ['masuk', 'pulang'], 'dua jenis');
    expect(DB::table('log_absensi')->where('user_id', $user->id)->whereNotNull('event_id')->count() === 0, 'event kosong');
});

$concurrentOk = false;
try {
    assertTestDb();
    cleanupSynthetic();
    applyPagiWindow('00:00:00', '00:00:00', '23:59:59');
    seedLab();
    $eventId = (string) Str::uuid();
    $waktu = now('Asia/Jakarta')->subMinute()->format('Y-m-d\TH:i:sP');
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'simalas-idem-mysql-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $fixture = $dir.DIRECTORY_SEPARATOR.'fixture.json';
    $go = $dir.DIRECTORY_SEPARATOR.'go';
    $readyA = $dir.DIRECTORY_SEPARATOR.'a.ready';
    $readyB = $dir.DIRECTORY_SEPARATOR.'b.ready';
    file_put_contents($fixture, json_encode([
        'api_key' => API_KEY,
        'id_mesin' => MESIN_ID,
        'body' => body($eventId, $waktu),
    ], JSON_THROW_ON_ERROR));

    $start = function (string $ready) use ($env, $fixture, $go) {
        $process = new Process([
            PHP_BINARY,
            dirname(__DIR__).DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'idempotent_tap_worker_mysql.php',
        ], dirname(__DIR__, 2));
        $process->setEnv([
            'APP_ENV' => 'local',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => (string) ($env['DB_PORT'] ?? '3306'),
            'DB_DATABASE' => TEST_DB,
            'DB_USERNAME' => (string) ($env['DB_USERNAME'] ?? ''),
            'DB_PASSWORD' => (string) ($env['DB_PASSWORD'] ?? ''),
            'DB_URL' => '',
            'MAIL_MAILER' => 'array',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'SIMALAS_TAP_FIXTURE' => $fixture,
            'SIMALAS_TAP_READY' => $ready,
            'SIMALAS_TAP_GO' => $go,
            'SIMALAS_TAP_RACE_DELAY_US' => '250000',
        ]);
        $process->setTimeout(60);
        $process->start();

        return $process;
    };
    $first = $start($readyA);
    $second = $start($readyB);
    $deadline = microtime(true) + 20;
    do {
        $ready = is_file($readyA) && is_file($readyB);
        if ($ready) {
            break;
        }
        usleep(2000);
    } while (microtime(true) < $deadline);
    expect($ready ?? false, 'worker tidak siap');
    file_put_contents($go, 'go');
    $first->wait();
    $second->wait();
    $decoded = [];
    foreach ([$first, $second] as $process) {
        $raw = (string) $process->getOutput();
        $pos = strpos($raw, '{');
        $item = $pos === false ? null : json_decode(substr($raw, $pos), true);
        expect(is_array($item), 'worker kode '.$process->getExitCode());
        $decoded[] = $item;
    }
    $userId = DB::table('users')->where('nitk_nidn', NITK)->value('id');
    expect((int) DB::table('log_absensi')->where('user_id', $userId)->count() === 1, 'konkuren satu log');
    expect((int) DB::table('tap_receipts')->where('event_id', $eventId)->count() === 1, 'konkuren satu receipt');
    expect(DB::table('log_absensi')->where('user_id', $userId)->value('jenis') === 'masuk', 'konkuren masuk');
    $ok = 0;
    foreach ($decoded as $item) {
        expect(($item['jenis'] ?? null) !== 'pulang', 'konkuren bukan pulang');
        if (($item['code'] ?? null) === 'MASUK_OK') {
            $ok++;
            expect(($item['terminal'] ?? null) === true && ($item['event_id'] ?? null) === $eventId, 'konkuren terminal');
        }
    }
    expect($ok >= 1, 'konkuren ada jawaban masuk');
    $concurrentOk = true;
    pass('replay konkuren');
} catch (Throwable $e) {
    fail('replay konkuren — '.$e->getMessage());
} finally {
    Carbon::setTestNow();
    cleanupSynthetic();
    if (is_array($sesiSnapshot) && $sesiSnapshot !== []) {
        DB::table('absensi_sesi_settings')->where('shift', 'pagi')->update($sesiSnapshot);
    }
    if (isset($dir) && is_dir($dir)) {
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }
}

$afterUsers = digest('users', Illuminate\Support\Facades\Schema::getColumnListing('users'));
$afterTemplate = templateChecksum();
$afterLog = digest('log_absensi', array_values(array_diff(
    Illuminate\Support\Facades\Schema::getColumnListing('log_absensi'),
    ['event_id', 'diterima_pada']
)));
$afterHarian = digest('absensi_harian', Illuminate\Support\Facades\Schema::getColumnListing('absensi_harian'));
$afterSesi = digest('absensi_sesi_settings', Illuminate\Support\Facades\Schema::getColumnListing('absensi_sesi_settings'));

foreach ([
    'pengguna uji utuh' => [$beforeUsers, $afterUsers],
    'template uji utuh' => [$beforeTemplate, $afterTemplate],
    'log uji utuh' => [$beforeLog, $afterLog],
    'harian uji utuh' => [$beforeHarian, $afterHarian],
    'sesi uji utuh' => [$beforeSesi, $afterSesi],
] as $label => [$before, $after]) {
    if ($before === $after) {
        pass($label);
    } else {
        fail($label);
    }
}

if (! $concurrentOk) {
    fail('konkuren belum lulus');
}

echo 'HASIL gagal '.$failures."\n";
exit($failures > 0 ? 1 : 0);
