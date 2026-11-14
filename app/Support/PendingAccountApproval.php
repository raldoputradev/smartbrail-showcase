<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PendingAccountApproval
{
    /** Akun mahasiswa/dosen: OTP sudah OK, menunggu admin setujui/tolak. */
    public static function query(): Builder
    {
        return User::query()
            ->whereIn('role', ['mahasiswa', 'dosen'])
            ->whereNotNull('email_verified_at')
            ->where(function ($q) {
                $q->where('approval_status', 'pending')->orWhereNull('approval_status');
            })
            ->where(function ($q) {
                $q->whereNull('approval_status')->orWhere('approval_status', '!=', 'rejected');
            });
    }

    public static function count(): int
    {
        return (int) self::query()->count();
    }

    public static function countMahasiswa(): int
    {
        return (int) self::query()->where('role', 'mahasiswa')->count();
    }

    public static function countDosen(): int
    {
        return (int) self::query()->where('role', 'dosen')->count();
    }

    public static function notificationTitle(): string
    {
        $total = self::count();
        if ($total === 0) {
            return '';
        }

        $m = self::countMahasiswa();
        $d = self::countDosen();

        if ($m > 0 && $d > 0) {
            return "{$total} akun menunggu persetujuan ({$m} mahasiswa, {$d} dosen)";
        }

        if ($d > 0) {
            return "{$d} akun dosen menunggu persetujuan admin";
        }

        return "{$m} akun mahasiswa menunggu persetujuan admin";
    }

    public static function notificationBody(): string
    {
        return 'Mahasiswa/dosen sudah verifikasi email (OTP). Menunggu persetujuan admin — buka Manajemen User untuk setujui atau tolak.';
    }

    /** @return Collection<int, User> */
    public static function recent(int $limit = 6): Collection
    {
        return self::query()
            ->orderByDesc('email_verified_at')
            ->limit($limit)
            ->get(['id', 'nama_lengkap', 'email', 'role', 'nitk_nidn', 'email_verified_at', 'created_at', 'approval_status']);
    }
}
