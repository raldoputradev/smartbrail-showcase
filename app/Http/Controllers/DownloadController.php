<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    public function index(): View
    {
        $apk = config('mobile.apk');
        $filename = (string) ($apk['filename'] ?? 'AIoT-SIMALAS-Mobile.apk');
        $path = public_path('downloads/'.$filename);
        $sizeMb = File::exists($path)
            ? round(File::size($path) / 1024 / 1024, 1)
            : null;

        return view('download', [
            'pageProps' => [
                'version' => $apk['version'] ?? '1.0.0',
                'versionCode' => (int) ($apk['version_code'] ?? 1),
                'changelog' => $apk['changelog'] ?? '',
                'apkUrl' => route('download.apk'),
                'apkExists' => File::exists($path),
                'apkSizeMb' => $sizeMb,
                'urls' => [
                    'beranda' => route('beranda'),
                    'download' => route('download'),
                    'helpdeskChat' => route('helpdesk.chat'),
                    'logoMark' => asset('branding/aiot-simalas-mark-s.png').'?v=4',
                    'logoLockup' => asset('branding/aiot-simalas-logo-lockup-s.png').'?v=4',
                ],
                'appVersion' => config('mobile.apk.version', '1.3.10'),
            ],
        ]);
    }

    public function apk(): BinaryFileResponse|Response
    {
        $filename = (string) config('mobile.apk.filename', 'AIoT-SIMALAS-Mobile.apk');
        $path = public_path('downloads/'.$filename);

        if (! File::exists($path)) {
            abort(404, 'File APK belum diunggah ke server. Hubungi admin laboratorium.');
        }

        return response()->download($path, 'AIoT SIMALAS Mobile.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
