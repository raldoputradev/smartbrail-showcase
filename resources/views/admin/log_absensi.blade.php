@extends('layouts.dashboard')

@section('title', 'Log Absensi Real')
@section('page-title', 'Log Absensi (Real-time)')
@section('page-subtitle', 'Data tap sidik jari dari mesin ESP32 absensi')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @include('absensi._snapshot_boot', [
        'snapshotSurface' => 'log',
        'snapshotUrl' => route('admin.log.absensi.snapshot'),
        'snapshotInterval' => 15000,
        'snapshotChannel' => 'absensi.staff.laboran',
    ])
    <div class="stats-grid">
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Log</span>
                <div class="stat-card-icon">📋</div>
            </div>
            <div class="stat-card-value" id="log-stat-total">{{ $stats['total'] }}</div>
            <div class="stat-card-change">Seluruh waktu</div>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Hari Ini</span>
                <div class="stat-card-icon">🌞</div>
            </div>
            <div class="stat-card-value" id="log-stat-today">{{ $stats['today'] }}</div>
            <div class="stat-card-change">{{ now()->translatedFormat('d M Y') }}</div>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Minggu Ini</span>
                <div class="stat-card-icon">📆</div>
            </div>
            <div class="stat-card-value" id="log-stat-minggu">{{ $stats['minggu_ini'] }}</div>
            <div class="stat-card-change">Sen-Min</div>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Mesin Absensi</span>
                <div class="stat-card-icon">📡</div>
            </div>
            <div class="stat-card-value" id="log-stat-mesin">{{ $mesinList->count() }}</div>
            <div class="stat-card-change">Terdaftar</div>
        </div>
    </div>

    <div class="card animate-in" style="margin-bottom:20px;">
        <div class="card-header">
            <h3>Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.log.absensi') }}" class="filter-toolbar filter-toolbar-grid">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Cari Nama / NIDN / NIM</label>
                    <input type="text" name="q" value="{{ $filter['q'] }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Mesin</label>
                    <select name="id_mesin">
                        <option value="">Semua</option>
                        @foreach($mesinList as $m)
                            <option value="{{ $m->id_mesin }}" @selected($filter['id_mesin'] === $m->id_mesin)>{{ $m->id_mesin }} - {{ $m->nama_mesin }}</option>
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
                    <button type="submit" class="btn-premium btn-premium--primary" title="Filter">@include('components.icon', ['name' => 'search'])</button>
                    <a href="{{ route('admin.log.absensi') }}" class="topbar-btn" title="Reset" style="text-decoration:none;">↺</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-header">
            <h3>Data Log Absensi</h3>
            <span id="log-total-label" style="font-size:12px;color:var(--text-muted);">Urut siapa duluan absen · Total: {{ $logs->total() }}</span>
        </div>
        <div class="card-body" style="padding:0;" data-snap-live="log" data-snap-scroll>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:48px;">#</th>
                        <th>Waktu Tap</th>
                        <th>Nama</th>
                        <th>NITK/NIDN</th>
                        <th>Role</th>
                        <th>ID Mesin</th>
                    </tr>
                </thead>
                <tbody id="log-rows">
                    @include('admin._log_absensi_rows', ['logs' => $logs])
                </tbody>
            </table>
            <div id="log-pager">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
