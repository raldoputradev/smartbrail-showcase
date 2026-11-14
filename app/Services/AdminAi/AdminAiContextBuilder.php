<?php

namespace App\Services\AdminAi;

use App\Models\MesinIot;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\PblDataReadiness;
use App\Support\PendingAccountApproval;
use Illuminate\Support\Facades\DB;

final class AdminAiContextBuilder
{
    /**
     * Snapshot ringkas untuk system prompt (capped).
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $mesinTotal = MesinIot::count();
        $mesinAktif = MesinIot::where('is_active', true)->count();
        $mesinOnline = MesinIot::online()->count();

        $enrollBelum = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => ! $u->hasFingerprintEnrolled())
            ->count();

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $tapWeek = (int) DB::table('log_absensi')
            ->whereBetween('waktu_tap', [$weekStart, $weekEnd])
            ->count();
        $pbl = PblDataReadiness::report();

        return [
            'generated_at' => now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
            'domain_rules' => [
                'absensi' => 'Khusus kehadiran lab; ada batas sesi/jadwal (alfa, tidak lengkap).',
            ],
            'ops' => [
                'pending_approval' => PendingAccountApproval::count(),
                'suggestions_baru' => Suggestion::query()->where('status', 'baru')->count(),
                'enroll_belum' => $enrollBelum,
                'pbl_ok' => (bool) ($pbl['ok'] ?? false),
            ],
            'mesin' => [
                'total' => $mesinTotal,
                'aktif' => $mesinAktif,
                'online' => $mesinOnline,
                'offline' => max(0, $mesinAktif - $mesinOnline),
            ],
            'minggu_ini' => [
                'tap_absensi' => $tapWeek,
            ],
        ];
    }
}
