<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\ProgramStudi;
use App\Models\User;

/**
 * Opsi form profil mahasiswa (mobile & admin) — satu sumber kebenaran.
 */
class MahasiswaProfileOptions
{
    /** @return list<string> */
    public static function kelasOptions(): array
    {
        return [
            'Robotika A',
            'Robotika B',
            'Robotika C',
            'Mekatronika A',
            'Mekatronika B',
            'Mekatronika C',
            'Rekayasa Elektro A',
            'Rekayasa Elektro B',
            'Rekayasa Elektro C',
        ];
    }

    /**
     * Kelas yang sesuai program studi (untuk form registrasi mobile).
     *
     * @return list<string>
     */
    public static function kelasForProgramStudi(?int $programStudiId): array
    {
        if ($programStudiId === null) {
            return self::kelasOptions();
        }

        $prodi = ProgramStudi::query()->where('aktif', true)->find($programStudiId);
        if (!$prodi) {
            return self::kelasOptions();
        }

        $kode = strtolower((string) $prodi->kode);
        $nama = strtolower((string) $prodi->nama);

        return array_values(array_filter(self::kelasOptions(), function (string $kelas) use ($kode, $nama) {
            $k = strtolower($kelas);

            if (str_contains($kode, 'mekatronika') || str_contains($nama, 'mekatronika')) {
                return str_contains($k, 'mekatronika');
            }

            if ($kode === 'tre' || str_contains($nama, 'rekayasa') || str_contains($nama, 'elektro')) {
                return str_contains($k, 'rekayasa') || str_contains($k, 'elektro') || str_contains($k, 'tre');
            }

            if (str_contains($kode, 'robotika') || str_contains($nama, 'robotika')) {
                return str_contains($k, 'robotika');
            }

            return true;
        }));
    }

    /** @return list<array{id:int,kode:string,nama:string}> */
    public static function programStudiList(): array
    {
        return ProgramStudi::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama'])
            ->map(fn (ProgramStudi $p) => [
                'id' => (int) $p->id,
                'kode' => (string) $p->kode,
                'nama' => (string) $p->nama,
            ])
            ->values()
            ->all();
    }

    public static function inferProgramStudiId(?string $kelas): ?int
    {
        $k = strtolower(trim((string) $kelas));

        $code = match (true) {
            str_contains($k, 'mekatronika') => 'mekatronika',
            str_contains($k, 'rekayasa') || str_contains($k, 'tre') || str_contains($k, 'elektro') => 'tre',
            str_contains($k, 'robotika') => 'robotika',
            default => null,
        };

        if ($code === null) {
            return null;
        }

        return ProgramStudi::query()
            ->where('kode', $code)
            ->where('aktif', true)
            ->value('id');
    }

    public static function syncKelasMetadata(string $kelas): array
    {
        $updates = [];
        if (preg_match('/\b([abc])\b/i', $kelas, $m)) {
            $updates['kelas_paralel'] = strtoupper($m[1]);
        }
        $updates['shift'] = str_contains(strtolower($kelas), 'malam') ? 'malam' : 'pagi';

        return $updates;
    }

    /** Kode singkat tampilan mobile: PBL-RE-044 → RE-044. */
    public static function shortPblCode(string $kode): string
    {
        $k = strtoupper(trim($kode));
        if (str_starts_with($k, 'PBL-')) {
            return substr($k, 4);
        }

        return $k;
    }

    /**
     * Daftar proyek PBL untuk picker mobile (kode + nama).
     *
     * @return list<array{kode:string,short_kode:string,nama:string,label:string}>
     */
    public static function pblProjectOptions(): array
    {
        $fromProjects = PblProject::query()
            ->where('aktif', true)
            ->orderBy('kode')
            ->get(['kode', 'nama']);

        if ($fromProjects->isNotEmpty()) {
            return $fromProjects
                ->map(function (PblProject $p) {
                    $kode = trim((string) $p->kode);
                    $short = self::shortPblCode($kode);
                    $nama = trim((string) $p->nama);
                    $label = $short !== '' ? "{$short} · {$nama}" : $nama;

                    return [
                        'kode' => $kode,
                        'short_kode' => $short,
                        'nama' => $nama,
                        'label' => $label,
                    ];
                })
                ->filter(fn (array $row) => $row['nama'] !== '')
                ->values()
                ->all();
        }

        return collect(self::pblTeamOptions())
            ->map(fn (string $nama) => [
                'kode' => '',
                'short_kode' => '',
                'nama' => $nama,
                'label' => $nama,
            ])
            ->values()
            ->all();
    }

    /**
     * Tahun angkatan untuk filter/form — gabungan data DB + rentang dinamis.
     *
     * @return list<int>
     */
    public static function angkatanYears(): array
    {
        $fromDb = User::query()
            ->where('role', 'mahasiswa')
            ->whereNotNull('angkatan')
            ->distinct()
            ->orderByDesc('angkatan')
            ->pluck('angkatan')
            ->map(fn ($y) => (int) $y)
            ->all();

        $current = (int) now()->year;
        $range = range($current + 1, max(2020, $current - 8));

        $years = array_values(array_unique(array_merge($fromDb, $range)));
        rsort($years, SORT_NUMERIC);

        return $years;
    }

    /** @return list<string> Nama tim PBL aktif (sama sumber dengan admin / penempatan PBL). */
    public static function pblTeamOptions(): array
    {
        $fromProjects = PblProject::query()
            ->where('aktif', true)
            ->orderBy('kode')
            ->pluck('nama')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($fromProjects !== []) {
            return $fromProjects;
        }

        $fromUsers = User::query()
            ->where('role', 'mahasiswa')
            ->whereNotNull('tim')
            ->where('tim', '!=', '')
            ->distinct()
            ->orderBy('tim')
            ->pluck('tim')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();

        if ($fromUsers !== []) {
            return $fromUsers;
        }

        return [
            'AIOT',
            'Robotika',
            'Vision',
            'Embedded',
            'Mekatronika',
            'IoT Lab',
            'Tim 1',
            'Tim 2',
        ];
    }
}
