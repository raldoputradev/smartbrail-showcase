<?php

/**
 * Bandingkan finger_id dan template kelompok pengguna asli
 * antara database operasional dan database pengujian.
 * Lalu buka halaman fingerprint dua kali pada database pengujian.
 * Tidak memperbaiki selisih. Tidak mencetak NIM, email, template, atau sandi.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

const TEST_DB = 'simalas_cleanup_test';
const FORBIDDEN_DB = 'simalas';
const SYNTHETIC = ['UJI-CLEANUP-LAB', 'UJI-CLEANUP-DOS', 'UJI-SNAP-MHS', 'UJI-SNAP-OTHER'];

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

function rowHash(object $row): string
{
    $raw = $row->finger_template;
    $present = $raw !== null && $raw !== '';

    return hash('sha256', implode('|', [
        (string) $row->id,
        (string) $row->role,
        $row->finger_id === null || $row->finger_id === '' ? '' : (string) $row->finger_id,
        $present ? hash('sha256', (string) $raw) : '',
    ]));
}

function loadUsers(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, nitk_nidn, role, is_active, finger_id, finger_template FROM users ORDER BY id')->fetchAll(PDO::FETCH_OBJ);
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row->id] = $row;
    }

    return $out;
}

function isSynthetic(object $row): bool
{
    return in_array((string) $row->nitk_nidn, SYNTHETIC, true);
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

$pdoFor = function (string $database) use ($host, $port, $user, $pass): PDO {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $current = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($current !== $database) {
        refuse('koneksi '.$database.' salah target');
    }

    return $pdo;
};

$operational = loadUsers($pdoFor(FORBIDDEN_DB));
$test = loadUsers($pdoFor(TEST_DB));

$summarize = function (array $rows, string $label): array {
    $synthetic = array_filter($rows, 'isSynthetic');
    $original = array_filter($rows, fn ($row) => ! isSynthetic($row));
    $count = function (array $group, callable $pred): int {
        return count(array_filter($group, $pred));
    };
    $withId = fn ($row) => $row->finger_id !== null && $row->finger_id !== '';
    $withTpl = fn ($row) => $row->finger_template !== null && $row->finger_template !== '';
    $mhsNull = fn ($row) => $row->role === 'mahasiswa' && ($row->finger_id === null || $row->finger_id === '');
    echo $label." total ".count($rows)
        .' asli '.count($original)
        .' sintetis '.count($synthetic)
        .' asli_finger '. $count($original, $withId)
        .' asli_template '. $count($original, $withTpl)
        .' asli_mhs_slot_kosong '. $count($original, $mhsNull)
        .' semua_finger '. $count($rows, $withId)
        .' semua_template '. $count($rows, $withTpl)
        ."\n";
    foreach ($synthetic as $row) {
        $slot = ($row->finger_id === null || $row->finger_id === '') ? 'kosong' : 'terisi';
        $tpl = ($row->finger_template === null || $row->finger_template === '') ? 'tanpa-template' : 'ada-template';
        echo "SINTETIS {$row->nitk_nidn} peran {$row->role} aktif {$row->is_active} slot {$slot} {$tpl}\n";
    }
    foreach ($original as $row) {
        if ($row->role === 'mahasiswa' && ($row->finger_id === null || $row->finger_id === '')) {
            $tpl = ($row->finger_template === null || $row->finger_template === '') ? 'tanpa-template' : 'ada-template';
            echo "ASLI slot kosong id {$row->id} peran {$row->role} aktif {$row->is_active} {$tpl}\n";
        }
    }

    return $original;
};

echo "PHP ".PHP_VERSION."\n";
$originalOperational = $summarize($operational, 'OPERASIONAL');
$originalTest = $summarize($test, 'UJI');

$onlyOperational = array_diff(array_keys($originalOperational), array_keys($originalTest));
$onlyTest = array_diff(array_keys($originalTest), array_keys($originalOperational));
$fingerDiff = [];
$templateDiff = [];
$identityDiff = [];
$same = 0;
foreach ($originalTest as $id => $row) {
    if (! isset($originalOperational[$id])) {
        continue;
    }
    $other = $originalOperational[$id];
    if ((string) $row->role !== (string) $other->role || (string) $row->nitk_nidn !== (string) $other->nitk_nidn) {
        $identityDiff[] = $id;
        continue;
    }
    $fingerSame = (string) ($row->finger_id ?? '') === (string) ($other->finger_id ?? '');
    $leftTpl = ($row->finger_template === null || $row->finger_template === '') ? '' : hash('sha256', (string) $row->finger_template);
    $rightTpl = ($other->finger_template === null || $other->finger_template === '') ? '' : hash('sha256', (string) $other->finger_template);
    if (! $fingerSame) {
        $fingerDiff[] = $id;
    }
    if ($leftTpl !== $rightTpl) {
        $templateDiff[] = $id;
    }
    if ($fingerSame && $leftTpl === $rightTpl) {
        $same++;
    }
}
echo 'ASLI sama '. $same
    .' hanya_operasional '.count($onlyOperational)
    .' hanya_uji '.count($onlyTest)
    .' beda_identitas '.count($identityDiff)
    .' beda_finger '.count($fingerDiff)
    .' beda_template '.count($templateDiff)
    ."\n";
foreach (['finger' => $fingerDiff, 'template' => $templateDiff, 'identitas' => $identityDiff] as $kind => $ids) {
    foreach ($ids as $id) {
        $row = $originalTest[$id] ?? $originalOperational[$id];
        echo "SELISIH {$kind} id {$id} peran {$row->role} aktif {$row->is_active}\n";
    }
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
    'database.connections.mysql.port' => (string) $port,
    'database.connections.mysql.database' => TEST_DB,
    'database.connections.mysql.username' => $user,
    'database.connections.mysql.password' => $pass,
    'mail.default' => 'array',
]);
DB::purge('mysql');
DB::reconnect('mysql');
$current = (string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '');
if ($current !== TEST_DB) {
    refuse('boot bukan database uji');
}
Mail::fake();

$groupHash = function () use ($test): array {
    $rows = DB::select('SELECT id, nitk_nidn, role, is_active, finger_id, finger_template FROM users ORDER BY id');
    $ctx = hash_init('sha256');
    $n = 0;
    foreach ($rows as $row) {
        if (isSynthetic($row)) {
            continue;
        }
        $n++;
        hash_update($ctx, rowHash($row));
    }

    return ['jumlah' => $n, 'sha256' => hash_final($ctx)];
};

$before = $groupHash();
$plain = bin2hex(random_bytes(18));
$rotated = DB::table('users')->where('nitk_nidn', 'UJI-CLEANUP-LAB')->where('role', 'laboran')->update([
    'password' => Hash::make($plain),
    'email_verified_at' => now(),
    'is_active' => 1,
]);
if ($rotated !== 1) {
    refuse('akun uji laboran tidak unik');
}

$jar = [];
$http = function (string $method, string $uri, array $params = [], array $headers = []) use (&$jar): array {
    $current = (string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '');
    if ($current !== TEST_DB) {
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

$loginPage = $http('GET', '/masuk');
if ($loginPage['status'] !== 200 || ! preg_match('/name="_token" value="([^"]+)"/', $loginPage['body'], $match)) {
    refuse('halaman masuk tidak siap');
}
$posted = $http('POST', '/masuk', [
    '_token' => $match[1],
    'nitk_nidn' => 'UJI-CLEANUP-LAB',
    'password' => $plain,
    'role' => 'laboran',
], ['Accept' => 'application/json']);
$login = json_decode($posted['body'], true);
if ($posted['status'] !== 200 || ! is_array($login) || ($login['ok'] ?? false) !== true) {
    refuse('login uji gagal status '.$posted['status']);
}
$pages = [
    $http('GET', '/admin/fingerprint'),
    $http('GET', '/admin/fingerprint'),
    $http('GET', '/admin/fingerprint/snapshot'),
    $http('GET', '/admin/fingerprint/snapshot'),
];
$after = $groupHash();
echo 'GET '.implode(',', array_map(fn ($page) => (string) $page['status'], $pages))."\n";
echo 'ASLI sebelum GET '.$before['jumlah'].' '.$before['sha256']."\n";
echo 'ASLI sesudah GET '.$after['jumlah'].' '.$after['sha256']."\n";
echo 'GET mengubah kelompok asli '.($before === $after ? 'tidak' : 'ya')."\n";
