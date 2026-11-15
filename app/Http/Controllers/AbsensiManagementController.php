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
use App\Support\AdminAudit;
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
    use \App\Http\Controllers\Concerns\AdminShellProps;

    public function __construct(
        private readonly AbsensiTapEvaluator $evaluator
    ) {}

    public function settings(Request $request)
    {
        $this->ensureLaboran();

        $sesi = AbsensiSesiSetting::query()->orderBy('shift')->get();
        $pagi = $sesi->firstWhere('shift', 'pagi');
        $kebijakan = AbsensiKebijakan::config();
        $jadwalMingguan = \App\Support\AbsensiEffectiveSchedule::normalizeWeeklyDays($kebijakan['jadwal_mingguan'] ?? []);
        $kalenderLibur = \Illuminate\Support\Facades\Schema::hasTable('absensi_kalender_libur')
            ? AbsensiKalenderLibur::query()->orderByDesc('tanggal')->limit(100)->get()
            : collect();
        $ops = AbsensiReadinessReport::build();
        $slotLabPagi = $pagi ? AbsensiLabSlotResolver::slotScheduleForPagi($pagi) : [];
        $user = $request->user();

        $activeDows = collect($jadwalMingguan)->filter(fn ($r) => ! empty($r['aktif']))->keys()->sort()->values();
        $dayNames = \App\Support\AbsensiEffectiveSchedule::DAY_NAMES;
        $kalenderItems = $kalenderLibur->map(function (AbsensiKalenderLibur $kl) {
            $endSame = ! $kl->tanggal_selesai || $kl->tanggal_selesai->toDateString() === $kl->tanggal?->toDateString();
            return [
                'id' => $kl->id,
                'tanggal' => $kl->tanggal?->toDateString(),
                'tanggal_selesai' => $kl->tanggal_selesai?->toDateString(),
                'rentang' => $kl->tanggal?->translatedFormat('d M Y') . ($endSame ? '' : ' – ' . $kl->tanggal_selesai->translatedFormat('d M Y')),
                'jenis' => $kl->effectiveJenis(),
                'dampak' => $kl->effectiveDampak(),
                'jam_khusus' => $kl->effectiveDampak() === 'ubah_jam'
                    ? substr((string) $kl->jam_masuk_khusus, 0, 5) . '–' . substr((string) $kl->jam_pulang_khusus, 0, 5)
                    : null,
                'keterangan' => $kl->keterangan,
                'updated_label' => $kl->updated_at?->diffForHumans(),
            ];
        })->values()->all();

        // Agenda terdekat/aktif untuk kartu "Kalender Akademik" di ringkasan.
        $today = today()->toDateString();
        $kalenderAktif = $kalenderLibur->first(function (AbsensiKalenderLibur $kl) use ($today) {
            return $kl->tanggal?->toDateString() <= $today
                && ($kl->tanggal_selesai?->toDateString() ?: $kl->tanggal?->toDateString()) >= $today;
        });
        $kalenderBerikut = $kalenderAktif ? null : $kalenderLibur
            ->filter(fn (AbsensiKalenderLibur $kl) => $kl->tanggal?->toDateString() >= $today)
            ->sortBy('tanggal')->first();

        // Riwayat perubahan terakhir — dari timestamp nyata (bukan log fiksi).
        $riwayat = collect();
        foreach ($sesi as $s) {
            if ($s->updated_at) {
                $riwayat->push(['at' => $s->updated_at, 'label' => "Jadwal sesi {$s->shift} diperbarui", 'by' => 'Pengaturan sesi']);
            }
        }
        foreach ($kalenderLibur->sortByDesc('updated_at')->take(3) as $kl) {
            if ($kl->updated_at) {
                $riwayat->push(['at' => $kl->updated_at, 'label' => "Kalender: {$kl->keterangan}", 'by' => 'Kalender akademik']);
            }
        }
        if (($hb = $ops['heartbeat'] ?? null) && ! empty($hb['at'])) {
            $riwayat->push(['at' => $hb['at'], 'label' => 'Aksi scheduler: ' . ($hb['command'] ?? 'heartbeat'), 'by' => 'Sistem']);
        }
        if ($set = \App\Models\SystemSetting::query()->where('key', AbsensiKebijakan::SETTING_KEY)->first()) {
            if ($set->updated_at) {
                $riwayat->push(['at' => $set->updated_at, 'label' => 'Kebijakan absensi diperbarui (' . AbsensiKebijakan::labelMode() . ')', 'by' => 'Kebijakan']);
            }
        }
        $riwayat = $riwayat->sortByDesc('at')->take(6)->map(fn ($r) => [
            'label' => $r['label'], 'by' => $r['by'],
            'time_label' => $r['at']->diffForHumans(),
            'time_exact' => $r['at']->translatedFormat('d M Y, H:i'),
        ])->values()->all();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('AturAbsensi', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'kebijakan' => [
                'mode' => $kebijakan['mode'] ?? 'kamis',
                'mode_label' => AbsensiKebijakan::labelMode(),
                'effective_from' => $kebijakan['effective_from'] ?? null,
                'jadwal_mingguan' => $jadwalMingguan,
            ],
            'hari_nama' => $dayNames,
            'sesi' => $sesi->map(fn (AbsensiSesiSetting $s) => [
                'shift' => $s->shift,
                'jam_masuk' => substr((string) $s->jam_masuk, 0, 5),
                'jam_mulai_tap_masuk' => substr((string) ($s->jam_mulai_tap_masuk ?? '06:30'), 0, 5),
                'jam_pulang' => substr((string) $s->jam_pulang, 0, 5),
                'toleransi_masuk_menit' => (int) $s->toleransi_masuk_menit,
                'toleransi_pulang_menit' => (int) ($s->toleransi_pulang_menit ?? $s->toleransi_masuk_menit),
                'grace_alfa_menit' => (int) $s->grace_alfa_menit,
                'aktif' => (bool) $s->aktif,
                'timezone' => $s->timezone,
                'updated_label' => $s->updated_at?->diffForHumans(),
            ])->values()->all(),
            'slot_lab' => $slotLabPagi,
            'kalender' => $kalenderItems,
            'ringkasan' => [
                'hari_aktif' => $activeDows->count(),
                'hari_label' => $activeDows->isEmpty()
                    ? 'Tidak ada'
                    : ($dayNames[$activeDows->first()] . ' – ' . $dayNames[$activeDows->last()]),
                'slot_per_hari' => count($slotLabPagi),
                'slot_menit' => 55,
                'sesi_rentang' => $pagi
                    ? substr((string) $pagi->jam_masuk, 0, 5) . ' – ' . substr((string) $pagi->jam_pulang, 0, 5)
                    : null,
                'kalender_aktif' => $kalenderAktif ? ['keterangan' => $kalenderAktif->keterangan, 'rentang' => $kalenderItems[array_search($kalenderAktif->id, array_column($kalenderItems, 'id'))]['rentang'] ?? ''] : null,
                'kalender_berikut' => $kalenderBerikut ? ['keterangan' => $kalenderBerikut->keterangan, 'tanggal' => $kalenderBerikut->tanggal?->translatedFormat('d M Y')] : null,
                'kalender_total' => $kalenderLibur->count(),
            ],
            'riwayat' => $riwayat,
            'ops' => [
                'today' => $ops['today'],
                'yesterday' => $ops['yesterday'],
                'kebijakan_label' => $ops['kebijakan_label'],
                'hari_wajib_hari_ini' => $ops['hari_wajib_hari_ini'],
                'kamis_ready' => $ops['kamis_ready'],
                'heartbeat' => [
                    'is_ok' => $ops['heartbeat']['is_ok'],
                    'at_label' => $ops['heartbeat']['at']?->diffForHumans() ?? 'Belum ada heartbeat',
                    'at_exact' => $ops['heartbeat']['at']?->translatedFormat('d M Y, H:i'),
                    'minutes_ago' => $ops['heartbeat']['minutes_ago'],
                    'command' => $ops['heartbeat']['command'],
                ],
                'stats' => $ops['stats'],
                'close_hints' => $ops['close_hints'],
                'issues' => $ops['issues'],
                'pbl' => ['ok' => $ops['pbl']['ok'] ?? true, 'issues' => $ops['pbl']['issues'] ?? []],
            ],
            'can' => [
                'update' => (bool) ($user?->isAdmin()) && \App\Support\PermissionRegistry::canCurrent('settings', 'update'),
            ],
            'tab' => in_array($request->query('tab'), ['ringkasan', 'jadwal', 'kalender', 'operasional'], true)
                ? $request->query('tab')
                : 'ringkasan',
            'urls' => [
                'index' => route('admin.absensi.settings'),
                'kebijakan' => route('admin.absensi.kebijakan'),
                'sesi' => route('admin.absensi.sesi'),
                'libur' => route('admin.absensi.libur'),
                'tutup_sesi' => route('admin.absensi.tutup-sesi'),
                'generate_alfa' => route('admin.absensi.generate-alfa'),
                'rekap' => route('admin.attendance'),
                'pbl' => route('admin.pbl.index'),
                'fingerprint' => route('admin.fingerprint'),
                'pengaturan' => route('admin.settings'),
            ],
        ]);
    }

    public function kalender(Request $request)
    {
        $this->ensureLaboran();
        $user = $request->user();

        $monthParam = (string) $request->query('month', now('Asia/Jakarta')->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            $monthParam = now('Asia/Jakarta')->format('Y-m');
        }
        $monthStart = Carbon::createFromFormat('Y-m-d', $monthParam . '-01', 'Asia/Jakarta')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $todayStr = now('Asia/Jakarta')->toDateString();

        $jenisLabels = [
            'libur' => 'Hari Libur', 'peringatan' => 'Peringatan Nasional',
            'agenda' => 'Agenda Kegiatan', 'ujian' => 'Ujian',
            'perubahan_jam' => 'Absensi Khusus', 'informasi' => 'Informasi',
        ];
        $dampakLabels = [
            'batal_wajib' => 'Absensi tidak wajib pada tanggal ini',
            'ubah_jam' => 'Absensi tetap wajib dengan jam khusus',
            'tetap_wajib' => 'Tidak mengubah kewajiban absensi',
        ];
        $prioritasLabels = [
            'rendah' => 'Rendah', 'normal' => 'Normal',
            'penting' => 'Penting', 'mendesak' => 'Mendesak',
        ];

        $entries = \Illuminate\Support\Facades\Schema::hasTable('absensi_kalender_libur')
            ? AbsensiKalenderLibur::query()
                ->whereDate('tanggal', '<=', $monthEnd->toDateString())
                ->where(fn ($q) => $q
                    ->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $monthStart->toDateString()))
                ->orderBy('tanggal')
                ->get()
            : collect();

        $serialize = function (AbsensiKalenderLibur $e) use ($jenisLabels, $dampakLabels, $prioritasLabels) {
            $jenis = $e->effectiveJenis();
            $dampak = $e->effectiveDampak();
            $same = ! $e->tanggal_selesai || $e->tanggal_selesai->toDateString() === $e->tanggal?->toDateString();

            return [
                'id' => (int) $e->id,
                'jenis' => $jenis,
                'jenis_label' => $jenisLabels[$jenis] ?? ucfirst($jenis),
                'dampak' => $dampak,
                'dampak_label' => $dampakLabels[$dampak] ?? $dampak,
                'prioritas' => $e->effectivePrioritas(),
                'prioritas_label' => $prioritasLabels[$e->effectivePrioritas()] ?? 'Normal',
                'keterangan' => $e->keterangan,
                'tanggal' => $e->tanggal?->toDateString(),
                'tanggal_selesai' => $e->tanggal_selesai?->toDateString(),
                'rentang' => $e->tanggal?->translatedFormat('d M Y') . ($same ? '' : ' – ' . $e->tanggal_selesai->translatedFormat('d M Y')),
                'tanggal_label' => $e->tanggal?->translatedFormat('l, d F Y'),
                'jam_khusus' => $dampak === 'ubah_jam'
                    ? substr((string) $e->jam_masuk_khusus, 0, 5) . ' – ' . substr((string) $e->jam_pulang_khusus, 0, 5)
                    : null,
            ];
        };

        $byDate = [];
        foreach ($entries as $e) {
            $from = Carbon::parse(max($e->tanggal->toDateString(), $monthStart->toDateString()));
            $to = Carbon::parse(min(
                $e->tanggal_selesai?->toDateString() ?: $e->tanggal->toDateString(),
                $monthEnd->toDateString()
            ));
            for ($c = $from->copy(); $c->lte($to); $c->addDay()) {
                $byDate[$c->toDateString()][] = $serialize($e);
            }
        }

        $kebijakan = AbsensiKebijakan::config();
        $weekly = \App\Support\AbsensiEffectiveSchedule::normalizeWeeklyDays($kebijakan['jadwal_mingguan'] ?? []);
        $dayNames = \App\Support\AbsensiEffectiveSchedule::DAY_NAMES;
        $sesiPagi = AbsensiSesiSetting::query()->where('shift', 'pagi')->first();

        $days = [];
        for ($c = $monthStart->copy(); $c->lte($monthEnd); $c->addDay()) {
            $dStr = $c->toDateString();
            $dow = $c->dayOfWeek;
            $w = $weekly[$dow] ?? null;
            $wajib = (bool) ($w['aktif'] ?? false);
            $days[] = [
                'tanggal' => $dStr,
                'day' => (int) $c->day,
                'dow' => $dow,
                'is_today' => $dStr === $todayStr,
                'is_wajib' => $wajib,
                'jam' => $wajib ? ($w['jam_masuk'] ?? '07:50') . ' – ' . ($w['jam_pulang'] ?? '17:00') : null,
                'agenda' => $byDate[$dStr] ?? [],
            ];
        }

        $activeDows = collect($weekly)->filter(fn ($r) => ! empty($r['aktif']))->keys()->sort()->values();
        $agendaBulan = $entries->map(fn ($e) => $serialize($e))->values()->all();
        $countJenis = fn (array $js) => collect($agendaBulan)->whereIn('jenis', $js)->count();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('KalenderAgenda', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'month' => [
                'value' => $monthParam,
                'label' => $monthStart->translatedFormat('F Y'),
                'prev' => $monthStart->copy()->subMonth()->format('Y-m'),
                'next' => $monthStart->copy()->addMonth()->format('Y-m'),
                'is_current' => $monthParam === now('Asia/Jakarta')->format('Y-m'),
            ],
            'first_offset' => ($monthStart->dayOfWeek + 6) % 7,
            'days' => $days,
            'agenda' => $agendaBulan,
            'kpi' => [
                'agenda' => count($agendaBulan),
                'hari_aktif' => $activeDows->count(),
                'hari_label' => $activeDows->isEmpty() ? 'Tidak ada'
                    : $activeDows->map(fn ($d) => $dayNames[$d])->implode(', '),
                'libur' => $countJenis(['libur']),
                'peringatan' => $countJenis(['peringatan', 'informasi']),
            ],
            'jadwal' => [
                'hari_label' => $activeDows->isEmpty() ? 'Tidak ada hari wajib'
                    : $activeDows->map(fn ($d) => $dayNames[$d])->implode(', '),
                'jam' => $sesiPagi
                    ? substr((string) $sesiPagi->jam_masuk, 0, 5) . ' – ' . substr((string) $sesiPagi->jam_pulang, 0, 5)
                    : null,
                'toleransi' => $sesiPagi ? (int) $sesiPagi->toleransi_masuk_menit : null,
            ],
            'can_update' => \App\Support\PermissionRegistry::canCurrent('attendance', 'update'),
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'kalender')
                    : route('admin.kalender'),
                'store' => route('admin.kalender.agenda'),
                'update_base' => route('admin.kalender') . '/agenda',
                'destroy_base' => route('admin.kalender') . '/agenda',
                'atur_absensi' => route('admin.absensi.settings'),
            ],
        ]);
    }

    public function updateKebijakan(Request $request): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'mode' => ['required', 'in:harian,kamis,jadwal_mingguan'],
            'effective_from' => ['nullable', 'date'],
            'jadwal_mingguan' => ['nullable', 'array'],
            'jadwal_mingguan.*.aktif' => ['nullable'],
            'jadwal_mingguan.*.jam_masuk' => ['nullable', 'date_format:H:i'],
            'jadwal_mingguan.*.jam_pulang' => ['nullable', 'date_format:H:i'],
            'jadwal_mingguan.*.jam_mulai_tap_masuk' => ['nullable', 'date_format:H:i'],
            'jadwal_mingguan.*.keterangan' => ['nullable', 'string', 'max:120'],
        ]);

        $rawDays = $request->input('jadwal_mingguan');
        $normalizedInput = [
            'mode' => $validated['mode'],
        ];

        if ($request->exists('effective_from')) {
            $normalizedInput['effective_from'] = $validated['effective_from'] ?? null;
        }

        if (is_array($rawDays)) {
            $daysPayload = [];
            foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
                $row = $rawDays[$dow] ?? $rawDays[(string) $dow] ?? [];
                if (! is_array($row)) {
                    $row = [];
                }
                $daysPayload[$dow] = [
                    'aktif' => ! empty($row['aktif']),
                    'jam_masuk' => $row['jam_masuk'] ?? null,
                    'jam_pulang' => $row['jam_pulang'] ?? null,
                    'jam_mulai_tap_masuk' => $row['jam_mulai_tap_masuk'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                ];
            }
            $normalizedInput['jadwal_mingguan'] = $daysPayload;
        }

        AbsensiKebijakan::savePolicy($normalizedInput);

        AdminAudit::catat('absensi.kebijakan', 'Kebijakan absensi', AbsensiKebijakan::labelMode());

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

        AdminAudit::catat('absensi.sesi', 'Sesi '.$validated['shift']);

        return back()->with('success', 'Jadwal sesi ' . $validated['shift'] . ' disimpan.');
    }

    public function storeLibur(Request $request): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'tanggal' => ['required_without:tanggal_list', 'nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'tanggal_list' => ['nullable', 'array', 'max:62'],
            'tanggal_list.*' => ['date'],
            'jenis' => ['nullable', 'in:libur,ujian,perubahan_jam,informasi,peringatan,agenda'],
            'dampak_absensi' => ['nullable', 'in:batal_wajib,ubah_jam,tetap_wajib'],
            'prioritas' => ['nullable', 'in:rendah,normal,penting,mendesak'],
            'jam_masuk_khusus' => ['nullable', 'date_format:H:i'],
            'jam_pulang_khusus' => ['nullable', 'date_format:H:i'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $jenis = $validated['jenis'] ?? 'libur';
        $dampak = $validated['dampak_absensi'] ?? match ($jenis) {
            'perubahan_jam' => 'ubah_jam',
            'ujian', 'informasi', 'peringatan', 'agenda' => 'tetap_wajib',
            default => 'batal_wajib',
        };

        $defaultKet = match ($jenis) {
            'ujian' => 'Periode ujian akademik',
            'perubahan_jam' => 'Perubahan jam absensi',
            'informasi' => 'Agenda akademik',
            'peringatan' => 'Peringatan nasional',
            'agenda' => 'Agenda kegiatan laboratorium',
            default => 'Hari libur',
        };

        // Mode tandai tanggal — satu entri per tanggal yang dipilih agar
        // masing-masing bisa dikelola/dihapus terpisah.
        $dates = ! empty($validated['tanggal_list'])
            ? collect($validated['tanggal_list'])->unique()->sort()->values()
            : collect([$validated['tanggal']]);

        $payload = [
            'keterangan' => ! empty($validated['keterangan']) ? $validated['keterangan'] : $defaultKet,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('absensi_kalender_libur', 'tanggal_selesai')) {
            $payload['tanggal_selesai'] = ! empty($validated['tanggal_selesai'])
                ? $validated['tanggal_selesai']
                : $dates->first();
            $payload['jenis'] = $jenis;
            $payload['dampak_absensi'] = $dampak;
            $payload['prioritas'] = $validated['prioritas'] ?? 'normal';
            $payload['jam_masuk_khusus'] = $dampak === 'ubah_jam' && ! empty($validated['jam_masuk_khusus'])
                ? $validated['jam_masuk_khusus'] . ':00'
                : null;
            $payload['jam_pulang_khusus'] = $dampak === 'ubah_jam' && ! empty($validated['jam_pulang_khusus'])
                ? $validated['jam_pulang_khusus'] . ':00'
                : null;
        }

        foreach ($dates as $tgl) {
            $row = $payload;
            if (array_key_exists('tanggal_selesai', $row) && ! empty($validated['tanggal_list'])) {
                $row['tanggal_selesai'] = $tgl;
            }
            AbsensiKalenderLibur::query()->updateOrCreate(['tanggal' => $tgl], $row);
        }

        AdminAudit::catat('absensi.libur', $dates->implode(', '), $validated['keterangan'] ?? null);

        return back()->with('success', 'Kalender & pengecualian akademik berhasil disimpan.');
    }

    /** Ubah satu entri agenda — dipakai modal "Ubah Agenda". */
    public function updateLibur(Request $request, AbsensiKalenderLibur $libur): RedirectResponse
    {
        $this->ensureLaboran();

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'jenis' => ['nullable', 'in:libur,ujian,perubahan_jam,informasi,peringatan,agenda'],
            'dampak_absensi' => ['nullable', 'in:batal_wajib,ubah_jam,tetap_wajib'],
            'prioritas' => ['nullable', 'in:rendah,normal,penting,mendesak'],
            'jam_masuk_khusus' => ['nullable', 'date_format:H:i'],
            'jam_pulang_khusus' => ['nullable', 'date_format:H:i'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $jenis = $validated['jenis'] ?? $libur->effectiveJenis();
        $dampak = $validated['dampak_absensi'] ?? $libur->effectiveDampak();

        $libur->fill([
            'tanggal' => $validated['tanggal'],
            'tanggal_selesai' => $validated['tanggal_selesai'] ?: $validated['tanggal'],
            'jenis' => $jenis,
            'dampak_absensi' => $dampak,
            'prioritas' => $validated['prioritas'] ?? $libur->effectivePrioritas(),
            'jam_masuk_khusus' => $dampak === 'ubah_jam' && ! empty($validated['jam_masuk_khusus'])
                ? $validated['jam_masuk_khusus'] . ':00'
                : null,
            'jam_pulang_khusus' => $dampak === 'ubah_jam' && ! empty($validated['jam_pulang_khusus'])
                ? $validated['jam_pulang_khusus'] . ':00'
                : null,
            'keterangan' => $validated['keterangan'] ?? $libur->keterangan,
        ])->save();

        return back()->with('success', 'Agenda kalender diperbarui.');
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

    public function rekap(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $state = $this->rekapState($request);

        // Dosen → halaman React (Inertia); laboran tetap layout Blade yang sama.
        if ($user && $user->isDosen() && ! $user->isLaboran()) {
            \Inertia\Inertia::setRootView('app-admin');

            return \Inertia\Inertia::render('DosenAbsensi', [
                'menu' => $this->dosenSidebarMenu($user),
                'notif' => $this->dosenNotifications($user),
                'initial' => $this->rekapSnapshotData($request, $state),
                'filter' => $state['filter'],
                'options' => [
                    'kelas' => $state['kelasOptions'],
                    'tim' => $state['timOptions'],
                    'angkatan' => $state['angkatanOptions'],
                    'semester' => $state['semesterOptions'],
                    'prodi' => $state['prodiOptions']->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama])->all(),
                    'sesi' => ['pagi', 'malam'],
                    'shift' => ['pagi', 'malam'],
                ],
                'meta' => [
                    'kebijakan_label' => $state['kebijakanLabel'],
                    'hari_ini_wajib' => (bool) $state['hariIniWajib'],
                    'tanggal' => now('Asia/Jakarta')->translatedFormat('l, d F Y'),
                ],
                'urls' => [
                    'snapshot' => route('dosen.absensi.snapshot'),
                    'export' => route('dosen.absensi.export'),
                    'print' => route('dosen.absensi.print'),
                    'reports' => route('dosen.reports'),
                ],
            ]);
        }

        // Admin & laboran → halaman React RekapHarian (app-admin).
        $dateObj = Carbon::parse($state['filter']['date']);
        $jadwalPagi = $state['jadwalSesi']['pagi'] ?? null;
        $jadwalMalam = $state['jadwalSesi']['malam'] ?? null;
        $jamAwal = $jadwalPagi?->jam_masuk ? substr((string) $jadwalPagi->jam_masuk, 0, 5) : '07:50';
        $jamAkhir = ($jadwalMalam?->jam_pulang ?? $jadwalPagi?->jam_pulang)
            ? substr((string) ($jadwalMalam?->jam_pulang ?? $jadwalPagi->jam_pulang), 0, 5)
            : '17:00';

        $snap = $this->rekapLivePayload($request, $state);

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('RekapHarian', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'initial' => $snap,
            'filter' => $state['filter'],
            'options' => [
                'kelas' => $state['kelasOptions'],
                'tim' => $state['timOptions'],
            ],
            'meta' => [
                'tanggal' => $dateObj->translatedFormat('l, d F Y'),
                'jadwal_label' => $jamAwal . ' – ' . $jamAkhir,
                'kebijakan_label' => $state['kebijakanLabel'],
                'hari_ini_wajib' => \App\Support\AbsensiKebijakan::isHariWajib($dateObj),
            ],
            'urls' => [
                'index' => route('admin.attendance'),
                'live' => route('admin.attendance.live'),
                'export' => route('admin.attendance.export'),
                'print' => route('admin.attendance.print'),
                'reports' => route('admin.reports'),
            ],
        ]);
    }

    /**
     * Payload rekap harian lengkap (semua baris + feed tap) — dipakai halaman
     * React RekapHarian dan endpoint polling realtime admin.attendance.live.
     *
     * @return array<string, mixed>
     */
    private function rekapLivePayload(Request $request, array $state): array
    {
        $snap = $this->rekapSnapshotData($request, $state);
        $snap['rows'] = collect($state['allRows'])->map(fn (array $row) => [
            'nim' => $row['nim'],
            'nama' => $row['nama'],
            'kelas' => $row['kelas'],
            'tim' => $row['tim'],
            'sesi' => $row['sesi'],
            'status' => $row['status'],
            'jam_masuk' => $row['jam_masuk'],
            'jam_pulang' => $row['jam_pulang'],
            'menit_terlambat_masuk' => $row['menit_terlambat_masuk'],
            'slot_lab_masuk' => $row['slot_lab_masuk'],
        ])->values()->all();
        // Tren tap masuk per slot lab — dasar grafik "Tren Kehadiran per Sesi".
        $snap['sesi_trend'] = collect($snap['tap_masuk'])
            ->groupBy(fn ($t) => $t['slot_lab'] ?: 'Lainnya')
            ->map(fn ($items, $slot) => ['slot' => $slot, 'total' => $items->count()])
            ->sortBy('slot')->values()->all();

        return $snap;
    }

    /**
     * Endpoint polling ringan untuk halaman RekapHarian — data segar tanpa
     * refresh: summary + seluruh baris + feed tap masuk + tren per sesi.
     */
    public function rekapLive(Request $request): JsonResponse
    {
        $state = $this->rekapState($request);

        return response()->json([
            'ok' => true,
            'synced_at' => now('Asia/Jakarta')->format('H:i:s'),
            'snapshot' => $this->rekapLivePayload($request, $state),
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $this->ensureStaffAbsensi();
        $state = $this->rekapState($request);
        $user = $request->user();
        $scope = $user->isLaboran() ? 'laboran' : 'dosen';
        $payload = $this->rekapSnapshotData($request, $state);
        $paginator = $state['rows']->withPath(route($state['panel']['route_rekap']));
        $payload['regions'] = [
            'rows' => view('absensi._rekap_detail_rows', [
                'rows' => $paginator,
                'canKoreksi' => (bool) $state['canKoreksi'],
                'panel' => $state['panel'],
            ])->render(),
            'taps' => view('absensi._rekap_tap_rows', [
                'tapMasukHariIni' => $state['tapMasukHariIni'],
            ])->render(),
            'pager' => view('components.table-pagination', ['paginator' => $paginator])->render(),
        ];

        return response()->json(AbsensiSnapshot::envelope('rekap', $scope, $payload));
    }

    /**
     * Payload rekap tanpa HTML region — dipakai endpoint snapshot dan
     * props awal halaman React dosen.
     *
     * @return array<string, mixed>
     */
    private function rekapSnapshotData(Request $request, array $state): array
    {
        $user = $request->user();
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
        $revision = substr(hash('sha256', json_encode([
            $state['tab'],
            $state['tabCounts'],
            $pageRows,
            $state['tapMasukHariIni'],
        ])), 0, 16);

        $kelasScopeEmpty = $user && $user->isDosen() && ! $user->isLaboran()
            && ! \App\Support\DosenKelasScope::hasSupervision($user);

        return [
            'query' => AbsensiSnapshot::canonicalQuery($request),
            'revision' => $revision,
            'summary_mode' => $state['summaryMode'],
            'tab' => $state['tab'],
            'tab_counts' => $state['tabCounts'],
            'can_koreksi' => $canKoreksi,
            'kelas_scope_empty' => $kelasScopeEmpty,
            'kelas_scope_message' => $kelasScopeEmpty
                ? 'Belum ada mahasiswa dalam bimbingan Anda. Minta laboran menugaskan kelas di Manajemen User atau pastikan Anda terdaftar sebagai manpro/co-manpro tim PBL.'
                : null,
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
        ];
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
            'allRows' => $allRows,
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
        $reqDate = (string) $request->query('date', '');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate) ? $reqDate : $today;
        $filter['tanggal_mulai'] = $date;
        $filter['tanggal_akhir'] = $date;
        $filter['date'] = $date;

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
        $query = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true);

        $actor = request()->user();
        if ($actor && $actor->isDosen() && !$actor->isLaboran()) {
            return \App\Support\DosenKelasScope::applySupervisedMahasiswaQuery($query, $actor);
        }

        return $query;
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
