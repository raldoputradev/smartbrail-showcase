/**
 * AIoT SIMALAS — Landing global dark / light theme
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'simalas-landing-theme';
    var body = document.body;

    function isDark() {
        return body.classList.contains('landing-theme-dark');
    }

    function applyTheme(dark) {
        body.classList.toggle('landing-theme-dark', dark);
        body.classList.toggle('landing-theme-light', !dark);

        var btn = document.getElementById('landingThemeToggle');
        if (!btn) return;

        var lang = document.documentElement.lang === 'en' ? 'en' : 'id';
        btn.setAttribute(
            'aria-label',
            dark
                ? (lang === 'en' ? 'Light mode' : 'Mode terang')
                : (lang === 'en' ? 'Dark mode' : 'Mode gelap')
        );
        btn.setAttribute('title', btn.getAttribute('aria-label') || '');
    }

    function readStored() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function writeStored(dark) {
        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch (e) {}
    }

    function initThemeToggle() {
        var stored = readStored();
        var dark = stored !== 'light';
        applyTheme(dark);

        var btn = document.getElementById('landingThemeToggle');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var nextDark = !isDark();
            applyTheme(nextDark);
            writeStored(nextDark);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeToggle);
    } else {
        initThemeToggle();
    }
})();
