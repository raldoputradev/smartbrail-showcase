<?php

namespace App\Support;

use App\Models\AdminActivityLog;
use Illuminate\Support\Facades\Auth;

/**
 * Jejak audit aksi admin/laboran — siapa melakukan apa, kapan.
 * Dipanggil eksplisit di titik aksi penting; kegagalan pencatatan
 * tidak boleh menggagalkan aksi utama.
 */
class AdminAudit
{
    /** Label aksi untuk tampilan + filter. */
    public const AKSI = [
        'ruangan.buat'       => 'Membuat ruangan',
        'ruangan.ubah'       => 'Mengubah ruangan',
        'perangkat.binding'  => 'Binding perangkat ke ruangan',
        'petugas.buat'       => 'Menambah petugas ruangan',
        'petugas.ubah'       => 'Mengubah petugas ruangan',
        'pengajuan.setujui'  => 'Menyetujui pengajuan ruangan',
        'pengajuan.tolak'    => 'Menolak pengajuan ruangan',
        'pengajuan.pengganti'=> 'Menawarkan jadwal pengganti',
        'pengajuan.cabut'    => 'Mencabut pengajuan',
        'pengguna.buat'      => 'Membuat akun pengguna',
        'pengguna.ubah'      => 'Mengubah data pengguna',
        'mesin.buat'         => 'Menambah mesin/perangkat',
        'mesin.ubah'         => 'Mengubah mesin/perangkat',
        'mesin.toggle'       => 'Mengaktifkan/menonaktifkan mesin',
        'mesin.regenerate'   => 'Generate ulang API key mesin',
        'mesin.hapus'        => 'Menghapus mesin',
        'pengumuman.buat'    => 'Membuat pengumuman',
        'pengumuman.ubah'    => 'Mengubah pengumuman',
        'pengumuman.toggle'  => 'Mengaktifkan/menonaktifkan pengumuman',
        'pengumuman.hapus'   => 'Menghapus pengumuman',
        'absensi.kebijakan'  => 'Mengubah kebijakan absensi',
        'absensi.sesi'       => 'Mengubah jadwal sesi absensi',
        'absensi.libur'      => 'Menambah hari libur/agenda',
        'akademik.periode'   => 'Mengelola periode akademik',
        'akademik.aktivasi'  => 'Mengaktifkan periode akademik',
        'akademik.kenaikan'  => 'Menerapkan kenaikan semester',
    ];

    public static function catat(string $aksi, ?string $target = null, ?string $detail = null): void
    {
        try {
            $u = Auth::user();
            AdminActivityLog::create([
                'actor_id'   => $u?->id,
                'actor_nama' => $u?->nama_lengkap ?? $u?->username ?? 'sistem',
                'actor_role' => $u?->role ?? 'sistem',
                'aksi'       => $aksi,
                'target'     => $target ? mb_substr($target, 0, 160) : null,
                'detail'     => $detail ? mb_substr($detail, 0, 2000) : null,
                'ip'         => request()?->ip(),
            ]);
        } catch (\Throwable) {
            // Audit tidak pernah menggagalkan aksi utama.
        }
    }

    public static function label(string $aksi): string
    {
        return self::AKSI[$aksi] ?? $aksi;
    }
}
