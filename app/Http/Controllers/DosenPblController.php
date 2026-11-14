<?php



namespace App\Http\Controllers;



use App\Models\PblProject;

use App\Models\PblTeamMember;

use App\Models\User;

use App\Support\PblTeamService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;

use Illuminate\View\View;



class DosenPblController extends Controller

{

    public function index(Request $request): View

    {

        $dosen = $request->user();

        $scope = $request->input('scope', 'all');



        $query = PblProject::query()

            ->with([

                'manproUser:id,nama_lengkap',

                'coManproUser:id,nama_lengkap',

                'teamMembers.user:id,nama_lengkap,nitk_nidn,kelas,tim,shift',

            ])

            ->orderBy('nama');



        if ($scope === 'mine') {

            $query->where(function ($q) use ($dosen) {

                $q->where('manpro_user_id', $dosen->id)

                    ->orWhere('co_manpro_user_id', $dosen->id);

            });

        }



        $projects = $query->get()->map(function (PblProject $project) use ($dosen) {

            $ketua = $project->teamMembers->firstWhere('role', PblTeamMember::ROLE_KETUA);

            $wakil = $project->teamMembers->firstWhere('role', PblTeamMember::ROLE_WAKIL);

            $anggota = $project->teamMembers

                ->where('role', PblTeamMember::ROLE_ANGGOTA)

                ->values();



            $members = PblTeamService::projectMemberUsers($project);
            $memberRoles = $project->teamMembers->keyBy('user_id');

            return [
                'model' => $project,
                'is_mine' => $project->isManagedBy($dosen),
                'my_role' => match (true) {
                    (int) $project->manpro_user_id === (int) $dosen->id => 'manpro',
                    (int) $project->co_manpro_user_id === (int) $dosen->id => 'co_manpro',
                    default => null,
                },
                'ketua' => $ketua,
                'wakil' => $wakil,
                'anggota' => $anggota,
                'members' => $members,
                'member_roles' => $memberRoles,
                'can_manage' => $project->isManagedBy($dosen),
            ];
        })->sortByDesc('is_mine')->values();

        $unassignedMahasiswa = PblTeamService::unassignedMahasiswa();

        $myCount = PblProject::query()
            ->where(function ($q) use ($dosen) {
                $q->where('manpro_user_id', $dosen->id)
                    ->orWhere('co_manpro_user_id', $dosen->id);
            })
            ->count();

        return view('dosen.pbl', [
            'projects' => $projects,
            'scope' => $scope,
            'unassignedMahasiswa' => $unassignedMahasiswa,

            'stats' => [

                'total' => PblProject::query()->where('aktif', true)->count(),

                'mine' => $myCount,

            ],

        ]);

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



        return back()->with('success', "{$user->nama_lengkap} dihapus dari tim {$pbl->nama}.");

    }

}


