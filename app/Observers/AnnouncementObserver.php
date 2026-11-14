<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\MobilePushToken;
use App\Models\User;
use App\Support\MobilePushService;
use Illuminate\Support\Str;

class AnnouncementObserver
{
    public function created(Announcement $announcement): void
    {
        $this->pushIfNeeded($announcement);
    }

    public function updated(Announcement $announcement): void
    {
        if (! $announcement->wasChanged(['judul', 'isi', 'target', 'is_active'])) {
            return;
        }

        $this->pushIfNeeded($announcement);
    }

    private function pushIfNeeded(Announcement $announcement): void
    {
        if (! $announcement->is_active) {
            return;
        }

        $push = app(MobilePushService::class);
        if (! $push->isConfigured()) {
            return;
        }

        if (! in_array($announcement->target, ['mahasiswa', 'semua'], true)) {
            return;
        }

        $userIds = $this->recipientUserIds($announcement);
        if ($userIds === []) {
            return;
        }

        $title = $this->pushTitle($announcement);
        $body = Str::limit(strip_tags((string) $announcement->isi), 120)
            ?: 'Ada notifikasi baru untuk Anda.';

        $tokens = MobilePushToken::query()
            ->whereIn('user_id', $userIds)
            ->pluck('token')
            ->unique()
            ->values()
            ->all();

        $push->sendToTokens($tokens, $title, $body, [
            'type' => $this->pushType($announcement),
            'announcement_id' => (string) $announcement->id,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function recipientUserIds(Announcement $announcement): array
    {
        if ($announcement->channel === Announcement::CHANNEL_NOTIFIKASI_AKUN
            && filled($announcement->target_tim)) {
            $id = (int) $announcement->target_tim;

            return $id > 0 ? [$id] : [];
        }

        if ($announcement->channel === Announcement::CHANNEL_NOTIFIKASI_LOKER) {
            return [];
        }

        if ($announcement->target === 'mahasiswa') {
            return User::query()
                ->where('role', 'mahasiswa')
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('role', ['mahasiswa', 'dosen'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function pushTitle(Announcement $announcement): string
    {
        $judul = trim((string) $announcement->judul);
        $judul = preg_replace('/^\[[^\]]+\]\s*/', '', $judul) ?? $judul;

        if ($judul !== '') {
            return $judul;
        }

        return match ($announcement->channel) {
            Announcement::CHANNEL_NOTIFIKASI_LOKER => 'Notifikasi Loker',
            Announcement::CHANNEL_NOTIFIKASI_AKUN => 'Notifikasi SIMALAS',
            default => 'Pengumuman SIMALAS',
        };
    }

    private function pushType(Announcement $announcement): string
    {
        return match ($announcement->channel) {
            Announcement::CHANNEL_NOTIFIKASI_LOKER => 'locker',
            Announcement::CHANNEL_NOTIFIKASI_AKUN => 'account',
            default => 'announcement',
        };
    }
}
