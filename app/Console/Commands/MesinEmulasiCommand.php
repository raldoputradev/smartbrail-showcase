<?php

namespace App\Console\Commands;

use App\Models\MesinIot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Emulator perangkat pintu ESP32 — menguji kontrak akses end-to-end
 * (acl → check → event) persis seperti mesin asli, tanpa hardware.
 *
 * Provisioning uji:   php artisan mesin:emulasi --setup --ruangan=1
 * Jalankan skenario:  php artisan mesin:emulasi --id=EMU-DOOR-1 --key=<key>
 *                     [--base=https://smartbrail.com] [--tipe=rfid --nilai=04A1B2]
 */
class MesinEmulasiCommand extends Command
{
    protected $signature = 'mesin:emulasi
        {--id= : id_mesin perangkat}
        {--key= : api_key plaintext dari provisioning}
        {--setup : buat perangkat uji + tampilkan key sekali}
        {--ruangan= : ruangan_id untuk --setup}
        {--base= : base URL API (default config app.url)}
        {--tipe=fingerprint : tipe kredensial check (fingerprint|rfid|pin)}
        {--nilai= : nilai kredensial (default: entri finger pertama dari ACL)}
        {--tolak : jalankan juga skenario kredensial tidak dikenal}';

    protected $description = 'Emulasi perangkat pintu: uji kontrak acl/check/event end-to-end.';

    public function handle(): int
    {
        if ($this->option('setup')) {
            return $this->setup();
        }

        $id = (string) $this->option('id');
        $key = (string) $this->option('key');
        if ($id === '' || $key === '') {
            $this->error('Wajib --id dan --key (atau --setup untuk provisioning).');
            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');
        $http = Http::baseUrl($base)->acceptJson()->timeout(15)
            ->withHeaders(['X-API-KEY' => $key, 'X-ID-MESIN' => $id]);

        $this->info("== EMU {$id} → {$base}");

        // 1) Tarik ACL
        $acl = $http->get('/api/mesin/ruangan/access/acl');
        if (!$acl->ok() || !$acl->json('ok')) {
            $this->error("ACL gagal ({$acl->status()}): " . $acl->body());
            return self::FAILURE;
        }
        $entries = $acl->json('entries', []);
        $this->info("ACL ruangan {$acl->json('ruangan_id')}: " . count($entries) . " entri, ttl {$acl->json('ttl')}s");

        // 2) Cek izin — default: pakai entri finger pertama dari ACL.
        $tipe = (string) $this->option('tipe');
        $nilai = (string) $this->option('nilai');
        if ($nilai === '' && $tipe === 'fingerprint') {
            $first = collect($entries)->firstWhere('t', 'finger');
            $nilai = $first['v'] ?? '1';
            $this->line("   (nilai otomatis dari ACL: finger {$nilai})");
        }
        $check = $http->post('/api/mesin/ruangan/access/check', [
            'tipe' => $tipe, 'nilai' => $nilai, 'waktu' => now()->toIso8601String(),
        ]);
        $this->line("check {$tipe}:{$nilai} → " . $check->body());

        // 3) Lapor event buka
        $event = $http->post('/api/mesin/ruangan/access/event', [
            'jenis' => 'buka', 'hasil' => 'dibuka',
            'meta' => ['emulator' => true, 'tipe' => $tipe, 'nilai' => $nilai],
            'terjadi_pada' => now()->toIso8601String(),
        ]);
        $this->line('event buka → ' . $event->body());

        if ($this->option('tolak')) {
            $bad = $http->post('/api/mesin/ruangan/access/check', [
                'tipe' => 'fingerprint', 'nilai' => '999999',
            ]);
            $this->line('check tidak-dikenal → ' . $bad->body());
        }

        $this->info('== selesai');
        return self::SUCCESS;
    }

    private function setup(): int
    {
        $ruanganId = (int) $this->option('ruangan');
        if ($ruanganId <= 0) {
            $this->error('--setup butuh --ruangan=<id ruangan yang diikat>');
            return self::FAILURE;
        }

        $id = 'EMU-DOOR-' . $ruanganId;
        $key = Str::random(40);
        MesinIot::updateOrCreate(
            ['id_mesin' => $id],
            [
                'nama_mesin' => 'Emulator Pintu ' . $ruanganId,
                'tipe' => 'absensi',
                'ruangan_id' => $ruanganId,
                'api_key' => Hash::make($key),
                'is_active' => true,
            ]
        );

        $this->info("Perangkat uji {$id} siap (ruangan {$ruanganId}).");
        $this->warn('SIMPAN api_key ini — hanya ditampilkan sekali:');
        $this->line("  {$key}");
        $this->line("Uji: php artisan mesin:emulasi --id={$id} --key={$key} --base=<url>");
        return self::SUCCESS;
    }
}
