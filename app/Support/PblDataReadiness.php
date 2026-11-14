<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\User;
use Illuminate\Support\Collection;

class PblDataReadiness
{
    /**
     * @return array{
     *   ok: bool,
     *   issues: list<array{level: string, title: string, detail: string, count: int}>,
     *   stats: array<string, int>
     * }
     */
    public static function report(): array
    {
        $timKeys = PblProject::query()
            ->where('aktif', true)
            ->get()
            ->flatMap(fn (PblProject $p) => array_filter([trim($p->nama), trim($p->kode)]))
            ->filter()
            ->unique()
            ->values();

        $mhsAktif = User::query()->where('role', 'mahasiswa')->where('is_active', true);
        $totalMhs = (clone $mhsAktif)->count();

        $tanpaTim = (clone $mhsAktif)
            ->where(function ($q) {
                $q->whereNull('tim')->orWhere('tim', '');
            })
            ->count();

        $timTidakDikenal = $timKeys->isEmpty()
            ? $totalMhs
            : (clone $mhsAktif)
                ->whereNotNull('tim')
                ->where('tim', '!=', '')
                ->whereNotIn('tim', $timKeys->all())
                ->count();

        $tanpaShift = (clone $mhsAktif)
            ->where(function ($q) {
                $q->whereNull('shift')->orWhere('shift', '');
            })
            ->count();

        $proyekTanpaManpro = PblProject::query()
            ->where('aktif', true)
            ->where(function ($q) {
                $q->whereNull('manpro')->orWhere('manpro', '');
            })
            ->count();

        $linkedDosenIds = PblProject::query()
            ->where('aktif', true)
            ->get()
            ->flatMap(fn (PblProject $p) => array_filter([$p->manpro_user_id, $p->co_manpro_user_id]))
            ->unique()
            ->values();

        $dosenTanpaPbl = User::query()
            ->where('role', 'dosen')
            ->where('is_active', true)
            ->when($linkedDosenIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $linkedDosenIds->all()))
            ->count();

        $issues = [];

        if ($timKeys->isEmpty()) {
            $issues[] = self::issue('danger', 'Belum ada proyek PBL aktif', 'Import CSV proyek di menu Proyek PBL.', 0);
        }

        if ($tanpaTim > 0) {
            $issues[] = self::issue('warning', 'Mahasiswa aktif tanpa tim', 'Sinkron CSV nim + tim di Proyek PBL.', $tanpaTim);
        }

        if ($timTidakDikenal > 0) {
            $issues[] = self::issue('warning', 'Tim mahasiswa tidak cocok PBL', 'Kolom tim harus sama nama/kode proyek.', $timTidakDikenal);
        }

        if ($tanpaShift > 0) {
            $issues[] = self::issue('warning', 'Mahasiswa tanpa shift', 'Isi shift (pagi/malam) atau sinkron CSV.', $tanpaShift);
        }

        if ($proyekTanpaManpro > 0) {
            $issues[] = self::issue('info', 'Proyek tanpa manpro terhubung', 'Pastikan nama dosen di CSV cocok akun SIMALAS.', $proyekTanpaManpro);
        }

        return [
            'ok' => collect($issues)->where('level', 'danger')->isEmpty()
                && $tanpaTim === 0
                && $timTidakDikenal === 0
                && $timKeys->isNotEmpty(),
            'issues' => $issues,
            'stats' => [
                'mahasiswa_aktif' => $totalMhs,
                'proyek_aktif' => PblProject::where('aktif', true)->count(),
                'tanpa_tim' => $tanpaTim,
                'tim_tidak_cocok' => $timTidakDikenal,
                'tanpa_shift' => $tanpaShift,
                'proyek_tanpa_manpro' => $proyekTanpaManpro,
                'dosen_tanpa_pbl' => $dosenTanpaPbl,
            ],
        ];
    }

    /**
     * @return array{level: string, title: string, detail: string, count: int}
     */
    private static function issue(string $level, string $title, string $detail, int $count): array
    {
        return compact('level', 'title', 'detail', 'count');
    }
}
