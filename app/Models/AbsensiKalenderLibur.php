<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AbsensiKalenderLibur extends Model
{
    protected $table = 'absensi_kalender_libur';

    protected $fillable = [
        'tanggal',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public static function isLibur(Carbon|string $date): bool
    {
        $d = $date instanceof Carbon ? $date->toDateString() : $date;

        return static::query()->whereDate('tanggal', $d)->exists();
    }
}
