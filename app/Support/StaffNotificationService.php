<?php

namespace App\Support;

use App\Models\MesinIot;
use App\Models\RuanganAccessEvent;
use App\Models\RuanganPengajuan;
use App\Models\StaffOpsDismissal;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\PendingAccountApproval;
use App\Support\PendingEmailVerification;
use Illuminate\Support\Collection;

class StaffNotificationService
{
    /**
     * Fingerprint konteks item — memakai `sig` (id entitas terbaru) bila ada,
     * sehingga entri BARU pada jumlah yang sama tetap muncul kembali setelah
     * dismissal sebelumnya (dismissal tidak lagi permanen per angka).
     *
     * @param  array{id: string, count?: int, sig?: string|null}  $item
     */
    public static function fingerprint(array $item): string
    {
        return $item['id'] . ':' . ($item['sig'] ?? (string) (int) ($item['count'] ?? 0));
    }

    /**
     * Antrian operasional yang belum di-dismiss user.
     *
     * @return Collection<int, array{id: string, title: string, body: string, url: string, kind: string, count: int, fingerprint: string}>
     */
    public static function operationalItems(User $user): Collection
    {
        return self::rawOperationalItems($user)
            ->filter(fn (array $item) => !self::isDismissed($user, $item))
            ->values();
    }

    public static function dismissByKey(User $user, string $opsKey): bool
    {
        if (self::isPersistentOpsKey($opsKey)) {
            return false;
        }

        foreach (self::rawOperationalItems($user) as $item) {
            if ($item['id'] !== $opsKey) {
                continue;
            }
            self::dismiss($user, $item['id'], $item['fingerprint']);

            return true;
        }

        return false;
    }

    /** Jumlah item antrian (bukan jumlah mesin/pengajuan di dalamnya). */
    public static function operationalCount(User $user): int
    {
        return self::operationalItems($user)->count();
    }

    /**
     * Item dianggap sudah dibaca bila sig-nya (id entitas terbaru) tidak lebih baru
     * dari yang terakhir di-dismiss — jadi centang = sembunyikan permanen, dan item
     * hanya muncul kembali bila ada entri yang benar-benar baru.
     */
    public static function isDismissed(User $user, array $item): bool
    {
        if (self::isPersistentOpsKey($item['id'])) {
            return false;
        }

        $dismissal = StaffOpsDismissal::query()
            ->where('user_id', $user->id)
            ->where('ops_key', $item['id'])
            ->latest('dismissed_at')
            ->first();
        if (!$dismissal) {
            return false;
        }

        if (isset($item['sig']) && $item['sig'] !== null && $item['sig'] !== '') {
            $storedSig = (int) substr((string) strrchr($dismissal->context_fingerprint, ':'), 1);
            return (int) $item['sig'] <= $storedSig;
        }

        return $dismissal->context_fingerprint === self::fingerprint($item);
    }

    public static function dismiss(User $user, string $opsKey, string $fingerprint): void
    {
        if (self::isPersistentOpsKey($opsKey)) {
            return;
        }

        StaffOpsDismissal::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'ops_key' => $opsKey,
                'context_fingerprint' => $fingerprint,
            ],
            ['dismissed_at' => now()]
        );
    }

    public static function dismissAllVisible(User $user): int
    {
        $n = 0;
        foreach (self::operationalItems($user) as $item) {
            if (self::isPersistentOpsKey($item['id'])) {
                continue;
            }
            self::dismiss($user, $item['id'], $item['fingerprint']);
            $n++;
        }

        return $n;
    }

    /**
     * @return Collection<int, array{id: string, title: string, body: string, url: string, kind: string, count: int, fingerprint: string}>
     */
    /**
     * @return Collection<int, array{id: string, title: string, body: string, url: string, kind: string, count: int, fingerprint: string}>
     */
    public static function rawOperationalItems(User $user): Collection
    {
        $items = collect();

        if ($user->isLaboran()) {
            $otpPending = PendingEmailVerification::count();
            if ($otpPending > 0) {
                $items->push(self::item(
                    'ops-users-otp',
                    PendingEmailVerification::notificationTitle(),
                    PendingEmailVerification::notificationBody(),
                    route('admin.users', ['verify_status' => 'otp_pending']),
                    'info',
                    $otpPending,
                    (string) PendingEmailVerification::query()->max('id')
                ));
            }

            $pendingUsers = PendingAccountApproval::count();
            if ($pendingUsers > 0) {
                $items->push(self::item(
                    'ops-users',
                    PendingAccountApproval::notificationTitle(),
                    PendingAccountApproval::notificationBody(),
                    route('admin.users', ['verify_status' => 'ready_approve']),
                    'warning',
                    $pendingUsers,
                    (string) PendingAccountApproval::query()->max('id')
                ));
            }

            $pengajuanQ = RuanganPengajuan::query()
                ->whereIn('status', RuanganPengajuan::STATUS_AKTIF_MENUNGGU);
            $pendingPengajuan = $pengajuanQ->count();
            if ($pendingPengajuan > 0) {
                $items->push(self::item(
                    'ops-pengajuan',
                    "{$pendingPengajuan} pengajuan ruangan menunggu keputusan",
                    'Tinjau pemohon, jadwal, dan konflik — keputusan dicatat atas nama akun Anda.',
                    route('admin.pengajuan.index'),
                    'warning',
                    $pendingPengajuan,
                    (string) $pengajuanQ->max('id')
                ));
            }

            $suggestQ = Suggestion::query()->where('status', 'baru');
            $newSuggest = $suggestQ->count();
            if ($newSuggest > 0) {
                $items->push(self::item(
                    'ops-suggest',
                    "{$newSuggest} saran & masukan baru (belum ditinjau)",
                    'Masuk dari landing, dosen, atau mahasiswa — buka menu Saran & Masukan.',
                    route('admin.suggestions.index', ['status' => 'baru']),
                    'info',
                    $newSuggest,
                    (string) $suggestQ->max('id')
                ));
            }

            $loginLocked = PendingLoginUnlock::count();
            if ($loginLocked > 0) {
                $items->push(self::item(
                    'ops-login-locked',
                    PendingLoginUnlock::notificationTitle(),
                    PendingLoginUnlock::notificationBody(),
                    route('admin.users', ['login_lock' => 'locked']),
                    'danger',
                    $loginLocked,
                    (string) PendingLoginUnlock::query()->max('id')
                ));
            }

            // Keamanan pintu: terbuka paksa / menganga / buka manual — 2 jam
            // terakhir. sig=max(id) → kejadian baru memunculkan notif lagi
            // walau yang lama sudah di-dismiss.
            $secQ = RuanganAccessEvent::query()
                ->where('terjadi_pada', '>=', now()->subHours(2))
                ->where(function ($q) {
                    $q->where(fn ($w) => $w->where('jenis', 'tolak')->where('meta->alasan', 'pintu_paksa'))
                        ->orWhere(fn ($w) => $w->where('jenis', 'buka')->where('meta->sumber', 'tombol_manual'))
                        ->orWhere(fn ($w) => $w->where('jenis', 'telemetri')
                            ->where(fn ($m) => $m->where('meta->paksa', true)->orWhere('meta->ajar', true)));
                });
            $secCount = (clone $secQ)->count();
            if ($secCount > 0) {
                $secLast = (clone $secQ)->with('ruangan:id,nama')->latest('terjadi_pada')->first();
                $items->push(self::item(
                    'ops-pintu-keamanan',
                    "{$secCount} kejadian keamanan pintu (2 jam terakhir)",
                    'Terakhir: '.($secLast?->ruangan?->nama ?? 'ruangan').' — '
                        .($secLast?->terjadi_pada?->diffForHumans() ?? '').'. Periksa segera.',
                    $secLast?->ruangan_id ? route('admin.ruangan.aktivitas', $secLast->ruangan_id) : route('admin.monitoring'),
                    'danger',
                    $secCount,
                    (string) $secQ->max('id')
                ));
            }

            $enrollBelumUsers = User::query()
                ->where('role', 'mahasiswa')
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u) => ! $u->hasFingerprintEnrolled());
            $enrollBelum = $enrollBelumUsers->count();
            if ($enrollBelum > 0) {
                $items->push(self::item(
                    'ops-enroll',
                    "{$enrollBelum} mahasiswa belum enroll sidik jari",
                    'Mahasiswa aktif tanpa template sidik jari valid — arahkan ke mesin absensi atau halaman Fingerprint.',
                    route('admin.fingerprint', ['status' => 'pending_enroll']),
                    'warning',
                    $enrollBelum,
                    (string) $enrollBelumUsers->max('id')
                ));
            }

            $threshold = MesinIot::ONLINE_THRESHOLD_MINUTES;
            $mesinQ = MesinIot::query()
                ->where('is_active', true)
                ->where(function ($q) use ($threshold) {
                    $q->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', now()->subMinutes($threshold));
                });
            $offline = $mesinQ->count();
            if ($offline > 0) {
                $items->push(self::item(
                    'ops-mesin',
                    "{$offline} mesin IoT offline",
                    'Periksa Monitoring / heartbeat mesin.',
                    route('admin.monitoring'),
                    'danger',
                    $offline,
                    (string) $mesinQ->max('id')
                ));
            }
        }

        if ($user->isDosen()) {
            self::pushFeedbackReplyItem($items, $user, 'dosen.feedback.index');
        }

        if ($user->isMahasiswa()) {
            self::pushFeedbackReplyItem($items, $user, 'mahasiswa.gate');
        }

        return $items;
    }

    /**
     * @return array{id: string, title: string, body: string, url: string, kind: string, count: int, fingerprint: string}
     */
    private static function item(
        string $id,
        string $title,
        string $body,
        string $url,
        string $kind,
        int $count,
        ?string $sig = null
    ): array {
        $row = [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'url' => self::urlWithDismiss($url, $id),
            'kind' => $kind,
            'count' => $count,
            'sig' => $sig,
        ];
        $row['fingerprint'] = self::fingerprint($row);

        return $row;
    }

    private static function urlWithDismiss(string $url, string $opsKey): string
    {
        if (self::isPersistentOpsKey($opsKey)) {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . 'dismiss_notif=' . urlencode($opsKey);
    }

    private static function isPersistentOpsKey(string $opsKey): bool
    {
        return $opsKey === 'ops-suggest';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     */
    private static function pushFeedbackReplyItem(Collection $items, User $user, string $routeName): void
    {
        $replyQ = Suggestion::query()
            ->where('user_id', $user->id)
            ->whereNotNull('admin_replied_at')
            ->whereNull('user_read_reply_at');
        $count = $replyQ->count();

        if ($count > 0) {
            $items->push(self::item(
                'ops-feedback-reply',
                "{$count} balasan admin pada saran Anda",
                'Buka halaman Saran & Masukan.',
                route($routeName),
                'info',
                $count,
                (string) $replyQ->max('id')
            ));
        }
    }
}
