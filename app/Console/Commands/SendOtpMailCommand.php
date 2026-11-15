<?php

namespace App\Console\Commands;

use App\Mail\OtpMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Artisan command yang dipanggil sebagai detached child process untuk
 * mengirim email OTP tanpa memblokir request HTTP utama.
 *
 * Argumen `name` di-encode base64 untuk menghindari masalah escape shell
 * (apostrof, spasi, karakter unicode).
 */
class SendOtpMailCommand extends Command
{
    protected $signature = 'simalas:send-otp-mail
        {--email= : Alamat email tujuan}
        {--otp= : Kode OTP 6 digit}
        {--name= : Nama lengkap user (base64-encoded)}
        {--type=registration : Tipe email (registration|password_reset|resend)}';

    protected $description = 'Mengirim email OTP secara terpisah (dipanggil sebagai child process).';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $otp   = (string) $this->option('otp');
        $name  = (string) $this->option('name');
        $type  = (string) $this->option('type');

        if ($email === '' || $otp === '') {
            $this->error('email dan otp wajib diisi.');
            return self::FAILURE;
        }

        // Decode name base64 jika valid.
        $decoded = base64_decode($name, true);
        if ($decoded !== false) {
            $name = $decoded;
        }
        if ($name === '') {
            $name = 'Pengguna';
        }
        if (!in_array($type, ['registration', 'password_reset', 'resend', 'account_verify'], true)) {
            $type = 'registration';
        }

        try {
            Mail::to($email)->send(new OtpMail($otp, $name, $type, $email));
            Log::info("simalas:send-otp-mail OK for {$email} (type={$type})");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::warning("simalas:send-otp-mail FAILED for {$email}: " . $e->getMessage());
            // Kode OTP tidak dicatat — rahasia tidak boleh masuk log.
            Log::info("SIMALAS Fallback OTP gagal terkirim untuk {$email} (type={$type}); minta kirim ulang.");
            return self::FAILURE;
        }
    }
}
