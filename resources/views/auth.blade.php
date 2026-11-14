<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Dosen & Laboran — AIoT SIMALAS</title>
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
        /* overflow-x dipasang hanya di body supaya position:sticky di navbar tidak rusak. */
        html,body{height:100%;font-family:var(--font);color:var(--text)}
        body.auth-split-page{overflow-x:clip;min-height:100vh;margin:0}
        /* ── Navbar legacy (split page memakai auth-topbar di auth-luxury.css) ── */
        .navbar{position:sticky;top:0;z-index:30;backdrop-filter:blur(14px);border-bottom:1px solid rgba(148,163,184,.12);flex-shrink:0}
        .navbar-inner{max-width:1180px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:64px;gap:24px}
        .navbar-brand{display:flex;align-items:center;gap:12px;text-decoration:none}
        .brand-logo{
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
            height:44px;
            width:auto;
            max-width:min(42vw,168px);
            padding:0;
            border:none;
            background:transparent;
            box-shadow:none;
            border-radius:0;
            overflow:visible;
        }
        .brand-logo img{
            height:100%;
            width:auto;
            max-height:44px;
            display:block;
            object-fit:contain;
        }
        .brand-name{font-size:16px;font-weight:800;color:#0f172a;letter-spacing:.5px;line-height:1.1}
        .brand-sub{font-size:11px;color:#64748b;font-weight:500;margin-top:2px;letter-spacing:.4px}
        .navbar-links{display:flex;gap:6px;list-style:none;align-items:center;margin:0;padding:0}
        .navbar-links a{padding:8px 14px;border-radius:999px;color:#64748b;font-size:13.5px;font-weight:500;transition:var(--tr);text-decoration:none}
        .navbar-links a:hover{color:#0f172a;background:rgba(148,163,184,.12)}
        .navbar-right{display:flex;align-items:center;gap:10px}
        .lang-switch{display:flex;gap:2px;background:#fff;border:1px solid rgba(148,163,184,.22);border-radius:999px;padding:3px}
        .lang-btn{border:none;background:transparent;color:#94a3b8;font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;cursor:pointer;font-family:var(--font);transition:var(--tr)}
        .lang-btn.active{background:#334155;color:#fff}
        .nav-cta{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:999px;font-weight:600;font-size:13.5px;border:1px solid rgba(148,163,184,.35);background:#fff;color:#334155;text-decoration:none;transition:var(--tr)}
        .nav-cta:hover{border-color:#94a3b8;background:#f8fafc}
        .nav-cta.primary{background:linear-gradient(135deg,#e11d48,#f97316);border-color:transparent;box-shadow:0 8px 22px rgba(225,29,72,.35)}
        .nav-cta.primary:hover{transform:translateY(-1px);box-shadow:0 12px 28px rgba(249,115,22,.45)}
        .nav-burger{display:none;width:42px;height:42px;border-radius:12px;background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.16);align-items:center;justify-content:center;cursor:pointer}
        .nav-burger span{display:block;width:18px;height:2px;background:#cbd5e1;position:relative}
        .nav-burger span::before,.nav-burger span::after{content:'';position:absolute;left:0;width:18px;height:2px;background:#cbd5e1}
        .nav-burger span::before{top:-6px}
        .nav-burger span::after{top:6px}
        .navbar-mobile{display:none;padding:12px 24px;border-top:1px solid rgba(148,163,184,.12);background:rgba(255,255,255,.95)}
        .navbar-mobile.open{display:flex;flex-direction:column;gap:6px}
        .navbar-mobile a{padding:10px 14px;border-radius:10px;color:#64748b;text-decoration:none;font-size:14px;font-weight:500}
        .navbar-mobile a:hover{background:rgba(148,163,184,.12);color:#0f172a}
        @media(max-width:820px){.navbar-links{display:none}.brand-sub{display:none}.nav-burger{display:flex}}
        @media(max-width:560px){.nav-cta span{display:none}}
        @media(max-width:560px){.auth-topbar-back span{display:none}}
        .auth-card{width:100%;max-width:520px;background:var(--card);border-radius:var(--radius);box-shadow:0 16px 48px rgba(15,23,42,.08),0 0 0 1px rgba(148,163,184,.18);overflow:hidden}
        .tab-bar{position:relative;display:flex;background:#f1f5f9;border-bottom:1px solid var(--input-border);padding:4px;gap:0}
        .tab-slider{position:absolute;top:4px;bottom:4px;left:4px;width:calc(50% - 4px);background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(15,23,42,.08);transition:transform .3s ease;z-index:0;pointer-events:none}
        .tab-slider.is-login{transform:translateX(calc(100% + 4px))}
        .tab-btn{position:relative;z-index:1;flex:1;padding:12px 16px;font-size:14px;font-weight:600;border:none;background:transparent;color:var(--text-muted);cursor:pointer;transition:color .3s ease;display:flex;align-items:center;justify-content:center;gap:6px;font-family:var(--font)}
        .tab-btn.active{color:#0f172a}
        .tab-btn:hover:not(.active){color:#334155}
        .card-body{padding:28px 32px 24px}
        .card-header{margin-bottom:22px}
        .card-header--plain .card-header-text{text-align:left}
        .card-header-text h2{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px;letter-spacing:-.02em}
        .card-header-text p{font-size:13px;color:var(--text-muted);line-height:1.5}
        .form-group{margin-bottom:16px}
        .form-row{display:flex;gap:12px}
        .form-row .form-group{flex:1}
        .form-label{display:block;font-size:13px;font-weight:600;color:var(--label);margin-bottom:6px}
        .form-input{width:100%;padding:12px 14px;font-size:14px;font-family:var(--font);background:var(--input-bg);border:1.5px solid transparent;border-radius:var(--ri);color:var(--text);transition:var(--tr);outline:none}
        .form-input::placeholder{color:#94a3b8;font-weight:400}
        .form-input:focus{border-color:#94a3b8;background:#fff;box-shadow:0 0 0 3.5px rgba(148,163,184,.25)}
        .password-field{position:relative}
        .password-field .form-input{padding-right:44px}
        .password-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#64748b;cursor:pointer;font-size:17px;line-height:1;padding:4px;border-radius:6px;transition:var(--tr)}
        .password-toggle:hover{color:#0f172a;background:rgba(148,163,184,.15)}
        .role-selector{display:flex;gap:8px}
        .role-selector--2 .role-option{min-width:calc(50% - 6px)}
        .role-selector--3{flex-wrap:wrap}
        .role-selector--3 .role-option{min-width:calc(33.33% - 6px)}
        .role-option{flex:1;position:relative}
        .role-option input[type="radio"]{position:absolute;opacity:0;pointer-events:none}
        .role-option label{display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--input-bg);border:1.5px solid transparent;border-radius:var(--ri);cursor:pointer;transition:var(--tr);font-size:13px;font-weight:500}
        .role-option label:hover,.role-option.is-hovered label{border-color:#94a3b8;box-shadow:0 4px 14px rgba(15,23,42,.08)}
        .role-option input:checked+label{border-color:#334155;background:#f1f5f9;box-shadow:0 2px 10px rgba(15,23,42,.08)}
        .role-icon{font-size:16px}
        .role-label-text small{display:block;font-size:10px;color:var(--text-muted);font-weight:400}
        .otp-wrapper{display:flex;gap:10px;justify-content:center;margin:8px 0 4px}
        .otp-input{width:48px;height:54px;text-align:center;font-size:22px;font-weight:700;font-family:var(--font);background:var(--input-bg);border:1.5px solid transparent;border-radius:var(--ri);color:var(--text);transition:var(--tr);outline:none}
        .otp-input:focus{border-color:#94a3b8;background:#fff;box-shadow:0 0 0 3px rgba(148,163,184,.25)}
        .btn-primary{width:100%;padding:13px;font-size:15px;font-weight:700;font-family:var(--font);color:#fff;background:linear-gradient(135deg,#e11d48,#f97316);border:none;border-radius:var(--ri);cursor:pointer;transition:var(--tr);box-shadow:0 4px 18px rgba(225,29,72,.35);display:flex;align-items:center;justify-content:center;gap:8px;margin-top:6px;letter-spacing:.01em}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(249,115,22,.45)}
        .btn-primary:active{transform:translateY(0)}
        .card-footer-text{text-align:center;margin-top:16px;font-size:13px;color:var(--text-muted)}
        .link-btn{background:none;border:none;color:#e11d48;font-weight:600;font-size:13px;cursor:pointer;font-family:var(--font);text-decoration:none;transition:var(--tr)}
        .link-btn:hover{color:#9f1239;text-decoration:underline}
        .link-btn:disabled{opacity:.55;cursor:not-allowed;text-decoration:none;color:var(--text-muted)}
        .forgot-link{text-align:center;margin-top:10px}
        .alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;display:flex;align-items:center;gap:8px;animation:alertIn .3s ease}
        @keyframes alertIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}
        .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
        .auth-panel{display:none;flex:1;flex-direction:column}
        .auth-panel.active{display:flex;animation:panelFade .35s ease}
        @keyframes panelFade{from{opacity:0;transform:translateX(8px)}to{opacity:1;transform:translateX(0)}}
        .site-footer{display:none}
        .remember-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
        .remember-label{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--label);cursor:pointer}
        .remember-label input{accent-color:#334155}
        .auth-shell{display:flex;align-items:center;justify-content:center;width:100%;max-width:480px;margin:0 auto;padding:24px 16px}
        .auth-shell .auth-card{width:100%;max-width:520px}
        @media(max-width:640px){.navbar{padding:0 16px}.form-row{flex-direction:column;gap:0}.auth-card{max-width:100%;border-radius:12px}}

    </style>
</head>
<body class="auth auth-simple auth-luxury auth-split-page" @if($errors->any()) data-auth-has-errors="1" @endif>
    <div class="auth-split">
        <aside class="auth-split-hero" style="--auth-hero-img: url('{{ asset('images/campus-polybatam.jpg') }}')">
            <div class="auth-split-hero-overlay" aria-hidden="true"></div>
            <div class="auth-split-hero-content auth-hero-anim-pending">
                <div class="auth-hero-logo-wrap">
                    <img
                        class="auth-hero-logo-static"
                        src="{{ asset('branding/aiot-simalas-logo-lockup-s.png') }}?v=4"
                        alt="AIoT SIMALAS"
                    >
                </div>
                <p class="auth-hero-eyebrow" data-id="POLITEKNIK NEGERI BATAM" data-en="POLYTECHNIC OF BATAM">POLITEKNIK NEGERI BATAM</p>
                <h1 class="auth-hero-title auth-hero-anim auth-hero-anim-1">
                    <span class="auth-hero-title-flip" id="authHeroTitleFlip" aria-label="AIoT SIMALAS">AIoT SIMALAS</span>
                </h1>
                <p class="auth-hero-subtitle auth-hero-anim auth-hero-anim-2" data-id="Smart Laboratory Management System" data-en="Smart Laboratory Management System">Smart Laboratory Management System</p>
                <p class="auth-hero-desc auth-hero-anim auth-hero-anim-3" data-id="Platform terpusat untuk dosen & laboran — absensi biometrik dan keamanan laboratorium BRAIL." data-en="Centralized platform for lecturers & lab admins — biometric attendance and BRAIL lab security.">
                    Platform terpusat untuk dosen & laboran — absensi biometrik dan keamanan laboratorium BRAIL.
                </p>
                <ul class="auth-hero-features auth-hero-anim auth-hero-anim-4" aria-label="Fitur utama">
                    <li class="auth-hero-feature">
                        <span class="auth-hero-feature-icon" aria-hidden="true"><i data-lucide="fingerprint"></i></span>
                        <span data-id="Absensi Biometrik" data-en="Biometric Attendance">Absensi Biometrik</span>
                    </li>
                    <li class="auth-hero-feature">
                        <span class="auth-hero-feature-icon" aria-hidden="true"><i data-lucide="shield-check"></i></span>
                        <span data-id="Keamanan Lab" data-en="Lab Security">Keamanan Lab</span>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="auth-split-form">
        <div class="auth-luxury-shell">
        <div class="auth-mobile-brand" aria-hidden="false">
            <img
                src="{{ asset('branding/aiot-simalas-logo-lockup-s.png') }}?v=4"
                alt="AIoT SIMALAS"
            >
        </div>
        <div class="auth-card auth-stagger-pending" id="authCard">
            <div class="tab-bar auth-stagger-item auth-stagger-1" id="authTabBar">
                <span class="tab-slider" id="authTabSlider" aria-hidden="true"></span>
                <button type="button" class="tab-btn" id="tabRegister" data-switch-panel="register" data-tab-index="0" data-id="Daftar" data-en="Register"><span class="icon-wrap"><i data-lucide="user-plus"></i></span> Daftar</button>
                <button type="button" class="tab-btn active" id="tabLogin" data-switch-panel="login" data-tab-index="1" data-id="Login" data-en="Login"><span class="icon-wrap"><i data-lucide="log-in"></i></span> Login</button>
            </div>
            <div class="card-body">
                {{-- ══════ LOGIN PANEL ══════ --}}
                <div class="auth-panel active" id="panelLogin">
                    <div class="card-header card-header--plain auth-stagger-item auth-stagger-2">
                        <div class="card-header-text">
                            <h2 data-id="Selamat Datang Kembali 👋" data-en="Welcome Back 👋">Selamat Datang Kembali 👋</h2>
                            <p data-id="Masukkan kredensial Anda untuk melanjutkan ke dashboard." data-en="Enter your credentials to continue to the dashboard.">Masukkan kredensial Anda untuk melanjutkan ke dashboard.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('login.post') }}">
                        @csrf
                        <div class="form-group auth-stagger-item auth-stagger-3">
                            <label class="form-label" data-id="Hak Akses" data-en="Access Role">Hak Akses</label>
                            <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;line-height:1.5;">
                                Mahasiswa memakai <strong>aplikasi mobile SIMALAS</strong>, bukan login web ini.
                            </p>
                            <div class="role-selector role-selector--2">
                                <div class="role-option">
                                    <input type="radio" name="role" id="loginRoleDosen" value="dosen" {{ old('role','dosen')=='dosen'?'checked':'' }}>
                                    <label for="loginRoleDosen">
                                        <span class="role-icon-lucide"><i data-lucide="presentation"></i></span>
                                        <span class="role-label-text"
                                            data-id-html='Dosen<small>Lecturer</small>'
                                            data-en-html='Lecturer<small>Faculty</small>'>Dosen<small>Lecturer</small></span>
                                    </label>
                                </div>
                                <div class="role-option">
                                    <input type="radio" name="role" id="loginRoleLaboran" value="laboran" {{ old('role')=='laboran'?'checked':'' }}>
                                    <label for="loginRoleLaboran">
                                        <span class="role-icon-lucide"><i data-lucide="flask-conical"></i></span>
                                        <span class="role-label-text"
                                            data-id-html='Laboran<small>Admin Lab</small>'
                                            data-en-html='Lab Admin<small>Laboran</small>'>Laboran<small>Admin Lab</small></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group auth-stagger-item auth-stagger-4">
                            <label class="form-label" id="loginIdLabel">NITK / NIDN</label>
                            <input type="text" name="nitk_nidn" id="loginIdInput" class="form-input" placeholder="Masukkan NITK/NIDN" value="{{ old('nitk_nidn') }}" required autocomplete="username">
                            @error('nitk_nidn')
                                <p class="form-error" style="margin-top:6px;font-size:12px;color:#e11d48;font-weight:600;">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group auth-stagger-item auth-stagger-5">
                            <label class="form-label" data-id="Password" data-en="Password">Password</label>
                            <div class="password-field">
                                <input type="password" name="password" id="loginPassword" class="form-input"
                                    placeholder="Masukkan password"
                                    data-id-placeholder="Masukkan password"
                                    data-en-placeholder="Enter password" required autocomplete="current-password">
                                <button type="button" class="password-toggle" data-target="password"
                                    aria-label="Tampilkan password"
                                    data-id-aria="Tampilkan password"
                                    data-en-aria="Show password"><i data-lucide="eye"></i></button>
                            </div>
                        </div>
                        <div class="remember-row auth-stagger-item auth-stagger-6">
                            <label class="remember-label"><input type="checkbox" name="remember"> <span data-id="Ingat saya" data-en="Remember me">Ingat saya</span></label>
                            <button type="button" class="link-btn" data-switch-panel="forgot" data-id="Lupa password?" data-en="Forgot password?">Lupa password?</button>
                        </div>
                        <button type="submit" class="btn-primary btn-login-submit auth-stagger-item auth-stagger-7" id="loginSubmitBtn">
                            <span class="login-btn-label"><i data-lucide="log-in"></i><span data-id="Login" data-en="Sign in">Login</span></span>
                            <span class="login-btn-spinner" aria-hidden="true"><span class="login-btn-spinner-ring"></span></span>
                        </button>
                    </form>
                    <div class="card-footer-text auth-stagger-item auth-stagger-8"
                        data-id-html='Belum punya akun? <button type="button" class="link-btn" data-switch-panel="register"><strong>Daftar sekarang</strong></button>'
                        data-en-html='No account yet? <button type="button" class="link-btn" data-switch-panel="register"><strong>Register now</strong></button>'>
                        Belum punya akun? <button type="button" class="link-btn" data-switch-panel="register"><strong>Daftar sekarang</strong></button>
                    </div>
                    <div class="card-footer-text" style="margin-top:6px;">
                        Punya kode aktivasi dari admin? <a href="{{ route('activation.show') }}" class="link-btn"><strong>Aktivasi akun</strong></a>
                    </div>
                </div>

                {{-- ══════ REGISTER PANEL (Khusus Dosen) ══════ --}}
                <div class="auth-panel" id="panelRegister">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Buat Akun Dosen ✨" data-en="Create Lecturer Account ✨">Buat Akun Dosen ✨</h2>
                            <p data-id="Lengkapi data di bawah untuk bergabung ke portal SIMALAS." data-en="Complete the form below to join the SIMALAS portal.">Lengkapi data di bawah untuk bergabung ke portal SIMALAS.</p>
                        </div>
                    </div>
                    @if(session('register_verified'))
                    <div class="alert alert-success" style="margin-bottom:16px;font-size:13px;line-height:1.55;">
                        <strong>Verifikasi email berhasil.</strong>
                        Akun menunggu persetujuan admin/laboran sebelum bisa login.
                        Anda bisa <strong>daftar akun dosen lain</strong> di bawah, atau
                        <button type="button" class="link-btn" data-switch-panel="login"><strong>login</strong></button>
                        setelah akun disetujui.
                    </div>
                    @endif
                    @if(!($registrationOpen ?? true))
                    <div class="alert alert-error" id="registrationClosedNotice" role="status" style="display:block;margin-bottom:16px;font-size:13px;line-height:1.55;">
                        <strong>Pendaftaran ditutup.</strong> {{ $registrationClosedMessage ?? '' }}
                        <div style="margin-top:8px;"><a href="{{ route('activation.show') }}"><strong>Buka halaman Aktivasi Akun</strong></a></div>
                    </div>
                    @else
                    @error('registration')
                    <div class="alert alert-error" style="margin-bottom:16px;font-size:13px;line-height:1.55;">{{ $message }}</div>
                    @enderror
                    <form method="POST" action="{{ route('register.post') }}">
                        @csrf
                        <input type="hidden" name="role" value="dosen">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" data-id="Nama Lengkap" data-en="Full Name">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-input"
                                    placeholder="Nama lengkap"
                                    data-id-placeholder="Nama lengkap"
                                    data-en-placeholder="Full name" value="{{ old('nama_lengkap') }}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" data-id="NIDN" data-en="NIDN">NIDN</label>
                                <input type="tel" name="nitk_nidn" class="form-input"
                                    placeholder="Contoh: 4222301077"
                                    data-id-placeholder="Contoh: 4222301077"
                                    data-en-placeholder="Example: 4222301077"
                                    value="{{ old('nitk_nidn') }}" pattern="[0-9]+" inputmode="numeric"
                                    title="NIDN hanya boleh berisi angka"
                                    data-id-title="NIDN hanya boleh berisi angka"
                                    data-en-title="NIDN must contain digits only" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" data-id="Email" data-en="Email">Email</label>
                            <input type="email" name="email" id="registerEmail" class="form-input"
                                placeholder="nama@polibatam.ac.id"
                                data-id-placeholder="nama@polibatam.ac.id"
                                data-en-placeholder="name@institution.edu"
                                value="{{ old('email') }}" autocomplete="email" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" data-id="Password" data-en="Password">Password</label>
                            <div class="password-field">
                                <input type="password" name="password" id="registerPassword" class="form-input"
                                    placeholder="Min. 8 karakter, huruf besar/kecil & angka"
                                    data-id-placeholder="Min. 8 karakter, huruf besar/kecil & angka"
                                    data-en-placeholder="Min. 8 chars with upper, lower & number"
                                    minlength="8" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-target="registerPassword"
                                    aria-label="Tampilkan password"
                                    data-id-aria="Tampilkan password"
                                    data-en-aria="Show password">
                                    <i data-lucide="eye" style="width:18px;height:18px;"></i>
                                </button>
                            </div>
                        </div>

                        @include('partials.recaptcha', ['slot' => 'register'])

                        <div class="hp-field" aria-hidden="true">
                            <label>Website</label>
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <button type="submit" class="btn-primary">
                            <i data-lucide="mail"></i>
                            <span data-id="Daftar & Kirim Verifikasi" data-en="Register & Send Verification">Daftar & Kirim Verifikasi</span>
                        </button>
                    </form>
                    @endif
                    <div class="card-footer-text"
                        data-id-html='Sudah punya akun? <button type="button" class="link-btn" data-switch-panel="login"><strong>Login di sini</strong></button><br><button type="button" class="link-btn" data-switch-panel="reg-resume" style="margin-top:6px;"><strong>Sudah daftar? Masukkan kode OTP</strong></button>'
                        data-en-html='Already have an account? <button type="button" class="link-btn" data-switch-panel="login"><strong>Sign in here</strong></button><br><button type="button" class="link-btn" data-switch-panel="reg-resume" style="margin-top:6px;"><strong>Already registered? Enter OTP code</strong></button>'>
                        Sudah punya akun? <button type="button" class="link-btn" data-switch-panel="login"><strong>Login di sini</strong></button>
                        <br><button type="button" class="link-btn" data-switch-panel="reg-resume" style="margin-top:6px;"><strong>Sudah daftar? Masukkan kode OTP</strong></button>
                    </div>
                </div>

                {{-- ══════ LANJUTKAN VERIFIKASI REGISTRASI ══════ --}}
                <div class="auth-panel" id="panelRegResume">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Lanjutkan Verifikasi" data-en="Continue Verification">Lanjutkan Verifikasi</h2>
                            <p data-id="Masukkan email yang dipakai saat mendaftar untuk melanjutkan verifikasi OTP." data-en="Enter the email used during registration to continue OTP verification.">Masukkan email yang dipakai saat mendaftar untuk melanjutkan verifikasi OTP.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('register.resume.post') }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" data-id="Email Pendaftaran" data-en="Registration Email">Email Pendaftaran</label>
                            <input type="email" name="email" class="form-input"
                                placeholder="nama@email.com"
                                data-id-placeholder="nama@email.com"
                                data-en-placeholder="name@email.com"
                                value="{{ old('email', session('register_email')) }}" required autocomplete="email">
                        </div>
                        <button type="submit" class="btn-primary" data-id="Lanjutkan ke Verifikasi OTP" data-en="Continue to OTP Verification"><i data-lucide="arrow-right"></i><span>Lanjutkan ke Verifikasi OTP</span></button>
                    </form>
                    <div class="card-footer-text">
                        <button type="button" class="link-btn" data-switch-panel="register" data-id="Kembali ke formulir daftar" data-en="Back to registration form">Kembali ke formulir daftar</button>
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

                {{-- ══════ VERIFY REGISTRATION OTP PANEL ══════ --}}
                <div class="auth-panel" id="panelRegVerify">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Verifikasi Email" data-en="Email Verification">Verifikasi Email</h2>
                            <p data-id="Masukkan 6 digit kode yang dikirim ke email Anda" data-en="Enter the 6-digit code sent to your email">Masukkan 6 digit kode yang dikirim ke email Anda</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('verify-registration.post') }}" id="regOtpForm">
                        @csrf
                        <input type="hidden" name="email" value="{{ session('register_email', session('register_flow_email', old('email'))) }}">
                        <input type="hidden" name="otp_code" id="regOtpHidden">
                        @php
                            $regVerifyEmail = session('register_email', session('register_flow_email', old('email')));
                        @endphp
                        @if($regVerifyEmail)
                        <div class="form-group">
                            <label class="form-label" data-id="Email" data-en="Email">Email</label>
                            <input type="email" class="form-input" value="{{ $regVerifyEmail }}" readonly>
                        </div>
                        @endif
                        <div class="form-group">
                            <label class="form-label" data-id="Kode Verifikasi (6 Digit)" data-en="Verification Code (6 Digits)">Kode Verifikasi (6 Digit)</label>
                            <div class="otp-wrapper">
                                <input type="text" class="otp-input reg-otp" maxlength="1" data-idx="0" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input reg-otp" maxlength="1" data-idx="1" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input reg-otp" maxlength="1" data-idx="2" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input reg-otp" maxlength="1" data-idx="3" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input reg-otp" maxlength="1" data-idx="4" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input reg-otp" maxlength="1" data-idx="5" inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" data-id="Verifikasi Akun" data-en="Verify Account"><i data-lucide="badge-check"></i><span>Verifikasi Akun</span></button>
                    </form>
                    <div class="card-footer-text">
                        <form method="POST" action="{{ route('resend-verification.post') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('register_email', session('register_flow_email', old('email'))) }}">
                            <button type="submit" class="link-btn" id="registerResendBtn" data-id="Kirim ulang kode" data-en="Resend code">Kirim ulang kode</button>
                        </form>
                        &nbsp;|&nbsp;
                        <form method="POST" action="{{ route('cancel-registration-verification.post') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('register_email', session('register_flow_email', old('email'))) }}">
                            <button type="submit" class="link-btn" data-id="Batalkan Verifikasi" data-en="Cancel Verification">Batalkan Verifikasi</button>
                        </form>
                    </div>
                </div>

                {{-- ══════ FORGOT PASSWORD PANEL ══════ --}}
                <div class="auth-panel" id="panelForgot">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Lupa Password" data-en="Forgot Password">Lupa Password</h2>
                            <p data-id="Masukkan email untuk menerima kode OTP" data-en="Enter your email to receive an OTP code">Masukkan email untuk menerima kode OTP</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('forgot-password.post') }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" data-id="Email" data-en="Email">Email</label>
                            <input type="email" name="email" class="form-input"
                                placeholder="Email yang terdaftar"
                                data-id-placeholder="Email yang terdaftar"
                                data-en-placeholder="Registered email" value="{{ old('email') }}" required>
                        </div>
                        @include('partials.recaptcha', ['slot' => 'forgot'])
                        <div class="hp-field" aria-hidden="true">
                            <label>Website</label>
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <button type="submit" class="btn-primary" data-id="Kirim Kode OTP" data-en="Send OTP Code"><i data-lucide="send"></i><span>Kirim Kode OTP</span></button>
                    </form>
                    <div class="card-footer-text"
                        data-id-html='<button type="button" class="link-btn" data-switch-panel="login"><strong>Kembali ke Login</strong></button>'
                        data-en-html='<button type="button" class="link-btn" data-switch-panel="login"><strong>Back to Sign in</strong></button>'>
                        <button type="button" class="link-btn" data-switch-panel="login"><strong>Kembali ke Login</strong></button>
                    </div>
                </div>

                {{-- ══════ VERIFY OTP (PASSWORD RESET) PANEL ══════ --}}
                <div class="auth-panel" id="panelVerifyOtp">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Verifikasi OTP" data-en="OTP Verification">Verifikasi OTP</h2>
                            <p data-id="Masukkan 6 digit kode reset password" data-en="Enter the 6-digit password reset code">Masukkan 6 digit kode reset password</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('verify-otp.post') }}" id="otpForm">
                        @csrf
                        <input type="hidden" name="email" value="{{ session('password_reset_flow_email', session('otp_email', old('email'))) }}">
                        <input type="hidden" name="otp_code" id="otpHidden">
                        <div class="form-group">
                            <label class="form-label" data-id="Kode OTP (6 Digit)" data-en="OTP Code (6 Digits)">Kode OTP (6 Digit)</label>
                            <div class="otp-wrapper">
                                <input type="text" class="otp-input pwd-otp" maxlength="1" data-idx="0" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input pwd-otp" maxlength="1" data-idx="1" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input pwd-otp" maxlength="1" data-idx="2" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input pwd-otp" maxlength="1" data-idx="3" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input pwd-otp" maxlength="1" data-idx="4" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-input pwd-otp" maxlength="1" data-idx="5" inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" data-id="Verifikasi OTP" data-en="Verify OTP"><i data-lucide="badge-check"></i><span>Verifikasi OTP</span></button>
                    </form>
                    <div class="card-footer-text">
                        <form method="POST" action="{{ route('resend-otp.post') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('password_reset_flow_email', session('otp_email', old('email'))) }}">
                            <button type="submit" class="link-btn" id="passwordResendBtn" data-id="Kirim ulang kode" data-en="Resend code">Kirim ulang kode</button>
                        </form>
                        &nbsp;|&nbsp;
                        <form method="POST" action="{{ route('cancel-password-reset.post') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('password_reset_flow_email', session('otp_email', old('email'))) }}">
                            <button type="submit" class="link-btn" data-id="Batalkan Reset" data-en="Cancel Reset">Batalkan Reset</button>
                        </form>
                    </div>
                </div>

                {{-- ══════ RESET PASSWORD PANEL ══════ --}}
                <div class="auth-panel" id="panelResetPassword">
                    <div class="card-header card-header--plain">
                        <div class="card-header-text">
                            <h2 data-id="Reset Password" data-en="Reset Password">Reset Password</h2>
                            <p data-id="Buat password baru untuk akun Anda" data-en="Create a new password for your account">Buat password baru untuk akun Anda</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('reset-password.post') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ session('password_reset_flow_email', session('otp_email', old('email'))) }}">
                        <div class="form-group">
                            <label class="form-label" data-id="Password Baru" data-en="New Password">Password Baru</label>
                            <div class="password-field">
                                <input type="password" name="password" class="form-input"
                                    placeholder="Masukkan password baru"
                                    data-id-placeholder="Masukkan password baru"
                                    data-en-placeholder="Enter new password" required>
                                <button type="button" class="password-toggle" data-target="password"
                                    aria-label="Tampilkan password"
                                    data-id-aria="Tampilkan password"
                                    data-en-aria="Show password"><i data-lucide="eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" data-id="Konfirmasi Password" data-en="Confirm Password">Konfirmasi Password</label>
                            <div class="password-field">
                                <input type="password" name="password_confirmation" class="form-input"
                                    placeholder="Ulangi password baru"
                                    data-id-placeholder="Ulangi password baru"
                                    data-en-placeholder="Repeat new password" required>
                                <button type="button" class="password-toggle" data-target="password_confirmation"
                                    aria-label="Tampilkan konfirmasi password"
                                    data-id-aria="Tampilkan konfirmasi password"
                                    data-en-aria="Show confirm password"><i data-lucide="eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" data-id="Simpan Password Baru" data-en="Save New Password"><i data-lucide="save"></i><span>Simpan Password Baru</span></button>
                    </form>
                    <div class="card-footer-text">
                        <form method="POST" action="{{ route('cancel-password-reset.post') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('password_reset_flow_email', session('otp_email', old('email'))) }}">
                            <button type="submit" class="link-btn" data-id="Batalkan Reset" data-en="Cancel Reset">Batalkan Reset</button>
                        </form>
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
        // Loader dihapus — no-op agar handler SweetAlert register/forgot tetap aman.
        window.simalasShowLoader = function() {};
        window.simalasHideLoader = function() {};

        // ── Bahasa tetap Indonesia (portal privat staff) ──
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

            // ── Helper kamus i18n untuk dialog SweetAlert ──
            window.simalasI18n = {
                id: {
                    confirmRegisterTitle: 'Konfirmasi Registrasi',
                    confirmRegisterHtml: 'Kode verifikasi akan dikirim ke email Anda.<br><strong>Pastikan email yang dimasukkan benar!</strong>',
                    confirmRegisterBtn:  'Ya, Daftar & Kirim Kode',
                    confirmForgotTitle:  'Kirim Kode OTP?',
                    confirmForgotText:   'Kode OTP akan dikirim ke email yang Anda masukkan.',
                    confirmForgotBtn:    'Ya, Kirim OTP',
                    confirmResetTitle:   'Simpan Password Baru?',
                    confirmResetText:    'Password Anda akan diubah. Pastikan Anda mengingat password baru.',
                    confirmResetBtn:     'Ya, Simpan',
                    cancel:              'Batal',
                    successTitle:        'Berhasil!',
                    errorTitle:          'Oops!',
                    loaderRegister:      'Mengirim kode verifikasi',
                    loaderOtp:           'Mengirim kode OTP',
                    loaderReset:         'Menyimpan password',
                    greetWelcome:        'Selamat datang',
                    greetMorning:        'Selamat pagi',
                    greetMidday:         'Selamat siang',
                    greetEvening:        'Selamat sore',
                    greetNight:          'Selamat malam',
                    loginLabelDosen:     'NITK / NIDN',
                    loginPhDosen:        'Masukkan NITK/NIDN',
                    loginLabelLab:       'Username',
                    loginPhLab:          'Masukkan username admin',
                    loadingPage:         'Memuat halaman',
                    processing:          'Memproses',
                    days:   ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'],
                    months: ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
                },
                en: {
                    confirmRegisterTitle: 'Confirm Registration',
                    confirmRegisterHtml: 'A verification code will be sent to your email.<br><strong>Make sure the email is correct!</strong>',
                    confirmRegisterBtn:  'Yes, Register & Send Code',
                    confirmForgotTitle:  'Send OTP Code?',
                    confirmForgotText:   'An OTP code will be sent to the email you entered.',
                    confirmForgotBtn:    'Yes, Send OTP',
                    confirmResetTitle:   'Save New Password?',
                    confirmResetText:    'Your password will be changed. Make sure to remember it.',
                    confirmResetBtn:     'Yes, Save',
                    cancel:              'Cancel',
                    successTitle:        'Success!',
                    errorTitle:          'Oops!',
                    loaderRegister:      'Sending verification code',
                    loaderOtp:           'Sending OTP code',
                    loaderReset:         'Saving password',
                    greetWelcome:        'Welcome',
                    greetMorning:        'Good morning',
                    greetMidday:         'Good afternoon',
                    greetEvening:        'Good evening',
                    greetNight:          'Good night',
                    loginLabelDosen:     'NITK / NIDN',
                    loginPhDosen:        'Enter NITK/NIDN',
                    loginLabelLab:       'Username',
                    loginPhLab:          'Enter admin username',
                    loadingPage:         'Loading page',
                    processing:          'Processing',
                    days:   ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
                    months: ['January','February','March','April','May','June','July','August','September','October','November','December']
                }
            };
            window.simalasT = function(){ return window.simalasI18n[window.simalasLang] || window.simalasI18n.id; };
        })();

        function updateTabSlider(tabId) {
            const slider = document.getElementById('authTabSlider');
            if (!slider) return;
            slider.classList.toggle('is-login', tabId === 'tabLogin');
        }

        function switchPanel(panel) {
            document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            const map = {
                'login':         { panel: 'panelLogin',         tab: 'tabLogin' },
                'register':      { panel: 'panelRegister',      tab: 'tabRegister' },
                'reg-resume':    { panel: 'panelRegResume',     tab: 'tabRegister' },
                'reg-verify':    { panel: 'panelRegVerify',     tab: 'tabRegister' },
                'add-email':     { panel: 'panelAddEmail',      tab: 'tabLogin' },
                'account-verify':{ panel: 'panelAccountVerify', tab: 'tabLogin' },
                'forgot':        { panel: 'panelForgot',        tab: null },
                'verify-otp':    { panel: 'panelVerifyOtp',     tab: null },
                'reset-password':{ panel: 'panelResetPassword', tab: null },
            };
            const t = map[panel];
            if (t) {
                document.getElementById(t.panel).classList.add('active');
                if (t.tab) {
                    document.getElementById(t.tab).classList.add('active');
                    updateTabSlider(t.tab);
                }
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

        // OTP auto-advance for both OTP groups
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
        initOtpGroup('.reg-otp', 'regOtpHidden');
        initOtpGroup('.pwd-otp', 'otpHidden');
        initOtpGroup('.account-otp', 'accountOtpHidden');
        ['regOtpForm', 'accountOtpForm'].forEach(function (formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            form.addEventListener('submit', function () {
                const selector = formId === 'regOtpForm' ? '.reg-otp' : '.account-otp';
                const hiddenId = formId === 'regOtpForm' ? 'regOtpHidden' : 'accountOtpHidden';
                updateHidden(document.querySelectorAll(selector), hiddenId);
            });
        });
        window.switchPanel = switchPanel;

        // ── Dynamic Login Label based on Role — bilingual ──
        function updateLoginLabel() {
            const label = document.getElementById('loginIdLabel');
            const input = document.getElementById('loginIdInput');
            const t = (window.simalasT && window.simalasT()) || {};
            const dosen = document.getElementById('loginRoleDosen')?.checked;
            if (dosen) {
                label.textContent = t.loginLabelDosen || 'NITK / NIDN';
                input.placeholder  = t.loginPhDosen    || 'Masukkan NITK/NIDN';
            } else {
                label.textContent = t.loginLabelLab || 'Username';
                input.placeholder  = t.loginPhLab    || 'Masukkan username admin';
            }
        }
        ['loginRoleDosen', 'loginRoleLaboran'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', updateLoginLabel);
        });
        document.addEventListener('simalas:lang-change', updateLoginLabel);
        updateLoginLabel();

        // ── NIDN Number-Only Validation (Register Form) ──
        const regNidn = document.querySelector('input[name="nitk_nidn"][type="tel"]');
        if (regNidn) {
            regNidn.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        }

        // ── SweetAlert2 Notifications — bilingual ──
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
                    $recaptchaSlot = match (session('auth_panel')) {
                        'register', 'reg-verify', 'reg-resume' => 'register',
                        'forgot' => 'forgot',
                        default => null,
                    };
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

        // ── Form submit confirmations — bilingual ──
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            const action = form.getAttribute('action') || '';
            if (action.includes('/register') && !action.includes('verify')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formEl = this;
                    const t = window.simalasT();
                    Swal.fire({
                        title: t.confirmRegisterTitle,
                        html:  t.confirmRegisterHtml,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: t.confirmRegisterBtn,
                        cancelButtonText:  t.cancel,
                        confirmButtonColor: '#e11d48',
                        cancelButtonColor: '#94a3b8',
                        reverseButtons: true
                    }).then(r => {
                        if (r.isConfirmed) {
                            if (window.simalasRecaptchaAttachToForm && !window.simalasRecaptchaAttachToForm(formEl, 'register')) {
                                alert('Centang verifikasi "Saya bukan robot" terlebih dahulu.');
                                return;
                            }
                            if (window.simalasShowLoader) window.simalasShowLoader(t.loaderRegister);
                            formEl.submit();
                        }
                    });
                });
            }
            if (action.includes('/forgot-password')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formEl = this;
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
            @elseif(session('register_otp_sent'))
                switchPanel('reg-verify');
            @elseif(session('account_verify_otp_sent'))
                switchPanel('account-verify');
            @elseif(request()->routeIs('forgot-password'))
                switchPanel('forgot');
            @elseif(request()->routeIs('register'))
                switchPanel('register');
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

        applyResendCooldown('registerResendBtn', @json(session('register_resend_cooldown')), 'Kirim ulang kode');
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
<script src="{{ asset('js/auth-luxury.js') }}?v=17" defer></script>
<script>document.addEventListener("DOMContentLoaded",function(){if(window.lucide)lucide.createIcons();if(window.simalasRecaptchaReady)setTimeout(simalasRecaptchaReady,120);});</script>

    <div id="authLoginTransition" class="auth-login-transition" hidden aria-hidden="true">
        <div class="auth-login-transition__stage">
            <video
                class="auth-login-transition__mark js-logo-anim"
                src="{{ asset('branding/aiot-simalas-logo-anim.mp4') }}?v=3"
                muted
                playsinline
                preload="auto"
                poster="{{ asset('branding/aiot-simalas-mark-s.png') }}?v=4"
                aria-hidden="true"
            ></video>
            <span class="auth-login-transition__title">AIoT SIMALAS</span>
        </div>
    </div>
</body>
</html>
