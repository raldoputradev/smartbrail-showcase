<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TapReceipt extends Model
{
    protected $table = 'tap_receipts';

    protected $fillable = [
        'event_id',
        'id_mesin',
        'finger_id',
        'waktu_tap',
        'payload_hash',
        'success',
        'terminal',
        'code',
        'http_status',
        'message',
        'response_data',
        'log_absensi_id',
        'diterima_pada',
    ];

    protected function casts(): array
    {
        return [
            'finger_id' => 'integer',
            'waktu_tap' => 'datetime',
            'success' => 'boolean',
            'terminal' => 'boolean',
            'http_status' => 'integer',
            'response_data' => 'array',
            'diterima_pada' => 'datetime',
        ];
    }

    public function logAbsensi(): BelongsTo
    {
        return $this->belongsTo(LogAbsensi::class, 'log_absensi_id');
    }
}
