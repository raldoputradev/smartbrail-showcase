<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Buka/tutup pendaftaran mandiri (dosen via web, mahasiswa via APK).
 * Tidak memengaruhi login akun aktif maupun aktivasi akun hasil import.
 */
class RegistrationGate
{
    public const KEY = 'registrasi_mandiri';

    public const ROLES = ['dosen', 'mahasiswa'];

    /** @return array{dosen: bool, mahasiswa: bool} */
    public static function flags(): array
    {
        $stored = [];
        if (Schema::hasTable('system_settings')) {
            $value = SystemSetting::valueFor(self::KEY, []);
            $stored = is_array($value) ? $value : [];
        }

        $flags = [];
        foreach (self::ROLES as $role) {
            $flags[$role] = array_key_exists($role, $stored) ? (bool) $stored[$role] : true;
        }

        return $flags;
    }

    public static function isOpen(string $role): bool
    {
        return self::flags()[$role] ?? true;
    }

    /** @param  array{dosen?: bool, mahasiswa?: bool}  $flags */
    public static function save(array $flags): void
    {
        $current = self::flags();
        foreach (self::ROLES as $role) {
            if (array_key_exists($role, $flags)) {
                $current[$role] = (bool) $flags[$role];
            }
        }
        SystemSetting::setValue(self::KEY, $current);
    }

    public static function closedMessage(string $role): string
    {
        $who = $role === 'dosen' ? 'dosen' : 'mahasiswa';

        return "Pendaftaran akun {$who} sedang ditutup. Akun dibuat oleh admin laboran; "
            .'jika sudah menerima kode aktivasi, buka halaman Aktivasi Akun. Akun yang sudah aktif tetap bisa login.';
    }
}
