<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha
{
    public static function isConfigured(): bool
    {
        return (bool) config('services.recaptcha.site_key')
            && (bool) config('services.recaptcha.secret_key');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function verify(?string $response, ?string $remoteIp = null): array
    {
        $secret = config('services.recaptcha.secret_key');

        if (!$secret || !config('services.recaptcha.site_key')) {
            return [
                'ok' => false,
                'message' => 'reCAPTCHA belum dikonfigurasi. Hubungi administrator.',
            ];
        }

        if (!$response) {
            return [
                'ok' => false,
                'message' => 'Centang "Saya bukan robot" terlebih dahulu.',
            ];
        }

        try {
            $verify = Http::asForm()
                ->timeout(10)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $response,
                    'remoteip' => $remoteIp,
                ]);
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA siteverify gagal: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => 'Verifikasi reCAPTCHA tidak dapat dihubungi. Coba lagi.',
            ];
        }

        if (!$verify->ok()) {
            return [
                'ok' => false,
                'message' => 'Verifikasi reCAPTCHA gagal. Coba lagi.',
            ];
        }

        $body = $verify->json();
        if (!($body['success'] ?? false)) {
            $codes = $body['error-codes'] ?? [];

            return [
                'ok' => false,
                'message' => self::messageForErrorCodes($codes),
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * @param  array<int, string>  $codes
     */
    private static function messageForErrorCodes(array $codes): string
    {
        if (in_array('timeout-or-duplicate', $codes, true)) {
            return 'Verifikasi robot kedaluwarsa atau sudah dipakai. Centang "Saya bukan robot" lagi lalu kirim ulang.';
        }

        if (in_array('invalid-input-response', $codes, true)) {
            return 'Verifikasi robot tidak valid. Centang "Saya bukan robot" lagi lalu kirim ulang.';
        }

        if ($codes !== []) {
            return 'Verifikasi robot gagal (' . implode(', ', $codes) . '). Centang ulang kotak reCAPTCHA.';
        }

        return 'reCAPTCHA tidak valid. Silakan coba lagi.';
    }
}
