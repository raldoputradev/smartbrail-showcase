@extends('layouts.dashboard')

@section('title', 'Rekap Absensi')
@section('page-title', 'Rekap Absensi')
@section('page-subtitle', 'Status masuk–pulang per sesi (hijau = lengkap)')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @include('absensi._nav', ['activeNav' => 'rekap'])

    @if(session('success'))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;border-color:rgba(15,118,110,0.3);background:rgba(15,118,110,0.08);">
            {{ session('success') }}
        </div>
    @endif

    <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card"><div class="stat-card-title">Lengkap</div><div class="stat-card-value" style="color:#059669;">{{ $summary['lengkap'] }}</div></div>
        <div class="stat-card"><div class="stat-card-title">Alfa</div><div class="stat-card-value" style="color:#dc2626;">{{ $summary['alfa'] }}</div></div>
        <div class="stat-card"><div class="stat-card-title">Tidak lengkap</div><div class="stat-card-value" style="color:#ea580c;">{{ $summary['tidak_lengkap'] }}</div></div>
        <div class="stat-card"><div class="stat-card-title">% Lengkap</div><div class="stat-card-value">{{ $summary['persentase_lengkap'] }}%</div></div>
    </div>

    <div class="card animate-in" style="margin-bottom:20px;">
        <div class="card-body">
            <form method="GET" action="{{ route($panel['route_rekap']) }}" class="filter-toolbar filter-toolbar-grid">
                <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Nama / NIM">
                <input type="date" name="date" value="{{ $filter['date'] }}">
                <select name="kelas"><option value="">Semua kelas</option>@foreach($kelasOptions as $k)<option value="{{ $k }}" @selected($filter['kelas']===$k)>{{ $k }}</option>@endforeach</select>
                <select name="tim"><option value="">Semua tim</option>@foreach($timOptions as $t)<option value="{{ $t }}" @selected($filter['tim']===$t)>{{ $t }}</option>@endforeach</select>
                <select name="shift"><option value="">Semua shift</option><option value="pagi" @selected($filter['shift']==='pagi')>Pagi</option><option value="malam" @selected($filter['shift']==='malam')>Malam</option></select>
                <select name="status"><option value="">Semua status</option>@foreach(['belum','masuk','lengkap','tidak_lengkap','alfa','libur'] as $st)<option value="{{ $st }}" @selected($filter['status']===$st)>{{ $st }}</option>@endforeach</select>
                <button type="submit" class="btn-logout-top" style="color:#0f766e;">Terapkan</button>
            </form>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-body" style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid var(--border-subtle);">
                        <th style="padding:10px;">Tanggal</th><th>NIM</th><th>Nama</th><th>Kelas</th><th>Prodi</th><th>Shift</th><th>Sesi</th><th>Status</th><th>Telat</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $badge = match($row['status']) {
                                'lengkap' => '#059669',
                                'alfa' => '#dc2626',
                                'tidak_lengkap' => '#ea580c',
                                'masuk' => '#2563eb',
                                'libur' => '#64748b',
                                default => '#94a3b8',
                            };
                        @endphp
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:10px;">{{ $row['tanggal'] }}</td>
                            <td>{{ $row['nim'] }}</td>
                            <td>{{ $row['nama'] }}</td>
                            <td>{{ $row['kelas'] }}</td>
                            <td>{{ $row['prodi'] }}</td>
                            <td>{{ $row['shift'] }}</td>
                            <td>{{ $row['sesi'] }}</td>
                            <td><span style="color:{{ $badge }};font-weight:700;">{{ strtoupper($row['status']) }}</span></td>
                            <td style="font-size:11px;">@if($row['menit_terlambat_masuk'])M {{ $row['menit_terlambat_masuk'] }}m @endif @if($row['menit_terlambat_pulang']) P {{ $row['menit_terlambat_pulang'] }}m @endif</td>
                            <td>
                                @if($row['harian_id'])
                                    <form method="POST" action="{{ route($panel['route_koreksi'], $row['harian_id']) }}" style="display:flex;gap:4px;flex-wrap:wrap;">
                                        @csrf
                                        <select name="status" style="font-size:11px;padding:4px;">
                                            <option value="lengkap">lengkap</option>
                                            <option value="tidak_lengkap">tidak_lengkap</option>
                                            <option value="alfa">alfa</option>
                                        </select>
                                        <input type="text" name="catatan_koreksi" placeholder="Catatan" style="font-size:11px;padding:4px;width:80px;">
                                        <button type="submit" style="font-size:11px;">Koreksi</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="padding:20px;text-align:center;color:var(--text-muted);">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @include('components.table-pagination', ['paginator' => $rows])
        </div>
    </div>
@endsection
