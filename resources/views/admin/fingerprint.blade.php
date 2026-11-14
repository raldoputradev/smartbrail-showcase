@extends('layouts.dashboard')

@section('title', 'Fingerprint Enroll')
@section('page-title', 'Fingerprint Enroll')
@section('page-subtitle', 'Status sidik jari mahasiswa & distribusi ke mesin')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/fingerprint-premium.css') }}?v=4">
@endsection

@section('content')
    @include('absensi._snapshot_boot', [
        'snapshotSurface' => 'fingerprint',
        'snapshotUrl' => route('admin.fingerprint.snapshot'),
        'snapshotInterval' => 25000,
        'snapshotChannel' => 'absensi.staff.laboran',
    ])
    @php
        $syncAuto = ($syncPolicy['mode'] ?? 'auto') === 'auto';
        $syncBurst = \App\Support\FingerprintSyncPolicy::burstActive();
        $modeLabel = $syncAuto ? 'Otomatis' : ($syncBurst ? 'Manual · distribusi aktif' : 'Manual');
    @endphp

    <div class="fp-toolbar animate-in">
        <div class="fp-toolbar__left">
            <span class="fp-live"><span class="fp-live__dot"></span>ENROLL</span>
            <div>
                <strong>Sidik jari mahasiswa</strong>
                <small>Slot Finger ID · template blob · sync ke mesin</small>
            </div>
        </div>
        <div class="fp-toolbar__right">
            <div class="fp-meter">
                <div class="fp-meter__meta">
                    <span>Progress</span>
                    <strong id="fp-stat-rate">{{ $fingerprintStats['completion_rate'] }}%</strong>
                </div>
                <div class="fp-meter__track"><i id="fp-stat-meter" style="width:{{ min(100, $fingerprintStats['completion_rate']) }}%"></i></div>
                <small id="fp-stat-enrolled">{{ number_format($fingerprintStats['enrolled']) }} / {{ number_format($fingerprintStats['total']) }}</small>
            </div>
            <button type="button" class="fp-btn fp-btn--ghost" onclick="openFpHelp('status')">
                Panduan <span class="fp-help-q">?</span>
            </button>
        </div>
    </div>

    <div class="fp-stat-grid animate-in">
        <div class="fp-stat-card fp-stat-card--ready">
            <div class="fp-stat-card__top">
                <span class="fp-stat-card__label">Sudah enroll</span>
                <button type="button" class="fp-help-q fp-help-q--sm" onclick="openFpHelp('status')" aria-label="Bantuan">?</button>
            </div>
            <strong class="fp-stat-card__value">{{ number_format($fingerprintStats['enrolled']) }}</strong>
            <span class="fp-stat-card__hint">Blob OK di server</span>
        </div>
        <div class="fp-stat-card fp-stat-card--warn">
            <div class="fp-stat-card__top">
                <span class="fp-stat-card__label">Belum enroll</span>
            </div>
            <strong class="fp-stat-card__value">{{ number_format($fingerprintStats['pending_enroll']) }}</strong>
            <span class="fp-stat-card__hint">Punya slot, belum scan 2×</span>
        </div>
        <div class="fp-stat-card fp-stat-card--danger">
            <div class="fp-stat-card__top">
                <span class="fp-stat-card__label">Belum assign</span>
            </div>
            <strong class="fp-stat-card__value">{{ number_format($fingerprintStats['unassigned']) }}</strong>
            <span class="fp-stat-card__hint">Perlu Finger ID</span>
        </div>
        <div class="fp-stat-card fp-stat-card--info">
            <div class="fp-stat-card__top">
                <span class="fp-stat-card__label">Kapasitas slot</span>
            </div>
            <strong class="fp-stat-card__value">{{ number_format($fingerprintStats['capacity_used']) }}/{{ number_format($fingerprintStats['capacity_total']) }}</strong>
            <span class="fp-stat-card__hint">Slot sensor terpakai</span>
        </div>
    </div>

    <div class="fp-charts animate-in">
        <section class="fp-chart-card">
            <div class="fp-chart-card__head">
                <div>
                    <h3>Status enroll</h3>
                    <p>Sudah · belum scan · belum assign</p>
                </div>
            </div>
            <div class="fp-chart-card__body fp-chart-card__body--donut">
                <canvas id="fpChartStatus" height="220"></canvas>
            </div>
        </section>
        <section class="fp-chart-card">
            <div class="fp-chart-card__head">
                <div>
                    <h3>Tren enroll kumulatif</h3>
                    <p>{{ $fingerprintCharts['enroll_trend']['subtitle'] ?? 'Blob OK di server' }}</p>
                </div>
            </div>
            <div class="fp-chart-card__body">
                <canvas id="fpChartTrend" height="220"></canvas>
            </div>
        </section>
        <section class="fp-chart-card">
            <div class="fp-chart-card__head">
                <div>
                    <h3>Enroll per kelas</h3>
                    <p>{{ $fingerprintCharts['kelas_bar']['subtitle'] ?? 'Sudah enroll' }}</p>
                </div>
            </div>
            <div class="fp-chart-card__body">
                <canvas id="fpChartKelas" height="220"></canvas>
            </div>
        </section>
        <section class="fp-chart-card">
            <div class="fp-chart-card__head">
                <div>
                    <h3>Aktivitas sync mesin</h3>
                    <p>{{ $fingerprintCharts['sync_bar']['subtitle'] ?? 'Pull mingguan' }}</p>
                </div>
            </div>
            <div class="fp-chart-card__body">
                <canvas id="fpChartSync" height="220"></canvas>
            </div>
        </section>
    </div>

    <section class="fp-sync animate-in">
        <div class="fp-sync__head">
            <div>
                <h3>
                    Distribusi template ke mesin
                    <button type="button" class="fp-help-q" onclick="openFpHelp('distribusi')" title="Panduan distribusi" aria-label="Panduan distribusi">?</button>
                </h3>
                <p>Enroll di absensi → simpan di server → mesin pull via sync</p>
            </div>
            <span class="fp-sync__badge {{ $syncAuto ? 'is-auto' : 'is-manual' }}">Mode: {{ $modeLabel }}</span>
        </div>

        <div class="fp-sync__steps">
            <div class="fp-sync__step">
                <span class="fp-sync__n">1</span>
                <div>
                    <strong>Mesin absensi</strong>
                    <small>Scan 2× → upload blob</small>
                </div>
            </div>
            <div class="fp-sync__step">
                <span class="fp-sync__n">2</span>
                <div>
                    <strong>Database</strong>
                    <small>Template per Finger ID</small>
                </div>
            </div>
            <div class="fp-sync__step">
                <span class="fp-sync__n">3</span>
                <div>
                    <strong>Mesin absensi lain</strong>
                    <small>Pull template lewat sync</small>
                </div>
            </div>
        </div>

        <div class="fp-sync__actions">
            <form method="POST" action="{{ route('admin.fingerprint.sync-mode') }}">
                @csrf
                <input type="hidden" name="mode" value="auto">
                <button type="submit" class="fp-btn fp-btn--primary {{ $syncAuto ? 'is-active' : '' }}">Sync otomatis</button>
            </form>
            <form method="POST" action="{{ route('admin.fingerprint.sync-mode') }}">
                @csrf
                <input type="hidden" name="mode" value="manual">
                <button type="submit" class="fp-btn {{ !$syncAuto ? 'is-active' : '' }}">Manual (jeda pull)</button>
            </form>
            <form method="POST" action="{{ route('admin.fingerprint.sync-distribute') }}">
                @csrf
                <button type="submit" class="fp-btn fp-btn--warn">Distribusi sekarang (20 menit)</button>
            </form>
            <button type="button" class="fp-help-link" onclick="openFpHelp('mode')">Apa bedanya mode?</button>
        </div>
    </section>

    <div class="card animate-in fp-mhs-card">
        <div class="fp-mhs-card__head">
            <div>
                <h3>Daftar mahasiswa</h3>
                <span id="fp-stat-hasil">{{ $mahasiswa->total() }} hasil filter</span>
            </div>
            <div class="fp-mhs-card__tools">
                <button type="button" class="fp-help-q" onclick="openFpHelp('aksi')" title="Panduan aksi" aria-label="Panduan aksi">?</button>
                <form method="POST" action="{{ route('admin.fingerprint.backfill-shift') }}">
                    @csrf
                    <button type="submit" class="fp-btn" title="Isi shift pagi/malam otomatis">Perbaiki shift kosong</button>
                </form>
            </div>
        </div>

        <div class="fp-mhs-card__filters">
            <form method="GET" action="{{ route('admin.fingerprint') }}" class="filter-toolbar filter-toolbar-grid fingerprint-data-filter">
                @if(($filter['sync_mesin'] ?? '') !== '')
                    <input type="hidden" name="sync_mesin" value="{{ $filter['sync_mesin'] }}">
                @endif
                <div class="filter-field filter-field--wide">
                    <label class="fp-text-bright">Cari mahasiswa</label>
                    <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Nama, NIM, atau email">
                </div>
                <x-comm-premium-select name="status" id="fingerprintStatusFilter" label="Status enroll" icon="fingerprint" class="filter-toolbar__premium-select filter-toolbar__premium-select--wide">
                    <option value="">Semua status</option>
                    <option value="enrolled" @selected($filter['status'] === 'enrolled')>Blob OK</option>
                    <option value="pending_enroll" @selected($filter['status'] === 'pending_enroll')>Belum enroll</option>
                    <option value="legacy_re_enroll" @selected($filter['status'] === 'legacy_re_enroll')>Legacy (enroll ulang)</option>
                    <option value="unassigned" @selected($filter['status'] === 'unassigned')>Belum assign Finger ID</option>
                    <option value="inactive" @selected($filter['status'] === 'inactive')>Tidak aktif</option>
                </x-comm-premium-select>
                <x-comm-premium-select name="kelas" id="fingerprintKelasFilter" label="Kelas" icon="users" class="filter-toolbar__premium-select">
                    <option value="">Semua kelas</option>
                    @foreach($kelasOptions ?? [] as $kelasOpt)
                        <option value="{{ $kelasOpt }}" @selected(($filter['kelas'] ?? '') === $kelasOpt)>{{ $kelasOpt }}</option>
                    @endforeach
                </x-comm-premium-select>
                <x-comm-premium-select name="tim" id="fingerprintTimFilter" label="Tim PBL" icon="folder-kanban" class="filter-toolbar__premium-select">
                    <option value="">Semua tim</option>
                    @foreach($timOptions ?? [] as $timOpt)
                        <option value="{{ $timOpt }}" @selected(($filter['tim'] ?? '') === $timOpt)>{{ $timOpt }}</option>
                    @endforeach
                </x-comm-premium-select>
                @include('partials.filter-actions', ['resetUrl' => route('admin.fingerprint'), 'submitLabel' => 'Filter'])
            </form>
        </div>

        <div class="fp-table-wrap" data-snap-live="fingerprint" data-snap-scroll>
            <table class="data-table fingerprint-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Kelas / Tim</th>
                        <th>Shift</th>
                        <th>Finger ID</th>
                        <th>Status</th>
                        <th>Kesiapan</th>
                        <th class="is-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="fp-rows">
                    @include('admin._fingerprint_rows', ['mahasiswa' => $mahasiswa])
                </tbody>
            </table>
            <div class="fp-pager" id="fp-pager">{{ $mahasiswa->links() }}</div>
        </div>
    </div>

    <section class="fp-log animate-in">
        <div class="fp-log__head">
            <div>
                <h3>
                    Log sync template
                    <button type="button" class="fp-help-q" onclick="openFpHelp('log')" title="Panduan log" aria-label="Panduan log">?</button>
                </h3>
                <p>
                    Hari ini: <span id="fp-stat-sync-today">{{ number_format($syncReport['stats']['today']) }}</span> request ·
                    {{ number_format($syncReport['stats']['pull_today']) }} pull ·
                    {{ number_format($syncReport['stats']['templates_today']) }} template
                </p>
            </div>
            <button type="button" class="fp-btn fp-btn--ghost" id="fpLogToggle" onclick="toggleFpLog()">Tampilkan log</button>
        </div>

        <div class="fp-log__body" id="fpLogBody" hidden>
            <form method="GET" action="{{ route('admin.fingerprint') }}" class="sync-log-filter fingerprint-sync-log-filter">
                @foreach(['q', 'status', 'kelas', 'tim'] as $fk)
                    @if(($filter[$fk] ?? '') !== '')
                        <input type="hidden" name="{{ $fk }}" value="{{ $filter[$fk] }}">
                    @endif
                @endforeach
                <x-comm-premium-select name="sync_mesin" id="syncMesinFilter" label="Filter mesin" icon="cpu">
                    <option value="">Semua mesin</option>
                    @foreach($syncMesinOptions as $m)
                        <option value="{{ $m->id_mesin }}" @selected($filter['sync_mesin'] === $m->id_mesin)>
                            {{ $m->id_mesin }} ({{ $m->tipe }})
                        </option>
                    @endforeach
                </x-comm-premium-select>
                <div class="filter-toolbar__actions">
                    <button type="submit" class="btn-premium btn-premium--primary">Filter log</button>
                    <a href="{{ route('admin.fingerprint', array_filter(['q' => $filter['q'], 'status' => $filter['status'], 'kelas' => $filter['kelas'] ?? '', 'tim' => $filter['tim'] ?? ''])) }}" class="fp-btn" style="text-decoration:none;">Reset</a>
                </div>
            </form>

            <div class="fp-table-wrap">
                <table class="data-table fingerprint-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Mesin</th>
                            <th>Tipe</th>
                            <th>Jenis</th>
                            <th>Batch</th>
                            <th>Delta</th>
                            <th>Template</th>
                            <th>Finger ID</th>
                            <th>Token</th>
                            <th>IP</th>
                            <th>Hasil</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="fp-sync-rows">
                        @include('admin._fingerprint_sync_rows', ['rows' => $syncReport['rows']])
                    </tbody>
                </table>
            </div>
            @if(isset($syncReport['rows']) && $syncReport['rows'] instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                @include('components.table-pagination', ['paginator' => $syncReport['rows']])
            @endif
        </div>
    </section>

    <div class="iot-modal-backdrop" id="fpHelpModal" role="dialog" aria-modal="true">
        <div class="iot-modal fp-help-modal">
            <button type="button" class="iot-modal-close" onclick="closeFpHelp()" aria-label="Tutup">×</button>
            <div class="fp-help-badge">PANDUAN</div>
            <h3 id="fpHelpTitle">Panduan</h3>
            <p id="fpHelpDesc" class="fp-help-desc"></p>
            <div class="fp-help-code" id="fpHelpCode"></div>
            <div class="fp-help-actions">
                <button type="button" class="fp-btn fp-btn--primary" onclick="closeFpHelp()">Mengerti</button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/simalas-charts.js') }}?v=6"></script>
<script>
    const FP_HELP = {
        status: {
            title: 'Status sidik jari',
            desc: 'Finger ID = nomor slot di sensor. Status di bawah menjelaskan kesiapan mahasiswa di server SIMALAS.',
            code: 'Blob OK     → template 512-byte tersimpan, siap sync ke semua mesin\nBelum enroll → sudah punya Finger ID, belum scan 2× di mesin absensi\nLegacy      → data lama (SENSOR_ENROLLED), wajib enroll ulang\nBelum assign → belum punya nomor slot fingerprint\n\nMahasiswa juga wajib punya shift absensi (pagi/malam).'
        },
        distribusi: {
            title: 'Distribusi template ke mesin',
            desc: 'Alur normal: mahasiswa enroll di mesin absensi, blob tersimpan di database, lalu mesin absensi lain pull lewat API sync (~5 menit).',
            code: '1. Mesin absensi  → scan 2×, upload template ke server\n2. Database       → menyimpan blob per Finger ID\n3. Mesin absensi lain → pull template lewat sync\n\nLog pull yang banyak (mis. 1418) = batch kecil berulang saat mesin menyamakan slot — bukan duplikat mahasiswa.'
        },
        mode: {
            title: 'Mode sync otomatis vs manual',
            desc: 'Mode mengatur apakah mesin boleh pull template secara berkala dari server.',
            code: 'Sync otomatis → mesin pull periodik (~5 menit)\nManual        → jeda pull (berguna saat maintenance)\nDistribusi sekarang → buka jendela pull aktif ±20 menit meski mode manual'
        },
        aksi: {
            title: 'Aksi di daftar mahasiswa',
            desc: 'Tombol di kolom Aksi untuk kelola slot dan enroll ulang tanpa hapus mahasiswa.',
            code: 'Simpan     → set / ubah Finger ID (1–200, atau kosongkan untuk auto)\nUlang enroll → hapus blob di server, slot tetap; scan 2× lagi di mesin (tombol e)\nReset      → hapus Finger ID + template sepenuhnya\nPerbaiki shift kosong → isi shift pagi/malam otomatis dari data PBL'
        },
        log: {
            title: 'Log sync template',
            desc: 'Setiap baris = satu request API dari mesin absensi ke server SIMALAS.',
            code: 'Pull template → server kirim batch kecil (maks. 5) ke mesin\nArah: server → mesin (bukan upload mahasiswa baru)\nAngka besar = banyak polling periodik, bukan duplikat enroll'
        }
    };

    function openFpHelp(kind) {
        const data = FP_HELP[kind] || FP_HELP.status;
        const modal = document.getElementById('fpHelpModal');
        const title = document.getElementById('fpHelpTitle');
        const desc = document.getElementById('fpHelpDesc');
        const code = document.getElementById('fpHelpCode');
        if (!modal || !title || !desc || !code) return;
        title.textContent = data.title;
        desc.textContent = data.desc;
        code.textContent = data.code || '';
        modal.style.display = 'flex';
        modal.classList.add('is-open');
    }

    function closeFpHelp() {
        const modal = document.getElementById('fpHelpModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    function toggleFpLog() {
        const body = document.getElementById('fpLogBody');
        const btn = document.getElementById('fpLogToggle');
        if (!body || !btn) return;
        const open = body.hasAttribute('hidden');
        if (open) body.removeAttribute('hidden');
        else body.setAttribute('hidden', '');
        btn.textContent = open ? 'Sembunyikan log' : 'Tampilkan log';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('fpHelpModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeFpHelp();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeFpHelp();
        });

        @if(($filter['sync_mesin'] ?? '') !== '')
            toggleFpLog();
        @endif

        const charts = @json($fingerprintCharts ?? []);
        if (window.SimalasCharts && charts.status_donut) {
            const status = document.getElementById('fpChartStatus');
            const trend = document.getElementById('fpChartTrend');
            const kelas = document.getElementById('fpChartKelas');
            const sync = document.getElementById('fpChartSync');
            if (status) {
                SimalasCharts.buildDonutChart(status, {
                    labels: charts.status_donut.labels,
                    values: charts.status_donut.values,
                    colors: charts.status_donut.colors,
                    centerMain: charts.status_donut.center,
                    centerSub: charts.status_donut.center_sub || 'Mahasiswa',
                });
            }
            if (trend) {
                SimalasCharts.buildMultiLineChart(trend, {
                    labels: charts.enroll_trend.labels,
                    series: [{
                        key: 'enroll',
                        label: 'Blob tersimpan',
                        color: '#0f766e',
                        data: charts.enroll_trend.values,
                    }],
                    fill: true,
                    yTitle: 'Mahasiswa',
                });
            }
            if (kelas) {
                SimalasCharts.buildBarChart(kelas, {
                    labels: charts.kelas_bar.labels,
                    values: charts.kelas_bar.values,
                    colors: charts.kelas_bar.colors,
                });
            }
            if (sync) {
                SimalasCharts.buildBarChart(sync, {
                    labels: charts.sync_bar.labels,
                    values: charts.sync_bar.values,
                    colors: charts.sync_bar.colors,
                });
            }
        }

        if (window.refreshLucideIcons) window.refreshLucideIcons();
    });
</script>
@endsection
