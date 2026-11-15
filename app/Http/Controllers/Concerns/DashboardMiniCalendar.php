<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mini-kalender agenda satu bulan untuk dashboard React — sumber bersama
 * `absensi_kalender_libur` (sama dengan halaman Kalender & Agenda).
 * Dipakai AdminController dan DosenController.
 */
trait DashboardMiniCalendar
{
    /** JSON: navigasi bulan pada mini-kalender dashboard tanpa reload. */
    public function dashboardMiniCalJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        return response()->json($this->dashboardMiniCalendar($validated['bulan'] ?? null));
    }

    /** Agenda kalender terdekat — sumber sama dengan halaman Kalender & Agenda. */
    private function dashboardAgendaTerdekat(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('absensi_kalender_libur')) {
            return [];
        }
        $today = now('Asia/Jakarta')->toDateString();
        $labels = [
            'libur' => 'Hari Libur', 'peringatan' => 'Peringatan', 'agenda' => 'Agenda',
            'ujian' => 'Ujian', 'perubahan_jam' => 'Jam Khusus', 'informasi' => 'Informasi',
        ];

        return \App\Models\AbsensiKalenderLibur::query()
            ->where(fn ($q) => $q->whereDate('tanggal_selesai', '>=', $today)->orWhereDate('tanggal', '>=', $today))
            ->orderBy('tanggal')->limit(6)->get()
            ->map(function ($e) use ($labels, $today) {
                $jenis = $e->effectiveJenis();
                $same = ! $e->tanggal_selesai || $e->tanggal_selesai->toDateString() === $e->tanggal?->toDateString();
                return [
                    'id' => (int) $e->id,
                    'jenis' => $jenis,
                    'jenis_label' => $labels[$jenis] ?? ucfirst($jenis),
                    'keterangan' => $e->keterangan,
                    'rentang' => $e->tanggal?->translatedFormat('d M') . ($same ? '' : ' – ' . $e->tanggal_selesai->translatedFormat('d M')),
                    'hari_ini' => $e->coversDate(\Illuminate\Support\Carbon::parse($today)),
                ];
            })->all();
    }

    /** Mini kalender bulan berjalan — sel tanggal bertanda agenda pusat (sama sumber dengan Kalender & Agenda). */
    private function dashboardMiniCalendar(?string $bulan = null): array
    {
        $now = now('Asia/Jakarta');
        $view = $now->copy();
        if (is_string($bulan) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            $view = Carbon::createFromFormat('!Y-m', $bulan, 'Asia/Jakarta') ?: $now->copy();
        }
        $first = $view->copy()->startOfMonth();
        $end = $view->copy()->endOfMonth();

        $marks = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('absensi_kalender_libur')) {
            \App\Models\AbsensiKalenderLibur::query()
                ->whereDate('tanggal', '<=', $end->toDateString())
                ->where(function ($q) use ($first) {
                    $q->whereDate('tanggal_selesai', '>=', $first->toDateString())
                        ->orWhere(function ($q2) use ($first) {
                            $q2->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $first->toDateString());
                        });
                })
                ->get()
                ->each(function ($e) use (&$marks, $first, $end) {
                    $start = $e->tanggal?->copy()->max($first);
                    $finish = ($e->tanggal_selesai ?? $e->tanggal)?->copy()->min($end);
                    if (! $start || ! $finish) {
                        return;
                    }
                    for ($t = $start->copy(); $t->lte($finish); $t->addDay()) {
                        $marks[$t->toDateString()][] = [
                            'jenis' => $e->effectiveJenis(),
                            'keterangan' => $e->keterangan,
                        ];
                    }
                });
        }

        $days = [];
        for ($i = 1; $i <= $view->daysInMonth; $i++) {
            $t = $first->copy()->day($i);
            $ds = $t->toDateString();
            $days[] = [
                'd' => $i,
                'tanggal' => $ds,
                'today' => $ds === $now->toDateString(),
                'agenda' => $marks[$ds] ?? [],
            ];
        }

        return [
            'bulan' => $view->format('Y-m'),
            'bulan_label' => $view->translatedFormat('F Y'),
            'offset' => $first->dayOfWeekIso - 1, // Senin = 0
            'days' => $days,
        ];
    }
}
