<?php

namespace App\Support;

use App\Models\AbsensiHarian;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodicReportBuilder
{
    private const STATUS_HADIR = ['lengkap'];

    private const STATUS_TIDAK_LENGKAP = ['tidak_lengkap', 'masuk'];

    private const STATUS_ALFA = ['alfa'];

    /**
     * @return array<string, mixed>
     */
    public static function build(Request $request, ?User $dosen = null): array
    {
        $filter = self::resolveFilter($request);
        $start = Carbon::parse($filter['tanggal_mulai'])->startOfDay();
        $end = Carbon::parse($filter['tanggal_akhir'])->endOfDay();

        $mahasiswaBaseQuery = self::mahasiswaBaseQuery($dosen);

        $kelasOptions = (clone $mahasiswaBaseQuery)
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        $timOptions = (clone $mahasiswaBaseQuery)
            ->whereNotNull('tim')
            ->where('tim', '!=', '')
            ->distinct()
            ->orderBy('tim')
            ->pluck('tim');

        $absensiStudentFilter = function ($qb) use ($filter): void {
            $qb->where('role', 'mahasiswa');
            if ($filter['kelas'] !== '') {
                $qb->where('kelas', $filter['kelas']);
            }
            if ($filter['tim'] !== '') {
                $qb->where('tim', $filter['tim']);
            }
        };

        $harianQuery = AbsensiHarian::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->whereHas('user', $absensiStudentFilter);

        $absensiQuery = LogAbsensi::query()
            ->with(['user:id,nama_lengkap,nitk_nidn,kelas,tim,role'])
            ->whereBetween('waktu_tap', [$start, $end])
            ->whereHas('user', $absensiStudentFilter);

        $statusCounts = self::countStatuses(clone $harianQuery);

        $summary = [
            'mahasiswa' => (clone $mahasiswaBaseQuery)
                ->when($filter['kelas'] !== '', fn ($q) => $q->where('kelas', $filter['kelas']))
                ->when($filter['tim'] !== '', fn ($q) => $q->where('tim', $filter['tim']))
                ->count(),
            'dosen' => $dosen ? 0 : User::query()->where('role', 'dosen')->count(),
            'hadir' => $filter['aktivitas'] !== 'loker' ? $statusCounts['hadir'] : 0,
            'tidak_lengkap' => $filter['aktivitas'] !== 'loker' ? $statusCounts['tidak_lengkap'] : 0,
            'alfa' => $filter['aktivitas'] !== 'loker' ? $statusCounts['alfa'] : 0,
            'loker' => 0,
            'mesin_online' => MesinIot::online()->count(),
        ];

        $statusPerHari = (clone $harianQuery)
            ->selectRaw('tanggal')
            ->selectRaw(self::statusSumSql('hadir', self::STATUS_HADIR))
            ->selectRaw(self::statusSumSql('tidak_lengkap', self::STATUS_TIDAK_LENGKAP))
            ->selectRaw(self::statusSumSql('alfa', self::STATUS_ALFA))
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        $lokerPerHari = collect();

        $normalizeDate = static fn ($value): string => Carbon::parse($value)->toDateString();

        $hadirMap = $statusPerHari->mapWithKeys(fn ($row) => [$normalizeDate($row->tanggal) => (int) $row->hadir]);
        $tidakLengkapMap = $statusPerHari->mapWithKeys(fn ($row) => [$normalizeDate($row->tanggal) => (int) $row->tidak_lengkap]);
        $alfaMap = $statusPerHari->mapWithKeys(fn ($row) => [$normalizeDate($row->tanggal) => (int) $row->alfa]);
        $lokerMap = $lokerPerHari->mapWithKeys(fn ($row) => [$normalizeDate($row->tanggal) => (int) $row->total]);

        // Isi label harian agar grafik selalu tampil (bukan kosong meski data tipis).
        $daySpan = max(1, $start->diffInDays($end) + 1);
        if ($daySpan <= 93) {
            $chartLabels = collect();
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $chartLabels->push($cursor->toDateString());
                $cursor->addDay();
            }
        } else {
            $chartLabels = match ($filter['aktivitas']) {
                'absensi' => collect($statusPerHari)->map(fn ($row) => $normalizeDate($row->tanggal)),
                'loker' => collect($lokerPerHari)->map(fn ($row) => $normalizeDate($row->tanggal)),
                default => collect($statusPerHari)->map(fn ($row) => $normalizeDate($row->tanggal))
                    ->merge(collect($lokerPerHari)->map(fn ($row) => $normalizeDate($row->tanggal))),
            };
            $chartLabels = $chartLabels->unique()->sort()->values();
            if ($chartLabels->isEmpty()) {
                $chartLabels = collect([$start->toDateString(), $end->toDateString()]);
            }
        }

        $chart = [
            'labels' => $chartLabels->all(),
            'hadir' => $chartLabels->map(fn ($date) => (int) ($hadirMap[$date] ?? 0))->all(),
            'tidak_lengkap' => $chartLabels->map(fn ($date) => (int) ($tidakLengkapMap[$date] ?? 0))->all(),
            'alfa' => $chartLabels->map(fn ($date) => (int) ($alfaMap[$date] ?? 0))->all(),
            'loker' => $chartLabels->map(fn ($date) => (int) ($lokerMap[$date] ?? 0))->all(),
        ];

        $topAbsensiMachines = (clone $absensiQuery)
            ->selectRaw('id_mesin, COUNT(*) as total')
            ->groupBy('id_mesin')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['aktivitas' => 'Absensi', 'id_mesin' => $row->id_mesin, 'total' => (int) $row->total]);

        $topLokerMachines = collect();

        $topMachines = (match ($filter['aktivitas']) {
            'absensi' => $topAbsensiMachines,
            'loker' => $topLokerMachines,
            default => $topAbsensiMachines->merge($topLokerMachines),
        })
            ->sortByDesc('total')
            ->take(8)
            ->values();

        $recentAbsensi = $filter['aktivitas'] !== 'loker'
            ? (clone $absensiQuery)->latest('waktu_tap')->limit(8)->get()->map(fn ($row) => [
                'aktivitas' => 'Absensi',
                'waktu' => $row->waktu_tap,
                'nama' => $row->user->nama_lengkap ?? '-',
                'nim' => $row->user->nitk_nidn ?? '-',
                'kelas' => $row->user->kelas ?? '-',
                'tim' => $row->user->tim ?? '-',
                'id_mesin' => $row->id_mesin,
                'tap_jenis' => $row->jenis ?: 'tap',
                'status' => self::tapJenisLabel($row->jenis),
            ])
            : collect();

        $recentLoker = collect();

        $recentActivities = $recentAbsensi
            ->merge($recentLoker)
            ->sortByDesc('waktu')
            ->take(12)
            ->values();

        $harianByUser = (clone $harianQuery)
            ->selectRaw('user_id')
            ->selectRaw(self::statusSumSql('hadir', self::STATUS_HADIR))
            ->selectRaw(self::statusSumSql('tidak_lengkap', self::STATUS_TIDAK_LENGKAP))
            ->selectRaw(self::statusSumSql('alfa', self::STATUS_ALFA))
            ->selectRaw('MAX(tanggal) as terakhir_tanggal')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $lokerByUser = collect();

        $studentRecaps = (clone $mahasiswaBaseQuery)
            ->when($filter['kelas'] !== '', fn ($q) => $q->where('kelas', $filter['kelas']))
            ->when($filter['tim'] !== '', fn ($q) => $q->where('tim', $filter['tim']))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'kelas', 'tim'])
            ->map(function (User $student) use ($harianByUser, $lokerByUser, $filter) {
                $harian = $harianByUser->get($student->id);
                $hadir = $filter['aktivitas'] !== 'loker' ? (int) ($harian->hadir ?? 0) : 0;
                $tidakLengkap = $filter['aktivitas'] !== 'loker' ? (int) ($harian->tidak_lengkap ?? 0) : 0;
                $alfa = $filter['aktivitas'] !== 'loker' ? (int) ($harian->alfa ?? 0) : 0;
                $lokerRow = $lokerByUser->get($student->id);
                $lokerTotal = $filter['aktivitas'] !== 'absensi' ? (int) ($lokerRow->total ?? 0) : 0;
                $lastHarian = $harian?->terakhir_tanggal
                    ? Carbon::parse($harian->terakhir_tanggal)->endOfDay()
                    : null;
                $lastLoker = $lokerRow?->terakhir
                    ? Carbon::parse($lokerRow->terakhir)
                    : null;

                return [
                    'user_id' => $student->id,
                    'nama' => $student->nama_lengkap,
                    'nim' => $student->nitk_nidn,
                    'kelas' => $student->kelas ?: '-',
                    'tim' => $student->tim ?: '-',
                    'hadir' => $hadir,
                    'tidak_lengkap' => $tidakLengkap,
                    'alfa' => $alfa,
                    'loker' => $lokerTotal,
                    'terakhir' => collect([$lastHarian, $lastLoker])->filter()->sortDesc()->first(),
                ];
            })
            ->sortByDesc(fn ($row) => $row['hadir'] + $row['tidak_lengkap'] + $row['alfa'] + $row['loker'])
            ->values();

        // Export & rekor: fokus mahasiswa yang punya jejak absensi di periode (bukan dump semua).
        $studentRecapsActive = $studentRecaps
            ->filter(fn ($row) => ((int) $row['hadir'] + (int) $row['tidak_lengkap'] + (int) $row['alfa']) > 0
                || ($filter['aktivitas'] !== 'absensi' && (int) $row['loker'] > 0))
            ->values();

        $rankings = self::buildRankings(
            $studentRecapsActive,
            $filter['rekor'],
            $filter['rekor_group'],
            $filter['aktivitas']
        );

        $detailAbsensi = $filter['aktivitas'] !== 'loker'
            ? (clone $absensiQuery)->latest('waktu_tap')->limit(250)->get()->map(fn ($row) => [
                'aktivitas' => 'Absensi',
                'waktu' => $row->waktu_tap,
                'nama' => $row->user->nama_lengkap ?? '-',
                'nim' => $row->user->nitk_nidn ?? '-',
                'kelas' => $row->user->kelas ?? '-',
                'tim' => $row->user->tim ?? '-',
                'id_mesin' => $row->id_mesin,
                'tap_jenis' => $row->jenis ?: 'tap',
                'status' => self::tapJenisLabel($row->jenis),
                'status_meta' => ($row->menit_terlambat ?? 0) > 0
                    ? 'Terlambat ' . (int) $row->menit_terlambat . ' menit'
                    : 'Tepat waktu',
            ])
            : collect();

        $detailLoker = collect();

        $detailRows = $detailAbsensi
            ->merge($detailLoker)
            ->sortByDesc('waktu')
            ->take(250)
            ->values();

        $activityLabel = match ($filter['aktivitas']) {
            'absensi' => 'Absensi',
            'loker' => 'Loker',
            default => 'Absensi + Loker',
        };

        $periodeLabel = match ($filter['periode']) {
            'minggu_ini' => 'Minggu ini',
            'minggu_lalu' => 'Minggu lalu',
            'bulan_ini' => 'Bulan ini',
            'bulan_lalu' => 'Bulan lalu',
            'tahun_bulan' => Carbon::parse($filter['bulan'] . '-01')->translatedFormat('F Y'),
            default => $filter['tanggal_mulai'] . ' s/d ' . $filter['tanggal_akhir'],
        };

        return compact(
            'filter',
            'kelasOptions',
            'timOptions',
            'summary',
            'chart',
            'topMachines',
            'recentActivities',
            'studentRecaps',
            'studentRecapsActive',
            'rankings',
            'detailRows',
            'activityLabel',
            'periodeLabel'
        );
    }

    /**
     * @return array<string, string>
     */
    private static function resolveFilter(Request $request): array
    {
        $periode = (string) $request->query('periode', 'bulan_ini');
        if (!in_array($periode, ['minggu_ini', 'minggu_lalu', 'bulan_ini', 'bulan_lalu', 'tahun_bulan', 'custom'], true)) {
            $periode = 'bulan_ini';
        }

        $bulan = (string) $request->query('bulan', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = now()->format('Y-m');
        }

        $now = now()->timezone(config('app.timezone', 'Asia/Jakarta'));

        [$mulai, $akhir] = match ($periode) {
            'minggu_ini' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)],
            'minggu_lalu' => [
                $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'bulan_ini' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'bulan_lalu' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'tahun_bulan' => [
                Carbon::parse($bulan . '-01')->startOfMonth(),
                Carbon::parse($bulan . '-01')->endOfMonth(),
            ],
            default => [null, null],
        };

        if ($periode === 'custom') {
            try {
                $mulai = Carbon::parse((string) $request->query('tanggal_mulai', $now->copy()->subDays(30)->toDateString()))->startOfDay();
            } catch (\Throwable) {
                $mulai = $now->copy()->subDays(30)->startOfDay();
            }
            try {
                $akhir = Carbon::parse((string) $request->query('tanggal_akhir', $now->toDateString()))->endOfDay();
            } catch (\Throwable) {
                $akhir = $now->copy()->endOfDay();
            }
        }

        if ($akhir->lt($mulai)) {
            [$mulai, $akhir] = [$akhir->copy()->startOfDay(), $mulai->copy()->endOfDay()];
        }

        $aktivitas = 'absensi';

        $rekor = (string) $request->query('rekor', 'terajin');
        if (!in_array($rekor, ['terajin', 'alfa', 'jarang', 'tidak_lengkap'], true)) {
            $rekor = 'terajin';
        }

        $rekorGroup = (string) $request->query('rekor_group', 'semua');
        if (!in_array($rekorGroup, ['semua', 'kelas', 'tim'], true)) {
            $rekorGroup = 'semua';
        }

        return [
            'periode' => $periode,
            'bulan' => $bulan,
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_akhir' => $akhir->toDateString(),
            'kelas' => (string) $request->query('kelas', ''),
            'tim' => (string) $request->query('tim', ''),
            'aktivitas' => $aktivitas,
            'rekor' => $rekor,
            'rekor_group' => $rekorGroup,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $studentRecaps
     * @return array{category: string, group: string, title: string, subtitle: string, metric: string, items: list<array<string, mixed>>}
     */
    private static function buildRankings($studentRecaps, string $category, string $group, string $aktivitas): array
    {
        $limit = 10;
        $rows = collect($studentRecaps);

        $metricMeta = match ($category) {
            'alfa' => [
                'title' => 'Paling sering alfa',
                'subtitle' => 'Hari alfa terbanyak di periode',
                'metric' => 'Alfa',
                'key' => 'alfa',
            ],
            'jarang' => [
                'title' => 'Tap paling jarang',
                'subtitle' => 'Hadir lengkap paling sedikit',
                'metric' => 'Hadir',
                'key' => 'hadir',
            ],
            'tidak_lengkap' => [
                'title' => 'Masuk tanpa pulang',
                'subtitle' => 'Tidak lengkap / hanya masuk terbanyak',
                'metric' => 'Tidak lengkap',
                'key' => 'tidak_lengkap',
            ],
            default => [
                'title' => 'Mahasiswa terajin',
                'subtitle' => 'Hadir lengkap (masuk + pulang) terbanyak',
                'metric' => 'Hadir',
                'key' => 'hadir',
            ],
        };

        if ($aktivitas === 'loker') {
            $items = $rows
                ->filter(fn ($r) => (int) ($r['loker'] ?? 0) > 0)
                ->sortByDesc(fn ($r) => (int) $r['loker'])
                ->take($limit)
                ->values()
                ->map(fn ($r, $i) => self::rankItem($i + 1, $r['nama'], ($r['nim'] ?? '-') . ' · ' . ($r['kelas'] ?? '-') . ' / ' . ($r['tim'] ?? '-'), (int) $r['loker'], number_format((int) $r['loker']) . ' akses', $r['user_id'] ?? null, [
                    'hadir' => 0, 'tidak_lengkap' => 0, 'alfa' => 0, 'loker' => (int) $r['loker'],
                ]))
                ->all();

            return [
                'category' => $category,
                'group' => $group,
                'title' => 'Akses loker terbanyak',
                'subtitle' => 'Mode loker — terpisah dari rekap hadir/alfa',
                'metric' => 'Loker',
                'items' => $items,
            ];
        }

        // Pecah dulu berdasarkan tipe (kelas / tim), pakai metrik yang dipilih.
        if (in_array($group, ['kelas', 'tim'], true)) {
            $groupKey = $group;
            $sortKey = $metricMeta['key'];
            $items = $rows
                ->groupBy(fn ($r) => ($r[$groupKey] ?? '-') ?: '-')
                ->map(function ($groupRows, $label) use ($sortKey, $groupKey) {
                    $hadir = (int) $groupRows->sum('hadir');
                    $tl = (int) $groupRows->sum('tidak_lengkap');
                    $alfa = (int) $groupRows->sum('alfa');
                    $metricVal = (int) $groupRows->sum($sortKey);
                    $denom = max(1, $hadir + $tl + $alfa);
                    $rate = round(($hadir / $denom) * 100, 1);

                    return [
                        'label' => $label === '-' ? 'Tanpa ' . $groupKey : $label,
                        'meta' => $groupRows->count() . ' mhs · hadir ' . $hadir . ' · TL ' . $tl . ' · alfa ' . $alfa,
                        'value' => $metricVal,
                        'value_label' => number_format($metricVal) . ' · ' . $rate . '% hadir',
                        'user_id' => null,
                        'bars' => [
                            'hadir' => $hadir,
                            'tidak_lengkap' => $tl,
                            'alfa' => $alfa,
                            'loker' => 0,
                        ],
                        '_sort' => $metricVal,
                    ];
                })
                ->filter(fn ($r) => ($r['bars']['hadir'] + $r['bars']['tidak_lengkap'] + $r['bars']['alfa']) > 0)
                ->sortByDesc('_sort')
                ->take($limit)
                ->values()
                ->map(function ($r, $i) {
                    unset($r['_sort']);
                    $r['rank'] = $i + 1;

                    return $r;
                })
                ->all();

            $groupTitle = $group === 'kelas' ? 'Per kelas' : 'Per tim PBL';

            return [
                'category' => $category,
                'group' => $group,
                'title' => $metricMeta['title'] . ' · ' . $groupTitle,
                'subtitle' => 'Agregat ' . $groupTitle . ' berdasarkan metrik yang dipilih (semua mahasiswa dulu bisa dilihat di tab Semua)',
                'metric' => $metricMeta['metric'],
                'items' => $items,
            ];
        }

        // Default: semua mahasiswa (belum dipecah kelas/tim).
        $sorted = match ($category) {
            'alfa' => $rows->filter(fn ($r) => (int) $r['alfa'] > 0)->sortByDesc(fn ($r) => (int) $r['alfa']),
            'tidak_lengkap' => $rows->filter(fn ($r) => (int) $r['tidak_lengkap'] > 0)->sortByDesc(fn ($r) => (int) $r['tidak_lengkap']),
            'jarang' => $rows->sort(function ($a, $b) {
                $cmp = ((int) $a['hadir']) <=> ((int) $b['hadir']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return ((int) $b['alfa']) <=> ((int) $a['alfa']);
            }),
            default => $rows->filter(fn ($r) => (int) $r['hadir'] > 0)->sortByDesc(fn ($r) => (int) $r['hadir']),
        };

        $metricKey = $metricMeta['key'];
        $items = $sorted->take($limit)->values()->map(function ($r, $i) use ($metricKey) {
            $val = (int) ($r[$metricKey] ?? 0);

            return self::rankItem(
                $i + 1,
                $r['nama'],
                ($r['nim'] ?? '-') . ' · ' . ($r['kelas'] ?? '-') . ' / ' . ($r['tim'] ?? '-'),
                $val,
                number_format($val) . ' sesi',
                $r['user_id'] ?? null,
                [
                    'hadir' => (int) $r['hadir'],
                    'tidak_lengkap' => (int) $r['tidak_lengkap'],
                    'alfa' => (int) $r['alfa'],
                    'loker' => 0,
                ]
            );
        })->all();

        return [
            'category' => $category,
            'group' => 'semua',
            'title' => $metricMeta['title'],
            'subtitle' => $metricMeta['subtitle'] . ' · cakupan semua (belum dipecah kelas/tim)',
            'metric' => $metricMeta['metric'],
            'items' => $items,
        ];
    }

    /**
     * @param  array{hadir:int,tidak_lengkap:int,alfa:int,loker:int}  $bars
     * @return array<string, mixed>
     */
    private static function rankItem(int $rank, string $label, string $meta, int $value, string $valueLabel, $userId, array $bars): array
    {
        return [
            'rank' => $rank,
            'label' => $label,
            'meta' => $meta,
            'value' => $value,
            'value_label' => $valueLabel,
            'user_id' => $userId,
            'bars' => $bars,
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function statusSumSql(string $alias, array $statuses): string
    {
        $quoted = implode(',', array_map(static fn (string $s) => "'{$s}'", $statuses));

        return "SUM(CASE WHEN status IN ({$quoted}) THEN 1 ELSE 0 END) as {$alias}";
    }

    /**
     * @return array{hadir: int, tidak_lengkap: int, alfa: int}
     */
    private static function countStatuses(Builder $query): array
    {
        $row = (clone $query)
            ->selectRaw(self::statusSumSql('hadir', self::STATUS_HADIR))
            ->selectRaw(self::statusSumSql('tidak_lengkap', self::STATUS_TIDAK_LENGKAP))
            ->selectRaw(self::statusSumSql('alfa', self::STATUS_ALFA))
            ->first();

        return [
            'hadir' => (int) ($row->hadir ?? 0),
            'tidak_lengkap' => (int) ($row->tidak_lengkap ?? 0),
            'alfa' => (int) ($row->alfa ?? 0),
        ];
    }

    private static function tapJenisLabel(?string $jenis): string
    {
        return match ($jenis) {
            'masuk' => 'Masuk',
            'pulang' => 'Pulang',
            default => 'Tap',
        };
    }

    /**
     * @return Builder<User>
     */
    private static function mahasiswaBaseQuery(?User $dosen): Builder
    {
        $query = User::query()->where('role', 'mahasiswa');

        if ($dosen) {
            $query->where('is_active', true);
        }

        return $query;
    }
}
