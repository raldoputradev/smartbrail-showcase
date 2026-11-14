<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PendingEmailVerification
{
    /** Akun mahasiswa/dosen: belum verifikasi OTP, menunggu user selesaikan email. */
    public static function query(): Builder
    {
        return User::query()
            ->whereIn('role', ['mahasiswa', 'dosen'])
            ->whereNotNull('email')
            ->whereNull('email_verified_at')
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
            return "{$total} akun menunggu verifikasi OTP ({$m} mahasiswa, {$d} dosen)";
        }

        if ($d > 0) {
            return "{$d} akun dosen menunggu verifikasi OTP";
        }

        return "{$m} akun mahasiswa menunggu verifikasi OTP";
    }

    public static function notificationBody(): string
    {
        return 'Pengguna sudah mendaftar tetapi belum menyelesaikan verifikasi email (OTP). Tidak perlu disetujui admin sampai OTP selesai.';
    }

    /** @return Collection<int, User> */
    public static function recent(int $limit = 6): Collection
    {
        return self::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'nama_lengkap', 'email', 'role', 'nitk_nidn', 'created_at', 'approval_status']);
    }
}
