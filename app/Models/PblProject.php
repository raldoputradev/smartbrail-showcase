<?php

namespace App\Models;

use App\Support\PblTeamService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PblProject extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'manpro',
        'co_manpro',
        'shift_sesi',
        'manpro_user_id',
        'co_manpro_user_id',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function manproUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manpro_user_id');
    }

    public function coManproUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'co_manpro_user_id');
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(User::class, 'tim', 'nama')
            ->where('role', 'mahasiswa');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(PblTeamMember::class, 'pbl_project_id');
    }

    public function ketuaMember(): HasMany
    {
        return $this->hasMany(PblTeamMember::class, 'pbl_project_id')
            ->where('role', PblTeamMember::ROLE_KETUA);
    }

    public function anggotaMembers(): HasMany
    {
        return $this->hasMany(PblTeamMember::class, 'pbl_project_id')
            ->where('role', PblTeamMember::ROLE_ANGGOTA);
    }

    public function isManagedBy(User $user): bool
    {
        return PblTeamService::managesProject($user, $this);
    }
}
