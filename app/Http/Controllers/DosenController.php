<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AdminShellProps;
use App\Http\Controllers\Concerns\DashboardMiniCalendar;
use App\Http\Controllers\Concerns\HandlesPeriodicReports;
use App\Models\Announcement;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\User;
use App\Support\DashboardDemoData;
use App\Support\DashboardStatLabels;
use App\Support\DosenKelasScope;
use App\Support\DosenMahasiswaScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DosenController extends Controller
{
    use AdminShellProps, DashboardMiniCalendar, HandlesPeriodicReports;

    protected function periodicReportDosen(): ?User
    {
        return $this->dosenUser();
    }

    protected function periodicReportPanel(): array
    {
        return [
            'sidebar' => 'dosen._sidebar',
            'is_dosen' => true,
            'routes' => [
                'index' => 'dosen.reports',
                'export_excel' => 'dosen.reports.export.excel',
                'export_word' => 'dosen.reports.export.word',
                'print' => 'dosen.reports.print',
                'rekap' => 'dosen.absensi',
                'student_detail' => 'dosen.reports.student-detail',
            ],
        ];
    }

    public function dashboard(Request $request)
    {
        $dosen = $this->dosenUser();
        $pblMeta = $this->pblMeta($dosen);

        $kelasOptions = $this->allMahasiswaQuery(false)
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        $timOptions = $this->allMahasiswaQuery(false)
            ->whereNotNull('tim')
            ->where('tim', '!=', '')
            ->distinct()
            ->orderBy('tim')
            ->pluck('tim');

        $validated = $request->validate([
            'mode' => ['sometimes', 'in:hari,minggu,bulan,tahun'],
            'tanggal' => ['sometimes', 'date'],
            'kelas' => ['sometimes', 'nullable', 'string', 'max:80'],
            'tim' => ['sometimes', 'nullable', 'string', 'max:80'],
            'aktivitas' => ['sometimes', 'nullable', 'string', 'in:semua,absensi'],
        ]);

        $weekday = $request->filled('weekday') ? (int) $request->input('weekday') : null;
        if ($weekday !== null && ($weekday < 1 || $weekday > 7)) {
            $weekday = null;
        }

        $timFilter = $this->normalizeTimFilter($dosen, $validated['tim'] ?? '');

        $analytics = $this->computeDashboardAnalytics(
            $dosen,
            $validated['mode'] ?? 'minggu',
            $validated['tanggal'] ?? now()->toDateString(),
            $validated['kelas'] ?? '',
            $timFilter,
            $weekday,
            $validated['aktivitas'] ?? 'semua'
        );

        $pengumumanTerbaru = Announcement::active()
            ->pengumumanSaja()
            ->forRole('dosen')
            ->with('author:id,nama_lengkap')
            ->latest()
            ->limit(3)
            ->get();

        $absensiTerbaru = LogAbsensi::query()
            ->with(['user:id,nama_lengkap,nitk_nidn,role,tim,profile_photo_path'])
            ->whereHas('user', fn ($q) => $q->where('role', 'mahasiswa'))
            ->latest('waktu_tap')
            ->limit(8)
            ->get();

        $user = $request->user();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('DosenDashboard', [
            'menu' => $this->dosenSidebarMenu($user),
            'notif' => $this->dosenNotifications($user),
            'analytics' => $analytics,
            'kelas_options' => $kelasOptions->values()->all(),
            'tim_options' => $timOptions->values()->all(),
            'pengumuman' => $pengumumanTerbaru->map(fn ($p) => [
                'id' => $p->id,
                'judul' => $p->judul,
                'prioritas' => $p->prioritas ?: 'normal',
                'isi' => \Illuminate\Support\Str::limit(strip_tags((string) $p->isi), 160),
                'author' => optional($p->author)->nama_lengkap ?? 'Admin',
                'waktu' => optional($p->created_at)->diffForHumans(),
            ])->all(),
            'absensi_terbaru' => $absensiTerbaru->map(fn ($log) => [
                'id' => $log->id,
                'nama' => $log->user->nama_lengkap ?? '-',
                'nitk' => $log->user->nitk_nidn ?? '—',
                'tim' => $log->user->tim ?: '—',
                'foto' => $log->user->profile_photo_url ?? null,
                'jenis' => strtolower((string) ($log->jenis ?? '')) ?: 'tap',
                'sesi' => $log->sesi ?? '—',
                'waktu' => optional($log->waktu_tap)->translatedFormat('d M Y · H:i'),
                'menit_terlambat' => (int) ($log->menit_terlambat ?? 0),
                'pulang_lembur' => ! empty($log->pulang_lembur),
            ])->all(),
            'kalender_mini' => $this->dashboardMiniCalendar($request->query('kcal')),
            'agenda_terdekat' => $this->dashboardAgendaTerdekat(),
            'pbl' => [
                'has_pbl' => (bool) ($pblMeta['has_pbl'] ?? false),
                'tim_keys' => collect($pblMeta['tim_keys'] ?? [])->values()->all(),
            ],
            'urls' => [
                'data' => route('dosen.dashboard.data'),
                'minical' => route('dosen.dashboard.minical'),
                'rekap' => route('dosen.absensi'),
                'reports' => route('dosen.reports'),
                'mahasiswa' => route('dosen.mahasiswa'),
                'announcements' => route('dosen.announcements'),
                'ruangan' => route('dosen.ruangan'),
            ],
        ]);
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $dosen = $this->dosenUser();

        $validated = $request->validate([
            'mode' => ['required', 'in:hari,minggu,bulan,tahun'],
            'tanggal' => ['required', 'date'],
            'kelas' => ['nullable', 'string', 'max:80'],
            'tim' => ['nullable', 'string', 'max:80'],
            'aktivitas' => ['nullable', 'string', 'in:semua,absensi'],
        ]);

        $weekday = $request->filled('weekday') ? (int) $request->input('weekday') : null;
        if ($weekday !== null && ($weekday < 1 || $weekday > 7)) {
            $weekday = null;
        }

        $payload = $this->computeDashboardAnalytics(
            $dosen,
            $validated['mode'],
            $validated['tanggal'],
            $validated['kelas'] ?? '',
            $this->normalizeTimFilter($dosen, $validated['tim'] ?? ''),
            $weekday,
            $validated['aktivitas'] ?? 'semua'
        );

        return response()->json($payload);
    }

    public function announcements(Request $request)
    {
        $user = $request->user();

        $received = Announcement::active()
            ->pengumumanSaja()
            ->forAudience($user)
            ->with('author:id,nama_lengkap,role')
            ->latest()->limit(60)->get();

        // Tandai terbaca (lonceng bersih), sama seperti versi lama.
        $rows = $received->map(fn ($a) => [
            'announcement_id' => $a->id,
            'user_id' => $user->id,
            'read_at' => Carbon::now(),
        ])->all();
        if (! empty($rows)) {
            \App\Models\AnnouncementRead::query()->upsert($rows, ['announcement_id', 'user_id'], ['read_at']);
        }

        $mine = Announcement::query()
            ->where('dibuat_oleh', $user->id)
            ->latest()->limit(60)->get();

        $card = function (Announcement $a) {
            return [
                'id' => $a->id,
                'judul' => $a->judul,
                'isi' => \Illuminate\Support\Str::limit((string) $a->isi, 220),
                'isi_full' => (string) $a->isi,
                'prioritas' => $a->prioritas ?? 'normal',
                'target_label' => $a->target_tim
                    ? 'Tim '.$a->target_tim
                    : ($a->target === 'mahasiswa' ? 'Mahasiswa' : ($a->target === 'dosen' ? 'Dosen' : 'Semua')),
                'status' => ! $a->is_active ? 'Draft' : ($a->expires_at && $a->expires_at->isPast() ? 'Kedaluwarsa' : 'Terbit'),
                'tanggal' => optional($a->created_at)?->translatedFormat('d M Y, H:i'),
                'penulis' => $a->author?->nama_lengkap,
            ];
        };

        $timOptions = \App\Models\PblProject::query()
            ->where(fn ($q) => $q->where('manpro_user_id', $user->id)->orWhere('co_manpro_user_id', $user->id))
            ->where('aktif', true)->orderBy('nama')->pluck('nama')->values();

        $todayCount = $mine->filter(fn ($a) => optional($a->created_at)?->isToday())->count();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('DosenPengumuman', [
            'menu' => $this->dosenSidebarMenu($user),
            'notif' => $this->dosenNotifications($user),
            'stats' => [
                'total_mine' => $mine->count(),
                'ke_tim' => $mine->filter(fn ($a) => ! empty($a->target_tim))->count(),
                'ke_mahasiswa' => $mine->filter(fn ($a) => $a->target === 'mahasiswa' && empty($a->target_tim))->count(),
                'diterima' => $received->count(),
            ],
            'mine' => $mine->map($card)->values(),
            'received' => $received->map($card)->values(),
            'tim_options' => $timOptions,
            'store_url' => route('dosen.announcements.store'),
            'batas' => ['harian' => 10, 'terpakai' => $todayCount],
            'aktivitas' => $mine->take(5)->map(fn ($a) => [
                'judul' => $a->judul,
                'tanggal' => optional($a->created_at)?->translatedFormat('d M Y, H:i'),
                'status' => $a->is_active ? 'diterbitkan' : 'disimpan sebagai draft',
            ])->values(),
        ]);
    }

    /**
     * Dosen menerbitkan pengumuman — tujuan: semua mahasiswa atau tim PBL bimbingannya.
     */
    public function storeAnnouncement(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'isi' => ['required', 'string', 'min:10', 'max:5000'],
            'tujuan' => ['required', 'string', 'max:120'],
            'prioritas' => ['required', 'in:normal,penting,urgent'],
            'expires_at' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addDays(30)->toDateString()],
            'action' => ['nullable', 'in:draft,publish'],
        ], [
            'expires_at.before_or_equal' => 'Masa berlaku pengumuman maksimal 30 hari.',
        ]);

        $hariIni = Announcement::query()
            ->where('dibuat_oleh', $user->id)
            ->whereDate('created_at', today())
            ->count();
        if ($hariIni >= 10) {
            return back()->withErrors(['judul' => 'Batas 10 pengumuman per hari tercapai — coba lagi besok.']);
        }

        $tujuan = $validated['tujuan'];
        $targetTim = null;
        if (str_starts_with($tujuan, 'tim:')) {
            $namaTim = substr($tujuan, 4);
            $tims = \App\Support\RuanganBookingService::timBimbingan($user)->pluck('nama');
            abort_unless($tims->contains($namaTim), 403, 'Tim itu bukan bimbingan Anda.');
            $targetTim = $namaTim;
        } elseif ($tujuan !== 'mahasiswa') {
            abort(422, 'Tujuan tidak dikenal.');
        }

        $publish = ($validated['action'] ?? 'publish') === 'publish';
        Announcement::query()->create([
            'judul' => $validated['judul'],
            'isi' => $validated['isi'],
            'target' => 'mahasiswa',
            'target_tim' => $targetTim,
            'channel' => Announcement::CHANNEL_PENGUMUMAN,
            'prioritas' => $validated['prioritas'],
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $publish,
            'dibuat_oleh' => $user->id,
        ]);

        $label = $targetTim ? "tim {$targetTim}" : 'semua mahasiswa';

        return back()->with('success', $publish
            ? "Pengumuman diterbitkan ke {$label}."
            : 'Draft pengumuman disimpan.');
    }

    public function notifikasi(Request $request)
    {
        $user = $request->user();
        $opsItems = \App\Support\StaffNotificationService::operationalItems($user);

        return view('dosen.notifikasi', [
            'opsItems' => $opsItems,
            'pblMeta' => $this->pblMeta($user),
        ]);
    }

    public function absensi(Request $request)
    {
        $dosen = $this->dosenUser();

        $filter = [
            'q' => trim((string) $request->query('q', '')),
            'id_mesin' => (string) $request->query('id_mesin', ''),
            'tanggal_mulai' => (string) $request->query('tanggal_mulai', now()->subDays(7)->toDateString()),
            'tanggal_akhir' => (string) $request->query('tanggal_akhir', now()->toDateString()),
        ];

        $query = LogAbsensi::query()
            ->with(['user:id,nama_lengkap,nitk_nidn,role,tim,kelas'])
            ->whereHas('user', fn ($q) => $q->where('role', 'mahasiswa'));

        if ($filter['id_mesin'] !== '') {
            $query->where('id_mesin', $filter['id_mesin']);
        }
        if ($filter['tanggal_mulai'] !== '') {
            $query->whereDate('waktu_tap', '>=', $filter['tanggal_mulai']);
        }
        if ($filter['tanggal_akhir'] !== '') {
            $query->whereDate('waktu_tap', '<=', $filter['tanggal_akhir']);
        }
        if ($filter['q'] !== '') {
            $kw = $filter['q'];
            $query->whereHas('user', function ($qb) use ($kw) {
                $qb->where('role', 'mahasiswa')
                    ->where(function ($inner) use ($kw) {
                        $inner->where('nama_lengkap', 'like', "%{$kw}%")
                            ->orWhere('nitk_nidn', 'like', "%{$kw}%");
                    });
            });
        }

        $logs = $query->latest('waktu_tap')->paginate(20)->withQueryString();
        $mesinList = MesinIot::where('tipe', 'absensi')->orderBy('id_mesin')->get(['id_mesin', 'nama_mesin']);

        return view('dosen.absensi', [
            'logs' => $logs,
            'filter' => $filter,
            'mesinList' => $mesinList,
            'pblMeta' => $this->pblMeta($dosen),
        ]);
    }



    public function mahasiswa(Request $request)
    {
        $dosen = $this->dosenUser();
        $pblMeta = $this->pblMeta($dosen);
        $timKeys = $pblMeta['tim_keys'];

        $scope = (string) $request->query('scope', $pblMeta['has_pbl'] ? 'mine' : 'all');
        if (! in_array($scope, ['all', 'mine'], true)) {
            $scope = 'all';
        }

        $sort = (string) $request->query('sort', 'nama');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->query('per_page', 15);
        if (! in_array($perPage, [15, 30, 50], true)) {
            $perPage = 15;
        }

        $baseStatsQuery = $this->allMahasiswaQuery(false);
        $mineStatsQuery = (clone $baseStatsQuery);
        if ($timKeys->isNotEmpty()) {
            $mineStatsQuery->whereIn('tim', $timKeys->all());
        } else {
            $mineStatsQuery->whereRaw('0 = 1');
        }

        $stats = [
            'total' => (clone $baseStatsQuery)->count(),
            'aktif' => (clone $baseStatsQuery)->where('is_active', true)->count(),
            'pending' => (clone $baseStatsQuery)->where('is_active', false)->count(),
            'finger' => (clone $baseStatsQuery)->whereNotNull('finger_id')->count(),
            'mine' => (clone $mineStatsQuery)->count(),
            'mine_finger' => (clone $mineStatsQuery)->whereNotNull('finger_id')->count(),
            'tanpa_tim' => (clone $baseStatsQuery)->where(function ($q) {
                $q->whereNull('tim')->orWhere('tim', '');
            })->count(),
        ];

        $query = $this->mahasiswaDirectoryQuery($request, $dosen, $scope, $timKeys);

        $sortMap = [
            'nama' => 'nama_lengkap',
            'nim' => 'nitk_nidn',
            'kelas' => 'kelas',
            'tim' => 'tim',
            'semester' => 'semester',
        ];
        $sortCol = $sortMap[$sort] ?? 'nama_lengkap';
        $mahasiswa = $query->orderBy($sortCol, $dir)->orderBy('nama_lengkap')->paginate($perPage)->withQueryString();

        $kelasOptions = $this->allMahasiswaQuery(false)->whereNotNull('kelas')->where('kelas', '!=', '')->distinct()->orderBy('kelas')->pluck('kelas');
        $timOptions = $this->allMahasiswaQuery(false)->whereNotNull('tim')->where('tim', '!=', '')->distinct()->orderBy('tim')->pluck('tim');
        $semesterOptions = $this->allMahasiswaQuery(false)->whereNotNull('semester')->distinct()->orderBy('semester')->pluck('semester');
        $myTimOptions = $timKeys->values();

        return view('dosen.mahasiswa', compact(
            'mahasiswa',
            'kelasOptions',
            'timOptions',
            'semesterOptions',
            'myTimOptions',
            'stats',
            'scope',
            'sort',
            'dir',
            'perPage',
            'timKeys',
            'pblMeta'
        ));
    }

    public function exportMahasiswaCsv(Request $request)
    {
        $dosen = $this->dosenUser();
        $pblMeta = $this->pblMeta($dosen);
        $timKeys = $pblMeta['tim_keys'];
        $scope = (string) $request->query('scope', $pblMeta['has_pbl'] ? 'mine' : 'all');
        if (! in_array($scope, ['all', 'mine'], true)) {
            $scope = 'all';
        }

        $rows = $this->mahasiswaDirectoryQuery($request, $dosen, $scope, $timKeys)
            ->orderBy('nama_lengkap')
            ->get([
                'nama_lengkap',
                'nitk_nidn',
                'kelas',
                'tim',
                'email',
                'semester',
                'angkatan',
                'shift',
                'finger_id',
                'is_active',
            ]);

        $filename = 'mahasiswa-dosen-'.now()->format('Ymd-His').'.csv';

        $kelasKeys = DosenKelasScope::kelasKeysFor($dosen);

        return response()->streamDownload(function () use ($rows, $kelasKeys) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Nama', 'NIM', 'Kelas', 'Tim PBL', 'Email', 'Semester', 'Angkatan', 'Shift',
                'Finger ID', 'Status', 'Kelas Ampuan Dosen',
            ]);
            foreach ($rows as $m) {
                $mine = $kelasKeys->contains($m->kelas) ? 'Ya' : 'Tidak';
                fputcsv($out, [
                    $m->nama_lengkap,
                    $m->nitk_nidn,
                    $m->kelas ?? '',
                    $m->tim ?? '',
                    $m->email ?? '',
                    $m->semester ?? '',
                    $m->angkatan ?? '',
                    $m->shift ?? '',
                    $m->finger_id ?? '',
                    $m->is_active ? 'Aktif' : 'Pending',
                    $mine,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $timKeys
     * @return Builder<User>
     */
    private function mahasiswaDirectoryQuery(Request $request, User $dosen, string $scope, $timKeys): Builder
    {
        // Cakupan dosen = kelas yang ditugaskan laboran UNION tim PBL
        // yang ia bimbing; tanpa keduanya → tolak akses.
        $query = DosenKelasScope::applySupervisedMahasiswaQuery(
            $this->allMahasiswaQuery(false),
            $dosen
        );

        if ($request->filled('kelas')) {
            $query->where('kelas', $request->kelas);
        }
        if ($request->filled('tim')) {
            $query->where('tim', $request->tim);
        }
        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('q')) {
            $kw = trim((string) $request->q);
            $query->where(function ($qb) use ($kw) {
                $qb->where('nama_lengkap', 'like', "%{$kw}%")
                    ->orWhere('nitk_nidn', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%")
                    ->orWhere('tim', 'like', "%{$kw}%")
                    ->orWhere('kelas', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('finger') && in_array($request->finger, ['yes', 'no'], true)) {
            if ($request->finger === 'yes') {
                $query->whereNotNull('finger_id');
            } else {
                $query->whereNull('finger_id');
            }
        }

        if ($request->boolean('no_tim')) {
            $query->where(function ($q) {
                $q->whereNull('tim')->orWhere('tim', '');
            });
        }

        return $query;
    }

    /**
     * Laporan periodik dosen → halaman React (data dari PeriodicReportBuilder
     * yang sama dengan versi Blade; scope mahasiswa tetap via periodicReportDosen).
     */
    public function reports(Request $request)
    {
        $user = $request->user();
        $p = $this->buildPeriodicReportPayload($request);

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('DosenReports', [
            'menu' => $this->dosenSidebarMenu($user),
            'notif' => $this->dosenNotifications($user),
            'scope_empty' => ! \App\Support\DosenKelasScope::hasSupervision($user),
            'scope_message' => 'Belum ada mahasiswa dalam bimbingan Anda, sehingga laporan kosong. Minta laboran menugaskan kelas di Manajemen User atau pastikan Anda terdaftar sebagai manpro/co-manpro tim PBL.',
            'report' => [
                'filter' => $p['filter'],
                'periode_label' => $p['periodeLabel'],
                'activity_label' => $p['activityLabel'],
                'kebijakan_label' => $p['kebijakanLabel'],
                'kelas_options' => collect($p['kelasOptions'])->values()->all(),
                'tim_options' => collect($p['timOptions'])->values()->all(),
                'summary' => $p['summary'],
                'chart' => $p['chart'],
                'machines' => collect($p['topMachines'])->values()->all(),
                'recent' => collect($p['recentActivities'])->map(fn ($r) => array_merge($r, [
                    'waktu' => $r['waktu'] instanceof \Carbon\Carbon
                        ? $r['waktu']->translatedFormat('d M · H:i')
                        : (string) $r['waktu'],
                ]))->values()->all(),
                'students' => collect($p['studentRecaps'])->map(fn ($r) => array_merge($r, [
                    'terakhir' => !empty($r['terakhir'])
                        ? \Carbon\Carbon::parse($r['terakhir'])->translatedFormat('d M Y · H:i')
                        : null,
                ]))->values()->all(),
                'rankings' => $p['rankings'],
            ],
            'urls' => [
                'index' => route('dosen.reports'),
                'excel' => route('dosen.reports.export.excel'),
                'word' => route('dosen.reports.export.word'),
                'print' => route('dosen.reports.print'),
            ],
        ]);
    }

    public function profile(Request $request)
    {
        // Profil dosen memakai halaman profil bersama (profiles.show) yang sudah
        // lengkap: identitas, status akun, proyek PBL, riwayat persetujuan.
        return redirect()->route('profiles.show', $request->user()->id);
    }

    private function dosenUser(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->isDosen(), 403);

        return $user;
    }


    /**
     * @return array{tim_keys: \Illuminate\Support\Collection<int, string>, has_pbl: bool}
     */
    private function pblMeta(User $dosen): array
    {
        $timKeys = DosenMahasiswaScope::timKeysFor($dosen);

        return [
            'tim_keys' => $timKeys,
            'has_pbl' => $timKeys->isNotEmpty(),
        ];
    }

    /**
     * @return Builder<User>
     */
    /**
     * @return Builder<User>
     */
    private function allMahasiswaQuery(bool $activeOnly = true): Builder
    {
        $q = User::query()->where('role', 'mahasiswa');
        if ($activeOnly) {
            $q->where('is_active', true);
        }

        return $q;
    }


    private function normalizeTimFilter(User $dosen, string $tim): string
    {
        return trim($tim);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeDashboardAnalytics(
        User $dosen,
        string $mode,
        string $tanggal,
        string $kelas,
        string $tim,
        ?int $weekday,
        string $aktivitas = 'semua'
    ): array {
        $anchor = Carbon::parse($tanggal)->startOfDay();
        $aktivitas = in_array($aktivitas, ['semua', 'absensi'], true) ? $aktivitas : 'semua';

        [$curStart, $curEnd, $prevStart, $prevEnd, $periodLabel] = $this->resolveDashboardPeriod($mode, $anchor);

        $studentQ = $this->mahasiswaAktifQuery($dosen, $kelas, $tim);
        $totalMahasiswa = (clone $studentQ)->count();

        $pctCurrent = $this->persentaseHadirRentang($dosen, $curStart, $curEnd, $kelas, $tim, $weekday);
        $pctPrev = $this->persentaseHadirRentang($dosen, $prevStart, $prevEnd, $kelas, $tim, $weekday);

        $tapsCurrent = $this->countAbsensiTaps($dosen, $curStart, $curEnd, $kelas, $tim, $weekday);
        $tapsPrev = $this->countAbsensiTaps($dosen, $prevStart, $prevEnd, $kelas, $tim, $weekday);

        $hadirUnik = $this->distinctMahasiswaHadir($dosen, $curStart, $curEnd, $kelas, $tim, $weekday);
        $tidakHadir = max(0, $totalMahasiswa - $hadirUnik);

        $line = $this->buildLineChartSeries($dosen, $mode, $anchor, $kelas, $tim, $weekday, $aktivitas);

        $payload = [
            'period_label' => $periodLabel,
            'mode' => $mode,
            'tanggal' => $anchor->toDateString(),
            'kelas' => $kelas,
            'tim' => $tim,
            'weekday' => $weekday,
            'aktivitas' => $aktivitas,
            'pbl_tim_keys' => DosenMahasiswaScope::timKeysFor($dosen)->values()->all(),
            'stats' => [
                'total_mahasiswa' => $totalMahasiswa,
                'persen_hadir' => $pctCurrent,
                'persen_delta' => round($pctCurrent - $pctPrev, 1),
                'persen_meta' => DashboardStatLabels::persenHadirMeta(round($pctCurrent - $pctPrev, 1)),
                'tap_absensi' => $tapsCurrent,
                'tap_prev' => $tapsPrev,
                'tap_delta' => $tapsCurrent - $tapsPrev,
                'tap_meta' => DashboardStatLabels::tapAbsensiMeta($tapsCurrent - $tapsPrev, $tapsPrev, $mode),
            ],
            'line' => $line,
            'donut' => [
                'labels' => ['Sudah hadir', 'Belum hadir'],
                'values' => [$hadirUnik, $tidakHadir],
                'persen' => $pctCurrent,
            ],
        ];

        return $payload;
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:Carbon,3:Carbon,4:string}
     */
    private function resolveDashboardPeriod(string $mode, Carbon $anchor): array
    {
        if ($mode === 'hari') {
            $s = $anchor->copy()->startOfDay();
            $e = $anchor->copy()->endOfDay();
            $ps = $s->copy()->subDay()->startOfDay();
            $pe = $s->copy()->subDay()->endOfDay();
            $label = 'Harian · ' . $s->translatedFormat('d M Y');

            return [$s, $e, $ps, $pe, $label];
        }

        if ($mode === 'minggu') {
            $s = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $e = $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $ps = $s->copy()->subWeek();
            $pe = $e->copy()->subWeek();
            $label = 'Mingguan · ' . $s->translatedFormat('d M') . ' – ' . $e->translatedFormat('d M Y');

            return [$s, $e, $ps, $pe, $label];
        }

        if ($mode === 'bulan') {
            $s = $anchor->copy()->startOfMonth()->startOfDay();
            $e = $anchor->copy()->endOfMonth()->endOfDay();
            $ps = $anchor->copy()->subMonth()->startOfMonth()->startOfDay();
            $pe = $anchor->copy()->subMonth()->endOfMonth()->endOfDay();
            $label = 'Bulanan · ' . $s->translatedFormat('F Y');

            return [$s, $e, $ps, $pe, $label];
        }

        $s = $anchor->copy()->startOfYear()->startOfDay();
        $e = $anchor->copy()->endOfYear()->endOfDay();
        $ps = $anchor->copy()->subYear()->startOfYear()->startOfDay();
        $pe = $anchor->copy()->subYear()->endOfYear()->endOfDay();
        $label = 'Tahunan · ' . $s->year;

        return [$s, $e, $ps, $pe, $label];
    }

    private function mahasiswaAktifQuery(User $dosen, string $kelas, string $tim): Builder
    {
        $q = DosenKelasScope::applySupervisedMahasiswaQuery($this->allMahasiswaQuery(true), $dosen);
        if ($kelas !== '') {
            $q->where('kelas', $kelas);
        }
        if ($tim !== '') {
            $q->where('tim', $tim);
        }

        return $q;
    }

    private function mysqlWeekdayFromIso(int $iso1to7): int
    {
        return $iso1to7 === 7 ? 6 : $iso1to7 - 1;
    }

    private function applyWeekdayToAbsensiQuery($query, ?int $weekdayIso): void
    {
        if ($weekdayIso === null || $weekdayIso < 1 || $weekdayIso > 7) {
            return;
        }
        $w = $this->mysqlWeekdayFromIso($weekdayIso);
        $query->whereRaw('WEEKDAY(waktu_tap) = ?', [$w]);
    }

    private function distinctMahasiswaHadir(User $dosen, Carbon $from, Carbon $to, string $kelas, string $tim, ?int $weekday): int
    {
        $q = LogAbsensi::query()
            ->whereBetween('waktu_tap', [$from, $to])
            ->whereHas('user', function ($qb) use ($kelas, $tim, $dosen) {
                DosenKelasScope::applySupervisedMahasiswaQuery($qb->where('is_active', true), $dosen);
                if ($kelas !== '') {
                    $qb->where('kelas', $kelas);
                }
                if ($tim !== '') {
                    $qb->where('tim', $tim);
                }
            });

        $this->applyWeekdayToAbsensiQuery($q, $weekday);

        return (int) $q->selectRaw('count(distinct user_id) as c')->value('c');
    }

    private function persentaseHadirRentang(User $dosen, Carbon $from, Carbon $to, string $kelas, string $tim, ?int $weekday): float
    {
        $total = (clone $this->mahasiswaAktifQuery($dosen, $kelas, $tim))->count();
        if ($total === 0) {
            return 0.0;
        }
        $hadir = $this->distinctMahasiswaHadir($dosen, $from, $to, $kelas, $tim, $weekday);

        return round(($hadir / $total) * 100, 1);
    }

    private function countAbsensiTaps(User $dosen, Carbon $from, Carbon $to, string $kelas, string $tim, ?int $weekday): int
    {
        $q = LogAbsensi::query()
            ->whereBetween('waktu_tap', [$from, $to])
            ->whereHas('user', function ($qb) use ($kelas, $tim, $dosen) {
                DosenKelasScope::applySupervisedMahasiswaQuery($qb, $dosen);
                if ($kelas !== '') {
                    $qb->where('kelas', $kelas);
                }
                if ($tim !== '') {
                    $qb->where('tim', $tim);
                }
            });

        $this->applyWeekdayToAbsensiQuery($q, $weekday);

        return (int) $q->count();
    }


    /**
     * @return array{labels: array<int, string>, values: array<int, float>, subtitle: string}
     */
    private function buildLineChartSeries(User $dosen, string $mode, Carbon $anchor, string $kelas, string $tim, ?int $weekday, string $aktivitas): array
    {
        $labels = [];
        $absensi = [];
        $subtitle = 'Persentase mahasiswa yang tap absensi (seluruh mahasiswa, filter kelas/tim)';

        if ($mode === 'hari') {
            $day = $anchor->copy()->startOfDay();
            for ($h = 6; $h <= 22; $h++) {
                $hs = $day->copy()->setTime($h, 0, 0);
                $he = $day->copy()->setTime($h, 59, 59);
                $labels[] = sprintf('%02d:00', $h);
                $absensi[] = $this->persentaseHadirRentang($dosen, $hs, $he, $kelas, $tim, null);
            }
            $subtitle .= ' · Per jam (06:00–22:00)';

            return ['labels' => $labels, 'values' => $absensi, 'absensi' => $absensi, 'subtitle' => $subtitle];
        }

        if ($mode === 'minggu') {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            for ($i = 0; $i < 7; $i++) {
                $d = $start->copy()->addDays($i);
                if ($weekday !== null && (int) $d->format('N') !== $weekday) {
                    continue;
                }
                $labels[] = $d->translatedFormat('D');
                $absensi[] = $this->persentaseHadirRentang(
                    $dosen,
                    $d->copy()->startOfDay(),
                    $d->copy()->endOfDay(),
                    $kelas,
                    $tim,
                    null
                );
            }

            return ['labels' => $labels, 'values' => $absensi, 'absensi' => $absensi, 'subtitle' => $subtitle];
        }

        if ($mode === 'bulan') {
            $start = $anchor->copy()->startOfMonth()->startOfDay();
            $endM = $anchor->copy()->endOfMonth()->endOfDay();
            for ($d = $start->copy(); $d->lte($endM); $d->addDay()) {
                if ($weekday !== null && (int) $d->format('N') !== $weekday) {
                    continue;
                }
                $labels[] = $d->format('j');
                $absensi[] = $this->persentaseHadirRentang(
                    $dosen,
                    $d->copy()->startOfDay(),
                    $d->copy()->endOfDay(),
                    $kelas,
                    $tim,
                    null
                );
            }
            $subtitle .= $weekday ? ' · Hanya hari tertentu dalam bulan' : ' · Per hari dalam bulan';

            return ['labels' => $labels, 'values' => $absensi, 'absensi' => $absensi, 'subtitle' => $subtitle];
        }

        for ($m = 1; $m <= 12; $m++) {
            $s = Carbon::create($anchor->year, $m, 1)->startOfDay();
            $e = $s->copy()->endOfMonth()->endOfDay();
            $labels[] = $s->translatedFormat('M');
            $hadir = $this->distinctMahasiswaHadir($dosen, $s, $e, $kelas, $tim, $weekday);
            $total = (clone $this->mahasiswaAktifQuery($dosen, $kelas, $tim))->count();
            $absensi[] = $total > 0 ? round(($hadir / $total) * 100, 1) : 0.0;
        }
        $subtitle .= $weekday ? ' · Hari terpilih per bulan' : ' · Per bulan';

        return ['labels' => $labels, 'values' => $absensi, 'absensi' => $absensi, 'subtitle' => $subtitle];
    }
}
