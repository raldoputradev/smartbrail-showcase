<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Support\AdminAudit;
use App\Support\AbsensiSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminLogController extends Controller
{
    use Concerns\AdminShellProps;

    public function absensi(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $state = $this->absensiState($request);
        $user = $request->user();

        return \Inertia\Inertia::render('LogTap', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'filters' => $state['filter'],
            'options' => $state['options'],
            'stats' => $state['stats'],
            'rincian' => $state['rincian'],
            'tren' => $state['tren'],
            'logs' => $state['logs'],
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'log-tap')
                    : route('admin.log.absensi'),
            ],
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $state = $this->absensiState($request);
        $logs = $state['logs'];
        $rows = collect($logs['data'])->map(fn (array $log): array => [
            'waktu' => $log['waktu'],
            'nama' => $log['pengguna']['nama'] ?? '',
            'nim' => $log['pengguna']['identitas'] ?? '',
            'role' => $log['pengguna']['role'] ?? '',
            'id_mesin' => $log['perangkat']['id_mesin'] ?? '',
        ])->all();
        $stats = $state['stats'];

        return response()->json(AbsensiSnapshot::envelope('log', 'laboran', [
            'query' => AbsensiSnapshot::canonicalQuery($request),
            'revision' => substr(hash('sha256', json_encode([$rows, $stats])), 0, 16),
            'summary' => ['total' => $stats['total'], 'today' => $stats['today'], 'minggu_ini' => $stats['minggu_ini']],
            'summary_text' => [
                'total' => (string) $stats['total'],
                'today' => (string) $stats['today'],
                'minggu' => (string) $stats['minggu_ini'],
                'mesin' => (string) $state['options']['mesin']->count(),
            ],
            'pagination' => $logs['meta'],
            'rows' => $rows,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function absensiState(Request $request): array
    {
        $perPage = (int) $request->query('per_page', 20);
        if (! in_array($perPage, [10, 20, 50], true)) {
            $perPage = 20;
        }

        $filter = [
            'q' => trim((string) $request->query('q', '')),
            'id_mesin' => (string) $request->query('id_mesin', ''),
            'jenis' => (string) $request->query('jenis', ''),
            'arah' => (string) $request->query('arah', ''),
            'tanggal_mulai' => (string) $request->query('tanggal_mulai', ''),
            'tanggal_akhir' => (string) $request->query('tanggal_akhir', ''),
            'per_page' => $perPage,
        ];
        if (! in_array($filter['arah'], ['masuk', 'pulang'], true)) {
            $filter['arah'] = '';
        }

        $today = now()->toDateString();
        if ($filter['tanggal_mulai'] === '' && $filter['tanggal_akhir'] === '') {
            $filter['tanggal_mulai'] = $today;
            $filter['tanggal_akhir'] = $today;
        }

        $base = LogAbsensi::query();

        if ($filter['id_mesin'] !== '') {
            $base->where('id_mesin', $filter['id_mesin']);
        }
        if ($filter['jenis'] !== '') {
            $base->whereIn('id_mesin', MesinIot::query()->where('tipe', $filter['jenis'])->pluck('id_mesin'));
        }
        if ($filter['arah'] !== '') {
            $base->where('jenis', $filter['arah']);
        }
        if ($filter['tanggal_mulai'] !== '') {
            $base->whereDate('waktu_tap', '>=', $filter['tanggal_mulai']);
        }
        if ($filter['tanggal_akhir'] !== '') {
            $base->whereDate('waktu_tap', '<=', $filter['tanggal_akhir']);
        }
        if ($filter['q'] !== '') {
            $kw = $filter['q'];
            $base->whereHas('user', function ($qb) use ($kw) {
                $qb->where('nama_lengkap', 'like', "%{$kw}%")
                    ->orWhere('nitk_nidn', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%");
            });
        }

        $stats = [
            'total' => (clone $base)->count(),
            'masuk' => (clone $base)->where('jenis', 'masuk')->count(),
            'pulang' => (clone $base)->where('jenis', 'pulang')->count(),
            'unik' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
            'perangkat' => MesinIot::query()->where('tipe', '!=', 'loker')->count(),
            'today' => LogAbsensi::query()->whereDate('waktu_tap', $today)->count(),
            'minggu_ini' => LogAbsensi::query()->whereBetween('waktu_tap', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
        $stats['masuk_pct'] = $stats['total'] > 0 ? round($stats['masuk'] / $stats['total'] * 100) : 0;
        $stats['pulang_pct'] = $stats['total'] > 0 ? round($stats['pulang'] / $stats['total'] * 100) : 0;

        // Rincian akses per jenis perangkat (mesin tak dikenal → "Lainnya").
        $rincian = (clone $base)
            ->leftJoin('mesin_iot', 'log_absensi.id_mesin', '=', 'mesin_iot.id_mesin')
            ->selectRaw("COALESCE(mesin_iot.tipe, 'lainnya') as tipe, COUNT(*) as total")
            ->groupBy('tipe')
            ->pluck('total', 'tipe')
            ->map(fn ($total, $tipe) => [
                'key' => (string) $tipe,
                'label' => $tipe === 'lainnya' ? 'Lainnya' : Str::headline(str_replace(['-', '_'], ' ', (string) $tipe)),
                'total' => (int) $total,
                'pct' => $stats['total'] > 0 ? round($total / $stats['total'] * 100) : 0,
            ])
            ->sortByDesc('total')
            ->values()->all();

        // Tren per jam pada rentang filter.
        $hourExpr = LogAbsensi::query()->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', waktu_tap) AS INTEGER)"
            : 'HOUR(waktu_tap)';
        $hourRows = (clone $base)
            ->whereTime('waktu_tap', '>=', '06:00:00')
            ->whereTime('waktu_tap', '<=', '22:59:59')
            ->selectRaw("{$hourExpr} as jam, COUNT(*) as total")
            ->groupBy('jam')
            ->pluck('total', 'jam');
        $tren = ['labels' => [], 'values' => []];
        for ($h = 6; $h <= 22; $h++) {
            $tren['labels'][] = sprintf('%02d:00', $h);
            $tren['values'][] = (int) ($hourRows[$h] ?? 0);
        }

        $paginator = $base->with(['user:id,nama_lengkap,email,nitk_nidn,role,profile_photo_path'])
            ->latest('waktu_tap')
            ->paginate($perPage)
            ->withQueryString();

        $mesinMap = MesinIot::query()
            ->whereIn('id_mesin', collect($paginator->items())->pluck('id_mesin')->filter()->unique())
            ->with('ruangan:id,nama,kode')
            ->get()->keyBy('id_mesin');

        $rows = collect($paginator->items())->map(function (LogAbsensi $log) use ($mesinMap) {
            $mesin = $mesinMap->get($log->id_mesin);
            $ket = [];
            if (($log->menit_terlambat ?? 0) > 0) {
                $ket[] = 'Terlambat ' . $log->menit_terlambat . ' mnt';
            }
            if (($log->menit_pulang_cepat ?? 0) > 0 || $log->pulang_cepat) {
                $ket[] = 'Pulang cepat' . (($log->menit_pulang_cepat ?? 0) > 0 ? ' ' . $log->menit_pulang_cepat . ' mnt' : '');
            }
            if ($log->pulang_lembur) {
                $ket[] = 'Lembur';
            }

            return [
                'id' => $log->id,
                'waktu' => $log->waktu_tap?->translatedFormat('d M Y, H:i:s') ?? '—',
                'waktu_sort' => $log->waktu_tap?->toIso8601String(),
                'pengguna' => [
                    'nama' => $log->user->nama_lengkap ?? '—',
                    'email' => $log->user->email ?? '',
                    'identitas' => $log->user->nitk_nidn ?? '—',
                    'role' => $log->user->role ?? '',
                    'foto' => $log->user?->profile_photo_url,
                ],
                'jenis' => $mesin
                    ? Str::headline(str_replace(['-', '_'], ' ', (string) $mesin->tipe))
                    : 'Absensi',
                'jenis_key' => (string) ($mesin->tipe ?? 'absensi'),
                'arah' => $log->jenis === 'pulang' ? 'keluar' : ($log->jenis === 'masuk' ? 'masuk' : null),
                'arah_label' => $log->jenis === 'pulang' ? 'Keluar' : ($log->jenis === 'masuk' ? 'Masuk' : '—'),
                'perangkat' => [
                    'id_mesin' => $log->id_mesin,
                    'nama' => $mesin?->nama_mesin ?: $log->id_mesin,
                    'ruangan' => $mesin?->ruangan?->nama,
                ],
                'status' => 'Valid',
                'keterangan' => $ket === [] ? '—' : implode(' · ', $ket),
            ];
        })->values()->all();

        $logs = [
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ];

        $options = [
            'mesin' => MesinIot::query()->where('tipe', '!=', 'loker')->orderBy('id_mesin')
                ->get(['id_mesin', 'nama_mesin'])
                ->map(fn ($m) => ['id' => $m->id_mesin, 'nama' => $m->nama_mesin ?: $m->id_mesin]),
            'jenis' => MesinIot::query()->where('tipe', '!=', 'loker')->whereNotNull('tipe')
                ->distinct()->orderBy('tipe')->pluck('tipe')
                ->map(fn ($t) => ['key' => $t, 'label' => Str::headline(str_replace(['-', '_'], ' ', $t))]),
        ];

        return compact('logs', 'stats', 'filter', 'options', 'rincian', 'tren');
    }

    /**
     * Jejak aktivitas admin/laboran — siapa melakukan apa, kapan.
     * Transparansi aksi panel: buat/ubah ruangan, keputusan pengajuan,
     * binding perangkat, kelola petugas.
     */
    public function jejakAktivitas(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();

        [$filter, $query] = $this->jejakAktivitasQuery($request);

        $logs = $query->paginate(20)->withQueryString();

        return \Inertia\Inertia::render('JejakAktivitas', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'filters' => $filter,
            'aksi_options' => collect(AdminAudit::AKSI)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()->all(),
            'aktor_options' => ['admin', 'laboran'],
            'logs' => [
                'data' => collect($logs->items())->map(fn (AdminActivityLog $l) => [
                    'id' => $l->id,
                    'waktu' => $l->created_at?->translatedFormat('d M Y · H:i:s'),
                    'aktor' => $l->actor_nama,
                    'role' => $l->actor_role,
                    'aksi' => $l->aksi,
                    'aksi_label' => AdminAudit::label($l->aksi),
                    'target' => $l->target,
                    'detail' => $l->detail,
                    'ip' => $l->ip,
                ])->all(),
                'meta' => [
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                ],
            ],
            'urls' => [
                'index' => route('admin.jejak-aktivitas'),
                'export' => route('admin.jejak-aktivitas.export'),
            ],
        ]);
    }

    public function jejakAktivitasExport(Request $request)
    {
        [$filter, $query] = $this->jejakAktivitasQuery($request);
        $format = $request->query('format') === 'xls' ? 'xls' : 'csv';
        $stamp = now()->format('Ymd-His');
        $filename = "jejak-aktivitas-{$stamp}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM — Excel membaca UTF-8 dgn benar
                fputcsv($out, ['Waktu', 'Aktor', 'Role', 'Aksi', 'Target', 'Detail', 'IP'], ';');
                $query->chunkById(500, function ($rows) use ($out) {
                    foreach ($rows as $l) {
                        fputcsv($out, [
                            $l->created_at?->format('Y-m-d H:i:s'),
                            $l->actor_nama, $l->actor_role,
                            AdminAudit::label($l->aksi),
                            $l->target, $l->detail, $l->ip,
                        ], ';');
                    }
                });
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($query, $filter) {
            $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $cellS = static fn ($v, string $style = '') => '<Cell'.($style ? ' ss:StyleID="'.$style.'"' : '').'><Data ss:Type="String">'.$esc($v).'</Data></Cell>';
            $row = static fn (array $cells) => '<Row>'.implode('', $cells).'</Row>';

            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>'
                .'<Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#1E3A5F"/></Style>'
                .'<Style ss:ID="head"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/></Style>'
                .'<Style ss:ID="lbl"><Font ss:Bold="1"/></Style>'
                .'</Styles>';

            echo '<Worksheet ss:Name="JEJAK_AKTIVITAS"><Table>';
            echo $row([$cellS('SMARTBRAIL | JEJAK AKTIVITAS ADMIN & LABORAN', 'title')]);
            echo $row([$cellS('')]);
            echo $row([$cellS('Tanggal cetak', 'lbl'), $cellS(now()->translatedFormat('d F Y H:i'))]);
            echo $row([$cellS('Filter', 'lbl'), $cellS(
                'aksi='.($filter['aksi'] ?: 'semua')
                .' · aktor='.($filter['aktor'] ?: 'semua')
                .' · rentang='.($filter['dari'] ?: '—').' s/d '.($filter['sampai'] ?: '—')
                .($filter['q'] !== '' ? ' · teks="'.$filter['q'].'"' : '')
            )]);
            echo $row([$cellS('')]);
            echo $row([
                $cellS('Waktu', 'head'), $cellS('Aktor', 'head'), $cellS('Role', 'head'),
                $cellS('Aksi', 'head'), $cellS('Target', 'head'), $cellS('Detail', 'head'), $cellS('IP', 'head'),
            ]);
            $query->chunkById(500, function ($rows2) use ($row, $cellS) {
                foreach ($rows2 as $l) {
                    echo $row([
                        $cellS($l->created_at?->format('Y-m-d H:i:s')),
                        $cellS($l->actor_nama), $cellS($l->actor_role),
                        $cellS(AdminAudit::label($l->aksi)),
                        $cellS($l->target), $cellS($l->detail), $cellS($l->ip),
                    ]);
                }
            });
            echo '</Table></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function jejakAktivitasQuery(Request $request): array
    {
        $filter = [
            'q'     => trim((string) $request->query('q', '')),
            'aksi'  => (string) $request->query('aksi', ''),
            'aktor' => (string) $request->query('aktor', ''),
            'dari'  => (string) $request->query('dari', ''),
            'sampai'=> (string) $request->query('sampai', ''),
        ];

        $query = AdminActivityLog::query()->latest('id');
        if ($filter['q'] !== '') {
            $q = '%'.$filter['q'].'%';
            $query->where(fn ($w) => $w->where('actor_nama', 'like', $q)
                ->orWhere('target', 'like', $q)->orWhere('detail', 'like', $q));
        }
        if ($filter['aksi'] !== '') {
            $query->where('aksi', $filter['aksi']);
        }
        if ($filter['aktor'] !== '') {
            $query->where('actor_role', $filter['aktor']);
        }
        if ($filter['dari'] !== '') {
            $query->where('created_at', '>=', $filter['dari'].' 00:00:00');
        }
        if ($filter['sampai'] !== '') {
            $query->where('created_at', '<=', $filter['sampai'].' 23:59:59');
        }

        return [$filter, $query];
    }
}
