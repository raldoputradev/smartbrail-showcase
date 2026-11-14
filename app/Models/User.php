<?php

namespace App\Models;

use App\Casts\FingerprintTemplateCast;
use App\Support\FingerprintTemplateCodec;
use App\Support\PblTeamService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'nama_lengkap',
        'nitk_nidn',
        'email',
        'no_hp',
        'profile_photo_path',
        'password',
        'role',
        'kelas',
        'tim',
        'program_studi_id',
        'kelas_paralel',
        'shift',
        'angkatan',
        'semester',
        'semester_type',
        'profile_completed_at',
        'finger_id',
        'finger_template',
        'is_active',
        'approval_status',
        'approval_note',
        'approved_by',
        'approved_at',
        'rejected_at',
        'email_verified_at',
        'mobile_api_token',
        'mobile_token_expires_at',
        'mobile_last_login_at',
        'failed_login_attempts',
        'login_locked_until',
        'login_requires_admin_unlock',
        'login_suspicious_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mobile_api_token',
        'finger_template',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'finger_id' => 'integer',
            'finger_template' => FingerprintTemplateCast::class,
            'angkatan' => 'integer',
            'semester' => 'integer',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'mobile_token_expires_at' => 'datetime',
            'mobile_last_login_at' => 'datetime',
            'login_locked_until' => 'datetime',
            'login_suspicious_at' => 'datetime',
            'login_requires_admin_unlock' => 'boolean',
            'failed_login_attempts' => 'integer',
        ];
    }

    public function isLoginTemporarilyLocked(): bool
    {
        return $this->login_locked_until !== null && $this->login_locked_until->isFuture();
    }

    public function needsAdminLoginUnlock(): bool
    {
        return (bool) $this->login_requires_admin_unlock;
    }

    public function hasLoginSecurityIssue(): bool
    {
        return $this->needsAdminLoginUnlock() || $this->isLoginTemporarilyLocked();
    }

    /** Akun hasil import yang belum menyelesaikan aktivasi mandiri. */
    public function isAwaitingActivation(): bool
    {
        return $this->approval_status === \App\Support\AccountActivation::STATUS;
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function logAbsensi(): HasMany
    {
        return $this->hasMany(LogAbsensi::class);
    }


    public function approvalLogs(): HasMany
    {
        return $this->hasMany(AccountApprovalLog::class);
    }

    public function pblTeamMemberships(): HasMany
    {
        return $this->hasMany(PblTeamMember::class);
    }

    public function isPblKetua(): bool
    {
        if (!$this->isMahasiswa()) {
            return false;
        }

        return $this->pblTeamMemberships()
            ->where('role', PblTeamMember::ROLE_KETUA)
            ->exists();
    }

    public function ketuaPblProject(): ?PblProject
    {
        return PblTeamService::ketuaProjectFor($this);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if user is laboran (admin).
     */
    public function isLaboran(): bool
    {
        return $this->role === 'laboran';
    }

    /**
     * Check if user is dosen.
     */
    public function isDosen(): bool
    {
        return $this->role === 'dosen';
    }

    /**
     * Check if user is mahasiswa.
     */
    public function isMahasiswa(): bool
    {
        return $this->role === 'mahasiswa';
    }

    public function hasFingerprintEnrolled(): bool
    {
        return FingerprintTemplateCodec::hasValidTemplate($this->finger_template);
    }

    public function hasLegacyFingerprintPlaceholder(): bool
    {
        return $this->finger_template === FingerprintTemplateCodec::LEGACY_PLACEHOLDER;
    }

    public function needsFingerprintReEnroll(): bool
    {
        return $this->finger_id !== null
            && ! $this->hasFingerprintEnrolled()
            && ($this->finger_template === null || $this->finger_template === '' || $this->hasLegacyFingerprintPlaceholder());
    }

    /**
     * Get the display name for the user's role.
     */
    public function getRoleDisplayAttribute(): string
    {
        return match ($this->role) {
            'laboran'   => 'Admin Laboran',
            'dosen'     => 'Dosen',
            'mahasiswa' => 'Mahasiswa',
            default     => ucfirst($this->role),
        };
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset($this->profile_photo_path) : null;
    }

    public function hasCompleteStudentProfile(): bool
    {
        if ($this->role !== 'mahasiswa') {
            return true;
        }

        return filled($this->kelas)
            && filled($this->tim)
            && filled($this->angkatan)
            && filled($this->semester)
            && filled($this->semester_type);
    }
}
