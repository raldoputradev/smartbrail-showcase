@extends('layouts.dashboard')

@section('title', 'Profil ' . $profileUser->nama_lengkap)
@section('page-title', auth()->id() === $profileUser->id ? 'Profil Saya' : 'Profil Pengguna')
@section('page-subtitle', $profileUser->isDosen()
    ? 'Akun dosen · data akademik & keterlibatan PBL'
    : ($profileUser->isLaboran() ? 'Akun admin laboran · identitas & akses panel' : 'Detail akun mahasiswa & aktivitas lab'))

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/profile-premium.css') }}?v=4">
@endsection

@section('sidebar-nav')
    @if(auth()->user()->role === 'laboran')
        @include('admin._sidebar')
    @elseif(auth()->user()->role === 'dosen')
        @include('dosen._sidebar')
    @else
        <div class="sidebar-section">
            <div class="sidebar-section-title">Mahasiswa</div>
            <a href="{{ route('mahasiswa.gate') }}" class="sidebar-link"><span class="sidebar-link-icon"><i data-lucide="smartphone"></i></span>Aplikasi mobile</a>
        </div>
    @endif
@endsection

@section('content')
@php
    $isLaboran = $profileUser->isLaboran();
    $isDosen = $profileUser->isDosen();
    $isMahasiswa = $profileUser->isMahasiswa();
    $roleKey = $isLaboran ? 'laboran' : ($isDosen ? 'dosen' : 'mahasiswa');
    $idLabel = $isDosen ? 'NIDN' : ($isLaboran ? 'NITK' : 'NIM');
    $canSeeSecurity = auth()->user()->isLaboran() || auth()->id() === $profileUser->id;
    $hasPhoto = (bool) $profileUser->profile_photo_url;
    $initial = strtoupper(mb_substr($profileUser->nama_lengkap, 0, 1));
    $roleLabel = $isLaboran ? 'Admin Laboran' : ($isDosen ? 'Dosen' : 'Mahasiswa');
    $roleIcon = $isLaboran ? 'shield-check' : ($isDosen ? 'graduation-cap' : 'user-round');
    $emailText = trim((string) $profileUser->email) !== '' ? $profileUser->email : 'Email belum diatur';
    $hasEmail = trim((string) $profileUser->email) !== '';
    $panelLabel = $isLaboran ? 'Panel Admin Laboran' : ($isDosen ? 'Panel Dosen' : 'Aplikasi Mobile Mahasiswa');
    $panelIcon = $isLaboran ? 'layout-dashboard' : ($isDosen ? 'presentation' : 'smartphone');
@endphp

    <div class="pf-page">
        @if(session('success'))
            <div class="pf-flash animate-in">
                <i data-lucide="check-circle-2"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- ── Hero identitas ── --}}
        <section class="pf-hero pf-hero--{{ $roleKey }} animate-in">
            <div class="pf-hero__cover">
                <span class="pf-hero__tag">
                    <i data-lucide="{{ $panelIcon }}"></i>{{ $panelLabel }}
                </span>
            </div>

            <div class="pf-hero__main">
                @if($hasPhoto)
                    <button type="button" class="pf-avatar"
                        data-photo-preview
                        data-photo-src="{{ $profileUser->profile_photo_url }}"
                        data-photo-name="{{ $profileUser->nama_lengkap }}"
                        data-photo-meta="{{ $roleLabel }} · {{ $idLabel }} {{ $profileUser->nitk_nidn }}"
                        title="Klik untuk memperbesar foto profil"
                        aria-label="Perbesar foto profil {{ $profileUser->nama_lengkap }}">
                        <img src="{{ $profileUser->profile_photo_url }}" alt="Foto profil {{ $profileUser->nama_lengkap }}">
                        <span class="pf-avatar__zoom"><i data-lucide="maximize-2"></i></span>
                    </button>
                @else
                    <div class="pf-avatar">{{ $initial }}</div>
                @endif

                <div class="pf-identity">
                    <h2 class="pf-identity__name">
                        {{ $profileUser->nama_lengkap }}
                        <span class="pf-role pf-role--{{ $roleKey }}">
                            <i data-lucide="{{ $roleIcon }}"></i>{{ $roleLabel }}
                        </span>
                    </h2>
                    <p class="pf-identity__mail">{{ $emailText }}</p>
                    <div class="pf-pills">
                        <span class="status-badge {{ $approvalBadge['tone'] }}">{{ $approvalBadge['label'] }}</span>
                        @if($profileUser->email_verified_at)
                            <span class="status-badge success">Email terverifikasi</span>
                        @else
                            <span class="status-badge warning">Email belum diverifikasi</span>
                        @endif
                        <span class="status-badge {{ $profileUser->is_active ? 'success' : 'danger' }}">
                            {{ $profileUser->is_active ? 'Akun aktif' : 'Akun nonaktif' }}
                        </span>
                    </div>
                </div>

                @if($canUploadPhoto)
                    <div class="pf-hero__actions">
                        <button type="button" class="btn-premium" id="pfPhotoToggle" aria-expanded="false" aria-controls="pfPhotoPanel">
                            @include('components.icon', ['name' => 'camera'])<span>{{ $hasPhoto ? 'Ubah foto' : 'Tambah foto' }}</span>
                        </button>
                        @if($hasPhoto)
                            <form method="POST" action="{{ route('profiles.photo.destroy') }}"
                                data-swal-confirm
                                data-swal-title="Hapus foto profil?"
                                data-swal-text="Avatar kembali ke inisial nama."
                                data-swal-icon="warning">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-premium" style="color:#e11d48;border-color:rgba(225,29,72,0.28);">
                                    @include('components.icon', ['name' => 'trash-2'])<span>Hapus</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            <div class="pf-facts">
                <div class="pf-fact">
                    <div class="pf-fact__label"><i data-lucide="hash"></i>{{ $idLabel }}</div>
                    <div class="pf-fact__value pf-fact__value--mono">{{ $profileUser->nitk_nidn ?: '—' }}</div>
                </div>
                <div class="pf-fact">
                    <div class="pf-fact__label"><i data-lucide="calendar-days"></i>Terdaftar</div>
                    <div class="pf-fact__value">{{ $profileUser->created_at?->translatedFormat('d M Y') ?? '—' }}</div>
                </div>
                @if($isDosen)
                    <div class="pf-fact">
                        <div class="pf-fact__label"><i data-lucide="folder-kanban"></i>Proyek PBL</div>
                        <div class="pf-fact__value">{{ $pblProjects->count() }} proyek aktif</div>
                    </div>
                @elseif($isLaboran)
                    <div class="pf-fact">
                        <div class="pf-fact__label"><i data-lucide="layout-dashboard"></i>Akses</div>
                        <div class="pf-fact__value">Panel admin laboran</div>
                    </div>
                @else
                    <div class="pf-fact">
                        <div class="pf-fact__label"><i data-lucide="users"></i>Tim PBL</div>
                        <div class="pf-fact__value">{{ $profileUser->tim ?: '—' }}</div>
                    </div>
                @endif
                <div class="pf-fact">
                    <div class="pf-fact__label"><i data-lucide="clock"></i>Diperbarui</div>
                    <div class="pf-fact__value">{{ $profileUser->updated_at?->translatedFormat('d M Y, H:i') ?? '—' }}</div>
                </div>
            </div>

            @if($canUploadPhoto)
                <div class="pf-photo" id="pfPhotoPanel">
                    <div class="pf-photo__row">
                        <form method="POST" action="{{ route('profiles.photo.update') }}" enctype="multipart/form-data" class="pf-photo__form" id="pfPhotoForm">
                            @csrf
                            <label class="pf-drop" for="pfPhotoInput">
                                <span class="pf-drop__ico"><i data-lucide="image-plus"></i></span>
                                <span class="pf-drop__txt">
                                    <strong id="pfPhotoName">Pilih foto profil</strong>
                                    <span>JPG, PNG, atau WEBP · maksimal 2 MB</span>
                                </span>
                                <input type="file" id="pfPhotoInput" name="profile_photo"
                                    accept="image/png,image/jpeg,image/jpg,image/webp" required>
                            </label>
                            <button type="submit" class="btn-premium btn-premium--primary">
                                @include('components.icon', ['name' => 'upload'])<span>Simpan foto</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </section>

        <div class="pf-cols">
            {{-- ── Kolom utama ── --}}
            <div class="pf-stack">
                <div class="pf-card animate-in">
                    <div class="pf-card__head">
                        <div class="pf-card__title">
                            <span class="pf-card__ico"><i data-lucide="id-card"></i></span>
                            {{ $isMahasiswa ? 'Data mahasiswa' : ($isDosen ? 'Data kepegawaian dosen' : 'Data akun admin') }}
                        </div>
                        @if(auth()->id() === $profileUser->id)
                            <span class="pf-card__hint">Perubahan data inti lewat admin laboran</span>
                        @endif
                    </div>
                    <div class="pf-card__body">
                        <div class="pf-fields">
                            @if($isLaboran)
                                <div class="pf-field">
                                    <span class="pf-field__label">NITK</span>
                                    <span class="pf-field__value pf-field__value--mono">{{ $profileUser->nitk_nidn ?: '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Email</span>
                                    <span class="pf-field__value {{ $hasEmail ? '' : 'pf-field__value--muted' }}">{{ $emailText }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Peran sistem</span>
                                    <span class="pf-field__value">{{ $profileUser->role_display }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Cakupan kerja</span>
                                    <span class="pf-field__value">Operasional lab &amp; verifikasi akun</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Terdaftar</span>
                                    <span class="pf-field__value">{{ $profileUser->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Email diverifikasi</span>
                                    <span class="pf-field__value {{ $profileUser->email_verified_at ? '' : 'pf-field__value--muted' }}">
                                        {{ $profileUser->email_verified_at?->translatedFormat('d M Y, H:i') ?? 'Belum diverifikasi' }}
                                    </span>
                                </div>
                            @elseif($isDosen)
                                <div class="pf-field">
                                    <span class="pf-field__label">NIDN</span>
                                    <span class="pf-field__value pf-field__value--mono">{{ $profileUser->nitk_nidn ?: '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Email</span>
                                    <span class="pf-field__value {{ $hasEmail ? '' : 'pf-field__value--muted' }}">{{ $emailText }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Peran sistem</span>
                                    <span class="pf-field__value">{{ $profileUser->role_display }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Program studi</span>
                                    <span class="pf-field__value {{ $profileUser->programStudi ? '' : 'pf-field__value--muted' }}">
                                        {{ $profileUser->programStudi?->nama ?? 'Tidak diatur' }}
                                    </span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Peran PBL</span>
                                    <span class="pf-field__value">
                                        @php
                                            $asManpro = $pblProjects->where('manpro_user_id', $profileUser->id)->count();
                                            $asCo = $pblProjects->where('co_manpro_user_id', $profileUser->id)->count();
                                        @endphp
                                        @if($asManpro || $asCo)
                                            {{ $asManpro ? $asManpro . ' manpro' : '' }}{{ $asManpro && $asCo ? ' · ' : '' }}{{ $asCo ? $asCo . ' co-manpro' : '' }}
                                        @else
                                            Belum ditugaskan
                                        @endif
                                    </span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Email diverifikasi</span>
                                    <span class="pf-field__value {{ $profileUser->email_verified_at ? '' : 'pf-field__value--muted' }}">
                                        {{ $profileUser->email_verified_at?->translatedFormat('d M Y, H:i') ?? 'Belum diverifikasi' }}
                                    </span>
                                </div>
                            @else
                                <div class="pf-field">
                                    <span class="pf-field__label">NIM</span>
                                    <span class="pf-field__value pf-field__value--mono">{{ $profileUser->nitk_nidn }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Email</span>
                                    <span class="pf-field__value {{ $hasEmail ? '' : 'pf-field__value--muted' }}">{{ $emailText }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Kelas</span>
                                    <span class="pf-field__value">{{ $profileUser->kelas ?: '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Tim PBL</span>
                                    <span class="pf-field__value">{{ $profileUser->tim ?: '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Prodi</span>
                                    <span class="pf-field__value">{{ $profileUser->programStudi?->nama ?? '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Semester</span>
                                    <span class="pf-field__value">{{ $profileUser->semester ? $profileUser->semester . ($profileUser->semester_type ? ' · ' . ucfirst($profileUser->semester_type) : '') : '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Angkatan</span>
                                    <span class="pf-field__value">{{ $profileUser->angkatan ?? '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Fingerprint ID</span>
                                    <span class="pf-field__value pf-field__value--mono">{{ $profileUser->finger_id ?: '—' }}</span>
                                </div>
                                <div class="pf-field">
                                    <span class="pf-field__label">Login mobile terakhir</span>
                                    <span class="pf-field__value {{ $profileUser->mobile_last_login_at ? '' : 'pf-field__value--muted' }}">
                                        {{ $profileUser->mobile_last_login_at?->translatedFormat('d M Y, H:i') ?? 'Belum pernah' }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($isDosen)
                    <div class="pf-card animate-in">
                        <div class="pf-card__head">
                            <div class="pf-card__title">
                                <span class="pf-card__ico pf-card__ico--violet"><i data-lucide="folder-kanban"></i></span>
                                Proyek PBL yang dibimbing
                            </div>
                            <span class="pf-card__hint">Diatur admin di menu Proyek PBL</span>
                        </div>
                        <div class="pf-card__body">
                            @if($pblProjects->isNotEmpty())
                                <div class="pf-pbl">
                                    @foreach($pblProjects as $proj)
                                        <div class="pf-pbl__item">
                                            <span class="pf-pbl__ico"><i data-lucide="cpu"></i></span>
                                            <span class="pf-pbl__txt">
                                                <strong>{{ $proj->nama }}</strong>
                                                <span>Kode: {{ $proj->kode ?: '—' }}</span>
                                            </span>
                                            @if($proj->manpro_user_id === $profileUser->id)
                                                <span class="status-badge success">Manpro utama</span>
                                            @elseif($proj->co_manpro_user_id === $profileUser->id)
                                                <span class="status-badge">Co-manpro</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="pf-empty">
                                    <i data-lucide="info"></i>
                                    <span>
                                        Belum terhubung ke proyek PBL. Admin laboran mengaitkan dosen lewat menu <strong>Proyek PBL</strong>.
                                        Tanpa ini, modul <strong>Pantauan Loker</strong> terbatas pada tim PBL Anda — rekap absensi tetap mencakup seluruh mahasiswa.
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if(auth()->id() === $profileUser->id && ($isLaboran || $isDosen))
                    <div class="pf-card animate-in">
                        <div class="pf-card__head">
                            <div class="pf-card__title">
                                <span class="pf-card__ico pf-card__ico--teal"><i data-lucide="compass"></i></span>
                                Pintasan kerja
                            </div>
                            <span class="pf-card__hint">Sesuai hak akses peran Anda</span>
                        </div>
                        <div class="pf-card__body">
                            <div class="pf-links">
                                @if($isLaboran)
                                    @if(\App\Support\PermissionRegistry::canCurrent('users', 'view'))
                                        <a href="{{ route('admin.users') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="user-check"></i></span>
                                            <span class="pf-link__txt"><strong>Manajemen User</strong><span>Verifikasi, aktivasi, dan data akun</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                    @if(\App\Support\PermissionRegistry::canCurrent('notifications', 'view'))
                                        <a href="{{ route('admin.notifications') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="bell"></i></span>
                                            <span class="pf-link__txt"><strong>Antrian Notifikasi</strong><span>Persetujuan akun & tindak lanjut</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                    @if(\App\Support\PermissionRegistry::canCurrent('announcements', 'view'))
                                        <a href="{{ route('admin.announcements.index') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="megaphone"></i></span>
                                            <span class="pf-link__txt"><strong>Pengumuman</strong><span>Info resmi ke dosen & mahasiswa</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                    @if(\App\Support\PermissionRegistry::canCurrent('settings', 'view'))
                                        <a href="{{ route('admin.settings') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="settings"></i></span>
                                            <span class="pf-link__txt"><strong>Pengaturan</strong><span>Kebijakan lab & konfigurasi sistem</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                @else
                                    @if(\App\Support\PermissionRegistry::canCurrent('dosen_mahasiswa', 'view'))
                                        <a href="{{ route('dosen.mahasiswa') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="users"></i></span>
                                            <span class="pf-link__txt"><strong>Mahasiswa Bimbingan</strong><span>Anggota tim PBL Anda</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                    @if(\App\Support\PermissionRegistry::canCurrent('dosen_absensi', 'view'))
                                        <a href="{{ route('dosen.absensi') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="fingerprint"></i></span>
                                            <span class="pf-link__txt"><strong>Rekap Absensi</strong><span>Kehadiran lab per periode</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                    @if(\App\Support\PermissionRegistry::canCurrent('dosen_absensi', 'view'))
                                        <a href="{{ route('dosen.reports') }}" class="pf-link">
                                            <span class="pf-link__ico"><i data-lucide="file-bar-chart"></i></span>
                                            <span class="pf-link__txt"><strong>Laporan</strong><span>Unduh rekap untuk evaluasi</span></span>
                                            <span class="pf-link__go"><i data-lucide="chevron-right"></i></span>
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if($isMahasiswa)
                    <div class="stat-cards">
                        @include('partials.stat-card', ['tone' => 'blue', 'icon' => 'fingerprint', 'label' => 'Total absensi', 'value' => number_format($stats['absensi'] ?? 0), 'meta' => 'Tap di mesin lab'])
                        @include('partials.stat-card', ['tone' => 'teal', 'icon' => 'clock', 'label' => 'Absensi terakhir', 'value' => $stats['last_absensi']?->waktu_tap?->translatedFormat('d/m H:i') ?? '—', 'meta' => 'Waktu tap terakhir'])
                    </div>

                    <div class="pf-split">
                        <div class="pf-card animate-in">
                            <div class="pf-card__head">
                                <div class="pf-card__title">
                                    <span class="pf-card__ico"><i data-lucide="fingerprint"></i></span>
                                    Riwayat absensi
                                </div>
                            </div>
                            <div class="pf-card__body pf-card__body--flush">
                                <table class="data-table">
                                    <thead><tr><th>Waktu</th><th>Mesin</th></tr></thead>
                                    <tbody>
                                        @forelse($recentAbsensi as $log)
                                            <tr>
                                                <td style="font-family:monospace;font-size:12px;">{{ $log->waktu_tap?->translatedFormat('d M Y H:i') }}</td>
                                                <td style="font-family:monospace;">{{ $log->id_mesin }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" style="text-align:center;padding:20px;color:var(--text-muted);">Belum ada riwayat.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ── Kolom samping ── --}}
            <aside class="pf-stack">
                <div class="pf-card animate-in">
                    <div class="pf-card__head">
                        <div class="pf-card__title">
                            <span class="pf-card__ico pf-card__ico--teal"><i data-lucide="shield-check"></i></span>
                            Status akun
                        </div>
                    </div>
                    <div class="pf-card__body">
                        <div class="pf-rows">
                            <div class="pf-row">
                                <span class="pf-row__k"><i data-lucide="badge-check"></i>Persetujuan</span>
                                <span class="pf-row__v"><span class="status-badge {{ $approvalBadge['tone'] }}">{{ $approvalBadge['label'] }}</span></span>
                            </div>
                            <div class="pf-row">
                                <span class="pf-row__k"><i data-lucide="mail-check"></i>Email</span>
                                <span class="pf-row__v">{{ $profileUser->email_verified_at ? 'Terverifikasi' : 'Belum diverifikasi' }}</span>
                            </div>
                            <div class="pf-row">
                                <span class="pf-row__k"><i data-lucide="power"></i>Akses login</span>
                                <span class="pf-row__v">{{ $profileUser->is_active ? 'Aktif' : 'Dinonaktifkan' }}</span>
                            </div>
                            @if($canSeeSecurity && $profileUser->needsAdminLoginUnlock())
                                <div class="pf-row">
                                    <span class="pf-row__k"><i data-lucide="lock"></i>Kunci login</span>
                                    <span class="pf-row__v"><span class="status-badge danger">Perlu buka kunci admin</span></span>
                                </div>
                            @elseif($canSeeSecurity && $profileUser->isLoginTemporarilyLocked())
                                <div class="pf-row">
                                    <span class="pf-row__k"><i data-lucide="timer"></i>Kunci login</span>
                                    <span class="pf-row__v">Sampai {{ $profileUser->login_locked_until->translatedFormat('H:i') }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="pf-note">
                            <strong>Asal akun:</strong> {{ $accountOrigin['label'] }}
                            @if($accountOrigin['at'])
                                · {{ $accountOrigin['at']->translatedFormat('d M Y, H:i') }}
                            @endif
                            <span class="pf-note__sub">{{ $accountOrigin['detail'] }}</span>
                            @if($profileUser->approved_at && ($profileUser->approval_status ?? '') === 'approved')
                                <span class="pf-note__sub">
                                    Disetujui {{ $profileUser->approved_at->translatedFormat('d M Y, H:i') }}@if($profileUser->approvedBy) oleh {{ $profileUser->approvedBy->nama_lengkap }}@endif.
                                </span>
                            @endif
                        </div>

                        @if(($profileUser->approval_status ?? 'pending') === 'pending' && $isDosen)
                            <div class="pf-note pf-note--warn">
                                <strong>Menunggu persetujuan admin.</strong>
                                <span class="pf-note__sub">Akun dosen bisa login setelah email diverifikasi dan admin menyetujui.</span>
                            </div>
                        @elseif(($profileUser->approval_status ?? 'pending') === 'pending' && $isMahasiswa)
                            <div class="pf-note pf-note--warn">
                                <strong>Menunggu persetujuan admin.</strong>
                                <span class="pf-note__sub">Mahasiswa dapat login ke aplikasi mobile setelah akun disetujui.</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if($approvalTimeline->isNotEmpty() && $canSeeSecurity)
                    <div class="pf-card animate-in">
                        <div class="pf-card__head">
                            <div class="pf-card__title">
                                <span class="pf-card__ico pf-card__ico--violet"><i data-lucide="history"></i></span>
                                Riwayat persetujuan
                            </div>
                            <span class="pf-card__hint">{{ $approvalTimeline->count() }} log terakhir</span>
                        </div>
                        <div class="pf-card__body">
                            <div class="pf-timeline">
                                @foreach($approvalTimeline as $log)
                                    @php
                                        $action = (string) $log->action;
                                        $tone = str_contains($action, 'reject') ? 'bad'
                                            : (str_contains($action, 'approve') ? 'ok'
                                            : (str_contains($action, 'lock') || str_contains($action, 'deactivate') ? 'warn' : ''));
                                    @endphp
                                    <div class="pf-timeline__item {{ $tone ? 'pf-timeline__item--' . $tone : '' }}">
                                        <div class="pf-timeline__act">{{ str_replace('_', ' ', $action) }}</div>
                                        @if($log->note)
                                            <div class="pf-timeline__note">{{ $log->note }}</div>
                                        @endif
                                        <div class="pf-timeline__time">
                                            {{ $log->created_at->translatedFormat('d M Y H:i') }}@if($log->actor) · {{ $log->actor->nama_lengkap }}@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.refreshLucideIcons) window.refreshLucideIcons();

    // Panel ganti foto + nama file terpilih
    const toggle = document.getElementById('pfPhotoToggle');
    const panel = document.getElementById('pfPhotoPanel');
    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            const open = panel.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) document.getElementById('pfPhotoInput')?.focus();
        });
    }

    const input = document.getElementById('pfPhotoInput');
    const fileName = document.getElementById('pfPhotoName');
    if (input && fileName) {
        input.addEventListener('change', function () {
            fileName.textContent = input.files && input.files.length
                ? input.files[0].name
                : 'Pilih foto profil';
        });
    }
});
</script>
@endsection
