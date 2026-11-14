/**
 * SIMALAS — Chart.js presets (modern line + donut)
 * Ringan: animasi terbatas, DPR capped, hormati prefers-reduced-motion.
 */
window.SimalasCharts = (function () {
    const font = "'Inter', system-ui, sans-serif";
    const reduceMotion = () =>
        typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function gridColor() {
        return document.documentElement.getAttribute('data-theme') === 'dark'
            ? 'rgba(148, 163, 184, 0.1)'
            : 'rgba(148, 163, 184, 0.22)';
    }

    function textColor() {
        return document.documentElement.getAttribute('data-theme') === 'dark'
            ? '#e2e8f0'
            : '#475569';
    }

    function legendTextColor() {
        return document.documentElement.getAttribute('data-theme') === 'dark'
            ? '#f1f5f9'
            : '#334155';
    }

    function makeGradient(ctx, area, rgb) {
        if (!area) return `rgba(${rgb}, 0.12)`;
        const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        g.addColorStop(0, `rgba(${rgb}, 0.28)`);
        g.addColorStop(1, `rgba(${rgb}, 0.02)`);
        return g;
    }

    function animDuration(ms) {
        return reduceMotion() ? 0 : Math.min(ms, 560);
    }

    /** Count-up odometer: gulung digit (bukan naik polos). */
    const _countTimers = new WeakMap();
    function animateCount(el, rawValue, opts) {
        if (!el) return;
        const options = opts || {};
        const duration = Math.min(Math.max(options.duration || 1.15, 0.6), 2.2);
        const suffix = options.suffix || '';
        const prefix = options.prefix || '';

        const prev = _countTimers.get(el);
        if (prev) {
            clearInterval(prev.interval);
            cancelAnimationFrame(prev.raf);
            if (prev.timeouts) prev.timeouts.forEach(clearTimeout);
        }

        const text = String(rawValue ?? '').trim();
        const finalize = (finalText) => {
            el.classList.remove('simalas-odo', 'simalas-odo--slash');
            el.textContent = finalText;
        };

        if (reduceMotion()) {
            if (/^\d+\s*\/\s*\d+$/.test(text)) {
                finalize(text.replace(/\s/g, ''));
            } else if (suffix === '%' || text.includes('%')) {
                const n = Number(String(rawValue).replace(/[^\d.-]/g, ''));
                finalize(prefix + (Number.isFinite(n) ? (String(rawValue).includes('.') ? n.toFixed(1) : String(Math.round(n))) : text) + '%');
            } else {
                const n = Number(String(rawValue).replace(/[^\d.-]/g, ''));
                finalize(prefix + (Number.isFinite(n) ? String(Math.round(n)) : text) + suffix);
            }
            return;
        }

        // "12/20" → dua odometer
        if (/^\d+\s*\/\s*\d+$/.test(text)) {
            const parts = text.split('/').map((p) => parseInt(p.trim(), 10) || 0);
            const finalText = parts[0] + '/' + parts[1];
            mountSlashOdometer(el, parts[0], parts[1], duration, () => finalize(finalText));
            return;
        }

        const numeric = Number(String(rawValue).replace(/[^\d.-]/g, ''));
        const target = Number.isFinite(numeric) ? Math.max(0, Math.round(numeric)) : 0;
        const isPct = suffix === '%' || String(rawValue).includes('%');
        const decimals = isPct && String(rawValue).includes('.') ? 1 : 0;
        const finalText = prefix
            + (decimals ? Number(numeric).toFixed(1) : String(target))
            + (isPct ? '%' : suffix);

        mountOdometer(el, target, {
            duration,
            prefix,
            suffix: isPct ? '%' : suffix,
            decimals,
            onDone: () => finalize(finalText),
        });
    }

    function mountSlashOdometer(el, a, b, duration, onDone) {
        el.classList.add('simalas-odo', 'simalas-odo--slash');
        el.innerHTML = '';
        const left = document.createElement('span');
        const sep = document.createElement('span');
        const right = document.createElement('span');
        sep.className = 'simalas-odo__sep';
        sep.textContent = '/';
        el.appendChild(left);
        el.appendChild(sep);
        el.appendChild(right);
        const state = { interval: 0, raf: 0, timeouts: [] };
        _countTimers.set(el, state);
        const doneMs = (Math.min(Math.max(duration, 0.9), 1.85) + 0.35) * 1000;
        runDigitReel(left, a, duration * 0.95, state);
        state.timeouts.push(setTimeout(() => runDigitReel(right, b, duration * 0.85, state), 90));
        if (typeof onDone === 'function') {
            state.timeouts.push(setTimeout(onDone, doneMs));
        }
    }

    function mountOdometer(el, target, cfg) {
        el.classList.add('simalas-odo');
        el.innerHTML = '';
        if (cfg.prefix) {
            const p = document.createElement('span');
            p.className = 'simalas-odo__affix';
            p.textContent = cfg.prefix;
            el.appendChild(p);
        }
        const host = document.createElement('span');
        host.className = 'simalas-odo__value';
        el.appendChild(host);
        if (cfg.suffix) {
            const s = document.createElement('span');
            s.className = 'simalas-odo__affix';
            s.textContent = cfg.suffix;
            el.appendChild(s);
        }
        const state = { interval: 0, raf: 0, timeouts: [] };
        _countTimers.set(el, state);

        if (cfg.decimals > 0) {
            const start = performance.now();
            const from = 0;
            const to = target;
            const tick = (now) => {
                const p = Math.min(1, (now - start) / (cfg.duration * 1000));
                const eased = 1 - Math.pow(1 - p, 3);
                const v = from + (to - from) * eased;
                host.textContent = v.toFixed(1);
                if (p < 1) state.raf = requestAnimationFrame(tick);
                else if (typeof cfg.onDone === 'function') cfg.onDone();
            };
            state.raf = requestAnimationFrame(tick);
            return;
        }

        runDigitReel(host, target, cfg.duration, state);
        if (typeof cfg.onDone === 'function') {
            const base = Math.min(Math.max(cfg.duration, 0.9), 1.85);
            const digits = String(Math.max(0, Math.round(target))).length;
            const doneMs = (base + Math.max(0, digits - 1) * 0.22) * 1000 + 120;
            state.timeouts.push(setTimeout(cfg.onDone, doneMs));
        }
    }

    function runDigitReel(host, target, duration, state) {
        const str = String(Math.max(0, Math.round(target)));
        host.innerHTML = '';
        host.className = 'simalas-odo__digits';
        host.style.cssText = 'display:inline-flex;align-items:center;overflow:hidden;max-width:100%;';

        const cellEm = 1.15;
        const cols = [];
        for (let i = 0; i < str.length; i++) {
            const col = document.createElement('span');
            col.className = 'simalas-odo__col';
            col.style.cssText = 'display:inline-block;overflow:hidden;height:' + cellEm + 'em;width:0.62em;vertical-align:bottom;';

            const windowEl = document.createElement('span');
            windowEl.className = 'simalas-odo__window';
            windowEl.style.cssText = 'display:block;height:' + cellEm + 'em;overflow:hidden;position:relative;width:100%;';

            const strip = document.createElement('span');
            strip.className = 'simalas-odo__strip';
            strip.style.cssText = 'display:flex;flex-direction:column;align-items:center;will-change:transform;transform:translate3d(0,0,0);';

            // 2 putaran penuh lalu berhenti di digit target
            for (let r = 0; r < 3; r++) {
                for (let d = 0; d <= 9; d++) {
                    const cell = document.createElement('span');
                    cell.className = 'simalas-odo__cell';
                    cell.style.cssText = 'display:flex;align-items:center;justify-content:center;height:' + cellEm + 'em;line-height:' + cellEm + 'em;width:100%;flex:0 0 ' + cellEm + 'em;margin:0;padding:0;text-transform:none;font-size:inherit;font-weight:inherit;letter-spacing:inherit;color:inherit;';
                    cell.textContent = String(d);
                    strip.appendChild(cell);
                }
            }
            windowEl.appendChild(strip);
            col.appendChild(windowEl);
            host.appendChild(col);
            cols.push({ strip, digit: parseInt(str[i], 10) || 0 });
        }

        void host.offsetHeight;
        const base = Math.min(Math.max(duration, 0.9), 1.85);
        cols.forEach((c, i) => {
            const steps = 20 + c.digit;
            const delay = (cols.length - 1 - i) * 0.05 + i * 0.025;
            const dur = base + i * 0.12;
            c.strip.style.transition = 'transform ' + dur + 's cubic-bezier(0.15, 0.85, 0.12, 1) ' + delay + 's';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    c.strip.style.transform = 'translate3d(0, ' + (-steps * cellEm) + 'em, 0)';
                });
            });
        });

        const totalMs = (base + Math.max(0, cols.length - 1) * 0.2) * 1000 + 80;
        state.timeouts.push(setTimeout(() => {
            host.classList.add('is-done');
            state.timeouts.push(setTimeout(() => host.classList.remove('is-done'), 400));
        }, totalMs));
    }

    function centerTextPlugin(mainLabel, subLabel) {
        return {
            id: 'simalasCenterText',
            afterDatasetsDraw(chart) {
                const { ctx, chartArea } = chart;
                if (!chartArea) return;
                const ds = chart.data.datasets[0];
                if (!ds) return;
                const total = (ds.data || []).reduce((a, b) => a + Number(b || 0), 0);
                const cx = (chartArea.left + chartArea.right) / 2;
                const cy = (chartArea.top + chartArea.bottom) / 2;
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                const mainColor = isDark ? '#f8fafc' : '#0f172a';
                const subColor = isDark ? '#cbd5e1' : '#475569';
                const badgeBg = isDark ? 'rgba(15, 23, 42, 0.96)' : 'rgba(255, 255, 255, 0.98)';
                const radius = Math.min(chartArea.width, chartArea.height) * 0.2;

                ctx.save();
                ctx.beginPath();
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.fillStyle = badgeBg;
                ctx.fill();
                ctx.strokeStyle = isDark ? 'rgba(148,163,184,0.18)' : 'rgba(148,163,184,0.28)';
                ctx.lineWidth = 1;
                ctx.stroke();

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = mainColor;
                ctx.font = `800 22px ${font}`;
                ctx.fillText(String(mainLabel !== undefined ? mainLabel : total), cx, cy - 6);
                if (subLabel) {
                    ctx.fillStyle = subColor;
                    ctx.font = `700 10px ${font}`;
                    ctx.fillText(subLabel, cx, cy + 14);
                }
                ctx.restore();
            },
        };
    }

    /** Soft fade — tanpa progressive kiri→kanan (lebih santai di CPU). */
    function lineProgressiveAnimation() {
        return undefined;
    }

    function baseOptions(extraAnim) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: Math.min(window.devicePixelRatio || 1, 1.5),
            animation: {
                duration: animDuration(480),
                easing: 'easeOutQuad',
            },
            animations: extraAnim || undefined,
            interaction: { mode: 'nearest', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 14,
                        font: { family: font, size: 11, weight: '600' },
                        color: legendTextColor(),
                    },
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.94)',
                    titleFont: { family: font, size: 12, weight: '700' },
                    bodyFont: { family: font, size: 11 },
                    padding: 10,
                    cornerRadius: 8,
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    caretPadding: 10,
                    displayColors: true,
                },
            },
        };
    }

    function destroyIfExists(canvas) {
        if (!canvas || typeof Chart === 'undefined' || !Chart.getChart) return;
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();
    }

    function buildLineChart(canvas, config) {
        destroyIfExists(canvas);
        const ctx = canvas.getContext('2d');
        const activity = config.activity || 'semua';
        const labels = config.labels || [];
        const datasets = [];
        const primaryLine = config.percentLine || config.absensi;

        if (activity !== 'loker' && primaryLine) {
            const isPercent = Boolean(config.yMaxPercent);
            const lineRgb = isPercent ? '59, 130, 246' : '34, 211, 238';
            const lineColor = isPercent ? '#3b82f6' : '#22d3ee';
            datasets.push({
                label: config.percentLabel || config.absensiLabel || 'Tap Absensi',
                data: primaryLine,
                borderColor: lineColor,
                backgroundColor: (c) => {
                    const { chart } = c;
                    if (!chart.chartArea) return `rgba(${lineRgb}, 0.1)`;
                    return makeGradient(chart.ctx, chart.chartArea, lineRgb);
                },
                fill: true,
                tension: 0.42,
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBorderWidth: 2,
                pointBackgroundColor: '#22d3ee',
                yAxisID: 'y',
            });
        }

        if (activity !== 'absensi' && config.loker) {
            datasets.push({
                label: config.lokerLabel || 'Akses Loker',
                data: config.loker,
                borderColor: '#a78bfa',
                backgroundColor: (c) => {
                    const { chart } = c;
                    if (!chart.chartArea) return 'rgba(167, 139, 250, 0.1)';
                    return makeGradient(chart.ctx, chart.chartArea, '167, 139, 250');
                },
                fill: true,
                tension: 0.42,
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBorderWidth: 2,
                pointBackgroundColor: '#a78bfa',
                yAxisID: activity === 'semua' ? 'y1' : 'y',
            });
        }

        const scales = {
            x: {
                grid: { display: false },
                ticks: { font: { family: font, size: 11 }, color: textColor(), maxTicksLimit: 10 },
                border: { display: false },
            },
            y: {
                beginAtZero: true,
                max: config.yMaxPercent ? 100 : undefined,
                grid: { color: gridColor() },
                ticks: {
                    font: { family: font, size: 11 },
                    color: textColor(),
                    callback: config.yMaxPercent ? (v) => v + '%' : undefined,
                    precision: 0,
                },
                border: { display: false },
            },
        };

        if (activity === 'semua' && config.loker && config.absensi) {
            scales.y1 = {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: { font: { family: font, size: 11 }, color: textColor(), precision: 0 },
                border: { display: false },
            };
        }

        const pointCount = labels.length || 7;
        const progressive = lineProgressiveAnimation(pointCount);

        return new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                ...baseOptions(progressive),
                scales,
            },
        });
    }

    function buildDonutChart(canvas, config) {
        destroyIfExists(canvas);
        const ctx = canvas.getContext('2d');
        const colors = config.colors || ['#22c55e', '#3b82f6', '#f59e0b', '#94a3b8'];
        const values = config.values || [];
        const total = values.reduce((a, b) => a + Number(b || 0), 0);
        const centerMain = config.centerMain !== undefined ? config.centerMain : total;
        const centerSub = config.centerSub || 'Total';
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const useCanvasCenter = config.htmlCenter !== true;

        // Tooltip diarahkan ke tepi ring agar tidak menutupi angka tengah.
        const outerTooltip = {
            id: 'simalasDonutTooltipPos',
            beforeTooltipDraw(chart, args) {
                const tooltip = args.tooltip;
                if (!tooltip || !chart.chartArea) return;
                const cx = (chart.chartArea.left + chart.chartArea.right) / 2;
                const cy = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                const dx = tooltip.caretX - cx;
                const dy = tooltip.caretY - cy;
                const dist = Math.sqrt(dx * dx + dy * dy) || 1;
                const minR = Math.min(chart.chartArea.width, chart.chartArea.height) * 0.28;
                if (dist < minR) {
                    const scale = minR / dist;
                    tooltip.caretX = cx + dx * scale;
                    tooltip.caretY = cy + dy * scale;
                    tooltip.x = tooltip.caretX - tooltip.width / 2;
                    tooltip.y = tooltip.caretY - tooltip.height - 12;
                }
            },
        };

        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: config.labels || [],
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 3,
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                    hoverOffset: 4,
                    spacing: 1,
                }],
            },
            options: {
                ...baseOptions(),
                cutout: '70%',
                interaction: { mode: 'nearest', intersect: true },
                animation: {
                    duration: animDuration(520),
                    easing: 'easeOutQuad',
                    animateRotate: true,
                    animateScale: false,
                },
                plugins: {
                    ...baseOptions().plugins,
                    legend: {
                        ...baseOptions().plugins.legend,
                        display: config.hideLegend ? false : true,
                        position: config.legendPosition || 'bottom',
                        labels: {
                            ...(baseOptions().plugins.legend?.labels || {}),
                            generateLabels(chart) {
                                const data = chart.data;
                                const ds = (data.datasets && data.datasets[0]) || {};
                                const vals = ds.data || [];
                                const sum = vals.reduce((a, b) => a + Number(b || 0), 0);
                                const meta = chart.getDatasetMeta(0);
                                return (data.labels || []).map((label, i) => {
                                    const val = Number(vals[i] || 0);
                                    const pct = sum > 0 ? ((val / sum) * 100).toFixed(0) : '0';
                                    const fill = Array.isArray(ds.backgroundColor)
                                        ? ds.backgroundColor[i]
                                        : ds.backgroundColor;
                                    const fg = legendTextColor();
                                    return {
                                        text: config.legendPercent === false
                                            ? String(label)
                                            : `${label} (${pct}%)`,
                                        fillStyle: fill,
                                        strokeStyle: fill,
                                        fontColor: fg,
                                        color: fg,
                                        lineWidth: 0,
                                        hidden: meta?.data?.[i]?.hidden || false,
                                        index: i,
                                        datasetIndex: 0,
                                    };
                                });
                            },
                        },
                    },
                    tooltip: {
                        ...(baseOptions().plugins.tooltip || {}),
                        callbacks: {
                            label: function (ctx) {
                                const val = Number(ctx.raw || 0);
                                const sum = (ctx.dataset.data || []).reduce((a, b) => a + Number(b || 0), 0) || 1;
                                const pct = ((val / sum) * 100).toFixed(1);
                                return ` ${ctx.label}: ${val.toLocaleString('id-ID')} (${pct}%)`;
                            },
                        },
                    },
                },
            },
            plugins: useCanvasCenter
                ? [centerTextPlugin(centerMain, centerSub), outerTooltip]
                : [outerTooltip],
        });
    }

    function buildStackedBarChart(canvas, config) {
        destroyIfExists(canvas);
        const ctx = canvas.getContext('2d');
        const labels = config.labels || [];
        const series = config.series || [];
        const palette = config.colors || ['#0284c7', '#7c3aed', '#0f766e', '#b45309', '#be123c', '#64748b'];

        const datasets = series.map((s, i) => ({
            label: s.label || s.key || ('Series ' + (i + 1)),
            data: s.data || [],
            backgroundColor: s.color || palette[i % palette.length],
            borderRadius: 5,
            borderSkipped: false,
            maxBarThickness: 34,
            stack: 'iot',
        }));

        return new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    legend: {
                        ...baseOptions().plugins.legend,
                        display: datasets.length > 1,
                    },
                    tooltip: {
                        ...(baseOptions().plugins.tooltip || {}),
                        callbacks: {
                            footer: function (items) {
                                const sum = items.reduce((a, it) => a + Number(it.parsed.y || 0), 0);
                                return 'Total: ' + sum.toLocaleString('id-ID');
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: { font: { family: font, size: 11 }, color: textColor() },
                        border: { display: false },
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: gridColor() },
                        ticks: {
                            font: { family: font, size: 11 },
                            color: textColor(),
                            precision: 0,
                        },
                        border: { display: false },
                        title: {
                            display: true,
                            text: 'Jumlah event',
                            font: { family: font, size: 11, weight: '600' },
                            color: textColor(),
                        },
                    },
                },
            },
        });
    }

    function buildBarChart(canvas, config) {
        destroyIfExists(canvas);
        const ctx = canvas.getContext('2d');
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: config.labels || [],
                datasets: [{
                    data: config.values || [],
                    backgroundColor: config.colors || ['#22c55e', '#94a3b8', '#3b82f6', '#a855f7'],
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 48,
                }],
            },
            options: {
                ...baseOptions(),
                plugins: { ...baseOptions().plugins, legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: font, size: 11 }, color: textColor() },
                        border: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor() },
                        ticks: { font: { family: font, size: 11 }, color: textColor(), precision: 0 },
                        border: { display: false },
                    },
                },
            },
        });
    }

    function hexToRgb(hex) {
        const h = String(hex || '').replace('#', '');
        if (h.length !== 6) return '59, 130, 246';
        return [
            parseInt(h.slice(0, 2), 16),
            parseInt(h.slice(2, 4), 16),
            parseInt(h.slice(4, 6), 16),
        ].join(', ');
    }

    function buildMultiLineChart(canvas, config) {
        destroyIfExists(canvas);
        const ctx = canvas.getContext('2d');
        const labels = config.labels || [];
        const series = config.series || [];
        const progressive = lineProgressiveAnimation(labels.length || 7);
        const useFill = Boolean(config.fill);

        const datasets = series.map((s) => {
            const color = s.color || '#3b82f6';
            const rgb = hexToRgb(color);
            return {
                label: s.label || '',
                data: s.data || [],
                borderColor: color,
                backgroundColor: useFill
                    ? (c) => {
                        const { chart } = c;
                        if (!chart.chartArea) return `rgba(${rgb}, 0.06)`;
                        const g = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                        g.addColorStop(0, `rgba(${rgb}, 0.16)`);
                        g.addColorStop(1, `rgba(${rgb}, 0.01)`);
                        return g;
                    }
                    : 'transparent',
                tension: 0.35,
                borderWidth: 2,
                pointRadius: labels.length > 14 ? 0 : 2.5,
                pointHoverRadius: 5,
                pointBackgroundColor: color,
                fill: useFill,
            };
        });

        return new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                ...baseOptions(progressive),
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: font, size: 11 }, color: textColor(), maxRotation: 45 },
                        border: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { family: font, size: 11 },
                            color: textColor(),
                            precision: 0,
                        },
                        grid: { color: gridColor() },
                        border: { display: false },
                        title: {
                            display: config.yTitle !== false,
                            text: config.yTitle || 'Jumlah event',
                            font: { family: font, size: 11, weight: '600' },
                            color: textColor(),
                        },
                    },
                },
            },
        });
    }

    return { buildLineChart, buildDonutChart, buildBarChart, buildStackedBarChart, buildMultiLineChart, animateCount };
})();
