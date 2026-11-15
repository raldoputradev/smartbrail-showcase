@php
    $evNeedsEmail = session('account_verify_needs_email');
    $evEmail = session('account_verify_email', Auth::user()->email);
    $evAutoOpen = session('account_verify_otp_sent') || $errors->has('email') || $errors->has('otp_code');
    $evShowSuccess = session('account_verify_success');
    $evIsDosen = (Auth::user()->role ?? '') === 'dosen';
@endphp

<div class="ev-modal-backdrop"
    id="evModalBackdrop"
    aria-hidden="true"
    data-needs-email="{{ $evNeedsEmail ? '1' : '0' }}"
    data-auto-open="{{ $evAutoOpen ? '1' : '0' }}"
    data-show-success="{{ $evShowSuccess ? '1' : '0' }}"
    data-check-url="{{ route('account-email.check') }}"
    data-verify-email="{{ $evEmail }}">
    <div class="ev-modal {{ $evShowSuccess ? 'is-success' : '' }}" role="dialog" aria-modal="true" aria-labelledby="evModalTitle">
        @unless($evShowSuccess)
        <button type="button" class="ev-modal-close" id="evModalClose" aria-label="Tutup">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <button type="button" class="ev-modal-info" id="evModalInfo" aria-label="Informasi verifikasi" aria-expanded="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </button>
        <div class="ev-info-pop" id="evInfoPop" role="tooltip" hidden>
            <strong>Cara verifikasi email</strong>
            <ol>
                <li>Isi email aktif yang valid (contoh: nama@email.com).</li>
                <li>Baca &amp; setujui Syarat &amp; Ketentuan (scroll sampai bawah).</li>
                <li>Centang reCAPTCHA &ldquo;Saya bukan robot&rdquo;.</li>
                <li>Tekan Kirim Kode, lalu masukkan 6 digit OTP dari inbox.</li>
            </ol>
            <span>Akun tetap bisa dipakai walau belum verifikasi — ini hanya pengaman tambahan.</span>
        </div>
        @endunless

        <div class="ev-modal-form-wrap" id="evModalFormWrap">
            <div class="ev-modal-hero">
                <div class="ev-modal-icon-ring" aria-hidden="true">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="3"/>
                        <path d="m2 7 10 7 10-7"/>
                    </svg>
                </div>
                <span class="ev-modal-badge">Keamanan Akun</span>
                <h2 id="evModalTitle">Verifikasi Email</h2>
                <p id="evModalSubtitle">
                    @if($evNeedsEmail)
                        Tambahkan email aktif untuk menerima kode OTP dan mengamankan akun Anda.
                    @else
                        Masukkan 6 digit kode OTP yang dikirim ke <strong>{{ $evEmail }}</strong>
                    @endif
                </p>
            </div>

            <div class="ev-modal-body">
                @if($errors->has('email') || $errors->has('otp_code'))
                <div class="ev-form-error">
                    {{ $errors->first('email') ?: $errors->first('otp_code') }}
                </div>
                @endif

                <div class="ev-step" id="evStepEmail" @if(!$evNeedsEmail) hidden @endif>
                    <form method="POST" action="{{ route('account-email.post') }}" id="evEmailForm">
                        @csrf
                        <label class="ev-field-label" for="evAccountEmail">Email aktif</label>
                        <div class="ev-email-wrap" id="evEmailWrap">
                            <svg class="ev-email-wrap__lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
                            <input id="evAccountEmail" type="email" name="email" class="ev-email-input"
                                placeholder="nama@email.com" required autocomplete="email"
                                value="{{ old('email') }}" aria-describedby="evEmailHint">
                            <span class="ev-email-status" id="evEmailStatus" aria-live="polite" aria-hidden="true">
                                <svg class="ev-email-status__ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                <svg class="ev-email-status__bad" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </span>
                        </div>
                        <p class="ev-email-error" id="evEmailError" hidden aria-live="polite"></p>
                        <p class="ev-hint" id="evEmailHint">Kode OTP berlaku 30 menit. Pastikan inbox atau folder spam Anda.</p>

                        @if(\App\Support\Recaptcha::isConfigured())
                        <div class="ev-recaptcha-wrap" id="evRecaptchaWrap">
                            {{-- Overlay transparan: klik reCAPTCHA dulu wajib baca syarat --}}
                            <button type="button" class="ev-captcha-hit" id="evCaptchaHit" aria-label="Buka persyaratan sebelum reCAPTCHA"></button>
                            <div class="ev-captcha-widget" id="evCaptchaWidget">
                                @include('partials.recaptcha', ['slot' => 'account-email'])
                            </div>
                        </div>
                        @else
                        {{-- Fallback tanpa Google reCAPTCHA: tombol buka syarat --}}
                        <button type="button" class="ev-captcha-fallback" id="evCaptchaHit">
                            Baca &amp; setujui persyaratan keamanan
                        </button>
                        @endif

                        @error('g-recaptcha-response')
                        <div class="ev-form-error">{{ $message }}</div>
                        @enderror

                        <button type="submit" class="ev-btn-primary ev-btn-primary--locked" id="evEmailSubmit" disabled aria-disabled="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                            Kirim Kode Verifikasi
                        </button>
                    </form>
                </div>

                <div class="ev-step" id="evStepOtp" @if($evNeedsEmail) hidden @endif>
                    <div class="ev-email-readonly">{{ $evEmail }}</div>
                    <form method="POST" action="{{ route('verify-account-email.post') }}" id="evOtpForm">
                        @csrf
                        <input type="hidden" name="email" value="{{ $evEmail }}">
                        <input type="hidden" name="otp_code" id="evOtpHidden">
                        <label class="ev-field-label">Kode OTP (6 digit)</label>
                        <div class="ev-otp-wrapper">
                            @for($i = 0; $i < 6; $i++)
                            <input type="text" class="ev-otp-input" maxlength="1" inputmode="numeric" autocomplete="one-time-code" aria-label="Digit {{ $i + 1 }}">
                            @endfor
                        </div>
                        <button type="submit" class="ev-btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                            Verifikasi Email
                        </button>
                    </form>
                    <div class="ev-form-footer">
                        <form method="POST" action="{{ route('resend-account-verification.post') }}" style="display:inline">
                            @csrf
                            <button type="submit" class="ev-link-btn">Kirim ulang kode</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="ev-modal-success" id="evModalSuccess">
            <div class="ev-success-burst" aria-hidden="true"></div>
            <div class="ev-success-icon" aria-hidden="true">
                <svg viewBox="0 0 52 52" width="96" height="96">
                    <circle class="ev-success-circle" cx="26" cy="26" r="23"/>
                    <path class="ev-success-check" d="M14 27l7 7 16-16"/>
                </svg>
            </div>
            <h2>Verifikasi Berhasil!</h2>
            <p>
                @if($evIsDosen)
                    Email terverifikasi. Menunggu persetujuan admin sebelum akses panel dosen penuh.
                @else
                    Email Anda telah terverifikasi. Akun siap digunakan dengan aman di AIoT SIMALAS.
                @endif
            </p>
            <button type="button" class="ev-btn-primary ev-btn-done" id="evModalDone">Lanjutkan</button>
        </div>
    </div>
</div>

{{-- Popup syarat & ketentuan: dua kolom — panel identitas kiri, isi kanan. Bisa ditutup via X; reCAPTCHA tetap terkunci sampai disetujui --}}
<div class="ev-terms-sheet" id="evTermsSheet" aria-hidden="true" hidden>
    <div class="ev-terms-sheet__backdrop" id="evTermsBackdrop" aria-hidden="true"></div>
    <div class="ev-terms-sheet__panel ev-terms-sheet__panel--split" role="dialog" aria-modal="true" aria-labelledby="evTermsTitle">

        <aside class="ev-terms-side">
            <div class="ev-terms-side__art" aria-hidden="true">
                <svg viewBox="0 0 120 110" fill="none">
                    <ellipse cx="60" cy="55" rx="52" ry="40" stroke="rgba(74,166,255,.35)" stroke-width="1.2" transform="rotate(-18 60 55)"/>
                    <rect x="34" y="18" width="44" height="58" rx="7" fill="rgba(74,166,255,.10)" stroke="rgba(147,197,253,.7)" stroke-width="1.6"/>
                    <path d="M42 32h28M42 42h28M42 52h20" stroke="rgba(147,197,253,.75)" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="82" cy="70" r="17" fill="rgba(20,184,166,.15)" stroke="#2dd4bf" stroke-width="1.8"/>
                    <path d="M75 70l5 5 10-10" stroke="#2dd4bf" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="22" cy="44" r="9" fill="rgba(255,122,0,.14)" stroke="rgba(255,158,60,.8)" stroke-width="1.4"/>
                    <path d="M22 40v5l3 2" stroke="rgba(255,158,60,.9)" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </div>

            <h3 class="ev-terms-side__title">
                Syarat &amp; Ketentuan<br>Penggunaan Akun {{ $evIsDosen ? 'Dosen' : '' }}
            </h3>
            <p class="ev-terms-side__lead">
                Harap membaca dan memahami seluruh syarat dan ketentuan berikut
                sebelum melanjutkan proses verifikasi akun Anda.
            </p>

            <ul class="ev-terms-side__points">
                <li>
                    <span class="ev-terms-side__ico"><i data-lucide="shield-check"></i></span>
                    <div><strong>Keamanan Data</strong><span>Data Anda terlindungi dengan sistem keamanan terbaik.</span></div>
                </li>
                <li>
                    <span class="ev-terms-side__ico"><i data-lucide="check-circle-2"></i></span>
                    <div><strong>Akses Layanan</strong><span>Dengan menyetujui, Anda dapat mengakses seluruh fitur portal.</span></div>
                </li>
                <li>
                    <span class="ev-terms-side__ico"><i data-lucide="heart-handshake"></i></span>
                    <div><strong>Komitmen Bersama</strong><span>Mari menjaga integritas dan penggunaan yang bertanggung jawab.</span></div>
                </li>
            </ul>
        </aside>

        <div class="ev-terms-main">
            <header class="ev-terms-sheet__head">
                <div>
                    <h3 id="evTermsTitle">Syarat &amp; Ketentuan</h3>
                    <p>Scroll ke bawah untuk membaca seluruh ketentuan.</p>
                </div>
                <button type="button" class="ev-terms-close" id="evTermsClose" aria-label="Tutup syarat &amp; ketentuan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </header>

            <div class="ev-terms-sheet__scroll" id="evTermsScroll">
                <article class="ev-terms-block">
                    <span class="ev-terms-block__num">1</span>
                    <span class="ev-terms-block__ico"><i data-lucide="mail"></i></span>
                    <div>
                        <h4>Email aktif &amp; dapat diakses</h4>
                        <p>Gunakan email institusi atau pribadi yang valid dan aktif. Kode OTP dikirim melalui email ini — pastikan Anda dapat mengaksesnya, termasuk folder spam/promosi.</p>
                    </div>
                </article>
                <article class="ev-terms-block">
                    <span class="ev-terms-block__num">2</span>
                    <span class="ev-terms-block__ico"><i data-lucide="lock"></i></span>
                    <div>
                        <h4>OTP bersifat rahasia</h4>
                        <p>Kode OTP hanya berlaku selama 30 menit. Jangan bagikan kode tersebut kepada siapa pun, termasuk pihak yang mengaku dari tim SmartBrail.</p>
                    </div>
                </article>
                <article class="ev-terms-block">
                    <span class="ev-terms-block__num">3</span>
                    <span class="ev-terms-block__ico"><i data-lucide="bell"></i></span>
                    <div>
                        <h4>Notifikasi &amp; persetujuan akun</h4>
                        <p>
                            @if($evIsDosen)
                                Setelah email dan nomor HP terverifikasi, akun Anda akan aktif dan dapat digunakan untuk mengakses seluruh fitur pada portal dosen.
                            @else
                                Email dipakai untuk notifikasi sistem dan konfirmasi keamanan akun Anda.
                            @endif
                        </p>
                    </div>
                </article>
                <article class="ev-terms-block">
                    <span class="ev-terms-block__num">4</span>
                    <span class="ev-terms-block__ico"><i data-lucide="user"></i></span>
                    <div>
                        <h4>Tanggung jawab pengguna</h4>
                        <p>Anda bertanggung jawab menjaga kerahasiaan data akun, termasuk password, OTP, dan seluruh aktivitas yang dilakukan di dalam sistem.</p>
                    </div>
                </article>
                <article class="ev-terms-block">
                    <span class="ev-terms-block__num">5</span>
                    <span class="ev-terms-block__ico"><i data-lucide="ban"></i></span>
                    <div>
                        <h4>Larangan penyalahgunaan</h4>
                        <p>Dilarang menggunakan akun untuk tujuan yang melanggar hukum, menyebarkan informasi palsu, atau merugikan pihak lain.</p>
                    </div>
                </article>
                <article class="ev-terms-block">
                    <span class="ev-terms-block__num">6</span>
                    <span class="ev-terms-block__ico"><i data-lucide="bot"></i></span>
                    <div>
                        <h4>Perlindungan anti-bot</h4>
                        <p>Formulir ini dilindungi reCAPTCHA Google untuk mencegah penyalahgunaan otomatis dan spam.</p>
                    </div>
                </article>
                <div class="ev-terms-end">— Akhir syarat &amp; ketentuan —</div>

                <div class="ev-terms-agree" id="evTermsAgree">
                    <p>
                        Dengan ini saya menyatakan telah membaca, memahami, dan menyetujui
                        seluruh Syarat &amp; Ketentuan yang berlaku, serta bersedia menerima
                        kode OTP dan notifikasi keamanan pada email yang saya daftarkan.
                    </p>
                    <label class="ev-terms-check" id="evTermsCheckLabel">
                        <input type="checkbox" id="evTermsCheck" disabled>
                        <span>Saya telah membaca, memahami, dan menyetujui seluruh syarat &amp; ketentuan yang berlaku.</span>
                    </label>
                </div>
            </div>

            <footer class="ev-terms-sheet__foot">
                <p class="ev-terms-scroll-hint" id="evTermsScrollHint">Scroll sampai bawah — centang pernyataan di akhir teks untuk melanjutkan</p>
                <button type="button" class="ev-btn-primary ev-btn-primary--locked" id="evTermsAccept" disabled aria-disabled="true">
                    Setuju &amp; Lanjutkan
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </footer>
        </div>
    </div>
</div>
