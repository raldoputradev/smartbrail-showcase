@extends('layouts.dashboard')

@section('title', 'Monitoring Sistem')
@section('page-title', 'Monitoring Sistem')
@section('page-subtitle', 'Heartbeat mesin IoT & aktivitas realtime')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @php
        $m = $metrics;
        $threshold = (int) ($onlineThresholdMinutes ?? 5);
    @endphp

    <div class="mon-toolbar animate-in">
        <div class="mon-toolbar__left">
            <span class="mon-live" id="monLiveBadge">
                <span class="mon-live__dot"></span>
                LIVE
            </span>
            <div class="mon-toolbar__meta">
                <strong>Watchdesk mesin</strong>
                <small>
                    Online = heartbeat/tap ≤ {{ $threshold }} menit ·
                    Sync: <span id="monLatestSync">{{ $m['latest_sync'] }}</span>
                </small>
            </div>
        </div>
        <div class="mon-toolbar__right">
            <span class="mon-refresh-hint" id="monRefreshHint">Refresh otomatis 15 dtk</span>
            <a href="{{ route('admin.mesin.index') }}" class="mon-btn">
                @include('components.icon', ['name' => 'cpu'])
                <span>Kelola mesin</span>
            </a>
        </div>
    </div>

    <div class="mon-kpi animate-in">
        <div class="mon-kpi__item mon-kpi__item--ok">
            <div class="mon-kpi__icon">@include('components.icon', ['name' => 'wifi'])</div>
            <div>
                <span>Online</span>
                <strong id="kpiOnline">{{ number_format($m['machine_online']) }}</strong>
                <small>dari <span id="kpiTotal">{{ number_format($m['machine_total']) }}</span> mesin</small>
            </div>
        </div>
        <div class="mon-kpi__item mon-kpi__item--warn">
            <div class="mon-kpi__icon">@include('components.icon', ['name' => 'wifi-off'])</div>
            <div>
                <span>Offline</span>
                <strong id="kpiOffline">{{ number_format($m['machine_offline']) }}</strong>
                <small>aktif, belum heartbeat</small>
            </div>
        </div>
        <div class="mon-kpi__item mon-kpi__item--blue">
            <div class="mon-kpi__icon">@include('components.icon', ['name' => 'fingerprint'])</div>
            <div>
                <span>Tap absensi</span>
                <strong id="kpiAbsensi">{{ number_format($m['absensi_today']) }}</strong>
                <small>hari ini</small>
            </div>
        </div>
    </div>

    <div class="mon-charts animate-in">
        <div class="card mon-chart-card">
            <div class="card-header mon-card-head">
                <div>
                    <h3>Aktivitas hari ini</h3>
                    <span>Event per jam (06–22) · tap absensi</span>
                </div>
                <strong class="mon-events-total" id="kpiEvents">{{ number_format($m['events_today']) }} event</strong>
            </div>
            <div class="card-body mon-chart-body">
                <canvas id="monHourlyChart"></canvas>
            </div>
        </div>
        <div class="card mon-chart-card mon-health-card">
            <div class="card-header mon-card-head">
                <div>
                    <h3>Kesehatan armada</h3>
                    <span>Proporsi status mesin</span>
                </div>
            </div>
            <div class="card-body mon-health-body">
                <div class="mon-health-canvas">
                    <canvas id="monHealthChart"></canvas>
                    <div class="mon-health-center">
                        <strong id="monHealthCenterMain">{{ (int) ($metrics['machine_total'] ?? 0) }}</strong>
                        <small>Mesin</small>
                    </div>
                </div>
                <div class="mon-health-legend" id="monHealthLegend"></div>
            </div>
        </div>
    </div>

    <div class="mon-tables animate-in">
        <div class="card">
            <div class="card-header mon-card-head">
                <div>
                    <h3>Status mesin IoT</h3>
                    <span id="monMachineCount">{{ count($machines) }} mesin ditampilkan</span>
                </div>
                <a href="{{ route('admin.mesin.index') }}" class="mon-link">Semua mesin →</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="mon-table-wrap">
                    <table class="mon-table" id="monMachineTable">
                        <thead>
                            <tr>
                                <th>Mesin</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th>Terakhir dilihat</th>
                            </tr>
                        </thead>
                        <tbody id="monMachineBody">
                            @forelse($machines as $machine)
                                <tr data-id="{{ $machine['id_mesin'] }}">
                                    <td>
                                        <div class="mon-machine-cell">
                                            <span class="mon-dot mon-dot--{{ $machine['status'] }}"></span>
                                            <div>
                                                <strong>{{ $machine['nama_mesin'] }}</strong>
                                                <small>{{ $machine['id_mesin'] }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="mon-chip">{{ $machine['tipe_label'] }}</span></td>
                                    <td><span class="mon-badge mon-badge--{{ $machine['status'] }}">{{ $machine['status_label'] }}</span></td>
                                    <td>
                                        <div class="mon-time-cell">
                                            <strong>{{ $machine['last_seen_label'] }}</strong>
                                            <small>{{ $machine['last_seen_exact'] }}</small>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="mon-empty">Belum ada mesin IoT terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header mon-card-head">
                <div>
                    <h3>Aktivitas terbaru</h3>
                    <span>Maks. 10 event · data tetap masuk, detail di log</span>
                </div>
                <div class="mon-head-links">
                    <a href="{{ route('admin.log.absensi') }}" class="mon-link">Log absensi →</a>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="mon-table-wrap">
                    <table class="mon-table" id="monActivityTable">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Jenis</th>
                                <th>Mesin</th>
                                <th>Status</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody id="monActivityBody">
                            @forelse($recentActivities as $activity)
                                <tr>
                                    <td>
                                        <div class="mon-user-cell">
                                            @include('partials.user-avatar', [
                                                'name' => $activity['student'],
                                                'photoUrl' => $activity['profile_photo_url'] ?? null,
                                                'size' => 32,
                                                'tone' => ($activity['type_key'] ?? '') === 'loker' ? 'amber' : 'teal',
                                            ])
                                            <div>
                                                <strong>{{ $activity['student'] }}</strong>
                                                <small>{{ $activity['identifier'] }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="mon-chip mon-chip--{{ $activity['type_key'] ?? 'absensi' }}">{{ $activity['type'] }}</span></td>
                                    <td style="font-variant-numeric:tabular-nums;">{{ $activity['machine'] }}</td>
                                    <td><span class="mon-status-txt {{ ($activity['ok'] ?? true) ? 'is-ok' : 'is-bad' }}">{{ $activity['status'] }}</span></td>
                                    <td>
                                        <div class="mon-time-cell">
                                            <strong>{{ $activity['time_label'] ?? (optional($activity['time'])->diffForHumans() ?? '-') }}</strong>
                                            <small>{{ $activity['time_exact'] ?? (optional($activity['time'])->translatedFormat('d M Y H:i') ?? '-') }}</small>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="mon-empty">Belum ada aktivitas mesin yang masuk.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
    .mon-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        background: var(--bg-card);
        border: 1px solid var(--border-subtle);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }
    [data-theme="dark"] .mon-toolbar {
        background: rgba(15, 23, 42, 0.72);
        box-shadow: 0 10px 28px rgba(0,0,0,.28);
    }
    .mon-toolbar__left,
    .mon-toolbar__right {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }
    .mon-toolbar__meta { min-width: 0; }
    .mon-toolbar__meta strong {
        display: block;
        font-size: 13.5px;
        line-height: 1.2;
    }
    .mon-toolbar__meta small {
        display: block;
        margin-top: 2px;
        color: var(--text-muted);
        font-size: 11.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mon-live {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        flex-shrink: 0;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(5, 150, 105, 0.12);
        color: #059669;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .08em;
    }
    .mon-live.is-stale {
        background: rgba(148, 163, 184, 0.16);
        color: #64748b;
    }
    .mon-live__dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: currentColor;
        box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.45);
        animation: monPulse 1.8s ease-out infinite;
    }
    .mon-live.is-stale .mon-live__dot { animation: none; }
    @keyframes monPulse {
        0% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.45); }
        70% { box-shadow: 0 0 0 8px rgba(5, 150, 105, 0); }
        100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); }
    }
    .mon-refresh-hint {
        color: var(--text-muted);
        font-size: 11.5px;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .mon-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        padding: 0 12px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--soft-blue, #4a90d9) 14%, transparent);
        color: var(--soft-blue, #4a90d9);
        border: 1px solid color-mix(in srgb, var(--soft-blue, #4a90d9) 28%, transparent);
        text-decoration: none;
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
    }
    .mon-btn:hover { filter: brightness(1.05); }

    .mon-kpi {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .mon-kpi__item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 14px;
        background: var(--bg-card);
        border: 1px solid var(--border-subtle);
        border-left: 3px solid var(--kpi-accent, #0284c7);
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.035);
        min-height: 74px;
    }
    [data-theme="dark"] .mon-kpi__item {
        background: rgba(15, 23, 42, 0.72);
    }
    .mon-kpi__item--ok { --kpi-accent: #059669; }
    .mon-kpi__item--warn { --kpi-accent: #d97706; }
    .mon-kpi__item--blue { --kpi-accent: #0284c7; }
    .mon-kpi__item--violet { --kpi-accent: #7c3aed; }
    .mon-kpi__icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        background: color-mix(in srgb, var(--kpi-accent) 12%, transparent);
        color: var(--kpi-accent);
    }
    .mon-kpi__item span {
        display: block;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .mon-kpi__item strong {
        display: block;
        margin-top: 1px;
        font-size: 24px;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
        transition: color .25s ease;
    }
    .mon-kpi__item strong.is-flash { color: var(--kpi-accent); }
    .mon-kpi__item small {
        display: block;
        margin-top: 2px;
        color: var(--text-muted);
        font-size: 11px;
    }

    .mon-charts {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(260px, .75fr);
        gap: 12px;
        margin-bottom: 14px;
    }
    .mon-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px 10px !important;
    }
    .mon-card-head h3 { margin: 0; font-size: 14.5px; }
    .mon-card-head span { display: block; margin-top: 3px; color: var(--text-muted); font-size: 11.5px; }
    .mon-events-total {
        font-size: 12px;
        color: var(--text-muted);
        font-weight: 700;
        white-space: nowrap;
    }
    .mon-chart-body {
        height: 220px;
        padding: 4px 12px 14px !important;
    }
    .mon-health-body {
        display: grid;
        grid-template-columns: 140px minmax(0, 1fr);
        gap: 10px;
        align-items: center;
        padding: 8px 14px 16px !important;
        min-height: 220px;
    }
    .mon-health-canvas { position: relative; height: 140px; }
    .mon-health-center {
        position: absolute; inset: 0; display: grid; place-content: center;
        text-align: center; pointer-events: none; z-index: 2;
    }
    .mon-health-center strong {
        display: block; font-size: 22px; line-height: 1; letter-spacing: -.03em; color: var(--text-dark);
    }
    .mon-health-center small {
        display: block; margin-top: 3px; font-size: 10.5px; font-weight: 700; color: var(--text-muted);
    }
    .mon-health-legend { display: grid; gap: 8px; }
    .mon-health-item {
        display: grid;
        grid-template-columns: 8px minmax(0, 1fr) auto;
        gap: 8px;
        align-items: center;
        padding: 8px 10px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--border-subtle) 55%, transparent);
    }
    .mon-health-item strong { font-size: 13px; }
    .mon-health-item small { display: block; color: var(--text-muted); font-size: 10.5px; }
    .mon-health-item em {
        font-style: normal;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        font-size: 14px;
    }

    .mon-tables {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
        gap: 12px;
    }
    .mon-table-wrap { overflow: auto; max-height: 420px; }
    .mon-tables .card:last-child .mon-table-wrap { max-height: none; }
    .mon-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }
    .mon-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        text-align: left;
        padding: 10px 14px;
        background: color-mix(in srgb, var(--bg-card) 88%, #64748b);
        color: var(--text-muted);
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        border-bottom: 1px solid var(--border-subtle);
    }
    [data-theme="dark"] .mon-table thead th {
        background: rgba(15, 23, 42, 0.96);
    }
    .mon-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-subtle);
        vertical-align: middle;
    }
    .mon-table tbody tr {
        transition: background .2s ease;
    }
    .mon-table tbody tr.is-updated {
        background: color-mix(in srgb, #0284c7 8%, transparent);
    }
    .mon-machine-cell,
    .mon-user-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .mon-machine-cell strong,
    .mon-user-cell strong,
    .mon-time-cell strong {
        display: block;
        font-size: 12.5px;
        line-height: 1.25;
    }
    .mon-machine-cell small,
    .mon-user-cell small,
    .mon-time-cell small {
        display: block;
        margin-top: 2px;
        color: var(--text-muted);
        font-size: 11px;
    }
    .mon-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        flex-shrink: 0;
        background: #94a3b8;
        box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.14);
    }
    .mon-dot--online { background: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.16); }
    .mon-dot--offline { background: #d97706; box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.16); }
    .mon-dot--inactive { background: #94a3b8; }
    .mon-chip {
        display: inline-flex;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(100, 116, 139, 0.12);
        color: var(--text-muted);
        font-size: 11px;
        font-weight: 700;
    }
    .mon-chip--absensi { background: rgba(2, 132, 199, 0.12); color: #0284c7; }
    .mon-chip--loker { background: rgba(124, 58, 237, 0.12); color: #7c3aed; }
    .mon-badge {
        display: inline-flex;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }
    .mon-badge--online { background: rgba(5, 150, 105, 0.12); color: #059669; }
    .mon-badge--offline { background: rgba(217, 119, 6, 0.14); color: #b45309; }
    .mon-badge--inactive { background: rgba(100, 116, 139, 0.14); color: #64748b; }
    .mon-status-txt { font-weight: 700; font-size: 12px; }
    .mon-status-txt.is-ok { color: #059669; }
    .mon-status-txt.is-bad { color: #be123c; }
    .mon-empty {
        text-align: center;
        color: var(--text-muted);
        padding: 28px 14px !important;
    }
    .mon-link {
        color: var(--soft-blue, #4a90d9);
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .mon-head-links { display: flex; gap: 12px; }
    .mon-updating { opacity: .82; transition: opacity .2s ease; }

    @media (max-width: 1100px) {
        .mon-charts,
        .mon-tables { grid-template-columns: 1fr; }
        .mon-kpi { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 720px) {
        .mon-toolbar { flex-direction: column; align-items: stretch; }
        .mon-toolbar__right { justify-content: space-between; }
        .mon-kpi { grid-template-columns: 1fr; }
        .mon-health-body { grid-template-columns: 1fr; }
        .mon-toolbar__meta small { white-space: normal; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/simalas-charts.js') }}?v=6"></script>
<script>
(function () {
    const feedUrl = @json(route('admin.monitoring.feed'));
    const initial = {
        metrics: @json($metrics),
        machines: @json($machines),
        activities: @json($recentActivities),
        hourly: @json($hourly),
        health: @json($health),
    };

    const INTERVAL_MS = 15000;
    let hourlyChart = null;
    let healthChart = null;
    let countdown = INTERVAL_MS / 1000;
    let lastFingerprint = '';
    let currentPayload = initial;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    function setCount(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        const prev = el.textContent;
        if (window.SimalasCharts && typeof SimalasCharts.animateCount === 'function') {
            SimalasCharts.animateCount(el, value, { duration: 0.7 });
        } else {
            el.textContent = Number(value).toLocaleString('id-ID');
        }
        if (String(prev) !== String(value) && String(prev) !== Number(value).toLocaleString('id-ID')) {
            el.classList.add('is-flash');
            setTimeout(() => el.classList.remove('is-flash'), 500);
        }
    }

    function fingerprint(payload) {
        return JSON.stringify({
            m: payload.metrics,
            h: payload.hourly,
            health: payload.health,
            machines: (payload.machines || []).map(x => [x.id_mesin, x.status, x.last_seen_at]),
            acts: (payload.activities || []).map(x => [x.type, x.time, x.identifier, x.status]),
        });
    }

    function renderHealthLegend(health) {
        const box = document.getElementById('monHealthLegend');
        if (!box) return;
        const total = (health.values || []).reduce((a, b) => a + Number(b || 0), 0) || 1;
        box.innerHTML = (health.labels || []).map((label, i) => {
            const val = Number(health.values[i] || 0);
            const pct = ((val / total) * 100).toFixed(0);
            const color = health.colors[i] || '#94a3b8';
            return `<div class="mon-health-item">
                <span style="width:8px;height:8px;border-radius:999px;background:${color};"></span>
                <div><strong>${esc(label)}</strong><small>${pct}% armada</small></div>
                <em>${val.toLocaleString('id-ID')}</em>
            </div>`;
        }).join('');
    }

    function ensureCharts(payload) {
        const hourly = payload.hourly || { labels: [], absensi: [], loker: [] };
        const health = payload.health || { labels: [], values: [], colors: [] };

        if (!hourlyChart) {
            hourlyChart = SimalasCharts.buildStackedBarChart(document.getElementById('monHourlyChart'), {
                labels: hourly.labels,
                series: [
                    { key: 'absensi', label: 'Absensi', color: '#0284c7', data: hourly.absensi },
                ],
            });
        } else {
            hourlyChart.data.labels = hourly.labels;
            hourlyChart.data.datasets[0].data = hourly.absensi || [];
            hourlyChart.update('none');
        }

        if (healthChart) healthChart.destroy();
        healthChart = SimalasCharts.buildDonutChart(document.getElementById('monHealthChart'), {
            labels: health.labels,
            values: health.values,
            colors: health.colors,
            centerMain: (payload.metrics && payload.metrics.machine_total) || 0,
            centerSub: 'Mesin',
            hideLegend: true,
            htmlCenter: true,
        });
        const centerEl = document.getElementById('monHealthCenterMain');
        if (centerEl) {
            centerEl.textContent = Number((payload.metrics && payload.metrics.machine_total) || 0).toLocaleString('id-ID');
        }
        renderHealthLegend(health);
    }

    function renderMachines(rows) {
        const body = document.getElementById('monMachineBody');
        if (!body) return;
        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="4" class="mon-empty">Belum ada mesin IoT terdaftar.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(m => `
            <tr data-id="${esc(m.id_mesin)}">
                <td>
                    <div class="mon-machine-cell">
                        <span class="mon-dot mon-dot--${esc(m.status)}"></span>
                        <div>
                            <strong>${esc(m.nama_mesin)}</strong>
                            <small>${esc(m.id_mesin)}</small>
                        </div>
                    </div>
                </td>
                <td><span class="mon-chip">${esc(m.tipe_label)}</span></td>
                <td><span class="mon-badge mon-badge--${esc(m.status)}">${esc(m.status_label)}</span></td>
                <td>
                    <div class="mon-time-cell">
                        <strong>${esc(m.last_seen_label)}</strong>
                        <small>${esc(m.last_seen_exact)}</small>
                    </div>
                </td>
            </tr>
        `).join('');
        const count = document.getElementById('monMachineCount');
        if (count) count.textContent = rows.length + ' mesin ditampilkan';
    }

    function avatarFallback(name, tone) {
        const initial = (String(name || '?').trim().charAt(0) || '?').toUpperCase();
        const bg = tone === 'amber' ? 'rgba(217,119,6,.14)' : 'rgba(5,150,105,.14)';
        const color = tone === 'amber' ? '#b45309' : '#059669';
        return `<div style="width:32px;height:32px;border-radius:999px;display:grid;place-items:center;background:${bg};color:${color};font-weight:800;font-size:12px;flex-shrink:0;">${esc(initial)}</div>`;
    }

    function renderActivities(rows) {
        const body = document.getElementById('monActivityBody');
        if (!body) return;
        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="5" class="mon-empty">Belum ada aktivitas mesin yang masuk.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(a => {
            const tone = a.type_key === 'loker' ? 'amber' : 'teal';
            const avatar = a.profile_photo_url
                ? `<img src="${esc(a.profile_photo_url)}" alt="" width="32" height="32" style="width:32px;height:32px;border-radius:999px;object-fit:cover;flex-shrink:0;">`
                : avatarFallback(a.student, tone);
            return `<tr>
                <td>
                    <div class="mon-user-cell">
                        ${avatar}
                        <div>
                            <strong>${esc(a.student)}</strong>
                            <small>${esc(a.identifier)}</small>
                        </div>
                    </div>
                </td>
                <td><span class="mon-chip mon-chip--${esc(a.type_key || 'absensi')}">${esc(a.type)}</span></td>
                <td style="font-variant-numeric:tabular-nums;">${esc(a.machine)}</td>
                <td><span class="mon-status-txt ${a.ok === false ? 'is-bad' : 'is-ok'}">${esc(a.status)}</span></td>
                <td>
                    <div class="mon-time-cell">
                        <strong>${esc(a.time_label)}</strong>
                        <small>${esc(a.time_exact)}</small>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    function applyPayload(payload, { flashTables = false } = {}) {
        currentPayload = payload;
        const metrics = payload.metrics || {};
        setCount('kpiOnline', metrics.machine_online || 0);
        setCount('kpiOffline', metrics.machine_offline || 0);
        setCount('kpiAbsensi', metrics.absensi_today || 0);
        const totalEl = document.getElementById('kpiTotal');
        if (totalEl) totalEl.textContent = Number(metrics.machine_total || 0).toLocaleString('id-ID');
        const eventsEl = document.getElementById('kpiEvents');
        if (eventsEl) eventsEl.textContent = Number(metrics.events_today || 0).toLocaleString('id-ID') + ' event';
        const syncEl = document.getElementById('monLatestSync');
        if (syncEl) syncEl.textContent = metrics.latest_sync || '—';

        ensureCharts(payload);
        renderMachines(payload.machines || []);
        renderActivities(payload.activities || []);

        if (flashTables) {
            ['monMachineBody', 'monActivityBody'].forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.add('mon-updating');
                requestAnimationFrame(() => {
                    setTimeout(() => el.classList.remove('mon-updating'), 180);
                });
            });
        }
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    async function refreshFeed() {
        if (document.visibilityState !== 'visible') return;
        const badge = document.getElementById('monLiveBadge');
        try {
            const res = await fetch(feedUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('bad status');
            const data = await res.json();
            if (!data.ok) return;
            const fp = fingerprint(data);
            if (fp !== lastFingerprint) {
                applyPayload(data, { flashTables: Boolean(lastFingerprint) });
                lastFingerprint = fp;
            }
            if (badge) badge.classList.remove('is-stale');
        } catch (e) {
            if (badge) badge.classList.add('is-stale');
        }
    }

    function tickCountdown() {
        const hint = document.getElementById('monRefreshHint');
        countdown -= 1;
        if (countdown <= 0) {
            countdown = INTERVAL_MS / 1000;
            refreshFeed();
        }
        if (hint) hint.textContent = 'Update dalam ' + countdown + ' dtk';
    }

    applyPayload(initial);
    lastFingerprint = fingerprint(initial);

    setInterval(tickCountdown, 1000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            countdown = INTERVAL_MS / 1000;
            refreshFeed();
        }
    });
    document.addEventListener('simalas:themechange', function () {
        if (hourlyChart) { hourlyChart.destroy(); hourlyChart = null; }
        if (healthChart) { healthChart.destroy(); healthChart = null; }
        ensureCharts(currentPayload || initial);
    });
})();
</script>
@endsection
