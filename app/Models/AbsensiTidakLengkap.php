<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiTidakLengkap extends Model
{
    protected $table = 'absensi_tidak_lengkap';

    protected $fillable = [
        'user_id',
        'tanggal',
        'sesi',
        'jenis',
        'selesai',
        'catatan_koreksi',
        'ditangani_oleh',
        'ditangani_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'selesai' => 'boolean',
            'ditangani_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ditanganiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(AbsensiKomentar::class, 'target_id')
            ->where('target_type', 'tidak_lengkap');
    }
}
