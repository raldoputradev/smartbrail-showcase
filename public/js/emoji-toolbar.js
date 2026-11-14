(function () {
    'use strict';

    var CATEGORIES = [
        { id: 'umum', label: 'Umum', title: 'Emoji umum', emojis: ['😀', '🙂', '😊', '🙌', '👍', '👏', '🙏', '❤️', '✨', '🔥', '💪', '🤝'] },
        { id: 'info', label: 'Info', title: 'Info & jadwal', emojis: ['📢', '📣', 'ℹ️', '📅', '📆', '⏰', '📌', '📧', '✉️', '💬', '🗓️', '📋'] },
        { id: 'akademik', label: 'Akademik', title: 'Akademik & praktikum', emojis: ['🎓', '📚', '📖', '🔬', '💻', '🧪', '📝', '✅', '📊', '🏫', '🎯', '🧠'] },
        { id: 'peringatan', label: 'Perhatian', title: 'Peringatan & penting', emojis: ['⚠️', '❗', '‼️', '🔔', '🚨', '🛑', '❌', '🔒', '⛔', '💡', '🔑', '✳️'] },
    ];

    function insertAtCursor(el, text) {
        if (!el) return;
        var start = el.selectionStart;
        var end = el.selectionEnd;
        var value = el.value || '';
        el.value = value.slice(0, start) + text + value.slice(end);
        el.selectionStart = el.selectionEnd = start + text.length;
        el.focus();
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function restorePickerToRoot(pop) {
        var root = pop && pop._emojiRoot;
        var backdrop = pop && pop._emojiBackdrop;
        var trigger = pop && pop._emojiTrigger;
        if (!root || !backdrop || !trigger || !pop) return;
        root.appendChild(backdrop);
        root.appendChild(trigger);
        root.appendChild(pop);
    }

    function closeAll() {
        document.querySelectorAll('.comm-emoji-picker-pop.is-open').forEach(function (p) {
            var backdrop = p._emojiBackdrop;
            p.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
            p.style.position = '';
            p.style.left = '';
            p.style.top = '';
            p.style.width = '';
            p.style.maxWidth = '';
            p.style.zIndex = '';
            restorePickerToRoot(p);
        });
        document.querySelectorAll('.comm-emoji-picker-trigger[aria-expanded="true"]').forEach(function (t) {
            t.setAttribute('aria-expanded', 'false');
        });
    }

    function positionFloatingPop(trigger, pop) {
        var rect = trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var margin = 8;
        var w = Math.min(380, vw - margin * 2);
        var left = Math.max(margin, Math.min(rect.left, vw - w - margin));
        var top = rect.bottom + margin;
        var approxH = 300;
        if (top + approxH > vh - margin) {
            top = Math.max(margin, rect.top - approxH - margin);
        }
        pop.style.position = 'fixed';
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
        pop.style.width = w + 'px';
        pop.style.maxWidth = 'calc(100vw - 16px)';
        pop.style.zIndex = '100002';
    }

    function buildPicker(root, targetId) {
        var target = document.getElementById(targetId);
        if (!target) return;

        var backdrop = document.createElement('div');
        backdrop.className = 'comm-emoji-picker-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'comm-emoji-picker-trigger';
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.innerHTML =
            '<span class="comm-emoji-picker-trigger-ico" aria-hidden="true">😊</span>' +
            '<span class="comm-emoji-picker-trigger-label">Emoji &amp; ikon</span>';

        var pop = document.createElement('div');
        pop.className = 'comm-emoji-picker-pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Pilih emoji');

        pop._emojiRoot = root;
        pop._emojiBackdrop = backdrop;
        pop._emojiTrigger = trigger;

        var tabs = document.createElement('div');
        tabs.className = 'comm-emoji-picker-tabs';
        tabs.setAttribute('role', 'tablist');

        var panels = [];
        CATEGORIES.forEach(function (cat, idx) {
            var tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'comm-emoji-picker-tab' + (idx === 0 ? ' is-active' : '');
            tab.textContent = cat.label;
            if (cat.title) tab.setAttribute('title', cat.title);
            tab.setAttribute('data-tab', cat.id);
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            tabs.appendChild(tab);

            var panel = document.createElement('div');
            panel.className = 'comm-emoji-picker-panel';
            panel.setAttribute('data-panel', cat.id);
            panel.setAttribute('role', 'tabpanel');
            panel.style.display = idx === 0 ? 'block' : 'none';

            var grid = document.createElement('div');
            grid.className = 'comm-emoji-picker-grid';
            cat.emojis.forEach(function (emoji) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'comm-emoji-picker-cell';
                cell.textContent = emoji;
                cell.setAttribute('aria-label', 'Sisipkan ' + emoji);
                cell.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    insertAtCursor(target, emoji);
                });
                grid.appendChild(cell);
            });
            panel.appendChild(grid);
            pop.appendChild(panel);
            panels.push({ id: cat.id, tab: tab, panel: panel });
        });

        pop.insertBefore(tabs, pop.firstChild);

        root.appendChild(backdrop);
        root.appendChild(trigger);
        root.appendChild(pop);

        function setTab(id) {
            panels.forEach(function (p) {
                var on = p.id === id;
                p.tab.classList.toggle('is-active', on);
                p.tab.setAttribute('aria-selected', on ? 'true' : 'false');
                p.panel.style.display = on ? 'block' : 'none';
            });
        }

        panels.forEach(function (p) {
            p.tab.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                setTab(p.id);
            });
        });

        pop.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        function open() {
            closeAll();
            document.body.appendChild(backdrop);
            backdrop.classList.add('is-open');
            positionFloatingPop(trigger, pop);
            document.body.appendChild(pop);
            pop.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function close() {
            pop.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            pop.style.position = '';
            pop.style.left = '';
            pop.style.top = '';
            pop.style.width = '';
            pop.style.maxWidth = '';
            pop.style.zIndex = '';
            restorePickerToRoot(pop);
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (pop.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        backdrop.addEventListener('click', function (e) {
            e.preventDefault();
            close();
        });

        window.addEventListener('resize', function () {
            if (pop.classList.contains('is-open')) {
                positionFloatingPop(trigger, pop);
            }
        });
    }

    function initEmojiPickers() {
        document.querySelectorAll('.comm-emoji-picker-root[data-emoji-for]').forEach(function (root) {
            var id = root.getAttribute('data-emoji-for');
            if (!id || root.querySelector('.comm-emoji-picker-trigger')) return;
            buildPicker(root, id);
        });

        document.querySelectorAll('[data-emoji-target]:not([data-emoji-for])').forEach(function (bar) {
            var targetId = bar.getAttribute('data-emoji-target');
            if (!targetId || bar.classList.contains('comm-emoji-picker-root')) return;
            if (!document.getElementById(targetId)) return;
            bar.classList.add('comm-emoji-picker-root');
            bar.setAttribute('data-emoji-for', targetId);
            bar.removeAttribute('data-emoji-target');
            buildPicker(bar, targetId);
        });

        var hash = window.location.hash;
        if (hash && hash.indexOf('#saran-') === 0) {
            window.requestAnimationFrame(function () {
                var el = document.querySelector(hash);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('comm-thread-card--highlight');
                    window.setTimeout(function () {
                        el.classList.remove('comm-thread-card--highlight');
                    }, 2200);
                }
            });
        }
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeAll();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmojiPickers);
    } else {
        initEmojiPickers();
    }
})();
