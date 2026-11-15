(function () {
    'use strict';

    const body = document.body;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const shell = document.getElementById('studentShell');

    function setTheme(next) {
        document.documentElement.setAttribute('data-theme', next);
        try {
            localStorage.setItem('dashboard_theme', next);
        } catch (e) {}
        document.cookie = 'dashboard_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
    }

    // 1. Theme Toggle
    const themeToggle = document.getElementById('studentThemeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // 2. Sidebar Collapse (Desktop) & Drawer (Mobile)
    const sidebarToggle = document.getElementById('studentSidebarToggle');
    const sidebarClose = document.getElementById('studentSidebarClose');
    const sidebarBackdrop = document.getElementById('studentSidebarBackdrop');

    try {
        if (shell && window.innerWidth > 960 && localStorage.getItem('simalas_student_sidebar_collapsed') === '1') {
            shell.classList.add('is-sidebar-collapsed');
        }
    } catch (e) {}

    if (sidebarToggle && shell) {
        sidebarToggle.addEventListener('click', function () {
            if (window.innerWidth <= 960) {
                shell.classList.toggle('is-mobile-drawer-open');
            } else {
                const collapsed = shell.classList.toggle('is-sidebar-collapsed');
                try {
                    localStorage.setItem('simalas_student_sidebar_collapsed', collapsed ? '1' : '0');
                } catch (e) {}
            }
        });
    }

    function closeMobileDrawer() {
        if (shell) shell.classList.remove('is-mobile-drawer-open');
    }
    if (sidebarClose) sidebarClose.addEventListener('click', closeMobileDrawer);
    if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeMobileDrawer);

    // 3. Topbar User Dropdown
    const userMenuBtn = document.getElementById('studentUserMenuBtn');
    const userDropdown = document.getElementById('studentUserDropdown');

    function closeUserDropdown() {
        if (!userDropdown || !userMenuBtn) return;
        userDropdown.hidden = true;
        userMenuBtn.setAttribute('aria-expanded', 'false');
    }

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const willOpen = userDropdown.hidden;
            userDropdown.hidden = !willOpen;
            userMenuBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!userDropdown.hidden && !userDropdown.contains(e.target) && e.target !== userMenuBtn) {
                closeUserDropdown();
            }
        });
    }

    // 4. Notification Slide-over Drawer & Accessible Detail Modal
    const notifBellBtn = document.getElementById('studentNotifBellBtn');
    const notifBadge = document.getElementById('studentNotifBadge');
    const notifUnreadTabCount = document.getElementById('studentNotifUnreadTabCount');
    const notifDrawer = document.getElementById('studentNotifDrawer');
    const notifBackdrop = document.getElementById('studentNotifBackdrop');
    const notifCloseBtn = document.getElementById('studentNotifCloseBtn');
    const notifList = document.getElementById('studentNotifList');
    const notifReadAllBtn = document.getElementById('studentNotifReadAllBtn');
    const notifLoadMoreBtn = document.getElementById('studentNotifLoadMoreBtn');
    const notifTabs = document.querySelectorAll('[data-notif-filter]');

    const notifModal = document.getElementById('studentNotifDetailModal');
    const notifModalCategory = document.getElementById('studentNotifModalCategory');
    const notifModalTitle = document.getElementById('studentNotifModalTitle');
    const notifModalMeta = document.getElementById('studentNotifModalMeta');
    const notifModalBody = document.getElementById('studentNotifModalBody');
    const notifModalAction = document.getElementById('studentNotifModalAction');
    const notifModalCloseBtn = document.getElementById('studentNotifModalCloseBtn');
    const notifModalDoneBtn = document.getElementById('studentNotifModalDoneBtn');

    let currentNotifFilter = 'all';
    let currentNotifPage = 1;
    let lastFocusedNotifTrigger = null;

    function updateUnreadBadgeCount(count) {
        const n = Math.max(0, Number(count) || 0);
        if (notifBadge) {
            notifBadge.textContent = n > 99 ? '99+' : String(n);
            notifBadge.hidden = n === 0;
        }
        if (notifUnreadTabCount) {
            notifUnreadTabCount.textContent = String(n);
        }
        if (notifReadAllBtn) {
            notifReadAllBtn.hidden = n === 0;
        }
    }

    function openNotifDrawer() {
        if (!notifDrawer || !notifBackdrop) return;
        closeUserDropdown();
        notifDrawer.hidden = false;
        notifBackdrop.hidden = false;
        if (notifBellBtn) notifBellBtn.setAttribute('aria-expanded', 'true');
    }

    function closeNotifDrawer() {
        if (!notifDrawer || !notifBackdrop) return;
        notifDrawer.hidden = true;
        notifBackdrop.hidden = true;
        if (notifBellBtn) notifBellBtn.setAttribute('aria-expanded', 'false');
    }

    if (notifBellBtn) {
        notifBellBtn.addEventListener('click', function () {
            if (notifDrawer && notifDrawer.hidden) openNotifDrawer();
            else closeNotifDrawer();
        });
    }
    if (notifCloseBtn) notifCloseBtn.addEventListener('click', closeNotifDrawer);
    if (notifBackdrop) notifBackdrop.addEventListener('click', closeNotifDrawer);

    function createNotifCardElement(item) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'st-notif-card' + (item.is_read ? '' : ' is-unread');
        btn.setAttribute('data-notif-item', JSON.stringify(item));

        const top = document.createElement('div');
        top.className = 'st-notif-card__top';

        const badge = document.createElement('span');
        const badgeTone = item.kind === 'suggestion_reply' ? 'st-badge--info' : (item.priority === 'penting' ? 'st-badge--warn' : 'st-badge--neutral');
        badge.className = 'st-badge ' + badgeTone;
        badge.textContent = item.category_label || 'Info';

        const time = document.createElement('span');
        time.className = 'st-notif-card__time';
        time.textContent = item.time_human || '';

        top.appendChild(badge);
        top.appendChild(time);

        const title = document.createElement('strong');
        title.className = 'st-notif-card__title';
        title.textContent = item.title || '';

        const excerpt = document.createElement('p');
        excerpt.className = 'st-notif-card__excerpt';
        excerpt.textContent = item.excerpt || '';

        btn.appendChild(top);
        btn.appendChild(title);
        btn.appendChild(excerpt);
        return btn;
    }

    async function fetchNotifFeed(filter, page, append) {
        const feedUrl = body?.dataset?.notifFeedUrl;
        if (!feedUrl || !notifList) return;
        try {
            const url = new URL(feedUrl, window.location.origin);
            url.searchParams.set('filter', filter);
            url.searchParams.set('page', String(page));
            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            updateUnreadBadgeCount(data.unread_count);
            if (!append) {
                notifList.innerHTML = '';
            }
            const items = Array.isArray(data.items) ? data.items : [];
            if (items.length === 0 && !append) {
                const empty = document.createElement('div');
                empty.className = 'st-empty-compact';
                empty.textContent = filter === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi saat ini.';
                notifList.appendChild(empty);
            } else {
                items.forEach(function (item) {
                    notifList.appendChild(createNotifCardElement(item));
                });
            }
            if (notifLoadMoreBtn) {
                notifLoadMoreBtn.hidden = !data.has_more;
            }
        } catch (e) {}
    }

    notifTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const filter = tab.getAttribute('data-notif-filter') || 'all';
            currentNotifFilter = filter;
            currentNotifPage = 1;
            notifTabs.forEach(function (t) {
                const active = t === tab;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            fetchNotifFeed(currentNotifFilter, 1, false);
        });
    });

    if (notifLoadMoreBtn) {
        notifLoadMoreBtn.addEventListener('click', function () {
            currentNotifPage += 1;
            fetchNotifFeed(currentNotifFilter, currentNotifPage, true);
        });
    }

    if (notifReadAllBtn) {
        notifReadAllBtn.addEventListener('click', async function () {
            const url = body?.dataset?.notifReadAllUrl;
            if (!url) return;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                if (res.ok) {
                    updateUnreadBadgeCount(0);
                    document.querySelectorAll('.st-notif-card.is-unread').forEach(function (el) {
                        el.classList.remove('is-unread');
                    });
                }
            } catch (e) {}
        });
    }

    function openNotifDetailModal(item, triggerEl) {
        if (!notifModal) return;
        lastFocusedNotifTrigger = triggerEl || document.activeElement;
        if (notifModalCategory) notifModalCategory.textContent = item.category_label || 'Notifikasi';
        if (notifModalTitle) notifModalTitle.textContent = item.title || 'Detail Notifikasi';
        if (notifModalMeta) notifModalMeta.textContent = (item.meta ? item.meta + ' · ' : '') + (item.time_human || '');
        if (notifModalBody) notifModalBody.textContent = item.body || item.excerpt || '';

        if (notifModalAction) {
            if (item.action_url) {
                notifModalAction.href = item.action_url;
                notifModalAction.textContent = item.action_label || 'Buka Tautan';
                notifModalAction.hidden = false;
            } else {
                notifModalAction.hidden = true;
            }
        }

        notifModal.hidden = false;
        if (notifModalCloseBtn) notifModalCloseBtn.focus();

        // Mark individual item read if unread
        if (!item.is_read && item.read_url) {
            item.is_read = true;
            if (triggerEl) {
                triggerEl.classList.remove('is-unread');
                triggerEl.setAttribute('data-notif-item', JSON.stringify(item));
            }
            fetch(item.read_url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (data && typeof data.unread_count !== 'undefined') {
                        updateUnreadBadgeCount(data.unread_count);
                    }
                })
                .catch(function () {});
        }
    }

    function closeNotifDetailModal() {
        if (!notifModal) return;
        notifModal.hidden = true;
        if (lastFocusedNotifTrigger && typeof lastFocusedNotifTrigger.focus === 'function') {
            lastFocusedNotifTrigger.focus();
        }
    }

    if (notifList) {
        notifList.addEventListener('click', function (e) {
            const card = e.target.closest('[data-notif-item]');
            if (!card) return;
            try {
                const item = JSON.parse(card.getAttribute('data-notif-item') || '{}');
                openNotifDetailModal(item, card);
            } catch (err) {}
        });
    }
    if (notifModalCloseBtn) notifModalCloseBtn.addEventListener('click', closeNotifDetailModal);
    if (notifModalDoneBtn) notifModalDoneBtn.addEventListener('click', closeNotifDetailModal);
    if (notifModal) {
        notifModal.addEventListener('click', function (e) {
            if (e.target === notifModal) closeNotifDetailModal();
        });
    }

    // 5. Interactive Compact Calendar on Dashboard
    const calDetailDate = document.getElementById('stCalDetailDate');
    const calDetailSchedule = document.getElementById('stCalDetailSchedule');
    const calDetailStatus = document.getElementById('stCalDetailStatus');
    const calDetailAtt = document.getElementById('stCalDetailAtt');

    document.querySelectorAll('[data-cal-day]').forEach(function (cell) {
        cell.addEventListener('click', function () {
            document.querySelectorAll('[data-cal-day].is-selected').forEach(function (c) {
                c.classList.remove('is-selected');
            });
            cell.classList.add('is-selected');
            try {
                const d = JSON.parse(cell.getAttribute('data-cal-day') || '{}');
                if (calDetailDate) calDetailDate.textContent = d.tanggal_human || d.tanggal || '';
                if (calDetailSchedule) calDetailSchedule.textContent = d.label_jadwal || '';
                if (calDetailStatus) calDetailStatus.textContent = d.label_status_hari || '';
                if (calDetailAtt) {
                    if (d.absensi_status) {
                        calDetailAtt.innerHTML = '';
                        const prefix = document.createTextNode('Catatan Anda: ');
                        const strong = document.createElement('strong');
                        strong.textContent = d.absensi_status;
                        const suffix = document.createTextNode(
                            ' (Masuk ' + (d.absensi_masuk || '—') + ', Pulang ' + (d.absensi_pulang || '—') +
                            (d.absensi_ketepatan && d.absensi_ketepatan !== '—' ? ' · ' + d.absensi_ketepatan : '') + ')'
                        );
                        calDetailAtt.appendChild(prefix);
                        calDetailAtt.appendChild(strong);
                        calDetailAtt.appendChild(suffix);
                    } else {
                        calDetailAtt.textContent = d.is_wajib
                            ? 'Belum ada catatan tap absensi pada tanggal wajib ini.'
                            : 'Tidak ada kewajiban tap absensi pada tanggal ini.';
                    }
                }
            } catch (e) {}
        });
    });

    // 6. Attendance Detail Modal & Pre-fill Report Action
    const attModal = document.getElementById('studentAttDetailModal');
    const attModalBadge = document.getElementById('stAttModalBadge');
    const attModalTitle = document.getElementById('stAttModalTitle');
    const attModalSubtitle = document.getElementById('stAttModalSubtitle');
    const attModalJadwal = document.getElementById('stAttModalJadwal');
    const attModalKelengkapan = document.getElementById('stAttModalKelengkapan');
    const attModalMasuk = document.getElementById('stAttModalMasuk');
    const attModalPulang = document.getElementById('stAttModalPulang');
    const attModalSlot = document.getElementById('stAttModalSlot');
    const attModalReportBtn = document.getElementById('stAttModalReportBtn');
    const attModalCloseBtn = document.getElementById('stAttModalCloseBtn');
    const attModalDoneBtn = document.getElementById('stAttModalDoneBtn');
    let currentAttPayload = null;

    function openAttDetailModal(payload) {
        if (!attModal) return;
        currentAttPayload = payload;
        if (attModalBadge) {
            attModalBadge.className = 'st-badge st-badge--' + (payload.kelengkapan_tone || 'info');
            attModalBadge.textContent = payload.kelengkapan_label || 'Rincian';
        }
        if (attModalTitle) attModalTitle.textContent = payload.tanggal_human || payload.tanggal || 'Detail Kehadiran';
        if (attModalSubtitle) attModalSubtitle.textContent = 'Sesi ' + (payload.sesi || 'PAGI') + ' · ' + (payload.kelengkapan_desc || '');
        if (attModalJadwal) attModalJadwal.textContent = payload.jadwal || '—';
        if (attModalKelengkapan) attModalKelengkapan.textContent = payload.kelengkapan_label || '—';
        if (attModalMasuk) attModalMasuk.textContent = (payload.jam_masuk || '—') + ' (' + (payload.ketepatan_masuk_label || '—') + ')';
        if (attModalPulang) attModalPulang.textContent = (payload.jam_pulang || '—') + ' (' + (payload.ketepatan_pulang_label || '—') + ')';
        if (attModalSlot) attModalSlot.textContent = payload.slot_lab || '—';
        attModal.hidden = false;
    }

    function closeAttDetailModal() {
        if (attModal) attModal.hidden = true;
    }

    document.querySelectorAll('[data-att-detail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                const payload = JSON.parse(btn.getAttribute('data-att-detail') || '{}');
                openAttDetailModal(payload);
            } catch (e) {}
        });
    });

    if (attModalCloseBtn) attModalCloseBtn.addEventListener('click', closeAttDetailModal);
    if (attModalDoneBtn) attModalDoneBtn.addEventListener('click', closeAttDetailModal);
    if (attModal) {
        attModal.addEventListener('click', function (e) {
            if (e.target === attModal) closeAttDetailModal();
        });
    }

    // 7. Floating Help Launcher, Rotating Greeting Pill, AI Chat & Report Confirmation Flow
    const helpPill = document.getElementById('studentHelpGreetingPill');
    const helpPillText = document.getElementById('studentHelpGreetingText');
    const helpPillClose = document.getElementById('studentHelpPillClose');
    const helpLauncher = document.getElementById('studentHelpLauncher');
    const helpWidget = document.getElementById('studentHelpWidget');
    const helpCloseBtn = document.getElementById('studentHelpCloseBtn');
    const helpTabs = document.querySelectorAll('[data-help-tab]');
    const helpPanes = document.querySelectorAll('[data-help-pane]');

    const greetings = [
        'Butuh bantuan?',
        'Ada kendala absensi?',
        'Tanya tentang SIMALAS',
        'Punya saran?'
    ];
    let greetingIndex = 0;
    let greetingTimer = null;

    function stopGreetingPill(hidePill) {
        if (greetingTimer) {
            clearInterval(greetingTimer);
            greetingTimer = null;
        }
        if (hidePill && helpPill) {
            helpPill.hidden = true;
            try {
                sessionStorage.setItem('simalas_help_pill_dismissed', '1');
            } catch (e) {}
        }
    }

    if (helpPill && helpPillText) {
        let dismissed = false;
        try {
            dismissed = sessionStorage.getItem('simalas_help_pill_dismissed') === '1';
        } catch (e) {}

        if (dismissed) {
            helpPill.hidden = true;
        } else {
            greetingTimer = setInterval(function () {
                greetingIndex = (greetingIndex + 1) % greetings.length;
                helpPillText.textContent = greetings[greetingIndex];
            }, 5000);
        }
    }

    if (helpPillClose) {
        helpPillClose.addEventListener('click', function (e) {
            e.stopPropagation();
            stopGreetingPill(true);
        });
    }

    function switchHelpTab(tabName) {
        helpTabs.forEach(function (t) {
            const active = t.getAttribute('data-help-tab') === tabName;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        helpPanes.forEach(function (p) {
            p.hidden = p.getAttribute('data-help-pane') !== tabName;
        });
    }

    function openHelpWidget(tabName) {
        if (!helpWidget) return;
        stopGreetingPill(true);
        helpWidget.hidden = false;
        if (helpLauncher) helpLauncher.setAttribute('aria-expanded', 'true');
        if (tabName) switchHelpTab(tabName);
    }

    function closeHelpWidget() {
        if (!helpWidget) return;
        helpWidget.hidden = true;
        if (helpLauncher) helpLauncher.setAttribute('aria-expanded', 'false');
    }

    window.openStudentHelpTab = function (tabName) {
        openHelpWidget(tabName || 'report');
    };

    if (helpLauncher) {
        helpLauncher.addEventListener('click', function () {
            if (helpWidget && helpWidget.hidden) openHelpWidget();
            else closeHelpWidget();
        });
    }
    if (helpCloseBtn) helpCloseBtn.addEventListener('click', closeHelpWidget);

    helpTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.getAttribute('data-help-tab');
            if (target) switchHelpTab(target);
        });
    });

    // AI Chat inside Help Widget
    const aiForm = document.getElementById('studentHelpAiForm');
    const aiInput = document.getElementById('studentHelpAiInput');
    const aiSendBtn = document.getElementById('studentHelpAiSendBtn');
    const aiChatLog = document.getElementById('studentHelpChatLog');
    const aiHistory = [];

    function appendChatBubble(role, text) {
        if (!aiChatLog) return;
        const div = document.createElement('div');
        div.className = 'st-chat-bubble ' + (role === 'user' ? 'st-chat-bubble--user' : 'st-chat-bubble--ai');
        div.textContent = text;
        aiChatLog.appendChild(div);
        aiChatLog.scrollTop = aiChatLog.scrollHeight;
    }

    async function sendStudentAiMessage(text) {
        const aiUrl = body?.dataset?.helpAiUrl;
        if (!aiUrl || !text) return;
        appendChatBubble('user', text);
        if (aiSendBtn) aiSendBtn.disabled = true;

        try {
            const res = await fetch(aiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    message: text,
                    history: aiHistory.slice(-8)
                })
            });
            const data = await res.json();
            const reply = data.reply || 'Maaf, asisten belum dapat merespons saat ini.';
            appendChatBubble('assistant', reply);
            aiHistory.push({ role: 'user', text: text });
            aiHistory.push({ role: 'assistant', text: reply });
        } catch (e) {
            appendChatBubble('assistant', 'Gagal menghubungi layanan bantuan. Anda tetap dapat mengirim laporan resmi pada tab Kirim Laporan.');
        } finally {
            if (aiSendBtn) aiSendBtn.disabled = false;
        }
    }

    if (aiForm && aiInput) {
        aiForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const msg = aiInput.value.trim();
            if (!msg) return;
            aiInput.value = '';
            sendStudentAiMessage(msg);
        });
    }

    document.querySelectorAll('[data-ai-prompt]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const prompt = chip.getAttribute('data-ai-prompt');
            if (prompt) sendStudentAiMessage(prompt);
        });
    });

    // Report Confirmation Flow inside Help Widget
    const reportForm = document.getElementById('studentHelpReportForm');
    const stepEdit = document.getElementById('studentHelpReportStepEdit');
    const stepConfirm = document.getElementById('studentHelpReportStepConfirm');
    const stepDone = document.getElementById('studentHelpReportStepDone');
    const previewBtn = document.getElementById('studentHelpPreviewBtn');
    const backEditBtn = document.getElementById('studentHelpBackEditBtn');
    const submitConfirmBtn = document.getElementById('studentHelpSubmitConfirmBtn');
    const resetReportBtn = document.getElementById('studentHelpResetReportBtn');
    const reportError = document.getElementById('studentHelpReportError');

    const inputKategori = document.getElementById('stReportKategori');
    const inputTanggal = document.getElementById('stReportTanggal');
    const inputRef = document.getElementById('stReportRef');
    const inputSubjek = document.getElementById('stReportSubjek');
    const inputPesan = document.getElementById('stReportPesan');

    const confirmKategori = document.getElementById('stConfirmKategori');
    const confirmRefWrap = document.getElementById('stConfirmRefWrap');
    const confirmRef = document.getElementById('stConfirmRef');
    const confirmSubjek = document.getElementById('stConfirmSubjek');
    const confirmPesan = document.getElementById('stConfirmPesan');
    const doneTitle = document.getElementById('stDoneTitle');
    const doneMeta = document.getElementById('stDoneMeta');

    function showReportError(msg) {
        if (!reportError) return;
        if (!msg) {
            reportError.hidden = true;
            reportError.textContent = '';
        } else {
            reportError.hidden = false;
            reportError.textContent = msg;
        }
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            const pesanVal = (inputPesan?.value || '').trim();
            if (pesanVal.length < 10) {
                showReportError('Uraian laporan minimal 10 karakter sebelum dikonfirmasi.');
                if (inputPesan) inputPesan.focus();
                return;
            }
            showReportError('');

            const katLabel = inputKategori?.options[inputKategori.selectedIndex]?.text || 'Laporan';
            const tglVal = inputTanggal?.value || '';
            const refVal = (inputRef?.value || '').trim();
            const subVal = (inputSubjek?.value || '').trim() || (tglVal ? '[Kendala Absensi ' + tglVal + ']' : katLabel);

            if (confirmKategori) confirmKategori.textContent = katLabel;
            if (confirmRefWrap && confirmRef) {
                if (tglVal || refVal) {
                    confirmRefWrap.hidden = false;
                    confirmRef.textContent = [tglVal, refVal].filter(Boolean).join(' · ');
                } else {
                    confirmRefWrap.hidden = true;
                }
            }
            if (confirmSubjek) confirmSubjek.textContent = subVal;
            if (confirmPesan) confirmPesan.textContent = pesanVal;

            if (stepEdit) stepEdit.hidden = true;
            if (stepConfirm) stepConfirm.hidden = false;
            if (stepDone) stepDone.hidden = true;
        });
    }

    if (backEditBtn) {
        backEditBtn.addEventListener('click', function () {
            if (stepEdit) stepEdit.hidden = false;
            if (stepConfirm) stepConfirm.hidden = true;
        });
    }

    if (resetReportBtn) {
        resetReportBtn.addEventListener('click', function () {
            if (reportForm) reportForm.reset();
            if (stepEdit) stepEdit.hidden = false;
            if (stepConfirm) stepConfirm.hidden = true;
            if (stepDone) stepDone.hidden = true;
        });
    }

    if (reportForm) {
        reportForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const submitUrl = body?.dataset?.helpSubmitUrl;
            if (!submitUrl) return;
            if (submitConfirmBtn) submitConfirmBtn.disabled = true;

            try {
                const payload = {
                    kategori: inputKategori?.value || 'absensi',
                    tanggal_absensi: inputTanggal?.value || null,
                    referensi_absensi: inputRef?.value || null,
                    subjek: inputSubjek?.value || null,
                    pesan: inputPesan?.value || ''
                };

                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    const errMsg = data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Gagal mengirim laporan.');
                    if (stepEdit) stepEdit.hidden = false;
                    if (stepConfirm) stepConfirm.hidden = true;
                    showReportError(errMsg);
                    return;
                }

                if (doneTitle) doneTitle.textContent = 'Laporan ' + (data.ticket_ref || '') + ' Berhasil Dikirim';
                if (doneMeta) {
                    doneMeta.textContent = 'Status: ' + (data.status || 'baru').toUpperCase() + ' · Tercatat pada ' + (data.created_at || '');
                }
                if (stepEdit) stepEdit.hidden = true;
                if (stepConfirm) stepConfirm.hidden = true;
                if (stepDone) stepDone.hidden = false;
            } catch (err) {
                if (stepEdit) stepEdit.hidden = false;
                if (stepConfirm) stepConfirm.hidden = true;
                showReportError('Gagal mengirim laporan. Silakan coba lagi.');
            } finally {
                if (submitConfirmBtn) submitConfirmBtn.disabled = false;
            }
        });
    }

    // Pre-fill report from Attendance Detail Modal ("Laporkan Masalah Absensi Tanggal Ini")
    if (attModalReportBtn) {
        attModalReportBtn.addEventListener('click', function () {
            const p = currentAttPayload;
            closeAttDetailModal();
            openHelpWidget('report');
            if (stepEdit) stepEdit.hidden = false;
            if (stepConfirm) stepConfirm.hidden = true;
            if (stepDone) stepDone.hidden = true;

            if (p) {
                if (inputKategori) inputKategori.value = 'absensi';
                if (inputTanggal && p.tanggal) inputTanggal.value = p.tanggal;
                if (inputRef) {
                    inputRef.value = (p.kelengkapan_label || '') + ' (Masuk: ' + (p.jam_masuk || '—') + ', Pulang: ' + (p.jam_pulang || '—') + ')';
                }
                if (inputSubjek) {
                    inputSubjek.value = 'Kendala Absensi ' + (p.tanggal_human || p.tanggal || '');
                }
                if (inputPesan) inputPesan.focus();
            }
        });
    }

    // Global Escape key handler for modals & drawers
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (notifModal && !notifModal.hidden) {
            closeNotifDetailModal();
            return;
        }
        if (attModal && !attModal.hidden) {
            closeAttDetailModal();
            return;
        }
        if (notifDrawer && !notifDrawer.hidden) {
            closeNotifDrawer();
            return;
        }
        if (helpWidget && !helpWidget.hidden) {
            closeHelpWidget();
            return;
        }
        closeUserDropdown();
        closeMobileDrawer();
    });

    // 8. Session Guard Ping
    const pingUrl = body?.dataset?.sessionPingUrl;
    const loginUrl = body?.dataset?.loginUrl || '/masuk';
    if (pingUrl) {
        setInterval(async function () {
            if (document.hidden) return;
            try {
                const res = await fetch(pingUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (res.status === 401 || res.status === 409) {
                    window.location.href = loginUrl;
                }
            } catch (e) {}
        }, 12000);
    }
})();

