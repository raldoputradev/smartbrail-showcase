<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ValidatesRecaptcha;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\AsyncMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        if (!$user) {
            return back()->withErrors(['email' => 'Email tidak terdaftar.'])
                         ->with('auth_panel', 'forgot')
                         ->withInput();
        }

        if ($user->isAwaitingActivation()) {
            return back()->withErrors(['email' => 'Akun ini belum diaktivasi. Selesaikan aktivasi di halaman Aktivasi Akun ('.route('activation.show').').'])
                         ->with('auth_panel', 'forgot')
                         ->withInput();
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
                $remaining = ceil(60 - $elapsed);
                return back()->withErrors(['email' => "Tunggu {$remaining} detik sebelum mengirim ulang kode OTP."])
                             ->with('auth_panel', 'verify-otp')
                             ->with('password_resend_cooldown', $remaining)
                             ->with('otp_sent', true)
                             ->with('otp_email', $email);
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
        $emailStatus = $isResend
            ? "Kode OTP baru sedang dikirim ke {$user->email}."
            : "Kode OTP sedang dikirim ke {$user->email}.";

        $request->session()->put('password_reset_flow_email', $email);

        return back()->with('otp_sent', true)
                     ->with('auth_panel', 'verify-otp')
                     ->with('otp_email', $email)
                     ->with('success', $emailStatus);
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

        $record = DB::table('email_verification_tokens')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('otp_code', $request->otp_code)
            ->where('type', 'password_reset')
            ->orderByDesc('id')
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->expires_at)->isPast()) {
            return back()->withErrors(['otp_code' => 'Kode OTP tidak valid atau sudah kadaluarsa.'])
                         ->with('auth_panel', 'verify-otp')
                         ->with('otp_sent', true)
                         ->with('otp_email', $email);
        }

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
            return back()->withErrors(['email' => 'Email tidak terdaftar.'])
                         ->with('auth_panel', 'forgot');
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

        // Update the password
        $user->update([
            'password' => $request->password,
        ]);

        // Clean up all tokens for this email
        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->delete();

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
