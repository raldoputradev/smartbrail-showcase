(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const root = document.documentElement;
    const body = document.body;
    const isLanding = body.classList.contains('landing');
    const isAuth = body.classList.contains('auth');

    if (!isLanding && !isAuth) return;

    let cursorX = 0.5;
    let cursorY = 0.5;
    let smoothX = 0.5;
    let smoothY = 0.5;
    let scrollHue = isLanding ? 195 : 210;

    const themes = [
        { h: 192, a: '#5eead4', b: '#38bdf8' },
        { h: 268, a: '#c4b5fd', b: '#818cf8' },
        { h: 32, a: '#fcd34d', b: '#fb923c' },
        { h: 340, a: '#fda4af', b: '#f472b6' },
        { h: 158, a: '#6ee7b7', b: '#34d399' },
        { h: 214, a: '#7dd3fc', b: '#60a5fa' },
    ];

    function setPointer(clientX, clientY) {
        const w = window.innerWidth || 1;
        const h = window.innerHeight || 1;
        cursorX = clientX / w;
        cursorY = clientY / h;
    }

    window.addEventListener('mousemove', (e) => setPointer(e.clientX, e.clientY), { passive: true });
    window.addEventListener('touchmove', (e) => {
        if (e.touches && e.touches[0]) {
            setPointer(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: true });

    function paintFrame() {
        smoothX += (cursorX - smoothX) * (reduceMotion ? 1 : 0.08);
        smoothY += (cursorY - smoothY) * (reduceMotion ? 1 : 0.08);

        root.style.setProperty('--cursor-x', smoothX.toFixed(4));
        root.style.setProperty('--cursor-y', smoothY.toFixed(4));
        root.style.setProperty('--theme-h', String(Math.round(scrollHue)));

        if (!reduceMotion) {
            const blobs = document.querySelectorAll('.ambient-blob');
            blobs.forEach((blob, index) => {
                const depth = (index + 1) * 14;
                const sign = index % 2 === 0 ? 1 : -1;
                blob.style.transform = `translate3d(${smoothX * depth * sign}px, ${smoothY * depth * 0.8}px, 0)`;
            });

            const grid = document.querySelector('.ambient-grid');
            if (grid) {
                grid.style.transform = `translate3d(${smoothX * -10}px, ${smoothY * -10}px, 0)`;
            }

            const spotlight = document.querySelector('.ambient-spotlight');
            if (spotlight) {
                spotlight.style.transform = `translate3d(calc(${smoothX * 100}vw - 50%), calc(${smoothY * 100}vh - 50%), 0)`;
            }
        }

        requestAnimationFrame(paintFrame);
    }

    paintFrame();

    if (isLanding && !reduceMotion) {
        const sections = Array.from(document.querySelectorAll('[data-scroll-theme]'));
        if (sections.length && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const idx = parseInt(entry.target.getAttribute('data-scroll-theme') || '0', 10);
                    const theme = themes[idx] || themes[0];
                    scrollHue = theme.h;
                    root.style.setProperty('--accent-a', theme.a);
                    root.style.setProperty('--accent-b', theme.b);
                });
            }, { threshold: 0.35, rootMargin: '-20% 0px -20% 0px' });

            sections.forEach((section) => observer.observe(section));
        }
    }

    const canvas = document.getElementById('ambientCanvas');
    if (!canvas || reduceMotion) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let width = 0;
    let height = 0;
    let particles = [];
    const count = isLanding ? 54 : 36;

    function resizeCanvas() {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = Math.floor(width * (window.devicePixelRatio || 1));
        canvas.height = Math.floor(height * (window.devicePixelRatio || 1));
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(window.devicePixelRatio || 1, 0, 0, window.devicePixelRatio || 1, 0, 0);

        particles = Array.from({ length: count }, () => ({
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.45,
            vy: (Math.random() - 0.5) * 0.45,
            r: 1 + Math.random() * 1.8,
        }));
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas, { passive: true });

    function drawParticles() {
        ctx.clearRect(0, 0, width, height);
        const mx = smoothX * width;
        const my = smoothY * height;
        const linkDist = isLanding ? 130 : 110;

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];
            p.x += p.vx + (mx - p.x) * 0.0008;
            p.y += p.vy + (my - p.y) * 0.0008;

            if (p.x < 0 || p.x > width) p.vx *= -1;
            if (p.y < 0 || p.y > height) p.vy *= -1;

            for (let j = i + 1; j < particles.length; j++) {
                const q = particles[j];
                const dx = p.x - q.x;
                const dy = p.y - q.y;
                const dist = Math.hypot(dx, dy);
                if (dist > linkDist) continue;

                const alpha = (1 - dist / linkDist) * 0.22;
                ctx.strokeStyle = `rgba(148, 196, 255, ${alpha})`;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(q.x, q.y);
                ctx.stroke();
            }

            const glow = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * 4);
            glow.addColorStop(0, 'rgba(94, 234, 212, 0.55)');
            glow.addColorStop(1, 'rgba(94, 234, 212, 0)');
            ctx.fillStyle = glow;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r * 4, 0, Math.PI * 2);
            ctx.fill();
        }

        const cursorGlow = ctx.createRadialGradient(mx, my, 0, mx, my, isLanding ? 220 : 180);
        cursorGlow.addColorStop(0, 'rgba(125, 211, 252, 0.18)');
        cursorGlow.addColorStop(1, 'rgba(125, 211, 252, 0)');
        ctx.fillStyle = cursorGlow;
        ctx.beginPath();
        ctx.arc(mx, my, isLanding ? 220 : 180, 0, Math.PI * 2);
        ctx.fill();

        requestAnimationFrame(drawParticles);
    }

    drawParticles();
})();
