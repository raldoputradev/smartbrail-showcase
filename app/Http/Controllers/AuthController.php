<?php

namespace App\Http\Controllers;

use App\Models\AccountApprovalLog;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Rules\StrongPassword;
use App\Http\Concerns\ValidatesRecaptcha;
use App\Support\AccountActivation;
use App\Support\AccountEmailVerification;
use App\Support\AsyncMailer;
use App\Support\LoginSecurityService;
use App\Support\RegistrationGate;
use App\Support\WebSessionGuard;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ValidatesRecaptcha;

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
     * Show the unified auth page.
     */
    public function showAuthPage(Request $request)
    {
        $this->pruneEmailVerificationTokens();

        $persistentAuthPanel = null;

        $registerFlowEmail = $request->session()->get('register_flow_email');
        if ($registerFlowEmail) {
            $hasRegistrationToken = DB::table('email_verification_tokens')
                ->where('email', $registerFlowEmail)
                ->where('type', 'registration')
                ->where('expires_at', '>', now())
                ->exists();

            if ($hasRegistrationToken) {
                $persistentAuthPanel = 'reg-verify';
            } else {
                $request->session()->forget('register_flow_email');
            }
        }

        if (!$persistentAuthPanel) {
            $pendingUserId = (int) $request->session()->get('account_verify_user_id', 0);
            if ($pendingUserId > 0) {
                $pendingUser = User::query()->find($pendingUserId);
                if ($pendingUser && !$pendingUser->email_verified_at) {
                    $persistentAuthPanel = blank($pendingUser->email) ? 'add-email' : 'account-verify';
                } else {
                    $request->session()->forget(['account_verify_user_id', 'account_verify_email', 'account_verify_otp_sent']);
                }
            }
        }

        if (!$persistentAuthPanel) {
            $passwordFlowEmail = strtolower(trim((string) $request->session()->get('password_reset_flow_email', '')));
            if ($passwordFlowEmail !== '') {
                $activeResetToken = DB::table('email_verification_tokens')
                    ->whereRaw('LOWER(email) = ?', [$passwordFlowEmail])
                    ->where('type', 'password_reset')
                    ->where('expires_at', '>', now())
                    ->orderByDesc('id')
                    ->first();

                if ($activeResetToken) {
                    $persistentAuthPanel = $activeResetToken->verified ? 'reset-password' : 'verify-otp';
                } else {
                    $request->session()->forget('password_reset_flow_email');
                }
            }
        }

        return view('auth', [
            'persistentAuthPanel' => $persistentAuthPanel,
            'registrationOpen' => RegistrationGate::isOpen('dosen'),
            'registrationClosedMessage' => RegistrationGate::closedMessage('dosen'),
        ]);
    }

    private function activationRedirect(string $message)
    {
        return redirect()->route('activation.show')->with('info', $message);
    }

    /**
     * Handle login via nitk_nidn + password + role selection.
     * Users must select their role (laboran/dosen) before logging in.
     */
    public function login(Request $request, LoginSecurityService $loginSecurity)
    {
        $request->validate([
            'nitk_nidn' => 'required|string|max:50',
            'password'  => 'required|string|max:128',
            'role'      => 'required|in:laboran,dosen',
        ]);

        $nitk = trim((string) $request->nitk_nidn);
        $guard = $loginSecurity->guardBeforeAttempt($request, $nitk);

        if ($guard['message']) {
            throw ValidationException::withMessages([
                'nitk_nidn' => [$guard['message']],
            ]);
        }

        $user = $guard['user'];

        // Akun hasil import: kredensial benar (kode/password aktivasi) → arahkan ke /aktivasi tanpa sesi login.
        if ($user && $user->isAwaitingActivation() && AccountActivation::secretMatches($user, (string) $request->password)) {
            $loginSecurity->recordSuccessfulLogin($user);
            $request->session()->flash('activation_identifier', $user->nitk_nidn);
            $message = 'Akun Anda belum aktif penuh. Selesaikan aktivasi: buat password, isi email & nomor HP, lalu verifikasi email.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'redirect' => route('activation.show'),
                    'message' => $message,
                    'activation_required' => true,
                ]);
            }

            return redirect()->route('activation.show')->with('info', $message);
        }

        $credentialsInvalid = !$user || !Hash::check($request->password, $user->password);
        $roleInvalid = $user && $user->role !== $request->role;

        if ($credentialsInvalid || $roleInvalid) {
            $failMessage = $loginSecurity->recordFailedAttempt($request, $user, $nitk);

            throw ValidationException::withMessages(
                $roleInvalid && !$credentialsInvalid
                    ? ['role' => ['Hak akses yang dipilih tidak sesuai dengan akun Anda.']]
                    : ['nitk_nidn' => [$failMessage]]
            );
        }

        if (($user->approval_status ?? 'pending') === 'rejected') {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda ditolak oleh admin. Hubungi laboran jika merasa ini keliru.'],
            ]);
        }

        if (!$user->is_active || ($user->approval_status ?? 'pending') !== 'approved') {
            throw ValidationException::withMessages([
                'email' => ['Email sudah diverifikasi, tetapi akun masih menunggu persetujuan admin/laboran.'],
            ]);
        }

        $loginSecurity->recordSuccessfulLogin($user);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        WebSessionGuard::stampLogin($request);

        $emailVerificationRequired = !$user->email_verified_at;
        $needsEmail = blank($user->email);
        $otpMessage = null;

        if ($emailVerificationRequired) {
            $request->session()->put('email_verification_pending', true);
            $request->session()->put('account_verify_needs_email', $needsEmail);
            $request->session()->put('account_verify_user_id', $user->id);

            if (!$needsEmail) {
                try {
                    $otpMessage = AccountEmailVerification::send($user);
                    $request->session()->put('account_verify_email', $user->email);
                    $request->session()->put('account_verify_otp_sent', true);
                } catch (\RuntimeException $e) {
                    $otpMessage = $e->getMessage();
                }
            }
        }

        $welcomeMessage = "Login berhasil. Selamat datang, {$user->nama_lengkap}!";

        $dashboardPath = match ($user->role) {
            'laboran' => '/admin/dashboard',
            'dosen' => '/dosen/dashboard',
            'mahasiswa' => route('mahasiswa.gate', absolute: false),
            default => '/dashboard',
        };

        $redirect = redirect()->intended($dashboardPath);

        if ($request->expectsJson() || $request->ajax()) {
            $message = $user->role === 'mahasiswa'
                ? 'Akun mahasiswa hanya untuk aplikasi mobile SIMALAS. Website ini untuk laboran dan dosen.'
                : $welcomeMessage;

            return response()->json([
                'ok'       => true,
                'redirect' => url($dashboardPath),
                'message'  => $message,
                'email_verification_required' => $emailVerificationRequired,
                'needs_email' => $needsEmail,
            ]);
        }

        $flashRedirect = match ($user->role) {
            'laboran', 'dosen' => $redirect
                ->with('login_success', true)
                ->with('success', $welcomeMessage),
            'mahasiswa' => $redirect
                ->with('info', 'Akun mahasiswa hanya untuk aplikasi mobile SIMALAS. Website ini untuk laboran dan dosen.'),
            default => $redirect,
        };

        if ($emailVerificationRequired && in_array($user->role, ['laboran', 'dosen'], true)) {
            $info = $needsEmail
                ? 'Email belum diisi. Lengkapi email akun Anda dari banner di dashboard.'
                : (($otpMessage ?? null) ? $otpMessage . ' Verifikasi email dari banner di dashboard.' : 'Email belum diverifikasi. Selesaikan verifikasi dari banner di dashboard.');
            $flashRedirect = $flashRedirect->with('info', $info);
        }

        return $flashRedirect;
    }

    /**
     * Handle user registration.
     * Step 1: Create user (inactive) and send email verification token.
     */
    public function register(Request $request)
    {
        if ($resume = $this->resumePendingDosenRegistrationIfExists($request)) {
            return $resume;
        }

        if (!RegistrationGate::isOpen('dosen')) {
            return back()
                ->withErrors(['registration' => RegistrationGate::closedMessage('dosen')])
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('auth_panel', 'register');
        }

        if ($redirect = $this->validateRecaptcha($request, 'register')) {
            if ($resume = $this->resumePendingDosenRegistrationIfExists($request)) {
                return $resume->with(
                    'info',
                    'Pendaftaran sudah tercatat di sistem. Lanjutkan verifikasi OTP — centang reCAPTCHA tidak diperlukan lagi.'
                );
            }

            return $redirect;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'nitk_nidn'    => 'required|string|max:50|regex:/^[0-9]+$/|unique:users,nitk_nidn',
            'email'        => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email', new NotDisposableEmail],
            'password'     => ['required', 'string', 'max:128', 'confirmed', new StrongPassword],
            'role'         => 'required|in:dosen',
        ], [
            'nitk_nidn.regex' => 'NIDN hanya boleh berisi angka.',
            'nitk_nidn.unique' => 'NIDN sudah terdaftar.',
            'email.unique' => 'Email sudah terdaftar.',
            'email.email' => 'Format email tidak valid atau domain tidak ditemukan.',
        ]);

        if ($validator->fails()) {
            if ($resume = $this->resumePendingDosenRegistrationIfExists($request)) {
                return $resume;
            }

            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('auth_panel', 'register');
        }

        $this->pruneEmailVerificationTokens();

        // Create user as inactive (not verified yet)
        $user = User::create([
            'nama_lengkap' => $request->nama_lengkap,
            'nitk_nidn'    => $request->nitk_nidn,
            'email'        => $request->email,
            'password'     => $request->password,
            'role'         => $request->role,
            'is_active'    => false,
            'approval_status' => 'pending',
        ]);

        $this->recordApprovalLog($request, $user, 'registered', 'Calon dosen mendaftar mandiri dari halaman publik.');

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Clean up any existing tokens for this email
        DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->where('type', 'registration')
            ->delete();

        // Store verification token
        DB::table('email_verification_tokens')->insert([
            'email'      => $user->email,
            'token'      => '', // unused
            'otp_code'   => $otpCode,
            'type'       => 'registration',
            'expires_at' => now()->addMinutes(30),
            'verified'   => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kirim email verifikasi sebagai detached child process: respons HTTP langsung kembali.
        AsyncMailer::dispatchOtp($user->email, $otpCode, $user->nama_lengkap, 'registration');
        $emailStatus = "Kode verifikasi sedang dikirim ke {$user->email}. Silakan cek email Anda dalam beberapa detik.";

        $request->session()->put('register_flow_email', $user->email);

        return back()->with('register_otp_sent', true)
                     ->with('auth_panel', 'reg-verify')
                     ->with('register_email', $user->email)
                     ->with('success', $emailStatus);
    }

    /**
     * Resume registration OTP flow via signed link from email (no prior session required).
     */
    public function resumeRegistrationVerification(Request $request)
    {
        $this->pruneEmailVerificationTokens();

        $request->validate([
            'email' => 'required|email',
        ]);

        return $this->redirectToRegistrationVerification($request, $request->email);
    }

    /**
     * Resume registration OTP flow by entering email on the register page.
     */
    public function startRegistrationVerification(Request $request)
    {
        $this->pruneEmailVerificationTokens();

        $request->validate([
            'email' => 'required|email',
        ]);

        return $this->redirectToRegistrationVerification($request, $request->email, 'reg-resume');
    }

    /**
     * Verify registration email token.
     */
    public function verifyRegistration(Request $request)
    {
        $this->pruneEmailVerificationTokens();

        $request->validate([
            'email'    => 'required|email',
            'otp_code' => 'required|digits:6',
        ], [
            'otp_code.required' => 'Masukkan kode OTP 6 digit.',
            'otp_code.digits'   => 'Kode OTP harus berupa 6 digit angka.',
        ]);

        $record = DB::table('email_verification_tokens')
            ->where('email', $request->email)
            ->where('otp_code', $request->otp_code)
            ->where('type', 'registration')
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->expires_at)->isPast()) {
            return back()->withErrors(['otp_code' => 'Kode verifikasi tidak valid atau sudah kadaluarsa.'])
                         ->with('auth_panel', 'reg-verify')
                         ->with('register_otp_sent', true)
                         ->with('register_email', $request->email);
        }

        // OTP hanya memverifikasi email. Akses web dosen tetap menunggu approval admin.
        $user = User::where('email', $request->email)->first();
        if ($user?->isAwaitingActivation()) {
            return $this->activationRedirect('Akun ini dibuat admin dan diaktifkan lewat halaman Aktivasi Akun.');
        }
        if ($user) {
            $user->update([
                'email_verified_at' => now(),
                'approval_status' => $user->approval_status === 'rejected' ? 'rejected' : 'pending',
            ]);

            $this->recordApprovalLog($request, $user, 'email_verified', 'Email dosen berhasil diverifikasi OTP, menunggu approval admin.');
        }

        // Clean up verification tokens
        DB::table('email_verification_tokens')
            ->where('email', $request->email)
            ->where('type', 'registration')
            ->delete();

        $request->session()->forget('register_flow_email');

        return redirect()->route('register')
            ->with('auth_panel', 'register')
            ->with('register_verified', true)
            ->with('success', 'Email berhasil diverifikasi. Akun dosen menunggu persetujuan admin/laboran. Anda bisa mendaftarkan akun dosen lain atau login setelah disetujui.');
    }

    /**
     * Resend registration verification OTP.
     */
    public function resendVerification(Request $request)
    {
        $this->pruneEmailVerificationTokens();

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Email tidak ditemukan.'])
                         ->with('auth_panel', 'reg-verify')
                         ->with('register_otp_sent', true)
                         ->with('register_email', $request->email);
        }

        if ($user->isAwaitingActivation()) {
            return $this->activationRedirect('Akun ini dibuat admin dan diaktifkan lewat halaman Aktivasi Akun.');
        }

        if (($user->approval_status ?? 'pending') === 'rejected') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Akun ini sudah ditolak oleh admin/laboran. Hubungi admin jika merasa ini keliru.'])
                ->with('auth_panel', 'login');
        }

        if ($user->email_verified_at && $user->is_active && ($user->approval_status ?? 'pending') === 'approved') {
            return redirect()->route('login')
                ->with('success', 'Akun sudah diverifikasi. Silakan login.');
        }

        // Rate limit: check if last OTP was sent less than 60 seconds ago
        $lastToken = DB::table('email_verification_tokens')
            ->where('email', $request->email)
            ->where('type', 'registration')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastToken) {
            $elapsed = now()->diffInSeconds(\Carbon\Carbon::parse($lastToken->created_at), true);
            if ($elapsed < 60) {
                $remaining = ceil(60 - $elapsed);
                return back()->withErrors(['email' => "Tunggu {$remaining} detik sebelum mengirim ulang kode verifikasi."])
                             ->with('auth_panel', 'reg-verify')
                             ->with('register_resend_cooldown', $remaining)
                             ->with('register_otp_sent', true)
                             ->with('register_email', $request->email);
            }
        }

        // Generate new OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('email_verification_tokens')
            ->where('email', $request->email)
            ->where('type', 'registration')
            ->delete();

        DB::table('email_verification_tokens')->insert([
            'email'      => $user->email,
            'token'      => '', // unused
            'otp_code'   => $otpCode,
            'type'       => 'registration',
            'expires_at' => now()->addMinutes(30),
            'verified'   => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AsyncMailer::dispatchOtp($user->email, $otpCode, $user->nama_lengkap, 'resend');
        $status = "Kode verifikasi baru sedang dikirim ke {$user->email}.";

        $request->session()->put('register_flow_email', $user->email);

        return back()->with('register_otp_sent', true)
                     ->with('auth_panel', 'reg-verify')
                     ->with('register_email', $user->email)
                     ->with('success', $status);
    }

    public function submitAccountEmail(Request $request)
    {
        $user = $this->pendingAccountVerifyUser($request);
        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Sesi verifikasi habis. Silakan login ulang.'])
                ->with('auth_panel', 'login');
        }

        if ($redirect = $this->validateRecaptcha($request)) {
            $request->session()->put('email_verification_pending', true);
            $request->session()->put('account_verify_needs_email', true);

            return $redirect;
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')->ignore($user->id), new NotDisposableEmail],
        ], [
            'email.unique' => 'Email sudah terdaftar.',
            'email.email' => 'Format email tidak valid atau domain tidak ditemukan.',
        ]);

        $email = AccountEmailVerification::normalizeEmail($validated['email']);
        $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();

        try {
            $status = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            return back()
                ->withErrors(['email' => $e->getMessage()])
                ->with('auth_panel', 'add-email')
                ->withInput();
        }

        $request->session()->put('account_verify_user_id', $user->id);
        $request->session()->put('account_verify_email', $email);
        $request->session()->put('account_verify_otp_sent', true);
        $request->session()->put('account_verify_needs_email', false);
        $request->session()->put('email_verification_pending', true);

        if ($request->user()) {
            return back()
                ->with('account_verify_email', $email)
                ->with('account_verify_otp_sent', true)
                ->with('success', $status);
        }

        return back()
            ->with('auth_panel', 'account-verify')
            ->with('account_verify_email', $email)
            ->with('account_verify_otp_sent', true)
            ->with('success', $status);
    }

    public function verifyAccountEmail(Request $request)
    {
        $user = $this->pendingAccountVerifyUser($request);
        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['otp_code' => 'Sesi verifikasi habis. Silakan login ulang.'])
                ->with('auth_panel', 'login');
        }

        $request->validate([
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        $email = AccountEmailVerification::normalizeEmail($request->input('email'));
        if ($email !== AccountEmailVerification::normalizeEmail($user->email)) {
            return back()
                ->withErrors(['otp_code' => 'Email tidak sesuai dengan akun yang sedang diverifikasi.'])
                ->with('auth_panel', 'account-verify')
                ->with('account_verify_otp_sent', true)
                ->with('account_verify_email', $user->email);
        }

        $verified = AccountEmailVerification::verify($email, $request->input('otp_code'));
        if (!$verified || $verified->id !== $user->id) {
            return back()
                ->withErrors(['otp_code' => 'Kode verifikasi tidak valid atau sudah kadaluarsa.'])
                ->with('auth_panel', 'account-verify')
                ->with('account_verify_otp_sent', true)
                ->with('account_verify_email', $email);
        }

        $this->recordApprovalLog($request, $verified, 'email_verified', 'Email diverifikasi mandiri setelah login (akun dibuat admin).');
        $this->clearAccountVerifySession($request);
        $request->session()->forget('email_verification_pending');

        return $this->finishLoginAfterEmailVerification($request, $verified);
    }

    public function resendAccountVerification(Request $request)
    {
        $user = $this->pendingAccountVerifyUser($request);
        if (!$user || blank($user->email)) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Sesi verifikasi habis. Silakan login ulang.'])
                ->with('auth_panel', 'login');
        }

        try {
            $status = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            return back()
                ->withErrors(['email' => $e->getMessage()])
                ->with('auth_panel', 'account-verify')
                ->with('account_verify_otp_sent', true)
                ->with('account_verify_email', $user->email);
        }

        $request->session()->put('account_verify_otp_sent', true);
        $request->session()->put('account_verify_email', $user->email);

        return back()
            ->with('auth_panel', 'account-verify')
            ->with('account_verify_otp_sent', true)
            ->with('account_verify_email', $user->email)
            ->with('success', $status);
    }

    public function cancelAccountVerification(Request $request)
    {
        $email = AccountEmailVerification::normalizeEmail(
            (string) $request->session()->get('account_verify_email', $request->input('email', ''))
        );

        if ($email !== '') {
            DB::table('email_verification_tokens')
                ->where('email', $email)
                ->where('type', AccountEmailVerification::TYPE)
                ->delete();
        }

        $this->clearAccountVerifySession($request);

        return redirect()->route('login')
            ->with('auth_panel', 'login')
            ->with('success', 'Verifikasi email dibatalkan. Silakan login kembali jika diperlukan.');
    }

    public function cancelRegistrationVerification(Request $request)
    {
        $email = $request->input('email', $request->session()->get('register_flow_email'));
        if ($email) {
            DB::table('email_verification_tokens')
                ->where('email', $email)
                ->where('type', 'registration')
                ->delete();
        }

        $request->session()->forget('register_flow_email');

        return redirect()->route('register')
            ->with('auth_panel', 'register')
            ->with('success', 'Verifikasi registrasi dibatalkan. Token lama sudah dihapus.');
    }

    /**
     * Logout the user.
     */
    public function logout(Request $request)
    {
        $expired = $request->boolean('session_expired') || $request->query('expired');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($expired) {
            $redirect = redirect()
                ->route('login', ['expired' => 1])
                ->with(
                    'session_expired',
                    'Sesi berakhir karena tidak ada aktivitas atau batas waktu login. Silakan login ulang.'
                );
        } else {
            $redirect = redirect()->route('login');
        }

        // Cegah HP/browser menampilkan dashboard dari cache setelah logout.
        return $redirect
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Clear-Site-Data', '"cache"');
    }

    private function beginAccountEmailVerification(Request $request, User $user)
    {
        $request->session()->put('account_verify_user_id', $user->id);

        if (blank($user->email)) {
            $message = 'Email belum diisi pada akun Anda. Tambahkan email aktif untuk verifikasi sebelum melanjutkan.';

            return $this->accountVerificationResponse($request, 'add-email', $message, $user, false);
        }

        try {
            $message = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }

        $request->session()->put('account_verify_email', $user->email);
        $request->session()->put('account_verify_otp_sent', true);

        $info = $message . ' Masukkan kode OTP untuk menyelesaikan verifikasi.';

        return $this->accountVerificationResponse($request, 'account-verify', $info, $user, true);
    }

    private function accountVerificationResponse(
        Request $request,
        string $panel,
        string $message,
        User $user,
        bool $otpSent
    ) {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'code' => 'EMAIL_NOT_VERIFIED',
                'needs_email' => $panel === 'add-email',
                'auth_panel' => $panel,
                'message' => $message,
                'email' => $user->email,
            ], 403);
        }

        $redirect = redirect()->route('login')
            ->with('auth_panel', $panel)
            ->with('info', $message);

        if ($otpSent) {
            $redirect->with('account_verify_otp_sent', true)
                ->with('account_verify_email', $user->email);
        }

        return $redirect;
    }

    private function finishLoginAfterEmailVerification(Request $request, User $user)
    {
        if ($request->user()?->id === $user->id) {
            $this->clearAccountVerifySession($request);
            $request->session()->forget('email_verification_pending');
            $request->session()->forget('account_verify_needs_email');

            return redirect()->back()
                ->with('account_verify_success', true)
                ->with('success', 'Email berhasil diverifikasi.');
        }

        if (($user->approval_status ?? 'pending') === 'rejected') {
            return redirect()->route('login')
                ->with('auth_panel', 'login')
                ->withErrors(['email' => 'Akun Anda ditolak oleh admin. Hubungi laboran jika merasa ini keliru.']);
        }

        if (!$user->is_active || ($user->approval_status ?? 'pending') !== 'approved') {
            return redirect()->route('login')
                ->with('auth_panel', 'login')
                ->with('success', 'Email berhasil diverifikasi. Akun masih menunggu persetujuan admin/laboran sebelum bisa login.');
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        WebSessionGuard::stampLogin($request);

        $welcomeMessage = "Email terverifikasi. Selamat datang, {$user->nama_lengkap}!";

        return match ($user->role) {
            'laboran' => redirect()->intended('/admin/dashboard')->with('success', $welcomeMessage),
            'dosen' => redirect()->intended('/dosen/dashboard')->with('success', $welcomeMessage),
            default => redirect()->route('login')->with('success', $welcomeMessage),
        };
    }

    private function pendingAccountVerifyUser(Request $request): ?User
    {
        $authUser = $request->user();
        if ($authUser && !$authUser->email_verified_at) {
            return $authUser;
        }

        $userId = (int) $request->session()->get('account_verify_user_id', 0);
        if ($userId <= 0) {
            return null;
        }

        $user = User::query()->find($userId);

        return ($user && !$user->email_verified_at) ? $user : null;
    }

    private function clearAccountVerifySession(Request $request): void
    {
        $request->session()->forget([
            'account_verify_user_id',
            'account_verify_email',
            'account_verify_otp_sent',
            'account_verify_needs_email',
        ]);
    }

    /**
     * Jika dosen sudah terdaftar tetapi OTP belum selesai, arahkan ke panel verifikasi
     * (hindari error "sudah terdaftar" + reCAPTCHA kedaluwarsa saat submit ulang dari HP).
     */
    private function resumePendingDosenRegistrationIfExists(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $nidn = trim((string) $request->input('nitk_nidn', ''));

        if ($email === '' && $nidn === '') {
            return null;
        }

        $user = User::query()
            ->where('role', 'dosen')
            ->whereNull('email_verified_at')
            ->where(fn ($q) => $q->whereNull('approval_status')->orWhere('approval_status', '<>', AccountActivation::STATUS))
            ->where(function ($query) use ($email, $nidn) {
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
                if ($nidn !== '') {
                    $query->orWhere('nitk_nidn', $nidn);
                }
            })
            ->first();

        if (!$user || ($user->approval_status ?? 'pending') === 'rejected') {
            return null;
        }

        return $this->redirectToRegistrationVerification($request, $user->email, 'reg-verify')
            ->with(
                'info',
                'Pendaftaran Anda sudah tercatat. Masukkan kode OTP dari email, atau klik "Kirim ulang kode" jika belum menerima.'
            );
    }

    private function redirectToRegistrationVerification(Request $request, string $email, ?string $errorPanel = 'reg-resume')
    {
        $email = strtolower(trim($email));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return $this->registrationVerificationError(
                $request,
                'Email tidak ditemukan. Daftar terlebih dahulu atau periksa ejaan email.',
                $errorPanel,
                $email
            );
        }

        if ($user->role !== 'dosen') {
            return $this->registrationVerificationError(
                $request,
                'Email ini bukan akun pendaftaran dosen.',
                $errorPanel,
                $email
            );
        }

        if ($user->isAwaitingActivation()) {
            return $this->activationRedirect('Akun ini dibuat admin dan diaktifkan lewat halaman Aktivasi Akun.');
        }

        if (($user->approval_status ?? 'pending') === 'rejected') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Akun ini sudah ditolak oleh admin/laboran. Hubungi admin jika merasa ini keliru.'])
                ->with('auth_panel', 'login');
        }

        if ($user->email_verified_at) {
            if ($user->is_active && ($user->approval_status ?? 'pending') === 'approved') {
                return redirect()->route('login')
                    ->with('success', 'Akun sudah diverifikasi. Silakan login.');
            }

            return redirect()->route('login')
                ->with('info', 'Email sudah diverifikasi. Akun menunggu persetujuan admin/laboran sebelum bisa login.');
        }

        $hasActiveToken = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->where('type', 'registration')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$hasActiveToken) {
            $request->session()->put('register_flow_email', $user->email);

            return redirect()->route('register')
                ->withErrors(['email' => 'Kode verifikasi sudah kadaluarsa. Klik "Kirim ulang kode" di bawah untuk mendapatkan OTP baru.'])
                ->with('auth_panel', 'reg-verify')
                ->with('register_otp_sent', true)
                ->with('register_email', $user->email)
                ->with('register_token_expired', true);
        }

        $request->session()->put('register_flow_email', $user->email);

        return redirect()->route('register')
            ->with('auth_panel', 'reg-verify')
            ->with('register_otp_sent', true)
            ->with('register_email', $user->email)
            ->with('success', "Lanjutkan verifikasi email untuk {$user->email}. Masukkan 6 digit kode OTP.");
    }

    private function registrationVerificationError(
        Request $request,
        string $message,
        ?string $panel,
        string $email
    ) {
        $redirect = redirect()->route('register')
            ->withErrors(['email' => $message])
            ->withInput(['email' => $email]);

        if ($panel) {
            $redirect->with('auth_panel', $panel);
        }

        return $redirect;
    }

    private function recordApprovalLog(Request $request, User $user, string $action, ?string $note = null): void
    {
        AccountApprovalLog::query()->create([
            'user_id' => $user->id,
            'subject_name' => $user->nama_lengkap,
            'subject_email' => $user->email,
            'subject_nitk_nidn' => $user->nitk_nidn,
            'subject_role' => $user->role,
            'subject_meta' => [
                'finger_id' => $user->finger_id,
                'kelas' => $user->kelas,
                'tim' => $user->tim,
                'angkatan' => $user->angkatan,
                'semester' => $user->semester,
                'semester_type' => $user->semester_type,
            ],
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'note' => $note,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
