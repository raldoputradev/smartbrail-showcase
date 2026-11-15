<?php

namespace App\Support;

use App\Models\DosenKelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cakupan operasional dosen berbasis kelas (pivot dosen_kelas).
 * Menggantikan ketergantungan tim/manpro PBL untuk akses rekap/direktori/ekspor.
 * Tanpa penugasan → hasil kosong (deny-by-default), BUKAN semua kelas.
 */
class DosenKelasScope
{
    private const MEMO_KEY = 'dosen_kelas_scope_memo';

    /**
     * @return Collection<int, string>
     */
    public static function kelasKeysFor(User $dosen): Collection
    {
        if (! $dosen->isDosen() || ! Schema::hasTable('dosen_kelas')) {
            return collect();
        }

        // Memo diikat ke request aktif agar tidak bocor antar-request/test.
        $req = request();
        $memo = (array) $req->attributes->get(self::MEMO_KEY, []);
        $id = (int) $dosen->id;
        if (! array_key_exists($id, $memo)) {
            $memo[$id] = DosenKelas::query()
                ->where('user_id', $id)
                ->pluck('kelas')
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $req->attributes->set(self::MEMO_KEY, $memo);
        }

        return collect($memo[$id]);
    }

    public static function hasAssignment(User $dosen): bool
    {
        return self::kelasKeysFor($dosen)->isNotEmpty();
    }

    /**
     * Dosen punya lingkup bimbingan sama sekali (kelas yang ditugaskan
     * ATAU tim PBL yang ia ampu sebagai manpro/co-manpro).
     */
    public static function hasSupervision(User $dosen): bool
    {
        return self::hasAssignment($dosen)
            || \App\Support\DosenMahasiswaScope::timKeysFor($dosen)->isNotEmpty();
    }

    /**
     * Batasi query mahasiswa ke seluruh lingkup bimbingan dosen:
     * mahasiswa di kelas yang ditugaskan UNION mahasiswa di tim PBL-nya.
     * Keduanya kosong → 0=1 (tolak akses, bukan membuka semua).
     *
     * @param  Builder<User>  $query
     */
    public static function applySupervisedMahasiswaQuery(Builder $query, User $dosen): Builder
    {
        $kelasKeys = self::kelasKeysFor($dosen);
        $timKeys = \App\Support\DosenMahasiswaScope::timKeysFor($dosen);

        if ($kelasKeys->isEmpty() && $timKeys->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('role', 'mahasiswa')
            ->where(function ($q) use ($kelasKeys, $timKeys) {
                if ($kelasKeys->isNotEmpty()) {
                    $q->orWhereIn('kelas', $kelasKeys->all());
                }
                if ($timKeys->isNotEmpty()) {
                    $q->orWhereIn('tim', $timKeys->all());
                }
            });
    }

    /**
     * Batasi query mahasiswa ke kelas yang ditugaskan ke dosen.
     * Tanpa penugasan → 0=1 (tolak akses, bukan membuka semua kelas).
     *
     * @param  Builder<User>  $query
     */
    public static function applyToMahasiswaQuery(Builder $query, User $dosen): Builder
    {
        $kelasKeys = self::kelasKeysFor($dosen);
        if ($kelasKeys->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('role', 'mahasiswa')->whereIn('kelas', $kelasKeys->all());
    }

    /** Untuk test dan saat pemetaan disimpan dalam request yang sama. */
    public static function clearMemo(): void
    {
        request()->attributes->remove(self::MEMO_KEY);
    }
}
