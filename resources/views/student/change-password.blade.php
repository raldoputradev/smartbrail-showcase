@extends('layouts.student')

@section('title', $isMandatory ? 'Wajib Ganti Password' : 'Ganti Password Akun')
@section('page-title', $isMandatory ? 'Aktivasi Keamanan Akun' : 'Ganti Password')

@section('content')
    <div class="sp-page">
        <div class="sgp-layout">
            {{-- Panel ilustrasi & penjelasan --}}
            <aside class="sgp-side" aria-label="Informasi keamanan">
                <div class="sgp-side__art" aria-hidden="true">
                    <svg viewBox="0 0 220 170" fill="none">
                        <defs>
                            <linearGradient id="sgpLap" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#dbeafe"/><stop offset="1" stop-color="#bfdbfe"/>
                            </linearGradient>
                            <linearGradient id="sgpShield" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#2563eb"/><stop offset="1" stop-color="#06b6d4"/>
                            </linearGradient>
                        </defs>
                        <rect x="45" y="38" width="130" height="86" rx="9" fill="url(#sgpLap)" stroke="#93c5fd" stroke-width="2"/>
                        <rect x="55" y="48" width="110" height="62" rx="5" fill="#eff6ff"/>
                        <rect x="30" y="124" width="160" height="10" rx="5" fill="#93c5fd"/>
                        <circle cx="110" cy="79" r="17" fill="#1e3a8a"/>
                        <rect x="101" y="74" width="18" height="15" rx="3.5" fill="#fff"/>
                        <path d="M104.5 74v-3a5.5 5.5 0 0 1 11 0v3" stroke="#fff" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                        <circle cx="110" cy="80.5" r="1.8" fill="#1e3a8a"/>
                        <rect x="70" y="97" width="34" height="5" rx="2.5" fill="#93c5fd"/>
                        <rect x="70" y="97" width="6" height="5" rx="2.5" fill="#2563eb"/>
                        <path d="M163 52l26 9v16c0 16-11 27-26 33-15-6-26-17-26-33V61Z" fill="url(#sgpShield)"/>
                        <path d="m152 79 8 8 15-16" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M58 18l4 8M70 12l1 9M46 24l6 6" stroke="#60a5fa" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3 class="sgp-side__title">Perbarui Password Anda</h3>
                <p class="sgp-side__sub">Gunakan password yang kuat dan unik untuk menjaga keamanan akun SmartBrail Anda.</p>
                <div class="sgp-points">
                    <div class="sgp-point">
                        <span class="sgp-point__ico">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <div><strong>Lebih Aman</strong><span>Melindungi data dan aktivitas akun Anda.</span></div>
                    </div>
                    <div class="sgp-point">
                        <span class="sgp-point__ico">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/></svg>
                        </span>
                        <div><strong>Akses Tetap Lancar</strong><span>Gunakan password baru saat login berikutnya.</span></div>
                    </div>
                    <div class="sgp-point">
                        <span class="sgp-point__ico">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <div><strong>Kendali Penuh</strong><span>Hanya Anda yang dapat mengganti password.</span></div>
                    </div>
                </div>
            </aside>

            {{-- Form --}}
            <section class="sp-card" aria-label="Formulir Ganti Password">
                <div class="sp-card__head" style="margin-bottom:16px">
                    <div>
                        <h2 class="sp-card__title" style="display:flex;align-items:center;gap:9px">
                            <span class="sp-pagehead__icon" style="width:36px;height:36px;border-radius:9px">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            {{ $isMandatory ? 'Buat Password Pribadi Anda' : 'Ganti Password' }}
                        </h2>
                        <p class="sp-card__desc">
                            @if($isMandatory)
                                Akun <strong>{{ $user->nama_lengkap }}</strong> masih memakai password sementara. Buat password pribadi baru untuk mulai menggunakan portal.
                            @else
                                Masukkan password saat ini, lalu buat password baru yang kuat.
                            @endif
                        </p>
                    </div>
                </div>

                @if(! $isMandatory)
                    <div class="sgp-tabs" role="tablist" aria-label="Metode ganti password">
                        <button type="button" class="sgp-tab is-on" id="sgpTabCurrent" role="tab" aria-selected="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 2l-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.8 7.8 5.5 5.5 0 0 1 7.8-7.8Zm0 0L19 4m-4 4 3 3"/></svg>
                            Gunakan Password Saat Ini
                        </button>
                        <button type="button" class="sgp-tab" id="sgpTabEmail" role="tab" aria-selected="false">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                            Lupa Password? (Lewat Email)
                        </button>
                    </div>
                @endif

                <form method="POST" action="{{ route('mahasiswa.password.update') }}" class="sp-form" id="sgpForm">
                    @csrf

                    @if(! $isMandatory)
                        <div class="sp-field">
                            <label for="current_password" class="sp-label">Password Saat Ini</label>
                            <div class="sp-pwd-field">
                                <input id="current_password" type="password" name="current_password" class="sp-input" required
                                       autocomplete="current-password" placeholder="Masukkan password saat ini">
                                <button type="button" class="sp-pwd-toggle" data-toggle-password="current_password" aria-label="Tampilkan password">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            @error('current_password')
                                <p class="sp-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="sp-field">
                        <label for="password" class="sp-label">Password Baru</label>
                        <div class="sp-pwd-field">
                            <input id="password" type="password" name="password" class="sp-input" required
                                   autocomplete="new-password" placeholder="Buat password baru (minimal 8 karakter)">
                            <button type="button" class="sp-pwd-toggle" data-toggle-password="password" aria-label="Tampilkan password">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="sp-pwd-meter" aria-live="polite">
                            <div class="sp-pwd-meter__bar"><div class="sp-pwd-meter__fill" id="pwdMeterFill"></div></div>
                            <div class="sp-pwd-meter__label" id="pwdMeterLabel">Kekuatan password</div>
                        </div>
                        @error('password')
                            <p class="sp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sp-field">
                        <label for="password_confirmation" class="sp-label">Ulangi Password Baru</label>
                        <div class="sp-pwd-field">
                            <input id="password_confirmation" type="password" name="password_confirmation" class="sp-input" required
                                   autocomplete="new-password" placeholder="Ketik ulang password baru">
                            <button type="button" class="sp-pwd-toggle" data-toggle-password="password_confirmation" aria-label="Tampilkan konfirmasi password">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="sp-pwd-match" id="passwordMatchHint"></div>
                    </div>

                    <div class="sp-req-box">
                        <strong>Ketentuan Password</strong>
                        <ul class="sp-checklist">
                            <li data-pwd-rule="len"><span class="pwd-dot">○</span> <span>Minimal 8 karakter</span></li>
                            <li data-pwd-rule="case"><span class="pwd-dot">○</span> <span>Kombinasi huruf besar (A-Z) dan huruf kecil (a-z)</span></li>
                            <li data-pwd-rule="num"><span class="pwd-dot">○</span> <span>Memuat setidaknya 1 angka (0-9)</span></li>
                            <li data-pwd-rule="sym"><span class="pwd-dot">○</span> <span>Memuat simbol atau karakter unik (misal: !@#$%^&*)</span></li>
                        </ul>
                    </div>

                    <div class="sp-form-actions">
                        <a href="{{ route('mahasiswa.beranda') }}" class="sp-btn sp-btn--ghost">Batal</a>
                        <button type="submit" class="sp-btn sp-btn--primary" id="sgpSubmit">
                            {{ $isMandatory ? 'Simpan Password & Masuk ke Beranda' : 'Simpan Password Baru' }}
                        </button>
                    </div>
                </form>

                @if(! $isMandatory)
                    <div id="sgpPaneEmail" hidden>
                        <div class="sp-otp-card" style="margin-top:4px">
                            <div>
                                <span class="sp-status sp-status--info">Pemulihan Email</span>
                                <h4>Atur ulang lewat email terdaftar</h4>
                                <p>
                                    @if(filled($user->email))
                                        Kami akan mengirim tautan <strong>"Atur Ulang Password"</strong> ke <strong>{{ $user->email }}</strong>. Tautan berlaku terbatas dan hanya sekali pakai.
                                    @else
                                        Akun Anda belum memiliki email terikat. Minta password sementara baru kepada admin/laboran untuk masuk kembali.
                                    @endif
                                </p>
                            </div>
                            @if(filled($user->email))
                                <form method="POST" action="{{ route('mahasiswa.password.reset-link') }}">
                                    @csrf
                                    <button type="submit" class="sp-btn sp-btn--primary sp-btn--sm">Kirim Tautan Reset ke Email Saya</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    <style>
        .sp-pwd-field{position:relative}
        .sp-pwd-field .sp-input{padding-right:42px}
        .sp-pwd-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:var(--sp-muted,#64748b);cursor:pointer;line-height:1;padding:3px;border-radius:6px;display:grid;place-items:center}
        .sp-pwd-toggle:hover{color:var(--sp-primary)}
        .sp-pwd-meter{margin-top:8px}
        .sp-pwd-meter__bar{height:6px;border-radius:99px;background:var(--sp-line,#e2e8f0);overflow:hidden}
        .sp-pwd-meter__fill{height:100%;width:0%;border-radius:99px;transition:width .25s ease,background .25s ease}
        .sp-pwd-meter__fill--weak{background:#e11d48;width:33%}
        .sp-pwd-meter__fill--medium{background:#d97706;width:66%}
        .sp-pwd-meter__fill--strong{background:#059669;width:100%}
        .sp-pwd-meter__label{font-size:12px;font-weight:600;margin-top:5px;color:var(--sp-muted,#64748b)}
        .sp-pwd-match{font-size:12px;font-weight:600;margin-top:6px;min-height:1em}
        .sp-pwd-match--ok{color:#059669}
        .sp-pwd-match--bad{color:#e11d48}
        .sp-checklist li{display:flex;align-items:center;gap:8px;margin:5px 0;font-size:12.5px;color:var(--sp-muted,#64748b)}
        .sp-checklist li.is-ok{color:#059669}
        .pwd-dot{display:inline-flex;width:14px;justify-content:center}
        .sp-form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:6px}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var svgEye = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
            var svgEyeOff = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M6.61 6.61A13.526 13.526 0 0 0 1 12s4 8 11 8a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';
            document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(this.getAttribute('data-toggle-password'));
                    if (!input) return;
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                    this.innerHTML = show ? svgEyeOff : svgEye;
                });
            });

            var pwd = document.getElementById('password');
            var conf = document.getElementById('password_confirmation');
            var fill = document.getElementById('pwdMeterFill');
            var label = document.getElementById('pwdMeterLabel');
            var match = document.getElementById('passwordMatchHint');
            var rules = {
                len: function (v) { return v.length >= 8; },
                case: function (v) { return /[a-z]/.test(v) && /[A-Z]/.test(v); },
                num: function (v) { return /\d/.test(v); },
                sym: function (v) { return /[^A-Za-z0-9]/.test(v); },
            };

            function refresh() {
                var v = pwd ? pwd.value : '';
                var score = 0;
                Object.keys(rules).forEach(function (key) {
                    var ok = rules[key](v);
                    var li = document.querySelector('[data-pwd-rule="' + key + '"]');
                    if (li) {
                        li.classList.toggle('is-ok', ok);
                        var dot = li.querySelector('.pwd-dot');
                        if (dot) dot.textContent = ok ? '✓' : '○';
                    }
                    if (ok) score++;
                });
                if (fill && label) {
                    fill.className = 'sp-pwd-meter__fill';
                    if (!v) { label.textContent = 'Kekuatan password'; }
                    else if (score <= 2) { fill.classList.add('sp-pwd-meter__fill--weak'); label.textContent = 'Lemah'; }
                    else if (score === 3) { fill.classList.add('sp-pwd-meter__fill--medium'); label.textContent = 'Sedang'; }
                    else { fill.classList.add('sp-pwd-meter__fill--strong'); label.textContent = 'Kuat'; }
                }
                if (match && conf) {
                    if (!conf.value) { match.textContent = ''; match.className = 'sp-pwd-match'; }
                    else if (conf.value === v) { match.className = 'sp-pwd-match sp-pwd-match--ok'; match.textContent = '✓ Konfirmasi cocok'; }
                    else { match.className = 'sp-pwd-match sp-pwd-match--bad'; match.textContent = '✗ Konfirmasi belum cocok'; }
                }
            }
            if (pwd) pwd.addEventListener('input', refresh);
            if (conf) conf.addEventListener('input', refresh);

            var form = document.getElementById('sgpForm');
            var submit = document.getElementById('sgpSubmit');
            if (form && submit) {
                form.addEventListener('submit', function () {
                    submit.disabled = true;
                    submit.textContent = 'Menyimpan…';
                });
            }

            var tabCurrent = document.getElementById('sgpTabCurrent');
            var tabEmail = document.getElementById('sgpTabEmail');
            var paneEmail = document.getElementById('sgpPaneEmail');
            if (tabCurrent && tabEmail && paneEmail) {
                function setPane(email) {
                    tabCurrent.classList.toggle('is-on', !email);
                    tabEmail.classList.toggle('is-on', email);
                    tabCurrent.setAttribute('aria-selected', String(!email));
                    tabEmail.setAttribute('aria-selected', String(email));
                    paneEmail.hidden = !email;
                    form.hidden = email;
                }
                tabCurrent.addEventListener('click', function () { setPane(false); });
                tabEmail.addEventListener('click', function () { setPane(true); });
            }
        });
    </script>
@endsection
