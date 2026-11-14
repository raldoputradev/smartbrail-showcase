<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Support\AbsensiTapIdempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MesinAbsensiController extends Controller
{
    /**
     * Endpoint untuk ESP32: simpan event tap sidik jari.
     * event_id membuat keputusan pertama dapat diulang. Tanpa event_id, firmware lama tetap diterima
     * dan jalur itu belum idempoten.
     */
    public function tap(Request $request, AbsensiTapIdempotency $taps): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        return $taps->handle($request, $mesin);
    }

    /**
     * Endpoint untuk Flutter: ambil log absensi terbaru dengan data user.
     */
    public function mobileLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = $validated['limit'] ?? 50;

        $logs = LogAbsensi::query()
            ->with(['user:id,nama_lengkap,nitk_nidn'])
            ->latest('waktu_tap')
            ->limit($limit)
            ->get()
            ->map(function (LogAbsensi $log) {
                return [
                    'id' => $log->id,
                    'id_mesin' => $log->id_mesin,
                    'sesi' => $log->sesi,
                    'jenis' => $log->jenis,
                    'menit_terlambat' => $log->menit_terlambat,
                    'waktu_tap' => optional($log->waktu_tap)->toDateTimeString(),
                    'user' => [
                        'id' => $log->user?->id,
                        'nama_lengkap' => $log->user?->nama_lengkap,
                        'nitk_nidn' => $log->user?->nitk_nidn,
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ], 200);
    }
}
