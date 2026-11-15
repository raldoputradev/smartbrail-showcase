<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Rules\StrongPassword;
use App\Support\AccountActivation;
use App\Support\AccountEmailVerification;
use App\Support\LoginSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Aktivasi mandiri akun hasil import. Sesi di sini terbatas: tidak ada Auth::login,
 * hanya kunci sesi `account_activation` untuk menyelesaikan password, email, HP, dan OTP email.
 */
class ActivationController extends Controller
{
    public function show(Request $request)
    {
        $user = AccountActivation::sessionUser($request);

        if (!$user) {
            return $this->noStore(view('auth.activation', [
                'step' => 'masuk',
                'user' => null,
                'identifier' => (string) $request->session()->get('activation_identifier', old('nitk_nidn', '')),
            ]));
        }

        // Mahasiswa tidak lagi memakai aktivasi panjang; akunnya login dengan NIM + password sementara.
        if ($user->role === 'mahasiswa') {
            AccountActivation::endSession($request);

            return redirect()->route('login', ['role' => 'mahasiswa'])->with('info',
                'Akun mahasiswa masuk melalui halaman login memakai NIM dan password sementara dari admin. Tidak perlu aktivasi.');
        }

        $step = AccountActivation::nextStep($user);
        if ($step === 'selesai') {
            return $this->finish($request, $user);
        }
        if ($step === 'otp' && $request->query('ubah') === '1') {
            $step = 'profil';
        }

        return $this->noStore(view('auth.activation', [
            'step' => $step,
            'user' => $user,
            'identifier' => $user->nitk_nidn,
        ]));
    }

    /** Langkah 1: ID + kode aktivasi + password baru. Kode sekali pakai. */
    public function start(Request $request, LoginSecurityService $loginSecurity)
    {
        $data = $request->validate([
            'nitk_nidn' => ['required', 'string', 'max:50'],
            'kode_aktivasi' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:128', 'confirmed', new StrongPassword],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $nitk = trim($data['nitk_nidn']);
        $user = $this->guard($request, $loginSecurity, $nitk, 'kode_aktivasi');

        if ($user && $user->role === 'mahasiswa') {
            throw ValidationException::withMessages([
                'kode_aktivasi' => 'Akun mahasiswa tidak memakai aktivasi. Masuk di halaman login dengan NIM dan password sementara Anda.',
            ]);
        }

        $valid = $user
            && $user->isAwaitingActivation()
            && !AccountActivation::passwordSetSinceLastCode($user)
            && Hash::check(AccountActivation::normalizeCode($data['kode_aktivasi']), (string) $user->password);

        if (!$valid) {
            $this->fail($request, $loginSecurity, $user, $nitk, 'kode_aktivasi',
                'ID atau kode aktivasi salah, atau kode sudah dipakai. Jika sudah membuat password, pakai "Lanjutkan aktivasi".');
        }

        if (AccountActivation::codeExpired($user)) {
            throw ValidationException::withMessages([
                'kode_aktivasi' => 'Kode aktivasi sudah kedaluwarsa. Minta admin laboran membuat kode baru.',
            ]);
        }

        $loginSecurity->recordSuccessfulLogin($user);
        AccountActivation::setPasswordFromCode($user, $data['password'], $request);
        AccountActivation::startSession($request, $user);

        return redirect()->route('activation.show')
            ->with('success', 'Password dibuat. Kode aktivasi sudah tidak berlaku. Lanjutkan dengan mengisi email dan nomor HP.');
    }

    /** Lanjutkan aktivasi yang terputus dengan ID + password yang sudah dibuat. */
    public function resume(Request $request, LoginSecurityService $loginSecurity)
    {
        $data = $request->validate([
            'nitk_nidn' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $nitk = trim($data['nitk_nidn']);
        $user = $this->guard($request, $loginSecurity, $nitk, 'resume_password');

        if ($user && $user->role === 'mahasiswa') {
            throw ValidationException::withMessages([
                'resume_password' => 'Akun mahasiswa tidak memakai aktivasi. Masuk di halaman login dengan NIM dan password Anda.',
            ]);
        }

        if (!$user || !Hash::check($data['password'], (string) $user->password)) {
            if ($user && $user->isAwaitingActivation() && !AccountActivation::passwordSetSinceLastCode($user)
                && AccountActivation::secretMatches($user, $data['password'])) {
                throw ValidationException::withMessages([
                    'resume_password' => 'Akun ini belum membuat password. Gunakan form "Mulai aktivasi" dengan kode aktivasi.',
                ]);
            }
            $this->fail($request, $loginSecurity, $user, $nitk, 'resume_password', 'ID atau password salah.');
        }

        if (!$user->isAwaitingActivation()) {
            throw ValidationException::withMessages(['resume_password' => AccountActivation::blockedReason($user)]);
        }
        if (!AccountActivation::passwordSetSinceLastCode($user)) {
            throw ValidationException::withMessages([
                'resume_password' => 'Akun ini belum membuat password. Gunakan form "Mulai aktivasi" dengan kode aktivasi.',
            ]);
        }

        $loginSecurity->recordSuccessfulLogin($user);
        AccountActivation::startSession($request, $user);

        return redirect()->route('activation.show');
    }

    /** Langkah 2: email + nomor HP milik sendiri, lalu kirim OTP email. HP tidak diverifikasi. */
    public function saveProfile(Request $request)
    {
        $user = AccountActivation::sessionUser($request);
        if (!$user) {
            return $this->expired();
        }

        $emailRule = config('simalas.activation_email_dns_check', true) ? 'email:rfc,dns' : 'email:rfc';
        $data = $request->validate([
            'email' => ['required', $emailRule, 'max:255', Rule::unique('users', 'email')->ignore($user->id), new NotDisposableEmail],
            'no_hp' => ['required', 'string', 'max:25'],
        ], [
            'email.unique' => 'Email sudah dipakai akun lain.',
            'email.email' => 'Format email tidak valid atau domain tidak ditemukan.',
        ]);

        $phone = AccountActivation::normalizePhone($data['no_hp']);
        if (!AccountActivation::phoneIsValid($phone)) {
            throw ValidationException::withMessages([
                'no_hp' => 'Nomor HP harus 9–15 digit (boleh diawali +), contoh 081234567890.',
            ]);
        }

        $email = AccountEmailVerification::normalizeEmail($data['email']);
        $emailChanged = $email !== AccountEmailVerification::normalizeEmail($user->email);

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            'no_hp' => $phone,
        ])->save();

        AccountActivation::log($user, null, $request, 'activation_profile_set',
            'Pemilik mengisi email dan nomor HP (nomor HP belum diverifikasi).');

        if ($user->email_verified_at) {
            return redirect()->route('activation.show');
        }

        try {
            $status = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            return redirect()->route('activation.show')->with('info', $e->getMessage());
        }

        RateLimiter::clear($this->otpKey($user));

        return redirect()->route('activation.show')->with('success', $status);
    }

    /** Langkah 3: OTP email. Berhasil → akun aktif penuh tanpa persetujuan admin tambahan. */
    public function verifyOtp(Request $request)
    {
        $user = AccountActivation::sessionUser($request);
        if (!$user) {
            return $this->expired();
        }

        $data = $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ], [
            'otp_code.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        $key = $this->otpKey($user);
        if (RateLimiter::tooManyAttempts($key, AccountActivation::OTP_MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([
                'otp_code' => "Terlalu banyak kode salah. Kirim ulang kode baru atau coba lagi dalam {$minutes} menit.",
            ]);
        }

        $verified = filled($user->email) ? AccountEmailVerification::verify($user->email, $data['otp_code']) : null;
        if (!$verified || $verified->id !== $user->id) {
            RateLimiter::hit($key, 15 * 60);
            throw ValidationException::withMessages([
                'otp_code' => 'Kode OTP salah atau sudah kedaluwarsa. Anda bisa kirim ulang kode tanpa mengulang aktivasi.',
            ]);
        }

        RateLimiter::clear($key);
        AccountActivation::log($verified, null, $request, 'email_verified', 'Email diverifikasi OTP saat aktivasi mandiri.');

        return $this->finish($request, $verified);
    }

    public function resendOtp(Request $request)
    {
        $user = AccountActivation::sessionUser($request);
        if (!$user) {
            return $this->expired();
        }
        if (!filled($user->email)) {
            return redirect()->route('activation.show');
        }

        try {
            $status = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            return redirect()->route('activation.show')->withErrors(['otp_code' => $e->getMessage()]);
        }
        RateLimiter::clear($this->otpKey($user));

        return redirect()->route('activation.show')->with('success', $status);
    }

    public function leave(Request $request)
    {
        AccountActivation::endSession($request);

        return redirect()->route('activation.show')
            ->with('info', 'Sesi aktivasi ditutup. Lanjutkan kapan saja dengan ID dan password yang sudah Anda buat.');
    }

    public function done(Request $request)
    {
        $done = $request->session()->get('activation_done');
        if (!is_array($done)) {
            return redirect()->route('activation.show');
        }

        return $this->noStore(view('auth.activation', [
            'step' => 'selesai',
            'user' => null,
            'identifier' => '',
            'done' => $done,
        ]));
    }

    private function finish(Request $request, User $user)
    {
        try {
            $active = AccountActivation::complete($user, $request);
        } catch (\RuntimeException $e) {
            return redirect()->route('activation.show')->withErrors(['activation' => $e->getMessage()]);
        }

        AccountActivation::endSession($request);
        $request->session()->flash('activation_done', [
            'nama' => $active->nama_lengkap,
            'role' => $active->role,
        ]);

        return redirect()->route('activation.done');
    }

    private function guard(Request $request, LoginSecurityService $loginSecurity, string $nitk, string $field): ?User
    {
        $guard = $loginSecurity->guardBeforeAttempt($request, $nitk);
        if ($guard['message']) {
            throw ValidationException::withMessages([$field => $guard['message']]);
        }

        return $guard['user'];
    }

    private function fail(Request $request, LoginSecurityService $loginSecurity, ?User $user, string $nitk, string $field, string $generic): never
    {
        $message = $loginSecurity->recordFailedAttempt($request, $user, $nitk);
        if (str_contains($message, 'dikunci') || str_contains($message, 'sisa')) {
            $generic .= ' '.preg_replace('/^NIM \/ NITK \/ NIDN atau password salah\.\s*/', '', $message);
        }

        throw ValidationException::withMessages([$field => trim($generic)]);
    }

    private function otpKey(User $user): string
    {
        return 'activation-otp:'.$user->id;
    }

    private function expired()
    {
        return redirect()->route('activation.show')
            ->withErrors(['activation' => 'Sesi aktivasi habis. Masuk lagi dengan ID dan password yang sudah Anda buat (atau kode aktivasi jika belum membuat password).']);
    }

    private function noStore($view)
    {
        return response($view)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
