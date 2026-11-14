@php use App\Support\PermissionRegistry as P; @endphp
<div class="sidebar-section">
    <div class="sidebar-section-title">Menu Admin</div>

    @php
        $annBadge = $sidebarAnnouncementBadge ?? 0;
        $newSuggest = class_exists(\App\Models\Suggestion::class) ? \App\Models\Suggestion::where('status','baru')->count() : 0;
        $groupActive = [
            'main' => request()->routeIs('admin.dashboard') || request()->routeIs('admin.monitoring') || request()->routeIs('admin.ai*'),
            'ops' => request()->routeIs('admin.mesin.*') || request()->routeIs('admin.fingerprint*') || request()->routeIs('admin.absensi.settings'),
            'pbl' => request()->routeIs('admin.pbl.*'),
            'audit' => request()->routeIs('admin.log.*') || request()->routeIs('admin.reports') || request()->routeIs('admin.attendance*') || request()->routeIs('admin.absensi.alfa') || request()->routeIs('admin.absensi.tidak-lengkap'),
            'comm' => request()->routeIs('admin.announcements*') || request()->routeIs('admin.suggestions*') || request()->routeIs('admin.notifications'),
            'admin' => request()->routeIs('admin.users*') || request()->routeIs('admin.role.*') || request()->routeIs('admin.settings') || request()->routeIs('admin.pbl.*'),
        ];
    @endphp

    <div class="sidebar-group {{ $groupActive['main'] ? 'open' : '' }}" data-sidebar-group="admin-main" @if($groupActive['main']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="layout-dashboard"></i></span><span class="sidebar-group-label">Utama</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('dashboard'))
                <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard</a>
            @endif
            @if(P::canCurrent('ai_assistant'))
                <a href="{{ route('admin.ai') }}" class="sidebar-link {{ request()->routeIs('admin.ai*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="sparkles"></i></span>Asisten AI</a>
            @endif
            @if(P::canCurrent('monitoring'))
                <a href="{{ route('admin.monitoring') }}" class="sidebar-link {{ request()->routeIs('admin.monitoring') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="radio"></i></span>Monitoring</a>
            @endif
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['ops'] ? 'open' : '' }}" data-sidebar-group="admin-ops" @if($groupActive['ops']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="cpu"></i></span><span class="sidebar-group-label">Operasional IoT</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('mesin'))
                <a href="{{ route('admin.mesin.index') }}" class="sidebar-link {{ request()->routeIs('admin.mesin.*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="cpu"></i></span>Mesin IoT</a>
            @endif
            @if(P::canCurrent('settings'))
                <a href="{{ route('admin.absensi.settings') }}" class="sidebar-link {{ request()->routeIs('admin.absensi.settings') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="calendar"></i></span>Atur Absensi</a>
            @endif
            @if(P::canCurrent('fingerprint'))
                <a href="{{ route('admin.fingerprint') }}" class="sidebar-link {{ request()->routeIs('admin.fingerprint*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="fingerprint"></i></span>Fingerprint</a>
            @endif
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['audit'] ? 'open' : '' }}" data-sidebar-group="admin-audit" @if($groupActive['audit']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="bar-chart-3"></i></span><span class="sidebar-group-label">Rekap & Audit</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('attendance'))
                <a href="{{ route('admin.attendance') }}" class="sidebar-link {{ request()->routeIs('admin.attendance') || request()->routeIs('admin.absensi.alfa') || request()->routeIs('admin.absensi.tidak-lengkap') ? 'active' : '' }}" title="Rekap operasional hari ini"><span class="sidebar-link-icon"><i data-lucide="clipboard-check"></i></span>Rekap Harian</a>
            @endif
            @if(P::canCurrent('reports'))
                <a href="{{ route('admin.reports') }}" class="sidebar-link {{ request()->routeIs('admin.reports') ? 'active' : '' }}" title="Agregat periode absensi"><span class="sidebar-link-icon"><i data-lucide="bar-chart-3"></i></span>Laporan Periodik</a>
            @endif
            @if(P::canCurrent('log_absensi'))
                <a href="{{ route('admin.log.absensi') }}" class="sidebar-link {{ request()->routeIs('admin.log.absensi') ? 'active' : '' }}" title="Log mentah tap ESP32 — debug & audit"><span class="sidebar-link-icon"><i data-lucide="scroll-text"></i></span>Audit Absensi Mentah</a>
            @endif
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['comm'] ? 'open' : '' }}" data-sidebar-group="admin-comm" @if($groupActive['comm']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="bell"></i></span><span class="sidebar-group-label">Komunikasi</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('announcements'))
                <a href="{{ route('admin.announcements.index') }}" class="sidebar-link {{ request()->routeIs('admin.announcements*') ? 'active' : '' }}">
                    <span class="sidebar-link-icon"><i data-lucide="megaphone"></i></span>Pengumuman
                    <span id="sidebarAnnouncementBadge" class="sidebar-link-badge {{ $annBadge > 0 ? 'pulsing' : '' }}" style="{{ $annBadge > 0 ? '' : 'display:none;' }}">{{ $annBadge > 99 ? '99+' : $annBadge }}</span>
                </a>
            @endif
            @if(P::canCurrent('suggestions'))
                <a href="{{ route('admin.suggestions.index') }}" class="sidebar-link {{ request()->routeIs('admin.suggestions*') ? 'active' : '' }}">
                    <span class="sidebar-link-icon"><i data-lucide="message-square"></i></span>Saran &amp; Masukan
                    <span id="sidebarSuggestBadge" class="sidebar-link-badge {{ $newSuggest > 0 ? 'pulsing' : '' }}" style="{{ $newSuggest > 0 ? '' : 'display:none;' }}">{{ $newSuggest > 99 ? '99+' : $newSuggest }}</span>
                </a>
            @endif
            @if(P::canCurrent('notifications'))
                <a href="{{ route('admin.notifications') }}" class="sidebar-link {{ request()->routeIs('admin.notifications') ? 'active' : '' }}">
                    <span class="sidebar-link-icon"><i data-lucide="list-todo"></i></span>Antrian Notifikasi
                    <span id="sidebarOpsBadge" class="sidebar-link-badge" style="background:#f59e0b;{{ ($notifOpsCount ?? 0) > 0 ? '' : 'display:none;' }}">{{ ($notifOpsCount ?? 0) > 99 ? '99+' : ($notifOpsCount ?? 0) }}</span>
                </a>
            @endif
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['admin'] ? 'open' : '' }}" data-sidebar-group="admin-manage" @if($groupActive['admin']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="settings"></i></span><span class="sidebar-group-label">Administrasi</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('users'))
                <a href="{{ route('admin.users') }}" class="sidebar-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="users"></i></span>Manajemen User</a>
                <a href="{{ route('admin.pbl.index') }}" class="sidebar-link {{ request()->routeIs('admin.pbl.*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="folder-kanban"></i></span>Proyek PBL</a>
            @endif
            @if(P::canCurrent('settings'))
                <a href="{{ route('admin.settings') }}" class="sidebar-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="settings"></i></span>Pengaturan</a>
            @endif
        </div>
    </div>
</div>
