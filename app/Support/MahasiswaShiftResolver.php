<?php

namespace App\Support;

use App\Models\AbsensiSesiSetting;
use App\Models\PblProject;
use App\Models\User;
use Carbon\Carbon;

class MahasiswaShiftResolver
{
    /**
     * Pastikan mahasiswa punya shift pagi/malam; simpan ke DB jika bisa diturunkan.
     */
    public static function ensure(User $user, ?Carbon $at = null, bool $persist = true): ?string
    {
        if ($user->role !== 'mahasiswa') {
            return null;
        }

        if (in_array($user->shift, ['pagi', 'malam'], true)) {
            return $user->shift;
        }

        $shift = self::resolve($user, $at);
        if ($shift && $persist) {
            $user->forceFill(['shift' => $shift])->save();
        }

        return $shift;
    }

    /**
     * Turunkan shift dari data profil, proyek PBL, atau jendela waktu absensi aktif.
     */
    public static function resolve(User $user, ?Carbon $at = null): ?string
    {
        if (in_array($user->shift, ['pagi', 'malam'], true)) {
            return $user->shift;
        }

        $candidates = array_values(array_filter([
            self::inferFromKelas($user->kelas),
            self::inferFromTim($user->tim),
            self::inferFromTime($at),
        ]));

        if ($candidates !== []) {
            return $candidates[0];
        }

        // Satu sesi aktif di sistem → pakai itu
        $active = AbsensiSesiSetting::query()->where('aktif', true)->pluck('shift')->unique()->values();
        if ($active->count() === 1) {
            return $active->first();
        }

        return 'pagi';
    }

    public static function inferFromKelas(?string $kelas): ?string
    {
        if ($kelas === null || trim($kelas) === '') {
            return null;
        }

        $upper = strtoupper(trim($kelas));
        if (str_contains($upper, 'MALAM') || str_contains($upper, 'NIGHT')) {
            return 'malam';
        }
        if (str_contains($upper, 'PAGI') || str_contains($upper, 'MORNING')) {
            return 'pagi';
        }

        return null;
    }

    public static function inferFromTim(?string $tim): ?string
    {
        if ($tim === null || trim($tim) === '') {
            return null;
        }

        $tim = trim($tim);
        $project = PblProject::query()
            ->where('aktif', true)
            ->where(function ($q) use ($tim) {
                $q->where('nama', $tim)->orWhere('kode', $tim);
            })
            ->first();

        $shift = $project?->shift_sesi;

        return in_array($shift, ['pagi', 'malam'], true) ? $shift : null;
    }

    /**
     * Shift yang sedang dalam jendela tap (masuk atau pulang) menurut pengaturan sesi.
     */
    public static function inferFromTime(?Carbon $at = null): ?string
    {
        $now = $at ?? now();
        $matches = [];

        foreach (AbsensiSesiSetting::query()->where('aktif', true)->get() as $setting) {
            $localized = $setting->timezoneCarbon($now);
            $jamMasuk = $setting->jamMasukOn($localized);
            $jamPulang = $setting->jamPulangOn($localized);
            $start = $jamMasuk->copy()->subMinutes((int) $setting->toleransi_masuk_menit);
            $end = $jamPulang->copy()->addMinutes((int) $setting->grace_alfa_menit);

            if ($localized->between($start, $end)) {
                $matches[] = $setting->shift;
            }
        }

        $matches = array_values(array_unique($matches));

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Isi shift untuk semua mahasiswa aktif yang belum punya shift.
     */
    public static function backfillMissing(): int
    {
        $count = 0;
        User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('shift')
                    ->orWhereNotIn('shift', ['pagi', 'malam']);
            })
            ->orderBy('id')
            ->each(function (User $user) use (&$count) {
                if (self::ensure($user, null, true)) {
                    $count++;
                }
            });

        return $count;
    }
}
