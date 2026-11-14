/**
 * AIoT SIMALAS — Auth theme toggle + role hover + stagger cleanup
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'simalas-auth-theme';

    function prefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function readStoredTheme() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function writeStoredTheme(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {}
    }

    function isDarkMode() {
        var stored = readStoredTheme();
        if (stored === 'dark') return true;
        if (stored === 'light') return false;
        return prefersDark();
    }

    function applyTheme(dark) {
        document.body.classList.toggle('auth-theme-dark', dark);

        var btn = document.getElementById('authThemeToggle');
        if (!btn) return;

        var moon = btn.querySelector('.auth-theme-icon--moon');
        var sun = btn.querySelector('.auth-theme-icon--sun');
        if (moon) moon.hidden = dark;
        if (sun) sun.hidden = !dark;

        var lang = document.documentElement.lang === 'en' ? 'en' : 'id';
        var ariaDark = btn.getAttribute('data-' + lang + '-aria') || 'Mode gelap';
        var ariaLight = lang === 'en' ? 'Light mode' : 'Mode terang';
        btn.setAttribute('aria-label', dark ? ariaLight : ariaDark);
        btn.setAttribute('title', dark ? ariaLight : ariaDark);
    }

    function initThemeToggle() {
        applyTheme(isDarkMode());

        var btn = document.getElementById('authThemeToggle');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var nextDark = !document.body.classList.contains('auth-theme-dark');
            applyTheme(nextDark);
            writeStoredTheme(nextDark ? 'dark' : 'light');
            if (window.lucide) lucide.createIcons();
        });
    }

    function initRoleHover() {
        document.querySelectorAll('.role-option').forEach(function (option) {
            option.addEventListener('mouseenter', function () {
                option.classList.add('is-hovered');
            });
            option.addEventListener('mouseleave', function () {
                option.classList.remove('is-hovered');
            });
        });
    }

    function finishStagger() {
        var card = document.getElementById('authCard');
        var hero = document.querySelector('.auth-split-hero-content');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion) {
            if (card) card.classList.remove('auth-stagger-pending');
            if (hero) hero.classList.remove('auth-hero-anim-pending');
            return;
        }

        window.setTimeout(function () {
            if (card) card.classList.remove('auth-stagger-pending');
        }, 900);

        window.setTimeout(function () {
            if (hero) hero.classList.remove('auth-hero-anim-pending');
        }, 1100);
    }

    function init() {
        initThemeToggle();
        initRoleHover();
        finishStagger();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
