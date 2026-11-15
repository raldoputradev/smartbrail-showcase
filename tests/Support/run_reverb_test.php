<?php

/**
 * Uji WebSocket absensi pada database pengujian.
 * Tidak membuat tap di database operasional dan tidak mencetak rahasia.
 */

use App\Models\MesinIot;
use App\Models\TapReceipt;
use App\Support\AbsensiSnapshotSignals;
use App\Support\AbsensiTapIdempotency;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

const TEST_DB = 'simalas_cleanup_test';
const FORBIDDEN_DB = 'simalas';
const SYNTHETIC = ['UJI-CLEANUP-LAB', 'UJI-CLEANUP-DOS', 'UJI-SNAP-MHS', 'UJI-SNAP-OTHER'];
const REPLAY_EVENT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';

$failures = 0;
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

function readEnvFile(string $path): array
{
    $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path)) ?? '';
    $out = [];
    foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && str_ends_with($value, $q)) {
                $value = stripcslashes(substr($value, 1, -1));
            }
        }
        $out[trim($key)] = $value;
    }

    return $out;
}

$root = dirname(__DIR__, 2);
$env = readEnvFile($root.'\\.env');
$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
if (! in_array($host, ['127.0.0.1', 'localhost'], true) || ($env['DB_URL'] ?? '') !== '' || ($env['DB_DATABASE'] ?? '') !== FORBIDDEN_DB) {
    refuse('env operasional tidak sesuai target lokal');
}
if (($env['BROADCAST_CONNECTION'] ?? '') !== 'reverb' || ($env['REVERB_APP_KEY'] ?? '') === '' || ($env['REVERB_APP_SECRET'] ?? '') === '') {
    refuse('key reverb belum lengkap');
}

foreach ([
    'APP_ENV' => 'local',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => (string) $port,
    'DB_DATABASE' => TEST_DB,
    'DB_USERNAME' => $user,
    'DB_PASSWORD' => $pass,
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'file',
    'BROADCAST_CONNECTION' => 'reverb',
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
    'database.connections.mysql.database' => TEST_DB,
    'database.connections.mysql.username' => $user,
    'database.connections.mysql.password' => $pass,
    'mail.default' => 'array',
    'broadcasting.default' => 'reverb',
]);
DB::purge('mysql');
DB::reconnect('mysql');
if ((string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '') !== TEST_DB) {
    refuse('boot bukan database uji');
}
Mail::fake();
echo 'TARGET '.TEST_DB.' PHP '.PHP_VERSION."\n";

$jar = [];
$http = function (string $method, string $uri, array $params = [], array $headers = []) use (&$jar): array {
    if ((string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '') !== TEST_DB) {
        refuse('permintaan bukan database uji');
    }
    Auth::logout();
    Auth::forgetGuards();
    $_COOKIE = [];
    $session = app('session');
    if ($session->driver()->isStarted()) {
        $session->driver()->flush();
    }
    $server = [];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create($uri, $method, $params, $jar, [], $server);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    foreach ($response->headers->getCookies() as $cookie) {
        $jar[$cookie->getName()] = $cookie->getValue();
    }

    return ['status' => $response->getStatusCode(), 'body' => (string) $response->getContent()];
};

$login = function (string $nitk, string $role) use ($http, &$jar): bool {
    $jar = [];
    $plain = bin2hex(random_bytes(18));
    $updated = DB::table('users')->where('nitk_nidn', $nitk)->where('role', $role)->update([
        'password' => Hash::make($plain),
        'email_verified_at' => now(),
        'is_active' => 1,
        'approval_status' => 'approved',
    ]);
    if ($updated !== 1) {
        return false;
    }
    $page = $http('GET', '/masuk');
    if (! preg_match('/name="_token" value="([^"]+)"/', $page['body'], $match)) {
        return false;
    }
    $posted = $http('POST', '/masuk', [
        '_token' => $match[1],
        'nitk_nidn' => $nitk,
        'password' => $plain,
        'role' => $role,
    ], ['Accept' => 'application/json']);
    $json = json_decode($posted['body'], true);

    return $posted['status'] === 200 && is_array($json) && ($json['ok'] ?? false) === true;
};

$auth = function (array $sessionJar, string $channel, string $page) use ($http, &$jar): array {
    $jar = $sessionJar;
    $page = $http('GET', $page);
    if (! preg_match('/name="csrf-token" content="([^"]+)"/', $page['body'], $match) && ! preg_match('/name="_token" value="([^"]+)"/', $page['body'], $match)) {
        return ['status' => 0, 'ok' => false];
    }
    $posted = $http('POST', '/broadcasting/auth', [
        'socket_id' => '1.1',
        'channel_name' => $channel,
        '_token' => $match[1],
    ], [
        'Accept' => 'application/json',
        'X-CSRF-TOKEN' => $match[1],
    ]);
    $json = json_decode($posted['body'], true);

    return ['status' => $posted['status'], 'ok' => is_array($json) && isset($json['auth']) && $json['auth'] !== ''];
};

if ($login('UJI-CLEANUP-LAB', 'laboran')) {
    pass('login laboran');
} else {
    fail('login laboran');
}
$labJar = $jar;
if ($login('UJI-CLEANUP-DOS', 'dosen')) {
    pass('login dosen');
} else {
    fail('login dosen');
}
$dosJar = $jar;

$guest = $auth([], 'private-absensi.staff.laboran', '/masuk');
echo "AUTH tamu status {$guest['status']}\n";
if (in_array($guest['status'], [302, 401, 403], true) && ! $guest['ok']) {
    pass('tamu ditolak');
} else {
    fail('tamu ditolak');
}
$labOwn = $auth($labJar, 'private-absensi.staff.laboran', '/admin/attendance');
$labCross = $auth($labJar, 'private-absensi.staff.dosen', '/admin/attendance');
$dosOwn = $auth($dosJar, 'private-absensi.staff.dosen', '/dosen/absensi');
$dosCross = $auth($dosJar, 'private-absensi.staff.laboran', '/dosen/absensi');
$bogus = $auth($labJar, 'private-absensi.mahasiswa.1', '/admin/attendance');
echo "AUTH channel tak terdaftar {$bogus['status']}\n";
if (! $bogus['ok'] && $bogus['status'] === 403) {
    pass('channel tak terdaftar ditolak');
} else {
    fail('channel tak terdaftar ditolak');
}
echo "AUTH laboran-sendiri {$labOwn['status']} lintas {$labCross['status']} dosen-sendiri {$dosOwn['status']} lintas {$dosCross['status']}\n";
if ($labOwn['ok'] && $dosOwn['ok'] && $labCross['status'] === 403 && $dosCross['status'] === 403) {
    pass('channel privat menolak peran lain');
} else {
    fail('channel privat menolak peran lain');
}

$box = (object) ['events' => []];
app(Illuminate\Broadcasting\BroadcastManager::class)->extend('capture', function () use ($box) {
    return new class($box) extends NullBroadcaster
    {
        public function __construct(private object $box) {}

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            $this->box->events[] = [
                'event' => (string) $event,
                'surfaces' => $payload['surfaces'] ?? [],
                'delivery_id' => $payload['delivery_id'] ?? '',
                'event_id' => $payload['event_id'] ?? null,
                'keys' => array_keys($payload),
                'channels' => array_map('strval', $channels),
            ];
        }
    };
});
config([
    'broadcasting.connections.capture' => ['driver' => 'capture'],
    'broadcasting.default' => 'capture',
]);
Broadcast::forgetDrivers();

$box->events = [];
DB::beginTransaction();
AbsensiSnapshotSignals::committed(['rekap', 'log'], 'staff', REPLAY_EVENT);
DB::rollBack();
if ($box->events === []) {
    pass('rollback tidak broadcast');
} else {
    fail('rollback tidak broadcast');
}

$box->events = [];
DB::transaction(function () {
    AbsensiSnapshotSignals::committed(['rekap', 'log'], 'staff', null);
});
$commitOk = count($box->events) === 1
    && ($box->events[0]['event'] ?? '') === 'snapshot.wake'
    && ($box->events[0]['surfaces'] ?? []) === ['rekap', 'log']
    && ($box->events[0]['delivery_id'] ?? '') !== '';
if ($commitOk) {
    pass('commit membangunkan rekap dan log');
} else {
    fail('commit membangunkan rekap dan log');
}
$keys = $box->events[0]['keys'] ?? [];
sort($keys);
if ($keys === ['committed_at', 'delivery_id', 'event_id', 'socket', 'surfaces']) {
    pass('payload hanya delivery_id surfaces committed_at event_id');
} else {
    fail('payload hanya delivery_id surfaces committed_at event_id');
}
if (($box->events[0]['channels'] ?? []) === ['private-absensi.staff.laboran', 'private-absensi.staff.dosen']) {
    pass('tap ke channel staf laboran dan dosen');
} else {
    fail('tap ke channel staf laboran dan dosen');
}

$box->events = [];
DB::transaction(function () {
    AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');
});
if (count($box->events) === 1 && ($box->events[0]['channels'] ?? []) === ['private-absensi.staff.laboran']) {
    pass('fingerprint hanya ke channel laboran');
} else {
    fail('fingerprint hanya ke channel laboran');
}

$box->events = [];
DB::transaction(function () {
    for ($i = 0; $i < 5; $i++) {
        AbsensiSnapshotSignals::committed(['rekap'], 'staff', null);
    }
});
$ids = array_column($box->events, 'delivery_id');
if (count($box->events) === 5 && count(array_unique($ids)) === 5) {
    pass('burst lima event punya delivery_id berbeda');
} else {
    fail('burst lima event punya delivery_id berbeda');
}

$logsBefore = (int) DB::table('log_absensi')->count();
$harianBefore = (int) DB::table('absensi_harian')->count();
$mesin = MesinIot::query()->where('tipe', 'absensi')->first();
if (! $mesin) {
    fail('mesin absensi uji tidak ada');
} else {
    $seen = $mesin->last_seen_at;
    $payload = [
        'finger_id' => 7,
        'id_mesin' => $mesin->id_mesin,
        'waktu_tap' => null,
    ];
    ksort($payload);
    TapReceipt::query()->create([
        'event_id' => REPLAY_EVENT,
        'id_mesin' => $mesin->id_mesin,
        'finger_id' => 7,
        'waktu_tap' => null,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'success' => true,
        'terminal' => true,
        'code' => 'REPLAY_UJI',
        'http_status' => 200,
        'message' => 'resmi uji',
        'response_data' => null,
        'log_absensi_id' => null,
        'diterima_pada' => now(),
    ]);
    $box->events = [];
    $request = Illuminate\Http\Request::create('/api/mesin/absensi', 'POST', [
        'finger_id' => 7,
        'event_id' => REPLAY_EVENT,
    ]);
    $response = app(AbsensiTapIdempotency::class)->handle($request, $mesin->fresh());
    $body = json_decode($response->getContent(), true);
    $replayOk = $response->getStatusCode() === 200
        && is_array($body)
        && ($body['idempotent'] ?? false) === true
        && $box->events === []
        && (int) DB::table('log_absensi')->count() === $logsBefore
        && (int) DB::table('absensi_harian')->count() === $harianBefore;
    if ($replayOk) {
        pass('replay receipt tidak broadcast dan tidak menambah tap');
    } else {
        fail('replay receipt tidak broadcast dan tidak menambah tap');
        echo 'replay status '.$response->getStatusCode().' event '.count($box->events).' log '.(int) DB::table('log_absensi')->count()."\n";
    }
    TapReceipt::query()->where('event_id', REPLAY_EVENT)->delete();
    DB::table('log_absensi')->where('event_id', REPLAY_EVENT)->delete();
    $mesin->forceFill(['last_seen_at' => $seen])->save();
}

$original = DB::table('users')->whereNotIn('nitk_nidn', SYNTHETIC)->orderBy('id')->get(['id', 'role', 'finger_id', 'finger_template']);
$ctx = hash_init('sha256');
foreach ($original as $row) {
    $raw = $row->finger_template;
    $present = $raw !== null && $raw !== '';
    hash_update($ctx, implode('|', [
        (string) $row->id,
        (string) $row->role,
        $row->finger_id === null ? '' : (string) $row->finger_id,
        $present ? hash('sha256', (string) $raw) : '',
    ]));
}
echo 'ASLI jumlah '.$original->count().' sha '.hash_final($ctx)."\n";
echo 'log '.(int) DB::table('log_absensi')->count().' harian '.(int) DB::table('absensi_harian')->count().' receipt uji '.(int) TapReceipt::query()->where('event_id', REPLAY_EVENT)->count()."\n";
echo 'HASIL gagal '.$failures."\n";
exit($failures > 0 ? 1 : 0);
