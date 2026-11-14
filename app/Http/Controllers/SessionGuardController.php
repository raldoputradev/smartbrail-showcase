<?php

namespace App\Http\Controllers;

use App\Support\WebSessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionGuardController extends Controller
{
    /**
     * Keep-alive + cek sesi masih valid (dipakai guard idle browser).
     */
    public function ping(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid.',
                'code' => 'SESSION_EXPIRED',
            ], 401);
        }

        if (WebSessionGuard::isAbsoluteExpired($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi login sudah berakhir.',
                'code' => 'SESSION_EXPIRED',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => WebSessionGuard::pingPayload($request),
        ]);
    }
}
