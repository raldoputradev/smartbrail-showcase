<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('program_studi')) {
            return;
        }

        $map = [
            'robotika' => 'Teknik Robotika',
            'mekatronika' => 'Teknik Mekatronika',
            'tre' => 'Teknik Rekayasa Elektro',
        ];

        foreach ($map as $kode => $nama) {
            DB::table('program_studi')
                ->where('kode', $kode)
                ->update(['nama' => $nama, 'updated_at' => now()]);
        }

        // Mahasiswa dengan kelas Robotika/Mekatronika tapi prodi kosong → isi otomatis.
        if (!\Illuminate\Support\Facades\Schema::hasTable('users')) {
            return;
        }

        $prodiIds = DB::table('program_studi')->pluck('id', 'kode');

        DB::table('users')
            ->where('role', 'mahasiswa')
            ->whereNull('program_studi_id')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($prodiIds) {
                foreach ($users as $row) {
                    $kelas = strtolower((string) ($row->kelas ?? ''));
                    $code = match (true) {
                        str_contains($kelas, 'mekatronika') => 'mekatronika',
                        str_contains($kelas, 'rekayasa') || str_contains($kelas, 'tre') || str_contains($kelas, 'elektro') => 'tre',
                        str_contains($kelas, 'robotika') => 'robotika',
                        default => null,
                    };
                    if ($code === null || !isset($prodiIds[$code])) {
                        continue;
                    }
                    DB::table('users')->where('id', $row->id)->update([
                        'program_studi_id' => $prodiIds[$code],
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Tidak di-rollback agar data mahasiswa tetap terisi.
    }
};
