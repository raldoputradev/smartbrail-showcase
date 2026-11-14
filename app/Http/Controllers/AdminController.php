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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use HandlesPeriodicReports;

    public function dashboard(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['sometimes', 'in:hari,minggu,bulan,tahun'],
            'tanggal' => ['sometimes', 'date'],
            'kelas' => ['sometimes', 'nullable', 'string', 'max:80'],
            'tim' => ['sometimes', 'nullable', 'string', 'max:80'],
            'aktivitas' => ['sometimes', 'nullable', 'string', Rule::in(['semua', 'absensi', 'loker', ''])],
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

        $latestUsers = User::query()
            ->with('programStudi:id,nama')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get([
                'id',
                'nama_lengkap',
                'nitk_nidn',
                'role',
                'kelas',
                'tim',
                'semester',
                'semester_type',
                'is_active',
                'email',
                'profile_photo_path',
                'updated_at',
                'created_at',
            ]);

        $opsShortcuts = [
            'pending_approval' => PendingAccountApproval::count(),
            'new_suggestions' => Suggestion::query()->where('status', 'baru')->count(),
            'pbl_readiness' => PblDataReadiness::report(),
            'enroll_belum' => User::query()
                ->where('role', 'mahasiswa')
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u) => ! $u->hasFingerprintEnrolled())
                ->count(),
        ];

        // DEMO-DASHBOARD — overlay angka ops agar card animasi terisi
        if (! empty($analytics['ops']) && is_array($analytics['ops'])) {
            $opsShortcuts = array_merge($opsShortcuts, $analytics['ops']);
        }

        return view('dashboards.admin', [
            'analytics' => $analytics,
            'latestTaps' => $latestTaps,
            'latestUsers' => $latestUsers,
            'opsShortcuts' => $opsShortcuts,
        ]);
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:hari,minggu,bulan,tahun'],
            'tanggal' => ['required', 'date'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'aktivitas' => ['nullable', 'string', Rule::in(['semua', 'absensi', 'loker', ''])],
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
        $aktivitas = in_array($aktivitas, ['semua', 'absensi', 'loker'], true) ? $aktivitas : 'semua';

        $totalUser = User::count();
        $totalDosen = User::where('role', 'dosen')->count();
        $totalMhs = User::where('role', 'mahasiswa')->count();
        $totalLaboran = User::where('role', 'laboran')->count();
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

        // Kompatibilitas lama
        $mesinAbsensi = MesinIot::where('tipe', 'absensi')->count();
        $mesinLoker = 0;

        $tapsCur = $this->countAbsensiByFilter($curStart, $curEnd, $kelas, $tim);
        $tapsPrev = $this->countAbsensiByFilter($prevStart, $prevEnd, $kelas, $tim);

        $lokerCur = 0;
        $lokerPrev = 0;

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
                'mesin_loker' => $mesinLoker,
                'tap_absensi' => $tapsCur,
                'tap_prev' => $tapsPrev,
                'tap_delta' => $tapsCur - $tapsPrev,
                'tap_meta' => DashboardStatLabels::tapAbsensiMeta($tapsCur - $tapsPrev, $tapsPrev, $mode),
                'akses_loker' => $lokerCur,
                'loker_prev' => $lokerPrev,
                'loker_delta' => $lokerCur - $lokerPrev,
                'loker_meta' => DashboardStatLabels::aksesLokerMeta($lokerCur - $lokerPrev, $lokerPrev, $mode),
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

        // DEMO-DASHBOARD — hapus baris overlay ini saat data asli dipakai penuh
        return DashboardDemoData::overlayAdmin($payload);
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
     * @return array{labels: array<int, string>, absensi: array<int, int>, loker: array<int, int>, subtitle: string}
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
                    'loker' => 0,
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

        // Kompatibilitas lama (jika masih ada pemanggil absensi/loker)
        $absensi = $buckets['absensi'] ?? array_fill(0, count($labels), 0);
        $loker = $buckets['loker'] ?? array_fill(0, count($labels), 0);

        return [
            'labels' => $labels,
            'series' => $series,
            'absensi' => $absensi,
            'loker' => $loker,
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

        if ($request->filled('role') && in_array($request->role, ['laboran', 'dosen', 'mahasiswa'], true)) {
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

        $users = $query->latest()->paginate(15)->appends($request->except(['fragment']));

        $logQuery = AccountApprovalLog::query()
            ->with([
                'user:id,nama_lengkap,email,nitk_nidn,role,approval_status,profile_photo_path',
                'actor:id,nama_lengkap,role,profile_photo_path',
            ]);

        if ($request->filled('log_action') && in_array($request->log_action, [
            'registered', 'admin_created', 'email_verified', 'approved', 'rejected',
            'deactivated', 'activated', 'updated_pending', 'deleted',
        ], true)) {
            $logQuery->where('action', $request->log_action);
        }

        if ($request->filled('log_role') && in_array($request->log_role, ['mahasiswa', 'dosen', 'laboran'], true)) {
            $role = $request->log_role;
            $logQuery->where(function ($q) use ($role) {
                $q->whereHas('user', fn ($uq) => $uq->where('role', $role))
                    ->orWhere('subject_role', $role);
            });
        }

        if ($request->filled('log_actor')) {
            if ($request->log_actor === 'system') {
                $logQuery->whereNull('actor_id');
            } elseif (is_numeric($request->log_actor)) {
                $logQuery->where('actor_id', (int) $request->log_actor);
            }
        }

        if ($request->filled('log_q')) {
            $logKeyword = trim((string) $request->log_q);
            $logQuery->where(function ($q) use ($logKeyword) {
                $q->where('note', 'like', "%{$logKeyword}%")
                    ->orWhere('subject_name', 'like', "%{$logKeyword}%")
                    ->orWhere('subject_email', 'like', "%{$logKeyword}%")
                    ->orWhere('subject_nitk_nidn', 'like', "%{$logKeyword}%")
                    ->orWhereHas('user', function ($uq) use ($logKeyword) {
                        $uq->where('nama_lengkap', 'like', "%{$logKeyword}%")
                            ->orWhere('email', 'like', "%{$logKeyword}%")
                            ->orWhere('nitk_nidn', 'like', "%{$logKeyword}%");
                    })
                    ->orWhereHas('actor', function ($aq) use ($logKeyword) {
                        $aq->where('nama_lengkap', 'like', "%{$logKeyword}%");
                    });
            });
        }

        $approvalLogs = $logQuery->latest()->paginate(10, ['*'], 'log_page')->withQueryString();

        $approvalActors = User::query()
            ->whereIn('id', AccountApprovalLog::query()->whereNotNull('actor_id')->distinct()->pluck('actor_id'))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'role']);

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
            'approved' => (int) User::query()->where('approval_status', 'approved')->count(),
            'pending_pipeline' => $pendingOtpCount + $pendingApprovalCount,
            'locked' => $loginLockedCount,
            'enrolled' => $mahasiswaEnrolled,
            'enroll_pct' => $mahasiswaTotal > 0 ? round(($mahasiswaEnrolled / $mahasiswaTotal) * 100) : 0,
        ];

        $roleChart = [
            'labels' => ['Mahasiswa', 'Dosen', 'Laboran'],
            'values' => [
                $userStats['mahasiswa'],
                $userStats['dosen'],
                $userStats['laboran'],
            ],
            'colors' => ['#0f766e', '#2563eb', '#ea580c'],
        ];

        $logExpanded = $request->hasAny(['log_q', 'log_action', 'log_role', 'log_actor', 'log_page']);

        if ($request->query('fragment') === 'list' || $request->header('X-UM-Fragment') === 'list') {
            return response()
                ->view('admin.partials.users-list-fragment', compact('users'))
                ->header('X-UM-Total', (string) $users->total())
                ->header('X-UM-Page', (string) $users->currentPage());
        }

        return view('admin.users', compact(
            'users',
            'approvalLogs',
            'approvalActors',
            'programStudi',
            'pblTimOptions',
            'angkatanYears',
            'loginLockedCount',
            'pendingApprovalCount',
            'pendingApprovalUsers',
            'pendingOtpCount',
            'pendingOtpUsers',
            'awaitingActivationCount',
            'onboardingMahasiswa',
            'userStats',
            'roleChart',
            'logExpanded',
        ));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nitk_nidn' => ['required', 'string', 'max:50', 'unique:users,nitk_nidn'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['laboran', 'dosen', 'mahasiswa'])],
            'is_active' => ['nullable', 'boolean'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'angkatan' => ['nullable', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'semester' => ['nullable', 'integer', 'min:1', 'max:8'],
            'semester_type' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'program_studi_id' => ['nullable', 'exists:program_studi,id'],
            'shift' => ['nullable', Rule::in(['pagi', 'malam'])],
            'kelas_paralel' => ['nullable', Rule::in(['A', 'B', 'C'])],
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
        ]);

        if ($validated['role'] === 'mahasiswa') {
            try {
                $user = FingerIdAllocator::ensureForMahasiswa($user);
            } catch (\RuntimeException $e) {
                $user->delete();

                return back()->with('error', $e->getMessage())->withInput();
            }
        }

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
            'role' => ['required', Rule::in(['laboran', 'dosen', 'mahasiswa'])],
            'is_active' => ['nullable', 'boolean'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'angkatan' => ['nullable', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'semester' => ['nullable', 'integer', 'min:1', 'max:8'],
            'semester_type' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'program_studi_id' => ['nullable', 'exists:program_studi,id'],
            'shift' => ['nullable', Rule::in(['pagi', 'malam'])],
            'kelas_paralel' => ['nullable', Rule::in(['A', 'B', 'C'])],
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
        ];
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
            $payload['finger_id'] = null;
        }

        if (!empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

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
                'center_sub' => 'Mahasiswa',
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
        return view('admin.fingerprint', $this->fingerprintState($request));
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
        ];

        $fingerprintStats = [
            'total' => User::query()->where('role', 'mahasiswa')->count(),
            'enrolled' => FingerprintSyncService::countEnrolledMahasiswa(),
            'pending_enroll' => FingerprintSyncService::countPendingEnrollMahasiswa(),
            'unassigned' => User::query()->where('role', 'mahasiswa')->whereNull('finger_id')->count(),
            'capacity_used' => User::query()->where('role', 'mahasiswa')->whereNotNull('finger_id')->distinct('finger_id')->count('finger_id'),
            'capacity_total' => 200,
        ];

        $fingerprintStats['completion_rate'] = $fingerprintStats['total'] > 0
            ? round(($fingerprintStats['enrolled'] / $fingerprintStats['total']) * 100, 1)
            : 0;

        $query = User::query()->where('role', 'mahasiswa');

        if ($filter['q'] !== '') {
            $kw = $filter['q'];
            $query->where(function ($qb) use ($kw) {
                $qb->where('nama_lengkap', 'like', "%{$kw}%")
                    ->orWhere('nitk_nidn', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%");
            });
        }

        if ($filter['status'] === 'enrolled') {
            $enrolledIds = FingerprintSyncService::enrolledQuery()
                ->pluck('id');
            $query->whereIn('id', $enrolledIds);
        } elseif ($filter['status'] === 'pending_enroll') {
            $enrolledIds = FingerprintSyncService::enrolledQuery()->pluck('id');
            $query->whereNotNull('finger_id')
                ->whereNotIn('id', $enrolledIds);
        } elseif ($filter['status'] === 'legacy_re_enroll') {
            $legacyIds = User::query()
                ->where('role', 'mahasiswa')
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
        if ($user->role !== 'mahasiswa') {
            return back()->with('error', 'Finger ID hanya untuk mahasiswa.');
        }

        $validated = $request->validate([
            'finger_id' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if (!empty($validated['finger_id'])) {
            $exists = User::where('finger_id', $validated['finger_id'])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                return back()->with('error', "Finger ID #{$validated['finger_id']} sudah dipakai mahasiswa lain.");
            }
            $user->finger_id = $validated['finger_id'];
        } else {
            try {
                $user->finger_id = FingerIdAllocator::assignNext();
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        MahasiswaShiftResolver::ensure($user, null, true);
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');

        $shiftLabel = $user->shift ? ' · shift ' . $user->shift : '';

        return back()->with('success', "Finger ID #{$user->finger_id} berhasil di-assign untuk {$user->nama_lengkap}{$shiftLabel}.");
    }

    public function backfillMahasiswaShift()
    {
        $count = MahasiswaShiftResolver::backfillMissing();

        return back()->with('success', "Shift absensi diperbarui untuk {$count} mahasiswa.");
    }

    public function resetFingerId(User $user)
    {
        if ($user->role !== 'mahasiswa') {
            return back()->with('error', 'Hanya mahasiswa yang memiliki finger_id.');
        }
        $user->finger_id = null;
        $user->finger_template = null;
        $user->save();
        \App\Support\AbsensiSnapshotSignals::committed(['fingerprint'], 'laboran');

        return back()->with('success', "Finger ID untuk {$user->nama_lengkap} berhasil direset.");
    }

    public function clearFingerprintEnroll(User $user)
    {
        if ($user->role !== 'mahasiswa') {
            return back()->with('error', 'Hanya mahasiswa.');
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

    public function monitoring()
    {
        $payload = $this->buildMonitoringPayload();

        return view('admin.monitoring', [
            'metrics' => $payload['metrics'],
            'machines' => $payload['machines'],
            'recentActivities' => $payload['activities'],
            'hourly' => $payload['hourly'],
            'health' => $payload['health'],
            'onlineThresholdMinutes' => MesinIot::ONLINE_THRESHOLD_MINUTES,
        ]);
    }

    public function monitoringFeed()
    {
        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            ...$this->buildMonitoringPayload(),
        ]);
    }

    /**
     * @return array{
     *   metrics: array<string, mixed>,
     *   machines: list<array<string, mixed>>,
     *   activities: list<array<string, mixed>>,
     *   hourly: array{labels: list<string>, absensi: list<int>, loker: list<int>},
     *   health: array{labels: list<string>, values: list<int>, colors: list<string>}
     * }
     */
    private function buildMonitoringPayload(): array
    {
        $onlineThreshold = now()->subMinutes(MesinIot::ONLINE_THRESHOLD_MINUTES);
        $fleet = MesinIot::query()->where('tipe', '!=', 'loker');
        $machineTotal = (clone $fleet)->count();
        $machineOnline = (clone $fleet)->online()->count();
        $machineActive = (clone $fleet)->where('is_active', true)->count();
        $machineOffline = max(0, $machineActive - $machineOnline);
        $machineInactive = max(0, $machineTotal - $machineActive);
        $latestHeartbeat = (clone $fleet)
            ->whereNotNull('last_seen_at')
            ->latest('last_seen_at')
            ->first();

        $metrics = [
            'machine_total' => $machineTotal,
            'machine_online' => $machineOnline,
            'machine_offline' => $machineOffline,
            'machine_inactive' => $machineInactive,
            'latest_sync' => $latestHeartbeat?->last_seen_at?->translatedFormat('d M Y, H:i:s') ?? 'Belum ada heartbeat',
            'latest_sync_human' => $latestHeartbeat?->last_seen_at?->diffForHumans() ?? 'Belum ada',
            'absensi_today' => LogAbsensi::query()->whereDate('waktu_tap', today())->count(),
            'events_today' => 0,
        ];
        $metrics['events_today'] = $metrics['absensi_today'];

        $machines = (clone $fleet)
            ->orderByRaw('last_seen_at IS NULL')
            ->orderByDesc('last_seen_at')
            ->orderBy('id_mesin')
            ->limit(20)
            ->get()
            ->map(function (MesinIot $machine) use ($onlineThreshold) {
                $isOnline = $machine->is_active
                    && $machine->last_seen_at
                    && $machine->last_seen_at->gte($onlineThreshold);

                $status = $isOnline ? 'online' : ($machine->is_active ? 'offline' : 'inactive');

                return [
                    'id_mesin' => $machine->id_mesin,
                    'nama_mesin' => $machine->nama_mesin ?: $machine->id_mesin,
                    'tipe' => (string) $machine->tipe,
                    'tipe_label' => Str::title(str_replace('_', ' ', (string) $machine->tipe)),
                    'is_active' => (bool) $machine->is_active,
                    'is_online' => $isOnline,
                    'status' => $status,
                    'status_label' => match ($status) {
                        'online' => 'Online',
                        'offline' => 'Offline',
                        default => 'Nonaktif',
                    },
                    'last_seen_at' => $machine->last_seen_at?->toIso8601String(),
                    'last_seen_label' => $machine->last_seen_at
                        ? $machine->last_seen_at->diffForHumans()
                        : 'Belum pernah heartbeat',
                    'last_seen_exact' => $machine->last_seen_at
                        ? $machine->last_seen_at->translatedFormat('d M Y H:i:s')
                        : '—',
                ];
            })
            ->values()
            ->all();

        $recentAbsensi = LogAbsensi::query()
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
            ]);

        $activities = $recentAbsensi
            ->sortByDesc('time')
            ->take(10)
            ->values()
            ->all();

        $day = today();
        $hourlyLabels = [];
        $hourlyAbsensi = array_fill(0, 17, 0);
        for ($h = 6; $h <= 22; $h++) {
            $hourlyLabels[] = sprintf('%02d', $h);
        }

        $absensiHourRows = LogAbsensi::query()
            ->whereDate('waktu_tap', $day)
            ->whereTime('waktu_tap', '>=', '06:00:00')
            ->whereTime('waktu_tap', '<=', '22:59:59')
            ->selectRaw('HOUR(waktu_tap) as jam, COUNT(*) as total')
            ->groupBy('jam')
            ->pluck('total', 'jam');

        for ($h = 6; $h <= 22; $h++) {
            $idx = $h - 6;
            $hourlyAbsensi[$idx] = (int) ($absensiHourRows[$h] ?? 0);
        }

        return [
            'metrics' => $metrics,
            'machines' => $machines,
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

    public function settings()
    {
        $libur = \App\Models\AbsensiKalenderLibur::query()->orderByDesc('tanggal')->limit(60)->get();
        $prodi = \App\Models\ProgramStudi::query()->orderBy('nama')->get();
        $registrasi = \App\Support\RegistrationGate::flags();

        return view('admin.settings', compact('libur', 'prodi', 'registrasi'));
    }

    public function updateRegistrationSettings(Request $request)
    {
        \App\Support\RegistrationGate::save([
            'dosen' => $request->boolean('dosen'),
            'mahasiswa' => $request->boolean('mahasiswa'),
        ]);

        $flags = \App\Support\RegistrationGate::flags();
        $label = fn (bool $open) => $open ? 'dibuka' : 'ditutup';

        return back()->with('success', "Pendaftaran mandiri dosen {$label($flags['dosen'])}, mahasiswa {$label($flags['mahasiswa'])}. Login dan aktivasi akun tidak terpengaruh.");
    }

    public function notifications()
    {
        $notifications = collect();

        $pendingUsers = PendingAccountApproval::count();
        if ($pendingUsers > 0) {
            $notifications->push([
                'title' => PendingAccountApproval::notificationTitle(),
                'description' => 'Akun mahasiswa/dosen sudah siap. Setujui atau tolak di Manajemen User.',
                'action' => 'Ke Manajemen User',
                'time' => now()->translatedFormat('d M Y, H:i'),
                'url' => route('admin.users', ['verify_status' => 'ready_approve']),
                'tone' => 'amber',
            ]);
        }

        $otpPending = PendingEmailVerification::count();
        if ($otpPending > 0) {
            $notifications->push([
                'title' => PendingEmailVerification::notificationTitle(),
                'description' => 'Pendaftar masih di tahap OTP. Pantau di Manajemen User.',
                'action' => 'Lihat OTP pending',
                'time' => now()->translatedFormat('d M Y, H:i'),
                'url' => route('admin.users', ['verify_status' => 'otp_pending']),
                'tone' => 'amber',
            ]);
        }

        $newSuggestions = Suggestion::query()->where('status', 'baru')->count();
        if ($newSuggestions > 0) {
            $notifications->push([
                'title' => "{$newSuggestions} saran & masukan baru",
                'description' => 'Buka Saran & Masukan → balas atau tandai status tiket.',
                'action' => 'Buka Saran & Masukan',
                'time' => now()->translatedFormat('d M Y, H:i'),
                'url' => route('admin.suggestions.index', ['status' => 'baru']),
                'tone' => 'green',
            ]);
        }

        $loginLocked = PendingLoginUnlock::count();
        if ($loginLocked > 0) {
            $notifications->push([
                'title' => PendingLoginUnlock::notificationTitle(),
                'description' => 'Akun terkunci karena gagal login berulang. Buka kunci di Manajemen User.',
                'action' => 'Buka kunci login',
                'time' => now()->translatedFormat('d M Y, H:i'),
                'url' => route('admin.users', ['login_lock' => 'locked']),
                'tone' => 'red',
            ]);
        }

        $threshold = MesinIot::ONLINE_THRESHOLD_MINUTES;
        $offlineMachines = MesinIot::query()
            ->where('tipe', '!=', 'loker')
            ->where('is_active', true)
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subMinutes($threshold));
            })
            ->count();
        if ($offlineMachines > 0) {
            $notifications->push([
                'title' => "{$offlineMachines} mesin IoT terdeteksi offline",
                'description' => 'Cek daya, jaringan, dan status mesin di Monitoring.',
                'action' => 'Ke Monitoring',
                'time' => now()->translatedFormat('d M Y, H:i'),
                'url' => route('admin.monitoring'),
                'tone' => 'red',
            ]);
        }

        if ($notifications->isEmpty()) {
            $notifications->push([
                'title' => 'Tidak ada antrian prioritas saat ini',
                'description' => 'Semua tugas operasional sedang tenang. Gunakan panduan arah di atas untuk menu lain.',
                'action' => null,
                'time' => now()->translatedFormat('d M Y, H:i'),
                'url' => null,
                'tone' => 'muted',
            ]);
        }

        $activityLogs = AccountApprovalLog::query()
            ->with(['actor:id,nama_lengkap,role'])
            ->whereIn('action', [
                'registered', 'admin_created', 'email_verified', 'approved', 'rejected', 'updated_pending', 'deleted',
            ])
            ->latest()
            ->paginate(15, ['*'], 'activity_page')
            ->withQueryString();

        $pendingApprovalCount = PendingAccountApproval::count();
        $pendingApprovalUsers = PendingAccountApproval::recent(6);

        return view('admin.notifications', compact('notifications', 'activityLogs', 'pendingApprovalCount', 'pendingApprovalUsers'));
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

