/**
 * Modal verifikasi email
 * Tampilan awal: email + reCAPTCHA (terlihat)
 * Klik area captcha → popup S&K wajib (scroll + centang, tanpa batal/silang)
 * Setelah setuju → unlock reCAPTCHA → kirim OTP
 */
(function () {
    'use strict';

    var backdrop = document.getElementById('evModalBackdrop');
    if (!backdrop) return;

    var modal = backdrop.querySelector('.ev-modal');
    var stepEmail = document.getElementById('evStepEmail');
    var stepOtp = document.getElementById('evStepOtp');
    var closeBtn = document.getElementById('evModalClose');
    var doneBtn = document.getElementById('evModalDone');
    var bannerToggle = document.getElementById('emailVerifyBannerToggle');

    var needsEmail = backdrop.dataset.needsEmail === '1';
    var showSuccess = backdrop.dataset.showSuccess === '1';
    var termsAccepted = false;
    var scrolledToEnd = false;

    var emailForm = document.getElementById('evEmailForm');
    var emailInput = document.getElementById('evAccountEmail');
    var emailWrap = document.getElementById('evEmailWrap');
    var emailSubmit = document.getElementById('evEmailSubmit');
    var captchaHit = document.getElementById('evCaptchaHit');
    var recaptchaConfigured = !!window.SIMALAS_RECAPTCHA_SITE_KEY;

    var termsSheet = document.getElementById('evTermsSheet');
    var termsScroll = document.getElementById('evTermsScroll');
    var termsCheck = document.getElementById('evTermsCheck');
    var termsAccept = document.getElementById('evTermsAccept');
    var termsHint = document.getElementById('evTermsScrollHint');

    var emailError = document.getElementById('evEmailError');
    var infoBtn = document.getElementById('evModalInfo');
    var infoPop = document.getElementById('evInfoPop');
    var termsClose = document.getElementById('evTermsClose');
    var termsBackdrop = document.getElementById('evTermsBackdrop');

    // Validasi ketat: kembalikan pesan error spesifik, atau null bila valid
    function emailErrorReason(value) {
        var v = (value || '').trim();
        if (!v) return 'Email wajib diisi.';
        if (v.length > 255) return 'Email terlalu panjang (maksimal 255 karakter).';
        if (v.indexOf('@') === -1) return 'Email harus mengandung @ — contoh: nama@email.com';
        var parts = v.split('@');
        if (parts.length !== 2) return 'Format email tidak valid — hanya satu tanda @.';
        var local = parts[0];
        var domain = parts[1];
        if (!local) return 'Nama pengguna sebelum @ tidak boleh kosong.';
        if (local.length > 64) return 'Nama pengguna sebelum @ terlalu panjang.';
        if (!/^[A-Za-z0-9._%+-]+$/.test(local) || /^\.|\.$/.test(local) || local.indexOf('..') !== -1) {
            return 'Nama pengguna email tidak valid — hindari titik ganda atau karakter aneh.';
        }
        if (!domain) return 'Domain email tidak boleh kosong — contoh: nama@email.com';
        if (domain.indexOf('.') === -1) return 'Domain tidak valid — gunakan akhiran seperti .com, .ac.id, .co.id, .edu';
        var labels = domain.split('.');
        for (var i = 0; i < labels.length; i++) {
            var lb = labels[i];
            if (!lb || !/^[A-Za-z0-9-]+$/.test(lb) || /^-|-$/.test(lb)) {
                return 'Domain tidak valid — periksa ejaan setelah @';
            }
        }
        if (!/^[A-Za-z]{2,}$/.test(labels[labels.length - 1])) {
            return 'Akhiran domain tidak valid — gunakan seperti .com, .ac.id, .co.id, .edu';
        }
        return null;
    }

    function isValidEmail(value) {
        return emailErrorReason(value) === null;
    }

    function setEmailError(msg) {
        if (!emailError) return;
        if (msg) {
            emailError.textContent = msg;
            emailError.hidden = false;
        } else {
            emailError.textContent = '';
            emailError.hidden = true;
        }
    }

    function recaptchaOk() {
        if (!recaptchaConfigured || !emailForm) return true;
        var slot = emailForm.querySelector('[data-recaptcha-slot="account-email"]');
        if (!slot || !slot.dataset.widgetId || !window.grecaptcha) return false;
        try {
            return (grecaptcha.getResponse(parseInt(slot.dataset.widgetId, 10)) || '').length > 0;
        } catch (e) {
            return false;
        }
    }

    function setEmailVisual(state) {
        if (!emailWrap) return;
        emailWrap.classList.remove('is-valid', 'is-invalid');
        var status = document.getElementById('evEmailStatus');
        if (state === 'valid') {
            emailWrap.classList.add('is-valid');
            if (status) status.setAttribute('aria-hidden', 'false');
        } else if (state === 'invalid') {
            emailWrap.classList.add('is-invalid');
            if (status) status.setAttribute('aria-hidden', 'false');
        } else if (status) {
            status.setAttribute('aria-hidden', 'true');
        }
    }

    function updateEmailSubmit() {
        if (!emailSubmit) return;
        var emailOk = emailInput ? isValidEmail(emailInput.value) : false;
        var captchaOk = !recaptchaConfigured || recaptchaOk();
        var ok = emailOk && !remoteTakenReason && termsAccepted && captchaOk;
        if (!recaptchaConfigured) {
            ok = emailOk && !remoteTakenReason && termsAccepted;
        }
        emailSubmit.disabled = !ok;
        emailSubmit.setAttribute('aria-disabled', ok ? 'false' : 'true');
        emailSubmit.classList.toggle('ev-btn-primary--locked', !ok);
    }

    function updateTermsAccept() {
        if (!termsAccept || !termsCheck) return;
        var ok = scrolledToEnd && termsCheck.checked;
        termsAccept.disabled = !ok;
        termsAccept.setAttribute('aria-disabled', ok ? 'false' : 'true');
        termsAccept.classList.toggle('ev-btn-primary--locked', !ok);
    }

    function unlockCaptcha() {
        termsAccepted = true;
        if (captchaHit) {
            captchaHit.hidden = true;
            captchaHit.style.display = 'none';
        }
        var wrap = document.getElementById('evRecaptchaWrap');
        if (wrap) wrap.classList.add('is-unlocked');
        ensureRecaptcha();
        updateEmailSubmit();
    }

    // Cek ketersediaan email ke server — email yang sudah dipakai akun lain
    // langsung ditolak di UI sebelum submit.
    var checkUrl = backdrop.dataset.checkUrl || '';
    var remoteTakenReason = null;
    var emailCheckTimer = null;
    var emailCheckSeq = 0;

    function queueEmailCheck() {
        remoteTakenReason = null;
        if (!checkUrl || !emailInput) return;
        var v = emailInput.value.trim();
        if (emailErrorReason(v) !== null) return; // format lokal belum valid — jangan spam server
        clearTimeout(emailCheckTimer);
        emailCheckTimer = setTimeout(function () {
            var seq = ++emailCheckSeq;
            var meta = document.querySelector('meta[name="csrf-token"]');
            fetch(checkUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': meta ? meta.content : '',
                },
                body: JSON.stringify({ email: v }),
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (seq !== emailCheckSeq) return; // respons usang
                    if (d && d.ok && d.available === false && d.reason) {
                        remoteTakenReason = d.reason;
                        if (emailInput.value.trim() === v) {
                            setEmailVisual('invalid');
                            setEmailError(remoteTakenReason);
                        }
                    } else {
                        remoteTakenReason = null;
                    }
                    updateEmailSubmit();
                })
                .catch(function () {
                    // Jaringan gagal — validasi unique di server tetap jadi gerbang akhir.
                });
        }, 450);
    }

    // Gate: reCAPTCHA hanya boleh dibuka setelah email valid
    function requireValidEmail() {
        if (!emailInput) return true;
        if (remoteTakenReason) {
            setEmailVisual('invalid');
            setEmailError(remoteTakenReason);
            emailInput.focus();
            return false;
        }
        var reason = emailErrorReason(emailInput.value);
        if (reason) {
            setEmailVisual('invalid');
            setEmailError(reason);
            emailWrap.classList.add('is-shake');
            setTimeout(function () { emailWrap.classList.remove('is-shake'); }, 500);
            emailInput.focus();
            return false;
        }
        setEmailError(null);
        return true;
    }

    function openTermsSheet() {
        if (!termsSheet) return;
        scrolledToEnd = false;
        if (termsScroll) termsScroll.scrollTop = 0;
        if (termsCheck) {
            termsCheck.checked = false;
            termsCheck.disabled = true;
        }
        if (termsHint) {
            termsHint.hidden = false;
            termsHint.textContent = 'Scroll sampai bawah — centang pernyataan di akhir teks';
        }
        updateTermsAccept();
        termsSheet.hidden = false;
        termsSheet.classList.add('is-open');
        termsSheet.setAttribute('aria-hidden', 'false');
        setTimeout(checkTermsScrolled, 80);
        setTimeout(checkTermsScrolled, 220);
    }

    function closeTermsSheet() {
        if (!termsSheet) return;
        termsSheet.classList.remove('is-open');
        termsSheet.setAttribute('aria-hidden', 'true');
        termsSheet.hidden = true;
    }

    function checkTermsScrolled() {
        if (!termsScroll) return;
        var el = termsScroll;
        var reached = el.scrollHeight - el.scrollTop - el.clientHeight <= 28;
        if (el.scrollHeight <= el.clientHeight + 4) reached = true;
        scrolledToEnd = reached;
        if (termsCheck) termsCheck.disabled = !reached;
        if (termsHint) {
            termsHint.hidden = reached;
            if (!reached) termsHint.textContent = 'Scroll sampai bawah — centang pernyataan di akhir teks';
        }
        if (!reached && termsCheck) termsCheck.checked = false;
        updateTermsAccept();
    }

    function ensureRecaptcha() {
        if (!recaptchaConfigured || !emailForm) return;
        var slot = emailForm.querySelector('[data-recaptcha-slot="account-email"]');
        if (!slot) return;
        if (slot.dataset.widgetId) {
            updateEmailSubmit();
            return;
        }
        var theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        if (typeof window.simalasRecaptchaRenderSlot === 'function') {
            window.simalasRecaptchaRenderSlot('account-email', theme);
        } else if (typeof window.simalasRecaptchaReady === 'function') {
            window.simalasRecaptchaReady();
        }
        patchRecaptchaUpdate();
        updateEmailSubmit();
    }

    function patchRecaptchaUpdate() {
        if (!emailForm) return;
        var prev = emailForm._simalasRecaptchaUpdate;
        emailForm._simalasRecaptchaUpdate = function () {
            if (typeof prev === 'function') prev();
            updateEmailSubmit();
        };
    }

    function initEmailFormGate() {
        if (!emailForm || !emailInput || !emailSubmit) return;

        emailInput.addEventListener('input', function () {
            var v = emailInput.value.trim();
            if (!v) {
                setEmailVisual('');
                setEmailError(null);
            } else {
                var reason = emailErrorReason(v);
                setEmailVisual(reason ? 'invalid' : 'valid');
                setEmailError(reason);
                if (!reason) queueEmailCheck();
                else remoteTakenReason = null;
            }
            updateEmailSubmit();
        });

        emailInput.addEventListener('blur', function () {
            var v = emailInput.value.trim();
            if (v) {
                var reason = emailErrorReason(v);
                setEmailVisual(reason ? 'invalid' : 'valid');
                setEmailError(reason);
            }
        });

        emailForm.addEventListener('submit', function (e) {
            if (remoteTakenReason) {
                e.preventDefault();
                setEmailVisual('invalid');
                setEmailError(remoteTakenReason);
                emailInput.focus();
                return;
            }
            var reason = emailErrorReason(emailInput.value);
            if (reason) {
                e.preventDefault();
                setEmailVisual('invalid');
                setEmailError(reason);
                emailInput.focus();
                return;
            }
            if (!termsAccepted) {
                e.preventDefault();
                openTermsSheet();
                return;
            }
            if (recaptchaConfigured && typeof window.simalasRecaptchaAttachToForm === 'function') {
                if (!window.simalasRecaptchaAttachToForm(emailForm, 'account-email')) {
                    e.preventDefault();
                    alert('Centang verifikasi "Saya bukan robot" terlebih dahulu.');
                }
            }
        });

        patchRecaptchaUpdate();
        updateEmailSubmit();
        if (emailInput.value.trim()) {
            emailInput.dispatchEvent(new Event('input'));
        }
    }

    function openModal(step) {
        if (step) showStep(step);
        backdrop.classList.add('is-open');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (step === 'email' || (needsEmail && stepEmail && !stepEmail.hidden)) {
            // Render captcha di balik overlay supaya terlihat dari awal
            ensureRecaptcha();
            setTimeout(function () {
                if (emailInput) emailInput.focus();
            }, 150);
        } else {
            var firstOtp = backdrop.querySelector('.ev-otp-input');
            if (firstOtp) setTimeout(function () { firstOtp.focus(); }, 120);
        }
    }

    function closeModal() {
        // Tutup terms sheet dulu bila masih terbuka
        if (termsSheet && termsSheet.classList.contains('is-open')) {
            closeTermsSheet();
        }
        backdrop.classList.remove('is-open');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function showStep(step) {
        if (stepEmail) stepEmail.hidden = step !== 'email';
        if (stepOtp) stepOtp.hidden = step !== 'otp';
        var title = document.getElementById('evModalTitle');
        var sub = document.getElementById('evModalSubtitle');
        if (step === 'otp') {
            if (title) title.textContent = 'Masukkan Kode OTP';
            if (sub) sub.innerHTML = 'Ketik 6 digit kode yang dikirim ke email Anda.';
        } else {
            if (title) title.textContent = 'Verifikasi Email';
            if (sub) sub.textContent = 'Tambahkan email aktif untuk menerima kode OTP dan mengamankan akun Anda.';
        }
    }

    if (captchaHit) {
        captchaHit.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            // Email valid dulu → baru S&K → baru reCAPTCHA
            if (!requireValidEmail()) return;
            openTermsSheet();
        });
    }

    if (termsClose) {
        termsClose.addEventListener('click', closeTermsSheet);
    }
    if (termsBackdrop) {
        termsBackdrop.addEventListener('click', closeTermsSheet);
    }

    if (infoBtn && infoPop) {
        infoBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = infoPop.hidden;
            infoPop.hidden = !open;
            infoBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (infoPop.hidden) return;
            if (infoPop.contains(e.target) || infoBtn.contains(e.target)) return;
            infoPop.hidden = true;
            infoBtn.setAttribute('aria-expanded', 'false');
        });
    }

    if (termsScroll) {
        termsScroll.addEventListener('scroll', checkTermsScrolled, { passive: true });
    }

    if (termsCheck) {
        termsCheck.addEventListener('change', updateTermsAccept);
    }

    if (termsAccept) {
        termsAccept.addEventListener('click', function () {
            if (termsAccept.disabled) return;
            if (!termsCheck || !termsCheck.checked || !scrolledToEnd) return;
            closeTermsSheet();
            unlockCaptcha();
        });
    }

    function openFromTrigger() {
        var step = needsEmail ? 'email' : 'otp';
        showStep(step);
        openModal(step);
    }

    if (bannerToggle) {
        bannerToggle.addEventListener('click', openFromTrigger);
    }

    // Pemicu tambahan (mis. tombol "Verifikasi Email" di halaman profil)
    document.querySelectorAll('[data-email-verify-open]').forEach(function (el) {
        el.addEventListener('click', openFromTrigger);
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (doneBtn) doneBtn.addEventListener('click', closeModal);

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop && !showSuccess) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (infoPop && !infoPop.hidden) {
                infoPop.hidden = true;
                if (infoBtn) infoBtn.setAttribute('aria-expanded', 'false');
                return;
            }
            // Escape menutup terms sheet (batal), tapi reCAPTCHA tetap terkunci
            if (termsSheet && termsSheet.classList.contains('is-open')) {
                closeTermsSheet();
                return;
            }
            if (backdrop.classList.contains('is-open') && !showSuccess) closeModal();
        }
    });

    function initOtpGroup(selector, hiddenId) {
        var inputs = Array.prototype.slice.call(document.querySelectorAll(selector));
        var hidden = document.getElementById(hiddenId);
        if (!inputs.length || !hidden) return;

        function updateHidden() {
            hidden.value = inputs.map(function (i) { return i.value; }).join('');
        }

        inputs.forEach(function (input, idx) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 1);
                updateHidden();
                if (this.value && idx < inputs.length - 1) inputs[idx + 1].focus();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && idx > 0) inputs[idx - 1].focus();
            });
            input.addEventListener('paste', function (e) {
                e.preventDefault();
                var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach(function (ch, i) {
                    if (inputs[i]) inputs[i].value = ch;
                });
                updateHidden();
                inputs[Math.min(pasted.length, inputs.length - 1)].focus();
            });
        });

        var form = hidden.closest('form');
        if (form) form.addEventListener('submit', updateHidden);
    }

    initOtpGroup('.ev-otp-input', 'evOtpHidden');
    initEmailFormGate();

    if (showSuccess && modal) {
        modal.classList.add('is-success');
        if (closeBtn) closeBtn.style.display = 'none';
        openModal();
    } else if (backdrop.dataset.autoOpen === '1') {
        var initialStep = needsEmail ? 'email' : 'otp';
        showStep(initialStep);
        openModal(initialStep);
    }

    if (typeof window.refreshLucideIcons === 'function') {
        window.refreshLucideIcons();
    }
})();
