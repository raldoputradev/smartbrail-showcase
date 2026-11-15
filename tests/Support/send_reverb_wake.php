<?php

/**
 * Kirim wake ke Reverb lokal untuk uji browser. Tidak menulis tap.
 * Pemakaian: send_reverb_wake.php burst|dup|fingerprint
 */

use App\Events\AbsensiSnapshotWake;
use App\Support\AbsensiSnapshotSignals;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

const TEST_DB = 'simalas_cleanup_test';

$mode = $argv[1] ?? '';
foreach (['DB_DATABASE' => TEST_DB, 'DB_HOST' => '127.0.0.1', 'DB_URL' => '', 'BROADCAST_CONNECTION' => 'reverb', 'MAIL_MAILER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
$root = dirname(__DIR__, 2);
require_once $root.'\\vendor\\autoload.php';
$app = require $root.'\\bootstrap\\app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.mysql.database' => TEST_DB, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
if ((string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '') !== TEST_DB) {
    fwrite(STDERR, "Ditolak: bukan database uji\n");
    exit(2);
}

if ($mode === 'burst') {
    for ($i = 0; $i < 5; $i++) {
        DB::transaction(fn () => AbsensiSnapshotSignals::committed(['rekap', 'log'], 'staff'));
        usleep(50000);
    }
    echo "burst 5 terkirim\n";
} elseif ($mode === 'dup') {
    $wake = new AbsensiSnapshotWake('dup-'.bin2hex(random_bytes(6)), ['rekap', 'log'], now('Asia/Jakarta')->toIso8601String(), null, 'staff');
    broadcast($wake);
    sleep(1);
    broadcast(clone $wake);
    echo "dup 2 kirim delivery_id sama\n";
} elseif ($mode === 'fingerprint') {
    DB::transaction(fn () => AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran'));
    echo "fingerprint 1 terkirim\n";
} else {
    fwrite(STDERR, "mode burst|dup|fingerprint\n");
    exit(2);
}
