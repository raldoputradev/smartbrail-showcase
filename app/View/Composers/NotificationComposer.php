<?php

namespace App\View\Composers;

use App\Models\Announcement;
use App\Support\StaffNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class NotificationComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (!$user) {
            $view->with([
                'notifAnnouncements'        => collect(),
                'notifUnreadCount'          => 0,
                'notifOpsCount'             => 0,
                'notifOpsItems'             => collect(),
                'notifTotalCount'           => 0,
                'sidebarAnnouncementBadge'  => 0,
            ]);
            return;
        }

        try {
            // Lonceng: pengumuman + notifikasi akun (riwayat di halaman Pengumuman).
            $items = Announcement::query()
                ->active()
                ->bellFeed($user)
                ->forAudience($user)
                ->unreadBy($user)
                ->with('author:id,nama_lengkap,role')
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->each(fn (Announcement $a) => $a->setAttribute('is_unread', true));

            $unreadCount = Announcement::query()
                ->active()
                ->bellFeed($user)
                ->forAudience($user)
                ->unreadBy($user)
                ->count();
            $opsItems = StaffNotificationService::operationalItems($user);
            $opsCount = StaffNotificationService::operationalCount($user);

            $view->with([
                'notifAnnouncements'       => $items,
                'notifUnreadCount'         => $unreadCount,
                'notifOpsCount'            => $opsCount,
                'notifOpsItems'            => $opsItems,
                'notifTotalCount'          => $unreadCount + $opsCount,
                'sidebarAnnouncementBadge' => $unreadCount,
            ]);
        } catch (Throwable $e) {
            Log::warning('NotificationComposer gagal memuat notifikasi: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);

            $view->with([
                'notifAnnouncements'       => collect(),
                'notifUnreadCount'         => 0,
                'notifOpsCount'            => 0,
                'notifOpsItems'            => collect(),
                'notifTotalCount'          => 0,
                'sidebarAnnouncementBadge' => 0,
            ]);
        }
    }
}
