<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PblTeamMember extends Model
{
    public const ROLE_KETUA = 'ketua';

    public const ROLE_WAKIL = 'wakil';

    public const ROLE_ANGGOTA = 'anggota';

    protected $fillable = [
        'pbl_project_id',
        'user_id',
        'role',
        'assigned_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PblProject::class, 'pbl_project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isKetua(): bool
    {
        return $this->role === self::ROLE_KETUA;
    }

    public function isWakil(): bool
    {
        return $this->role === self::ROLE_WAKIL;
    }

    public function isAnggota(): bool
    {
        return $this->role === self::ROLE_ANGGOTA;
    }
}
