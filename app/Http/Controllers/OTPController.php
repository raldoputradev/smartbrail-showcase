<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ValidatesRecaptcha;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\AccountActivation;
use App\Support\AsyncMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OTPController extends Controller
{
    use ValidatesRecaptcha;

    private const OTP_TTL_MINUTES = 10;

    /** Window to set a new password after OTP is verified. */
    private const RESET_PASSWORD_TTL_MINUTES = 30;

    private function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    private function pruneEmailVerificationTokens(): void
    {
        DB::table('email_verification_tokens')
            ->where('expires_at', '<', now())
            ->orWhere(function ($query) {
                $query->where('verified', true)
                    ->where('updated_at', '<', now()->subHour());
            })
            ->delete();
    }

    /**
     * Send a 6-digit OTP to the user's email for password reset.
     */
    public function sendOtp(Request $request)
    {
        return $this->dispatchPasswordResetOtp($request, false);
    }

    /**
     * Resend OTP from verify-otp panel without re-entering email.
     */
    public function resendOtp(Request $request)
    {
        return $this->dispatchPasswordResetOtp($request, true);
    }

    private function dispatchPasswordResetOtp(Request $request, bool $isResend)
    {
        $this->pruneEmailVerificationTokens();

        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $this->normalizeEmail($request->email);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // Respons seragam — tidak membocorkan ada/tidaknya akun atau statusnya.
        // Panel verify-otp tetap ditampilkan dengan email yang dikirim pengguna.
        if (!$user || $user->isAwaitingActivation() || !$user->email_verified_at) {
            $request->session()->put('password_reset_flow_email', $email);

            return back()->with('otp_sent', true)
                         ->with('auth_panel', 'verify-otp')
                         ->with('otp_email', $email)
                         ->with('success', 'Jika email Anda terdaftar dan terverifikasi, kode OTP akan dikirim ke email tersebut.');
        }

        if (!$isResend) {
            if ($redirect = $this->validateRecaptcha($request, 'forgot')) {
                return $redirect;
            }
        }

        // Rate limit: check if last OTP was sent less than 60 seconds ago
        $lastToken = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastToken) {
            $elapsed = now()->diffInSeconds(\Carbon\Carbon::parse($lastToken->created_at), true);
            if ($elapsed < 60) {
                // Dalam cooldown → respons sama seperti sukses, tanpa kirim ulang;
                // perbedaan respons tidak boleh membedakan akun terdaftar/tidak.
                return back()->with('otp_sent', true)
                             ->with('auth_panel', 'verify-otp')
                             ->with('otp_email', $email)
                             ->with('password_resend_cooldown', (int) ceil(60 - $elapsed))
                             ->with('success', 'Jika email Anda terdaftar dan terverifikasi, kode OTP akan dikirim ke email tersebut.');
            }
        }

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Remove any existing reset tokens for this email
        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->delete();

        // Store OTP
        DB::table('email_verification_tokens')->insert([
            'email'      => $email,
            'token'      => '', // unused
            'otp_code'   => $otpCode,
            'type'       => 'password_reset',
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'verified'   => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kirim email OTP sebagai detached child process supaya respons HTTP cepat.
        AsyncMailer::dispatchOtp($user->email, $otpCode, $user->nama_lengkap, 'password_reset');

        $request->session()->put('password_reset_flow_email', $email);

        return back()->with('otp_sent', true)
                     ->with('auth_panel', 'verify-otp')
                     ->with('otp_email', $email)
                     ->with('success', 'Jika email Anda terdaftar dan terverifikasi, kode OTP akan dikirim ke email tersebut.');
    }

    /**
     * Verify the 6-digit OTP for password reset.
     */
    public function verifyOtp(Request $request)
    {
        $this->pruneEmailVerificationTokens();

        $request->validate([
            'email'    => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        $email = $this->normalizeEmail(
            $request->input('email') ?: $request->session()->get('password_reset_flow_email')
        );

        // Batas tebakan diikat ke record token (bukan IP/email): ganti IP tidak
        // meloloskan, dan percobaan pada token lama tidak menghapus token baru.
        $record = DB::table('email_verification_tokens')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('type', 'password_reset')
            ->orderByDesc('id')
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->expires_at)->isPast()) {
            return back()->withErrors(['otp_code' => 'Kode OTP tidak valid atau sudah kadaluarsa.'])
                         ->with('auth_panel', 'verify-otp')
                         ->with('otp_sent', true)
                         ->with('otp_email', $email);
        }

        $otpAttemptKey = 'password-reset-otp:' . $record->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($otpAttemptKey, 8)) {
            // Hanya record INI yang dibatalkan — token terbit kemudian tidak ikut terhapus.
            DB::table('email_verification_tokens')->where('id', $record->id)->delete();
            \Illuminate\Support\Facades\RateLimiter::clear($otpAttemptKey);
            $request->session()->forget('password_reset_flow_email');

            return redirect()->route('login')
                ->withErrors(['otp_code' => 'Terlalu banyak kode salah. Silakan minta kode OTP baru dari halaman Lupa Password.'])
                ->with('auth_panel', 'forgot');
        }

        if (! hash_equals((string) $record->otp_code, (string) $request->otp_code)) {
            \Illuminate\Support\Facades\RateLimiter::hit($otpAttemptKey, 15 * 60);

            return back()->withErrors(['otp_code' => 'Kode OTP tidak valid atau sudah kadaluarsa.'])
                         ->with('auth_panel', 'verify-otp')
                         ->with('otp_sent', true)
                         ->with('otp_email', $email);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($otpAttemptKey);

        // Mark as verified and extend window for setting a new password.
        DB::table('email_verification_tokens')
            ->where('id', $record->id)
            ->update([
                'email'      => $email,
                'verified'   => true,
                'expires_at' => now()->addMinutes(self::RESET_PASSWORD_TTL_MINUTES),
                'updated_at' => now(),
            ]);

        $request->session()->put('password_reset_flow_email', $email);

        return back()->with('otp_verified', true)
                     ->with('auth_panel', 'reset-password')
                     ->with('otp_email', $email)
                     ->with('success', 'OTP berhasil diverifikasi. Silakan buat password baru.');
    }

    /**
     * Reset the user's password after OTP verification.
     */
    public function resetPassword(Request $request)
    {
        $email = $this->normalizeEmail(
            $request->input('email') ?: $request->session()->get('password_reset_flow_email')
        );

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['email' => $email, 'password' => $request->password, 'password_confirmation' => $request->password_confirmation],
            [
                'email'    => 'required|email',
                'password' => ['required', 'string', 'max:128', 'confirmed', new StrongPassword],
            ],
            [
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
                'password.required'  => 'Password wajib diisi.',
            ]
        );

        if ($validator->fails()) {
            return back()->withErrors($validator)
                         ->withInput(['email' => $email])
                         ->with('auth_panel', 'reset-password')
                         ->with('otp_verified', true)
                         ->with('otp_email', $email);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            $request->session()->forget('password_reset_flow_email');

            return back()->withErrors(['email' => 'Sesi reset password tidak valid. Silakan ulangi dari Lupa Password.'])
                         ->with('auth_panel', 'forgot');
        }

        if (!$user->email_verified_at) {
            $request->session()->forget('password_reset_flow_email');

            return back()->withErrors([
                'email' => 'Email belum terverifikasi sehingga tidak dapat digunakan untuk reset password mandiri.',
            ])->with('auth_panel', 'forgot');
        }

        // Verify there's a verified OTP record for this email
        $record = DB::table('email_verification_tokens')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('type', 'password_reset')
            ->where('verified', true)
            ->orderByDesc('id')
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->expires_at)->isPast()) {
            $request->session()->forget('password_reset_flow_email');

            return back()
                ->withErrors(['password' => 'Sesi reset password tidak valid atau sudah kedaluwarsa. Silakan ulangi dari Lupa Password.'])
                ->with('auth_panel', 'forgot');
        }

        $payload = [
            'password' => $request->password,
            'must_change_password' => false,
            'remember_token' => Str::random(60),
            'mobile_api_token' => null,
            'mobile_token_expires_at' => null,
        ];
        if (Schema::hasColumn('users', 'failed_login_attempts')) {
            $payload['failed_login_attempts'] = 0;
        }
        if (Schema::hasColumn('users', 'login_locked_until')) {
            $payload['login_locked_until'] = null;
        }
        if (Schema::hasColumn('users', 'login_requires_admin_unlock')) {
            $payload['login_requires_admin_unlock'] = false;
        }
        if (Schema::hasColumn('users', 'login_suspicious_at')) {
            $payload['login_suspicious_at'] = null;
        }

        $user->forceFill($payload)->save();
        AccountActivation::revokeActiveTokensAndSessions($user);

        $request->session()->forget('password_reset_flow_email');

        return redirect()->route('login')
            ->with('auth_panel', 'login')
            ->with('success', 'Password berhasil direset! Silakan login dengan password baru.');
    }

    public function cancelPasswordReset(Request $request)
    {
        $email = $this->normalizeEmail(
            $request->input('email') ?: $request->session()->get('password_reset_flow_email')
        );
        if ($email) {
            DB::table('email_verification_tokens')
                ->where('email', $email)
                ->where('type', 'password_reset')
                ->delete();
        }

        $request->session()->forget('password_reset_flow_email');

        return redirect()->route('login')
            ->with('auth_panel', 'login')
            ->with('success', 'Proses reset password dibatalkan. Token OTP lama sudah dihapus.');
    }
}
