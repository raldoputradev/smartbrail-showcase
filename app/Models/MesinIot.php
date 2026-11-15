<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MesinIot extends Model
{
    use HasFactory;

    protected $table = 'mesin_iot';

    /**
     * Ambang waktu (menit) untuk menganggap mesin masih "online" sejak last_seen_at.
     * Bisa di-override via config jika nanti perlu.
     */
    public const ONLINE_THRESHOLD_MINUTES = 5;

    protected $fillable = [
        'id_mesin',
        'nama_mesin',
        'tipe',
        'ruangan_id',
        'api_key',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }












    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * True jika mesin tercatat aktif DAN baru-baru ini berkomunikasi
     * dengan server (heartbeat dalam ambang waktu).
     */
    public function isOnline(): bool
    {
        if (!$this->is_active || $this->last_seen_at === null) {
            return false;
        }

        return $this->last_seen_at->gte(now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES));
    }

    /**
     * Catat heartbeat dari mesin (dipanggil tiap kali tap masuk dari ESP32).
     */
    public function touchHeartbeat(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    /**
     * Scope: mesin yang dianggap online sekarang.
     */
    public function scopeOnline($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES));
    }
}
