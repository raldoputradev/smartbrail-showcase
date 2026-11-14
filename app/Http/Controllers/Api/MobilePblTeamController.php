<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\User;
use App\Support\PblTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobilePblTeamController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user') ?? $request->user();

        if (!$user->isMahasiswa()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur tim PBL hanya untuk mahasiswa.',
            ], 403);
        }

        $current = PblTeamService::projectForMahasiswa($user);
        $membership = PblTeamService::membershipFor($user);

        $projects = PblProject::query()
            ->where('aktif', true)
            ->with(['manproUser:id,nama_lengkap,profile_photo_path'])
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'shift_sesi', 'manpro_user_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'has_pbl_team' => $current !== null,
                'current_project_id' => $current?->id,
                'current_role' => $membership?->role,
                'projects' => $projects->map(fn (PblProject $p) => [
                    'id' => $p->id,
                    'kode' => $p->kode,
                    'nama' => $p->nama,
                    'shift_sesi' => $p->shift_sesi,
                    'manpro' => $p->manproUser?->nama_lengkap,
                    'manpro_user' => $this->personPayload($p->manproUser),
                    'is_current' => $current && (int) $current->id === (int) $p->id,
                ])->values(),
            ],
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user') ?? $request->user();

        if (!$user->isMahasiswa()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur tim PBL hanya untuk mahasiswa.',
            ], 403);
        }

        $validated = $request->validate([
            'pbl_project_id' => ['required', 'integer', 'exists:pbl_projects,id'],
        ]);

        $project = PblProject::query()->where('aktif', true)->findOrFail($validated['pbl_project_id']);

        try {
            PblTeamService::joinProject($project, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Anda bergabung ke tim PBL {$project->nama}.",
            'data' => [
                'project_id' => $project->id,
                'project_nama' => $project->nama,
                'shift_sesi' => $project->shift_sesi,
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user') ?? $request->user();

        if (!$user->isMahasiswa()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur tim PBL hanya untuk mahasiswa.',
            ], 403);
        }

        $project = PblTeamService::projectForMahasiswa($user);
        $membership = PblTeamService::membershipFor($user);

        if (!$project) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pbl_team' => false,
                    'is_ketua' => false,
                    'role' => null,
                    'project' => null,
                    'manpro_user' => null,
                    'co_manpro_user' => null,
                    'ketua' => null,
                    'wakil' => null,
                    'anggota' => [],
                    'members' => [],
                ],
            ]);
        }

        $project->load([
            'teamMembers.user:id,nama_lengkap,nitk_nidn,kelas,tim,shift,profile_photo_path',
            'manproUser:id,nama_lengkap,nitk_nidn,profile_photo_path',
            'coManproUser:id,nama_lengkap,nitk_nidn,profile_photo_path',
        ]);

        $ketua = $project->teamMembers->firstWhere('role', PblTeamMember::ROLE_KETUA);
        $wakil = $project->teamMembers->firstWhere('role', PblTeamMember::ROLE_WAKIL);
        $anggotaStructured = $project->teamMembers
            ->where('role', PblTeamMember::ROLE_ANGGOTA)
            ->values();

        $listedUserIds = $project->teamMembers->pluck('user_id')->filter()->all();

        // Legacy: mahasiswa dengan field tim = nama proyek, belum ada di pbl_team_members.
        $legacyUsers = User::query()
            ->where('role', 'mahasiswa')
            ->where('tim', $project->nama)
            ->when(!empty($listedUserIds), fn ($q) => $q->whereNotIn('id', $listedUserIds))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'kelas', 'tim', 'shift', 'profile_photo_path']);

        $anggota = $anggotaStructured
            ->map(fn (PblTeamMember $m) => $this->memberPayload($m))
            ->concat($legacyUsers->map(fn (User $u) => $this->userAsMemberPayload($u, PblTeamMember::ROLE_ANGGOTA)))
            ->values();

        // Daftar lengkap untuk UI (ketua → wakil → anggota), termasuk legacy.
        $members = collect()
            ->when($ketua, fn ($c) => $c->push($this->memberPayload($ketua)))
            ->when($wakil, fn ($c) => $c->push($this->memberPayload($wakil)))
            ->concat($anggota)
            ->values();

        $isKetua = $membership?->isKetua() ?? false;
        $manproUser = $this->personPayload($project->manproUser);
        $coManproUser = $this->personPayload($project->coManproUser);

        return response()->json([
            'success' => true,
            'data' => [
                'has_pbl_team' => true,
                'is_ketua' => $isKetua,
                'role' => $membership?->role,
                'project' => [
                    'id' => $project->id,
                    'kode' => $project->kode,
                    'nama' => $project->nama,
                    'shift_sesi' => $project->shift_sesi,
                    'manpro' => $project->manproUser?->nama_lengkap ?? $project->manpro,
                    'co_manpro' => $project->coManproUser?->nama_lengkap ?? $project->co_manpro,
                ],
                'manpro_user' => $manproUser,
                'co_manpro_user' => $coManproUser,
                'ketua' => $ketua ? $this->memberPayload($ketua) : null,
                'wakil' => $wakil ? $this->memberPayload($wakil) : null,
                'anggota' => $anggota,
                'members' => $members,
            ],
        ]);
    }

    public function storeAnggota(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user') ?? $request->user();
        $project = PblTeamService::ketuaProjectFor($user);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Anda bukan ketua tim PBL.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $mahasiswa = User::query()->findOrFail($validated['user_id']);

        try {
            $member = PblTeamService::assignAnggota($project, $mahasiswa, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$mahasiswa->nama_lengkap} ditambahkan ke tim.",
            'data' => $this->memberPayload($member),
        ]);
    }

    public function destroyAnggota(Request $request, User $member): JsonResponse
    {
        $user = $request->attributes->get('mobile_user') ?? $request->user();
        $project = PblTeamService::ketuaProjectFor($user);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Anda bukan ketua tim PBL.',
            ], 403);
        }

        try {
            PblTeamService::removeMember($project, $member, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$member->nama_lengkap} dihapus dari tim.",
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personPayload(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'user_id' => $user->id,
            'nama_lengkap' => $user->nama_lengkap,
            'nitk_nidn' => $user->nitk_nidn,
            'profile_photo_url' => $user->profile_photo_path
                ? url($user->profile_photo_path)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(PblTeamMember $member): array
    {
        $u = $member->user;

        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'role' => $member->role,
            'nama_lengkap' => $u?->nama_lengkap,
            'nitk_nidn' => $u?->nitk_nidn,
            'kelas' => $u?->kelas,
            'tim' => $u?->tim,
            'shift' => $u?->shift,
            'profile_photo_url' => $u?->profile_photo_path
                ? url($u->profile_photo_path)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userAsMemberPayload(User $user, string $role): array
    {
        return [
            'id' => null,
            'user_id' => $user->id,
            'role' => $role,
            'nama_lengkap' => $user->nama_lengkap,
            'nitk_nidn' => $user->nitk_nidn,
            'kelas' => $user->kelas,
            'tim' => $user->tim,
            'shift' => $user->shift,
            'profile_photo_url' => $user->profile_photo_path
                ? url($user->profile_photo_path)
                : null,
        ];
    }
}
