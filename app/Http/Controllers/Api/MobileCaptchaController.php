<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MobileImageCaptcha;
use Illuminate\Http\JsonResponse;

class MobileCaptchaController extends Controller
{
    public function issue(): JsonResponse
    {
        if (!function_exists('imagecreatetruecolor')) {
            return response()->json([
                'success' => false,
                'message' => 'CAPTCHA gambar tidak tersedia di server (GD extension).',
            ], 503)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        $payload = MobileImageCaptcha::issue();

        return response()->json([
            'success' => true,
            'data' => $payload,
        ])->withHeaders([
            // Hindari CDN/proxy/browser menyajikan gambar+token lama (penyebab "sudah kedaluwarsa").
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
