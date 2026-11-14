/* SIMALAS Copilot — klien chat layar penuh. Output AI tidak pernah dimasukkan sebagai HTML mentah. */
(function () {
    'use strict';

    var cfgEl = document.getElementById('cpConfig');
    if (!cfgEl) return;
    var cfg = JSON.parse(cfgEl.textContent || '{}');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var app = document.getElementById('cpApp');
    var side = document.getElementById('cpSide');
    var scrim = document.getElementById('cpScrim');
    var main = app.querySelector('.cp-main');
    var thread = document.getElementById('cpThread');
    var inner = document.getElementById('cpThreadInner');
    var jumpBtn = document.getElementById('cpJump');
    var historyEl = document.getElementById('cpHistory');
    var historyEmpty = document.getElementById('cpHistoryEmpty');
    var form = document.getElementById('cpForm');
    var input = document.getElementById('cpInput');
    var sendBtn = document.getElementById('cpSend');
    var formError = document.getElementById('cpFormError');
    var formErrorText = document.getElementById('cpFormErrorText');
    var formRetry = document.getElementById('cpFormRetry');
    var live = document.getElementById('cpLive');
    var toggleBtn = document.getElementById('cpToggle');
    var collapseBtn = document.getElementById('cpCollapse');
    var deleteDialog = document.getElementById('cpDeleteDialog');

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var mobileMq = window.matchMedia('(max-width: 860px)');
    var coarse = window.matchMedia('(pointer: coarse)');

    var state = {
        conversationId: null,
        run: null,          // jawaban yang sedang berjalan & tampil
        loadToken: 0,
        stick: true,
        lastScrollTop: 0,
        failedSend: null,   // {text, clientId} bila request belum diterima server
    };

    /* ───────────────────────── util ───────────────────────── */

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = String(text);
        return n;
    }

    function svgIcon(paths) {
        var ns = 'http://www.w3.org/2000/svg';
        var s = document.createElementNS(ns, 'svg');
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('aria-hidden', 'true');
        paths.forEach(function (d) {
            var p = document.createElementNS(ns, 'path');
            p.setAttribute('d', d);
            s.appendChild(p);
        });
        return s;
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        var b = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(b);
        return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    }

    function announce(text) {
        live.textContent = '';
        window.setTimeout(function () { live.textContent = text; }, 40);
    }

    function safeHref(href) {
        try {
            var u = new URL(href, window.location.href);
            if ((u.protocol === 'http:' || u.protocol === 'https:') && u.origin === window.location.origin) {
                return u.href;
            }
        } catch (e) {}
        return null;
    }

    function store(kind, key, value) {
        try {
            var s = kind === 'local' ? window.localStorage : window.sessionStorage;
            if (value === undefined) return s.getItem(key);
            if (value === null || value === '') s.removeItem(key); else s.setItem(key, value);
        } catch (e) {}
        return null;
    }

    /* ─────────────────── Markdown aman (DOM saja) ─────────────────── */

    var ENTITIES = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'", '&nbsp;': '\u00a0' };

    function cleanText(s) {
        return s
            .replace(/<\/?[a-zA-Z][^<>]*>/g, '')
            .replace(/&(amp|lt|gt|quot|#39|nbsp);/g, function (m) { return ENTITIES[m]; });
    }

    var INLINE_RE = /(`+)([^`]|[^`][\s\S]*?[^`])\1(?!`)|\*\*(?=\S)([\s\S]*?\S)\*\*|__(?=\S)([\s\S]*?\S)__(?![\p{L}\p{N}_])|\[([^\]\n]+)\]\(([^()\s]+)\)|\*(?=[^\s*])([^*\n]*?[^\s*])\*|(^|[^\p{L}\p{N}_])_(?=[^\s_])([^_\n]*?[^\s_])_(?![\p{L}\p{N}_])/u;

    function inline(text, parent) {
        var rest = text;
        while (rest.length) {
            var m = INLINE_RE.exec(rest);
            if (!m) { appendText(parent, rest); break; }
            if (m.index > 0) appendText(parent, rest.slice(0, m.index));
            if (m[1]) {
                parent.appendChild(el('code', null, m[2].replace(/^ (.*) $/, '$1')));
            } else if (m[3] !== undefined) {
                var b = el('strong'); inline(m[3], b); parent.appendChild(b);
            } else if (m[4] !== undefined) {
                var b2 = el('strong'); inline(m[4], b2); parent.appendChild(b2);
            } else if (m[5] !== undefined) {
                var href = safeHref(m[6]);
                if (href) {
                    var a = el('a'); a.href = href; inline(m[5], a); parent.appendChild(a);
                } else {
                    inline(m[5], parent);
                }
            } else if (m[7] !== undefined) {
                var i = el('em'); inline(m[7], i); parent.appendChild(i);
            } else if (m[9] !== undefined) {
                if (m[8]) appendText(parent, m[8]);
                var i2 = el('em'); inline(m[9], i2); parent.appendChild(i2);
            }
            rest = rest.slice(m.index + m[0].length);
        }
    }

    function appendText(parent, s) {
        var t = cleanText(s);
        if (t) parent.appendChild(document.createTextNode(t));
    }

    function inlineWithBreaks(lines, parent) {
        lines.forEach(function (ln, idx) {
            if (idx > 0) parent.appendChild(el('br'));
            inline(ln, parent);
        });
    }

    var RE_FENCE = /^\s{0,3}(```|~~~)\s*([\w+-]*)\s*$/;
    var RE_HEAD = /^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/;
    var RE_HR = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/;
    var RE_LIST = /^(\s*)([-*+]|\d{1,3}[.)])\s+(.*)$/;
    var RE_QUOTE = /^\s{0,3}>\s?(.*)$/;
    var RE_TSEP = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;

    function isPipeRow(ln) { return /^\s*\|/.test(ln) || /\S\s*\|\s*\S/.test(ln); }

    function splitRow(ln) {
        var s = ln.trim();
        if (s.charAt(0) === '|') s = s.slice(1);
        if (s.charAt(s.length - 1) === '|') s = s.slice(0, -1);
        return s.replace(/\\\|/g, '\u0000').split('|').map(function (c) { return c.trim().replace(/\u0000/g, '|'); });
    }

    function prepareStreaming(src) {
        var lines = src.split('\n');
        var last = lines[lines.length - 1];
        if (/^\s*\|/.test(last)) {
            lines.pop();
        }
        var n = lines.length;
        if (n && /^\s*\|/.test(lines[n - 1]) && !(n > 1 && /^\s*\|/.test(lines[n - 2]))) {
            lines.pop();
        }
        var text = lines.join('\n');
        var tail = text.slice(text.lastIndexOf('\n') + 1);
        var cut = tail.search(/\[[^\]\n]*(\]\([^)\s]*)?$/);
        if (cut >= 0) text = text.slice(0, text.length - tail.length + cut);
        text = text.replace(/<\/?[a-zA-Z][^<>\n]*$/, '').replace(/[*_]{1,2}$/, '');
        var fences = (text.match(/^\s{0,3}(```|~~~)/gm) || []).length;
        if (fences % 2 === 0) {
            var lastLine = text.slice(text.lastIndexOf('\n') + 1);
            var noCode = lastLine.replace(/`[^`]*`/g, '');
            if ((noCode.match(/`/g) || []).length % 2) text += '`';
            if ((noCode.match(/\*\*/g) || []).length % 2) text += '**';
        }
        return text;
    }

    function renderMarkdown(src, streaming) {
        var frag = document.createDocumentFragment();
        var text = String(src || '').replace(/\r\n?/g, '\n');
        if (streaming) text = prepareStreaming(text);
        var lines = text.split('\n');
        var i = 0;
        var para = [];

        function flushPara() {
            if (!para.length) return;
            var p = el('p');
            inlineWithBreaks(para, p);
            frag.appendChild(p);
            para = [];
        }

        while (i < lines.length) {
            var ln = lines[i];
            var m;

            if ((m = RE_FENCE.exec(ln))) {
                flushPara();
                var fence = m[1];
                var code = [];
                i++;
                while (i < lines.length && lines[i].trim().indexOf(fence) !== 0) { code.push(lines[i]); i++; }
                i++;
                var pre = el('pre');
                pre.appendChild(el('code', null, code.join('\n')));
                frag.appendChild(pre);
                continue;
            }
            if (!ln.trim()) { flushPara(); i++; continue; }
            if ((m = RE_HEAD.exec(ln))) {
                flushPara();
                var h = el(m[1].length <= 2 ? 'h3' : 'h4');
                inline(m[2], h);
                frag.appendChild(h);
                i++;
                continue;
            }
            if (RE_HR.test(ln)) {
                flushPara();
                frag.appendChild(el('hr'));
                i++;
                continue;
            }
            if (isPipeRow(ln) && i + 1 < lines.length && RE_TSEP.test(lines[i + 1])) {
                flushPara();
                var head = splitRow(ln);
                var aligns = splitRow(lines[i + 1]).map(function (c) {
                    return /-:$/.test(c) ? 'is-end' : '';
                });
                i += 2;
                var wrap = el('div', 'cp-table-wrap');
                wrap.tabIndex = 0;
                var table = el('table');
                var thead = el('thead');
                var trh = el('tr');
                head.forEach(function (c, ci) {
                    var th = el('th', aligns[ci] || null);
                    inline(c, th);
                    trh.appendChild(th);
                });
                thead.appendChild(trh);
                table.appendChild(thead);
                var tbody = el('tbody');
                while (i < lines.length && lines[i].trim() && isPipeRow(lines[i])) {
                    var cells = splitRow(lines[i]);
                    var tr = el('tr');
                    for (var ci = 0; ci < head.length; ci++) {
                        var td = el('td', aligns[ci] || null);
                        inline(cells[ci] || '', td);
                        tr.appendChild(td);
                    }
                    tbody.appendChild(tr);
                    i++;
                }
                table.appendChild(tbody);
                wrap.appendChild(table);
                frag.appendChild(wrap);
                continue;
            }
            if ((m = RE_LIST.exec(ln))) {
                flushPara();
                var ordered = /\d/.test(m[2]);
                var list = el(ordered ? 'ol' : 'ul');
                if (ordered && parseInt(m[2], 10) > 1) list.start = parseInt(m[2], 10);
                var baseIndent = m[1].length;
                var li = null;
                var sub = null;
                while (i < lines.length) {
                    var l2 = lines[i];
                    var mm = RE_LIST.exec(l2);
                    if (mm && mm[1].length <= baseIndent + 1) {
                        li = el('li');
                        inline(mm[3], li);
                        list.appendChild(li);
                        sub = null;
                        i++;
                    } else if (mm && li) {
                        if (!sub) { sub = el(/\d/.test(mm[2]) ? 'ol' : 'ul'); li.appendChild(sub); }
                        var sli = el('li');
                        inline(mm[3], sli);
                        sub.appendChild(sli);
                        i++;
                    } else if (l2.trim() && /^\s{2,}\S/.test(l2) && li) {
                        li.appendChild(el('br'));
                        inline(l2.trim(), li);
                        i++;
                    } else {
                        break;
                    }
                }
                frag.appendChild(list);
                continue;
            }
            if ((m = RE_QUOTE.exec(ln))) {
                flushPara();
                var q = [];
                while (i < lines.length && (m = RE_QUOTE.exec(lines[i]))) { q.push(m[1]); i++; }
                var bq = el('blockquote');
                bq.appendChild(renderMarkdown(q.join('\n'), false));
                frag.appendChild(bq);
                continue;
            }
            para.push(ln);
            i++;
        }
        flushPara();
        return frag;
    }

    /* ─────────────────── blok data dari backend ─────────────────── */

    function blockHead(b) {
        if (!b.title && !b.subtitle) return null;
        var h = el('div', 'cp-block__head');
        if (b.title) h.appendChild(el('div', 'cp-block__title', b.title));
        if (b.subtitle) h.appendChild(el('div', 'cp-block__sub', b.subtitle));
        return h;
    }

    function renderBlock(b) {
        if (!b || typeof b !== 'object') return null;
        var box = el('section', 'cp-block');
        var head = blockHead(b);
        if (head && b.type !== 'chart') box.appendChild(head);

        if (b.type === 'stats') {
            var grid = el('div', 'cp-stats');
            (b.items || []).forEach(function (it) {
                var s = el('div', 'cp-stat');
                s.appendChild(el('div', 'cp-stat__label', it.label));
                s.appendChild(el('div', 'cp-stat__value', it.value));
                if (it.meta) s.appendChild(el('div', 'cp-stat__meta', it.meta));
                grid.appendChild(s);
            });
            box.appendChild(grid);
        } else if (b.type === 'table') {
            var rows = Array.isArray(b.rows) ? b.rows : [];
            if (!rows.length) {
                box.appendChild(el('div', 'cp-block__empty', b.empty || 'Tidak ada data.'));
            } else {
                var wrap = el('div', 'cp-table-wrap');
                wrap.tabIndex = 0;
                var table = el('table');
                var thead = el('thead');
                var trh = el('tr');
                var cols = b.columns || [];
                cols.forEach(function (c) {
                    var th = el('th', c.align === 'end' ? 'is-end' : null, c.label);
                    th.scope = 'col';
                    trh.appendChild(th);
                });
                thead.appendChild(trh);
                table.appendChild(thead);
                var tbody = el('tbody');
                rows.forEach(function (r) {
                    var tr = el('tr');
                    cols.forEach(function (c) {
                        var v = r[c.key];
                        tr.appendChild(el('td', c.align === 'end' ? 'is-end' : null, v === null || v === undefined || v === '' ? '—' : v));
                    });
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);
                wrap.appendChild(table);
                box.appendChild(wrap);
            }
        } else if (b.type === 'links') {
            var links = el('div', 'cp-links');
            (b.items || []).forEach(function (it) {
                var href = safeHref(it.href);
                if (!href) return;
                var a = el('a', 'cp-link');
                a.href = href;
                if (it.kind === 'download') {
                    a.appendChild(svgIcon(['M12 4v12', 'M6 11l6 6 6-6', 'M5 20h14']));
                } else {
                    a.appendChild(svgIcon(['M14 4h6v6', 'M20 4l-9 9', 'M18 14v6H4V6h6']));
                    a.target = '_blank';
                    a.rel = 'noopener';
                }
                a.appendChild(document.createTextNode(it.label));
                links.appendChild(a);
            });
            if (!links.childNodes.length) return null;
            box.appendChild(links);
        } else if (b.type === 'choices') {
            var ch = el('div', 'cp-choices');
            (b.options || []).forEach(function (o) {
                var btn = el('button', 'cp-choice', o.label);
                btn.type = 'button';
                btn.addEventListener('click', function () { send(String(o.message || o.label)); });
                ch.appendChild(btn);
            });
            box.appendChild(ch);
        } else if (b.type === 'chart' && b.chart) {
            var c = b.chart;
            var hd = blockHead({ title: c.title, subtitle: c.subtitle });
            if (hd) box.appendChild(hd);
            var holder = el('div', 'cp-chart');
            var canvas = el('canvas');
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', (c.title || 'Grafik') + ', total ' + (c.total || 0));
            holder.appendChild(canvas);
            box.appendChild(holder);
            if (c.note) box.appendChild(el('div', 'cp-block__note', c.note));
            drawChart(canvas, c);
        } else if (b.type === 'archived_action') {
            box.appendChild(el('div', 'cp-archived', 'Aksi lama (diarsipkan, tidak dapat dijalankan dari Copilot): ' + (b.title || '') + ' · ' + (b.status || '')));
        } else {
            return null;
        }
        if (b.note) box.appendChild(el('div', 'cp-block__note', b.note));
        return box;
    }

    var chartLoader = null;
    function loadChartJs() {
        if (window.Chart) return Promise.resolve(window.Chart);
        if (!chartLoader) {
            chartLoader = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = cfg.chartJs;
                s.onload = function () { resolve(window.Chart); };
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }
        return chartLoader;
    }

    function drawChart(canvas, c) {
        loadChartJs().then(function (Chart) {
            if (!canvas.isConnected) return;
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            var color = /^#[0-9a-f]{3,8}$/i.test(c.color || '') ? c.color : '#0284c7';
            new Chart(canvas, {
                type: c.type === 'bar' ? 'bar' : 'line',
                data: {
                    labels: c.labels || [],
                    datasets: [{
                        data: c.values || [],
                        borderColor: color,
                        backgroundColor: color + '33',
                        fill: c.type !== 'bar',
                        tension: 0.3,
                        pointRadius: (c.labels || []).length > 31 ? 0 : 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: reduceMotion.matches ? false : { duration: 300 },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: dark ? '#94a3b8' : '#64748b', maxRotation: 0, autoSkip: true }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { color: dark ? '#94a3b8' : '#64748b', precision: 0 }, grid: { color: dark ? '#1e293b' : '#eef2f7' } },
                    },
                },
            });
        }).catch(function () {
            if (canvas.parentNode) canvas.parentNode.replaceChildren(el('div', 'cp-block__empty', 'Grafik tidak dapat dimuat.'));
        });
    }

    function isWideBlock(b) { return b && (b.type === 'table' || b.type === 'chart' || b.type === 'stats'); }

    /* ─────────────────── pesan ─────────────────── */

    function clearEmpty() {
        var e = inner.querySelector('.cp-empty');
        if (e) e.remove();
    }

    function renderEmpty() {
        inner.replaceChildren();
        var box = el('div', 'cp-empty');
        box.appendChild(el('h2', null, 'Apa yang ingin kamu cek hari ini?'));
        box.appendChild(el('p', null, 'Copilot membaca data absensi dan laporan SIMALAS sesuai hak aksesmu.'));
        var list = (cfg.suggestions || []).slice(0, 4);
        if (list.length) {
            var grid = el('div', 'cp-suggest');
            list.forEach(function (s) {
                var b = el('button');
                b.type = 'button';
                b.appendChild(el('strong', null, s.label));
                if (s.hint) b.appendChild(el('span', null, s.hint));
                b.addEventListener('click', function () {
                    if (s.message) {
                        send(s.message);
                    } else {
                        input.value = s.fill || '';
                        autoGrow();
                        syncSend();
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    }
                });
                grid.appendChild(b);
            });
            box.appendChild(grid);
        }
        inner.appendChild(box);
        jumpBtn.hidden = true;
    }

    function addUserMessage(text) {
        clearEmpty();
        var wrap = el('div', 'cp-msg cp-msg--user');
        wrap.appendChild(el('div', 'cp-msg__body', text));
        inner.appendChild(wrap);
        return wrap;
    }

    function createBotView() {
        clearEmpty();
        var wrap = el('article', 'cp-msg cp-msg--bot');
        wrap.setAttribute('aria-label', 'Jawaban Copilot');
        var md = el('div', 'cp-md');
        var blocks = el('div', 'cp-blocks');
        var foot = el('div', 'cp-msg__foot');
        foot.hidden = true;
        wrap.appendChild(md);
        wrap.appendChild(blocks);
        wrap.appendChild(foot);
        inner.appendChild(wrap);
        return { wrap: wrap, md: md, blocks: blocks, foot: foot, pending: null };
    }

    function setPending(view, label) {
        if (!view.pending) {
            view.pending = el('div', 'cp-pending');
            var dots = el('span', 'cp-dots');
            dots.setAttribute('aria-hidden', 'true');
            dots.appendChild(el('i')); dots.appendChild(el('i')); dots.appendChild(el('i'));
            view.pending.appendChild(dots);
            view.pending.appendChild(el('span', 'cp-pending__label'));
            view.wrap.insertBefore(view.pending, view.blocks);
        }
        view.pending.querySelector('.cp-pending__label').textContent = label;
    }

    function clearPending(view) {
        if (view.pending) { view.pending.remove(); view.pending = null; }
    }

    function appendBlock(view, b) {
        var node = renderBlock(b);
        if (!node) return;
        view.blocks.appendChild(node);
        if (isWideBlock(b)) view.wrap.classList.add('has-wide');
    }

    function setFoot(view, status, errorText, onRetry) {
        view.foot.replaceChildren();
        view.foot.classList.toggle('is-error', status === 'error');
        if (status === 'complete') { view.foot.hidden = true; return; }
        var label = status === 'stopped'
            ? 'Dihentikan — jawaban belum lengkap.'
            : (errorText || 'Jawaban gagal.');
        view.foot.appendChild(el('span', null, label));
        if (onRetry) {
            var b = el('button', null, status === 'stopped' ? 'Buat ulang' : 'Coba lagi');
            b.type = 'button';
            b.addEventListener('click', onRetry);
            view.foot.appendChild(b);
        }
        view.foot.hidden = false;
    }

    /** Pesan riwayat: tampil langsung, tanpa animasi. */
    function renderStoredAssistant(m, retryInfo) {
        var view = createBotView();
        view.md.appendChild(renderMarkdown(m.content || '', false));
        (m.blocks || []).forEach(function (b) { appendBlock(view, b); });
        var status = m.status || 'complete';
        if (status !== 'complete') {
            setFoot(view, status, m.error, retryInfo ? function () {
                startRun(retryInfo.text, retryInfo.clientId, view);
            } : null);
        }
        return view;
    }

    /* ─────────────────── engine tampil bertahap ─────────────────── */

    function Reveal(view) {
        this.view = view;
        this.target = '';
        this.shown = 0;
        this.ended = false;
        this.onFinish = null;
        this.raf = 0;
        this.lastPaint = 0;
        this.lastTick = 0;
        this.cancelled = false;
    }
    Reveal.prototype.push = function (delta) {
        if (this.cancelled) return;
        this.target += delta;
        if (this.target && this.view.pending) clearPending(this.view);
        this.kick();
    };
    Reveal.prototype.end = function (cb) {
        this.ended = true;
        this.onFinish = cb;
        this.kick();
    };
    Reveal.prototype.kick = function () {
        if (this.raf || this.cancelled) return;
        var self = this;
        this.lastTick = performance.now();
        this.raf = requestAnimationFrame(function step(now) {
            self.raf = 0;
            if (self.cancelled) return;
            var backlog = self.target.length - self.shown;
            if (backlog > 0) {
                if (reduceMotion.matches) {
                    self.shown = self.target.length;
                } else {
                    var dt = Math.min(64, now - self.lastTick);
                    var rate = Math.max(0.06, backlog / 450);
                    var n = Math.max(1, Math.round(rate * dt));
                    var next = Math.min(self.target.length, self.shown + n);
                    var sp = self.target.indexOf(' ', next);
                    if (sp > 0 && sp - next < 6) next = sp + 1;
                    self.shown = Math.min(self.target.length, next);
                }
            }
            self.lastTick = now;
            var finished = self.ended && self.shown >= self.target.length;
            if (finished || now - self.lastPaint >= 40) {
                self.paint(!finished);
                self.lastPaint = now;
            }
            if (finished) {
                var cb = self.onFinish; self.onFinish = null;
                if (cb) cb();
                return;
            }
            if (self.shown < self.target.length) {
                self.raf = requestAnimationFrame(step);
            } else if (self.shown && self.lastPaint !== now) {
                self.paint(true);
                self.lastPaint = now;
            }
        });
    };
    Reveal.prototype.paint = function (streaming) {
        this.view.md.replaceChildren(renderMarkdown(this.target.slice(0, this.shown), streaming));
        followBottom();
    };
    Reveal.prototype.flushAll = function () {
        if (this.raf) cancelAnimationFrame(this.raf);
        this.raf = 0;
        this.shown = this.target.length;
        this.view.md.replaceChildren(renderMarkdown(this.target, false));
    };
    Reveal.prototype.cancel = function () {
        this.cancelled = true;
        if (this.raf) cancelAnimationFrame(this.raf);
        this.raf = 0;
    };

    /* ─────────────────── SSE lewat fetch ─────────────────── */

    function parseSse(buffer, onEvent) {
        var parts = buffer.split(/\r?\n\r?\n/);
        var rest = parts.pop();
        parts.forEach(function (chunk) {
            var ev = 'message';
            var data = [];
            chunk.split(/\r?\n/).forEach(function (line) {
                if (!line || line.charAt(0) === ':') return;
                var idx = line.indexOf(':');
                var field = idx < 0 ? line : line.slice(0, idx);
                var val = idx < 0 ? '' : line.slice(idx + 1).replace(/^ /, '');
                if (field === 'event') ev = val;
                else if (field === 'data') data.push(val);
            });
            if (!data.length) return;
            var payload;
            try { payload = JSON.parse(data.join('\n')); } catch (e) { return; }
            onEvent(ev, payload);
        });
        return rest;
    }

    function setBusy(on) {
        sendBtn.classList.toggle('is-stop', on);
        sendBtn.setAttribute('aria-label', on ? 'Hentikan' : 'Kirim');
        sendBtn.title = on ? 'Hentikan' : 'Kirim';
        syncSend();
    }

    function syncSend() {
        var busy = !!state.run;
        sendBtn.disabled = !busy && !input.value.trim();
    }

    function showFormError(msg, retry) {
        formErrorText.textContent = msg;
        formError.hidden = false;
        formRetry.hidden = !retry;
    }

    function hideFormError() {
        formError.hidden = true;
        state.failedSend = null;
    }

    function send(text, clientId) {
        text = String(text || '').trim();
        if (!text || state.run) return;
        hideFormError();
        var draftKey = draftKeyFor(state.conversationId);
        if (input.value.trim() === text) {
            input.value = '';
            autoGrow();
            store('session', draftKey, null);
        }
        var userNode = addUserMessage(text);
        var view = createBotView();
        startRun(text, clientId || uuid(), view, userNode, draftKey);
    }

    function startRun(text, clientId, view, userNode, draftKey) {
        if (state.run) return;
        view.md.replaceChildren();
        view.blocks.replaceChildren();
        view.wrap.classList.remove('has-wide');
        setFoot(view, 'complete');
        setPending(view, 'Memproses…');
        view.wrap.setAttribute('aria-busy', 'true');

        var run = {
            text: text,
            clientId: clientId,
            view: view,
            userNode: userNode || null,
            draftKey: draftKey || null,
            controller: new AbortController(),
            reveal: new Reveal(view),
            accepted: false,
            done: null,
            error: null,
            stoppedByUser: false,
            detached: false,
            conversationId: state.conversationId,
        };
        state.run = run;
        state.stick = true;
        setBusy(true);
        scrollToBottom(false);
        announce('Copilot sedang menjawab.');

        var body = { message: text, client_id: clientId };
        if (state.conversationId) body.conversation_id = state.conversationId;

        fetch(cfg.streamUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
            signal: run.controller.signal,
        }).then(function (res) {
            var ct = res.headers.get('Content-Type') || '';
            if (!res.ok || ct.indexOf('text/event-stream') < 0) {
                return res.json().catch(function () { return {}; }).then(function (j) {
                    var msg = j && j.message ? j.message : httpMessage(res.status);
                    throw { http: res.status, message: msg };
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buf = '';
            function pump() {
                return reader.read().then(function (r) {
                    if (r.done) {
                        buf = parseSse(buf + decoder.decode() + '\n\n', function (ev, d) { onEvent(run, ev, d); });
                        return;
                    }
                    buf = parseSse(buf + decoder.decode(r.value, { stream: true }), function (ev, d) { onEvent(run, ev, d); });
                    return pump();
                });
            }
            return pump();
        }).then(function () {
            if (run.stoppedByUser) return;
            if (!run.done) {
                run.error = run.error || 'Koneksi terputus sebelum jawaban selesai.';
            }
            finishRun(run);
        }).catch(function (err) {
            if (run.stoppedByUser) return;
            run.error = err && err.message ? err.message : 'Koneksi ke server gagal.';
            run.httpStatus = err && err.http;
            finishRun(run);
        });
    }

    function httpMessage(status) {
        if (status === 419) return 'Sesi kedaluwarsa. Muat ulang halaman lalu kirim lagi.';
        if (status === 401) return 'Sesi berakhir. Silakan masuk lagi.';
        if (status === 403) return 'Akses Copilot tidak diizinkan untuk akun ini.';
        if (status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
        if (status === 409) return 'Pesan ini masih diproses. Coba lagi sebentar.';
        return 'Server tidak dapat memproses pesan.';
    }

    function onEvent(run, ev, d) {
        if (ev === 'meta') {
            run.accepted = true;
            if (d.conversation && d.conversation.id) {
                run.conversationId = d.conversation.id;
                upsertHistory(d.conversation);
                if (!run.detached) {
                    if (state.conversationId !== d.conversation.id && run.draftKey === draftKeyFor(null)) {
                        var leftover = store('session', run.draftKey);
                        if (leftover) store('session', draftKeyFor(d.conversation.id), leftover);
                        store('session', run.draftKey, null);
                    }
                    state.conversationId = d.conversation.id;
                    syncUrl(d.conversation.id);
                    markActive(d.conversation.id);
                }
            }
            if (run.detached) cancelDetached(run);
            return;
        }
        if (run.detached) {
            if (ev === 'done') run.done = d;
            return;
        }
        if (ev === 'status') {
            if (!run.reveal.target) setPending(run.view, d.label || 'Memproses…');
        } else if (ev === 'delta') {
            run.reveal.push(String(d.text || ''));
        } else if (ev === 'block') {
            appendBlock(run.view, d.block || d);
            followBottom();
        } else if (ev === 'replay') {
            var m = d.message || {};
            run.reveal.cancel();
            clearPending(run.view);
            run.view.md.replaceChildren(renderMarkdown(m.content || '', false));
            run.view.blocks.replaceChildren();
            (m.blocks || []).forEach(function (b) { appendBlock(run.view, b); });
            run.replayed = true;
        } else if (ev === 'error') {
            run.error = d.message || 'Jawaban gagal.';
        } else if (ev === 'done') {
            run.done = d;
        }
    }

    function finishRun(run) {
        if (run.detached) return;
        var view = run.view;
        var reveal = run.reveal;

        var complete = function () {
            if (state.run !== run) return;
            state.run = null;
            clearPending(view);
            view.wrap.removeAttribute('aria-busy');
            setBusy(false);

            if (!run.accepted) {
                if (run.userNode) run.userNode.remove();
                view.wrap.remove();
                if (!inner.querySelector('.cp-msg')) renderEmpty();
                if (!input.value.trim()) {
                    input.value = run.text;
                    autoGrow();
                    syncSend();
                }
                state.failedSend = { text: run.text, clientId: run.clientId };
                showFormError(run.error || 'Pesan belum terkirim.', run.httpStatus !== 419 && run.httpStatus !== 403);
                announce('Pesan belum terkirim. ' + (run.error || ''));
                return;
            }

            var status = run.done ? run.done.status : 'error';
            if (run.error && status === 'complete') status = 'error';
            if (status !== 'complete') {
                if (!reveal.target && !run.replayed && !view.blocks.childNodes.length) {
                    view.md.replaceChildren();
                }
                setFoot(view, status, run.error, function () { startRun(run.text, run.clientId, view); });
                announce(status === 'stopped' ? 'Jawaban dihentikan.' : 'Jawaban gagal. ' + (run.error || ''));
            } else {
                announce('Jawaban selesai.');
            }
            followBottom();
        };

        if (run.replayed) {
            complete();
        } else if (run.error || !run.done) {
            reveal.flushAll();
            complete();
        } else {
            reveal.end(complete);
        }
    }

    function stopRun() {
        var run = state.run;
        if (!run) return;
        if (run.done) {
            // Server sudah selesai & menyimpan jawaban lengkap: Stop hanya melewati animasi.
            run.reveal.flushAll();
            finishRun(run);
            return;
        }
        run.stoppedByUser = true;
        try { run.controller.abort(); } catch (e) {}
        run.reveal.cancel();
        run.reveal.flushAll();
        run.error = null;
        state.run = null;
        clearPending(run.view);
        run.view.wrap.removeAttribute('aria-busy');
        setBusy(false);
        setFoot(run.view, 'stopped', null, function () { startRun(run.text, run.clientId, run.view); });
        announce('Jawaban dihentikan.');
    }

    /**
     * Pindah percakapan saat jawaban berjalan: tampilan lama berhenti dan event berikutnya diabaikan.
     * Request dibatalkan begitu server sudah mencatat pesan (event meta) agar server menyimpan
     * status "dihentikan" di percakapan asalnya; sebelum itu dibiarkan jalan supaya pesan tidak hilang.
     */
    function detachRun() {
        var run = state.run;
        if (!run) return;
        run.detached = true;
        run.reveal.cancel();
        state.run = null;
        setBusy(false);
        if (run.accepted) cancelDetached(run);
    }

    function cancelDetached(run) {
        if (run.done || run.stoppedByUser) return;
        run.stoppedByUser = true;
        try { run.controller.abort(); } catch (e) {}
    }

    /* ─────────────────── scroll ─────────────────── */

    function nearBottom() {
        return thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
    }

    function scrollToBottom(smooth) {
        thread.scrollTo({ top: thread.scrollHeight, behavior: smooth && !reduceMotion.matches ? 'smooth' : 'auto' });
    }

    function followBottom() {
        if (state.stick) {
            thread.scrollTop = thread.scrollHeight;
        }
        updateJump();
    }

    function updateJump() {
        jumpBtn.hidden = nearBottom() || !inner.querySelector('.cp-msg');
    }

    thread.addEventListener('scroll', function () {
        var top = thread.scrollTop;
        if (top < state.lastScrollTop - 2) state.stick = false;
        if (nearBottom()) state.stick = true;
        state.lastScrollTop = top;
        updateJump();
    }, { passive: true });

    thread.addEventListener('wheel', function (e) {
        if (e.deltaY < 0 && thread.scrollHeight > thread.clientHeight + 4) state.stick = false;
    }, { passive: true });

    jumpBtn.addEventListener('click', function () {
        state.stick = true;
        scrollToBottom(true);
    });

    /* ─────────────────── riwayat ─────────────────── */

    function draftKeyFor(id) { return 'cp_draft:' + (id || 'new'); }

    /* URL menandai percakapan yang dibuka eksplisit (?c=id); tanpa penanda = percakapan baru. */
    function syncUrl(id) {
        if (!cfg.pageUrl || !window.history || !history.replaceState) return;
        var target = new URL(cfg.pageUrl + (id ? '?c=' + encodeURIComponent(id) : ''), location.href);
        if (location.pathname + location.search !== target.pathname + target.search) {
            history.replaceState(null, '', target.pathname + target.search);
        }
    }

    function saveDraft() {
        store('session', draftKeyFor(state.conversationId), input.value);
    }

    function restoreDraft(id) {
        input.value = store('session', draftKeyFor(id)) || '';
        autoGrow();
        syncSend();
    }

    function markActive(id) {
        historyEl.querySelectorAll('.cp-history__item').forEach(function (it) {
            var on = String(it.dataset.id) === String(id);
            it.classList.toggle('is-active', on);
            var btn = it.querySelector('.cp-history__open');
            if (on) btn.setAttribute('aria-current', 'true'); else btn.removeAttribute('aria-current');
        });
    }

    function historyItem(conv) {
        var item = el('div', 'cp-history__item');
        item.dataset.id = conv.id;
        var open = el('button', 'cp-history__open', conv.title || 'Percakapan');
        open.type = 'button';
        open.dataset.open = conv.id;
        var del = el('button', 'cp-history__del');
        del.type = 'button';
        del.dataset.del = conv.id;
        del.setAttribute('aria-label', 'Hapus percakapan ' + (conv.title || ''));
        del.title = 'Hapus';
        del.appendChild(svgIcon(['M3 6h18', 'M8 6V4h8v2', 'M19 6l-1 14H6L5 6']));
        item.appendChild(open);
        item.appendChild(del);
        return item;
    }

    function upsertHistory(conv) {
        var existing = historyEl.querySelector('.cp-history__item[data-id="' + Number(conv.id) + '"]');
        if (existing) {
            existing.querySelector('.cp-history__open').textContent = conv.title || 'Percakapan';
            existing.remove();
        } else {
            existing = historyItem(conv);
        }
        historyEl.insertBefore(existing, historyEl.firstChild);
        historyEmpty.hidden = true;
        if (String(state.conversationId) === String(conv.id)) markActive(conv.id);
    }

    function newChat() {
        if (state.run) detachRun();
        saveDraft();
        state.conversationId = null;
        syncUrl(null);
        markActive(null);
        hideFormError();
        renderEmpty();
        restoreDraft(null);
        closeDrawer();
        input.focus();
    }

    function openConversation(id, opts) {
        opts = opts || {};
        if (String(id) === String(state.conversationId) && !opts.force) { closeDrawer(); return; }
        if (state.run) detachRun();
        if (!opts.keepInput) saveDraft();
        state.conversationId = Number(id);
        syncUrl(id);
        markActive(id);
        hideFormError();
        if (!opts.keepInput) restoreDraft(id);
        closeDrawer();
        var token = ++state.loadToken;
        inner.replaceChildren(el('div', 'cp-pending', 'Memuat percakapan…'));

        fetch(cfg.conversationsUrl + '/' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            if (!r.ok) throw new Error(String(r.status));
            return r.json();
        }).then(function (j) {
            if (token !== state.loadToken) return;
            inner.replaceChildren();
            var msgs = j.messages || [];
            if (!msgs.length) { renderEmpty(); return; }
            var lastUser = null;
            msgs.forEach(function (m) {
                if (m.role === 'user') {
                    addUserMessage(m.content);
                    lastUser = m;
                } else {
                    var retry = lastUser && lastUser.client_id ? { text: lastUser.content, clientId: lastUser.client_id } : null;
                    renderStoredAssistant(m, retry);
                    lastUser = null;
                }
            });
            if (lastUser) {
                var orphan = createBotView();
                var info = lastUser.client_id ? { text: lastUser.content, clientId: lastUser.client_id } : null;
                setFoot(orphan, 'pending', 'Jawaban belum tersimpan — mungkin masih diproses atau sudah dihentikan.', info ? function () {
                    startRun(info.text, info.clientId, orphan);
                } : null);
            }
            state.stick = true;
            scrollToBottom(false);
            updateJump();
        }).catch(function (e) {
            if (token !== state.loadToken) return;
            if (e.message === '404' || e.message === '403') {
                syncUrl(null);
                var gone = historyEl.querySelector('.cp-history__item[data-id="' + Number(id) + '"]');
                if (gone) gone.remove();
                state.conversationId = null;
                renderEmpty();
                return;
            }
            inner.replaceChildren(el('div', 'cp-block__empty', 'Percakapan gagal dimuat. Coba pilih lagi.'));
        });
    }

    var pendingDelete = null;
    historyEl.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-open]');
        if (openBtn) { openConversation(openBtn.dataset.open); return; }
        var delBtn = e.target.closest('[data-del]');
        if (delBtn) {
            pendingDelete = delBtn.dataset.del;
            var title = delBtn.parentNode.querySelector('.cp-history__open').textContent;
            document.getElementById('cpDeleteName').textContent = title;
            deleteDialog.returnValue = '';
            if (deleteDialog.showModal) deleteDialog.showModal();
            else if (window.confirm('Hapus percakapan "' + title + '"?')) doDelete(pendingDelete);
        }
    });

    deleteDialog.addEventListener('close', function () {
        if (deleteDialog.returnValue === 'confirm' && pendingDelete) doDelete(pendingDelete);
        pendingDelete = null;
    });

    function doDelete(id) {
        fetch(cfg.conversationsUrl + '/' + encodeURIComponent(id), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            if (!r.ok) throw new Error();
            var item = historyEl.querySelector('.cp-history__item[data-id="' + Number(id) + '"]');
            if (item) item.remove();
            store('session', draftKeyFor(id), null);
            historyEmpty.hidden = !!historyEl.querySelector('.cp-history__item');
            if (String(state.conversationId) === String(id)) newChat();
        }).catch(function () {
            announce('Percakapan gagal dihapus.');
            window.alert('Percakapan gagal dihapus. Coba lagi.');
        });
    }

    document.getElementById('cpNew').addEventListener('click', newChat);

    /* ─────────────────── sidebar / drawer ─────────────────── */

    function applyLayoutMode() {
        if (mobileMq.matches) {
            var open = app.classList.contains('is-drawer-open');
            side.inert = !open;
            main.inert = open;
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggleBtn.setAttribute('aria-label', 'Buka riwayat');
        } else {
            app.classList.remove('is-drawer-open');
            scrim.hidden = true;
            side.inert = false;
            main.inert = false;
            var collapsed = app.classList.contains('is-collapsed');
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggleBtn.setAttribute('aria-label', 'Tampilkan riwayat');
        }
    }

    function openDrawer() {
        app.classList.add('is-drawer-open');
        scrim.hidden = false;
        applyLayoutMode();
        var first = side.querySelector('.cp-history__item.is-active .cp-history__open') || document.getElementById('cpNew');
        first.focus();
    }

    function closeDrawer() {
        if (!app.classList.contains('is-drawer-open')) return;
        app.classList.remove('is-drawer-open');
        scrim.hidden = true;
        applyLayoutMode();
        toggleBtn.focus({ preventScroll: true });
    }

    toggleBtn.addEventListener('click', function () {
        if (mobileMq.matches) {
            openDrawer();
        } else {
            app.classList.remove('is-collapsed');
            store('local', 'cp_side_collapsed', null);
            applyLayoutMode();
        }
    });

    collapseBtn.addEventListener('click', function () {
        var c = app.classList.toggle('is-collapsed');
        store('local', 'cp_side_collapsed', c ? '1' : null);
        applyLayoutMode();
        collapseBtn.setAttribute('aria-label', c ? 'Tampilkan riwayat' : 'Ciutkan riwayat');
        collapseBtn.title = c ? 'Tampilkan riwayat' : 'Ciutkan riwayat';
    });

    scrim.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && app.classList.contains('is-drawer-open')) closeDrawer();
    });
    if (mobileMq.addEventListener) mobileMq.addEventListener('change', applyLayoutMode);

    /* ─────────────────── composer ─────────────────── */

    function autoGrow() {
        var max = parseFloat(window.getComputedStyle(input).maxHeight) || 200;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, max) + 'px';
        input.style.overflowY = input.scrollHeight > max ? 'auto' : 'hidden';
        if (state.stick) thread.scrollTop = thread.scrollHeight;
    }

    var draftTimer = 0;
    input.addEventListener('input', function () {
        autoGrow();
        syncSend();
        window.clearTimeout(draftTimer);
        draftTimer = window.setTimeout(saveDraft, 250);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.shiftKey || e.isComposing || e.keyCode === 229) return;
        if (coarse.matches) return;
        e.preventDefault();
        if (!state.run) submit();
    });

    function submit() {
        var text = input.value.trim();
        if (!text) return;
        var reuse = state.failedSend && state.failedSend.text === text ? state.failedSend.clientId : null;
        send(text, reuse);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (state.run) { stopRun(); return; }
        submit();
    });

    formRetry.addEventListener('click', function () {
        if (!state.failedSend || state.run) return;
        send(state.failedSend.text, state.failedSend.clientId);
    });

    /* Tinggi viewport nyata (keyboard ponsel / bilah alamat). */
    function syncViewport() {
        var vv = window.visualViewport;
        var h = vv ? vv.height : window.innerHeight;
        document.documentElement.style.setProperty('--app-h', Math.round(h) + 'px');
        if (vv && vv.offsetTop) window.scrollTo(0, 0);
        if (state.stick) thread.scrollTop = thread.scrollHeight;
    }
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncViewport);
        window.visualViewport.addEventListener('scroll', syncViewport);
    }
    window.addEventListener('resize', syncViewport);
    syncViewport();

    /* ─────────────────── mulai ─────────────────── */

    if (store('local', 'cp_side_collapsed') === '1') {
        app.classList.add('is-collapsed');
        collapseBtn.setAttribute('aria-label', 'Tampilkan riwayat');
        collapseBtn.title = 'Tampilkan riwayat';
    }
    applyLayoutMode();

    store('local', 'cp_last_conv', null);
    var navEntry = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
    var isReload = !!(navEntry && navEntry.type === 'reload');
    if (cfg.initialConversation) {
        restoreDraft(cfg.initialConversation);
        openConversation(cfg.initialConversation, { force: true, keepInput: true });
    } else {
        // Dibuka dari menu: selalu kosong. Draft percakapan lain tidak disentuh.
        if (!isReload) store('session', draftKeyFor(null), null);
        syncUrl(null);
        renderEmpty();
        restoreDraft(null);
    }

    window.SimalasCopilot = { renderMarkdown: renderMarkdown };
})();
