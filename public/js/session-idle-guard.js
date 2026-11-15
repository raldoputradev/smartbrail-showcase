/**
 * SIMALAS — idle session guard (auto logout → halaman login)
 * - Idle: reset timer saat ada interaksi (mouse/keyboard/scroll)
 * - Ping server berkala saat aktif agar SESSION_LIFETIME ikut diperpanjang
 * - Absolute: cutoff sejak login (opsional)
 * - Tab kembali fokus: hanya logout jika server bilang sesi invalid (401), bukan network blip
 */
(function () {
    'use strict';

    const el = document.getElementById('session-guard-config');
    if (!el) return;

    let cfg;
    try {
        cfg = JSON.parse(el.textContent || '{}');
    } catch (e) {
        return;
    }

    if (!cfg.enabled) return;

    const idleMs = Math.max(1, cfg.idleMinutes) * 60 * 1000;
    const pingUrl = cfg.pingUrl;
    const logoutUrl = cfg.logoutUrl;
    const csrf = cfg.csrf || '';
    // Perpanjang sesi server setiap ~2 menit selama user masih aktif
    const pingEveryMs = Math.min(120000, Math.max(30000, Math.floor(idleMs / 4)));

    let idleTimer = null;
    let lastActivityReset = 0;
    let lastPingAt = 0;
    let pingInFlight = false;

    function absoluteDeadline() {
        if (!cfg.absoluteExpiresAt) return null;
        const t = Date.parse(cfg.absoluteExpiresAt);
        return Number.isNaN(t) ? null : t;
    }

    function submitLogout(expired) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = logoutUrl;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);

        if (expired) {
            const flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = 'session_expired';
            flag.value = '1';
            form.appendChild(flag);
        }

        document.body.appendChild(form);
        form.submit();
    }

    function forceLogout() {
        submitLogout(true);
    }

    function pingServer() {
        if (pingInFlight || !pingUrl) return Promise.resolve(null);
        pingInFlight = true;
        lastPingAt = Date.now();
        return fetch(pingUrl, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).finally(function () {
            pingInFlight = false;
        });
    }

    function clearTimers() {
        if (idleTimer) clearTimeout(idleTimer);
        idleTimer = null;
    }

    function scheduleIdle() {
        clearTimers();

        const abs = absoluteDeadline();
        if (abs && Date.now() >= abs) {
            forceLogout();
            return;
        }

        // Jangan idle lebih lama dari sisa absolute
        let wait = idleMs;
        if (abs) {
            wait = Math.min(wait, Math.max(1000, abs - Date.now()));
        }
        idleTimer = setTimeout(onIdleTimeout, wait);
    }

    function onIdleTimeout() {
        const abs = absoluteDeadline();
        if (abs && Date.now() >= abs) {
            forceLogout();
            return;
        }
        // Konfirmasi ke server dulu — kalau masih valid, perpanjang idle
        pingServer()
            .then(function (r) {
                if (!r) {
                    forceLogout();
                    return;
                }
                if (r.status === 401) {
                    forceLogout();
                    return;
                }
                if (r.ok) {
                    scheduleIdle();
                    return;
                }
                // Error non-auth: jangan langsung kick
                scheduleIdle();
            })
            .catch(function () {
                // Network blip — coba lagi nanti, jangan logout
                scheduleIdle();
            });
    }

    function onActivity() {
        const now = Date.now();
        if (now - lastActivityReset < 5000) return;
        lastActivityReset = now;
        scheduleIdle();

        // Perpanjang sesi Laravel saat user benar-benar aktif
        if (now - lastPingAt >= pingEveryMs) {
            pingServer().then(function (r) {
                if (r && r.status === 401) forceLogout();
            }).catch(function () { /* ignore */ });
        }
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        document.addEventListener(ev, onActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        pingServer()
            .then(function (r) {
                if (!r) return;
                if (r.status === 401) {
                    forceLogout();
                    return;
                }
                if (r.ok) scheduleIdle();
                // selain itu: biarkan, jangan kick karena 419/500/network
            })
            .catch(function () {
                // Network blip saat ganti tab — jangan logout
            });
    });

    // Heartbeat presence — jaga last_seen_at tetap segar walau user pasif
    // (tab dibiarkan terbuka). Browser men-throttle interval di tab tersembunyi,
    // jadi tidak membebani server.
    setInterval(function () {
        pingServer().catch(function () { /* presence saja — abaikan error */ });
    }, 150000);

    // Ping awal agar sesi server hidup sejak halaman dibuka
    pingServer().finally(scheduleIdle);
})();
