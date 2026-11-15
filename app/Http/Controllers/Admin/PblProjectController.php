<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\User;
use App\Services\PblAssignmentNotifier;
use App\Services\PblTeamAssignmentNotifier;
use App\Support\PblActivityLogger;
use App\Support\PblCsvImporter;
use App\Support\PblDataReadiness;
use App\Support\PblDosenLinker;
use App\Support\PblTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PblProjectController extends Controller
{
    use \App\Http\Controllers\Concerns\AdminShellProps;

    public function index(Request $request)
    {
        $user = $request->user();

        $projects = PblProject::query()
            ->with([
                'manproUser:id,nama_lengkap,nitk_nidn,profile_photo_path',
                'coManproUser:id,nama_lengkap,nitk_nidn,profile_photo_path',
                'teamMembers.user:id,nama_lengkap,nitk_nidn,kelas,tim,shift,semester,profile_photo_path',
            ])
            ->orderBy('kode')
            ->get();

        $rows = $projects->map(function (PblProject $p) {
            $meta = \App\Support\PblProjectMeta::card($p);
            $members = PblTeamService::projectMemberUsers($p);
            $roleOf = $p->teamMembers->keyBy('user_id');

            return [
                'id' => $p->id,
                'kode' => $p->kode,
                'nama' => $p->nama,
                'shift_sesi' => $p->shift_sesi,
                'aktif' => (bool) $p->aktif,
                'foto_url' => $meta['foto_url'],
                'deskripsi' => $meta['deskripsi'],
                'progress' => $meta['progress'],
                'status_label' => $meta['status_label'],
                'tahun_ajaran' => $meta['tahun_ajaran'],
                'kategori' => $meta['kategori'],
                'semester' => $meta['semester'],
                'periode_mulai' => $meta['periode_mulai'],
                'periode_selesai' => $meta['periode_selesai'],
                'manpro_id' => $p->manpro_user_id,
                'manpro' => $p->manproUser?->nama_lengkap ?? $p->manpro,
                'co_manpro_id' => $p->co_manpro_user_id,
                'co_manpro' => $p->coManproUser?->nama_lengkap ?? $p->co_manpro,
                'jumlah_anggota' => $members->count(),
                'members' => $members->map(fn ($m) => [
                    'id' => $m->id,
                    'nama' => $m->nama_lengkap,
                    'nim' => $m->nitk_nidn,
                    'kelas' => $m->kelas,
                    'foto' => $m->profile_photo_url ?? null,
                    'role' => $roleOf->get($m->id)?->role ?? 'anggota',
                ])->values(),
            ];
        })->values();

        $dosenList = User::query()
            ->where('role', 'dosen')
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'nama' => $d->nama_lengkap,
                'nip' => $d->nitk_nidn,
                'sebagai_manpro' => $rows->where('manpro_id', $d->id)->count(),
                'sebagai_co' => $rows->where('co_manpro_id', $d->id)->count(),
            ])->values();

        $unassigned = PblTeamService::unassignedMahasiswa()
            ->map(fn ($m) => [
                'id' => $m->id,
                'nama' => $m->nama_lengkap,
                'nim' => $m->nitk_nidn,
                'kelas' => $m->kelas,
            ])->values();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('AdminPbl', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'stats' => [
                'total' => $rows->count(),
                'aktif' => $rows->where('aktif', true)->count(),
                'selesai' => $rows->where('status_label', 'Selesai')->count(),
                'anggota' => $rows->sum('jumlah_anggota'),
            ],
            'projects' => $rows,
            'dosen_options' => $dosenList,
            'unassigned' => $unassigned,
            'tahun_ajaran_options' => $rows->pluck('tahun_ajaran')->filter()->unique()->sort()->values(),
            'routes' => [
                'store' => route('admin.pbl.store'),
                'update' => url('/admin/pbl/0'),
                'destroy' => url('/admin/pbl/0'),
                'staff' => url('/admin/pbl/0/staff'),
                'ketua' => url('/admin/pbl/0/ketua'),
                'wakil' => url('/admin/pbl/0/wakil'),
                'clear_role' => url('/admin/pbl/0/clear-role'),
                'assign' => route('admin.pbl.assign-mahasiswa'),
                'clear_tim' => url('/admin/pbl/mahasiswa/0/clear-tim'),
                'aktivitas' => url('/admin/pbl/0/aktivitas'),
                'media' => url('/admin/pbl/0/media'),
                'import' => route('admin.pbl.import-projects'),
                'csv_template' => route('admin.pbl.csv-template'),
            ],
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function mahasiswaPlacementQuery(Request $request)
    {
        $mhsQ = trim((string) $request->input('mhs_q', ''));
        $mhsFilter = $request->input('mhs_filter', 'semua');
        $mhsTim = trim((string) $request->input('mhs_tim', ''));

        $query = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true);

        if ($mhsQ !== '') {
            $query->where(function ($q) use ($mhsQ) {
                $q->where('nama_lengkap', 'like', "%{$mhsQ}%")
                    ->orWhere('nitk_nidn', 'like', "%{$mhsQ}%")
                    ->orWhere('kelas', 'like', "%{$mhsQ}%");

                if (ctype_digit($mhsQ)) {
                    $q->orWhere('semester', (int) $mhsQ);
                }
            });
        }

        if ($mhsFilter === 'belum') {
            $query->where(function ($q) {
                $q->whereNull('tim')->orWhere('tim', '');
            });
        } elseif ($mhsFilter === 'sudah') {
            $query->whereNotNull('tim')->where('tim', '!=', '');
        }

        if ($mhsTim !== '') {
            $query->where('tim', $mhsTim);
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, User>>  $mahasiswaByTim
     * @return array<int, array{project: PblProject, members: \Illuminate\Support\Collection, ketua: ?PblTeamMember, wakil: ?PblTeamMember, count: int}>
     */
    private function buildPlacementCards(Request $request, $mahasiswaByTim): array
    {
        $mhsFilter = $request->input('mhs_filter', 'semua');
        $mhsTim = trim((string) $request->input('mhs_tim', ''));

        if ($mhsFilter === 'belum') {
            return [];
        }

        $projectsQuery = PblProject::query()
            ->with([
                'manproUser:id,nama_lengkap',
                'coManproUser:id,nama_lengkap',
                'teamMembers.user:id,nama_lengkap,nitk_nidn,kelas,semester',
            ])
            ->orderBy('kode');

        if ($mhsTim !== '') {
            $projectsQuery->where('nama', $mhsTim);
        }

        $cards = [];

        foreach ($projectsQuery->get() as $project) {
            $members = $mahasiswaByTim->get($project->nama, collect())->values();

            if ($mhsFilter === 'sudah' && $members->isEmpty()) {
                continue;
            }

            $ketua = $project->teamMembers->firstWhere('role', PblTeamMember::ROLE_KETUA);
            $wakil = $project->teamMembers->firstWhere('role', PblTeamMember::ROLE_WAKIL);

            $cards[] = [
                'project' => $project,
                'members' => $members,
                'ketua' => $ketua,
                'wakil' => $wakil,
                'count' => $members->count(),
            ];
        }

        return $cards;
    }

    public function search(Request $request): JsonResponse
    {
        $q = $this->normalizeProjectSearchQuery($request->input('q'));
        $shift = $this->normalizeProjectShift($request->input('shift'));

        $query = PblProject::query()
            ->with(['manproUser:id,nama_lengkap', 'coManproUser:id,nama_lengkap'])
            ->withCount(['mahasiswa as jumlah_mhs' => function ($qb) {
                $qb->where('is_active', true);
            }])
            ->orderBy('kode');

        $this->applyProjectListFilters($query, $q, $shift);

        $total = (clone $query)->count();
        $projects = $query->limit(80)->get()->map(fn (PblProject $p) => $this->serializeProjectRow($p))->values();

        $statsBase = PblProject::query();
        $this->applyProjectListFilters($statsBase, $q, $shift);

        return response()->json([
            'ok' => true,
            'q' => $q,
            'shift' => $shift,
            'total' => $total,
            'stats' => [
                'total' => $total,
                'aktif' => (clone $statsBase)->where('aktif', true)->count(),
                'pagi' => (clone $statsBase)->where('shift_sesi', 'pagi')->count(),
                'malam' => (clone $statsBase)->where('shift_sesi', 'malam')->count(),
            ],
            'projects' => $projects,
            'suggestions' => $projects->take(8)->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProjectRow(PblProject $p): array
    {
        $manproName = $p->manproUser?->nama_lengkap ?? $p->manpro ?? '';
        $coManproName = $p->coManproUser?->nama_lengkap ?? $p->co_manpro ?? '';

        return [
            'id' => $p->id,
            'kode' => $p->kode,
            'nama' => $p->nama,
            'manpro' => $p->manpro ?? '',
            'co_manpro' => $p->co_manpro ?? '',
            'manpro_name' => $manproName !== '' ? $manproName : '—',
            'co_manpro_name' => $coManproName !== '' ? $coManproName : '—',
            'manpro_user_id' => $p->manpro_user_id,
            'co_manpro_user_id' => $p->co_manpro_user_id,
            'shift_sesi' => $p->shift_sesi,
            'aktif' => (bool) $p->aktif,
            'jumlah_mhs' => (int) ($p->jumlah_mhs ?? 0),
            'destroy_url' => route('admin.pbl.destroy', $p),
            'label' => $p->kode.' · '.$p->nama,
            'group' => trim(($manproName !== '' ? 'Manpro: '.$manproName : '').($coManproName !== '' ? ' · Co: '.$coManproName : '')),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PblProject>  $query
     */
    private function applyProjectListFilters($query, string $q, ?string $shift = null): void
    {
        $shift = $this->normalizeProjectShift($shift);

        if ($q !== '') {
            $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($tokens as $token) {
                $like = '%'.addcslashes($token, '%_\\').'%';
                $query->where(function ($qb) use ($like) {
                    $qb->where('kode', 'like', $like)
                        ->orWhere('nama', 'like', $like)
                        ->orWhere('manpro', 'like', $like)
                        ->orWhere('co_manpro', 'like', $like)
                        ->orWhereHas('manproUser', fn ($user) => $user->where('nama_lengkap', 'like', $like))
                        ->orWhereHas('coManproUser', fn ($user) => $user->where('nama_lengkap', 'like', $like));
                });
            }
        }

        if (in_array($shift, ['pagi', 'malam'], true)) {
            $query->where('shift_sesi', $shift);
        }
    }

    private function normalizeProjectSearchQuery(mixed $value): string
    {
        $q = trim((string) ($value ?? ''));

        return mb_substr($q, 0, 100);
    }

    private function normalizeProjectShift(mixed $value): string
    {
        $shift = trim((string) ($value ?? ''));

        return in_array($shift, ['pagi', 'malam'], true) ? $shift : '';
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProject($request);
        $meta = $this->validateProjectMeta($request);

        $project = PblProject::query()->create($validated);
        if ($meta !== []) {
            \App\Support\PblProjectMeta::put($project->id, $meta);
        }

        PblActivityLogger::record($project, $request->user(), 'buat_proyek', "Proyek {$project->kode} dibuat", null, [
            'kode' => $project->kode, 'nama' => $project->nama, 'shift' => $project->shift_sesi,
            'manpro' => $project->manpro, 'co_manpro' => $project->co_manpro, 'meta' => $meta,
        ]);

        app(PblAssignmentNotifier::class)->notifyAssignments(
            $project,
            $request->user(),
            [
                'manpro' => (int) $validated['manpro_user_id'],
                'co_manpro' => $validated['co_manpro_user_id'] ? (int) $validated['co_manpro_user_id'] : null,
            ],
        );

        return redirect()->route('admin.pbl.index')->with('success', 'Proyek PBL berhasil ditambahkan.');
    }

    public function update(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $this->validateProject($request, $pbl);

        $previous = [
            'manpro' => (int) $pbl->manpro_user_id,
            'co_manpro' => $pbl->co_manpro_user_id ? (int) $pbl->co_manpro_user_id : null,
        ];

        $oldNama = $pbl->nama;
        $sebelum = $pbl->only(['kode', 'nama', 'shift_sesi', 'manpro', 'co_manpro', 'aktif']);
        $pbl->update($validated);

        $meta = $this->validateProjectMeta($request);
        if ($meta !== []) {
            \App\Support\PblProjectMeta::put($pbl->id, $meta);
        }

        PblActivityLogger::record($pbl, $request->user(), 'ubah_proyek', "Proyek {$pbl->kode} diperbarui", $sebelum, array_merge(
            $pbl->only(['kode', 'nama', 'shift_sesi', 'manpro', 'co_manpro', 'aktif']),
            ['meta' => $meta],
        ));

        app(PblAssignmentNotifier::class)->notifyAssignments(
            $pbl->fresh(),
            $request->user(),
            [
                'manpro' => (int) $validated['manpro_user_id'],
                'co_manpro' => $validated['co_manpro_user_id'] ? (int) $validated['co_manpro_user_id'] : null,
            ],
            $previous,
        );

        if ($oldNama !== $pbl->nama) {
            $affected = User::query()
                ->where('role', 'mahasiswa')
                ->where('tim', $oldNama)
                ->get();

            User::query()
                ->where('role', 'mahasiswa')
                ->where('tim', $oldNama)
                ->update(['tim' => $pbl->nama, 'shift' => $pbl->shift_sesi]);

            $notifier = app(PblTeamAssignmentNotifier::class);
            foreach ($affected as $mhs) {
                $notifier->notifyChange(
                    $mhs,
                    $request->user(),
                    $oldNama,
                    $mhs->shift,
                    $pbl->nama,
                    $pbl->shift_sesi,
                );
            }
        }

        return redirect()->route('admin.pbl.index')->with('success', 'Proyek PBL diperbarui.');
    }

    public function destroy(Request $request, PblProject $pbl): RedirectResponse
    {
        $nama = $pbl->nama;
        $affected = User::query()
            ->where('role', 'mahasiswa')
            ->where('tim', $nama)
            ->get();

        $notifier = app(PblTeamAssignmentNotifier::class);
        $admin = $request->user();
        foreach ($affected as $mhs) {
            $notifier->notifyChange($mhs, $admin, $nama, $mhs->shift, null, $mhs->shift);
        }

        $snapshot = $pbl->kode.' · '.$nama;
        $pbl->delete();

        User::query()
            ->where('role', 'mahasiswa')
            ->where('tim', $nama)
            ->update(['tim' => null]);

        PblActivityLogger::record(null, $admin, 'hapus_proyek', "Proyek {$snapshot} dihapus ({$affected->count()} mahasiswa dilepas)", [
            'kode' => $pbl->kode, 'nama' => $nama, 'manpro' => $pbl->manpro, 'anggota_dilepas' => $affected->count(),
        ], null, $snapshot);

        $count = $affected->count();

        return redirect()->route('admin.pbl.index')->with(
            'success',
            'Proyek PBL dihapus.' . ($count > 0 ? " {$count} mahasiswa diberi notifikasi dan perlu dipetakan ulang." : '')
        );
    }

    public function assignMahasiswa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pbl_project_id' => ['required', 'integer', 'exists:pbl_projects,id'],
        ]);

        $user = User::query()->where('role', 'mahasiswa')->findOrFail($validated['user_id']);
        $project = PblProject::query()->findOrFail($validated['pbl_project_id']);

        $prevTim = $user->tim;
        $prevShift = $user->shift;

        try {
            PblTeamService::placeMahasiswa($project, $user, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        PblActivityLogger::record($project, $request->user(), 'tambah_anggota', "{$user->nama_lengkap} dimasukkan ke tim", [
            'tim' => $prevTim, 'shift' => $prevShift,
        ], [
            'user_id' => $user->id, 'nama' => $user->nama_lengkap, 'nim' => $user->nitk_nidn,
            'tim' => $project->nama, 'shift' => $project->shift_sesi,
        ]);

        app(\App\Services\PblTeamAssignmentNotifier::class)->notifyChange(
            $user->fresh(),
            $request->user(),
            $prevTim,
            $prevShift,
            $user->tim,
            $user->shift,
        );

        return back()->with('success', "{$user->nama_lengkap} ditempatkan ke {$project->nama}.");
    }

    public function clearMahasiswaTim(User $user): RedirectResponse
    {
        if ($user->role !== 'mahasiswa') {
            return back()->with('error', 'Hanya akun mahasiswa yang bisa dikosongkan timnya.');
        }

        $prevTim = $user->tim;
        $prevShift = $user->shift;
        $prevProject = $prevTim ? PblProject::query()->where('nama', $prevTim)->first() : null;

        PblTeamService::releaseFromPbl($user);

        PblActivityLogger::record($prevProject, request()->user(), 'keluarkan_anggota', "{$user->nama_lengkap} dikeluarkan dari tim {$prevTim}", [
            'user_id' => $user->id, 'nama' => $user->nama_lengkap, 'nim' => $user->nitk_nidn,
            'tim' => $prevTim, 'shift' => $prevShift,
        ]);

        app(\App\Services\PblTeamAssignmentNotifier::class)->notifyChange(
            $user->fresh(),
            request()->user(),
            $prevTim,
            $prevShift,
            null,
            $user->shift,
        );

        return back()->with('success', "Tim {$user->nama_lengkap} dikosongkan. Mahasiswa perlu penempatan ulang.");
    }

    public function updateStaff(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $request->validate([
            'manpro_user_id' => ['required', 'integer', 'exists:users,id'],
            'co_manpro_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:manpro_user_id'],
        ]);

        $manproUser = User::query()->where('role', 'dosen')->findOrFail($validated['manpro_user_id']);
        $coUser = !empty($validated['co_manpro_user_id'])
            ? User::query()->where('role', 'dosen')->findOrFail($validated['co_manpro_user_id'])
            : null;

        $previous = [
            'manpro' => (int) $pbl->manpro_user_id,
            'co_manpro' => $pbl->co_manpro_user_id ? (int) $pbl->co_manpro_user_id : null,
        ];

        $pbl->update([
            'manpro_user_id' => $manproUser->id,
            'co_manpro_user_id' => $coUser?->id,
            'manpro' => $manproUser->nama_lengkap,
            'co_manpro' => $coUser?->nama_lengkap,
        ]);

        PblActivityLogger::record($pbl, $request->user(), 'ubah_pengampu', "Pengampu {$pbl->kode} diubah", [
            'manpro_id' => $previous['manpro'], 'co_manpro_id' => $previous['co_manpro'],
        ], [
            'manpro' => $manproUser->nama_lengkap, 'co_manpro' => $coUser?->nama_lengkap,
        ]);

        app(PblAssignmentNotifier::class)->notifyAssignments(
            $pbl->fresh(),
            $request->user(),
            [
                'manpro' => (int) $manproUser->id,
                'co_manpro' => $coUser?->id,
            ],
            $previous,
        );

        return back()->with('success', 'Pengampu proyek diperbarui.')->withFragment('penempatan-mhs');
    }

    public function assignKetua(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $mahasiswa = User::query()->where('role', 'mahasiswa')->findOrFail($validated['user_id']);

        try {
            PblTeamService::assignKetua($pbl, $mahasiswa, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->withFragment('penempatan-mhs');
        }

        PblActivityLogger::record($pbl, $request->user(), 'set_ketua', "{$mahasiswa->nama_lengkap} ditetapkan sebagai ketua tim", null, [
            'user_id' => $mahasiswa->id, 'nama' => $mahasiswa->nama_lengkap, 'nim' => $mahasiswa->nitk_nidn,
        ]);

        return back()->with('success', "{$mahasiswa->nama_lengkap} ditetapkan sebagai ketua tim.")->withFragment('penempatan-mhs');
    }

    public function assignWakil(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $mahasiswa = User::query()->where('role', 'mahasiswa')->findOrFail($validated['user_id']);

        try {
            PblTeamService::assignWakil($pbl, $mahasiswa, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->withFragment('penempatan-mhs');
        }

        PblActivityLogger::record($pbl, $request->user(), 'set_wakil', "{$mahasiswa->nama_lengkap} ditetapkan sebagai wakil ketua", null, [
            'user_id' => $mahasiswa->id, 'nama' => $mahasiswa->nama_lengkap, 'nim' => $mahasiswa->nitk_nidn,
        ]);

        return back()->with('success', "{$mahasiswa->nama_lengkap} ditetapkan sebagai wakil ketua.")->withFragment('penempatan-mhs');
    }

    public function clearTeamRole(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:ketua,wakil'],
        ]);

        try {
            PblTeamService::clearTeamRole($pbl, $validated['role'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withFragment('penempatan-mhs');
        }

        PblActivityLogger::record($pbl, $request->user(), 'clear_role', "Peran {$validated['role']} dikosongkan", [
            'role' => $validated['role'],
        ]);

        $label = $validated['role'] === 'ketua' ? 'Ketua tim' : 'Wakil ketua';

        return back()->with('success', "{$label} dikosongkan.")->withFragment('penempatan-mhs');
    }

    /**
     * Admin/laboran memasang foto + metadata tampilan proyek — sama seperti
     * manpro di sisi dosen, tapi berlaku untuk semua proyek. Diaudit.
     */
    public function updateMedia(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $request->validate([
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:Perencanaan,Berjalan,Evaluasi,Selesai'],
            'hapus_foto' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('hapus_foto')) {
            \App\Support\PblProjectMeta::removeFoto((int) $pbl->id);
        }
        if ($request->hasFile('foto')) {
            \App\Support\PblProjectMeta::storeFoto((int) $pbl->id, $request->file('foto'));
        }
        \App\Support\PblProjectMeta::put((int) $pbl->id, [
            'deskripsi' => $validated['deskripsi'] ?? null,
            'progress' => isset($validated['progress']) ? (int) $validated['progress'] : null,
            'status' => $validated['status'] ?? null,
        ]);

        PblActivityLogger::record($pbl, $request->user(), 'ubah_media', "Tampilan proyek {$pbl->kode} diperbarui (foto/deskripsi/progress/status)", null, [
            'foto' => $request->hasFile('foto') ? 'diunggah' : ($request->boolean('hapus_foto') ? 'dihapus' : 'tidak berubah'),
            'deskripsi' => $validated['deskripsi'] ?? null,
            'progress' => $validated['progress'] ?? null,
            'status' => $validated['status'] ?? null,
        ]);

        return back()->with('success', "Tampilan proyek {$pbl->nama} diperbarui.");
    }

    /** Riwayat aktivitas proyek (JSON) — append-only, hanya baca. */
    public function aktivitas(PblProject $pbl): JsonResponse
    {
        $logs = \App\Models\PblActivityLog::query()
            ->where('pbl_project_id', $pbl->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (\App\Models\PblActivityLog $l) => [
                'id' => $l->id,
                'aksi' => $l->aksi,
                'ringkasan' => $l->ringkasan,
                'actor' => $l->actor_nama ?? 'Sistem',
                'actor_role' => $l->actor_role,
                'sebelum' => $l->sebelum,
                'sesudah' => $l->sesudah,
                'waktu' => $l->created_at?->format('d M Y, H:i'),
            ])->values();

        return response()->json(['ok' => true, 'logs' => $logs]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProject(Request $request, ?PblProject $pbl = null): array
    {
        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('pbl_projects', 'kode')->ignore($pbl?->id),
            ],
            'nama' => ['required', 'string', 'max:200'],
            'shift_sesi' => ['required', 'in:pagi,malam'],
            'manpro_user_id' => ['required', 'integer', 'exists:users,id'],
            'co_manpro_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:manpro_user_id'],
            'aktif' => ['nullable', 'boolean'],
        ], [
            'manpro_user_id.required' => 'Pilih akun dosen manpro dari daftar.',
            'co_manpro_user_id.different' => 'Co-manpro tidak boleh sama dengan manpro.',
        ]);

        $validated['aktif'] = $request->boolean('aktif');
        $validated['manpro_user_id'] = (int) $validated['manpro_user_id'];
        $validated['co_manpro_user_id'] = $validated['co_manpro_user_id'] ?? null;
        $validated['co_manpro_user_id'] = $validated['co_manpro_user_id'] ? (int) $validated['co_manpro_user_id'] : null;

        $manproUser = User::query()->where('role', 'dosen')->find($validated['manpro_user_id']);
        $validated['manpro'] = $manproUser?->nama_lengkap;

        if ($validated['co_manpro_user_id']) {
            $coUser = User::query()->where('role', 'dosen')->find($validated['co_manpro_user_id']);
            $validated['co_manpro'] = $coUser?->nama_lengkap;
        } else {
            $validated['co_manpro'] = null;
        }

        return $validated;
    }

    /** Field meta opsional (disimpan di PblProjectMeta JSON — bukan kolom tabel). */
    private function validateProjectMeta(Request $request): array
    {
        return $request->validate([
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'kategori' => ['nullable', 'string', 'max:80'],
            'semester' => ['nullable', 'string', 'max:20'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);
    }

    public function importProjectsCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_projects' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = app(PblCsvImporter::class)->importProjects($request->file('csv_projects'));

        $msg = "Import proyek: {$result['imported']} baru, {$result['updated']} diperbarui.";
        if (! empty($result['errors'])) {
            return back()
                ->with('success', $msg)
                ->with('csv_errors', $result['errors']);
        }

        return back()->with('success', $msg);
    }

    public function syncMahasiswaTimCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_mahasiswa_tim' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = app(PblCsvImporter::class)->syncMahasiswaTim($request->file('csv_mahasiswa_tim'));

        if (! empty($result['changes'])) {
            app(PblTeamAssignmentNotifier::class)->notifyBatch($result['changes'], $request->user());
        }

        $msg = "Sinkron tim: {$result['matched']} baris diproses.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} dilewati.";
        }
        if (! empty($result['changes'])) {
            $msg .= ' ' . count($result['changes']) . ' mahasiswa mendapat notifikasi perubahan tim.';
        }

        if (! empty($result['errors'])) {
            return back()
                ->with('success', $msg)
                ->with('csv_errors', $result['errors']);
        }

        return back()->with('success', $msg);
    }

    public function downloadCsvTemplate(Request $request): Response
    {
        $type = $request->query('type', 'projects');

        if ($type === 'mahasiswa_tim') {
            $content = "nim,tim,shift\n12345678,Nama Proyek PBL,pagi\n";

            return response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="template_mahasiswa_tim.csv"',
            ]);
        }

        $content = "kode,nama,shift_sesi,manpro,co_manpro\nPBL-01,Contoh Proyek,pagi,Nama Dosen Manpro,Nama Dosen Co\n";

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_proyek_pbl.csv"',
        ]);
    }
}
