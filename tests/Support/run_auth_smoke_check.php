<?php

/**
 * Uji login Laboran/Dosen, reCAPTCHA kosong, dan verifikasi OTP
 * pada sqlite. Tidak mengirim email dan tidak menyentuh MySQL simalas.
 */

use App\Models\User;
use App\Support\Recaptcha;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

require_once __DIR__.'/../../vendor/autoload.php';

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
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

$driver = (string) config('database.connections.'.config('database.default').'.driver');
$database = (string) config('database.connections.'.config('database.default').'.database');
if ($driver !== 'sqlite' || $database !== ':memory:') {
    fwrite(STDERR, "Ditolak: bukan sqlite memory.\n");
    exit(2);
}

Mail::fake();
Artisan::call('migrate', ['--force' => true]);

$make = function (string $role, string $nitk) {
    return User::query()->create([
        'nama_lengkap' => 'Uji '.$role,
        'nitk_nidn' => $nitk,
        'email' => strtolower($role).'@example.test',
        'password' => Hash::make('sandi-uji-lokal'),
        'role' => $role,
        'is_active' => true,
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);
};

$laboran = $make('laboran', 'LAB-UJI-001');
$dosen = $make('dosen', 'DOS-UJI-001');

$login = function (string $nitk, string $role) use ($app): array {
    Auth::logout();
    $app['session']->flush();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Request::create('/login', 'POST', [
        'nitk_nidn' => $nitk,
        'password' => 'sandi-uji-lokal',
        'role' => $role,
    ]);
    $request->headers->set('Accept', 'application/json');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $json = json_decode($response->getContent(), true);

    return ['status' => $response->getStatusCode(), 'json' => is_array($json) ? $json : []];
};

$lab = $login($laboran->nitk_nidn, 'laboran');
$dos = $login($dosen->nitk_nidn, 'dosen');
check(($lab['status'] ?? 0) === 200 && ($lab['json']['ok'] ?? false) === true && str_contains((string) ($lab['json']['redirect'] ?? ''), 'admin'), 'login laboran');
if (($dos['status'] ?? 0) !== 200 || ($dos['json']['ok'] ?? false) !== true) {
    $msg = (string) ($dos['json']['message'] ?? json_encode($dos['json']['errors'] ?? []));
    echo "INFO  dosen status {$dos['status']} {$msg}\n";
}
check(($dos['status'] ?? 0) === 200 && ($dos['json']['ok'] ?? false) === true && str_contains((string) ($dos['json']['redirect'] ?? ''), 'dosen'), 'login dosen');

$emptyCaptcha = Recaptcha::verify(null);
check(Recaptcha::isConfigured(), 'reCAPTCHA site key dan secret terpasang');
check(($emptyCaptcha['ok'] ?? true) === false, 'reCAPTCHA menolak token kosong');
check(
    str_contains((string) ($emptyCaptcha['message'] ?? ''), 'bukan robot')
    || str_contains((string) ($emptyCaptcha['message'] ?? ''), 'belum dikonfigurasi'),
    'pesan reCAPTCHA kosong tetap pesan validasi'
);

$pending = User::query()->create([
    'nama_lengkap' => 'Uji OTP',
    'nitk_nidn' => 'OTP-UJI-001',
    'email' => 'otp-uji@example.test',
    'password' => Hash::make('sandi-uji-lokal'),
    'role' => 'dosen',
    'is_active' => true,
    'approval_status' => 'approved',
]);
DB::table('email_verification_tokens')->insert([
    'email' => 'otp-uji@example.test',
    'token' => 'token-uji',
    'otp_code' => '135790',
    'type' => 'registration',
    'expires_at' => now()->addMinutes(30),
    'verified' => false,
    'created_at' => now(),
    'updated_at' => now(),
]);
Auth::logout();
$app['session']->flush();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$otpRequest = Request::create('/verify-registration', 'POST', [
    'email' => 'otp-uji@example.test',
    'otp_code' => '135790',
]);
$otpRequest->headers->set('Accept', 'application/json');
$otpResponse = $kernel->handle($otpRequest);
$kernel->terminate($otpRequest, $otpResponse);
$pending->refresh();
if ($pending->email_verified_at === null) {
    $loc = (string) $otpResponse->headers->get('Location');
    echo "INFO  otp status {$otpResponse->getStatusCode()} location {$loc}\n";
}
check($pending->email_verified_at !== null, 'verifikasi OTP registrasi tanpa mengirim email');
$wrong = DB::table('email_verification_tokens')->where('otp_code', '000000')->exists();
check($wrong === false, 'OTP yang tidak terdaftar tidak tersimpan');
check(Mail::sent(App\Mail\OtpMail::class)->count() === 0, 'tidak ada email OTP terkirim');

echo $failures === 0 ? "HASIL  semua pemeriksaan lulus\n" : "HASIL  {$failures} gagal\n";
exit($failures === 0 ? 0 : 1);
