<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|;\s*)dashboard_theme=(light|dark)/);
            var t = m ? m[1] : null;
            if (!t) {
                try { t = localStorage.getItem('dashboard_theme'); } catch (e) {}
            }
            if (t !== 'light' && t !== 'dark') {
                t = 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
            try {
                if (window.innerWidth > 1024 && localStorage.getItem('simalas_student_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('sp-rail');
                }
            } catch (e) {}
        })();
    </script>
    <title>@yield('title', 'Portal Mahasiswa') — SmartBrail</title>
    <link rel="icon" type="image/png" href="{{ asset('branding/brail-mark.png') }}?v=20261114c">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/student-portal.css') }}?v=20261114c">
    <link rel="stylesheet" href="{{ asset('css/student-shell.css') }}?v=20261114c">
    @stack('styles')
</head>
<body class="student-body sp-body"
      data-session-ping-url="{{ auth()->check() ? route('session.ping') : '' }}"
      data-login-url="{{ route('login') }}"
      data-notif-feed-url="{{ auth()->check() ? route('mahasiswa.notifications.feed') : '' }}"
      data-notif-read-all-url="{{ auth()->check() ? route('mahasiswa.notifications.read-all') : '' }}"
      data-help-ai-url="{{ auth()->check() ? route('mahasiswa.help.ai') : '' }}"
      data-help-submit-url="{{ auth()->check() ? route('mahasiswa.suggestions.store') : '' }}"
      data-help-history-url="{{ auth()->check() ? route('mahasiswa.suggestions') : '' }}">
    @php
        $navUser = auth()->user();
        $isPasswordGate = (bool) ($navUser?->must_change_password ?? false);
        $initials = collect(explode(' ', trim((string) ($navUser?->nama_lengkap ?? 'M'))))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'M';
        $photoUrl = $navUser?->profile_photo_url;
        $firstName = \Illuminate\Support\Str::of((string) ($navUser?->nama_lengkap ?? 'Mahasiswa'))->explode(' ')->first();
        $notifState = $navNotificationState ?? ['unread_count' => 0, 'has_more' => false, 'items' => []];
        $unreadCount = (int) ($notifState['unread_count'] ?? 0);
        $notifItems = $notifState['items'] ?? [];

        $icons = [
            'home' => '<path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1z"/>',
            'history' => '<rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M8 2.5v4M16 2.5v4M3.5 9.5h17"/><path d="m9 15 2 2 4-4"/>',
            'calendar' => '<rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M8 2.5v4M16 2.5v4M3.5 9.5h17"/><path d="M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01"/>',
            'ticket' => '<path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.6-5.2A8.5 8.5 0 1 1 21 12z"/><path d="M8.5 10.5h7M8.5 14h4.5"/>',
            'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/><path d="M8.5 7.5h7"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 5.2a3.5 3.5 0 0 1 0 5.6M21.5 20a6.4 6.4 0 0 0-3.7-5.8"/>',
            'room' => '<path d="M13.5 4.5 20 6.8v10.4l-6.5 2.3z"/><path d="M13.5 4.5V22"/><path d="M4 19.5h9.5"/><circle cx="16.3" cy="12" r=".9" fill="currentColor" stroke="none"/>',
            'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.3a2.9 2.9 0 0 1 5.6 1c0 1.9-2.8 2.3-2.8 4"/><path d="M12 17.5h.01"/>',
            'shield' => '<path d="M12 3 4.5 6v5.5c0 4.7 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.8 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
        ];

        // Navigasi utama portal mahasiswa Smart BRAIL — datar, semua rute nyata.
        $isRuanganRiwayat = request()->routeIs('mahasiswa.ruangan') && request()->query('tab') === 'riwayat';
        $navItemsTop = [
            ['label' => 'Beranda', 'route' => 'mahasiswa.beranda', 'icon' => 'home', 'active' => ['mahasiswa.beranda']],
            ['label' => 'Absensi', 'route' => 'mahasiswa.absensi', 'icon' => 'history', 'active' => ['mahasiswa.absensi'], 'hint' => 'Riwayat'],
        ];
        $navGroupRuangan = [
            'key' => 'ruangan', 'label' => 'Ruangan', 'icon' => 'room', 'items' => [
                ['label' => 'Ajukan Ruangan', 'route' => 'mahasiswa.ruangan', 'icon' => 'room', 'active' => ['mahasiswa.ruangan'], 'on' => request()->routeIs('mahasiswa.ruangan') && ! $isRuanganRiwayat, 'feature' => 'pengajuan-ruangan'],
                ['label' => 'Riwayat Pengajuan', 'href' => route('mahasiswa.ruangan', ['tab' => 'riwayat']), 'icon' => 'history', 'active' => ['mahasiswa.ruangan'], 'on' => $isRuanganRiwayat],
            ],
        ];
        $navItemsMid = [
            ['label' => 'Kalender', 'route' => 'mahasiswa.calendar', 'icon' => 'calendar', 'active' => ['mahasiswa.calendar']],
            ['label' => 'Tim PBL', 'route' => 'mahasiswa.team', 'icon' => 'users', 'active' => ['mahasiswa.team'], 'feature' => 'pbl-tim'],
        ];
        $navGroupBantuan = [
            'key' => 'bantuan', 'label' => 'Laporan & Bantuan', 'items' => [
                ['label' => 'Riwayat Laporan', 'route' => 'mahasiswa.suggestions', 'icon' => 'ticket', 'active' => ['mahasiswa.suggestions']],
                ['label' => 'Panduan Penggunaan', 'route' => 'mahasiswa.guide', 'icon' => 'book', 'active' => ['mahasiswa.guide']],
            ],
        ];
    @endphp

    <a href="#spMain" class="sp-skip">Lewati ke konten</a>

    <div class="sp-shell @if($isPasswordGate) sp-shell--gate @endif" id="spShell">
        @if(! $isPasswordGate)
            <div class="sp-backdrop" id="spBackdrop" hidden></div>

            <aside class="sp-sidebar" id="spSidebar" aria-label="Navigasi portal mahasiswa">
                <div class="sp-sidebar__head">
                    <a href="{{ route('mahasiswa.beranda') }}" class="sp-brand" aria-label="SmartBrail — Beranda mahasiswa">
                        <span class="sp-brand__mark"><img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/smartbrail-logo.jpg')) }}" alt="" width="30" height="30"></span>
                        <span class="sp-brand__text">
                            <strong>{{ \App\Support\Branding::appName() }}</strong>
                            <small>Portal Mahasiswa</small>
                        </span>
                    </a>
                    <button type="button" class="sp-icon-btn sp-sidebar__close" id="spSidebarClose" aria-label="Tutup menu">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <nav class="sp-nav" aria-label="Menu utama">
                    <ul class="sp-nav__list sp-nav__list--flat">
                        @foreach($navItemsTop as $item)
                            @include('layouts.partials.student-nav-item')
                        @endforeach
                    </ul>

                    @include('layouts.partials.student-nav-group', ['group' => $navGroupRuangan])

                    <ul class="sp-nav__list sp-nav__list--flat">
                        @foreach($navItemsMid as $item)
                            @include('layouts.partials.student-nav-item')
                        @endforeach
                    </ul>

                    @include('layouts.partials.student-nav-group', ['group' => $navGroupBantuan])
                </nav>
                <div class="sp-sidebar__deco" aria-hidden="true">
                    <svg viewBox="0 0 240 132" fill="none" preserveAspectRatio="xMidYMax slice">
                        <g stroke="rgba(148, 197, 255, .55)" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M30 132V72l24-14 24 14v60"/>
                            <path d="M38 132V84h32v48"/>
                            <path d="M46 92h6M46 102h6M46 112h6M64 92h6M64 102h6M64 112h6"/>
                            <path d="M92 132V58l18-10 18 10v74"/>
                            <path d="M98 66h6M98 76h6M98 86h6M98 96h6M98 106h6M116 66h6M116 76h6M116 86h6M116 96h6M116 106h6"/>
                            <path d="M142 132V80l16-9 16 9v52"/>
                            <path d="M150 88h5M150 96h5M150 104h5M163 88h5M163 96h5M163 104h5"/>
                            <path d="M186 132V96l12-7 12 7v36"/>
                        </g>
                        <path d="M0 96c30-12 52 8 84-2s56-18 84-6 46 2 72-8v52H0Z" fill="rgba(37, 99, 235, .14)"/>
                        <path d="M0 108c34-10 58 10 92 0s52-16 82-4 44 0 66-8v36H0Z" fill="rgba(6, 182, 212, .16)"/>
                        <path d="M0 96c30-12 52 8 84-2s56-18 84-6 46 2 72-8" stroke="rgba(125, 211, 252, .6)" stroke-width="1.5" fill="none"/>
                        <path d="M0 108c34-10 58 10 92 0s52-16 82-4 44 0 66-8" stroke="rgba(34, 211, 238, .55)" stroke-width="1.5" fill="none"/>
                    </svg>
                </div>
            </aside>
        @endif

        <div class="sp-workspace">
            <header class="sp-topbar">
                <div class="sp-topbar__left">
                    @if(! $isPasswordGate)
                        <button type="button" class="sp-icon-btn" id="spSidebarToggle" aria-controls="spSidebar" aria-expanded="true" aria-label="Perkecil atau perluas menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                        </button>
                    @else
                        <span class="sp-brand sp-brand--compact">
                            <span class="sp-brand__mark"><img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/smartbrail-logo.jpg')) }}" alt="" width="26" height="26"></span>
                            <span class="sp-brand__text"><strong>{{ \App\Support\Branding::appName() }}</strong><small>Keamanan akun</small></span>
                        </span>
                    @endif
                    <h1 class="sp-topbar__title">@yield('page-title', 'Portal Mahasiswa')</h1>
                </div>

                <div class="sp-topbar__right">
                    @if(! $isPasswordGate)
                        @php
                            $menuSearchItems = collect($navItemsTop)
                                ->merge($navItemsMid)
                                ->merge(collect([$navGroupRuangan, $navGroupBantuan])->flatMap(fn ($g) => $g['items']))
                                ->map(fn ($it) => ['label' => $it['label'], 'url' => $it['href'] ?? route($it['route'])])
                                ->push(['label' => 'Profil Saya', 'url' => route('mahasiswa.profile')])
                                ->push(['label' => 'Keamanan Akun', 'url' => route('mahasiswa.password.change')])
                                ->unique('url')
                                ->values()
                                ->all();
                        @endphp
                        <div class="sp-menusearch" id="studentMenuSearch">
                            <svg class="sp-menusearch__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input type="text"
                                   id="studentMenuSearchInput"
                                   class="sp-menusearch__input"
                                   placeholder="Cari menu…"
                                   autocomplete="off"
                                   role="combobox"
                                   aria-expanded="false"
                                   aria-controls="studentMenuSearchList"
                                   aria-label="Cari menu portal">
                            <ul class="sp-menusearch__list" id="studentMenuSearchList" role="listbox" hidden></ul>
                            <script type="application/json" id="studentMenuSearchData">{!! json_encode($menuSearchItems, JSON_UNESCAPED_UNICODE) !!}</script>
                        </div>
                    @endif
                    <button type="button" class="sp-icon-btn" id="studentThemeToggle" aria-label="Ganti tema terang atau gelap" title="Ganti tema">
                        <svg class="st-theme-icon st-theme-icon--sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                        <svg class="st-theme-icon st-theme-icon--moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    </button>

                    @if(! $isPasswordGate)
                        {{-- 7. Popover Mini Notifikasi --}}
                        <div class="sp-notif-wrap" id="studentNotifWrap">
                            <button type="button" class="sp-icon-btn sp-bell" id="studentNotifBellBtn" aria-label="Notifikasi{{ $unreadCount ? ', ' . $unreadCount . ' belum dibaca' : '' }}" aria-expanded="false" aria-controls="studentNotifPopover" title="Notifikasi">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8.5a6 6 0 0 0-12 0c0 6.5-2.5 8.5-2.5 8.5h17S18 15 18 8.5"/><path d="M10.3 20.5a2 2 0 0 0 3.4 0"/></svg>
                                <span class="sp-bell__badge" id="studentNotifBadge" @if($unreadCount === 0) hidden @endif>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                            </button>

                            <div class="sp-notif-popover" id="studentNotifPopover" role="dialog" aria-label="Panel notifikasi ringkas" hidden>
                                <div class="sp-notif-popover__head">
                                    <div class="sp-notif-popover__title">
                                        <strong>Notifikasi</strong>
                                        <span class="sp-notif-popover__pill" id="studentNotifUnreadPill" @if($unreadCount === 0) hidden @endif>{{ $unreadCount }} belum dibaca</span>
                                    </div>
                                    <button type="button" class="sp-btn sp-btn--ghost sp-btn--xs" id="studentNotifReadAllBtn" @if($unreadCount === 0) hidden @endif>Tandai dibaca</button>
                                </div>
                                <div class="sp-notif-popover__list" id="studentNotifList">
                                    @forelse($notifItems as $item)
                                        <button type="button" class="sp-notif-item {{ empty($item['is_read']) ? 'is-unread' : '' }}" data-notif-item="{{ json_encode($item) }}">
                                            <div class="sp-notif-item__meta">
                                                <span class="sp-status sp-status--{{ ($item['kind'] ?? '') === 'suggestion_reply' ? 'info' : 'neutral' }}">{{ $item['category_label'] ?? 'Info' }}</span>
                                                <time>{{ $item['time_human'] ?? '' }}</time>
                                            </div>
                                            <strong class="sp-notif-item__title">{{ $item['title'] }}</strong>
                                            <p class="sp-notif-item__desc">{{ $item['excerpt'] }}</p>
                                        </button>
                                    @empty
                                        <div class="sp-notif-empty-state">
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sp-ok)"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                            <strong>Semua notifikasi sudah dibaca</strong>
                                            <p>Lihat riwayat lengkap di halaman notifikasi.</p>
                                        </div>
                                    @endforelse
                                </div>
                                <div class="sp-notif-popover__foot">
                                    <a href="{{ route('mahasiswa.notifications') }}" class="sp-link">Buka riwayat notifikasi &rarr;</a>
                                </div>
                            </div>
                        </div>

                        {{-- Modal Detail Notifikasi Ringkas --}}
                        <div class="sp-modal-backdrop" id="studentNotifDetailModal" role="dialog" aria-modal="true" aria-labelledby="studentNotifModalTitle" hidden>
                            <div class="sp-modal">
                                <div class="sp-modal__head">
                                    <div>
                                        <span class="sp-status sp-status--info" id="studentNotifModalCategory">Pengumuman</span>
                                        <h3 id="studentNotifModalTitle" class="sp-modal__title">Detail Notifikasi</h3>
                                        <p class="sp-modal__meta" id="studentNotifModalMeta"></p>
                                    </div>
                                    <button type="button" class="sp-icon-btn" id="studentNotifModalCloseBtn" aria-label="Tutup detail notifikasi">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                                <div class="sp-modal__body">
                                    <div class="sp-modal__content" id="studentNotifModalBody"></div>
                                </div>
                                <div class="sp-modal__foot">
                                    <a href="#" class="sp-btn sp-btn--primary sp-btn--sm" id="studentNotifModalAction" hidden>Buka Riwayat Laporan</a>
                                    <button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" id="studentNotifModalDoneBtn">Tutup</button>
                                </div>
                            </div>
                        </div>

                        {{-- User Avatar Dropdown --}}
                        <div class="sp-user" id="studentUserMenu">
                            <button type="button" class="sp-user__btn" id="studentUserMenuBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="studentUserDropdown">
                                <span class="sp-avatar sp-avatar--sm">
                                    @if($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="" onerror="this.remove()">
                                    @endif
                                    <span>{{ $initials }}</span>
                                </span>
                                <span class="sp-user__name">{{ $firstName }}</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="sp-user__menu" id="studentUserDropdown" role="menu" hidden>
                                <div class="sp-user__head">
                                    <strong>{{ $navUser?->nama_lengkap }}</strong>
                                    <span>NIM {{ $navUser?->nitk_nidn }}</span>
                                </div>
                                <a href="{{ route('mahasiswa.profile') }}" class="sp-user__item" role="menuitem">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">{!! $icons['user'] !!}</svg>
                                    Profil Saya
                                </a>
                                <a href="{{ route('mahasiswa.password.change') }}" class="sp-user__item" role="menuitem">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/></svg>
                                    Keamanan Akun
                                </a>
                                <form method="POST" action="{{ route('mahasiswa.logout') }}">
                                    @csrf
                                    <button type="submit" class="sp-user__item sp-user__item--danger" role="menuitem">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                                        Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('mahasiswa.logout') }}">
                            @csrf
                            <button type="submit" class="sp-btn sp-btn--ghost">Keluar</button>
                        </form>
                    @endif
                </div>
            </header>

            <main class="sp-main" id="spMain" tabindex="-1">
                @if (session('status'))
                    <div class="sp-flash sp-flash--info" role="status">{{ session('status') }}</div>
                @endif
                @if (session('success'))
                    <div class="sp-flash sp-flash--ok" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="sp-flash sp-flash--danger" role="alert">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @if(! $isPasswordGate)
        {{-- 8. Chat Bantuan CS SmartBrail (Single Thread, Tanpa Tab, Tanpa Form Panjang) --}}
        <div class="sp-help-dock" id="spHelpDock"
             data-user-initials="{{ $initials }}"
             data-user-photo="{{ $photoUrl ?? '' }}"
             data-user-name="{{ $firstName }}">

            {{-- Floating Greeting Bubble / Pop up kecil bubble berganti teks --}}
            <div class="sp-help-pill" id="spHelpPill" role="button" tabindex="0" aria-label="Buka bantuan SmartBrail">
                <span class="sp-help-pill__robot" aria-hidden="true">
                    <svg class="sp-robot-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="2" x2="12" y2="5"/>
                        <circle cx="12" cy="2" r="1.5" fill="currentColor"/>
                        <rect x="4" y="5" width="16" height="13" rx="4" fill="currentColor" fill-opacity="0.15"/>
                        <rect x="2" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                        <rect x="20" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                        <circle cx="8.5" cy="10" r="1.5" fill="currentColor"/>
                        <circle cx="15.5" cy="10" r="1.5" fill="currentColor"/>
                        <path d="M8.5 13.5C9.5 15 14.5 15 15.5 13.5"/>
                        <path d="M8 20C8 19 9.5 18 12 18C14.5 18 16 19 16 20"/>
                    </svg>
                </span>
                <span class="sp-help-pill__text" id="spHelpPillText" aria-live="polite">Butuh bantuan?</span>
                <button type="button" class="sp-help-pill__close" id="spHelpPillClose" aria-label="Tutup sapaan bantuan">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <button type="button" class="sp-help-fab" id="spHelpFab" aria-label="Buka obrolan Bantuan SmartBrail" aria-expanded="false" aria-controls="spHelpWidget">
                <span class="sp-help-fab__icon-open" aria-hidden="true">
                    <svg class="sp-robot-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="2" x2="12" y2="5"/>
                        <circle cx="12" cy="2" r="1.5" fill="currentColor"/>
                        <rect x="4" y="5" width="16" height="13" rx="4" fill="currentColor" fill-opacity="0.15"/>
                        <rect x="2" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                        <rect x="20" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                        <circle cx="8.5" cy="10" r="1.5" fill="currentColor"/>
                        <circle cx="15.5" cy="10" r="1.5" fill="currentColor"/>
                        <path d="M8.5 13.5C9.5 15 14.5 15 15.5 13.5"/>
                        <path d="M8 20C8 19 9.5 18 12 18C14.5 18 16 19 16 20"/>
                    </svg>
                </span>
                <span class="sp-help-fab__icon-close" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </span>
                <span>Bantuan</span>
            </button>

            <section class="sp-chat-widget" id="spHelpWidget" aria-label="Layanan Bantuan Mahasiswa" hidden>
                <header class="sp-chat-widget__head">
                    <div class="sp-chat-widget__title">
                        <div class="sp-chat-avatar sp-chat-avatar--bot" aria-hidden="true">
                            <svg class="sp-robot-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="5"/>
                                <circle cx="12" cy="2" r="1.5" fill="currentColor"/>
                                <rect x="4" y="5" width="16" height="13" rx="4" fill="currentColor" fill-opacity="0.15"/>
                                <rect x="2" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                                <rect x="20" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                                <circle cx="8.5" cy="10" r="1.5" fill="currentColor"/>
                                <circle cx="15.5" cy="10" r="1.5" fill="currentColor"/>
                                <path d="M8.5 13.5C9.5 15 14.5 15 15.5 13.5"/>
                                <path d="M8 20C8 19 9.5 18 12 18C14.5 18 16 19 16 20"/>
                            </svg>
                        </div>
                        <div>
                            <strong>Bantuan SmartBrail</strong>
                            <small class="sp-chat-widget__status"><span class="sp-dot sp-dot--ok"></span> Online · Siap membantu</small>
                        </div>
                    </div>
                    <button type="button" class="sp-icon-btn sp-icon-btn--sm" id="spHelpCloseBtn" aria-label="Tutup obrolan">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </header>

                <div class="sp-chat-widget__log" id="spHelpChatLog" role="log" aria-live="polite">
                    <div class="sp-chat-row sp-chat-row--bot">
                        <div class="sp-chat-avatar sp-chat-avatar--bot" title="Asisten SmartBrail">
                            <svg class="sp-robot-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="5"/>
                                <circle cx="12" cy="2" r="1.5" fill="currentColor"/>
                                <rect x="4" y="5" width="16" height="13" rx="4" fill="currentColor" fill-opacity="0.15"/>
                                <rect x="2" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                                <rect x="20" y="9" width="2" height="5" rx="1" fill="currentColor"/>
                                <circle cx="8.5" cy="10" r="1.5" fill="currentColor"/>
                                <circle cx="15.5" cy="10" r="1.5" fill="currentColor"/>
                                <path d="M8.5 13.5C9.5 15 14.5 15 15.5 13.5"/>
                                <path d="M8 20C8 19 9.5 18 12 18C14.5 18 16 19 16 20"/>
                            </svg>
                        </div>
                        <div class="sp-msg sp-msg--bot">
                            <p>Halo <strong>{{ $firstName }}</strong>! 👋 Saya asisten virtual SmartBrail.</p>
                            <p>Ada kendala atau hal yang bisa saya bantu hari ini? Pilih opsi cepat di bawah atau tulis pertanyaan Anda:</p>
                            <div class="sp-quick-options">
                                <button type="button" class="sp-quick-btn" data-quick-action="kendala_absensi">
                                    <span class="sp-quick-btn__icon">📌</span>
                                    <span>Kendala Absensi</span>
                                </button>
                                <button type="button" class="sp-quick-btn" data-quick-action="jadwal_lab">
                                    <span class="sp-quick-btn__icon">🕒</span>
                                    <span>Jadwal Kuliah Lab</span>
                                </button>
                                <button type="button" class="sp-quick-btn" data-quick-action="akun_sidik_jari">
                                    <span class="sp-quick-btn__icon">🔑</span>
                                    <span>Akun &amp; Sidik Jari</span>
                                </button>
                                <button type="button" class="sp-quick-btn" data-quick-action="lapor_laboran">
                                    <span class="sp-quick-btn__icon">📝</span>
                                    <span>Kirim Laporan Resmi</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="sp-chat-widget__foot" id="spHelpForm">
                    <input type="text" id="spHelpInput" class="sp-chat-input" placeholder="Tulis pesan Anda..." maxlength="600" autocomplete="off" required>
                    <button type="submit" class="sp-btn sp-btn--primary sp-btn--sm" id="spHelpSendBtn" aria-label="Kirim pesan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </section>
        </div>
    @endif

    <script src="{{ asset('js/student-shell.js') }}?v=20261114c" defer></script>
    @stack('scripts')
    @include('partials.session-idle-guard')
</body>
</html>
