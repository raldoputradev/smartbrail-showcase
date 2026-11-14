<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MesinIot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MesinHeartbeatController extends Controller
{
    /**
     * Sinyal hidup berkala dari ESP32 (standby, tanpa tap).
     * Dipakai monitoring admin agar status "online" tidak mati saat tidak ada absen.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var MesinIot $mesin */
        $mesin = $request->attributes->get('mesin_iot');

        $validated = $request->validate([
            'id_mesin' => ['nullable', 'string', 'max:100'],
        ]);

        if (!empty($validated['id_mesin']) && $mesin->id_mesin !== $validated['id_mesin']) {
            return response()->json([
                'success' => false,
                'message' => 'id_mesin tidak cocok dengan mesin autentikasi.',
            ], 422);
        }

        $mesin->touchHeartbeat();

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat diterima.',
            'data' => [
                'id_mesin' => $mesin->id_mesin,
                'tipe' => $mesin->tipe,
                'last_seen_at' => $mesin->fresh()->last_seen_at?->toDateTimeString(),
                'online_threshold_minutes' => MesinIot::ONLINE_THRESHOLD_MINUTES,
            ],
        ]);
    }
}
