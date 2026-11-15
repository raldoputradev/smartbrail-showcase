<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>SmartBrail — Portal Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('branding/brail-mark.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>:root { --sb-campus-img: url('{{ asset('images/campus-polybatam.jpg') }}'); }</style>
    @routes
    @vite('resources/js/admin/main.tsx')
    @inertiaHead
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="antialiased">
    {{-- Splash boot screen — muncul instan sebelum React mount, fade-out saat app siap. --}}
    <div id="sb-splash" aria-hidden="true">
        <div class="sb-splash__orb sb-splash__orb--a"></div>
        <div class="sb-splash__orb sb-splash__orb--b"></div>
        <div class="sb-splash__inner">
            <div class="sb-splash__ring">
                <span class="sb-splash__pulse"></span>
                <img src="{{ asset('branding/brail-mark.png') }}" alt="BRAIL" class="sb-splash__logo">
            </div>
            <div class="sb-splash__name">Smart<span>Brail</span></div>
            <div class="sb-splash__sub">Portal {{ ['admin' => 'Admin', 'laboran' => 'Laboran', 'dosen' => 'Dosen'][auth()->user()->role ?? ''] ?? 'Staf' }} · Memuat…</div>
            <div class="sb-splash__bar"><span></span></div>
        </div>
    </div>
    <style>
        #sb-splash { position: fixed; inset: 0; z-index: 9999; display: grid; place-items: center; overflow: hidden;
            background: radial-gradient(120% 120% at 20% 10%, #12264d 0%, #0b1730 45%, #070d1d 100%); transition: opacity .45s ease, visibility .45s; }
        #sb-splash.sb-splash--hide { opacity: 0; visibility: hidden; pointer-events: none; }
        .sb-splash__orb { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .5; }
        .sb-splash__orb--a { width: 340px; height: 340px; background: #1d6ff2; top: -90px; right: -70px; animation: sbSplashFloat 7s ease-in-out infinite; }
        .sb-splash__orb--b { width: 260px; height: 260px; background: #38bdf8; bottom: -80px; left: -60px; animation: sbSplashFloat 9s ease-in-out infinite reverse; }
        @keyframes sbSplashFloat { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(26px) scale(1.08); } }
        .sb-splash__inner { position: relative; display: grid; justify-items: center; gap: 10px; }
        .sb-splash__ring { position: relative; width: 96px; height: 96px; display: grid; place-items: center; }
        .sb-splash__ring::before { content: ''; position: absolute; inset: 0; border-radius: 50%; padding: 3px;
            background: conic-gradient(#38bdf8, #1d6ff2, transparent 65%, #38bdf8);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; mask-composite: exclude;
            animation: sbSplashSpin 1.6s linear infinite; }
        @keyframes sbSplashSpin { to { transform: rotate(360deg); } }
        .sb-splash__pulse { position: absolute; inset: 12px; border-radius: 50%; background: rgba(56,189,248,.12); animation: sbSplashPulse 1.6s ease-in-out infinite; }
        @keyframes sbSplashPulse { 0%,100% { transform: scale(.9); opacity: .5; } 50% { transform: scale(1.08); opacity: 1; } }
        .sb-splash__logo { position: relative; width: 56px; height: 56px; border-radius: 16px; }
        .sb-splash__name { font: 800 26px/1 'Plus Jakarta Sans', system-ui, sans-serif; color: #eaf2ff; letter-spacing: .5px; }
        .sb-splash__name span { color: #38bdf8; }
        .sb-splash__sub { font: 600 11.5px/1 'Plus Jakarta Sans', system-ui, sans-serif; color: #7f97c4; letter-spacing: .14em; text-transform: uppercase; }
        .sb-splash__bar { margin-top: 8px; width: 190px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.09); overflow: hidden; }
        .sb-splash__bar span { display: block; height: 100%; width: 38%; border-radius: 4px;
            background: linear-gradient(90deg, #38bdf8, #1d6ff2); animation: sbSplashBar 1.15s ease-in-out infinite; }
        @keyframes sbSplashBar { 0% { transform: translateX(-110%); } 60%,100% { transform: translateX(400%); } }
    </style>
    <script>
        (function () {
            var splash = document.getElementById('sb-splash');
            var done = false;
            function hide() { if (done || !splash) return; done = true; splash.classList.add('sb-splash--hide'); setTimeout(function () { splash.remove(); }, 600); }
            window.addEventListener('load', function () { setTimeout(hide, 250); });
            setTimeout(hide, 5000);
        })();
    </script>
    @inertia
    <script>
        // Terapkan preferensi tema tersimpan sebelum React mount — kunci sama
        // dengan layout Blade admin (dashboard_theme) agar tema konsisten
        // di halaman React maupun halaman Blade yang belum dimigrasi.
        try {
            const t = localStorage.getItem('dashboard_theme');
            if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
        } catch (e) {}
    </script>
</body>
</html>
