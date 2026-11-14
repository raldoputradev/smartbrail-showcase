@if(\App\Support\Recaptcha::isConfigured())
    <div class="recaptcha-field recaptcha-card" data-recaptcha-slot="{{ $slot ?? 'default' }}" @if(($slot ?? '') === 'account-email') data-recaptcha-defer="1" @endif>
        <div class="recaptcha-card-head">
            <span class="recaptcha-card-icon" aria-hidden="true">
                <i data-lucide="shield-check"></i>
            </span>
            <div class="recaptcha-card-copy">
                <label class="form-label recaptcha-label">Verifikasi keamanan</label>
                <p class="recaptcha-caption">Lindungi formulir dari bot spam.</p>
            </div>
        </div>
        <div class="recaptcha-box">
            <div class="recaptcha-inner">
                <div class="g-recaptcha"></div>
            </div>
        </div>
        @error('g-recaptcha-response')
            <p class="recaptcha-error">{{ $message }}</p>
        @enderror
        <p class="recaptcha-domain-error" hidden>Verifikasi reCAPTCHA tidak dapat dimuat. Domain situs belum terdaftar di Google reCAPTCHA — hubungi administrator.</p>
    </div>
@endif
