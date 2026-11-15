<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\AccountActivation;
use App\Support\WebSessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman ganti password bersama untuk akun non-mahasiswa (dosen, laboran, admin)
 * yang login memakai password sementara hasil import — pemilik mengamankan
 * akunnya sendiri sebelum boleh mengakses halaman lain.
 */
class AccountPasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->mustChangePassword()) {
            return redirect()->to($this->dashboardPath($user));
        }

        return view('auth.force-change-password', [
            'user' => $user,
            'dashboard' => $this->dashboardPath($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isMandatory = (bool) $user->must_change_password;

        $rules = [
            'password' => ['required', 'string', 'confirmed', new StrongPassword()],
        ];
        $messages = [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ];

        if (! $isMandatory) {
            $rules['current_password'] = ['required', 'string'];
            $messages['current_password.required'] = 'Password saat ini wajib diisi untuk verifikasi keamanan.';
        }

        $validated = $request->validate($rules, $messages);

        if (! $isMandatory && ! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        if (Hash::check($validated['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'Password baru harus berbeda dari password yang sedang digunakan.']);
        }

        DB::transaction(function () use ($user, $validated, $request, $isMandatory) {
            $updates = [
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ];
            if (Schema::hasColumn('users', 'must_change_password')) {
                $updates['must_change_password'] = false;
            }
            $user->forceFill($updates)->save();

            AccountActivation::log(
                $user,
                $user,
                $request,
                'activation_password_set',
                $isMandatory
                    ? 'Pengguna mengganti password sementara hasil import dengan password pribadi.'
                    : 'Pengguna memperbarui password pribadi secara mandiri.'
            );
        });

        WebSessionGuard::stampLogin($request, $user->fresh());

        return redirect()->to($this->dashboardPath($user))
            ->with('success', 'Password pribadi berhasil disimpan. Selamat datang di SmartBrail.');
    }

    private function dashboardPath(User $user): string
    {
        return match ($user->role) {
            'admin', 'laboran' => '/admin/dashboard',
            'dosen' => '/dosen/dashboard',
            'mahasiswa' => route('mahasiswa.beranda', absolute: false),
            default => '/dashboard',
        };
    }
}
