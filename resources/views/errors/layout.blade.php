<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') — {{ \App\Support\Branding::appName() }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #060d1f; --card: #0d1930; --border: #1d2f52;
            --text: #e8effc; --muted: #8ba3cc;
            --accent: #38bdf8;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', system-ui, sans-serif; color: var(--text);
            background:
                radial-gradient(900px 500px at 15% -10%, rgba(56,189,248,.12), transparent 60%),
                radial-gradient(800px 460px at 90% 110%, rgba(37,99,235,.14), transparent 60%),
                var(--bg);
            display: grid; place-items: center; padding: 28px;
        }
        .err-card {
            width: min(620px, 100%); text-align: center; position: relative;
            background: linear-gradient(180deg, rgba(23,41,74,.75), rgba(13,25,48,.9));
            border: 1px solid var(--border); border-radius: 22px;
            padding: 46px 42px 38px; overflow: hidden;
            box-shadow: 0 24px 80px rgba(2,8,23,.6);
        }
        .err-card::before {
            content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .5;
            background-image:
                linear-gradient(rgba(125,211,252,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(125,211,252,.05) 1px, transparent 1px);
            background-size: 34px 34px;
            mask-image: radial-gradient(420px 200px at 50% 0%, #000, transparent 75%);
            -webkit-mask-image: radial-gradient(420px 200px at 50% 0%, #000, transparent 75%);
        }
        .err-brand { position: relative; display: inline-flex; align-items: center; gap: 10px; margin-bottom: 26px; }
        .err-brand img { height: 34px; width: auto; filter: drop-shadow(0 2px 8px rgba(56,189,248,.35)); }
        .err-brand span { font-size: 15px; font-weight: 800; letter-spacing: .02em; }
        .err-code {
            position: relative; font-family: 'JetBrains Mono', monospace;
            font-size: clamp(64px, 14vw, 104px); font-weight: 800; line-height: 1;
            letter-spacing: -.04em; margin-bottom: 14px;
            background: linear-gradient(135deg, var(--accent), #2563eb 70%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            filter: drop-shadow(0 6px 26px color-mix(in srgb, var(--accent) 35%, transparent));
        }
        .err-title { position: relative; font-size: clamp(18px, 3vw, 23px); font-weight: 800; margin-bottom: 10px; letter-spacing: -.01em; }
        .err-msg { position: relative; font-size: 14px; line-height: 1.65; color: var(--muted); max-width: 440px; margin: 0 auto 30px; }
        .err-msg b { color: var(--text); }
        .err-acts { position: relative; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .err-btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px;
            border-radius: 11px; font: inherit; font-size: 13.5px; font-weight: 700;
            text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: .15s;
        }
        .err-btn--primary { background: linear-gradient(135deg, #2563eb, #0ea5e9); color: #fff; }
        .err-btn--primary:hover { filter: brightness(1.12); }
        .err-btn--ghost { background: transparent; color: var(--text); border-color: var(--border); }
        .err-btn--ghost:hover { border-color: var(--accent); color: var(--accent); }
        .err-foot { position: relative; margin-top: 30px; padding-top: 18px; border-top: 1px solid var(--border); font-size: 11.5px; color: var(--muted); }
        .err-foot code { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: color-mix(in srgb, var(--accent) 80%, var(--text)); }
    </style>
</head>
<body>
    <div class="err-card" style="--accent: @yield('accent', '#38bdf8')">
        <div class="err-brand">
            <img src="{{ asset('branding/smartbrail-logo.png') }}" alt="{{ \App\Support\Branding::appName() }}">
            <span>{{ \App\Support\Branding::appName() }}</span>
        </div>
        <div class="err-code">@yield('code')</div>
        <div class="err-title">@yield('title')</div>
        <p class="err-msg">@yield('message')</p>
        <div class="err-acts">
            @yield('actions')
        </div>
        <div class="err-foot">
            Kode <code>@yield('code')</code> · {{ \App\Support\Branding::appName() }} — bila masalah berulang, laporkan ke administrator lab.
        </div>
    </div>
</body>
</html>
