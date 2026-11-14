<?php

namespace App\Support;

use App\Models\AccountApprovalLog;
use App\Models\PblProject;
use App\Models\User;
use Illuminate\Support\Collection;

class AccountOrigin
{
    /**
     * @return array{label: string, detail: string, action: string|null, at: \Carbon\Carbon|null}
     */
    public static function resolve(User $user): array
    {
        $log = $user->approvalLogs()
            ->with('actor:id,nama_lengkap,role')
            ->whereIn('action', ['registered', 'admin_created'])
            ->oldest()
            ->first();

        if (!$log) {
            return [
                'label' => 'Tidak tercatat',
                'detail' => 'Belum ada log asal pendaftaran.',
                'action' => null,
                'at' => $user->created_at,
            ];
        }

        $label = match ($log->action) {
            'registered' => 'Pendaftaran mandiri',
            'admin_created' => 'Dibuat oleh admin/laboran',
            default => ucfirst(str_replace('_', ' ', $log->action)),
        };

        $actor = $log->actor?->nama_lengkap;
        $detail = $log->note ?: match ($log->action) {
            'registered' => 'Form registrasi publik SIMALAS (role dosen).',
            'admin_created' => $actor
                ? "Akun dibuat manual oleh {$actor}."
                : 'Akun dibuat manual dari panel admin.',
            default => '',
        };

        return [
            'label' => $label,
            'detail' => $detail,
            'action' => $log->action,
            'at' => $log->created_at,
        ];
    }

    /**
     * @return array{status: string, label: string, tone: string}
     */
    public static function approvalBadge(User $user): array
    {
        $status = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');

        return match ($status) {
            'approved' => ['status' => 'approved', 'label' => 'Disetujui', 'tone' => 'success'],
            'rejected' => ['status' => 'rejected', 'label' => 'Ditolak', 'tone' => 'danger'],
            AccountActivation::STATUS => ['status' => AccountActivation::STATUS, 'label' => 'Menunggu aktivasi', 'tone' => 'info'],
            default => ['status' => 'pending', 'label' => 'Menunggu persetujuan', 'tone' => 'warning'],
        };
    }

    /**
     * @return Collection<int, PblProject>
     */
    public static function pblProjectsForDosen(User $dosen): Collection
    {
        if (!$dosen->isDosen()) {
            return collect();
        }

        return PblProject::query()
            ->where('aktif', true)
            ->where(function ($q) use ($dosen) {
                $q->where('manpro_user_id', $dosen->id)
                    ->orWhere('co_manpro_user_id', $dosen->id);
            })
            ->orderBy('nama')
            ->get();
    }
}
