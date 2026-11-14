<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AbsensiSesiSetting extends Model
{
    protected $table = 'absensi_sesi_settings';

    protected $fillable = [
        'shift',
        'jam_masuk',
        'jam_mulai_tap_masuk',
        'jam_pulang',
        'toleransi_masuk_menit',
        'toleransi_pulang_menit',
        'grace_alfa_menit',
        'aktif',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'toleransi_masuk_menit' => 'integer',
            'toleransi_pulang_menit' => 'integer',
            'grace_alfa_menit' => 'integer',
        ];
    }

    public static function forShift(string $shift): ?self
    {
        return static::query()->where('shift', $shift)->where('aktif', true)->first();
    }

    public function timezoneCarbon(?Carbon $at = null): Carbon
    {
        return ($at ?? now())->copy()->timezone($this->timezone ?: 'Asia/Jakarta');
    }

    public function jamMasukOn(Carbon $date): Carbon
    {
        return $this->parseTimeOnDate($date, (string) $this->jam_masuk);
    }

    public function jamPulangOn(Carbon $date): Carbon
    {
        return $this->parseTimeOnDate($date, (string) $this->jam_pulang);
    }

    public function jamMulaiTapOn(Carbon $date): Carbon
    {
        $time = $this->jam_mulai_tap_masuk ?: $this->jam_masuk;

        return $this->parseTimeOnDate($date, (string) $time);
    }

    public function toleransiPulangMenit(): int
    {
        if ($this->toleransi_pulang_menit !== null) {
            return (int) $this->toleransi_pulang_menit;
        }

        return (int) ($this->toleransi_masuk_menit ?? 0);
    }

    /** Batas aman masuk: sampai jam standar + toleransi tidak dihitung telat. */
    public function batasAmanMasukOn(Carbon $date): Carbon
    {
        return $this->jamMasukOn($date)->copy()->addMinutes((int) $this->toleransi_masuk_menit);
    }

    /** Batas aman pulang: sampai jam pulang + toleransi tidak dihitung telat pulang. */
    public function batasAmanPulangOn(Carbon $date): Carbon
    {
        return $this->jamPulangOn($date)->copy()->addMinutes($this->toleransiPulangMenit());
    }

    public function tutupSesiOn(Carbon $date): Carbon
    {
        return $this->jamPulangOn($date)->copy()->addMinutes((int) $this->grace_alfa_menit);
    }

    private function parseTimeOnDate(Carbon $date, string $time): Carbon
    {
        $tz = $this->timezone ?: 'Asia/Jakarta';
        $parts = explode(':', $time);

        return Carbon::create(
            $date->year,
            $date->month,
            $date->day,
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
            $tz
        );
    }
}
