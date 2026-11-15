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
    // Peringatan tampil (idleMs - warnMs) setelah aktivitas terakhir
    const warnMs = Math.min(Math.max(0, (cfg.warnMinutes || 0) * 60 * 1000), Math.max(0, idleMs - 5000));

    let idleTimer = null;
    let warnTimer = null;
    let warnTicker = null;
    let warnEl = null;
    let warnDeadline = 0;
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

    function fmtCountdown(ms) {
        const s = Math.max(0, Math.ceil(ms / 1000));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }

    function ensureWarnEl() {
        if (warnEl) return warnEl;
        warnEl = document.createElement('div');
        warnEl.setAttribute('role', 'alert');
        warnEl.style.cssText = [
            'position:fixed', 'top:16px', 'left:50%', 'transform:translateX(-50%)',
            'z-index:99999', 'display:flex', 'align-items:center', 'gap:12px',
            'background:#1e293b', 'color:#e2e8f0', 'border:1px solid #f59e0b',
            'border-radius:12px', 'padding:12px 16px', 'font:500 14px/1.4 system-ui,sans-serif',
            'box-shadow:0 10px 30px rgba(0,0,0,.45)', 'max-width:min(92vw,480px)',
        ].join(';');
        warnEl.innerHTML =
            '<span style="flex:1">Sesi akan berakhir dalam <b id="sb-warn-count"></b> karena tidak ada aktivitas.</span>' +
            '<button type="button" id="sb-warn-stay" style="background:#f59e0b;color:#111827;border:0;border-radius:8px;padding:7px 12px;font:600 13px system-ui;cursor:pointer;white-space:nowrap">Tetap masuk</button>';
        return warnEl;
    }

    function showWarning() {
        const el = ensureWarnEl();
        warnDeadline = Date.now() + warnMs;
        if (!el.isConnected) document.body.appendChild(el);
        const count = el.querySelector('#sb-warn-count');
        function tick() {
            if (count) count.textContent = fmtCountdown(warnDeadline - Date.now());
        }
        tick();
        if (warnTicker) clearInterval(warnTicker);
        warnTicker = setInterval(tick, 1000);
        const btn = el.querySelector('#sb-warn-stay');
        if (btn) {
            btn.onclick = function () {
                // Klik tombol = aktivitas; listener document juga menangkapnya.
                onActivity(true);
            };
        }
    }

    function hideWarning() {
        if (warnTicker) { clearInterval(warnTicker); warnTicker = null; }
        if (warnEl && warnEl.isConnected) warnEl.remove();
    }

    function clearTimers() {
        if (idleTimer) clearTimeout(idleTimer);
        idleTimer = null;
        if (warnTimer) clearTimeout(warnTimer);
        warnTimer = null;
    }

    function scheduleIdle() {
        clearTimers();
        hideWarning();

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
        if (warnMs > 0 && wait > warnMs + 500) {
            warnTimer = setTimeout(showWarning, wait - warnMs);
        }
        idleTimer = setTimeout(onIdleTimeout, wait);
    }

    function onIdleTimeout() {
        // Idle adalah keputusan klien — JANGAN ping server di sini: ping itu
        // sendiri me-refresh SESSION_LIFETIME sehingga respons selalu "valid"
        // dan timer tak pernah berhenti (bug: idle logout tak pernah terjadi).
        forceLogout();
    }

    function onActivity(force) {
        const now = Date.now();
        if (!force && now - lastActivityReset < 5000) return;
        lastActivityReset = now;
        window.__sbLastActivity = now;
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

    // Heartbeat presence — jaga last_seen_at tetap segar selama user masih
    // berinteraksi baru-baru ini. PENTING: jangan ping saat user diam melebihi
    // pingEveryMs — ping apa pun memperpanjang SESSION_LIFETIME di server dan
    // akan mengalahkan idle-timeout (bug: sesi tidak pernah berakhir).
    setInterval(function () {
        if (Date.now() - lastActivityReset <= pingEveryMs) {
            pingServer().catch(function () { /* presence saja — abaikan error */ });
        }
    }, 150000);

    // Ping awal agar sesi server hidup sejak halaman dibuka
    window.__sbLastActivity = Date.now();
    pingServer().finally(scheduleIdle);
})();
