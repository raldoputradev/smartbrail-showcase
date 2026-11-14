<?php

namespace App\Support;

use App\Models\MobilePushToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobilePushService
{
    public function isConfigured(): bool
    {
        if (filled(config('mobile.fcm.server_key'))) {
            return true;
        }

        return FcmAccessToken::get() !== null && FcmAccessToken::projectId() !== null;
    }

    /**
     * @param  Collection<int, User>|array<int, User>  $users
     */
    public function sendToUsers(Collection|array $users, string $title, string $body, array $data = []): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $userIds = collect($users)->pluck('id')->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return 0;
        }

        $tokens = MobilePushToken::query()
            ->whereIn('user_id', $userIds)
            ->pluck('token')
            ->unique()
            ->values()
            ->all();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        if (! $this->isConfigured() || $tokens === []) {
            return 0;
        }

        if (filled(config('mobile.fcm.server_key'))) {
            return $this->sendLegacy($tokens, $title, $body, $data);
        }

        return $this->sendV1($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function sendV1(array $tokens, string $title, string $body, array $data): int
    {
        $accessToken = FcmAccessToken::get();
        $projectId = FcmAccessToken::projectId();
        if (! $accessToken || ! $projectId) {
            return 0;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $sent = 0;

        $stringData = [];
        foreach (array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']) as $key => $value) {
            $stringData[(string) $key] = (string) $value;
        }

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => $stringData,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => 'simalas_default',
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $sent++;
                } else {
                    Log::warning('FCM v1 push failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'token' => substr($token, 0, 12).'…',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM v1 push exception: '.$e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function sendLegacy(array $tokens, string $title, string $body, array $data): int
    {
        $key = (string) config('mobile.fcm.server_key');
        $sent = 0;

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key='.$key,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => array_values($chunk),
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => array_merge($data, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]),
                    'priority' => 'high',
                ]);

                if ($response->successful()) {
                    $sent += count($chunk);
                } else {
                    Log::warning('FCM legacy push failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM legacy push exception: '.$e->getMessage());
            }
        }

        return $sent;
    }

    public function notifyAppUpdate(): int
    {
        $apk = config('mobile.apk');
        $version = (string) ($apk['version'] ?? '1.0.0');
        $tokens = MobilePushToken::query()->pluck('token')->unique()->values()->all();

        return $this->sendToTokens(
            $tokens,
            'Update AIoT SIMALAS',
            "Versi $version tersedia. Ketuk untuk unduh.",
            [
                'type' => 'app_update',
                'download_url' => route('download'),
                'version' => $version,
            ]
        );
    }

    public function notifyMahasiswaAnnouncement(User $user, string $title, string $body, int $announcementId): int
    {
        if ($user->role !== 'mahasiswa') {
            return 0;
        }

        return $this->sendToUsers([$user], $title, $body, [
            'type' => 'announcement',
            'announcement_id' => (string) $announcementId,
        ]);
    }
}
