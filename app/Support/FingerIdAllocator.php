<?php

namespace App\Support;

use App\Models\User;

class FingerIdAllocator
{
    public const SLOT_MAX = 150;

    public static function assignNext(): int
    {
        $taken = User::query()
            ->whereNotNull('finger_id')
            ->pluck('finger_id')
            ->all();

        $takenSet = array_flip(array_map('intval', $taken));
        for ($i = 1; $i <= self::SLOT_MAX; $i++) {
            if (!isset($takenSet[$i])) {
                return $i;
            }
        }

        throw new \RuntimeException('Kapasitas finger_id penuh (' . self::SLOT_MAX . ' slot).');
    }

  /**
     * Isi finger_id satu mahasiswa pada aksi tulis (buat, impor, OTP, approval, atau assign).
     * Bukan untuk GET halaman atau polling mesin.
     */
    public static function ensureForMahasiswa(User $user): User
    {
        if ($user->role !== 'mahasiswa' || $user->finger_id) {
            return $user;
        }

        $user->finger_id = self::assignNext();
        MahasiswaShiftResolver::ensure($user, null, true);
        $user->save();

        return $user->refresh();
    }

    public static function assignMissingForActiveMahasiswa(): int
    {
        $count = 0;
        User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->whereNull('finger_id')
            ->orderBy('id')
            ->each(function (User $user) use (&$count) {
                self::ensureForMahasiswa($user);
                $count++;
            });

        return $count;
    }
}
