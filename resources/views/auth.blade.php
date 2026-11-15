<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Portal — {{ \App\Support\Branding::appName() }}</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/public-premium.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('css/auth-luxury.css') }}?v=22">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    @if(\App\Support\Recaptcha::isConfigured())
    <script>window.SIMALAS_RECAPTCHA_SITE_KEY = @json(config('services.recaptcha.site_key'));</script>
    <script src="{{ asset('js/recaptcha-forms.js') }}?v=4"></script>
    <script src="https://www.google.com/recaptcha/api.js?onload=simalasRecaptchaReady&amp;render=explicit&amp;hl=id" async defer></script>
    @endif
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--navy:#0f172a;--navy-light:#334155;--soft-blue:#334155;--brand-orange:#f97316;--teal:#ea580c;--bg:#f8fafc;--bg2:#f1f5f9;--card:#ffffff;--input-bg:#f1f5f9;--input-border:#e2e8f0;--text:#1e293b;--text-muted:#64748b;--label:#475569;--shadow:0 16px 48px rgba(15,23,42,.08);--radius:16px;--ri:12px;--font:'Inter',-apple-system,sans-serif;--tr:all .3s cubic-bezier(.4,0,.2,1)}
        html,body{height:100%;font-family:var(--font);color:var(--text)}
        body.auth-split-page{overflow-x:clip;min-height:100vh;margin:0}
        .auth-card{width:100%;max-width:520px;background:var(--card);border-radius:var(--radius);box-shadow:0 16px 48px rgba(15,23,42,.08),0 0 0 1px rgba(148,163,184,.18);overflow:hidden}
        .card-body{padding:28px 32px 26px}
        .card-header{margin-bottom:22px}
        .card-header--plain .card-header-text{text-align:left}
        .card-header-text h2{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px;letter-spacing:-.02em}
        .card-header-text p{font-size:13px;color:var(--text-muted);line-height:1.5}
        .form-group{margin-bottom:16px}
        .forgot-email-hint{display:flex;gap:12px;align-items:flex-start;padding:13px 15px;border-radius:var(--ri);border:1.5px solid #dbe4f0;background:#f6f9fe;animation:forgotHintIn .28s ease-out}
        .forgot-email-hint[data-state="found"]{border-color:#bbf7d0;background:#f0fdf6}
        .forgot-email-hint[data-state="warn"]{border-color:#fde68a;background:#fffbeb}
        .forgot-email-hint[data-state="error"]{border-color:#fecaca;background:#fef2f2}
        .forgot-email-hint-icon{width:20px;height:20px;margin-top:2px;color:#64748b;flex-shrink:0}
        .forgot-email-hint[data-state="found"] .forgot-email-hint-icon{color:#16a34a}
        .forgot-email-hint[data-state="warn"] .forgot-email-hint-icon{color:#d97706}
        .forgot-email-hint[data-state="error"] .forgot-email-hint-icon{color:#dc2626}
        .forgot-email-hint-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
        .forgot-email-hint-value{font-size:15px;font-weight:700;color:var(--text);font-family:'JetBrains Mono',monospace;margin-top:2px;word-break:break-all}
        .forgot-email-hint-sub{font-size:12px;color:var(--text-muted);margin-top:3px}
        .forgot-email-hint[data-state="checking"] .forgot-email-hint-value{color:#64748b}
        @keyframes forgotHintIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
        .btn-primary:disabled,.btn-primary.btn-primary--locked{opacity:.55;cursor:not-allowed;filter:saturate(.7)}
        @media (prefers-reduced-motion: reduce){.forgot-email-hint{animation:none}}
        .form-row{display:flex;gap:12px}
        .form-row .form-group{flex:1}
        .form-label{display:block;font-size:13px;font-weight:600;color:var(--label);margin-bottom:6px}
        .form-input{width:100%;padding:12px 14px;font-size:14px;font-family:var(--font);background:var(--input-bg);border:1.5px solid transparent;border-radius:var(--ri);color:var(--text);transition:var(--tr);outline:none}
        .form-input::placeholder{color:#94a3b8;font-weight:400}
        .form-input:focus{border-color:#94a3b8;background:#fff;box-shadow:0 0 0 3.5px rgba(148,163,184,.25)}
        .select-field{position:relative}
        .form-select{appearance:none;-webkit-appearance:none;padding-right:42px;cursor:pointer;font-weight:600}
        .select-chevron{position:absolute;right:14px;top:50%;transform:translateY(-50%);pointer-events:none;color:#64748b;display:inline-flex;align-items:center}
        .select-chevron svg,.select-chevron [data-lucide]{width:18px;height:18px}
        .role-hint{font-size:12px;color:var(--text-muted);margin-top:6px;line-height:1.45}
        .password-field{position:relative}
        .password-field .form-input{padding-right:44px}
        .password-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#64748b;cursor:pointer;font-size:17px;line-height:1;padding:4px;border-radius:6px;transition:var(--tr)}
        .password-toggle:hover{color:#0f172a;background:rgba(148,163,184,.15)}
        .otp-wrapper{display:flex;gap:10px;justify-content:center;margin:8px 0 4px}
        .otp-input{width:48px;height:54px;text-align:center;font-size:22px;font-weight:700;font-family:var(--font);background:var(--input-bg);border:1.5px solid transparent;border-radius:var(--ri);color:var(--text);transition:var(--tr);outline:none}
        .otp-input:focus{border-color:#94a3b8;background:#fff;box-shadow:0 0 0 3px rgba(148,163,184,.25)}
        .btn-primary{width:100%;padding:13px;font-size:15px;font-weight:700;font-family:var(--font);color:#fff;background:linear-gradient(135deg,#e11d48,#f97316);border:none;border-radius:var(--ri);cursor:pointer;transition:var(--tr);box-shadow:0 4px 18px rgba(225,29,72,.35);display:flex;align-items:center;justify-content:center;gap:8px;margin-top:6px;letter-spacing:.01em}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(249,115,22,.45)}
        .btn-primary:active{transform:translateY(0)}
        .card-footer-text{text-align:center;margin-top:16px;font-size:13px;color:var(--text-muted);line-height:1.5}
        .link-btn{background:none;border:none;color:#e11d48;font-weight:600;font-size:13px;cursor:pointer;font-family:var(--font);text-decoration:none;transition:var(--tr)}
        .link-btn:hover{color:#9f1239;text-decoration:underline}
        .link-btn:disabled{opacity:.55;cursor:not-allowed;text-decoration:none;color:var(--text-muted)}
        .alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;display:flex;align-items:center;gap:8px;animation:alertIn .3s ease}
        @keyframes alertIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}
        .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
        .auth-panel{display:none;flex:1;flex-direction:column}
        .auth-panel.active{display:flex;animation:panelFade .35s ease}
        @keyframes panelFade{from{opacity:0;transform:translateX(8px)}to{opacity:1;transform:translateX(0)}}
        .remember-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
        .remember-label{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--label);cursor:pointer}
        .remember-label input{accent-color:#334155}
        .form-error{margin-top:6px;font-size:12px;color:#e11d48;font-weight:600;line-height:1.4}
        body.auth-theme-dark .select-chevron{color:#94a3b8}
        body.auth-theme-dark .form-select option{background:#1e1e1e;color:#f8fafc}
        @media(max-width:640px){.form-row{flex-direction:column;gap:0}.auth-card{max-width:100%;border-radius:12px}}
    </style>
</head>
<body class="auth auth-simple auth-luxury auth-split-page" @if($errors->any()) data-auth-has-errors="1" @endif>
    @php
        $activeRole = old('role', $selectedRole ?? request()->query('role', 'mahasiswa'));
        if (!in_array($activeRole, ['mahasiswa', 'dosen', 'laboran', 'admin'], true)) {
            $activeRole = 'mahasiswa';
        }
    @endphp
    <div class="auth-split">
        <aside class="auth-split-hero" style="--auth-hero-img: url('{{ asset('images/campus-polybatam.jpg') }}')">
            <div class="auth-split-hero-overlay" aria-hidden="true"></div>
            <div class="auth-split-hero-content auth-hero-anim-pending">
                <div class="auth-hero-logo-wrap">
                    <img
                        class="auth-hero-logo-static"
                        src="{{ \App\Support\Branding::logoUrlOr(asset('branding/aiot-simalas-logo-lockup-s.png')) }}"
                        alt="{{ \App\Support\Branding::appName() }}"
                    >
                </div>
                <p class="auth-hero-eyebrow" data-id="POLITEKNIK NEGERI BATAM" data-en="POLYTECHNIC OF BATAM">POLITEKNIK NEGERI BATAM</p>
                <h1 class="auth-hero-title auth-hero-anim auth-hero-anim-1">
                    <span class="auth-hero-title-flip" id="authHeroTitleFlip" aria-label="{{ \App\Support\Branding::appName() }}">{{ \App\Support\Branding::appName() }}</span>
                </h1>
                <p class="auth-hero-subtitle auth-hero-anim auth-hero-anim-2" data-id="Smart Laboratory Management System" data-en="Smart Laboratory Management System">Smart Laboratory Management System</p>
                <p class="auth-hero-desc auth-hero-anim auth-hero-anim-3" data-id="Platform terpusat untuk mahasiswa, dosen, dan laboran — absensi biometrik dan pengelolaan laboratorium." data-en="Centralized platform for students, lecturers, and lab admins — biometric attendance and laboratory management.">
                    Platform terpusat untuk mahasiswa, dosen, dan laboran — absensi biometrik dan pengelolaan laboratorium.
                </p>
                <ul class="auth-hero-features auth-hero-anim auth-hero-anim-4" aria-label="Fitur utama">
                    <li class="auth-hero-feature">
                        <span class="auth-hero-feature-icon" aria-hidden="true"><i data-lucide="fingerprint"></i></span>
                        <span data-id="Absensi Biometrik" data-en="Biometric Attendance">Absensi Biometrik</span>
                    </li>
                    <li class="auth-hero-feature">
                        <span class="auth-hero-feature-icon" aria-hidden="true"><i data-lucide="shield-check"></i></span>
                        <span data-id="Portal Terintegrasi" data-en="Integrated Portal">Portal Terintegrasi</span>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="auth-split-form">
        <div class="auth-luxury-shell">
        <div class="auth-mobile-brand" aria-hidden="false">
            <img
                src="{{ \App\Support\Branding::logoUrlOr(asset('branding/aiot-simalas-logo-lockup-s.png')) }}"
                alt="{{ \App\Support\Branding::appName() }}"
            >
        </div>
        <div class="auth-card auth-stagger-pending" id="authCard">
            <div class="card-body">
                {{-- ══════ LOGIN PANEL ══════ --}}
                <div class="auth-panel active" id="panelLogin">
                    <div class="card-header card-header--plain auth-stagger-item auth-stagger-1">
                        <div class="card-header-text">
                            <h2 data-id="Selamat Datang Kembali 👋" data-en="Welcome Back 👋">Selamat Datang Kembali 👋</h2>
                            <p data-id="Pilih tipe pengguna dan masukkan kredensial Anda untuk melanjutkan ke portal." data-en="Select your user role and enter your credentials to continue.">Pilih tipe pengguna dan masukkan kredensial Anda untuk melanjutkan ke portal.</p>
                        </div>
                    </div>
                    @error('registration')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                    @error('email')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                    <form method="POST" action="{{ route('login.post') }}">
                        @csrf
                        <div class="form-group auth-stagger-item auth-stagger-2">
                            <label class="form-label" for="loginRoleSelect" data-id="Masuk Sebagai" data-en="Sign in As">Masuk Sebagai</label>
                            <div class="select-field">
                                <select name="role" id="loginRoleSelect" class="form-input form-select" required>
                                    <option value="admin" @selected($activeRole === 'admin')>Admin</option>
                                    <option value="laboran" @selected($activeRole === 'laboran')>Laboran</option>
                                    <option value="dosen" @selected($activeRole === 'dosen')>Dosen</option>
                                    <option value="mahasiswa" @selected($activeRole === 'mahasiswa')>Mahasiswa</option>
                                </select>
                                <span class="select-chevron" aria-hidden="true"><i data-lucide="chevron-down"></i></span>
                            </div>
                            <p class="role-hint" id="loginRoleHint">Akses portal mahasiswa: absensi pribadi, tim PBL, dan notifikasi.</p>
                            @error('role')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group auth-stagger-item auth-stagger-3">
                            <label class="form-label" id="loginIdLabel" for="loginIdInput">NIM</label>
                            <input type="text" name="nitk_nidn" id="loginIdInput" class="form-input" placeholder="Masukkan NIM" value="{{ old('nitk_nidn') }}" required autocomplete="username" maxlength="50">
                            @error('nitk_nidn')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group auth-stagger-item auth-stagger-4">
                            <label class="form-label" for="loginPassword" data-id="Password" data-en="Password">Password</label>
                            <div class="password-field">
                                <input type="password" name="password" id="loginPassword" class="form-input"
                                    placeholder="Masukkan password"
                                    data-id-placeholder="Masukkan password"
                                    data-en-placeholder="Enter password" required autocomplete="current-password" maxlength="128">
                                <button type="button" class="password-toggle" data-target="password"
                                    aria-label="Tampilkan password"
                                    data-id-aria="Tampilkan password"
                                    data-en-aria="Show password"><i data-lucide="eye"></i></button>
                            </div>
                            @error('password')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="remember-row auth-stagger-item auth-stagger-5">
                            <label class="remember-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> <span data-id="Ingat saya" data-en="Remember me">Ingat saya</span></label>
                            <button type="button" class="link-btn" data-switch-panel="forgot" data-id="Lupa password?" data-en="Forgot password?">Lupa password?</button>
                        </div>
                        <button type="submit" class="btn-primary btn-login-submit auth-stagger-item auth-stagger-6" id="loginSubmitBtn">
                            <span class="login-btn-label"><i data-lucide="log-in"></i><span data-id="Masuk" data-en="Sign in">Masuk</span></span>
                            <span class="login-btn-spinner" aria-hidden="true"><span class="login-btn-spinner-ring"></span></span>
                        </button>
                    </form>
                    <div class="card-footer-text auth-stagger-item auth-stagger-7">
                        Akun dibuat oleh admin/laboran. Lupa password? Gunakan pemulihan email di atas atau hubungi laboran.
                    </div>
                </div>

                {{-- ══════ TAMBAH EMAIL (AKUN ADMIN TANPA EMAIL) ══════ --}}
                <div class="auth-panel" id="panelAddEmail">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Lengkapi Email" data-en="Add Your Email">Lengkapi Email</h2>
                            <p data-id="Akun Anda belum memiliki email. Masukkan email aktif untuk menerima kode verifikasi OTP." data-en="Your account has no email yet. Enter an active email to receive an OTP verification code.">Akun Anda belum memiliki email. Masukkan email aktif untuk menerima kode verifikasi OTP.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('account-email.post') }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" data-id="Email Aktif" data-en="Active Email">Email Aktif</label>
                            <input type="email" name="email" class="form-input"
                                placeholder="nama@email.com"
                                data-id-placeholder="nama@email.com"
                                data-en-placeholder="name@email.com"
                                value="{{ old('email') }}" required autocomplete="email">
                        </div>
                        <button type="submit" class="btn-primary" data-id="Kirim Kode Verifikasi" data-en="Send Verification Code"><i data-lucide="mail"></i><span>Kirim Kode Verifikasi</span></button>
                    </form>
                    <div class="card-footer-text">
                        <form method="POST" action="{{ route('cancel-account-verification.post') }}" style="display:inline">
                            @csrf
                            <button type="submit" class="link-btn" data-id="Batalkan & kembali login" data-en="Cancel & back to login">Batalkan & kembali login</button>
                        </form>
                    </div>
                </div>

                {{-- ══════ VERIFIKASI EMAIL AKUN (LOGIN PERTAMA) ══════ --}}
                <div class="auth-panel" id="panelAccountVerify">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Verifikasi Email Akun" data-en="Verify Account Email">Verifikasi Email Akun</h2>
                            <p data-id="Email belum diverifikasi. Masukkan 6 digit kode OTP yang dikirim ke email Anda." data-en="Email not verified yet. Enter the 6-digit OTP sent to your email.">Email belum diverifikasi. Masukkan 6 digit kode OTP yang dikirim ke email Anda.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('verify-account-email.post') }}" id="accountOtpForm">
                        @csrf
                        <input type="hidden" name="email" value="{{ session('account_verify_email', old('email')) }}">
                        <input type="hidden" name="otp_code" id="accountOtpHidden">
                        <div class="form-group">
                            <label class="form-label" data-id="Email" data-en="Email">Email</label>
                            <input type="email" class="form-input" value="{{ session('account_verify_email', old('email')) }}" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label" data-id="Kode OTP (6 Digit)" data-en="OTP Code (6 Digits)">Kode OTP (6 Digit)</label>
                            <div class="otp-wrapper">
                                <input type="text" class="otp-input account-otp" maxlength="1" data-idx="0" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input account-otp" maxlength="1" data-idx="1" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input account-otp" maxlength="1" data-idx="2" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input account-otp" maxlength="1" data-idx="3" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input account-otp" maxlength="1" data-idx="4" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input account-otp" maxlength="1" data-idx="5" inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" data-id="Verifikasi & Lanjutkan" data-en="Verify & Continue"><i data-lucide="badge-check"></i><span>Verifikasi & Lanjutkan</span></button>
                    </form>
                    <div class="card-footer-text">
                        <form method="POST" action="{{ route('resend-account-verification.post') }}" style="display:inline">
                            @csrf
                            <button type="submit" class="link-btn" id="accountResendBtn" data-id="Kirim ulang kode" data-en="Resend code">Kirim ulang kode</button>
                        </form>
                        &nbsp;|&nbsp;
                        <form method="POST" action="{{ route('cancel-account-verification.post') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('account_verify_email', old('email')) }}">
                            <button type="submit" class="link-btn" data-id="Batalkan" data-en="Cancel">Batalkan</button>
                        </form>
                    </div>
                </div>

                {{-- ══════ FORGOT PASSWORD PANEL ══════ --}}
                <div class="auth-panel" id="panelForgot">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Lupa Password" data-en="Forgot Password">Lupa Password</h2>
                            <p data-id="Masukkan NIM atau username Anda — kami tampilkan email yang terikat (disamarkan) untuk Anda konfirmasi." data-en="Enter your student ID or username — we'll show the bound email (masked) for you to confirm.">Masukkan NIM atau username Anda — kami tampilkan email yang terikat (disamarkan) untuk Anda konfirmasi.</p>
                        </div>
                    </div>
                    @if(session('reset_link_sent'))
                        <div class="alert alert-success">{{ session('success') ?? 'Jika akun Anda terdaftar dan memiliki email terikat, tautan sudah dikirim.' }}</div>
                    @endif
                    <form method="POST" action="{{ route('forgot-password.post') }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" data-id="NIM / Username" data-en="ID / Username">NIM / Username</label>
                            <input type="text" name="identifier" id="forgotIdentifier" class="form-input"
                                placeholder="NIM atau username akun"
                                data-id-placeholder="NIM atau username akun"
                                data-en-placeholder="Student ID or account username" value="{{ old('identifier') }}" required autocomplete="username" maxlength="255">
                            @error('identifier')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group" id="forgotEmailHint" hidden>
                            <div class="forgot-email-hint" data-state="checking">
                                <i data-lucide="mail-check" class="forgot-email-hint-icon"></i>
                                <div>
                                    <p class="forgot-email-hint-label" data-id="Email terdaftar" data-en="Registered email">Email terdaftar</p>
                                    <p class="forgot-email-hint-value" id="forgotEmailMasked">…</p>
                                    <p class="forgot-email-hint-sub" id="forgotEmailSub" data-id="Benar email Anda?" data-en="Is this your email?">Benar email Anda?</p>
                                </div>
                            </div>
                        </div>
                        @include('partials.recaptcha', ['slot' => 'forgot'])
                        <div class="hp-field" aria-hidden="true">
                            <label>Website</label>
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <button type="submit" id="forgotSendBtn" class="btn-primary" data-id="Kirim Tautan Reset" data-en="Send Reset Link" disabled><i data-lucide="send"></i><span>Kirim Tautan Reset</span></button>
                        <p class="role-hint" style="margin-top:10px">
                            Pemulihan via WhatsApp / OTP nomor HP — <strong>Segera hadir</strong> (belum tersedia).
                        </p>
                    </form>
                    <div class="card-footer-text"
                        data-id-html='<button type="button" class="link-btn" data-switch-panel="login"><strong>Kembali ke Login</strong></button>'
                        data-en-html='<button type="button" class="link-btn" data-switch-panel="login"><strong>Back to Sign in</strong></button>'>
                        <button type="button" class="link-btn" data-switch-panel="login"><strong>Kembali ke Login</strong></button>
                    </div>
                </div>
            </div>
        </div>
        </div>
            <p class="auth-split-copy"
                data-id="Copyright © SIMALAS {{ date('Y') }}"
                data-en="Copyright © SIMALAS {{ date('Y') }}">Copyright © SIMALAS {{ date('Y') }}</p>
        </main>
    </div>

    <script>
        window.simalasShowLoader = function() {};
        window.simalasHideLoader = function() {};

        (function(){
            window.simalasLang = 'id';
            document.documentElement.lang = 'id';
            const applyLang = (lang) => {
                window.simalasLang = lang;
                document.querySelectorAll('[data-id]').forEach(el => {
                    const v = el.getAttribute('data-' + lang);
                    if (v != null) el.textContent = v;
                });
                document.querySelectorAll('[data-id-html]').forEach(el => {
                    const v = el.getAttribute('data-' + lang + '-html');
                    if (v != null) el.innerHTML = v;
                });
                document.querySelectorAll('[data-id-placeholder]').forEach(el => {
                    const v = el.getAttribute('data-' + lang + '-placeholder');
                    if (v != null) el.setAttribute('placeholder', v);
                });
                document.querySelectorAll('[data-id-title]').forEach(el => {
                    const v = el.getAttribute('data-' + lang + '-title');
                    if (v != null) el.setAttribute('title', v);
                });
                document.querySelectorAll('[data-id-aria]').forEach(el => {
                    const v = el.getAttribute('data-' + lang + '-aria');
                    if (v != null) el.setAttribute('aria-label', v);
                });
                document.dispatchEvent(new CustomEvent('simalas:lang-change', { detail: { lang } }));
            };
            applyLang('id');

            window.simalasI18n = {
                id: {
                    confirmForgotTitle:  'Kirim Tautan Reset?',
                    confirmForgotText:   'Tautan atur ulang password dikirim ke email Anda bila NIM/username dan email cocok.',
                    confirmForgotBtn:    'Ya, Kirim Tautan',
                    confirmResetTitle:   'Simpan Password Baru?',
                    confirmResetText:    'Password Anda akan diubah. Pastikan Anda mengingat password baru.',
                    confirmResetBtn:     'Ya, Simpan',
                    cancel:              'Batal',
                    successTitle:        'Berhasil!',
                    errorTitle:          'Oops!',
                    loaderOtp:           'Memeriksa akun & mengirim tautan',
                    loaderReset:         'Menyimpan password',
                    loginLabelMhs:       'NIM',
                    loginPhMhs:          'Masukkan NIM',
                    loginHintMhs:        'Akses portal mahasiswa: absensi pribadi, tim PBL, dan notifikasi.',
                    loginLabelDosen:     'NITK / NIDN',
                    loginPhDosen:        'Masukkan NITK/NIDN',
                    loginHintDosen:      'Akses portal dosen: rekap absensi, proyek PBL, dan pelaporan.',
                    loginLabelLab:       'Username / NITK',
                    loginPhLab:          'Masukkan username atau NITK admin',
                    loginHintLab:        'Akses portal admin laboran: manajemen pengguna, perangkat, dan absensi.',
                },
                en: {
                    confirmForgotTitle:  'Send Reset Link?',
                    confirmForgotText:   'A password reset link is sent to your email if the ID and email match.',
                    confirmForgotBtn:    'Yes, Send Link',
                    confirmResetTitle:   'Save New Password?',
                    confirmResetText:    'Your password will be changed. Make sure to remember it.',
                    confirmResetBtn:     'Yes, Save',
                    cancel:              'Cancel',
                    successTitle:        'Success!',
                    errorTitle:          'Oops!',
                    loaderOtp:           'Checking account & sending link',
                    loaderReset:         'Saving password',
                    loginLabelMhs:       'Student ID (NIM)',
                    loginPhMhs:          'Enter NIM',
                    loginHintMhs:        'Student portal: personal attendance, PBL team, and notifications.',
                    loginLabelDosen:     'NITK / NIDN',
                    loginPhDosen:        'Enter NITK/NIDN',
                    loginHintDosen:      'Lecturer portal: attendance recap, PBL projects, and reports.',
                    loginLabelLab:       'Username / NITK',
                    loginPhLab:          'Enter admin username or NITK',
                    loginHintLab:        'Lab admin portal: user, device, and attendance management.',
                }
            };
            window.simalasT = function(){ return window.simalasI18n[window.simalasLang] || window.simalasI18n.id; };
        })();

        function switchPanel(panel) {
            document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
            const map = {
                'login':         'panelLogin',
                'add-email':     'panelAddEmail',
                'account-verify':'panelAccountVerify',
                'forgot':        'panelForgot',
                'verify-otp':    'panelVerifyOtp',
                'reset-password':'panelResetPassword',
            };
            const targetId = map[panel] || 'panelLogin';
            const el = document.getElementById(targetId);
            if (el) {
                el.classList.add('active');
            }
            if (window.simalasRecaptchaReady && window.grecaptcha) {
                setTimeout(function () { window.simalasRecaptchaReady(); }, 80);
            }
            if (window.lucide) lucide.createIcons();
        }

        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-switch-panel]');
            if (!trigger) return;
            e.preventDefault();
            switchPanel(trigger.getAttribute('data-switch-panel'));
        });

        function initOtpGroup(selector, hiddenId) {
            const inputs = document.querySelectorAll(selector);
            inputs.forEach((input, idx) => {
                input.addEventListener('input', e => {
                    const val = e.target.value.replace(/\D/g, '');
                    e.target.value = val;
                    if (val && idx < inputs.length - 1) inputs[idx + 1].focus();
                    updateHidden(inputs, hiddenId);
                });
                input.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && !e.target.value && idx > 0) inputs[idx - 1].focus();
                });
                input.addEventListener('paste', e => {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    paste.split('').forEach((ch, i) => { if (inputs[i]) inputs[i].value = ch; });
                    if (inputs[Math.min(paste.length, 5)]) inputs[Math.min(paste.length, 5)].focus();
                    updateHidden(inputs, hiddenId);
                });
            });
        }
        function updateHidden(inputs, hiddenId) {
            const digits = Array.from(inputs).map(i => i.value).join('');
            const h = document.getElementById(hiddenId);
            if (h) h.value = digits;
        }
        initOtpGroup('.pwd-otp', 'otpHidden');
        initOtpGroup('.account-otp', 'accountOtpHidden');
        ['otpForm', 'accountOtpForm'].forEach(function (formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            form.addEventListener('submit', function () {
                const selector = formId === 'otpForm' ? '.pwd-otp' : '.account-otp';
                const hiddenId = formId === 'otpForm' ? 'otpHidden' : 'accountOtpHidden';
                updateHidden(document.querySelectorAll(selector), hiddenId);
            });
        });
        window.switchPanel = switchPanel;

        // ── Dynamic Login Label & Hint based on Role Dropdown ──
        function updateLoginLabel() {
            const label = document.getElementById('loginIdLabel');
            const input = document.getElementById('loginIdInput');
            const hint  = document.getElementById('loginRoleHint');
            const select = document.getElementById('loginRoleSelect');
            if (!label || !input || !select) return;

            const t = (window.simalasT && window.simalasT()) || {};
            const role = select.value;

            if (role === 'dosen') {
                label.textContent = t.loginLabelDosen || 'NITK / NIDN';
                input.placeholder = t.loginPhDosen    || 'Masukkan NITK/NIDN';
                if (hint) hint.textContent = t.loginHintDosen || '';
            } else if (role === 'laboran' || role === 'admin') {
                label.textContent = t.loginLabelLab   || 'Username / NITK';
                input.placeholder = t.loginPhLab      || 'Masukkan username atau NITK admin';
                if (hint) hint.textContent = t.loginHintLab || '';
            } else {
                label.textContent = t.loginLabelMhs   || 'NIM';
                input.placeholder = t.loginPhMhs      || 'Masukkan NIM';
                if (hint) hint.textContent = t.loginHintMhs || '';
            }
        }
        const roleSelectEl = document.getElementById('loginRoleSelect');
        if (roleSelectEl) {
            roleSelectEl.addEventListener('change', updateLoginLabel);
        }
        document.addEventListener('simalas:lang-change', updateLoginLabel);
        updateLoginLabel();

        // ── SweetAlert2 Notifications ──
        @if(session('session_expired') || request()->query('expired'))
            Swal.fire({
                icon: 'info',
                title: 'Sesi berakhir',
                text: @json(session('session_expired', 'Sesi login sudah berakhir. Silakan login ulang untuk keamanan akun Anda.')),
                confirmButtonColor: '#e11d48',
            });
        @endif
        @if(session('success'))
            (function(){
                const t = (window.simalasT && window.simalasT()) || {};
                Swal.fire({
                    icon: 'success',
                    title: t.successTitle || 'Berhasil!',
                    text: @json(session('success')),
                    confirmButtonColor: '#e11d48',
                    timer: 5000,
                    timerProgressBar: true,
                    showClass: { popup: 'animate__animated animate__fadeInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp' }
                });
            })();
        @endif
        @if(session('info'))
            Swal.fire({
                icon: 'info',
                title: 'Informasi',
                text: @json(session('info')),
                confirmButtonColor: '#e11d48',
            });
        @endif
        @if($errors->any())
            (function(){
                const t = (window.simalasT && window.simalasT()) || {};
                Swal.fire({
                    icon: 'error',
                    title: t.errorTitle || 'Oops!',
                    text: @json($errors->first()),
                    confirmButtonColor: '#f43f5e',
                    showClass: { popup: 'animate__animated animate__shakeX' }
                });
                @php
                    $recaptchaSlot = session('auth_panel') === 'forgot' ? 'forgot' : null;
                @endphp
                @if($recaptchaSlot)
                    setTimeout(function () {
                        if (window.simalasRecaptchaReset) {
                            window.simalasRecaptchaReset(@json($recaptchaSlot));
                        }
                    }, 300);
                @endif
            })();
        @endif

        // ── Form submit confirmations (Forgot & Reset Password) ──
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            const action = form.getAttribute('action') || '';
            if (action.includes('/forgot-password')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formEl = this;
                    if (formEl._forgotEmailReady && !formEl._forgotEmailReady()) {
                        Swal.fire({ icon: 'info', title: 'Cek dulu ID Anda', text: 'Masukkan NIM/username sampai email terdaftar tampil, lalu centang verifikasi.', confirmButtonColor: '#1d6ff2' });
                        return;
                    }
                    const t = window.simalasT();
                    Swal.fire({
                        title: t.confirmForgotTitle,
                        text:  t.confirmForgotText,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: t.confirmForgotBtn,
                        cancelButtonText:  t.cancel,
                        confirmButtonColor: '#e11d48',
                        cancelButtonColor: '#94a3b8'
                    }).then(r => {
                        if (r.isConfirmed) {
                            if (window.simalasRecaptchaAttachToForm && !window.simalasRecaptchaAttachToForm(formEl, 'forgot')) {
                                alert('Centang verifikasi "Saya bukan robot" terlebih dahulu.');
                                return;
                            }
                            if (window.simalasShowLoader) window.simalasShowLoader(t.loaderOtp);
                            formEl.submit();
                        }
                    });
                });
            }
            if (action.includes('/forgot-password')) {
                // ── Tahapan: NIM → email tersamarkan → captcha → kirim ──
                var idInput   = form.querySelector('#forgotIdentifier');
                var hintWrap  = form.querySelector('#forgotEmailHint');
                var hintCard  = hintWrap ? hintWrap.querySelector('.forgot-email-hint') : null;
                var hintValue = form.querySelector('#forgotEmailMasked');
                var hintSub   = form.querySelector('#forgotEmailSub');
                var sendBtn   = form.querySelector('#forgotSendBtn');
                var emailReady = false;
                var lookupTimer = null;
                var lastChecked = '';

                function forgotCaptchaSolved() {
                    var slot = form.querySelector('[data-recaptcha-slot="forgot"]');
                    if (!slot || !slot.dataset.widgetId || !window.grecaptcha) return true;
                    try { return grecaptcha.getResponse(parseInt(slot.dataset.widgetId, 10)).length > 0; }
                    catch (e) { return false; }
                }
                function updateSendBtn() {
                    if (!sendBtn) return;
                    var ok = emailReady && forgotCaptchaSolved();
                    sendBtn.disabled = !ok;
                    sendBtn.classList.toggle('btn-primary--locked', !ok);
                }
                function setHint(state, value, sub) {
                    if (!hintWrap || !hintCard) return;
                    hintWrap.hidden = false;
                    hintCard.dataset.state = state;
                    if (hintValue) hintValue.textContent = value;
                    if (hintSub) hintSub.textContent = sub;
                }
                function checkIdentifier() {
                    var id = (idInput.value || '').trim();
                    if (id === lastChecked) return;
                    lastChecked = id;
                    emailReady = false;
                    if (id.length < 4) { if (hintWrap) hintWrap.hidden = true; updateSendBtn(); return; }
                    setHint('checking', 'Memeriksa akun…', '');
                    fetch('{{ route('forgot-password.cek') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ identifier: id }),
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        if (id !== (idInput.value || '').trim()) return;
                        if (d && d.state === 'found') {
                            emailReady = true;
                            setHint('found', d.email_masked, 'Benar email Anda? Jika ya, centang verifikasi lalu kirim.');
                        } else if (d && d.state === 'no_email') {
                            setHint('warn', 'Akun ditemukan', 'Akun ini belum memiliki email — minta admin/laboran menambahkan email Anda.');
                        } else if (d && d.state === 'no_account') {
                            setHint('error', 'Tidak ditemukan', 'NIM/username ini tidak terdaftar — periksa kembali penulisannya.');
                        } else {
                            setHint('warn', 'Gagal memeriksa', 'Coba lagi beberapa saat lagi.');
                        }
                        updateSendBtn();
                    }).catch(function () {
                        if (id !== (idInput.value || '').trim()) return;
                        setHint('warn', 'Gagal memeriksa', 'Periksa koneksi lalu coba lagi.');
                        updateSendBtn();
                    });
                }

                if (idInput && sendBtn) {
                    form._forgotEmailReady = function () { return emailReady; };
                    idInput.addEventListener('input', function () {
                        emailReady = false;
                        updateSendBtn();
                        clearTimeout(lookupTimer);
                        lookupTimer = setTimeout(checkIdentifier, 500);
                    });
                    idInput.addEventListener('blur', checkIdentifier);
                    if ((idInput.value || '').trim()) checkIdentifier();
                }

                // Gerbang gabungan: email terkonfirmasi DAN captcha selesai.
                var gateTimer = setInterval(function () {
                    if (form._simalasRecaptchaUpdate && !form._forgotGateWrapped) {
                        form._forgotGateWrapped = true;
                        var base = form._simalasRecaptchaUpdate;
                        form._simalasRecaptchaUpdate = function () { base(); updateSendBtn(); };
                        updateSendBtn();
                        clearInterval(gateTimer);
                    }
                }, 300);
            }
            if (action.includes('/reset-password')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formEl = this;
                    const t = window.simalasT();
                    Swal.fire({
                        title: t.confirmResetTitle,
                        text:  t.confirmResetText,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: t.confirmResetBtn,
                        cancelButtonText:  t.cancel,
                        confirmButtonColor: '#e11d48',
                        cancelButtonColor: '#94a3b8'
                    }).then(r => {
                        if (r.isConfirmed) {
                            if (window.simalasShowLoader) window.simalasShowLoader(t.loaderReset);
                            formEl.submit();
                        }
                    });
                });
            }
        });

        // Auto-detect panel from server session
        (function() {
            @if(session('auth_panel'))
                switchPanel(@json(session('auth_panel')));
            @elseif(!empty($persistentAuthPanel))
                switchPanel(@json($persistentAuthPanel));
            @elseif(session('otp_verified'))
                switchPanel('reset-password');
            @elseif(session('otp_sent'))
                switchPanel('verify-otp');
            @elseif(session('account_verify_otp_sent'))
                switchPanel('account-verify');
            @elseif(request()->routeIs('forgot-password'))
                switchPanel('forgot');
            @else
                switchPanel('login');
            @endif
        })();

        function applyResendCooldown(buttonId, seconds, baseText) {
            const btn = document.getElementById(buttonId);
            if (!btn || !seconds || seconds <= 0) return;

            let remaining = Number(seconds);
            btn.disabled = true;
            const originalText = baseText || btn.textContent.trim();

            const tick = () => {
                if (remaining <= 0) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                    return;
                }
                btn.textContent = `${originalText} (${remaining} dtk)`;
                remaining -= 1;
                setTimeout(tick, 1000);
            };

            tick();
        }

        applyResendCooldown('passwordResendBtn', @json(session('password_resend_cooldown')), 'Kirim ulang kode');

        document.querySelectorAll('.password-toggle').forEach(toggleBtn => {
            toggleBtn.addEventListener('click', function() {
                const container = this.closest('.password-field');
                if (!container) return;

                const input = container.querySelector('input.form-input');
                if (!input) return;

                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                const icon = this.querySelector('[data-lucide]');
                if (icon) {
                    icon.setAttribute('data-lucide', isHidden ? 'eye-off' : 'eye');
                    if (window.lucide) lucide.createIcons();
                }
                this.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
            });
        });

    </script>
<script src="{{ asset('js/auth-security.js') }}?v=2"></script>
<script src="{{ asset('js/auth-luxury.js') }}?v=18" defer></script>
<script>document.addEventListener("DOMContentLoaded",function(){if(window.lucide)lucide.createIcons();if(window.simalasRecaptchaReady)setTimeout(simalasRecaptchaReady,120);});</script>

    <div id="authLoginTransition" class="auth-login-transition" hidden aria-hidden="true">
        <div class="auth-login-transition__stage">
            <img
                class="auth-login-transition__mark js-logo-anim"
                src="{{ \App\Support\Branding::logoUrlOr(asset('branding/brail-mark.png')) }}"
                alt="BRAIL"
            >
            <span class="auth-login-transition__title">{{ \App\Support\Branding::appName() }}</span>
        </div>
    </div>
</body>
</html>
