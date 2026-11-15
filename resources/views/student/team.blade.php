@extends('layouts.student')

@section('title', 'Tim PBL Saya')
@section('page-title', 'Tim PBL')

@section('content')
@php
    $hasTeam = (bool) ($team['has_pbl_team'] ?? false);
    $project = $team['project'] ?? [];
    $members = collect($team['members'] ?? []);
    $myRole = $team['role'] ?? 'anggota';
    $initialsOf = function (?string $name) {
        return collect(explode(' ', trim((string) $name)))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'M';
    };
    $roleLabel = function (?string $role) {
        return match ($role) {
            'ketua' => 'Ketua',
            'wakil' => 'Wakil',
            'manpro' => 'Manpro',
            'co_manpro' => 'Co-Manpro',
            default => 'Anggota',
        };
    };
@endphp

<div class="sp-page">
    <section class="sp-pagehead" aria-label="Kepala halaman tim">
        <p class="sp-pagehead__crumb">
            <a href="{{ route('mahasiswa.beranda') }}">Kolaborasi</a>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
            Tim PBL
        </p>
        <div class="sp-pagehead__main">
            <span class="sp-pagehead__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <div>
                <h2 class="sp-pagehead__title">Tim PBL Saya</h2>
                <p class="sp-pagehead__sub">Informasi proyek dan anggota tim berdasarkan penugasan PBL.</p>
            </div>
        </div>
    </section>

    @if(! $hasTeam)
        <section class="sp-card">
            <div class="sp-empty">
                <strong>Belum ada tim PBL</strong>
                <p>Anda belum ditugaskan ke proyek PBL. Hubungi dosen pengampu atau laboran jika memerlukan penugasan.</p>
            </div>
        </section>
    @else
        <section class="stp-project" aria-label="Ringkasan proyek">
            <span class="stp-project__mark">
                <img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/smartbrail-logo.jpg')) }}" alt="SmartBrail">
            </span>
            <div>
                <h3 class="stp-project__name">
                    {{ $project['nama'] ?? 'Proyek PBL' }}
                    @if(!empty($project['kode']))
                        <span class="sp-badge sp-badge--info">{{ $project['kode'] }}</span>
                    @endif
                </h3>
                @if(!empty($project['deskripsi']))
                    <p class="stp-project__desc">{{ $project['deskripsi'] }}</p>
                @endif
                <div class="stp-facts">
                    <div>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <div><span>Peran Saya</span><strong>{{ $roleLabel($myRole) }}</strong></div>
                    </div>
                    <div>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <div><span>Shift</span><strong>{{ ucfirst($project['shift_sesi'] ?? 'pagi') }}</strong></div>
                    </div>
                    <div>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        <div><span>Manpro</span><strong>{{ $project['manpro'] ?: 'Belum ditentukan' }}</strong></div>
                    </div>
                    <div>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <div><span>Co-Manpro</span><strong>{{ $project['co_manpro'] ?: 'Belum ditentukan' }}</strong></div>
                    </div>
                    <div>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <div><span>Anggota</span><strong>{{ $members->count() }} orang</strong></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="sp-card" aria-label="Anggota tim">
            <div class="sp-card__head" style="margin-bottom:14px">
                <div>
                    <h3 class="sp-card__title">Anggota Tim ({{ $members->count() }})</h3>
                    <p class="sp-card__desc">Daftar anggota resmi dari penugasan PBL.</p>
                </div>
            </div>
            <div class="stp-toolbar" style="margin-bottom:14px">
                <div class="stp-view" role="group" aria-label="Mode tampilan">
                    <button type="button" id="stpViewGrid" class="is-on" title="Tampilan grid" aria-pressed="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    </button>
                    <button type="button" id="stpViewList" title="Tampilan daftar" aria-pressed="false">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
                    </button>
                </div>
                <div class="stp-toolbar__search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" id="stpSearch" class="sp-input" placeholder="Cari anggota tim…" aria-label="Cari anggota tim">
                </div>
            </div>

            <div class="stp-members" id="stpMembers">
                @forelse($members as $member)
                    <article class="stp-member @if(($member['user_id'] ?? null) === $user->id) is-me @endif"
                             data-search="{{ mb_strtolower(($member['nama_lengkap'] ?? '') . ' ' . ($member['nitk_nidn'] ?? '') . ' ' . ($member['kelas'] ?? '')) }}">
                        <span class="sp-avatar">
                            @if(!empty($member['profile_photo_url']))
                                <img src="{{ $member['profile_photo_url'] }}" alt="Foto {{ $member['nama_lengkap'] }}" onerror="this.remove()">
                            @endif
                            <span>{{ $initialsOf($member['nama_lengkap'] ?? null) }}</span>
                        </span>
                        <div class="stp-member__meta">
                            <strong>{{ $member['nama_lengkap'] ?? '—' }}</strong>
                            <small>{{ $member['nitk_nidn'] ?? '—' }}@if(!empty($member['kelas'])) · {{ $member['kelas'] }}@endif</small>
                        </div>
                        <span class="sp-badge {{ in_array($member['role'], ['ketua', 'wakil'], true) ? 'sp-badge--warn' : 'sp-badge--neutral' }}">
                            {{ $roleLabel($member['role'] ?? null) }}
                        </span>
                    </article>
                @empty
                    <div class="sp-empty" style="grid-column:1/-1">Belum ada anggota yang terdaftar.</div>
                @endforelse
            </div>
            <p class="sp-empty" id="stpEmpty" hidden>Tidak ada anggota yang cocok dengan pencarian.</p>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var grid = document.getElementById('stpMembers');
                var search = document.getElementById('stpSearch');
                var empty = document.getElementById('stpEmpty');
                var btnGrid = document.getElementById('stpViewGrid');
                var btnList = document.getElementById('stpViewList');
                function setView(list) {
                    grid.classList.toggle('is-list', list);
                    btnGrid.classList.toggle('is-on', !list);
                    btnList.classList.toggle('is-on', list);
                    btnGrid.setAttribute('aria-pressed', String(!list));
                    btnList.setAttribute('aria-pressed', String(list));
                }
                btnGrid.addEventListener('click', function () { setView(false); });
                btnList.addEventListener('click', function () { setView(true); });
                search.addEventListener('input', function () {
                    var q = this.value.trim().toLowerCase();
                    var visible = 0;
                    grid.querySelectorAll('.stp-member').forEach(function (el) {
                        var show = !q || (el.getAttribute('data-search') || '').indexOf(q) !== -1;
                        el.style.display = show ? '' : 'none';
                        if (show) visible++;
                    });
                    if (empty) empty.hidden = visible !== 0;
                });
            });
        </script>
    @endif
</div>
@endsection
