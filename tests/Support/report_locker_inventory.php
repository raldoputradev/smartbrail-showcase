<?php

/**
 * Inventaris schema locker pada MySQL operasional.
 * Hanya jumlah dan nama objek. Tidak mencetak email, template, atau kredensial.
 * Tidak mengubah data.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

echo "database {$database} driver {$driver}\n";

foreach (['log_loker', 'loker_slot_admin_logs', 'mesin_iot', 'users', 'log_absensi', 'absensi_harian', 'role_permissions', 'system_settings', 'announcements'] as $table) {
    echo 'tabel '. $table.' '. (Schema::hasTable($table) ? 'ada' : 'tidak ada')."\n";
}

if (Schema::hasTable('mesin_iot')) {
    foreach (['locker_slots', 'locker_state', 'locker_state_note', 'allowed_teams', 'tipe'] as $column) {
        echo 'kolom mesin_iot.'.$column.' '.(Schema::hasColumn('mesin_iot', $column) ? 'ada' : 'tidak ada')."\n";
    }
    $types = DB::table('mesin_iot')
        ->select('tipe', DB::raw('COUNT(*) as jumlah'))
        ->groupBy('tipe')
        ->get();
    foreach ($types as $row) {
        echo 'mesin tipe '.($row->tipe ?? '(kosong)').' jumlah '.(int) $row->jumlah."\n";
    }
}

if (Schema::hasTable('log_loker')) {
    echo 'baris log_loker '.DB::table('log_loker')->count()."\n";
}
if (Schema::hasTable('loker_slot_admin_logs')) {
    echo 'baris loker_slot_admin_logs '.DB::table('loker_slot_admin_logs')->count()."\n";
}
if (Schema::hasTable('users')) {
    echo 'baris users '.DB::table('users')->count()."\n";
    echo 'users punya finger_id '.DB::table('users')->whereNotNull('finger_id')->count()."\n";
}
if (Schema::hasTable('log_absensi')) {
    echo 'baris log_absensi '.DB::table('log_absensi')->count()."\n";
}
if (Schema::hasTable('absensi_harian')) {
    echo 'baris absensi_harian '.DB::table('absensi_harian')->count()."\n";
}
if (Schema::hasTable('role_permissions')) {
    $perms = DB::table('role_permissions')
        ->whereIn('menu_key', ['lockers', 'log_loker', 'dosen_loker'])
        ->select('menu_key', DB::raw('COUNT(*) as jumlah'))
        ->groupBy('menu_key')
        ->get();
    if ($perms->isEmpty()) {
        echo "permission locker 0\n";
    }
    foreach ($perms as $row) {
        echo 'permission '.$row->menu_key.' jumlah '.(int) $row->jumlah."\n";
    }
}
if (Schema::hasTable('system_settings')) {
    $keys = DB::table('system_settings')
        ->whereIn('key', ['machine_access', 'locker_fulfillment', 'absensi_kebijakan'])
        ->pluck('key');
    echo 'setting kunci '.($keys->isEmpty() ? '(tidak ada yang cocok)' : $keys->implode(', '))."\n";
}
if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'channel')) {
    echo 'pengumuman channel notifikasi_loker '.DB::table('announcements')->where('channel', 'notifikasi_loker')->count()."\n";
    echo 'pengumuman judul awalan LOKER '.DB::table('announcements')->where('judul', 'like', '[LOKER:%')->count()."\n";
}

$fks = DB::select(
    'select TABLE_NAME as tbl, CONSTRAINT_NAME as cname, REFERENCED_TABLE_NAME as ref
     from information_schema.KEY_COLUMN_USAGE
     where TABLE_SCHEMA = ? and TABLE_NAME in (?, ?) and REFERENCED_TABLE_NAME is not null',
    [$database, 'log_loker', 'loker_slot_admin_logs']
);
if ($fks === []) {
    echo "foreign key locker tidak ditemukan\n";
}
foreach ($fks as $fk) {
    echo 'fk '.$fk->tbl.' '.$fk->cname.' -> '.$fk->ref."\n";
}
