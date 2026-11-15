<?php

/**
 * Lab Copilot 127.0.0.1:8099: SQLite baru berisi data sintetis + Gemini palsu 127.0.0.1:8097.
 * Tidak menyentuh MySQL operasional, cache, maupun log aplikasi utama.
 *
 *   php tests/Support/serve_copilot_lab.php          migrasi + seed + jalankan server
 *   php tests/Support/serve_copilot_lab.php --check  (internal) cek koneksi
 *   php tests/Support/serve_copilot_lab.php --seed   (internal) isi data sintetis
 */

$root = dirname(__DIR__, 2);
$db = sys_get_temp_dir().DIRECTORY_SEPARATOR.'simalas_copilot_lab.sqlite';
$mode = $argv[1] ?? '';

if ($mode === '--check' || $mode === '--seed') {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $conn = Illuminate\Support\Facades\DB::connection();
    if ($conn->getDriverName() !== 'sqlite' || realpath((string) $conn->getDatabaseName()) !== realpath($db)) {
        fwrite(STDERR, "Ditolak: koneksi bukan SQLite lab\n");
        exit(2);
    }
    if ($mode === '--seed') {
        $ids = Tests\Support\CopilotSyntheticData::seed(Carbon\Carbon::today('Asia/Jakarta'));
        echo 'Seed sintetis: '.count($ids['students'])." mahasiswa contoh\n";
    } else {
        echo "OK sqlite lab\n";
    }
    exit(0);
}

if (file_exists($db)) {
    unlink($db);
}
touch($db);

$childEnv = getenv();
foreach ([
    'APP_ENV' => 'local',
    'APP_URL' => 'http://127.0.0.1:8099',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $db,
    'DB_URL' => '',
    'DB_FOREIGN_KEYS' => 'true',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'file',
    'SESSION_DOMAIN' => '',
    'SESSION_SECURE_COOKIE' => 'false',
    'CACHE_STORE' => 'array',
    'BROADCAST_CONNECTION' => 'null',
    'LOG_CHANNEL' => 'stderr',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
    'GEMINI_API_KEY' => 'kunci-palsu-lab',
    'GEMINI_BASE_URL' => 'http://127.0.0.1:8097/v1beta',
    'GEMINI_MODEL' => 'model-uji',
    'GEMINI_FALLBACK_MODELS' => 'model-uji',
    'GEMINI_READ_TIMEOUT' => '6',
] as $key => $value) {
    $childEnv[$key] = $value;
}

$run = function (array $cmd) use ($root, $childEnv): int {
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $root, $childEnv);
    fclose($pipes[0]);

    return proc_close($proc);
};

if ($run([PHP_BINARY, __FILE__, '--check']) !== 0) {
    exit(2);
}
if ($run([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction']) !== 0) {
    fwrite(STDERR, "Migrasi SQLite lab gagal\n");
    exit(1);
}
if ($run([PHP_BINARY, __FILE__, '--seed']) !== 0) {
    exit(1);
}

$cmd = [PHP_BINARY, '-S', '127.0.0.1:8099', '-t', $root.'\\public', $root.'\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\resources\\server.php'];
echo 'LAB Copilot 127.0.0.1:8099 (SQLite sintetis) PHP '.PHP_VERSION."\n";
$proc = proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root.'\\public', $childEnv);
exit(is_resource($proc) ? proc_close($proc) : 1);
