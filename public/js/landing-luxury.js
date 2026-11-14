/**
 * AIoT SIMALAS — Landing: scrollspy + scroll reveal
 */
(function () {
    'use strict';

    var SECTIONS = ['top', 'tentang', 'fitur', 'tim', 'kontak'];

    function initScrollspy() {
        var nav = document.querySelector('.landing .nav');
        var desktopLinks = document.querySelectorAll('.landing .nav-links a[href^="#"]');
        var mobileLinks = document.querySelectorAll('.landing .nav-mobile a[href^="#"]');
        var allLinks = Array.prototype.slice.call(desktopLinks).concat(
            Array.prototype.slice.call(mobileLinks)
        );

        if (!allLinks.length) return;

        function getSectionId(href) {
            if (!href || href.charAt(0) !== '#') return null;
            var id = href.slice(1);
            return id || 'top';
        }

        function setActive(sectionId) {
            allLinks.forEach(function (link) {
                var linkId = getSectionId(link.getAttribute('href'));
                link.classList.toggle('is-active', linkId === sectionId);
            });
        }

        function observeSections() {
            var observed = [];
            SECTIONS.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) observed.push({ id: id, el: el });
            });

            if (!observed.length) return;

            var ratios = {};
            observed.forEach(function (item) { ratios[item.id] = 0; });

            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    var id = entry.target.id || 'top';
                    ratios[id] = entry.isIntersecting ? entry.intersectionRatio : 0;
                });

                var best = 'top';
                var bestRatio = -1;
                observed.forEach(function (item) {
                    if (ratios[item.id] > bestRatio) {
                        bestRatio = ratios[item.id];
                        best = item.id;
                    }
                });

                if (bestRatio > 0) setActive(best);
            }, {
                root: null,
                rootMargin: '-20% 0px -55% 0px',
                threshold: [0, 0.1, 0.25, 0.5, 0.75, 1]
            });

            observed.forEach(function (item) { io.observe(item.el); });
        }

        if (nav) {
            var scrollTicking = false;
            window.addEventListener('scroll', function () {
                if (scrollTicking) return;
                scrollTicking = true;
                requestAnimationFrame(function () {
                    nav.classList.toggle('is-scrolled', window.scrollY > 24);
                    scrollTicking = false;
                });
            }, { passive: true });
            nav.classList.toggle('is-scrolled', window.scrollY > 24);
        }

        allLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                var id = getSectionId(link.getAttribute('href'));
                if (id) setActive(id);
            });
        });

        observeSections();
        setActive('top');
    }

    function initScrollReveal() {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var items = document.querySelectorAll('.landing .reveal');

        if (!items.length) return;

        if (reduce || !('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('in'); });
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -8% 0px'
        });

        items.forEach(function (el) { io.observe(el); });
    }

    function initHeroIconCycle() {
        var icons = document.querySelectorAll('#heroIconSwitcher .hero-tech-icon');
        if (icons.length < 2) return;

        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return;

        var idx = 0;
        window.setInterval(function () {
            icons[idx].classList.remove('is-active');
            idx = (idx + 1) % icons.length;
            icons[idx].classList.add('is-active');
        }, 3000);
    }

    function initHeroEngineeringAnim() {
        var copy = document.querySelector('.landing .hero-engineering-copy');
        if (!copy) return;

        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            copy.classList.remove('hero-copy-anim-pending');
            return;
        }

        window.setTimeout(function () {
            copy.classList.remove('hero-copy-anim-pending');
        }, 700);
    }

    function initFooterSocial() {
        var buttons = document.querySelectorAll('.landing .footer-social-btn[data-social]');
        if (!buttons.length) return;

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var label = btn.getAttribute('data-social') || 'Media sosial';
                var isEn = document.documentElement.lang === 'en';
                var title = isEn ? 'Coming soon' : 'Segera hadir';
                var text = isEn
                    ? label + ' will be available soon.'
                    : label + ' akan segera tersedia.';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: title,
                        text: text,
                        confirmButtonColor: '#0891b2',
                        confirmButtonText: isEn ? 'OK' : 'Mengerti',
                    });
                    return;
                }

                window.alert(title + ': ' + label);
            });
        });
    }

    function init() {
        initScrollspy();
        initScrollReveal();
        initHeroEngineeringAnim();
        initHeroIconCycle();
        initFooterSocial();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
