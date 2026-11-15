<?php

use Illuminate\Contracts\Console\Kernel;

$stage = 'bootstrap';
$directory = null;
$defaultsFile = null;
$backupFile = null;
$restoreDatabase = null;

function guard(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function runProcess(array $arguments, ?string $stdinFile, string $stage): void
{
    $descriptors = [
        0 => $stdinFile ? ['file', $stdinFile, 'r'] : ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($arguments, $descriptors, $pipes);
    guard(is_resource($process), 'Gagal membuka proses '.$stage);
    if ($stdinFile === null) {
        fclose($pipes[0]);
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    guard($status === 0, 'Proses gagal: '.$stage);
}

function pdoFor(array $config, ?string $database): PDO
{
    $dsn = 'mysql:host='.$config['host'].';port='.($config['port'] ?? 3306).';charset=utf8mb4';
    if ($database !== null) {
        $dsn .= ';dbname='.$database;
    }

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function quoteIdentifier(string $value): string
{
    return '`'.str_replace('`', '``', $value).'`';
}

function baseTables(PDO $pdo, string $database): array
{
    $statement = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
    $statement->execute([$database]);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function createDefinition(PDO $pdo, string $database, string $table): string
{
    $row = $pdo->query('SHOW CREATE TABLE '.quoteIdentifier($database).'.'.quoteIdentifier($table))->fetch();

    return (string) $row['Create Table'];
}

function rowCount(PDO $pdo, string $database, string $table): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM '.quoteIdentifier($database).'.'.quoteIdentifier($table))->fetchColumn();
}

try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $config = config('database.connections.mysql');
    guard(($config['driver'] ?? null) === 'mysql', 'Driver bukan mysql');
    guard(in_array($config['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true), 'Host bukan lokal');
    $database = (string) $config['database'];
    guard(! in_array($database, ['simalas_activation_test', 'simalas_cleanup_test'], true), 'Database sumber ditolak');

    $pdo = pdoFor($config, $database);
    $tables = baseTables($pdo, $database);
    guard($tables !== [], 'Tabel aplikasi tidak ditemukan');
    $engines = $pdo->prepare('SELECT DISTINCT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=?');
    $engines->execute([$database]);
    guard($engines->fetchAll(PDO::FETCH_COLUMN) === ['InnoDB'], 'Tidak semua tabel InnoDB');

    $stamp = date('Ymd-His');
    $directory = 'C:\\xampp\\mysql-backups\\operational-'.$stamp;
    guard(! is_dir($directory), 'Direktori backup sudah ada');
    guard(mkdir($directory, 0700, true), 'Gagal membuat direktori backup');
    $backupFile = $directory.'\\backup.sql';
    $defaultsFile = $directory.'\\client.cnf';
    $restoreDatabase = 'simalas_restore_'.date('Ymd_His');

    $cnfValue = fn (mixed $value): string => addcslashes((string) $value, "\\\"\r\n");
    $cnf = "[client]\n"
        .'user="'.$cnfValue($config['username'])."\"\n"
        .'password="'.$cnfValue($config['password'])."\"\n"
        .'host="'.$cnfValue($config['host'])."\"\n"
        .'port="'.$cnfValue($config['port'] ?? 3306)."\"\n";
    guard(file_put_contents($defaultsFile, $cnf, LOCK_EX) !== false, 'Gagal menyiapkan autentikasi backup');
    chmod($defaultsFile, 0600);

    $stage = 'mysqldump consistent snapshot';
    runProcess([
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        '--defaults-extra-file='.$defaultsFile,
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--routines=0',
        '--events=0',
        '--triggers=0',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        '--result-file='.$backupFile,
        $database,
    ], null, $stage);
    guard(is_file($backupFile) && filesize($backupFile) > 0, 'File backup kosong/tidak ada');
    $size = filesize($backupFile);
    $hash = hash_file('sha256', $backupFile);
    echo "BACKUP_PATH={$directory}\nBACKUP_BYTES={$size}\nBACKUP_SHA256={$hash}\n";

    $stage = 'restore unique database';
    $restorePdo = pdoFor($config, null);
    $exists = $restorePdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=?');
    $exists->execute([$restoreDatabase]);
    guard($exists->fetchColumn() === false, 'Database restore sudah ada');
    $restorePdo->exec('CREATE DATABASE '.quoteIdentifier($restoreDatabase).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    runProcess([
        'C:\\xampp\\mysql\\bin\\mysql.exe',
        '--defaults-extra-file='.$defaultsFile,
        '--database='.$restoreDatabase,
        '--default-character-set=utf8mb4',
    ], $backupFile, $stage);

    $stage = 'restore verification';
    $restored = pdoFor($config, $restoreDatabase);
    $restoredTables = baseTables($restored, $restoreDatabase);
    guard($restoredTables === $tables, 'Daftar tabel restore berbeda');
    foreach ($tables as $table) {
        guard(createDefinition($pdo, $database, $table) === createDefinition($restored, $restoreDatabase, $table), 'Definisi tabel berbeda: '.$table);
        guard(rowCount($pdo, $database, $table) === rowCount($restored, $restoreDatabase, $table), 'Jumlah baris berbeda: '.$table);
    }
    echo 'RESTORE_DATABASE='.$restoreDatabase."\n";
    echo 'RESTORE_TABLE_DEFINITIONS_MATCH='.count($tables)."\n";
    echo 'RESTORE_ROW_COUNTS_MATCH='.count($tables)."\n";
    echo "BACKUP_VERIFIED=true\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'BACKUP_FAILED_STAGE='.$stage.'; detail disamarkan'."\n");
    if ($restoreDatabase !== null) {
        fwrite(STDERR, 'RESTORE_DATABASE_RETAINED='.$restoreDatabase."\n");
    }
    exit(2);
} finally {
    if ($defaultsFile !== null && is_file($defaultsFile)) {
        unlink($defaultsFile);
    }
}
