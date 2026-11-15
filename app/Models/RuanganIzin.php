<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuanganIzin extends Model
{
    protected $table = 'ruangan_izin';

    public const STATUS_AKTIF = 'aktif';
    public const STATUS_DICABUT = 'dicabut';
    public const STATUS_KEDALUWARSA = 'kedaluwarsa';

    protected $fillable = [
        'pengajuan_id', 'user_id', 'ruangan_id', 'mulai', 'selesai', 'status',
        'akses_pin_enc',
    ];

    protected function casts(): array
    {
        return ['mulai' => 'datetime', 'selesai' => 'datetime'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(RuanganPengajuan::class, 'pengajuan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /** PIN akses per-izin (terenkripsi) — dibaca untuk ditampilkan ke pemilik izin. */
    public function aksesPin(): ?string
    {
        if (empty($this->akses_pin_enc)) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($this->akses_pin_enc);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Izin berlaku pada saat tertentu (tidak otomatis berlaku saat offline/device down). */
    public function scopeBerlakuPada($query, $waktu)
    {
        return $query->where('status', self::STATUS_AKTIF)
            ->where('mulai', '<=', $waktu)
            ->where('selesai', '>=', $waktu);
    }
}
