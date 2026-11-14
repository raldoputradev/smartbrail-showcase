<?php

namespace App\Support;

use App\Models\FingerprintSyncLog;
use App\Models\MesinIot;
use Illuminate\Http\Request;

class FingerprintSyncLogService
{
    /**
     * Hindari history penuh: pull kosong atau batch identik berulang tidak dicatat.
     */
    public static function shouldRecordPull(MesinIot $mesin, array $payload): bool
    {
        $count = (int) ($payload['count'] ?? 0);
        if ($count === 0) {
            return false;
        }

        $fingerIds = self::fingerIdsFromPayload($payload);
        if ($fingerIds === []) {
            return true;
        }

        $last = FingerprintSyncLog::query()
            ->where('id_mesin', $mesin->id_mesin)
            ->where('action', 'sync_pull')
            ->where('success', true)
            ->latest('requested_at')
            ->first();

        if ($last && is_array($last->finger_ids)
            && array_values($last->finger_ids) === array_values($fingerIds)) {
            return false;
        }

        return true;
    }

    public static function record(
        MesinIot $mesin,
        string $action,
        Request $request,
        bool $success,
        int $templatesCount = 0,
        ?array $fingerIds = null,
        ?string $syncToken = null,
        ?string $message = null,
        ?string $since = null,
        ?int $afterFingerId = null,
        ?int $batchLimit = null,
    ): void {
        try {
            FingerprintSyncLog::query()->create([
                'id_mesin' => $mesin->id_mesin,
                'mesin_tipe' => $mesin->tipe,
                'action' => $action,
                'since' => $since,
                'after_finger_id' => $afterFingerId,
                'batch_limit' => $batchLimit,
                'templates_count' => $templatesCount,
                'finger_ids' => $fingerIds !== null && $fingerIds !== [] ? array_values($fingerIds) : null,
                'sync_token' => $syncToken,
                'success' => $success,
                'message' => $message,
                'ip_address' => $request->ip(),
                'requested_at' => now(),
            ]);
        } catch (\Throwable) {
            // Jangan gagalkan API mesin jika log gagal
        }
    }

    /**
     * @return list<int>
     */
    public static function fingerIdsFromPayload(array $payload): array
    {
        $fps = $payload['fingerprints'] ?? [];

        if (! is_array($fps)) {
            return [];
        }

        return collect($fps)
            ->pluck('finger_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array{rows: \Illuminate\Support\Collection, stats: array<string, int|float>}
     */
    public static function adminReport(?string $idMesin = null, int $perPage = 20): array
    {
        $query = FingerprintSyncLog::query()->orderByDesc('requested_at');

        if ($idMesin !== null && $idMesin !== '') {
            $query->where('id_mesin', $idMesin);
        }

        $rows = $query->paginate($perPage, ['*'], 'sync_page')->withQueryString();

        $today = now()->toDateString();
        $statsQuery = FingerprintSyncLog::query();
        if ($idMesin !== null && $idMesin !== '') {
            $statsQuery->where('id_mesin', $idMesin);
        }

        $pullTodayQuery = (clone $statsQuery)->whereDate('requested_at', $today)->where('action', 'sync_pull');

        return [
            'rows' => $rows,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'today' => (clone $statsQuery)->whereDate('requested_at', $today)->count(),
                'pull_today' => (clone $pullTodayQuery)->count(),
                'templates_today' => (clone $pullTodayQuery)->sum('templates_count'),
            ],
        ];
    }

    public static function pruneOlderThan(int $days = 30): int
    {
        if ($days < 1) {
            return 0;
        }

        return FingerprintSyncLog::query()
            ->where('requested_at', '<', now()->subDays($days))
            ->delete();
    }
}
