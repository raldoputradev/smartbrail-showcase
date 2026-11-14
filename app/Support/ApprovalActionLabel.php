<?php

namespace App\Support;

class ApprovalActionLabel
{
    public static function label(string $action): string
    {
        return match ($action) {
            'registered' => 'Registrasi',
            'admin_created' => 'Dibuat admin',
            'email_verified' => 'Email diverifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'deactivated' => 'Dinonaktifkan',
            'activated' => 'Diaktifkan',
            'updated_pending' => 'Diubah (pending)',
            'deleted' => 'Dihapus permanen',
            'activation_code_issued' => 'Kode aktivasi dibuat',
            'activation_password_set' => 'Password dibuat pemilik',
            'activation_profile_set' => 'Email & HP diisi pemilik',
            'activation_completed' => 'Aktivasi selesai',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    public static function tone(string $action): string
    {
        return match ($action) {
            'approved', 'activated', 'activation_completed' => 'success',
            'rejected', 'deleted' => 'danger',
            'deactivated' => 'warning',
            'email_verified', 'activation_code_issued', 'activation_password_set', 'activation_profile_set' => 'info',
            default => 'warning',
        };
    }
}