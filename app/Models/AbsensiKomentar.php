<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiKomentar extends Model
{
    protected $table = 'absensi_komentar';

    protected $fillable = [
        'user_id',
        'target_type',
        'target_id',
        'isi',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
