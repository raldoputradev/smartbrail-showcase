<?php

namespace App\Http\Controllers;

use App\Models\AccountApprovalLog;
use App\Models\LogAbsensi;
use App\Models\Suggestion;
use App\Models\MesinIot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Http\Controllers\Concerns\HandlesPeriodicReports;
use App\Support\AccountActivation;
use App\Support\DashboardDemoData;
use App\Support\DashboardStatLabels;
use App\Support\LoginSecurityService;
use App\Support\FingerIdAllocator;
use App\Support\FingerprintSyncLogService;
use App\Support\FingerprintSyncPolicy;
use App\Support\FingerprintSyncService;
use App\Support\MahasiswaShiftResolver;
use App\Support\PendingAccountApproval;
use App\Support\PendingEmailVerification;
use App\Support\PendingLoginUnlock;
use App\Support\PblDataReadiness;
use App\Support\TablePagination;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use HandlesPeriodicReports, Concerns\AdminShellProps, Concerns\DashboardMiniCalendar;

    public function dashboard(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['sometimes', 'in:hari,minggu,bulan,tahun'],
            'tanggal' => ['sometimes', 'date'],
            'kelas' => ['sometimes', 'nullable', 'string', 'max:80'],
            'tim' => ['sometimes', 'nullable', 'string', 'max:80'],
            'aktivitas' => ['sometimes', 'nullable', 'string', Rule::in(['semua', 'absensi', ''])],
        ]);

        $analytics = $this->computeAdminAnalytics(
            $validated['mode'] ?? 'minggu',
            $validated['tanggal'] ?? now()->toDateString(),
            $validated['kelas'] ?? '',
            $validated['tim'] ?? '',
            $validated['aktivitas'] ?? 'semua'
        );

        $latestTaps = LogAbsensi::query()
            ->with([
                'user:id,nama_lengkap,nitk_nidn,role,kelas,tim,semester,semester_type,profile_photo_path',
            ])
            ->latest('waktu_tap')
            ->limit(10)
            ->get();

        $mhsAktif = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->get();

        $opsShortcuts = [
            'pending_approval' => PendingAccountApproval::count(),
            'new_suggestions' => Suggestion::query()->where('status', 'baru')->count(),
            'pbl_readiness' => PblDataReadiness::report(),
            'enroll_belum' => $mhsAktif->filter(fn (User $u) => ! $u->hasFingerprintEnrolled())->count(),
            'enroll_done' => $mhsAktif->filter(fn (User $u) => $u->hasFingerprintEnrolled())->count(),
        ];

        // DEMO-DASHBOARD — overlay angka ops agar card animasi terisi
        if (! empty($analytics['ops']) && is_array($analytics['ops'])) {
            $opsShortcuts = array_merge($opsShortcuts, $analytics['ops']);
        }

        return $this->renderAdminDashboard($request, $analytics, $latestTaps, $opsShortcuts);
    }

    /**
     * Dashboard admin — React + TypeScript via Inertia (root view app-admin).
     * Data diserialkan eksplisit; permission menu difilter di sini, bukan di frontend.
     */
    private function renderAdminDashboard(
        Request $request,
        array $analytics,
        $latestTaps,
        array $opsShortcuts
    ) {
        $bms = $this->bmsDashboardData();
        $s = $analytics['stats'];
        $user = $request->user();

        $feed = $latestTaps->map(fn ($tap) => [
            'waktu' => optional($tap->waktu_tap)->format('H:i') ?? '—',
            'nama' => $tap->user->nama_lengkap ?? '-',
            'nim' => $tap->user->nitk_nidn ?? '-',
            'kelas_tim' => ($tap->user->kelas ?: '—') . ' · ' . ($tap->user->tim ?: '—'),
            'foto' => $tap->user->profile_photo_url ?? null,
        ])->all();

        $pengajuan = collect($bms['pengajuan_terbaru'] ?? [])->map(fn ($p) => [
            'pemohon' => $p->snapshot['pengaju']['name'] ?? $p->pengaju->nama_lengkap ?? '-',
            'ruangan' => $p->ruangan->nama ?? '—',
            'tanggal' => optional($p->tanggal)->translatedFormat('d M Y') ?? (string) $p->tanggal,
            'jam' => substr((string) $p->jam_mulai, 0, 5) . ' – ' . substr((string) $p->jam_selesai, 0, 5),
            'status' => $p->statusEfektif(),
            'foto' => $p->pengaju->profile_photo_url ?? null,
        ])->all();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('Dashboard', [
            'is_admin' => (bool) $user->isAdmin(),
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'stats' => [
                'total_pengguna' => $s['total_user'],
                'pengguna_aktif' => $s['user_aktif'],
                'pengguna_pending' => $s['user_pending'],
                'pengguna_online' => \App\Support\Presence::onlineUsers(),
                'sidik_jari_terdaftar' => (int) ($opsShortcuts['enroll_done'] ?? 0),
                'ruangan_aktif' => (int) ($bms['ruangan_aktif'] ?? 0),
                'mesin_aktif' => $s['mesin_aktif'],
                'mesin_total' => $s['mesin_total'],
                'mesin_online' => $s['mesin_online'] ?? 0,
                'mesin_meta' => $s['mesin_meta'] ?? '',
                'pengajuan_pending' => (int) ($bms['pengajuan_menunggu'] ?? 0),
            ],
            'line' => $analytics['line'],
            'donut' => [
                'labels' => $analytics['role_donut']['labels'] ?? [],
                'values' => $analytics['role_donut']['values'] ?? [],
                'colors' => ['#22c55e', '#38bdf8', '#f59e0b', '#8b5cf6'],
                'total' => array_sum(array_map('intval', $analytics['role_donut']['values'] ?? [])),
            ],
            'status_sistem' => [
                ['nama' => 'Server Aplikasi', 'status' => 'Online', 'icon' => 'server'],
                ['nama' => 'Database', 'status' => 'Online', 'icon' => 'database'],
                ['nama' => 'Mesin IoT (' . ($s['mesin_online'] ?? 0) . '/' . ($s['mesin_aktif'] ?? 0) . ')', 'status' => 'Online', 'icon' => 'cpu'],
                ['nama' => 'Layanan Absensi', 'status' => 'Normal', 'icon' => 'clipboard-check'],
            ],
            'feed' => $feed,
            'pengajuan' => $pengajuan,
            'agenda_terdekat' => $this->dashboardAgendaTerdekat(),
            'kalender_mini' => $this->dashboardMiniCalendar($request->query('kcal')),
            'aksi_cepat' => $this->adminQuickActions(),
            'urls' => [
                'feed_semua' => route('admin.log.absensi'),
                'pengajuan_semua' => route('admin.pengajuan.index'),
                'kalender' => route('admin.kalender'),
                'minical' => route('admin.dashboard.minical'),
                'data' => $request->routeIs('dev.preview.*')
                    ? route('dev.preview.admin.dashboard-data')
                    : route('admin.dashboard.data'),
            ],
        ]);
    }


    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    private function adminQuickActions(): array
    {
        $actions = [
            ['perm' => 'settings', 'label' => 'Atur Absensi', 'url' => 'admin.absensi.settings', 'icon' => 'calendar'],
            ['perm' => 'ruangan', 'label' => 'Kelola Ruangan', 'url' => 'admin.ruangan.index', 'icon' => 'door-open'],
            ['perm' => 'users', 'label' => 'Kelola Pengguna', 'url' => 'admin.users', 'icon' => 'users', 'admin' => true],
            ['perm' => 'reports', 'label' => 'Buat Laporan', 'url' => 'admin.reports', 'icon' => 'file-text'],
        ];

        return collect($actions)
            ->filter(fn ($a) => \App\Support\PermissionRegistry::canCurrent($a['perm'])
                && (empty($a['admin']) || request()->user()->isAdmin()))
            ->map(fn ($a) => ['label' => $a['label'], 'url' => route($a['url']), 'icon' => $a['icon']])
            ->values()
            ->all();
    }

    /** Data Smart BRAIL untuk dashboard — berbeda antara Admin (sistem) dan Laboran (operasional). */
    private function bmsDashboardData(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ruangan_pengajuan')) {
            return ['available' => false];
        }
        $today = now()->toDateString();

        return [
            'available' => true,
            'ruangan_aktif' => \App\Models\Ruangan::where('aktif', true)->count(),
            'pengajuan_menunggu' => \App\Models\RuanganPengajuan::whereIn('status', \App\Models\RuanganPengajuan::STATUS_AKTIF_MENUNGGU)->count(),
            'pengajuan_hari_ini' => \App\Models\RuanganPengajuan::with(['ruangan:id,nama', 'pengaju:id,nama_lengkap'])
                ->whereDate('tanggal', $today)->orderBy('jam_mulai')->limit(12)->get(),
            'pengajuan_terbaru' => \App\Models\RuanganPengajuan::with(['ruangan:id,nama', 'pengaju:id,nama_lengkap'])
                ->orderByDesc('created_at')->limit(5)->get(),
            'izin_aktif' => \App\Models\RuanganIzin::berlakuPada(now())->count(),
            'petugas_aktif' => \App\Models\RuanganPetugas::where('status', 'aktif')->count(),
            'mesin_terikat' => \App\Models\MesinIot::whereNotNull('ruangan_id')->where('is_active', true)->count(),
            'event_terakhir' => \App\Models\RuanganAccessEvent::with('ruangan:id,nama')->orderByDesc('terjadi_pada')->limit(5)->get(),
        ];
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:hari,minggu,bulan,tahun'],
            'tanggal' => ['required', 'date'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'aktivitas' => ['nullable', 'string', Rule::in(['semua', 'absensi', ''])],
        ]);

        return response()->json($this->computeAdminAnalytics(
            $validated['mode'],
            $validated['tanggal'],
            $validated['kelas'] ?? '',
            $validated['tim'] ?? '',
            $validated['aktivitas'] ?? 'semua'
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function computeAdminAnalytics(string $mode, string $tanggal, string $kelas, string $tim, string $aktivitas): array
    {
        $anchor = Carbon::parse($tanggal)->startOfDay();
        [$curStart, $curEnd, $prevStart, $prevEnd, $label] = $this->resolveAdminPeriod($mode, $anchor);
        $aktivitas = in_array($aktivitas, ['semua', 'absensi'], true) ? $aktivitas : 'semua';

        $totalUser = User::count();
        $totalDosen = User::where('role', 'dosen')->count();
        $totalMhs = User::where('role', 'mahasiswa')->count();
        $totalLaboran = User::whereIn('role', ['laboran', 'admin'])->count();
        $userAktif = User::where('is_active', true)->count();
        $userPending = max(0, $totalUser - $userAktif);

        $fleet = MesinIot::query()->where('tipe', '!=', 'loker');
        $mesinTotal = (clone $fleet)->count();
        $mesinAktif = (clone $fleet)->where('is_active', true)->count();
        $mesinOnline = (clone $fleet)->online()->count();
        $mesinOffline = max(0, $mesinAktif - $mesinOnline);
        $mesinNonaktif = max(0, $mesinTotal - $mesinAktif);
        $mesinTipeCount = (int) (clone $fleet)
            ->whereNotNull('tipe')
            ->where('tipe', '!=', '')
            ->select('tipe')
            ->distinct()
            ->get()
            ->count();

        $mesinMeta = $mesinOnline . ' online · ' . $mesinOffline . ' offline';
        if ($mesinNonaktif > 0) {
            $mesinMeta .= ' · ' . $mesinNonaktif . ' nonaktif';
        } elseif ($mesinTipeCount > 0) {
            $mesinMeta .= ' · ' . $mesinTipeCount . ' tipe';
        }

        $mesinAbsensi = MesinIot::where('tipe', 'absensi')->count();

        $tapsCur = $this->countAbsensiByFilter($curStart, $curEnd, $kelas, $tim);
        $tapsPrev = $this->countAbsensiByFilter($prevStart, $prevEnd, $kelas, $tim);

        $line = $this->buildAdminLine($mode, $anchor, $kelas, $tim, $aktivitas);

        $payload = [
            'period_label' => $label,
            'mode' => $mode,
            'tanggal' => $anchor->toDateString(),
            'kelas' => $kelas,
            'tim' => $tim,
            'aktivitas' => $aktivitas,
            'stats' => [
                'total_user' => $totalUser,
                'total_mahasiswa' => $totalMhs,
                'total_dosen' => $totalDosen,
                'total_laboran' => $totalLaboran,
                'user_aktif' => $userAktif,
                'user_pending' => $userPending,
                'mesin_total' => $mesinTotal,
                'mesin_aktif' => $mesinAktif,
                'mesin_online' => $mesinOnline,
                'mesin_offline' => $mesinOffline,
                'mesin_nonaktif' => $mesinNonaktif,
                'mesin_tipe_count' => $mesinTipeCount,
                'mesin_meta' => $mesinMeta,
                'mesin_absensi' => $mesinAbsensi,
                'tap_absensi' => $tapsCur,
                'tap_prev' => $tapsPrev,
                'tap_delta' => $tapsCur - $tapsPrev,
                'tap_meta' => DashboardStatLabels::tapAbsensiMeta($tapsCur - $tapsPrev, $tapsPrev, $mode),
            ],
            'line' => $line,
            'role_donut' => [
                'labels' => ['Mahasiswa', 'Dosen', 'Laboran'],
                'values' => [$totalMhs, $totalDosen, $totalLaboran],
            ],
            'mesin_bar' => [
                'labels' => ['Online', 'Offline', 'Nonaktif'],
                'values' => [$mesinOnline, $mesinOffline, $mesinNonaktif],
            ],
        ];

        return $payload;
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:Carbon,3:Carbon,4:string}
     */
    private function resolveAdminPeriod(string $mode, Carbon $anchor): array
    {
        if ($mode === 'hari') {
            $s = $anchor->copy()->startOfDay();
            $e = $anchor->copy()->endOfDay();
            $ps = $s->copy()->subDay()->startOfDay();
            $pe = $s->copy()->subDay()->endOfDay();
            return [$s, $e, $ps, $pe, 'Harian · ' . $s->translatedFormat('d M Y')];
        }
        if ($mode === 'minggu') {
            $s = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $e = $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $ps = $s->copy()->subWeek();
            $pe = $e->copy()->subWeek();
            return [$s, $e, $ps, $pe, 'Mingguan · ' . $s->translatedFormat('d M') . ' – ' . $e->translatedFormat('d M Y')];
        }
        if ($mode === 'bulan') {
            $s = $anchor->copy()->startOfMonth()->startOfDay();
            $e = $anchor->copy()->endOfMonth()->endOfDay();
            $ps = $anchor->copy()->subMonth()->startOfMonth()->startOfDay();
            $pe = $anchor->copy()->subMonth()->endOfMonth()->endOfDay();
            return [$s, $e, $ps, $pe, 'Bulanan · ' . $s->translatedFormat('F Y')];
        }
        $s = $anchor->copy()->startOfYear()->startOfDay();
        $e = $anchor->copy()->endOfYear()->endOfDay();
        $ps = $anchor->copy()->subYear()->startOfYear()->startOfDay();
        $pe = $anchor->copy()->subYear()->endOfYear()->endOfDay();
        return [$s, $e, $ps, $pe, 'Tahunan · ' . $s->year];
    }

    private function countAbsensiByFilter(Carbon $from, Carbon $to, string $kelas, string $tim): int
    {
        $q = LogAbsensi::query()->whereBetween('waktu_tap', [$from, $to]);
        if ($kelas !== '' || $tim !== '') {
            $q->whereHas('user', function ($qb) use ($kelas, $tim) {
                $qb->where('role', 'mahasiswa');
                if ($kelas !== '') {
                    $qb->where('kelas', $kelas);
                }
                if ($tim !== '') {
                    $qb->where('tim', $tim);
                }
            });
        }
        return (int) $q->count();
    }


    /**
     * @return array{labels: array<int, string>, absensi: array<int, int>, subtitle: string}
     */
    private function buildAdminLine(string $mode, Carbon $anchor, string $kelas, string $tim, string $aktivitas): array
    {
        // Series dinamis per tipe mesin — mudah ditambah tipe baru tanpa ubah chart frontend.
        $typeMeta = [
            'absensi' => ['label' => 'Absensi', 'color' => '#0284c7'],
        ];
        $extraPalette = ['#0f766e', '#b45309', '#be123c', '#64748b', '#0891b2'];
        $extraIdx = 0;
        foreach (MesinIot::query()->select('tipe')->distinct()->pluck('tipe') as $tipe) {
            $key = strtolower((string) $tipe);
            if ($key === '' || $key === 'loker' || isset($typeMeta[$key])) {
                continue;
            }
            $typeMeta[$key] = [
                'label' => Str::title(str_replace('_', ' ', $key)),
                'color' => $extraPalette[$extraIdx % count($extraPalette)],
            ];
            $extraIdx++;
        }

        $typeMeta = array_intersect_key($typeMeta, ['absensi' => true]);

        $labels = [];
        $buckets = [];
        foreach (array_keys($typeMeta) as $key) {
            $buckets[$key] = [];
        }

        $pushBucket = function (Carbon $from, Carbon $to) use (&$buckets, $kelas, $tim) {
            foreach (array_keys($buckets) as $key) {
                $buckets[$key][] = match ($key) {
                    'absensi' => $this->countAbsensiByFilter($from, $to, $kelas, $tim),
                    default => 0, // tipe baru: sambungkan counter log-nya di sini
                };
            }
        };

        if ($mode === 'hari') {
            $day = $anchor->copy()->startOfDay();
            for ($h = 6; $h <= 22; $h++) {
                $hs = $day->copy()->setTime($h, 0, 0);
                $he = $day->copy()->setTime($h, 59, 59);
                $labels[] = sprintf('%02d:00', $h);
                $pushBucket($hs, $he);
            }
            $rangeNote = 'Per jam (06:00–22:00)';
        } elseif ($mode === 'minggu') {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            for ($i = 0; $i < 7; $i++) {
                $d = $start->copy()->addDays($i);
                $labels[] = $d->translatedFormat('D');
                $pushBucket($d->copy()->startOfDay(), $d->copy()->endOfDay());
            }
            $rangeNote = 'Per hari (Senin–Minggu)';
        } elseif ($mode === 'bulan') {
            $start = $anchor->copy()->startOfMonth()->startOfDay();
            $end = $anchor->copy()->endOfMonth()->endOfDay();
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $labels[] = $d->format('j');
                $pushBucket($d->copy()->startOfDay(), $d->copy()->endOfDay());
            }
            $rangeNote = 'Per hari dalam bulan';
        } else {
            for ($m = 1; $m <= 12; $m++) {
                $s = Carbon::create($anchor->year, $m, 1)->startOfDay();
                $e = $s->copy()->endOfMonth()->endOfDay();
                $labels[] = $s->translatedFormat('M');
                $pushBucket($s, $e);
            }
            $rangeNote = 'Per bulan sepanjang tahun';
        }

        $series = [];
        foreach ($typeMeta as $key => $meta) {
            $series[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'data' => $buckets[$key] ?? [],
            ];
        }

        $absensi = $buckets['absensi'] ?? array_fill(0, count($labels), 0);

        return [
            'labels' => $labels,
            'series' => $series,
            'absensi' => $absensi,
            'subtitle' => 'Event mesin per tipe · ' . $rangeNote,
            'chart_type' => 'stacked_bar',
        ];
    }

    public function users(Request $request)
    {
        if ($request->filled('approval') && !$request->filled('approval_status')) {
            $request->merge(['approval_status' => $request->input('approval')]);
        }

        $query = User::query();

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $query->where(function ($q) use ($keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('nitk_nidn', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('role') && in_array($request->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true)) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('approval_status') && in_array($request->approval_status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('verify_status') && in_array($request->verify_status, ['otp_pending', 'ready_approve', 'menunggu_aktivasi', 'all'], true)) {
            if ($request->verify_status === 'menunggu_aktivasi') {
                $query->where('approval_status', AccountActivation::STATUS);
            } elseif ($request->verify_status === 'otp_pending') {
                $query->whereIn('role', ['mahasiswa', 'dosen'])
                    ->whereNull('email_verified_at')
                    ->where(function ($q) {
                        $q->where('approval_status', 'pending')->orWhereNull('approval_status');
                    })
                    ->where(function ($q) {
                        $q->whereNull('approval_status')->orWhere('approval_status', '!=', 'rejected');
                    });
            } elseif ($request->verify_status === 'ready_approve') {
                $query->whereIn('id', PendingAccountApproval::query()->pluck('id'));
            }
        }

        if ($request->filled('angkatan')) {
            $query->where('angkatan', (int) $request->angkatan);
        }

        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->semester);
        }

        if ($request->filled('login_lock') && $request->login_lock === 'locked') {
            $query->whereIn('id', PendingLoginUnlock::query()->pluck('id'));
        }

        $perPage = in_array((int) $request->per_page, [5, 10, 15, 25, 50], true) ? (int) $request->per_page : 15;
        $users = $query->with('kelasCakupan:id,user_id,kelas')->latest()->paginate($perPage)->appends($request->except(['fragment']));

        $programStudi = \App\Models\ProgramStudi::query()->where('aktif', true)->orderBy('nama')->get();
        $pblTimOptions = \App\Support\MahasiswaProfileOptions::pblTeamOptions();
        $angkatanYears = \App\Support\MahasiswaProfileOptions::angkatanYears();
        $loginLockedCount = PendingLoginUnlock::count();

        $pendingApprovalCount = PendingAccountApproval::count();
        $pendingApprovalUsers = PendingAccountApproval::recent(6);
        $pendingOtpCount = PendingEmailVerification::count();
        $pendingOtpUsers = PendingEmailVerification::recent(6);
        $awaitingActivationCount = User::query()->where('approval_status', AccountActivation::STATUS)->count();

        $onboardingMahasiswa = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->where('approved_at', '>=', now()->subDays(14))
            ->orderByDesc('approved_at')
            ->limit(12)
            ->get()
            ->filter(fn (User $u) => blank($u->tim) || blank($u->shift) || ! $u->hasFingerprintEnrolled())
            ->values();

        $roleCounts = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $mahasiswaTotal = (int) ($roleCounts['mahasiswa'] ?? 0);
        $mahasiswaEnrolled = $mahasiswaTotal > 0
            ? (int) User::query()
                ->where('role', 'mahasiswa')
                ->whereNotNull('finger_template')
                ->where('finger_template', '!=', '')
                ->where('finger_template', '!=', \App\Support\FingerprintTemplateCodec::LEGACY_PLACEHOLDER)
                ->count()
            : 0;

        $userStats = [
            'total' => (int) User::query()->count(),
            'active' => (int) User::query()->where('is_active', true)->count(),
            'mahasiswa' => $mahasiswaTotal,
            'dosen' => (int) ($roleCounts['dosen'] ?? 0),
            'laboran' => (int) ($roleCounts['laboran'] ?? 0),
            'admin' => (int) ($roleCounts['admin'] ?? 0),
            'approved' => (int) User::query()->where('approval_status', 'approved')->count(),
            'pending_pipeline' => $pendingOtpCount + $pendingApprovalCount,
            'locked' => $loginLockedCount,
            'enrolled' => $mahasiswaEnrolled,
            'enroll_pct' => $mahasiswaTotal > 0 ? round(($mahasiswaEnrolled / $mahasiswaTotal) * 100) : 0,
            'new_30d' => (int) User::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        if ($request->user()?->role === 'admin') {
            $userStats['online'] = (int) User::query()
                ->where('last_seen_at', '>=', now()->subMinutes(5))->count();
        }

        $roleChart = [
            'labels' => ['Mahasiswa', 'Dosen', 'Laboran', 'Admin'],
            'values' => [
                $userStats['mahasiswa'],
                $userStats['dosen'],
                $userStats['laboran'],
                (int) ($roleCounts['admin'] ?? 0),
            ],
            'colors' => ['#0f766e', '#2563eb', '#ea580c', '#7c3aed'],
        ];

        $selfId = (int) auth()->id();
        // Pemantauan online khusus admin — laboran/dosen tidak melihatnya.
        $presenceForAdmin = $request->user()?->role === 'admin';
        $rows = $users->getCollection()->map(function (User $u) use ($selfId, $presenceForAdmin) {
            $approval = $u->approval_status ?? ($u->is_active ? 'approved' : 'pending');
            $awaiting = $approval === AccountActivation::STATUS;
            $enroll = null;
            if ($u->role === 'mahasiswa') {
                $enroll = $u->hasFingerprintEnrolled() ? 'enrolled'
                    : ($u->hasLegacyFingerprintPlaceholder() ? 'legacy'
                    : ($u->finger_id ? 'pending' : 'unassigned'));
            }
            $verify = $awaiting ? 'menunggu_aktivasi'
                : ($approval === 'approved' ? 'approved'
                : ($approval === 'rejected' ? 'rejected'
                : ($u->email_verified_at ? 'tunggu_admin' : 'tunggu_otp')));

            return [
                'id' => $u->id,
                'nama' => $u->nama_lengkap,
                'email' => $u->email,
                'no_hp' => $u->no_hp,
                'rfid_uid' => $u->rfid_uid,
                'has_akses_pin' => filled($u->akses_pin),
                'nitk_nidn' => $u->nitk_nidn,
                'role' => $u->role,
                'role_label' => $u->role_display,
                'avatar_url' => $u->profile_photo_url,
                'kelas' => $u->kelas,
                'tim' => $u->tim,
                'angkatan' => $u->angkatan,
                'semester' => $u->semester,
                'semester_type' => $u->semester_type,
                'program_studi_id' => $u->program_studi_id,
                'shift' => $u->shift,
                'finger_id' => $u->finger_id,
                'is_active' => (bool) $u->is_active,
                'approval_status' => $approval,
                'verify' => $verify,
                'enroll' => $enroll,
                'lock' => $u->needsAdminLoginUnlock() ? 'admin' : ($u->isLoginTemporarilyLocked() ? 'temp' : null),
                'suspicious' => $u->login_suspicious_at !== null,
                'must_change_password' => (bool) $u->must_change_password,
                'approval_note' => $u->approval_note,
                'kelas_cakupan' => $u->kelasCakupan->pluck('kelas')->values()->all(),
                'can_issue_code' => $awaiting || ($u->is_active && $approval === 'approved'),
                'is_self' => $u->id === $selfId,
                'online' => $presenceForAdmin ? $u->isOnline() : null,
                'last_seen' => $presenceForAdmin ? $u->last_seen_label : null,
                'profile_url' => route('profiles.show', $u),
            ];
        })->values()->all();

        $logAction = (string) $request->query('log_action', '');
        $aktivitas = AccountApprovalLog::query()
            ->with('actor:id,nama_lengkap,role')
            ->when($logAction !== '', fn ($q) => $q->where('action', $logAction))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AccountApprovalLog $log) => [
                'action' => $log->action,
                'label' => \App\Support\ApprovalActionLabel::label($log->action),
                'tone' => \App\Support\ApprovalActionLabel::tone($log->action),
                'subject' => $log->subjectDisplayName(),
                'actor' => $log->actor?->nama_lengkap ?? 'Sistem',
                'waktu' => $log->created_at?->diffForHumans(),
            ])->values()->all();

        $user = $request->user();
        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('Users', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'stats' => $userStats,
            'role_chart' => $roleChart,
            'users' => [
                'data' => $rows,
                'meta' => [
                    'page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'from' => $users->firstItem() ?? 0,
                    'to' => $users->lastItem() ?? 0,
                ],
            ],
            'filters' => [
                'q' => (string) $request->q,
                'role' => (string) $request->role,
                'status' => (string) $request->status,
                'approval_status' => (string) $request->approval_status,
                'verify_status' => (string) $request->verify_status,
                'login_lock' => (string) $request->login_lock,
                'angkatan' => (string) $request->angkatan,
                'semester' => (string) $request->semester,
                'per_page' => $perPage,
            ],
            'options' => [
                'program_studi' => $programStudi->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama])->values()->all(),
                'pbl_tim' => $pblTimOptions,
                'angkatan' => $angkatanYears,
                'kelas' => \App\Support\MahasiswaProfileOptions::kelasOptions(),
            ],
            'alerts' => [
                'awaiting_activation' => $awaitingActivationCount,
                'pending_otp' => $pendingOtpCount,
                'pending_approval' => $pendingApprovalCount,
                'login_locked' => $loginLockedCount,
                'onboarding' => $onboardingMahasiswa->count(),
            ],
            'aktivitas' => $aktivitas,
            'can' => [
                // Mutasi akun tetap admin-only (route diproteksi admin.only) — non-admin view saja.
                'create' => $request->user()->isAdmin() && \App\Support\PermissionRegistry::canCurrent('users', 'create'),
                'update' => $request->user()->isAdmin() && \App\Support\PermissionRegistry::canCurrent('users', 'update'),
                'delete' => $request->user()->isAdmin() && \App\Support\PermissionRegistry::canCurrent('users', 'delete'),
            ],
            'role_page' => null,
            'urls' => [
                'index' => route('admin.users'),
                'role_base' => rtrim(route('admin.users.role', 'mahasiswa'), 'mahasiswa'),
                'store' => route('admin.users.store'),
                'user_base' => rtrim(route('admin.users.update', 0), '0'),
                'import' => route('admin.users.import'),
                'riwayat' => route('admin.notifications'),
                'profiles_base' => rtrim(route('profiles.show', 0), '0'),
            ],
        ]);
    }

    /**
     * Halaman data akun per-role (Mahasiswa/Dosen/Laboran) — URL terpisah,
     * tetap memakai query & proteksi yang sama dengan daftar pengguna gabungan.
     */
    public function usersRole(Request $request, string $role)
    {
        abort_unless(in_array($role, ['mahasiswa', 'dosen', 'laboran'], true), 404);
        $request->merge(['role' => $role]);

        $response = $this->users($request);
        if (method_exists($response, 'with')) {
            $response->with('role_page', $role);
        }

        return $response;
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nitk_nidn' => ['required', 'string', 'max:50', 'unique:users,nitk_nidn'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'laboran', 'dosen', 'mahasiswa'])],
            'is_active' => ['nullable', 'boolean'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'angkatan' => ['nullable', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'semester' => ['nullable', 'integer', 'min:1', 'max:8'],
            'semester_type' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'program_studi_id' => ['nullable', 'exists:program_studi,id'],
            'shift' => ['nullable', Rule::in(['pagi', 'malam'])],
            'kelas_paralel' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'kelas_cakupan' => ['nullable', 'array', 'max:30'],
            'kelas_cakupan.*' => ['string', 'max:80'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'rfid_uid' => ['nullable', 'string', 'max:64', 'unique:users,rfid_uid'],
            'akses_pin' => ['nullable', 'string', 'min:4', 'max:20'],
        ]);

        if ($validated['role'] === 'mahasiswa') {
            validator($request->all(), [
                'kelas' => ['required', 'string', 'max:80'],
                'tim' => ['required', 'string', 'max:80'],
                'angkatan' => ['required', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
                'semester' => ['required', 'integer', 'min:1', 'max:8'],
                'semester_type' => ['required', Rule::in(['ganjil', 'genap'])],
                'program_studi_id' => ['required', 'exists:program_studi,id'],
                'shift' => ['required', Rule::in(['pagi', 'malam'])],
            ])->validate();
        }

        $email = filled($validated['email'] ?? null) ? strtolower(trim($validated['email'])) : null;

        $user = User::create([
            'nama_lengkap' => $validated['nama_lengkap'],
            'nitk_nidn' => $validated['nitk_nidn'],
            'email' => $email,
            'password' => $validated['password'],
            'role' => $validated['role'],
            'kelas' => $validated['role'] === 'mahasiswa' ? ($validated['kelas'] ?? null) : null,
            'tim' => $validated['role'] === 'mahasiswa' ? ($validated['tim'] ?? null) : null,
            'angkatan' => $validated['role'] === 'mahasiswa' ? ($validated['angkatan'] ?? null) : null,
            'semester' => $validated['role'] === 'mahasiswa' ? ($validated['semester'] ?? null) : null,
            'semester_type' => $validated['role'] === 'mahasiswa' ? ($validated['semester_type'] ?? null) : null,
            'program_studi_id' => $validated['role'] === 'mahasiswa' ? ($validated['program_studi_id'] ?? null) : null,
            'shift' => $validated['role'] === 'mahasiswa' ? ($validated['shift'] ?? null) : null,
            'kelas_paralel' => $validated['role'] === 'mahasiswa' ? ($validated['kelas_paralel'] ?? $this->inferKelasParalel($validated['kelas'] ?? null)) : null,
            'profile_completed_at' => $validated['role'] === 'mahasiswa' ? now() : null,
            'finger_id' => null,
            'is_active' => $request->boolean('is_active'),
            'approval_status' => $request->boolean('is_active') ? 'approved' : 'pending',
            'approved_by' => $request->boolean('is_active') ? auth()->id() : null,
            'approved_at' => $request->boolean('is_active') ? now() : null,
            'email_verified_at' => ($request->boolean('is_active') && $email) ? now() : null,
            'no_hp' => filled($validated['no_hp'] ?? null) ? trim($validated['no_hp']) : null,
            'rfid_uid' => filled($validated['rfid_uid'] ?? null) ? Str::upper(trim($validated['rfid_uid'])) : null,
            'akses_pin' => $request->filled('akses_pin') && ($validated['role'] ?? '') !== 'mahasiswa'
                ? Hash::make($validated['akses_pin'])
                : null,
        ]);

        if ($validated['role'] === 'mahasiswa') {
            try {
                $user = FingerIdAllocator::ensureForMahasiswa($user);
            } catch (\RuntimeException $e) {
                $user->delete();

                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        try {
            // Nomor kartu akses virtual otomatis bila UID kartu fisik belum diisi admin.
            $user = \App\Support\KartuAksesAllocator::ensure($user);
        } catch (\RuntimeException $e) {
            $user->delete();

            return back()->with('error', $e->getMessage())->withInput();
        }

        $this->syncDosenKelas($user, $validated['kelas_cakupan'] ?? null);

        $this->recordApprovalLog($request, $user, 'admin_created', $email
            ? 'User dibuat manual oleh admin.'
            : 'User dibuat manual oleh admin tanpa email — verifikasi mandiri saat login.');
        if ($request->boolean('is_active')) {
            $this->recordApprovalLog($request, $user, 'approved', 'User langsung diaktifkan saat dibuat manual oleh admin.');
        }

        $success = $validated['role'] === 'mahasiswa' && $user->finger_id
            ? "Pengguna berhasil ditambahkan. Finger ID #{$user->finger_id} otomatis."
            : 'Pengguna berhasil ditambahkan.';

        return back()->with('success', $success);
    }

    /**
     * Sinkron cakupan kelas dosen (dosen_kelas). Dipanggil saat role=dosen;
     * mengosongkan daftar saat role bukan dosen agar pemetaan tidak tertinggal.
     *
     * @param  array<int, string>|null  $kelasList
     */
    private function syncDosenKelas(User $user, ?array $kelasList): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('dosen_kelas')) {
            return;
        }

        if ($user->role !== 'dosen') {
            \App\Models\DosenKelas::query()->where('user_id', $user->id)->delete();
            \App\Support\DosenKelasScope::clearMemo();

            return;
        }

        if ($kelasList === null) {
            return;
        }

        $kelasList = collect($kelasList)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        \App\Models\DosenKelas::query()->where('user_id', $user->id)
            ->whereNotIn('kelas', $kelasList->isEmpty() ? [''] : $kelasList->all())
            ->delete();

        foreach ($kelasList as $kelas) {
            \App\Models\DosenKelas::query()->firstOrCreate([
                'user_id' => $user->id,
                'kelas' => $kelas,
            ]);
        }

        \App\Support\DosenKelasScope::clearMemo();
    }

    public function updateUser(Request $request, User $user)
    {
        $request->merge([
            'password' => $request->filled('password') ? $request->password : null,
        ]);

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nitk_nidn' => ['required', 'string', 'max:50', Rule::unique('users', 'nitk_nidn')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'laboran', 'dosen', 'mahasiswa'])],
            'is_active' => ['nullable', 'boolean'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'angkatan' => ['nullable', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'semester' => ['nullable', 'integer', 'min:1', 'max:8'],
            'semester_type' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'program_studi_id' => ['nullable', 'exists:program_studi,id'],
            'shift' => ['nullable', Rule::in(['pagi', 'malam'])],
            'kelas_paralel' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'kelas_cakupan' => ['nullable', 'array', 'max:30'],
            'kelas_cakupan.*' => ['string', 'max:80'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'rfid_uid' => ['nullable', 'string', 'max:64', Rule::unique('users', 'rfid_uid')->ignore($user->id)],
            'akses_pin' => ['nullable', 'string', 'min:4', 'max:20'],
        ]);

        if ($validated['role'] === 'mahasiswa') {
            validator($request->all(), [
                'kelas' => ['required', 'string', 'max:80'],
                'tim' => ['required', 'string', 'max:80'],
                'angkatan' => ['required', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
                'semester' => ['required', 'integer', 'min:1', 'max:8'],
                'semester_type' => ['required', Rule::in(['ganjil', 'genap'])],
                'program_studi_id' => ['required', 'exists:program_studi,id'],
                'shift' => ['required', Rule::in(['pagi', 'malam'])],
            ])->validate();
        }

        $email = filled($validated['email'] ?? null) ? strtolower(trim($validated['email'])) : null;

        $awaitingActivation = $user->isAwaitingActivation();
        if ($awaitingActivation && ($request->boolean('is_active') || !empty($validated['password']))) {
            return back()->with('error', 'Akun ini menunggu aktivasi mandiri. Status aktif dan password diatur pemilik akun lewat halaman Aktivasi Akun; gunakan "Kode aktivasi baru" bila kodenya hilang.')->withInput();
        }

        $payload = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'nitk_nidn' => $validated['nitk_nidn'],
            'email' => $awaitingActivation ? $user->email : $email,
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
            'email_verified_at' => $request->boolean('is_active') && $email
                ? ($user->email_verified_at ?? now())
                : ($awaitingActivation ? $user->email_verified_at : null),
            'approval_status' => $request->boolean('is_active') ? 'approved' : ($awaitingActivation ? AccountActivation::STATUS : 'pending'),
            'approved_by' => $request->boolean('is_active') ? auth()->id() : null,
            'approved_at' => $request->boolean('is_active') ? ($user->approved_at ?? now()) : null,
            'rejected_at' => null,
            'no_hp' => filled($validated['no_hp'] ?? null) ? trim($validated['no_hp']) : null,
            'rfid_uid' => filled($validated['rfid_uid'] ?? null) ? Str::upper(trim($validated['rfid_uid'])) : null,
        ];
        if ($request->filled('akses_pin') && $user->role !== 'mahasiswa') {
            // PIN akses mesin ruangan: disimpan sebagai hash; dicek mesin lewat AksesRuanganService.
            // Mahasiswa memakai PIN per-izin (ruangan_izin.akses_pin_enc), bukan akses_pin akun.
            $payload['akses_pin'] = Hash::make($validated['akses_pin']);
        }
        if ($validated['role'] === 'mahasiswa') {
            $payload['kelas'] = $validated['kelas'] ?? null;
            $payload['tim'] = $validated['tim'] ?? null;
            $payload['angkatan'] = $validated['angkatan'] ?? null;
            $payload['semester'] = $validated['semester'] ?? null;
            $payload['semester_type'] = $validated['semester_type'] ?? null;
            $payload['program_studi_id'] = $validated['program_studi_id'] ?? null;
            $payload['shift'] = $validated['shift'] ?? null;
            $payload['kelas_paralel'] = $validated['kelas_paralel'] ?? $this->inferKelasParalel($validated['kelas'] ?? null);
            $payload['profile_completed_at'] = now();
        } else {
            $payload['kelas'] = null;
            $payload['tim'] = null;
            $payload['angkatan'] = null;
            $payload['semester'] = null;
            $payload['semester_type'] = null;
            $payload['program_studi_id'] = null;
            $payload['shift'] = null;
            $payload['kelas_paralel'] = null;
            $payload['profile_completed_at'] = null;
        }

        if (!empty($validated['password'])) {
            $payload['password'] = $validated['password'];
            $payload['must_change_password'] = false;
        }

        $user->update($payload);

        $this->syncDosenKelas($user, $validated['kelas_cakupan'] ?? null);

        if (!empty($validated['password']) || !$request->boolean('is_active')) {
            $user->forceFill([
                'remember_token' => Str::random(60),
                'mobile_api_token' => null,
                'mobile_token_expires_at' => null,
            ])->save();
            AccountActivation::revokeActiveTokensAndSessions($user);
        }

        if ($validated['role'] === 'mahasiswa' && !$user->finger_id && !$awaitingActivation) {
            try {
                FingerIdAllocator::ensureForMahasiswa($user->fresh());
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        $this->recordApprovalLog(
            $request,
            $user,
            $request->boolean('is_active') ? 'approved' : 'updated_pending',
            'Data user diperbarui dari Manajemen User.'
        );

        return back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function approveUser(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak bisa menyetujui akun sendiri.');
        }

        if ($user->isAwaitingActivation()) {
            return back()->with('error', 'Akun hasil import diaktifkan sendiri oleh pemiliknya lewat halaman Aktivasi Akun, bukan lewat persetujuan admin.');
        }

        $user->update([
            'is_active' => true,
            'approval_status' => 'approved',
            'approval_note' => null,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_at' => null,
            'email_verified_at' => filled($user->email) ? ($user->email_verified_at ?? now()) : null,
        ]);

        $channel = match ($user->role) {
            'mahasiswa' => 'aplikasi mobile',
            'dosen' => 'panel web dosen',
            'laboran' => 'panel web admin',
            default => 'sistem',
        };

        $this->recordApprovalLog($request, $user, 'approved', "Akun disetujui admin. Pengguna dapat mengakses {$channel}.");

        $fingerNote = '';
        if ($user->role === 'mahasiswa' && ! $user->fresh()->finger_id) {
            try {
                $assigned = FingerIdAllocator::ensureForMahasiswa($user->fresh());
                if ($assigned->finger_id) {
                    $fingerNote = " Finger ID #{$assigned->finger_id} dialokasikan.";
                }
            } catch (\RuntimeException $e) {
                $fingerNote = ' Finger ID belum terisi: '.$e->getMessage();
            }
        }

        app(\App\Services\AccountApprovalNotifier::class)->notifyApproved($user->fresh(), $request->user());

        return back()->with('success', "Akun {$user->nama_lengkap} disetujui. Pengguna sudah bisa login ke {$channel}. Pengguna juga menerima notifikasi email & in-app.{$fingerNote}");
    }

    public function bulkApproveUsers(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $readyIds = PendingAccountApproval::query()
            ->whereIn('id', $validated['user_ids'])
            ->pluck('id');

        if ($readyIds->isEmpty()) {
            return back()->with('error', 'Tidak ada akun siap disetujui. Pastikan pengguna sudah verifikasi OTP.');
        }

        $admin = $request->user();
        $notifier = app(\App\Services\AccountApprovalNotifier::class);
        $approved = 0;

        foreach (User::query()->whereIn('id', $readyIds)->get() as $user) {
            if ($admin->id === $user->id) {
                continue;
            }

            $user->update([
                'is_active' => true,
                'approval_status' => 'approved',
                'approval_note' => null,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'email_verified_at' => filled($user->email) ? ($user->email_verified_at ?? now()) : null,
            ]);

            $channel = match ($user->role) {
                'mahasiswa' => 'aplikasi mobile',
                'dosen' => 'panel web dosen',
                default => 'sistem',
            };

            $this->recordApprovalLog($request, $user, 'approved', "Akun disetujui admin (bulk). Pengguna dapat mengakses {$channel}.");
            $notifier->notifyApproved($user->fresh(), $admin);
            $approved++;
        }

        if ($approved === 0) {
            return back()->with('error', 'Tidak ada akun yang disetujui.');
        }

        return back()->with('success', "{$approved} akun berhasil disetujui sekaligus. Pengguna menerima notifikasi email & in-app.");
    }

    public function unlockUserLogin(Request $request, User $user, LoginSecurityService $loginSecurity)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak bisa membuka kunci login akun sendiri dari sini.');
        }

        if (!$user->hasLoginSecurityIssue() && !$user->login_suspicious_at) {
            return back()->with('info', 'Akun ini tidak sedang terkunci login.');
        }

        $loginSecurity->adminUnlock($user, auth()->user(), $request);

        return back()->with('success', "Kunci login untuk {$user->nama_lengkap} telah dibuka. Pengguna bisa mencoba login lagi.");
    }

    public function rejectUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'approval_note' => ['required', 'string', 'max:500'],
        ]);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak bisa menolak akun sendiri.');
        }

        $approval = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');
        if ($approval === 'approved') {
            return back()->with('error', 'Akun sudah disetujui. Gunakan Nonaktifkan akun, bukan Tolak.');
        }
        if ($approval === 'rejected') {
            return back()->with('error', 'Akun ini sudah ditolak sebelumnya.');
        }

        $user->update([
            'is_active' => false,
            'approval_status' => 'rejected',
            'approval_note' => $validated['approval_note'],
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => now(),
        ]);

        $this->recordApprovalLog($request, $user, 'rejected', $validated['approval_note']);

        app(\App\Services\AccountApprovalNotifier::class)->notifyRejected(
            $user->fresh(),
            $request->user(),
            $validated['approval_note']
        );

        return back()->with('success', "Akun {$user->nama_lengkap} berhasil ditolak. Pengguna menerima notifikasi email & in-app.");
    }

    public function toggleUserActive(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak bisa menonaktifkan akun sendiri.');
        }

        $approval = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');
        if ($approval !== 'approved') {
            return back()->with('error', 'Hanya akun yang sudah disetujui yang bisa dinonaktifkan / diaktifkan kembali.');
        }

        $activating = ! $user->is_active;
        $user->update([
            'is_active' => $activating,
        ]);

        if (! $activating) {
            $user->forceFill([
                'remember_token' => Str::random(60),
                'mobile_api_token' => null,
                'mobile_token_expires_at' => null,
            ])->save();
            AccountActivation::revokeActiveTokensAndSessions($user);
        }

        $action = $activating ? 'activated' : 'deactivated';
        $note = $activating
            ? 'Akun diaktifkan kembali oleh admin. Pengguna bisa login.'
            : 'Akun dinonaktifkan sementara oleh admin. Persetujuan tetap Disetujui.';

        $this->recordApprovalLog($request, $user, $action, $note);

        return back()->with(
            'success',
            $activating
                ? "Akun {$user->nama_lengkap} diaktifkan kembali."
                : "Akun {$user->nama_lengkap} dinonaktifkan. Pengguna tidak bisa login sampai diaktifkan lagi."
        );
    }

    public function destroyUser(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }

        AccountApprovalLog::query()->create([
            'user_id' => $user->id,
            'subject_name' => $user->nama_lengkap,
            'subject_email' => $user->email,
            'subject_nitk_nidn' => $user->nitk_nidn,
            'subject_role' => $user->role,
            'subject_meta' => [
                'finger_id' => $user->finger_id,
                'kelas' => $user->kelas,
                'tim' => $user->tim,
                'angkatan' => $user->angkatan,
                'semester' => $user->semester,
                'semester_type' => $user->semester_type,
                'program_studi_id' => $user->program_studi_id,
                'shift' => $user->shift,
                'approval_status' => $user->approval_status,
            ],
            'actor_id' => auth()->id(),
            'action' => 'deleted',
            'note' => 'Akun dihapus permanen oleh admin. Data login & sidik jari ikut terhapus dari sistem.',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus. Riwayat penghapusan tercatat di bawah.');
    }

    private function recordApprovalLog(Request $request, User $user, string $action, ?string $note = null): void
    {
        AccountApprovalLog::query()->create([
            'user_id' => $user->id,
            'subject_name' => $user->nama_lengkap,
            'subject_email' => $user->email,
            'subject_nitk_nidn' => $user->nitk_nidn,
            'subject_role' => $user->role,
            'subject_meta' => [
                'finger_id' => $user->finger_id,
                'kelas' => $user->kelas,
                'tim' => $user->tim,
                'angkatan' => $user->angkatan,
                'semester' => $user->semester,
                'semester_type' => $user->semester_type,
            ],
            'actor_id' => auth()->id(),
            'action' => $action,
            'note' => $note,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    public function attendance(Request $request)
    {
        $filter = $this->resolveAttendanceFilter($request);
        $allRows = $this->buildAttendanceRows($filter);
        $summary = $this->attendanceSummary($allRows);
        $rows = TablePagination::paginate($allRows, 'page', 15);
        $kelasOptions = User::query()
            ->where('role', 'mahasiswa')
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');
        $timOptions = User::query()
            ->where('role', 'mahasiswa')
            ->whereNotNull('tim')
            ->where('tim', '!=', '')
            ->distinct()
            ->orderBy('tim')
            ->pluck('tim');

        return view('admin.attendance', compact('summary', 'rows', 'filter', 'kelasOptions', 'timOptions'));
    }









    public function fingerprintSnapshot(Request $request): JsonResponse
    {
        $state = $this->fingerprintState($request);
        $mahasiswa = $state['mahasiswa']->withPath(route('admin.fingerprint'));
        $rows = [];
        foreach ($mahasiswa as $student) {
            $rows[] = [
                'nama' => $student->nama_lengkap,
                'nim' => $student->nitk_nidn,
                'email' => $student->email,
                'kelas' => $student->kelas ?: '',
                'tim' => $student->tim ?: '',
                'shift' => $student->shift ?: '',
                'finger_id' => $student->finger_id,
                'is_active' => (bool) $student->is_active,
                'enrolled' => $student->hasFingerprintEnrolled(),
                'legacy' => $student->hasLegacyFingerprintPlaceholder(),
            ];
        }
        $stats = $state['fingerprintStats'];
        $sync = $state['syncReport']['stats'] ?? [];

        return response()->json(\App\Support\AbsensiSnapshot::envelope('fingerprint', 'laboran', [
            'query' => \App\Support\AbsensiSnapshot::canonicalQuery($request),
            'revision' => substr(hash('sha256', json_encode($rows)), 0, 16),
            'summary' => [
                'total' => $stats['total'],
                'enrolled' => $stats['enrolled'],
                'pending_enroll' => $stats['pending_enroll'],
                'unassigned' => $stats['unassigned'],
                'completion_rate' => $stats['completion_rate'],
            ],
            'summary_text' => [
                'rate' => $stats['completion_rate'].'%',
                'enrolled' => number_format($stats['enrolled']).' / '.number_format($stats['total']),
                'hasil' => $mahasiswa->total().' hasil filter',
                'sync-today' => number_format($sync['today'] ?? 0),
            ],
            'pagination' => [
                'page' => $mahasiswa->currentPage(),
                'per_page' => $mahasiswa->perPage(),
                'total' => $mahasiswa->total(),
                'last_page' => $mahasiswa->lastPage(),
            ],
            'rows' => $rows,
            'regions' => [
                'rows' => view('admin._fingerprint_rows', ['mahasiswa' => $mahasiswa])->render(),
                'pager' => $mahasiswa->links()->toHtml(),
                'sync_rows' => view('admin._fingerprint_sync_rows', [
                    'rows' => $state['syncReport']['rows'],
                ])->render(),
            ],
        ]));
    }

    /**
     * @param  array<string, int|float>  $stats
     * @return array<string, mixed>
     */
    private function fingerprintCharts(array $stats): array
    {
        $enrolled = \App\Support\FingerprintSyncService::enrolledQuery()
            ->get()
            ->filter(fn (User $user) => \App\Support\FingerprintTemplateCodec::hasValidTemplate($user->finger_template));
        $byKelas = $enrolled
            ->groupBy(fn (User $user) => trim((string) $user->kelas) !== '' ? (string) $user->kelas : 'Tanpa kelas')
            ->map(fn ($group) => $group->count())
            ->sortDesc();
        $palette = ['#0284c7', '#38bdf8', '#7c3aed', '#14b8a6', '#d97706', '#e11d48'];
        $kelasLabels = $byKelas->keys()->values()->all();
        $kelasValues = $byKelas->values()->map(fn ($count) => (int) $count)->all();
        if ($kelasLabels === []) {
            $kelasLabels = ['Belum ada'];
            $kelasValues = [0];
        }

        $start = now('Asia/Jakarta')->startOfDay()->subDays(6);
        $syncCounts = \App\Models\FingerprintSyncLog::query()
            ->where('requested_at', '>=', $start)
            ->get(['requested_at'])
            ->groupBy(fn ($log) => optional($log->requested_at)?->timezone('Asia/Jakarta')->toDateString());
        $syncLabels = [];
        $syncValues = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $syncLabels[] = $day->translatedFormat('D d');
            $syncValues[] = (int) ($syncCounts[$day->toDateString()] ?? collect())->count();
        }

        $today = now('Asia/Jakarta');

        return [
            'status_donut' => [
                'labels' => ['Sudah enroll', 'Belum enroll', 'Belum assign'],
                'values' => [
                    (int) ($stats['enrolled'] ?? 0),
                    (int) ($stats['pending_enroll'] ?? 0),
                    (int) ($stats['unassigned'] ?? 0),
                ],
                'colors' => ['#0f766e', '#d97706', '#e11d48'],
                'center' => (int) ($stats['total'] ?? 0),
                'center_sub' => (string) ($stats['role_label'] ?? 'Mahasiswa'),
            ],
            'enroll_trend' => [
                'labels' => [$today->translatedFormat('d M')],
                'values' => [(int) ($stats['enrolled'] ?? 0)],
                'subtitle' => 'Blob tersimpan saat ini',
            ],
            'kelas_bar' => [
                'labels' => $kelasLabels,
                'values' => $kelasValues,
                'colors' => array_slice(array_merge($palette, $palette), 0, count($kelasLabels)),
                'subtitle' => 'Sudah enroll per kelas',
            ],
            'sync_bar' => [
                'labels' => $syncLabels,
                'values' => $syncValues,
                'colors' => array_fill(0, 7, '#14b8a6'),
                'subtitle' => 'Permintaan sync 7 hari terakhir',
            ],
        ];
    }

    public function fingerprint(Request $request)
    {
        $user = $request->user();
        $state = $this->fingerprintState($request);
        $stats = $state['fingerprintStats'];
        $charts = $state['fingerprintCharts'];
        $canUpdate = \App\Support\PermissionRegistry::canCurrent('fingerprint', 'update');
        $canDelete = \App\Support\PermissionRegistry::canCurrent('fingerprint', 'delete');

        // Tren harian: mahasiswa enrolled dikelompokkan per tanggal update
        // terakhir (proxy waktu enroll — tidak ada kolom enrolled_at).
        $trendStart = now('Asia/Jakarta')->startOfDay()->subDays(6);
        $enrollByDay = FingerprintSyncService::enrolledQuery($state['filter']['role'])
            ->where('updated_at', '>=', $trendStart)
            ->get(['updated_at', 'finger_template'])
            ->filter(fn (User $u) => \App\Support\FingerprintTemplateCodec::hasValidTemplate($u->finger_template))
            ->groupBy(fn (User $u) => $u->updated_at?->timezone('Asia/Jakarta')->toDateString());
        $trendLabels = [];
        $trendValues = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $trendStart->copy()->addDays($i);
            $trendLabels[] = $day->translatedFormat('d M');
            $trendValues[] = (int) ($enrollByDay[$day->toDateString()] ?? collect())->count();
        }

        $mahasiswa = $state['mahasiswa'];
        $rows = collect($mahasiswa->items())->map(function (User $s) {
            $enrolled = $s->hasFingerprintEnrolled();
            $legacy = $s->hasLegacyFingerprintPlaceholder();
            if (! $s->is_active) {
                $status = 'tidak_aktif'; $kesiapan = 'Akun nonaktif';
            } elseif ($enrolled) {
                $status = 'enrolled'; $kesiapan = 'Siap tap';
            } elseif ($legacy) {
                $status = 'legacy'; $kesiapan = 'Enroll ulang';
            } elseif ($s->finger_id) {
                $status = 'pending'; $kesiapan = 'Scan 2× di mesin';
            } else {
                $status = 'unassigned'; $kesiapan = 'Assign slot';
            }
            return [
                'id' => $s->id,
                'nama' => $s->nama_lengkap,
                'role' => $s->role,
                'nim' => $s->nitk_nidn,
                'email' => $s->email,
                'kelas' => $s->kelas ?: null,
                'tim' => $s->tim ?: null,
                'shift' => in_array($s->shift, ['pagi', 'malam'], true) ? $s->shift : null,
                'finger_id' => $s->finger_id,
                'is_active' => (bool) $s->is_active,
                'status' => $status,
                'kesiapan' => $kesiapan,
                'can_clear' => $enrolled || $legacy,
                'profile_url' => route('profiles.show', $s),
            ];
        })->values()->all();

        $syncRows = $state['syncReport']['rows'];
        $syncItems = collect($syncRows->items())->map(fn ($r) => [
            'id' => $r->id,
            'waktu' => $r->requested_at?->timezone('Asia/Jakarta')->translatedFormat('d M, H:i'),
            'waktu_human' => $r->requested_at?->diffForHumans(),
            'id_mesin' => $r->id_mesin,
            'action' => $r->action,
            'delta' => trim(($r->since ?? '') . '→' . ($r->after_finger_id ?? ''), '→') ?: null,
            'templates_count' => (int) ($r->templates_count ?? 0),
            'finger_ids' => $r->finger_ids,
            'ip' => $r->ip_address,
            'success' => (bool) $r->success,
            'message' => $r->message,
        ])->values()->all();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('Fingerprint', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'stats' => [
                'total' => (int) $stats['total'],
                'enrolled' => (int) $stats['enrolled'],
                'pending_enroll' => (int) $stats['pending_enroll'],
                'unassigned' => (int) $stats['unassigned'],
                'capacity_used' => (int) $stats['capacity_used'],
                'capacity_total' => (int) $stats['capacity_total'],
                'completion_rate' => (float) $stats['completion_rate'],
                'role_label' => (string) $stats['role_label'],
            ],
            'charts' => [
                'donut' => $charts['status_donut'],
                'trend' => ['labels' => $trendLabels, 'values' => $trendValues],
                'kelas_bar' => $charts['kelas_bar'],
                'sync_bar' => $charts['sync_bar'],
            ],
            'mahasiswa' => [
                'data' => $rows,
                'meta' => [
                    'page' => $mahasiswa->currentPage(),
                    'last_page' => $mahasiswa->lastPage(),
                    'total' => $mahasiswa->total(),
                    'per_page' => $mahasiswa->perPage(),
                    'from' => $mahasiswa->firstItem() ?? 0,
                    'to' => $mahasiswa->lastItem() ?? 0,
                ],
            ],
            'filters' => $state['filter'],
            'options' => [
                'kelas' => $state['kelasOptions'],
                'tim' => $state['timOptions'],
                'mesin' => $state['syncMesinOptions']->map(fn ($m) => [
                    'id_mesin' => $m->id_mesin, 'nama' => $m->nama_mesin ?: $m->id_mesin,
                ])->values()->all(),
            ],
            'sync' => [
                'mode' => $state['syncPolicy']['mode'],
                'burst_active' => \App\Support\FingerprintSyncPolicy::burstActive(),
                'burst_until' => $state['syncPolicy']['burst_until'],
                'stats' => $state['syncReport']['stats'],
                'log' => [
                    'data' => $syncItems,
                    'meta' => [
                        'page' => $syncRows->currentPage(),
                        'last_page' => $syncRows->lastPage(),
                        'total' => $syncRows->total(),
                    ],
                ],
            ],
            'can' => ['update' => $canUpdate, 'delete' => $canDelete],
            'tab' => in_array($request->query('tab'), ['ringkasan', 'mahasiswa', 'distribusi', 'sinkronisasi', 'log'], true)
                ? $request->query('tab')
                : 'ringkasan',
            'urls' => [
                'index' => route('admin.fingerprint'),
                'user_base' => rtrim(route('admin.fingerprint'), '/'),
                'backfill' => route('admin.fingerprint.backfill-shift'),
                'sync_mode' => route('admin.fingerprint.sync-mode'),
                'sync_distribute' => route('admin.fingerprint.sync-distribute'),
                'mesin' => route('admin.mesin.index'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprintState(Request $request): array
    {
        $filter = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'sync_mesin' => trim((string) $request->query('sync_mesin', '')),
            'kelas' => trim((string) $request->query('kelas', '')),
            'tim' => trim((string) $request->query('tim', '')),
            'role' => (string) $request->query('role', 'mahasiswa'),
        ];
        if (! in_array($filter['role'], \App\Support\FingerprintSyncService::FP_ROLES, true)) {
            $filter['role'] = 'mahasiswa';
        }
        $role = $filter['role'];

        $fingerprintStats = [
            'total' => User::query()->where('role', $role)->count(),
            'enrolled' => FingerprintSyncService::countEnrolled($role),
            'pending_enroll' => FingerprintSyncService::countPendingEnroll($role),
            'unassigned' => User::query()->where('role', $role)->whereNull('finger_id')->count(),
            'capacity_used' => User::query()->whereNotNull('finger_id')->distinct('finger_id')->count('finger_id'),
            'capacity_total' => 200,
        ];

        $fingerprintStats['completion_rate'] = $fingerprintStats['total'] > 0
            ? round(($fingerprintStats['enrolled'] / $fingerprintStats['total']) * 100, 1)
            : 0;
        $fingerprintStats['role_label'] = match ($role) {
            'laboran' => 'Laboran',
            'dosen' => 'Dosen',
            'admin' => 'Admin',
            default => 'Mahasiswa',
        };

        $query = User::query()->where('role', $role);

        if ($filter['q'] !== '') {
            $kw = $filter['q'];
            $query->where(function ($qb) use ($kw) {
                $qb->where('nama_lengkap', 'like', "%{$kw}%")
                    ->orWhere('nitk_nidn', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%");
            });
        }

        if ($filter['status'] === 'enrolled') {
            $enrolledIds = FingerprintSyncService::enrolledQuery($role)
                ->pluck('id');
            $query->whereIn('id', $enrolledIds);
        } elseif ($filter['status'] === 'pending_enroll') {
            $enrolledIds = FingerprintSyncService::enrolledQuery($role)->pluck('id');
            $query->whereNotNull('finger_id')
                ->whereNotIn('id', $enrolledIds);
        } elseif ($filter['status'] === 'legacy_re_enroll') {
            $legacyIds = User::query()
                ->where('role', $role)
                ->whereNotNull('finger_id')
                ->get()
                ->filter(fn (User $u) => ! $u->hasFingerprintEnrolled() && ! empty($u->finger_template))
                ->pluck('id');
            $query->whereIn('id', $legacyIds);
        } elseif ($filter['status'] === 'unassigned') {
            $query->whereNull('finger_id');
        } elseif ($filter['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        if ($filter['kelas'] !== '') {
            $query->where('kelas', $filter['kelas']);
        }

        if ($filter['tim'] !== '') {
            $query->where('tim', $filter['tim']);
        }

        $mahasiswa = $query->orderBy('nama_lengkap')->paginate(15)->withQueryString();

        $syncReport = FingerprintSyncLogService::adminReport(
            $filter['sync_mesin'] !== '' ? $filter['sync_mesin'] : null
        );
        $syncMesinOptions = MesinIot::query()
            ->where('tipe', 'absensi')
            ->orderBy('id_mesin')
            ->get(['id_mesin', 'nama_mesin', 'tipe']);

        $kelasOptions = collect(\App\Support\MahasiswaProfileOptions::kelasOptions())
            ->merge(
                User::query()
                    ->where('role', 'mahasiswa')
                    ->whereNotNull('kelas')
                    ->where('kelas', '!=', '')
                    ->distinct()
                    ->orderBy('kelas')
                    ->pluck('kelas')
            )
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $timOptions = collect(\App\Support\MahasiswaProfileOptions::pblTeamOptions())
            ->merge(
                User::query()
                    ->where('role', 'mahasiswa')
                    ->whereNotNull('tim')
                    ->where('tim', '!=', '')
                    ->distinct()
                    ->orderBy('tim')
                    ->pluck('tim')
            )
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $syncPolicy = FingerprintSyncPolicy::config();

        $fingerprintCharts = $this->fingerprintCharts($fingerprintStats);

        return compact(
            'mahasiswa',
            'fingerprintStats',
            'fingerprintCharts',
            'filter',
            'syncReport',
            'syncMesinOptions',
            'kelasOptions',
            'timOptions',
            'syncPolicy',
        );
    }

    public function updateFingerprintSyncMode(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:auto,manual'],
        ]);

        FingerprintSyncPolicy::setMode($validated['mode']);

        $label = $validated['mode'] === 'auto'
            ? 'Sync template otomatis diaktifkan — mesin absensi akan menerima template saat polling.'
            : 'Sync template manual — mesin tidak menerima blob sampai Anda klik distribusi.';

        return back()->with('success', $label);
    }

    public function triggerFingerprintSyncBurst()
    {
        FingerprintSyncPolicy::triggerBurst(20);

        return back()->with('success', 'Distribusi template diaktifkan 20 menit. Mesin absensi akan menerima template pada request berikutnya.');
    }

    public function assignFingerId(Request $request, User $user)
    {
        if (! in_array($user->role, \App\Support\FingerprintSyncService::FP_ROLES, true)) {
            return back()->with('error', 'Finger ID tidak berlaku untuk role ini.');
        }

        $validated = $request->validate([
            'finger_id' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if (!empty($validated['finger_id'])) {
            $exists = User::where('finger_id', $validated['finger_id'])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                return back()->with('error', "Finger ID #{$validated['finger_id']} sudah dipakai pengguna lain.");
            }
            $user->finger_id = $validated['finger_id'];
        } else {
            try {
                $user->finger_id = FingerIdAllocator::assignNext();
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        if ($user->role === 'mahasiswa') {
            MahasiswaShiftResolver::ensure($user, null, true);
        }
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');

        $shiftLabel = $user->role === 'mahasiswa' && $user->shift ? ' · shift ' . $user->shift : '';

        return back()->with('success', "Finger ID #{$user->finger_id} berhasil di-assign untuk {$user->nama_lengkap}{$shiftLabel}.");
    }

    public function backfillMahasiswaShift()
    {
        $count = MahasiswaShiftResolver::backfillMissing();

        return back()->with('success', "Shift absensi diperbarui untuk {$count} mahasiswa.");
    }

    public function resetFingerId(User $user)
    {
        if (! in_array($user->role, \App\Support\FingerprintSyncService::FP_ROLES, true)) {
            return back()->with('error', 'Finger ID tidak berlaku untuk role ini.');
        }
        $user->finger_id = null;
        $user->finger_template = null;
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');

        return back()->with('success', "Finger ID untuk {$user->nama_lengkap} berhasil direset.");
    }

    public function clearFingerprintEnroll(User $user)
    {
        if (! in_array($user->role, \App\Support\FingerprintSyncService::FP_ROLES, true)) {
            return back()->with('error', 'Finger ID tidak berlaku untuk role ini.');
        }
        if (!$user->finger_id) {
            return back()->with('error', 'Assign finger_id dulu sebelum ulang enroll.');
        }

        $user->finger_template = null;
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');

        return back()->with(
            'success',
            "Enroll {$user->nama_lengkap} (slot #{$user->finger_id}) dibatalkan di sistem. "
            . 'Di mesin absensi: ketik d → finger_id yang sama (hapus sensor + sync DB), '
            . 'atau e → scan ulang. Jika sensor menolak, hapus slot dulu lalu enroll lagi.'
        );
    }

    public function monitoring(Request $request)
    {
        $tab = (string) $request->query('tab', 'overview');
        $payload = $this->buildMonitoringPayload($tab);
        $user = $request->user();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('MonitoringOperasional', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            ...$payload,
            'threshold_minutes' => MesinIot::ONLINE_THRESHOLD_MINUTES,
            'urls' => [
                'index' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin', 'monitoring')
                    : route('admin.monitoring'),
                'feed' => $request->attributes->get('dev_preview')
                    ? route('dev.preview.admin.monitoring-feed')
                    : route('admin.monitoring.feed'),
                'mesin' => route('admin.mesin.index'),
                'log' => route('admin.log.absensi'),
            ],
        ]);
    }

    public function monitoringFeed(Request $request)
    {
        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            ...$this->buildMonitoringPayload((string) $request->query('tab', 'overview')),
        ]);
    }

    /**
     * Pusat Kendali — satu halaman lintas sistem untuk admin: presence pengguna,
     * armada mesin, antrian approval/pengajuan, akses ruangan ditolak, dan
     * aktivitas PBL terakhir. Khusus admin (konten memuat data per-pengguna).
     */
    public function pusatKendali(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('PusatKendali', [
            'menu' => $this->adminSidebarMenu($request->user()),
            'notif' => $this->adminNotifications($request->user()),
            ...$this->buildPusatKendaliPayload(),
            'urls' => [
                'feed' => route('admin.pusat-kendali.feed'),
                'users' => route('admin.users'),
                'mesin' => route('admin.mesin.index'),
                'monitoring' => route('admin.monitoring'),
                'pengajuan' => route('admin.pengajuan.index'),
                'ruangan' => route('admin.ruangan.index'),
                'pbl' => route('admin.pbl.index'),
                'notifikasi' => route('admin.notifications'),
            ],
        ]);
    }

    public function pusatKendaliFeed(Request $request)
    {
        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            ...$this->buildPusatKendaliPayload(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPusatKendaliPayload(): array
    {
        $onlineSince = now()->subMinutes(5);
        $onlineUsers = User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineSince)
            ->orderByDesc('last_seen_at')
            ->limit(14)
            ->get(['id', 'nama_lengkap', 'role', 'last_seen_at', 'profile_photo_path']);

        $mesin = MesinIot::query()
            ->where('tipe', '!=', 'loker')
            ->with('ruangan:id,nama')
            ->get();
        $mesinOnline = $mesin->filter(fn ($m) => $m->isOnline());
        $mesinDown = $mesin->filter(fn ($m) => $m->is_active && ! $m->isOnline())
            ->sortBy('last_seen_at')
            ->take(8)
            ->map(fn ($m) => [
                'nama' => $m->nama_mesin ?? $m->id_mesin,
                'tipe' => $m->tipe ?? 'absensi',
                'ruangan' => $m->ruangan?->nama,
                'terakhir' => $m->last_seen_at?->diffForHumans() ?? 'belum pernah',
            ])->values()->all();

        $pendingUsers = PendingAccountApproval::query()
            ->orderByDesc('email_verified_at')
            ->limit(8)
            ->get(['id', 'nama_lengkap', 'role', 'email_verified_at']);

        $pengajuanMenunggu = collect();
        $aksesDitolak = collect();
        $aksesDitolakHariIni = 0;
        try {
            $pengajuanMenunggu = \App\Models\RuanganPengajuan::query()
                ->whereIn('status', \App\Models\RuanganPengajuan::STATUS_AKTIF_MENUNGGU)
                ->with(['ruangan:id,nama', 'pengaju:id,nama_lengkap'])
                ->orderByDesc('created_at')->limit(6)->get();
            $aksesDitolak = \App\Models\RuanganAccessEvent::query()
                ->where('hasil', 'ditolak')
                ->with(['ruangan:id,nama', 'user:id,nama_lengkap'])
                ->orderByDesc('terjadi_pada')->limit(8)->get();
            $aksesDitolakHariIni = \App\Models\RuanganAccessEvent::query()
                ->where('hasil', 'ditolak')->whereDate('terjadi_pada', today())->count();
        } catch (\Throwable $e) {
            // Tabel opsional — jangan gagalkan halaman.
        }

        $pblTerakhir = \App\Models\PblActivityLog::query()
            ->orderByDesc('created_at')->limit(8)->get();

        return [
            'kpi' => [
                'online' => $onlineUsers->count(),
                'mesin_online' => $mesinOnline->count(),
                'mesin_total' => $mesin->where('is_active', true)->count(),
                'mesin_down' => count($mesinDown),
                'pending_approval' => PendingAccountApproval::count(),
                'pengajuan_menunggu' => $pengajuanMenunggu->count() > 0
                    ? \App\Models\RuanganPengajuan::whereIn('status', \App\Models\RuanganPengajuan::STATUS_AKTIF_MENUNGGU)->count()
                    : 0,
                'akses_ditolak_hari_ini' => $aksesDitolakHariIni,
            ],
            'online_users' => $onlineUsers->map(fn ($u) => [
                'id' => $u->id,
                'nama' => $u->nama_lengkap,
                'role' => $u->role,
                'foto' => $u->profile_photo_url,
                'terakhir' => $u->last_seen_at?->diffForHumans(),
            ])->all(),
            'mesin_down' => $mesinDown,
            'pending_users' => $pendingUsers->map(fn ($u) => [
                'id' => $u->id,
                'nama' => $u->nama_lengkap,
                'role' => $u->role,
                'sejak' => $u->email_verified_at?->diffForHumans(),
            ])->all(),
            'pengajuan_menunggu' => $pengajuanMenunggu->map(fn ($p) => [
                'pemohon' => $p->pengaju?->nama_lengkap ?? '-',
                'ruangan' => $p->ruangan?->nama ?? '—',
                'tanggal' => $p->tanggal?->translatedFormat('d M Y') ?? (string) $p->tanggal,
            ])->all(),
            'akses_ditolak' => $aksesDitolak->map(fn ($e) => [
                'ruangan' => $e->ruangan?->nama ?? '—',
                'nama' => $e->user?->nama_lengkap ?? ($e->meta['alasan'] ?? 'identitas tidak dikenal'),
                'alasan' => $e->meta['alasan'] ?? 'ditolak',
                'tipe' => $e->meta['tipe'] ?? '-',
                'waktu' => $e->terjadi_pada?->diffForHumans(),
            ])->all(),
            'pbl_terakhir' => $pblTerakhir->map(fn ($l) => [
                'aktor' => $l->actor_nama ?? '—',
                'role' => $l->actor_role ?? '-',
                'aksi' => $l->aksi,
                'ringkasan' => $l->ringkasan,
                'waktu' => $l->created_at?->diffForHumans(),
            ])->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Payload Monitoring Operasional — kartu jenis mengikuti tipe mesin nyata di DB
     * (tipe baru otomatis jadi kartu/tab baru); tile kamera & ringkasan APD hanya
     * muncul saat perangkat vision/kamera benar-benar terdaftar.
     *
     * @return array<string, mixed>
     */
    private function buildMonitoringPayload(string $tab = 'overview'): array
    {
        $tab = $tab !== '' ? $tab : 'overview';
        $onlineThreshold = now()->subMinutes(MesinIot::ONLINE_THRESHOLD_MINUTES);
        $isOnline = fn (MesinIot $m) => $m->is_active
            && $m->last_seen_at
            && $m->last_seen_at->gte($onlineThreshold);
        $tipeLabel = fn ($t) => Str::headline(str_replace(['-', '_'], ' ', (string) ($t ?: 'absensi')));
        $isVision = fn (string $t) => (bool) preg_match('/cam|kamera|vision|cctv/i', $t);

        $fleetAll = MesinIot::query()
            ->where('tipe', '!=', 'loker')
            ->with('ruangan:id,nama,kode,lokasi')
            ->get();

        // Kartu per jenis perangkat — data nyata, diurutkan absensi dulu.
        $typeCards = $fleetAll->groupBy(fn ($m) => (string) ($m->tipe ?: 'absensi'))
            ->map(function ($group, $tipe) use ($isOnline, $tipeLabel) {
                $online = $group->filter($isOnline)->count();
                $active = $group->where('is_active', true)->count();
                return [
                    'key' => (string) $tipe,
                    'label' => $tipeLabel($tipe),
                    'total' => $group->count(),
                    'online' => $online,
                    'offline' => max(0, $active - $online),
                    'inactive' => max(0, $group->count() - $active),
                    'rooms' => $group->pluck('ruangan_id')->filter()->unique()->count(),
                    'is_vision' => (bool) preg_match('/cam|kamera|vision|cctv/i', (string) $tipe),
                ];
            })
            ->sortBy(fn ($c) => ($c['key'] === 'absensi' ? '0' : '1') . $c['key'])
            ->values()->all();

        $hasVision = collect($typeCards)->contains(fn ($c) => $c['is_vision'] && $c['total'] > 0);
        if (! collect($typeCards)->contains(fn ($c) => $c['is_vision'])) {
            $typeCards[] = [
                'key' => 'vision', 'label' => 'Vision (APD)', 'total' => 0,
                'online' => 0, 'offline' => 0, 'inactive' => 0, 'rooms' => 0,
                'is_vision' => true,
            ];
        }

        $tabs = [['key' => 'overview', 'label' => 'Overview', 'count' => $fleetAll->count()]];
        foreach ($typeCards as $c) {
            $tabs[] = ['key' => $c['key'], 'label' => $c['label'], 'count' => $c['total']];
        }
        if (! collect($tabs)->contains(fn ($t) => $t['key'] === $tab)) {
            $tab = 'overview';
        }

        $fleet = $tab === 'overview' ? $fleetAll : $fleetAll->where('tipe', $tab)->values();

        $machineTotal = $fleet->count();
        $machineOnline = $fleet->filter($isOnline)->count();
        $machineActive = $fleet->where('is_active', true)->count();
        $machineOffline = max(0, $machineActive - $machineOnline);
        $machineInactive = max(0, $machineTotal - $machineActive);
        $latestHeartbeat = $fleet->whereNotNull('last_seen_at')->sortByDesc('last_seen_at')->first();

        // Query log dengan filter tipe (join hanya saat tab spesifik).
        $logBase = function () use ($tab) {
            $q = LogAbsensi::query();
            if ($tab !== 'overview') {
                $q->join('mesin_iot', 'log_absensi.id_mesin', '=', 'mesin_iot.id_mesin')
                    ->where('mesin_iot.tipe', $tab)
                    ->select('log_absensi.*');
            }
            return $q;
        };

        $metrics = [
            'machine_total' => $machineTotal,
            'machine_online' => $machineOnline,
            'machine_offline' => $machineOffline,
            'machine_inactive' => $machineInactive,
            'latest_sync' => $latestHeartbeat?->last_seen_at?->translatedFormat('d M Y, H:i:s') ?? 'Belum ada heartbeat',
            'latest_sync_human' => $latestHeartbeat?->last_seen_at?->diffForHumans() ?? 'Belum ada',
            'absensi_today' => $logBase()->whereDate('waktu_tap', today())->count(),
            'events_today' => 0,
        ];
        $metrics['events_today'] = $metrics['absensi_today'];

        // Tabel status: perangkat + ruangan (ruangan selalu tampil di overview;
        // pada tab jenis hanya ruangan yang terikat mesin jenis tsb).
        $units = $fleet
            ->sortBy('id_mesin')->sortByDesc('last_seen_at')
            ->take(20)
            ->map(function (MesinIot $m) use ($isOnline, $tipeLabel) {
                $status = $isOnline($m) ? 'online' : ($m->is_active ? 'offline' : 'inactive');
                return [
                    'key' => 'mesin-' . $m->id,
                    'kind' => 'mesin',
                    'nama' => $m->nama_mesin ?: $m->id_mesin,
                    'sub' => $m->id_mesin,
                    'jenis' => $tipeLabel($m->tipe),
                    'jenis_key' => (string) $m->tipe,
                    'lokasi' => $m->ruangan ? trim($m->ruangan->nama . ' ' . ($m->ruangan->kode ? '(' . $m->ruangan->kode . ')' : '')) : '—',
                    'status' => $status,
                    'status_label' => match ($status) {
                        'online' => 'Online',
                        'offline' => 'Offline',
                        default => 'Nonaktif',
                    },
                    'update_label' => $m->last_seen_at?->translatedFormat('d M Y, H:i:s') ?? 'Belum pernah heartbeat',
                    'url' => route('admin.mesin.index'),
                ];
            });

        $rooms = \App\Models\Ruangan::query()->orderBy('nama')->get();
        if ($tab !== 'overview') {
            $rooms = $rooms->whereIn('id', $fleet->pluck('ruangan_id')->filter()->unique());
        }
        foreach ($rooms->take(15) as $r) {
            $units->push([
                'key' => 'ruangan-' . $r->id,
                'kind' => 'ruangan',
                'nama' => $r->nama,
                'sub' => $r->kode,
                'jenis' => 'Ruangan',
                'jenis_key' => 'ruangan',
                'lokasi' => $r->lokasi ?: '—',
                'status' => $r->aktif ? 'tersedia' : 'inactive',
                'status_label' => $r->aktif ? 'Tersedia' : 'Nonaktif',
                'update_label' => '—',
                'url' => route('admin.ruangan.index'),
            ]);
        }
        $units = $units->values()->all();

        // Peringatan: mesin aktif yang offline / belum pernah heartbeat.
        $alerts = $fleetAll
            ->filter(fn ($m) => $m->is_active && ! $isOnline($m))
            ->sortBy('last_seen_at')
            ->take(6)
            ->map(fn ($m) => [
                'tone' => 'danger',
                'title' => ($m->nama_mesin ?: $m->id_mesin) . ' Offline',
                'desc' => $m->last_seen_at
                    ? 'Tidak terhubung sejak ' . $m->last_seen_at->diffForHumans()
                    : 'Belum pernah heartbeat',
                'time' => $m->last_seen_at?->translatedFormat('H:i') ?? '—',
            ])
            ->values()->all();

        // Tile siaran langsung — hanya perangkat kamera/vision; streaming belum
        // diimplementasikan sehingga tile menampilkan status perangkat saja.
        $liveTiles = $fleetAll
            ->filter(fn ($m) => $isVision((string) $m->tipe))
            ->map(fn ($m) => [
                'nama' => $m->nama_mesin ?: $m->id_mesin,
                'ruangan' => $m->ruangan?->nama,
                'status' => $isOnline($m) ? 'live' : ($m->is_active ? 'offline' : 'inactive'),
            ])
            ->values()->all();

        $activities = $logBase()
            ->with(['user:id,nama_lengkap,nitk_nidn,profile_photo_path,role'])
            ->latest('waktu_tap')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'type' => 'Absensi',
                'type_key' => 'absensi',
                'time' => $row->waktu_tap?->toIso8601String(),
                'time_label' => $row->waktu_tap?->diffForHumans() ?? '-',
                'time_exact' => $row->waktu_tap?->translatedFormat('d M Y H:i:s') ?? '-',
                'student' => $row->user->nama_lengkap ?? '-',
                'identifier' => $row->user->nitk_nidn ?? '-',
                'machine' => $row->id_mesin,
                'status' => 'Tap berhasil',
                'ok' => true,
                'profile_photo_url' => $row->user?->profile_photo_url,
            ])
            ->sortByDesc('time')
            ->take(10)
            ->values()->all();

        $day = today();
        $hourlyLabels = [];
        $hourlyAbsensi = array_fill(0, 17, 0);
        for ($h = 6; $h <= 22; $h++) {
            $hourlyLabels[] = sprintf('%02d', $h);
        }

        $hourExpr = LogAbsensi::query()->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', waktu_tap) AS INTEGER)"
            : 'HOUR(waktu_tap)';
        $absensiHourRows = $logBase()
            ->whereDate('waktu_tap', $day)
            ->whereTime('waktu_tap', '>=', '06:00:00')
            ->whereTime('waktu_tap', '<=', '22:59:59')
            ->selectRaw("{$hourExpr} as jam, COUNT(*) as total")
            ->groupBy('jam')
            ->pluck('total', 'jam');

        for ($h = 6; $h <= 22; $h++) {
            $hourlyAbsensi[$h - 6] = (int) ($absensiHourRows[$h] ?? 0);
        }

        return [
            'tab' => $tab,
            'tabs' => $tabs,
            'type_cards' => $typeCards,
            'has_vision' => $hasVision,
            'metrics' => $metrics,
            'units' => $units,
            'alerts' => $alerts,
            'live_tiles' => $liveTiles,
            'activities' => $activities,
            'hourly' => [
                'labels' => $hourlyLabels,
                'absensi' => $hourlyAbsensi,
            ],
            'health' => [
                'labels' => ['Online', 'Offline', 'Nonaktif'],
                'values' => [$machineOnline, $machineOffline, $machineInactive],
                'colors' => ['#059669', '#d97706', '#94a3b8'],
            ],
        ];
    }

    public function settings(Request $request)
    {
        $libur = \App\Models\AbsensiKalenderLibur::query()->orderByDesc('tanggal')->limit(60)->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'tanggal' => $l->tanggal?->format('Y-m-d'),
                'tanggal_label' => $l->tanggal?->format('d/m/Y'),
                'keterangan' => $l->keterangan,
                'url' => route('admin.absensi.libur.destroy', $l),
            ])->values()->all();
        $prodi = \App\Models\ProgramStudi::query()->orderBy('nama')->get()
            ->map(fn ($pr) => ['id' => $pr->id, 'kode' => $pr->kode, 'nama' => $pr->nama])
            ->values()->all();

        $user = $request->user();
        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('Pengaturan', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'info' => [
                'nama_sistem' => \App\Support\Branding::appName(),
                'nama_laboratorium' => \App\Support\Branding::labName(),
                'deskripsi' => 'Sistem Manajemen Laboratorium',
                'versi' => (string) config('app.version', '2.1.0'),
                'lingkungan' => config('app.env') === 'production' ? 'Produksi' : (string) config('app.env'),
                'is_production' => config('app.env') === 'production',
                'diperbarui' => now()->translatedFormat('d M Y, H:i'),
                'status' => 'Normal',
            ],
            'general' => \App\Support\Branding::general() + [
                'zona_waktu' => (string) config('app.timezone', 'Asia/Jakarta'),
                'bahasa' => 'Bahasa Indonesia',
                'format_tanggal' => 'd M Y (contoh: ' . now()->format('d M Y') . ')',
                'format_waktu' => '24 Jam (HH:mm)',
            ],
            'page_branding' => [
                'logo_url' => \App\Support\Branding::logoUrl(),
                'has_custom' => \App\Support\Branding::customLogoPath() !== null,
                'default_url' => asset('branding/smartbrail-logo.png'),
            ],
            'security' => [
                ['label' => 'Durasi sesi login', 'value' => (int) config('session_guard.absolute_hours', 8) . ' jam (batas absolut)', 'status' => 'Aktif'],
                ['label' => 'Tetap masuk (remember)', 'value' => 'Maksimal 30 hari', 'status' => 'Aktif'],
                ['label' => 'Proteksi brute-force login', 'value' => 'Kunci sementara + tinjauan admin', 'status' => 'Aktif'],
                ['label' => 'Registrasi mandiri', 'value' => 'Akun dibuat admin lewat import', 'status' => 'Tertutup permanen'],
                ['label' => 'Verifikasi email (OTP)', 'value' => 'Wajib saat aktivasi akun', 'status' => 'Aktif'],
                ['label' => 'Pencatatan aktivitas', 'value' => 'Riwayat approval & aksi penting tersimpan', 'status' => 'Aktif'],
            ],
            'integrations' => [
                ['label' => 'Email (SMTP/mailer)', 'detail' => 'Driver: ' . (string) config('mail.default', 'log'), 'configured' => config('mail.default') !== 'log'],
                ['label' => 'Asisten AI (Gemini)', 'detail' => 'Model: ' . (string) config('services.gemini.model', '-'), 'configured' => ! empty(config('services.gemini.api_key'))],
                ['label' => 'Push notifikasi (FCM)', 'detail' => 'Kanal aplikasi mobile — belum diperlukan (APK nonaktif)', 'configured' => ! empty(config('services.fcm.server_key'))],
                ['label' => 'Web Push (browser)', 'detail' => 'Notifikasi sistem ke browser semua role via VAPID', 'configured' => ! empty(config('services.webpush.public_key')) && ! empty(config('services.webpush.private_key'))],
                ['label' => 'reCAPTCHA login', 'detail' => 'Proteksi form login publik', 'configured' => ! empty(config('services.recaptcha.site_key'))],
                ['label' => 'Sinkronisasi fingerprint', 'detail' => 'Antrian sync template ke mesin absensi', 'configured' => true],
            ],
            'libur' => $libur,
            'prodi' => $prodi,
            'feature_badges' => [
                'enabled' => \App\Support\FeatureBadge::config()['enabled'],
                'until' => \App\Support\FeatureBadge::config()['until'],
                'items' => \App\Support\FeatureBadge::adminList(),
            ],
            'urls' => [
                'update' => route('admin.settings.update'),
                'branding' => route('admin.settings.branding'),
                'branding_reset' => route('admin.settings.branding.reset'),
                'feature_badges' => route('admin.settings.feature-badges'),
                'libur' => route('admin.absensi.libur'),
                'prodi' => route('admin.absensi.prodi'),
                'absensi' => route('admin.absensi.settings'),
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'nama_sistem' => ['required', 'string', 'min:2', 'max:60'],
            'nama_laboratorium' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        \App\Models\SystemSetting::setValue(\App\Support\Branding::GENERAL_KEY, [
            'nama_sistem' => trim($data['nama_sistem']),
            'nama_laboratorium' => trim($data['nama_laboratorium']),
        ]);

        return back()->with('success', 'Konfigurasi umum disimpan. Nama sistem baru dipakai di seluruh portal.');
    }

    /** Simpan konfigurasi label fitur BARU/SOON di sidebar seluruh portal. */
    public function updateFeatureBadges(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'until' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'in:new,soon,off'],
        ]);

        \App\Support\FeatureBadge::save(
            (bool) ($data['enabled'] ?? true),
            (string) ($data['until'] ?? ''),
            (array) ($data['items'] ?? [])
        );

        return back()->with('success', 'Label fitur diperbarui — berlaku di sidebar semua portal.');
    }

    public function updateBranding(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
        ]);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        foreach (glob(public_path('branding/app-logo.*')) ?: [] as $old) {
            @unlink($old);
        }
        $path = 'branding/app-logo.' . $ext;
        $file->move(public_path('branding'), 'app-logo.' . $ext);

        \App\Models\SystemSetting::setValue(\App\Support\Branding::KEY, ['logo_path' => $path]);

        return back()->with('success', 'Logo baru diterapkan ke seluruh portal (admin, mahasiswa, halaman login).');
    }

    public function resetBranding()
    {
        foreach (glob(public_path('branding/app-logo.*')) ?: [] as $old) {
            @unlink($old);
        }
        \App\Models\SystemSetting::query()->where('key', \App\Support\Branding::KEY)->delete();
        \App\Models\SystemSetting::flushValueMemo();

        return back()->with('success', 'Logo dikembalikan ke bawaan SmartBrail.');
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        $raw = \App\Support\StaffNotificationService::rawOperationalItems($user);

        $meta = [
            'ops-users-otp'    => ['target' => 'Pendaftar (OTP)', 'peminta' => 'Sistem (Manajemen User)'],
            'ops-users'        => ['target' => 'Mahasiswa & Dosen', 'peminta' => 'Sistem (Manajemen User)'],
            'ops-suggest'      => ['target' => 'Semua Pengguna', 'peminta' => 'Sistem (Komunikasi)'],
            'ops-login-locked' => ['target' => 'Akun Terkunci', 'peminta' => 'Sistem (Keamanan)'],
            'ops-enroll'       => ['target' => 'Mahasiswa', 'peminta' => 'Sistem (Fingerprint)'],
            'ops-mesin'        => ['target' => 'Admin & Laboran', 'peminta' => 'Sistem (Monitoring)'],
        ];
        $prioMap = ['danger' => 'Urgent', 'warning' => 'Tinggi', 'info' => 'Normal'];
        $waktu = now()->translatedFormat('d M Y, H:i');

        $items = $raw->values()->map(function (array $it, int $i) use ($meta, $prioMap, $waktu) {
            $m = $meta[$it['id']] ?? ['target' => 'Semua Pengguna', 'peminta' => 'Sistem'];

            return [
                'kode' => 'NTF-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'ops_key' => $it['id'],
                'fingerprint' => $it['fingerprint'],
                'judul' => $it['title'],
                'isi' => $it['body'],
                'target' => $m['target'],
                'prioritas' => $prioMap[$it['kind']] ?? 'Normal',
                'tone' => $it['kind'],
                'status' => 'Menunggu',
                'peminta' => $m['peminta'],
                'waktu' => $waktu,
                'jumlah' => (int) ($it['count'] ?? 0),
                'url' => $it['url'],
                'can_dismiss' => $it['id'] !== 'ops-suggest',
            ];
        })->all();

        $dismissedToday = \App\Models\StaffOpsDismissal::query()
            ->where('user_id', $user->id)
            ->whereDate('dismissed_at', today())
            ->count();

        $activityLogs = AccountApprovalLog::query()
            ->with(['actor:id,nama_lengkap,role'])
            ->latest()
            ->paginate(10, ['*'], 'activity_page')
            ->withQueryString();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('AntrianNotifikasi', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'kpi' => [
                'total' => count($items),
                'menunggu' => count($items),
                'diproses' => $dismissedToday,
                'terjadwal' => 0,
            ],
            'items' => $items,
            'activity' => [
                'data' => $activityLogs->getCollection()->map(fn ($log) => [
                    'id' => $log->id,
                    'waktu' => $log->created_at?->translatedFormat('d M Y H:i'),
                    'subjek' => $log->subjectDisplayName(),
                    'subjek_meta' => trim((string) ($log->subjectDisplayEmail() ?? '') . ' · ' . (string) ($log->subjectDisplayNitk() ?? ''), ' ·'),
                    'aksi' => \App\Support\ApprovalActionLabel::label($log->action),
                    'tone' => \App\Support\ApprovalActionLabel::tone($log->action),
                    'oleh' => $log->actor?->nama_lengkap ?? 'Sistem / pendaftar',
                    'catatan' => $log->note,
                ])->values()->all(),
                'meta' => [
                    'page' => $activityLogs->currentPage(),
                    'last_page' => $activityLogs->lastPage(),
                    'total' => $activityLogs->total(),
                    'from' => $activityLogs->firstItem() ?? 0,
                    'to' => $activityLogs->lastItem() ?? 0,
                ],
            ],
            'urls' => [
                'dismiss' => route('notifications.ops.dismiss'),
                'riwayat_hapus' => route('admin.users', ['log_action' => 'deleted']),
            ],
        ]);
    }

    private function resolveAttendanceFilter(Request $request): array
    {
        $dateRaw = (string) $request->query('date', now()->toDateString());
        try {
            $date = Carbon::parse($dateRaw)->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'kelas' => (string) $request->query('kelas', ''),
            'tim' => (string) $request->query('tim', ''),
            'date' => $date,
        ];
    }

    private function buildAttendanceRows(array $filter): array
    {
        $date = Carbon::parse($filter['date']);
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $students = User::query()
            ->where('role', 'mahasiswa')
            ->when($filter['q'] !== '', function ($query) use ($filter) {
                $q = $filter['q'];
                $query->where(function ($inner) use ($q) {
                    $inner->where('nama_lengkap', 'like', "%{$q}%")
                        ->orWhere('nitk_nidn', 'like', "%{$q}%");
                });
            })
            ->when($filter['kelas'] !== '', fn ($query) => $query->where('kelas', $filter['kelas']))
            ->when($filter['tim'] !== '', fn ($query) => $query->where('tim', $filter['tim']))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'kelas', 'tim', 'finger_id']);

        $logs = LogAbsensi::query()
            ->whereBetween('waktu_tap', [$start, $end])
            ->whereIn('user_id', $students->pluck('id'))
            ->orderByDesc('waktu_tap')
            ->get()
            ->groupBy('user_id');

        return $students->map(function (User $student) use ($logs, $filter) {
            $studentLogs = $logs->get($student->id, collect());
            $latest = $studentLogs->first();

            return [
                'tanggal' => $filter['date'],
                'nama' => $student->nama_lengkap,
                'nim' => $student->nitk_nidn,
                'kelas' => $student->kelas ?: '-',
                'tim' => $student->tim ?: '-',
                'finger_id' => $student->finger_id ?: '-',
                'status' => $latest ? 'Sudah Tap' : 'Belum Tap',
                'waktu_tap' => $latest?->waktu_tap,
                'id_mesin' => $latest?->id_mesin ?: '-',
                'jumlah_tap' => $studentLogs->count(),
            ];
        })->values()->all();
    }

    private function attendanceSummary(array $rows): array
    {
        $total = count($rows);
        $sudahTap = collect($rows)->where('status', 'Sudah Tap')->count();
        $belumTap = max(0, $total - $sudahTap);
        $totalTap = collect($rows)->sum('jumlah_tap');

        return [
            'sudah_tap' => $sudahTap,
            'belum_tap' => $belumTap,
            'total_tap' => $totalTap,
            'persentase_tap' => $total > 0 ? round(($sudahTap / $total) * 100, 2) : 0,
            'total' => $total,
        ];
    }




    private function inferKelasParalel(?string $kelas): ?string
    {
        if ($kelas && preg_match('/\b([abc])\b/i', $kelas, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }
}

