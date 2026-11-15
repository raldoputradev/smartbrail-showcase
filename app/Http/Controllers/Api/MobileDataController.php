<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\Suggestion;
use App\Models\AbsensiHarian;
use App\Support\AbsensiKebijakan;
use App\Support\AbsensiSnapshot;
use App\Support\SuggestionSubmissionLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileDataController extends Controller
{
    public function announcements(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        $role = $user?->role === 'mahasiswa' ? 'mahasiswa' : 'dosen';

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = $validated['limit'] ?? 20;

        $baseQuery = Announcement::active()
            ->pengumumanSaja()
            ->forAudience($user);

        $unreadCount = (clone $baseQuery)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user?->id))
            ->count();

        $suggestionReplyUnread = self::suggestionReplyUnreadCount($user);

        $rows = $baseQuery
            ->with('author:id,nama_lengkap')
            ->withExists(['reads as is_read' => fn ($q) => $q->where('user_id', $user?->id)])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'judul' => $a->judul,
                'isi' => $a->isi,
                'target' => $a->target,
                'prioritas' => $a->prioritas,
                'expires_at' => optional($a->expires_at)->toIso8601String(),
                'created_at' => optional($a->created_at)->toIso8601String(),
                'penulis' => $a->author->nama_lengkap ?? null,
                'is_read' => (bool) $a->is_read,
            ]);

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'unread_count' => $unreadCount + $suggestionReplyUnread,
            'announcement_unread_count' => $unreadCount,
            'suggestion_reply_unread_count' => $suggestionReplyUnread,
            'data' => $rows,
        ]);
    }

    /**
     * Balasan admin terhadap saran/masukan akun mahasiswa/dosen (notifikasi mobile).
     */
    public function suggestionReplies(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        abort_unless($user && in_array($user->role, ['mahasiswa', 'dosen'], true), 403);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = $validated['limit'] ?? 30;

        $unreadCount = self::suggestionReplyUnreadCount($user);

        $rows = Suggestion::query()
            ->where('user_id', $user->id)
            ->whereNotNull('admin_replied_at')
            ->with('repliedBy:id,nama_lengkap')
            ->latest('admin_replied_at')
            ->limit($limit)
            ->get()
            ->map(fn (Suggestion $s) => [
                'id' => $s->id,
                'type' => 'suggestion_reply',
                'judul' => 'Balasan admin: ' . ($s->subjek ?: 'Saran & masukan Anda'),
                'isi' => $s->admin_reply,
                'pesan_asli' => $s->pesan,
                'subjek' => $s->subjek,
                'prioritas' => 'balasan',
                'penulis' => $s->repliedBy?->nama_lengkap ?? 'Admin laboran',
                'created_at' => optional($s->admin_replied_at)->toIso8601String(),
                'is_read' => $s->user_read_reply_at !== null,
            ]);

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'unread_count' => $unreadCount,
            'data' => $rows,
        ]);
    }

    public function markSuggestionReplyRead(Request $request, Suggestion $suggestion): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        abort_unless($user && (int) $suggestion->user_id === (int) $user->id, 403);
        abort_unless($suggestion->admin_replied_at, 404);

        if (! $suggestion->user_read_reply_at) {
            $suggestion->update(['user_read_reply_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Balasan admin ditandai sudah dibaca.',
        ]);
    }

    public function markAllSuggestionRepliesRead(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        abort_unless($user && in_array($user->role, ['mahasiswa', 'dosen'], true), 403);

        $marked = Suggestion::query()
            ->where('user_id', $user->id)
            ->whereNotNull('admin_replied_at')
            ->whereNull('user_read_reply_at')
            ->update(['user_read_reply_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Semua balasan admin ditandai sudah dibaca.',
            'marked' => $marked,
        ]);
    }

    private static function suggestionReplyUnreadCount(?\App\Models\User $user): int
    {
        if (! $user || ! in_array($user->role, ['mahasiswa', 'dosen'], true)) {
            return 0;
        }

        return Suggestion::query()
            ->where('user_id', $user->id)
            ->whereNotNull('admin_replied_at')
            ->whereNull('user_read_reply_at')
            ->count();
    }

    /**
     * Feed notifikasi gabungan (pengumuman + balasan admin) dengan paginasi per mahasiswa/dosen.
     */
    public function notificationsFeed(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        abort_unless($user, 401);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:30'],
            'unread_only' => ['nullable', 'boolean'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 12);
        $unreadOnly = $request->boolean('unread_only');

        $annUnread = Announcement::active()
            ->where('channel', '!=', Announcement::CHANNEL_NOTIFIKASI_LOKER)
            ->forAudience($user)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
        $replyUnread = self::suggestionReplyUnreadCount($user);

        $annQuery = Announcement::active()
            ->where('channel', '!=', Announcement::CHANNEL_NOTIFIKASI_LOKER)
            ->forAudience($user)
            ->with('author:id,nama_lengkap')
            ->withExists(['reads as is_read' => fn ($q) => $q->where('user_id', $user->id)]);

        if ($unreadOnly) {
            $annQuery->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id));
        }

        $annRows = $annQuery
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'type' => $a->isLockerNotification() ? 'locker_notification' : 'announcement',
                'judul' => $a->judul,
                'isi' => $a->isi,
                'target' => $a->target,
                'prioritas' => $a->prioritas,
                'expires_at' => optional($a->expires_at)->toIso8601String(),
                'penulis' => $a->author->nama_lengkap ?? null,
                'created_at' => optional($a->created_at)->toIso8601String(),
                'is_read' => (bool) $a->is_read,
                'sort_at' => $a->created_at?->timestamp ?? 0,
            ]);

        $replyRows = collect();
        if (in_array($user->role, ['mahasiswa', 'dosen'], true)) {
            $replyQuery = Suggestion::query()
                ->where('user_id', $user->id)
                ->whereNotNull('admin_replied_at')
                ->with('repliedBy:id,nama_lengkap');

            if ($unreadOnly) {
                $replyQuery->whereNull('user_read_reply_at');
            }

            $replyRows = $replyQuery
                ->get()
                ->map(fn (Suggestion $s) => [
                    'id' => $s->id,
                    'type' => 'suggestion_reply',
                    'judul' => 'Balasan admin: ' . ($s->subjek ?: 'Saran & masukan Anda'),
                    'isi' => $s->admin_reply,
                    'pesan_asli' => $s->pesan,
                    'subjek' => $s->subjek,
                    'prioritas' => 'balasan',
                    'penulis' => $s->repliedBy?->nama_lengkap ?? 'Admin laboran',
                    'created_at' => optional($s->admin_replied_at)->toIso8601String(),
                    'is_read' => $s->user_read_reply_at !== null,
                    'sort_at' => $s->admin_replied_at?->timestamp ?? 0,
                ]);
        }

        $merged = $annRows->concat($replyRows)
            ->sortByDesc('sort_at')
            ->values();

        $total = $merged->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $items = $merged
            ->forPage($page, $perPage)
            ->values()
            ->map(fn (array $row) => collect($row)->except('sort_at')->all());

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
            ],
            'unread_count' => $annUnread + $replyUnread,
            'announcement_unread_count' => $annUnread,
            'suggestion_reply_unread_count' => $replyUnread,
        ]);
    }

    public function markAnnouncementRead(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        $role = $user?->role === 'mahasiswa' ? 'mahasiswa' : 'dosen';

        $allowed = Announcement::active()
            ->forAudience($user)
            ->whereKey($announcement->id)
            ->exists();

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan atau tidak tersedia untuk akun ini.',
            ], 404);
        }

        AnnouncementRead::query()->updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            ['read_at' => now()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman ditandai sudah dibaca.',
        ]);
    }

    /**
     * Tandai semua pengumuman aktif yang relevan untuk pengguna sebagai sudah dibaca (satu tap di aplikasi).
     */
    public function markAnnouncementsReadAll(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');

        $ids = Announcement::active()
            ->forAudience($user)
            ->pluck('id');

        $now = now();
        foreach ($ids as $id) {
            AnnouncementRead::query()->updateOrCreate(
                [
                    'announcement_id' => $id,
                    'user_id' => $user->id,
                ],
                ['read_at' => $now]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Semua pengumuman ditandai sudah dibaca.',
            'marked' => $ids->count(),
        ]);
    }

    public function absensi(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $limit = $validated['limit'] ?? 50;
        $days = (int) ($validated['days'] ?? 365);

        $rows = LogAbsensi::query()
            ->where('user_id', $user->id)
            ->latest('waktu_tap')
            ->limit($limit)
            ->get()
            ->filter(function (LogAbsensi $log) {
                $tanggal = optional($log->waktu_tap)?->timezone('Asia/Jakarta')?->toDateString()
                    ?? optional($log->waktu_tap)?->toDateString();

                return $tanggal && AbsensiKebijakan::isHariTerjadwal($tanggal);
            })
            ->values()
            ->map(fn (LogAbsensi $log) => [
                'id' => $log->id,
                'id_mesin' => $log->id_mesin,
                'sesi' => $log->sesi,
                'jenis' => $log->jenis,
                'menit_terlambat' => $log->menit_terlambat,
                'slot_lab' => $log->slot_lab,
                'pulang_lembur' => (bool) $log->pulang_lembur,
                'waktu_tap' => optional($log->waktu_tap)->toDateTimeString(),
            ]);

        $harian = \App\Models\AbsensiHarian::query()
            ->with(['masukLog', 'pulangLog'])
            ->where('user_id', $user->id)
            ->where('tanggal', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('tanggal')
            ->get()
            // Hanya hari terjadwal (default: Kamis). Senin/Rabu/Sabtu tidak ditampilkan.
            ->filter(fn ($h) => AbsensiKebijakan::isHariTerjadwal($h->tanggal))
            ->map(function ($h) {
                $tz = 'Asia/Jakarta';
                $tanggalStr = $h->tanggal->toDateString();
                $jamMasuk = $h->masukLog?->waktu_tap?->timezone($tz)->format('H:i');
                $jamPulang = $h->pulangLog?->waktu_tap?->timezone($tz)->format('H:i');
                $jadwal = \App\Support\AbsensiEffectiveSchedule::resolve($tanggalStr, $h->sesi ?: 'pagi');
                $penilaian = \App\Support\AbsensiEffectiveSchedule::assessRecord($jadwal, [
                    'status' => $h->status,
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'menit_terlambat_masuk' => $h->menit_terlambat_masuk,
                    'menit_terlambat_pulang' => $h->menit_terlambat_pulang,
                    'menit_pulang_cepat' => $h->menit_pulang_cepat ?? 0,
                    'pulang_cepat' => (bool) ($h->pulang_cepat ?? false),
                    'pulang_lembur' => (bool) $h->pulang_lembur,
                ]);

                return [
                    'tanggal' => $tanggalStr,
                    'sesi' => $h->sesi,
                    'status' => $h->status,
                    'menit_terlambat_masuk' => $h->menit_terlambat_masuk,
                    'menit_terlambat_pulang' => $h->menit_terlambat_pulang,
                    'menit_pulang_cepat' => $penilaian['menit_pulang_cepat'],
                    'pulang_cepat' => $penilaian['ketepatan_pulang_kode'] === 'pulang_cepat',
                    'slot_lab_masuk' => $h->slot_lab_masuk,
                    'pulang_lembur' => (bool) $h->pulang_lembur,
                    'lengkap_at' => optional($h->lengkap_at)->toDateTimeString(),
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'jadwal_jam_masuk' => $jadwal['jam_masuk'],
                    'jadwal_jam_pulang' => $jadwal['jam_pulang'],
                    'jadwal_konsisten' => \App\Support\AbsensiEffectiveSchedule::scheduleConsistentWithRecord($jadwal, [
                        'jam_masuk' => $jamMasuk,
                        'menit_terlambat_masuk' => $h->menit_terlambat_masuk,
                    ]),
                    'kelengkapan_label' => $penilaian['kelengkapan_label'],
                    'ringkasan_ketepatan' => $penilaian['ringkasan_ketepatan'],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'data' => $rows,
            'ringkasan_harian' => $harian,
            'shift' => $user->shift,
            'kebijakan' => [
                'mode' => AbsensiKebijakan::mode(),
                'hari_wajib' => AbsensiKebijakan::config()['hari_wajib'] ?? [4],
                'label' => AbsensiKebijakan::labelMode(),
            ],
        ]);
    }

    public function absensiSnapshot(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        abort_unless($user, 401);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $limit = $validated['limit'] ?? 50;
        $days = (int) ($validated['days'] ?? 365);
        $rows = LogAbsensi::query()
            ->where('user_id', $user->id)
            ->latest('waktu_tap')
            ->limit($limit)
            ->get()
            ->filter(function (LogAbsensi $log) {
                $tanggal = optional($log->waktu_tap)?->timezone('Asia/Jakarta')?->toDateString()
                    ?? optional($log->waktu_tap)?->toDateString();

                return $tanggal && AbsensiKebijakan::isHariTerjadwal($tanggal);
            })
            ->values()
            ->map(fn (LogAbsensi $log) => [
                'id' => $log->id,
                'id_mesin' => $log->id_mesin,
                'sesi' => $log->sesi,
                'jenis' => $log->jenis,
                'menit_terlambat' => $log->menit_terlambat,
                'slot_lab' => $log->slot_lab,
                'pulang_lembur' => (bool) $log->pulang_lembur,
                'waktu_tap' => optional($log->waktu_tap)->toDateTimeString(),
            ]);
        $harian = AbsensiHarian::query()
            ->with(['masukLog', 'pulangLog'])
            ->where('user_id', $user->id)
            ->where('tanggal', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('tanggal')
            ->get()
            ->filter(fn ($h) => AbsensiKebijakan::isHariTerjadwal($h->tanggal))
            ->map(function ($h) {
                $tz = 'Asia/Jakarta';
                $tanggalStr = $h->tanggal->toDateString();
                $jamMasuk = $h->masukLog?->waktu_tap?->timezone($tz)->format('H:i');
                $jamPulang = $h->pulangLog?->waktu_tap?->timezone($tz)->format('H:i');
                $jadwal = \App\Support\AbsensiEffectiveSchedule::resolve($tanggalStr, $h->sesi ?: 'pagi');
                $penilaian = \App\Support\AbsensiEffectiveSchedule::assessRecord($jadwal, [
                    'status' => $h->status,
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'menit_terlambat_masuk' => $h->menit_terlambat_masuk,
                    'menit_terlambat_pulang' => $h->menit_terlambat_pulang,
                    'menit_pulang_cepat' => $h->menit_pulang_cepat ?? 0,
                    'pulang_cepat' => (bool) ($h->pulang_cepat ?? false),
                    'pulang_lembur' => (bool) $h->pulang_lembur,
                ]);

                return [
                    'tanggal' => $tanggalStr,
                    'sesi' => $h->sesi,
                    'status' => $h->status,
                    'menit_terlambat_masuk' => $h->menit_terlambat_masuk,
                    'menit_terlambat_pulang' => $h->menit_terlambat_pulang,
                    'menit_pulang_cepat' => $penilaian['menit_pulang_cepat'],
                    'pulang_cepat' => $penilaian['ketepatan_pulang_kode'] === 'pulang_cepat',
                    'slot_lab_masuk' => $h->slot_lab_masuk,
                    'pulang_lembur' => (bool) $h->pulang_lembur,
                    'lengkap_at' => optional($h->lengkap_at)->toDateTimeString(),
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'jadwal_jam_masuk' => $jadwal['jam_masuk'],
                    'jadwal_jam_pulang' => $jadwal['jam_pulang'],
                    'jadwal_konsisten' => \App\Support\AbsensiEffectiveSchedule::scheduleConsistentWithRecord($jadwal, [
                        'jam_masuk' => $jamMasuk,
                        'menit_terlambat_masuk' => $h->menit_terlambat_masuk,
                    ]),
                    'kelengkapan_label' => $penilaian['kelengkapan_label'],
                    'ringkasan_ketepatan' => $penilaian['ringkasan_ketepatan'],
                ];
            })
            ->values();

        return response()->json(AbsensiSnapshot::envelope(
            'absensi',
            $user->role === 'mahasiswa' ? 'mahasiswa' : 'diri',
            [
                'query' => AbsensiSnapshot::canonicalQuery($request),
                'revision' => substr(hash('sha256', json_encode([$rows, $harian])), 0, 16),
                'success' => true,
                'count' => $rows->count(),
                'data' => $rows,
                'ringkasan_harian' => $harian,
                'shift' => $user->shift,
                'kebijakan' => [
                    'mode' => AbsensiKebijakan::mode(),
                    'hari_wajib' => AbsensiKebijakan::config()['hari_wajib'] ?? [4],
                    'label' => AbsensiKebijakan::labelMode(),
                ],
            ]
        ));
    }










    public function storeSuggestion(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        abort_unless($user && $user->role === 'mahasiswa', 403);

        $validated = $request->validate([
            'subjek' => ['nullable', 'string', 'max:180'],
            'pesan'  => ['required', 'string', 'min:10', 'max:4000'],
        ], [
            'pesan.required' => 'Pesan wajib diisi.',
            'pesan.min'      => 'Pesan terlalu singkat (minimal 10 karakter).',
        ]);

        if ($limitMsg = SuggestionSubmissionLimiter::checkAccount($user->id, $validated['pesan'])) {
            return response()->json([
                'success' => false,
                'message' => $limitMsg,
            ], 422);
        }

        SuggestionSubmissionLimiter::recordAccountHit($user->id);

        Suggestion::query()->create([
            'user_id'    => $user->id,
            'source'     => 'mahasiswa',
            'nama'       => $user->nama_lengkap,
            'email'      => $user->email,
            'subjek'     => $validated['subjek'] ?? null,
            'pesan'      => $validated['pesan'],
            'status'     => 'baru',
            'ip'         => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Saran Anda sudah diterima admin laboran.',
        ]);
    }

}
