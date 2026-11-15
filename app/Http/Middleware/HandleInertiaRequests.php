<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // User dievaluasi malas saat respons dibangun (bukan saat middleware masuk),
        // agar resolver user yang dipasang controller tetap terbaca.
        $authUser = fn () => ($user = $request->user()) ? [
            'id' => $user->id,
            'nama_lengkap' => $user->nama_lengkap,
            'nitk_nidn' => $user->nitk_nidn,
            'role' => $user->role,
            'kelas' => $user->kelas,
            'tim' => $user->tim,
            'shift' => $user->shift,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'finger_id' => $user->finger_id,
            'must_change_password' => (bool) $user->must_change_password,
            'has_fingerprint' => $user->isMahasiswa() ? $user->hasFingerprintEnrolled() : null,
            'profile_photo_url' => $user->profile_photo_url,
        ] : null;

        return array_merge(parent::share($request), [
            'auth' => ['user' => $authUser],
            'flash' => [
                'login_welcome' => fn () => $request->session()->pull('login_welcome'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'status' => fn () => $request->session()->get('status'),
                'terkirim' => fn () => $request->session()->get('terkirim'),
                'activation_once' => fn () => $request->session()->get('activation_once'),
            ],
            'navNotificationState' => fn () => ($user = $request->user()) && $user->role === 'mahasiswa'
                ? app(\App\Http\Controllers\StudentPortalController::class)->sharedNotificationState($user)
                : null,
            'branding' => fn () => [
                'logo_url' => \App\Support\Branding::logoUrl(),
                'app_name' => \App\Support\Branding::appName(),
            ],
            // Label fitur sidebar (BARU/SOON) — dikelola admin di Pengaturan.
            'features' => fn () => \App\Support\FeatureBadge::states(),
            'ai_attachments' => fn () => (bool) config('services.ai.attachments', true),
            'preview' => fn () => $request->routeIs('dev.preview.mahasiswa')
                ? ['ketersediaan_url' => route('dev.preview.mahasiswa.ketersediaan')]
                : null,
            'reverb' => fn () => $request->user() ? [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host') ?: $request->getHost(),
                'port' => (int) config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
                'auth_url' => url('/broadcasting/auth'),
            ] : null,
        ]);
    }
}
