<?php

use App\Models\User;
use App\Support\AccountActivation;
use App\Support\AccountEmailVerification;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

const TEST_DB = 'simalas_activation_test';
const TEST_CONNECTION = 'activation_test';
const MIGRATION_NO_HP = '2026_10_09_120000_add_no_hp_to_users_table';
const MIGRATION_ACCOUNT_VERIFY = '2026_10_09_120100_add_account_verify_to_email_verification_tokens_type';

$stage = 'bootstrap';
$createdByHarness = false;

function pass(string $message): void
{
    echo "PASS {$message}\n";
}

function note(string $message): void
{
    echo "INFO {$message}\n";
}

function guard(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('GUARD: '.$message);
    }
}

function localHost(mixed $host): bool
{
    return in_array((string) $host, ['127.0.0.1', 'localhost', '::1'], true);
}

function quoteIdentifier(string $identifier): string
{
    return '`'.str_replace('`', '``', $identifier).'`';
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
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function guardTestPdo(PDO $pdo): void
{
    $actual = $pdo->query('SELECT DATABASE() AS db')->fetchColumn();
    guard($actual === TEST_DB, 'PDO tidak berada pada database test');
    guard($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'Driver PDO bukan mysql');
}

function guardLaravel(): void
{
    guard(DB::getDefaultConnection() === TEST_CONNECTION, 'Koneksi default proses bukan koneksi test');
    guard(DB::connection()->getDriverName() === 'mysql', 'Driver Laravel bukan mysql');
    guard(localHost(config('database.connections.'.TEST_CONNECTION.'.host')), 'Host koneksi test bukan lokal');
    $actual = DB::selectOne('SELECT DATABASE() AS db');
    guard(($actual->db ?? null) === TEST_DB, 'Koneksi Laravel tidak berada pada database test');
}

function scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function tableRow(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function migrationRow(string $name): ?object
{
    return DB::table('migrations')->where('migration', $name)->first(['migration', 'batch']);
}

function runMigration(string $name): void
{
    global $stage;
    $stage = 'migration '.$name;
    guardLaravel();
    $path = base_path('database/migrations/'.$name.'.php');
    guard(is_file($path), 'File migration tidak ditemukan');
    $status = Artisan::call('migrate', [
        '--path' => $path,
        '--realpath' => true,
        '--force' => true,
    ]);
    guard($status === 0, 'Migration gagal: '.$name);
    $row = migrationRow($name);
    guard($row !== null, 'Catatan migration tidak ditemukan: '.$name);
    pass('migration '.$name.' batch='.$row->batch);
}

function httpRequest(string $method, string $uri, array $params, array &$cookies): array
{
    $server = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html,application/xhtml+xml'];
    $request = Request::create($uri, $method, $params, $cookies, [], $server);
    $kernel = app(HttpKernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }

    return [
        'status' => $response->getStatusCode(),
        'body' => (string) $response->getContent(),
        'location' => $response->headers->get('Location'),
    ];
}

function httpGet(string $uri, array &$cookies): array
{
    return httpRequest('GET', $uri, [], $cookies);
}

function httpPost(string $uri, array $params, array &$cookies, string $csrf): array
{
    global $stage;
    $stage = 'internal POST '.$uri;

    return httpRequest('POST', $uri, ['_token' => $csrf] + $params, $cookies);
}

function csrfFrom(string $body): string
{
    if (! preg_match('/name=["\']_token["\']\s+value=["\']([^"\']+)["\']/', $body, $match)
        && ! preg_match('/name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/', $body, $match)) {
        throw new RuntimeException('GUARD: CSRF token tidak ditemukan pada halaman aktivasi');
    }

    return html_entity_decode($match[1], ENT_QUOTES);
}

function expectStatus(array $response, int $expected): void
{
    guard($response['status'] === $expected, 'Status internal tidak sesuai: '.$response['status']);
}

try {
    $root = dirname(__DIR__, 2);
    $env = [
        'APP_ENV' => 'testing',
        'APP_URL' => 'http://localhost',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'SIMALAS_ACTIVATION_EMAIL_DNS_CHECK' => 'false',
    ];
    foreach ($env as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();

    config([
        'cache.default' => 'array',
        'session.driver' => 'array',
        'broadcasting.default' => 'null',
        'queue.default' => 'sync',
        'mail.default' => 'array',
        'services.gemini.api_key' => '',
        'mobile.fcm.server_key' => null,
        'mobile.fcm.project_id' => null,
        'simalas.activation_email_dns_check' => false,
    ]);
    Mail::fake();
    Notification::fake();
    Queue::fake();
    Bus::fake();
    Http::fake();
    Http::preventStrayRequests();

    $source = config('database.connections.mysql');
    guard(($source['driver'] ?? null) === 'mysql', 'Driver sumber bukan mysql');
    guard(localHost($source['host'] ?? null), 'Host sumber bukan lokal');
    guard(($source['database'] ?? null) !== TEST_DB, 'Database sumber sudah memakai nama test');

    $admin = pdoFor($source, $source['database']);
    $exists = scalar($admin, 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=?', [TEST_DB]);
    guard($exists === false, 'Database test sudah ada dan tidak akan digunakan/ditimpa');

    $stage = 'create isolated database';
    $admin->exec('CREATE DATABASE '.quoteIdentifier(TEST_DB).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $createdByHarness = true;
    $testPdo = pdoFor($source, TEST_DB);
    guardTestPdo($testPdo);
    pass('database test dibuat dengan identitas lokal/mysql');

    $stage = 'copy schema-only';
    $tables = $admin->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=".$admin->quote($source['database'])." AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);
    guard(in_array('users', $tables, true) && in_array('email_verification_tokens', $tables, true), 'Tabel prasyarat tidak lengkap');
    $testPdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $copied = 0;
    foreach ($tables as $table) {
        $definition = $admin->query('SHOW CREATE TABLE '.quoteIdentifier($source['database']).'.'.quoteIdentifier($table))->fetch();
        $sql = $definition['Create Table'] ?? null;
        guard(is_string($sql) && preg_match('/^CREATE TABLE/i', $sql) === 1, 'Definisi bukan base table');
        guard(preg_match('/\b(DEFINER|TRIGGER|ROUTINE)\b/i', $sql) !== 1, 'Objek berisiko pada definisi tabel');
        guard(stripos($sql, quoteIdentifier($source['database']).'.') === false, 'Referensi database operasional pada schema');
        $sql = preg_replace('/\sAUTO_INCREMENT=\d+/i', '', $sql);
        guardTestPdo($testPdo);
        $testPdo->exec($sql);
        $copied++;
    }
    $testPdo->exec('SET FOREIGN_KEY_CHECKS=1');
    guardTestPdo($testPdo);
    pass("schema-only {$copied} base tables; tanpa record/trigger/event/routine");

    $initialMigrationCount = (int) scalar($testPdo, 'SELECT COUNT(*) FROM migrations');
    guard($initialMigrationCount === 0, 'Repository migration test tidak dimulai kosong');
    $initialNoHp = tableRow($testPdo, 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [TEST_DB, 'users', 'no_hp']);
    $initialType = tableRow($testPdo, 'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [TEST_DB, 'email_verification_tokens', 'type']);
    guard($initialNoHp === null, 'Kondisi awal no_hp tidak sesuai');
    guard($initialType !== null && ! str_contains($initialType['COLUMN_TYPE'], 'account_verify'), 'Kondisi awal enum tidak sesuai');
    pass('repository migration awal kosong; schema awal sesuai');

    $stage = 'configure isolated Laravel connection';
    $testConfig = $source;
    $testConfig['database'] = TEST_DB;
    config([
        'database.connections.'.TEST_CONNECTION => $testConfig,
        'database.default' => TEST_CONNECTION,
    ]);
    foreach (['DB_CONNECTION' => TEST_CONNECTION, 'DB_DATABASE' => TEST_DB] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    DB::purge(TEST_CONNECTION);
    DB::setDefaultConnection(TEST_CONNECTION);
    DB::reconnect(TEST_CONNECTION);
    guardLaravel();
    pass('guard Laravel menunjuk database test');

    $stage = 'synthetic fixtures';
    $user = new User;
    $user->forceFill([
        'nama_lengkap' => 'Akun Sintetis Aktivasi',
        'nitk_nidn' => 'UJI-AKTIVASI-0001',
        'email' => null,
        'password' => 'temporary',
        'role' => 'mahasiswa',
        'kelas' => 'Uji Pagi',
        'is_active' => false,
        'approval_status' => AccountActivation::STATUS,
        'failed_login_attempts' => 0,
        'login_requires_admin_unlock' => false,
    ])->save();
    $issued = AccountActivation::issueCode($user, null, null, 'Fixture aktivasi terisolasi');
    $activationCode = $issued['code'];
    $now = now();
    foreach (['registration', 'password_reset'] as $index => $type) {
        DB::table('email_verification_tokens')->insert([
            'email' => 'synthetic-'.$index.'@example.test',
            'token' => 'synthetic-token-'.$index,
            'otp_code' => '10000'.$index,
            'type' => $type,
            'expires_at' => $now->copy()->addHour(),
            'verified' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $userBefore = $user->fresh();
    $beforeUser = [
        'id' => $userBefore->id,
        'nitk_nidn' => $userBefore->nitk_nidn,
        'nama_lengkap' => $userBefore->nama_lengkap,
        'role' => $userBefore->role,
        'kelas' => $userBefore->kelas,
        'password' => $userBefore->getRawOriginal('password'),
        'approval_status' => $userBefore->approval_status,
        'is_active' => $userBefore->is_active,
        'created_at' => (string) $userBefore->created_at,
    ];
    $beforeTokens = DB::table('email_verification_tokens')
        ->orderBy('id')
        ->get(['email', 'token', 'otp_code', 'type', 'expires_at', 'verified'])
        ->map(fn ($row) => (array) $row)->all();
    $beforeHash = hash('sha256', json_encode([$beforeUser, $beforeTokens]));
    pass('fixture sintetis dan checksum non-target disimpan');

    runMigration(MIGRATION_NO_HP);
    guard((int) DB::table('migrations')->count() === 1, 'Jumlah repository setelah migration pertama bukan satu');

    runMigration(MIGRATION_ACCOUNT_VERIFY);
    $migrationNames = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    sort($migrationNames);
    $expectedNames = [MIGRATION_NO_HP, MIGRATION_ACCOUNT_VERIFY];
    sort($expectedNames);
    guard($migrationNames === $expectedNames, 'Repository memuat migration di luar dua target');
    pass('repository hanya memuat dua migration target');

    $stage = 'schema and preservation assertions';
    $noHp = tableRow($testPdo, 'SELECT DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,ORDINAL_POSITION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [TEST_DB, 'users', 'no_hp']);
    $emailPosition = (int) scalar($testPdo, 'SELECT ORDINAL_POSITION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [TEST_DB, 'users', 'email']);
    guard($noHp !== null && $noHp['DATA_TYPE'] === 'varchar' && $noHp['COLUMN_TYPE'] === 'varchar(20)' && $noHp['IS_NULLABLE'] === 'YES', 'Kolom no_hp tidak sesuai');
    guard((int) $noHp['ORDINAL_POSITION'] === $emailPosition + 1, 'Posisi no_hp bukan setelah email');
    $type = tableRow($testPdo, 'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [TEST_DB, 'email_verification_tokens', 'type']);
    guard($type !== null, 'Kolom type tidak ditemukan');
    foreach (['registration', 'password_reset', 'account_verify'] as $value) {
        guard(str_contains($type['COLUMN_TYPE'], "'".$value."'"), 'Nilai enum hilang: '.$value);
    }
    $afterUser = $user->fresh();
    $afterUserComparable = [
        'id' => $afterUser->id,
        'nitk_nidn' => $afterUser->nitk_nidn,
        'nama_lengkap' => $afterUser->nama_lengkap,
        'role' => $afterUser->role,
        'kelas' => $afterUser->kelas,
        'password' => $afterUser->getRawOriginal('password'),
        'approval_status' => $afterUser->approval_status,
        'is_active' => $afterUser->is_active,
        'created_at' => (string) $afterUser->created_at,
    ];
    $afterTokens = DB::table('email_verification_tokens')
        ->orderBy('id')
        ->get(['email', 'token', 'otp_code', 'type', 'expires_at', 'verified'])
        ->map(fn ($row) => (array) $row)->all();
    $afterHash = hash('sha256', json_encode([$afterUserComparable, $afterTokens]));
    guard($beforeHash === $afterHash, 'Data sintetis/non-target berubah saat migration');
    pass('no_hp nullable setelah email; enum lengkap; data sintetis utuh');

    $stage = 'activation initial page';
    $cookies = [];
    $page = httpGet('/aktivasi', $cookies);
    expectStatus($page, 200);
    $csrf = csrfFrom($page['body']);
    $passwordBeforeAttempts = $user->fresh()->getRawOriginal('password');

    $weak = httpPost('/aktivasi/mulai', [
        'nitk_nidn' => 'UJI-AKTIVASI-0001',
        'kode_aktivasi' => $activationCode,
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ], $cookies, $csrf);
    expectStatus($weak, 302);
    guard($user->fresh()->getRawOriginal('password') === $passwordBeforeAttempts, 'Password lemah mengubah akun');
    pass('password lemah ditolak tanpa mengubah akun');

    $strongPassword = 'Uji-Aktivasi#2026';
    $started = httpPost('/aktivasi/mulai', [
        'nitk_nidn' => 'UJI-AKTIVASI-0001',
        'kode_aktivasi' => $activationCode,
        'password' => $strongPassword,
        'password_confirmation' => $strongPassword,
    ], $cookies, $csrf);
    expectStatus($started, 302);
    guard($user->fresh()->getRawOriginal('password') !== $passwordBeforeAttempts, 'Password kuat tidak diterapkan');
    pass('kode aktivasi dan password kuat diterima');

    $invalidProfile = httpPost('/aktivasi/profil', [
        'email' => 'aktivasi@example.test',
        'no_hp' => 'abc',
    ], $cookies, $csrf);
    expectStatus($invalidProfile, 302);
    guard($user->fresh()->email === null, 'Profil invalid tersimpan');
    pass('nomor HP invalid ditolak');

    $profile = httpPost('/aktivasi/profil', [
        'email' => 'aktivasi@example.test',
        'no_hp' => '+628123456789',
    ], $cookies, $csrf);
    expectStatus($profile, 302);
    $profiled = $user->fresh();
    guard($profiled->email === 'aktivasi@example.test' && $profiled->no_hp === '+628123456789', 'Profil valid tidak tersimpan');
    $otpRow = DB::table('email_verification_tokens')
        ->where('email', 'aktivasi@example.test')
        ->where('type', AccountEmailVerification::TYPE)
        ->latest('id')
        ->first();
    guard($otpRow !== null, 'OTP account_verify tidak dibuat');
    pass('profil dan OTP account_verify dibuat; mail hanya fake');

    $correctOtp = (string) $otpRow->otp_code;
    $wrongOtp = str_pad((string) (((int) $correctOtp + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    $wrong = httpPost('/aktivasi/otp', ['otp_code' => $wrongOtp], $cookies, $csrf);
    expectStatus($wrong, 302);
    guard(! $user->fresh()->is_active, 'OTP salah mengaktifkan akun');
    pass('OTP salah tidak mengaktifkan akun');

    $verified = httpPost('/aktivasi/otp', ['otp_code' => $correctOtp], $cookies, $csrf);
    expectStatus($verified, 302);
    guard(str_contains((string) $verified['location'], '/aktivasi/selesai'), 'Redirect penyelesaian tidak sesuai');
    $active = $user->fresh();
    guard($active->is_active && $active->approval_status === 'approved', 'Status akhir aktivasi tidak sesuai');
    guard($active->email_verified_at !== null && $active->no_hp === '+628123456789', 'Hasil profil/verifikasi tidak sesuai');
    guard($active->finger_id !== null, 'finger_id tidak dialokasikan');
    guard(DB::table('account_approval_logs')->where('user_id', $active->id)->where('action', 'activation_completed')->exists(), 'Log activation_completed tidak ditemukan');
    Mail::assertSent(App\Mail\OtpMail::class);
    pass('aktivasi selesai approved sesuai aturan import, tanpa approval laboran tambahan');

    note('SELF_REGISTRATION_APPROVAL_NOT_TESTED');
    note('TEST_DATABASE_RETAINED='.TEST_DB);
    note('NO_SCHEMA_ARTIFACTS_CREATED');
    pass('SEMUA PEMERIKSAAN SELESAI');
} catch (Throwable $e) {
    $safe = str_starts_with($e->getMessage(), 'GUARD: ') ? $e->getMessage() : 'detail tidak ditampilkan';
    fwrite(STDERR, "FAIL stage={$stage} class=".get_class($e)." message={$safe}\n");
    fwrite(STDERR, 'TEST_DATABASE_CREATED='.($createdByHarness ? 'true' : 'false')."; retained, tidak drop otomatis\n");
    exit(2);
}
