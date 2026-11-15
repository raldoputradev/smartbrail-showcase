<?php

/**
 * Server samping 127.0.0.1:8099 yang hanya memakai simalas_cleanup_test.
 */

const TEST_DB = 'simalas_cleanup_test';

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
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
            $value = stripcslashes(substr($value, 1, -1));
        }
        $out[trim($key)] = $value;
    }

    return $out;
}

$root = dirname(__DIR__, 2);
$env = readEnvFile($root.'\\.env');
if (! in_array($env['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true) || ($env['DB_URL'] ?? '') !== '') {
    fwrite(STDERR, "Ditolak: host database bukan lokal\n");
    exit(2);
}

$pdo = new PDO('mysql:host=127.0.0.1;port='.($env['DB_PORT'] ?? '3306').';dbname='.TEST_DB, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '');
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== TEST_DB) {
    fwrite(STDERR, "Ditolak: target bukan database uji\n");
    exit(2);
}
$pdo = null;

$childEnv = getenv();
foreach ([
    'APP_ENV' => 'local',
    'APP_URL' => 'http://127.0.0.1:8099',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_DATABASE' => TEST_DB,
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'SESSION_DRIVER' => 'file',
    'SESSION_DOMAIN' => '',
    'SESSION_SECURE_COOKIE' => 'false',
    'BROADCAST_CONNECTION' => 'reverb',
] as $key => $value) {
    $childEnv[$key] = $value;
}

$cmd = [PHP_BINARY, '-S', '127.0.0.1:8099', '-t', $root.'\\public', $root.'\\vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\resources\\server.php'];
echo 'SERVER uji 127.0.0.1:8099 database '.TEST_DB.' PHP '.PHP_VERSION."\n";
$proc = proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root.'\\public', $childEnv);
exit(is_resource($proc) ? proc_close($proc) : 1);
