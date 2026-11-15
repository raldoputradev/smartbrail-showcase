<?php

/**
 * Uji migration pada database lokal terpisah.
 * Menolak target simalas. Tidak mengubah .env. Tidak mengirim email.
 * Tidak mencetak NIM, email, template, atau API key.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

const TEST_DB = 'simalas_cleanup_test';
const FORBIDDEN_DB = 'simalas';
const BACKUP = 'C:\\project-archives\\Simalas-db-backup\\simalas-before-locker-drop-20261008.sql';
const DROP_PATH = 'database/migrations/2026_10_08_160000_drop_locker_schema_from_attendance.php';
const DROP_NAME = '2026_10_08_160000_drop_locker_schema_from_attendance';
const IDEM_PATH = 'database/migrations/2026_10_08_104500_add_tap_event_idempotency.php';
const IDEM_NAME = '2026_10_08_104500_add_tap_event_idempotency';
const LAB_NITK = 'UJI-CLEANUP-LAB';
const DOS_NITK = 'UJI-CLEANUP-DOS';

$failures = 0;
$cnf = null;
$sessionDir = 'C:\\project-archives\\Simalas-db-backup\\cleanup-test-sessions';
$envStamp = null;

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

function assertName(string $database, string $step): void
{
    if ($database === FORBIDDEN_DB || strcasecmp($database, FORBIDDEN_DB) === 0) {
        refuse("langkah {$step} mengarah ke database operasional");
    }
    if ($database !== TEST_DB) {
        refuse("langkah {$step} target bukan database uji");
    }
}

function safeThrowable(Throwable $e): string
{
    $msg = $e->getMessage();
    if (preg_match("/Unknown column '([A-Za-z0-9_]+)'/", $msg, $m)) {
        return $e::class.' kolom '.$m[1];
    }
    if (preg_match("/Table '([A-Za-z0-9_]+)\\.([A-Za-z0-9_]+)' doesn't exist/", $msg, $m)) {
        return $e::class.' tabel '.$m[2];
    }
    if (str_contains($msg, 'Duplicate entry')) {
        return $e::class.' duplikat';
    }

    return $e::class.' kode '.$e->getCode();
}

function readEnvFile(string $path): array
{
    $text = (string) file_get_contents($path);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $out = [];
    foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && str_ends_with($value, $q)) {
                $value = stripcslashes(substr($value, 1, -1));
            }
        }
        $out[$key] = $value;
    }

    return $out;
}

function scanBackup(string $path): void
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        refuse('backup tidak bisa dibaca');
    }
    $danger = 0;
    while (($line = fgets($handle)) !== false) {
        $trim = ltrim($line);
        if (preg_match('/^(CREATE|DROP)\s+DATABASE\b/i', $trim) || preg_match('/^USE\s+/i', $trim)) {
            $danger++;
        }
    }
    fclose($handle);
    if ($danger > 0) {
        refuse('backup memuat perintah database lain');
    }
    echo "backup perintah pindah-database 0 byte ".filesize($path)."\n";
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

function migrationNames(): array
{
    assertTestDb();

    return DB::table('migrations')->orderBy('id')->pluck('migration')->all();
}

function runOneMigration(string $relativePath, string $expectedName): void
{
    assertTestDb();
    $before = migrationNames();
    if (in_array($expectedName, $before, true)) {
        refuse('migration target sudah tercatat');
    }
    echo "PERINTAH php artisan migrate --path={$relativePath} --database=mysql --force\n";
    echo 'TARGET '.TEST_DB."\n";
    $code = Artisan::call('migrate', [
        '--path' => $relativePath,
        '--database' => 'mysql',
        '--force' => true,
    ]);
    $output = trim(preg_replace('/\s+/', ' ', Artisan::output()) ?? '');
    echo "artisan kode {$code}\n";
    echo "artisan keluaran {$output}\n";
    assertTestDb();
    $added = array_values(array_diff(migrationNames(), $before));
    echo 'migration baru '.(empty($added) ? '(tidak ada)' : implode(', ', $added))."\n";
    if ($code !== 0 || $added !== [$expectedName]) {
        fail('migration '.$expectedName);
        refuse('hasil migration tidak sesuai satu file target');
    }
    pass('migration '.$expectedName);
}

function compareDigest(string $label, array $before, array $after): void
{
    $same = $before['jumlah'] === $after['jumlah'] && $before['sha256'] === $after['sha256'];
    echo $label.' jumlah '.$before['jumlah'].' sha sama '.($before['sha256'] === $after['sha256'] ? 'ya' : 'tidak')."\n";
    if ($same) {
        pass($label);
    } else {
        fail($label);
    }
}

function httpCall(array &$jar, string $method, string $uri, array $params = [], array $headers = []): array
{
    assertTestDb();
    Illuminate\Support\Facades\Auth::forgetGuards();
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
    ];
}

function pageError(string $body): string
{
    if (preg_match("/Unknown column '([A-Za-z0-9_]+)'/i", $body, $m)) {
        return 'kolom '.$m[1];
    }
    if (preg_match("/Table '[A-Za-z0-9_]+\\.([A-Za-z0-9_]+)' doesn't exist/i", $body, $m)) {
        return 'tabel '.$m[1];
    }
    if (preg_match('/Route \[([A-Za-z0-9_.]+)\] not defined/', $body, $m)) {
        return 'rute '.$m[1];
    }

    return 'error tanpa detail aman';
}

if (TEST_DB === FORBIDDEN_DB) {
    refuse('nama database uji tidak valid');
}

$root = dirname(__DIR__, 2);
$envPath = $root.'\\.env';
$envStamp = [filemtime($envPath), filesize($envPath)];
$env = readEnvFile($envPath);
$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$envDatabase = $env['DB_DATABASE'] ?? '';
if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
    refuse('host database bukan lokal');
}
if (($env['DB_URL'] ?? '') !== '') {
    refuse('DB_URL terisi');
}
if ($envDatabase !== FORBIDDEN_DB) {
    refuse('env operasional tidak menunjuk database yang diharapkan');
}
assertName(TEST_DB, 'awal');
echo 'TARGET '.TEST_DB." host lokal port {$port}\n";
echo "env operasional tidak diubah pada awal pemeriksaan\n";

if (! is_file(BACKUP)) {
    refuse('file backup tidak ada');
}
scanBackup(BACKUP);

$pdo = new PDO(
    "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$current = $pdo->query('SELECT DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
if (($current['db'] ?? null) === FORBIDDEN_DB) {
    refuse('pdo awal tersambung ke database operasional');
}

$quoted = str_replace('`', '``', TEST_DB);
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$exists->execute([TEST_DB]);
$alreadyRestored = false;
if ((int) $exists->fetchColumn() > 0) {
    $tables = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
    $tables->execute([TEST_DB]);
    $tableCount = (int) $tables->fetchColumn();
    if ($tableCount > 0) {
        $pdo->exec('USE `'.$quoted.'`');
        $active = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        assertName($active, 'database uji yang sudah ada');
        $hasMigrations = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migrations'")->fetchColumn() === 1;
        $hasLocker = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'log_loker'")->fetchColumn() === 1;
        $newMigrations = $hasMigrations
            ? (int) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration IN ('".DROP_NAME."', '".IDEM_NAME."')")->fetchColumn()
            : 0;
        $hasUsers = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchColumn() === 1
            && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        $pagesOnly = in_array('--pages', $argv ?? [], true);
        $beforeCleanup = $hasMigrations && $hasLocker && $newMigrations === 0 && $hasUsers;
        $afterCleanup = $pagesOnly && $hasMigrations && ! $hasLocker && $newMigrations === 2 && $hasUsers;
        if (! $beforeCleanup && ! $afterCleanup) {
            refuse('database uji sudah berisi tabel yang bukan salinan utuh sebelum cleanup');
        }
        $alreadyRestored = true;
        echo $afterCleanup
            ? "database uji sudah selesai cleanup, pemulihan tidak diulang\n"
            : "database uji sudah berisi salinan backup sebelum cleanup, pemulihan tidak diulang\n";
    }
} else {
    $pdo->exec("CREATE DATABASE `{$quoted}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "database uji dibuat\n";
}

if ($alreadyRestored) {
    echo "pemulihan dilewati\n";
} else {
$cnf = 'C:\\project-archives\\Simalas-db-backup\\mysql-cleanup-once.cnf';
file_put_contents($cnf, "[client]\nuser=".addslashes($user)."\npassword=".addslashes($pass)."\nhost=127.0.0.1\nport=".addslashes((string) $port)."\n");
$mysql = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$spec = [
    0 => ['file', BACKUP, 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$proc = proc_open([
    $mysql,
    '--defaults-extra-file='.$cnf,
    '--default-character-set=utf8mb4',
    '--one-database',
    TEST_DB,
], $spec, $pipes);
if (! is_resource($proc)) {
    refuse('mysql klien tidak jalan');
}
if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}
$stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
$stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
if (isset($pipes[1]) && is_resource($pipes[1])) {
    fclose($pipes[1]);
}
if (isset($pipes[2]) && is_resource($pipes[2])) {
    fclose($pipes[2]);
}
$restoreCode = proc_close($proc);
if (is_file($cnf)) {
    unlink($cnf);
    $cnf = null;
}
if ($restoreCode !== 0) {
    $note = preg_replace('/password=\S+/i', 'password=[disembunyikan]', (string) $stderr) ?? '';
    fwrite(STDERR, "Pemulihan gagal kode {$restoreCode}. ".trim($note)."\n");
    exit(1);
}
echo "pemulihan selesai kode 0\n";
if (trim((string) $stdout) !== '') {
    echo "mysql stdout ada, tidak dicetak\n";
}
}

$check = $pdo->prepare('SELECT DATABASE()');
$pdo->exec('USE `'.$quoted.'`');
$nowDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
assertName($nowDb, 'setelah pemulihan');
echo "koneksi setelah pemulihan {$nowDb}\n";

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
echo "laravel tersambung ke ".TEST_DB."\n";

$stableLogColumns = Schema::getColumnListing('log_absensi');
$stableHarianColumns = Schema::getColumnListing('absensi_harian');
$stableUserColumns = Schema::getColumnListing('users');
$mesinKeep = array_values(array_intersect(
    ['id', 'id_mesin', 'nama_mesin', 'tipe', 'api_key', 'is_active', 'last_seen_at', 'created_at', 'updated_at'],
    Schema::getColumnListing('mesin_iot')
));

$snapshot = function () use ($stableLogColumns, $stableHarianColumns, $stableUserColumns, $mesinKeep): array {
    assertTestDb();

    return [
        'users' => digestRows('users', $stableUserColumns),
        'template' => templateChecksum(),
        'log_absensi' => digestRows('log_absensi', $stableLogColumns),
        'absensi_harian' => digestRows('absensi_harian', $stableHarianColumns),
        'mesin_absensi' => digestRows('mesin_iot', $mesinKeep, fn ($q) => $q->where('tipe', 'absensi')),
        'kebijakan' => digestRows('system_settings', ['id', 'key', 'value'], fn ($q) => $q->where('key', 'absensi_kebijakan')),
        'sesi' => digestRows('absensi_sesi_settings', Schema::getColumnListing('absensi_sesi_settings')),
        'libur' => digestRows('absensi_kalender_libur', Schema::getColumnListing('absensi_kalender_libur')),
    ];
};

$pagesOnly = in_array('--pages', $argv ?? [], true);
if ($pagesOnly) {
    assertTestDb();
    $ready = ! Schema::hasTable('log_loker')
        && in_array(DROP_NAME, migrationNames(), true)
        && in_array(IDEM_NAME, migrationNames(), true)
        && Schema::hasTable('tap_receipts');
    if (! $ready) {
        refuse('mode halaman meminta database uji yang sudah melewati cleanup dan idempotensi');
    }
    echo "MODE halaman: migration tidak diulang\n";
} else {
echo "BASELINE\n";
$before = $snapshot();
$modeRow = DB::table('system_settings')->where('key', 'absensi_kebijakan')->value('value');
$modeJson = json_decode((string) $modeRow, true);
$mode = is_array($modeJson) && in_array(($modeJson['mode'] ?? ''), ['kamis', 'harian'], true) ? $modeJson['mode'] : 'lain';
$shifts = DB::table('absensi_sesi_settings')->orderBy('shift')->pluck('shift')->implode(', ');
$tipe = DB::table('mesin_iot')->select('tipe', DB::raw('COUNT(*) as jumlah'))->groupBy('tipe')->get();
echo 'pengguna/finger_id/template '.$before['template']['jumlah']."\n";
echo 'log_absensi '.$before['log_absensi']['jumlah'].' absensi_harian '.$before['absensi_harian']['jumlah']."\n";
echo "kebijakan mode {$mode} sesi {$shifts} libur ".$before['libur']['jumlah']."\n";
foreach ($tipe as $row) {
    $name = (string) ($row->tipe ?? '');
    if (! in_array($name, ['absensi', 'loker', 'pintu', 'mesin_cuci'], true)) {
        $name = 'tipe-lain';
    }
    echo "mesin {$name} jumlah ".(int) $row->jumlah."\n";
}
$pending = array_values(array_diff([DROP_NAME, IDEM_NAME], migrationNames()));
echo 'pending uji '.implode(', ', $pending)."\n";

echo "CLEANUP LOCKER\n";
runOneMigration(DROP_PATH, DROP_NAME);
if (in_array(IDEM_NAME, migrationNames(), true)) {
    fail('idempotensi ikut tercatat');
    refuse('idempotensi berjalan bersama cleanup');
}
pass('idempotensi tidak ikut berjalan');

$afterDrop = $snapshot();
compareDigest('pengguna', $before['users'], $afterDrop['users']);
compareDigest('template', $before['template'], $afterDrop['template']);
compareDigest('log_absensi', $before['log_absensi'], $afterDrop['log_absensi']);
compareDigest('absensi_harian', $before['absensi_harian'], $afterDrop['absensi_harian']);
compareDigest('mesin absensi', $before['mesin_absensi'], $afterDrop['mesin_absensi']);
compareDigest('absensi_kebijakan', $before['kebijakan'], $afterDrop['kebijakan']);
compareDigest('absensi_sesi_settings', $before['sesi'], $afterDrop['sesi']);
compareDigest('absensi_kalender_libur', $before['libur'], $afterDrop['libur']);

$lockerGone = ! Schema::hasTable('log_loker')
    && ! Schema::hasTable('loker_slot_admin_logs')
    && ! Schema::hasColumn('mesin_iot', 'locker_slots')
    && ! Schema::hasColumn('mesin_iot', 'locker_state')
    && ! Schema::hasColumn('mesin_iot', 'locker_state_note')
    && ! Schema::hasColumn('mesin_iot', 'allowed_teams')
    && (int) DB::table('mesin_iot')->where('tipe', 'loker')->count() === 0
    && (int) DB::table('role_permissions')->whereIn('menu_key', ['lockers', 'log_loker', 'dosen_loker'])->count() === 0
    && (int) DB::table('system_settings')->whereIn('key', ['machine_access', 'locker_fulfillment'])->count() === 0
    && (int) DB::table('announcements')->where('channel', 'notifikasi_loker')->orWhere('judul', 'like', '[LOKER:%')->count() === 0;
if ($lockerGone) {
    pass('objek locker target hilang');
} else {
    fail('objek locker target hilang');
}
$pintu = (int) DB::table('mesin_iot')->where('tipe', 'pintu')->count();
$cuci = (int) DB::table('mesin_iot')->where('tipe', 'mesin_cuci')->count();
echo "mesin pintu {$pintu} mesin_cuci {$cuci}\n";
if ($pintu === 1 && $cuci === 1) {
    pass('pintu dan mesin_cuci tetap');
} else {
    fail('pintu dan mesin_cuci tetap');
}
if ((int) DB::table('system_settings')->where('key', 'absensi_kebijakan')->count() !== 1) {
    fail('absensi_kebijakan tetap ada');
} else {
    pass('absensi_kebijakan tetap ada');
}

if ($failures > 0) {
    refuse('cleanup belum lolos, idempotensi tidak dijalankan');
}

echo "IDEMPOTENSI\n";
runOneMigration(IDEM_PATH, IDEM_NAME);
$afterIdem = $snapshot();
compareDigest('pengguna setelah idempotensi', $before['users'], $afterIdem['users']);
compareDigest('template setelah idempotensi', $before['template'], $afterIdem['template']);
compareDigest('log_absensi setelah idempotensi', $before['log_absensi'], $afterIdem['log_absensi']);
compareDigest('absensi_harian setelah idempotensi', $before['absensi_harian'], $afterIdem['absensi_harian']);
compareDigest('mesin absensi setelah idempotensi', $before['mesin_absensi'], $afterIdem['mesin_absensi']);
compareDigest('absensi_kebijakan setelah idempotensi', $before['kebijakan'], $afterIdem['kebijakan']);
compareDigest('absensi_sesi_settings setelah idempotensi', $before['sesi'], $afterIdem['sesi']);
compareDigest('absensi_kalender_libur setelah idempotensi', $before['libur'], $afterIdem['libur']);
$idemSchema = Schema::hasTable('tap_receipts')
    && Schema::hasColumn('log_absensi', 'event_id')
    && Schema::hasColumn('log_absensi', 'diterima_pada')
    && (int) DB::table('tap_receipts')->count() === 0
    && (int) DB::table('log_absensi')->whereNull('event_id')->count() === (int) DB::table('log_absensi')->count();
if ($idemSchema) {
    pass('skema idempotensi dan baris lama');
} else {
    fail('skema idempotensi dan baris lama');
}

}
echo "HALAMAN\n";
$required = DB::select(
    'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND IS_NULLABLE = ? AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE ?',
    [TEST_DB, 'users', 'NO', '%auto_increment%']
);
$now = date('Y-m-d H:i:s');
$accounts = [
    'laboran' => [LAB_NITK, 'Akun Uji Laboran', 'uji-cleanup-lab@example.test'],
    'dosen' => [DOS_NITK, 'Akun Uji Dosen', 'uji-cleanup-dos@example.test'],
];
foreach ($accounts as $role => [$nitk, $nama, $email]) {
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
    $missing = [];
    foreach ($required as $column) {
        $name = $column->COLUMN_NAME;
        if (array_key_exists($name, $row)) {
            $insert[$name] = $row[$name];
            continue;
        }
        $missing[] = $name;
    }
    if ($missing !== []) {
        fail('kolom wajib users '.implode(',', $missing));
        refuse('akun sintetis tidak dibuat');
    }
    foreach ($row as $key => $value) {
        if (Schema::hasColumn('users', $key)) {
            $insert[$key] = $value;
        }
    }
    $existsAccount = DB::table('users')->where('nitk_nidn', $nitk)->exists();
    if ($existsAccount) {
        continue;
    }
    DB::table('users')->insert($insert);
}
$originalUsers = digestRows('users', $stableUserColumns, fn ($q) => $q->whereNotIn('nitk_nidn', [LAB_NITK, DOS_NITK]));
if (! $pagesOnly) {
    compareDigest('pengguna asli setelah akun sintetis', $before['users'], $originalUsers);
} else {
    echo 'pengguna asli jumlah '.$originalUsers['jumlah']."\n";
}
pass('akun sintetis hanya di database uji');

$passwords = [];
foreach ($accounts as $role => [$nitk]) {
    $plain = bin2hex(random_bytes(18));
    DB::table('users')->where('nitk_nidn', $nitk)->update(['password' => Hash::make($plain)]);
    $passwords[$role] = [$nitk, $plain];
}

$loginAs = function (string $role) use (&$passwords): array {
    $jar = [];
    $_COOKIE = [];
    Illuminate\Support\Facades\Auth::logout();
    Illuminate\Support\Facades\Auth::forgetGuards();
    $page = httpCall($jar, 'GET', '/masuk');
    if ($page['status'] !== 200 || ! preg_match('/name="_token" value="([^"]+)"/', $page['body'], $match)) {
        $path = $page['location'] !== '' ? (parse_url($page['location'], PHP_URL_PATH) ?: 'redirect') : '-';
        fail('token masuk '.$role.' status '.$page['status'].' byte '.$page['bytes'].' lokasi '.$path);
        return [];
    }
    [$nitk, $plain] = $passwords[$role];
    $posted = httpCall($jar, 'POST', '/masuk', [
        '_token' => $match[1],
        'nitk_nidn' => $nitk,
        'password' => $plain,
        'role' => $role,
    ], ['Accept' => 'application/json']);
    $json = json_decode($posted['body'], true);
    $ok = $posted['status'] === 200 && is_array($json) && ($json['ok'] ?? false) === true;
    $redirect = (string) ($json['redirect'] ?? '');
    $expects = $role === 'laboran' ? 'admin' : 'dosen';
    if ($ok && str_contains($redirect, $expects)) {
        pass('login '.$role);
    } else {
        fail('login '.$role.' status '.$posted['status']);
    }
    unset($passwords[$role]);

    return $jar;
};

$pages = [
    'laboran' => [
        ['/admin/dashboard', ['Dashboard']],
        ['/admin/users', ['User', 'Mahasiswa', 'Laboran']],
        ['/admin/mesin', ['Mesin']],
        ['/admin/fingerprint', ['Fingerprint', 'Sidik']],
        ['/admin/absensi/pengaturan', ['Absensi', 'Sesi']],
        ['/admin/settings', ['Libur', 'Program']],
        ['/admin/attendance?tab=alfa', ['Alfa', 'Hadir']],
        ['/admin/attendance?tab=hadir&kelas=ZZZ-TIDAK-ADA', ['Hadir']],
        ['/admin/attendance/print?tab=all', ['Rekap', 'Hadir']],
        ['/admin/reports?periode=bulan_lalu&aktivitas=absensi', ['Hadir', 'Laporan']],
        ['/admin/reports?periode=custom&tanggal_mulai=2026-06-01&tanggal_akhir=2026-06-30&kelas=ZZZ-TIDAK-ADA', ['Hadir', 'Laporan']],
        ['/admin/reports/print?periode=bulan_lalu', ['Hadir', 'Laporan']],
    ],
    'dosen' => [
        ['/dosen/dashboard', ['Dashboard']],
        ['/dosen/absensi?tab=tidak_lengkap', ['Absensi', 'Tidak']],
        ['/dosen/mahasiswa', ['Mahasiswa']],
        ['/dosen/reports?periode=minggu_ini', ['Hadir', 'Laporan']],
        ['/dosen/reports/print?periode=minggu_ini', ['Hadir', 'Laporan']],
    ],
];
$exports = [
    'laboran' => [
        '/admin/attendance/export?tab=all',
        '/admin/reports/export/excel?periode=bulan_lalu',
        '/admin/reports/export/word?periode=bulan_lalu',
    ],
    'dosen' => [
        '/dosen/reports/export/excel?periode=minggu_ini',
        '/dosen/reports/export/word?periode=minggu_ini',
        '/dosen/mahasiswa/export.csv',
    ],
];

$jars = [];
foreach (['laboran', 'dosen'] as $role) {
    $jar = $loginAs($role);
    $jars[$role] = $jar;
    if ($jar === []) {
        continue;
    }
    foreach ($pages[$role] as [$path, $needles]) {
        $res = httpCall($jar, 'GET', $path);
        $loginForm = str_contains($res['body'], 'id="loginIdInput"');
        $lockerUi = str_contains($res['body'], 'Manajemen Loker') || str_contains($res['body'], 'Loker Pintar') || str_contains($res['body'], 'Audit Loker');
        $marked = false;
        foreach ($needles as $needle) {
            if (stripos($res['body'], $needle) !== false) {
                $marked = true;
                break;
            }
        }
        if ($res['status'] === 200 && $marked && ! $loginForm && ! $lockerUi) {
            pass($role.' '.$path);
        } else {
            $why = $res['status'].' '.$res['bytes'].'b';
            if ($res['status'] >= 500) {
                $why .= ' '.pageError($res['body']);
            } elseif ($loginForm) {
                $why .= ' kembali ke masuk';
            } elseif ($lockerUi) {
                $why .= ' menu locker';
            } elseif (! $marked) {
                $why .= ' penanda tidak ketemu';
            }
            fail($role.' '.$path.' '.$why);
        }
    }
    foreach ($exports[$role] as $path) {
        $res = httpCall($jar, 'GET', $path);
        $type = strtolower($res['type']);
        $brokenExport = str_contains($res['body'], 'id="loginIdInput"')
            || str_contains($res['body'], 'SQLSTATE')
            || str_contains($res['body'], 'ErrorException');
        $isCsv = str_contains($type, 'csv');
        $hasTable = str_contains($res['body'], '<table') || str_contains($res['body'], '<html');
        $exportOk = $res['status'] === 200 && $res['bytes'] > 100 && ! $brokenExport && ($hasTable || $isCsv) && (
            str_contains($type, 'excel')
            || str_contains($type, 'spreadsheet')
            || str_contains($type, 'word')
            || str_contains($type, 'csv')
            || str_contains($type, 'octet-stream')
        );
        if ($exportOk) {
            pass($role.' ekspor '.$path.' byte '.$res['bytes']);
        } else {
            $why = $res['status'] >= 500 ? pageError($res['body']) : 'bukan berkas';
            fail($role.' ekspor '.$path.' status '.$res['status'].' '.$why);
        }
    }
}

$labJar = $jars['laboran'] ?? [];
if ($labJar !== []) {
    $cross = httpCall($labJar, 'GET', '/dosen/dashboard');
    if (in_array($cross['status'], [302, 403], true)) {
        pass('laboran tidak masuk dashboard dosen status '.$cross['status']);
    } else {
        fail('laboran tidak masuk dashboard dosen status '.$cross['status']);
    }
}

echo 'demo dashboard '.(config('simalas.dashboard_demo') ? 'aktif' : 'nonaktif')."\n";
$mailer = Mail::getFacadeRoot();
$sentCount = $mailer instanceof Illuminate\Support\Testing\Fakes\MailFake
    ? $mailer->sent(Illuminate\Mail\Mailable::class)->count()
    : -1;
echo "email tercatat mailer uji {$sentCount}\n";

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
$lockerTable = (int) $probe->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'simalas' AND TABLE_NAME = 'log_loker'")->fetchColumn();
$dropRan = (int) $probe->query("SELECT COUNT(*) FROM migrations WHERE migration = '".DROP_NAME."'")->fetchColumn();
$idemRan = (int) $probe->query("SELECT COUNT(*) FROM migrations WHERE migration = '".IDEM_NAME."'")->fetchColumn();
$opUsers = (int) $probe->query('SELECT COUNT(*) FROM users')->fetchColumn();
$opLog = (int) $probe->query('SELECT COUNT(*) FROM log_absensi')->fetchColumn();
$opHarian = (int) $probe->query('SELECT COUNT(*) FROM absensi_harian')->fetchColumn();
echo "baca-saja operasional log_loker tabel {$lockerTable} migration drop {$dropRan} idempotensi {$idemRan}\n";
echo 'baca-saja operasional pengguna '.$opUsers.' log '.$opLog.' harian '.$opHarian."\n";
if (isset($before)) {
    echo 'baseline uji pengguna '.$before['users']['jumlah'].' log '.$before['log_absensi']['jumlah'].' harian '.$before['absensi_harian']['jumlah']."\n";
}
if ($lockerTable === 1 && $dropRan === 0 && $idemRan === 0) {
    pass('operasional tidak dimigrasi');
} else {
    fail('operasional tidak dimigrasi');
}

if (is_file((string) $cnf)) {
    unlink($cnf);
}
if (is_dir($sessionDir)) {
    foreach (glob($sessionDir.'\\*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($sessionDir);
    echo "berkas sesi uji dihapus\n";
}

echo 'database uji dipertahankan '.TEST_DB."\n";
echo 'HASIL gagal '.$failures."\n";
exit($failures > 0 ? 1 : 0);
