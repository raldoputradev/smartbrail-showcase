<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PblTeamService
{
    public static function managesProject(User $user, PblProject $project): bool
    {
        if (!$user->isDosen()) {
            return false;
        }

        return (int) $project->manpro_user_id === (int) $user->id
            || (int) $project->co_manpro_user_id === (int) $user->id;
    }

    public static function isKetuaOf(User $mahasiswa, PblProject $project): bool
    {
        if (!$mahasiswa->isMahasiswa()) {
            return false;
        }

        return PblTeamMember::query()
            ->where('pbl_project_id', $project->id)
            ->where('user_id', $mahasiswa->id)
            ->where('role', PblTeamMember::ROLE_KETUA)
            ->exists();
    }

    public static function ketuaProjectFor(User $mahasiswa): ?PblProject
    {
        if (!$mahasiswa->isMahasiswa()) {
            return null;
        }

        $member = PblTeamMember::query()
            ->where('user_id', $mahasiswa->id)
            ->where('role', PblTeamMember::ROLE_KETUA)
            ->with('project')
            ->first();

        return $member?->project;
    }

    public static function canAssignAnggota(User $actor, PblProject $project): bool
    {
        return self::isKetuaOf($actor, $project)
            || self::managesProject($actor, $project)
            || $actor->isLaboran();
    }

    public static function canManageTeamStructure(User $actor, PblProject $project): bool
    {
        return self::managesProject($actor, $project) || $actor->isLaboran();
    }

    public static function projectForMahasiswa(User $mahasiswa): ?PblProject
    {
        if (!$mahasiswa->isMahasiswa()) {
            return null;
        }

        $member = PblTeamMember::query()
            ->where('user_id', $mahasiswa->id)
            ->with('project')
            ->first();

        if ($member?->project) {
            return $member->project;
        }

        $tim = trim((string) $mahasiswa->tim);
        if ($tim === '') {
            return null;
        }

        return PblProject::query()
            ->where('nama', $tim)
            ->where('aktif', true)
            ->first();
    }

    public static function membershipFor(User $mahasiswa): ?PblTeamMember
    {
        if (!$mahasiswa->isMahasiswa()) {
            return null;
        }

        return PblTeamMember::query()
            ->where('user_id', $mahasiswa->id)
            ->with('project')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function projectMemberUsers(PblProject $project): \Illuminate\Support\Collection
    {
        $fromMembers = PblTeamMember::query()
            ->where('pbl_project_id', $project->id)
            ->with('user:id,nama_lengkap,nitk_nidn,kelas,tim,shift,semester,profile_photo_path')
            ->get()
            ->pluck('user')
            ->filter();

        $legacy = User::query()
            ->where('role', 'mahasiswa')
            ->where('tim', $project->nama)
            ->whereNotIn('id', $fromMembers->pluck('id'))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'kelas', 'tim', 'shift', 'semester', 'profile_photo_path']);

        return $fromMembers->merge($legacy)->unique('id')->sortBy('nama_lengkap')->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function unassignedMahasiswa(): \Illuminate\Support\Collection
    {
        $memberIds = PblTeamMember::query()->pluck('user_id');

        return User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('tim')->orWhere('tim', '');
            })
            ->whereNotIn('id', $memberIds)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'kelas', 'semester', 'shift']);
    }

    public static function hasPblTeam(User $mahasiswa): bool
    {
        return self::projectForMahasiswa($mahasiswa) !== null;
    }

    /**
     * @throws ValidationException
     */
    public static function placeMahasiswa(PblProject $project, User $mahasiswa, User $actor): PblTeamMember
    {
        if ($mahasiswa->isMahasiswa() && (int) $actor->id === (int) $mahasiswa->id) {
            return self::joinProject($project, $mahasiswa);
        }

        if (!self::canAssignAnggota($actor, $project)) {
            throw ValidationException::withMessages([
                'user_id' => 'Anda tidak berwenang menambah anggota tim ini.',
            ]);
        }

        return self::assignAnggota($project, $mahasiswa, $actor);
    }

    /**
     * @throws ValidationException
     */
    public static function joinProject(PblProject $project, User $mahasiswa): PblTeamMember
    {
        self::assertEligibleMahasiswa($mahasiswa, $project);

        $existing = self::membershipFor($mahasiswa);
        if ($existing && (int) $existing->pbl_project_id === (int) $project->id) {
            self::syncUserTim($mahasiswa, $project);

            return $existing->fresh(['user']);
        }

        if (self::hasPblTeam($mahasiswa)) {
            throw ValidationException::withMessages([
                'pbl_project_id' => 'Anda sudah terdaftar di tim PBL lain. Hubungi dosen pengampu jika perlu pindah tim.',
            ]);
        }

        return DB::transaction(function () use ($project, $mahasiswa) {
            self::detachFromOtherProjects($mahasiswa, $project);

            $member = PblTeamMember::query()->updateOrCreate(
                [
                    'pbl_project_id' => $project->id,
                    'user_id' => $mahasiswa->id,
                ],
                [
                    'role' => PblTeamMember::ROLE_ANGGOTA,
                    'assigned_by' => $mahasiswa->id,
                ]
            );

            self::syncUserTim($mahasiswa, $project);

            return $member->fresh(['user']);
        });
    }

    public static function releaseFromPbl(User $mahasiswa): void
    {
        DB::transaction(function () use ($mahasiswa) {
            PblTeamMember::query()
                ->where('user_id', $mahasiswa->id)
                ->delete();

            $mahasiswa->tim = null;
            $mahasiswa->save();
        });
    }

    /**
     * @throws ValidationException
     */
    public static function assignKetua(PblProject $project, User $mahasiswa, User $actor): PblTeamMember
    {
        if (!self::canManageTeamStructure($actor, $project)) {
            throw ValidationException::withMessages([
                'user_id' => 'Anda tidak berwenang mengatur ketua tim proyek ini.',
            ]);
        }

        self::assertEligibleMahasiswa($mahasiswa, $project);

        return DB::transaction(function () use ($project, $mahasiswa, $actor) {
            $existingKetua = PblTeamMember::query()
                ->where('pbl_project_id', $project->id)
                ->where('role', PblTeamMember::ROLE_KETUA)
                ->where('user_id', '!=', $mahasiswa->id)
                ->first();

            if ($existingKetua) {
                $existingKetua->update(['role' => PblTeamMember::ROLE_ANGGOTA]);
            }

            $existingWakil = PblTeamMember::query()
                ->where('pbl_project_id', $project->id)
                ->where('user_id', $mahasiswa->id)
                ->where('role', PblTeamMember::ROLE_WAKIL)
                ->first();

            if ($existingWakil) {
                $existingWakil->update(['role' => PblTeamMember::ROLE_ANGGOTA]);
            }

            self::detachFromOtherProjects($mahasiswa, $project);

            $member = PblTeamMember::query()->updateOrCreate(
                [
                    'pbl_project_id' => $project->id,
                    'user_id' => $mahasiswa->id,
                ],
                [
                    'role' => PblTeamMember::ROLE_KETUA,
                    'assigned_by' => $actor->id,
                ]
            );

            self::syncUserTim($mahasiswa, $project);

            return $member->fresh(['user']);
        });
    }

    /**
     * @throws ValidationException
     */
    public static function assignWakil(PblProject $project, User $mahasiswa, User $actor): PblTeamMember
    {
        if (!self::canManageTeamStructure($actor, $project)) {
            throw ValidationException::withMessages([
                'user_id' => 'Anda tidak berwenang mengatur wakil ketua tim proyek ini.',
            ]);
        }

        self::assertEligibleMahasiswa($mahasiswa, $project);

        if (self::isKetuaOf($mahasiswa, $project)) {
            throw ValidationException::withMessages([
                'user_id' => 'Mahasiswa ini sudah menjadi ketua tim proyek ini.',
            ]);
        }

        return DB::transaction(function () use ($project, $mahasiswa, $actor) {
            $existingWakil = PblTeamMember::query()
                ->where('pbl_project_id', $project->id)
                ->where('role', PblTeamMember::ROLE_WAKIL)
                ->where('user_id', '!=', $mahasiswa->id)
                ->first();

            if ($existingWakil) {
                $existingWakil->update(['role' => PblTeamMember::ROLE_ANGGOTA]);
            }

            self::detachFromOtherProjects($mahasiswa, $project);

            $member = PblTeamMember::query()->updateOrCreate(
                [
                    'pbl_project_id' => $project->id,
                    'user_id' => $mahasiswa->id,
                ],
                [
                    'role' => PblTeamMember::ROLE_WAKIL,
                    'assigned_by' => $actor->id,
                ]
            );

            self::syncUserTim($mahasiswa, $project);

            return $member->fresh(['user']);
        });
    }

    /**
     * @throws ValidationException
     */
    public static function clearTeamRole(PblProject $project, string $role, User $actor): void
    {
        if (!self::canManageTeamStructure($actor, $project)) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak berwenang mengubah struktur tim ini.',
            ]);
        }

        if (!in_array($role, [PblTeamMember::ROLE_KETUA, PblTeamMember::ROLE_WAKIL], true)) {
            throw ValidationException::withMessages([
                'role' => 'Peran tim tidak valid.',
            ]);
        }

        PblTeamMember::query()
            ->where('pbl_project_id', $project->id)
            ->where('role', $role)
            ->get()
            ->each(function (PblTeamMember $member) {
                $member->update(['role' => PblTeamMember::ROLE_ANGGOTA]);
            });
    }

    /**
     * @throws ValidationException
     */
    public static function assignAnggota(PblProject $project, User $mahasiswa, User $actor): PblTeamMember
    {
        if (!self::canAssignAnggota($actor, $project)) {
            throw ValidationException::withMessages([
                'user_id' => 'Anda tidak berwenang menambah anggota tim ini.',
            ]);
        }

        self::assertEligibleMahasiswa($mahasiswa, $project);

        if (self::isKetuaOf($mahasiswa, $project)) {
            throw ValidationException::withMessages([
                'user_id' => 'Mahasiswa ini sudah menjadi ketua tim proyek ini.',
            ]);
        }

        return DB::transaction(function () use ($project, $mahasiswa, $actor) {
            self::detachFromOtherProjects($mahasiswa, $project);

            $member = PblTeamMember::query()->updateOrCreate(
                [
                    'pbl_project_id' => $project->id,
                    'user_id' => $mahasiswa->id,
                ],
                [
                    'role' => PblTeamMember::ROLE_ANGGOTA,
                    'assigned_by' => $actor->id,
                ]
            );

            self::syncUserTim($mahasiswa, $project);

            return $member->fresh(['user']);
        });
    }

    /**
     * @throws ValidationException
     */
    public static function removeMember(PblProject $project, User $mahasiswa, User $actor): void
    {
        $member = PblTeamMember::query()
            ->where('pbl_project_id', $project->id)
            ->where('user_id', $mahasiswa->id)
            ->first();

        if (!$member) {
            $actorIsDosen = self::managesProject($actor, $project);
            $actorIsKetua = self::isKetuaOf($actor, $project);
            $actorIsAdmin = $actor->isLaboran();

            if (strcasecmp(trim((string) $mahasiswa->tim), trim($project->nama)) === 0) {
                if (!$actorIsDosen && !$actorIsAdmin && !$actorIsKetua) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Anda tidak berwenang menghapus mahasiswa dari tim ini.',
                    ]);
                }

                $prevTim = $mahasiswa->tim;
                $prevShift = $mahasiswa->shift;
                self::releaseFromPbl($mahasiswa);

                app(\App\Services\PblTeamAssignmentNotifier::class)->notifyKicked(
                    $mahasiswa->fresh(),
                    $actor,
                    $project,
                    $prevTim,
                    $prevShift,
                );

                return;
            }

            throw ValidationException::withMessages([
                'user_id' => 'Mahasiswa tidak terdaftar di tim proyek ini.',
            ]);
        }

        $actorIsDosen = self::managesProject($actor, $project);
        $actorIsKetua = self::isKetuaOf($actor, $project);
        $actorIsAdmin = $actor->isLaboran();

        if ($member->isKetua() && !$actorIsDosen && !$actorIsAdmin) {
            throw ValidationException::withMessages([
                'user_id' => 'Ketua tim hanya dapat dihapus oleh dosen pengampu atau admin.',
            ]);
        }

        if ($member->isWakil() && !$actorIsDosen && !$actorIsAdmin) {
            throw ValidationException::withMessages([
                'user_id' => 'Wakil ketua hanya dapat dihapus oleh dosen pengampu atau admin.',
            ]);
        }

        if ($member->isAnggota() && !$actorIsKetua && !$actorIsDosen && !$actorIsAdmin) {
            throw ValidationException::withMessages([
                'user_id' => 'Anda tidak berwenang menghapus anggota ini.',
            ]);
        }

        $prevTim = $mahasiswa->tim;
        $prevShift = $mahasiswa->shift;

        DB::transaction(function () use ($mahasiswa, $member) {
            $member->delete();

            if (trim((string) $mahasiswa->tim) !== '') {
                $mahasiswa->tim = null;
                $mahasiswa->save();
            }
        });

        if ($actorIsDosen || $actorIsAdmin || ($actorIsKetua && $member->isAnggota())) {
            app(\App\Services\PblTeamAssignmentNotifier::class)->notifyKicked(
                $mahasiswa->fresh(),
                $actor,
                $project,
                $prevTim,
                $prevShift,
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private static function assertEligibleMahasiswa(User $mahasiswa, PblProject $project): void
    {
        if (!$mahasiswa->isMahasiswa()) {
            throw ValidationException::withMessages([
                'user_id' => 'Hanya akun mahasiswa yang dapat masuk tim PBL.',
            ]);
        }

        if (!$mahasiswa->is_active || $mahasiswa->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'user_id' => 'Mahasiswa harus aktif dan sudah disetujui admin.',
            ]);
        }

        if (!$project->aktif) {
            throw ValidationException::withMessages([
                'pbl_project_id' => 'Proyek PBL tidak aktif.',
            ]);
        }
    }

    private static function detachFromOtherProjects(User $mahasiswa, PblProject $target): void
    {
        $otherMemberships = PblTeamMember::query()
            ->where('user_id', $mahasiswa->id)
            ->where('pbl_project_id', '!=', $target->id)
            ->get();

        foreach ($otherMemberships as $membership) {
            $membership->delete();
        }

        $currentTim = trim((string) $mahasiswa->tim);
        if ($currentTim !== '' && strcasecmp($currentTim, trim($target->nama)) !== 0) {
            $mahasiswa->tim = null;
            $mahasiswa->save();
        }
    }

    private static function syncUserTim(User $mahasiswa, PblProject $project): void
    {
        $mahasiswa->tim = $project->nama;
        $mahasiswa->shift = $project->shift_sesi;
        $mahasiswa->save();
    }
}
