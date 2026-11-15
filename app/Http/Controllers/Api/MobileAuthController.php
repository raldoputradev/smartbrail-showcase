<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountApprovalLog;
use App\Models\MobilePushToken;
use App\Models\User;
use App\Support\FingerIdAllocator;
use App\Support\MahasiswaProfileOptions;
use App\Support\MahasiswaShiftResolver;
use App\Support\PblTeamService;
use App\Rules\NotDisposableEmail;
use App\Rules\StrongPassword;
use App\Support\AccountActivation;
use App\Support\AccountEmailVerification;
use App\Support\LoginSecurityService;
use App\Support\RegistrationGate;
use App\Support\MobileImageCaptcha;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public const TOKEN_IDLE_MINUTES = 10;
    private const OTP_TTL_MINUTES = 10;
    private const RESET_PASSWORD_TTL_MINUTES = 30;

    /**
     * Konfigurasi publik untuk aplikasi Flutter (reCAPTCHA, batasan form).
     */
    public function config(): JsonResponse
    {
        $apk = config('mobile.apk');

        return response()->json([
            'success' => true,
            'data' => [
                'app' => [
                    'api_base_url' => rtrim((string) config('app.url'), '/').'/api',
                    'public_url' => rtrim((string) config('app.url'), '/'),
                    'recaptcha_page' => route('mobile.recaptcha'),
                    'name' => config('app.name', 'AIoT SIMALAS'),
                ],
                'update' => [
                    'version_name' => (string) ($apk['version'] ?? '1.0.0'),
                    'version_code' => (int) ($apk['version_code'] ?? 1),
                    'min_version_code' => (int) ($apk['min_version_code'] ?? 1),
                    'download_url' => route('download'),
                    'apk_url' => route('download.apk'),
                    'changelog' => (string) ($apk['changelog'] ?? ''),
                    'force_update' => (bool) ($apk['force_update'] ?? false),
                ],
                'recaptcha' => [
                    'enabled' => Recaptcha::isConfigured(),
                    'site_key' => Recaptcha::isConfigured()
                        ? config('services.recaptcha.site_key')
                        : null,
                    'mobile_mode' => Recaptcha::isConfigured() ? 'image_captcha' : 'none',
                ],
                'register' => [
                    'min_password_length' => 6,
                    'requires_admin_approval' => true,
                    'open' => RegistrationGate::isOpen('mahasiswa'),
                    'closed_message' => RegistrationGate::isOpen('mahasiswa') ? null : RegistrationGate::closedMessage('mahasiswa'),
                ],
                'activation' => [
                    'url' => route('activation.show'),
                ],
                'program_studi' => MahasiswaProfileOptions::programStudiList(),
                'kelas' => MahasiswaProfileOptions::kelasOptions(),
                'kelas_by_prodi' => collect(MahasiswaProfileOptions::programStudiList())
                    ->mapWithKeys(fn (array $p) => [
                        (string) $p['id'] => MahasiswaProfileOptions::kelasForProgramStudi((int) $p['id']),
                    ])
                    ->all(),
                'pbl_tim' => MahasiswaProfileOptions::pblTeamOptions(),
                'pbl_projects' => MahasiswaProfileOptions::pblProjectOptions(),
            ],
        ]);
    }

    public function login(Request $request, LoginSecurityService $loginSecurity): JsonResponse
    {
        $validated = $request->validate([
            'nitk_nidn' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $nitk = trim((string) $validated['nitk_nidn']);
        $rawPassword = (string) $validated['password'];
        $guard = $loginSecurity->guardBeforeAttempt($request, $nitk);

        if ($guard['message']) {
            return response()->json([
                'success' => false,
                'message' => $guard['message'],
                'code' => 'LOGIN_BLOCKED',
            ], 429);
        }

        $user = $guard['user'];

        // 1. Transisi terkontrol untuk akun mahasiswa hasil import lama (menunggu_aktivasi).
        if ($user && $user->isMahasiswa() && $user->isAwaitingActivation()) {
            $legacy = AccountActivation::inspectLegacyStudentImportState($user);

            if (!$legacy['eligible']) {
                return $this->invalidCredentials($request, $loginSecurity, $user, $nitk, countAttempt: false);
            }

            if ($legacy['mode'] === 'personal_password' && Hash::check($rawPassword, (string) $user->password)) {
                $user = AccountActivation::transitionLegacyImportedMahasiswa($user, 'personal_password', $request);
            } elseif ($legacy['mode'] === 'temp_code' && Hash::check(AccountActivation::normalizeCode($rawPassword), (string) $user->password)) {
                $loginSecurity->recordSuccessfulLogin($user);

                return $this->activationRequiredResponse($user);
            } else {
                return $this->invalidCredentials($request, $loginSecurity, $user, $nitk);
            }
        } elseif ($user && $user->isAwaitingActivation() && AccountActivation::secretMatches($user, $rawPassword)) {
            $loginSecurity->recordSuccessfulLogin($user);

            return $this->activationRequiredResponse($user);
        }

        // 2. Mahasiswa aktif dengan password sementara wajib ganti password di web terlebih dahulu.
        if ($user && $user->isMahasiswa() && $user->mustChangePassword()) {
            if (!AccountActivation::temporaryPasswordMatches($user, $rawPassword)) {
                return $this->invalidCredentials($request, $loginSecurity, $user, $nitk);
            }

            if (($user->approval_status ?? 'pending') === 'rejected'
                || !$user->is_active
                || ($user->approval_status ?? 'pending') !== 'approved') {
                return $this->invalidCredentials($request, $loginSecurity, $user, $nitk, countAttempt: false);
            }

            $loginSecurity->recordSuccessfulLogin($user);

            return $this->passwordChangeRequiredResponse($user);
        }

        // Hash selalu diverifikasi (dummy saat akun tidak ada) agar waktu respons
        // tidak membocorkan keberadaan akun; pesan kegagalan seragam untuk semua
        // penyebab (akun tidak ada, password salah, role salah, status akun).
        if (!Hash::check($rawPassword, (string) ($user?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'))) {
            return $this->invalidCredentials($request, $loginSecurity, $user, $nitk);
        }

        if ($user->role !== 'mahasiswa') {
            return $this->invalidCredentials($request, $loginSecurity, $user, $nitk);
        }

        if (($user->approval_status ?? 'pending') === 'rejected'
            || !$user->is_active
            || ($user->approval_status ?? 'pending') !== 'approved') {
            return $this->invalidCredentials($request, $loginSecurity, $user, $nitk, countAttempt: false);
        }

        $loginSecurity->recordSuccessfulLogin($user);

        $plainToken = Str::random(64);

        $user->forceFill([
            'mobile_api_token' => hash('sha256', $plainToken),
            'mobile_token_expires_at' => now()->addMinutes(self::TOKEN_IDLE_MINUTES),
            'mobile_last_login_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $plainToken,
                'token_type' => 'Bearer',
                'expires_at' => $user->mobile_token_expires_at?->toIso8601String(),
                'email_verification_required' => !$user->email_verified_at,
                'needs_email' => blank($user->email),
                'user' => $this->serializeUser($user),
            ],
        ]);
    }

    /** Respons kegagalan seragam — tidak membedakan akun/password/role/status. */
    private function invalidCredentials(Request $request, LoginSecurityService $loginSecurity, ?User $user, string $nitk, bool $countAttempt = true): JsonResponse
    {
        $message = 'Tidak dapat masuk. Periksa data login Anda.';
        $status = 401;

        if ($countAttempt) {
            $failMessage = $loginSecurity->recordFailedAttempt($request, $user, $nitk);
            if (str_contains($failMessage, 'dikunci') || str_contains($failMessage, 'percobaan')) {
                $message = $failMessage;
                $status = str_contains($failMessage, 'dikunci') ? 429 : 401;
            }
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $status === 429 ? 'LOGIN_BLOCKED' : 'INVALID_CREDENTIALS',
        ], $status);
    }

    /** Kredensial benar tetapi akun hasil import belum diaktivasi: tanpa token, arahkan ke /aktivasi. */
    private function activationRequiredResponse(User $user): JsonResponse
    {
        $expired = AccountActivation::codeExpired($user);
        $message = $expired
            ? 'Akun belum aktif dan kode aktivasi sudah kedaluwarsa. Minta admin laboran membuat kode baru, lalu aktivasi di halaman web.'
            : 'Akun belum aktif penuh. Selesaikan aktivasi di halaman web, lalu kembali ke aplikasi dan login.';

        return response()->json([
            'success' => false,
            'code' => 'ACTIVATION_REQUIRED',
            'message' => $message,
            'data' => [
                'activation_url' => route('activation.show'),
                'code_expired' => $expired,
            ],
        ], 403);
    }

    /** Kredensial sementara mahasiswa benar tetapi wajib ganti password di web sebelum memakai token mobile. */
    private function passwordChangeRequiredResponse(User $user): JsonResponse
    {
        $expired = AccountActivation::temporaryPasswordExpired($user);
        $message = $expired
            ? 'Password sementara sudah kedaluwarsa. Minta admin laboran menerbitkan password sementara baru.'
            : 'Akun masih menggunakan password sementara. Silakan login di web SIMALAS untuk mengganti password terlebih dahulu.';

        return response()->json([
            'success' => false,
            'code' => 'PASSWORD_CHANGE_REQUIRED',
            'message' => $message,
            'data' => [
                'login_url' => route('login', ['role' => 'mahasiswa']),
                'activation_url' => route('login', ['role' => 'mahasiswa']),
                'code_expired' => $expired,
            ],
        ], 403);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');

        return response()->json([
            'success' => true,
            'data' => $this->serializeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');

        if ($user) {
            $user->forceFill([
                'mobile_api_token' => null,
                'mobile_token_expires_at' => null,
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function storePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        /** @var User $user */
        $user = $request->attributes->get('mobile_user');

        MobilePushToken::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $validated['token'],
            ],
            [
                'platform' => $validated['platform'] ?? 'android',
            ]
        );

        \Illuminate\Support\Facades\Log::info('FCM token registered', [
            'user_id' => $user->id,
            'nitk' => $user->nitk_nidn,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token push disimpan.',
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $this->pruneTokens();

        if ($request->filled('website')) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan ditolak.',
            ], 422);
        }

        if (!RegistrationGate::isOpen('mahasiswa')) {
            return response()->json([
                'success' => false,
                'code' => 'REGISTRATION_CLOSED',
                'message' => RegistrationGate::closedMessage('mahasiswa'),
            ], 403);
        }

        // Validasi data dulu — jangan habiskan CAPTCHA kalau email/NIM sudah terpakai.
        $validator = Validator::make($request->all(), [
            'nama_lengkap' => [
                'required',
                'string',
                'max:120',
                'regex:/^[\p{L}\s\'\.\-]+$/u',
            ],
            'nitk_nidn' => ['required', 'string', 'max:30', 'regex:/^[0-9]+$/', 'unique:users,nitk_nidn'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'max:128', 'confirmed', new StrongPassword],
            'kelas' => ['required', 'string', 'max:80'],
            'tim' => ['required', 'string', 'max:80'],
            'angkatan' => ['required', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'semester' => ['required', 'integer', 'min:1', 'max:8'],
            'semester_type' => ['required', 'string', 'in:ganjil,genap'],
        ], [
            'nama_lengkap.regex' => 'Nama hanya boleh huruf (boleh spasi, titik, apostrof, atau tanda hubung).',
            'nitk_nidn.regex' => 'NIM hanya boleh berisi angka.',
            'nitk_nidn.unique' => 'NIM sudah terdaftar. Pakai NIM lain atau lanjutkan verifikasi OTP jika Anda sudah mendaftar.',
            'email.unique' => 'Email sudah terdaftar. Pakai email lain atau login / lanjutkan verifikasi OTP.',
            'email.email' => 'Format email tidak valid.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $message = 'Data tidak valid.';
            if ($errors->has('email')) {
                $message = $errors->first('email');
            } elseif ($errors->has('nitk_nidn')) {
                $message = $errors->first('nitk_nidn');
            } elseif ($errors->has('password')) {
                $message = $errors->first('password');
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], 422);
        }

        if ($response = $this->validateMobileRecaptcha($request)) {
            return $response;
        }

        $email = strtolower($request->input('email'));
        $rateKey = 'mob-reg:' . $email;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }
        RateLimiter::hit($rateKey, 600);

        $kelas = $request->input('kelas');
        $programStudiId = $request->integer('program_studi_id');
        if (!$programStudiId) {
            $programStudiId = MahasiswaProfileOptions::inferProgramStudiId($kelas);
        }

        $user = User::create(array_merge([
            'nama_lengkap' => $request->input('nama_lengkap'),
            'nitk_nidn' => $request->input('nitk_nidn'),
            'email' => $email,
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
            'role' => 'mahasiswa',
            'kelas' => $kelas,
            'tim' => $request->input('tim'),
            'program_studi_id' => $programStudiId,
            'angkatan' => $request->integer('angkatan'),
            'semester' => $request->integer('semester'),
            'semester_type' => $request->input('semester_type'),
            'profile_completed_at' => now(),
            'is_active' => false,
            'approval_status' => 'pending',
        ], MahasiswaProfileOptions::syncKelasMetadata($kelas)));

        $this->recordMobileApprovalLog($request, $user, 'registered', 'Mahasiswa mendaftar dari aplikasi mobile.');

        $otp = $this->dispatchOtp($email);
        if (!$otp['mail_sent']) {
            return $this->otpMailFailedResponse($email);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk kode OTP verifikasi.',
            'data' => [
                'email' => $email,
                'expires_in_minutes' => self::OTP_TTL_MINUTES,
                'user_id' => $user->id,
                // OTP only returned in non-production to ease testing
                'otp_debug' => app()->environment(['local', 'testing']) ? $otp['code'] : null,
            ],
        ], 201);
    }

    public function verifyRegistration(Request $request): JsonResponse
    {
        $this->pruneTokens();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));
        $otp = $request->input('otp_code');

        $token = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'registration')
            ->where('otp_code', $otp)
            ->where('expires_at', '>', now())
            ->where('verified', false)
            ->orderByDesc('id')
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah atau sudah kedaluwarsa.',
            ], 422);
        }

        $user = User::where('email', $email)->where('role', 'mahasiswa')
            ->where($this->notAwaitingActivation(...))
            ->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($request, $user, $token) {
            if (empty($user->finger_id)) {
                $user->finger_id = FingerIdAllocator::assignNext();
            }
            MahasiswaShiftResolver::ensure($user, null, true);
            $user->is_active = false;
            $user->email_verified_at = now();
            $user->approval_status = $user->approval_status === 'rejected' ? 'rejected' : 'pending';
            $user->approval_note = null;
            $user->approved_by = null;
            $user->approved_at = null;
            $user->rejected_at = null;
            $user->save();

            DB::table('email_verification_tokens')
                ->where('id', $token->id)
                ->update(['verified' => true, 'updated_at' => now()]);

            DB::table('email_verification_tokens')
                ->where('email', $user->email)
                ->where('type', 'registration')
                ->where('id', '!=', $token->id)
                ->delete();

            $this->recordMobileApprovalLog(
                $request,
                $user,
                'email_verified',
                'Email diverifikasi OTP. Menunggu persetujuan admin untuk akses aplikasi.'
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil diverifikasi. Akun menunggu persetujuan admin sebelum bisa login ke aplikasi.',
            'data' => [
                'finger_id' => $user->finger_id,
                'user' => $this->serializeUser($user),
            ],
        ]);
    }

    public function resumeRegistration(Request $request): JsonResponse
    {
        $this->pruneTokens();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'resend' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));
        $user = User::where('email', $email)->where('role', 'mahasiswa')->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak terdaftar sebagai akun mahasiswa.',
            ], 404);
        }

        if ($user->isAwaitingActivation()) {
            return response()->json([
                'success' => false,
                'code' => 'ACTIVATION_REQUIRED',
                'message' => 'Akun ini dibuat admin dan diaktifkan lewat halaman Aktivasi Akun, bukan verifikasi pendaftaran.',
                'data' => ['activation_url' => route('activation.show')],
            ], 422);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email sudah diverifikasi. Silakan login atau tunggu persetujuan admin jika belum aktif.',
            ], 422);
        }

        if (($user->approval_status ?? 'pending') === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Registrasi akun ini telah ditolak admin. Hubungi laboran jika memerlukan bantuan.',
            ], 422);
        }

        $token = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'registration')
            ->where('verified', false)
            ->orderByDesc('id')
            ->first();

        $hasValidToken = $token && \Carbon\Carbon::parse($token->expires_at)->isFuture();
        $forceResend = $request->boolean('resend');
        $resent = false;
        $otpDebug = null;

        if (!$hasValidToken || $forceResend) {
            $rateKey = 'mob-resend:' . $email;
            if (RateLimiter::tooManyAttempts($rateKey, 1)) {
                $seconds = RateLimiter::availableIn($rateKey);
                return response()->json([
                    'success' => false,
                    'message' => "Mohon tunggu {$seconds} detik sebelum meminta kode verifikasi baru.",
                ], 429);
            }
            RateLimiter::hit($rateKey, 60);

            $dispatched = $this->dispatchOtp($email, $hasValidToken ? 'resend' : 'registration');
            if (!$dispatched['mail_sent']) {
                return $this->otpMailFailedResponse($email);
            }

            $otpDebug = $dispatched;
            $resent = true;
        }

        $message = $resent
            ? 'Kode verifikasi telah dikirim ulang ke email Anda. Silakan periksa kotak masuk atau folder spam.'
            : 'Registrasi menunggu verifikasi. Masukkan kode OTP yang telah dikirim ke email Anda.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'email' => $email,
                'resent' => $resent,
                'expires_in_minutes' => self::OTP_TTL_MINUTES,
                'otp_debug' => app()->environment(['local', 'testing']) ? ($otpDebug['code'] ?? null) : null,
            ],
        ]);
    }

    public function cancelRegistration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));

        DB::transaction(function () use ($email) {
            DB::table('email_verification_tokens')
                ->where('email', $email)
                ->where('type', 'registration')
                ->where('verified', false)
                ->delete();

            User::query()
                ->where('email', $email)
                ->where('role', 'mahasiswa')
                ->where('is_active', false)
                ->whereNull('email_verified_at')
                ->where($this->notAwaitingActivation(...))
                ->delete();
        });

        RateLimiter::clear('mob-reg:' . $email);
        RateLimiter::clear('mob-resend:' . $email);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi dibatalkan. Data sementara dan OTP sudah dihapus.',
        ]);
    }

    public function resendRegistrationOtp(Request $request): JsonResponse
    {
        $this->pruneTokens();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));
        $rateKey = 'mob-resend:' . $email;
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'success' => false,
                'message' => "Tunggu {$seconds} detik sebelum mengirim ulang.",
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $user = User::where('email', $email)->where('role', 'mahasiswa')
            ->where($this->notAwaitingActivation(...))
            ->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }
        if ($user->is_active) {
            return response()->json(['success' => false, 'message' => 'Akun sudah aktif.'], 422);
        }

        $otp = $this->dispatchOtp($email, 'resend');
        if (!$otp['mail_sent']) {
            return $this->otpMailFailedResponse($email);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode verifikasi baru telah dikirim ke email Anda.',
            'data' => [
                'expires_in_minutes' => self::OTP_TTL_MINUTES,
                'otp_debug' => app()->environment(['local', 'testing']) ? $otp['code'] : null,
            ],
        ]);
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        $this->pruneTokens();

        if ($request->filled('website')) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan ditolak.',
            ], 422);
        }

        $resend = $request->boolean('resend');

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'resend' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (!$resend && ($response = $this->validateMobileRecaptcha($request))) {
            return $response;
        }

        $email = strtolower($request->input('email'));
        $user = User::query()
            ->where('email', $email)
            ->where('role', 'mahasiswa')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email mahasiswa tidak ditemukan.',
            ], 404);
        }

        if ($user->isAwaitingActivation()) {
            return response()->json([
                'success' => false,
                'code' => 'ACTIVATION_REQUIRED',
                'message' => 'Akun ini belum diaktivasi. Selesaikan aktivasi di halaman Aktivasi Akun; reset password belum berlaku.',
                'data' => ['activation_url' => route('activation.show')],
            ], 422);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Email pada akun ini belum terverifikasi sehingga belum dapat digunakan untuk reset password mandiri. Hubungi admin/laboran untuk reset password sementara.',
            ], 422);
        }

        $pendingReset = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if ($resend) {
            if (!$pendingReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada permintaan reset aktif. Mulai dari awal atau tunggu OTP kedaluwarsa.',
                    'code' => 'RESET_NOT_ACTIVE',
                ], 422);
            }
        } elseif ($pendingReset) {
            // Kunci: jangan buat OTP baru selama masih aktif — masukkan OTP / batalkan / tunggu kadaluarsa.
            $expiresAt = \Carbon\Carbon::parse($pendingReset->expires_at);
            $secondsLeft = max(1, now()->diffInSeconds($expiresAt, false));
            $minutesLeft = max(1, (int) ceil($secondsLeft / 60));

            return response()->json([
                'success' => false,
                'message' => "Permintaan reset untuk email ini masih aktif (±{$minutesLeft} menit). Masukkan kode OTP yang sudah dikirim, atau batalkan untuk memulai ulang.",
                'code' => 'RESET_LOCKED',
                'data' => [
                    'email' => $email,
                    'expires_in_seconds' => $secondsLeft,
                    'locked' => true,
                ],
            ], 409);
        }

        $rateKey = 'mob-reset:' . $email;
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'success' => false,
                'message' => "Tunggu {$seconds} detik sebelum mengirim ulang OTP.",
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $otp = $this->dispatchPasswordResetOtp($user);
        if (!$otp['mail_sent']) {
            return $this->otpMailFailedResponse($email);
        }

        $message = $resend
            ? 'Kode OTP reset baru telah dikirim. Cek inbox atau folder spam.'
            : 'OTP reset password telah dikirim ke email Anda. Cek inbox atau folder spam.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'email' => $email,
                'resent' => $resend,
                'expires_in_minutes' => self::OTP_TTL_MINUTES,
                'otp_debug' => app()->environment(['local', 'testing']) ? $otp['code'] : null,
            ],
        ]);
    }

    public function cancelPasswordReset(Request $request): JsonResponse
    {
        $this->pruneTokens();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));

        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->delete();
        RateLimiter::clear('mob-reset:' . $email);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan reset password dibatalkan. Anda bisa memulai ulang.',
        ]);
    }

    public function verifyPasswordReset(Request $request): JsonResponse
    {
        $this->pruneTokens();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));
        $token = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->where('otp_code', $request->input('otp_code'))
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP reset salah atau sudah kedaluwarsa.',
            ], 422);
        }

        DB::table('email_verification_tokens')
            ->where('id', $token->id)
            ->update([
                'verified' => true,
                'expires_at' => now()->addMinutes(self::RESET_PASSWORD_TTL_MINUTES),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP valid. Silakan buat password baru.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->pruneTokens();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp_code' => ['nullable', 'digits:6'],
            'password' => ['required', 'string', 'max:128', 'confirmed', new StrongPassword],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->input('email'));
        $otpCode = $request->input('otp_code');

        $tokenQuery = DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->where('verified', true)
            ->where('expires_at', '>', now())
            ->orderByDesc('id');

        if (filled($otpCode)) {
            $tokenQuery->where('otp_code', $otpCode);
        }

        $token = $tokenQuery->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi reset password tidak valid. Ulangi dari awal.',
            ], 422);
        }

        $user = User::query()
            ->where('email', $email)
            ->where('role', 'mahasiswa')
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Email belum terverifikasi sehingga reset password mandiri tidak dapat dilakukan.',
            ], 422);
        }

        $payload = [
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
            'mobile_api_token' => null,
            'mobile_token_expires_at' => null,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'failed_login_attempts')) {
            $payload['failed_login_attempts'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'login_locked_until')) {
            $payload['login_locked_until'] = null;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'login_requires_admin_unlock')) {
            $payload['login_requires_admin_unlock'] = false;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'login_suspicious_at')) {
            $payload['login_suspicious_at'] = null;
        }
        $user->forceFill($payload)->save();
        AccountActivation::revokeActiveTokensAndSessions($user);

        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'password_reset')
            ->delete();
        RateLimiter::clear('mob-reset:' . $email);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Silakan login.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Perubahan data akademik hanya dapat dilakukan oleh admin/laboran melalui website SIMALAS.',
            'code' => 'PROFILE_EDIT_DISABLED',
        ], 403);
    }

    public function uploadProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $directory = public_path('uploads/profile-photos');
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $this->deleteProfilePhotoFile($user);
        $file = $request->file('photo');
        $filename = 'mobile-' . $user->id . '-' . Str::random(12) . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        $user->forceFill([
            'profile_photo_path' => 'uploads/profile-photos/' . $filename,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function deleteProfilePhoto(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Penghapusan foto profil tidak tersedia di aplikasi mobile. Gunakan Ganti Foto untuk memperbarui.',
            'code' => 'PHOTO_DELETE_DISABLED',
        ], 403);
    }

    /**
     * @return array{code: string, mail_sent: bool}
     */
    private function dispatchOtp(string $email, string $mailType = 'registration'): array
    {
        DB::table('email_verification_tokens')
            ->where('email', $email)
            ->where('type', 'registration')
            ->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('email_verification_tokens')->insert([
            'email' => $email,
            'token' => Str::random(64),
            'otp_code' => $otp,
            'type' => 'registration',
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cari nama user untuk mailable; kalau belum ada (kasus baru), pakai "Pengguna".
        $user = User::query()->where('email', $email)->first();
        $name = $user?->nama_lengkap ?? 'Pengguna';
        $mailSent = \App\Support\AsyncMailer::dispatchOtp($email, $otp, $name, $mailType);

        return ['code' => $otp, 'mail_sent' => $mailSent];
    }

    /**
     * @return array{code: string, mail_sent: bool}
     */
    private function dispatchPasswordResetOtp(User $user): array
    {
        DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->where('type', 'password_reset')
            ->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('email_verification_tokens')->insert([
            'email' => $user->email,
            'token' => Str::random(64),
            'otp_code' => $otp,
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailSent = \App\Support\AsyncMailer::dispatchOtp($user->email, $otp, $user->nama_lengkap, 'password_reset');

        return ['code' => $otp, 'mail_sent' => $mailSent];
    }

    private function otpMailFailedResponse(string $email): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Kode OTP gagal dikirim. Pastikan inbox email masih punya ruang, cek folder spam/promosi, lalu coba kirim ulang. Jika tetap gagal, hubungi laboran/admin untuk bantuan verifikasi.',
            'code' => 'OTP_MAIL_FAILED',
            'data' => ['email' => $email],
        ], 503);
    }

    private function deleteProfilePhotoFile(User $user): void
    {
        if (!$user->profile_photo_path) {
            return;
        }

        $path = public_path($user->profile_photo_path);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /** Akun hasil import (menunggu aktivasi) tidak boleh tersentuh alur pendaftaran mandiri. */
    private function notAwaitingActivation($query): void
    {
        $query->whereNull('approval_status')->orWhere('approval_status', '<>', AccountActivation::STATUS);
    }

    private function pruneTokens(): void
    {
        $expiredRegistrationEmails = DB::table('email_verification_tokens')
            ->where('type', 'registration')
            ->where('verified', false)
            ->where('expires_at', '<', now())
            ->pluck('email')
            ->filter()
            ->map(fn ($email) => strtolower((string) $email))
            ->unique()
            ->values();

        if ($expiredRegistrationEmails->isNotEmpty()) {
            User::query()
                ->whereIn('email', $expiredRegistrationEmails)
                ->where('role', 'mahasiswa')
                ->where('is_active', false)
                ->whereNull('email_verified_at')
                ->where($this->notAwaitingActivation(...))
                ->delete();
        }

        DB::table('email_verification_tokens')
            ->where(function ($q) {
                $q->where('expires_at', '<', now())
                    ->orWhere(function ($qb) {
                        $qb->where('verified', true)
                            ->where('updated_at', '<', now()->subDay());
                    });
            })
            ->delete();
    }

    public function submitAccountEmail(Request $request): JsonResponse
    {
        $user = $this->resolveAccountEmailUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'NIM atau password salah.'], 401);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:120', 'unique:users,email,' . $user->id, new NotDisposableEmail],
        ]);

        return $this->processSubmitAccountEmail($request, $user, $validated['email']);
    }

    public function submitMyAccountEmail(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Tidak terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:120', 'unique:users,email,' . $user->id, new NotDisposableEmail],
        ]);

        return $this->processSubmitAccountEmail($request, $user, $validated['email']);
    }

    public function verifyAccountEmail(Request $request): JsonResponse
    {
        $user = $this->resolveAccountEmailUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'NIM atau password salah.'], 401);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        return $this->processVerifyAccountEmail($request, $user, $validated['email'], $validated['otp_code']);
    }

    public function verifyMyAccountEmail(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Tidak terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        return $this->processVerifyAccountEmail($request, $user, $validated['email'], $validated['otp_code']);
    }

    public function resendAccountVerification(Request $request): JsonResponse
    {
        $user = $this->resolveAccountEmailUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'NIM atau password salah.'], 401);
        }

        return $this->processResendAccountVerification($user);
    }

    public function resendMyAccountVerification(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Tidak terautentikasi.'], 401);
        }

        return $this->processResendAccountVerification($user);
    }

    private function processSubmitAccountEmail(Request $request, User $user, string $rawEmail): JsonResponse
    {
        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'message' => 'Email sudah diverifikasi.'], 422);
        }

        $email = AccountEmailVerification::normalizeEmail($rawEmail);
        $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();

        try {
            $message = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 429);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['email' => $email],
        ]);
    }

    private function processVerifyAccountEmail(
        Request $request,
        User $user,
        string $rawEmail,
        string $otpCode
    ): JsonResponse {
        $email = AccountEmailVerification::normalizeEmail($rawEmail);
        if ($email !== AccountEmailVerification::normalizeEmail((string) $user->email)) {
            return response()->json(['success' => false, 'message' => 'Email tidak sesuai dengan akun.'], 422);
        }

        $verified = AccountEmailVerification::verify($email, $otpCode);
        if (!$verified || $verified->id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Kode OTP salah atau sudah kedaluwarsa.'], 422);
        }

        $this->recordMobileApprovalLog($request, $verified, 'email_verified', 'Email diverifikasi mandiri via aplikasi mobile.');

        $message = $verified->is_active && ($verified->approval_status ?? 'pending') === 'approved'
            ? 'Email berhasil diverifikasi.'
            : 'Email berhasil diverifikasi. Akun menunggu persetujuan admin sebelum bisa login.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['user' => $this->serializeUser($verified)],
        ]);
    }

    private function processResendAccountVerification(User $user): JsonResponse
    {
        if (blank($user->email)) {
            return response()->json(['success' => false, 'message' => 'Email belum diisi.'], 422);
        }

        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'message' => 'Email sudah diverifikasi.'], 422);
        }

        try {
            $message = AccountEmailVerification::send($user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 429);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['email' => $user->email],
        ]);
    }

    private function resolveAccountEmailUser(Request $request): ?User
    {
        $mobileUser = $request->attributes->get('mobile_user');
        if ($mobileUser instanceof User) {
            return $mobileUser;
        }

        $validated = $request->validate([
            'nitk_nidn' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $candidate = User::query()->where('nitk_nidn', trim($validated['nitk_nidn']))->where('role', 'mahasiswa')->first();
        if ($candidate?->isAwaitingActivation() && AccountActivation::secretMatches($candidate, $validated['password'])) {
            abort($this->activationRequiredResponse($candidate));
        }
        if ($candidate?->mustChangePassword() && AccountActivation::temporaryPasswordMatches($candidate, $validated['password'])) {
            abort($this->passwordChangeRequiredResponse($candidate));
        }

        $user = $this->resolveMahasiswaByCredentials($validated['nitk_nidn'], $validated['password']);
        if ($user?->isAwaitingActivation()) {
            abort($this->activationRequiredResponse($user));
        }

        return $user;
    }

    private function resolveMahasiswaByCredentials(string $nitk, string $password): ?User
    {
        $user = User::query()
            ->where('nitk_nidn', trim($nitk))
            ->where('role', 'mahasiswa')
            ->first();

        if (!$user || $user->mustChangePassword() || !Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    private function serializeUser(User $user): array
    {
        $user->loadMissing('programStudi');

        $hasFingerId = !empty($user->finger_id);
        $hasTemplate = \App\Support\FingerprintTemplateCodec::hasValidTemplate($user->finger_template);
        $fingerprintStatus = match (true) {
            $hasTemplate => 'Sudah Enroll',
            $hasFingerId => 'Belum Enroll',
            default => 'Belum Assign Finger ID',
        };

        $ketuaProject = $user->isMahasiswa() ? PblTeamService::ketuaProjectFor($user) : null;
        $pblProject = $user->isMahasiswa() ? PblTeamService::projectForMahasiswa($user) : null;
        $pblMembership = $user->isMahasiswa() ? PblTeamService::membershipFor($user) : null;

        return [
            'id' => $user->id,
            'nama_lengkap' => $user->nama_lengkap,
            'nitk_nidn' => $user->nitk_nidn,
            'email' => $user->email,
            'role' => $user->role,
            'kelas' => $user->kelas,
            'tim' => $user->tim,
            'has_pbl_team' => $pblProject !== null,
            'pbl_role' => $pblMembership?->role,
            'pbl_is_ketua' => $ketuaProject !== null,
            'pbl_project_id' => $pblProject?->id,
            'pbl_project_nama' => $pblProject?->nama,
            'angkatan' => $user->angkatan,
            'semester' => $user->semester ?? null,
            'semester_type' => $user->semester_type,
            'program_studi_id' => $user->program_studi_id,
            'prodi' => $user->programStudi?->nama,
            'profile_completed' => $user->hasCompleteStudentProfile(),
            'status_mahasiswa' => $user->status_mahasiswa ?? 'aktif',
            'finger_id' => $user->finger_id,
            'fingerprint_status' => $fingerprintStatus,
            'profile_photo_url' => $user->profile_photo_path
                ? url($user->profile_photo_path)
                : null,
            'updated_at' => optional($user->updated_at)->toIso8601String(),
            'is_active' => (bool) $user->is_active,
            'approval_status' => $user->approval_status ?? 'pending',
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            'email_verification_required' => !$user->email_verified_at,
            'needs_email' => blank($user->email),
            'last_login_at' => optional($user->mobile_last_login_at)->toIso8601String(),
        ];
    }

    /**
     * reCAPTCHA Google (Android/iOS) atau checkbox mobile_robot_ack (Windows/desktop tanpa WebView).
     */
    private function validateMobileRecaptcha(Request $request): ?JsonResponse
    {
        if (!Recaptcha::isConfigured()) {
            return null;
        }

        if ($request->filled('captcha_token') && $request->filled('captcha_answer')) {
            if (!MobileImageCaptcha::verify(
                $request->input('captcha_token'),
                $request->input('captcha_answer')
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode pada gambar CAPTCHA salah atau sudah kedaluwarsa. Muat ulang gambar lalu coba lagi.',
                    'code' => 'CAPTCHA_INVALID',
                    'errors' => ['captcha_answer' => ['Kode CAPTCHA tidak valid.']],
                ], 422);
            }

            return null;
        }

        if ($request->filled('g_recaptcha_response')) {
            $captcha = Recaptcha::verify(
                $request->input('g_recaptcha_response'),
                $request->ip()
            );
            if (!$captcha['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $captcha['message'],
                    'errors' => ['g_recaptcha_response' => [$captcha['message']]],
                ], 422);
            }

            return null;
        }

        if ($request->boolean('mobile_robot_ack')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Centang "Saya bukan robot" terlebih dahulu.',
            'errors' => ['g_recaptcha_response' => ['Centang "Saya bukan robot" terlebih dahulu.']],
        ], 422);
    }

    private function recordMobileApprovalLog(Request $request, User $user, string $action, ?string $note = null): void
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
            'actor_id' => null,
            'action' => $action,
            'note' => $note,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);
    }
}
