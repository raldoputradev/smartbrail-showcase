<?php

/**
 * Uji migration drop locker pada sqlite terpisah.
 * Tidak menyentuh MySQL operasional simalas.
 */

use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "PASS  {$label}\n";

        return;
    }

    $failures++;
    echo "FAIL  {$label}\n";
}

function bootSqlite(string $database): Illuminate\Foundation\Application
{
    require_once __DIR__.'/../../vendor/autoload.php';

    foreach ([
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $database,
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'MAIL_MAILER' => 'array',
        'BCRYPT_ROUNDS' => '4',
    ] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $name = (string) config('database.default');
    $driver = (string) config("database.connections.{$name}.driver");
    $db = (string) config("database.connections.{$name}.database");
    if ($driver !== 'sqlite' || ! str_contains($db, 'locker-drop-test.sqlite')) {
        fwrite(STDERR, "Ditolak: koneksi bukan sqlite uji.\n");
        exit(2);
    }

    return $app;
}

$sqlite = 'C:\\project-archives\\Simalas-db-backup\\locker-drop-test.sqlite';
if (! is_dir(dirname($sqlite))) {
    mkdir(dirname($sqlite), 0700, true);
}
if (is_file($sqlite)) {
    unlink($sqlite);
}
touch($sqlite);

$app = bootSqlite($sqlite);

$files = glob(__DIR__.'/../../database/migrations/*.php');
sort($files, SORT_STRING);
$drop = null;
foreach ($files as $file) {
    if (str_contains($file, '2026_10_08_160000_drop_locker_schema_from_attendance.php')) {
        $drop = $file;
        continue;
    }
    Artisan::call('migrate', [
        '--force' => true,
        '--path' => 'database/migrations/'.basename($file),
    ]);
}

check(Schema::hasTable('log_loker'), 'sebelum drop, tabel log_loker ada');
check(Schema::hasColumn('mesin_iot', 'locker_slots'), 'sebelum drop, kolom locker_slots ada');
check(Schema::hasTable('users'), 'tabel users ada');
check(Schema::hasColumn('users', 'finger_id'), 'kolom finger_id ada');

$user = User::query()->create([
    'nama_lengkap' => 'Mahasiswa Uji Drop',
    'nitk_nidn' => 'UJI-DROP-001',
    'email' => 'uji-drop@example.test',
    'password' => Hash::make('bukan-rahasia-uji'),
    'role' => 'mahasiswa',
    'is_active' => true,
    'finger_id' => 41,
]);
$user->finger_template = str_repeat('A', 512);
$user->save();

$absensi = MesinIot::query()->create([
    'id_mesin' => 'ABSEN-UJI-DROP',
    'nama_mesin' => 'Mesin Absensi Uji',
    'tipe' => 'absensi',
    'api_key' => Hash::make('kunci-uji'),
    'is_active' => true,
]);
DB::table('mesin_iot')->insert([
    'id_mesin' => 'LOKER-UJI-DROP',
    'nama_mesin' => 'Mesin Loker Uji',
    'tipe' => 'loker',
    'api_key' => Hash::make('kunci-uji-loker'),
    'is_active' => true,
    'created_at' => now(),
    'updated_at' => now(),
]);
LogAbsensi::query()->create([
    'user_id' => $user->id,
    'id_mesin' => $absensi->id_mesin,
    'waktu_tap' => now(),
]);
DB::table('log_loker')->insert([
    'user_id' => $user->id,
    'id_mesin' => 'LOKER-UJI-DROP',
    'aksi' => 'buka',
    'berhasil' => 1,
    'waktu_akses' => now(),
]);
DB::table('role_permissions')->insert([
    'role' => 'laboran',
    'menu_key' => 'lockers',
    'can_view' => 1,
    'can_create' => 0,
    'can_update' => 0,
    'can_delete' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);
DB::table('system_settings')->updateOrInsert(
    ['key' => 'machine_access'],
    ['value' => json_encode(['active' => true]), 'created_at' => now(), 'updated_at' => now()]
);
DB::table('system_settings')->updateOrInsert(
    ['key' => 'absensi_kebijakan'],
    ['value' => json_encode(['uji' => true]), 'created_at' => now(), 'updated_at' => now()]
);
if (Schema::hasColumn('announcements', 'channel')) {
    DB::table('announcements')->insert([
        'judul' => '[LOKER:UJI] pemberitahuan',
        'isi' => 'uji',
        'channel' => 'notifikasi_loker',
        'target' => 'mahasiswa',
        'prioritas' => 'normal',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$fingerBefore = User::query()->whereKey($user->id)->value('finger_id');
$tapBefore = LogAbsensi::query()->count();
$absenMachinesBefore = MesinIot::query()->where('tipe', 'absensi')->count();

Artisan::call('migrate', [
    '--force' => true,
    '--path' => 'database/migrations/'.basename($drop),
]);

check(! Schema::hasTable('log_loker'), 'setelah drop, tabel log_loker hilang');
check(! Schema::hasTable('loker_slot_admin_logs'), 'setelah drop, tabel loker_slot_admin_logs hilang');
check(! Schema::hasColumn('mesin_iot', 'locker_slots'), 'kolom locker_slots hilang');
check(! Schema::hasColumn('mesin_iot', 'locker_state'), 'kolom locker_state hilang');
check(! Schema::hasColumn('mesin_iot', 'allowed_teams'), 'kolom allowed_teams hilang');
check(MesinIot::query()->where('tipe', 'loker')->count() === 0, 'baris mesin loker terhapus');
check(MesinIot::query()->where('tipe', 'absensi')->count() === $absenMachinesBefore, 'mesin absensi utuh');
check((int) User::query()->whereKey($user->id)->value('finger_id') === (int) $fingerBefore, 'finger_id utuh');
check(User::query()->whereKey($user->id)->value('finger_template') !== null, 'template sidik jari utuh');
check(LogAbsensi::query()->count() === $tapBefore, 'log absensi utuh');
check(DB::table('role_permissions')->where('menu_key', 'lockers')->count() === 0, 'permission lockers terhapus');
check(DB::table('system_settings')->where('key', 'machine_access')->count() === 0, 'setting machine_access terhapus');
check(DB::table('system_settings')->where('key', 'absensi_kebijakan')->count() === 1, 'setting absensi tetap');
check(DB::table('announcements')->where('channel', 'notifikasi_loker')->count() === 0, 'pengumuman loker terhapus');
check(Schema::hasColumn('announcements', 'channel'), 'kolom announcements.channel tetap');

DB::disconnect('sqlite');
gc_collect_cycles();
if (is_file($sqlite)) {
    unlink($sqlite);
}
touch($sqlite);
DB::purge('sqlite');
DB::reconnect('sqlite');

Artisan::call('migrate', ['--force' => true]);
check(! Schema::hasTable('log_loker'), 'migrate berurutan berakhir tanpa tabel log_loker');
check(! Schema::hasColumn('mesin_iot', 'locker_slots'), 'migrate berurutan berakhir tanpa kolom locker');
check(Schema::hasTable('users'), 'migrate berurutan tetap membuat users');
check(Schema::hasTable('log_absensi'), 'migrate berurutan tetap membuat log_absensi');
check(Schema::hasColumn('users', 'finger_id'), 'migrate berurutan tetap membuat finger_id');

echo $failures === 0 ? "HASIL  semua pemeriksaan lulus\n" : "HASIL  {$failures} gagal\n";
exit($failures === 0 ? 0 : 1);
