<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Petugas (mis. cleaning service) — identitas akses tanpa akun portal.
 * Kredensial disimpan sebagai hash, bukan PIN/UID mentah.
 */
class RuanganPetugas extends Model
{
    protected $table = 'ruangan_petugas';

    protected $fillable = [
        'nama', 'id_internal', 'tipe_kredensial', 'kredensial_hash',
        'status', 'berlaku_mulai', 'berlaku_selesai', 'jadwal',
    ];

    protected function casts(): array
    {
        return [
            'berlaku_mulai' => 'date',
            'berlaku_selesai' => 'date',
            'jadwal' => 'array',
        ];
    }

    public function dalamMasaBerlaku(\Illuminate\Support\Carbon $tanggal): bool
    {
        if ($this->status !== 'aktif') {
            return false;
        }
        if ($this->berlaku_mulai && $tanggal->lt($this->berlaku_mulai)) {
            return false;
        }
        if ($this->berlaku_selesai && $tanggal->gt($this->berlaku_selesai)) {
            return false;
        }
        return true;
    }
}
