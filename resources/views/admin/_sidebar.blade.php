@php use App\Support\PermissionRegistry as P; @endphp
@php
    // Struktur identik dengan sidebar Admin React (AdminShell):
    // seksi → tautan langsung / grup collapsible ber-sub-menu.
    $annBadge = $sidebarAnnouncementBadge ?? 0;
    $newSuggest = class_exists(\App\Models\Suggestion::class) ? \App\Models\Suggestion::where('status','baru')->count() : 0;
    $opsBadge = $notifOpsCount ?? 0;

    $rolePageActive = request()->routeIs('admin.users.role') ? request()->route('role') : null;

    // Grup berisi halaman aktif → terbuka otomatis & tidak ditutup JS.
    $gAbsensi  = request()->routeIs('admin.mesin*') || request()->routeIs('admin.monitoring')
                || request()->routeIs('admin.absensi.settings') || request()->routeIs('admin.fingerprint*')
                || request()->routeIs('admin.kalender') || request()->routeIs('admin.log.absensi*');
    $gRuangan  = request()->routeIs('admin.ruangan*') || request()->routeIs('admin.pengajuan*')
                || request()->routeIs('admin.petugas*');
    $gUsers    = request()->routeIs('admin.users*');
    $gPbl      = request()->routeIs('admin.pbl*');
    $gKom      = request()->routeIs('admin.announcements*') || request()->routeIs('admin.suggestions*')
                || request()->routeIs('admin.notifications');
    $gLaporan  = request()->routeIs('admin.attendance') || request()->routeIs('admin.absensi.alfa')
                || request()->routeIs('admin.absensi.tidak-lengkap*') || request()->routeIs('admin.reports*');
@endphp

{{-- MENU ADMIN --}}
<div class="sidebar-section">
    <div class="sidebar-section-title">Menu Admin</div>
    @if(P::canCurrent('dashboard'))
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard</a>
    @endif
    @if(P::canCurrent('ai_assistant'))
        <a href="{{ route('admin.ai') }}" class="sidebar-link {{ request()->routeIs('admin.ai*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="sparkles"></i></span>Asisten AI
            @if(\App\Support\FeatureBadge::tag('asisten-ai'))<span class="sidebar-link-tag sidebar-link-tag--new">Baru</span>@endif</a>
        <a href="{{ route('admin.ai.training') }}" class="sidebar-link {{ request()->routeIs('admin.ai.training*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="graduation-cap"></i></span>Pelatihan AI
            @if(\App\Support\FeatureBadge::tag('pelatihan-ai'))<span class="sidebar-link-tag sidebar-link-tag--new">Baru</span>@endif</a>
    @endif
</div>
<hr class="sidebar-sep">

{{-- OPERASIONAL LABORATORIUM --}}
@if(P::canCurrent('mesin') || P::canCurrent('settings') || P::canCurrent('fingerprint') || P::canCurrent('log_absensi') || P::canCurrent('ruangan') || P::canCurrent('ruangan_persetujuan') || P::canCurrent('petugas'))
<div class="sidebar-section">
    <div class="sidebar-section-title">Operasional Laboratorium</div>

    @if(P::canCurrent('mesin') || P::canCurrent('settings') || P::canCurrent('fingerprint') || P::canCurrent('log_absensi'))
    <div class="sidebar-group {{ $gAbsensi ? 'open' : '' }}" data-sidebar-group="absensi-perangkat" @if($gAbsensi) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="clipboard-list"></i></span><span class="sidebar-group-label">Perangkat</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('mesin'))
                <a href="{{ route('admin.mesin.index') }}" class="sidebar-link {{ request()->routeIs('admin.mesin.index') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="cpu"></i></span>Mesin</a>
                <a href="{{ route('admin.mesin.panduan') }}" class="sidebar-link {{ request()->routeIs('admin.mesin.panduan') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="file-code"></i></span>Program Mesin
                    @if(\App\Support\FeatureBadge::tag('program-mesin'))<span class="sidebar-link-tag sidebar-link-tag--new">Baru</span>@endif</a>
            @endif
            @if(P::canCurrent('monitoring'))
                <a href="{{ route('admin.monitoring') }}" class="sidebar-link {{ request()->routeIs('admin.monitoring*') ? 'active' : '' }}" title="Pantau heartbeat & status perangkat/ruangan"><span class="sidebar-link-icon"><i data-lucide="activity"></i></span>Monitoring Operasional</a>
            @endif
            @if(P::canCurrent('settings'))
                <a href="{{ route('admin.absensi.settings') }}" class="sidebar-link {{ request()->routeIs('admin.absensi.settings') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="calendar"></i></span>Atur Absensi</a>
            @endif
            @if(P::canCurrent('attendance'))
                <a href="{{ route('admin.kalender') }}" class="sidebar-link {{ request()->routeIs('admin.kalender') ? 'active' : '' }}" title="Kalender & agenda laboratorium"><span class="sidebar-link-icon"><i data-lucide="calendar-days"></i></span>Kalender &amp; Agenda</a>
            @endif
            @if(P::canCurrent('fingerprint'))
                <a href="{{ route('admin.fingerprint') }}" class="sidebar-link {{ request()->routeIs('admin.fingerprint*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="fingerprint"></i></span>Fingerprint</a>
            @endif
            @if(P::canCurrent('log_absensi'))
                <a href="{{ route('admin.log.absensi') }}" class="sidebar-link {{ request()->routeIs('admin.log.absensi*') ? 'active' : '' }}" title="Log mentah tap ESP32"><span class="sidebar-link-icon"><i data-lucide="scroll-text"></i></span>Log Tap</a>
            @endif
        </div>
    </div>
    @endif

    @if(P::canCurrent('ruangan') || P::canCurrent('ruangan_persetujuan') || P::canCurrent('petugas'))
    <div class="sidebar-group {{ $gRuangan ? 'open' : '' }}" data-sidebar-group="manajemen-ruangan" @if($gRuangan) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="building-2"></i></span><span class="sidebar-group-label">Manajemen Ruangan</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('ruangan'))
                <a href="{{ route('admin.ruangan.index') }}" class="sidebar-link {{ request()->routeIs('admin.ruangan*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="door-open"></i></span>Ruangan</a>
            @endif
            @if(P::canCurrent('ruangan_persetujuan'))
                <a href="{{ route('admin.pengajuan.index') }}" class="sidebar-link {{ request()->routeIs('admin.pengajuan*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="calendar-check"></i></span>Pengajuan Ruangan</a>
            @endif
            @if(P::canCurrent('petugas'))
                <a href="{{ route('admin.petugas.index') }}" class="sidebar-link {{ request()->routeIs('admin.petugas*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="brush"></i></span>Petugas Ruangan</a>
            @endif
        </div>
    </div>
    @endif
</div>
<hr class="sidebar-sep">
@endif

{{-- DATA & PENGGUNA — lihat untuk pemilik perm users; mutasi & import tetap admin-only --}}
@if(P::canCurrent('users'))
<div class="sidebar-section">
    <div class="sidebar-section-title">Data &amp; Pengguna</div>

    @if(P::canCurrent('users'))
    <div class="sidebar-group {{ $gUsers ? 'open' : '' }}" data-sidebar-group="data-pengguna" @if($gUsers) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="users"></i></span><span class="sidebar-group-label">Data Pengguna</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            <a href="{{ route('admin.users.role', 'mahasiswa') }}" class="sidebar-link {{ $rolePageActive === 'mahasiswa' ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="graduation-cap"></i></span>Mahasiswa</a>
            <a href="{{ route('admin.users.role', 'dosen') }}" class="sidebar-link {{ $rolePageActive === 'dosen' ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="book-open"></i></span>Dosen</a>
            <a href="{{ route('admin.users.role', 'laboran') }}" class="sidebar-link {{ $rolePageActive === 'laboran' ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="flask-conical"></i></span>Laboran</a>
            <a href="{{ route('admin.users') }}" class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="users"></i></span>Semua Pengguna</a>
            @if(auth()->user()->isAdmin() && \App\Support\PermissionRegistry::canCurrent('users', 'create'))
            <a href="{{ route('admin.users.import') }}" class="sidebar-link {{ request()->routeIs('admin.users.import*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="file-up"></i></span>Import Akun</a>
            @endif
        </div>
    </div>
    @endif

    <div class="sidebar-group {{ $gPbl ? 'open' : '' }}" data-sidebar-group="proyek-pbl" @if($gPbl) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="clipboard-list"></i></span><span class="sidebar-group-label">Proyek PBL</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            <a href="{{ route('admin.pbl.index') }}" class="sidebar-link {{ $gPbl ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="clipboard-list"></i></span>Kelola Proyek</a>
            <a href="#" class="sidebar-link" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'Segera hadir',text:'Fitur ini masih tahap pengembangan.',confirmButtonText:'Mengerti',confirmButtonColor:'#4a90d9'});"><span class="sidebar-link-icon"><i data-lucide="clipboard-check"></i></span>Penilaian &amp; Export<span class="sidebar-link-tag">Soon</span></a>
            <a href="#" class="sidebar-link" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'Segera hadir',text:'Grup chat antar manpro, ketua tim, dan anggota tim PBL — masih dalam rancangan pengembangan.',confirmButtonText:'Mengerti',confirmButtonColor:'#4a90d9'});"><span class="sidebar-link-icon"><i data-lucide="messages-square"></i></span>Grup Chat Tim<span class="sidebar-link-tag">Soon</span></a>
        </div>
    </div>
</div>
<hr class="sidebar-sep">
@endif

{{-- KOMUNIKASI — grup top-level --}}
@if(P::canCurrent('announcements') || P::canCurrent('suggestions') || P::canCurrent('notifications'))
<div class="sidebar-section">
    <div class="sidebar-group {{ $gKom ? 'open' : '' }}" data-sidebar-group="komunikasi" @if($gKom) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="megaphone"></i></span><span class="sidebar-group-label">Komunikasi</span><span class="sidebar-group-caret">›</span></button>
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
                    <span id="sidebarOpsBadge" class="sidebar-link-badge" style="background:#f59e0b;{{ $opsBadge > 0 ? '' : 'display:none;' }}">{{ $opsBadge > 99 ? '99+' : $opsBadge }}</span>
                </a>
            @endif
        </div>
    </div>
</div>
<hr class="sidebar-sep">
@endif

{{-- LAPORAN & AUDIT — grup top-level --}}
@if(P::canCurrent('attendance') || P::canCurrent('reports'))
<div class="sidebar-section">
    <div class="sidebar-group {{ $gLaporan ? 'open' : '' }}" data-sidebar-group="laporan-audit" @if($gLaporan) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="file-text"></i></span><span class="sidebar-group-label">Laporan &amp; Audit</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('attendance'))
                <a href="{{ route('admin.attendance') }}" class="sidebar-link {{ request()->routeIs('admin.attendance') || request()->routeIs('admin.absensi.alfa') || request()->routeIs('admin.absensi.tidak-lengkap*') ? 'active' : '' }}" title="Rekap operasional hari ini"><span class="sidebar-link-icon"><i data-lucide="clipboard-check"></i></span>Rekap Harian</a>
            @endif
            @if(P::canCurrent('reports'))
                <a href="{{ route('admin.reports') }}" class="sidebar-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}" title="Agregat periode absensi"><span class="sidebar-link-icon"><i data-lucide="bar-chart-3"></i></span>Laporan Periodik</a>
            @endif
        </div>
    </div>
</div>
<hr class="sidebar-sep">
@endif

{{-- ADMINISTRASI — khusus kewenangan admin --}}
@if(auth()->user()->isAdmin() && P::canCurrent('settings'))
<div class="sidebar-section">
    <div class="sidebar-section-title">Administrasi</div>
    <a href="{{ route('admin.settings') }}" class="sidebar-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="settings"></i></span>Pengaturan</a>
</div>
@endif

{{-- AKUN — profil & keamanan untuk semua peran di shell ini --}}
<div class="sidebar-section">
    <div class="sidebar-section-title">Akun</div>
    <a href="{{ route('profiles.show', Auth::user()) }}" class="sidebar-link {{ request()->routeIs('profiles.show') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="user"></i></span>Profil Saya</a>
</div>
