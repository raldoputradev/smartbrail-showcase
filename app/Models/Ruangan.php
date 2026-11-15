<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    protected $table = 'ruangan';

    protected $fillable = ['kode', 'nama', 'lokasi', 'kapasitas', 'fasilitas', 'foto', 'aktif', 'aturan'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'aturan' => 'array', 'fasilitas' => 'array', 'kapasitas' => 'integer'];
    }

    /**
     * Aturan pemesanan efektif — semua nilai bisa diubah Laboran per ruangan.
     * hari: 1=Senin .. 7=Minggu (ISO-8601). Kosong = semua hari.
     */
    public function aturan(): array
    {
        return array_merge([
            'hari' => [],
            'jam_buka' => '07:00',
            'jam_tutup' => '21:00',
            'min_advance_days' => 1,
            'max_advance_days' => 2,
            'max_duration_minutes' => 120,
            'max_pending_per_team' => 1,
            // Batas peserta efektif: override via aturan, fallback ke kolom kapasitas.
            'max_peserta' => $this->kapasitas ?: null,
        ], $this->aturan ?? []);
    }

    public function pengajuan(): HasMany
    {
        return $this->hasMany(RuanganPengajuan::class, 'ruangan_id');
    }

    public function izin(): HasMany
    {
        return $this->hasMany(RuanganIzin::class, 'ruangan_id');
    }

    public function accessEvents(): HasMany
    {
        return $this->hasMany(RuanganAccessEvent::class, 'ruangan_id');
    }
}
