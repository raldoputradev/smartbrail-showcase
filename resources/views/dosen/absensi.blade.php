@extends('layouts.dashboard')

@section('title', 'Absensi Mahasiswa')
@section('page-title', 'Absensi Mahasiswa')
@section('page-subtitle', 'Rekap absensi sidik jari mahasiswa')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('content')
    <div class="card animate-in" style="margin-bottom:20px;">
        <div class="card-header">
            <h3>Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dosen.absensi') }}" class="filter-toolbar filter-toolbar-grid">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Cari Nama / NIM</label>
                    <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Nama atau NIM">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Mesin</label>
                    <select name="id_mesin">
                        <option value="">Semua</option>
                        @foreach($mesinList as $m)
                            <option value="{{ $m->id_mesin }}" @selected($filter['id_mesin'] === $m->id_mesin)>{{ $m->id_mesin }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Dari</label>
                    <input type="date" name="tanggal_mulai" value="{{ $filter['tanggal_mulai'] }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Sampai</label>
                    <input type="date" name="tanggal_akhir" value="{{ $filter['tanggal_akhir'] }}">
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="topbar-btn" title="Terapkan Filter">@include('components.icon', ['name' => 'search'])<span>Filter</span></button>
                    <a href="{{ route('dosen.absensi') }}" class="topbar-btn" title="Reset Filter" style="text-decoration:none;">@include('components.icon', ['name' => 'rotate-ccw'])<span>Reset</span></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-header">
            <h3>Hasil Absensi Mahasiswa</h3>
            <span style="font-size:12px;color:var(--text-muted);">Total: {{ $logs->total() }}</span>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu Tap</th>
                        <th>Nama Mahasiswa</th>
                        <th>NIM</th>
                        <th>ID Mesin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="font-family:monospace;font-size:12.5px;">{{ optional($log->waktu_tap)->translatedFormat('d M Y H:i:s') }}</td>
                            <td style="font-weight:600;">
                                @if($log->user)
                                    <a href="{{ route('profiles.show', $log->user) }}" style="color:var(--soft-blue);text-decoration:none;">{{ $log->user->nama_lengkap }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td style="font-family:monospace;">{{ $log->user->nitk_nidn ?? '-' }}</td>
                            <td style="font-family:monospace;">{{ $log->id_mesin }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">Belum ada absensi mahasiswa pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:16px;">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
