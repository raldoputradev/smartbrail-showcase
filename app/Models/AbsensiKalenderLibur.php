<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AbsensiKalenderLibur extends Model
{
    protected $table = 'absensi_kalender_libur';

    protected $fillable = [
        'tanggal',
        'tanggal_selesai',
        'keterangan',
        'jenis',
        'dampak_absensi',
        'prioritas',
        'jam_masuk_khusus',
        'jam_pulang_khusus',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function coversDate(CarbonInterface|string $date): bool
    {
        $target = $date instanceof CarbonInterface
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        $start = $this->tanggal?->toDateString();
        if (! $start) {
            return false;
        }
        $end = $this->tanggal_selesai?->toDateString() ?: $start;

        return $target >= $start && $target <= $end;
    }

    public function effectiveJenis(): string
    {
        return $this->jenis ?: 'libur';
    }

    public function effectivePrioritas(): string
    {
        return in_array($this->prioritas, ['rendah', 'normal', 'penting', 'mendesak'], true)
            ? $this->prioritas
            : 'normal';
    }

    public function effectiveDampak(): string
    {
        if (! empty($this->dampak_absensi)) {
            return $this->dampak_absensi;
        }

        return match ($this->effectiveJenis()) {
            'perubahan_jam' => 'ubah_jam',
            'ujian', 'informasi', 'peringatan', 'agenda' => 'tetap_wajib',
            default => 'batal_wajib',
        };
    }

    /**
     * Memo per-request: schema check + entri kalender dipanggil per tanggal oleh resolve()
     * (kalender 30 hari = 30x info_schema + 30x query entri sebelum memo ini).
     * Key diikat ke object request → request baru selalu membaca data segar;
     * event saved/deleted mengosongkan memo untuk write dalam request yang sama.
     *
     * @var array<int|string, array<string, mixed>>
     */
    private static array $memo = [];

    protected static function booted(): void
    {
        static::saved(fn () => self::$memo = []);
        static::deleted(fn () => self::$memo = []);
    }

    private static function memoKey(): int|string
    {
        $req = app('request');

        return $req ? spl_object_id($req) : 'cli';
    }

    private static function tableReady(): bool
    {
        $memo = &self::$memo[self::memoKey()];
        if (! is_array($memo)) {
            $memo = [];
        }
        if (! array_key_exists('schema|__table__', $memo)) {
            $memo['schema|__table__'] = Schema::hasTable('absensi_kalender_libur');
        }

        return (bool) $memo['schema|__table__'];
    }

    private static function schemaReady(string $column): bool
    {
        $key = 'schema|' . $column;
        $memo = &self::$memo[self::memoKey()];
        if (! is_array($memo)) {
            $memo = [];
        }
        if (! array_key_exists($key, $memo)) {
            $memo[$key] = self::tableReady()
                && Schema::hasColumn('absensi_kalender_libur', $column);
        }

        return (bool) $memo[$key];
    }

    /**
     * @return Collection<int, self>
     */
    public static function entriesForDate(CarbonInterface|string $date): Collection
    {
        $d = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $memo = &self::$memo[self::memoKey()];
        if (! is_array($memo)) {
            $memo = [];
        }

        if (! self::schemaReady('tanggal_selesai')) {
            // Fallback schema lama (kolom tanggal_selesai belum termigrasi): query per tanggal
            // tetapi hasil tetap di-memo per tanggal agar pemanggil berulang tidak query ulang.
            $fkey = 'entries|' . $d;
            if (! array_key_exists($fkey, $memo)) {
                $memo[$fkey] = self::tableReady()
                    ? static::query()->whereDate('tanggal', $d)->orderBy('id')->get()
                    : new Collection();
            }

            return $memo[$fkey];
        }

        // Schema baru: ambil semua entri yang berpotensi menutupi bulan ini SEKALI,
        // lalu petakan per tanggal — maksimum 1 query per bulan per request.
        $month = substr($d, 0, 7);
        $mkey = 'month|' . $month;
        if (! array_key_exists($mkey, $memo)) {
            $monthStart = $month . '-01';
            $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();
            $rows = static::query()
                ->whereDate('tanggal', '<=', $monthEnd)
                ->where(function ($q) use ($monthStart) {
                    $q->where(function ($sub) use ($monthStart) {
                        $sub->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $monthStart);
                    })->orWhereDate('tanggal_selesai', '>=', $monthStart);
                })
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get();

            $map = [];
            $days = (int) Carbon::parse($monthStart)->daysInMonth;
            for ($day = 1; $day <= $days; $day++) {
                $dateStr = sprintf('%s-%02d', $month, $day);
                $map[$dateStr] = $rows->filter(fn ($e) => $e->coversDate($dateStr))->values();
            }
            $memo[$mkey] = $map;
        }

        return $memo[$mkey][$d] ?? new Collection();
    }

    public static function isLibur(CarbonInterface|string $date): bool
    {
        $entries = static::entriesForDate($date);

        foreach ($entries as $entry) {
            if ($entry->effectiveDampak() === 'batal_wajib') {
                return true;
            }
        }

        return false;
    }
}
