<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit aksi PBL — append-only. Tidak ada endpoint update/delete;
 * baris menjadi bukti transparan siapa melakukan apa dan kapan.
 */
class PblActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pbl_project_id',
        'project_snapshot',
        'actor_id',
        'actor_nama',
        'actor_role',
        'aksi',
        'ringkasan',
        'sebelum',
        'sesudah',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PblProject::class, 'pbl_project_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
