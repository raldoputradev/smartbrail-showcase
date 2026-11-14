<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\MobileAuthController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan. Sertakan header Authorization: Bearer <token>.',
            ], 401);
        }

        $hashed = hash('sha256', $token);

        $user = User::query()
            ->where('mobile_api_token', $hashed)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid.',
            ], 401);
        }

        if ($user->mobile_token_expires_at && $user->mobile_token_expires_at->isPast()) {
            $user->forceFill([
                'mobile_api_token' => null,
                'mobile_token_expires_at' => null,
            ])->save();

            return response()->json([
                'success' => false,
                'message' => 'Sesi berakhir karena tidak ada aktivitas. Silakan login ulang.',
                'code' => 'SESSION_IDLE_EXPIRED',
            ], 401);
        }

        $user->forceFill([
            'mobile_token_expires_at' => now()->addMinutes(MobileAuthController::TOKEN_IDLE_MINUTES),
        ])->save();

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun belum aktif/diverifikasi.',
            ], 403);
        }

        $request->attributes->set('mobile_user', $user);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            $token = trim(substr($header, 7));
            return $token !== '' ? $token : null;
        }

        $queryToken = $request->query('token');
        return $queryToken ? (string) $queryToken : null;
    }
}
