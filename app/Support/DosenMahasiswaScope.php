<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DosenMahasiswaScope
{
    /**
     * Nama/kode tim PBL yang dikelola dosen (manpro / co-manpro).
     *
     * @return Collection<int, string>
     */
    public static function timKeysFor(User $dosen): Collection
    {
        if (!$dosen->isDosen()) {
            return collect();
        }

        return PblProject::query()
            ->where('aktif', true)
            ->where(function ($q) use ($dosen) {
                $q->where('manpro_user_id', $dosen->id)
                    ->orWhere('co_manpro_user_id', $dosen->id);
            })
            ->get()
            ->flatMap(fn (PblProject $p) => array_filter([trim($p->nama), trim($p->kode)]))
            ->filter()
            ->unique()
            ->values();
    }

    public static function appliesTo(User $user): bool
    {
        return $user->isDosen();
    }

    /**
     * Filter query mahasiswa ke tim PBL dosen (khusus modul loker).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function applyToMahasiswaQuery(Builder $query, User $dosen): Builder
    {
        $timKeys = self::timKeysFor($dosen);
        if ($timKeys->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('role', 'mahasiswa')->whereIn('tim', $timKeys);
    }

    /**
     * Filter relasi user (log absensi/loker) ke mahasiswa tim PBL dosen.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyToUserRelationQuery(Builder $query, User $dosen, string $relation = 'user', bool $activeOnly = true): Builder
    {
        return $query->whereHas($relation, function ($q) use ($dosen, $activeOnly) {
            if ($activeOnly) {
                $q->where('is_active', true);
            }
            self::applyToMahasiswaQuery($q, $dosen);
        });
    }
}
