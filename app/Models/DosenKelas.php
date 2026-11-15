<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosenKelas extends Model
{
    protected $table = 'dosen_kelas';

    protected $fillable = ['user_id', 'kelas'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
