<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot telemetri ruangan terakhir (satu baris per ruangan, upsert by ruangan_id).
 *
 * metrics — objek bebas dari edge; kunci yang dikenali UI:
 *   apd_ok, apd_total                 — kepatuhan APD hasil inferensi vision
 *   listrik_watt                      — daya listrik ruangan saat ini (W)
 *   suhu_c, kelembapan_pct            — lingkungan ruangan
 *   printer_state                     — idle|printing|paused|error
 *   printer_nozzle_c, printer_bed_c, printer_progress_pct, printer_eta_s
 *   solder_active (bool), solder_temp_c
 * alerts — daftar peringatan aktif dari edge, mis. ["apd_tidak_dipakai","suhu_tinggi"].
 */
class RuanganTelemetri extends Model
{
    protected $table = 'ruangan_telemetri';

    /** Data dianggap kedaluwarsa bila lebih tua dari ini. */
    public const STALE_MINUTES = 10;

    protected $fillable = ['ruangan_id', 'id_mesin', 'metrics', 'alerts', 'recorded_at'];

    protected $casts = [
        'metrics' => 'array',
        'alerts' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function isStale(): bool
    {
        return ! $this->recorded_at || $this->recorded_at->lt(now()->subMinutes(self::STALE_MINUTES));
    }
}
