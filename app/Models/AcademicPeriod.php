<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Periode akademik (semester ganjil/genap) — rentang tanggal eksplisit
 * yang dikelola admin. is_active menandai semester yang sedang berjalan;
 * laporan periodik dapat memakai periode ini sebagai preset rentang.
 */
class AcademicPeriod extends Model
{
    protected $fillable = [
        'kode', 'label', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'is_active',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_active' => 'boolean',
    ];

    public const JENIS = ['ganjil', 'genap'];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::active()->orderByDesc('tanggal_mulai')->first();
    }

    /**
     * Periode berikutnya secara akademik: ganjil → genap tahun yang sama,
     * genap → ganjil tahun berikutnya (berdasarkan kode "TAHUN-jenis").
     */
    public function next(): ?self
    {
        if ($this->jenis === 'ganjil') {
            return static::where('jenis', 'genap')
                ->where('tanggal_mulai', '>', $this->tanggal_mulai)
                ->orderBy('tanggal_mulai')->first();
        }
        return static::where('jenis', 'ganjil')
            ->where('tanggal_mulai', '>', $this->tanggal_mulai)
            ->orderBy('tanggal_mulai')->first();
    }
}
