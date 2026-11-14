/**
 * SIMALAS — auth page ambient background (cursor-direction flow, no spotlight/glow)
 */
(function () {
    'use strict';

    if (!document.body.classList.contains('auth')) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canvas = document.getElementById('authAmbientCanvas');
    const root = document.documentElement;

    let px = 0.5;
    let py = 0.5;
    let sx = 0.5;
    let sy = 0.5;
    let vx = 0;
    let vy = 0;
    let svx = 0;
    let svy = 0;
    let lastX = null;
    let lastY = null;
    let tick = 0;

    function setPointer(clientX, clientY) {
        const w = window.innerWidth || 1;
        const h = window.innerHeight || 1;
        const nx = clientX / w;
        const ny = clientY / h;

        if (lastX !== null) {
            vx = (clientX - lastX) / w;
            vy = (clientY - lastY) / h;
        }
        lastX = clientX;
        lastY = clientY;
        px = nx;
        py = ny;
    }

    window.addEventListener('mousemove', (e) => setPointer(e.clientX, e.clientY), { passive: true });
    window.addEventListener('touchmove', (e) => {
        if (e.touches && e.touches[0]) {
            setPointer(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: true });

    function updateCssParallax() {
        sx += (px - sx) * (reduceMotion ? 1 : 0.07);
        sy += (py - sy) * (reduceMotion ? 1 : 0.07);
        svx += (vx - svx) * 0.12;
        svy += (vy - svy) * 0.12;
        vx *= 0.92;
        vy *= 0.92;

        root.style.setProperty('--auth-cx', sx.toFixed(4));
        root.style.setProperty('--auth-cy', sy.toFixed(4));
        root.style.setProperty('--auth-vx', svx.toFixed(5));
        root.style.setProperty('--auth-vy', svy.toFixed(5));
        root.style.setProperty('--auth-tick', String(tick));

        const blobs = document.querySelectorAll('.auth-ambient-shape');
        blobs.forEach((el, i) => {
            const d = (i + 1) * 18;
            const sign = i % 2 === 0 ? 1 : -1;
            const driftX = sx * d * sign + svx * 120 * sign;
            const driftY = sy * d * 0.75 + svy * 90;
            const rot = (sx - 0.5) * 12 * sign + tick * (0.015 + i * 0.004);
            el.style.transform = `translate3d(${driftX}px, ${driftY}px, 0) rotate(${rot}deg)`;
        });

        const grid = document.querySelector('.auth-ambient-grid');
        if (grid) {
            grid.style.transform = `translate3d(${(sx - 0.5) * -28}px, ${(sy - 0.5) * -22}px, 0)`;
        }
    }

    if (!canvas || reduceMotion) {
        (function staticLoop() {
            tick += 1;
            updateCssParallax();
            requestAnimationFrame(staticLoop);
        })();
        return;
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let width = 0;
    let height = 0;
    let nodes = [];
    let ribbons = [];
    const nodeCount = 42;
    const ribbonCount = 5;

    function resize() {
        width = window.innerWidth;
        height = window.innerHeight;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        nodes = Array.from({ length: nodeCount }, (_, i) => ({
            x: Math.random() * width,
            y: Math.random() * height,
            ox: Math.random() * Math.PI * 2,
            r: 1.2 + Math.random() * 1.6,
            hue: i % 3 === 0 ? 214 : i % 3 === 1 ? 172 : 248,
        }));

        ribbons = Array.from({ length: ribbonCount }, (_, i) => ({
            yBase: (height / (ribbonCount + 1)) * (i + 1),
            amp: 28 + Math.random() * 36,
            freq: 0.004 + Math.random() * 0.003,
            phase: Math.random() * Math.PI * 2,
            speed: 0.008 + Math.random() * 0.012,
            hue: [214, 172, 248, 199, 230][i],
        }));
    }

    resize();
    window.addEventListener('resize', resize, { passive: true });

    function drawRibbons() {
        const windX = svx * width * 2.2;
        const windY = svy * height * 1.6;
        const parallaxY = (sy - 0.5) * 40;

        ribbons.forEach((ribbon, idx) => {
            const t = tick * ribbon.speed;
            ctx.beginPath();
            for (let x = -40; x <= width + 40; x += 8) {
                const wave =
                    Math.sin(x * ribbon.freq + ribbon.phase + t) * ribbon.amp +
                    Math.sin(x * ribbon.freq * 0.45 + t * 1.3) * (ribbon.amp * 0.35);
                const y =
                    ribbon.yBase +
                    wave +
                    parallaxY * (0.4 + idx * 0.12) +
                    windY * 0.25 +
                    Math.sin(t + x * 0.002) * windX * 0.08;

                if (x === -40) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.strokeStyle = `hsla(${ribbon.hue}, 72%, 58%, ${0.14 + idx * 0.02})`;
            ctx.lineWidth = 1.4 + (idx % 2);
            ctx.stroke();
        });
    }

    function drawNodes() {
        const mx = sx * width;
        const my = sy * height;
        const forceX = svx * width * 3.5;
        const forceY = svy * height * 3.5;
        const linkDist = 118;

        for (let i = 0; i < nodes.length; i++) {
            const n = nodes[i];
            const orbitX = Math.cos(n.ox + tick * 0.004) * 0.35;
            const orbitY = Math.sin(n.ox + tick * 0.005) * 0.35;

            n.x += orbitX + forceX * 0.018 + (mx - n.x) * 0.0004;
            n.y += orbitY + forceY * 0.018 + (my - n.y) * 0.0004;

            if (n.x < -20) n.x = width + 20;
            if (n.x > width + 20) n.x = -20;
            if (n.y < -20) n.y = height + 20;
            if (n.y > height + 20) n.y = -20;

            for (let j = i + 1; j < nodes.length; j++) {
                const m = nodes[j];
                const dx = n.x - m.x;
                const dy = n.y - m.y;
                const dist = Math.hypot(dx, dy);
                if (dist > linkDist) continue;

                const alpha = (1 - dist / linkDist) * 0.2;
                ctx.strokeStyle = `hsla(214, 70%, 52%, ${alpha})`;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(n.x, n.y);
                ctx.lineTo(m.x, m.y);
                ctx.stroke();
            }

            ctx.fillStyle = `hsla(${n.hue}, 68%, 48%, 0.55)`;
            ctx.beginPath();
            ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function drawOrbits() {
        const cx = sx * width;
        const cy = sy * height;
        const rings = [
            { r: Math.min(width, height) * 0.22, a: 0.07, w: 1.2 },
            { r: Math.min(width, height) * 0.34, a: 0.05, w: 1 },
            { r: Math.min(width, height) * 0.46, a: 0.035, w: 0.8 },
        ];

        rings.forEach((ring, i) => {
            const offX = svx * width * (18 + i * 8);
            const offY = svy * height * (14 + i * 6);
            ctx.beginPath();
            ctx.ellipse(cx + offX, cy + offY, ring.r, ring.r * 0.72, tick * 0.001 + i * 0.4, 0, Math.PI * 2);
            ctx.strokeStyle = `hsla(${210 + i * 18}, 65%, 55%, ${ring.a})`;
            ctx.lineWidth = ring.w;
            ctx.stroke();
        });
    }

    function frame() {
        tick += 1;
        updateCssParallax();

        ctx.clearRect(0, 0, width, height);
        drawRibbons();
        drawOrbits();
        drawNodes();

        requestAnimationFrame(frame);
    }

    frame();
})();
