<?php

namespace App\Console\Commands;

use App\Models\MesinIot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterMesinCommand extends Command
{
    protected $signature = 'mesin:register
        {id_mesin : Kode unik mesin (mis. ABSEN-LAB-A1)}
        {--nama= : Nama tampilan mesin}
        {--tipe=absensi : Tipe mesin (absensi|loker)}';

    protected $description = 'Daftarkan mesin IoT baru dan generate API key (sekali tampil).';

    public function handle(): int
    {
        $idMesin = (string) $this->argument('id_mesin');
        $nama = (string) ($this->option('nama') ?? $idMesin);
        $tipe = (string) $this->option('tipe');

        if (!in_array($tipe, ['absensi', 'loker'], true)) {
            $this->error('Tipe mesin harus "absensi" atau "loker".');
            return self::FAILURE;
        }

        if (MesinIot::query()->where('id_mesin', $idMesin)->exists()) {
            $this->error("Mesin dengan id_mesin '{$idMesin}' sudah terdaftar.");
            return self::FAILURE;
        }

        $plainKey = Str::random(40);

        MesinIot::query()->create([
            'id_mesin' => $idMesin,
            'nama_mesin' => $nama,
            'tipe' => $tipe,
            'api_key' => Hash::make($plainKey),
            'is_active' => true,
        ]);

        $this->info('Mesin berhasil didaftarkan.');
        $this->line("id_mesin   : {$idMesin}");
        $this->line("nama_mesin : {$nama}");
        $this->line("tipe       : {$tipe}");
        $this->line('-------------------------------------------');
        $this->warn('API KEY (simpan sekarang, hanya muncul sekali):');
        $this->line($plainKey);
        $this->line('-------------------------------------------');
        $this->line('Gunakan di header request mesin: X-API-KEY: <api_key>');

        return self::SUCCESS;
    }
}
