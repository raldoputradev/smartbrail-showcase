<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $status,
    ) {}

    public function build(): static
    {
        $approved = $this->status === 'approved';
        $subject = $approved
            ? 'SmartBrail — Akun Anda Disetujui'
            : 'SmartBrail — Status Pendaftaran Akun';

        $data = [
            'user' => $this->user,
            'status' => $this->status,
            'approved' => $approved,
            'namaLengkap' => $this->user->nama_lengkap,
            'headline' => $approved ? 'Akun Disetujui' : 'Pendaftaran Ditolak',
            'subtitle' => $approved
                ? 'Anda sudah bisa login ke SmartBrail'
                : 'Pendaftaran akun tidak disetujui admin',
            'loginHint' => $this->loginHint(),
            'sentAt' => now()->format('d M Y, H:i:s'),
            'refCode' => strtoupper(substr(sha1($this->user->id . microtime(true)), 0, 8)),
        ];

        return $this->subject($subject)
            ->view('emails.account_status_html', $data)
            ->text('emails.account_status_text', $data)
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-SmartBrail-Mail-Type', 'account_status_' . $this->status);
                $headers->addTextHeader('X-Mailer', 'SmartBrail');
            });
    }

    private function loginHint(): string
    {
        return match ($this->user->role) {
            'mahasiswa' => 'Buka aplikasi mobile SmartBrail dan login dengan email serta kata sandi Anda.',
            'dosen' => 'Buka panel web dosen SmartBrail dan login dengan email serta kata sandi Anda.',
            default => 'Login ke SmartBrail dengan email dan kata sandi Anda.',
        };
    }
}
