(function () {
    'use strict';

    function findSubmitButton(form) {
        return form.querySelector('button[type="submit"], input[type="submit"]');
    }

    function setSubmitEnabled(btn, enabled) {
        if (!btn) return;
        btn.disabled = !enabled;
        btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        if (enabled) {
            btn.classList.remove('btn-primary--locked');
        } else {
            btn.classList.add('btn-primary--locked');
        }
    }

    function hasRecaptchaWidget(slot) {
        return !!(slot && slot.dataset.widgetId && window.grecaptcha);
    }

    function recaptchaResponse(slot) {
        if (!hasRecaptchaWidget(slot)) return '';
        try {
            return grecaptcha.getResponse(parseInt(slot.dataset.widgetId, 10)) || '';
        } catch (e) {
            return '';
        }
    }

    function showRecaptchaDomainError(slot) {
        if (!slot) return;
        var err = slot.querySelector('.recaptcha-domain-error');
        if (err) err.hidden = false;
    }

    function detectTheme(explicit) {
        if (explicit === 'dark' || explicit === 'light') return explicit;
        if (document.documentElement.classList.contains('dark')) return 'dark';
        if (document.documentElement.getAttribute('data-theme') === 'dark') return 'dark';
        return 'light';
    }

    function recaptchaRenderOptions(slot, form, submitBtn) {
        function onVerified(ok) {
            if (form && form._simalasRecaptchaUpdate) {
                form._simalasRecaptchaUpdate();
            } else if (submitBtn) {
                setSubmitEnabled(submitBtn, ok);
            }
        }

        return {
            callback: function () { onVerified(true); },
            'expired-callback': function () { onVerified(false); },
            'error-callback': function () {
                onVerified(false);
                showRecaptchaDomainError(slot);
            },
        };
    }

    function bindForm(form) {
        if (!form || form.dataset.recaptchaBound === '1') return;

        var slot = form.querySelector('[data-recaptcha-slot]');
        var submitBtn = findSubmitButton(form);
        if (!slot || !submitBtn) return;

        form.dataset.recaptchaBound = '1';

        var update = function () {
            // Belum ada widget (React belum mount) → jangan kunci tombol selamanya;
            // tapi submit tetap dicek di handler / server.
            if (!hasRecaptchaWidget(slot)) {
                setSubmitEnabled(submitBtn, true);
                return;
            }
            setSubmitEnabled(submitBtn, recaptchaResponse(slot).length > 0);
        };

        form.addEventListener('submit', function (e) {
            if (!hasRecaptchaWidget(slot)) {
                e.preventDefault();
                alert('Verifikasi "Saya bukan robot" belum siap. Tunggu sebentar lalu coba lagi.');
                window.simalasRecaptchaMountPending && window.simalasRecaptchaMountPending();
                return;
            }

            var slotName = slot.getAttribute('data-recaptcha-slot') || '';
            if (!window.simalasRecaptchaAttachToForm(form, slotName)) {
                e.preventDefault();
                alert('Centang verifikasi "Saya bukan robot" terlebih dahulu.');
            }
        });

        slot.addEventListener('click', function () {
            setTimeout(update, 300);
        });

        form._simalasRecaptchaUpdate = update;
        update();
    }

    window.simalasBindRecaptchaForms = function () {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.querySelector('[data-recaptcha-slot]')) {
                bindForm(form);
            }
        });
    };

    /**
     * Render semua slot yang sudah ada di DOM (aman dipanggil berkali-kali).
     * Penting untuk React: form kontak baru muncul setelah API reCAPTCHA ready.
     */
    window.simalasRecaptchaMountPending = function (theme) {
        var siteKey = window.SIMALAS_RECAPTCHA_SITE_KEY;
        if (!siteKey || !window.grecaptcha || typeof grecaptcha.render !== 'function') {
            return 0;
        }

        var resolvedTheme = detectTheme(theme);
        var mounted = 0;

        document.querySelectorAll('[data-recaptcha-slot]').forEach(function (slot) {
            if (slot.dataset.widgetId) return;
            if (slot.dataset.recaptchaDefer === '1') return;

            var el = slot.querySelector('.g-recaptcha');
            if (!el) return;

            var form = slot.closest('form');
            var submitBtn = form ? findSubmitButton(form) : null;

            try {
                var id = grecaptcha.render(el, Object.assign({
                    sitekey: siteKey,
                    theme: resolvedTheme,
                    hl: 'id',
                }, recaptchaRenderOptions(slot, form, submitBtn)));
                slot.dataset.widgetId = String(id);
                mounted += 1;
                if (form) bindForm(form);
            } catch (e) {
                // Element mungkin sudah ter-render / belum siap — abaikan.
            }
        });

        window.simalasBindRecaptchaForms();
        return mounted;
    };

    var retryTimer = null;

    function startRetryMount() {
        if (retryTimer) return;
        var attempts = 0;
        retryTimer = setInterval(function () {
            attempts += 1;
            window.simalasRecaptchaMountPending();
            var pending = document.querySelectorAll(
                '[data-recaptcha-slot]:not([data-widget-id]) .g-recaptcha'
            );
            if (pending.length === 0 || attempts >= 40) {
                clearInterval(retryTimer);
                retryTimer = null;
            }
        }, 350);
    }

    window.simalasRecaptchaReady = function () {
        window.simalasRecaptchaMountPending();
        startRetryMount();
    };

    window.simalasRecaptchaAttachToForm = function (form, slotName) {
        if (!form) return true;

        var slot = slotName
            ? form.querySelector('[data-recaptcha-slot="' + slotName + '"]')
            : form.querySelector('[data-recaptcha-slot]');

        if (!hasRecaptchaWidget(slot)) return true;

        var token = recaptchaResponse(slot);
        if (!token) return false;

        form.querySelectorAll(
            'textarea[name="g-recaptcha-response"], input[name="g-recaptcha-response"]'
        ).forEach(function (el) {
            if (!el.closest('[data-recaptcha-slot]')) {
                el.remove();
            }
        });

        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'g-recaptcha-response';
        inp.value = token;
        form.appendChild(inp);

        return true;
    };

    window.simalasRecaptchaRenderSlot = function (slotName, theme) {
        var siteKey = window.SIMALAS_RECAPTCHA_SITE_KEY;
        if (!siteKey || !window.grecaptcha) return false;

        var slot = document.querySelector('[data-recaptcha-slot="' + slotName + '"]');
        if (!slot) return false;
        if (slot.dataset.widgetId) return true;

        var el = slot.querySelector('.g-recaptcha');
        if (!el) return false;

        var resolvedTheme = detectTheme(theme);
        var form = slot.closest('form');
        var submitBtn = form ? findSubmitButton(form) : null;

        try {
            var id = grecaptcha.render(el, Object.assign({
                sitekey: siteKey,
                theme: resolvedTheme,
                hl: 'id',
            }, recaptchaRenderOptions(slot, form, submitBtn)));
            slot.dataset.widgetId = String(id);
            if (form) bindForm(form);
            return true;
        } catch (e) {
            return false;
        }
    };

    window.simalasRecaptchaReset = function (slotName) {
        if (!window.grecaptcha) return;

        var selector = slotName
            ? '[data-recaptcha-slot="' + slotName + '"]'
            : '[data-recaptcha-slot]';

        document.querySelectorAll(selector).forEach(function (slot) {
            if (!slot.dataset.widgetId) return;
            try {
                grecaptcha.reset(parseInt(slot.dataset.widgetId, 10));
            } catch (e) { /* ignore */ }

            var form = slot.closest('form');
            if (form && form._simalasRecaptchaUpdate) {
                form._simalasRecaptchaUpdate();
            }
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.simalasBindRecaptchaForms();
        startRetryMount();
    });
})();
