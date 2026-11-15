@php use App\Support\PermissionRegistry as P; @endphp
<div class="sidebar-section">
    <div class="sidebar-section-title">Menu Dosen</div>

    @php
        $annBadge = $sidebarAnnouncementBadge ?? 0;
        $groupActive = [
            'main' => request()->routeIs('dosen.dashboard'),
            'rekap' => request()->routeIs('dosen.absensi') || request()->routeIs('dosen.absensi.alfa') || request()->routeIs('dosen.absensi.tidak-lengkap') || request()->routeIs('dosen.reports'),
            'academic' => request()->routeIs('dosen.mahasiswa') || request()->routeIs('dosen.ruangan'),
            'pbl' => request()->routeIs('dosen.pbl*'),
            'comm' => request()->routeIs('dosen.announcements') || request()->routeIs('dosen.feedback*'),
            'account' => request()->routeIs('dosen.profile') || request()->routeIs('profiles.show'),
        ];
    @endphp

    <div class="sidebar-group {{ $groupActive['main'] ? 'open' : '' }}" data-sidebar-group="dosen-main" @if($groupActive['main']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="layout-dashboard"></i></span><span class="sidebar-group-label">Utama</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('dashboard'))
                <a href="{{ route('dosen.dashboard') }}" class="sidebar-link {{ request()->routeIs('dosen.dashboard') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard</a>
            @endif
            @if(P::canCurrent('ai_assistant') && (P::canCurrent('dosen_absensi') || P::canCurrent('dosen_mahasiswa')))
                <a href="{{ route('dosen.ai') }}" class="sidebar-link"><span class="sidebar-link-icon"><i data-lucide="sparkles"></i></span>Asisten AI
                    @if(\App\Support\FeatureBadge::tag('asisten-ai'))<span class="sidebar-link-tag sidebar-link-tag--new">Baru</span>@endif</a>
            @endif
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['rekap'] ? 'open' : '' }}" data-sidebar-group="dosen-rekap" @if($groupActive['rekap']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="bar-chart-3"></i></span><span class="sidebar-group-label">Rekap Absensi</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('dosen_absensi'))
                <a href="{{ route('dosen.absensi') }}" class="sidebar-link {{ request()->routeIs('dosen.absensi') || request()->routeIs('dosen.absensi.alfa') || request()->routeIs('dosen.absensi.tidak-lengkap') ? 'active' : '' }}" title="Rekap operasional hari ini"><span class="sidebar-link-icon"><i data-lucide="clipboard-check"></i></span>Rekap Harian</a>
                <a href="{{ route('dosen.reports') }}" class="sidebar-link {{ request()->routeIs('dosen.reports*') ? 'active' : '' }}" title="Agregat periode absensi"><span class="sidebar-link-icon"><i data-lucide="bar-chart-3"></i></span>Laporan Periodik</a>
            @endif
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['academic'] ? 'open' : '' }}" data-sidebar-group="dosen-academic" @if($groupActive['academic']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="graduation-cap"></i></span><span class="sidebar-group-label">Akademik</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('dosen_pbl'))
                {{-- Proyek PBL dinonaktifkan dari navigasi (data & histori dipertahankan) --}}
            @endif
            @if(P::canCurrent('dosen_mahasiswa'))
                <a href="{{ route('dosen.mahasiswa') }}" class="sidebar-link {{ request()->routeIs('dosen.mahasiswa') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="users"></i></span>Data Mahasiswa</a>
            @endif
            @if(P::canCurrent('dosen_pbl'))
                <a href="{{ route('dosen.ruangan') }}" class="sidebar-link {{ request()->routeIs('dosen.ruangan') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="door-open"></i></span>Pengajuan Ruangan</a>
            @endif
        </div>
    </div>

    @if(P::canCurrent('dosen_pbl'))
    <div class="sidebar-group {{ $groupActive['pbl'] ? 'open' : '' }}" data-sidebar-group="dosen-pbl" @if($groupActive['pbl']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="clipboard-list"></i></span><span class="sidebar-group-label">Proyek PBL</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            <a href="{{ route('dosen.pbl.index') }}" class="sidebar-link {{ request()->routeIs('dosen.pbl*') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="clipboard-list"></i></span>Proyek PBL Saya</a>
            <a href="#" class="sidebar-link" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'Segera hadir',text:'Fitur ini masih tahap pengembangan.',confirmButtonText:'Mengerti',confirmButtonColor:'#4a90d9'});"><span class="sidebar-link-icon"><i data-lucide="users"></i></span>Manajemen Tim<span class="sidebar-link-tag">Soon</span></a>
            <a href="#" class="sidebar-link" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'Segera hadir',text:'Fitur ini masih tahap pengembangan.',confirmButtonText:'Mengerti',confirmButtonColor:'#4a90d9'});"><span class="sidebar-link-icon"><i data-lucide="clipboard-check"></i></span>Penilaian PBL<span class="sidebar-link-tag">Soon</span></a>
            <a href="#" class="sidebar-link" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'Segera hadir',text:'Grup chat antar manpro, ketua tim, dan anggota tim PBL — masih dalam rancangan pengembangan.',confirmButtonText:'Mengerti',confirmButtonColor:'#4a90d9'});"><span class="sidebar-link-icon"><i data-lucide="messages-square"></i></span>Grup Chat Tim<span class="sidebar-link-tag">Soon</span></a>
        </div>
    </div>
    @endif

    <div class="sidebar-group {{ $groupActive['comm'] ? 'open' : '' }}" data-sidebar-group="dosen-comm" @if($groupActive['comm']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="megaphone"></i></span><span class="sidebar-group-label">Komunikasi</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('announcements'))
                <a href="{{ route('dosen.announcements') }}" class="sidebar-link {{ request()->routeIs('dosen.announcements') ? 'active' : '' }}">
                    <span class="sidebar-link-icon"><i data-lucide="megaphone"></i></span>Pengumuman
                    <span id="sidebarAnnouncementBadge" class="sidebar-link-badge {{ $annBadge > 0 ? 'pulsing' : '' }}" style="{{ $annBadge > 0 ? '' : 'display:none;' }}">{{ $annBadge > 99 ? '99+' : $annBadge }}</span>
                </a>
            @endif
            <a href="{{ route('dosen.feedback.index') }}" class="sidebar-link {{ request()->routeIs('dosen.feedback*') ? 'active' : '' }}">
                <span class="sidebar-link-icon"><i data-lucide="message-square"></i></span>Saran &amp; Masukan
            </a>
        </div>
    </div>

    <div class="sidebar-group {{ $groupActive['account'] ? 'open' : '' }}" data-sidebar-group="dosen-account" @if($groupActive['account']) data-active-group="1" @endif>
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle><span class="sidebar-link-icon"><i data-lucide="user"></i></span><span class="sidebar-group-label">Akun</span><span class="sidebar-group-caret">›</span></button>
        <div class="sidebar-submenu">
            @if(P::canCurrent('dosen_profile'))
                <a href="{{ route('dosen.profile') }}" class="sidebar-link {{ request()->routeIs('dosen.profile') || request()->routeIs('profiles.show') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="user"></i></span>Profil Saya</a>
                <a href="{{ route('profiles.show', Auth::user()) }}#status-akun" class="sidebar-link {{ request()->routeIs('profiles.show') ? 'active' : '' }}"><span class="sidebar-link-icon"><i data-lucide="shield-check"></i></span>Keamanan Akun</a>
            @endif
        </div>
    </div>
</div>
