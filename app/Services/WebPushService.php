<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push (VAPID) ke browser — pengguna menerima notifikasi sistem walau
 * tab SmartBrail sedang tertutup. Semua kegagalan bersifat fail-soft:
 * tidak pernah membatalkan alur utama.
 */
class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  iterable<int>  $userIds
     * @param  array{title: string, body: string, url?: string, tag?: string}  $payload
     */
    public function sendToUsers(iterable $userIds, array $payload): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $ids = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        $subs = PushSubscription::query()->whereIn('user_id', $ids)->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.webpush.subject'),
                'publicKey' => (string) config('services.webpush.public_key'),
                'privateKey' => (string) config('services.webpush.private_key'),
            ],
        ]);

        $body = json_encode([
            'title' => $payload['title'],
            'body' => $payload['body'],
            'url' => $payload['url'] ?? '/',
            'tag' => $payload['tag'] ?? 'smartbrail',
            'icon' => asset('branding/app-logo.png'),
        ]);

        $sent = 0;
        foreach ($subs as $sub) {
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => ['p256dh' => $sub->public_key, 'auth' => $sub->auth_token],
                        'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                    ]),
                    $body
                );

                if ($report->isSuccess()) {
                    $sent++;
                } elseif (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                    $sub->delete();
                } else {
                    Log::warning('WebPush gagal', ['status' => $report->getResponse()?->getStatusCode()]);
                }
            } catch (\Throwable $e) {
                Log::warning('WebPush exception: ' . $e->getMessage());
            }
        }

        return $sent;
    }
}
