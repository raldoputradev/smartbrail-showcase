<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\FingerprintSyncService;
use App\Support\FingerprintTemplateCodec;
use Illuminate\Console\Command;

class FingerprintReadinessCommand extends Command
{
    protected $signature = 'simalas:fingerprint-readiness
                            {--clear-legacy : Reset finger_template legacy ke null (siap re-enroll)}';

    protected $description = 'Laporan kesiapan blob template sidik jari + opsi bersihkan data legacy';

    public function handle(): int
    {
        $mahasiswa = User::query()->where('role', 'mahasiswa')->get();

        $blobOk = $mahasiswa->filter(fn (User $u) => $u->hasFingerprintEnrolled())->count();
        $legacy = $mahasiswa->filter(fn (User $u) => $u->hasLegacyFingerprintPlaceholder())->count();
        $pending = FingerprintSyncService::countPendingEnrollMahasiswa();
        $unassigned = $mahasiswa->whereNull('finger_id')->count();
        $total = $mahasiswa->count();
        $pct = $total > 0 ? round(($blobOk / $total) * 100, 1) : 0;

        $this->info('=== SIMALAS Fingerprint Blob Readiness ===');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total mahasiswa', $total],
                ['Blob OK (512 byte valid)', $blobOk],
                ['Legacy (SENSOR_ENROLLED)', $legacy],
                ['Belum enroll (punya finger_id)', $pending],
                ['Belum assign finger_id', $unassigned],
                ['Progress enroll blob', "{$pct}%"],
            ]
        );

        if ($legacy > 0) {
            $this->warn("Ada {$legacy} mahasiswa legacy — wajib re-enroll di mesin absensi.");
            $this->line('  Admin → Fingerprint → filter "Perlu Enroll Ulang (Legacy)"');
            $this->line('  Mesin absensi: perintah e → finger_id → scan 2×');
        }

        if ($this->option('clear-legacy')) {
            $cleared = 0;
            foreach ($mahasiswa as $user) {
                if (! $user->hasLegacyFingerprintPlaceholder()) {
                    continue;
                }
                $user->finger_template = null;
                $user->save();
                $cleared++;
                $this->line("  Reset legacy: #{$user->finger_id} {$user->nama_lengkap}");
            }
            $this->info("Legacy dibersihkan: {$cleared} mahasiswa (siap re-enroll).");
        } elseif ($legacy > 0) {
            $this->line('');
            $this->line('Jalankan: php artisan simalas:fingerprint-readiness --clear-legacy');
        }

        $this->newLine();
        $this->line('Target software (backend + firmware sketch): ~100% selesai.');
        $this->line('Target operasional penuh: flash ESP32 + re-enroll + uji tap absensi.');

        return self::SUCCESS;
    }
}
