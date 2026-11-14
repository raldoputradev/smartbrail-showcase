/**
 * Persist sidebar scroll + panduan (details) open state across reload / auto-refresh.
 */
(function () {
    'use strict';

    var PANDUAN_SELECTOR =
        'details.absensi-panduan-panel, details.fingerprint-sync-panduan, details.locker-panduan-panel, details.fp-log-panel';
    var SIDEBAR_SCROLL_KEY = 'simalas_sidebar_nav_scroll';
    var MAIN_SCROLL_PREFIX = 'simalas_main_scroll_';
    var PANDUAN_PREFIX = 'simalas_panduan_';

    function mainScrollEl() {
        return document.querySelector('.main-content');
    }

    function mainScrollStorageKey() {
        return MAIN_SCROLL_PREFIX + location.pathname;
    }

    function persistMainScroll() {
        var main = mainScrollEl();
        if (!main) {
            return;
        }
        sessionStorage.setItem(mainScrollStorageKey(), String(main.scrollTop));
    }

    function restoreMainScroll() {
        var main = mainScrollEl();
        if (!main) {
            return false;
        }
        var raw = sessionStorage.getItem(mainScrollStorageKey());
        if (raw === null) {
            return false;
        }
        var top = parseInt(raw, 10);
        if (!isNaN(top) && top >= 0) {
            main.scrollTop = top;
            return true;
        }
        return false;
    }

    function restoreMainScrollWithRetry() {
        restoreMainScroll();
        requestAnimationFrame(function () {
            restoreMainScroll();
            requestAnimationFrame(restoreMainScroll);
        });
    }

    function bindMainScroll() {
        var main = mainScrollEl();
        if (!main || main.dataset.scrollBound === '1') {
            return;
        }
        main.dataset.scrollBound = '1';

        var saveTimer = null;
        main.addEventListener(
            'scroll',
            function () {
                if (saveTimer) {
                    clearTimeout(saveTimer);
                }
                saveTimer = setTimeout(persistMainScroll, 80);
            },
            { passive: true }
        );
    }

    function panduanStorageKey(el) {
        var key = el.getAttribute('data-panduan-key');
        if (key) {
            return PANDUAN_PREFIX + key;
        }
        var summary = el.querySelector('summary');
        var slug = (summary ? summary.textContent : 'panduan')
            .trim()
            .slice(0, 80)
            .replace(/\s+/g, '_');
        return PANDUAN_PREFIX + location.pathname.replace(/\//g, '_') + '_' + slug;
    }

    function restorePanduanPanels() {
        document.querySelectorAll(PANDUAN_SELECTOR).forEach(function (el) {
            var storageKey = panduanStorageKey(el);
            var saved = localStorage.getItem(storageKey);
            if (saved === 'open') {
                el.setAttribute('open', '');
            } else if (saved === 'closed') {
                el.removeAttribute('open');
            }
        });
    }

    function bindPanduanPanels() {
        document.querySelectorAll(PANDUAN_SELECTOR).forEach(function (el) {
            if (el.dataset.panduanBound === '1') {
                return;
            }
            el.dataset.panduanBound = '1';
            el.addEventListener('toggle', function () {
                localStorage.setItem(
                    panduanStorageKey(el),
                    el.open ? 'open' : 'closed'
                );
            });
        });
    }

    function sidebarNav() {
        return document.querySelector('.sidebar-nav');
    }

    function persistSidebarScroll() {
        var nav = sidebarNav();
        if (!nav) {
            return;
        }
        sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(nav.scrollTop));
    }

    function restoreSidebarScroll() {
        var nav = sidebarNav();
        if (!nav) {
            return false;
        }
        var raw = sessionStorage.getItem(SIDEBAR_SCROLL_KEY);
        if (raw === null) {
            return false;
        }
        var top = parseInt(raw, 10);
        if (!isNaN(top) && top >= 0) {
            nav.scrollTop = top;
            return true;
        }
        return false;
    }

    function restoreSidebarScrollWithRetry() {
        restoreSidebarScroll();
        requestAnimationFrame(function () {
            restoreSidebarScroll();
            requestAnimationFrame(restoreSidebarScroll);
        });
    }

    function bindSidebarScroll() {
        var nav = sidebarNav();
        if (!nav || nav.dataset.scrollBound === '1') {
            return;
        }
        nav.dataset.scrollBound = '1';

        var saveTimer = null;
        nav.addEventListener(
            'scroll',
            function () {
                if (saveTimer) {
                    clearTimeout(saveTimer);
                }
                saveTimer = setTimeout(persistSidebarScroll, 80);
            },
            { passive: true }
        );

        nav.addEventListener(
            'pointerdown',
            function (e) {
                var link = e.target.closest('a[href]');
                if (link && nav.contains(link)) {
                    persistSidebarScroll();
                }
            },
            true
        );
    }

    function init() {
        restorePanduanPanels();
        bindPanduanPanels();
        restoreSidebarScrollWithRetry();
        bindSidebarScroll();
        restoreMainScrollWithRetry();
        bindMainScroll();
    }

    window.simalasPersistDashboardUi = function () {
        document.querySelectorAll(PANDUAN_SELECTOR).forEach(function (el) {
            localStorage.setItem(
                panduanStorageKey(el),
                el.open ? 'open' : 'closed'
            );
        });
        persistSidebarScroll();
        persistMainScroll();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('load', function () {
        restoreSidebarScrollWithRetry();
        restoreMainScrollWithRetry();
    });
    window.addEventListener('pageshow', function () {
        restorePanduanPanels();
        restoreSidebarScrollWithRetry();
        restoreMainScrollWithRetry();
    });

    window.addEventListener('beforeunload', function () {
        window.simalasPersistDashboardUi();
    });
})();
