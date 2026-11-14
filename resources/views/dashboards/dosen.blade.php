@extends('layouts.dashboard')

@section('title', 'Dashboard Dosen')
@section('page-title', 'Dashboard Dosen')
@section('page-subtitle', 'Absensi seluruh mahasiswa · loker tim PBL Anda')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('content')
    @php
        $a = $analytics;
        $s = $a['stats'];
        $donutLabels = $a['donut']['labels'] ?? ['Hadir unik', 'Belum hadir'];
        $donutValues = $a['donut']['values'] ?? [0, 0];
        $donutColors = ['#22c55e', '#64748b'];
        $donutTotal = max(1, array_sum(array_map('intval', $donutValues)));
    @endphp

    <style>
        .dosen-period-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; margin-bottom: 14px;
        }
        .dosen-live-dot {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 600; color: var(--text-muted);
        }
        .dosen-live-dot::before {
            content: ''; width: 8px; height: 8px; border-radius: 50%;
            background: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,.15);
        }
        .dosen-donut-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(180px, .9fr);
            gap: 18px; align-items: center; min-height: 260px;
        }
        @media (max-width: 720px) { .dosen-donut-layout { grid-template-columns: 1fr; } }
        .dosen-donut-canvas-wrap {
            position: relative; width: 100%; max-width: 280px; margin: 0 auto; aspect-ratio: 1;
        }
        .dosen-donut-legend { display: flex; flex-direction: column; gap: 10px; }
        .dosen-donut-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 12px;
            background: var(--bg-main); border: 1px solid var(--border-subtle);
        }
        .dosen-donut-swatch { width: 10px; height: 36px; border-radius: 999px; flex-shrink: 0; }
        .dosen-donut-item__meta { flex: 1; min-width: 0; }
        .dosen-donut-item__meta strong { display: block; font-size: 13px; }
        .dosen-donut-item__meta small { color: var(--text-muted); font-size: 11px; }
        .dosen-donut-item__val { text-align: right; font-variant-numeric: tabular-nums; }
        .dosen-donut-item__val strong { display: block; font-size: 15px; }
        .dosen-donut-item__val small { color: var(--text-muted); font-size: 11px; font-weight: 700; }
        .dosen-feed-table-wrap { overflow-x: auto; }
        .dosen-feed-table { width: 100%; border-collapse: collapse; min-width: 640px; }
        .dosen-feed-table th {
            text-align: left; font-size: 11px; font-weight: 700; letter-spacing: .05em;
            text-transform: uppercase; color: var(--text-muted); padding: 10px 14px;
            border-bottom: 1px solid var(--border-subtle); white-space: nowrap;
        }
        .dosen-feed-table td {
            padding: 10px 14px; border-bottom: 1px solid var(--border-subtle);
            font-size: 13px; vertical-align: middle;
        }
        .dosen-feed-table tr:last-child td { border-bottom: 0; }
        .dosen-feed-table tbody tr:hover td { background: rgba(148,163,184,.06); }
        .dosen-feed-table tbody tr.dosen-feed-row--masuk { box-shadow: inset 3px 0 0 #0f766e; }
        .dosen-feed-table tbody tr.dosen-feed-row--pulang { box-shadow: inset 3px 0 0 #ea580c; }
        .dosen-shift-pill {
            display: inline-flex; padding: 3px 9px; border-radius: 999px;
            font-size: 11px; font-weight: 700; text-transform: capitalize;
            background: rgba(100,116,139,.14); color: var(--text-muted);
        }
        .dosen-jenis {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 11px; border-radius: 999px;
            font-size: 11.5px; font-weight: 800; letter-spacing: .02em;
            text-transform: uppercase; white-space: nowrap;
        }
        .dosen-jenis .icon-wrap i { width: 14px; height: 14px; }
        .dosen-jenis--masuk {
            background: rgba(15,118,110,.16); color: #0f766e;
            border: 1px solid rgba(15,118,110,.3);
        }
        .dosen-jenis--pulang {
            background: rgba(234,88,12,.14); color: #ea580c;
            border: 1px solid rgba(234,88,12,.3);
        }
        .dosen-jenis--unknown {
            background: rgba(100,116,139,.12); color: var(--text-muted);
            border: 1px solid var(--border-subtle);
        }
        .dosen-feed-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .dosen-feed-late {
            display: inline-block; margin-left: 6px; font-size: 10.5px; font-weight: 700;
            color: #dc2626; background: rgba(220,38,38,.1); padding: 1px 6px; border-radius: 6px;
        }

        /* Pengumuman */
        .dosen-ann {
            margin-top: 16px; overflow: hidden;
            border: 1px solid var(--border-subtle); border-radius: 16px;
            background: var(--bg-card);
        }
        .dosen-ann__head {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 18px; border-bottom: 1px solid var(--border-subtle);
            background: radial-gradient(ellipse 60% 120% at 0% 0%, rgba(15,118,110,.12), transparent 55%);
        }
        .dosen-ann__head h3 {
            margin: 0; font-size: 15px; font-weight: 800; color: var(--text-dark);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .dosen-ann__head h3 .icon-wrap { color: #0f766e; }
        .dosen-ann__link {
            font-size: 12px; color: var(--soft-blue); text-decoration: none; font-weight: 700;
        }
        .dosen-ann__list { display: grid; gap: 0; }
        .dosen-ann__item {
            display: grid; grid-template-columns: 40px minmax(0,1fr) auto;
            gap: 12px; align-items: start;
            padding: 14px 18px; border-bottom: 1px solid var(--border-subtle);
            text-decoration: none; color: inherit; transition: background .12s ease;
        }
        .dosen-ann__item:last-child { border-bottom: 0; }
        .dosen-ann__item:hover { background: rgba(148,163,184,.06); }
        .dosen-ann__icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: grid; place-items: center; flex-shrink: 0;
        }
        .dosen-ann__icon .icon-wrap i { width: 18px; height: 18px; }
        .dosen-ann__icon--normal { background: rgba(15,118,110,.14); color: #0f766e; }
        .dosen-ann__icon--penting { background: rgba(234,88,12,.14); color: #ea580c; }
        .dosen-ann__icon--urgent { background: rgba(220,38,38,.14); color: #dc2626; }
        .dosen-ann__title {
            margin: 0; font-size: 13.5px; font-weight: 800; color: var(--text-dark);
            line-height: 1.35;
        }
        .dosen-ann__body {
            margin: 4px 0 0; font-size: 12.5px; color: var(--text-muted); line-height: 1.5;
        }
        .dosen-ann__foot {
            margin-top: 6px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
            font-size: 11px; color: var(--text-muted);
        }
        .dosen-ann__prio {
            display: inline-flex; padding: 3px 9px; border-radius: 999px;
            font-size: 10.5px; font-weight: 800; text-transform: capitalize; letter-spacing: .02em;
        }
        .dosen-ann__prio--normal { background: rgba(15,118,110,.14); color: #0f766e; }
        .dosen-ann__prio--penting { background: rgba(234,88,12,.14); color: #ea580c; }
        .dosen-ann__prio--urgent { background: rgba(220,38,38,.14); color: #dc2626; }
        @media (max-width: 640px) {
            .dosen-ann__item { grid-template-columns: 36px minmax(0,1fr); }
            .dosen-ann__item .dosen-ann__prio { grid-column: 2; justify-self: start; }
        }
    </style>

    <div class="stat-cards dash-stat-cards-first" id="dash-gcards">
        @include('partials.stat-card', [
            'tone' => 'blue', 'icon' => 'users', 'tag' => 'Mahasiswa', 'label' => 'Mahasiswa aktif',
            'value' => 0, 'valueId' => 'g-total',
            'meta' => 'Semua kelas · minggu ini',
            'href' => route('dosen.mahasiswa'),
        ])
        @include('partials.stat-card', [
            'tone' => 'cyan', 'icon' => 'percent', 'tag' => 'Kehadiran', 'label' => '% kehadiran unik',
            'value' => '0%', 'valueId' => 'g-pct',
            'meta' => $s['persen_meta'] ?? '', 'metaId' => 'g-pct-d',
            'metaClass' => \App\Support\DashboardStatLabels::deltaToneClass($s['persen_delta'] ?? 0),
            'href' => route('dosen.absensi'),
        ])
        @include('partials.stat-card', [
            'tone' => 'teal', 'icon' => 'fingerprint', 'tag' => 'Mingguan', 'label' => 'Tap absensi',
            'value' => 0, 'valueId' => 'g-tap',
            'meta' => $s['tap_meta'] ?? '', 'metaId' => 'g-tap-d',
            'metaClass' => \App\Support\DashboardStatLabels::deltaToneClass($s['tap_delta'] ?? 0),
            'href' => route('dosen.absensi'),
        ])
    </div>

    <div class="dosen-period-row">
        <div class="period-pill" id="dash-period-label">
            @include('components.icon', ['name' => 'calendar-range'])
            <span class="period-pill__text">{{ $a['period_label'] ?? '' }}</span>
        </div>
        <div class="dosen-live-dot">{{ !empty($a['demo']) ? 'Angka contoh pada grafik beranda' : 'Data absensi periode ini' }}</div>
    </div>

    <div class="dash-chart-grid">
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <h3>Grafik kehadiran</h3>
                <span class="chart-panel__sub" id="line-subtitle">{{ $a['line']['subtitle'] ?? '' }}</span>
            </div>
            <div class="chart-panel__body">
                <canvas id="chartLine"></canvas>
            </div>
        </div>
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <h3>Distribusi kehadiran</h3>
                <span class="chart-panel__sub">Mahasiswa unik hadir vs belum</span>
            </div>
            <div class="chart-panel__body">
                <div class="dosen-donut-layout">
                    <div class="dosen-donut-canvas-wrap">
                        <canvas id="chartDonut"></canvas>
                    </div>
                    <div class="dosen-donut-legend" id="dosenDonutLegend">
                        @foreach($donutLabels as $i => $label)
                            @php
                                $val = (int) ($donutValues[$i] ?? 0);
                                $pct = round(($val / $donutTotal) * 100, 1);
                                $color = $donutColors[$i] ?? '#94a3b8';
                            @endphp
                            <div class="dosen-donut-item">
                                <span class="dosen-donut-swatch" style="background:{{ $color }};"></span>
                                <div class="dosen-donut-item__meta">
                                    <strong>{{ $label }}</strong>
                                    <small>Status minggu ini</small>
                                </div>
                                <div class="dosen-donut-item__val">
                                    <strong>{{ number_format($val) }}</strong>
                                    <small>{{ $pct }}%</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-chart-grid" style="margin-top:12px;">
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <h3 id="dosenBarTimTitle">{{ $a['tim_bar']['title'] ?? '% kehadiran per tim PBL' }}</h3>
                <span class="chart-panel__sub" id="dosenBarTimSub">{{ $a['tim_bar']['subtitle'] ?? 'Pantauan tim ampuan' }}</span>
            </div>
            <div class="chart-panel__body">
                <canvas id="chartTimBar"></canvas>
            </div>
        </div>
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <h3 id="dosenBarSesiTitle">{{ $a['sesi_bar']['title'] ?? 'Akses loker tim Anda' }}</h3>
                <span class="chart-panel__sub" id="dosenBarSesiSub">{{ $a['sesi_bar']['subtitle'] ?? 'Aktivitas loker harian' }}</span>
            </div>
            <div class="chart-panel__body">
                <canvas id="chartSesiBar"></canvas>
            </div>
        </div>
    </div>

    <div class="card animate-in" style="margin-top:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <h3 style="margin:0;">Live feed absensi</h3>
                <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);">Tap mesin · bedakan <strong style="color:#0f766e;">Masuk</strong> &amp; <strong style="color:#ea580c;">Pulang</strong></p>
            </div>
            <a href="{{ route('dosen.absensi') }}" style="font-size:12px;color:var(--soft-blue);text-decoration:none;font-weight:600;">Lihat semua →</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="dosen-feed-table-wrap">
                <table class="dosen-feed-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Jenis</th>
                            <th>Shift</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($absensiTerbaru as $log)
                            @php
                                $jenis = strtolower((string) ($log->jenis ?? ''));
                                $isMasuk = $jenis === 'masuk';
                                $isPulang = $jenis === 'pulang';
                                $rowClass = $isMasuk ? 'dosen-feed-row--masuk' : ($isPulang ? 'dosen-feed-row--pulang' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        @include('partials.user-avatar', ['user' => $log->user, 'size' => 34, 'tone' => 'teal'])
                                        <div>
                                            <strong style="display:block;font-size:13px;">{{ $log->user->nama_lengkap ?? '-' }}</strong>
                                            <div class="dosen-feed-meta">
                                                {{ $log->user->nitk_nidn ?? '—' }}
                                                · Tim {{ $log->user->tim ?: '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($isMasuk)
                                        <span class="dosen-jenis dosen-jenis--masuk">
                                            @include('components.icon', ['name' => 'log-in'])
                                            Masuk
                                        </span>
                                    @elseif($isPulang)
                                        <span class="dosen-jenis dosen-jenis--pulang">
                                            @include('components.icon', ['name' => 'log-out'])
                                            Pulang
                                        </span>
                                    @else
                                        <span class="dosen-jenis dosen-jenis--unknown">{{ $log->jenis ?: 'Tap' }}</span>
                                    @endif
                                    @if(($log->menit_terlambat ?? 0) > 0)
                                        <span class="dosen-feed-late" title="Menit terlambat">+{{ (int) $log->menit_terlambat }}m</span>
                                    @endif
                                    @if(!empty($log->pulang_lembur))
                                        <span class="dosen-feed-late" style="color:#ea580c;background:rgba(234,88,12,.12);" title="Pulang lembur">Lembur</span>
                                    @endif
                                </td>
                                <td><span class="dosen-shift-pill">{{ $log->sesi ?? '—' }}</span></td>
                                <td style="white-space:nowrap;color:var(--text-muted);font-variant-numeric:tabular-nums;">
                                    {{ optional($log->waktu_tap)->translatedFormat('d M Y · H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--text-muted);padding:28px;">Belum ada absensi tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(($pengumumanTerbaru ?? collect())->isNotEmpty())
        <section class="dosen-ann animate-in">
            <div class="dosen-ann__head">
                <h3>
                    @include('components.icon', ['name' => 'megaphone'])
                    Pengumuman terbaru
                </h3>
                <a href="{{ route('dosen.announcements') }}" class="dosen-ann__link">Lihat semua →</a>
            </div>
            <div class="dosen-ann__list">
                @foreach($pengumumanTerbaru as $p)
                    @php
                        $prio = $p->prioritas ?: 'normal';
                        $prioIcon = match ($prio) {
                            'urgent' => 'alert-triangle',
                            'penting' => 'bell',
                            default => 'megaphone',
                        };
                    @endphp
                    <a href="{{ route('dosen.announcements') }}" class="dosen-ann__item">
                        <div class="dosen-ann__icon dosen-ann__icon--{{ $prio }}">
                            @include('components.icon', ['name' => $prioIcon])
                        </div>
                        <div>
                            <h4 class="dosen-ann__title">{{ $p->judul }}</h4>
                            <p class="dosen-ann__body">{{ \Illuminate\Support\Str::limit(strip_tags($p->isi), 120) }}</p>
                            <div class="dosen-ann__foot">
                                <span>{{ $p->created_at?->translatedFormat('d M Y · H:i') }}</span>
                                @if($p->author?->nama_lengkap)
                                    <span>· {{ $p->author->nama_lengkap }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="dosen-ann__prio dosen-ann__prio--{{ $prio }}">{{ $prio }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/simalas-charts.js') }}?v=11"></script>
    <script>
(function() {
    const initial = @json($analytics);
    const donutColors = @json($donutColors);
    const lineCtx = document.getElementById('chartLine');
    const donutCtx = document.getElementById('chartDonut');

    function deltaClass(v) {
        v = Number(v || 0);
        if (v === 0) return '';
        return v > 0 ? 'up' : 'down';
    }

    function setCount(id, value, opts) {
        const el = document.getElementById(id);
        if (!el) return;
        if (window.SimalasCharts && typeof SimalasCharts.animateCount === 'function') {
            SimalasCharts.animateCount(el, value, Object.assign({ duration: 1.5 }, opts || {}));
        } else {
            el.textContent = value + ((opts && opts.suffix) || '');
        }
    }

    function renderDonutLegend(labels, values, colors) {
        const box = document.getElementById('dosenDonutLegend');
        if (!box) return;
        const total = (values || []).reduce((a, b) => a + Number(b || 0), 0) || 1;
        box.innerHTML = (labels || []).map((label, i) => {
            const val = Number(values[i] || 0);
            const pct = ((val / total) * 100).toFixed(1);
            const color = colors[i] || '#94a3b8';
            return `<div class="dosen-donut-item">
                <span class="dosen-donut-swatch" style="background:${color};"></span>
                <div class="dosen-donut-item__meta"><strong>${label}</strong><small>Status minggu ini</small></div>
                <div class="dosen-donut-item__val"><strong>${val.toLocaleString('id-ID')}</strong><small>${pct}%</small></div>
            </div>`;
        }).join('');
    }

    function renderCharts(d) {
        const activity = d.aktivitas || 'semua';
        SimalasCharts.buildLineChart(lineCtx, {
            labels: d.line.labels,
            percentLine: d.line.absensi || d.line.values || [],
            activity: 'absensi',
            yMaxPercent: true,
            percentLabel: '% mahasiswa unik (tap)',
        });

        const hadir = Number(d.donut.values?.[0] || 0);
        SimalasCharts.buildDonutChart(donutCtx, {
            labels: d.donut.labels,
            values: d.donut.values,
            colors: donutColors,
            centerMain: (d.stats?.persen_hadir ?? hadir) + '%',
            centerSub: 'Hadir unik',
            hideLegend: true,
        });
        renderDonutLegend(d.donut.labels, d.donut.values, donutColors);

        if (d.tim_bar && document.getElementById('chartTimBar')) {
            const t = document.getElementById('dosenBarTimTitle');
            const s = document.getElementById('dosenBarTimSub');
            if (t && d.tim_bar.title) t.textContent = d.tim_bar.title;
            if (s) s.textContent = d.tim_bar.subtitle || '';
            SimalasCharts.buildBarChart(document.getElementById('chartTimBar'), {
                labels: d.tim_bar.labels || [],
                values: d.tim_bar.values || [],
                colors: d.tim_bar.colors || ['#f59e0b', '#0284c7', '#7c3aed', '#14b8a6'],
            });
        }
        if (d.sesi_bar && document.getElementById('chartSesiBar')) {
            const t = document.getElementById('dosenBarSesiTitle');
            const s = document.getElementById('dosenBarSesiSub');
            if (t && d.sesi_bar.title) t.textContent = d.sesi_bar.title;
            if (s) s.textContent = d.sesi_bar.subtitle || '';
            SimalasCharts.buildBarChart(document.getElementById('chartSesiBar'), {
                labels: d.sesi_bar.labels || [],
                values: d.sesi_bar.values || [],
                colors: d.sesi_bar.colors || ['#38bdf8'],
            });
        }
    }

    function applyPayload(d) {
        const periodEl = document.querySelector('#dash-period-label .period-pill__text');
        if (periodEl) periodEl.textContent = d.period_label || '';
        setCount('g-total', d.stats.total_mahasiswa);
        setCount('g-pct', d.stats.persen_hadir, { suffix: '%' });
        const pd = document.getElementById('g-pct-d');
        pd.textContent = d.stats.persen_meta || '';
        pd.className = 'stat-card__meta ' + deltaClass(d.stats.persen_delta);

        setCount('g-tap', d.stats.tap_absensi, { duration: 1.75 });
        const td = document.getElementById('g-tap-d');
        td.textContent = d.stats.tap_meta || '';
        td.className = 'stat-card__meta ' + deltaClass(d.stats.tap_delta);

        document.getElementById('line-subtitle').textContent = d.line.subtitle || '';
        renderCharts(d);
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    applyPayload(initial);
    document.addEventListener('simalas:themechange', function () {
        renderCharts(initial);
    });
})();
    </script>
@endsection
