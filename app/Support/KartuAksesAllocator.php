<?php

namespace App\Support;

use App\Models\User;

/**
 * Alokasi nomor kartu akses (rfid_uid) virtual per akun — pola yang sama dengan
 * FingerIdAllocator: nomor diberikan otomatis saat akun dibuat sebagai identitas
 * akses ruangan; saat kartu fisik diterbitkan, admin mengganti rfid_uid dengan
 * UID kartu asli lewat form edit pengguna.
 *
 * Format "SBK0001" sengaja bukan hex murni sehingga tidak akan pernah cocok
 * dengan UID kartu RFID fisik (hex) pada tap mesin.
 */
class KartuAksesAllocator
{
    public const PREFIX = 'SBK';
    public const SLOT_MAX = 99999;

    /** Prefix seri kartu per role — staf beda seri dari mahasiswa. */
    public const PREFIXES = [
        'mahasiswa' => 'SBM',
        'dosen' => 'SBD',
        'laboran' => 'SBL',
        'admin' => 'SBA',
    ];

    public static function prefixFor(string $role): string
    {
        return self::PREFIXES[$role] ?? self::PREFIX;
    }

    public static function assignNext(?string $prefix = null): string
    {
        $prefix ??= self::PREFIX;
        $taken = User::query()
            ->whereNotNull('rfid_uid')
            ->where('rfid_uid', 'like', $prefix.'%')
            ->pluck('rfid_uid')
            ->map(fn ($v) => (int) substr((string) $v, strlen($prefix)))
            ->all();

        $takenSet = array_flip($taken);
        for ($i = 1; $i <= self::SLOT_MAX; $i++) {
            if (! isset($takenSet[$i])) {
                return $prefix.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            }
        }

        throw new \RuntimeException('Kapasitas nomor kartu akses penuh ('.self::SLOT_MAX.' slot).');
    }

    /** Isi rfid_uid akun pada aksi tulis (buat manual, import, approve) bila belum ada. */
    public static function ensure(User $user): User
    {
        if ($user->rfid_uid) {
            return $user;
        }

        $user->rfid_uid = self::assignNext(self::prefixFor((string) $user->role));
        $user->save();

        return $user->refresh();
    }
}
