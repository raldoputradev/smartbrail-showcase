<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    protected $signature = 'mail:test {email?}';
    protected $description = 'Test SMTP email sending';

    public function handle()
    {
        $email = $this->argument('email') ?? 'tujuan@example.test';
        $this->info("Mengirim test email ke: {$email}...");

        try {
            Mail::send([], [], function ($message) use ($email) {
                $message->to($email)
                        ->subject('SIMALAS - Test Koneksi SMTP')
                        ->html(
                            '<div style="font-family:Inter,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f8f2ef;border-radius:12px;">'
                            . '<div style="text-align:center;margin-bottom:24px;">'
                            . '<h2 style="color:#2d3748;margin:0;">🔐 SIMALAS</h2>'
                            . '<p style="color:#718096;font-size:14px;">Test Email</p>'
                            . '</div>'
                            . '<div style="background:#ffffff;padding:24px;border-radius:10px;text-align:center;">'
                            . '<p style="color:#4a5568;font-size:14px;">Email SMTP berhasil terhubung!</p>'
                            . '<p style="color:#718096;font-size:12px;">Konfigurasi SMTP Gmail sudah benar.</p>'
                            . '</div>'
                            . '<p style="text-align:center;color:#a0aec0;font-size:11px;margin-top:16px;">© SIMALAS ' . date('Y') . '</p>'
                            . '</div>'
                        );
            });

            $this->info('✅ EMAIL BERHASIL TERKIRIM! Cek inbox ' . $email);
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ GAGAL: ' . $e->getMessage());
            return 1;
        }
    }
}
