<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Suggestion;
use App\Support\StaffNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * Mark a single announcement as read for the current user.
     */
    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        AnnouncementRead::firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id'         => $user->id,
            ],
            [
                'read_at' => Carbon::now(),
            ]
        );

        $opsCount = StaffNotificationService::operationalCount($user);

        return response()->json([
            'ok'                  => true,
            'unread_count'        => $this->unreadCountFor($user) + $opsCount,
            'announcement_unread' => $this->unreadCountFor($user),
            'ops_count'           => $opsCount,
            'total_unread_count'  => $this->unreadCountFor($user) + $opsCount,
        ]);
    }

    /**
     * Mark every announcement currently visible to the user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $announcementIds = Announcement::query()
            ->active()
            ->bellFeed($user)
            ->forAudience($user)
            ->pluck('id');

        $now = Carbon::now();
        $rows = $announcementIds->map(fn ($id) => [
            'announcement_id' => $id,
            'user_id'         => $user->id,
            'read_at'         => $now,
        ])->all();

        if (!empty($rows)) {
            AnnouncementRead::query()->upsert(
                $rows,
                ['announcement_id', 'user_id'],
                ['read_at']
            );
        }

        $dismissedOps = StaffNotificationService::dismissAllVisible($user);
        $opsCount = StaffNotificationService::operationalCount($user);

        $ops = StaffNotificationService::operationalItems($user);

        return response()->json([
            'ok'                  => true,
            'marked'              => count($rows),
            'dismissed_ops'       => $dismissedOps,
            'unread_count'        => $opsCount,
            'announcement_unread' => 0,
            'ops_count'           => $opsCount,
            'total_unread_count'  => $opsCount,
            'ops'                 => $ops->values(),
            'items'               => [],
        ]);
    }

    public function dismissOps(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'ops_key' => ['required', 'string', 'max:64'],
            'fingerprint' => ['required', 'string', 'max:64'],
        ]);

        StaffNotificationService::dismiss($user, $validated['ops_key'], $validated['fingerprint']);

        return $this->countsResponse($user);
    }

    private function countsResponse($user): JsonResponse
    {
        $announcementUnread = $this->unreadCountFor($user);
        $opsCount = StaffNotificationService::operationalCount($user);

        return response()->json([
            'ok'                  => true,
            'announcement_unread' => $announcementUnread,
            'ops_count'           => $opsCount,
            'unread_count'        => $announcementUnread + $opsCount,
            'total_unread_count'  => $announcementUnread + $opsCount,
            'ops'                 => StaffNotificationService::operationalItems($user)->values(),
        ]);
    }

    /**
     * Returns the JSON payload that the topbar bell polls for live updates.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $items = Announcement::query()
            ->active()
            ->bellFeed($user)
            ->forAudience($user)
            ->unreadBy($user)
            ->with('author:id,nama_lengkap,role')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $payload = $items->map(function (Announcement $a) {
            return [
                'id'         => $a->id,
                'judul'      => $a->judul,
                'isi'        => mb_strimwidth(strip_tags((string) $a->isi), 0, 140, '…'),
                'target'     => $a->target,
                'prioritas'  => $a->prioritas,
                'author'     => optional($a->author)->nama_lengkap ?? 'Admin',
                'is_unread'  => true,
                'created_at' => optional($a->created_at)->toIso8601String(),
                'created_human' => optional($a->created_at)->diffForHumans(),
            ];
        });

        $announcementUnread = $this->unreadCountFor($user);
        $ops = StaffNotificationService::operationalItems($user);
        $opsCount = StaffNotificationService::operationalCount($user);
        $newSuggestCount = $user->isLaboran()
            ? Suggestion::query()->where('status', 'baru')->count()
            : 0;

        return response()->json([
            'ok'                  => true,
            'items'               => $payload,
            'ops'                 => $ops->values(),
            'unread_count'        => $announcementUnread + $opsCount,
            'announcement_unread' => $announcementUnread,
            'ops_count'           => $opsCount,
            'total_unread_count'  => $announcementUnread + $opsCount,
            'new_suggest_count'   => $newSuggestCount,
        ]);
    }

    private function unreadCountFor($user): int
    {
        $total = Announcement::query()->active()->bellFeed($user)->forAudience($user)->count();
        $read = AnnouncementRead::query()
            ->where('user_id', $user->id)
            ->whereIn('announcement_id',
                Announcement::query()->active()->bellFeed($user)->forAudience($user)->pluck('id')
            )
            ->count();
        return max(0, $total - $read);
    }
}
