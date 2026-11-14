<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAbsensi extends Model
{
    use HasFactory;

    protected $table = 'log_absensi';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'id_mesin',
        'sesi',
        'jenis',
        'menit_terlambat',
        'slot_lab',
        'pulang_lembur',
        'waktu_tap',
        'event_id',
        'diterima_pada',
    ];

    protected function casts(): array
    {
        return [
            'waktu_tap' => 'datetime',
            'diterima_pada' => 'datetime',
            'menit_terlambat' => 'integer',
            'slot_lab' => 'integer',
            'pulang_lembur' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
