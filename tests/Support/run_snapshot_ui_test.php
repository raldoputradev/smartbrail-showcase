<?php

/**
 * Uji snapshot UI absensi pada database pengujian saja.
 * Menolak target simalas. Tidak membuat tap di database operasional.
 * Tidak mencetak NIM, email, template, password, atau API key.
 */

use App\Models\AbsensiHarian;
use App\Models\LogAbsensi;
use App\Models\User;
use App\Support\AbsensiKebijakan;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

const TEST_DB = 'simalas_cleanup_test';
const FORBIDDEN_DB = 'simalas';
const LAB_NITK = 'UJI-CLEANUP-LAB';
const DOS_NITK = 'UJI-CLEANUP-DOS';
const MHS_NITK = 'UJI-SNAP-MHS';
const OTHER_NITK = 'UJI-SNAP-OTHER';
const SYNTHETIC = [LAB_NITK, DOS_NITK, MHS_NITK, OTHER_NITK];

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

function assertTestDb(): void
{
    $configured = (string) config('database.connections.mysql.database');
    $current = (string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '');
    if ($configured === FORBIDDEN_DB || $current === FORBIDDEN_DB) {
        refuse('koneksi aktif adalah database operasional');
    }
    if ($configured !== TEST_DB || $current !== TEST_DB) {
        refuse('koneksi aktif bukan database uji');
    }
}

function readEnvFile(string $path): array
{
    $text = (string) file_get_contents($path);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
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

function digestRows(string $table, array $columns, ?callable $restrict = null): array
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
        hash_update($ctx, (string) $row->id);
        hash_update($ctx, '|');
        hash_update($ctx, $row->finger_id === null ? '' : (string) $row->finger_id);
        hash_update($ctx, '|');
        hash_update($ctx, $present ? hash('sha256', (string) $raw, true) : "\0");
        hash_update($ctx, "\x1f");
    }

    return [
        'jumlah' => $rows->count().'/'.$withId.'/'.$withTemplate,
        'sha256' => hash_final($ctx),
    ];
}

function httpCall(array &$jar, string $method, string $uri, array $params = [], array $headers = []): array
{
    assertTestDb();
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
    if ($response instanceof StreamedResponse) {
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();
    } else {
        $body = (string) $response->getContent();
    }

    return [
        'status' => $response->getStatusCode(),
        'location' => (string) $response->headers->get('Location'),
        'type' => (string) $response->headers->get('Content-Type'),
        'bytes' => strlen($body),
        'body' => $body,
        'at' => date('Y-m-d H:i:s'),
    ];
}

function exceptionClass(string $body): string
{
    if (preg_match('/SQLSTATE\[([A-Z0-9]+)\]/', $body, $m)) {
        return 'SQLSTATE '.$m[1];
    }
    if (preg_match('/<title>\s*([A-Za-z0-9_\\\\]+(?:Exception|Error)?)/', $body, $m)) {
        return $m[1];
    }
    if (preg_match('/([A-Za-z0-9_\\\\]+Exception)/', $body, $m)) {
        return $m[1];
    }

    return 'tanpa kelas';
}

function jsonBody(array $res): ?array
{
    $data = json_decode($res['body'], true);

    return is_array($data) ? $data : null;
}

function collectKeys(mixed $value, array &$keys): void
{
    if (! is_array($value)) {
        return;
    }
    foreach ($value as $key => $child) {
        $keys[] = (string) $key;
        collectKeys($child, $keys);
    }
}

function cleanupSynthetic(): void
{
    assertTestDb();
    $ids = DB::table('users')->whereIn('nitk_nidn', [MHS_NITK, OTHER_NITK])->pluck('id');
    if ($ids->isEmpty()) {
        return;
    }
    DB::table('log_absensi')->whereIn('user_id', $ids)->delete();
    DB::table('absensi_harian')->whereIn('user_id', $ids)->delete();
    DB::table('users')->whereIn('id', $ids)->delete();
}

function logSignature(string $message): string
{
    if (preg_match('/SQLSTATE\[([A-Z0-9]+)\]/', $message, $m)) {
        return 'SQLSTATE '.$m[1];
    }
    if (preg_match('/Call to undefined method /', $message)) {
        return 'undefined method';
    }
    if (preg_match('/Undefined array key /', $message)) {
        return 'undefined array key';
    }
    if (preg_match('/^([A-Za-z0-9_\\\\]+Exception)\b/', $message, $m)) {
        return $m[1];
    }

    return 'error lain';
}

function scanLog(string $path, int $offset, bool $attendanceOnly): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }
    if ($offset > 0) {
        fseek($handle, $offset);
    }
    $want = '/AbsensiManagementController|AdminLogController|AdminController->fingerprint|MobileDataController->absensi|log\/absensi|attendance\/snapshot/';
    $current = null;
    $hits = [];
    $flush = function () use (&$current, &$hits, $attendanceOnly, $want): void {
        if (! $current) {
            return;
        }
        if (! $attendanceOnly || $current['match'] || preg_match($want, $current['msg'])) {
            $hits[] = $current;
        }
    };
    while (($line = fgets($handle)) !== false) {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \S+\.(ERROR|CRITICAL): (.*)$/', $line, $m)) {
            $flush();
            $current = [
                'at' => $m[1],
                'signature' => logSignature($m[3]),
                'msg' => $m[3],
                'match' => (bool) preg_match($want, $line),
            ];
            continue;
        }
        if ($current && ! $current['match'] && preg_match($want, $line)) {
            $current['match'] = true;
        }
    }
    $flush();
    fclose($handle);

    return $hits;
}

$root = dirname(__DIR__, 2);
$envPath = $root.'\\.env';
$envStamp = [filemtime($envPath), filesize($envPath)];
$env = readEnvFile($envPath);
$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
if (! in_array($host, ['127.0.0.1', 'localhost'], true) || ($env['DB_URL'] ?? '') !== '' || ($env['DB_DATABASE'] ?? '') !== FORBIDDEN_DB) {
    refuse('env operasional tidak sesuai target lokal');
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
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'file',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require_once $root.'\\vendor\\autoload.php';
$app = require $root.'\\bootstrap\\app.php';
$app->make(Kernel::class)->bootstrap();
$sessionDir = 'C:\\project-archives\\Simalas-db-backup\\cleanup-test-sessions';
if (! is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
config([
    'database.default' => 'mysql',
    'database.connections.mysql.url' => null,
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => (string) $port,
    'database.connections.mysql.database' => TEST_DB,
    'database.connections.mysql.username' => $user,
    'database.connections.mysql.password' => $pass,
    'mail.default' => 'array',
    'session.driver' => 'file',
    'session.files' => $sessionDir,
]);
DB::purge('mysql');
DB::reconnect('mysql');
assertTestDb();
Mail::fake();
echo 'TARGET '.TEST_DB."\n";

$logPath = $root.'\\storage\\logs\\laravel.log';
$logOffset = is_file($logPath) ? filesize($logPath) : 0;
$historical = is_file($logPath) ? scanLog($logPath, 0, true) : [];
$grouped = [];
foreach ($historical as $hit) {
    $key = $hit['signature'];
    if (! isset($grouped[$key])) {
        $grouped[$key] = ['n' => 0, 'first' => $hit['at'], 'last' => $hit['at']];
    }
    $grouped[$key]['n']++;
    $grouped[$key]['last'] = $hit['at'];
}
echo "LOG historis absensi\n";
if ($grouped === []) {
    echo "tidak ada ERROR absensi pada log\n";
} else {
    foreach ($grouped as $signature => $row) {
        echo $row['first'].' .. '.$row['last'].' x'.$row['n'].' '.$signature."\n";
    }
}

$reloadFiles = [
    'resources/views/absensi/_auto_refresh.blade.php',
    'resources/views/absensi/rekap.blade.php',
    'resources/views/absensi/alfa.blade.php',
    'resources/views/admin/log_absensi.blade.php',
    'resources/views/admin/fingerprint.blade.php',
    'public/js/simalas-snapshot.js',
];
$reloadLeft = 0;
foreach ($reloadFiles as $rel) {
    $text = (string) file_get_contents($root.'\\'.str_replace('/', '\\', $rel));
    if (str_contains($text, 'location.reload')) {
        $reloadLeft++;
        fail('reload tersisa '.$rel);
    }
}
if ($reloadLeft === 0) {
    pass('log rekap alfa fingerprint tidak memakai location.reload');
}
$client = (string) file_get_contents($root.'\\public\\js\\simalas-snapshot.js');
$clientOk = str_contains($client, 'AbortController')
    && str_contains($client, "visibilityState === 'hidden'")
    && str_contains($client, 'pagehide')
    && str_contains($client, "'online'")
    && str_contains($client, '60000')
    && str_contains($client, 'data terakhir tetap');
if ($clientOk) {
    pass('klien snapshot punya batal, tab tersembunyi, backoff, dan data terakhir');
} else {
    fail('klien snapshot punya batal, tab tersembunyi, backoff, dan data terakhir');
}

$flutterRoot = $root.'\\simalas_mobile\\lib';
foreach ([
    'main.dart',
    'screens\\attendance_page.dart',
    'screens\\mhs_history_page.dart',
] as $rel) {
    $text = str_replace("\r\n", "\n", (string) file_get_contents($flutterRoot.'\\'.$rel));
    $old = str_contains($text, '/mobile/me/absensi?days=');
    $next = str_contains($text, '/mobile/me/absensi/snapshot?days=');
    if ($next && ! $old) {
        pass('flutter '.$rel.' memakai snapshot');
    } else {
        fail('flutter '.$rel.' memakai snapshot');
    }
}

cleanupSynthetic();
assertTestDb();
$userColumns = Schema::getColumnListing('users');
$baselineUsers = digestRows('users', $userColumns, fn ($q) => $q->whereNotIn('nitk_nidn', SYNTHETIC));
$baselineTemplate = templateChecksum();
$baselineLog = digestRows('log_absensi', Schema::getColumnListing('log_absensi'));
$baselineHarian = digestRows('absensi_harian', Schema::getColumnListing('absensi_harian'));

$required = DB::select(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND IS_NULLABLE = ? AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE ?',
    [TEST_DB, 'users', 'NO', '%auto_increment%']
);
$now = now()->format('Y-m-d H:i:s');
$accounts = [
    'laboran' => [LAB_NITK, 'Akun Uji Laboran', 'uji-cleanup-lab@example.test'],
    'dosen' => [DOS_NITK, 'Akun Uji Dosen', 'uji-cleanup-dos@example.test'],
];
foreach ($accounts as $role => [$nitk, $nama, $email]) {
    if (DB::table('users')->where('nitk_nidn', $nitk)->exists()) {
        continue;
    }
    $row = [
        'nama_lengkap' => $nama,
        'nitk_nidn' => $nitk,
        'email' => $email,
        'password' => Hash::make(bin2hex(random_bytes(18))),
        'role' => $role,
        'is_active' => 1,
        'approval_status' => 'approved',
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
        'failed_login_attempts' => 0,
        'login_requires_admin_unlock' => 0,
    ];
    $insert = [];
    foreach ($required as $column) {
        $name = $column->COLUMN_NAME;
        if (! array_key_exists($name, $row)) {
            fail('kolom wajib users');
            refuse('akun staf sintetis tidak lengkap');
        }
        $insert[$name] = $row[$name];
    }
    foreach ($row as $key => $value) {
        if (Schema::hasColumn('users', $key)) {
            $insert[$key] = $value;
        }
    }
    DB::table('users')->insert($insert);
}

$passwords = [];
foreach ($accounts as $role => [$nitk]) {
    $plain = bin2hex(random_bytes(18));
    DB::table('users')->where('nitk_nidn', $nitk)->update([
        'password' => Hash::make($plain),
        'email_verified_at' => $now,
        'is_active' => 1,
    ]);
    $passwords[$role] = [$nitk, $plain];
}
$loginAs = function (string $role) use (&$passwords): array {
    $jar = [];
    $_COOKIE = [];
    Auth::logout();
    Auth::forgetGuards();
    $page = httpCall($jar, 'GET', '/masuk');
    if ($page['status'] !== 200 || ! str_contains($page['body'], 'id="loginIdInput"') || ! preg_match('/name="_token" value="([^"]+)"/', $page['body'], $match)) {
        fail('token masuk '.$role.' status '.$page['status'].' '.$page['at']);
        return [];
    }
    [$nitk, $plain] = $passwords[$role];
    $posted = httpCall($jar, 'POST', '/masuk', [
        '_token' => $match[1],
        'nitk_nidn' => $nitk,
        'password' => $plain,
        'role' => $role,
    ], ['Accept' => 'application/json']);
    $json = jsonBody($posted);
    $ok = $posted['status'] === 200 && is_array($json) && ($json['ok'] ?? false) === true;
    if ($ok) {
        pass('login '.$role.' '.$posted['at']);
    } else {
        fail('login '.$role.' status '.$posted['status'].' '.$posted['at']);
    }

    return $jar;
};

$labJar = $loginAs('laboran');
$dosJar = $loginAs('dosen');

$recordPage = function (string $label, array &$jar, string $uri, array $expect) use ($root): void {
    $res = httpCall($jar, 'GET', $uri);
    $class = $res['status'] >= 500 ? exceptionClass($res['body']) : '-';
    $loc = $res['location'];
    $path = $loc !== '' ? (string) (parse_url($loc, PHP_URL_PATH) ?: '') : '';
    $query = $loc !== '' ? (string) (parse_url($loc, PHP_URL_QUERY) ?: '') : '';
    echo "HALAMAN {$label} status {$res['status']} waktu {$res['at']} kelas {$class}\n";
    $statusOk = in_array($res['status'], $expect, true);
    $loginForm = str_contains($res['body'], 'id="loginIdInput"');
    if ($statusOk && ! $loginForm) {
        if ($res['status'] === 302 && str_contains($uri, '/alfa') && ! str_contains($query, 'tab=alfa')) {
            fail($label.' redirect tab alfa');
            return;
        }
        pass($label);
    } else {
        fail($label.' status '.$res['status'].' kelas '.$class);
    }
};

if ($labJar !== []) {
    $recordPage('laboran rekap', $labJar, '/admin/attendance', [200]);
    $recordPage('laboran log', $labJar, '/admin/log/absensi', [200]);
    $recordPage('laboran alfa', $labJar, '/admin/absensi/alfa', [302]);
}
if ($dosJar !== []) {
    $recordPage('dosen rekap', $dosJar, '/dosen/absensi', [200]);
    $recordPage('dosen alfa', $dosJar, '/dosen/absensi/alfa', [302]);
}

$empty = [];
foreach ([
    '/admin/attendance/snapshot',
    '/admin/log/absensi/snapshot',
    '/admin/fingerprint/snapshot',
    '/dosen/absensi/snapshot',
] as $uri) {
    $res = httpCall($empty, 'GET', $uri);
    echo "HALAMAN tamu {$uri} status {$res['status']} waktu {$res['at']} kelas ".($res['status'] >= 500 ? exceptionClass($res['body']) : '-')."\n";
    if ($res['status'] === 302) {
        pass('tamu '.$uri);
    } else {
        fail('tamu '.$uri.' status '.$res['status']);
    }
}
$mobileGuest = httpCall($empty, 'GET', '/api/mobile/me/absensi/snapshot');
echo "HALAMAN tamu mobile snapshot status {$mobileGuest['status']} waktu {$mobileGuest['at']} kelas ".($mobileGuest['status'] >= 500 ? exceptionClass($mobileGuest['body']) : '-')."\n";
if ($mobileGuest['status'] === 401) {
    pass('tamu mobile snapshot');
} else {
    fail('tamu mobile snapshot status '.$mobileGuest['status']);
}

$today = now('Asia/Jakarta')->toDateString();
$scheduled = null;
$cursor = now('Asia/Jakarta')->startOfDay();
for ($i = 0; $i < 21; $i++) {
    if (AbsensiKebijakan::isHariTerjadwal($cursor)) {
        $scheduled = $cursor->toDateString();
        break;
    }
    $cursor->subDay();
}
$orphan = null;
$cursor = now('Asia/Jakarta')->startOfDay()->subDay();
for ($i = 0; $i < 60; $i++) {
    if (! AbsensiKebijakan::isHariTerjadwal($cursor)) {
        $orphan = $cursor->toDateString();
        break;
    }
    $cursor->subDay();
}
echo 'hari rekap '.$today.' hari terjadwal '.($scheduled ?? '-').' hari jejak '.($orphan ?? '-')."\n";

$mesin = (string) DB::table('mesin_iot')->where('tipe', 'absensi')->orderBy('id')->value('id_mesin');
if ($mesin === '') {
    fail('mesin absensi uji tidak ada');
}

$makeStudent = function (string $nitk, string $nama, string $email): int {
    assertTestDb();
    $now = now()->format('Y-m-d H:i:s');
    $row = [
        'nama_lengkap' => $nama,
        'nitk_nidn' => $nitk,
        'email' => $email,
        'password' => Hash::make(bin2hex(random_bytes(18))),
        'role' => 'mahasiswa',
        'is_active' => 1,
        'approval_status' => 'approved',
        'email_verified_at' => $now,
        'kelas' => 'SNAP-A',
        'tim' => 'SNAP-T',
        'shift' => 'pagi',
        'finger_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'failed_login_attempts' => 0,
        'login_requires_admin_unlock' => 0,
    ];
    $insert = [];
    foreach ($row as $key => $value) {
        if (Schema::hasColumn('users', $key)) {
            $insert[$key] = $value;
        }
    }
    return (int) DB::table('users')->insertGetId($insert);
};

try {
    $mhsId = $makeStudent(MHS_NITK, 'Mahasiswa Uji Snapshot', 'uji-snap-mhs@example.test');
    $otherId = $makeStudent(OTHER_NITK, 'Mahasiswa Uji Lain', 'uji-snap-other@example.test');
    $token = bin2hex(random_bytes(24));
    DB::table('users')->where('id', $mhsId)->update([
        'mobile_api_token' => hash('sha256', $token),
        'mobile_token_expires_at' => now()->addHour(),
    ]);

    $insertDay = function (int $userId, string $date, string $status = 'lengkap') use ($mesin): void {
        $when = $date.' 08:05:00';
        $logId = LogAbsensi::query()->insertGetId([
            'user_id' => $userId,
            'id_mesin' => $mesin,
            'sesi' => 'pagi',
            'jenis' => 'masuk',
            'menit_terlambat' => 0,
            'slot_lab' => 1,
            'pulang_lembur' => 0,
            'waktu_tap' => $when,
            'event_id' => null,
            'diterima_pada' => null,
        ]);
        AbsensiHarian::query()->insert([
            'user_id' => $userId,
            'tanggal' => $date,
            'sesi' => 'pagi',
            'status' => $status,
            'masuk_log_id' => $logId,
            'pulang_log_id' => null,
            'menit_terlambat_masuk' => 0,
            'menit_terlambat_pulang' => 0,
            'slot_lab_masuk' => 1,
            'pulang_lembur' => 0,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    };
    $rowStatus = function (?array $json, string $nitk): ?string {
        foreach (($json['rows'] ?? []) as $row) {
            if (is_array($row) && (string) ($row['nim'] ?? '') === $nitk) {
                return (string) ($row['status'] ?? '');
            }
        }

        return null;
    };
    $modeBeforeInsert = 'belum-diukur';
    if ($labJar !== []) {
        $beforeInsert = jsonBody(httpCall($labJar, 'GET', '/admin/attendance/snapshot'));
        $modeBeforeInsert = is_array($beforeInsert) ? (string) ($beforeInsert['summary_mode'] ?? '') : 'gagal';
        $emptySummary = is_array($beforeInsert) ? ($beforeInsert['summary'] ?? []) : [];
        $emptyOk = $modeBeforeInsert === 'operasional'
            && (int) ($emptySummary['hadir'] ?? -1) === 0
            && (int) ($emptySummary['terlambat'] ?? -1) === 0
            && (int) ($emptySummary['masuk'] ?? -1) === 0
            && (int) ($emptySummary['tidak_lengkap'] ?? -1) === 0
            && (int) ($emptySummary['alfa'] ?? -1) === 0
            && (int) ($emptySummary['belum'] ?? -1) === 0
            && (float) ($emptySummary['persentase_lengkap'] ?? -1) === 0.0
            && (int) ($emptySummary['tidak_wajib'] ?? 0) > 0;
        echo 'rekap kosong hadir '.(int) ($emptySummary['hadir'] ?? -1).' tidak_wajib '.(int) ($emptySummary['tidak_wajib'] ?? -1).' mode '.$modeBeforeInsert."\n";
        if ($emptyOk) {
            pass('rekap kosong tetap angka aktual');
        } else {
            fail('rekap kosong tetap angka aktual');
        }
        $print = httpCall($labJar, 'GET', '/admin/attendance/print');
        $export = httpCall($labJar, 'GET', '/admin/attendance/export');
        $report = httpCall($labJar, 'GET', '/admin/reports?periode=custom&tanggal_mulai=2020-01-10&tanggal_akhir=2020-01-11');
        $reportExcel = httpCall($labJar, 'GET', '/admin/reports/export/excel?periode=custom&tanggal_mulai=2020-01-10&tanggal_akhir=2020-01-11');
        $reportPrint = httpCall($labJar, 'GET', '/admin/reports/print?periode=custom&tanggal_mulai=2020-01-10&tanggal_akhir=2020-01-11');
        $home = httpCall($labJar, 'GET', '/admin/dashboard');
        $compactReport = preg_replace('/\s+/', '', $report['body']) ?? '';
        $noDemo = ! str_contains($print['body'], '71.9')
            && ! str_contains($export['body'], '71.9')
            && ! str_contains($report['body'], '71.9')
            && ! str_contains($reportExcel['body'], '71.9')
            && ! str_contains($reportPrint['body'], '71.9')
            && str_contains($compactReport, 'Hadir</span><strong>0</strong>');
        echo "cetak {$print['status']} ekspor {$export['status']} laporan {$report['status']} excel {$reportExcel['status']} cetak-laporan {$reportPrint['status']}\n";
        if ($print['status'] === 200 && $export['status'] === 200 && $report['status'] === 200 && $reportExcel['status'] === 200 && $reportPrint['status'] === 200 && $noDemo) {
            pass('cetak ekspor laporan tanpa angka contoh');
        } else {
            fail('cetak ekspor laporan tanpa angka contoh');
        }
        if ($home['status'] === 200 && str_contains($home['body'], 'CONTOH') && str_contains($home['body'], 'Angka contoh')) {
            pass('beranda laboran mode contoh berlabel');
        } else {
            fail('beranda laboran mode contoh berlabel');
        }
    }
    if ($mesin !== '') {
        $insertDay($mhsId, $today, 'masuk');
        if ($labJar !== []) {
            $masukHit = jsonBody(httpCall($labJar, 'GET', '/admin/attendance/snapshot?tab=tidak_lengkap&q='.rawurlencode(MHS_NITK)));
            $masukSummary = is_array($masukHit) ? ($masukHit['summary'] ?? []) : [];
            $masukOk = is_array($masukHit)
                && ($masukHit['summary_mode'] ?? '') === 'operasional'
                && (int) ($masukSummary['masuk'] ?? 0) >= 1
                && (int) ($masukSummary['hadir'] ?? -1) === 0
                && $rowStatus($masukHit, MHS_NITK) === 'masuk'
                && (float) ($masukSummary['persentase_lengkap'] ?? -1) !== 71.9;
            echo 'masuk tanpa pulang status '.($rowStatus($masukHit, MHS_NITK) ?? '-').' hadir '.(int) ($masukSummary['hadir'] ?? -1)."\n";
            if ($masukOk) {
                pass('masuk tanpa pulang tetap status masuk');
            } else {
                fail('masuk tanpa pulang tetap status masuk');
            }
        }
        DB::table('absensi_harian')->where('user_id', $mhsId)->whereDate('tanggal', $today)->update(['status' => 'alfa']);
        if ($labJar !== []) {
            $alfaHit = jsonBody(httpCall($labJar, 'GET', '/admin/attendance/snapshot?tab=alfa&q='.rawurlencode(MHS_NITK)));
            $alfaSummary = is_array($alfaHit) ? ($alfaHit['summary'] ?? []) : [];
            $alfaOk = is_array($alfaHit)
                && ($alfaHit['summary_mode'] ?? '') === 'operasional'
                && (int) ($alfaSummary['alfa'] ?? 0) >= 1
                && (int) ($alfaSummary['hadir'] ?? -1) === 0
                && $rowStatus($alfaHit, MHS_NITK) === 'alfa';
            echo 'alfa status '.($rowStatus($alfaHit, MHS_NITK) ?? '-').' jumlah '.(int) ($alfaSummary['alfa'] ?? -1)."\n";
            if ($alfaOk) {
                pass('baris alfa tetap alfa');
            } else {
                fail('baris alfa tetap alfa');
            }
        }
        DB::table('absensi_harian')->where('user_id', $mhsId)->whereDate('tanggal', $today)->update(['status' => 'lengkap']);
        if ($scheduled !== null && $scheduled !== $today) {
            $insertDay($mhsId, $scheduled);
        }
    }
    if ($orphan !== null) {
        AbsensiHarian::query()->insert([
            'user_id' => $otherId,
            'tanggal' => $orphan,
            'sesi' => 'pagi',
            'status' => 'belum',
            'masuk_log_id' => null,
            'pulang_log_id' => null,
            'created_at' => $orphan.' 00:00:00',
            'updated_at' => $orphan.' 00:00:00',
        ]);
    }

    $harianBefore = (int) DB::table('absensi_harian')->count();
    $orphanBefore = $orphan === null ? 0 : (int) DB::table('absensi_harian')->where('user_id', $otherId)->whereDate('tanggal', $orphan)->count();
    $nullFingerBefore = (int) DB::table('users')->where('role', 'mahasiswa')->whereNull('finger_id')->count();

    $expectSnapshot = function (string $label, array $res, string $scope, string $surface) : ?array {
        $class = $res['status'] >= 500 ? exceptionClass($res['body']) : '-';
        echo "ENDPOINT {$label} status {$res['status']} waktu {$res['at']} kelas {$class}\n";
        $json = jsonBody($res);
        if ($res['status'] !== 200 || ! is_array($json)) {
            fail($label.' status '.$res['status'].' kelas '.$class);
            return null;
        }
        $ok = ($json['ok'] ?? false) === true
            && ($json['realtime'] ?? true) === false
            && ($json['transport'] ?? '') === 'http-snapshot'
            && ($json['surface'] ?? '') === $surface
            && ($json['scope'] ?? '') === $scope;
        if ($ok) {
            pass($label.' kontrak');
        } else {
            fail($label.' kontrak');
        }

        return $json;
    };

    if ($labJar !== []) {
        $hit = httpCall($labJar, 'GET', '/admin/attendance/snapshot?q='.rawurlencode(MHS_NITK).'&tab=hadir');
        $json = $expectSnapshot('laboran rekap', $hit, 'laboran', 'rekap');
        if (is_array($json)) {
            $hasRow = str_contains($hit['body'], MHS_NITK);
            $koreksi = str_contains($hit['body'], 'btn-koreksi-rekap');
            $pagerOk = ! str_contains((string) ($json['regions']['pager'] ?? ''), 'snapshot');
            $mode = (string) ($json['summary_mode'] ?? '');
            echo "rekap summary_mode sebelum sisipan {$modeBeforeInsert} sesudah {$mode}\n";
            if ($hasRow && $koreksi && $pagerOk && $mode === 'operasional') {
                pass('laboran rekap memuat data baru dan koreksi');
            } else {
                fail('laboran rekap memuat data baru dan koreksi');
            }
            $keys = [];
            collectKeys($json['rows'] ?? [], $keys);
            if (in_array('harian_id', $keys, true) && in_array('koreksi_url', $keys, true)) {
                pass('laboran rekap field koreksi');
            } else {
                fail('laboran rekap field koreksi');
            }
        }
        $miss = httpCall($labJar, 'GET', '/admin/attendance/snapshot?kelas=SNAP-B');
        $missJson = jsonBody($miss);
        $detailRows = is_array($missJson) ? ($missJson['rows'] ?? []) : [];
        $detailNims = array_map(fn ($row) => (string) ($row['nim'] ?? ''), $detailRows);
        $detailHtml = is_array($missJson) ? (string) ($missJson['regions']['rows'] ?? '') : '';
        if ($miss['status'] === 200 && ! in_array(MHS_NITK, $detailNims, true) && ! str_contains($detailHtml, MHS_NITK)) {
            pass('filter kelas lain tidak memuat baris rekap uji');
        } else {
            fail('filter kelas lain tidak memuat baris rekap uji');
        }
        $orderA = jsonBody(httpCall($labJar, 'GET', '/admin/attendance/snapshot?tab=alfa&kelas=SNAP-A'));
        $orderB = jsonBody(httpCall($labJar, 'GET', '/admin/attendance/snapshot?kelas=SNAP-A&tab=alfa'));
        if (is_array($orderA) && is_array($orderB) && ($orderA['query'] ?? null) === ($orderB['query'] ?? null) && ($orderA['query'] ?? '') !== '') {
            pass('query snapshot kanonik');
        } else {
            fail('query snapshot kanonik');
        }
        $logHit = httpCall($labJar, 'GET', '/admin/log/absensi/snapshot?q='.rawurlencode(MHS_NITK));
        $logJson = $expectSnapshot('laboran log', $logHit, 'laboran', 'log');
        if (is_array($logJson)) {
            $logKeys = [];
            collectKeys($logJson['rows'] ?? [], $logKeys);
            $pagerOk = ! str_contains((string) ($logJson['regions']['pager'] ?? ''), 'snapshot');
            if (str_contains($logHit['body'], MHS_NITK) && $pagerOk && ! in_array('finger_template', $logKeys, true) && ! in_array('email', $logKeys, true)) {
                pass('laboran log data baru tanpa email');
            } else {
                fail('laboran log data baru tanpa email');
            }
        }
        $templateMid = templateChecksum();
        $slotMid = digestRows('users', ['id', 'finger_id', 'finger_template', 'shift']);
        $fpPage = httpCall($labJar, 'GET', '/admin/fingerprint');
        $fpPageAgain = httpCall($labJar, 'GET', '/admin/fingerprint');
        $fp = httpCall($labJar, 'GET', '/admin/fingerprint/snapshot?q='.rawurlencode(MHS_NITK));
        $fpAgain = httpCall($labJar, 'GET', '/admin/fingerprint/snapshot?q='.rawurlencode(MHS_NITK));
        $nullAfter = (int) DB::table('users')->where('role', 'mahasiswa')->whereNull('finger_id')->count();
        $slotAfter = digestRows('users', ['id', 'finger_id', 'finger_template', 'shift']);
        $fpClass = $fpPage['status'] >= 500 ? exceptionClass($fpPage['body']) : '-';
        echo "HALAMAN laboran fingerprint status {$fpPage['status']} ulang {$fpPageAgain['status']} waktu {$fpPage['at']} kelas {$fpClass}\n";
        echo "finger_id kosong sebelum {$nullFingerBefore} sesudah {$nullAfter}\n";
        $readOnly = $fpPage['status'] === 200
            && $fpPageAgain['status'] === 200
            && $fp['status'] === 200
            && $fpAgain['status'] === 200
            && $nullAfter === $nullFingerBefore
            && $slotAfter['sha256'] === $slotMid['sha256']
            && templateChecksum()['sha256'] === $templateMid['sha256']
            && ! str_contains($fpPage['body'], 'contoh mingguan');
        if ($readOnly) {
            pass('halaman dan snapshot fingerprint tidak menulis');
        } else {
            fail('halaman dan snapshot fingerprint tidak menulis');
        }
        $fpJson = $expectSnapshot('laboran fingerprint', $fp, 'laboran', 'fingerprint');
        if (is_array($fpJson)) {
            $keys = [];
            collectKeys($fpJson, $keys);
            $stillNull = DB::table('users')->where('id', $mhsId)->whereNull('finger_id')->exists();
            $rowKeys = [];
            collectKeys($fpJson['rows'] ?? [], $rowKeys);
            if ($stillNull && ! in_array('finger_template', $keys, true) && ! str_contains($fp['body'], 'finger_template') && ! in_array('sync_token', $rowKeys, true) && str_contains($fp['body'], MHS_NITK)) {
                pass('snapshot fingerprint baca-saja');
            } else {
                fail('snapshot fingerprint baca-saja');
            }
        }
        $cross = httpCall($labJar, 'GET', '/dosen/absensi/snapshot');
        echo "ENDPOINT laboran ke dosen snapshot status {$cross['status']} waktu {$cross['at']}\n";
        if ($cross['status'] === 403) {
            pass('laboran tidak membaca snapshot dosen');
        } else {
            fail('laboran tidak membaca snapshot dosen status '.$cross['status']);
        }
    }

    if ($dosJar !== []) {
        $hit = httpCall($dosJar, 'GET', '/dosen/absensi/snapshot?q='.rawurlencode(MHS_NITK));
        $json = $expectSnapshot('dosen rekap', $hit, 'dosen', 'rekap');
        if (is_array($json)) {
            $keys = [];
            collectKeys($json['rows'] ?? [], $keys);
            if (str_contains($hit['body'], MHS_NITK) && ! str_contains($hit['body'], 'btn-koreksi-rekap') && ! in_array('harian_id', $keys, true) && ! in_array('koreksi_url', $keys, true)) {
                pass('dosen rekap tanpa koreksi');
            } else {
                fail('dosen rekap tanpa koreksi');
            }
            if (($json['summary_mode'] ?? '') === 'operasional' && (int) (($json['summary']['hadir'] ?? -1)) >= 0 && (float) (($json['summary']['persentase_lengkap'] ?? -1)) !== 71.9) {
                pass('dosen rekap tanpa angka contoh');
            } else {
                fail('dosen rekap tanpa angka contoh');
            }
        }
        $dosenHome = httpCall($dosJar, 'GET', '/dosen/dashboard');
        if ($dosenHome['status'] === 200 && str_contains($dosenHome['body'], 'Angka contoh pada grafik beranda')) {
            pass('beranda dosen mode contoh berlabel');
        } else {
            fail('beranda dosen mode contoh berlabel');
        }
        $denied = httpCall($dosJar, 'GET', '/admin/fingerprint/snapshot');
        echo "ENDPOINT dosen fingerprint snapshot status {$denied['status']} waktu {$denied['at']}\n";
        if ($denied['status'] === 403) {
            pass('dosen tidak membaca snapshot fingerprint');
        } else {
            fail('dosen tidak membaca snapshot fingerprint status '.$denied['status']);
        }
    }

    $mobile = httpCall($empty, 'GET', '/api/mobile/me/absensi/snapshot?days=365', [], [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]);
    $mobileJson = $expectSnapshot('mahasiswa snapshot', $mobile, 'mahasiswa', 'absensi');
    $harianAfter = (int) DB::table('absensi_harian')->count();
    $orphanAfter = $orphan === null ? 0 : (int) DB::table('absensi_harian')->where('user_id', $otherId)->whereDate('tanggal', $orphan)->count();
    $legacy = httpCall($empty, 'GET', '/api/mobile/me/absensi?days=365', [], [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]);
    $harianAfterLegacy = (int) DB::table('absensi_harian')->count();
    $orphanAfterLegacy = $orphan === null ? 0 : (int) DB::table('absensi_harian')->where('user_id', $otherId)->whereDate('tanggal', $orphan)->count();
    echo "ENDPOINT riwayat lama status {$legacy['status']} waktu {$legacy['at']}\n";
    if ($legacy['status'] === 200 && $harianAfterLegacy === $harianAfter && $orphanAfterLegacy === $orphanAfter) {
        pass('riwayat lama tidak menghapus jejak kosong');
    } else {
        fail('riwayat lama tidak menghapus jejak kosong');
    }
    if (is_array($mobileJson)) {
        $keys = [];
        collectKeys($mobileJson, $keys);
        $own = str_contains($mobile['body'], $scheduled ?? $today);
        $leaks = str_contains($mobile['body'], OTHER_NITK) || in_array('email', $keys, true) || in_array('finger_template', $keys, true);
        $kept = $harianAfter === $harianBefore && $orphanAfter === $orphanBefore;
        if ($own && ! $leaks && $kept && ($mobileJson['success'] ?? false) === true) {
            pass('mahasiswa hanya data sendiri dan tidak menghapus jejak');
        } else {
            fail('mahasiswa hanya data sendiri dan tidak menghapus jejak');
        }
    }
} finally {
    cleanupSynthetic();
    assertTestDb();
}

$afterUsers = digestRows('users', $userColumns, fn ($q) => $q->whereNotIn('nitk_nidn', SYNTHETIC));
$afterTemplate = templateChecksum();
$afterLog = digestRows('log_absensi', Schema::getColumnListing('log_absensi'));
$afterHarian = digestRows('absensi_harian', Schema::getColumnListing('absensi_harian'));
foreach ([
    'pengguna asli' => [$baselineUsers, $afterUsers],
    'template' => [$baselineTemplate, $afterTemplate],
    'log_absensi' => [$baselineLog, $afterLog],
    'absensi_harian' => [$baselineHarian, $afterHarian],
] as $label => [$before, $after]) {
    $same = $before['jumlah'] === $after['jumlah'] && $before['sha256'] === $after['sha256'];
    echo $label.' jumlah '.$before['jumlah'].' sha sama '.($before['sha256'] === $after['sha256'] ? 'ya' : 'tidak')."\n";
    if ($same) {
        pass($label.' pulih');
    } else {
        fail($label.' pulih');
    }
}

$fresh = is_file($logPath) ? scanLog($logPath, $logOffset, false) : [];
echo 'ERROR baru selama uji '.count($fresh)."\n";
foreach ($fresh as $hit) {
    echo $hit['at'].' '.$hit['signature']."\n";
}
if ($fresh === []) {
    pass('uji tidak menambah ERROR log');
} else {
    fail('uji tidak menambah ERROR log');
}

$apache = [
    'http://localhost/Simalas/public/admin/attendance/snapshot',
    'http://localhost/Simalas/public/admin/log/absensi/snapshot',
    'http://localhost/Simalas/public/admin/fingerprint/snapshot',
    'http://localhost/Simalas/public/dosen/absensi/snapshot',
    'http://localhost/Simalas/public/api/mobile/me/absensi/snapshot',
];
foreach ($apache as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $at = date('Y-m-d H:i:s');
    $class = '-';
    if ($status >= 500 && is_string($raw)) {
        $class = exceptionClass($raw);
    }
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
    echo "APACHE {$path} status {$status} waktu {$at} kelas {$class}\n";
    $expect = str_contains($path, '/api/') ? 401 : 302;
    if ($status === $expect) {
        pass('apache tamu '.$path);
    } else {
        fail('apache tamu '.$path.' status '.$status);
    }
}

$envNow = [filemtime($envPath), filesize($envPath)];
if ($envNow === $envStamp) {
    pass('env operasional tidak berubah');
} else {
    fail('env operasional tidak berubah');
}
$probe = new PDO(
    'mysql:host=127.0.0.1;port='.$port.';dbname='.FORBIDDEN_DB.';charset=utf8mb4',
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$probeDb = (string) $probe->query('SELECT DATABASE()')->fetchColumn();
if ($probeDb !== FORBIDDEN_DB) {
    refuse('probe operasional salah target');
}
$operLog = (int) $probe->query('SELECT COUNT(*) FROM log_absensi')->fetchColumn();
echo "operasional log_absensi {$operLog} tidak ditambah uji\n";

echo 'HASIL gagal '.$failures."\n";
exit($failures > 0 ? 1 : 0);
