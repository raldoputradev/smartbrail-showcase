<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{label: string, value: string}>  $rows
     */
    public function __construct(
        public string $namaLengkap,
        public string $tanggalLabel,
        public array $rows,
        public int $unreadCount,
        public string $portalUrl,
    ) {}

    public function build(): static
    {
        return $this->subject('SmartBrail — Ringkasan Harian ' . $this->tanggalLabel)
            ->view('emails.digest_html')
            ->text('emails.digest_text')
            ->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('X-SmartBrail-Mail-Type', 'daily_digest');
                $message->getHeaders()->addTextHeader('X-Mailer', 'SmartBrail');
            });
    }
}
