<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ValidatesRecaptcha;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\LandingPageData;
use App\Support\Recaptcha;
use App\Support\SuggestionSubmissionLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LandingController extends Controller
{
    use ValidatesRecaptcha;
    /**
     * Halaman landing publik. Menampilkan pengenalan project,
     * statistik real-time, fitur, cara kerja, tim, peta, dan kontak.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return match (Auth::user()->role) {
                'laboran' => redirect()->route('admin.dashboard'),
                'dosen' => redirect()->route('dosen.dashboard'),
                default => redirect()->route('download'),
            };
        }

        if ($request->boolean('clear')) {
            $request->session()->forget('auth_panel');

            return redirect()->route('beranda');
        }

        // Mesin yang BENAR-BENAR online = aktif & punya heartbeat dalam ambang waktu.
        $mesinOnline   = MesinIot::online()->count();
        $mesinTerdaftar = MesinIot::where('is_active', true)->count();

        $stats = [
            'total_pengguna'  => User::where('role', 'mahasiswa')->count(),
            'pengguna_aktif'  => User::where('role', 'mahasiswa')->where('is_active', true)->count(),
            'mahasiswa'       => User::where('role', 'mahasiswa')->where('is_active', true)->count(),
            'dosen'           => User::where('role', 'dosen')->where('is_active', true)->count(),
            'mesin'           => $mesinOnline,
            'mesin_terdaftar' => $mesinTerdaftar,
            'tap_abs'         => LogAbsensi::count(),
            'finger'          => User::whereNotNull('finger_id')->count(),
        ];

        return view('landing', [
            'pageProps' => [
                'stats' => $stats,
                'team' => LandingPageData::teamMembers(),
                'urls' => [
                    'download' => route('download'),
                    'beranda' => route('beranda'),
                    'suggestions' => route('suggestions.store'),
                    'helpdeskChat' => route('helpdesk.chat'),
                    'logoMark' => asset('branding/aiot-simalas-mark-s.png').'?v=4',
                    'logoLockup' => asset('branding/aiot-simalas-logo-lockup-s.png').'?v=4',
                ],
                'flash' => [
                    'success' => session('suggest_success'),
                    'error' => session('suggest_error') ?? ($request->session()->get('errors')?->first()),
                ],
                'old' => old(),
                'recaptcha' => [
                    'enabled' => Recaptcha::isConfigured(),
                    'siteKey' => config('services.recaptcha.site_key'),
                ],
                'map' => config('landing.map'),
                'social' => config('landing.social'),
                'appVersion' => config('mobile.apk.version', '1.3.8'),
            ],
        ]);
    }

    /**
     * Endpoint penyimpanan saran/masukan dari pengunjung.
     * Disimpan ke database supaya bisa dikelola admin.
     */
    public function storeSuggestion(Request $request): RedirectResponse
    {
        if ($redirect = $this->validateRecaptcha($request)) {
            return $redirect->withFragment('kontak');
        }

        $validated = $request->validate([
            'nama'   => ['required', 'string', 'max:120'],
            'email'  => ['required', 'email', 'max:150'],
            'subjek' => ['nullable', 'string', 'max:180'],
            'pesan'  => ['required', 'string', 'min:10', 'max:4000'],
        ], [
            'nama.required'  => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi agar batas pengiriman dapat diterapkan.',
            'email.email'    => 'Format email tidak valid.',
            'pesan.required' => 'Pesan/saran wajib diisi.',
            'pesan.min'      => 'Pesan terlalu singkat (minimal 10 karakter).',
        ]);

        if ($limitMsg = SuggestionSubmissionLimiter::checkPublic(
            $request,
            $validated['email'],
            $validated['pesan']
        )) {
            return back()
                ->withInput()
                ->with('suggest_error', $limitMsg)
                ->withFragment('kontak');
        }

        SuggestionSubmissionLimiter::recordPublicHit($request, $validated['email']);

        Suggestion::create([
            'source'     => 'public',
            'user_id'    => null,
            'nama'       => $validated['nama'],
            'email'      => mb_strtolower(trim($validated['email'])),
            'subjek'     => $validated['subjek'] ?? null,
            'pesan'      => $validated['pesan'],
            'status'     => 'baru',
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return back()
            ->with('suggest_success', 'Terima kasih! Saran Anda sudah kami terima.')
            ->withFragment('kontak');
    }
}
