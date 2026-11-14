<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pengirim email asinkron.
 *
 * Strategi (urut prioritas):
 *  1. Spawn child PHP CLI process secara detached (via Artisan command).
 *     Cocok untuk XAMPP/Apache di Windows yang tidak melepas browser
 *     selama PHP request masih berjalan.
 *  2. Fallback: `app()->terminating()` (after-response hook).
 *  3. Fallback terakhir: kirim sinkron (blocking). Hanya kalau dua opsi
 *     di atas tidak tersedia.
 *
 * Untuk OTP gunakan `dispatchOtp()` karena sudah disesuaikan dengan
 * Artisan command khusus.
 */
class AsyncMailer
{
    /**
     * Spawn child process untuk mengirim OTP. Return SEGERA.
     *
     * @param string $email Tujuan email
     * @param string $otp   Kode OTP 6 digit
     * @param string $name  Nama lengkap user (akan di base64 untuk shell)
     * @param string $type  registration|password_reset|resend
     */
    public static function dispatchOtp(string $email, string $otp, string $name, string $type = 'registration'): bool
    {
        $mailable = new \App\Mail\OtpMail($otp, $name, $type, $email);
        $fallbackLog = "SIMALAS Fallback OTP for {$email}: {$otp}";

        // PHPUnit: kirim lewat Mail facade agar Mail::fake() menangkapnya (tanpa proses anak/SMTP).
        if (app()->runningUnitTests()) {
            Mail::to($email)->send($mailable);

            return true;
        }

        // VPS/Linux: kirim langsung dengan retry + fallback port 465.
        if (PHP_OS_FAMILY !== 'Windows') {
            try {
                self::sendOtpMailable($email, $mailable);
                Log::info("AsyncMailer OTP sent for {$email} (type={$type}).");

                return true;
            } catch (\Throwable $e) {
                Log::warning("AsyncMailer OTP failed for {$email}: ".$e->getMessage());
                Log::info($fallbackLog);

                return false;
            }
        }

        $phpBin = self::resolvePhpBinary();
        $artisan = base_path('artisan');

        if (!$phpBin || !is_file($phpBin) || !is_file($artisan)) {
            Log::warning('AsyncMailer: PHP CLI/artisan tidak ditemukan, fallback ke after-response.');

            return self::sendAfterResponse($email, $mailable, $fallbackLog);
        }

        if (function_exists('proc_open')) {
            // Bangun command CLI: cmd /c start "" /B "phpbin" "artisan" simalas:send-otp-mail ...
            // `start "" /B` memutus process child dari console parent (Apache/artisan-serve)
            // sehingga PHP CLI yang dispawn TIDAK mewarisi HTTP socket / stdin / stdout / stderr.
            $argString = sprintf(
                '"%s" "%s" simalas:send-otp-mail --email=%s --otp=%s --name=%s --type=%s',
                $phpBin,
                $artisan,
                escapeshellarg($email),
                escapeshellarg($otp),
                escapeshellarg(base64_encode($name)),
                escapeshellarg($type)
            );

            $wrapped = 'cmd /c start "" /B ' . $argString;

            // Redirect SEMUA I/O child ke NUL — ini krusial supaya tidak ada
            // handle yang menahan Apache menutup koneksi HTTP ke browser.
            $descriptors = [
                0 => ['file', 'NUL', 'r'],
                1 => ['file', 'NUL', 'w'],
                2 => ['file', 'NUL', 'w'],
            ];

            try {
                $proc = proc_open(
                    $wrapped,
                    $descriptors,
                    $pipes,
                    base_path(),
                    null,
                    ['bypass_shell' => true]
                );

                if (is_resource($proc)) {
                    // cmd /c start exits very fast after launching detached child,
                    // jadi proc_close tidak nunggu PHP CLI SMTP-nya.
                    proc_close($proc);
                    Log::info("AsyncMailer dispatchOtp spawned via proc_open for {$email} (type={$type}).");

                    return true;
                }

                Log::warning("AsyncMailer proc_open returned non-resource for {$email}.");
            } catch (\Throwable $e) {
                Log::warning("AsyncMailer proc_open failed for {$email}: " . $e->getMessage());
            }
        }

        return self::sendAfterResponse($email, $mailable, $fallbackLog);
    }

    /**
     * Kirim OTP dengan retry dan fallback smtp_ssl (port 465) bila 587 timeout.
     */
    private static function sendOtpMailable(string $email, Mailable $mailable): void
    {
        $mailers = array_values(array_unique([
            (string) config('mail.default', 'smtp'),
            'smtp',
            'smtp_ssl',
        ]));

        $lastError = null;

        foreach ($mailers as $mailer) {
            if (!config("mail.mailers.{$mailer}")) {
                continue;
            }

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    Mail::mailer($mailer)->to($email)->send($mailable);

                    return;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    Log::warning("AsyncMailer attempt {$attempt} via {$mailer} failed for {$email}: ".$e->getMessage());
                    if ($attempt < 3) {
                        usleep(800_000 * $attempt);
                    }
                }
            }
        }

        throw $lastError ?? new \RuntimeException('Pengiriman email OTP gagal.');
    }

    /**
     * Kirim email status persetujuan/penolakan akun setelah respons HTTP.
     */
    public static function dispatchAccountStatusMail(\App\Models\User $user, string $status, ?string $note = null): void
    {
        if (!filled($user->email)) {
            return;
        }

        if ($status === 'rejected' && filled($note)) {
            $user = clone $user;
            $user->approval_note = $note;
        }

        self::sendAfterResponse(
            $user->email,
            new \App\Mail\AccountStatusMail($user, $status),
            "SIMALAS Account {$status} notification for {$user->email}"
        );
    }

    /**
     * Jalankan pengiriman email setelah respons HTTP terkirim.
     */
  /**
     * @return bool true jika pengiriman dijadwalkan; false jika gagal sebelum response.
     */
    public static function sendAfterResponse(string $email, Mailable $mailable, ?string $fallbackLog = null): bool
    {
        return self::runAfterResponse(function () use ($email, $mailable) {
            Mail::to($email)->send($mailable);
        }, $fallbackLog, $email);
    }

    /**
     * Jalankan callable apa pun setelah response terkirim.
     */
    public static function runAfterResponse(callable $task, ?string $fallbackLog = null, ?string $context = null): bool
    {
        app()->terminating(function () use ($task, $fallbackLog, $context) {
            @ignore_user_abort(true);
            @set_time_limit(60);

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();

            try {
                $task();
            } catch (\Throwable $e) {
                $label = $context ? " ({$context})" : '';
                Log::warning("AsyncMailer task failed{$label}: " . $e->getMessage());
                if ($fallbackLog) {
                    Log::info($fallbackLog);
                }
            }
        });

        return true;
    }

    /**
     * Coba temukan binary PHP CLI yang valid.
     * Urutan: env PHP_CLI_BIN → PHP_BINARY (jika CLI) → kandidat default Windows.
     */
    private static function resolvePhpBinary(): ?string
    {
        $candidates = [];

        $envBin = env('PHP_CLI_BIN');
        if ($envBin) {
            $candidates[] = $envBin;
        }

        // PHP_BINARY hanya berguna kalau kita running di CLI atau FPM CLI mode.
        // Di Apache mod_php, PHP_BINARY menunjuk httpd.exe; jangan pakai itu.
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $bin = PHP_BINARY;
            if (stripos($bin, 'httpd') === false && stripos($bin, 'apache') === false) {
                $candidates[] = $bin;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = 'C:\\xampp\\php\\php.exe';
            $candidates[] = 'C:\\xampp\\php8\\php.exe';
            $candidates[] = 'C:\\Program Files\\PHP\\php.exe';
        } else {
            $candidates[] = '/usr/bin/php';
            $candidates[] = '/usr/local/bin/php';
        }

        foreach ($candidates as $c) {
            if ($c && is_file($c)) {
                return $c;
            }
        }
        return null;
    }
}
