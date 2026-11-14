@extends('layouts.dashboard')

@section('title', 'Rekap Absensi')
@section('page-title', $panel['is_laboran'] ? 'Rekap Absensi' : 'Rekap Absensi Mahasiswa')
@section('page-subtitle')
{{ $kebijakanLabel ?? '—' }} · {{ \Carbon\Carbon::parse($filter['date'])->translatedFormat('l, d M Y') }}
@endsection

@section('sidebar-nav')
    @include($panel['sidebar_include'])
@endsection

@section('styles')
@endsection

@section('content')
    @php
        $tab = $tab ?? 'all';
        $tabCounts = $tabCounts ?? ['all' => 0, 'alfa' => 0, 'tidak_lengkap' => 0, 'hadir' => 0];
        $chartSummary = $chartSummary ?? ['labels' => [], 'values' => [], 'colors' => []];
        $isLaboran = $panel['is_laboran'] ?? false;
        $canKoreksi = $canKoreksi ?? false;
        $queryBase = request()->except(['page', 'tab']);
    @endphp

    @include('absensi._styles')
    @include('absensi._snapshot_boot', [
        'snapshotSurface' => 'rekap',
        'snapshotUrl' => route($panel['route_rekap_snapshot']),
        'snapshotInterval' => 20000,
        'snapshotChannel' => ($isLaboran ?? false) ? 'absensi.staff.laboran' : 'absensi.staff.dosen',
    ])

    <div class="rk-toolbar animate-in">
        <div class="rk-toolbar__left">
            <span class="rk-live"><span class="rk-live__dot"></span>REKAP</span>
            <div>
                <strong>Rekap harian</strong>
                <small>
                    @if($hariIniWajib ?? true)
                        Hari wajib absensi
                    @else
                        Hari non-wajib — tap sukarela tetap dicatat
                    @endif
                    @unless($isLaboran)
                        · mode lihat saja
                    @endunless
                </small>
            </div>
        </div>
        <div class="rk-toolbar__right">
            <button type="button" class="rk-btn rk-btn--ghost" onclick="openRekapHelp()">
                Panduan <span class="rk-help-q">?</span>
            </button>
            @if($isLaboran)
                <a href="{{ route('admin.absensi.settings') }}" class="rk-btn rk-btn--ghost">Atur Absensi</a>
            @endif
            @include('absensi._export_buttons', ['panel' => $panel])
        </div>
    </div>

    @if(session('success'))
        <div class="rk-flash rk-flash--ok animate-in">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rk-flash rk-flash--bad animate-in">{{ session('error') }}</div>
    @endif

    <div class="rk-stats animate-in">
        @include('partials.absensi-summary-stats', ['summary' => $summary])
    </div>

    <div class="rk-charts animate-in">
        <section class="rk-card rk-card--chart">
            <div class="rk-card__head">
                <div>
                    <h3>Distribusi kehadiran</h3>
                    <p>Perbandingan status mahasiswa hari ini</p>
                </div>
                <button type="button" class="rk-help-q" onclick="openRekapHelp('status')" aria-label="Panduan status">?</button>
            </div>
            <div class="rk-chart-wrap">
                <canvas id="rekapDonutChart" height="220"></canvas>
            </div>
        </section>
        <section class="rk-card rk-card--chart">
            <div class="rk-card__head">
                <div>
                    <h3>Ringkasan bar</h3>
                    <p>Hadir · masalah · belum / non-wajib</p>
                </div>
            </div>
            <div class="rk-chart-wrap">
                <canvas id="rekapBarChart" height="220"></canvas>
            </div>
        </section>
    </div>

    @if(($filter['date'] ?? '') === now()->toDateString())
        <section class="rk-card animate-in">
            <div class="rk-card__head">
                <div>
                    <h3>Urutan tap masuk hari ini</h3>
                    <p>Siapa duluan absen — live feed mesin</p>
                </div>
                <button type="button" class="rk-help-q" onclick="openRekapHelp('tap')" aria-label="Panduan tap">?</button>
            </div>
            <div class="rk-table-wrap">
                <table class="absensi-table rk-table">
                    <thead>
                        <tr>
                            <th style="width:48px;">#</th>
                            <th>Jam tap</th>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Shift</th>
                            <th>Slot</th>
                            <th>Telat</th>
                        </tr>
                    </thead>
                    <tbody id="rekap-tap-rows">
                        @include('absensi._rekap_tap_rows', ['tapMasukHariIni' => $tapMasukHariIni ?? []])
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="rk-tabs animate-in">
        <a href="{{ route($panel['route_rekap'], array_merge($queryBase, ['tab' => 'all'])) }}" class="rk-tab {{ $tab === 'all' ? 'is-active' : '' }}">
            Semua <span data-snap-count="all">{{ number_format($tabCounts['all']) }}</span>
        </a>
        <a href="{{ route($panel['route_rekap'], array_merge($queryBase, ['tab' => 'hadir'])) }}" class="rk-tab {{ $tab === 'hadir' ? 'is-active' : '' }}">
            Sudah tap <span data-snap-count="hadir">{{ number_format($tabCounts['hadir']) }}</span>
        </a>
        <a href="{{ route($panel['route_rekap'], array_merge($queryBase, ['tab' => 'alfa'])) }}" class="rk-tab {{ $tab === 'alfa' ? 'is-active' : '' }}">
            Alfa <span data-snap-count="alfa">{{ number_format($tabCounts['alfa']) }}</span>
        </a>
        <a href="{{ route($panel['route_rekap'], array_merge($queryBase, ['tab' => 'tidak_lengkap'])) }}" class="rk-tab {{ $tab === 'tidak_lengkap' ? 'is-active' : '' }}">
            Tidak lengkap <span data-snap-count="tidak_lengkap">{{ number_format($tabCounts['tidak_lengkap']) }}</span>
        </a>
    </div>

    <section class="rk-card animate-in">
        <div class="rk-card__head rk-card__head--wrap">
            <div>
                <h3>Detail mahasiswa</h3>
                <p>
                    @if($tab === 'alfa') Daftar alfa hari ini
                    @elseif($tab === 'tidak_lengkap') Tidak lengkap / hanya masuk
                    @elseif($tab === 'hadir') Yang sudah tap — terbaru di atas
                    @else Semua status · yang sudah tap di depan
                    @endif
                    @unless($canKoreksi) · viewer @endunless
                </p>
            </div>
            <button type="button" class="rk-help-q" onclick="openRekapHelp('koreksi')" aria-label="Panduan">?</button>
        </div>

        <form method="GET" action="{{ route($panel['route_rekap']) }}" class="rk-filters">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Nama / NIM">
            <select name="kelas">
                <option value="">Semua kelas</option>
                @foreach($kelasOptions as $k)<option value="{{ $k }}" @selected($filter['kelas']===$k)>{{ $k }}</option>@endforeach
            </select>
            <select name="tim">
                <option value="">Semua tim</option>
                @foreach($timOptions as $t)<option value="{{ $t }}" @selected($filter['tim']===$t)>{{ $t }}</option>@endforeach
            </select>
            <select name="semester">
                <option value="">Semua semester</option>
                @foreach($semesterOptions ?? [] as $s)<option value="{{ $s }}" @selected(($filter['semester'] ?? '')===(string)$s)>{{ $s }}</option>@endforeach
            </select>
            <select name="shift">
                <option value="">Semua shift</option>
                <option value="pagi" @selected($filter['shift']==='pagi')>Pagi</option>
                <option value="malam" @selected($filter['shift']==='malam')>Malam</option>
            </select>
            @if($tab === 'all')
                <select name="status">
                    <option value="">Semua status</option>
                    @foreach(['belum','masuk','lengkap','tidak_lengkap','alfa','libur','tidak_wajib'] as $st)
                        <option value="{{ $st }}" @selected($filter['status']===$st)>{{ str_replace('_',' ', $st) }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="rk-btn rk-btn--primary">Filter</button>
        </form>

        <div class="rk-table-wrap" data-snap-live="rekap" data-snap-scroll>
            <table class="absensi-table rk-table">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Shift</th>
                        <th>Status</th>
                        <th>Slot</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Telat</th>
                        @if($canKoreksi)<th class="is-right">Aksi</th>@endif
                    </tr>
                </thead>
                <tbody id="rekap-rows">
                    @include('absensi._rekap_detail_rows', ['rows' => $rows, 'canKoreksi' => $canKoreksi, 'panel' => $panel])
                </tbody>
            </table>
            <div id="rekap-pager">
                @include('components.table-pagination', ['paginator' => $rows])
            </div>
        </div>
    </section>

    <div class="iot-modal-backdrop" id="rekapHelpModal" role="dialog" aria-modal="true">
        <div class="iot-modal rk-help-modal">
            <button type="button" class="iot-modal-close" onclick="closeRekapHelp()" aria-label="Tutup">×</button>
            <div class="rk-help-badge">PANDUAN</div>
            <h3 id="rekapHelpTitle">Panduan rekap</h3>
            <p id="rekapHelpDesc" class="rk-help-desc"></p>
            <div class="rk-help-code" id="rekapHelpCode"></div>
            <div class="rk-help-actions">
                <button type="button" class="rk-btn rk-btn--primary" onclick="closeRekapHelp()">Mengerti</button>
            </div>
        </div>
    </div>

    @if($canKoreksi)
        @include('absensi._koreksi_modal')
    @endif
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/simalas-charts.js') }}?v=8"></script>
<script>
    const REKAP_HELP = {
        general: {
            title: 'Cara baca Rekap Harian',
            desc: 'Halaman ini menampilkan status absensi semua mahasiswa untuk hari ini. Alfa dan Tidak lengkap digabung di tab — tidak perlu menu sidebar terpisah.',
            code: 'Hadir / lengkap  → masuk + pulang tercatat\nHanya masuk     → sudah tap masuk, belum pulang\nTidak lengkap   → sesi ditutup tanpa pulang\nAlfa            → hari wajib tanpa tap sama sekali\nTidak wajib     → hari non-jadwal (bukan alfa)\n\nTap di hari non-wajib = sukarela, langsung muncul di depan daftar.'
        },
        status: {
            title: 'Status di grafik',
            desc: 'Grafik membedakan kehadiran agar admin/dosen cepat melihat proporsi masalah vs hadir.',
            code: 'Hadir      = lengkap tepat waktu\nTerlambat  = ada menit telat masuk/pulang\nAlfa       = tanpa tap di hari wajib\nBelum tap  = hari wajib, belum ada data\nTidak wajib = di luar jadwal kebijakan'
        },
        tap: {
            title: 'Urutan tap masuk',
            desc: 'Feed live dari mesin ESP32. #1 = mahasiswa yang paling awal tap masuk hari ini.',
            code: 'Slot lab (S1–S10) dihitung dari jam standar masuk sesi pagi.\nTelat = menit di luar toleransi jam standar.'
        },
        koreksi: {
            title: 'Koreksi & peran',
            desc: @json($canKoreksi
                ? 'Anda login sebagai admin laboran — bisa mengkoreksi status mahasiswa.'
                : 'Anda login sebagai viewer (dosen) — hanya melihat data, tanpa tombol koreksi.'),
            code: 'Admin laboran → Koreksi status, batalkan alfa, tandai selesai\nDosen         → Lihat rekap, filter, grafik, export\n\nTab Alfa / Tidak lengkap = filter cepat di halaman yang sama.'
        }
    };

    function openRekapHelp(kind) {
        const data = REKAP_HELP[kind] || REKAP_HELP.general;
        const modal = document.getElementById('rekapHelpModal');
        const title = document.getElementById('rekapHelpTitle');
        const desc = document.getElementById('rekapHelpDesc');
        const code = document.getElementById('rekapHelpCode');
        if (!modal || !title || !desc || !code) return;
        title.textContent = data.title;
        desc.textContent = data.desc;
        code.textContent = data.code || '';
        modal.style.display = 'flex';
        modal.classList.add('is-open');
    }

    function closeRekapHelp() {
        const modal = document.getElementById('rekapHelpModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const chart = @json($chartSummary);
        if (window.SimalasCharts && chart.labels) {
            const donut = document.getElementById('rekapDonutChart');
            const bar = document.getElementById('rekapBarChart');
            if (donut) {
                SimalasCharts.buildDonutChart(donut, {
                    labels: chart.labels,
                    values: chart.values,
                    colors: chart.colors,
                    centerMain: {{ (int) ($summary['total'] ?? 0) }},
                    centerSub: 'Mahasiswa',
                });
            }
            if (bar) {
                const groups = {
                    labels: ['Hadir+', 'Masalah', 'Belum / non-wajib'],
                    values: [
                        (chart.values[0] || 0) + (chart.values[1] || 0) + (chart.values[2] || 0),
                        (chart.values[3] || 0) + (chart.values[4] || 0),
                        (chart.values[5] || 0) + (chart.values[6] || 0),
                    ],
                    colors: ['#0f766e', '#e11d48', '#94a3b8'],
                };
                SimalasCharts.buildBarChart(bar, {
                    labels: groups.labels,
                    values: groups.values,
                    colors: groups.colors,
                });
            }
        }

        const helpModal = document.getElementById('rekapHelpModal');
        if (helpModal) {
            helpModal.addEventListener('click', (e) => {
                if (e.target === helpModal) closeRekapHelp();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeRekapHelp();
        });

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.btn-koreksi-rekap');
            if (!btn) return;
            openAbsensiKoreksi({
                action: btn.dataset.action,
                title: 'Koreksi absensi harian',
                status: btn.dataset.status === 'belum' || btn.dataset.status === 'libur' || btn.dataset.status === 'tidak_wajib' ? 'lengkap' : btn.dataset.status,
                meta: `<strong>${btn.dataset.nama}</strong> (${btn.dataset.nim})<br>${btn.dataset.tanggal}`,
            });
        });

        if (window.refreshLucideIcons) window.refreshLucideIcons();
    });
</script>
@endsection
