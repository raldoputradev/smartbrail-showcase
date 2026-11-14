@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')
@section('page-subtitle', 'Ringkasan operasional lab & absensi realtime')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @php
        $a = $analytics;
        $s = $a['stats'];
        $ops = $opsShortcuts ?? [];
        $pblReady = $ops['pbl_readiness'] ?? ['ok' => true, 'stats' => []];
        $roleLabels = $a['role_donut']['labels'] ?? ['Mahasiswa', 'Dosen', 'Laboran'];
        $roleValues = $a['role_donut']['values'] ?? [0, 0, 0];
        $roleColors = ['#22c55e', '#38bdf8', '#f59e0b'];
        $roleTotal = max(1, array_sum(array_map('intval', $roleValues)));
    @endphp

    <style>
        .adm-toolbar {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            margin-bottom: 14px; padding: 12px 14px; border-radius: 14px;
            background: var(--bg-card); border: 1px solid var(--border-subtle);
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
        }
        [data-theme="dark"] .adm-toolbar { background: rgba(15,23,42,.72); box-shadow: 0 10px 28px rgba(0,0,0,.28); }
        .adm-toolbar__left { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .adm-toolbar__meta strong { display: block; font-size: 13.5px; }
        .adm-toolbar__meta small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11.5px; }
        .adm-live {
            display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
            padding: 6px 10px; border-radius: 999px; background: rgba(5,150,105,.12); color: #059669;
            font-size: 10.5px; font-weight: 800; letter-spacing: .08em;
        }
        .adm-live__dot {
            width: 8px; height: 8px; border-radius: 999px; background: currentColor;
            box-shadow: 0 0 0 0 rgba(5,150,105,.45); animation: admPulse 1.8s ease-out infinite;
        }
        @keyframes admPulse {
            0% { box-shadow: 0 0 0 0 rgba(5,150,105,.45); }
            70% { box-shadow: 0 0 0 8px rgba(5,150,105,0); }
            100% { box-shadow: 0 0 0 0 rgba(5,150,105,0); }
        }
        .adm-toolbar__right { display: flex; align-items: center; gap: 10px; }
        .adm-toolbar-btn {
            display: inline-flex; align-items: center; gap: 7px; min-height: 34px; padding: 0 12px;
            border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 700;
            background: color-mix(in srgb, var(--soft-blue,#4a90d9) 14%, transparent);
            color: var(--soft-blue,#4a90d9);
            border: 1px solid color-mix(in srgb, var(--soft-blue,#4a90d9) 28%, transparent);
        }

        .adm-ops-grid {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px; margin-bottom: 12px;
        }
        @media (max-width: 1100px) { .adm-ops-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .adm-ops-grid { grid-template-columns: 1fr; } }
        .adm-ops-card {
            display: flex; flex-direction: column; gap: 10px; padding: 14px;
            border-radius: 14px; background: var(--bg-card); border: 1px solid var(--border-subtle);
            text-decoration: none; color: inherit; position: relative; overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04); min-height: 118px;
        }
        .adm-ops-card::before {
            content: ''; position: absolute; inset: 0 auto 0 0; width: 3px;
            background: var(--ops-accent, #0284c7); opacity: .85;
        }
        .adm-ops-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
            border-color: color-mix(in srgb, var(--ops-accent, #0284c7) 30%, var(--border-subtle));
        }
        [data-theme="dark"] .adm-ops-card { background: rgba(15, 23, 42, 0.72); box-shadow: 0 10px 28px rgba(0,0,0,.28); }
        .adm-ops-card__top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .adm-ops-ico {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .adm-ops-tag {
            display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 999px;
            font-size: 10px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
        }
        .adm-ops-tag--ok { background: rgba(15,118,110,.12); color: #0f766e; }
        .adm-ops-tag--warn { background: rgba(245,158,11,.15); color: #b45309; }
        .adm-ops-tag--danger { background: rgba(220,38,38,.12); color: #b91c1c; }
        .adm-ops-tag--info { background: rgba(59,130,246,.12); color: #2563eb; }
        .adm-ops-tx strong { display: block; font-size: 13.5px; margin-bottom: 2px; }
        .adm-ops-tx small { color: var(--text-muted); font-size: 11.5px; line-height: 1.35; }
        .adm-ops-count {
            margin-top: auto; font-size: 22px; font-weight: 800; letter-spacing: -.03em;
            font-variant-numeric: tabular-nums; color: var(--text-dark);
            max-width: 100%;
            overflow: hidden;
        }

        .adm-kpi {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px; margin-bottom: 12px;
        }
        @media (max-width: 1100px) { .adm-kpi { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .adm-kpi { grid-template-columns: 1fr; } }
        .adm-kpi__item {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; min-height: 76px;
            border-radius: 14px; background: var(--bg-card); border: 1px solid var(--border-subtle);
            border-left: 3px solid var(--kpi-accent, #0284c7);
            box-shadow: 0 6px 18px rgba(15,23,42,.035); text-decoration: none; color: inherit;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .adm-kpi__item:hover {
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--kpi-accent) 35%, var(--border-subtle));
            box-shadow: 0 12px 26px rgba(15,23,42,.08);
        }
        [data-theme="dark"] .adm-kpi__item { background: rgba(15,23,42,.72); }
        .adm-kpi__item--blue { --kpi-accent: #0284c7; }
        .adm-kpi__item--violet { --kpi-accent: #7c3aed; }
        .adm-kpi__item--teal { --kpi-accent: #0f766e; }
        .adm-kpi__item--amber { --kpi-accent: #b45309; }
        .adm-kpi__icon {
            width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0;
            background: color-mix(in srgb, var(--kpi-accent) 12%, transparent); color: var(--kpi-accent);
        }
        .adm-kpi__item > div > span {
            display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .06em;
            text-transform: uppercase; color: var(--text-muted);
        }
        .adm-kpi__item strong {
            display: block; margin-top: 1px; font-size: 24px; line-height: 1.1;
            font-variant-numeric: tabular-nums;
            max-width: 100%;
            overflow: hidden;
        }
        .adm-kpi__item strong.simalas-odo {
            display: inline-flex !important;
            align-items: baseline;
            max-width: 100%;
            overflow: hidden;
        }
        .adm-kpi__item small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11px; }
        .adm-kpi__item small.up { color: #059669; }
        .adm-kpi__item small.down { color: #be123c; }

        .adm-period-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; margin-bottom: 12px;
        }
        .adm-live-dot {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 600; color: var(--text-muted);
        }
        .adm-live-dot::before {
            content: ''; width: 8px; height: 8px; border-radius: 50%;
            background: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,.15);
        }

        .adm-donut-layout {
            display: grid; grid-template-columns: minmax(160px, 1fr) minmax(0, 1.1fr);
            gap: 12px; align-items: center; height: 100%; min-height: 230px;
        }
        @media (max-width: 720px) { .adm-donut-layout { grid-template-columns: 1fr; } }
        .adm-donut-canvas-wrap {
            position: relative;
            height: 200px;
        }
        .adm-donut-center {
            position: absolute;
            inset: 0;
            display: grid;
            place-content: center;
            text-align: center;
            pointer-events: none;
            z-index: 2;
        }
        .adm-donut-center strong {
            display: block;
            font-size: 26px;
            line-height: 1;
            letter-spacing: -.03em;
            color: var(--text-dark);
        }
        .adm-donut-center small {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
        }
        .adm-donut-legend { display: flex; flex-direction: column; gap: 8px; }
        .adm-donut-item {
            display: grid; grid-template-columns: 8px minmax(0,1fr) auto; gap: 10px; align-items: center;
            padding: 8px 10px; border-radius: 10px;
            background: color-mix(in srgb, var(--border-subtle) 55%, transparent);
        }
        .adm-donut-swatch { width: 8px; height: 8px; border-radius: 999px; }
        .adm-donut-item__meta strong { display: block; font-size: 12.5px; }
        .adm-donut-item__meta small { color: var(--text-muted); font-size: 10.5px; }
        .adm-donut-item__val { text-align: right; font-variant-numeric: tabular-nums; }
        .adm-donut-item__val strong { display: block; font-size: 14px; }
        .adm-donut-item__val small { color: var(--text-muted); font-size: 11px; font-weight: 700; }

        .adm-feed-grid {
            display: grid; grid-template-columns: 1.15fr 1fr; gap: 12px;
        }
        @media (max-width: 960px) { .adm-feed-grid { grid-template-columns: 1fr; } }
        .adm-feed-card .card-header {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
            padding: 14px 16px 10px;
        }
        .adm-feed-card .card-header h3 { margin: 0; font-size: 14.5px; }
        .adm-feed-card .card-header small { display: block; margin-top: 3px; color: var(--text-muted); font-size: 11.5px; }
        .adm-feed-link { font-size: 12px; color: var(--soft-blue); text-decoration: none; font-weight: 700; white-space: nowrap; }
        .adm-feed-table-wrap { overflow: auto; max-height: 380px; }
        .adm-feed-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .adm-feed-table thead th {
            position: sticky; top: 0; z-index: 1; text-align: left; padding: 10px 14px;
            background: color-mix(in srgb, var(--bg-card) 88%, #64748b);
            color: var(--text-muted); font-size: 10.5px; font-weight: 800;
            letter-spacing: .05em; text-transform: uppercase; border-bottom: 1px solid var(--border-subtle);
        }
        [data-theme="dark"] .adm-feed-table thead th { background: rgba(15,23,42,.96); }
        .adm-feed-table tbody td { padding: 10px 14px; border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
        .adm-feed-user { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .adm-feed-user__text strong { display: block; font-size: 12.5px; }
        .adm-feed-user__text small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11px; }
        .adm-status-pill {
            display: inline-flex; padding: 3px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 700; text-transform: capitalize;
        }
        .adm-status-pill--role { background: rgba(2,132,199,.12); color: #0284c7; }
        .adm-status-pill--ok { background: rgba(5,150,105,.12); color: #059669; }
        .adm-status-pill--pending { background: rgba(217,119,6,.14); color: #b45309; }
        .adm-feed-empty { text-align: center; color: var(--text-muted); padding: 28px 14px !important; }
        @media (max-width: 720px) {
            .adm-toolbar { flex-direction: column; align-items: stretch; }
            .adm-toolbar__right { justify-content: flex-start; flex-wrap: wrap; }
        }
    </style>

    <div class="adm-toolbar animate-in">
        <div class="adm-toolbar__left">
            @if(!empty($a['demo']))
                <span class="adm-live" style="background:rgba(217,119,6,.16);color:#b45309"><span class="adm-live__dot"></span>CONTOH</span>
            @else
                <span class="adm-live"><span class="adm-live__dot"></span>LIVE</span>
            @endif
            <div class="adm-toolbar__meta">
                <strong>Command center lab</strong>
                <small>KPI, tren IoT, dan antrian operasional dalam satu layar</small>
            </div>
        </div>
        <div class="adm-toolbar__right">
            <a href="{{ route('admin.monitoring') }}" class="adm-toolbar-btn">@include('components.icon', ['name' => 'radio']) Monitoring</a>
            <a href="{{ route('admin.mesin.index') }}" class="adm-toolbar-btn">@include('components.icon', ['name' => 'cpu']) Mesin IoT</a>
        </div>
    </div>

    <div class="adm-ops-grid animate-in">
        <a href="{{ route('admin.pbl.index') }}" class="adm-ops-card" style="--ops-accent:#0f766e;">
            <div class="adm-ops-card__top">
                <div class="adm-ops-ico" style="background:rgba(15,118,110,0.1);color:#0f766e;">@include('components.icon', ['name' => 'folder-kanban'])</div>
                <span class="adm-ops-tag {{ ($pblReady['ok'] ?? false) ? 'adm-ops-tag--ok' : 'adm-ops-tag--danger' }}">
                    {{ ($pblReady['ok'] ?? false) ? 'Siap' : 'Perlu aksi' }}
                </span>
            </div>
            <div class="adm-ops-tx">
                <strong>Proyek PBL</strong>
                <small>Checklist readiness absensi Kamis</small>
            </div>
        </a>
        <a href="{{ route('admin.notifications') }}" class="adm-ops-card" style="--ops-accent:#b45309;">
            <div class="adm-ops-card__top">
                <div class="adm-ops-ico" style="background:rgba(180,83,9,0.1);color:#b45309;">@include('components.icon', ['name' => 'list-todo'])</div>
                <span class="adm-ops-tag adm-ops-tag--warn">Antrian</span>
            </div>
            <div class="adm-ops-tx">
                <strong>Approval akun</strong>
                <small>Menunggu persetujuan admin</small>
            </div>
            <div class="adm-ops-count" id="ops-approval" data-target="{{ (int) ($ops['pending_approval'] ?? 0) }}">0</div>
        </a>
        <a href="{{ route('admin.suggestions.index', ['status' => 'baru']) }}" class="adm-ops-card" style="--ops-accent:#be123c;">
            <div class="adm-ops-card__top">
                <div class="adm-ops-ico" style="background:rgba(190,18,60,0.1);color:#be123c;">@include('components.icon', ['name' => 'message-square'])</div>
                <span class="adm-ops-tag adm-ops-tag--danger">Saran</span>
            </div>
            <div class="adm-ops-tx">
                <strong>Saran baru</strong>
                <small>Masukan belum ditinjau</small>
            </div>
            <div class="adm-ops-count" id="ops-saran" data-target="{{ (int) ($ops['new_suggestions'] ?? 0) }}">0</div>
        </a>
        <a href="{{ route('admin.fingerprint', ['status' => 'pending_enroll']) }}" class="adm-ops-card" style="--ops-accent:#7c3aed;">
            <div class="adm-ops-card__top">
                <div class="adm-ops-ico" style="background:rgba(124,58,237,0.1);color:#7c3aed;">@include('components.icon', ['name' => 'fingerprint'])</div>
                <span class="adm-ops-tag adm-ops-tag--info">Enroll</span>
            </div>
            <div class="adm-ops-tx">
                <strong>Sidik jari</strong>
                <small>Mahasiswa aktif belum enroll</small>
            </div>
            <div class="adm-ops-count" id="ops-enroll" data-target="{{ (int) ($ops['enroll_belum'] ?? 0) }}">0</div>
        </a>
    </div>

    <div class="adm-kpi animate-in">
        <a href="{{ route('admin.users') }}" class="adm-kpi__item adm-kpi__item--blue">
            <div class="adm-kpi__icon">@include('components.icon', ['name' => 'users'])</div>
            <div>
                <span>Pengguna</span>
                <strong id="g-user" data-target="{{ $s['total_user'] }}">0</strong>
                <small id="g-user-d">{{ $s['user_aktif'] }} aktif · {{ $s['user_pending'] }} pending</small>
            </div>
        </a>
        <a href="{{ route('admin.mesin.index') }}" class="adm-kpi__item adm-kpi__item--violet">
            <div class="adm-kpi__icon">@include('components.icon', ['name' => 'cpu'])</div>
            <div>
                <span>Mesin IoT</span>
                <strong id="g-mesin" data-target="{{ $s['mesin_aktif'] }}/{{ $s['mesin_total'] }}">0/0</strong>
                <small id="g-mesin-d">{{ $s['mesin_meta'] ?? (($s['mesin_online'] ?? 0).' online · '.($s['mesin_offline'] ?? 0).' offline') }}</small>
            </div>
        </a>
        <a href="{{ route('admin.attendance') }}" class="adm-kpi__item adm-kpi__item--teal">
            <div class="adm-kpi__icon">@include('components.icon', ['name' => 'clipboard-check'])</div>
            <div>
                <span>Tap absensi</span>
                <strong id="g-tap" data-target="{{ $s['tap_absensi'] }}">0</strong>
                <small id="g-tap-d" class="{{ \App\Support\DashboardStatLabels::deltaToneClass($s['tap_delta'] ?? 0) }}">{{ $s['tap_meta'] ?? '' }}</small>
            </div>
        </a>
    </div>

    <div class="adm-period-row">
        <div class="period-pill" id="adm-period-label">
            @include('components.icon', ['name' => 'calendar-range'])
            <span class="period-pill__text">{{ $a['period_label'] ?? '' }}</span>
        </div>
        <div class="adm-live-dot">{{ !empty($a['demo']) ? 'Angka contoh pada grafik beranda' : 'Data absensi periode ini' }}</div>
    </div>

    <div class="dash-chart-grid">
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <div>
                    <h3>Tren aktivitas IoT</h3>
                </div>
                <span class="chart-panel__sub" id="adm-line-subtitle">{{ $a['line']['subtitle'] ?? '' }}</span>
            </div>
            <div class="chart-panel__body">
                <canvas id="admChartActivity"></canvas>
            </div>
        </div>
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <div>
                    <h3>Distribusi role</h3>
                </div>
                <span class="chart-panel__sub">Komposisi akun sistem</span>
            </div>
            <div class="chart-panel__body">
                <div class="adm-donut-layout">
                    <div class="adm-donut-canvas-wrap">
                        <canvas id="admChartDonut"></canvas>
                        <div class="adm-donut-center">
                            <strong id="admDonutCenterMain">{{ number_format(array_sum(array_map('intval', $roleValues))) }}</strong>
                            <small>Pengguna</small>
                        </div>
                    </div>
                    <div class="adm-donut-legend" id="admDonutLegend">
                        @foreach($roleLabels as $i => $label)
                            @php
                                $val = (int) ($roleValues[$i] ?? 0);
                                $pct = round(($val / $roleTotal) * 100, 1);
                                $color = $roleColors[$i] ?? '#94a3b8';
                            @endphp
                            <div class="adm-donut-item">
                                <span class="adm-donut-swatch" style="background:{{ $color }};"></span>
                                <div class="adm-donut-item__meta">
                                    <strong>{{ $label }}</strong>
                                    <small>Proporsi akun</small>
                                </div>
                                <div class="adm-donut-item__val">
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
                <div>
                    <h3 id="admBarMesinTitle">{{ $a['mesin_bar']['title'] ?? 'Status mesin IoT' }}</h3>
                </div>
                <span class="chart-panel__sub" id="admBarMesinSub">{{ $a['mesin_bar']['subtitle'] ?? 'Online / offline / nonaktif' }}</span>
            </div>
            <div class="chart-panel__body">
                <canvas id="admChartMesinBar"></canvas>
            </div>
        </div>
        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <div>
                    <h3 id="admBarKelasTitle">{{ $a['kelas_bar']['title'] ?? 'Tap absensi per kelas' }}</h3>
                </div>
                <span class="chart-panel__sub" id="admBarKelasSub">{{ $a['kelas_bar']['subtitle'] ?? 'Distribusi aktivitas kelas' }}</span>
            </div>
            <div class="chart-panel__body">
                <canvas id="admChartKelasBar"></canvas>
            </div>
        </div>
    </div>

    <div class="adm-feed-grid">
        <div class="card animate-in adm-feed-card">
            <div class="card-header">
                <div>
                    <h3>Live feed absensi</h3>
                    <small>Maks. 10 tap terbaru</small>
                </div>
                <a href="{{ route('admin.log.absensi') }}" class="adm-feed-link">Semua log →</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="adm-feed-table-wrap">
                    <table class="adm-feed-table">
                        <thead>
                            <tr>
                                <th>Mahasiswa</th>
                                <th>NIM / NITK</th>
                                <th>Mesin</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($latestTaps as $tap)
                                <tr>
                                    <td>
                                        <div class="adm-feed-user">
                                            @include('partials.user-avatar', ['user' => $tap->user, 'size' => 32, 'tone' => 'teal'])
                                            <div class="adm-feed-user__text">
                                                <strong>{{ $tap->user->nama_lengkap ?? '-' }}</strong>
                                                <small>{{ $tap->user->kelas ?: '—' }} · Tim {{ $tap->user->tim ?: '—' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-variant-numeric:tabular-nums;">{{ $tap->user->nitk_nidn ?? '-' }}</td>
                                    <td><code style="font-size:11px;">{{ $tap->id_mesin }}</code></td>
                                    <td style="white-space:nowrap;color:var(--text-muted);font-variant-numeric:tabular-nums;">
                                        {{ optional($tap->waktu_tap)->translatedFormat('d M Y · H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="adm-feed-empty">Belum ada tap absensi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card animate-in adm-feed-card">
            <div class="card-header">
                <div>
                    <h3>Pengguna baru</h3>
                    <small>Maks. 10 akun terbaru</small>
                </div>
                <a href="{{ route('admin.users') }}" class="adm-feed-link">Kelola →</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="adm-feed-table-wrap">
                    <table class="adm-feed-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($latestUsers as $u)
                                <tr onclick="window.location='{{ route('profiles.show', $u) }}'" style="cursor:pointer;">
                                    <td>
                                        <div class="adm-feed-user">
                                            @include('partials.user-avatar', ['user' => $u, 'size' => 32, 'tone' => 'blue'])
                                            <div class="adm-feed-user__text">
                                                <strong>{{ $u->nama_lengkap }}</strong>
                                                <small>{{ $u->email ?: ($u->nitk_nidn ?: '—') }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="adm-status-pill adm-status-pill--role">{{ $u->role }}</span></td>
                                    <td>
                                        @if($u->is_active)
                                            <span class="adm-status-pill adm-status-pill--ok">Aktif</span>
                                        @else
                                            <span class="adm-status-pill adm-status-pill--pending">Pending</span>
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap;color:var(--text-muted);font-variant-numeric:tabular-nums;">
                                        {{ optional($u->created_at)->translatedFormat('d M Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="adm-feed-empty">Belum ada pengguna.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/simalas-charts.js') }}?v=11"></script>
    <script>
(function() {
    const initial = @json($analytics);
    const roleColors = @json($roleColors);
    function setCount(id, value, opts) {
        const el = document.getElementById(id);
        if (!el) return;
        if (window.SimalasCharts && typeof SimalasCharts.animateCount === 'function') {
            SimalasCharts.animateCount(el, value, Object.assign({ duration: 1.55 }, opts || {}));
        } else {
            el.textContent = value;
        }
    }

    function deltaClass(v) {
        v = Number(v || 0);
        if (v === 0) return '';
        return v > 0 ? 'up' : 'down';
    }

    function renderDonutLegend(labels, values, colors) {
        const box = document.getElementById('admDonutLegend');
        if (!box) return;
        const total = (values || []).reduce((a, b) => a + Number(b || 0), 0) || 1;
        box.innerHTML = (labels || []).map((label, i) => {
            const val = Number(values[i] || 0);
            const pct = ((val / total) * 100).toFixed(1);
            const color = colors[i] || '#94a3b8';
            return `<div class="adm-donut-item">
                <span class="adm-donut-swatch" style="background:${color};"></span>
                <div class="adm-donut-item__meta"><strong>${label}</strong><small>Proporsi akun</small></div>
                <div class="adm-donut-item__val"><strong>${val.toLocaleString('id-ID')}</strong><small>${pct}%</small></div>
            </div>`;
        }).join('');
    }

    function renderCharts(d) {
        try {
            const series = (d.line.series && d.line.series.length)
                ? d.line.series
                : [
                    { key: 'absensi', label: 'Absensi', color: '#0284c7', data: d.line.absensi || [] },
                ];

            SimalasCharts.buildMultiLineChart(document.getElementById('admChartActivity'), {
                labels: d.line.labels,
                series: series,
                fill: true,
                yTitle: 'Jumlah event',
            });
        } catch (e) { console.warn('adm line chart', e); }

        try {
            const roleTotal = (d.role_donut.values || []).reduce((a, b) => a + Number(b || 0), 0);
            SimalasCharts.buildDonutChart(document.getElementById('admChartDonut'), {
                labels: d.role_donut.labels,
                values: d.role_donut.values,
                colors: roleColors,
                centerMain: roleTotal,
                centerSub: 'Pengguna',
                hideLegend: true,
                htmlCenter: true,
            });
            const centerEl = document.getElementById('admDonutCenterMain');
            if (centerEl) centerEl.textContent = Number(roleTotal).toLocaleString('id-ID');
            renderDonutLegend(d.role_donut.labels, d.role_donut.values, roleColors);
        } catch (e) { console.warn('adm donut chart', e); }

        try {
            if (d.mesin_bar && document.getElementById('admChartMesinBar')) {
                const title = document.getElementById('admBarMesinTitle');
                const sub = document.getElementById('admBarMesinSub');
                if (title && d.mesin_bar.title) title.textContent = d.mesin_bar.title;
                if (sub) sub.textContent = d.mesin_bar.subtitle || '';
                SimalasCharts.buildBarChart(document.getElementById('admChartMesinBar'), {
                    labels: d.mesin_bar.labels || [],
                    values: d.mesin_bar.values || [],
                    colors: d.mesin_bar.colors || ['#22c55e', '#94a3b8', '#f43f5e'],
                });
            }
        } catch (e) { console.warn('adm mesin bar', e); }

        try {
            if (d.kelas_bar && document.getElementById('admChartKelasBar')) {
                const title = document.getElementById('admBarKelasTitle');
                const sub = document.getElementById('admBarKelasSub');
                if (title && d.kelas_bar.title) title.textContent = d.kelas_bar.title;
                if (sub) sub.textContent = d.kelas_bar.subtitle || '';
                SimalasCharts.buildBarChart(document.getElementById('admChartKelasBar'), {
                    labels: d.kelas_bar.labels || [],
                    values: d.kelas_bar.values || [],
                    colors: d.kelas_bar.colors || ['#0284c7', '#38bdf8', '#7c3aed', '#14b8a6'],
                });
            }
        } catch (e) { console.warn('adm kelas bar', e); }
    }

    function apply(d) {
        const admPeriod = document.querySelector('#adm-period-label .period-pill__text');
        if (admPeriod) admPeriod.textContent = d.period_label || '';
        setCount('g-user', d.stats.total_user);
        document.getElementById('g-user-d').textContent = d.stats.user_aktif + ' aktif · ' + d.stats.user_pending + ' pending';
        setCount('g-mesin', d.stats.mesin_aktif + '/' + d.stats.mesin_total);
        document.getElementById('g-mesin-d').textContent = d.stats.mesin_meta
            || ((d.stats.mesin_online || 0) + ' online · ' + (d.stats.mesin_offline || 0) + ' offline');
        setCount('g-tap', d.stats.tap_absensi, { duration: 1.75 });
        const td = document.getElementById('g-tap-d');
        td.textContent = d.stats.tap_meta || '';
        td.className = deltaClass(d.stats.tap_delta);
        const ops = d.ops || {};
        setCount('ops-approval', ops.pending_approval != null ? ops.pending_approval : (document.getElementById('ops-approval')?.dataset.target || 0), { duration: 1.25 });
        setCount('ops-saran', ops.new_suggestions != null ? ops.new_suggestions : (document.getElementById('ops-saran')?.dataset.target || 0), { duration: 1.3 });
        setCount('ops-enroll', ops.enroll_belum != null ? ops.enroll_belum : (document.getElementById('ops-enroll')?.dataset.target || 0), { duration: 1.4 });

        document.getElementById('adm-line-subtitle').textContent = d.line.subtitle || '';

        renderCharts(d);
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    apply(initial);
    document.addEventListener('simalas:themechange', function () {
        renderCharts(initial);
    });
})();
    </script>
@endsection
