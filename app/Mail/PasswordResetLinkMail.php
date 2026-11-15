<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $namaLengkap,
        public readonly int $ttlMinutes = 60,
    ) {}

    public function build(): static
    {
        $data = [
            'resetUrl' => $this->resetUrl,
            'namaLengkap' => $this->namaLengkap,
            'ttlMinutes' => $this->ttlMinutes,
            'sentAt' => now()->format('d M Y, H:i:s'),
            'refCode' => strtoupper(substr(sha1($this->resetUrl . microtime(true)), 0, 8)),
        ];

        return $this->subject('AIoT SIMALAS — Atur Ulang Password Akun Anda')
            ->view('emails.reset_link_html', $data)
            ->text('emails.reset_link_text', $data)
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-SIMALAS-Mail-Type', 'password_reset_link');
                $headers->addTextHeader('X-Mailer', 'AIoT SIMALAS');
            });
    }
}
