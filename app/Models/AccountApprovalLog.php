<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountApprovalLog extends Model
{
    protected $fillable = [
        'user_id',
        'subject_name',
        'subject_email',
        'subject_nitk_nidn',
        'subject_role',
        'subject_meta',
        'actor_id',
        'action',
        'note',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'subject_meta' => 'array',
        ];
    }

    public function subjectDisplayName(): string
    {
        return $this->user?->nama_lengkap
            ?? $this->subject_name
            ?? 'User terhapus';
    }

    public function subjectDisplayEmail(): string
    {
        return $this->user?->email
            ?? $this->subject_email
            ?? '—';
    }

    public function subjectDisplayNitk(): string
    {
        return $this->user?->nitk_nidn
            ?? $this->subject_nitk_nidn
            ?? '—';
    }

    public function subjectDisplayRole(): ?string
    {
        return $this->user?->role ?? $this->subject_role;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
