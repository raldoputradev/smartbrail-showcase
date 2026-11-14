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
    data-verify-email="{{ $evEmail }}">
    <div class="ev-modal {{ $evShowSuccess ? 'is-success' : '' }}" role="dialog" aria-modal="true" aria-labelledby="evModalTitle">
        @unless($evShowSuccess)
        <button type="button" class="ev-modal-close" id="evModalClose" aria-label="Tutup">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
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

{{-- Popup syarat & ketentuan: wajib, tidak bisa ditutup --}}
<div class="ev-terms-sheet" id="evTermsSheet" aria-hidden="true" hidden>
    <div class="ev-terms-sheet__backdrop" aria-hidden="true"></div>
    <div class="ev-terms-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="evTermsTitle">
        <div class="ev-terms-sheet__handle" aria-hidden="true"></div>
        <header class="ev-terms-sheet__head">
            <h3 id="evTermsTitle">Syarat &amp; Ketentuan</h3>
            <p>Scroll sampai bawah, lalu centang setuju untuk melanjutkan.</p>
        </header>
        <div class="ev-terms-sheet__scroll" id="evTermsScroll">
            <p class="ev-terms-lead">
                @if($evIsDosen)
                    Verifikasi email adalah standar keamanan akun dosen di AIoT SIMALAS sebelum akses panel penuh.
                @else
                    Verifikasi email adalah standar keamanan akun di AIoT SIMALAS.
                @endif
            </p>
            <article class="ev-terms-block">
                <h4>1. Email aktif &amp; dapat diakses</h4>
                <p>Gunakan email institusi atau pribadi yang Anda cek rutin. Periksa juga folder spam/promosi jika kode belum masuk.</p>
            </article>
            <article class="ev-terms-block">
                <h4>2. OTP bersifat rahasia</h4>
                <p>Kode 6 digit berlaku 30 menit. Jangan bagikan ke siapapun, termasuk pihak yang mengaku admin atau laboran.</p>
            </article>
            <article class="ev-terms-block">
                <h4>3. Notifikasi &amp; persetujuan akun</h4>
                <p>
                    @if($evIsDosen)
                        Setelah email terverifikasi, akun dosen tetap menunggu persetujuan admin laboran sebelum login penuh ke panel.
                    @else
                        Email dipakai untuk notifikasi sistem dan konfirmasi keamanan akun.
                    @endif
                </p>
            </article>
            <article class="ev-terms-block">
                <h4>4. Tanggung jawab pengguna</h4>
                <p>Anda bertanggung jawab menjaga kerahasiaan kredensial, OTP, dan seluruh aktivitas di akun ini.</p>
            </article>
            <article class="ev-terms-block">
                <h4>5. Perlindungan anti-bot</h4>
                <p>Formulir dilindungi reCAPTCHA Google untuk mencegah penyalahgunaan otomatis dan spam.</p>
            </article>
            <article class="ev-terms-block">
                <h4>6. Kelanjutan proses</h4>
                <p>Dengan menyetujui, Anda melanjutkan centang reCAPTCHA lalu pengiriman kode OTP ke email yang Anda masukkan.</p>
            </article>
            <div class="ev-terms-end">— Akhir syarat &amp; ketentuan —</div>
        </div>
        <footer class="ev-terms-sheet__foot">
            <p class="ev-terms-scroll-hint" id="evTermsScrollHint">Scroll ke bawah untuk mengaktifkan centang</p>
            <label class="ev-terms-check" id="evTermsCheckLabel">
                <input type="checkbox" id="evTermsCheck" disabled>
                <span>Saya telah membaca dan menyetujui syarat &amp; ketentuan di atas.</span>
            </label>
            <button type="button" class="ev-btn-primary ev-btn-primary--locked" id="evTermsAccept" disabled aria-disabled="true">
                Setuju &amp; Lanjutkan ke reCAPTCHA
            </button>
        </footer>
    </div>
</div>
