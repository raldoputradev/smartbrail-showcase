<?php

/**
 * Cadangan MySQL operasional sebelum drop locker.
 * File disimpan di luar htdocs. Tidak mencetak kredensial.
 */

use Illuminate\Contracts\Console\Kernel;

require_once __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = (string) config('database.default');
$driver = (string) config("database.connections.{$name}.driver");
$database = (string) config("database.connections.{$name}.database");
if ($driver !== 'mysql' || $database !== 'simalas') {
    fwrite(STDERR, "Ditolak: koneksi bukan mysql simalas.\n");
    exit(2);
}

$dir = 'C:\\project-archives\\Simalas-db-backup';
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}

$out = $dir.'\\simalas-before-locker-drop-20261008.sql';
$dump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
if (! is_file($dump)) {
    fwrite(STDERR, "mysqldump tidak ditemukan.\n");
    exit(2);
}

$host = (string) config("database.connections.{$name}.host");
$port = (string) config("database.connections.{$name}.port");
$user = (string) config("database.connections.{$name}.username");
$pass = (string) config("database.connections.{$name}.password");

$cnf = $dir.'\\mysqldump-once.cnf';
file_put_contents($cnf, "[client]\nuser=".addslashes($user)."\npassword=".addslashes($pass)."\nhost=".addslashes($host)."\nport=".addslashes($port)."\n");

$cmd = [
    $dump,
    '--defaults-extra-file='.$cnf,
    '--single-transaction',
    '--routines',
    '--triggers',
    '--result-file='.$out,
    $database,
];

$spec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$proc = proc_open($cmd, $spec, $pipes);
if (! is_resource($proc)) {
    fwrite(STDERR, "Gagal menjalankan mysqldump.\n");
    exit(1);
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($proc);
if (is_file($cnf)) {
    unlink($cnf);
}

if ($code !== 0 || ! is_file($out) || filesize($out) < 1000) {
    $note = preg_replace('/password=\S+/i', 'password=[disembunyikan]', (string) $stderr);
    fwrite(STDERR, "Cadangan gagal, kode {$code}. ".trim((string) $note)."\n");
    exit(1);
}

echo 'cadangan tersimpan byte '.filesize($out)."\n";
if ($stdout !== '') {
    echo "mysqldump menulis keluaran tambahan\n";
}
if ($stderr !== '' && ! str_contains($stderr, 'Warning')) {
    echo "mysqldump selesai dengan catatan\n";
}
