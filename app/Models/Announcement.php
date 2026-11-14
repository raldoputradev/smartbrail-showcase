<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    use HasFactory;

    public const CHANNEL_PENGUMUMAN = 'pengumuman';

    public const CHANNEL_NOTIFIKASI_LOKER = 'notifikasi_loker';

    public const CHANNEL_NOTIFIKASI_PBL = 'notifikasi_pbl';

    public const CHANNEL_NOTIFIKASI_AKUN = 'notifikasi_akun';

    protected $fillable = [
        'judul',
        'isi',
        'target',
        'target_tim',
        'channel',
        'prioritas',
        'dibuat_oleh',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Pengumuman resmi (bukan notifikasi otomatis loker ke tim tertentu).
     */
    public function scopePengumumanSaja($query)
    {
        if (! self::hasChannelColumn()) {
            return $query;
        }

        return $query->where('channel', self::CHANNEL_PENGUMUMAN);
    }

    /**
     * Item yang tampil di lonceng: pengumuman resmi + notifikasi akun (dosen/mahasiswa).
     */
    public function scopeBellFeed($query, ?User $user = null)
    {
        if (! self::hasChannelColumn()) {
            return $query;
        }

        if ($user && $user->isLaboran()) {
            return $query->where('channel', self::CHANNEL_PENGUMUMAN);
        }

        return $query->where(function ($q) {
            $q->where('channel', self::CHANNEL_PENGUMUMAN)
                ->orWhere('channel', self::CHANNEL_NOTIFIKASI_AKUN);
        });
    }

    public function isLockerNotification(): bool
    {
        if (! self::hasChannelColumn()) {
            return str_starts_with((string) $this->judul, '[LOKER:');
        }

        return $this->channel === self::CHANNEL_NOTIFIKASI_LOKER;
    }

    public static function hasChannelColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasTable('announcements')
                && Schema::hasColumn('announcements', 'channel');
        }

        return $hasColumn;
    }

    public function scopeForRole($query, string $role)
    {
        // Laboran (admin) melihat semua pengumuman karena merekalah yang mengelola.
        if ($role === 'laboran') {
            return $query;
        }
        return $query->where(function ($q) use ($role) {
            $q->where('target', 'semua')->orWhere('target', $role);
        });
    }

    /**
     * Pengumuman khusus tim (mis. notifikasi loker) hanya untuk mahasiswa tim tersebut.
     */
    /**
     * Pengumuman yang belum dibaca user (untuk lonceng / badge).
     */
    public function scopeUnreadBy($query, User $user)
    {
        return $query->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id));
    }

    public function scopeForAudience($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $query->forRole($user->role);

        if ($user->role === 'dosen' && self::hasChannelColumn()) {
            $userId = (string) $user->id;

            return $query->where(function ($q) use ($userId) {
                $q->where(function ($inner) {
                    $inner->where('channel', '!=', self::CHANNEL_NOTIFIKASI_PBL)
                        ->where('channel', '!=', self::CHANNEL_NOTIFIKASI_AKUN)
                        ->orWhereNull('channel');
                })->orWhere(function ($inner) use ($userId) {
                    $inner->where('channel', self::CHANNEL_NOTIFIKASI_PBL)
                        ->where('target_tim', $userId);
                })->orWhere(function ($inner) use ($userId) {
                    $inner->where('channel', self::CHANNEL_NOTIFIKASI_AKUN)
                        ->where('target_tim', $userId);
                });
            })->visibleSinceUserJoined($user);
        }

        if ($user->role !== 'mahasiswa') {
            return $query->visibleSinceUserJoined($user);
        }

        $tim = strtolower(trim((string) ($user->tim ?? '')));
        $userId = (string) $user->id;

        return $query->where(function ($q) use ($tim, $userId) {
            $q->where(function ($inner) use ($userId) {
                $inner->where('channel', self::CHANNEL_NOTIFIKASI_AKUN)
                    ->where('target_tim', $userId);
            })->orWhere(function ($inner) use ($tim) {
                $inner->where(function ($ch) {
                    $ch->where('channel', '!=', self::CHANNEL_NOTIFIKASI_AKUN)
                        ->orWhereNull('channel');
                })->where(function ($tt) use ($tim) {
                    $tt->whereNull('target_tim')
                        ->orWhere('target_tim', '')
                        ->orWhereRaw('LOWER(TRIM(target_tim)) = ?', [$tim]);
                });
            });
        })->visibleSinceUserJoined($user);
    }

    /**
     * User baru tidak melihat arsip notifikasi/pengumuman sebelum akunnya aktif.
     * Titik mulai: approved_at (atau created_at bila belum ada).
     */
    public function scopeVisibleSinceUserJoined($query, User $user)
    {
        $since = $user->approved_at ?? $user->created_at;
        if (! $since) {
            return $query;
        }

        // Buffer 1 menit agar notifikasi approval yang hampir bersamaan tetap masuk.
        return $query->where('created_at', '>=', $since->copy()->subMinute());
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at');
    }

    public function isReadBy(?User $user): bool
    {
        if (!$user) {
            return true;
        }
        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
