<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiHarian extends Model
{
    protected $table = 'absensi_harian';

    protected $fillable = [
        'user_id',
        'tanggal',
        'sesi',
        'status',
        'masuk_log_id',
        'pulang_log_id',
        'menit_terlambat_masuk',
        'menit_terlambat_pulang',
        'slot_lab_masuk',
        'pulang_lembur',
        'lengkap_at',
        'catatan_koreksi',
        'koreksi_oleh',
        'koreksi_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'menit_terlambat_masuk' => 'integer',
            'menit_terlambat_pulang' => 'integer',
            'slot_lab_masuk' => 'integer',
            'pulang_lembur' => 'boolean',
            'lengkap_at' => 'datetime',
            'koreksi_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function masukLog(): BelongsTo
    {
        return $this->belongsTo(LogAbsensi::class, 'masuk_log_id');
    }

    public function pulangLog(): BelongsTo
    {
        return $this->belongsTo(LogAbsensi::class, 'pulang_log_id');
    }

    public function koreksiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koreksi_oleh');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(AbsensiKomentar::class, 'target_id')
            ->where('target_type', 'harian');
    }
}
