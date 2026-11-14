@extends('layouts.dashboard')

@section('title', 'Tidak Lengkap')
@section('page-title', 'Absensi Tidak Lengkap')
@section('page-subtitle', 'Mahasiswa dengan status tidak lengkap (masuk tanpa pulang atau sebaliknya)')

@section('sidebar-nav')
    @include($panel['sidebar_include'])
@endsection

@section('content')
    @include('absensi._styles')
    @include('absensi._nav', ['activeNav' => 'tidak_lengkap'])

    @if(session('success'))
        <div class="card" style="margin-bottom:16px;padding:14px;background:rgba(15,118,110,0.1);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="card" style="margin-bottom:16px;padding:14px;background:rgba(220,38,38,0.1);">{{ session('error') }}</div>
    @endif

    @if($panel['is_laboran'] ?? false)
    <div class="card animate-in" style="margin-bottom:16px;padding:14px;font-size:13px;color:var(--text-muted);line-height:1.55;">
        <strong style="color:var(--text-dark);">Sumber data</strong><br>
        Daftar ini mengikuti status <strong>tidak_lengkap</strong> di rekap harian (sama dengan kartu oranye di Rekap Absensi).
        Untuk tanggal lampau, sistem otomatis menutup sesi yang terlewat (alfa &amp; tidak lengkap) saat halaman ini dibuka.
        Setelah dicek, admin/dosen bisa <strong>koreksi</strong> ke lengkap atau <strong>tandai selesai</strong> untuk menutup antrian.
    </div>
    @endif

    <div class="card animate-in" style="margin-bottom:16px;">
        <div class="card-body">
            <form method="GET" action="{{ route($panel['route_tidak_lengkap']) }}" class="filter-toolbar">
                <input type="date" name="date" value="{{ $filter['date'] }}">
                <select name="kelas">
                    <option value="">Semua kelas</option>
                    @foreach($kelasOptions as $k)<option value="{{ $k }}" @selected($filter['kelas']===$k)>{{ $k }}</option>@endforeach
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
                        <th>Tanggal</th><th>Sesi</th><th>NIM</th><th>Nama</th><th>Kelas</th><th>Prodi</th><th>Semester</th>
                        @if($canKoreksi ?? false)<th>Aksi</th>@endif
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
                            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button type="button" class="btn-absensi btn-koreksi-tl"
                                    data-action="{{ route($panel['route_koreksi'], $item) }}"
                                    data-status="lengkap"
                                    data-nama="{{ $item->user?->nama_lengkap }}"
                                    data-nim="{{ $item->user?->nitk_nidn }}"
                                    data-tanggal="{{ $item->tanggal->format('Y-m-d') }}">
                                    Koreksi
                                </button>
                                <form method="POST" action="{{ route($panel['route_tl_selesai'], $item) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-absensi" onclick="return confirm('Tandai kasus ini selesai?')">Selesai</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($canKoreksi ?? false) ? 8 : 7 }}" style="padding:24px;text-align:center;color:var(--text-muted);">Tidak ada kasus tidak lengkap untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top:16px;">{{ $items->links() }}</div>
        </div>
    </div>

    @if($canKoreksi ?? false)
        @include('absensi._koreksi_modal')
        <script>
        document.querySelectorAll('.btn-koreksi-tl').forEach(btn => {
            btn.addEventListener('click', () => openAbsensiKoreksi({
                action: btn.dataset.action,
                title: 'Koreksi tidak lengkap',
                status: btn.dataset.status,
                meta: `<strong>${btn.dataset.nama}</strong> (${btn.dataset.nim})<br>${btn.dataset.tanggal}`,
            }));
        });
        </script>
    @endif
@endsection
