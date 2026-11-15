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
        'must_change_password',
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
            'must_change_password' => 'boolean',
            'finger_id' => 'integer',
            'is_admin' => 'boolean',
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
            'last_seen_at' => 'datetime',
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

    /** Akun yang sedang menggunakan password sementara dan wajib menggantinya saat login. */
    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    /** Kelas yang ditugaskan laboran sebagai cakupan operasional dosen. */
    public function kelasCakupan(): HasMany
    {
        return $this->hasMany(DosenKelas::class);
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
     * Check if user is laboran (admin operasional).
     * Role 'admin' dihitung laboran juga: admin tetap dapat menjalankan
     * operasional (persetujuan, fingerprint, laporan) di samping kewenangan sistem.
     */
    public function isLaboran(): bool
    {
        return in_array($this->role, ['laboran', 'admin'], true);
    }

    /**
     * Kewenangan admin sistem (akun/import/pengaturan/perangkat).
     * Sumber utama: role 'admin'. Flag is_admin tetap dihormati sebagai
     * transisi sampai migrasi role akun admin selesai.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->is_admin;
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
            'admin'     => 'Admin',
            'laboran'   => 'Laboran',
            'dosen'     => 'Dosen',
            'mahasiswa' => 'Mahasiswa',
            default     => ucfirst($this->role),
        };
    }

    /**
     * Presence — online bila aktif dalam 5 menit terakhir.
     * `last_seen_at` disentuh oleh EnforceWebSessionTimeout (maks 1x/menit).
     */
    public function isOnline(int $withinMinutes = 5): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subMinutes($withinMinutes));
    }

    public function getLastSeenLabelAttribute(): ?string
    {
        return $this->last_seen_at?->diffForHumans();
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $raw = trim((string) ($this->profile_photo_path ?? ''));
        if ($raw === '') {
            return null;
        }

        $rel = ltrim(str_replace('\\', '/', $raw), '/');
        if (str_contains($rel, '..')) {
            return null;
        }

        if (is_file(public_path($rel))) {
            return asset($rel);
        }

        if (is_file(public_path('storage/' . $rel))) {
            return asset('storage/' . $rel);
        }

        if (is_file(storage_path('app/public/' . $rel))) {
            return asset('storage/' . $rel);
        }

        return null;
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
