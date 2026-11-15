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
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ValidatesRecaptcha;

    /** Pesan tunggal untuk semua kegagalan login — tidak membedakan akun tidak ada, password salah, role salah, atau status akun. */
    private const GENERIC_LOGIN_ERROR = 'Tidak dapat masuk. Periksa data login Anda.';

    /** Hash bcrypt valid untuk menyamakan waktu verifikasi saat akun tidak ditemukan. */
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

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
        $request->session()->forget('register_flow_email');

        $pendingUserId = (int) $request->session()->get('account_verify_user_id', 0);
        if ($pendingUserId > 0) {
            $pendingUser = User::query()->find($pendingUserId);
            if ($pendingUser && !$pendingUser->email_verified_at) {
                $persistentAuthPanel = blank($pendingUser->email) ? 'add-email' : 'account-verify';
            } else {
                $request->session()->forget(['account_verify_user_id', 'account_verify_email', 'account_verify_otp_sent']);
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

        $queryRole = (string) $request->query('role', '');
        $selectedRole = in_array($queryRole, ['admin', 'laboran', 'dosen', 'mahasiswa'], true) ? $queryRole : 'mahasiswa';

        // Deep-link ke panel Lupa Password (dipakai tautan "Minta Tautan Baru" di halaman reset).
        if (! $persistentAuthPanel && $request->query('panel') === 'forgot') {
            $persistentAuthPanel = 'forgot';
        }

        // Panel login & lupa-password kini React/Inertia; panel OTP/reset/verifikasi
        // yang berjalan lewat session flow tetap pada view auth (Blade) yang sudah ada.
        if (! $persistentAuthPanel || $persistentAuthPanel === 'forgot') {
            return \Inertia\Inertia::render('Auth/Login', [
                'panel' => $request->session()->has('reset_link_sent') ? 'forgot' : $persistentAuthPanel,
                'selectedRole' => $selectedRole,
                'old' => [
                    'nitk_nidn' => old('nitk_nidn'),
                    'role' => old('role'),
                    'remember' => (bool) old('remember'),
                    'identifier' => old('identifier'),
                ],
                'csrf' => csrf_token(),
                'recaptchaSiteKey' => \App\Support\Recaptcha::isConfigured()
                    ? (string) config('services.recaptcha.site_key')
                    : null,
            ]);
        }

        return view('auth', [
            'persistentAuthPanel' => $persistentAuthPanel,
            'selectedRole' => $selectedRole,
        ]);
    }

    /**
     * Handle login via nitk_nidn + password + role selection.
     * Users must select their role (mahasiswa/dosen/laboran) before logging in.
     */
    public function login(Request $request, LoginSecurityService $loginSecurity)
    {
        $request->validate([
            'nitk_nidn' => 'required|string|max:50',
            'password'  => 'required|string|max:128',
            'role'      => 'required|in:mahasiswa,dosen,laboran,admin',
        ]);

        $nitk = trim((string) $request->nitk_nidn);
        $rawPassword = (string) $request->password;
        $guard = $loginSecurity->guardBeforeAttempt($request, $nitk);

        if ($guard['message']) {
            throw ValidationException::withMessages([
                'nitk_nidn' => [$guard['message']],
            ]);
        }

        $user = $guard['user'];

        // 1. Akun dosen/laboran hasil import (menunggu_aktivasi) tetap diarahkan ke /aktivasi.
        if ($user && !$user->isMahasiswa() && $user->isAwaitingActivation() && AccountActivation::secretMatches($user, $rawPassword)) {
            if (! self::selectedRoleMatches((string) $request->role, $user->role)) {
                $this->throwGenericLoginError($loginSecurity->recordFailedAttempt($request, $user, $nitk));
            }

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

        // 2. Transisi terkontrol untuk akun mahasiswa hasil import lama (menunggu_aktivasi).
        $legacyTransitioned = false;
        if ($user && $user->isMahasiswa() && $user->isAwaitingActivation()) {
            $legacyState = AccountActivation::inspectLegacyStudentImportState($user);

            if (!$legacyState['eligible']) {
                $this->throwGenericLoginError($loginSecurity->recordFailedAttempt($request, $user, $nitk));
            }

            $passwordMatches = $legacyState['mode'] === 'temp_code'
                ? Hash::check(AccountActivation::normalizeCode($rawPassword), (string) $user->password)
                : Hash::check($rawPassword, (string) $user->password);

            if (!$passwordMatches) {
                $this->throwGenericLoginError($loginSecurity->recordFailedAttempt($request, $user, $nitk));
            }

            if (! self::selectedRoleMatches((string) $request->role, $user->role)) {
                $this->throwGenericLoginError($loginSecurity->recordFailedAttempt($request, $user, $nitk));
            }

            if ($legacyState['mode'] === 'temp_code' && AccountActivation::codeExpired($user)) {
                throw ValidationException::withMessages([
                    'nitk_nidn' => [self::GENERIC_LOGIN_ERROR],
                ]);
            }

            $user = AccountActivation::transitionLegacyImportedMahasiswa($user, $legacyState['mode'], $request);
            $legacyTransitioned = true;
        }

        // 3. Verifikasi kredensial untuk akun yang sudah bukan menunggu_aktivasi.
        if (!$legacyTransitioned) {
            $usingTempPassword = $user
                && $user->mustChangePassword()
                && $user->is_active
                && ($user->approval_status ?? 'pending') === 'approved';

            // Hash selalu diverifikasi (dummy saat akun tidak ada) agar waktu respons
            // tidak membocorkan keberadaan akun.
            $passwordValid = $usingTempPassword
                ? AccountActivation::temporaryPasswordMatches($user, $rawPassword)
                : Hash::check($rawPassword, (string) ($user?->password ?? self::DUMMY_PASSWORD_HASH));

            // Jika akun dinonaktifkan/ditolak/pending namun masih berflag must_change_password, cek kecocokan agar pesan status akurat.
            if (!$passwordValid && $user && $user->mustChangePassword()) {
                $passwordValid = AccountActivation::temporaryPasswordMatches($user, $rawPassword);
            }

            $roleInvalid = $user && ! self::selectedRoleMatches((string) $request->role, $user->role);

            if (!$passwordValid || $roleInvalid) {
                $this->throwGenericLoginError($loginSecurity->recordFailedAttempt($request, $user, $nitk));
            }

            if (($user->approval_status ?? 'pending') === 'rejected'
                || (($user->approval_status ?? 'pending') === 'approved' && !$user->is_active)
                || !$user->is_active
                || ($user->approval_status ?? 'pending') !== 'approved') {
                throw ValidationException::withMessages([
                    'nitk_nidn' => [self::GENERIC_LOGIN_ERROR],
                ]);
            }

            if ($user->mustChangePassword() && AccountActivation::temporaryPasswordExpired($user)) {
                throw ValidationException::withMessages([
                    'nitk_nidn' => [self::GENERIC_LOGIN_ERROR],
                ]);
            }
        }

        $loginSecurity->recordSuccessfulLogin($user);

        $remember = $request->boolean('remember') && !$user->mustChangePassword();
        Auth::login($user, $remember);
        if ($remember) {
            // Batas "Tetap masuk" 30 hari: cookie terenkripsi sebagai batas terdepan recaller;
            // divalidasi ulang oleh EnforceWebSessionTimeout saat sesi dibuat via remember.
            Cookie::queue('remember_until', (string) now()->addDays(30)->timestamp, 60 * 24 * 30);
        } elseif ($request->cookies->has('remember_until')) {
            // Pilihan terbaru menang: login tanpa "Ingat saya" membatalkan penanda remember sebelumnya.
            Cookie::queue(Cookie::forget('remember_until'));
        }
        $request->session()->regenerate();
        WebSessionGuard::stampLogin($request, $user);
        $request->session()->forget('student_contact_prompt_dismissed_for');

        // 4. Akun dengan password sementara (semua role) dibatasi ke halaman ganti password.
        if ($user->mustChangePassword()) {
            $this->clearAccountVerifySession($request);
            $request->session()->forget('email_verification_pending');

            $changePath = $user->isMahasiswa()
                ? route('mahasiswa.password.change', absolute: false)
                : route('account.password.change', absolute: false);
            $changeMessage = 'Login berhasil. Demi keamanan akun, silakan buat password pribadi baru terlebih dahulu.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'redirect' => url($changePath),
                    'message' => $changeMessage,
                    'must_change_password' => true,
                ]);
            }

            return redirect()->to($changePath)->with('info', $changeMessage);
        }

        // 5. Verifikasi email bersifat sukarela untuk semua role — tidak ada lagi
        // banner wajib/OTP otomatis saat login; pengguna disarankan lewat kartu
        // di halaman profil (session()->now di UserProfileController).
        $emailVerificationRequired = !$user->isMahasiswa() && !$user->email_verified_at;
        $needsEmail = !$user->isMahasiswa() && blank($user->email);

        $this->clearAccountVerifySession($request);
        $request->session()->forget('email_verification_pending');

        $welcomeMessage = "Login berhasil. Selamat datang, {$user->nama_lengkap}!";

        $dashboardPath = match ($user->role) {
            'admin', 'laboran' => '/admin/dashboard',
            'dosen' => '/dosen/dashboard',
            'mahasiswa' => route('mahasiswa.beranda', absolute: false),
            default => '/dashboard',
        };

        $redirect = redirect()->intended($dashboardPath);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'       => true,
                'redirect' => url($dashboardPath),
                'message'  => $welcomeMessage,
                'email_verification_required' => $emailVerificationRequired,
                'needs_email' => $needsEmail,
            ]);
        }

        $flashRedirect = match ($user->role) {
            'admin', 'laboran', 'dosen' => $redirect
                ->with('login_success', true)
                ->with('login_welcome', true)
                ->with('success', $welcomeMessage),
            'mahasiswa' => $redirect
                ->with('login_welcome', true)
                ->with('success', $welcomeMessage),
            default => $redirect,
        };

        if ($emailVerificationRequired && in_array($user->role, ['admin', 'laboran', 'dosen'], true)) {
            $info = $needsEmail
                ? 'Email belum diisi. Anda bisa melengkapi email akun dari halaman Profil.'
                : 'Email belum diverifikasi. Anda bisa memverifikasinya kapan saja dari halaman Profil.';
            $flashRedirect = $flashRedirect->with('info', $info);
        }

        return $flashRedirect;
    }

    /** Opsi "Admin / Laboran" pada dropdown login mencakup dua role akun. */
    private static function selectedRoleMatches(string $selectedRole, string $userRole): bool
    {
        return $selectedRole === $userRole;
    }

    /**
     * Lempar kegagalan login generik; pesan throttle/lockout (identitas + IP, berlaku
     * juga untuk ID yang tidak terdaftar) tetap ditampilkan apa adanya.
     */
    private function throwGenericLoginError(string $serviceMessage): never
    {
        $isRateLimitMessage = str_contains($serviceMessage, 'percobaan') || str_contains($serviceMessage, 'dikunci');

        throw ValidationException::withMessages([
            'nitk_nidn' => [$isRateLimitMessage ? $serviceMessage : self::GENERIC_LOGIN_ERROR],
        ]);
    }

    /**
     * Pendaftaran mandiri web telah dinonaktifkan.
     */
    public function register(Request $request)
    {
        return $this->rejectManualWebRegistration($request);
    }

    public function resumeRegistrationVerification(Request $request)
    {
        return $this->rejectManualWebRegistration($request);
    }

    public function startRegistrationVerification(Request $request)
    {
        return $this->rejectManualWebRegistration($request);
    }

    public function verifyRegistration(Request $request)
    {
        return $this->rejectManualWebRegistration($request);
    }

    public function resendVerification(Request $request)
    {
        return $this->rejectManualWebRegistration($request);
    }

    private function rejectManualWebRegistration(Request $request)
    {
        $request->session()->forget('register_flow_email');
        $message = RegistrationGate::closedMessage('dosen');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'code' => 'REGISTRATION_CLOSED',
                'message' => $message,
            ], 403);
        }

        return redirect()->route('login')
            ->withErrors(['registration' => $message])
            ->with('auth_panel', 'login');
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

    /**
     * Cek ketersediaan email secara real-time untuk modal verifikasi.
     * Dipanggil sebelum submit agar email yang sudah dipakai akun lain
     * langsung ditolak di UI, bukan baru ketahuan setelah kirim.
     */
    public function checkAccountEmail(Request $request)
    {
        $user = $this->pendingAccountVerifyUser($request);
        if (!$user) {
            return response()->json([
                'ok' => false,
                'reason' => 'Sesi verifikasi tidak aktif.',
            ], 403);
        }

        $email = AccountEmailVerification::normalizeEmail((string) $request->input('email'));

        if ($email === '') {
            return response()->json(['ok' => true, 'available' => false, 'reason' => null]);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'ok' => true,
                'available' => false,
                'reason' => 'Format email tidak valid.',
            ]);
        }

        $taken = User::query()
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        return response()->json([
            'ok' => true,
            'available' => ! $taken,
            'reason' => $taken ? 'Email ini sudah dipakai akun lain.' : null,
        ]);
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

        // Batas tebakan diikat ke record token (bukan IP): ganti IP tidak meloloskan,
        // dan percobaan pada token lama tidak menghapus token yang baru diterbitkan.
        $record = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', AccountEmailVerification::TYPE)
            ->orderByDesc('id')
            ->first();

        if (!$record) {
            return back()
                ->withErrors(['otp_code' => 'Kode verifikasi tidak valid atau sudah kadaluarsa.'])
                ->with('auth_panel', 'account-verify')
                ->with('account_verify_otp_sent', true)
                ->with('account_verify_email', $email);
        }

        $otpAttemptKey = 'verify-email-otp:' . $record->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($otpAttemptKey, 8)) {
            DB::table('email_verification_tokens')->where('id', $record->id)->delete();
            \Illuminate\Support\Facades\RateLimiter::clear($otpAttemptKey);

            return redirect()->route('login')
                ->withErrors(['otp_code' => 'Terlalu banyak kode salah. Minta kode verifikasi baru.'])
                ->with('auth_panel', 'login');
        }

        $verified = AccountEmailVerification::verify($email, $request->input('otp_code'));
        if (!$verified || $verified->id !== $user->id) {
            \Illuminate\Support\Facades\RateLimiter::hit($otpAttemptKey, 15 * 60);

            return back()
                ->withErrors(['otp_code' => 'Kode verifikasi tidak valid atau sudah kadaluarsa.'])
                ->with('auth_panel', 'account-verify')
                ->with('account_verify_otp_sent', true)
                ->with('account_verify_email', $email);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($otpAttemptKey);
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
        return $this->rejectManualWebRegistration($request);
    }

    /**
     * Logout the user.
     */
    public function logout(Request $request)
    {
        $expired = $request->boolean('session_expired') || $request->query('expired');

        $request->session()->forget('student_contact_prompt_dismissed_for');

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
            ->withCookies([\Illuminate\Support\Facades\Cookie::forget('remember_until')])
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Clear-Site-Data', '"cache"');
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
        WebSessionGuard::stampLogin($request, $user);

        $welcomeMessage = "Email terverifikasi. Selamat datang, {$user->nama_lengkap}!";

        return match ($user->role) {
            'admin', 'laboran' => redirect()->intended('/admin/dashboard')->with('success', $welcomeMessage),
            'dosen' => redirect()->intended('/dosen/dashboard')->with('success', $welcomeMessage),
            'mahasiswa' => redirect()->intended(route('mahasiswa.beranda'))->with('success', $welcomeMessage),
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
