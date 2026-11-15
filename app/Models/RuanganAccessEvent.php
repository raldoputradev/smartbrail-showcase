<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kejadian akses ruangan — terpisah dari tap absensi.
 * 'check' = izin diverifikasi, 'buka' = perangkat melapor pintu dibuka,
 * bukti pintu benar-benar terbuka adalah jenis event tersendiri.
 */
class RuanganAccessEvent extends Model
{
    protected $table = 'ruangan_access_events';

    protected $fillable = [
        'mesin_iot_id', 'ruangan_id', 'user_id', 'petugas_id', 'izin_id',
        'jenis', 'hasil', 'meta', 'terjadi_pada',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'terjadi_pada' => 'datetime'];
    }

    public function mesin(): BelongsTo
    {
        return $this->belongsTo(MesinIot::class, 'mesin_iot_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
