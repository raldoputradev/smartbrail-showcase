<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiAlfa extends Model
{
    protected $table = 'absensi_alfa';

    protected $fillable = [
        'user_id',
        'tanggal',
        'sesi',
        'kategori',
        'sumber',
        'aktif',
        'catatan',
        'dibatalkan_oleh',
        'dibatalkan_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'aktif' => 'boolean',
            'dibatalkan_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dibatalkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(AbsensiKomentar::class, 'target_id')
            ->where('target_type', 'alfa');
    }
}
