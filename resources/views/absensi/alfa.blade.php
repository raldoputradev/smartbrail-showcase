@extends('layouts.dashboard')

@section('title', 'Tabel Alfa')
@section('page-title', 'Tabel Alfa')
@section('page-subtitle', 'Daftar mahasiswa alfa — tanpa tap pada sesi wajib')

@section('sidebar-nav')
    @include($panel['sidebar_include'])
@endsection

@section('content')
    @include('absensi._styles')
    @include('absensi._nav', ['activeNav' => 'alfa'])
    @if(session('success'))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;border-color:rgba(15,118,110,0.3);background:rgba(15,118,110,0.08);">
            {{ session('success') }}
        </div>
    @endif

    <div class="card animate-in" style="margin-bottom:16px;">
        <div class="card-body">
            <form method="GET" action="{{ route($panel['route_alfa']) }}" class="filter-toolbar">
                <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Nama / NIM">
                <input type="date" name="date" value="{{ $filter['date'] }}">
                <select name="kelas">
                    <option value="">Semua kelas</option>
                    @foreach($kelasOptions as $k)<option value="{{ $k }}" @selected($filter['kelas']===$k)>{{ $k }}</option>@endforeach
                </select>
                <select name="sesi">
                    <option value="">Semua sesi</option>
                    <option value="pagi" @selected($filter['sesi']==='pagi')>Pagi</option>
                    <option value="malam" @selected($filter['sesi']==='malam')>Malam</option>
                </select>
                <button type="submit" class="btn-premium btn-premium--primary">@include('components.icon', ['name' => 'search'])<span>Terapkan</span></button>
            </form>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-body" style="overflow-x:auto;">
            <table class="absensi-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Sesi</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Prodi</th>
                        <th>Semester</th>
                        @if($canKoreksi ?? false)<th style="width:100px;">Koreksi</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->tanggal->format('Y-m-d') }}</td>
                            <td>{{ ucfirst($item->sesi) }}</td>
                            <td>{{ $item->user?->nitk_nidn }}</td>
                            <td>{{ $item->user?->nama_lengkap }}</td>
                            <td>{{ $item->user?->kelas ?? '—' }}</td>
                            <td>{{ $item->user?->programStudi?->nama ?? '—' }}</td>
                            <td>{{ $item->user?->semester ?? '—' }}</td>
                            @if($canKoreksi ?? false)
                            <td>
                                @if($item->harian)
                                    <button type="button" class="btn-absensi primary btn-koreksi-alfa"
                                        data-action="{{ route($panel['route_koreksi'], $item->harian) }}"
                                        data-nama="{{ $item->user?->nama_lengkap }}"
                                        data-nim="{{ $item->user?->nitk_nidn }}"
                                        data-tanggal="{{ $item->tanggal->format('Y-m-d') }}"
                                        data-sesi="{{ $item->sesi }}">
                                        Koreksi
                                    </button>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($canKoreksi ?? false) ? 8 : 7 }}" style="padding:24px;text-align:center;color:var(--text-muted);">Tidak ada alfa aktif untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top:16px;">{{ $items->links() }}</div>
        </div>
    </div>

    @if($canKoreksi ?? false)
        @include('absensi._koreksi_modal')
        <script>
        document.querySelectorAll('.btn-koreksi-alfa').forEach(btn => {
            btn.addEventListener('click', () => openAbsensiKoreksi({
                action: btn.dataset.action,
                title: 'Koreksi alfa',
                alfaMode: true,
                status: 'lengkap',
                meta: `<strong>${btn.dataset.nama}</strong> (${btn.dataset.nim})<br>${btn.dataset.tanggal} · sesi ${btn.dataset.sesi}`,
            }));
        });
        </script>
    @endif
@endsection
