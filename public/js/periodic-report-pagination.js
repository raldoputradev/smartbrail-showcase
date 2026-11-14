/**
 * Laporan Periodik — pagination tanpa reload halaman penuh.
 */
(function () {
    'use strict';

    var PANELS = [
        { id: 'periodic-recap-panel', param: 'recap_page' },
        { id: 'periodic-detail-panel', param: 'detail_page' },
    ];

    function panelScrollTop(panel) {
        var anchor = panel.closest('.card') || panel;
        return anchor.getBoundingClientRect().top + window.scrollY - 88;
    }

    function fetchDocument(url) {
        return fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html',
            },
            credentials: 'same-origin',
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        });
    }

    function replacePanel(panel, html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById(panel.id);
        if (!fresh) {
            return false;
        }
        panel.innerHTML = fresh.innerHTML;
        return true;
    }

    function loadPanel(panel, url) {
        var scrollTop = panelScrollTop(panel);
        panel.setAttribute('aria-busy', 'true');
        panel.classList.add('is-pagination-loading');

        return fetchDocument(url)
            .then(function (html) {
                if (!replacePanel(panel, html)) {
                    window.location.href = url;
                    return;
                }
                history.pushState({ periodicPagination: true }, '', url);
                window.scrollTo({ top: scrollTop, behavior: 'instant' });
            })
            .catch(function () {
                window.location.href = url;
            })
            .finally(function () {
                panel.removeAttribute('aria-busy');
                panel.classList.remove('is-pagination-loading');
            });
    }

    function bindPanel(panel) {
        panel.addEventListener('click', function (event) {
            var link = event.target.closest('a.simalas-pagination-btn[href]');
            if (!link || !panel.contains(link)) {
                return;
            }
            event.preventDefault();
            loadPanel(panel, link.href);
        });
    }

    function refreshPanelsFromUrl(url) {
        return fetchDocument(url).then(function (html) {
            PANELS.forEach(function (cfg) {
                var panel = document.getElementById(cfg.id);
                if (panel) {
                    replacePanel(panel, html);
                }
            });

            var params = new URL(url, window.location.origin).searchParams;
            var target = null;
            if (params.get('detail_page') && params.get('detail_page') !== '1') {
                target = document.getElementById('periodic-detail-panel');
            } else if (params.get('recap_page') && params.get('recap_page') !== '1') {
                target = document.getElementById('periodic-recap-panel');
            }
            if (target) {
                window.scrollTo({ top: panelScrollTop(target), behavior: 'instant' });
            }
        });
    }

    function scrollToActivePanelOnLoad() {
        var params = new URLSearchParams(window.location.search);
        var target = null;
        if (params.get('detail_page') && params.get('detail_page') !== '1') {
            target = document.getElementById('periodic-detail-panel');
        } else if (params.get('recap_page') && params.get('recap_page') !== '1') {
            target = document.getElementById('periodic-recap-panel');
        }
        if (target) {
            window.scrollTo({ top: panelScrollTop(target), behavior: 'instant' });
        }
    }

    window.addEventListener('popstate', function () {
        if (!document.getElementById('periodic-detail-panel') && !document.getElementById('periodic-recap-panel')) {
            return;
        }
        refreshPanelsFromUrl(window.location.href).catch(function () {
            window.location.reload();
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        PANELS.forEach(function (cfg) {
            var panel = document.getElementById(cfg.id);
            if (panel) {
                bindPanel(panel);
            }
        });
        scrollToActivePanelOnLoad();
    });
})();
