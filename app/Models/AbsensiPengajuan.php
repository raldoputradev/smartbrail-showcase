<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiPengajuan extends Model
{
    protected $table = 'absensi_pengajuan';

    protected $fillable = [
        'user_id',
        'tanggal_mulai',
        'tanggal_akhir',
        'jenis',
        'alasan',
        'bukti_path',
        'status',
        'catatan_reviewer',
        'ditinjau_oleh',
        'ditinjau_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
            'ditinjau_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ditinjauOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }

    public function hasBukti(): bool
    {
        return !empty($this->bukti_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->bukti_path);
    }

    public function buktiUrl(): ?string
    {
        if (!$this->hasBukti()) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->bukti_path);
    }
}
