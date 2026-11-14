@extends('layouts.dashboard')

@section('title', 'Proyek PBL')
@section('page-title', 'Proyek PBL')
@section('page-subtitle', 'Lihat semua proyek — kelola ketua & wakil pada proyek yang Anda ampu')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/pbl-modal-premium.css') }}?v=7">
    <link rel="stylesheet" href="{{ asset('css/pbl-dosen-cards.css') }}?v=1">
@endsection

@section('content')
    @if(session('success'))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;background:rgba(15,118,110,0.1);">{{ session('success') }}</div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;background:rgba(220,38,38,0.1);font-size:13px;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="pbl-dosen-scope-bar animate-in">
        <a href="{{ route('dosen.pbl.index', ['scope' => 'all']) }}"
           class="pbl-dosen-scope-btn {{ $scope === 'all' ? 'is-active' : '' }}">
            Semua proyek <span>{{ $stats['total'] }}</span>
        </a>
        <a href="{{ route('dosen.pbl.index', ['scope' => 'mine']) }}"
           class="pbl-dosen-scope-btn pbl-dosen-scope-btn--mine {{ $scope === 'mine' ? 'is-active' : '' }}">
            ★ Proyek saya <span>{{ $stats['mine'] }}</span>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px;">
        <a href="{{ route('dosen.pbl.index', ['scope' => 'all']) }}" class="card" style="padding:16px;text-decoration:none;color:inherit;{{ $scope === 'all' ? 'outline:2px solid rgba(56,189,248,0.5);' : '' }}">
            <small style="color:var(--text-muted);">Proyek aktif</small>
            <div style="font-size:22px;font-weight:800;">{{ $stats['total'] }}</div>
        </a>
        <a href="{{ route('dosen.pbl.index', ['scope' => 'mine']) }}"
           class="card pbl-dosen-stat--mine {{ $scope === 'mine' ? 'is-active' : '' }}"
           style="padding:16px;text-decoration:none;color:inherit;">
            <small style="color:#fde68a;">★ Proyek saya (manpro/co-manpro)</small>
            <div style="font-size:22px;font-weight:800;color:#fbbf24;">{{ $stats['mine'] }}</div>
        </a>
    </div>

    @if($scope === 'mine')
        <div class="card animate-in" style="margin-bottom:16px;padding:12px 16px;background:rgba(180,83,9,0.15);border:1px solid rgba(251,191,36,0.35);font-size:13px;color:#fde68a;">
            Menampilkan <strong>hanya proyek yang Anda ampu</strong>.
            <a href="{{ route('dosen.pbl.index', ['scope' => 'all']) }}" style="color:#fbbf24;font-weight:700;margin-left:6px;">Tampilkan semua</a>
        </div>
    @endif

    <div class="card animate-in">
        <div class="card-header">
            <h3 style="margin:0;">Daftar Tim PBL</h3>
            <span style="font-size:12px;color:var(--text-muted);">Klik kartu untuk lihat detail — Anda hanya bisa mengatur ketua & wakil pada proyek yang Anda ampu</span>
        </div>

        @if($projects->isEmpty())
            <div class="card-body" style="padding:28px;text-align:center;color:var(--text-muted);">Belum ada proyek PBL.</div>
        @else
            <div class="card-body">
                <div class="pbl-team-legend" aria-label="Keterangan warna kartu">
                    <span class="pbl-team-legend__item pbl-team-legend__item--mine">★ Proyek Anda — manpro / co-manpro (bisa kelola ketua & wakil)</span>
                    <span class="pbl-team-legend__item pbl-team-legend__item--other">Kartu biasa — hanya lihat detail</span>
                </div>
                <div class="pbl-team-card-grid">
                    @foreach($projects as $item)
                        @php
                            $project = $item['model'];
                            $roleClass = $item['my_role'] === 'manpro'
                                ? 'pbl-team-card--manpro'
                                : ($item['my_role'] === 'co_manpro' ? 'pbl-team-card--comanpro' : '');
                        @endphp
                        <button type="button"
                            class="pbl-team-card js-pbl-team-open {{ $item['is_mine'] ? 'pbl-team-card--mine ' . $roleClass : 'pbl-team-card--other' }}"
                            data-detail-id="pbl-dosen-detail-{{ $project->id }}">
                            @if($item['is_mine'])
                                <span class="pbl-team-card__pin" aria-hidden="true">★</span>
                            @endif
                            <span class="pbl-team-card__badge">{{ $project->kode }}</span>
                            @if($item['is_mine'])
                                <span class="pbl-team-card__badge pbl-team-card__badge--mine">
                                    {{ $item['my_role'] === 'manpro' ? 'Manpro Anda' : 'Co-manpro Anda' }}
                                </span>
                            @endif
                            <strong class="pbl-team-card__title">{{ $project->nama }}</strong>
                            <p class="pbl-team-card__meta">
                                Sesi {{ ucfirst($project->shift_sesi) }}
                                · {{ $item['members']->count() }} anggota
                            </p>
                            <p class="pbl-team-card__staff">
                                Manpro: {{ $project->manproUser?->nama_lengkap ?? $project->manpro ?? '—' }}
                            </p>
                            @if($item['ketua']?->user)
                                <p class="pbl-team-card__ketua">Ketua: {{ $item['ketua']->user->nama_lengkap }}</p>
                            @endif
                            <span class="pbl-team-card__cta">Lihat detail</span>
                            <span class="pbl-team-card__btn" aria-hidden="true">Buka detail tim</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @foreach($projects as $item)
                @php
                    $project = $item['model'];
                    $ketua = $item['ketua'];
                    $wakil = $item['wakil'];
                    $members = $item['members'];
                    $memberRoles = $item['member_roles'];
                    $canManage = $item['can_manage'];
                @endphp
                <div id="pbl-dosen-detail-{{ $project->id }}" class="pbl-team-detail-source" hidden>
                    <div class="pbl-team-detail__head">
                        <span class="pbl-team-detail__badge">{{ $project->kode }}</span>
                        <h3>{{ $project->nama }}</h3>
                        <p>Sesi {{ ucfirst($project->shift_sesi) }} · {{ $members->count() }} anggota</p>
                    </div>

                    <section class="pbl-team-detail__section">
                        <h4>Pengampu proyek</h4>
                        <div class="pbl-team-detail__roles">
                            <div class="pbl-team-detail__role-card">
                                <small>Manpro</small>
                                <strong>{{ $project->manproUser?->nama_lengkap ?? $project->manpro ?? '—' }}</strong>
                            </div>
                            <div class="pbl-team-detail__role-card">
                                <small>Co-manpro</small>
                                <strong>{{ $project->coManproUser?->nama_lengkap ?? $project->co_manpro ?? '—' }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="pbl-team-detail__section">
                        <h4>Struktur tim</h4>
                        @if(!$canManage)
                            <p class="pbl-team-detail__note">Hanya dosen pengampu yang dapat menetapkan ketua & wakil.</p>
                        @endif
                        <div class="pbl-team-detail__roles pbl-team-detail__roles--stack">
                            @include('partials._pbl_role_assign', [
                                'project' => $project,
                                'role' => 'ketua',
                                'label' => 'Ketua tim',
                                'current' => $ketua,
                                'members' => $members,
                                'storeRoute' => route('dosen.pbl.ketua.store', $project),
                                'clearRoute' => route('dosen.pbl.clear-role', $project),
                                'canEdit' => $canManage,
                            ])
                            @include('partials._pbl_role_assign', [
                                'project' => $project,
                                'role' => 'wakil',
                                'label' => 'Wakil ketua',
                                'current' => $wakil,
                                'members' => $members,
                                'storeRoute' => route('dosen.pbl.wakil.store', $project),
                                'clearRoute' => route('dosen.pbl.clear-role', $project),
                                'canEdit' => $canManage,
                            ])
                        </div>
                    </section>

                    <section class="pbl-team-detail__section pbl-team-detail__section--members">
                        <h4>Anggota tim ({{ $members->count() }})</h4>
                        @if($canManage)
                            <p class="pbl-team-detail__note">
                                Tambahkan mahasiswa ke tim ini, atau keluarkan (kick) bila perlu.
                                Mahasiswa yang dikeluarkan statusnya <strong>belum punya PBL</strong> — penempatan ulang via dosen/admin
                                (nanti lewat chat aplikasi).
                            </p>
                            <form method="POST" action="{{ route('dosen.pbl.anggota.store', $project) }}" class="pbl-role-form__row" style="margin-bottom:14px;">
                                @csrf
                                <select name="user_id" required class="pbl-assign-select" style="flex:1;min-width:200px;">
                                    <option value="">Pilih mahasiswa belum punya tim…</option>
                                    @foreach($unassignedMahasiswa as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->nama_lengkap }} ({{ $candidate->nitk_nidn }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="pbl-assign-save">+ Tambah ke tim</button>
                            </form>
                        @else
                            <p class="pbl-team-detail__note">Hanya dosen pengampu yang dapat menambah atau mengeluarkan anggota.</p>
                        @endif
                        <div class="pbl-team-detail__members-scroll">
                            @if($members->isEmpty())
                                <p class="pbl-team-detail__empty">Belum ada anggota. Tambahkan mahasiswa di atas, atau mahasiswa bisa pilih proyek lewat aplikasi mobile.</p>
                            @else
                                <table class="data-table pbl-team-detail__table">
                                    <thead>
                                        <tr>
                                            <th>NIM</th>
                                            <th>Nama</th>
                                            <th>Peran</th>
                                            <th>Kelas</th>
                                            <th>Shift</th>
                                            @if($canManage)<th style="text-align:right;">Aksi</th>@endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($members as $mhs)
                                            @php
                                                $role = $memberRoles->get($mhs->id)?->role;
                                                $roleLabel = match ($role) {
                                                    'ketua' => 'Ketua',
                                                    'wakil' => 'Wakil',
                                                    default => 'Anggota',
                                                };
                                            @endphp
                                            <tr>
                                                <td style="font-family:monospace;font-size:12px;">{{ $mhs->nitk_nidn }}</td>
                                                <td style="font-weight:600;">{{ $mhs->nama_lengkap }}</td>
                                                <td><span class="status-badge {{ $role === 'ketua' ? 'success' : ($role === 'wakil' ? 'warning' : '') }}">{{ $roleLabel }}</span></td>
                                                <td>{{ $mhs->kelas ?: '—' }}</td>
                                                <td>{{ $mhs->shift ? ucfirst($mhs->shift) : '—' }}</td>
                                                @if($canManage)
                                                    <td style="text-align:right;">
                                                        <form method="POST" action="{{ route('dosen.pbl.members.destroy', [$project, $mhs]) }}" class="inline-action-form"
                                                            data-swal-confirm
                                                            data-swal-title="Keluarkan dari tim?"
                                                            data-swal-text="{{ $mhs->nama_lengkap }} akan dikeluarkan dari {{ $project->nama }}. Status PBL kosong — mahasiswa perlu penempatan ulang."
                                                            data-swal-icon="warning"
                                                            data-swal-confirm-button="Ya, keluarkan"
                                                            data-swal-confirm-color="#f43f5e">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="pbl-assign-clear">Keluarkan</button>
                                                        </form>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </section>
                </div>
            @endforeach
        @endif
    </div>

    <div class="pbl-modal-backdrop" id="pblTeamDetailModal" aria-hidden="true">
        <div class="pbl-modal pbl-team-detail-modal" role="dialog">
            <button type="button" class="pbl-modal-close" id="pblTeamDetailClose" aria-label="Tutup">
                @include('components.icon', ['name' => 'x'])
            </button>
            <div id="pblTeamDetailBody" class="pbl-team-detail-modal__body"></div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    const modal = document.getElementById('pblTeamDetailModal');
    const body = document.getElementById('pblTeamDetailBody');
    const closeBtn = document.getElementById('pblTeamDetailClose');
    if (!modal || !body) return;

    function openTeamDetail(detailId) {
        const source = document.getElementById(detailId);
        if (!source) return;
        body.innerHTML = source.innerHTML;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    function closeTeamDetail() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        body.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-pbl-team-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openTeamDetail(btn.getAttribute('data-detail-id'));
        });
    });

    closeBtn?.addEventListener('click', closeTeamDetail);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeTeamDetail();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeTeamDetail();
    });
})();
</script>
@endsection
