@extends('layouts.dashboard')

@section('title', 'Data Mahasiswa')
@section('page-title', 'Data Mahasiswa')
@section('page-subtitle', 'Direktori mahasiswa — fokus tim PBL Anda, export, dan pantauan sidik jari')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/dosen-mahasiswa.css') }}?v=1">
@endsection

@section('content')
@php
    $qs = request()->except(['page']);
    $sortLink = function (string $col) use ($qs, $sort, $dir) {
        $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        return route('dosen.mahasiswa', array_merge($qs, ['sort' => $col, 'dir' => $nextDir]));
    };
    $exportUrl = route('dosen.mahasiswa.export', request()->query());
    $fingerPct = ($stats['total'] ?? 0) > 0
        ? round((($stats['finger'] ?? 0) / $stats['total']) * 100)
        : 0;
@endphp

<div class="dm-page" id="dosenMahasiswaPage">
    <div class="dm-scope-bar animate-in">
        <a href="{{ route('dosen.mahasiswa', array_merge($qs, ['scope' => 'mine', 'page' => null])) }}"
           class="dm-scope-btn dm-scope-btn--mine {{ $scope === 'mine' ? 'is-active' : '' }}">
            Tim PBL saya <span>{{ $stats['mine'] }}</span>
        </a>
        <a href="{{ route('dosen.mahasiswa', array_merge($qs, ['scope' => 'all', 'page' => null])) }}"
           class="dm-scope-btn {{ $scope === 'all' ? 'is-active' : '' }}">
            Semua mahasiswa <span>{{ $stats['total'] }}</span>
        </a>
    </div>

    <div class="stat-cards dm-stats animate-in">
        @include('partials.stat-card', [
            'tone' => 'amber',
            'icon' => 'users',
            'label' => 'Anggota tim Anda',
            'value' => $stats['mine'],
            'meta' => ($pblMeta['has_pbl'] ?? false) ? 'Sesuai proyek manpro / co-manpro' : 'Belum ada proyek yang diampu',
            'href' => route('dosen.mahasiswa', array_merge($qs, ['scope' => 'mine', 'page' => null])),
        ])
        @include('partials.stat-card', [
            'tone' => 'blue',
            'icon' => 'graduation-cap',
            'label' => 'Total terdaftar',
            'value' => $stats['total'],
            'meta' => $stats['aktif'].' aktif · '.$stats['pending'].' pending',
            'href' => route('dosen.mahasiswa', array_merge($qs, ['scope' => 'all', 'page' => null])),
        ])
        @include('partials.stat-card', [
            'tone' => 'teal',
            'icon' => 'fingerprint',
            'label' => 'Sidik jari siap',
            'value' => $stats['finger'],
            'meta' => $fingerPct.'% dari seluruh mahasiswa',
            'href' => route('dosen.mahasiswa', array_merge($qs, ['finger' => 'yes', 'page' => null])),
        ])
        @include('partials.stat-card', [
            'tone' => 'rose',
            'icon' => 'user-x',
            'label' => 'Belum punya tim',
            'value' => $stats['tanpa_tim'],
            'meta' => 'Perlu penempatan PBL',
            'href' => route('dosen.mahasiswa', array_merge($qs, ['scope' => 'all', 'no_tim' => 1, 'tim' => null, 'page' => null])),
        ])
    </div>

    @if($scope === 'mine')
        <div class="dm-hint animate-in">
            Menampilkan <strong>hanya mahasiswa di tim yang Anda ampu</strong>
            ({{ $myTimOptions->isNotEmpty() ? $myTimOptions->take(3)->implode(', ').($myTimOptions->count() > 3 ? '…' : '') : 'belum terhubung' }}).
            <a href="{{ route('dosen.mahasiswa', array_merge($qs, ['scope' => 'all'])) }}">Lihat semua</a>
        </div>
    @endif

    @if($myTimOptions->isNotEmpty())
        <div class="dm-team-chips animate-in" aria-label="Pintasan tim Anda">
            <a class="dm-chip dm-chip--mine {{ $scope === 'mine' && !request('tim') ? 'is-active' : '' }}"
               href="{{ route('dosen.mahasiswa', array_merge($qs, ['scope' => 'mine', 'tim' => null, 'page' => null])) }}">Semua tim saya</a>
            @foreach($myTimOptions as $timChip)
                <a class="dm-chip dm-chip--mine {{ request('tim') === $timChip ? 'is-active' : '' }}"
                   href="{{ route('dosen.mahasiswa', array_merge($qs, ['scope' => 'mine', 'tim' => $timChip, 'page' => null])) }}">{{ $timChip }}</a>
            @endforeach
        </div>
    @endif

    <div class="card animate-in" style="margin-bottom:16px;">
        <div class="card-header">
            <h3>Filter & pencarian</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dosen.mahasiswa') }}" class="filter-toolbar">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <div class="filter-field filter-field--wide">
                    <label>Cari</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama, NIM, email, kelas, tim…">
                </div>
                <div class="filter-field">
                    <label>Kelas</label>
                    <select name="kelas">
                        <option value="">Semua</option>
                        @foreach($kelasOptions ?? [] as $k)
                            <option value="{{ $k }}" @selected(request('kelas')===$k)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <label>Tim PBL</label>
                    <select name="tim">
                        <option value="">Semua</option>
                        @foreach($timOptions ?? [] as $timOpt)
                            <option value="{{ $timOpt }}" @selected(request('tim')===$timOpt)>{{ $timOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <label>Semester</label>
                    <select name="semester">
                        <option value="">Semua</option>
                        @foreach($semesterOptions ?? [] as $s)
                            <option value="{{ $s }}" @selected((string)request('semester')===(string)$s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Semua</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Pending</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Sidik jari</label>
                    <select name="finger">
                        <option value="">Semua</option>
                        <option value="yes" @selected(request('finger') === 'yes')>Sudah enroll</option>
                        <option value="no" @selected(request('finger') === 'no')>Belum enroll</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Penempatan tim</label>
                    <select name="no_tim">
                        <option value="">Semua</option>
                        <option value="1" @selected(request()->boolean('no_tim'))>Belum punya tim</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Per halaman</label>
                    <select name="per_page">
                        @foreach([15, 30, 50] as $n)
                            <option value="{{ $n }}" @selected((int)$perPage === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                @include('partials.filter-actions', ['resetUrl' => route('dosen.mahasiswa', ['scope' => $scope]), 'submitLabel' => 'Terapkan'])
            </form>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-header" style="align-items:flex-start;gap:12px;">
            <div>
                <h3 style="margin:0;">Daftar mahasiswa</h3>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Menampilkan {{ $mahasiswa->firstItem() ?? 0 }}–{{ $mahasiswa->lastItem() ?? 0 }} dari {{ $mahasiswa->total() }}
                    @if($scope === 'mine') · lingkup tim Anda @endif
                </div>
            </div>
            <div class="dm-toolbar__right">
                <div class="dm-view-toggle" role="group" aria-label="Mode tampilan">
                    <button type="button" class="is-active" data-dm-view="table">Tabel</button>
                    <button type="button" data-dm-view="cards">Kartu</button>
                </div>
                <a class="dm-btn dm-btn--primary" href="{{ $exportUrl }}">Export CSV</a>
            </div>
        </div>

        <div class="card-body" style="padding:0;">
            @if($mahasiswa->isEmpty())
                <div class="dm-empty">
                    <strong>Tidak ada mahasiswa pada filter ini</strong>
                    Coba ubah lingkup ke “Semua mahasiswa”, kosongkan pencarian, atau reset filter.
                </div>
            @else
                <div class="dm-table-wrap">
                    <table class="data-table dm-table">
                        <thead>
                            <tr>
                                <th>
                                    <a class="dm-sort {{ $sort === 'nama' ? 'is-active' : '' }}" href="{{ $sortLink('nama') }}">
                                        Mahasiswa {{ $sort === 'nama' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>
                                    <a class="dm-sort {{ $sort === 'nim' ? 'is-active' : '' }}" href="{{ $sortLink('nim') }}">
                                        NIM {{ $sort === 'nim' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>
                                    <a class="dm-sort {{ $sort === 'kelas' ? 'is-active' : '' }}" href="{{ $sortLink('kelas') }}">
                                        Kelas {{ $sort === 'kelas' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>
                                    <a class="dm-sort {{ $sort === 'tim' ? 'is-active' : '' }}" href="{{ $sortLink('tim') }}">
                                        Tim PBL {{ $sort === 'tim' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>
                                    <a class="dm-sort {{ $sort === 'semester' ? 'is-active' : '' }}" href="{{ $sortLink('semester') }}">
                                        Sem. {{ $sort === 'semester' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>Sidik jari</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($mahasiswa as $m)
                                @php
                                    $isMine = $timKeys->contains($m->tim);
                                    $initials = collect(preg_split('/\s+/', trim((string) $m->nama_lengkap)))
                                        ->filter()
                                        ->take(2)
                                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                        ->implode('');
                                    $photo = $m->profile_photo_url;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="dm-person">
                                            @if($photo)
                                                <img class="dm-avatar" src="{{ $photo }}" alt="">
                                            @else
                                                <span class="dm-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                                            @endif
                                            <div>
                                                <a class="dm-person__name" href="{{ route('profiles.show', $m) }}">{{ $m->nama_lengkap }}</a>
                                                <div class="dm-person__meta">
                                                    {{ $m->email ?: 'Email belum diisi' }}
                                                    @if($isMine)
                                                        <span class="dm-badge dm-badge--mine" style="margin-left:6px;">Tim Anda</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="dm-mono">{{ $m->nitk_nidn }}</td>
                                    <td style="font-size:12px;">{{ $m->kelas ?: '—' }}</td>
                                    <td style="font-size:12px;">
                                        {{ $m->tim ?: '—' }}
                                        @if($isMine && $m->tim)
                                            <div style="margin-top:4px;"><span class="dm-badge dm-badge--mine">Ampuan</span></div>
                                        @endif
                                    </td>
                                    <td style="font-size:12px;">
                                        {{ $m->semester ? 'S'.$m->semester : '—' }}
                                        @if($m->angkatan)
                                            <div class="dm-person__meta">{{ $m->angkatan }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($m->finger_id)
                                            <span class="dm-badge dm-badge--ok">ID #{{ $m->finger_id }}</span>
                                        @else
                                            <span class="dm-badge dm-badge--warn">Belum enroll</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($m->is_active)
                                            <span class="dm-badge dm-badge--ok">Aktif</span>
                                        @else
                                            <span class="dm-badge dm-badge--muted">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dm-actions">
                                            <a class="dm-action" href="{{ route('profiles.show', $m) }}">Profil</a>
                                            <a class="dm-action" href="{{ route('dosen.absensi', ['q' => $m->nitk_nidn]) }}">Absensi</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="dm-grid">
                    @foreach($mahasiswa as $m)
                        @php
                            $isMine = $timKeys->contains($m->tim);
                            $initials = collect(preg_split('/\s+/', trim((string) $m->nama_lengkap)))
                                ->filter()
                                ->take(2)
                                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                ->implode('');
                            $photo = $m->profile_photo_url;
                        @endphp
                        <article class="dm-card {{ $isMine ? 'is-mine' : '' }}">
                            <div class="dm-person">
                                @if($photo)
                                    <img class="dm-avatar" src="{{ $photo }}" alt="">
                                @else
                                    <span class="dm-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                                @endif
                                <div>
                                    <a class="dm-person__name" href="{{ route('profiles.show', $m) }}">{{ $m->nama_lengkap }}</a>
                                    <div class="dm-person__meta">{{ $m->nitk_nidn }} · {{ $m->kelas ?: 'Tanpa kelas' }}</div>
                                </div>
                            </div>
                            <div style="font-size:12px;color:var(--text-muted);">
                                <div><strong style="color:var(--text-primary,#e2e8f0);">Tim:</strong> {{ $m->tim ?: '—' }}</div>
                                <div style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;">
                                    @if($isMine)<span class="dm-badge dm-badge--mine">Tim Anda</span>@endif
                                    @if($m->finger_id)
                                        <span class="dm-badge dm-badge--ok">Finger #{{ $m->finger_id }}</span>
                                    @else
                                        <span class="dm-badge dm-badge--warn">Belum enroll</span>
                                    @endif
                                    @if($m->is_active)
                                        <span class="dm-badge dm-badge--ok">Aktif</span>
                                    @else
                                        <span class="dm-badge dm-badge--muted">Pending</span>
                                    @endif
                                </div>
                            </div>
                            <div class="dm-card__foot">
                                <a class="dm-action" href="{{ route('profiles.show', $m) }}">Profil</a>
                                <a class="dm-action" href="{{ route('dosen.absensi', ['q' => $m->nitk_nidn]) }}">Absensi</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <div style="padding:16px;" class="dm-pagination-only-table">
                {{ $mahasiswa->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const page = document.getElementById('dosenMahasiswaPage');
    if (!page) return;
    const buttons = page.querySelectorAll('[data-dm-view]');
    const key = 'dosen.mahasiswa.view';
    const apply = (mode) => {
        page.classList.toggle('is-cards', mode === 'cards');
        buttons.forEach((btn) => btn.classList.toggle('is-active', btn.getAttribute('data-dm-view') === mode));
        try { localStorage.setItem(key, mode); } catch (_) {}
    };
    buttons.forEach((btn) => btn.addEventListener('click', () => apply(btn.getAttribute('data-dm-view'))));
    let saved = 'table';
    try { saved = localStorage.getItem(key) || 'table'; } catch (_) {}
    apply(saved === 'cards' ? 'cards' : 'table');
})();
</script>
@endsection
