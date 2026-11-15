<?php

namespace App\Http\Controllers;

use App\Support\LoginSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentAuthController extends Controller
{
    public function showLogin(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return match ($request->user()->role) {
                'laboran' => redirect()->route('admin.dashboard'),
                'dosen' => redirect()->route('dosen.dashboard'),
                default => redirect()->route('mahasiswa.beranda'),
            };
        }

        // Alihkan ke halaman login gabungan dengan pilihan Mahasiswa terpilih.
        return redirect()->route('login', ['role' => 'mahasiswa']);
    }

    public function login(Request $request, LoginSecurityService $loginSecurity)
    {
        // Jalur kompatibilitas POST /mahasiswa/masuk: pakai autentikasi gabungan dengan role mahasiswa.
        $request->merge(['role' => 'mahasiswa']);

        return app(AuthController::class)->login($request, $loginSecurity);
    }

    public function logout(Request $request): RedirectResponse
    {
        return app(AuthController::class)->logout($request);
    }
}
