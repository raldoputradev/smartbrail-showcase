<?php

namespace App\Console\Commands;

use App\Support\MobilePushService;
use Illuminate\Console\Command;

class MobileNotifyUpdateCommand extends Command
{
    protected $signature = 'mobile:notify-update';

    protected $description = 'Kirim push notifikasi update APK ke semua perangkat mobile terdaftar.';

    public function handle(MobilePushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->error('FCM belum dikonfigurasi. Set FCM_SERVICE_ACCOUNT_PATH (v1) atau FCM_SERVER_KEY (legacy) di .env.');

            return self::FAILURE;
        }

        $sent = $push->notifyAppUpdate();
        $version = (string) config('mobile.apk.version', '1.0.0');

        $this->info("Push update v{$version} dikirim ke {$sent} token.");

        return self::SUCCESS;
    }
}
