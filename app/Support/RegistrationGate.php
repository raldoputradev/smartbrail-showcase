<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Buka/tutup pendaftaran mandiri (mahasiswa via APK; dosen via web dinonaktifkan).
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

        return [
            'dosen' => false,
            'mahasiswa' => array_key_exists('mahasiswa', $stored) ? (bool) $stored['mahasiswa'] : true,
        ];
    }

    public static function isOpen(string $role): bool
    {
        // Registrasi publik ditutup permanen — akun dibuat admin lewat import.
        return false;
    }

    /** @param  array{dosen?: bool, mahasiswa?: bool}  $flags */
    public static function save(array $flags): void
    {
        $current = self::flags();
        $current['dosen'] = false;
        if (array_key_exists('mahasiswa', $flags)) {
            $current['mahasiswa'] = (bool) $flags['mahasiswa'];
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
