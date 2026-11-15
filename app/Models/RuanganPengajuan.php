<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RuanganPengajuan extends Model
{
    protected $table = 'ruangan_pengajuan';

    protected $appends = ['status_efektif', 'alasan_status'];

    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_MENUNGGU_PENERIMAAN = 'menunggu_penerimaan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_DIBATALKAN = 'dibatalkan';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_KEDALUWARSA = 'kedaluwarsa';

    public const STATUS_AKTIF_MENUNGGU = [self::STATUS_DIAJUKAN, self::STATUS_MENUNGGU_PENERIMAAN];

    /** Status yang menempel pada slot: menunggu keputusan + sudah disetujui. */
    public const STATUS_MEMBLOKIR_SLOT = [
        self::STATUS_DIAJUKAN,
        self::STATUS_MENUNGGU_PENERIMAAN,
        self::STATUS_DISETUJUI,
    ];

    protected $fillable = [
        'ruangan_id', 'pengaju_id', 'pbl_project_id', 'pembimbing_id',
        'tujuan', 'tanggal', 'jam_mulai', 'jam_selesai', 'peserta', 'snapshot',
        'status', 'pengganti_mulai', 'pengganti_selesai', 'pengganti_catatan',
        'diputus_oleh', 'diputus_pada', 'alasan_keputusan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'peserta' => 'array',
            'snapshot' => 'array',
            'pengganti_mulai' => 'datetime',
            'pengganti_selesai' => 'datetime',
            'diputus_pada' => 'datetime',
        ];
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengaju_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PblProject::class, 'pbl_project_id');
    }

    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_id');
    }

    /** Akun laboran/admin yang memutuskan — jejak audit, tidak boleh dihapus. */
    public function diputusOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputus_oleh');
    }

    public function izin(): HasMany
    {
        return $this->hasMany(RuanganIzin::class, 'pengajuan_id');
    }

    /** Rentang waktu efektif (waktu pengganti menggantikan setelah diterima). */
    public function mulaiAt(): Carbon
    {
        return Carbon::parse($this->tanggal->toDateString().' '.$this->jam_mulai);
    }

    public function selesaiAt(): Carbon
    {
        return Carbon::parse($this->tanggal->toDateString().' '.$this->jam_selesai);
    }

    /**
     * Status bermakna waktu: pengajuan lewat waktu tidak membuktikan ruangan dipakai.
     * disetujui+lewat => selesai HANYA bila ada bukti kedatangan dari mesin ruangan;
     * disetujui+lewat tanpa tap => kedaluwarsa (tidak datang). menunggu/diajukan+lewat => kedaluwarsa.
     */
    public function getStatusEfektifAttribute(): string
    {
        return $this->statusEfektif();
    }

    public function statusEfektif(): string
    {
        if (in_array($this->status, [self::STATUS_DITOLAK, self::STATUS_DIBATALKAN, self::STATUS_SELESAI, self::STATUS_KEDALUWARSA], true)) {
            return $this->status;
        }
        if ($this->selesaiAt()->isPast()) {
            if ($this->status === self::STATUS_DISETUJUI) {
                return $this->adaKedatanganRuangan() ? self::STATUS_SELESAI : self::STATUS_KEDALUWARSA;
            }
            return self::STATUS_KEDALUWARSA;
        }
        return $this->status;
    }

    /**
     * Bukti kehadiran di ruangan: event akses mesin ('check' diizinkan atau 'buka')
     * yang terikat izin milik pengajuan ini dalam jendela booking (±30 menit sebelum mulai).
     */
    public function adaKedatanganRuangan(): bool
    {
        if ($this->memoKedatangan !== null) {
            return $this->memoKedatangan;
        }

        $izinIds = $this->izin()->pluck('id');
        if ($izinIds->isEmpty()) {
            return $this->memoKedatangan = false;
        }

        return $this->memoKedatangan = RuanganAccessEvent::query()
            ->whereIn('izin_id', $izinIds)
            ->where(function ($q) {
                $q->where('hasil', 'diizinkan')->orWhere('jenis', 'buka');
            })
            ->whereBetween('terjadi_pada', [
                $this->mulaiAt()->copy()->subMinutes(30),
                $this->selesaiAt()->copy()->addMinutes(15),
            ])
            ->exists();
    }

    /**
     * Penjelasan mengapa pengajuan berakhir pada statusnya — diturunkan dari
     * kolom status tersimpan (status efektif 'kedaluwarsa' tidak disimpan,
     * jadi penyebabnya dibaca dari status asal + bukti kehadiran).
     */
    public function alasanStatus(): ?string
    {
        return match ($this->statusEfektif()) {
            self::STATUS_KEDALUWARSA => match ($this->status) {
                self::STATUS_DISETUJUI => 'Sudah disetujui, tetapi tidak ada tap akses di mesin ruangan selama jadwal berlangsung — dianggap tidak hadir.',
                self::STATUS_MENUNGGU_PENERIMAAN => 'Tawaran waktu pengganti dari petugas tidak diterima sampai jadwal peminjaman terlewat.',
                self::STATUS_DIAJUKAN => 'Tidak ada keputusan dari petugas sampai jadwal peminjaman terlewat.',
                default => 'Jadwal peminjaman terlewat tanpa penyelesaian.',
            },
            self::STATUS_DIBATALKAN => $this->diputus_oleh
                ? 'Dicabut oleh petugas — lihat catatan keputusan.'
                : 'Dibatalkan oleh pemohon.',
            default => null,
        };
    }

    public function getAlasanStatusAttribute(): ?string
    {
        return $this->alasanStatus();
    }

    private ?bool $memoKedatangan = null;
}
