<?php

namespace App\Http\Controllers;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiKalenderLibur;
use App\Models\AbsensiKomentar;
use App\Models\AbsensiSesiSetting;
use App\Models\AbsensiTidakLengkap;
use App\Models\LogAbsensi;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\AbsensiKebijakan;
use App\Support\TablePagination;
use App\Support\AbsensiLabSlotResolver;
use App\Support\AbsensiReadinessReport;
use App\Support\AbsensiSchedulerHeartbeat;
use App\Support\AbsensiSessionCloser;
use App\Support\AbsensiTapEvaluator;
use App\Support\MahasiswaShiftResolver;
use App\Support\RekapAbsensiExportExcel;
use App\Support\RekapAbsensiExportHtml;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Support\AbsensiSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsensiManagementController extends Controller
{
    public function __construct(
        private readonly AbsensiTapEvaluator $evaluator
    ) {}

    public function settings(Request $request): View
    {
        $this->ensureLaboran();

        $sesi = AbsensiSesiSetting::query()->orderBy('shift')->get();
        $pagi = $sesi->firstWhere('shift', 'pagi');

        return view('admin.absensi_settings', [
            'sesi' => $sesi,
            'jadwalSesi' => [
                'pagi' => $pagi,
                'malam' => $sesi->firstWhere('shift', 'malam'),
            ],
            'slotLabPagi' => $pagi ? AbsensiLabSlotResolver::slotScheduleForPagi($pagi) : [],
            'kebijakan' => AbsensiKebijakan::config(),
            'kebijakanLabel' => AbsensiKebijakan::labelMode(),
            'ops' => AbsensiReadinessReport::build(),
        ]);
    }

    public function updateKebijakan(Request $request): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'mode' => ['required', 'in:harian,kamis'],
        ]);

        AbsensiKebijakan::setMode($validated['mode']);

        return back()->with('success', 'Kebijakan absensi wajib disimpan: ' . AbsensiKebijakan::labelMode());
    }

    public function runCloseSessions(Request $request, AbsensiSessionCloser $closer): RedirectResponse
    {
        $this->ensureLaboran();

        $date = $request->input('tanggal');
        $results = $date
            ? $closer->closeForDate($date, force: true)
            : $closer->closeDueSessions();

        if ($results === []) {
            return back()->with('info', 'Belum ada sesi yang perlu ditutup (atau hari ini bukan hari wajib / belum lewat jam tutup).');
        }

        $alfa = array_sum(array_column($results, 'alfa'));
        $detail = collect($results)->map(fn ($r) => "{$r['tanggal']} {$r['shift']}: {$r['alfa']} alfa")->implode('; ');

        AbsensiSchedulerHeartbeat::touch('admin:close-sessions', [
            'sessions_closed' => count($results),
            'alfa_total' => $alfa,
            'manual_date' => $date,
        ]);

        return back()->with('success', "Penutupan sesi selesai — {$alfa} alfa. {$detail}");
    }

    public function runGenerateAlfa(Request $request, AbsensiSessionCloser $closer): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $totalAlfa = 0;
        $dates = [];

        if (!empty($validated['tanggal'])) {
            $dates[] = \Carbon\Carbon::parse($validated['tanggal'])->toDateString();
        } else {
            $days = max(1, (int) ($validated['days'] ?? 3));
            for ($i = 1; $i <= $days; $i++) {
                $dates[] = now()->subDays($i)->toDateString();
            }
        }

        foreach ($dates as $tanggal) {
            if (!AbsensiKebijakan::isHariWajib($tanggal)) {
                continue;
            }
            foreach ($closer->closeForDate($tanggal, force: true) as $row) {
                $totalAlfa += $row['alfa'];
            }
        }

        AbsensiSchedulerHeartbeat::touch('admin:generate-alfa', ['alfa_total' => $totalAlfa, 'dates' => $dates]);

        return back()->with('success', "Cadangan alfa selesai — {$totalAlfa} entri alfa diproses untuk " . count($dates) . ' hari.');
    }

    public function updateSesi(Request $request): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'shift' => ['required', 'in:pagi,malam'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_mulai_tap_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'toleransi_masuk_menit' => ['required', 'integer', 'min:0', 'max:180'],
            'toleransi_pulang_menit' => ['required', 'integer', 'min:0', 'max:180'],
            'grace_alfa_menit' => ['required', 'integer', 'min:15', 'max:360'],
            'aktif' => ['nullable', 'boolean'],
            'timezone' => ['required', 'string', 'max:50'],
        ]);

        $update = [
            'jam_masuk' => $validated['jam_masuk'] . ':00',
            'jam_pulang' => $validated['jam_pulang'] . ':00',
            'toleransi_masuk_menit' => $validated['toleransi_masuk_menit'],
            'toleransi_pulang_menit' => $validated['toleransi_pulang_menit'],
            'grace_alfa_menit' => $validated['grace_alfa_menit'],
            'aktif' => $request->boolean('aktif'),
            'timezone' => $validated['timezone'],
        ];

        if ($validated['shift'] === 'pagi') {
            $update['jam_mulai_tap_masuk'] = ($validated['jam_mulai_tap_masuk'] ?? '06:30') . ':00';
        }

        AbsensiSesiSetting::query()->where('shift', $validated['shift'])->update($update);

        return back()->with('success', 'Jadwal sesi ' . $validated['shift'] . ' disimpan.');
    }

    public function storeLibur(Request $request): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        AbsensiKalenderLibur::query()->updateOrCreate(
            ['tanggal' => $validated['tanggal']],
            ['keterangan' => $validated['keterangan'] ?? 'Hari libur']
        );

        return back()->with('success', 'Tanggal libur ditambahkan.');
    }

    public function destroyLibur(AbsensiKalenderLibur $libur): RedirectResponse
    {
        $this->ensureLaboran();
        $libur->delete();

        return back()->with('success', 'Tanggal libur dihapus.');
    }

    public function storeProdi(Request $request): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:program_studi,kode'],
            'nama' => ['required', 'string', 'max:120'],
        ]);

        ProgramStudi::query()->create([
            'kode' => strtolower($validated['kode']),
            'nama' => $validated['nama'],
            'aktif' => true,
        ]);

        return back()->with('success', 'Program studi ditambahkan.');
    }

    public function rekap(Request $request): View
    {
        $state = $this->rekapState($request);

        return view($state['panel']['view_rekap'], $state);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $this->ensureStaffAbsensi();
        $state = $this->rekapState($request);
        $user = $request->user();
        $scope = $user->isLaboran() ? 'laboran' : 'dosen';
        $canKoreksi = (bool) $state['canKoreksi'];
        $pageRows = collect($state['rows']->items())->map(function (array $row) use ($canKoreksi, $state): array {
            $public = [
                'tanggal' => $row['tanggal'],
                'nim' => $row['nim'],
                'nama' => $row['nama'],
                'kelas' => $row['kelas'],
                'prodi' => $row['prodi'],
                'semester' => $row['semester'],
                'shift' => $row['shift'],
                'sesi' => $row['sesi'],
                'status' => $row['status'],
                'slot_lab_masuk' => $row['slot_lab_masuk'],
                'jam_masuk' => $row['jam_masuk'],
                'jam_pulang' => $row['jam_pulang'],
                'menit_terlambat_masuk' => $row['menit_terlambat_masuk'],
                'menit_terlambat_pulang' => $row['menit_terlambat_pulang'],
                'pulang_lembur' => (bool) $row['pulang_lembur'],
            ];
            if ($canKoreksi) {
                $public['harian_id'] = $row['harian_id'];
                $public['koreksi_url'] = $row['harian_id']
                    ? route($state['panel']['route_koreksi'], $row['harian_id'])
                    : null;
            }

            return $public;
        })->all();
        $summary = $state['summary'];
        $paginator = $state['rows']->withPath(route($state['panel']['route_rekap']));
        $regions = [
            'rows' => view('absensi._rekap_detail_rows', [
                'rows' => $paginator,
                'canKoreksi' => $canKoreksi,
                'panel' => $state['panel'],
            ])->render(),
            'taps' => view('absensi._rekap_tap_rows', [
                'tapMasukHariIni' => $state['tapMasukHariIni'],
            ])->render(),
            'pager' => view('components.table-pagination', ['paginator' => $paginator])->render(),
        ];
        $revision = substr(hash('sha256', json_encode([
            $state['tab'],
            $state['tabCounts'],
            $pageRows,
            $state['tapMasukHariIni'],
        ])), 0, 16);

        return response()->json(AbsensiSnapshot::envelope('rekap', $scope, [
            'query' => AbsensiSnapshot::canonicalQuery($request),
            'revision' => $revision,
            'summary_mode' => $state['summaryMode'],
            'tab' => $state['tab'],
            'tab_counts' => $state['tabCounts'],
            'can_koreksi' => $canKoreksi,
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'hadir' => (int) ($summary['hadir'] ?? 0),
                'terlambat' => (int) ($summary['terlambat'] ?? 0),
                'lengkap' => (int) ($summary['lengkap'] ?? 0),
                'alfa' => (int) ($summary['alfa'] ?? 0),
                'tidak_lengkap' => (int) ($summary['tidak_lengkap'] ?? 0),
                'masuk' => (int) ($summary['masuk'] ?? 0),
                'belum' => (int) ($summary['belum'] ?? 0),
                'tidak_wajib' => (int) ($summary['tidak_wajib'] ?? 0),
                'persentase_lengkap' => (float) ($summary['persentase_lengkap'] ?? 0),
            ],
            'summary_text' => [
                'hadir' => number_format($summary['hadir'] ?? 0),
                'terlambat' => number_format($summary['terlambat'] ?? 0),
                'lengkap' => number_format($summary['lengkap'] ?? 0),
                'alfa' => number_format($summary['alfa'] ?? 0),
                'tidak_lengkap' => number_format($summary['tidak_lengkap'] ?? 0),
                'masuk' => number_format($summary['masuk'] ?? 0),
                'belum' => number_format($summary['belum'] ?? 0),
                'tidak_wajib' => number_format($summary['tidak_wajib'] ?? 0),
                'persen' => ($summary['persentase_lengkap'] ?? 0).'%',
                'persen-meta' => ($summary['lengkap'] ?? 0).' / '.($summary['denominator_wajib'] ?? 0).' wajib',
            ],
            'chart' => [
                'labels' => $state['chartSummary']['labels'],
                'values' => $state['chartSummary']['values'],
                'colors' => $state['chartSummary']['colors'],
                'center' => (int) ($summary['total'] ?? 0),
            ],
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'rows' => $pageRows,
            'tap_masuk' => $state['tapMasukHariIni'],
            'regions' => $regions,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rekapState(Request $request): array
    {
        $filter = $this->resolveRekapFilter($request);
        $tab = $this->resolveRekapTab($request);
        $allRows = $this->buildRekapRows($filter);
        $rawSummary = $this->rekapSummary($allRows);

        $tabRows = match ($tab) {
            'alfa' => array_values(array_filter($allRows, fn ($r) => ($r['status'] ?? '') === 'alfa')),
            'tidak_lengkap' => array_values(array_filter($allRows, fn ($r) => in_array($r['status'] ?? '', ['tidak_lengkap', 'masuk'], true))),
            'hadir' => array_values(array_filter($allRows, fn ($r) => ($r['jam_masuk'] ?? '—') !== '—' && ($r['jam_masuk'] ?? '') !== '')),
            default => $allRows,
        };

        $rows = TablePagination::paginate($tabRows, 'page', 15);
        $chartSummary = $this->rekapChartPayload($rawSummary);
        $summary = $rawSummary;
        $summaryMode = 'operasional';

        return [
            'rows' => $rows,
            'summary' => $summary,
            'summaryMode' => $summaryMode,
            'chartSummary' => $chartSummary,
            'tapMasukHariIni' => $this->buildTapMasukFeed($filter['date']),
            'kebijakanLabel' => AbsensiKebijakan::labelMode(),
            'hariIniWajib' => AbsensiKebijakan::isHariWajib(now()),
            'jadwalSesi' => [
                'pagi' => AbsensiSesiSetting::forShift('pagi'),
                'malam' => AbsensiSesiSetting::forShift('malam'),
            ],
            'filter' => $filter,
            'tab' => $tab,
            'tabCounts' => [
                'all' => count($allRows),
                'alfa' => count(array_filter($allRows, fn ($r) => ($r['status'] ?? '') === 'alfa')),
                'tidak_lengkap' => count(array_filter($allRows, fn ($r) => in_array($r['status'] ?? '', ['tidak_lengkap', 'masuk'], true))),
                'hadir' => count(array_filter($allRows, fn ($r) => in_array($r['status'] ?? '', ['lengkap', 'masuk'], true) && ($r['jam_masuk'] ?? '—') !== '—')),
            ],
            'canKoreksi' => $this->staffCanKoreksi(),
            'kelasOptions' => $this->distinctMahasiswaColumn('kelas'),
            'timOptions' => $this->distinctMahasiswaColumn('tim'),
            'angkatanOptions' => User::query()->where('role', 'mahasiswa')->whereNotNull('angkatan')->distinct()->orderByDesc('angkatan')->pluck('angkatan'),
            'semesterOptions' => User::query()->where('role', 'mahasiswa')->whereNotNull('semester')->distinct()->orderBy('semester')->pluck('semester'),
            'prodiOptions' => ProgramStudi::query()->where('aktif', true)->orderBy('nama')->get(),
            'panel' => $this->panelMeta(),
        ];
    }

    private function resolveRekapTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'all');

        return in_array($tab, ['all', 'alfa', 'tidak_lengkap', 'hadir'], true) ? $tab : 'all';
    }

    /**
     * @param  array<string, int|float>  $summary
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    private function rekapChartPayload(array $summary): array
    {
        return [
            'labels' => ['Hadir', 'Terlambat', 'Hanya masuk', 'Tidak lengkap', 'Alfa', 'Belum tap', 'Tidak wajib'],
            'values' => [
                (int) ($summary['hadir'] ?? 0),
                (int) ($summary['terlambat'] ?? 0),
                (int) ($summary['masuk'] ?? 0),
                (int) ($summary['tidak_lengkap'] ?? 0),
                (int) ($summary['alfa'] ?? 0),
                (int) ($summary['belum'] ?? 0),
                (int) ($summary['tidak_wajib'] ?? 0),
            ],
            'colors' => ['#0f766e', '#d97706', '#2563eb', '#ea580c', '#e11d48', '#94a3b8', '#64748b'],
        ];
    }

    public function exportRekapExcel(Request $request): StreamedResponse
    {
        $this->ensureStaffAbsensi();
        $ctx = $this->rekapExportContext($request);
        $filename = 'rekap-absensi-' . $ctx['filter']['tanggal_mulai'] . '-' . $ctx['filter']['tanggal_akhir'] . '.xls';
        $exportedBy = auth()->user();

        return response()->stream(function () use ($ctx, $exportedBy): void {
            echo RekapAbsensiExportExcel::render(
                $ctx['filter'],
                $ctx['rows'],
                $ctx['summary'],
                $exportedBy
            );
        }, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function printRekap(Request $request): View
    {
        $this->ensureStaffAbsensi();
        $ctx = $this->rekapExportContext($request);

        return view('absensi.rekap_print', [
            'rows' => $ctx['rows'],
            'summary' => $ctx['summary'],
            'filter' => $ctx['filter'],
            'kebijakanLabel' => AbsensiKebijakan::labelMode(),
            'panel' => $this->panelMeta(),
        ]);
    }

    /**
     * @return array{filter: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function rekapExportContext(Request $request): array
    {
        $filter = $this->resolveRekapFilter($request);
        $rows = $this->buildRekapRows($filter);
        return [
            'filter' => $filter,
            'rows' => $rows,
            'summary' => $this->rekapSummary($rows),
        ];
    }

    public function alfa(Request $request): RedirectResponse
    {
        $panel = $this->panelMeta();

        return redirect()->route($panel['route_rekap'], ['tab' => 'alfa'] + $request->query());
    }

    public function tidakLengkap(Request $request): RedirectResponse
    {
        $panel = $this->panelMeta();

        return redirect()->route($panel['route_rekap'], ['tab' => 'tidak_lengkap'] + $request->query());
    }

    public function koreksiHarian(Request $request, AbsensiHarian $harian): RedirectResponse
    {
        $this->ensureLaboranKoreksi();

        $validated = $request->validate([
            'status' => ['required', 'in:lengkap,tidak_lengkap,alfa,masuk,belum'],
            'catatan_koreksi' => ['nullable', 'string', 'max:2000'],
        ]);

        $harian->update([
            'status' => $validated['status'],
            'catatan_koreksi' => $validated['catatan_koreksi'],
            'koreksi_oleh' => $request->user()->id,
            'koreksi_at' => now(),
        ]);

        $this->syncSideRecordsAfterKoreksi($harian, $validated['status'], $request->user()->id);
        \App\Support\AbsensiSnapshotSignals::committed(['rekap'], 'staff');

        return back()->with('success', 'Koreksi absensi disimpan.');
    }

    public function batalkanAlfa(Request $request, AbsensiAlfa $alfa): RedirectResponse
    {
        $this->ensureLaboranKoreksi();

        $validated = $request->validate([
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $alfa->update([
            'aktif' => false,
            'catatan' => $validated['catatan'] ?? $alfa->catatan,
            'dibatalkan_oleh' => $request->user()->id,
            'dibatalkan_at' => now(),
        ]);

        AbsensiHarian::query()
            ->where('user_id', $alfa->user_id)
            ->whereDate('tanggal', $alfa->tanggal)
            ->where('sesi', $alfa->sesi)
            ->update(['status' => 'belum', 'koreksi_oleh' => $request->user()->id, 'koreksi_at' => now()]);
        \App\Support\AbsensiSnapshotSignals::committed(['rekap'], 'staff');

        return back()->with('success', 'Alfa dibatalkan.');
    }

    public function selesaiTidakLengkap(Request $request, AbsensiHarian $harian): RedirectResponse
    {
        $this->ensureLaboranKoreksi();

        if ($harian->status !== 'tidak_lengkap') {
            return back()->with('error', 'Baris ini bukan status tidak lengkap.');
        }

        $validated = $request->validate([
            'catatan_koreksi' => ['nullable', 'string', 'max:2000'],
        ]);

        AbsensiTidakLengkap::query()->updateOrCreate(
            [
                'user_id' => $harian->user_id,
                'tanggal' => $harian->tanggal,
                'sesi' => $harian->sesi,
                'jenis' => 'tanpa_pulang',
            ],
            [
                'selesai' => true,
                'catatan_koreksi' => $validated['catatan_koreksi'] ?? $harian->catatan_koreksi,
                'ditangani_oleh' => $request->user()->id,
                'ditangani_at' => now(),
            ]
        );
        \App\Support\AbsensiSnapshotSignals::committed(['rekap'], 'staff');

        return back()->with('success', 'Kasus tidak lengkap ditandai selesai (tetap tercatat di rekap).');
    }

    public function storeKomentar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isLaboran() && !$user->isDosen()) {
            abort(403);
        }

        $validated = $request->validate([
            'target_type' => ['required', 'in:alfa,tidak_lengkap,harian'],
            'target_id' => ['required', 'integer', 'min:1'],
            'isi' => ['required', 'string', 'max:2000'],
        ]);

        AbsensiKomentar::query()->create([
            'user_id' => $user->id,
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'],
            'isi' => $validated['isi'],
        ]);

        return back()->with('success', 'Komentar ditambahkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilter(Request $request): array
    {
        $mulai = (string) $request->query('tanggal_mulai', now()->toDateString());
        $akhir = (string) $request->query('tanggal_akhir', now()->toDateString());

        try {
            $mulai = Carbon::parse($mulai)->toDateString();
            $akhir = Carbon::parse($akhir)->toDateString();
        } catch (\Throwable) {
            $mulai = $akhir = now()->toDateString();
        }

        if ($request->has('date') && !$request->has('tanggal_mulai')) {
            $mulai = $akhir = Carbon::parse((string) $request->query('date'))->toDateString();
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'kelas' => (string) $request->query('kelas', ''),
            'tim' => (string) $request->query('tim', ''),
            'angkatan' => (string) $request->query('angkatan', ''),
            'program_studi_id' => (string) $request->query('program_studi_id', ''),
            'sesi' => (string) $request->query('sesi', ''),
            'shift' => (string) $request->query('shift', ''),
            'semester' => (string) $request->query('semester', ''),
            'status' => (string) $request->query('status', ''),
            'tanggal_mulai' => $mulai,
            'tanggal_akhir' => $akhir,
            'date' => $mulai,
        ];
    }

    /**
     * Rekap harian operasional: selalu hari ini (bukan rentang seperti laporan periodik).
     *
     * @return array<string, mixed>
     */
    private function resolveRekapFilter(Request $request): array
    {
        $filter = $this->resolveFilter($request);
        $today = now()->toDateString();
        $filter['tanggal_mulai'] = $today;
        $filter['tanggal_akhir'] = $today;
        $filter['date'] = $today;

        return $filter;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return list<array<string, mixed>>
     */
    private function buildRekapRows(array $filter): array
    {
        $students = $this->scopedMahasiswaQuery()
            ->with('programStudi')
            ->when($filter['q'] !== '', function ($q) use ($filter) {
                $kw = $filter['q'];
                $q->where(fn ($inner) => $inner
                    ->where('nama_lengkap', 'like', "%{$kw}%")
                    ->orWhere('nitk_nidn', 'like', "%{$kw}%"));
            })
            ->when($filter['kelas'] !== '', fn ($q) => $q->where('kelas', $filter['kelas']))
            ->when($filter['tim'] !== '', fn ($q) => $q->where('tim', $filter['tim']))
            ->when($filter['angkatan'] !== '', fn ($q) => $q->where('angkatan', $filter['angkatan']))
            ->when($filter['program_studi_id'] !== '', fn ($q) => $q->where('program_studi_id', $filter['program_studi_id']))
            ->when($filter['shift'] !== '', fn ($q) => $q->where('shift', $filter['shift']))
            ->when($filter['semester'] !== '', fn ($q) => $q->where('semester', $filter['semester']))
            ->orderBy('nama_lengkap')
            ->get();

        $harian = AbsensiHarian::query()
            ->with(['masukLog', 'pulangLog'])
            ->whereBetween('tanggal', [$filter['tanggal_mulai'], $filter['tanggal_akhir']])
            ->whereIn('user_id', $students->pluck('id'))
            ->when($filter['sesi'] !== '', fn ($q) => $q->where('sesi', $filter['sesi']))
            ->when($filter['status'] !== '', fn ($q) => $q->where('status', $filter['status']))
            ->get()
            ->groupBy(fn (AbsensiHarian $h) => $h->user_id . '|' . $h->tanggal->toDateString() . '|' . $h->sesi);

        $rows = [];
        $dates = $this->dateRange($filter['tanggal_mulai'], $filter['tanggal_akhir']);

        foreach ($students as $student) {
            foreach ($dates as $date) {
                $sesiDefault = MahasiswaShiftResolver::resolve(
                    $student,
                    Carbon::parse($date, config('app.timezone'))
                ) ?? 'pagi';
                $key = $student->id . '|' . $date . '|' . $sesiDefault;
                $dayRow = $harian->get($key)?->first();
                if (!$dayRow) {
                    $status = match (true) {
                        AbsensiKalenderLibur::isLibur($date) => 'libur',
                        !AbsensiKebijakan::isHariWajib($date) => 'tidak_wajib',
                        default => 'belum',
                    };
                    if ($filter['sesi'] !== '' && $filter['sesi'] !== $sesiDefault) {
                        continue;
                    }
                    if ($filter['status'] !== '' && $filter['status'] !== $status) {
                        continue;
                    }
                    $rows[] = $this->rowFromStudent($student, $date, $sesiDefault, $status, null);

                    continue;
                }

                if ($filter['sesi'] !== '' && $filter['sesi'] !== $dayRow->sesi) {
                    continue;
                }
                if ($filter['status'] !== '' && $filter['status'] !== $dayRow->status) {
                    continue;
                }
                $rows[] = $this->rowFromStudent($student, $date, $dayRow->sesi, $dayRow->status, $dayRow);
            }
        }

        return $this->sortRekapRowsByTapPriority($rows);
    }

    /**
     * Daftar tap masuk hari ini — urut kronologis (siapa duluan absen).
     *
     * @return list<array<string, mixed>>
     */
    private function buildTapMasukFeed(string $date): array
    {
        return LogAbsensi::query()
            ->with(['user:id,nama_lengkap,nitk_nidn,kelas,tim,shift'])
            ->where('jenis', 'masuk')
            ->whereDate('waktu_tap', $date)
            ->orderBy('waktu_tap')
            ->get()
            ->values()
            ->map(function (LogAbsensi $log, int $index): array {
                $tap = $log->waktu_tap?->timezone('Asia/Jakarta');

                return [
                    'urutan' => $index + 1,
                    'nama' => $log->user?->nama_lengkap ?? '—',
                    'nim' => $log->user?->nitk_nidn ?? '—',
                    'kelas' => $log->user?->kelas ?: '—',
                    'tim' => $log->user?->tim ?: '—',
                    'shift' => $log->user?->shift ?: $log->sesi,
                    'sesi' => $log->sesi,
                    'slot_lab' => $log->slot_lab,
                    'menit_terlambat' => (int) $log->menit_terlambat,
                    'waktu_tap' => $tap?->format('H:i:s') ?? '—',
                    'waktu_tap_label' => $tap?->translatedFormat('d M Y H:i:s') ?? '—',
                ];
            })
            ->all();
    }

    /**
     * Sudah tap di atas (terbaru duluan), status masalah di tengah, belum/tidak wajib di bawah.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortRekapRowsByTapPriority(array $rows): array
    {
        return collect($rows)->sort(function (array $a, array $b): int {
            $tier = function (array $row): int {
                $jam = $row['jam_masuk'] ?? '—';
                if ($jam !== '—' && $jam !== '') {
                    return 0; // sudah tap → depan
                }

                return match ((string) ($row['status'] ?? '')) {
                    'tidak_lengkap', 'masuk', 'alfa' => 1,
                    'belum' => 2,
                    default => 3, // tidak_wajib, libur
                };
            };

            $ta = $tier($a);
            $tb = $tier($b);
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            // Yang baru tap muncul di depan (jam masuk descending)
            if ($ta === 0) {
                $ja = (string) ($a['jam_masuk'] ?? '');
                $jb = (string) ($b['jam_masuk'] ?? '');
                if ($ja !== $jb) {
                    return strcmp($jb, $ja);
                }
            }

            return strcasecmp((string) ($a['nama'] ?? ''), (string) ($b['nama'] ?? ''));
        })->values()->all();
    }

    /**
     * @return list<string>
     */
    private function dateRange(string $start, string $end): array
    {
        $dates = [];
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);
        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromStudent(User $student, string $date, string $sesi, string $status, ?AbsensiHarian $harian): array
    {
        return [
            'harian_id' => $harian?->id,
            'tanggal' => $date,
            'nama' => $student->nama_lengkap,
            'nim' => $student->nitk_nidn,
            'kelas' => $student->kelas ?: '—',
            'tim' => $student->tim ?: '—',
            'angkatan' => $student->angkatan ?: '—',
            'prodi' => $student->programStudi?->nama ?? '—',
            'semester' => $student->semester ? (string) $student->semester : '—',
            'shift' => $student->shift ?: '—',
            'sesi' => $sesi,
            'status' => $status,
            'jam_masuk' => $harian?->masukLog?->waktu_tap?->timezone('Asia/Jakarta')->format('H:i:s') ?? '—',
            'jam_pulang' => $harian?->pulangLog?->waktu_tap?->timezone('Asia/Jakarta')->format('H:i:s') ?? '—',
            'menit_terlambat_masuk' => $harian?->menit_terlambat_masuk ?? 0,
            'menit_terlambat_pulang' => $harian?->menit_terlambat_pulang ?? 0,
            'slot_lab_masuk' => $harian?->slot_lab_masuk,
            'pulang_lembur' => (bool) ($harian?->pulang_lembur ?? false),
            'lengkap_at' => $harian?->lengkap_at,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{items: list<array<string, mixed>>, totals: array<string, int>}
     */
    private function buildRekapPerbandingan(array $rows): array
    {
        $items = [];
        $totals = [
            'hadir' => 0,
            'terlambat' => 0,
            'lengkap' => 0,
            'tidak_lengkap' => 0,
            'alfa' => 0,
            'belum' => 0,
            'tidak_wajib' => 0,
        ];

        foreach ($rows as $row) {
            $item = $this->classifyRekapRow($row);
            $items[] = $item;
            foreach (array_keys($totals) as $key) {
                if (!empty($item[$key])) {
                    $totals[$key]++;
                }
            }
        }

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function classifyRekapRow(array $row): array
    {
        $status = (string) ($row['status'] ?? 'belum');
        $telatMasuk = (int) ($row['menit_terlambat_masuk'] ?? 0);
        $telatPulang = (int) ($row['menit_terlambat_pulang'] ?? 0);
        $terlambat = $telatMasuk > 0 || $telatPulang > 0;

        $isLengkap = $status === 'lengkap';
        $isTidakLengkap = in_array($status, ['tidak_lengkap', 'masuk'], true);
        $isAlfa = $status === 'alfa';
        $isBelum = $status === 'belum';
        $isTidakWajib = in_array($status, ['tidak_wajib', 'libur'], true);
        $isHadir = $isLengkap && !$terlambat;
        $isTerlambat = $terlambat && in_array($status, ['masuk', 'lengkap', 'tidak_lengkap'], true);

        $ringkasan = match (true) {
            $status === 'libur' => 'Libur',
            $status === 'tidak_wajib' => 'Tidak wajib',
            $isAlfa => 'Alfa',
            $isBelum => 'Belum tap',
            $status === 'masuk' => 'Hanya masuk',
            $status === 'tidak_lengkap' => 'Tidak lengkap',
            $isLengkap && $isTerlambat => 'Lengkap · terlambat',
            $isLengkap => 'Hadir tepat waktu',
            $status === 'sakit' => 'Sakit',
            $status === 'izin' => 'Izin',
            default => ucfirst(str_replace('_', ' ', $status)),
        };

        return [
            'nim' => $row['nim'] ?? '—',
            'nama' => $row['nama'] ?? '—',
            'kelas' => $row['kelas'] ?? '—',
            'tim' => $row['tim'] ?? '—',
            'semester' => $row['semester'] ?? '—',
            'prodi' => $row['prodi'] ?? '—',
            'shift' => $row['shift'] ?? '—',
            'sesi' => $row['sesi'] ?? '—',
            'status' => $status,
            'ringkasan' => $ringkasan,
            'hadir' => $isHadir,
            'terlambat' => $isTerlambat,
            'lengkap' => $isLengkap,
            'tidak_lengkap' => $isTidakLengkap,
            'alfa' => $isAlfa,
            'belum' => $isBelum,
            'tidak_wajib' => $isTidakWajib,
            'menit_terlambat_masuk' => $telatMasuk,
            'menit_terlambat_pulang' => $telatPulang,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    private function rekapSummary(array $rows): array
    {
        $perbandingan = $this->buildRekapPerbandingan($rows);
        $totals = $perbandingan['totals'];

        $collection = collect($rows);
        $total = $collection->count();
        $lengkap = $collection->where('status', 'lengkap')->count();
        $alfa = $collection->where('status', 'alfa')->count();
        $tidakLengkap = $collection->where('status', 'tidak_lengkap')->count();
        $masuk = $collection->where('status', 'masuk')->count();
        $sakit = $collection->where('status', 'sakit')->count();
        $izin = $collection->where('status', 'izin')->count();
        $belum = $collection->where('status', 'belum')->count();
        $tidakWajib = $collection->where('status', 'tidak_wajib')->count();
        $libur = $collection->where('status', 'libur')->count();
        $denominator = $total - $tidakWajib - $libur;

        return [
            'total' => $total,
            'hadir' => $totals['hadir'],
            'terlambat' => $totals['terlambat'],
            'lengkap' => $lengkap,
            'alfa' => $alfa,
            'tidak_lengkap' => $tidakLengkap,
            'masuk' => $masuk,
            'sakit' => $sakit,
            'izin' => $izin,
            'belum' => $belum,
            'tidak_wajib' => $tidakWajib,
            'libur' => $libur,
            'denominator_wajib' => max(0, $denominator),
            'persentase_lengkap' => $denominator > 0 ? round(($lengkap / $denominator) * 100, 1) : 0,
        ];
    }

    /**
     * Finalisasi alfa & tidak lengkap untuk rentang tanggal lampau (mis. scheduler mati saat laptop dimatikan).
     */
    private function ensureAbsensiSessionsClosedForRange(AbsensiSessionCloser $closer, string $mulai, string $akhir): void
    {
        try {
            $start = Carbon::parse($mulai)->startOfDay();
            $end = Carbon::parse($akhir)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($end->lt($start)) {
            return;
        }

        $today = now()->toDateString();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dateStr = $cursor->toDateString();
            if ($dateStr > $today || !AbsensiKebijakan::isHariWajib($dateStr)) {
                continue;
            }

            $closer->closeForDate($dateStr, force: true);
        }
    }

    private function syncSideRecordsAfterKoreksi(AbsensiHarian $harian, string $status, int $staffId): void
    {
        $base = [
            'user_id' => $harian->user_id,
            'tanggal' => $harian->tanggal,
            'sesi' => $harian->sesi,
        ];

        if (in_array($status, ['lengkap', 'sakit', 'izin', 'masuk', 'belum'], true)) {
            AbsensiAlfa::query()
                ->where($base)
                ->where('aktif', true)
                ->update([
                    'aktif' => false,
                    'dibatalkan_oleh' => $staffId,
                    'dibatalkan_at' => now(),
                    'kategori' => in_array($status, ['sakit', 'izin'], true) ? $status : 'alfa',
                ]);
        }

        if ($status === 'alfa') {
            AbsensiAlfa::query()->updateOrCreate($base, [
                'sumber' => 'manual',
                'aktif' => true,
                'kategori' => 'alfa',
                'dibatalkan_at' => null,
                'dibatalkan_oleh' => null,
            ]);
        }

        if ($status === 'tidak_lengkap') {
            AbsensiTidakLengkap::query()->updateOrCreate(
                array_merge($base, ['jenis' => 'tanpa_pulang']),
                ['selesai' => false]
            );
        } elseif (in_array($status, ['lengkap', 'sakit', 'izin', 'alfa', 'belum'], true)) {
            AbsensiTidakLengkap::query()
                ->where($base)
                ->update([
                    'selesai' => true,
                    'ditangani_oleh' => $staffId,
                    'ditangani_at' => now(),
                ]);
        }
    }

    /**
     * @return Builder<User>
     */
    private function scopedMahasiswaQuery(): Builder
    {
        return User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true);
    }

    private function applyStudentFiltersOnHarian($query, array $filter): void
    {
        if ($filter['q'] !== '' || $filter['kelas'] !== '' || $filter['tim'] !== '' || $filter['angkatan'] !== '' || $filter['program_studi_id'] !== '' || ($filter['semester'] ?? '') !== '') {
            $query->whereHas('user', function ($uq) use ($filter) {
                if ($filter['q'] !== '') {
                    $kw = $filter['q'];
                    $uq->where(fn ($inner) => $inner
                        ->where('nama_lengkap', 'like', "%{$kw}%")
                        ->orWhere('nitk_nidn', 'like', "%{$kw}%"));
                }
                if ($filter['kelas'] !== '') {
                    $uq->where('kelas', $filter['kelas']);
                }
                if ($filter['tim'] !== '') {
                    $uq->where('tim', $filter['tim']);
                }
                if ($filter['angkatan'] !== '') {
                    $uq->where('angkatan', $filter['angkatan']);
                }
                if ($filter['program_studi_id'] !== '') {
                    $uq->where('program_studi_id', $filter['program_studi_id']);
                }
                if (($filter['semester'] ?? '') !== '') {
                    $uq->where('semester', $filter['semester']);
                }
            });
        }
    }

    private function staffCanKoreksi(): bool
    {
        return (bool) auth()->user()?->isLaboran();
    }

    private function ensureLaboranKoreksi(): void
    {
        if (!$this->staffCanKoreksi()) {
            abort(403, 'Hanya admin laboran yang dapat mengkoreksi absensi.');
        }
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AbsensiAlfa>  $items
     */
    private function attachHarianToAlfaItems($items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $harianQuery = AbsensiHarian::query();
        $harianQuery->where(function ($outer) use ($items) {
            foreach ($items as $alfa) {
                $outer->orWhere(function ($q) use ($alfa) {
                    $q->where('user_id', $alfa->user_id)
                        ->whereDate('tanggal', $alfa->tanggal)
                        ->where('sesi', $alfa->sesi);
                });
            }
        });

        $map = $harianQuery->get()->keyBy(
            fn (AbsensiHarian $h) => $h->user_id . '|' . $h->tanggal->toDateString() . '|' . $h->sesi
        );

        foreach ($items as $alfa) {
            $key = $alfa->user_id . '|' . $alfa->tanggal->toDateString() . '|' . $alfa->sesi;
            $alfa->setRelation('harian', $map->get($key));
        }
    }

    private function applyStudentFiltersOnRelation($query, array $filter): void
    {
        if ($filter['q'] !== '' || $filter['kelas'] !== '' || $filter['tim'] !== '' || $filter['angkatan'] !== '' || $filter['program_studi_id'] !== '' || ($filter['semester'] ?? '') !== '') {
            $query->whereHas('user', function ($uq) use ($filter) {
                if ($filter['q'] !== '') {
                    $kw = $filter['q'];
                    $uq->where(fn ($inner) => $inner
                        ->where('nama_lengkap', 'like', "%{$kw}%")
                        ->orWhere('nitk_nidn', 'like', "%{$kw}%"));
                }
                if ($filter['kelas'] !== '') {
                    $uq->where('kelas', $filter['kelas']);
                }
                if ($filter['tim'] !== '') {
                    $uq->where('tim', $filter['tim']);
                }
                if ($filter['angkatan'] !== '') {
                    $uq->where('angkatan', $filter['angkatan']);
                }
                if ($filter['program_studi_id'] !== '') {
                    $uq->where('program_studi_id', $filter['program_studi_id']);
                }
                if (($filter['semester'] ?? '') !== '') {
                    $uq->where('semester', $filter['semester']);
                }
            });
        }
    }

    private function distinctMahasiswaColumn(string $column)
    {
        return $this->scopedMahasiswaQuery()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    /**
     * @return array<string, string>
     */
    private function panelMeta(): array
    {
        $user = auth()->user();
        $isLaboran = $user && $user->isLaboran();

        return [
            'is_laboran' => $isLaboran,
            'sidebar_include' => $isLaboran ? 'admin._sidebar' : 'dosen._sidebar',
            'view_rekap' => 'absensi.rekap',
            'view_alfa' => 'absensi.alfa',
            'view_tidak_lengkap' => 'absensi.tidak_lengkap',
            'route_rekap' => $isLaboran ? 'admin.attendance' : 'dosen.absensi',
            'route_rekap_snapshot' => $isLaboran ? 'admin.attendance.snapshot' : 'dosen.absensi.snapshot',
            'route_alfa' => $isLaboran ? 'admin.absensi.alfa' : 'dosen.absensi.alfa',
            'route_tidak_lengkap' => $isLaboran ? 'admin.absensi.tidak-lengkap' : 'dosen.absensi.tidak-lengkap',
            'route_komentar' => $isLaboran ? 'admin.absensi.komentar' : 'dosen.absensi.komentar',
            'route_koreksi' => $isLaboran ? 'admin.absensi.koreksi' : 'dosen.absensi.koreksi',
            'route_alfa_batalkan' => $isLaboran ? 'admin.absensi.alfa.batalkan' : 'dosen.absensi.alfa.batalkan',
            'route_tl_selesai' => $isLaboran ? 'admin.absensi.tidak-lengkap.selesai' : 'dosen.absensi.tidak-lengkap.selesai',
            'route_rekap_export' => $isLaboran ? 'admin.attendance.export' : 'dosen.absensi.export',
            'route_rekap_print' => $isLaboran ? 'admin.attendance.print' : 'dosen.absensi.print',
        ];
    }

    private function ensureLaboran(): void
    {
        if (!auth()->user()?->isLaboran()) {
            abort(403);
        }
    }

    private function ensureStaffAbsensi(): void
    {
        $user = auth()->user();
        if (!$user || (!$user->isLaboran() && !$user->isDosen())) {
            abort(403);
        }
    }
}
