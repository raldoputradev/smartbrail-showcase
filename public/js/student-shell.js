/* =====================================================================
   SIMALAS Portal Mahasiswa — Shell, Popover Notifikasi, CS Chat & Kalender
   ===================================================================== */
(function () {
    'use strict';

    var root = document.documentElement;
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var shell = document.getElementById('spShell');
    var sidebar = document.getElementById('spSidebar');
    var toggle = document.getElementById('spSidebarToggle');
    var closeBtn = document.getElementById('spSidebarClose');
    var backdrop = document.getElementById('spBackdrop');
    var mq = window.matchMedia('(max-width: 1024px)');
    var lastFocus = null;

    function isMobile() { return mq.matches; }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 1. Theme Toggle
    var themeToggle = document.getElementById('studentThemeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var cur = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            var next = cur === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('dashboard_theme', next); } catch (e) {}
            document.cookie = 'dashboard_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
        });
    }

    // 2. Sidebar Toggle (Rail on Desktop, Drawer on Mobile)
    function syncToggleState() {
        if (!toggle) return;
        if (isMobile()) {
            toggle.setAttribute('aria-expanded', shell.classList.contains('is-drawer-open') ? 'true' : 'false');
            toggle.setAttribute('aria-label', 'Buka menu navigasi');
        } else {
            var rail = root.classList.contains('sp-rail');
            toggle.setAttribute('aria-expanded', rail ? 'false' : 'true');
            toggle.setAttribute('aria-label', rail ? 'Perluas menu' : 'Perkecil menu');
        }
    }

    function focusables(el) {
        return Array.prototype.filter.call(
            el.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'),
            function (n) { return n.offsetParent !== null; }
        );
    }

    function openDrawer() {
        lastFocus = document.activeElement;
        shell.classList.add('is-drawer-open');
        if (backdrop) backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
        syncToggleState();
        var f = focusables(sidebar);
        if (f.length) f[0].focus();
    }

    function closeDrawer() {
        if (!shell.classList.contains('is-drawer-open')) return;
        shell.classList.remove('is-drawer-open');
        if (backdrop) backdrop.hidden = true;
        document.body.style.overflow = '';
        syncToggleState();
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    if (shell && sidebar && toggle) {
        toggle.addEventListener('click', function () {
            if (isMobile()) {
                shell.classList.contains('is-drawer-open') ? closeDrawer() : openDrawer();
                return;
            }
            var rail = root.classList.toggle('sp-rail');
            try { localStorage.setItem('simalas_student_sidebar_collapsed', rail ? '1' : '0'); } catch (e) {}
            syncToggleState();
        });
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (backdrop) backdrop.addEventListener('click', closeDrawer);

        sidebar.addEventListener('keydown', function (e) {
            if (!isMobile() || !shell.classList.contains('is-drawer-open')) return;
            if (e.key === 'Escape') { closeDrawer(); return; }
            if (e.key !== 'Tab') return;
            var f = focusables(sidebar);
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });

        mq.addEventListener ? mq.addEventListener('change', function () { closeDrawer(); syncToggleState(); }) : null;
        syncToggleState();
    }

    // 3. Grup Navigasi Buka-Tutup
    var GROUP_KEY = 'simalas_student_nav_closed';
    var closedNav = [];
    try { closedNav = JSON.parse(localStorage.getItem(GROUP_KEY) || '[]') || []; } catch (e) { closedNav = []; }
    document.querySelectorAll('[data-nav-group]').forEach(function (group) {
        var key = group.getAttribute('data-nav-group');
        var btn = group.querySelector('.sp-nav__group-btn');
        if (!btn) return;
        if (closedNav.indexOf(key) !== -1 && !group.hasAttribute('data-active')) {
            group.classList.add('is-collapsed');
            btn.setAttribute('aria-expanded', 'false');
        }
        btn.addEventListener('click', function () {
            var nowClosed = group.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', nowClosed ? 'false' : 'true');
            closedNav = closedNav.filter(function (k) { return k !== key; });
            if (nowClosed) closedNav.push(key);
            try { localStorage.setItem(GROUP_KEY, JSON.stringify(closedNav)); } catch (e) {}
        });
    });

    // 4. Avatar Dropdown Menu
    var userBtn = document.getElementById('studentUserMenuBtn');
    var userMenu = document.getElementById('studentUserDropdown');
    function closeUserMenu() {
        if (!userMenu || userMenu.hidden) return;
        userMenu.hidden = true;
        if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
    }
    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var willOpen = userMenu.hidden;
            closeNotifPopover();
            userMenu.hidden = !willOpen;
            userBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!userMenu.contains(e.target) && e.target !== userBtn) closeUserMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !userMenu.hidden) {
                closeUserMenu();
                userBtn.focus();
            }
        });
    }

    // 5. POPOVER NOTIFIKASI MINI (SEKSI 7)
    var notifBell = document.getElementById('studentNotifBellBtn');
    var notifPopover = document.getElementById('studentNotifPopover');
    var notifBadge = document.getElementById('studentNotifBadge');
    var notifPill = document.getElementById('studentNotifUnreadPill');
    var notifList = document.getElementById('studentNotifList');
    var notifReadAllBtn = document.getElementById('studentNotifReadAllBtn');
    var notifModal = document.getElementById('studentNotifDetailModal');
    var notifModalCat = document.getElementById('studentNotifModalCategory');
    var notifModalTitle = document.getElementById('studentNotifModalTitle');
    var notifModalMeta = document.getElementById('studentNotifModalMeta');
    var notifModalBody = document.getElementById('studentNotifModalBody');
    var notifModalAction = document.getElementById('studentNotifModalAction');
    var notifModalCloseBtn = document.getElementById('studentNotifModalCloseBtn');
    var notifModalDoneBtn = document.getElementById('studentNotifModalDoneBtn');

    function closeNotifPopover() {
        if (!notifPopover || notifPopover.hidden) return;
        notifPopover.hidden = true;
        if (notifBell) notifBell.setAttribute('aria-expanded', 'false');
    }

    function checkPopoverEmpty() {
        if (!notifList) return;
        var remaining = notifList.querySelectorAll('.sp-notif-item');
        if (remaining.length === 0) {
            notifList.innerHTML = '<div class="sp-notif-empty-state">' +
                '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sp-ok)"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                '<strong>Semua notifikasi sudah dibaca</strong>' +
                '<p>Lihat riwayat lengkap di halaman notifikasi.</p>' +
                '</div>';
        }
    }

    function updateUnreadBadges(count) {
        count = Math.max(0, parseInt(count, 10) || 0);
        if (notifBadge) {
            notifBadge.textContent = count > 99 ? '99+' : count;
            notifBadge.hidden = count === 0;
        }
        if (notifPill) {
            notifPill.textContent = count + ' belum dibaca';
            notifPill.hidden = count === 0;
        }
        if (notifReadAllBtn) {
            notifReadAllBtn.hidden = count === 0;
        }
        if (count === 0) {
            checkPopoverEmpty();
        }
    }

    if (notifBell && notifPopover) {
        notifBell.addEventListener('click', function (e) {
            e.stopPropagation();
            var willOpen = notifPopover.hidden;
            closeUserMenu();
            notifPopover.hidden = !willOpen;
            notifBell.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!notifPopover.contains(e.target) && e.target !== notifBell) closeNotifPopover();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !notifPopover.hidden) {
                closeNotifPopover();
                notifBell.focus();
            }
        });
    }

    // Modal detail notifikasi
    function openNotifModal(item, itemEl) {
        if (!notifModal) return;
        notifModalCat.textContent = item.category_label || 'Info';
        notifModalTitle.textContent = item.title || '';
        notifModalMeta.textContent = (item.meta || '') + (item.time_human ? ' · ' + item.time_human : '');
        notifModalBody.textContent = item.body || item.excerpt || '';

        if (item.action_url && notifModalAction) {
            notifModalAction.href = item.action_url;
            notifModalAction.textContent = item.action_label || 'Buka Laporan';
            notifModalAction.hidden = false;
        } else if (notifModalAction) {
            notifModalAction.hidden = true;
        }

        notifModal.hidden = false;
        closeNotifPopover();

        // Tandai hanya item ini yang sudah dibaca dan hilangkan dari popover agar bersih
        if (!item.is_read && item.read_url) {
            item.is_read = true;
            if (itemEl) {
                itemEl.classList.add('is-removing');
                setTimeout(function () {
                    itemEl.remove();
                    checkPopoverEmpty();
                }, 200);
            }
            fetch(item.read_url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && typeof data.unread_count !== 'undefined') updateUnreadBadges(data.unread_count);
                })
                .catch(function () {});
        }
    }

    function closeNotifModal() {
        if (notifModal) notifModal.hidden = true;
    }

    if (notifList) {
        notifList.addEventListener('click', function (e) {
            var itemBtn = e.target.closest('[data-notif-item]');
            if (!itemBtn) return;
            try {
                var item = JSON.parse(itemBtn.getAttribute('data-notif-item'));
                openNotifModal(item, itemBtn);
            } catch (err) {}
        });
    }
    if (notifModalCloseBtn) notifModalCloseBtn.addEventListener('click', closeNotifModal);
    if (notifModalDoneBtn) notifModalDoneBtn.addEventListener('click', closeNotifModal);
    if (notifModal) {
        notifModal.addEventListener('click', function (e) {
            if (e.target === notifModal) closeNotifModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !notifModal.hidden) closeNotifModal();
        });
    }

    if (notifReadAllBtn) {
        notifReadAllBtn.addEventListener('click', function () {
            var url = document.body.dataset.notifReadAllUrl;
            if (!url) return;
            notifReadAllBtn.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function () {
                    updateUnreadBadges(0);
                    if (notifList) {
                        var items = notifList.querySelectorAll('.sp-notif-item');
                        items.forEach(function (el) { el.classList.add('is-removing'); });
                        setTimeout(function () {
                            items.forEach(function (el) { el.remove(); });
                            checkPopoverEmpty();
                        }, 200);
                    }
                })
                .catch(function () {})
                .finally(function () {
                    notifReadAllBtn.disabled = false;
                });
        });
    }

    // 6. CHAT BANTUAN CS SIMALAS (SEKSI 8: SINGLE THREAD TANPA TAB)
    var helpDock = document.getElementById('spHelpDock');
    var helpFab = document.getElementById('spHelpFab');
    var helpWidget = document.getElementById('spHelpWidget');
    var helpCloseBtn = document.getElementById('spHelpCloseBtn');
    var helpChatLog = document.getElementById('spHelpChatLog');
    var helpForm = document.getElementById('spHelpForm');
    var helpInput = document.getElementById('spHelpInput');
    var helpPill = document.getElementById('spHelpPill');
    var helpPillText = document.getElementById('spHelpPillText');
    var helpPillClose = document.getElementById('spHelpPillClose');
    var helpSubmitUrl = document.body.dataset.helpSubmitUrl;
    var helpAiUrl = document.body.dataset.helpAiUrl;

    var BOT_ROBOT_SVG = '<svg class="sp-robot-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<line x1="12" y1="2" x2="12" y2="5"/>' +
        '<circle cx="12" cy="2" r="1.5" fill="currentColor"/>' +
        '<rect x="4" y="5" width="16" height="13" rx="4" fill="currentColor" fill-opacity="0.15"/>' +
        '<rect x="2" y="9" width="2" height="5" rx="1" fill="currentColor"/>' +
        '<rect x="20" y="9" width="2" height="5" rx="1" fill="currentColor"/>' +
        '<circle cx="8.5" cy="10" r="1.5" fill="currentColor"/>' +
        '<circle cx="15.5" cy="10" r="1.5" fill="currentColor"/>' +
        '<path d="M8.5 13.5C9.5 15 14.5 15 15.5 13.5"/>' +
        '<path d="M8 20C8 19 9.5 18 12 18C14.5 18 16 19 16 20"/>' +
        '</svg>';

    var userInitials = helpDock ? (helpDock.dataset.userInitials || 'M') : 'M';
    var userPhoto = helpDock ? (helpDock.dataset.userPhoto || '') : '';

    // Floating Greeting Pill: berganti sapaan ramah setiap 3.5 detik
    var GREETING_PHRASES = [
        'Ada pertanyaan?',
        'Butuh bantuan?',
        'Ada kendala absensi?',
        'Siap membantu Anda!',
        'Tanya asisten SIMALAS'
    ];
    var pillIndex = 0;
    var pillInterval = null;

    function startPillRotation() {
        if (!helpPillText || !helpPill || helpPill.classList.contains('is-hidden')) return;
        if (pillInterval) clearInterval(pillInterval);
        pillInterval = setInterval(function () {
            if (helpDock && helpDock.classList.contains('is-open')) return;
            if (helpPill.classList.contains('is-hidden')) return;

            helpPillText.classList.add('is-changing');
            setTimeout(function () {
                pillIndex = (pillIndex + 1) % GREETING_PHRASES.length;
                helpPillText.textContent = GREETING_PHRASES[pillIndex];
                helpPillText.classList.remove('is-changing');
            }, 200);
        }, 3500);
    }

    if (helpPill) {
        var pillDismissed = false;
        try { pillDismissed = sessionStorage.getItem('sp_help_pill_closed') === '1'; } catch (e) {}
        if (pillDismissed) {
            helpPill.classList.add('is-hidden');
        } else {
            startPillRotation();
        }

        helpPill.addEventListener('click', function (e) {
            if (e.target.closest('#spHelpPillClose')) return;
            toggleChatWidget(true);
        });
    }

    if (helpPillClose) {
        helpPillClose.addEventListener('click', function (e) {
            e.stopPropagation();
            if (helpPill) helpPill.classList.add('is-hidden');
            if (pillInterval) clearInterval(pillInterval);
            try { sessionStorage.setItem('sp_help_pill_closed', '1'); } catch (err) {}
        });
    }

    function toggleChatWidget(forceOpen) {
        if (!helpWidget) return;
        var willOpen = typeof forceOpen === 'boolean' ? forceOpen : helpWidget.hidden;
        helpWidget.hidden = !willOpen;
        if (helpDock) helpDock.classList.toggle('is-open', willOpen);
        if (helpFab) helpFab.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen && helpInput) {
            setTimeout(function () { helpInput.focus(); }, 120);
        }
    }

    if (helpFab) helpFab.addEventListener('click', function () { toggleChatWidget(); });
    if (helpCloseBtn) helpCloseBtn.addEventListener('click', function () { toggleChatWidget(false); });

    var chatHistory = [];

    function formatMarkdown(text) {
        if (!text) return '';
        var safe = String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Bold: **text**
        safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Inline code: `text`
        safe = safe.replace(/`([^`]+)`/g, '<code class="sp-chat-code">$1</code>');

        var lines = safe.split('\n');
        var html = '';
        var inUl = false;
        var inOl = false;

        lines.forEach(function (line) {
            var trimmed = line.trim();
            if (trimmed.startsWith('- ') || trimmed.startsWith('• ') || trimmed.startsWith('* ')) {
                if (inOl) { html += '</ol>'; inOl = false; }
                if (!inUl) { html += '<ul class="sp-chat-list">'; inUl = true; }
                html += '<li>' + trimmed.replace(/^[-•*]\s*/, '') + '</li>';
            } else if (/^\d+\.\s/.test(trimmed)) {
                if (inUl) { html += '</ul>'; inUl = false; }
                if (!inOl) { html += '<ol class="sp-chat-list">'; inOl = true; }
                html += '<li>' + trimmed.replace(/^\d+\.\s*/, '') + '</li>';
            } else {
                if (inUl) { html += '</ul>'; inUl = false; }
                if (inOl) { html += '</ol>'; inOl = false; }
                if (trimmed !== '') {
                    html += '<p>' + trimmed + '</p>';
                }
            }
        });

        if (inUl) html += '</ul>';
        if (inOl) html += '</ol>';

        return html;
    }

    function showTypingIndicator() {
        if (!helpChatLog) return null;
        var row = document.createElement('div');
        row.className = 'sp-chat-row sp-chat-row--bot sp-chat-row--typing';
        row.innerHTML = '<div class="sp-chat-avatar sp-chat-avatar--bot" title="Asisten SIMALAS">' +
            BOT_ROBOT_SVG +
            '</div>' +
            '<div class="sp-typing-bubble" aria-label="Asisten sedang mengetik">' +
            '<span class="sp-typing-dot"></span>' +
            '<span class="sp-typing-dot"></span>' +
            '<span class="sp-typing-dot"></span>' +
            '</div>';
        helpChatLog.appendChild(row);
        helpChatLog.scrollTop = helpChatLog.scrollHeight;
        return row;
    }

    function appendMessage(role, textHtml) {
        if (!helpChatLog) return null;
        var row = document.createElement('div');
        row.className = 'sp-chat-row sp-chat-row--' + (role === 'user' ? 'user' : 'bot');

        var avatar = document.createElement('div');
        avatar.className = 'sp-chat-avatar sp-chat-avatar--' + (role === 'user' ? 'user' : 'bot');
        if (role === 'user') {
            avatar.setAttribute('title', 'Anda');
            if (userPhoto) {
                avatar.innerHTML = '<img src="' + esc(userPhoto) + '" alt="" onerror="this.remove()"><span>' + esc(userInitials) + '</span>';
            } else {
                avatar.innerHTML = '<span>' + esc(userInitials) + '</span>';
            }
        } else {
            avatar.setAttribute('title', 'Asisten SIMALAS');
            avatar.innerHTML = BOT_ROBOT_SVG;
        }

        var bubble = document.createElement('div');
        bubble.className = 'sp-msg sp-msg--' + (role === 'user' ? 'user' : 'bot');
        bubble.innerHTML = typeof textHtml === 'string' ? textHtml : '';

        if (role === 'user') {
            row.appendChild(bubble);
            row.appendChild(avatar);
        } else {
            row.appendChild(avatar);
            row.appendChild(bubble);
        }

        helpChatLog.appendChild(row);
        helpChatLog.scrollTop = helpChatLog.scrollHeight;
        return row;
    }

    function askAiAssistant(text, onAfterReply) {
        if (!text) return;
        chatHistory.push({ role: 'user', text: text, content: text });

        var typingRow = showTypingIndicator();
        var startTime = Date.now();

        fetch(helpAiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message: text,
                history: chatHistory.slice(-8)
            }),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var elapsed = Date.now() - startTime;
                var remainingWait = Math.max(0, 650 - elapsed);

                setTimeout(function () {
                    if (typingRow) typingRow.remove();
                    var reply = res.reply || 'Terima kasih atas pertanyaan Anda.';
                    chatHistory.push({ role: 'assistant', text: reply, content: reply });

                    var formattedHtml = formatMarkdown(reply);
                    var botRow = appendMessage('bot', formattedHtml);

                    if (res.suggest_report && res.report_data) {
                        presentReportConfirmation(res.report_data);
                    } else if (typeof onAfterReply === 'function') {
                        onAfterReply(res, botRow);
                    }
                }, remainingWait);
            })
            .catch(function () {
                var elapsed = Date.now() - startTime;
                var remainingWait = Math.max(0, 650 - elapsed);

                setTimeout(function () {
                    if (typingRow) typingRow.remove();
                    appendMessage('bot', '<p>Maaf, terjadi gangguan koneksi sementara. Silakan coba tanyakan kembali atau gunakan tombol <strong>Kirim Laporan Resmi</strong>.</p>');
                }, remainingWait);
            });
    }

    function presentReportConfirmation(data) {
        var msgRow = appendMessage('bot', '<p>Saya telah merangkum kendala Anda untuk dikirimkan secara resmi ke Admin Laboran:</p>');
        var card = document.createElement('div');
        card.className = 'sp-chat-confirm';
        card.innerHTML = '<dl>' +
            '<dt>Kategori</dt><dd>' + esc(data.kategori || 'Kendala Absensi') + '</dd>' +
            (data.tanggal_absensi ? '<dt>Tanggal</dt><dd>' + esc(data.tanggal_absensi) + (data.referensi_absensi ? ' (' + esc(data.referensi_absensi) + ')' : '') + '</dd>' : '') +
            '<dt>Subjek</dt><dd>' + esc(data.subjek || 'Laporan Kendala Mahasiswa') + '</dd>' +
            '<dt>Uraian</dt><dd>' + esc(data.pesan) + '</dd>' +
            '</dl>' +
            '<div class="sp-chat-confirm__actions">' +
            '<button type="button" class="sp-btn sp-btn--primary sp-btn--xs" data-btn-confirm-report>Kirim Laporan Resmi</button>' +
            '<button type="button" class="sp-btn sp-btn--ghost sp-btn--xs" data-btn-cancel-report>Batal</button>' +
            '</div>';

        var bubble = msgRow ? (msgRow.querySelector('.sp-msg') || msgRow) : null;
        if (bubble) bubble.appendChild(card);
        helpChatLog.scrollTop = helpChatLog.scrollHeight;

        var confirmBtn = card.querySelector('[data-btn-confirm-report]');
        var cancelBtn = card.querySelector('[data-btn-cancel-report]');

        cancelBtn.addEventListener('click', function () {
            card.remove();
            appendMessage('bot', '<p>Pengiriman laporan dibatalkan. Silakan pilih opsi lain atau tanyakan hal seputar lab.</p>');
        });

        confirmBtn.addEventListener('click', function () {
            confirmBtn.disabled = true;
            cancelBtn.disabled = true;
            confirmBtn.textContent = 'Mengirim...';

            fetch(helpSubmitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    card.remove();
                    if (res.success) {
                        appendMessage('bot', '<p><strong>Laporan ' + esc(res.ticket_ref || '#Laporan') + ' berhasil dikirim ke Admin Laboran!</strong></p>' +
                            '<p>' + esc(res.message || 'Laporan Anda telah tercatat.') + '</p>' +
                            '<p><a class="sp-link" href="' + esc(res.history_url || '/mahasiswa/saran') + '">Buka di Riwayat Laporan &rarr;</a></p>');
                    } else {
                        appendMessage('bot', '<p style="color:var(--sp-danger)">Gagal mengirim laporan: ' + esc(res.message || 'Terjadi kendala.') + '</p>');
                    }
                })
                .catch(function () {
                    card.remove();
                    appendMessage('bot', '<p style="color:var(--sp-danger)">Terjadi gangguan koneksi saat mengirim laporan ke server.</p>');
                });
        });
    }

    function handleQuickAction(action) {
        if (!action) return;

        if (action === 'kendala_absensi') {
            appendMessage('user', '<p>Saya mengalami kendala absensi pada mesin tap laboratorium.</p>');
            askAiAssistant('Saya mengalami kendala absensi pada mesin tap laboratorium dan ingin solusi atau bantuan tiket laporan.');
        } else if (action === 'jadwal_lab') {
            appendMessage('user', '<p>Bagaimana aturan jadwal kuliah lab dan kebijakan absensi saat ini?</p>');
            askAiAssistant('Bagaimana aturan jadwal kuliah lab dan jam absensi saya?');
        } else if (action === 'akun_sidik_jari') {
            appendMessage('user', '<p>Bagaimana status sidik jari dan keamanan akun saya?</p>');
            askAiAssistant('Bagaimana status pendaftaran sidik jari dan keamanan akun saya?');
        } else if (action === 'lapor_laboran') {
            appendMessage('user', '<p>Saya ingin membuat laporan resmi ke Admin Laboran.</p>');
            presentReportConfirmation({
                kategori: 'umum',
                subjek: 'Laporan Kendala Laboratorium Mahasiswa',
                pesan: 'Mohon bantuan Admin Laboran terkait kendala di laboratorium.'
            });
        }
    }

    if (helpChatLog) {
        helpChatLog.addEventListener('click', function (e) {
            var btn = e.target.closest('.sp-quick-btn');
            if (!btn) return;
            var action = btn.dataset.quickAction;
            handleQuickAction(action);
        });
    }

    if (helpForm && helpInput) {
        helpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = helpInput.value.trim();
            if (!text) return;
            helpInput.value = '';
            appendMessage('user', '<p>' + esc(text) + '</p>');

            askAiAssistant(text);
        });
    }

    // Tombol "Laporkan kendala" dari Beranda / riwayat membuka CS chat dan langsung menyiapkan tiket
    document.querySelectorAll('[data-open-help]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleChatWidget(true);
            var date = btn.dataset.reportDate || '';
            var ref = btn.dataset.reportRef || 'Kendala Absensi';
            var preprompt = 'Saya ingin melaporkan kendala absensi pada tanggal ' + date + ' (' + ref + '). Tap sudah dilakukan namun status belum sesuai.';

            appendMessage('user', '<p>' + esc(preprompt) + '</p>');
            presentReportConfirmation({
                kategori: 'absensi',
                tanggal_absensi: date,
                referensi_absensi: ref,
                subjek: '[Kendala Absensi ' + date + '] ' + ref,
                pesan: preprompt
            });
        });
    });

    // 7. KALENDER INTERAKTIF (SEKSI 4.D)
    var STATE_LABEL = {
        wajib: 'Wajib absensi',
        wajib_agenda: 'Wajib absensi · ada agenda',
        tidak_wajib: 'Tidak wajib absensi',
        tidak_wajib_agenda: 'Tidak wajib · ada agenda',
        libur: 'Libur — tidak wajib absensi',
        perubahan_jam: 'Wajib dengan jam khusus'
    };
    var STATE_TONE = { wajib: 'info', wajib_agenda: 'info', perubahan_jam: 'warn', libur: 'danger' };

    function dayMarks(day, filter) {
        var tags = '';
        var hasJam = false, hasAgenda = false;
        (day.agenda || []).forEach(function (a) {
            if (a.dampak_absensi === 'ubah_jam') hasJam = true;
            else if (a.dampak_absensi !== 'batal_wajib') hasAgenda = true;
        });
        var showAbsensi = filter !== 'ruangan';
        var showRuangan = filter !== 'absensi';
        if (showAbsensi) {
            if (day.is_libur) tags += '<span class="sp-cal__tag sp-cal__tag--libur" aria-hidden="true">L</span>';
            if (hasJam) tags += '<span class="sp-cal__tag sp-cal__tag--jam" aria-hidden="true">J</span>';
            if (hasAgenda) tags += '<span class="sp-cal__tag sp-cal__tag--agenda" aria-hidden="true">A</span>';
        }
        if (showRuangan && (day.ruangan || []).length) {
            tags += '<span class="sp-cal__tag sp-cal__tag--room" aria-hidden="true">R</span>';
        }
        return tags ? '<span class="sp-cal__marks">' + tags + '</span>' : '';
    }

    function dayAria(day) {
        var parts = [day.tanggal_human, STATE_LABEL[day.status_hari] || ''];
        if (day.is_wajib) parts.push(day.jam_masuk + ' sampai ' + day.jam_pulang);
        (day.agenda || []).forEach(function (a) { parts.push(a.jenis_label + ': ' + a.keterangan); });
        if (day.absensi) parts.push('Absensi ' + day.absensi.kelengkapan_label);
        (day.ruangan || []).forEach(function (r) { parts.push('Ruangan ' + r.ruangan + ' ' + r.jam); });
        if (day.is_today) parts.push('hari ini');
        return parts.filter(Boolean).join(', ');
    }

    function renderDetail(cal, el, day) {
        var tone = STATE_TONE[day.status_hari] || 'neutral';
        var html = '<p class="sp-cd__date">' + esc(day.tanggal_human) + (day.is_today ? ' · Hari ini' : '') + '</p>';
        html += '<p class="sp-cd__state"><span class="sp-status sp-status--' + tone + '">' + esc(STATE_LABEL[day.status_hari] || day.label_status_hari) + '</span></p>';

        html += '<div class="sp-cd__section"><h4>Jadwal Efektif</h4><div class="sp-cd__sched">' +
            (day.is_wajib ? '<span class="sp-mono">' + esc(day.jam_masuk) + ' – ' + esc(day.jam_pulang) + '</span> WIB' : (day.is_libur ? 'Libur' : 'Tidak ada kewajiban absensi')) +
            '<span class="sp-cd__src">Sumber: ' + esc(day.sumber_label) + '</span></div></div>';

        if (day.agenda && day.agenda.length) {
            html += '<div class="sp-cd__section"><h4>Agenda (' + day.agenda.length + ')</h4><ul class="sp-cd__agenda">';
            day.agenda.forEach(function (a) {
                var range = a.tanggal_selesai && a.tanggal_selesai !== a.tanggal ? ' · ' + esc(a.tanggal) + ' s.d. ' + esc(a.tanggal_selesai) : '';
                var jam = a.jam_masuk_khusus ? ' (' + esc(a.jam_masuk_khusus) + '–' + esc(a.jam_pulang_khusus || '') + ')' : '';
                html += '<li><em>' + esc(a.jenis_label) + '</em><strong>' + esc(a.keterangan) + '</strong><span>' + esc(a.dampak_label) + jam + range + '</span></li>';
            });
            html += '</ul></div>';
        }

        if (day.ruangan && day.ruangan.length) {
            html += '<div class="sp-cd__section"><h4>Penggunaan Ruangan (' + day.ruangan.length + ')</h4><ul class="sp-cd__agenda">';
            day.ruangan.forEach(function (r) {
                html += '<li><em>' + esc(r.jam) + '</em><strong>' + esc(r.ruangan) + '</strong><span>' + esc(r.status_label) + '</span></li>';
            });
            html += '</ul></div>';
        }

        html += '<div class="sp-cd__section"><h4>Rekaman Absensi</h4>';
        if (day.absensi) {
            var a = day.absensi;
            html += '<p style="margin:0 0 8px"><span class="sp-status sp-status--' + esc(a.kelengkapan_tone) + '">' + esc(a.kelengkapan_label) + '</span></p>';
            html += '<div class="sp-cd__rec"><div><small>Masuk</small><b>' + esc(a.jam_masuk || '—') + '</b><small>' + esc(a.jam_masuk ? a.ketepatan_masuk_label : '') + '</small></div>' +
                '<div><small>Pulang</small><b>' + esc(a.jam_pulang || '—') + '</b><small>' + esc(a.jam_pulang ? a.ketepatan_pulang_label : '') + '</small></div></div>';
            if (a.jadwal_konsisten === false) {
                html += '<p class="sp-note" style="margin-top:8px">Catatan waktu dinilai dengan pengaturan jadwal saat tap tercatat.</p>';
            }
            html += '<div class="sp-cd__foot"><a class="sp-link" href="' + esc(el.dataset.historyUrl) + '?bulan=' + esc(day.tanggal.slice(0, 7)) + '">Buka Riwayat Absensi &rarr;</a></div>';
        } else {
            html += '<p class="sp-muted" style="margin:0">' + (day.tanggal > cal.today ? 'Tanggal belum berlangsung.' : 'Tidak ada rekaman pada tanggal ini.') + '</p>';
        }
        html += '</div>';

        el.querySelector('[data-cal-detail]').innerHTML = html;
    }

    function renderCalendar(el, cal, selected) {
        el._cal = cal;
        var grid = el.querySelector('[data-cal-grid]');
        el.querySelector('[data-cal-month]').textContent = cal.month_label;
        el.querySelector('[data-cal-prev]').setAttribute('href', '?bulan=' + cal.prev_month);
        el.querySelector('[data-cal-next]').setAttribute('href', '?bulan=' + cal.next_month);

        var html = '';
        for (var i = 0; i < cal.first_offset; i++) html += '<span class="sp-cal__blank" aria-hidden="true"></span>';
        cal.days.forEach(function (d) {
            var cls = ['sp-cal__day'];
            if (d.is_wajib) cls.push('is-wajib');
            if (d.is_libur) cls.push('is-libur');
            if (d.is_today) cls.push('is-today');
            if (d.dow === 0 || d.dow === 6) cls.push('is-weekend');
            if (d.tanggal === selected) cls.push('is-selected');
            var rec = (d.absensi && el._calFilter !== 'ruangan') ? '<span class="sp-cal__rec sp-cal__rec--' + esc(d.absensi.kelengkapan_tone) + '" aria-hidden="true"></span>' : '';
            html += '<button type="button" role="gridcell" class="' + cls.join(' ') + '" data-date="' + d.tanggal + '"' +
                ' aria-label="' + esc(dayAria(d)) + '" aria-pressed="' + (d.tanggal === selected ? 'true' : 'false') + '"' +
                ' tabindex="' + (d.tanggal === selected ? '0' : '-1') + '">' +
                '<span class="sp-cal__num">' + d.day_num + '</span>' + dayMarks(d, el._calFilter) + rec + '</button>';
        });
        grid.innerHTML = html;

        var sel = cal.days.filter(function (d) { return d.tanggal === selected; })[0];
        if (sel) renderDetail(cal, el, sel);
        else el.querySelector('[data-cal-detail]').innerHTML = '<p class="sp-muted">Pilih tanggal untuk melihat jadwal, agenda, dan rekaman absensi.</p>';
        if (!sel) {
            var first = grid.querySelector('.sp-cal__day');
            if (first) first.setAttribute('tabindex', '0');
        }
    }

    function selectDate(el, date, focus) {
        var cal = el._cal;
        el.querySelectorAll('.sp-cal__day').forEach(function (b) {
            var on = b.getAttribute('data-date') === date;
            b.classList.toggle('is-selected', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.setAttribute('tabindex', on ? '0' : '-1');
            if (on && focus) b.focus();
        });
        var day = cal.days.filter(function (d) { return d.tanggal === date; })[0];
        if (day) renderDetail(cal, el, day);
    }

    function loadMonth(el, month, selected) {
        var grid = el.querySelector('[data-cal-grid]');
        grid.classList.add('is-loading');
        fetch(el.dataset.monthUrl + '?bulan=' + encodeURIComponent(month), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (json) {
                var cal = json.calendar;
                var sel = selected || (cal.days.some(function (d) { return d.tanggal === cal.today; }) ? cal.today : null);
                renderCalendar(el, cal, sel);
            })
            .catch(function () {
                window.location.search = '?bulan=' + month;
            })
            .finally(function () { grid.classList.remove('is-loading'); });
    }

    document.querySelectorAll('[data-sp-calendar]').forEach(function (el) {
        var dataEl = el.querySelector('[data-sp-calendar-data]');
        if (!dataEl) return;
        var cal;
        try { cal = JSON.parse(dataEl.textContent); } catch (e) { return; }
        var initialSel = cal.days.some(function (d) { return d.tanggal === cal.today; }) ? cal.today : null;
        renderCalendar(el, cal, initialSel);

        el._calFilter = 'semua';
        el.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-cal-filter]');
            if (chip) {
                el._calFilter = chip.dataset.calFilter;
                el.querySelectorAll('[data-cal-filter]').forEach(function (c) { c.classList.toggle('is-on', c === chip); });
                var selBtn = el.querySelector('.sp-cal__day.is-selected');
                renderCalendar(el, el._cal, selBtn ? selBtn.getAttribute('data-date') : null);
                return;
            }
            var day = e.target.closest('.sp-cal__day');
            if (day) { selectDate(el, day.getAttribute('data-date'), false); return; }
            var nav = e.target.closest('[data-cal-prev],[data-cal-next],[data-cal-today]');
            if (!nav) return;
            e.preventDefault();
            var c = el._cal;
            if (nav.hasAttribute('data-cal-prev')) loadMonth(el, c.prev_month);
            else if (nav.hasAttribute('data-cal-next')) loadMonth(el, c.next_month);
            else loadMonth(el, c.today_month, c.today);
        });

        // Navigasi keyboard di grid
        el.querySelector('[data-cal-grid]').addEventListener('keydown', function (e) {
            var cur = e.target.closest('.sp-cal__day');
            if (!cur) return;
            var c = el._cal;
            var idx = c.days.findIndex(function (d) { return d.tanggal === cur.getAttribute('data-date'); });
            var step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
            if (e.key === 'Home') step = -idx;
            if (e.key === 'End') step = c.days.length - 1 - idx;
            if (step === undefined) return;
            e.preventDefault();
            var next = idx + step;
            if (next < 0) { loadMonth(el, c.prev_month); return; }
            if (next >= c.days.length) { loadMonth(el, c.next_month); return; }
            selectDate(el, c.days[next].tanggal, true);
        });
    });
})();

/* ── Pencarian menu topbar (hanya menu yang boleh diakses pengguna; data dari server) ── */
(function () {
    'use strict';
    var wrap = document.getElementById('studentMenuSearch');
    var input = document.getElementById('studentMenuSearchInput');
    var list = document.getElementById('studentMenuSearchList');
    var dataEl = document.getElementById('studentMenuSearchData');
    if (!wrap || !input || !list || !dataEl) return;

    var items = [];
    try { items = JSON.parse(dataEl.textContent || '[]') || []; } catch (e) { items = []; }
    var ALIASES = {
        'profil': ['profil', 'akun', 'data diri', 'foto', 'kontak', 'email', 'hp'],
        'sandi': ['sandi', 'password', 'keamanan', 'ganti password', 'reset'],
        'absensi': ['absensi', 'absen', 'kehadiran', 'tap', 'riwayat'],
        'jadwal': ['jadwal', 'kalender', 'agenda', 'hari', 'jam'],
        'laporan': ['laporan', 'bantuan', 'saran', 'tiket', 'kendala'],
        'beranda': ['beranda', 'home', 'dashboard', 'utama'],
        'panduan': ['panduan', 'bantuan', 'guide', 'cara'],
        'notifikasi': ['notifikasi', 'pengumuman', 'info'],
        'tim': ['tim', 'pbl', 'kelompok', 'proyek']
    };
    var activeIdx = -1;

    function haystack(item) {
        var extra = '';
        Object.keys(ALIASES).forEach(function (k) {
            if (item.label.toLowerCase().indexOf(k) !== -1) extra += ' ' + ALIASES[k].join(' ');
        });
        return (item.label + ' ' + extra).toLowerCase();
    }

    function render(query) {
        var q = (query || '').trim().toLowerCase();
        var matches = q === '' ? items.slice(0, 8) : items.filter(function (i) { return haystack(i).indexOf(q) !== -1; });
        list.innerHTML = '';
        activeIdx = -1;
        if (matches.length === 0) {
            var li = document.createElement('li');
            li.className = 'sp-menusearch__empty';
            li.textContent = 'Tidak ada menu yang cocok.';
            list.appendChild(li);
        } else {
            matches.slice(0, 8).forEach(function (item, idx) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.className = 'sp-menusearch__item';
                a.href = item.url;
                a.textContent = item.label;
                a.setAttribute('role', 'option');
                a.dataset.idx = idx;
                li.appendChild(a);
                list.appendChild(li);
            });
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        return matches;
    }

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        activeIdx = -1;
    }

    var currentMatches = [];
    input.addEventListener('input', function () { currentMatches = render(input.value); });
    input.addEventListener('focus', function () { currentMatches = render(input.value); });

    input.addEventListener('keydown', function (e) {
        var opts = list.querySelectorAll('.sp-menusearch__item');
        if (e.key === 'Escape') { close(); input.blur(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (opts.length === 0) return;
            activeIdx = e.key === 'ArrowDown'
                ? Math.min(activeIdx + 1, opts.length - 1)
                : Math.max(activeIdx - 1, 0);
            opts.forEach(function (o, i) { o.classList.toggle('is-active', i === activeIdx); });
            if (activeIdx >= 0) opts[activeIdx].scrollIntoView({ block: 'nearest' });
            return;
        }
        if (e.key === 'Enter') {
            var target = activeIdx >= 0 ? opts[activeIdx] : opts[0];
            if (target) { window.location.href = target.href; }
        }
    });

    list.addEventListener('click', function (e) {
        var a = e.target.closest('.sp-menusearch__item');
        if (a) { e.preventDefault(); window.location.href = a.href; }
    });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
})();


/* =====================================================================
   Pengajuan ruangan — satu sumber nilai (form kanan), slot ketersediaan
   24-jam, modal peserta, dan stepper 1-2-3.
   ===================================================================== */
(function () {
    'use strict';
    var app = document.getElementById('srApp');
    if (!app) return;

    var url = app.dataset.ketersediaanUrl || '';
    var today = app.dataset.today || '';

    var roomSelect = document.getElementById('srRuangan');
    var tglPick = document.getElementById('srTanggalPick');
    var tglForm = document.getElementById('srTanggal');
    var inMulai = document.getElementById('srMulai');
    var inSelesai = document.getElementById('srSelesai');
    var inTujuan = document.getElementById('srTujuan');
    var timePick = document.getElementById('srTimePick');
    var timeError = document.getElementById('srTimeError');
    var durBox = document.getElementById('srDurasi');
    var slotBox = document.getElementById('srSlots');
    var slotMsg = document.getElementById('srSlotsMsg');
    var roomBtns = Array.prototype.slice.call(app.querySelectorAll('.sr-room'));
    var form = document.getElementById('srForm');
    var btnNext = document.getElementById('srNext');

    if (!roomBtns.length || !slotBox) return;

    var state = { durasi: 60, bolehTanggal: true, slotValid: false };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function menit(hhmmVal) {
        var m = /^(\d{1,2}):(\d{2})/.exec(String(hhmmVal || '').trim());
        return m ? (+m[1]) * 60 + (+m[2]) : null;
    }
    function hhmm(m) {
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }
    function durasiLabel(m) {
        if (m % 60 === 0) return (m / 60) + ' Jam';
        return m >= 60 ? Math.floor(m / 60) + ',5 Jam' : m + ' mnt';
    }
    function tanggalDipilih() {
        return (tglPick && tglPick.value) || (tglForm && tglForm.value) || today;
    }

    /* Satu sumber: mulai + durasi -> selesai (24 jam, tidak menyeberang hari). */
    function syncSelesai() {
        var m = menit(inMulai && inMulai.value);
        if (m === null || !inSelesai) { return false; }
        var end = m + state.durasi;
        if (end >= 24 * 60) { inSelesai.value = ''; return false; }
        inSelesai.value = hhmm(end);
        return true;
    }

    function updateTimePick() {
        var ok = syncSelesai();
        if (timePick) {
            var m = inMulai && inMulai.value, s = inSelesai && inSelesai.value;
            if (ok && m && s) {
                timePick.textContent = m + ' \u2013 ' + s;
                timePick.classList.add('is-filled');
            } else {
                timePick.textContent = '\u2014';
                timePick.classList.remove('is-filled');
            }
        }
        return ok;
    }

    /* Validitas keseluruhan -> tombol Lanjutkan + satu pesan error dekat field. */
    function refreshValidity() {
        var ok = updateTimePick();
        var msg = '';
        if (!state.bolehTanggal) {
            msg = slotMsg ? slotMsg.textContent : 'Ruangan tidak tersedia pada tanggal ini.';
            ok = false;
        } else if (!state.slotValid) {
            if (!(inMulai && inMulai.value)) {
                msg = 'Pilih waktu dari jadwal ketersediaan di kiri.';
            } else if (!ok) {
                msg = 'Jam mulai + durasi melampaui hari yang sama.';
            } else {
                msg = 'Waktu terpilih tidak tersedia \u2014 pilih slot tersedia di kiri.';
            }
            ok = false;
        }
        if (timeError) {
            timeError.textContent = msg;
            timeError.hidden = !msg;
        }
        if (btnNext) btnNext.disabled = !ok;
        return ok;
    }

    /* Badge "Tersedia"/"Tutup pada tanggal pilihan" mengikuti tanggal yang dipilih. */
    function refreshRoomBadges() {
        var tgl = tanggalDipilih();
        var dayIso = null;
        if (tgl) {
            var d = new Date(tgl + 'T00:00:00');
            if (!isNaN(d)) dayIso = d.getDay() === 0 ? 7 : d.getDay();
        }
        roomBtns.forEach(function (b) {
            var badge = b.querySelector('[data-avail]');
            if (!badge) return;
            var open = true;
            if (dayIso !== null && b.dataset.hari) {
                open = b.dataset.hari.split(',').map(Number).indexOf(dayIso) !== -1;
            }
            badge.textContent = open ? 'Tersedia' : 'Tutup pada tanggal pilihan';
            badge.classList.toggle('sp-status--ok', open);
            badge.classList.toggle('sp-status--neutral', !open);
        });
    }

    /* --- Sinkron ruangan: kartu kiri <-> select kanan --- */
    function maxPesertaAktif() {
        var sel = roomBtns.find(function (b) { return b.dataset.roomId === String(roomSelect && roomSelect.value); });
        return sel ? (+sel.dataset.maxPeserta || 0) : 0;
    }
    function setRoom(id, dariKartu) {
        roomBtns.forEach(function (b) {
            var on = b.dataset.roomId === String(id);
            b.classList.toggle('is-selected', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        if (roomSelect && dariKartu) roomSelect.value = String(id);
        refreshCapLabel();
        loadSlots();
    }
    roomBtns.forEach(function (b) {
        b.addEventListener('click', function () { setRoom(b.dataset.roomId, true); });
    });
    if (roomSelect) roomSelect.addEventListener('change', function () { setRoom(roomSelect.value, false); });

    /* --- Sinkron tanggal --- */
    function setTanggal(val, dariPicker) {
        if (!val) return;
        if (tglPick && !dariPicker) tglPick.value = val;
        if (tglForm && dariPicker) tglForm.value = val;
        refreshRoomBadges();
        loadSlots();
    }
    if (tglPick) tglPick.addEventListener('change', function () { setTanggal(tglPick.value, true); });
    if (tglForm) tglForm.addEventListener('change', function () { setTanggal(tglForm.value, false); });

    /* --- Durasi --- */
    function renderDurasi(opsi) {
        if (!durBox) return;
        if (opsi.indexOf(state.durasi) === -1) state.durasi = opsi[0];
        durBox.innerHTML = '';
        opsi.forEach(function (d) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'sr-dur__chip' + (d === state.durasi ? ' is-on' : '');
            b.textContent = durasiLabel(d);
            b.setAttribute('role', 'radio');
            b.setAttribute('aria-checked', d === state.durasi ? 'true' : 'false');
            b.addEventListener('click', function () {
                state.durasi = d;
                renderDurasi(opsi);
                loadSlots();
            });
            durBox.appendChild(b);
        });
    }

    /* --- Slot ketersediaan --- */
    var reqSeq = 0;
    var lastSlots = [];
    function loadSlots() {
        if (!url || !roomSelect) return;
        var rid = roomSelect.value;
        var tgl = tanggalDipilih();
        if (!rid || !tgl) return;

        var seq = ++reqSeq;
        slotBox.classList.add('sr-slots--loading');

        fetch(url + '?ruangan_id=' + encodeURIComponent(rid) + '&tanggal=' + encodeURIComponent(tgl) + '&durasi=' + state.durasi, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (seq !== reqSeq) return;
            slotBox.classList.remove('sr-slots--loading');
            var k = res.ketersediaan || {};
            renderDurasi(k.durasi_opsi || [60]);
            if (k.max_advance_days != null && today) {
                var max = new Date(today + 'T00:00:00');
                max.setDate(max.getDate() + (+k.max_advance_days));
                                var maxStr = max.toISOString().slice(0, 10);
                [tglPick, tglForm].forEach(function (el) { if (el) { el.min = today; el.max = maxStr; } });
            }
            state.bolehTanggal = !!k.boleh;
            var selBtn = roomBtns.find(function (b) { return b.dataset.roomId === String(roomSelect.value); });
            var selBadge = selBtn && selBtn.querySelector('[data-avail]');
            if (!k.boleh) {
                lastSlots = [];
                slotBox.innerHTML = '';
                slotMsg.hidden = false;
                slotMsg.textContent = k.alasan || 'Ruangan tidak tersedia pada tanggal ini.';
                if (selBadge) {
                    selBadge.textContent = 'Tutup pada tanggal pilihan';
                    selBadge.classList.remove('sp-status--ok');
                    selBadge.classList.add('sp-status--neutral');
                }
                state.slotValid = false;
                refreshValidity();
                return;
            }
            slotMsg.hidden = true;
            lastSlots = k.slots || [];
            renderSlots(lastSlots);
            /* Badge ruangan terpilih mengikuti ketersediaan nyata pada tanggal ini. */
            if (selBadge) {
                var adaSlot = lastSlots.some(function (x) { return x.status === 'tersedia'; });
                selBadge.textContent = adaSlot ? 'Tersedia' : 'Tidak tersedia';
                selBadge.classList.toggle('sp-status--ok', adaSlot);
                selBadge.classList.toggle('sp-status--neutral', !adaSlot);
            }
            revalidatePick();
        }).catch(function () {
            if (seq !== reqSeq) return;
            slotBox.classList.remove('sr-slots--loading');
            slotMsg.hidden = false;
            slotMsg.textContent = 'Gagal memuat ketersediaan \u2014 coba lagi.';
            state.bolehTanggal = false;
            refreshValidity();
        });
    }

    function renderSlots(slots) {
        slotBox.innerHTML = '';
        if (!slots.length) {
            slotMsg.hidden = false;
            slotMsg.textContent = 'Tidak ada waktu yang bisa dipilih pada tanggal ini.';
            return;
        }
        slots.forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            var label = s.status === 'dipesan' ? 'Sudah dipesan' : (s.status === 'petugas' ? 'Jadwal petugas' : (s.status === 'lewat' ? 'Sudah lewat' : ''));
            b.className = 'sr-slot' + (s.status !== 'tersedia' ? ' is-busy' : '');
            b.innerHTML = esc(s.mulai) + '\u2013' + esc(s.selesai) + (label ? '<small>' + esc(label) + '</small>' : '');
            if (s.status !== 'tersedia') {
                b.disabled = true;
            } else {
                b.addEventListener('click', function () { pilihSlot(s, b); });
            }
            slotBox.appendChild(b);
        });
    }

    function pilihSlot(s, btn) {
        if (inMulai) inMulai.value = s.mulai;
        if (inSelesai) inSelesai.value = s.selesai;
        slotBox.querySelectorAll('.sr-slot').forEach(function (x) { x.classList.remove('is-pick'); });
        btn.classList.add('is-pick');
        state.slotValid = true;
        refreshValidity();
    }

    /* Setelah reload slot: pilihan lama yang tidak lagi tersedia dihapus. */
    function revalidatePick() {
        var m = inMulai && inMulai.value, s = inSelesai && inSelesai.value;
        var still = lastSlots.find(function (x) {
            return x.status === 'tersedia' && x.mulai === m && x.selesai === s;
        });
        if (still) {
            state.slotValid = true;
            slotBox.querySelectorAll('.sr-slot').forEach(function (x) {
                x.classList.toggle('is-pick', x.textContent.indexOf(m) === 0);
            });
        } else {
            state.slotValid = false;
            if (inSelesai) inSelesai.value = '';
            slotBox.querySelectorAll('.sr-slot').forEach(function (x) { x.classList.remove('is-pick'); });
        }
        refreshValidity();
    }

    /* Jam mulai manual -> normalisasi HH:MM 24 jam, selesai dihitung ulang. */
    if (inMulai) {
        inMulai.addEventListener('change', function () {
            var m = menit(inMulai.value);
            if (m !== null && m < 24 * 60) inMulai.value = hhmm(m);
            syncSelesai();
            revalidatePick();
        });
    }

    /* --- Modal peserta --- */
    var modal = document.getElementById('srPesertaModal');
    var memberList = document.getElementById('srMemberList');
    var memberSearch = document.getElementById('srPesertaSearch');
    var memberEmpty = document.getElementById('srMemberEmpty');
    var modalCount = document.getElementById('srPesertaModalCount');
    var modalCap = document.getElementById('srPesertaModalCap');
    var capLabel = document.getElementById('srPesertaCap');
    var pickAll = document.getElementById('srPickAll');
    var pickAllNote = document.getElementById('srPickAllNote');
    var pickAllWrap = document.getElementById('srPickAllWrap');
    var pickedBox = document.getElementById('srPicked');
    var inputsBox = document.getElementById('srPesertaInputs');
    var countEl = document.getElementById('srPesertaCount');
    var anggota = [];
    try { anggota = JSON.parse(document.getElementById('srAnggotaData').textContent) || []; } catch (e) {}
    var anggotaMap = {};
    anggota.forEach(function (a) { anggotaMap[String(a.id)] = a.nama; });

    function selectedIds() {
        if (!memberList) return [];
        return Array.prototype.map.call(memberList.querySelectorAll('input[type=checkbox]:checked'), function (c) { return String(c.value); });
    }

    function refreshCapLabel() {
        var cap = maxPesertaAktif();
        var txt = cap ? ' / maks ' + cap : '';
        if (capLabel) capLabel.textContent = txt;
        if (modalCap) modalCap.textContent = txt;
        if (pickAllNote) {
            pickAllNote.textContent = cap && anggota.length > cap
                ? 'Batas ruangan ' + cap + ' orang \u2014 pilih sebagian.'
                : '';
        }
        if (pickAll) pickAll.disabled = !!(cap && anggota.length > cap);
    }

    function renderPicked() {
        var ids = selectedIds();
        if (countEl) countEl.textContent = ids.length;
        if (modalCount) modalCount.textContent = ids.length;
        if (inputsBox) {
            inputsBox.innerHTML = '';
            ids.forEach(function (id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'peserta[]';
                inp.value = id;
                inputsBox.appendChild(inp);
            });
        }
        if (pickedBox) {
            pickedBox.innerHTML = '';
            ids.forEach(function (id) {
                var chip = document.createElement('span');
                chip.className = 'sr-chip-person is-picked';
                chip.innerHTML = '<span>' + esc(anggotaMap[id] || id) + '</span>' +
                    '<button type="button" aria-label="Hapus peserta">\u00d7</button>';
                chip.querySelector('button').addEventListener('click', function () {
                    var cb = memberList && memberList.querySelector('input[value="' + id + '"]');
                    if (cb) { cb.checked = false; }
                    renderPicked();
                });
                pickedBox.appendChild(chip);
            });
        }
        if (pickAll) pickAll.checked = ids.length === anggota.length && anggota.length > 0;
    }

    if (modal && memberList) {
        var openModal = function () {
            refreshCapLabel();
            renderPicked();
            modal.hidden = false;
            if (memberSearch) { memberSearch.value = ''; memberSearch.dispatchEvent(new Event('input')); }
        };
        var closeModal = function () { modal.hidden = true; };
        var btnAdd = document.getElementById('srAddPeserta');
        var btnClose = document.getElementById('srPesertaModalClose');
        var btnDone = document.getElementById('srPesertaModalDone');
        if (btnAdd) btnAdd.addEventListener('click', openModal);
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnDone) btnDone.addEventListener('click', function () { renderPicked(); closeModal(); });
        modal.addEventListener('click', function (e) { if (e.target === modal) { renderPicked(); closeModal(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { renderPicked(); closeModal(); } });

        memberList.addEventListener('change', function (e) {
            var cap = maxPesertaAktif();
            if (cap > 0 && e.target.checked && selectedIds().length > cap) {
                e.target.checked = false;
            }
            renderPicked();
        });
        if (memberSearch) {
            memberSearch.addEventListener('input', function () {
                var q = this.value.trim().toLowerCase();
                var visible = 0;
                memberList.querySelectorAll('.sr-member').forEach(function (li) {
                    var show = !q || (li.getAttribute('data-search') || '').indexOf(q) !== -1;
                    li.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                if (memberEmpty) memberEmpty.hidden = visible !== 0;
            });
        }
        if (pickAll) {
            pickAll.addEventListener('change', function () {
                var cap = maxPesertaAktif();
                var on = this.checked;
                var n = 0;
                memberList.querySelectorAll('input[type=checkbox]').forEach(function (c) {
                    c.checked = on && (cap === 0 || n++ < cap);
                });
                renderPicked();
            });
        }
        renderPicked();
    }

    /* --- Stepper --- */
    var steps = Array.prototype.slice.call(app.querySelectorAll('[data-step]'));
    var dots = Array.prototype.slice.call(app.querySelectorAll('[data-step-dot]'));
    var btnBack = document.getElementById('srBack');
    var review = document.getElementById('srReview');

    function gotoStep(n) {
        steps.forEach(function (el) { el.hidden = el.dataset.step !== String(n); });
        dots.forEach(function (d) {
            var k = +d.dataset.stepDot;
            d.classList.toggle('is-active', k === n);
            d.classList.toggle('is-done', k < n);
        });
    }

    function buildReview() {
        if (!review) return;
        var nama = roomSelect && roomSelect.options[roomSelect.selectedIndex] ? roomSelect.options[roomSelect.selectedIndex].text : '\u2014';
        var peserta = selectedIds().map(function (id) { return anggotaMap[id] || id; });
        var rows = [
            ['Ruangan', nama],
            ['Tanggal', tglForm ? tglForm.value : '\u2014'],
            ['Waktu', (inMulai && inMulai.value) + ' \u2013 ' + (inSelesai && inSelesai.value)],
            ['Tujuan', inTujuan ? inTujuan.value : '\u2014'],
            ['Peserta', peserta.length ? peserta.join(', ') : '\u2014']
        ];
        review.innerHTML = rows.map(function (r) {
            return '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
        }).join('');
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (!refreshValidity()) return;
            if (form && !form.reportValidity()) return;
            buildReview();
            gotoStep(2);
        });
    }
    if (btnBack) btnBack.addEventListener('click', function () { gotoStep(1); });
    if (form) {
        form.addEventListener('submit', function (e) {
            var btn = document.getElementById('srSubmit');
            if (btn && btn.disabled) { e.preventDefault(); return; }
            if (btn) { btn.disabled = true; btn.textContent = 'Mengirim\u2026'; }
        });
    }

    /* Inisialisasi */
    refreshRoomBadges();
    if (roomSelect && roomSelect.value) {
        setRoom(roomSelect.value, false);
    } else if (roomBtns.length) {
        setRoom(roomBtns[0].dataset.roomId, true);
    }
    refreshValidity();
    if (form) gotoStep(1);
})();
