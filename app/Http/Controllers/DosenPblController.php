<?php



namespace App\Http\Controllers;



use App\Models\PblProject;

use App\Models\PblTeamMember;

use App\Models\User;

use App\Support\PblProjectMeta;

use App\Support\PblActivityLogger;
use App\Support\PblTeamService;

use App\Http\Controllers\Concerns\AdminShellProps;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;

class DosenPblController extends Controller

{
    use AdminShellProps;

    public function index(Request $request)

    {
        $dosen = $request->user();

        $projects = PblProject::query()
            ->with([
                'manproUser:id,nama_lengkap',
                'coManproUser:id,nama_lengkap',
                'teamMembers.user:id,nama_lengkap,nitk_nidn,kelas,tim,shift,profile_photo_path',
            ])
            ->orderBy('nama')
            ->get();

        $card = function (PblProject $p) use ($dosen) {
            $meta = PblProjectMeta::card($p);
            $members = PblTeamService::projectMemberUsers($p);
            $ketua = $p->teamMembers->firstWhere('role', PblTeamMember::ROLE_KETUA);
            $wakil = $p->teamMembers->firstWhere('role', PblTeamMember::ROLE_WAKIL);
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
                'is_mine' => $p->isManagedBy($dosen),
                'my_role' => match (true) {
                    (int) $p->manpro_user_id === (int) $dosen->id => 'manpro',
                    (int) $p->co_manpro_user_id === (int) $dosen->id => 'co_manpro',
                    default => null,
                },
                'manpro' => $p->manproUser?->nama_lengkap ?? $p->manpro,
                'manpro_id' => $p->manpro_user_id,
                'co_manpro_id' => $p->co_manpro_user_id,
                'co_manpro' => $p->coManproUser?->nama_lengkap ?? $p->co_manpro,
                'jumlah_anggota' => $members->count(),
                'ketua' => $ketua?->user?->nama_lengkap,
                'wakil' => $wakil?->user?->nama_lengkap,
                'members' => $members->map(fn ($m) => [
                    'id' => $m->id,
                    'nama' => $m->nama_lengkap ?? null,
                    'nim' => $m->nitk_nidn ?? null,
                    'kelas' => $m->kelas ?? null,
                    'foto' => $m->profile_photo_url ?? null,
                    'role' => $roleOf->get($m->id)?->role ?? 'anggota',
                ])->values()->all(),
            ];
        };

        $mine = $projects->filter(fn (PblProject $p) => $p->isManagedBy($dosen))->map($card)->values();
        $all = $projects->map($card)->values();

        $mahasiswaTotal = $projects->flatMap(fn (PblProject $p) => PblTeamService::projectMemberUsers($p)->pluck('id'))->unique()->count();

        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('DosenPbl', [
            'menu' => $this->dosenSidebarMenu($dosen),
            'notif' => $this->dosenNotifications($dosen),
            'stats' => [
                'total' => $projects->where('aktif', true)->count(),
                'mine' => $mine->count(),
                'tim' => $projects->count(),
                'mahasiswa' => $mahasiswaTotal,
            ],
            'mine' => $mine,
            'projects' => $all,
            'media_url' => route('dosen.pbl.media', ['pbl' => 0]),
            'create_url' => route('dosen.pbl.store'),
            'member_routes' => [
                'anggota' => route('dosen.pbl.anggota.store', ['pbl' => 0]),
                'ketua' => route('dosen.pbl.ketua.store', ['pbl' => 0]),
                'wakil' => route('dosen.pbl.wakil.store', ['pbl' => 0]),
                'hapus' => route('dosen.pbl.members.destroy', ['pbl' => 0, 'user' => 0]),
                'aktivitas' => route('dosen.pbl.aktivitas', ['pbl' => 0]),
            ],
            'unassigned_mahasiswa' => PblTeamService::unassignedMahasiswa()
                ->map(fn (User $m) => ['id' => $m->id, 'nama' => $m->nama_lengkap, 'nim' => $m->nitk_nidn, 'kelas' => $m->kelas])
                ->values()->all(),
        ]);

    }

    /**
     * Manpro membuat tim/proyek PBL baru — otomatis menjadi manpro.
     */
    public function store(Request $request): RedirectResponse
    {
        $dosen = $request->user();
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:40', \Illuminate\Validation\Rule::unique('pbl_projects', 'kode')],
            'nama' => ['required', 'string', 'max:200'],
            'shift_sesi' => ['required', 'in:pagi,malam'],
        ], [
            'kode.unique' => 'Kode proyek sudah dipakai — pilih kode lain.',
        ]);

        $project = PblProject::query()->create([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama' => trim($validated['nama']),
            'shift_sesi' => $validated['shift_sesi'],
            'manpro_user_id' => $dosen->id,
            'manpro' => $dosen->nama_lengkap,
            'co_manpro_user_id' => null,
            'co_manpro' => null,
            'aktif' => true,
        ]);

        PblActivityLogger::record($project, $dosen, 'buat_proyek', "Tim {$project->kode} dibuat oleh dosen", null, [
            'kode' => $project->kode, 'nama' => $project->nama, 'shift' => $project->shift_sesi,
        ]);

        app(\App\Services\PblAssignmentNotifier::class)->notifyAssignments(
            $project,
            $dosen,
            ['manpro' => (int) $dosen->id, 'co_manpro' => null],
        );

        return redirect()->route('dosen.pbl.index')->with('success', "Tim {$project->nama} dibuat — Anda otomatis menjadi manpro.");
    }

    /**
     * Upload foto + metadata proyek oleh manpro/co-manpro pembimbing.
     * Foto tampil juga di sisi mahasiswa (kartu tim PBL mobile/web).
     */
    public function updateMedia(Request $request, PblProject $pbl): RedirectResponse
    {
        $dosen = $request->user();
        if (! $pbl->isManagedBy($dosen)) {
            abort(403, 'Hanya manpro/co-manpro proyek ini yang bisa mengubah tampilannya.');
        }

        $validated = $request->validate([
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:Perencanaan,Berjalan,Evaluasi,Selesai'],
            'hapus_foto' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('hapus_foto')) {
            PblProjectMeta::removeFoto((int) $pbl->id);
        }
        if ($request->hasFile('foto')) {
            PblProjectMeta::storeFoto((int) $pbl->id, $request->file('foto'));
        }
        PblProjectMeta::put((int) $pbl->id, [
            'deskripsi' => $validated['deskripsi'] ?? null,
            'progress' => isset($validated['progress']) ? (int) $validated['progress'] : null,
            'status' => $validated['status'] ?? null,
        ]);

        PblActivityLogger::record($pbl, $dosen, 'ubah_media', "Tampilan proyek {$pbl->kode} diperbarui (foto/deskripsi/progress/status)", null, [
            'foto' => $request->hasFile('foto') ? 'diunggah' : ($request->boolean('hapus_foto') ? 'dihapus' : 'tidak berubah'),
            'deskripsi' => $validated['deskripsi'] ?? null,
            'progress' => $validated['progress'] ?? null,
            'status' => $validated['status'] ?? null,
        ]);

        return back()->with('success', "Tampilan proyek {$pbl->nama} diperbarui.");
    }



    public function storeKetua(Request $request, PblProject $pbl): RedirectResponse

    {

        $validated = $request->validate([

            'user_id' => ['required', 'integer', 'exists:users,id'],

        ]);



        $mahasiswa = User::query()->findOrFail($validated['user_id']);



        try {

            PblTeamService::assignKetua($pbl, $mahasiswa, $request->user());

        } catch (ValidationException $e) {

            return back()->withErrors($e->errors())->withInput();

        }



        PblActivityLogger::record($pbl, $request->user(), 'set_ketua', "{$mahasiswa->nama_lengkap} ditetapkan sebagai ketua tim", null, [
            'user_id' => $mahasiswa->id, 'nama' => $mahasiswa->nama_lengkap, 'nim' => $mahasiswa->nitk_nidn,
        ]);

        return back()->with('success', "{$mahasiswa->nama_lengkap} ditetapkan sebagai ketua tim {$pbl->nama}.");

    }



    public function storeWakil(Request $request, PblProject $pbl): RedirectResponse

    {

        $validated = $request->validate([

            'user_id' => ['required', 'integer', 'exists:users,id'],

        ]);



        $mahasiswa = User::query()->findOrFail($validated['user_id']);



        try {

            PblTeamService::assignWakil($pbl, $mahasiswa, $request->user());

        } catch (ValidationException $e) {

            return back()->withErrors($e->errors())->withInput();

        }



        PblActivityLogger::record($pbl, $request->user(), 'set_wakil', "{$mahasiswa->nama_lengkap} ditetapkan sebagai wakil ketua", null, [
            'user_id' => $mahasiswa->id, 'nama' => $mahasiswa->nama_lengkap, 'nim' => $mahasiswa->nitk_nidn,
        ]);

        return back()->with('success', "{$mahasiswa->nama_lengkap} ditetapkan sebagai wakil ketua tim {$pbl->nama}.");

    }



    public function clearTeamRole(Request $request, PblProject $pbl): RedirectResponse

    {

        $validated = $request->validate([

            'role' => ['required', 'in:ketua,wakil'],

        ]);



        try {

            PblTeamService::clearTeamRole($pbl, $validated['role'], $request->user());

        } catch (ValidationException $e) {

            return back()->withErrors($e->errors());

        }



        PblActivityLogger::record($pbl, $request->user(), 'clear_role', "Peran {$validated['role']} dikosongkan", [
            'role' => $validated['role'],
        ]);

        $label = $validated['role'] === 'ketua' ? 'Ketua tim' : 'Wakil ketua';



        return back()->with('success', "{$label} {$pbl->nama} dikosongkan.");
    }

    public function storeAnggota(Request $request, PblProject $pbl): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $mahasiswa = User::query()->where('role', 'mahasiswa')->findOrFail($validated['user_id']);

        $prevTim = $mahasiswa->tim;
        $prevShift = $mahasiswa->shift;

        try {
            PblTeamService::placeMahasiswa($pbl, $mahasiswa, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        PblActivityLogger::record($pbl, $request->user(), 'tambah_anggota', "{$mahasiswa->nama_lengkap} dimasukkan ke tim", [
            'tim' => $prevTim, 'shift' => $prevShift,
        ], [
            'user_id' => $mahasiswa->id, 'nama' => $mahasiswa->nama_lengkap, 'nim' => $mahasiswa->nitk_nidn,
        ]);

        app(\App\Services\PblTeamAssignmentNotifier::class)->notifyChange(
            $mahasiswa->fresh(),
            $request->user(),
            $prevTim,
            $prevShift,
            $pbl->nama,
            $pbl->shift_sesi,
        );

        return back()->with('success', "{$mahasiswa->nama_lengkap} ditambahkan ke tim {$pbl->nama}.");
    }

    public function destroyMember(Request $request, PblProject $pbl, User $user): RedirectResponse

    {

        try {

            PblTeamService::removeMember($pbl, $user, $request->user());

        } catch (ValidationException $e) {

            return back()->withErrors($e->errors());

        }



        PblActivityLogger::record($pbl, $request->user(), 'keluarkan_anggota', "{$user->nama_lengkap} dikeluarkan dari tim", [
            'user_id' => $user->id, 'nama' => $user->nama_lengkap, 'nim' => $user->nitk_nidn,
        ]);

        return back()->with('success', "{$user->nama_lengkap} dihapus dari tim {$pbl->nama}.");

    }

    /** Riwayat aktivitas proyek (JSON) — append-only, hanya proyek yang dikelola. */
    public function aktivitas(Request $request, PblProject $pbl): \Illuminate\Http\JsonResponse
    {
        $dosen = $request->user();
        if (! $pbl->isManagedBy($dosen)) {
            abort(403, 'Riwayat hanya bisa dilihat untuk proyek yang Anda kelola.');
        }

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

}


