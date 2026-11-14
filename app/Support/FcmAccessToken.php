<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmAccessToken
{
    public static function get(): ?string
    {
        $path = (string) config('mobile.fcm.service_account_path');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        return Cache::remember('fcm_v1_access_token', 3300, function () use ($path) {
            $account = json_decode((string) file_get_contents($path), true);
            if (! is_array($account)) {
                return null;
            }

            $email = (string) ($account['client_email'] ?? '');
            $privateKey = (string) ($account['private_key'] ?? '');
            if ($email === '' || $privateKey === '') {
                return null;
            }

            try {
                $jwt = self::buildJwt($email, $privateKey);
                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if (! $response->successful()) {
                    Log::warning('FCM OAuth failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::warning('FCM OAuth exception: '.$e->getMessage());

                return null;
            }
        });
    }

    public static function projectId(): ?string
    {
        $fromEnv = (string) config('mobile.fcm.project_id');
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $path = (string) config('mobile.fcm.service_account_path');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $account = json_decode((string) file_get_contents($path), true);

        return is_array($account) ? ($account['project_id'] ?? null) : null;
    }

    private static function buildJwt(string $clientEmail, string $privateKey): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $payload = self::base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]));

        $input = $header.'.'.$payload;
        openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $input.'.'.self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
