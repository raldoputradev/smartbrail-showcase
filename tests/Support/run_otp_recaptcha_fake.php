<?php

/**
 * reCAPTCHA (Http::fake, tanpa request ke Google) dan OTP (Mail::fake + child process PHP_CLI_BIN dengan mailer array).
 * Tidak mengirim email, tidak menyentuh MySQL. Wajib dijalankan dengan MAIL_MAILER=array di environment proses.
 */

use App\Mail\OtpMail;
use App\Support\AsyncMailer;
use App\Support\Recaptcha;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS  ' : 'FAIL  ').$label."\n";
    $failures += $ok ? 0 : 1;
}

$child = shell_exec('cmd /c echo %MAIL_MAILER%,%MAIL_HOST%,%MAIL_PORT%');
if (trim((string) $child) !== 'array,127.0.0.1,9') {
    fwrite(STDERR, "Ditolak: environment proses harus MAIL_MAILER=array, MAIL_HOST=127.0.0.1, MAIL_PORT=9\n");
    exit(2);
}

require_once __DIR__.'/../../vendor/autoload.php';
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $k => $v) {
    putenv($k.'='.$v);
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('mail.default') !== 'array' || config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Ditolak: mailer/database bukan uji\n");
    exit(2);
}

echo 'PHP ', PHP_VERSION, ' ', php_ini_loaded_file(), "\n";

config(['services.recaptcha.site_key' => 'uji-site', 'services.recaptcha.secret_key' => 'uji-secret']);
Http::preventStrayRequests();
Http::fakeSequence('www.google.com/recaptcha/*')
    ->push(['success' => true])
    ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])
    ->push('', 500)
    ->pushFailedConnection();

$r = Recaptcha::verify('token-1', '127.0.0.1');
check($r['ok'] === true, 'reCAPTCHA fake sukses diterima');
Http::assertSent(fn (HttpRequest $req) => $req['secret'] === 'uji-secret' && $req['response'] === 'token-1' && $req['remoteip'] === '127.0.0.1');
$r = Recaptcha::verify('token-2');
check($r['ok'] === false && str_contains($r['message'], 'kedaluwarsa'), 'reCAPTCHA timeout-or-duplicate ditolak dengan pesan');
$r = Recaptcha::verify('token-3');
check($r['ok'] === false && str_contains($r['message'], 'gagal'), 'reCAPTCHA HTTP 500 ditolak');
$r = Recaptcha::verify('token-4');
check($r['ok'] === false && str_contains($r['message'], 'tidak dapat dihubungi'), 'reCAPTCHA koneksi gagal ditolak tanpa exception');
$r = Recaptcha::verify(null);
check($r['ok'] === false && str_contains($r['message'], 'bukan robot'), 'reCAPTCHA token kosong ditolak tanpa request');
Http::assertSentCount(4);

Mail::fake();
$code = Artisan::call('simalas:send-otp-mail', ['--email' => 'otp-uji@example.invalid', '--otp' => '246810', '--name' => base64_encode('Uji OTP'), '--type' => 'registration']);
check($code === 0, 'command OTP selesai (Mail::fake)');
Mail::assertSent(OtpMail::class, fn (OtpMail $m) => $m->hasTo('otp-uji@example.invalid'));
check(Mail::sent(OtpMail::class)->count() === 1, 'satu OtpMail tertangkap fake');

$bin = (new ReflectionMethod(AsyncMailer::class, 'resolvePhpBinary'))->invoke(null);
$version = trim((string) shell_exec('"'.$bin.'" -r "echo PHP_VERSION;"'));
check($bin === 'C:/tools/php83/php.exe' && $version === '8.3.35', "PHP_CLI_BIN OTP = {$bin} ({$version})");

$log = storage_path('logs/laravel.log');
$email = 'otp-spawn-'.bin2hex(random_bytes(3)).'@example.invalid';
$ok = AsyncMailer::dispatchOtp($email, '135791', 'Uji Spawn', 'registration');
check($ok === true, 'dispatchOtp memulai child process');
$done = null;
for ($i = 0; $i < 40 && $done === null; $i++) {
    usleep(500_000);
    $text = (string) file_get_contents($log);
    if (str_contains($text, "simalas:send-otp-mail OK for {$email}")) {
        $done = 'ok';
    } elseif (str_contains($text, "simalas:send-otp-mail FAILED for {$email}")) {
        $done = 'gagal';
    }
}
check($done === 'ok', 'child PHP 8.3 mengirim OTP lewat mailer array (bukan SMTP)');

echo $failures === 0 ? "HASIL gagal 0\n" : "HASIL gagal {$failures}\n";
exit($failures === 0 ? 0 : 1);
