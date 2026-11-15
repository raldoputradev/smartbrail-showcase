<?php

/**
 * Tap HTTP nyata pada simalas_cleanup_test dengan Reverb mati atau hidup.
 * Pemakaian: run_reverb_failure_test.php mati|hidup
 * Fixture sintetis dibuat dan dihapus di database uji; tidak menyentuh simalas.
 */

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiTidakLengkap;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\TapReceipt;
use App\Models\User;
use App\Support\AbsensiSnapshotSignals;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

const TEST_DB = 'simalas_cleanup_test';
const FORBIDDEN_DB = 'simalas';
const MESIN_ID = 'UJI-SOKET-MESIN';
const MHS_NITK = ['UJI-SOKET-MHS1', 'UJI-SOKET-MHS2'];
const SYNTHETIC = ['UJI-CLEANUP-LAB', 'UJI-CLEANUP-DOS', 'UJI-SNAP-MHS', 'UJI-SNAP-OTHER', 'UJI-SOKET-MHS1', 'UJI-SOKET-MHS2'];

$mode = $argv[1] ?? '';
$slow = $mode === 'lambat';
if ($slow) {
    $mode = 'mati';
}
if (! in_array($mode, ['mati', 'hidup'], true)) {
    fwrite(STDERR, "mode mati|hidup|lambat\n");
    exit(2);
}

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    if (! $ok) {
        $failures++;
    }
    echo ($ok ? 'PASS  ' : 'FAIL  ').$label."\n";
}
function refuse(string $reason): void
{
    fwrite(STDERR, "Ditolak: {$reason}\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$text = (string) file_get_contents($root.'\\.env');
$get = function (string $key) use ($text): string {
    if (! preg_match('/^'.$key.'=(.*)$/m', $text, $m)) {
        return '';
    }
    $value = trim($m[1]);
    if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
        $value = substr($value, 1, -1);
    }

    return $value;
};
if (! in_array($get('DB_HOST'), ['127.0.0.1', 'localhost'], true) || $get('DB_URL') !== '' || $get('DB_DATABASE') !== FORBIDDEN_DB) {
    refuse('env operasional tidak sesuai target lokal');
}

$probe = @fsockopen('127.0.0.1', (int) ($get('REVERB_PORT') ?: 8080), $errno, $errstr, 0.5);
$open = is_resource($probe);
if ($open) {
    fclose($probe);
}
if ($mode === 'mati' && $open && ! $slow) {
    refuse('Reverb masih hidup; matikan dulu');
}
if ($mode === 'hidup' && ! $open) {
    refuse('Reverb belum hidup');
}
echo 'REVERB port '.($open ? 'terbuka' : 'tertutup')."\n";

foreach ([
    'APP_ENV' => 'local',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_DATABASE' => TEST_DB,
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'reverb',
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
    'database.connections.mysql.database' => TEST_DB,
    'broadcasting.default' => 'reverb',
    'mail.default' => 'array',
]);
if ($slow) {
    $hang = @fsockopen('127.0.0.1', 8097, $errno, $errstr, 1);
    if (! $hang) {
        fwrite(STDERR, "ABORT: soket macet uji di 127.0.0.1:8097 belum berjalan\n");
        exit(2);
    }
    fclose($hang);
    config([
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 8097,
    ]);
    Illuminate\Support\Facades\Broadcast::forgetDrivers();
    echo "HOST soket diarahkan ke soket macet 127.0.0.1:8097\n";
}
DB::purge('mysql');
if ((string) (DB::select('SELECT DATABASE() AS db')[0]->db ?? '') !== TEST_DB) {
    refuse('boot bukan database uji');
}
Mail::fake();
echo 'TARGET '.TEST_DB.' PHP '.PHP_VERSION.' cache '.config('cache.default').' timeout '.json_encode(config('broadcasting.connections.reverb.client_options'))."\n";

$digest = function (): string {
    $rows = DB::table('users')->whereNotIn('nitk_nidn', SYNTHETIC)->orderBy('id')->get(['id', 'finger_id', 'finger_template']);
    $ctx = hash_init('sha256');
    foreach ($rows as $row) {
        hash_update($ctx, $row->id.'|'.($row->finger_id ?? '').'|'.(($row->finger_template ?? '') === '' ? '' : hash('sha256', (string) $row->finger_template)));
    }

    return $rows->count().' '.hash_final($ctx);
};
$counts = fn () => [
    'log' => (int) DB::table('log_absensi')->count(),
    'harian' => (int) DB::table('absensi_harian')->count(),
    'receipt' => (int) DB::table('tap_receipts')->count(),
];
$logFile = storage_path('logs/laravel.log');
$logCount = function (string $needle) use ($logFile): int {
    return is_file($logFile) ? substr_count((string) file_get_contents($logFile), $needle) : 0;
};

$beforeDigest = $digest();
$beforeCounts = $counts();
$warnBefore = $logCount('Soket Reverb tidak terjangkau');
$okBefore = $logCount('Soket Reverb tersambung kembali');
$sigBefore = $logCount('auth_signature');

$used = DB::table('users')->whereNotNull('finger_id')->pluck('finger_id')->map(fn ($v) => (int) $v)->all();
$fingers = [];
for ($f = 150; $f >= 100 && count($fingers) < 2; $f--) {
    if (! in_array($f, $used, true)) {
        $fingers[] = $f;
    }
}
if (count($fingers) < 2 || DB::table('users')->whereIn('nitk_nidn', MHS_NITK)->exists() || MesinIot::query()->where('id_mesin', MESIN_ID)->exists()) {
    refuse('fixture sintetis bentrok atau sisa run sebelumnya');
}

$apiKey = bin2hex(random_bytes(16));
$userIds = [];

try {
    MesinIot::query()->create([
        'id_mesin' => MESIN_ID,
        'nama_mesin' => 'Mesin uji soket',
        'tipe' => 'absensi',
        'api_key' => Hash::make($apiKey),
        'is_active' => true,
    ]);
    foreach (MHS_NITK as $i => $nitk) {
        $userIds[] = User::query()->create([
            'nama_lengkap' => 'Akun Uji Soket '.($i + 1),
            'nitk_nidn' => $nitk,
            'email' => strtolower($nitk).'@example.test',
            'password' => Hash::make(bin2hex(random_bytes(12))),
            'role' => 'mahasiswa',
            'is_active' => true,
            'approval_status' => 'approved',
            'shift' => 'pagi',
            'finger_id' => $fingers[$i],
            'finger_template' => base64_encode(str_repeat(chr(65 + $i), App\Support\FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH)),
        ])->id;
    }

    if ($mode === 'mati') {
        Cache::forget(AbsensiSnapshotSignals::BREAKER_KEY);
        Cache::forget(AbsensiSnapshotSignals::LOG_KEY);
        Cache::forget(AbsensiSnapshotSignals::SINCE_KEY);
    } else {
        Cache::forget(AbsensiSnapshotSignals::BREAKER_KEY);
    }

    $tap = function (array $body) use ($apiKey): array {
        $kernel = app(Illuminate\Contracts\Http\Kernel::class);
        $request = Illuminate\Http\Request::create('/api/mesin/tap', 'POST', [], [], [], [
            'HTTP_X_API_KEY' => $apiKey,
            'HTTP_X_ID_MESIN' => MESIN_ID,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body));
        $t0 = microtime(true);
        $response = $kernel->handle($request);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $kernel->terminate($request, $response);

        return ['status' => $response->getStatusCode(), 'json' => json_decode((string) $response->getContent(), true) ?: [], 'ms' => $ms];
    };

    $eventA = (string) Str::uuid();
    $a = $tap(['finger_id' => $fingers[0], 'event_id' => $eventA]);
    echo "TAP A event_id status {$a['status']} kode ".($a['json']['code'] ?? '-')." {$a['ms']} ms\n";
    check($a['status'] === 200 && ($a['json']['success'] ?? false) === true && ($a['json']['terminal'] ?? false) === true, 'tap A sukses sesuai keputusan, bukan 500');
    $logA = (int) LogAbsensi::query()->where('user_id', $userIds[0])->count();
    $receiptA = TapReceipt::query()->where('event_id', $eventA)->first();
    check($logA === 1 && $receiptA !== null && (bool) $receiptA->success && (int) $receiptA->http_status === 200, 'tap A: 1 log dan receipt sukses');

    $breaker = Cache::has(AbsensiSnapshotSignals::BREAKER_KEY);
    if ($mode === 'mati') {
        check($breaker, 'jeda soket aktif setelah gagal');
        check($a['ms'] < ($slow ? 3500 : 2500), 'tap A tidak menunggu lebih dari batas timeout');
    } else {
        check(! $breaker, 'broadcast terkirim tanpa jeda');
    }

    $replay = $tap(['finger_id' => $fingers[0], 'event_id' => $eventA]);
    echo "REPLAY A status {$replay['status']} idempotent ".json_encode($replay['json']['idempotent'] ?? null)." {$replay['ms']} ms\n";
    check($replay['status'] === 200 && ($replay['json']['idempotent'] ?? false) === true
        && ($replay['json']['data']['log_id'] ?? null) === ($a['json']['data']['log_id'] ?? 'x')
        && (int) LogAbsensi::query()->where('user_id', $userIds[0])->count() === 1
        && (int) TapReceipt::query()->where('event_id', $eventA)->count() === 1, 'replay A idempoten tanpa log baru');

    $b = $tap(['finger_id' => $fingers[1]]);
    echo "TAP B firmware lama status {$b['status']} kode ".($b['json']['code'] ?? '-')." {$b['ms']} ms\n";
    check($b['status'] === 200 && ($b['json']['success'] ?? false) === true, 'tap B firmware lama sukses');
    if ($mode === 'mati') {
        check($b['ms'] < 1000, 'tap B melewati soket selama jeda');
    }

    $c = $tap(['finger_id' => 999999, 'event_id' => (string) Str::uuid()]);
    check($c['status'] === 404 && ($c['json']['code'] ?? '') === 'FINGER_NOT_FOUND', 'penolakan bisnis tetap 404');

    $warnAfter = $logCount('Soket Reverb tidak terjangkau');
    $okAfter = $logCount('Soket Reverb tersambung kembali');
    echo 'LOG peringatan +'.($warnAfter - $warnBefore).' pulih +'.($okAfter - $okBefore)."\n";
    if ($mode === 'mati') {
        check($warnAfter - $warnBefore === 1, 'peringatan soket dicatat sekali');
    } else {
        check($warnAfter - $warnBefore === 0, 'tidak ada peringatan saat soket hidup');
        check($okAfter - $okBefore <= 1, 'catatan pulih paling banyak sekali');
    }
    check($logCount('auth_signature') === $sigBefore, 'log tidak memuat tanda tangan Reverb');
    $hold = min(120, max(0, (int) ($argv[2] ?? 0)));
    if ($hold > 0) {
        echo "TAHAN {$hold} detik sebelum pembersihan\n";
        sleep($hold);
    }
} finally {
    if ($userIds !== []) {
        foreach ([LogAbsensi::class, AbsensiHarian::class, AbsensiTidakLengkap::class, AbsensiAlfa::class] as $model) {
            (new $model)->newQuery()->whereIn('user_id', $userIds)->delete();
        }
        User::query()->whereIn('id', $userIds)->delete();
    }
    TapReceipt::query()->where('id_mesin', MESIN_ID)->delete();
    MesinIot::query()->where('id_mesin', MESIN_ID)->delete();
    if ($mode === 'hidup') {
        Cache::forget(AbsensiSnapshotSignals::BREAKER_KEY);
        Cache::forget(AbsensiSnapshotSignals::LOG_KEY);
        Cache::forget(AbsensiSnapshotSignals::SINCE_KEY);
    }
}

$afterDigest = $digest();
$afterCounts = $counts();
check($afterDigest === $beforeDigest, 'pengguna asli finger/template tetap');
check($afterCounts === $beforeCounts, 'log/harian/receipt kembali '.json_encode($afterCounts));
echo 'ASLI '.$afterDigest."\n";
echo 'HASIL gagal '.$failures."\n";
exit($failures > 0 ? 1 : 0);
