/**
 * AIoT SIMALAS — AJAX login + SaaS transition (logo video from 0.5s)
 */
(function () {
    'use strict';

    var MIN_SPINNER_MS = 1200;
    var TEXT_DELAY_MS = 350;
    var HOLD_MS = 3200;
    /** Skip frame gelap di awal MP4 logo. */
    var LOGO_TRIM_START = 0.5;

    function playSafe(video) {
        if (!video) return;
        var p = video.play();
        if (p && typeof p.catch === 'function') p.catch(function () {});
    }

    function seekToTrim(video, thenPlay) {
        if (!video) return;
        video.muted = true;
        video.playsInline = true;

        function apply() {
            try {
                if (video.duration && video.duration > LOGO_TRIM_START) {
                    video.currentTime = LOGO_TRIM_START;
                }
            } catch (e) { /* ignore */ }
            if (thenPlay) playSafe(video);
        }

        if (video.readyState >= 1) {
            apply();
            return;
        }

        video.addEventListener('loadedmetadata', apply, { once: true });
        try { video.load(); } catch (e) { /* ignore */ }
    }

    function bindTrimmedLogoVideo(video) {
        if (!video || video.dataset.logoTrimBound === '1') return;
        video.dataset.logoTrimBound = '1';
        video.muted = true;
        video.playsInline = true;
        video.removeAttribute('loop');
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');

        var seeking = false;

        function jumpToTrim() {
            if (!video.duration || video.duration <= LOGO_TRIM_START) return;
            if (Math.abs(video.currentTime - LOGO_TRIM_START) < 0.04) return;
            seeking = true;
            try {
                video.currentTime = LOGO_TRIM_START;
            } catch (e) { /* ignore */ }
        }

        video.addEventListener('loadedmetadata', function () {
            jumpToTrim();
        });

        video.addEventListener('seeked', function () {
            seeking = false;
        });

        video.addEventListener('timeupdate', function () {
            if (seeking || !video.duration) return;
            if (video.currentTime >= video.duration - 0.08) {
                jumpToTrim();
                playSafe(video);
                return;
            }
            if (video.currentTime < LOGO_TRIM_START - 0.05) {
                jumpToTrim();
            }
        });

        video.addEventListener('ended', function () {
            jumpToTrim();
            playSafe(video);
        });

        if (video.readyState >= 1) {
            jumpToTrim();
        }
    }

    function initLogoVideos() {
        document.querySelectorAll('video.js-logo-anim').forEach(function (video) {
            bindTrimmedLogoVideo(video);
            try { video.preload = 'auto'; } catch (e) { /* ignore */ }
        });
    }

    function restartTransitionLogo() {
        var overlay = document.getElementById('authLoginTransition');
        if (!overlay) return;
        var video = overlay.querySelector('video.js-logo-anim');
        if (!video) return;
        bindTrimmedLogoVideo(video);
        seekToTrim(video, true);
    }

    function firstValidationMessage(payload) {
        if (!payload || !payload.errors) return null;
        var errors = payload.errors;
        var keys = Object.keys(errors);
        for (var i = 0; i < keys.length; i++) {
            var val = errors[keys[i]];
            if (Array.isArray(val) && val[0]) return val[0];
            if (typeof val === 'string' && val) return val;
        }
        return payload.message || null;
    }

    function setButtonLoading(btn, loading) {
        btn.classList.toggle('is-loading', loading);
        btn.disabled = !!loading;
    }

    function resetButton(btn) {
        setButtonLoading(btn, false);
    }

    function delay(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function showTransitionOverlay(reduceMotion) {
        var overlay = document.getElementById('authLoginTransition');
        if (!overlay) return delay(reduceMotion ? 0 : HOLD_MS);

        overlay.hidden = false;
        overlay.removeAttribute('hidden');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.remove('is-text-visible');
        overlay.classList.remove('is-logo-active');
        overlay.classList.remove('is-visible');

        restartTransitionLogo();

        var started = Date.now();

        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    overlay.classList.add('is-visible');

                    if (reduceMotion) {
                        overlay.classList.add('is-logo-active');
                        overlay.classList.add('is-text-visible');
                        resolve();
                        return;
                    }

                    overlay.classList.add('is-logo-active');

                    setTimeout(function () {
                        overlay.classList.add('is-text-visible');
                    }, TEXT_DELAY_MS);

                    var elapsed = Date.now() - started;
                    var remaining = Math.max(0, HOLD_MS - elapsed);
                    setTimeout(resolve, remaining);
                });
            });
        });
    }

    function redirectTo(url) {
        try {
            var target = new URL(url, window.location.href);
            if (target.origin !== window.location.origin) {
                window.location.href = target.pathname + target.search + target.hash;
                return;
            }
            window.location.href = target.href;
        } catch (e) {
            window.location.href = url;
        }
    }

    function showError(message) {
        var t = (window.simalasT && window.simalasT()) || {};
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: t.errorTitle || 'Oops!',
                text: message || 'Login gagal. Periksa kredensial Anda.',
                confirmButtonColor: '#f43f5e',
            });
        } else {
            alert(message || 'Login gagal.');
        }
    }

    function initAjaxLogin() {
        var btn = document.getElementById('loginSubmitBtn');
        var form = document.querySelector('#panelLogin form');
        if (!btn || !form) return;

        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var busy = false;

        form.addEventListener('submit', function (e) {
            if (busy) {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            busy = true;
            setButtonLoading(btn, true);

            var formData = new FormData(form);
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (csrf && csrf.content) {
                headers['X-CSRF-TOKEN'] = csrf.content;
            }

            var fetchPromise = fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: headers,
                credentials: 'same-origin',
            }).then(function (res) {
                var contentType = (res.headers.get('content-type') || '').toLowerCase();

                // Fallback: server redirect HTML (bukan JSON)
                if (contentType.indexOf('application/json') === -1) {
                    if (res.redirected && res.url) {
                        return { ok: true, status: res.status, data: { redirect: res.url, ok: true } };
                    }
                    if (res.ok) {
                        return { ok: true, status: res.status, data: { redirect: res.url || window.location.href, ok: true } };
                    }
                    return { ok: false, status: res.status, data: { message: 'Respons server tidak valid.' } };
                }

                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                }).catch(function () {
                    return { ok: false, status: res.status, data: { message: 'Respons server tidak valid.' } };
                });
            });

            Promise.all([fetchPromise, delay(reduceMotion ? 200 : MIN_SPINNER_MS)])
                .then(function (results) {
                    var result = results[0];

                    if (!result.ok) {
                        resetButton(btn);
                        busy = false;
                        var payload = result.data || {};
                        if (payload.code === 'EMAIL_NOT_VERIFIED' && payload.auth_panel && typeof window.switchPanel === 'function') {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Verifikasi Email',
                                    text: payload.message || 'Email belum diverifikasi.',
                                    confirmButtonColor: '#e11d48',
                                }).then(function () {
                                    window.switchPanel(payload.auth_panel);
                                });
                            } else {
                                window.switchPanel(payload.auth_panel);
                            }
                            return;
                        }
                        var msg = firstValidationMessage(payload)
                            || payload.message
                            || 'Login gagal. Periksa kredensial Anda.';
                        showError(msg);
                        return;
                    }

                    var data = result.data || {};
                    if (!data.redirect) {
                        resetButton(btn);
                        busy = false;
                        showError('Respons server tidak valid.');
                        return;
                    }

                    setButtonLoading(btn, false);

                    showTransitionOverlay(reduceMotion).then(function () {
                        redirectTo(data.redirect);
                    });
                })
                .catch(function () {
                    resetButton(btn);
                    busy = false;
                    showError('Koneksi gagal. Periksa jaringan Anda dan coba lagi.');
                });
        });
    }

    function initHeroTitleFlip() {
        var root = document.getElementById('authHeroTitleFlip');
        if (!root) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var text = (root.getAttribute('aria-label') || root.textContent || 'AIoT SIMALAS').trim();
        root.textContent = '';
        root.classList.add('is-ready');

        var chars = [];
        for (var i = 0; i < text.length; i++) {
            var ch = text.charAt(i);
            var span = document.createElement('span');
            span.className = 'auth-hero-ch' + (ch === ' ' ? ' auth-hero-ch--space' : '');
            span.textContent = ch === ' ' ? '\u00a0' : ch;
            root.appendChild(span);
            chars.push(span);
        }

        var n = chars.length;
        if (!n) return;

        var STAGGER_MS = 78;
        var FLIP_MS = 680;
        var HOLD_MS = 1700;
        var START_DELAY_MS = 1100;
        var cycle = 0;
        var timer = null;

        function clearFlip(nodes) {
            for (var i = 0; i < nodes.length; i++) {
                nodes[i].classList.remove('is-flipping');
                nodes[i].style.animationDelay = '';
            }
        }

        function runFlip(mode) {
            clearFlip(chars);
            void root.offsetWidth;

            for (var i = 0; i < n; i++) {
                var delay = 0;
                if (mode === 'wave') delay = i * STAGGER_MS;
                else if (mode === 'reverse') delay = (n - 1 - i) * STAGGER_MS;
                else if (mode === 'center') {
                    var mid = (n - 1) / 2;
                    delay = Math.abs(i - mid) * STAGGER_MS;
                }
                chars[i].style.animationDelay = delay + 'ms';
                chars[i].classList.add('is-flipping');
            }

            var staggerSpan = mode === 'sync' ? 0 : (n - 1) * STAGGER_MS;
            if (mode === 'center') staggerSpan = Math.ceil(midOrZero(n) * STAGGER_MS);
            return FLIP_MS + staggerSpan;
        }

        function midOrZero(len) {
            return (len - 1) / 2;
        }

        function pickMode() {
            var bag = ['wave', 'wave', 'wave', 'reverse', 'sync', 'center', 'wave', 'sync'];
            return bag[cycle % bag.length];
        }

        function scheduleNext(afterMs) {
            if (timer) clearTimeout(timer);
            timer = setTimeout(tick, afterMs);
        }

        function tick() {
            var mode = pickMode();
            cycle += 1;
            var duration = runFlip(mode);
            scheduleNext(duration + HOLD_MS);
        }

        scheduleNext(START_DELAY_MS);
    }

    function boot() {
        initLogoVideos();
        initAjaxLogin();
        initHeroTitleFlip();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
