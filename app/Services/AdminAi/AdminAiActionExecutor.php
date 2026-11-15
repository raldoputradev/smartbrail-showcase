<?php

namespace App\Services\AdminAi;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiTidakLengkap;
use App\Models\AccountApprovalLog;
use App\Models\AiActionProposal;
use App\Models\Announcement;
use App\Models\MesinIot;
use App\Models\Suggestion;
use App\Models\User;
use App\Services\AccountApprovalNotifier;
use App\Support\LoginSecurityService;
use App\Support\PendingAccountApproval;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAiActionExecutor
{
    /**
     * @return array{ok: bool, message: string, result?: array<string, mixed>}
     */
    public function confirm(AiActionProposal $proposal, User $admin, Request $request): array
    {
        $proposal->markExpiredIfNeeded();
        $proposal->refresh();

        if ($proposal->status !== 'pending') {
            return ['ok' => false, 'message' => 'Proposal tidak lagi pending (status: '.$proposal->status.').'];
        }
        if ($proposal->expires_at->isPast()) {
            $proposal->update(['status' => 'expired']);

            return ['ok' => false, 'message' => 'Proposal sudah kedaluwarsa. Minta AI buat ulang.'];
        }
        if ((int) $proposal->requested_by !== (int) $admin->id) {
            return ['ok' => false, 'message' => 'Hanya pemohon proposal yang bisa mengonfirmasi.'];
        }
        if (! PermissionRegistry::can($admin->role, $proposal->perm_menu, $proposal->perm_action)) {
            return ['ok' => false, 'message' => 'Izin RBAC tidak mencukupi untuk aksi ini.'];
        }

        try {
            $result = DB::transaction(function () use ($proposal, $admin, $request) {
                return match ($proposal->tool) {
                    'approve_user' => $this->execApprove($proposal, $admin, $request),
                    'reject_user' => $this->execReject($proposal, $admin, $request),
                    'bulk_approve_ready' => $this->execBulkApprove($proposal, $admin, $request),
                    'reply_suggestion' => $this->execReplySuggestion($proposal, $admin),
                    'create_announcement' => $this->execAnnouncement($proposal, $admin),
                    'toggle_mesin' => $this->execToggleMesin($proposal),
                    'unlock_user_login' => $this->execUnlock($proposal, $admin, $request),
                    'fingerprint_sync_burst' => $this->execFingerprintBurst(),
                    'toggle_user_active' => $this->execToggleUserActive($proposal, $admin),
                    'toggle_announcement' => $this->execToggleAnnouncement($proposal),
                    'koreksi_absensi_harian' => $this->execKoreksiAbsensi($proposal, $admin),
                    'batalkan_alfa' => $this->execBatalkanAlfa($proposal, $admin),
                    'selesai_tidak_lengkap' => $this->execSelesaiTidakLengkap($proposal, $admin),
                    'draft_pengumuman' => $this->execAnnouncement($proposal, $admin),
                    'create_ruangan' => $this->execCreateRuangan($proposal),
                    default => throw new \RuntimeException('Tool eksekusi tidak didukung: '.$proposal->tool),
                };
            });

            $proposal->update([
                'status' => 'confirmed',
                'executed_at' => now(),
                'result' => $result,
            ]);

            return ['ok' => true, 'message' => (string) ($result['message'] ?? 'Aksi berhasil.'), 'result' => $result];
        } catch (\Throwable $e) {
            $proposal->update([
                'status' => 'failed',
                'executed_at' => now(),
                'result' => ['error' => $e->getMessage()],
            ]);

            return ['ok' => false, 'message' => 'Gagal eksekusi: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function cancel(AiActionProposal $proposal, User $admin): array
    {
        if ((int) $proposal->requested_by !== (int) $admin->id) {
            return ['ok' => false, 'message' => 'Hanya pemohon yang bisa membatalkan.'];
        }
        if ($proposal->status !== 'pending') {
            return ['ok' => false, 'message' => 'Proposal tidak pending.'];
        }
        $proposal->update([
            'status' => 'cancelled',
            'result' => ['cancelled_at' => now()->toDateTimeString()],
        ]);

        return ['ok' => true, 'message' => 'Proposal dibatalkan.'];
    }

    /** @return array<string, mixed> */
    private function execCreateRuangan(AiActionProposal $proposal): array
    {
        $p = $proposal->payload;
        $kode = trim((string) ($p['kode'] ?? ''));
        if ($kode === '') {
            $kode = 'R-'.str_pad((string) ((\App\Models\Ruangan::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
            while (\App\Models\Ruangan::query()->where('kode', $kode)->exists()) {
                $kode = 'R-'.str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
            }
        }
        $r = \App\Models\Ruangan::query()->create([
            'nama' => (string) $p['nama'],
            'kode' => $kode,
            'kapasitas' => isset($p['kapasitas']) ? (int) $p['kapasitas'] : null,
            'fasilitas' => $p['fasilitas'] ?? null,
            'aktif' => true,
        ]);

        return ['message' => "Ruangan {$r->nama} ({$r->kode}) terdaftar.", 'ruangan_id' => $r->id];
    }

    /** @return array<string, mixed> */
    private function execApprove(AiActionProposal $proposal, User $admin, Request $request): array
    {
        $userId = (int) ($proposal->payload['user_id'] ?? 0);
        $user = User::query()->findOrFail($userId);
        if ($admin->id === $user->id) {
            throw new \RuntimeException('Tidak bisa approve akun sendiri.');
        }
        if (! PendingAccountApproval::query()->where('id', $user->id)->exists()) {
            throw new \RuntimeException('User tidak lagi siap approve.');
        }

        $user->update([
            'is_active' => true,
            'approval_status' => 'approved',
            'approval_note' => null,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'email_verified_at' => filled($user->email) ? ($user->email_verified_at ?? now()) : null,
        ]);

        $this->logApproval($request, $user, $admin, 'approved', 'Disetujui via Asisten AI (HITL).');
        app(AccountApprovalNotifier::class)->notifyApproved($user->fresh(), $admin);

        return ['message' => "Akun {$user->nama_lengkap} disetujui.", 'user_id' => $user->id];
    }

    /** @return array<string, mixed> */
    private function execReject(AiActionProposal $proposal, User $admin, Request $request): array
    {
        $userId = (int) ($proposal->payload['user_id'] ?? 0);
        $note = (string) ($proposal->payload['approval_note'] ?? '');
        $user = User::query()->findOrFail($userId);
        $status = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');
        if ($status === 'approved' || $status === 'rejected') {
            throw new \RuntimeException('Status akun tidak valid untuk ditolak.');
        }

        $user->update([
            'is_active' => false,
            'approval_status' => 'rejected',
            'approval_note' => $note,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => now(),
        ]);

        $this->logApproval($request, $user, $admin, 'rejected', $note.' (via Asisten AI)');
        app(AccountApprovalNotifier::class)->notifyRejected($user->fresh(), $admin, $note);

        return ['message' => "Akun {$user->nama_lengkap} ditolak.", 'user_id' => $user->id];
    }

    /** @return array<string, mixed> */
    private function execBulkApprove(AiActionProposal $proposal, User $admin, Request $request): array
    {
        $ids = array_map('intval', (array) ($proposal->payload['user_ids'] ?? []));
        $readyIds = PendingAccountApproval::query()->whereIn('id', $ids)->pluck('id');
        if ($readyIds->isEmpty()) {
            throw new \RuntimeException('Tidak ada akun siap approve.');
        }

        $notifier = app(AccountApprovalNotifier::class);
        $approved = 0;
        foreach (User::query()->whereIn('id', $readyIds)->get() as $user) {
            if ($admin->id === $user->id) {
                continue;
            }
            $user->update([
                'is_active' => true,
                'approval_status' => 'approved',
                'approval_note' => null,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'email_verified_at' => filled($user->email) ? ($user->email_verified_at ?? now()) : null,
            ]);
            $this->logApproval($request, $user, $admin, 'approved', 'Bulk approve via Asisten AI (HITL).');
            $notifier->notifyApproved($user->fresh(), $admin);
            $approved++;
        }

        return ['message' => "{$approved} akun disetujui.", 'count' => $approved];
    }

    /** @return array<string, mixed> */
    private function execReplySuggestion(AiActionProposal $proposal, User $admin): array
    {
        $id = (int) ($proposal->payload['suggestion_id'] ?? 0);
        $text = (string) ($proposal->payload['admin_reply'] ?? '');
        $suggestion = Suggestion::query()->findOrFail($id);
        $suggestion->update([
            'admin_reply' => $text,
            'admin_replied_at' => now(),
            'admin_replied_by' => $admin->id,
            'user_read_reply_at' => null,
            'status' => 'ditanggapi',
        ]);

        return ['message' => 'Balasan saran #'.$id.' tersimpan.', 'suggestion_id' => $id];
    }

    /** @return array<string, mixed> */
    private function execAnnouncement(AiActionProposal $proposal, User $admin): array
    {
        $p = $proposal->payload;
        $publish = (($p['action'] ?? 'draft') === 'publish');
        $row = Announcement::create([
            'judul' => $p['judul'],
            'isi' => $p['isi'],
            'target' => $p['target'] ?? 'semua',
            'channel' => Announcement::CHANNEL_PENGUMUMAN,
            'prioritas' => $p['prioritas'] ?? 'normal',
            'expires_at' => null,
            'is_active' => $publish,
            'dibuat_oleh' => $admin->id,
        ]);

        return [
            'message' => $publish ? 'Pengumuman dipublikasikan.' : 'Draft pengumuman disimpan.',
            'announcement_id' => $row->id,
        ];
    }

    /** @return array<string, mixed> */
    private function execToggleMesin(AiActionProposal $proposal): array
    {
        $id = (int) ($proposal->payload['mesin_id'] ?? 0);
        $setActive = (bool) ($proposal->payload['set_active'] ?? false);
        $mesin = MesinIot::query()->findOrFail($id);
        $mesin->update(['is_active' => $setActive]);

        return [
            'message' => ($setActive ? 'Mesin diaktifkan: ' : 'Mesin dinonaktifkan: ').$mesin->nama_mesin,
            'mesin_id' => $mesin->id,
            'is_active' => $setActive,
        ];
    }

    /** @return array<string, mixed> */
    private function execUnlock(AiActionProposal $proposal, User $admin, Request $request): array
    {
        $user = User::query()->findOrFail((int) ($proposal->payload['user_id'] ?? 0));
        if ($admin->id === $user->id) {
            throw new \RuntimeException('Tidak bisa unlock akun sendiri dari sini.');
        }
        app(LoginSecurityService::class)->adminUnlock($user, $admin, $request);

        return ['message' => "Kunci login {$user->nama_lengkap} dibuka.", 'user_id' => $user->id];
    }

    /** @return array<string, mixed> */
    private function execFingerprintBurst(): array
    {
        \App\Support\FingerprintSyncPolicy::triggerBurst(20);

        return [
            'message' => 'Distribusi template fingerprint diaktifkan 20 menit.',
        ];
    }

    /** @return array<string, mixed> */
    private function execToggleUserActive(AiActionProposal $proposal, User $admin): array
    {
        $user = User::query()->findOrFail((int) ($proposal->payload['user_id'] ?? 0));
        $set = (bool) ($proposal->payload['set_active'] ?? false);
        if ($admin->id === $user->id) {
            throw new \RuntimeException('Tidak bisa mengubah status aktif akun sendiri.');
        }
        $user->update([
            'is_active' => $set,
            'approval_status' => $set ? 'approved' : ($user->approval_status ?? 'pending'),
            'approved_by' => $set ? $admin->id : $user->approved_by,
            'approved_at' => $set ? ($user->approved_at ?? now()) : $user->approved_at,
        ]);

        return [
            'message' => ($set ? 'Akun diaktifkan: ' : 'Akun dinonaktifkan: ').$user->nama_lengkap,
            'user_id' => $user->id,
            'is_active' => $set,
        ];
    }

    /** @return array<string, mixed> */
    private function execToggleAnnouncement(AiActionProposal $proposal): array
    {
        $row = Announcement::query()->findOrFail((int) ($proposal->payload['announcement_id'] ?? 0));
        $set = array_key_exists('set_active', $proposal->payload)
            ? (bool) $proposal->payload['set_active']
            : ! (bool) $row->is_active;
        $row->update(['is_active' => $set]);

        return [
            'message' => ($set ? 'Pengumuman dipublish: ' : 'Pengumuman di-unpublish: ').$row->judul,
            'announcement_id' => $row->id,
            'is_active' => $set,
        ];
    }

    /** @return array<string, mixed> */
    private function execKoreksiAbsensi(AiActionProposal $proposal, User $admin): array
    {
        if (! $admin->isLaboran()) {
            throw new \RuntimeException('Hanya laboran yang boleh koreksi absensi.');
        }
        $harian = AbsensiHarian::query()->with('user:id,nama_lengkap')->findOrFail((int) ($proposal->payload['harian_id'] ?? 0));
        $status = (string) ($proposal->payload['status'] ?? '');
        if (! in_array($status, ['lengkap', 'tidak_lengkap', 'alfa', 'masuk', 'belum'], true)) {
            throw new \RuntimeException('Status koreksi tidak valid.');
        }
        $harian->update([
            'status' => $status,
            'catatan_koreksi' => $proposal->payload['catatan_koreksi'] ?? null,
            'koreksi_oleh' => $admin->id,
            'koreksi_at' => now(),
        ]);
        AdminAiExtendedOps::syncAbsensiAfterKoreksi($harian->fresh(), $status, $admin->id);
        $nama = $harian->user->nama_lengkap ?? '#'.$harian->user_id;

        return [
            'message' => "Koreksi absensi {$nama} disimpan → {$status}.",
            'harian_id' => $harian->id,
            'status' => $status,
        ];
    }

    /** @return array<string, mixed> */
    private function execBatalkanAlfa(AiActionProposal $proposal, User $admin): array
    {
        if (! $admin->isLaboran()) {
            throw new \RuntimeException('Hanya laboran yang boleh batalkan alfa.');
        }
        $alfa = AbsensiAlfa::query()->with('user:id,nama_lengkap')->findOrFail((int) ($proposal->payload['alfa_id'] ?? 0));
        if (! $alfa->aktif) {
            throw new \RuntimeException('Alfa sudah tidak aktif.');
        }
        $alfa->update([
            'aktif' => false,
            'catatan' => $proposal->payload['catatan'] ?? $alfa->catatan,
            'dibatalkan_oleh' => $admin->id,
            'dibatalkan_at' => now(),
        ]);
        AbsensiHarian::query()
            ->where('user_id', $alfa->user_id)
            ->whereDate('tanggal', $alfa->tanggal)
            ->where('sesi', $alfa->sesi)
            ->update([
                'status' => 'belum',
                'koreksi_oleh' => $admin->id,
                'koreksi_at' => now(),
            ]);
        $nama = $alfa->user->nama_lengkap ?? '#'.$alfa->user_id;

        return [
            'message' => "Alfa {$nama} dibatalkan.",
            'alfa_id' => $alfa->id,
        ];
    }

    /** @return array<string, mixed> */
    private function execSelesaiTidakLengkap(AiActionProposal $proposal, User $admin): array
    {
        if (! $admin->isLaboran()) {
            throw new \RuntimeException('Hanya laboran yang boleh menyelesaikan tidak lengkap.');
        }
        $harian = AbsensiHarian::query()->with('user:id,nama_lengkap')->findOrFail((int) ($proposal->payload['harian_id'] ?? 0));
        if ($harian->status !== 'tidak_lengkap') {
            throw new \RuntimeException('Status harian bukan tidak_lengkap.');
        }
        AbsensiTidakLengkap::query()->updateOrCreate(
            [
                'user_id' => $harian->user_id,
                'tanggal' => $harian->tanggal,
                'sesi' => $harian->sesi,
                'jenis' => 'tanpa_pulang',
            ],
            [
                'selesai' => true,
                'catatan_koreksi' => $proposal->payload['catatan_koreksi'] ?? null,
                'ditangani_oleh' => $admin->id,
                'ditangani_at' => now(),
            ]
        );
        $nama = $harian->user->nama_lengkap ?? '#'.$harian->user_id;

        return [
            'message' => "Kasus tidak lengkap {$nama} ditandai selesai.",
            'harian_id' => $harian->id,
        ];
    }


    private function logApproval(Request $request, User $user, User $admin, string $action, string $note): void
    {
        AccountApprovalLog::query()->create([
            'user_id' => $user->id,
            'subject_name' => $user->nama_lengkap,
            'subject_email' => $user->email,
            'subject_nitk_nidn' => $user->nitk_nidn,
            'subject_role' => $user->role,
            'subject_meta' => [
                'finger_id' => $user->finger_id,
                'kelas' => $user->kelas,
                'tim' => $user->tim,
            ],
            'actor_id' => $admin->id,
            'action' => $action,
            'note' => $note,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
