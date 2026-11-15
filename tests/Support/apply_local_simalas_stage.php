<?php

/**
 * Cleanup locker lalu idempotensi pada MySQL lokal database simalas.
 * Berhenti jika target bukan simalas, jika cleanup mengubah data absensi,
 * atau jika uji perilaku di database pengujian gagal.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const OPERATIONAL_DB = 'simalas';
const TEST_DB = 'simalas_cleanup_test';
const DROP_PATH = 'database/migrations/2026_10_08_160000_drop_locker_schema_from_attendance.php';
const DROP_NAME = '2026_10_08_160000_drop_locker_schema_from_attendance';
const IDEM_PATH = 'database/migrations/2026_10_08_104500_add_tap_event_idempotency.php';
const IDEM_NAME = '2026_10_08_104500_add_tap_event_idempotency';

$failures = 0;
$cnf = null;

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
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $value = stripcslashes(substr($value, 1, -1));
        }
        $out[trim($key)] = $value;
    }

    return $out;
}

function assertOperational(): void
{
    $configured = (string) config('database.connections.mysql.database');
    $host = (string) config('database.connections.mysql.host');
    $current = (string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '');
    if (! in_array($host, ['127.0.0.1', 'localhost'], true) || $current === TEST_DB || $configured === TEST_DB) {
        refuse('koneksi bukan MySQL lokal simalas');
    }
    if ($configured !== OPERATIONAL_DB || $current !== OPERATIONAL_DB) {
        refuse('SELECT DATABASE bukan simalas');
    }
}

function digest(string $table, array $columns, ?callable $restrict = null): array
{
    assertOperational();
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
    assertOperational();
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

function compare(string $label, array $before, array $after): void
{
    $same = $before === $after;
    echo $label.' jumlah '.$before['jumlah'].' checksum '.($before['sha256'] === $after['sha256'] ? 'sama' : 'berbeda')."\n";
    if ($same) {
        pass($label);
    } else {
        fail($label);
    }
}

function snapshot(): array
{
    $logColumns = array_values(array_diff(Schema::getColumnListing('log_absensi'), ['event_id', 'diterima_pada']));
    $mesinKeep = array_values(array_intersect(
        ['id', 'id_mesin', 'nama_mesin', 'tipe', 'api_key', 'is_active', 'last_seen_at', 'created_at', 'updated_at'],
        Schema::getColumnListing('mesin_iot')
    ));

    return [
        'users' => digest('users', Schema::getColumnListing('users')),
        'template' => templateChecksum(),
        'log_absensi' => digest('log_absensi', $logColumns),
        'absensi_harian' => digest('absensi_harian', Schema::getColumnListing('absensi_harian')),
        'mesin_absensi' => digest('mesin_iot', $mesinKeep, fn ($q) => $q->where('tipe', 'absensi')),
        'kebijakan' => digest('system_settings', ['id', 'key', 'value'], fn ($q) => $q->where('key', 'absensi_kebijakan')),
        'sesi' => digest('absensi_sesi_settings', Schema::getColumnListing('absensi_sesi_settings')),
        'libur' => digest('absensi_kalender_libur', Schema::getColumnListing('absensi_kalender_libur')),
    ];
}

function runOne(string $path, string $name): void
{
    assertOperational();
    $before = DB::table('migrations')->pluck('migration')->all();
    if (in_array($name, $before, true)) {
        refuse('migration sudah tercatat: '.$name);
    }
    echo "PERINTAH php artisan migrate --path={$path} --database=mysql --force\n";
    echo 'TARGET '.OPERATIONAL_DB."\n";
    $code = Artisan::call('migrate', [
        '--path' => $path,
        '--database' => 'mysql',
        '--force' => true,
    ]);
    $output = trim(preg_replace('/\s+/', ' ', Artisan::output()) ?? '');
    echo "artisan kode {$code}\n";
    echo "artisan keluaran {$output}\n";
    assertOperational();
    $added = array_values(array_diff(DB::table('migrations')->pluck('migration')->all(), $before));
    echo 'migration baru '.(empty($added) ? '(tidak ada)' : implode(', ', $added))."\n";
    if ($code !== 0 || $added !== [$name]) {
        fail($name);
        refuse('migration tidak sesuai satu file target');
    }
    pass($name);
}

$root = dirname(__DIR__, 2);
$envPath = $root.'\\.env';
$envStamp = [filemtime($envPath), filesize($envPath)];
$env = readEnvFile($envPath);
if (! in_array($env['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true) || ($env['DB_URL'] ?? '') !== '') {
    refuse('host env bukan lokal atau DB_URL terisi');
}
if (($env['DB_DATABASE'] ?? '') !== OPERATIONAL_DB) {
    refuse('env tidak menunjuk simalas');
}
$port = (string) ($env['DB_PORT'] ?? '3306');
$user = (string) ($env['DB_USERNAME'] ?? '');
$pass = (string) ($env['DB_PASSWORD'] ?? '');

$pdo = new PDO(
    "mysql:host=127.0.0.1;port={$port};dbname=".OPERATIONAL_DB.';charset=utf8mb4',
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$current = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($current !== OPERATIONAL_DB) {
    refuse('pdo bukan simalas');
}
echo 'TARGET '.$current." host lokal port {$port}\n";

$backupDir = 'C:\\project-archives\\Simalas-db-backup';
if (! is_dir($backupDir)) {
    mkdir($backupDir, 0700, true);
}
$backup = $backupDir.'\\simalas-local-before-locker-idem-'.date('Ymd-His').'.sql';
$cnf = $backupDir.'\\mysql-operational-once.cnf';
file_put_contents($cnf, "[client]\nuser=".addslashes($user)."\npassword=".addslashes($pass)."\nhost=127.0.0.1\nport=".addslashes($port)."\n");
$dump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$proc = proc_open([
    $dump,
    '--defaults-extra-file='.$cnf,
    '--single-transaction',
    '--routines',
    '--triggers',
    '--result-file='.$backup,
    OPERATIONAL_DB,
], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (! is_resource($proc)) {
    refuse('mysqldump tidak jalan');
}
fclose($pipes[0]);
stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($proc);
if (is_file($cnf)) {
    unlink($cnf);
    $cnf = null;
}
if ($code !== 0 || ! is_file($backup) || filesize($backup) < 1000) {
    $note = preg_replace('/password=\S+/i', 'password=[disembunyikan]', (string) $stderr) ?? '';
    fwrite(STDERR, 'Cadangan gagal kode '.$code.' '.trim($note)."\n");
    exit(1);
}
$aclUser = getenv('USERNAME') ?: '';
if ($aclUser !== '') {
    $acl = proc_open([
        'icacls',
        $backup,
        '/inheritance:r',
        '/grant:r',
        $aclUser.':(R)',
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $aclPipes);
    if (is_resource($acl)) {
        fclose($aclPipes[0]);
        stream_get_contents($aclPipes[1]);
        stream_get_contents($aclPipes[2]);
        fclose($aclPipes[1]);
        fclose($aclPipes[2]);
        proc_close($acl);
    }
}
echo 'backup byte '.filesize($backup)."\n";
pass('backup terbaru di luar htdocs');

foreach ([
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => $port,
    'DB_DATABASE' => OPERATIONAL_DB,
    'DB_USERNAME' => $user,
    'DB_PASSWORD' => $pass,
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
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
    'database.connections.mysql.port' => $port,
    'database.connections.mysql.database' => OPERATIONAL_DB,
    'database.connections.mysql.username' => $user,
    'database.connections.mysql.password' => $pass,
    'mail.default' => 'array',
]);
DB::purge('mysql');
DB::reconnect('mysql');
assertOperational();
echo 'laravel '.OPERATIONAL_DB."\n";

echo "BASELINE\n";
$before = snapshot();
$mode = (string) (json_decode((string) DB::table('system_settings')->where('key', 'absensi_kebijakan')->value('value'), true)['mode'] ?? '');
$mode = in_array($mode, ['kamis', 'harian'], true) ? $mode : 'lain';
$shifts = DB::table('absensi_sesi_settings')->orderBy('shift')->pluck('shift')->implode(', ');
echo 'pengguna/finger_id/template '.$before['template']['jumlah']."\n";
echo 'log_absensi '.$before['log_absensi']['jumlah'].' absensi_harian '.$before['absensi_harian']['jumlah']."\n";
echo "kebijakan {$mode} sesi {$shifts} libur ".$before['libur']['jumlah']."\n";
foreach (DB::table('mesin_iot')->select('tipe', DB::raw('COUNT(*) as jumlah'))->groupBy('tipe')->get() as $row) {
    $name = (string) ($row->tipe ?? '');
    if (! in_array($name, ['absensi', 'loker', 'pintu', 'mesin_cuci'], true)) {
        $name = 'tipe-lain';
    }
    echo "mesin {$name} ".(int) $row->jumlah."\n";
}

echo "CLEANUP\n";
runOne(DROP_PATH, DROP_NAME);
if (in_array(IDEM_NAME, DB::table('migrations')->pluck('migration')->all(), true)) {
    refuse('idempotensi ikut tercatat');
}
pass('idempotensi belum dijalankan');
$afterDrop = snapshot();
foreach ([
    'pengguna' => 'users',
    'template' => 'template',
    'log_absensi' => 'log_absensi',
    'absensi_harian' => 'absensi_harian',
    'mesin absensi' => 'mesin_absensi',
    'kebijakan' => 'kebijakan',
    'sesi' => 'sesi',
    'libur' => 'libur',
] as $label => $key) {
    compare($label, $before[$key], $afterDrop[$key]);
}
$lockerGone = ! Schema::hasTable('log_loker')
    && ! Schema::hasTable('loker_slot_admin_logs')
    && ! Schema::hasColumn('mesin_iot', 'locker_slots')
    && ! Schema::hasColumn('mesin_iot', 'locker_state')
    && ! Schema::hasColumn('mesin_iot', 'locker_state_note')
    && ! Schema::hasColumn('mesin_iot', 'allowed_teams')
    && (int) DB::table('mesin_iot')->where('tipe', 'loker')->count() === 0
    && (int) DB::table('role_permissions')->whereIn('menu_key', ['lockers', 'log_loker', 'dosen_loker'])->count() === 0
    && (int) DB::table('system_settings')->whereIn('key', ['machine_access', 'locker_fulfillment'])->count() === 0
    && (int) DB::table('announcements')->where(function ($q) {
        $q->where('channel', 'notifikasi_loker')->orWhere('judul', 'like', '[LOKER:%');
    })->count() === 0;
if ($lockerGone) {
    pass('objek locker hilang');
} else {
    fail('objek locker hilang');
}
$pintu = (int) DB::table('mesin_iot')->where('tipe', 'pintu')->count();
$cuci = (int) DB::table('mesin_iot')->where('tipe', 'mesin_cuci')->count();
echo "pintu {$pintu} mesin_cuci {$cuci}\n";
if ($pintu !== 1 || $cuci !== 1) {
    fail('pintu dan mesin_cuci');
} else {
    pass('pintu dan mesin_cuci tetap');
}
if ($failures > 0) {
    refuse('cleanup berubah di luar scope, idempotensi tidak dijalankan');
}

echo "UJI IDEMPOTENSI MYSQL\n";
$behavior = proc_open([
    PHP_BINARY,
    $root.'\\tests\\Support\\run_mysql_idempotency_behavior.php',
], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $behaviorPipes, $root);
if (! is_resource($behavior)) {
    refuse('uji perilaku tidak jalan');
}
fclose($behaviorPipes[0]);
$behaviorOut = stream_get_contents($behaviorPipes[1]);
$behaviorErr = stream_get_contents($behaviorPipes[2]);
fclose($behaviorPipes[1]);
fclose($behaviorPipes[2]);
$behaviorCode = proc_close($behavior);
echo $behaviorOut;
if (trim((string) $behaviorErr) !== '') {
    echo "uji stderr ada, tidak dicetak\n";
}
assertOperational();
if ($behaviorCode !== 0 || ! str_contains((string) $behaviorOut, 'HASIL gagal 0')) {
    refuse('uji MySQL gagal, migration idempotensi tidak dijalankan');
}
pass('uji perilaku database pengujian');

echo "IDEMPOTENSI\n";
runOne(IDEM_PATH, IDEM_NAME);
$afterIdem = snapshot();
foreach ([
    'pengguna setelah idempotensi' => 'users',
    'template setelah idempotensi' => 'template',
    'log setelah idempotensi' => 'log_absensi',
    'harian setelah idempotensi' => 'absensi_harian',
    'mesin setelah idempotensi' => 'mesin_absensi',
    'kebijakan setelah idempotensi' => 'kebijakan',
    'sesi setelah idempotensi' => 'sesi',
    'libur setelah idempotensi' => 'libur',
] as $label => $key) {
    compare($label, $before[$key], $afterIdem[$key]);
}
$nullEvents = (int) DB::table('log_absensi')->whereNull('event_id')->count();
$logCount = (int) DB::table('log_absensi')->count();
$idemOk = Schema::hasTable('tap_receipts')
    && Schema::hasColumn('log_absensi', 'event_id')
    && Schema::hasColumn('log_absensi', 'diterima_pada')
    && (int) DB::table('tap_receipts')->count() === 0
    && $nullEvents === $logCount;
if ($idemOk) {
    pass('tap_receipts ada dan event lama kosong');
} else {
    fail('tap_receipts ada dan event lama kosong');
}

echo "FUNGSI\n";
try {
    assertOperational();
    $queries = [
        'users' => DB::table('users')->count(),
        'finger' => DB::table('users')->whereNotNull('finger_id')->count(),
        'mesin absensi' => DB::table('mesin_iot')->where('tipe', 'absensi')->count(),
        'sesi' => DB::table('absensi_sesi_settings')->count(),
        'harian' => DB::table('absensi_harian')->count(),
        'log' => DB::table('log_absensi')->count(),
    ];
    $modeNow = \App\Support\AbsensiKebijakan::mode();
    echo 'query pengguna '.$queries['users'].' finger '.$queries['finger'].' mesin '.$queries['mesin absensi'].' sesi '.$queries['sesi'].' harian '.$queries['harian'].' log '.$queries['log']." mode {$modeNow}\n";
    pass('query skema operasional');
} catch (Throwable $e) {
    fail('query skema operasional');
}

$base = 'http://127.0.0.1/Simalas/public';
foreach ([
    '/masuk',
    '/admin/dashboard',
    '/admin/users',
    '/admin/mesin',
    '/admin/fingerprint',
    '/admin/absensi/pengaturan',
    '/admin/attendance',
    '/admin/reports',
    '/dosen/dashboard',
    '/dosen/absensi',
    '/dosen/mahasiswa',
    '/dosen/reports',
] as $path) {
    $ch = curl_init($base.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $expect = $path === '/masuk' ? 200 : 302;
    if ($status === $expect) {
        pass('http '.$status.' '.$path);
    } else {
        fail('http '.$status.' '.$path);
    }
}

$envNow = [filemtime($envPath), filesize($envPath)];
if ($envNow === $envStamp) {
    pass('env tidak berubah');
} else {
    fail('env tidak berubah');
}
assertOperational();
echo 'PHP '.PHP_VERSION."\n";
echo 'HASIL gagal '.$failures."\n";
exit($failures > 0 ? 1 : 0);
