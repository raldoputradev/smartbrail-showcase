@extends('layouts.dashboard')

@section('title', 'Data Absensi')
@section('page-title', 'Data Absensi')
@section('page-subtitle', 'Cek operasional tap absensi mahasiswa harian')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    <div class="card animate-in" style="margin-bottom:20px;">
        <div class="card-header">
            <div>
                <h3>Cek Tap Absensi</h3>
                <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Halaman ini hanya untuk pengecekan cepat siapa yang sudah tap pada tanggal tertentu. Rekap dan export resmi dipusatkan di menu Laporan.
                </p>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.attendance') }}" class="filter-toolbar filter-toolbar-grid">
                <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Cari nama / NIM">

                <select name="kelas">
                    <option value="">Semua Kelas</option>
                    @foreach($kelasOptions as $kelas)
                        <option value="{{ $kelas }}" @selected($filter['kelas'] === $kelas)>{{ $kelas }}</option>
                    @endforeach
                </select>

                <select name="tim">
                    <option value="">Semua Tim</option>
                    @foreach($timOptions as $tim)
                        <option value="{{ $tim }}" @selected($filter['tim'] === $tim)>{{ $tim }}</option>
                    @endforeach
                </select>

                <input type="date" name="date" value="{{ $filter['date'] }}">

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn-logout-top" style="color:#0f766e;border-color:rgba(15,118,110,0.2);background:rgba(15,118,110,0.08);">
                        @include('components.icon', ['name' => 'search'])<span>Terapkan</span>
                    </button>
                    <a href="{{ route('admin.attendance') }}" class="btn-logout-top" style="text-decoration:none;color:#334155;border-color:rgba(51,65,85,0.2);background:rgba(148,163,184,0.12);">
                        @include('components.icon', ['name' => 'rotate-ccw'])<span>Reset</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Sudah Tap</span>
                <div class="stat-card-icon">✅</div>
            </div>
            <div class="stat-card-value">{{ $summary['sudah_tap'] }}</div>
            <div class="stat-card-change">Mahasiswa sudah absen</div>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Belum Tap</span>
                <div class="stat-card-icon">⏳</div>
            </div>
            <div class="stat-card-value">{{ $summary['belum_tap'] }}</div>
            <div class="stat-card-change down">Perlu dicek jika jam absen sudah lewat</div>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Tap</span>
                <div class="stat-card-icon">👆</div>
            </div>
            <div class="stat-card-value">{{ $summary['total_tap'] }}</div>
            <div class="stat-card-change">Termasuk tap ulang pada hari ini</div>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card-header">
                <span class="stat-card-title">Persentase Tap</span>
                <div class="stat-card-icon">📈</div>
            </div>
            <div class="stat-card-value">{{ $summary['persentase_tap'] }}%</div>
            <div class="stat-card-change">Dari {{ $summary['total'] }} mahasiswa</div>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-header">
            <div>
                <h3>Status Tap Mahasiswa</h3>
                <span style="font-size:12px;color:var(--text-muted);">Tanggal {{ \Carbon\Carbon::parse($filter['date'])->translatedFormat('d M Y') }}</span>
            </div>
            <a href="{{ route('admin.reports', ['tanggal_mulai' => $filter['date'], 'tanggal_akhir' => $filter['date'], 'kelas' => $filter['kelas'], 'tim' => $filter['tim'], 'aktivitas' => 'absensi']) }}" class="btn-logout-top" style="text-decoration:none;color:#166534;border-color:rgba(22,101,52,0.2);background:rgba(22,163,74,0.1);">
                <span>📈</span> Buka Rekap di Laporan
            </a>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Kelas</th>
                        <th>Tim</th>
                        <th>Finger ID</th>
                        <th>Waktu Tap Terakhir</th>
                        <th>ID Mesin</th>
                        <th>Jumlah Tap</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['nama'] }}</td>
                            <td>{{ $row['nim'] }}</td>
                            <td>{{ $row['kelas'] }}</td>
                            <td>{{ $row['tim'] }}</td>
                            <td style="font-family:monospace;">{{ $row['finger_id'] }}</td>
                            <td style="font-family:monospace;">{{ $row['waktu_tap'] ? $row['waktu_tap']->translatedFormat('d M Y H:i:s') : '-' }}</td>
                            <td style="font-family:monospace;">{{ $row['id_mesin'] }}</td>
                            <td>{{ $row['jumlah_tap'] }}</td>
                            <td>
                                @if($row['status'] === 'Sudah Tap')
                                    <span class="status-badge success">✅ Sudah Tap</span>
                                @else
                                    <span class="status-badge warning">⏳ Belum Tap</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:24px;">Tidak ada mahasiswa sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @include('components.table-pagination', ['paginator' => $rows])
        </div>
    </div>
@endsection

@section('scripts')
@endsection
