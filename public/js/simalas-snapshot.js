/**
 * Pembaruan halaman absensi lewat snapshot HTTP.
 * Bukan saluran WebSocket. Dipakai untuk muat awal, tombol manual,
 * kembali ke tab, sambungan online, dan fallback berkala.
 */
(function () {
    function normalizeQuery(query) {
        const src = new URLSearchParams(query || '');
        const keys = Array.from(new Set(Array.from(src.keys()))).sort();
        const out = new URLSearchParams();
        keys.forEach(function (key) {
            src.getAll(key).forEach(function (value) {
                out.append(key, value);
            });
        });
        return out.toString();
    }

    function decide(input) {
        if (input.reason !== 'manual' && input.visible === false) {
            return 'pause';
        }
        if (input.aborted) {
            return 'ignore';
        }
        if (!input.okHttp || input.payloadOk !== true || input.realtime === true) {
            return 'keep';
        }
        if (normalizeQuery(input.sentQuery) !== normalizeQuery(input.currentQuery)) {
            return 'drop';
        }
        if (normalizeQuery(input.payloadQuery) !== normalizeQuery(input.sentQuery)) {
            return 'drop';
        }
        if (input.focused || input.selectionInside) {
            return 'defer';
        }
        if (input.revision && input.revision === input.previousRevision) {
            return 'unchanged';
        }
        return 'apply';
    }

    function text(id, value) {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null) {
            el.textContent = String(value);
        }
    }

    function html(id, value) {
        const el = document.getElementById(id);
        if (!el || typeof value !== 'string') {
            return;
        }
        el.innerHTML = value;
    }

    function selectionInside(surface) {
        const sel = document.getSelection();
        if (!sel || sel.isCollapsed || !sel.anchorNode) {
            return false;
        }
        const roots = document.querySelectorAll('[data-snap-live="' + surface + '"]');
        for (let i = 0; i < roots.length; i += 1) {
            if (roots[i].contains(sel.anchorNode)) {
                return true;
            }
        }
        return false;
    }

    function focusedInside(surface) {
        const active = document.activeElement;
        if (!active) {
            return false;
        }
        const roots = document.querySelectorAll('[data-snap-live="' + surface + '"]');
        for (let i = 0; i < roots.length; i += 1) {
            if (roots[i].contains(active) && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) {
                return true;
            }
        }
        return false;
    }

    function applyRekap(payload) {
        const summary = payload.summary_text || {};
        Object.keys(summary).forEach(function (key) {
            text('rekap-stat-' + key, summary[key]);
        });
        const counts = payload.tab_counts || {};
        Object.keys(counts).forEach(function (key) {
            const el = document.querySelector('[data-snap-count="' + key + '"]');
            if (el) {
                el.textContent = String(counts[key]);
            }
        });
        const regions = payload.regions || {};
        html('rekap-tap-rows', regions.taps);
        html('rekap-rows', regions.rows);
        html('rekap-pager', regions.pager);
        if (window.SimalasCharts && payload.chart && payload.chart.labels) {
            const donut = document.getElementById('rekapDonutChart');
            const bar = document.getElementById('rekapBarChart');
            const values = payload.chart.values || [];
            if (donut) {
                window.SimalasCharts.buildDonutChart(donut, {
                    labels: payload.chart.labels,
                    values: values,
                    colors: payload.chart.colors,
                    centerMain: payload.chart.center || 0,
                    centerSub: 'Mahasiswa',
                });
            }
            if (bar) {
                window.SimalasCharts.buildBarChart(bar, {
                    labels: ['Hadir+', 'Masalah', 'Belum / non-wajib'],
                    values: [
                        (values[0] || 0) + (values[1] || 0) + (values[2] || 0),
                        (values[3] || 0) + (values[4] || 0),
                        (values[5] || 0) + (values[6] || 0),
                    ],
                    colors: ['#0f766e', '#e11d48', '#94a3b8'],
                });
            }
        }
        if (window.refreshLucideIcons) {
            window.refreshLucideIcons();
        }
    }

    function applyLog(payload) {
        const summary = payload.summary_text || {};
        Object.keys(summary).forEach(function (key) {
            text('log-stat-' + key, summary[key]);
        });
        const regions = payload.regions || {};
        html('log-rows', regions.rows);
        html('log-pager', regions.pager);
        text('log-total-label', payload.pagination ? ('Urut siapa duluan absen · Total: ' + payload.pagination.total) : null);
    }

    function applyFingerprint(payload) {
        const summary = payload.summary_text || {};
        Object.keys(summary).forEach(function (key) {
            text('fp-stat-' + key, summary[key]);
        });
        const regions = payload.regions || {};
        html('fp-rows', regions.rows);
        html('fp-pager', regions.pager);
        html('fp-sync-rows', regions.sync_rows);
        const meter = document.getElementById('fp-stat-meter');
        if (meter && payload.summary && payload.summary.completion_rate !== undefined) {
            meter.style.width = Math.min(100, Number(payload.summary.completion_rate)) + '%';
        }
        if (window.refreshLucideIcons) {
            window.refreshLucideIcons();
        }
    }

    const appliers = {
        rekap: applyRekap,
        log: applyLog,
        fingerprint: applyFingerprint,
    };

    function start(options) {
        const surface = options.surface;
        const baseUrl = options.url;
        const baseInterval = options.intervalMs || 20000;
        const statusEl = document.querySelector('[data-snap-status="' + surface + '"]');
        let timer = null;
        let controller = null;
        let fail = 0;
        let seq = 0;
        let stopped = false;
        let previousRevision = '';
        let transport = 'poll';

        function setStatus(message, kind) {
            if (!statusEl) {
                return;
            }
            const prefix = transport === 'socket' ? 'Soket · ' : 'Polling · ';
            statusEl.textContent = prefix + message;
            statusEl.dataset.kind = kind;
            statusEl.dataset.transport = transport;
        }

        function nextDelay() {
            if (fail === 0) {
                return baseInterval;
            }
            return Math.min(60000, baseInterval * Math.pow(2, Math.min(fail, 3)));
        }

        function schedule() {
            if (timer) {
                clearTimeout(timer);
            }
            if (stopped || liveConnected() || document.visibilityState === 'hidden') {
                return;
            }
            timer = setTimeout(function () {
                refresh('interval');
            }, nextDelay());
        }

        function refresh(reason) {
            const my = ++seq;
            const visible = document.visibilityState !== 'hidden';
            const gate = decide({
                reason: reason,
                visible: visible,
                aborted: false,
                okHttp: true,
                payloadOk: true,
                realtime: false,
                sentQuery: '',
                currentQuery: '',
                payloadQuery: '',
                focused: false,
                selectionInside: false,
            });
            if (gate === 'pause') {
                return;
            }
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            const sentQuery = window.location.search.replace(/^\?/, '');
            const url = baseUrl + (sentQuery ? ('?' + sentQuery) : '');
            const scrollY = window.scrollY;
            const scrolls = Array.from(document.querySelectorAll('[data-snap-scroll], .main-content')).map(function (el) {
                return { el: el, top: el.scrollTop };
            });

            fetch(url, {
                credentials: 'same-origin',
                redirect: 'manual',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(function (res) {
                const type = res.headers.get('content-type') || '';
                const redirected = res.type === 'opaqueredirect' || res.status === 301 || res.status === 302;
                if (redirected || !res.ok || type.indexOf('json') === -1) {
                    const error = new Error('snapshot');
                    error.status = redirected ? (res.status || 302) : res.status;
                    throw error;
                }
                return res.json().then(function (payload) {
                    return { payload: payload, status: res.status };
                });
            }).then(function (result) {
                if (my !== seq) {
                    return;
                }
                const payload = result.payload || {};
                const action = decide({
                    reason: reason,
                    visible: document.visibilityState !== 'hidden',
                    aborted: false,
                    okHttp: true,
                    payloadOk: payload.ok === true,
                    realtime: payload.realtime === true,
                    sentQuery: sentQuery,
                    currentQuery: window.location.search.replace(/^\?/, ''),
                    payloadQuery: payload.query || '',
                    focused: focusedInside(surface),
                    selectionInside: selectionInside(surface),
                    revision: payload.revision || '',
                    previousRevision: previousRevision,
                });
                if (action === 'drop' || action === 'ignore') {
                    return;
                }
                if (action === 'defer') {
                    setStatus('Pembaruan ditunda — input atau pilihan sedang dipakai', 'wait');
                    fail = 0;
                    return;
                }
                if (action === 'keep') {
                    const error = new Error('kontrak');
                    error.status = result.status;
                    throw error;
                }
                if (action === 'apply') {
                    const apply = appliers[surface];
                    if (apply) {
                        apply(payload);
                    }
                    previousRevision = payload.revision || previousRevision;
                    window.scrollTo(0, scrollY);
                    scrolls.forEach(function (item) {
                        item.el.scrollTop = item.top;
                    });
                }
                fail = 0;
                const stamp = String(payload.generated_at || '').slice(11, 19);
                const demo = payload.summary_mode === 'demo' ? ' · grafik demo' : '';
                setStatus('Diperbarui ' + stamp + demo, 'ok');
                document.dispatchEvent(new CustomEvent('simalas:snapshot', {
                    detail: { surface: surface, payload: payload, reason: reason, action: action },
                }));
            }).catch(function (error) {
                if (my !== seq || (error && error.name === 'AbortError')) {
                    return;
                }
                fail += 1;
                const status = error && error.status ? error.status : 0;
                window.SimalasSnapshot.lastError = {
                    surface: surface,
                    status: status,
                    at: new Date().toISOString(),
                    reason: reason,
                };
                setStatus('Pembaruan gagal' + (status ? (' HTTP ' + status) : '') + ' — data terakhir tetap', 'bad');
            }).finally(function () {
                if (my === seq) {
                    schedule();
                }
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                seq += 1;
                if (timer) {
                    clearTimeout(timer);
                }
                if (controller) {
                    controller.abort();
                }
                return;
            }
            refresh('visible');
        });
        window.addEventListener('online', function () {
            refresh('online');
        });
        window.addEventListener('pagehide', function () {
            stopped = true;
            seq += 1;
            if (controller) {
                controller.abort();
            }
            if (timer) {
                clearTimeout(timer);
            }
        });
        const button = document.querySelector('[data-snap-refresh="' + surface + '"]');
        if (button) {
            button.addEventListener('click', function () {
                refresh('manual');
            });
        }
        function liveConnected() {
            return transport === 'socket';
        }

        const handle = {
            surface: surface,
            refresh: refresh,
            setTransport: function (next) {
                const previous = transport;
                transport = next === 'socket' ? 'socket' : 'poll';
                if (statusEl) {
                    statusEl.dataset.transport = transport;
                    const rest = statusEl.textContent.replace(/^(Soket|Polling) · /, '');
                    statusEl.textContent = (transport === 'socket' ? 'Soket · ' : 'Polling · ') + rest;
                }
                if (transport === 'socket') {
                    if (timer) {
                        clearTimeout(timer);
                        timer = null;
                    }
                    return;
                }
                if (previous === 'poll' && timer) {
                    return;
                }
                schedule();
            },
        };
        window.SimalasSnapshot.handles[surface] = handle;
        refresh('initial');

        return handle;
    }

    window.SimalasSnapshot = {
        start: start,
        decide: decide,
        normalizeQuery: normalizeQuery,
        handles: {},
        lastError: null,
    };
})();
