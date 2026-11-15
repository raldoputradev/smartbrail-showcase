<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $namaLengkap;
    public string $tipe;
    public ?string $email;

    /**
     * Create a new OTP mail instance.
     */
    public function __construct(string $otpCode, string $namaLengkap, string $tipe = 'registration', ?string $email = null)
    {
        $this->otpCode = $otpCode;
        $this->namaLengkap = $namaLengkap;
        $this->tipe = $tipe;
        $this->email = $email;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $subject = match ($this->tipe) {
            'password_reset' => 'SmartBrail — Kode OTP Reset Kata Sandi',
            'resend'         => 'SmartBrail — Kode Verifikasi Baru',
            'registration'   => 'SmartBrail — Verifikasi Email Registrasi',
            'account_verify' => 'SmartBrail — Verifikasi Email Akun',
            default          => 'SmartBrail — Kode Verifikasi',
        };

        $data = $this->getViewData();
        $tipe = $this->tipe;
        $otpCode = $this->otpCode;

        return $this->subject($subject)
                     ->view('emails.otp_html', $data)
                     ->text('emails.otp_text', $data)
                     ->withSymfonyMessage(function ($message) use ($tipe, $otpCode) {
                         $headers = $message->getHeaders();
                         $headers->addTextHeader('X-SmartBrail-Mail-Type', $tipe);
                         $headers->addTextHeader('X-Mailer', 'SmartBrail');
                         $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'simalas.local';
                         $unique = bin2hex(random_bytes(8)) . '.' . substr(sha1($otpCode . microtime(true)), 0, 12);
                         try {
                             $headers->remove('Message-ID');
                             $headers->addIdHeader('Message-ID', $unique . '@' . $domain);
                         } catch (\Throwable $e) {
                             $headers->addTextHeader('X-SmartBrail-Ref', $unique);
                         }
                     });
    }

    /**
     * Get the view data array.
     */
    private function getViewData(): array
    {
        $user = null;
        if (filled($this->email)) {
            try {
                $user = User::query()->where('email', $this->email)->first();
            } catch (\Throwable) {
                // Jangan gagalkan pengiriman email hanya karena query user error.
            }
        }

        $data = [
            'otpCode'          => $this->otpCode,
            'namaLengkap'      => $this->namaLengkap,
            'headline'         => $this->getHeadline(),
            'subtitle'         => $this->getSubtitle(),
            'desc'             => $this->getDesc(),
            'expiry'           => $this->getExpiry($user),
            'warning'          => $this->getWarning(),
            'isResend'         => $this->tipe === 'resend',
            'sentAt'           => now()->format('d M Y, H:i:s'),
            'refCode'          => strtoupper(substr(sha1($this->otpCode . microtime(true)), 0, 8)),
            'verifyUrl'        => null,
            'mobileResumeHint' => false,
        ];

        if (in_array($this->tipe, ['registration', 'resend'], true) && filled($this->email)) {
            if ($user?->role === 'mahasiswa') {
                $data['mobileResumeHint'] = true;
            }
        }

        return $data;
    }

    private function getHeadline(): string
    {
        return match ($this->tipe) {
            'password_reset' => 'Reset Kata Sandi',
            'registration', 'resend', 'account_verify' => 'Verifikasi Email',
            default => 'Verifikasi Akun',
        };
    }

    private function getSubtitle(): string
    {
        return match ($this->tipe) {
            'password_reset' => 'Permintaan penggantian kata sandi akun Anda',
            'registration'   => 'Penyelesaian proses registrasi akun baru',
            'resend'         => 'Pengiriman ulang kode verifikasi',
            'account_verify' => 'Konfirmasi alamat email akun Anda',
            default          => 'Konfirmasi keamanan akun',
        };
    }

    private function getDesc(): string
    {
        return match ($this->tipe) {
            'password_reset' => 'Terima kasih. Gunakan kode verifikasi (OTP) berikut untuk melanjutkan proses penggantian kata sandi akun Anda:',
            'registration', 'resend', 'account_verify' => 'Terima kasih telah mendaftar di SmartBrail. Gunakan kode verifikasi (OTP) berikut untuk menyelesaikan proses registrasi akun Anda:',
            default => 'Gunakan kode verifikasi (OTP) berikut untuk melanjutkan:',
        };
    }

    private function getExpiry(?User $user): string
    {
        if ($this->tipe === 'password_reset') {
            return '10 menit';
        }

        if ($user?->role === 'mahasiswa') {
            return '10 menit';
        }

        return '30 menit';
    }

    private function getWarning(): string
    {
        return match ($this->tipe) {
            'password_reset' => 'Apabila Anda tidak mengajukan permintaan reset kata sandi, abaikan email ini. Jangan bagikan kode OTP kepada siapa pun.',
            'registration', 'resend', 'account_verify' => 'Apabila Anda tidak melakukan pendaftaran di SmartBrail, abaikan email ini. Jangan bagikan kode OTP kepada siapa pun demi keamanan akun Anda.',
            default => 'Jangan bagikan kode OTP kepada siapa pun.',
        };
    }
}
