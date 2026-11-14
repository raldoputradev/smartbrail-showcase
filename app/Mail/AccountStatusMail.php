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
            ? 'AIoT SIMALAS — Akun Anda Disetujui'
            : 'AIoT SIMALAS — Status Pendaftaran Akun';

        $data = [
            'user' => $this->user,
            'status' => $this->status,
            'approved' => $approved,
            'namaLengkap' => $this->user->nama_lengkap,
            'headline' => $approved ? 'Akun Disetujui' : 'Pendaftaran Ditolak',
            'subtitle' => $approved
                ? 'Anda sudah bisa login ke SIMALAS'
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
                $headers->addTextHeader('X-SIMALAS-Mail-Type', 'account_status_' . $this->status);
                $headers->addTextHeader('X-Mailer', 'AIoT SIMALAS');
            });
    }

    private function loginHint(): string
    {
        return match ($this->user->role) {
            'mahasiswa' => 'Buka aplikasi mobile AIoT SIMALAS dan login dengan email serta kata sandi Anda.',
            'dosen' => 'Buka panel web dosen SIMALAS dan login dengan email serta kata sandi Anda.',
            default => 'Login ke SIMALAS dengan email dan kata sandi Anda.',
        };
    }
}
