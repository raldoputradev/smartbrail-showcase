@extends('layouts.dashboard')
@php
    use App\Support\ApprovalActionLabel;
    use App\Support\PermissionRegistry as P;
@endphp

@section('title', 'Manajemen User')
@section('page-title', 'Manajemen User')
@section('page-subtitle', 'Persetujuan akun · daftar pengguna · riwayat ringkas')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/users-mgmt-premium.css') }}?v=6">
@endsection

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    <div class="users-mgmt-page">
        @php $stats = $userStats ?? []; @endphp

        <div class="um-top animate-in">
            <div>
                <h2 class="um-top__title">
                    Manajemen User
                    <button type="button" class="users-help-q" onclick="openUsersHelp()" title="Panduan" aria-label="Panduan">?</button>
                </h2>
                <p class="um-top__sub">{{ number_format($stats['total'] ?? 0) }} user · {{ number_format($stats['pending_pipeline'] ?? 0) }} antrian · enroll {{ number_format($stats['enroll_pct'] ?? 0) }}%</p>
            </div>
            @if(P::canCurrent('users', 'create'))
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="{{ route('admin.users.import') }}" class="btn-premium" style="text-decoration:none;border:1px solid var(--border-subtle);">
                        @include('components.icon', ['name' => 'upload'])<span>Import Akun</span>
                    </a>
                    <button type="button" class="btn-premium btn-premium--primary" onclick="openUserModal('create')">
                        @include('components.icon', ['name' => 'user-plus'])<span>Tambah User</span>
                    </button>
                </div>
            @endif
        </div>

        @if(($awaitingActivationCount ?? 0) > 0)
            <div class="users-pending-banner animate-in" style="border-left-color:#0ea5e9;margin-bottom:12px;">
                <div>
                    <div class="users-pending-banner__title">
                        @include('components.icon', ['name' => 'key'])
                        <span>{{ $awaitingActivationCount }} akun hasil import menunggu aktivasi</span>
                    </div>
                    <p>Pemilik akun mengaktifkan sendiri di halaman Aktivasi Akun; tidak perlu disetujui admin.</p>
                </div>
                <a href="{{ route('admin.users', ['verify_status' => 'menunggu_aktivasi']) }}" class="btn-premium" style="text-decoration:none;border:1px solid var(--border-subtle);">Lihat</a>
            </div>
        @endif

        @if(($pendingOtpCount ?? 0) > 0)
            <div class="users-pending-banner animate-in" style="border-left-color:#6366f1;margin-bottom:12px;">
                <div>
                    <div class="users-pending-banner__title">
                        @include('components.icon', ['name' => 'mail'])
                        <span>{{ $pendingOtpCount }} akun menunggu verifikasi OTP</span>
                    </div>
                    <p>Belum bisa disetujui sampai OTP selesai.</p>
                </div>
                <a href="{{ route('admin.users', ['verify_status' => 'otp_pending']) }}" class="btn-premium" style="text-decoration:none;border:1px solid var(--border-subtle);">Lihat</a>
            </div>
        @endif

        @if(($pendingApprovalCount ?? 0) > 0)
            <div class="users-pending-banner animate-in">
                <div>
                    <div class="users-pending-banner__title">
                        @include('components.icon', ['name' => 'user-check'])
                        <span>{{ $pendingApprovalCount }} akun siap disetujui</span>
                    </div>
                    <p>Setujui dari kolom Aksi atau centang beberapa baris.</p>
                </div>
                <a href="{{ route('admin.users', ['verify_status' => 'ready_approve']) }}" class="btn-premium btn-premium--primary" style="text-decoration:none;">Lihat</a>
            </div>
        @endif

        @if(($loginLockedCount ?? 0) > 0)
            <div class="users-pending-banner animate-in" style="border-left-color:#dc2626;margin-bottom:12px;">
                <div>
                    <div class="users-pending-banner__title">
                        @include('components.icon', ['name' => 'lock'])
                        <span>{{ $loginLockedCount }} akun terkunci login</span>
                    </div>
                    <p>Buka kunci dari kolom Aksi.</p>
                </div>
                <a href="{{ route('admin.users', ['login_lock' => 'locked']) }}" class="btn-premium btn-premium--primary" style="text-decoration:none;">Lihat</a>
            </div>
        @endif

        @if(session('success'))
            <div class="panel-info-strip animate-in" style="margin-bottom:16px;border-color:rgba(15,118,110,0.3);">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="panel-info-strip animate-in" style="margin-bottom:16px;border-color:rgba(220,38,38,0.35);color:#b91c1c;">{{ session('error') }}</div>
        @endif

        @php $umTab = request('um_tab') === 'arsip' || ($logExpanded ?? false) ? 'arsip' : 'list'; @endphp
        <div class="um-tabs animate-in" id="umTabs" data-active="{{ $umTab }}">
            <button type="button" class="um-tabs__btn {{ $umTab === 'list' ? 'is-active' : '' }}" data-um-tab="list">Daftar Pengguna</button>
            <button type="button" class="um-tabs__btn {{ $umTab === 'arsip' ? 'is-active' : '' }}" data-um-tab="arsip">Arsip Approval <span class="um-tabs__count">{{ number_format($approvalLogs->total()) }}</span></button>
        </div>

        <div class="um-board animate-in" id="umPanelList" data-um-panel="list" @if($umTab === 'arsip') hidden @endif>
            <section class="um-main card">
                <div class="um-main__head">
                    <div>
                        <h3>Daftar Pengguna</h3>
                        <p id="umListSubtitle">{{ $users->total() }} akun · halaman {{ $users->currentPage() }}</p>
                    </div>
                    <div class="um-main__head-actions">
                        @if(P::canCurrent('users', 'update'))
                            <button type="submit" form="bulkApproveForm" class="btn-premium btn-premium--primary" id="bulkApproveBtn" disabled style="font-size:12px;">
                                @include('components.icon', ['name' => 'check-circle'])
                                <span>Setujui terpilih (<span id="bulkApproveCount">0</span>)</span>
                            </button>
                        @endif
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.users') }}" class="um-toolbar" id="umToolbar" data-um-ajax>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama / email / NIM…">
                    <select name="role">
                        <option value="">Role</option>
                        <option value="dosen" @selected(request('role') === 'dosen')>Dosen</option>
                        <option value="mahasiswa" @selected(request('role') === 'mahasiswa')>Mahasiswa</option>
                        <option value="laboran" @selected(request('role') === 'laboran')>Laboran</option>
                    </select>
                    <select name="approval_status">
                        <option value="">Persetujuan</option>
                        <option value="pending" @selected(request('approval_status') === 'pending')>Menunggu</option>
                        <option value="approved" @selected(request('approval_status') === 'approved')>Disetujui</option>
                        <option value="rejected" @selected(request('approval_status') === 'rejected')>Ditolak</option>
                    </select>
                    <select name="verify_status" id="umVerifyStatus">
                        <option value="">Verifikasi</option>
                        <option value="otp_pending" @selected(request('verify_status') === 'otp_pending')>OTP</option>
                        <option value="ready_approve" @selected(request('verify_status') === 'ready_approve')>Siap</option>
                        <option value="all" @selected(request('verify_status') === 'all')>Semua tahap</option>
                    </select>
                    <select name="status">
                        <option value="">Status</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                    </select>
                    <select name="login_lock" id="umLoginLock">
                        <option value="">Login</option>
                        <option value="locked" @selected(request('login_lock') === 'locked')>Terkunci</option>
                    </select>
                    <select name="angkatan">
                        <option value="">Angkatan</option>
                        @foreach($angkatanYears ?? \App\Support\MahasiswaProfileOptions::angkatanYears() as $year)
                            <option value="{{ $year }}" @selected((string) request('angkatan') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="semester">
                        <option value="">Sem</option>
                        @foreach(range(1, 8) as $semester)
                            <option value="{{ $semester }}" @selected((string) request('semester') === (string) $semester)>{{ $semester }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-premium btn-premium--primary um-toolbar__btn">Filter</button>
                    <button type="button" class="users-reset-btn" id="umToolbarReset" title="Reset filter" @if(!request()->hasAny(['q','role','status','approval_status','verify_status','login_lock','angkatan','semester'])) style="display:none" @endif>
                        @include('components.icon', ['name' => 'rotate-ccw'])
                    </button>
                </form>

                @if(P::canCurrent('users', 'update'))
                    <form method="POST" action="{{ route('admin.users.bulk-approve') }}" id="bulkApproveForm" data-swal-confirm
                        data-swal-title="Setujui akun terpilih?"
                        data-swal-text="Akun yang sudah verifikasi OTP akan disetujui sekaligus dan menerima notifikasi."
                        data-swal-icon="question"
                        data-swal-confirm-button="Ya, Setujui Semua"
                        data-swal-confirm-color="#22c55e">
                        @csrf
                    </form>
                @endif

                <div id="umListFragment" class="um-list-fragment">
                    @include('admin.partials.users-list-fragment')
                </div>
            </section>

            <aside class="um-side">
                <section class="card um-side__card">
                    <h3>Komposisi peran</h3>
                    <div class="um-side__chart">
                        <canvas id="usersRoleDonut" height="180"></canvas>
                    </div>
                    <div class="users-donut-legend" id="usersRoleLegend"></div>
                    <div class="users-enroll-bar" style="margin-top:12px;">
                        <i style="width:{{ (int) ($stats['enroll_pct'] ?? 0) }}%"></i>
                    </div>
                    <small class="users-enroll-caption">{{ number_format($stats['enroll_pct'] ?? 0) }}% mahasiswa enroll sidik jari</small>
                </section>

                <section class="card um-side__card um-actions">
                    <div class="um-actions__head">
                        <h3>Aksi cepat</h3>
                        <button type="button" class="um-actions__reset" id="umQuickReset" title="Kembali ke tampilan awal">Reset</button>
                    </div>
                    <p class="um-actions__hint" id="umQuickHint">Filter daftar tanpa reload halaman.</p>
                    <button type="button" class="um-actions__btn" data-um-quick='{"verify_status":"ready_approve"}'>
                        Siap disetujui <span>{{ number_format($pendingApprovalCount ?? 0) }}</span>
                    </button>
                    <button type="button" class="um-actions__btn" data-um-quick='{"verify_status":"otp_pending"}'>
                        Tunggu OTP <span>{{ number_format($pendingOtpCount ?? 0) }}</span>
                    </button>
                    <button type="button" class="um-actions__btn" data-um-quick='{"login_lock":"locked"}'>
                        Login terkunci <span>{{ number_format($loginLockedCount ?? 0) }}</span>
                    </button>
                    <a href="{{ route('admin.pbl.index') }}" class="um-actions__btn um-actions__btn--ghost">Proyek PBL →</a>
                    <button type="button" class="um-actions__btn um-actions__btn--ghost" data-um-quick='{"role":"mahasiswa"}'>Filter mahasiswa →</button>
                </section>
            </aside>
        </div>

        <section class="um-arsip card animate-in" id="umPanelArsip" data-um-panel="arsip" @if($umTab !== 'arsip') hidden @endif>
            <div class="um-arsip__head">
                <div>
                    <h3>Arsip Approval & Registrasi</h3>
                    <p>Log persetujuan, OTP, registrasi, dan hapus akun — {{ $approvalLogs->total() }} entri.</p>
                </div>
            </div>
            <div class="um-arsip__filters">
                <form method="GET" action="{{ route('admin.users') }}" class="um-toolbar um-toolbar--arsip" id="umLogToolbar">
                    <input type="hidden" name="um_tab" value="arsip">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="role" value="{{ request('role') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="approval_status" value="{{ request('approval_status') }}">
                    <input type="hidden" name="verify_status" value="{{ request('verify_status') }}">
                    <input type="hidden" name="login_lock" value="{{ request('login_lock') }}">
                    <input type="hidden" name="angkatan" value="{{ request('angkatan') }}">
                    <input type="hidden" name="semester" value="{{ request('semester') }}">
                    <input type="search" name="log_q" value="{{ request('log_q') }}" placeholder="Cari user / admin / catatan">
                    <select name="log_action">
                        <option value="">Semua aksi</option>
                        <option value="registered" @selected(request('log_action') === 'registered')>Registrasi</option>
                        <option value="email_verified" @selected(request('log_action') === 'email_verified')>Email diverifikasi</option>
                        <option value="approved" @selected(request('log_action') === 'approved')>Disetujui</option>
                        <option value="rejected" @selected(request('log_action') === 'rejected')>Ditolak</option>
                        <option value="deactivated" @selected(request('log_action') === 'deactivated')>Dinonaktifkan</option>
                        <option value="activated" @selected(request('log_action') === 'activated')>Diaktifkan</option>
                        <option value="admin_created" @selected(request('log_action') === 'admin_created')>Dibuat admin</option>
                        <option value="deleted" @selected(request('log_action') === 'deleted')>Dihapus permanen</option>
                    </select>
                    <select name="log_role">
                        <option value="">Semua role</option>
                        <option value="mahasiswa" @selected(request('log_role') === 'mahasiswa')>Mahasiswa</option>
                        <option value="dosen" @selected(request('log_role') === 'dosen')>Dosen</option>
                        <option value="laboran" @selected(request('log_role') === 'laboran')>Laboran</option>
                    </select>
                    <select name="log_actor">
                        <option value="">Semua admin / sistem</option>
                        <option value="system" @selected(request('log_actor') === 'system')>Sistem / pendaftar</option>
                        @foreach($approvalActors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) request('log_actor') === (string) $actor->id)>
                                {{ $actor->nama_lengkap }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-premium btn-premium--primary um-toolbar__btn">Filter arsip</button>
                </form>
            </div>
            <div class="um-table-wrap">
                <table class="data-table um-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>Diproses Oleh</th>
                            <th>IP</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($approvalLogs as $log)
                            @php
                                $meta = is_array($log->subject_meta) ? $log->subject_meta : [];
                                $metaParts = [];
                                if (!empty($meta['finger_id'])) { $metaParts[] = 'Finger ID ' . $meta['finger_id']; }
                                if (!empty($meta['kelas'])) { $metaParts[] = 'Kelas ' . $meta['kelas']; }
                                if (!empty($meta['tim'])) { $metaParts[] = 'Tim ' . $meta['tim']; }
                                if (!empty($meta['angkatan'])) { $metaParts[] = 'Angkatan ' . $meta['angkatan']; }
                                if (!empty($meta['semester'])) {
                                    $semType = ($meta['semester_type'] ?? '') === 'genap' ? 'Genap' : 'Ganjil';
                                    $metaParts[] = 'Sem ' . $meta['semester'] . ' ' . $semType;
                                }
                                $logIcon = match ($log->action) {
                                    'approved', 'activated' => 'check-circle',
                                    'rejected', 'deleted', 'deactivated' => 'x-circle',
                                    default => 'activity',
                                };
                                $subjectRole = $log->subjectDisplayRole();
                                $avatarTone = $subjectRole === 'mahasiswa' ? 'teal' : ($subjectRole === 'dosen' ? 'blue' : 'amber');
                            @endphp
                            <tr>
                                <td style="font-family:monospace;font-size:12px;">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <div class="users-name-cell">
                                        @include('partials.user-avatar', [
                                            'user' => $log->user,
                                            'name' => $log->subjectDisplayName(),
                                            'size' => 34,
                                            'tone' => $avatarTone,
                                        ])
                                        <div class="users-name-cell__text">
                                            <strong class="users-name-cell__plain">{{ $log->subjectDisplayName() }}</strong>
                                            <span class="users-name-cell__meta">
                                                {{ $log->subjectDisplayEmail() }} · {{ $log->subjectDisplayNitk() }}
                                                @if($subjectRole) · {{ ucfirst($subjectRole) }} @endif
                                            </span>
                                            @if($log->action === 'deleted' && $metaParts !== [])
                                                <span class="users-name-cell__meta">{{ implode(' · ', $metaParts) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-badge--icon {{ ApprovalActionLabel::tone($log->action) }}">
                                        @include('components.icon', ['name' => $logIcon, 'class' => 'status-badge__icon'])
                                        {{ ApprovalActionLabel::label($log->action) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->actor)
                                        <div class="users-name-cell users-name-cell--compact">
                                            @include('partials.user-avatar', ['user' => $log->actor, 'size' => 28, 'tone' => 'blue'])
                                            <div class="users-name-cell__text">
                                                <strong class="users-name-cell__plain">{{ $log->actor->nama_lengkap }}</strong>
                                                <span class="users-name-cell__meta">{{ $log->actor->role_display ?? $log->actor->role }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <span style="font-weight:600;color:var(--text-muted);">Sistem / pendaftar</span>
                                    @endif
                                </td>
                                <td style="font-family:monospace;font-size:12px;">{{ $log->ip_address ?: '—' }}</td>
                                <td style="max-width:280px;color:var(--text-muted);font-size:12px;">{{ $log->note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="um-empty">Belum ada riwayat approval.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="um-pager">@include('components.table-pagination', ['paginator' => $approvalLogs])</div>
        </section>

    </div>

    @if(P::canCurrent('users', 'create') || P::canCurrent('users', 'update'))
        <div class="user-modal-backdrop" id="userModal">
            <div class="user-modal">
                <button type="button" class="user-modal-close" onclick="closeUserModal()">×</button>
                <div class="user-modal-badge" id="userModalBadge">TAMBAH USER</div>
                <h3 id="userModalTitle">Tambah User</h3>
                <p style="font-size:13px;color:var(--text-muted);line-height:1.6;margin-bottom:14px;">
                    Centang <strong>Aktifkan akun</strong> untuk langsung menyetujui. Kosongkan jika akun harus menunggu persetujuan admin.
                </p>

                <form method="POST" id="userForm" action="{{ route('admin.users.store') }}" style="display:grid;gap:12px;">
                    @csrf
                    <input type="hidden" name="_method" id="userMethod" value="POST" disabled>

                    <div class="user-form-grid">
                        <div>
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="userName" required>
                        </div>
                        <div>
                            <label>NITK/NIDN/NIM</label>
                            <input type="text" name="nitk_nidn" id="userNitk" required>
                        </div>
                        <div>
                            <label>Email <span style="color:var(--text-muted);font-weight:500;">(opsional)</span></label>
                            <input type="email" name="email" id="userEmail" placeholder="Kosongkan jika verifikasi mandiri saat login">
                            <small style="display:block;margin-top:4px;color:var(--text-muted);font-size:11.5px;line-height:1.45;">
                                Jika dikosongkan, pengguna wajib mengisi &amp; verifikasi email sendiri saat pertama kali login.
                            </small>
                        </div>
                        <div>
                            <label>Password <span id="passwordHint" style="color:var(--text-muted);font-weight:500;">(wajib)</span></label>
                            <input type="password" name="password" id="userPassword" minlength="6" required placeholder="Minimal 6 karakter">
                        </div>
                        <div>
                            <label>Role</label>
                            <select name="role" id="userRole" required onchange="toggleStudentFields()">
                                <option value="dosen">Dosen</option>
                                <option value="mahasiswa">Mahasiswa</option>
                                <option value="laboran">Laboran</option>
                            </select>
                        </div>
                        <label class="user-check">
                            <input type="checkbox" name="is_active" value="1" id="userActive">
                            <span>
                                <strong>Aktifkan & setujui akun</strong>
                                <small>Jika tidak dicentang, user menunggu persetujuan manual.</small>
                            </span>
                        </label>
                    </div>

                    <div class="student-fields" id="studentFields">
                        <div>
                            <label>Kelas</label>
                            <select name="kelas" id="userKelas">
                                <option value="">Pilih kelas</option>
                                @foreach(\App\Support\MahasiswaProfileOptions::kelasOptions() as $kelasOpt)
                                    <option value="{{ $kelasOpt }}">{{ $kelasOpt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Program Studi</label>
                            <select name="program_studi_id" id="userProgramStudi">
                                <option value="">Pilih prodi</option>
                                @foreach($programStudi ?? [] as $prodi)
                                    <option value="{{ $prodi->id }}">{{ $prodi->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Shift absensi</label>
                            <select name="shift" id="userShift">
                                <option value="pagi">Pagi</option>
                                <option value="malam">Malam</option>
                            </select>
                        </div>
                        <div>
                            <label>Tim PBL</label>
                            <select name="tim" id="userTim">
                                <option value="">Pilih tim PBL</option>
                                @foreach($pblTimOptions ?? \App\Support\MahasiswaProfileOptions::pblTeamOptions() as $timOpt)
                                    <option value="{{ $timOpt }}">{{ $timOpt }}</option>
                                @endforeach
                            </select>
                            <small style="display:block;margin-top:4px;font-size:11px;color:var(--text-muted);">
                                Daftar dari <a href="{{ route('admin.pbl.index') }}" style="color:var(--soft-blue);font-weight:700;">Proyek PBL</a>.
                            </small>
                        </div>
                        <div>
                            <label>Angkatan</label>
                            <select name="angkatan" id="userAngkatan">
                                <option value="">Pilih angkatan</option>
                                @foreach($angkatanYears ?? \App\Support\MahasiswaProfileOptions::angkatanYears() as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Semester</label>
                            <select name="semester" id="userSemester">
                                <option value="">Pilih semester</option>
                                @foreach(range(1, 8) as $semester)
                                    <option value="{{ $semester }}">Semester {{ $semester }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Periode Semester</label>
                            <select name="semester_type" id="userSemesterType">
                                <option value="">Pilih periode</option>
                                <option value="ganjil">Ganjil</option>
                                <option value="genap">Genap</option>
                            </select>
                        </div>
                        <div id="userFingerInfo" style="grid-column:1/-1;">
                            <label>Finger ID</label>
                            <p id="userFingerHint" style="margin:0;padding:11px 12px;border:1px dashed var(--border-subtle);border-radius:10px;background:var(--bg-main);font-size:12.5px;color:var(--text-muted);line-height:1.5;">
                                Diisi otomatis saat mahasiswa disimpan. Ubah manual lewat menu <strong>Fingerprint</strong> jika diperlukan.
                            </p>
                            <p id="userFingerCurrent" style="display:none;margin:6px 0 0;font-size:12px;font-weight:700;color:var(--text-dark);"></p>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px;">
                        <button type="button" class="btn-premium" onclick="closeUserModal()">Batal</button>
                        <button type="submit" class="btn-premium btn-premium--primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="rejectUserModal" class="user-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="rejectUserModalTitle">
        <div class="user-modal" style="width:min(520px,100%);">
            <div class="user-modal__header">
                <span class="user-modal__badge" style="background:rgba(220,38,38,0.12);color:#b91c1c;">TOLAK AKUN</span>
                <h3 id="rejectUserModalTitle" style="margin:8px 0 0;">Tolak pendaftaran</h3>
                <p id="rejectUserModalName" style="margin:6px 0 0;font-size:13px;color:var(--text-muted);"></p>
            </div>
            <div style="padding:0 22px 22px;">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">Pilih alasan cepat atau tulis catatan sendiri (wajib):</p>
                <div class="reject-reason-chips">
                    @foreach([
                        ['label' => 'Data tidak valid', 'text' => 'Data pendaftaran tidak valid atau tidak lengkap.'],
                        ['label' => 'NIDN/NIM tidak terdaftar', 'text' => 'NIDN/NIM tidak terdaftar di data institusi.'],
                        ['label' => 'Duplikat akun', 'text' => 'Terindikasi duplikat akun.'],
                        ['label' => 'Tidak memenuhi syarat lab', 'text' => 'Tidak memenuhi persyaratan laboratorium.'],
                    ] as $tpl)
                        <button type="button" class="reject-template-btn" data-template="{{ $tpl['text'] }}">{{ $tpl['label'] }}</button>
                    @endforeach
                </div>
                <label for="rejectUserNote" style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Catatan penolakan</label>
                <textarea id="rejectUserNote" rows="4" maxlength="500" placeholder="Alasan penolakan akan dikirim ke email & notifikasi pengguna..." style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--border-subtle);font-family:inherit;font-size:13px;resize:vertical;"></textarea>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                    <button type="button" class="btn-premium" onclick="closeRejectUserModal()">Batal</button>
                    <button type="button" class="btn-premium btn-premium--primary" id="confirmRejectUserBtn" style="background:#dc2626;border-color:#dc2626;">Tolak akun</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/simalas-charts.js') }}?v=8"></script>
<style>
    .user-modal-backdrop {
        position: fixed; inset: 0; z-index: 3000; display: none;
        align-items: center; justify-content: center; padding: 22px;
        background: rgba(15, 23, 42, 0.68); backdrop-filter: blur(8px);
    }
    .user-modal {
        width: min(780px, 100%); max-height: 92vh; overflow-y: auto; position: relative;
        border: 1px solid rgba(148,163,184,0.28); border-radius: 22px; padding: 24px;
        background: var(--bg-card); color: var(--text-dark); box-shadow: 0 24px 70px rgba(15,23,42,0.32);
    }
    .user-modal-close {
        position: absolute; top: 14px; right: 14px; width: 34px; height: 34px; border: 0;
        border-radius: 999px; background: rgba(148,163,184,0.14); color: var(--text-dark);
        font-size: 20px; cursor: pointer;
    }
    .user-modal-badge {
        display: inline-flex; padding: 6px 10px; border-radius: 999px;
        background: rgba(74,144,217,0.14); color: #2563eb; font-size: 11px; font-weight: 800; letter-spacing: 0.08em;
    }
    .user-modal h3 { margin: 8px 0; font-size: 22px; }
    .user-form-grid, .student-fields {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;
    }
    .student-fields {
        grid-template-columns: repeat(3, minmax(0, 1fr)); padding: 14px;
        border: 1px solid var(--border-subtle); border-radius: 14px; background: rgba(45,212,191,0.06);
    }
    .user-modal label {
        display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color: var(--text-label);
    }
    .user-modal input, .user-modal select {
        width: 100%; padding: 11px 12px; border: 1px solid var(--border-subtle);
        border-radius: 10px; background: var(--bg-main); color: var(--text-dark); font-family: inherit;
    }
    .user-check {
        display: flex !important; align-items: center; gap: 10px; padding: 11px 12px;
        border: 1px solid var(--border-subtle); border-radius: 10px; background: var(--bg-main); margin: 0 !important;
    }
    .user-check input { width: auto; }
    .user-check small { display: block; color: var(--text-muted); font-weight: 500; }
    @media (max-width: 900px) {
        .user-form-grid, .student-fields, form[action="{{ route('admin.users') }}"] { grid-template-columns: 1fr !important; }
    }
</style>
<script>
    function ensureTimOption(value) {
        const sel = document.getElementById('userTim');
        if (!sel || !value) return;
        const exists = Array.from(sel.options).some((o) => o.value === value);
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value + ' (di luar daftar)';
            sel.appendChild(opt);
        }
        sel.value = value;
    }

    function openUserModal(mode, button = null) {
        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        const method = document.getElementById('userMethod');
        const title = document.getElementById('userModalTitle');
        const badge = document.getElementById('userModalBadge');
        const password = document.getElementById('userPassword');
        const passwordHint = document.getElementById('passwordHint');
        if (!modal || !form || !method || !title || !badge || !password || !passwordHint) return;

        form.reset();
        document.getElementById('userActive').checked = false;

        if (mode === 'edit' && button) {
            form.action = button.dataset.updateUrl;
            method.disabled = false;
            method.value = 'PUT';
            title.textContent = 'Edit User';
            badge.textContent = 'EDIT USER';
            password.required = false;
            password.value = '';
            password.placeholder = 'Kosongkan jika tidak diganti';
            passwordHint.textContent = '(opsional)';
            document.getElementById('userName').value = button.dataset.name || '';
            document.getElementById('userNitk').value = button.dataset.nitk || '';
            document.getElementById('userEmail').value = button.dataset.email || '';
            document.getElementById('userRole').value = button.dataset.role || 'dosen';
            document.getElementById('userKelas').value = button.dataset.kelas || '';
            ensureTimOption(button.dataset.tim || '');
            document.getElementById('userProgramStudi').value = button.dataset.programStudiId || '';
            document.getElementById('userShift').value = button.dataset.shift || 'pagi';
            document.getElementById('userAngkatan').value = button.dataset.angkatan || '';
            document.getElementById('userSemester').value = button.dataset.semester || '';
            document.getElementById('userSemesterType').value = button.dataset.semesterType || '';
            const fingerCurrent = document.getElementById('userFingerCurrent');
            if (fingerCurrent) {
                if (button.dataset.fingerId) {
                    fingerCurrent.style.display = 'block';
                    fingerCurrent.textContent = 'Finger ID saat ini: #' + button.dataset.fingerId;
                } else {
                    fingerCurrent.style.display = 'none';
                    fingerCurrent.textContent = '';
                }
            }
            document.getElementById('userActive').checked = button.dataset.active === '1';
        } else {
            form.action = '{{ route('admin.users.store') }}';
            method.disabled = true;
            method.value = 'POST';
            title.textContent = 'Tambah User';
            badge.textContent = 'TAMBAH USER';
            password.required = true;
            password.placeholder = 'Minimal 6 karakter';
            passwordHint.textContent = '(wajib)';
            const fingerCurrent = document.getElementById('userFingerCurrent');
            if (fingerCurrent) {
                fingerCurrent.style.display = 'none';
                fingerCurrent.textContent = '';
            }
        }
        toggleStudentFields();
        modal.style.display = 'flex';
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    function closeUserModal() {
        const modal = document.getElementById('userModal');
        if (modal) modal.style.display = 'none';
    }

    function toggleStudentFields() {
        const role = document.getElementById('userRole')?.value;
        const fields = document.getElementById('studentFields');
        if (!fields) return;
        const isStudent = role === 'mahasiswa';
        fields.style.display = isStudent ? 'grid' : 'none';
        ['userKelas', 'userTim', 'userProgramStudi', 'userShift', 'userAngkatan', 'userSemester', 'userSemesterType'].forEach((id) => {
            const input = document.getElementById(id);
            if (input) input.required = isStudent;
        });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.reject-user-btn');
        if (!btn) return;
        const form = btn.closest('.reject-user-form');
        if (!form) return;
        e.preventDefault();
        openRejectUserModal(form, btn.dataset.userName || 'Pengguna');
    });

    let activeRejectForm = null;

    function openRejectUserModal(form, userName) {
        activeRejectForm = form;
        const modal = document.getElementById('rejectUserModal');
        const nameEl = document.getElementById('rejectUserModalName');
        const noteEl = document.getElementById('rejectUserNote');
        if (!modal || !noteEl) return;
        if (nameEl) nameEl.textContent = userName;
        noteEl.value = '';
        modal.style.display = 'flex';
        noteEl.focus();
    }

    function closeRejectUserModal() {
        const modal = document.getElementById('rejectUserModal');
        if (modal) modal.style.display = 'none';
        activeRejectForm = null;
    }

    document.querySelectorAll('.reject-template-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const noteEl = document.getElementById('rejectUserNote');
            if (noteEl) noteEl.value = btn.dataset.template || '';
        });
    });

    document.getElementById('confirmRejectUserBtn')?.addEventListener('click', function () {
        const noteEl = document.getElementById('rejectUserNote');
        const reason = noteEl?.value?.trim() || '';
        if (!reason || !activeRejectForm) return;
        activeRejectForm.querySelector('input[name="approval_note"]').value = reason;
        activeRejectForm.submit();
    });

    document.getElementById('rejectUserModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeRejectUserModal();
    });

    (function initUsersMgmtBoard() {
        const listUrl = @json(route('admin.users'));
        const fragment = document.getElementById('umListFragment');
        const toolbar = document.getElementById('umToolbar');
        const subtitle = document.getElementById('umListSubtitle');
        const resetBtn = document.getElementById('umToolbarReset');
        const quickHint = document.getElementById('umQuickHint');
        let loading = false;

        function refreshBulkApprove() {
            const btn = document.getElementById('bulkApproveBtn');
            const countEl = document.getElementById('bulkApproveCount');
            if (!btn || !countEl) return;
            const n = document.querySelectorAll('.bulk-approve-check:checked').length;
            countEl.textContent = String(n);
            btn.disabled = n === 0;
            const selectAll = document.getElementById('bulkSelectAll');
            if (selectAll) {
                const all = document.querySelectorAll('.bulk-approve-check');
                selectAll.checked = all.length > 0 && n === all.length;
                selectAll.indeterminate = n > 0 && n < all.length;
            }
        }

        function bindFragmentControls() {
            refreshBulkApprove();
            document.querySelectorAll('.bulk-approve-check').forEach((cb) => {
                cb.addEventListener('change', refreshBulkApprove);
            });
            const selectAll = document.getElementById('bulkSelectAll');
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.bulk-approve-check').forEach((cb) => {
                        cb.checked = selectAll.checked;
                    });
                    refreshBulkApprove();
                });
            }
            if (window.refreshLucideIcons) window.refreshLucideIcons();
        }

        function syncResetVisibility(params) {
            const keys = ['q', 'role', 'status', 'approval_status', 'verify_status', 'login_lock', 'angkatan', 'semester'];
            const active = keys.some((k) => params.get(k));
            if (resetBtn) resetBtn.style.display = active ? '' : 'none';
            document.querySelectorAll('[data-um-quick]').forEach((el) => {
                let match = false;
                try {
                    const spec = JSON.parse(el.getAttribute('data-um-quick') || '{}');
                    match = Object.keys(spec).every((k) => String(params.get(k) || '') === String(spec[k]));
                } catch (e) {}
                el.classList.toggle('is-active', match);
            });
            if (quickHint) {
                quickHint.textContent = active
                    ? 'Filter aktif — klik Reset untuk tampilan awal.'
                    : 'Filter daftar tanpa reload halaman.';
            }
        }

        function formParams(extra = {}) {
            const fd = new FormData(toolbar);
            const params = new URLSearchParams();
            for (const [k, v] of fd.entries()) {
                if (String(v).trim() !== '') params.set(k, String(v));
            }
            Object.entries(extra).forEach(([k, v]) => {
                if (v === null || v === undefined || v === '') params.delete(k);
                else params.set(k, String(v));
            });
            return params;
        }

        async function loadList(params, { push = true } = {}) {
            if (!fragment || loading) return;
            loading = true;
            fragment.classList.add('is-loading');
            params.set('fragment', 'list');
            const url = listUrl + '?' + params.toString();
            try {
                const res = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-UM-Fragment': 'list',
                        'Accept': 'text/html',
                    },
                });
                if (!res.ok) throw new Error('Gagal memuat daftar');
                const html = await res.text();
                fragment.innerHTML = html;
                params.delete('fragment');
                if (push) {
                    const clean = listUrl + (params.toString() ? '?' + params.toString() : '');
                    history.pushState({ um: true }, '', clean);
                }
                const meta = fragment.querySelector('.um-list-meta');
                if (subtitle && meta) {
                    subtitle.textContent = meta.textContent.trim();
                }
                syncResetVisibility(params);
                bindFragmentControls();
            } catch (err) {
                console.error(err);
                window.location.href = listUrl + '?' + params.toString().replace(/fragment=list&?/, '');
            } finally {
                loading = false;
                fragment.classList.remove('is-loading');
            }
        }

        function clearToolbar() {
            if (!toolbar) return;
            toolbar.querySelectorAll('input[type="search"], input[type="text"]').forEach((el) => { el.value = ''; });
            toolbar.querySelectorAll('select').forEach((el) => { el.value = ''; });
        }

        function applyQuick(spec) {
            clearToolbar();
            Object.entries(spec).forEach(([k, v]) => {
                const el = toolbar?.elements?.namedItem(k);
                if (el) el.value = v;
            });
            loadList(formParams());
        }

        toolbar?.addEventListener('submit', function (e) {
            e.preventDefault();
            loadList(formParams());
        });

        resetBtn?.addEventListener('click', function () {
            clearToolbar();
            loadList(new URLSearchParams());
        });

        document.getElementById('umQuickReset')?.addEventListener('click', function () {
            clearToolbar();
            loadList(new URLSearchParams());
            switchTab('list');
        });

        document.querySelectorAll('[data-um-quick]').forEach((btn) => {
            btn.addEventListener('click', function () {
                let spec = {};
                try { spec = JSON.parse(btn.getAttribute('data-um-quick') || '{}'); } catch (e) {}
                switchTab('list');
                applyQuick(spec);
            });
        });

        // pagination inside fragment (event delegation)
        fragment?.addEventListener('click', function (e) {
            const a = e.target.closest('a');
            if (!a || !fragment.contains(a)) return;
            const href = a.getAttribute('href') || '';
            if (!href || href === '#' || a.target === '_blank') return;
            try {
                const u = new URL(a.href, window.location.origin);
                if (u.pathname !== new URL(listUrl, window.location.origin).pathname) return;
                e.preventDefault();
                loadList(u.searchParams);
            } catch (err) {}
        });

        window.addEventListener('popstate', function () {
            const params = new URLSearchParams(window.location.search);
            // sync toolbar
            if (toolbar) {
                clearToolbar();
                params.forEach((v, k) => {
                    const el = toolbar.elements?.namedItem(k);
                    if (el && 'value' in el) el.value = v;
                });
            }
            const tab = params.get('um_tab') === 'arsip' ? 'arsip' : 'list';
            switchTab(tab, false);
            if (tab === 'list') loadList(params, { push: false });
        });

        function switchTab(tab, updateUrl = true) {
            document.querySelectorAll('[data-um-tab]').forEach((b) => {
                b.classList.toggle('is-active', b.getAttribute('data-um-tab') === tab);
            });
            const list = document.getElementById('umPanelList');
            const arsip = document.getElementById('umPanelArsip');
            if (list) list.hidden = tab !== 'list';
            if (arsip) arsip.hidden = tab !== 'arsip';
            if (updateUrl) {
                const params = new URLSearchParams(window.location.search);
                if (tab === 'arsip') params.set('um_tab', 'arsip');
                else params.delete('um_tab');
                const clean = listUrl + (params.toString() ? '?' + params.toString() : '');
                history.replaceState({ um: true }, '', clean);
            }
        }

        document.querySelectorAll('[data-um-tab]').forEach((btn) => {
            btn.addEventListener('click', function () {
                switchTab(btn.getAttribute('data-um-tab'));
            });
        });

        syncResetVisibility(new URLSearchParams(window.location.search));
        bindFragmentControls();
    })();

    document.addEventListener('DOMContentLoaded', function () {
        if (window.refreshLucideIcons) window.refreshLucideIcons();

        const chart = @json($roleChart ?? ['labels' => [], 'values' => [], 'colors' => []]);
        const canvas = document.getElementById('usersRoleDonut');
        const legendEl = document.getElementById('usersRoleLegend');
        if (canvas && typeof SimalasCharts !== 'undefined' && chart.labels) {
            SimalasCharts.buildDonutChart(canvas, {
                labels: chart.labels,
                values: chart.values,
                colors: chart.colors,
                centerMain: {{ (int) (($userStats['total'] ?? 0)) }},
                centerSub: 'User',
                hideLegend: true,
            });
            const sum = (chart.values || []).reduce((a, b) => a + Number(b || 0), 0) || 1;
            if (legendEl) {
                legendEl.innerHTML = (chart.labels || []).map(function (label, i) {
                    const val = Number(chart.values[i] || 0);
                    const pct = Math.round((val / sum) * 100);
                    const color = chart.colors[i] || '#64748b';
                    return '<span><i style="background:' + color + '"></i> ' + label + ' (' + pct + '%)</span>';
                }).join('');
            }
        }
    });
</script>

<div class="iot-modal-backdrop" id="usersHelpModal" role="dialog" aria-modal="true" style="display:none;align-items:center;justify-content:center;">
    <div class="iot-modal" style="width:min(560px,100%);position:relative;border:1px solid rgba(148,163,184,.28);border-radius:18px;padding:22px;background:var(--bg-card);color:var(--text-dark);">
        <button type="button" class="iot-modal-close" onclick="closeUsersHelp()" aria-label="Tutup" style="position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:999px;background:rgba(148,163,184,.14);color:var(--text-dark);font-size:20px;cursor:pointer;">×</button>
        <div style="display:inline-flex;padding:5px 10px;border-radius:999px;margin-bottom:8px;background:rgba(15,118,110,.14);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;">PANDUAN</div>
        <h3 style="margin:0 0 8px;font-size:18px;">Alur persetujuan akun</h3>
        <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:var(--text-muted);">
            Dosen daftar di web → OTP → admin setujui → login panel. Mahasiswa daftar di mobile → OTP → admin setujui → login app.
        </p>
        <div style="font-family:ui-monospace,Consolas,monospace;font-size:12px;line-height:1.55;padding:12px 14px;border-radius:12px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 40%,transparent);white-space:pre-wrap;">OTP pending   → belum bisa disetujui
Siap disetujui → Aksi cepat / centang lalu Setujui terpilih
Arsip Approval → tab di atas daftar (log registrasi & approval)
Reset          → kembali ke tampilan awal
Hapus akun     → tetap tercatat di arsip</div>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
            <button type="button" class="btn-premium btn-premium--primary" onclick="closeUsersHelp()">Mengerti</button>
        </div>
    </div>
</div>
<script>
function openUsersHelp() {
    const m = document.getElementById('usersHelpModal');
    if (!m) return;
    m.style.display = 'flex';
    m.classList.add('is-open');
}
function closeUsersHelp() {
    const m = document.getElementById('usersHelpModal');
    if (!m) return;
    m.style.display = 'none';
    m.classList.remove('is-open');
}
document.getElementById('usersHelpModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeUsersHelp();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeUsersHelp();
});
</script>
@endsection
