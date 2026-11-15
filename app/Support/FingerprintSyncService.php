<?php

namespace App\Support;

use App\Models\MesinIot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FingerprintSyncService
{
    /** Role portal yang boleh punya finger_id + template (enroll di mesin). */
    public const FP_ROLES = ['mahasiswa', 'laboran', 'dosen', 'admin'];

    /**
     * Pengguna dengan template blob valid di database.
     * Default mahasiswa (dipakai statistik halaman); null = semua role (sync mesin).
     */
    public static function enrolledQuery(array|string|null $roles = 'mahasiswa'): Builder
    {
        $query = User::query()->where('is_active', true);
        if ($roles !== null) {
            $query->whereIn('role', (array) $roles);
        }

        return $query
            ->whereNotNull('finger_id')
            ->whereNotNull('finger_template')
            ->where('finger_template', '!=', '');
    }

    public static function countEnrolled(array|string $roles = 'mahasiswa'): int
    {
        return self::enrolledQuery($roles)
            ->get()
            ->filter(fn (User $u) => FingerprintTemplateCodec::hasValidTemplate($u->finger_template))
            ->count();
    }

    public static function countPendingEnroll(array|string $roles = 'mahasiswa'): int
    {
        return User::query()
            ->whereIn('role', (array) $roles)
            ->whereNotNull('finger_id')
            ->get()
            ->filter(fn (User $u) => ! FingerprintTemplateCodec::hasValidTemplate($u->finger_template))
            ->count();
    }

    /** @deprecated gunakan countEnrolled('mahasiswa') */
    public static function countEnrolledMahasiswa(): int
    {
        return self::countEnrolled('mahasiswa');
    }

    /** @deprecated gunakan countPendingEnroll('mahasiswa') */
    public static function countPendingEnrollMahasiswa(): int
    {
        return self::countPendingEnroll('mahasiswa');
    }

    /**
     * @return Collection<int, User>
     */
    public static function fetchForSync(?string $since = null, ?int $limit = null, ?int $afterFingerId = null): Collection
    {
        $query = self::enrolledQuery(null)
            ->orderBy('finger_id');

        if ($afterFingerId !== null && $afterFingerId > 0) {
            $query->where('finger_id', '>', $afterFingerId);
        }

        if ($since !== null && $since !== '') {
            try {
                $cutoff = Carbon::parse($since);
                $query->where('updated_at', '>', $cutoff);
            } catch (\Throwable) {
                // Abaikan filter since jika format invalid — kembalikan full sync
            }
        }

        if ($limit !== null && $limit > 0) {
            $query->limit(min($limit, 300));
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildSyncPayload(MesinIot $mesin, ?string $since = null, ?int $limit = null, ?int $afterFingerId = null): array
    {
        $users = self::fetchForSync($since, $limit, $afterFingerId)
            ->filter(fn (User $u) => FingerprintTemplateCodec::hasValidTemplate($u->finger_template));

        $fingerprints = $users->map(fn (User $u) => self::formatFingerprintEntry($u))->values();

        $latest = $users->max(fn (User $u) => $u->updated_at);

        return [
            'id_mesin' => $mesin->id_mesin,
            'nama_mesin' => $mesin->nama_mesin,
            'template_bytes' => FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH,
            'sync_since' => $since,
            'after_finger_id' => $afterFingerId,
            'sync_token' => $latest?->toIso8601String(),
            'count' => $fingerprints->count(),
            'fingerprints' => $fingerprints,
        ];
    }

    /**
     * Ringkasan tanpa blob — untuk polling delta.
     *
     * @return array<string, mixed>
     */
    public static function buildStatusPayload(MesinIot $mesin): array
    {
        $rows = self::enrolledQuery(null)
            ->orderBy('finger_id')
            ->get(['finger_id', 'finger_template', 'updated_at'])
            ->filter(fn (User $u) => FingerprintTemplateCodec::hasValidTemplate($u->finger_template));

        $latest = $rows->max(fn (User $u) => $u->updated_at);

        return [
            'id_mesin' => $mesin->id_mesin,
            'sync_token' => $latest?->toIso8601String(),
            'count' => $rows->count(),
            'fingerprints' => $rows->map(fn (User $u) => [
                'finger_id' => (int) $u->finger_id,
                'updated_at' => $u->updated_at?->toIso8601String(),
                'template_crc' => FingerprintTemplateCodec::crc32Hex($u->finger_template),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatFingerprintEntry(User $user, bool $includeTemplate = true): array
    {
        $row = [
            'finger_id' => (int) $user->finger_id,
            'nitk_nidn' => $user->nitk_nidn,
            'nama_lengkap' => $user->nama_lengkap,
            'role' => $user->role,
            'kelas' => $user->kelas,
            'tim' => $user->tim,
            'shift' => $user->shift,
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];

        if ($includeTemplate && FingerprintTemplateCodec::hasValidTemplate($user->finger_template)) {
            $row['template'] = FingerprintTemplateCodec::toBase64($user->finger_template);
            $row['template_hex'] = FingerprintTemplateCodec::toHex($user->finger_template);
            $row['template_size'] = FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH;
            $row['template_crc'] = FingerprintTemplateCodec::crc32Hex($user->finger_template);
        }

        return $row;
    }
}
