<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <script>
    (function () {
        try {
            var saved = localStorage.getItem('dashboard_theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
    </script>
    <style>
        html { background: #f8fafc; }
        html[data-theme="dark"] { background: #0b1322; color-scheme: dark; }
        html[data-theme="dark"] body { background: #0b1322; }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AIoT SIMALAS - @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy: #0a1f44;
            --navy-light: #1f3f74;
            --soft-blue: #4da6ff;
            --accent-teal: #00b5d8;
            --accent-purple: #7c6cff;
            --accent-amber: #ff7a00;
            --accent-rose: #f43f5e;
            --bg-dark: #0f1a2f;
            --bg-sidebar: #dbeafe;
            --sidebar-ink: #0f172a;
            --sidebar-ink-muted: #475569;
            --sidebar-hover-bg: rgba(14, 165, 233, 0.12);
            --sidebar-active-bg: rgba(14, 165, 233, 0.2);
            --sidebar-active-ink: #0369a1;
            --sidebar-border: rgba(14, 165, 233, 0.22);
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-white: #f8fafc;
            --text-muted: #8aa0bc;
            --text-dark: #1b2d48;
            --text-label: #516987;
            --border-subtle: #dce5ef;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-sm: 8px;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 88px;
            --topbar-height: 84px;
        }
        [data-theme="dark"] {
            --bg-dark: #0b1322;
            --bg-sidebar: #0f1d35;
            --sidebar-ink: #f1f5f9;
            --sidebar-ink-muted: #94a3b8;
            --sidebar-hover-bg: rgba(255, 255, 255, 0.06);
            --sidebar-active-bg: linear-gradient(135deg, rgba(74, 144, 217, 0.15), rgba(45, 212, 191, 0.1));
            --sidebar-active-ink: #5eead4;
            --sidebar-border: rgba(255, 255, 255, 0.06);
            --bg-main: #171f2f;
            --bg-card: #1b2538;
            --text-white: #f1f5f9;
            --text-muted: #7f92b0;
            --text-dark: #e7edf5;
            --text-label: #9fb0c8;
            --border-subtle: #2c3d57;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.2);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
        }
        html, body {
            height: 100%;
            font-family: var(--font);
            background: var(--bg-main);
            color: var(--text-dark);
            overflow: hidden;
        }

        /* ── Layout ── */
        .dashboard-layout {
            display: flex;
            align-items: stretch;
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }

        /* ── Sidebar ── */
        .sidebar-shell {
            position: relative;
            flex-shrink: 0;
            z-index: 100;
            align-self: stretch;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            height: 100%;
            min-height: 100vh;
            flex: 1 1 auto;
            background: var(--bg-sidebar);
            color: var(--sidebar-ink);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            transition: var(--transition);
        }
        .sidebar-edge-toggle {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translate(50%, -50%);
            z-index: 120;
            width: 30px;
            height: 52px;
            padding: 0;
            border-radius: 999px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
            color: var(--text-label);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.12);
            transition: var(--transition);
        }
        .sidebar-edge-toggle:hover {
            background: var(--bg-main);
            border-color: var(--soft-blue);
            color: var(--soft-blue);
        }
        .sidebar-edge-toggle .icon-wrap svg {
            width: 18px;
            height: 18px;
        }
        .dashboard-layout.sidebar-collapsed .sidebar {
            width: var(--sidebar-width-collapsed);
        }
        .sidebar-header {
            height: var(--topbar-height);
            padding: 0 16px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0;
            box-sizing: border-box;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--sidebar-ink);
            min-width: 0;
            width: 100%;
        }
        .sidebar-brand-icon {
            width: 46px;
            height: 46px;
            min-width: 46px;
            border-radius: 12px;
            background: #ffffff;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            flex-shrink: 0;
            box-sizing: border-box;
        }
        .sidebar-brand-icon img,
        .sidebar-brand-icon video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            filter: none;
        }
        /* Mode gelap: pelat gelap agar mark merah kontras, bukan kotak putih */
        [data-theme="dark"] .sidebar-brand-icon {
            background: #0b1220;
            border-color: rgba(255, 255, 255, 0.14);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
        }
        .sidebar-brand-text {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.5px;
            line-height: 1.15;
        }
        .sidebar-brand-sub {
            font-size: 11px;
            color: var(--sidebar-ink-muted);
            font-weight: 400;
            margin-top: 2px;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-header {
            padding: 0 10px;
            justify-content: center;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-brand {
            justify-content: center;
            width: auto;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-brand-text,
        .dashboard-layout.sidebar-collapsed .sidebar-brand-sub {
            display: none;
        }

        /* ── Sidebar Navigation ── */
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
            scrollbar-width: thin;
            scrollbar-color: rgba(148,163,184,0.36) transparent;
        }
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(148,163,184,0.28);
            border-radius: 999px;
        }
        .sidebar-nav:hover::-webkit-scrollbar-thumb {
            background: rgba(148,163,184,0.48);
        }
        .sidebar-section {
            margin-bottom: 24px;
        }
        .sidebar-section-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 0 12px;
            margin-bottom: 8px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--sidebar-ink-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            margin-bottom: 2px;
        }
        .sidebar-link:hover {
            background: var(--sidebar-hover-bg);
            color: var(--sidebar-ink);
        }
        .sidebar-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-ink);
            font-weight: 700;
        }
        .sidebar-link-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-section-title,
        .dashboard-layout.sidebar-collapsed .sidebar-link-badge {
            display: none;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-link {
            justify-content: center;
            padding: 10px 8px;
            gap: 0;
            font-size: 0;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-link-icon {
            font-size: 20px;
            width: auto;
        }
        .sidebar-link-badge {
            margin-left: auto;
            background: var(--accent-rose);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
        }
        .sidebar-group {
            margin-bottom: 8px;
        }
        .sidebar-group-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 0;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.03);
            color: var(--text-muted);
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            text-align: left;
            cursor: pointer;
            transition: var(--transition);
        }
        .sidebar-group-toggle:hover,
        .sidebar-group.open > .sidebar-group-toggle {
            background: var(--sidebar-hover-bg);
            color: var(--sidebar-ink);
        }
        .sidebar-group-label {
            flex: 1;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .sidebar-group-caret {
            font-size: 12px;
            transition: transform 0.2s ease;
        }
        .sidebar-group.open .sidebar-group-caret {
            transform: rotate(90deg);
        }
        .sidebar-submenu {
            display: none;
            margin: 6px 0 10px 10px;
            padding-left: 8px;
            border-left: 1px solid rgba(148,163,184,0.18);
        }
        .sidebar-group.open .sidebar-submenu {
            display: block;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-group-label,
        .dashboard-layout.sidebar-collapsed .sidebar-group-caret,
        .dashboard-layout.sidebar-collapsed .sidebar-submenu {
            display: none;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-group-toggle {
            justify-content: center;
            padding: 10px 8px;
        }

        /* ── Sidebar Footer ── */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--sidebar-border);
            margin-top: auto;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-logout-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid rgba(244, 63, 94, 0.28);
            background: rgba(244, 63, 94, 0.1);
            color: #fb7185;
            font-family: var(--font);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }
        .sidebar-logout-btn:hover {
            background: rgba(244, 63, 94, 0.18);
            border-color: rgba(244, 63, 94, 0.45);
            color: #fda4af;
        }
        .sidebar-logout-btn [data-lucide],
        .sidebar-logout-btn svg {
            width: 15px;
            height: 15px;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-logout-btn span {
            display: none;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-logout-btn {
            padding: 9px;
        }
        .sidebar-user {
            width: 100%;
            border: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.55);
            cursor: pointer;
            text-align: left;
            transition: var(--transition);
        }
        .sidebar-user:hover {
            background: var(--sidebar-hover-bg);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .sidebar-user {
            background: rgba(255, 255, 255, 0.04);
        }
        .sidebar-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--soft-blue), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            overflow: hidden;
        }
        .sidebar-avatar img,
        .profile-popover-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }
        .sidebar-user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--sidebar-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user-role {
            font-size: 11px;
            color: var(--sidebar-ink-muted);
        }
        .sidebar-copy {
            margin-top: 10px;
            text-align: center;
            font-size: 10px;
            color: var(--sidebar-ink-muted);
            opacity: 0.9;
        }
        .profile-popover-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2400;
            display: none;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(5px);
            pointer-events: none;
        }
        .profile-popover-backdrop.is-open {
            display: block;
            pointer-events: auto;
        }
        .profile-popover {
            position: fixed;
            left: 18px;
            bottom: 88px;
            width: min(360px, calc(100vw - 36px));
            border: 1px solid rgba(148,163,184,0.28);
            border-radius: 22px;
            background: var(--bg-card);
            color: var(--text-dark);
            box-shadow: 0 24px 70px rgba(15,23,42,0.38);
            overflow: hidden;
        }
        .profile-popover-cover {
            height: 86px;
            background: radial-gradient(circle at 20% 20%, rgba(45,212,191,0.32), transparent 36%),
                linear-gradient(135deg, var(--navy), var(--soft-blue), var(--accent-teal));
        }
        .profile-popover-body {
            padding: 0 18px 18px;
        }
        .profile-popover-avatar {
            width: 72px;
            height: 72px;
            margin-top: -36px;
            border-radius: 20px;
            border: 4px solid var(--bg-card);
            background: linear-gradient(135deg, var(--soft-blue), var(--accent-purple));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 900;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .profile-popover-name {
            margin-top: 10px;
            font-size: 18px;
            font-weight: 800;
        }
        .profile-popover-role {
            margin-top: 3px;
            color: var(--text-muted);
            font-size: 12px;
        }
        .profile-popover-meta {
            display: grid;
            gap: 8px;
            margin: 16px 0;
        }
        .profile-popover-meta div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 10px;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            background: var(--bg-main);
            font-size: 12px;
        }
        .profile-popover-meta span {
            color: var(--text-muted);
            font-weight: 700;
        }
        .profile-popover-meta strong {
            text-align: right;
            word-break: break-word;
        }
        .profile-popover-actions {
            display: flex;
            gap: 10px;
        }
        .profile-popover-hint {
            margin: 0 0 14px;
            font-size: 11.5px;
            line-height: 1.4;
            color: var(--text-muted);
            text-align: center;
        }
        .profile-popover-link,
        .profile-popover-close {
            flex: 1;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid var(--border-subtle);
            cursor: pointer;
        }
        .profile-popover-link {
            background: linear-gradient(135deg, var(--navy-light), var(--accent-teal));
            color: #fff;
            border: 0;
        }
        .profile-popover-close {
            background: var(--bg-main);
            color: var(--text-dark);
        }

        /* ── Popup foto profil ── */
        button.photo-zoom {
            padding: 0;
            font-family: inherit;
            cursor: zoom-in;
        }
        .photo-lightbox {
            position: fixed;
            inset: 0;
            z-index: 4200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(4, 10, 20, 0.86);
            backdrop-filter: blur(8px);
        }
        .photo-lightbox.is-open {
            display: flex;
            animation: photoLightboxFade 0.18s ease;
        }
        .photo-lightbox__box {
            position: relative;
            width: 100%;
            max-width: min(660px, 92vw);
            animation: photoLightboxPop 0.22s cubic-bezier(0.34, 1.4, 0.64, 1);
        }
        @keyframes photoLightboxFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes photoLightboxPop {
            from { opacity: 0; transform: scale(0.94); }
            to { opacity: 1; transform: scale(1); }
        }
        .photo-lightbox__img {
            display: block;
            width: 100%;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 18px;
            background: #0b1322;
            border: 1px solid rgba(255,255,255,0.14);
            box-shadow: 0 24px 70px rgba(0,0,0,0.55);
        }
        .photo-lightbox__cap {
            margin-top: 14px;
            text-align: center;
            color: #e2e8f0;
        }
        .photo-lightbox__cap strong {
            display: block;
            font-size: 15px;
            font-weight: 800;
        }
        .photo-lightbox__cap span {
            font-size: 12px;
            color: rgba(226,232,240,0.7);
        }
        .photo-lightbox__close {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.26);
            background: rgba(15,23,42,0.92);
            color: #fff;
            cursor: pointer;
        }
        .photo-lightbox__close:hover {
            background: #e11d48;
            border-color: #e11d48;
        }
        .photo-lightbox__close i {
            width: 19px;
            height: 19px;
        }
        @media (max-width: 720px) {
            .photo-lightbox__close {
                top: 8px;
                right: 8px;
            }
        }

        .dashboard-layout.sidebar-collapsed .sidebar-user-info,
        .dashboard-layout.sidebar-collapsed .sidebar-copy {
            display: none;
        }
        .dashboard-layout.sidebar-collapsed .sidebar-user {
            justify-content: center;
            padding: 8px;
        }

        /* ── Main Content Area ── */
        .main-area {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
            min-width: 0;
            width: 100%;
        }

        /* ── Top Bar ── */
        .topbar {
            height: var(--topbar-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 0 36px;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
            box-sizing: border-box;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 0;
            flex: 1 1 auto;
        }
        .topbar-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }
        .topbar-subtitle {
            font-size: 13px;
            color: var(--text-label);
            font-weight: 400;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        /* Menu search (HP-style dropdown) */
        .topbar-search {
            position: relative;
            flex: 1 1 560px;
            max-width: 720px;
            min-width: 260px;
        }
        .topbar-search-field {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 42px;
            padding: 0 14px;
            border-radius: 12px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-main);
            transition: var(--transition);
        }
        .topbar-search-field:focus-within {
            border-color: var(--soft-blue);
            background: var(--bg-card);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
        }
        .topbar-search-field .icon-wrap,
        .topbar-search-field [data-lucide] {
            color: var(--text-label);
            flex-shrink: 0;
            width: 16px;
            height: 16px;
        }
        .topbar-search-input {
            flex: 1;
            min-width: 0;
            border: 0;
            outline: none;
            background: transparent;
            font-family: var(--font);
            font-size: 13.5px;
            color: var(--text-dark);
        }
        .topbar-search-input::placeholder {
            color: var(--text-label);
        }
        .topbar-search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            max-height: 320px;
            overflow-y: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            z-index: 220;
            display: none;
            padding: 6px;
        }
        .topbar-search-dropdown.show {
            display: block;
        }
        .topbar-search-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-dark);
            transition: background 0.15s ease;
        }
        .topbar-search-item:hover,
        .topbar-search-item.is-active {
            background: var(--bg-main);
        }
        .topbar-search-item-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(14, 165, 233, 0.1);
            color: var(--soft-blue);
            flex-shrink: 0;
        }
        .topbar-search-item-icon [data-lucide],
        .topbar-search-item-icon svg {
            width: 15px;
            height: 15px;
        }
        .topbar-search-item-text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .topbar-search-item-label {
            font-size: 13px;
            font-weight: 650;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-search-item-group {
            font-size: 11px;
            color: var(--text-label);
        }
        .topbar-search-empty {
            padding: 16px 12px;
            text-align: center;
            font-size: 13px;
            color: var(--text-label);
        }

        /* Theme switch: kiri terang, kanan gelap */
        .theme-switch {
            position: relative;
            width: 64px;
            height: 34px;
            padding: 0;
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            background: var(--bg-main);
            cursor: pointer;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            transition: var(--transition);
        }
        .theme-switch:hover {
            border-color: var(--soft-blue);
        }
        .theme-switch-track {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 8px;
            pointer-events: none;
        }
        .theme-switch-track [data-lucide],
        .theme-switch-track svg {
            width: 14px;
            height: 14px;
            color: var(--text-label);
            opacity: 0.7;
        }
        .theme-switch-thumb {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        .theme-switch-thumb [data-lucide],
        .theme-switch-thumb svg {
            width: 13px;
            height: 13px;
            color: #0f172a;
        }
        .theme-switch.is-dark .theme-switch-thumb {
            transform: translateX(30px);
            background: #1e293b;
        }
        .theme-switch.is-dark .theme-switch-thumb [data-lucide],
        .theme-switch.is-dark .theme-switch-thumb svg {
            color: #fbbf24;
        }
        [data-theme="dark"] .theme-switch {
            background: #1e293b;
            border-color: rgba(148, 163, 184, 0.25);
        }
        [data-theme="dark"] .theme-switch-thumb {
            background: #0f172a;
        }

        .topbar-btn {
            width: 42px;
            min-width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 12px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            cursor: pointer;
            transition: var(--transition);
            font-size: 13px;
            font-weight: 700;
            color: var(--text-label);
            position: relative;
            white-space: nowrap;
            line-height: 1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
        }
        .topbar-btn:hover {
            background: var(--bg-main);
            border-color: var(--soft-blue);
            color: var(--soft-blue);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(74, 144, 217, 0.12);
        }
        .topbar-btn:active {
            transform: translateY(0);
        }
        .topbar-btn .notif-dot {
            position: absolute;
            top: -3px;
            right: -3px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            background: linear-gradient(135deg, #e11d48, #f43f5e);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            line-height: 18px;
            text-align: center;
            border-radius: 999px;
            border: 2px solid var(--bg-card);
            box-shadow: 0 4px 10px rgba(244, 63, 94, 0.35);
            animation: none;
        }
        @keyframes notifPulse {
            0%   { box-shadow: 0 0 0 0 rgba(244,63,94,0.55); }
            70%  { box-shadow: 0 0 0 8px rgba(244,63,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(244,63,94,0); }
        }
        .notif-bell-pulse {
            animation: bellShake 0.55s ease-in-out 2;
        }
        @keyframes bellShake {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(-12deg); }
            40% { transform: rotate(12deg); }
            60% { transform: rotate(-8deg); }
            80% { transform: rotate(8deg); }
        }
        .notif-panel {
            position: absolute;
            top: 52px;
            right: 0;
            width: 380px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.16);
            z-index: 200;
            display: none;
            overflow: hidden;
        }
        .notif-panel.show {
            display: block;
        }
        .notif-panel-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text-dark);
            font-size: 13px;
        }
        .notif-clear-btn {
            border: 0;
            background: transparent;
            color: var(--soft-blue);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .notif-clear-btn:disabled { opacity: 0.45; cursor: not-allowed; pointer-events: none; }
        .notif-clear-btn:not(:disabled) { pointer-events: auto; }
        .notif-ops-link:hover { border-color: rgba(99, 102, 241, 0.45); }
        .notif-panel-body {
            max-height: 360px;
            overflow-y: auto;
            padding: 10px 12px;
        }
        .notif-item {
            position: relative;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            background: var(--bg-main);
            border: 1px solid var(--border-subtle);
            margin-bottom: 8px;
            transition: var(--transition);
        }
        .notif-item:last-child { margin-bottom: 0; }
        .notif-item.is-unread {
            background: linear-gradient(180deg, rgba(74,144,217,0.10), rgba(45,212,191,0.06));
            border-color: rgba(74,144,217,0.35);
        }
        .notif-item.is-unread::before {
            content: '';
            position: absolute;
            left: -1px; top: -1px; bottom: -1px;
            width: 3px;
            background: linear-gradient(180deg, var(--soft-blue), var(--accent-teal));
            border-radius: 4px 0 0 4px;
        }
        .notif-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 2px;
        }
        .notif-item-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.3;
        }
        .notif-item-badge {
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 2px 7px;
            border-radius: 999px;
            flex-shrink: 0;
        }
        .notif-item-badge.prio-normal  { background: rgba(74,144,217,0.12); color: #2563eb; }
        .notif-item-badge.prio-penting { background: rgba(245,158,11,0.14); color: #b45309; }
        .notif-item-badge.prio-urgent  { background: rgba(244,63,94,0.14);  color: #be123c; }
        .notif-item-body {
            font-size: 11.5px;
            color: var(--text-label);
            line-height: 1.45;
            margin: 4px 0 6px;
        }
        .notif-item-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 10.5px;
            color: var(--text-muted);
        }
        .notif-empty {
            font-size: 12px;
            color: var(--text-muted);
            padding: 14px 8px;
            text-align: center;
        }
        .notif-panel-footer {
            padding: 10px 14px;
            border-top: 1px solid var(--border-subtle);
            text-align: center;
        }
        .notif-panel-footer a {
            font-size: 12px;
            font-weight: 600;
            color: var(--soft-blue);
            text-decoration: none;
        }
        .notif-panel-footer a:hover { text-decoration: underline; }
        .sidebar-link-badge.pulsing {
            animation: notifPulse 2s ease-out infinite;
        }
        .access-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.62);
            backdrop-filter: blur(6px);
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .access-modal-backdrop.show {
            display: flex;
        }
        .access-modal {
            width: min(980px, 100%);
            max-height: min(82vh, 760px);
            overflow-y: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 22px;
            box-shadow: 0 24px 80px rgba(15,23,42,0.38);
        }
        .access-modal-hero {
            padding: 24px 26px;
            color: #fff;
            background: linear-gradient(135deg, #1e3a5f 0%, #4a90d9 48%, #8b5cf6 100%);
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }
        .access-modal-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .access-modal-subtitle {
            font-size: 13px;
            opacity: 0.86;
            max-width: 680px;
            line-height: 1.55;
        }
        .access-modal-close {
            width: 36px;
            height: 36px;
            border: 1px solid rgba(255,255,255,0.26);
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            cursor: pointer;
            font-size: 18px;
            flex-shrink: 0;
        }
        .access-user-strip {
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
        }
        .access-user-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.18);
            border: 2px solid rgba(255,255,255,0.42);
            font-size: 18px;
            font-weight: 800;
        }
        .access-user-name {
            font-weight: 800;
            font-size: 15px;
        }
        .access-user-meta {
            margin-top: 2px;
            font-size: 12px;
            opacity: 0.86;
        }
        .access-modal-body {
            padding: 22px;
        }
        .access-modal-footer {
            padding: 14px 22px 18px;
            border-top: 1px solid var(--border-subtle);
            background: rgba(74,144,217,0.05);
        }
        .access-modal-footer-link {
            font-size: 13px;
            font-weight: 800;
            color: var(--soft-blue);
            text-decoration: none;
        }
        .access-modal-footer-link:hover { text-decoration: underline; }
        .access-modal-footer-hint {
            margin: 6px 0 0;
            font-size: 11px;
            line-height: 1.45;
            color: var(--text-muted);
        }
        .access-role-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .access-role-card {
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            background: var(--bg-card);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            position: relative;
        }
        .access-role-card.active {
            border-color: rgba(74,144,217,0.7);
            box-shadow: 0 12px 36px rgba(74,144,217,0.2);
        }
        .access-role-top {
            padding: 18px;
            border-top: 4px solid var(--soft-blue);
        }
        .access-role-card.accent-green .access-role-top { border-top-color: #22c55e; }
        .access-role-card.accent-amber .access-role-top { border-top-color: var(--accent-amber); }
        .access-role-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(74,144,217,0.12);
            margin-bottom: 12px;
        }
        .access-role-icon svg {
            width: 22px;
            height: 22px;
        }
        .sidebar-toggle-btn .icon-wrap svg {
            width: 20px;
            height: 20px;
        }
        .access-role-card.accent-green .access-role-icon { background: rgba(34,197,94,0.12); }
        .access-role-card.accent-amber .access-role-icon { background: rgba(245,158,11,0.14); }
        .access-role-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-dark);
        }
        .access-role-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            line-height: 1.45;
        }
        .access-current-badge {
            display: inline-flex;
            margin-top: 10px;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(45,212,191,0.13);
            color: var(--accent-teal);
            font-size: 11px;
            font-weight: 800;
        }
        .access-role-content {
            padding: 0 18px 18px;
        }
        .access-role-summary {
            font-size: 12.5px;
            color: var(--text-label);
            line-height: 1.55;
            margin-bottom: 12px;
        }
        .access-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0 0 12px;
            padding: 0;
        }
        .access-list li {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            font-size: 12px;
            color: var(--text-dark);
            line-height: 1.45;
        }
        .access-list .access-check { color: #16a34a; font-weight: 800; }
        .access-list .access-cross { color: var(--accent-rose); font-weight: 800; }
        .access-active-menus {
            padding-top: 10px;
            border-top: 1px solid var(--border-subtle);
        }
        .access-active-title {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .access-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .access-chip {
            padding: 4px 8px;
            border-radius: 999px;
            background: var(--bg-main);
            border: 1px solid var(--border-subtle);
            font-size: 11px;
            color: var(--text-label);
        }
        @media (max-width: 960px) {
            .access-role-grid { grid-template-columns: 1fr; }
            .access-modal-hero { flex-direction: column; }
        }
        .btn-logout-top {
            display: none;
        }
        .profile-menu-wrap {
            position: relative;
        }
        .profile-menu-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px 4px 4px;
            border-radius: 999px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font);
            color: var(--text-dark);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            max-width: none;
        }
        .profile-menu-toggle:hover,
        .profile-menu-wrap.open .profile-menu-toggle {
            border-color: var(--soft-blue);
            background: var(--bg-main);
        }
        .profile-menu-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9, #6366f1);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            overflow: hidden;
            flex-shrink: 0;
        }
        .profile-menu-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .profile-menu-meta {
            display: none !important;
        }
        .profile-menu-name,
        .profile-menu-role {
            display: none;
        }
        .profile-menu-caret {
            color: var(--text-label);
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }
        .profile-menu-caret [data-lucide],
        .profile-menu-caret svg {
            width: 14px;
            height: 14px;
        }
        .profile-menu-wrap.open .profile-menu-caret {
            transform: rotate(180deg);
        }
        .profile-menu-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 220px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            z-index: 230;
            display: none;
            padding: 6px;
            overflow: hidden;
        }
        .profile-menu-wrap.open .profile-menu-dropdown {
            display: block;
        }
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--text-dark);
            text-decoration: none;
            font-family: var(--font);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: background 0.15s ease;
        }
        .profile-menu-item:hover {
            background: var(--bg-main);
        }
        .profile-menu-item [data-lucide],
        .profile-menu-item svg {
            width: 15px;
            height: 15px;
            color: var(--text-label);
            flex-shrink: 0;
        }
        .profile-menu-item.is-danger {
            color: #e11d48;
        }
        .profile-menu-item.is-danger [data-lucide],
        .profile-menu-item.is-danger svg {
            color: #e11d48;
        }
        .profile-menu-divider {
            height: 1px;
            margin: 4px 8px;
            background: var(--border-subtle);
        }

        /* ── Main Content ── */
        .main-content {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 32px;
            -webkit-overflow-scrolling: touch;
        }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-subtle);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--soft-blue), var(--accent-teal)); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--accent-purple), var(--soft-blue)); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--accent-amber), var(--accent-rose)); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, var(--accent-teal), #34d399); }
        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .stat-card-title {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-label);
        }
        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .stat-card:nth-child(1) .stat-card-icon { background: rgba(74,144,217,0.1); }
        .stat-card:nth-child(2) .stat-card-icon { background: rgba(139,92,246,0.1); }
        .stat-card:nth-child(3) .stat-card-icon { background: rgba(245,158,11,0.1); }
        .stat-card:nth-child(4) .stat-card-icon { background: rgba(45,212,191,0.1); }
        .stat-card-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-card-change {
            font-size: 12px;
            color: #22c55e;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .stat-card-change.down { color: var(--accent-rose); }

        /* ── Content Grid ── */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
        }

        /* ── Card ── */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-subtle);
            overflow: hidden;
        }
        .card-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-dark);
        }
        .card-body {
            padding: 22px;
        }

        /* ── Activity List ── */
        .activity-list {
            list-style: none;
        }
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-subtle);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 5px;
            flex-shrink: 0;
        }
        .activity-dot.blue { background: var(--soft-blue); }
        .activity-dot.green { background: var(--accent-teal); }
        .activity-dot.amber { background: var(--accent-amber); }
        .activity-dot.rose { background: var(--accent-rose); }
        .activity-text {
            font-size: 13px;
            color: var(--text-dark);
            line-height: 1.5;
        }
        .activity-time {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── Quick Actions ── */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-dark);
            font-family: var(--font);
        }
        .quick-action-btn:hover {
            background: var(--bg-main);
            border-color: var(--soft-blue);
            transform: translateX(4px);
        }
        .quick-action-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .quick-action-icon .icon-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .quick-action-icon .icon-wrap svg {
            width: 20px;
            height: 20px;
        }
        .quick-action-text {
            font-size: 13px;
            font-weight: 600;
        }
        .quick-action-sub {
            font-size: 11px;
            color: var(--text-muted);
        }
        .quick-actions--scroll {
            max-height: 480px;
            overflow-y: auto;
            padding-right: 4px;
        }

        /* ── Welcome Banner ── */
        .welcome-banner {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 50%, var(--soft-blue) 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            color: #fff;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -40%;
            right: 10%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .welcome-banner h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            position: relative;
        }
        .welcome-banner p {
            font-size: 14px;
            opacity: 0.85;
            position: relative;
        }
        .welcome-banner .welcome-date {
            font-size: 12px;
            opacity: 0.6;
            margin-top: 8px;
            position: relative;
        }

        /* ── Table ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .data-table td {
            padding: 12px 14px;
            font-size: 13px;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border-subtle);
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(74, 144, 217, 0.06); }
        [data-theme="dark"] .data-table tr:hover td { background: rgba(148, 163, 184, 0.1); }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.success { background: rgba(34,197,94,0.1); color: #16a34a; }
        .status-badge.warning { background: rgba(245,158,11,0.1); color: #d97706; }
        .status-badge.danger { background: rgba(244,63,94,0.1); color: #e11d48; }

        .simalas-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-top: 1px solid var(--border-subtle);
        }
        .simalas-pagination-info {
            font-size: 12px;
            color: var(--text-muted);
        }
        .simalas-pagination-links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .simalas-pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 34px;
            padding: 0 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-label);
            background: var(--bg-main);
            border: 1px solid var(--border-subtle);
            transition: var(--transition);
        }
        a.simalas-pagination-btn:hover {
            border-color: var(--soft-blue);
            color: var(--soft-blue);
        }
        .simalas-pagination-btn.is-active {
            background: linear-gradient(135deg, #2d5a8e, #4a90d9);
            color: #fff;
            border-color: transparent;
        }
        .simalas-pagination-btn.is-disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .topbar { padding: 0 16px; gap: 12px; height: 72px; --topbar-height: 72px; }
            .topbar-search { max-width: none; flex: 1 1 auto; min-width: 0; }
            .topbar-left > div:first-child { display: none; }
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .theme-switch { width: 56px; height: 32px; }
            .theme-switch.is-dark .theme-switch-thumb { transform: translateX(24px); }
            .theme-switch-thumb { width: 24px; height: 24px; }
        }

        /* ── Animations ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeInUp 0.4s ease-out forwards;
        }
        .animate-in:nth-child(1) { animation-delay: 0s; }
        .animate-in:nth-child(2) { animation-delay: 0.05s; }
        .animate-in:nth-child(3) { animation-delay: 0.1s; }
        .animate-in:nth-child(4) { animation-delay: 0.15s; }

        /* ── Inline Action Buttons ── */
        .inline-action-form { display: inline; }
        .action-btn-sm {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .action-btn-activate {
            background: rgba(34,197,94,0.1);
        }
        .action-btn-activate:hover {
            background: rgba(34,197,94,0.25);
            transform: scale(1.1);
        }
        .action-btn-delete {
            background: rgba(244,63,94,0.1);
        }
        .action-btn-delete:hover {
            background: rgba(244,63,94,0.25);
            transform: scale(1.1);
        }

        /* ── Global Page Loader ── */
        #simalas-loader {
            position: fixed; inset: 0; z-index: 9999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: radial-gradient(circle at 50% 40%, rgba(74,144,217,0.18), rgba(15,23,42,0.96) 70%);
            backdrop-filter: blur(6px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        #simalas-loader.is-visible { opacity: 1; visibility: visible; pointer-events: auto; }
        #simalas-loader.is-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .simalas-loader-stage {
            position: relative; width: 120px; height: 110px;
            display: flex; align-items: center; justify-content: center;
        }
        .simalas-loader-ring {
            position: absolute; inset: 0; border-radius: 50%;
            border: 3px solid transparent;
            border-top-color: #4a90d9; border-right-color: rgba(74,144,217,0.25);
            animation: simalasSpin 0.9s linear infinite;
        }
        .simalas-loader-ring.alt {
            inset: 14px;
            border-top-color: #2dd4bf; border-right-color: rgba(45,212,191,0.18);
            animation: simalasSpin 1.4s linear infinite reverse;
        }
        .simalas-loader-core {
            width: auto; min-width: 76px; max-width: 102px; height: 76px; border-radius: 50%;
            padding: 0 8px;
            background: radial-gradient(circle at 50% 35%, rgba(30,58,95,0.72), rgba(15,23,42,0.2));
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 1px rgba(148,163,184,0.12), 0 12px 36px rgba(15,23,42,0.55);
            animation: simalasPulse 1.2s ease-in-out infinite;
        }
        .simalas-loader-core img {
            height: 64px; width: auto; max-width: 90px; object-fit: contain; display: block;
            animation: simalasLoaderMarkPulse 2.2s ease-in-out infinite;
            filter: drop-shadow(0 0 14px rgba(45,212,191,0.45));
        }
        @keyframes simalasLoaderMarkPulse { 0%, 100% { transform: scale(1); opacity: 0.88; } 50% { transform: scale(1.06); opacity: 1; } }
        .simalas-loader-text {
            margin-top: 22px; color: #e2e8f0; font-size: 12.5px; font-weight: 600;
            letter-spacing: 0.18em; text-transform: uppercase;
        }
        .simalas-loader-progress {
            margin-top: 14px; width: 220px; height: 4px; border-radius: 4px;
            background: rgba(148,163,184,0.18); overflow: hidden; position: relative;
        }
        .simalas-loader-progress::after {
            content: ''; position: absolute; left: -40%; top: 0; bottom: 0; width: 40%;
            background: linear-gradient(90deg, transparent, #4a90d9, #2dd4bf, transparent);
            animation: simalasShimmer 1.3s ease-in-out infinite;
        }
        @keyframes simalasSpin { to { transform: rotate(360deg); } }
        @keyframes simalasPulse { 0%,100% { transform: scale(1); box-shadow: 0 12px 36px rgba(74,144,217,0.55); } 50% { transform: scale(1.06); box-shadow: 0 14px 42px rgba(45,212,191,0.6); } }
        @keyframes simalasShimmer { 0% { left: -40%; } 100% { left: 100%; } }
        @media (prefers-reduced-motion: reduce) {
            .simalas-loader-ring, .simalas-loader-ring.alt { animation: none; }
            .simalas-loader-core { animation: none; }
            .simalas-loader-core img { animation: none; }
            .simalas-loader-progress::after { animation: none; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/dashboard-premium.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('css/panel-premium.css') }}?v=8">
    @yield('styles')
</head>
<body>
    @unless(request()->routeIs('admin.reports') || request()->routeIs('dosen.reports'))
        <div id="simalas-loader" class="is-hidden" role="status" aria-label="Memuat halaman">
            <div class="simalas-loader-stage">
                <div class="simalas-loader-ring"></div>
                <div class="simalas-loader-ring alt"></div>
                <div class="simalas-loader-core"><img src="{{ asset('branding/aiot-simalas-loading.png') }}?v=6" alt=""></div>
            </div>
            <div class="simalas-loader-text" id="simalas-loader-text">Memuat halaman</div>
            <div class="simalas-loader-progress" aria-hidden="true"></div>
        </div>
        <script>
            // Guard paling awal agar overlay tidak pernah mengunci halaman.
            (function () {
                var hideLoader = function () {
                    var loader = document.getElementById('simalas-loader');
                    if (loader) {
                        loader.classList.add('is-hidden');
                    loader.classList.remove('is-visible');
                        loader.style.opacity = '0';
                        loader.style.visibility = 'hidden';
                        loader.style.pointerEvents = 'none';
                    }
                };
                window.simalasHideLoader = hideLoader;
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function () { setTimeout(hideLoader, 120); }, { once: true });
                } else {
                    setTimeout(hideLoader, 120);
                }
                setTimeout(hideLoader, 1600);
                window.addEventListener('pageshow', hideLoader);
            })();
        </script>
    @endunless

    <div class="dashboard-layout">
        <div class="sidebar-shell">
        <!-- ══════ SIDEBAR ══════ -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="{{ Auth::user()->role === 'dosen' ? route('dosen.dashboard') : route('admin.dashboard') }}" class="sidebar-brand">
                    <div class="sidebar-brand-icon"><img src="{{ asset('branding/aiot-simalas-mark.png') }}?v=7" alt="AIoT SIMALAS"></div>
                    <div>
                        <div class="sidebar-brand-text">AIoT SIMALAS</div>
                        <div class="sidebar-brand-sub">Smart Lab System</div>
                    </div>
                </a>
            </div>

            <nav class="sidebar-nav">
                @yield('sidebar-nav')
            </nav>

            <div class="sidebar-footer">
                <button type="button" class="sidebar-user" id="sidebarProfileToggle" title="Lihat profil singkat">
                    <div class="sidebar-avatar">
                        @if(Auth::user()->profile_photo_url)
                            <img src="{{ Auth::user()->profile_photo_url }}" alt="Foto {{ Auth::user()->nama_lengkap }}">
                        @else
                            {{ strtoupper(substr(Auth::user()->nama_lengkap, 0, 1)) }}
                        @endif
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name">{{ Auth::user()->nama_lengkap }}</div>
                        <div class="sidebar-user-role">{{ Auth::user()->role_display }}</div>
                    </div>
                </button>
                <button type="button" class="sidebar-logout-btn" onclick="confirmLogout()" title="Logout">
                    <i data-lucide="log-out"></i>
                    <span>Logout</span>
                </button>
                <div class="sidebar-copy">by AIOT SIMALAS 2026</div>
            </div>
        </aside>
        <button type="button"
            class="sidebar-edge-toggle"
            id="sidebarToggle"
            title="Perkecil sidebar"
            aria-label="Perkecil sidebar"
            aria-expanded="true">
            @include('components.icon', ['name' => 'chevron-left'])
        </button>
        </div>

        <!-- ══════ MAIN AREA ══════ -->
        <div class="main-area">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <div>
                        <div class="topbar-title">@yield('page-title', 'Dashboard')</div>
                        <div class="topbar-subtitle">@yield('page-subtitle', '')</div>
                    </div>
                    <div class="topbar-search" id="topbarSearch">
                        <div class="topbar-search-field">
                            <i data-lucide="search" aria-hidden="true"></i>
                            <input
                                type="search"
                                class="topbar-search-input"
                                id="menuSearchInput"
                                placeholder="Cari menu..."
                                autocomplete="off"
                                aria-label="Cari menu"
                                aria-autocomplete="list"
                                aria-controls="menuSearchDropdown"
                            >
                        </div>
                        <div class="topbar-search-dropdown" id="menuSearchDropdown" role="listbox" aria-label="Hasil pencarian menu"></div>
                    </div>
                </div>
                <div class="topbar-right">
                    @php
                        $roleAccessCards = \App\Support\PermissionRegistry::roleAccessCards(optional(Auth::user())->role);
                        $roleAccessIcons = ['laboran' => 'shield', 'dosen' => 'graduation-cap', 'mahasiswa' => 'user-round'];
                        $activeRoleLabel = \App\Support\PermissionRegistry::roleLabels()[optional(Auth::user())->role] ?? Auth::user()->role_display;
                        $profileUrl = Auth::user()->role === 'dosen'
                            ? route('dosen.profile')
                            : route('profiles.show', Auth::user());
                    @endphp

                    {{-- Theme switch: kiri terang, kanan gelap --}}
                    <button
                        type="button"
                        class="theme-switch"
                        id="themeToggle"
                        role="switch"
                        aria-checked="false"
                        title="Mode terang / gelap"
                        aria-label="Mode terang atau gelap"
                    >
                        <span class="theme-switch-track" aria-hidden="true">
                            <i data-lucide="sun"></i>
                            <i data-lucide="moon"></i>
                        </span>
                        <span class="theme-switch-thumb" aria-hidden="true">
                            <i data-lucide="sun" id="themeToggleIcon"></i>
                        </span>
                    </button>

                    {{-- Role Access Panel --}}
                    <button class="topbar-btn" id="accessToggle" title="Akses Role SIMALAS" aria-label="Akses Role SIMALAS" type="button">
                        @include('components.icon', ['name' => 'shield'])
                    </button>
                    <div class="access-modal-backdrop" id="accessModal" role="dialog" aria-modal="true" aria-label="Akses Role SIMALAS">
                        <div class="access-modal">
                            <div class="access-modal-hero">
                                <div>
                                    <div class="access-modal-title">Akses Anda di IoT SIMALAS</div>
                                    <div class="access-modal-subtitle">
                                        Ringkasan peran di SIMALAS. Pembatasan menu web berlaku untuk <strong>admin laboran</strong> dan
                                        <strong>dosen</strong>. Mahasiswa memakai <strong>aplikasi mobile</strong> — bukan dashboard web penuh.
                                    </div>
                                    <div class="access-user-strip">
                                        <div class="access-user-avatar">{{ strtoupper(substr(Auth::user()->nama_lengkap, 0, 2)) }}</div>
                                        <div>
                                            <div class="access-user-name">{{ Auth::user()->nama_lengkap }}</div>
                                            <div class="access-user-meta">
                                                {{ Auth::user()->email }} · {{ $activeRoleLabel }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="access-modal-close" id="accessClose" aria-label="Tutup panel akses">×</button>
                            </div>
                            <div class="access-modal-body">
                                <div class="access-role-grid">
                                    @foreach($roleAccessCards as $roleKey => $card)
                                        <div class="access-role-card accent-{{ $card['accent'] }} {{ $card['is_active'] ? 'active' : '' }}">
                                            <div class="access-role-top">
                                                <div class="access-role-icon"><i data-lucide="{{ $roleAccessIcons[$roleKey] ?? 'key' }}"></i></div>
                                                <div class="access-role-title">{{ $card['title'] }}</div>
                                                <div class="access-role-subtitle">{{ $card['subtitle'] }}</div>
                                                @if($card['is_active'])
                                                    <span class="access-current-badge">Role aktif Anda</span>
                                                @endif
                                            </div>
                                            <div class="access-role-content">
                                                <p class="access-role-summary">{{ $card['summary'] }}</p>
                                                <ul class="access-list">
                                                    @foreach($card['benefits'] as $benefit)
                                                        <li><span class="access-check">✓</span><span>{{ $benefit }}</span></li>
                                                    @endforeach
                                                </ul>
                                                @if(!empty($card['limitations']))
                                                    <ul class="access-list">
                                                        @foreach($card['limitations'] as $limit)
                                                            <li><span class="access-cross">×</span><span>{{ $limit }}</span></li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                <div class="access-active-menus">
                                                    <div class="access-active-title">
                                                        {{ $roleKey === 'mahasiswa' ? 'Fitur aplikasi mobile' : 'Menu web aktif (dosen / admin)' }}
                                                    </div>
                                                    <div class="access-chip-row">
                                                        @php
                                                            $chips = $roleKey === 'mahasiswa'
                                                                ? ['Beranda', 'Absensi', 'Loker & ambil barang tim', 'Pengumuman', 'Profil']
                                                                : array_values($card['active_menus']);
                                                        @endphp
                                                        @forelse($chips as $label)
                                                            <span class="access-chip">{{ $label }}</span>
                                                        @empty
                                                            <span class="access-chip">Tidak ada menu web aktif</span>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if(optional(Auth::user())->role === 'laboran' && \App\Support\PermissionRegistry::canCurrent('role_access'))
                                    <div class="access-modal-footer">
                                        <a href="{{ route('admin.role.index') }}" class="access-modal-footer-link">
                                            Konfigurasi menu web dosen (opsional) →
                                        </a>
                                        <p class="access-modal-footer-hint">
                                            Hanya memengaruhi sidebar dashboard dosen. Tidak mengubah aplikasi mobile mahasiswa.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Notification Bell --}}
                    @php
                        $notifAnnouncements = $notifAnnouncements ?? collect();
                        $notifUnreadCount   = $notifUnreadCount ?? 0;
                        $notifOpsCount      = $notifOpsCount ?? 0;
                        $notifOpsItems      = $notifOpsItems ?? collect();
                        $notifTotalCount    = $notifTotalCount ?? ($notifUnreadCount + $notifOpsCount);
                        $hasOpsSuggest      = $notifOpsItems->contains(fn ($it) => ($it['id'] ?? '') === 'ops-suggest');
                        $userRole           = optional(Auth::user())->role;
                        $announcementListRoute = $userRole === 'laboran'
                            ? route('admin.announcements.index')
                            : ($userRole === 'dosen' ? route('dosen.announcements') : '#');
                    @endphp
                    <div class="notif-wrapper" style="position:relative;">
                        <button class="topbar-btn" id="notifToggle" title="Notifikasi" aria-label="Notifikasi" type="button">
                            @include('components.icon', ['name' => 'bell'])
                            <span class="notif-dot" id="notifDot" style="{{ $notifTotalCount > 0 ? '' : 'display:none;' }}">{{ $notifTotalCount > 99 ? '99+' : $notifTotalCount }}</span>
                        </button>
                        <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifikasi">
                            <div class="notif-panel-header notif-ops-header" style="border-bottom:1px solid var(--border-subtle);{{ $notifOpsItems->isNotEmpty() ? '' : 'display:none;' }}">
                                <strong>Antrian operasional</strong>
                                @if($notifOpsCount > 0)
                                    <span class="notif-ops-count" style="font-size:11px;color:var(--text-muted);font-weight:500;">{{ $notifOpsCount }} item</span>
                                @endif
                            </div>
                            <div class="notif-panel-body" id="notifOpsList" style="max-height:180px;{{ $notifOpsItems->isNotEmpty() ? '' : 'display:none;' }}">
                                @foreach($notifOpsItems as $ops)
                                    <a href="{{ $ops['url'] }}" class="notif-item notif-ops-link" data-ops-id="{{ $ops['id'] }}" data-ops-fp="{{ $ops['fingerprint'] ?? ($ops['id'].':'.($ops['count'] ?? 0)) }}" data-ops-persistent="{{ ($ops['id'] ?? '') === 'ops-suggest' ? '1' : '0' }}" style="display:block;text-decoration:none;color:inherit;">
                                        <div class="notif-item-title">{{ $ops['title'] }}</div>
                                        <div class="notif-item-body" style="font-size:12px;">{{ $ops['body'] }}</div>
                                    </a>
                                @endforeach
                            </div>
                            <div class="notif-panel-header">
                                <strong>Pengumuman</strong>
                                <button type="button" class="notif-clear-btn" id="notifClear" data-announcement-unread="{{ $notifUnreadCount }}" data-ops-count="{{ $notifOpsCount }}" {{ ($notifUnreadCount + $notifOpsCount) > 0 ? '' : 'disabled' }}>
                                    Tandai semua dibaca
                                </button>
                            </div>
                            @if($notifOpsCount > 0 && $notifUnreadCount === 0)
                                <div style="padding:8px 14px 0;font-size:11px;color:var(--text-muted);line-height:1.45;">
                                    Lonceng menampilkan <strong>antrian operasional</strong> (user, saran, mesin) terpisah dari <strong>pengumuman resmi</strong> di menu Komunikasi.
                                </div>
                            @endif
                            <div class="notif-panel-body" id="notifList">
                                @forelse($notifAnnouncements as $ann)
                                    @php
                                        $prio = $ann->prioritas ?: 'normal';
                                        $isUnread = (bool) ($ann->is_unread ?? false);
                                    @endphp
                                    <div class="notif-item {{ $isUnread ? 'is-unread' : '' }}" data-id="{{ $ann->id }}">
                                        <div class="notif-item-row">
                                            <div class="notif-item-title">{{ $ann->judul }}</div>
                                            <span class="notif-item-badge prio-{{ $prio }}">{{ $prio }}</span>
                                        </div>
                                        <div class="notif-item-body">
                                            {{ \Illuminate\Support\Str::limit(strip_tags($ann->isi), 130) }}
                                        </div>
                                        <div class="notif-item-meta">
                                            <span>{{ optional($ann->author)->nama_lengkap ?? 'Admin' }}</span>
                                            <span>{{ optional($ann->created_at)->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="notif-empty">
                                        @if($notifOpsCount > 0)
                                            Tidak ada pengumuman baru saat ini.<br>
                                            <span style="font-size:11px;color:var(--text-muted);">Lihat antrian operasional di atas — berbeda dari badge Saran &amp; Masukan di sidebar.</span>
                                        @else
                                            Tidak ada pengumuman baru.<br>
                                            <span style="font-size:11px;color:var(--text-muted);">Riwayat ada di menu Pengumuman.</span>
                                        @endif
                                    </div>
                                @endforelse
                            </div>
                            @if($announcementListRoute !== '#')
                            <div class="notif-panel-footer">
                                <a href="{{ $announcementListRoute }}">Lihat riwayat pengumuman →</a>
                                @if($hasOpsSuggest && $userRole === 'laboran')
                                    <a href="{{ route('admin.suggestions.index', ['status' => 'baru']) }}" style="display:block;margin-top:6px;">Saran &amp; masukan baru →</a>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Profile dropdown (logout di sini) --}}
                    <div class="profile-menu-wrap" id="profileMenuWrap">
                        <button type="button" class="profile-menu-toggle" id="profileMenuToggle" aria-haspopup="menu" aria-expanded="false" title="Menu profil">
                            <span class="profile-menu-avatar">
                                @if(Auth::user()->profile_photo_url)
                                    <img src="{{ Auth::user()->profile_photo_url }}" alt="">
                                @else
                                    {{ strtoupper(substr(Auth::user()->nama_lengkap, 0, 1)) }}
                                @endif
                            </span>
                            <span class="profile-menu-caret" aria-hidden="true"><i data-lucide="chevron-down"></i></span>
                        </button>
                        <div class="profile-menu-dropdown" role="menu" aria-label="Menu profil">
                            <a href="{{ $profileUrl }}" class="profile-menu-item" role="menuitem">
                                <i data-lucide="user"></i>
                                <span>Lihat profil</span>
                            </a>
                            @if(($userRole ?? null) === 'laboran' && \App\Support\PermissionRegistry::canCurrent('settings', 'view'))
                                <a href="{{ route('admin.settings') }}" class="profile-menu-item" role="menuitem">
                                    <i data-lucide="settings"></i>
                                    <span>Pengaturan</span>
                                </a>
                            @endif
                            <div class="profile-menu-divider"></div>
                            <button type="button" class="profile-menu-item is-danger" role="menuitem" onclick="confirmLogout()">
                                <i data-lucide="log-out"></i>
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" id="logoutForm" style="display:none;">
                        @csrf
                    </form>
                </div>
            </header>

            <!-- Page Content -->
            <main class="main-content">
                @if(session('email_verification_pending') && !Auth::user()->email_verified_at)
                <div class="email-verify-banner" id="emailVerifyBanner">
                    <div class="email-verify-banner__text">
                        <strong>Verifikasi email diperlukan</strong>
                        <span>
                            @if(session('account_verify_needs_email'))
                                Akun Anda belum memiliki email. Tambahkan email aktif untuk menerima kode OTP.
                            @else
                                Email belum diverifikasi. Masukkan kode OTP yang dikirim ke {{ session('account_verify_email', Auth::user()->email) }}.
                            @endif
                        </span>
                    </div>
                    <button type="button" class="email-verify-banner__toggle" id="emailVerifyBannerToggle">
                        Verifikasi sekarang
                    </button>
                </div>
                <link rel="stylesheet" href="{{ asset('css/email-verify-modal.css') }}?v=7">
                @if(\App\Support\Recaptcha::isConfigured())
                <script>window.SIMALAS_RECAPTCHA_SITE_KEY = @json(config('services.recaptcha.site_key'));</script>
                <script src="{{ asset('js/recaptcha-forms.js') }}?v=4"></script>
                <script src="https://www.google.com/recaptcha/api.js?onload=simalasRecaptchaReady&amp;render=explicit&amp;hl=id" async defer></script>
                @endif
                @include('partials.email-verify-modal')
                <script src="{{ asset('js/email-verify-modal.js') }}?v=6" defer></script>
                @elseif(session('account_verify_success'))
                <link rel="stylesheet" href="{{ asset('css/email-verify-modal.css') }}?v=7">
                @include('partials.email-verify-modal')
                <script src="{{ asset('js/email-verify-modal.js') }}?v=6" defer></script>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <div class="profile-popover-backdrop" id="profilePopover" aria-hidden="true">
        <div class="profile-popover" role="dialog" aria-label="Profil singkat pengguna">
            <div class="profile-popover-cover"></div>
            <div class="profile-popover-body">
                @if(Auth::user()->profile_photo_url)
                    <button type="button" class="profile-popover-avatar photo-zoom"
                        data-photo-preview
                        data-photo-src="{{ Auth::user()->profile_photo_url }}"
                        data-photo-name="{{ Auth::user()->nama_lengkap }}"
                        data-photo-meta="{{ Auth::user()->role_display }}"
                        title="Klik untuk memperbesar foto profil">
                        <img src="{{ Auth::user()->profile_photo_url }}" alt="Foto {{ Auth::user()->nama_lengkap }}">
                    </button>
                @else
                    <div class="profile-popover-avatar">
                        {{ strtoupper(substr(Auth::user()->nama_lengkap, 0, 1)) }}
                    </div>
                @endif
                <div class="profile-popover-name">{{ Auth::user()->nama_lengkap }}</div>
                <div class="profile-popover-role">{{ Auth::user()->role_display }}</div>
                <div class="profile-popover-meta">
                    <div><span>NITK/NIDN</span><strong>{{ Auth::user()->nitk_nidn }}</strong></div>
                    <div><span>Email</span><strong>{{ Auth::user()->email ?: '—' }}</strong></div>
                    <div><span>Status</span><strong>{{ Auth::user()->is_active ? 'Aktif' : 'Pending' }}</strong></div>
                    <div><span>Verifikasi</span><strong>{{ Auth::user()->email_verified_at ? Auth::user()->email_verified_at->translatedFormat('d M Y') : '—' }}</strong></div>
                </div>
                <p class="profile-popover-hint">Edit foto &amp; data lengkap lewat halaman profil.</p>
                <div class="profile-popover-actions">
                    <a class="profile-popover-link" href="{{ route('profiles.show', Auth::user()) }}">Lihat Profil Lengkap</a>
                    <button type="button" class="profile-popover-close" id="profilePopoverClose">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="photo-lightbox" id="photoLightbox" role="dialog" aria-modal="true" aria-label="Foto profil">
        <div class="photo-lightbox__box">
            <button type="button" class="photo-lightbox__close" id="photoLightboxClose" aria-label="Tutup foto">
                <i data-lucide="x"></i>
            </button>
            <img src="" alt="Foto profil" class="photo-lightbox__img" id="photoLightboxImg">
            <div class="photo-lightbox__cap">
                <strong id="photoLightboxName"></strong>
                <span id="photoLightboxMeta"></span>
            </div>
        </div>
    </div>

    <script>
        // ── Global Page Loader ──
        (function() {
            const loader = document.getElementById('simalas-loader');
            const loaderText = document.getElementById('simalas-loader-text');
            window.simalasHideLoader = window.simalasHideLoader || function () {};
            if (!loader) return;

            const hide = () => {
                loader.classList.add('is-hidden');
                loader.classList.remove('is-visible');
            };
            const show = (label) => {
                if (label && loaderText) loaderText.textContent = label;
                loader.classList.add('is-visible');
                loader.classList.remove('is-hidden');
            };

            // Hide once DOM is usable. Do not wait forever on slow external assets.
            if (document.readyState === 'complete') {
                requestAnimationFrame(() => setTimeout(hide, 180));
            } else {
                document.addEventListener('DOMContentLoaded', () => setTimeout(hide, 220), { once: true });
                window.addEventListener('load', () => setTimeout(hide, 180));
            }
            setTimeout(hide, 2500);

            // Show on internal navigation (link / form submit) so transitions feel smooth
            const isInternal = (url) => {
                try {
                    const u = new URL(url, window.location.origin);
                    return u.origin === window.location.origin;
                } catch (e) { return false; }
            };

            document.addEventListener('click', function(e) {
                const a = e.target.closest('a');
                if (!a) return;
                if (a.target === '_blank' || a.hasAttribute('download')) return;
                if (a.dataset.noLoader === '1') return;
                const href = a.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
                if (!isInternal(href)) return;
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                show('Memuat halaman');
            });

            document.addEventListener('submit', function(e) {
                if (e.defaultPrevented) return;
                const form = e.target;
                if (!form || form.dataset.noLoader === '1') return;
                const method = (form.getAttribute('method') || 'GET').toUpperCase();
                const action = form.getAttribute('action') || window.location.href;
                if (!isInternal(action)) return;
                show(method === 'POST' ? 'Memproses' : 'Memuat halaman');
            });

            // Reshow during real navigation
            window.addEventListener('beforeunload', () => show('Memuat halaman'));

            // Back-forward cache (swipe kembali di HP): force reload supaya
            // middleware auth jalan ulang — setelah logout wajib login lagi.
            window.addEventListener('pageshow', function(ev) {
                if (ev.persisted) {
                    window.location.reload();
                    return;
                }
                hide();
            });
        })();

        // ── Theme Switch (kiri terang, kanan gelap) ──
        (function initTheme() {
            const root = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            const savedTheme = localStorage.getItem('dashboard_theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');

            function applyTheme(isDark, emitEvent) {
                if (isDark) {
                    root.setAttribute('data-theme', 'dark');
                } else {
                    root.removeAttribute('data-theme');
                }
                if (themeToggle) {
                    themeToggle.classList.toggle('is-dark', isDark);
                    themeToggle.setAttribute('aria-checked', isDark ? 'true' : 'false');
                    const icon = document.getElementById('themeToggleIcon');
                    if (icon) {
                        icon.setAttribute('data-lucide', isDark ? 'moon' : 'sun');
                        if (window.refreshLucideIcons) window.refreshLucideIcons();
                    }
                }
                if (emitEvent) {
                    document.dispatchEvent(new CustomEvent('simalas:themechange', {
                        detail: { theme: isDark ? 'dark' : 'light' },
                    }));
                }
            }

            applyTheme(theme === 'dark', false);

            if (themeToggle) {
                themeToggle.addEventListener('click', function () {
                    const nextDark = root.getAttribute('data-theme') !== 'dark';
                    localStorage.setItem('dashboard_theme', nextDark ? 'dark' : 'light');
                    applyTheme(nextDark, true);
                });
            }
        })();

        // ── Menu Search (dropdown seperti pencarian HP) ──
        (function initMenuSearch() {
            const input = document.getElementById('menuSearchInput');
            const dropdown = document.getElementById('menuSearchDropdown');
            const wrap = document.getElementById('topbarSearch');
            if (!input || !dropdown || !wrap) return;

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));

            function collectMenus() {
                const items = [];
                const seen = new Set();
                document.querySelectorAll('.sidebar-nav a.sidebar-link').forEach((link) => {
                    const href = link.getAttribute('href');
                    if (!href || href === '#' || seen.has(href)) return;
                    seen.add(href);
                    const clone = link.cloneNode(true);
                    clone.querySelectorAll('.sidebar-link-badge, .sidebar-link-icon').forEach((n) => n.remove());
                    const label = (clone.textContent || '').replace(/\s+/g, ' ').trim();
                    if (!label) return;
                    const group = link.closest('.sidebar-group');
                    const groupLabel = group
                        ? ((group.querySelector('.sidebar-group-label')?.textContent || '').replace(/\s+/g, ' ').trim())
                        : '';
                    const iconEl = link.querySelector('.sidebar-link-icon [data-lucide]');
                    items.push({
                        href,
                        label,
                        group: groupLabel,
                        icon: iconEl ? iconEl.getAttribute('data-lucide') : 'circle'
                    });
                });
                return items;
            }

            let menus = collectMenus();
            let activeIndex = -1;

            function render(list, query) {
                if (!query) {
                    dropdown.classList.remove('show');
                    dropdown.innerHTML = '';
                    activeIndex = -1;
                    return;
                }
                if (!list.length) {
                    dropdown.innerHTML = '<div class="topbar-search-empty">Menu tidak ditemukan</div>';
                    dropdown.classList.add('show');
                    activeIndex = -1;
                    return;
                }
                dropdown.innerHTML = list.map((item, idx) => `
                    <a href="${esc(item.href)}" class="topbar-search-item" role="option" data-idx="${idx}">
                        <span class="topbar-search-item-icon"><i data-lucide="${esc(item.icon)}"></i></span>
                        <span class="topbar-search-item-text">
                            <span class="topbar-search-item-label">${esc(item.label)}</span>
                            ${item.group ? `<span class="topbar-search-item-group">${esc(item.group)}</span>` : ''}
                        </span>
                    </a>
                `).join('');
                dropdown.classList.add('show');
                activeIndex = 0;
                updateActive();
                if (window.refreshLucideIcons) window.refreshLucideIcons();
            }

            function updateActive() {
                dropdown.querySelectorAll('.topbar-search-item').forEach((el, idx) => {
                    el.classList.toggle('is-active', idx === activeIndex);
                });
            }

            function filterMenus(q) {
                const query = q.trim().toLowerCase();
                if (!query) return render([], '');
                const ranked = menus
                    .map((item) => {
                        const hay = `${item.label} ${item.group}`.toLowerCase();
                        let score = -1;
                        if (item.label.toLowerCase().startsWith(query)) score = 3;
                        else if (item.label.toLowerCase().includes(query)) score = 2;
                        else if (hay.includes(query)) score = 1;
                        return { item, score };
                    })
                    .filter((r) => r.score > 0)
                    .sort((a, b) => b.score - a.score || a.item.label.localeCompare(b.item.label))
                    .slice(0, 8)
                    .map((r) => r.item);
                render(ranked, query);
            }

            input.addEventListener('focus', () => {
                menus = collectMenus();
                if (input.value.trim()) filterMenus(input.value);
            });
            input.addEventListener('input', () => filterMenus(input.value));
            input.addEventListener('keydown', (e) => {
                const items = [...dropdown.querySelectorAll('.topbar-search-item')];
                if (!items.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    updateActive();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + items.length) % items.length;
                    updateActive();
                } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    window.location.href = items[activeIndex].getAttribute('href');
                } else if (e.key === 'Escape') {
                    dropdown.classList.remove('show');
                    input.blur();
                }
            });

            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        })();

        // ── Profile menu dropdown ──
        (function initProfileMenu() {
            const wrap = document.getElementById('profileMenuWrap');
            const toggle = document.getElementById('profileMenuToggle');
            if (!wrap || !toggle) return;

            const setOpen = (open) => {
                wrap.classList.toggle('open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                setOpen(!wrap.classList.contains('open'));
            });
            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) setOpen(false);
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') setOpen(false);
            });
        })();

        // ── Sidebar Collapse Toggle ──
        (function initSidebarToggle() {
            const layout = document.querySelector('.dashboard-layout');
            const toggle = document.getElementById('sidebarToggle');
            if (!layout || !toggle) return;

            const applyState = (collapsed) => {
                layout.classList.toggle('sidebar-collapsed', collapsed);
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.title = collapsed ? 'Buka sidebar' : 'Perkecil sidebar';
                toggle.setAttribute('aria-label', toggle.title);
                const icon = toggle.querySelector('[data-lucide]');
                if (icon) {
                    icon.setAttribute('data-lucide', collapsed ? 'chevron-right' : 'chevron-left');
                    if (window.refreshLucideIcons) window.refreshLucideIcons();
                }
            };

            const saved = localStorage.getItem('dashboard_sidebar_collapsed') === '1';
            applyState(saved);

            toggle.addEventListener('click', function () {
                const next = !layout.classList.contains('sidebar-collapsed');
                applyState(next);
                localStorage.setItem('dashboard_sidebar_collapsed', next ? '1' : '0');
            });
        })();

        // ── Sidebar Dropdown Groups ──
        (function initSidebarGroups() {
            const groups = document.querySelectorAll('[data-sidebar-group]');
            groups.forEach((group) => {
                const button = group.querySelector('[data-sidebar-group-toggle]');
                const key = group.dataset.sidebarGroup;
                if (!button || !key) return;

                const saved = localStorage.getItem(`dashboard_sidebar_group_${key}`);
                if (saved === 'open') {
                    group.classList.add('open');
                } else if (saved === 'closed' && !group.dataset.activeGroup) {
                    group.classList.remove('open');
                }

                button.addEventListener('click', () => {
                    const open = !group.classList.contains('open');
                    group.classList.toggle('open', open);
                    localStorage.setItem(`dashboard_sidebar_group_${key}`, open ? 'open' : 'closed');
                });
            });
        })();

        // ── Sidebar Profile Popover ──
        (function initProfilePopover() {
            const toggle = document.getElementById('sidebarProfileToggle');
            const popover = document.getElementById('profilePopover');
            const close = document.getElementById('profilePopoverClose');
            if (!toggle || !popover) return;

            const open = () => {
                popover.classList.add('is-open');
                popover.setAttribute('aria-hidden', 'false');
            };
            const hide = () => {
                popover.classList.remove('is-open');
                popover.setAttribute('aria-hidden', 'true');
            };

            toggle.addEventListener('click', open);
            close?.addEventListener('click', hide);
            popover.addEventListener('click', function (e) {
                if (e.target === popover) hide();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hide();
            });
        })();

        // ── Popup Foto Profil ──
        (function initPhotoLightbox() {
            const box = document.getElementById('photoLightbox');
            const img = document.getElementById('photoLightboxImg');
            const name = document.getElementById('photoLightboxName');
            const meta = document.getElementById('photoLightboxMeta');
            const close = document.getElementById('photoLightboxClose');
            if (!box || !img) return;

            const hide = () => {
                box.classList.remove('is-open');
                img.src = '';
            };

            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('[data-photo-preview]');
                if (!trigger || !trigger.dataset.photoSrc) return;
                e.preventDefault();
                img.src = trigger.dataset.photoSrc;
                if (name) name.textContent = trigger.dataset.photoName || '';
                if (meta) meta.textContent = trigger.dataset.photoMeta || '';
                box.classList.add('is-open');
                close?.focus();
            });

            close?.addEventListener('click', hide);
            box.addEventListener('click', function (e) {
                if (e.target === box) hide();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && box.classList.contains('is-open')) hide();
            });
        })();

        // ── Role Access Panel ──
        (function initAccessPanel() {
            const toggle = document.getElementById('accessToggle');
            const modal = document.getElementById('accessModal');
            const close = document.getElementById('accessClose');
            if (!toggle || !modal || !close) return;

            const open = () => modal.classList.add('show');
            const hide = () => modal.classList.remove('show');

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                open();
            });
            close.addEventListener('click', hide);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) hide();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hide();
            });
        })();

        // ── Notification Panel (server-driven, real announcements) ──
        (function initNotifications() {
            const notifToggle = document.getElementById('notifToggle');
            const notifPanel = document.getElementById('notifPanel');
            const notifList = document.getElementById('notifList');
            const notifOpsList = document.getElementById('notifOpsList');
            const notifDot = document.getElementById('notifDot');
            const notifClear = document.getElementById('notifClear');
            if (!notifToggle || !notifPanel || !notifList || !notifDot || !notifClear) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const sidebarBadge = document.getElementById('sidebarAnnouncementBadge');
            const sidebarSuggestBadge = document.getElementById('sidebarSuggestBadge');
            const sidebarOpsBadge = document.getElementById('sidebarOpsBadge');
            let lastTotalCount = {{ (int) ($notifTotalCount ?? 0) }};

            const pulseBell = () => {
                notifToggle.classList.add('notif-bell-pulse');
                setTimeout(() => notifToggle.classList.remove('notif-bell-pulse'), 1200);
            };

            const updateSidebarSuggestBadge = (count) => {
                if (!sidebarSuggestBadge) return;
                const n = Math.max(0, parseInt(count, 10) || 0);
                if (n > 0) {
                    sidebarSuggestBadge.textContent = n > 99 ? '99+' : String(n);
                    sidebarSuggestBadge.style.display = '';
                    sidebarSuggestBadge.classList.add('pulsing');
                } else {
                    sidebarSuggestBadge.style.display = 'none';
                    sidebarSuggestBadge.classList.remove('pulsing');
                }
            };

            const updateSidebarOpsBadge = (count) => {
                if (!sidebarOpsBadge) return;
                const n = Math.max(0, parseInt(count, 10) || 0);
                if (n > 0) {
                    sidebarOpsBadge.textContent = n > 99 ? '99+' : String(n);
                    sidebarOpsBadge.style.display = '';
                } else {
                    sidebarOpsBadge.style.display = 'none';
                }
            };
            const updateBellBadge = (totalCount) => {
                const n = Math.max(0, parseInt(totalCount, 10) || 0);
                if (n > lastTotalCount) {
                    pulseBell();
                }
                lastTotalCount = n;
                if (n > 0) {
                    notifDot.textContent = n > 99 ? '99+' : String(n);
                    notifDot.style.display = '';
                } else {
                    notifDot.style.display = 'none';
                }
            };

            const updateClearButton = (announcementUnread, opsCount) => {
                const ann = Math.max(0, parseInt(announcementUnread, 10) || 0);
                const ops = Math.max(0, parseInt(opsCount, 10) || 0);
                if (notifClear) {
                    notifClear.dataset.announcementUnread = String(ann);
                    notifClear.dataset.opsCount = String(ops);
                    notifClear.disabled = (ann + ops) === 0;
                }
            };

            const updateAnnouncementUi = (announcementUnread, opsCount) => {
                const ann = Math.max(0, parseInt(announcementUnread, 10) || 0);
                updateClearButton(ann, opsCount ?? notifClear?.dataset?.opsCount ?? 0);
                if (sidebarBadge) {
                    if (ann > 0) {
                        sidebarBadge.textContent = ann > 99 ? '99+' : String(ann);
                        sidebarBadge.style.display = '';
                        sidebarBadge.classList.add('pulsing');
                    } else {
                        sidebarBadge.style.display = 'none';
                        sidebarBadge.classList.remove('pulsing');
                    }
                }
            };

            const dismissOpsItem = async (opsKey, fingerprint) => {
                const res = await fetch('{{ route('notifications.ops.dismiss') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ops_key: opsKey, fingerprint }),
                });
                if (!res.ok) throw new Error('dismiss ops failed');
                return res.json();
            };

            const renderOps = (ops) => {
                if (!notifOpsList) return;
                const opsHeader = document.querySelector('.notif-ops-header');
                if (!Array.isArray(ops) || ops.length === 0) {
                    notifOpsList.innerHTML = '';
                    notifOpsList.style.display = 'none';
                    if (opsHeader) {
                        opsHeader.style.display = 'none';
                    }
                    return;
                }
                if (opsHeader) {
                    opsHeader.style.display = '';
                    const strong = opsHeader.querySelector('strong');
                    if (strong) {
                        strong.textContent = '⚡ Antrian operasional';
                    }
                    let countLabel = opsHeader.querySelector('.notif-ops-count');
                    if (!countLabel) {
                        countLabel = document.createElement('span');
                        countLabel.className = 'notif-ops-count';
                        countLabel.style.cssText = 'font-size:11px;color:var(--text-muted);font-weight:500;';
                        opsHeader.appendChild(countLabel);
                    }
                    countLabel.textContent = `${ops.length} item`;
                }
                notifOpsList.style.display = '';
                const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
                notifOpsList.innerHTML = ops.map(it => `
                    <a href="${esc(it.url)}" class="notif-item notif-ops-link" data-ops-id="${esc(it.id)}" data-ops-fp="${esc(it.fingerprint || '')}" data-ops-persistent="${it.id === 'ops-suggest' ? '1' : '0'}" style="display:block;text-decoration:none;color:inherit;">
                        <div class="notif-item-title">${esc(it.title)}</div>
                        <div class="notif-item-body" style="font-size:12px;">${esc(it.body)}</div>
                    </a>
                `).join('');
            };

            const pengumumanEmptyHtml = (opsCount) => {
                const ops = Math.max(0, parseInt(opsCount, 10) || 0);
                if (ops > 0) {
                    return '<div class="notif-empty">Tidak ada pengumuman baru saat ini.<br><span style="font-size:11px;color:var(--text-muted);">Lihat antrian operasional di atas — berbeda dari badge Saran &amp; Masukan di sidebar.</span></div>';
                }
                return '<div class="notif-empty">✨ Tidak ada pengumuman baru.<br><span style="font-size:11px;color:var(--text-muted);">Riwayat ada di menu Pengumuman.</span></div>';
            };

            notifPanel.addEventListener('click', async function (e) {
                const link = e.target.closest('a.notif-ops-link');
                if (!link) return;
                if (link.dataset.opsPersistent === '1') {
                    return;
                }
                const opsKey = link.dataset.opsId;
                const fp = link.dataset.opsFp;
                if (!opsKey || !fp) return;
                e.preventDefault();
                try {
                    const data = await dismissOpsItem(opsKey, fp);
                    updateBellBadge(data.total_unread_count ?? 0);
                    updateAnnouncementUi(data.announcement_unread ?? 0, data.ops_count ?? 0);
                    renderOps(data.ops ?? []);
                    window.location.href = link.href;
                } catch (err) {
                    window.location.href = link.href;
                }
            });

            notifToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                notifPanel.classList.toggle('show');
            });

            notifClear.addEventListener('click', async function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (notifClear.disabled) return;
                notifClear.disabled = true;
                try {
                    const res = await fetch('{{ route('notifications.readAll') }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    if (!res.ok) throw new Error('mark-all failed');
                    const data = await res.json();
                    document.querySelectorAll('#notifList .notif-item.is-unread').forEach(el => el.classList.remove('is-unread'));
                    notifList.innerHTML = pengumumanEmptyHtml(data.ops_count ?? 0);
                    updateBellBadge(data.total_unread_count ?? 0);
                    updateAnnouncementUi(data.announcement_unread ?? 0, data.ops_count ?? 0);
                    renderOps(data.ops ?? []);
                } catch (err) {
                    console.warn('notif mark-all error', err);
                    updateClearButton(
                        notifClear.dataset.announcementUnread ?? 0,
                        notifClear.dataset.opsCount ?? 0
                    );
                }
            });

            document.addEventListener('click', function (e) {
                if (!notifPanel.contains(e.target) && !notifToggle.contains(e.target)) {
                    notifPanel.classList.remove('show');
                }
            });

            // Live refresh every 60s — new announcements show up without reload.
            const refreshFeed = async () => {
                try {
                    const res = await fetch('{{ route('notifications.feed') }}', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    updateBellBadge(data.total_unread_count ?? 0);
                    updateAnnouncementUi(data.announcement_unread ?? 0, data.ops_count ?? 0);
                    updateSidebarSuggestBadge(data.new_suggest_count ?? 0);
                    updateSidebarOpsBadge(data.ops_count ?? 0);
                    renderOps(data.ops ?? []);
                    if (!Array.isArray(data.items)) return;

                    if (data.items.length === 0) {
                        notifList.innerHTML = pengumumanEmptyHtml(data.ops_count ?? 0);
                        return;
                    }
                    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
                    notifList.innerHTML = data.items.map(it => `
                        <div class="notif-item ${it.is_unread ? 'is-unread' : ''}" data-id="${it.id}">
                            <div class="notif-item-row">
                                <div class="notif-item-title">${esc(it.judul)}</div>
                                <span class="notif-item-badge prio-${esc(it.prioritas || 'normal')}">${esc(it.prioritas || 'normal')}</span>
                            </div>
                            <div class="notif-item-body">${esc(it.isi)}</div>
                            <div class="notif-item-meta">
                                <span>👤 ${esc(it.author)}</span>
                                <span>${esc(it.created_human || '')}</span>
                            </div>
                        </div>
                    `).join('');
                } catch (err) { /* silent */ }
            };
            setInterval(refreshFeed, 12_000);
            refreshFeed();
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') refreshFeed();
            });
        })();

        // ── Logout Confirmation ──
        function confirmLogout() {
            Swal.fire({
                title: 'Yakin ingin logout?',
                text: 'Anda akan keluar dari sistem SIMALAS.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '🚪 Ya, Logout',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f43f5e',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logoutForm').submit();
                }
            });
        }

        // ── Reusable SweetAlert Confirmation ──
        document.querySelectorAll('form[data-swal-confirm]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: this.dataset.swalTitle || 'Lanjutkan aksi ini?',
                    text: this.dataset.swalText || 'Pastikan data yang dipilih sudah benar.',
                    icon: this.dataset.swalIcon || 'warning',
                    showCancelButton: true,
                    confirmButtonText: this.dataset.swalConfirmButton || 'Ya, Lanjutkan',
                    cancelButtonText: this.dataset.swalCancelButton || 'Batal',
                    confirmButtonColor: this.dataset.swalConfirmColor || '#f43f5e',
                    cancelButtonColor: this.dataset.swalCancelColor || '#94a3b8',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
        });

        // ── SweetAlert Session Notifications ──
        @if(session('login_success'))
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: @json(session('success')),
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true
            });
        @elseif(session('success') && !session('account_verify_success') && !session('mesin_plain_key'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: @json(session('success')),
                confirmButtonColor: '#4a90d9',
                timer: 4000,
                timerProgressBar: true
            });
        @endif
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: @json(session('error')),
                confirmButtonColor: '#f43f5e'
            });
        @endif

        // ── Confirm Activate/Delete Actions ──
        document.querySelectorAll('.action-btn-activate').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const form = this.closest('form');
                if (form?.matches('[data-swal-confirm]')) return;
                e.preventDefault();
                Swal.fire({
                    title: 'Aktifkan User?',
                    text: 'User akan diaktifkan dan bisa login ke sistem.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '✅ Ya, Aktifkan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#22c55e',
                    cancelButtonColor: '#94a3b8'
                }).then(r => { if (r.isConfirmed) form.submit(); });
            });
        });
        document.querySelectorAll('.action-btn-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const form = this.closest('form');
                if (form?.matches('[data-swal-confirm]')) return;
                e.preventDefault();
                Swal.fire({
                    title: 'Hapus User?',
                    text: 'User akan dihapus permanen dari sistem!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '🗑️ Ya, Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#f43f5e',
                    cancelButtonColor: '#94a3b8',
                    reverseButtons: true
                }).then(r => { if (r.isConfirmed) form.submit(); });
            });
        });
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.refreshLucideIcons = function () {
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        };
        document.addEventListener('DOMContentLoaded', window.refreshLucideIcons);
    </script>
    <script src="{{ asset('js/dashboard-ui-state.js') }}?v=3"></script>
    @yield('scripts')
    @include('partials.session-idle-guard')
</body>
</html>
