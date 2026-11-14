@extends('layouts.dashboard')

@php
    $routes = $reportPanel['routes'];
    $isDosen = $reportPanel['is_dosen'] ?? false;
    $rankings = $rankings ?? ['category' => 'terajin', 'title' => 'Rekor', 'subtitle' => '', 'metric' => '', 'items' => [], 'group' => 'semua'];
    $rekor = $filter['rekor'] ?? 'terajin';
    $rekorGroup = $filter['rekor_group'] ?? 'semua';
    $periodeMode = $filter['periode'] ?? 'bulan_ini';
    $aktivitas = $filter['aktivitas'] ?? 'absensi';
    $queryKeep = request()->except(['rekor', 'rekor_group', 'page', 'recap_page', 'detail_page']);
    $showLokerOnly = false;
    $showLokerKpi = false;
    $aktifCount = is_countable($studentRecapsActive ?? null) ? count($studentRecapsActive) : 0;
@endphp

@section('title', 'Laporan Periodik')
@section('page-title', 'Laporan Periodik')
@section('page-subtitle', 'Filter periode · grafik absensi · rekor · export sesuai filter')

@section('sidebar-nav')
    @include($reportPanel['sidebar'])
@endsection

@section('content')
    @include('absensi._styles')

    <div class="lp-toolbar animate-in">
        <div class="lp-toolbar__left">
            <span class="lp-live"><span class="lp-live__dot"></span>PERIODIK</span>
            <div>
                <strong>{{ $periodeLabel ?? 'Periode' }}</strong>
                <small>{{ $activityLabel }} · {{ \Carbon\Carbon::parse($filter['tanggal_mulai'])->translatedFormat('d M Y') }} — {{ \Carbon\Carbon::parse($filter['tanggal_akhir'])->translatedFormat('d M Y') }}</small>
            </div>
        </div>
        <div class="lp-toolbar__right">
            <button type="button" class="lp-btn lp-btn--ghost" onclick="openLpHelp()" title="Panduan">
                <span class="lp-help-q">?</span>
            </button>
            <a href="{{ route($routes['rekap']) }}" class="lp-btn lp-btn--ghost">Rekap Harian</a>
        </div>
    </div>

    <form method="GET" action="{{ route($routes['index']) }}" class="lp-filter animate-in" id="lpFilterForm" data-no-loader="1">
        <input type="hidden" name="rekor" value="{{ $rekor }}">
        <input type="hidden" name="rekor_group" value="{{ $rekorGroup }}">

        <div class="lp-period">
            <span class="lp-period__label">Periode kalender</span>
            <div class="lp-period__chips">
                @foreach([
                    'minggu_ini' => 'Minggu ini',
                    'minggu_lalu' => 'Minggu lalu',
                    'bulan_ini' => 'Bulan ini',
                    'bulan_lalu' => 'Bulan lalu',
                    'tahun_bulan' => 'Tahun-bulan',
                    'custom' => 'Rentang custom',
                ] as $key => $label)
                    <label class="lp-chip {{ $periodeMode === $key ? 'is-active' : '' }}">
                        <input type="radio" name="periode" value="{{ $key }}" @checked($periodeMode === $key) onchange="lpTogglePeriode()">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="lp-filter__grid">
            <label id="lpBulanWrap" class="{{ $periodeMode === 'tahun_bulan' ? '' : 'is-hidden' }}">
                <span>Tahun-bulan</span>
                <input type="month" name="bulan" value="{{ $filter['bulan'] ?? now()->format('Y-m') }}">
            </label>
            <label id="lpDariWrap" class="{{ $periodeMode === 'custom' ? '' : 'is-hidden' }}">
                <span>Dari</span>
                <input type="date" name="tanggal_mulai" value="{{ $filter['tanggal_mulai'] }}">
            </label>
            <label id="lpSampaiWrap" class="{{ $periodeMode === 'custom' ? '' : 'is-hidden' }}">
                <span>Sampai</span>
                <input type="date" name="tanggal_akhir" value="{{ $filter['tanggal_akhir'] }}">
            </label>
            <input type="hidden" name="aktivitas" value="absensi">
            <label>
                <span>Kelas</span>
                <select name="kelas">
                    <option value="">Semua kelas</option>
                    @foreach($kelasOptions as $kelas)
                        <option value="{{ $kelas }}" @selected(($filter['kelas'] ?? '') === $kelas)>{{ $kelas }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Tim PBL</span>
                <select name="tim">
                    <option value="">Semua tim</option>
                    @foreach($timOptions as $tim)
                        <option value="{{ $tim }}" @selected(($filter['tim'] ?? '') === $tim)>{{ $tim }}</option>
                    @endforeach
                </select>
            </label>
            <div class="lp-filter__actions">
                <button type="submit" class="lp-btn lp-btn--primary">Terapkan filter</button>
                <a href="{{ route($routes['index']) }}" class="lp-btn lp-btn--ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="lp-export animate-in">
        <div class="lp-export__info">
            <strong>Export mengikuti filter di atas</strong>
            <small>Bukan dump semua mahasiswa. Periode: <b>{{ $periodeLabel ?? '—' }}</b>
                @if(!empty($filter['kelas'])) · kelas {{ $filter['kelas'] }} @endif
                @if(!empty($filter['tim'])) · tim {{ $filter['tim'] }} @endif
                · {{ number_format($aktifCount) }} mhs aktif di periode
            </small>
        </div>
        <div class="lp-export__btns">
            <a href="{{ route($routes['export_excel'], request()->query()) }}" class="lp-btn lp-btn--excel">Excel</a>
            <a href="{{ route($routes['print'], request()->query()) }}" target="_blank" class="lp-btn lp-btn--pdf">PDF</a>
            <a href="{{ route($routes['export_word'], request()->query()) }}" class="lp-btn lp-btn--word">Word</a>
        </div>
    </div>

    <div class="lp-kpis animate-in">
        <div class="lp-kpi">
            <span>Mahasiswa dalam cakupan</span>
            <strong>{{ number_format($summary['mahasiswa'] ?? 0) }}</strong>
            <small title="Dalam laporan = punya catatan hadir/tidak lengkap/alfa di periode (baris Excel/Word/cetak)">orang · {{ number_format($aktifCount) }} dalam laporan</small>
        </div>
        @unless($showLokerOnly)
            <div class="lp-kpi lp-kpi--ok">
                <span>Hadir</span>
                <strong>{{ number_format($summary['hadir'] ?? 0) }}</strong>
                <small>sesi-mahasiswa · lengkap</small>
            </div>
            <div class="lp-kpi lp-kpi--warn">
                <span>Tidak lengkap</span>
                <strong>{{ number_format($summary['tidak_lengkap'] ?? 0) }}</strong>
                <small>sesi-mahasiswa · masuk tanpa pulang</small>
            </div>
            <div class="lp-kpi lp-kpi--bad">
                <span>Alfa</span>
                <strong>{{ number_format($summary['alfa'] ?? 0) }}</strong>
                <small>sesi-mahasiswa · tanpa tap wajib</small>
            </div>
        @endunless
        @if($showLokerKpi)
            <div class="lp-kpi lp-kpi--violet">
                <span>Loker</span>
                <strong>{{ number_format($summary['loker'] ?? 0) }}</strong>
                <small>{{ $isDosen ? 'Tim PBL Anda' : 'Akses buka/tutup' }}</small>
            </div>
        @endif
    </div>

    <div class="lp-charts animate-in">
        <section class="lp-card lp-card--chart">
            <div class="lp-card__head">
                <div>
                    <h3>Tren {{ $showLokerOnly ? 'akses loker' : 'kehadiran' }} harian</h3>
                    <p>
                        @if($showLokerOnly)
                            Akses loker per hari
                        @else
                            Hadir · tidak lengkap · alfa
                        @endif
                    </p>
                </div>
                <button type="button" class="lp-help-q" onclick="openLpHelp('chart')" aria-label="Panduan grafik">?</button>
            </div>
            <div class="lp-chart-body">
                <canvas id="reportChartLine" height="280" aria-label="Grafik aktivitas harian"></canvas>
            </div>
        </section>

        <section class="lp-card">
            <div class="lp-card__head">
                <div>
                    <h3>{{ $showLokerOnly ? 'Komposisi loker' : 'Komposisi absensi' }}</h3>
                    <p>{{ $showLokerOnly ? 'Proporsi akses loker di periode' : 'Khusus status absensi — Hadir · TL · Alfa (bukan mesin)' }}</p>
                </div>
            </div>
            <div class="lp-chart-body lp-chart-body--donut">
                <canvas id="reportChartDonut" height="240"></canvas>
            </div>
            @php
                $donutHadir = (int) ($summary['hadir'] ?? 0);
                $donutTl = (int) ($summary['tidak_lengkap'] ?? 0);
                $donutAlfa = (int) ($summary['alfa'] ?? 0);
                $donutLoker = (int) ($summary['loker'] ?? 0);
                $donutSum = $showLokerOnly ? max(0, $donutLoker) : max(0, $donutHadir + $donutTl + $donutAlfa);
                $pct = static function (int $n, int $sum): string {
                    return $sum > 0 ? (string) round(($n / $sum) * 100) : '0';
                };
            @endphp
            <div class="lp-donut-legend" aria-label="Persentase komposisi">
                @if($showLokerOnly)
                    <span><i style="background:#7c3aed"></i> Loker ({{ $pct($donutLoker, $donutSum) }}%)</span>
                @else
                    <span><i style="background:#0f766e"></i> Hadir ({{ $pct($donutHadir, $donutSum) }}%)</span>
                    <span><i style="background:#ea580c"></i> Tidak lengkap ({{ $pct($donutTl, $donutSum) }}%)</span>
                    <span><i style="background:#e11d48"></i> Alfa ({{ $pct($donutAlfa, $donutSum) }}%)</span>
                @endif
            </div>
            <div class="lp-machines">
                <strong>{{ $showLokerOnly ? 'Top mesin loker' : 'Top mesin absensi' }}</strong>
                <small class="lp-machines__hint">Log perangkat — terpisah dari komposisi status di atas</small>
                @forelse($topMachines->take(5) as $machine)
                    <div class="lp-machines__row">
                        <span>{{ $machine['aktivitas'] }} · <code>{{ $machine['id_mesin'] }}</code></span>
                        <b>{{ number_format($machine['total']) }}</b>
                    </div>
                @empty
                    <p class="lp-empty-sm">Belum ada log mesin.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="lp-card animate-in">
        <div class="lp-card__head lp-card__head--wrap">
            <div>
                <h3>{{ $rankings['title'] }}</h3>
                <p>{{ $rankings['subtitle'] }}</p>
            </div>
            <button type="button" class="lp-help-q" onclick="openLpHelp('rekor')" aria-label="Panduan rekor">?</button>
        </div>

        @unless($showLokerOnly)
            <div class="lp-rekor-block">
                <span class="lp-rekor-label">Metrik</span>
                <div class="lp-rekor-tabs">
                    @foreach([
                        'terajin' => 'Terajin',
                        'alfa' => 'Sering alfa',
                        'jarang' => 'Tap jarang',
                        'tidak_lengkap' => 'Tanpa pulang',
                    ] as $key => $label)
                        <a href="{{ route($routes['index'], array_merge($queryKeep, ['rekor' => $key, 'rekor_group' => $rekorGroup])) }}"
                           class="lp-rekor-tab {{ $rekor === $key ? 'is-active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="lp-rekor-block">
                <span class="lp-rekor-label">Cakupan</span>
                <div class="lp-rekor-tabs">
                    @foreach([
                        'semua' => 'Semua mahasiswa',
                        'kelas' => 'Per kelas',
                        'tim' => 'Per tim PBL',
                    ] as $key => $label)
                        <a href="{{ route($routes['index'], array_merge($queryKeep, ['rekor' => $rekor, 'rekor_group' => $key])) }}"
                           class="lp-rekor-tab {{ $rekorGroup === $key ? 'is-active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        @endunless

        <div class="lp-rank-list">
            @forelse($rankings['items'] as $item)
                @php
                    $barHadir = (int) ($item['bars']['hadir'] ?? 0);
                    $barTl = (int) ($item['bars']['tidak_lengkap'] ?? 0);
                    $barAlfa = (int) ($item['bars']['alfa'] ?? 0);
                    $barLoker = (int) ($item['bars']['loker'] ?? 0);
                    $barTotal = max(1, $showLokerOnly ? $barLoker : ($barHadir + $barTl + $barAlfa));
                    $medal = $item['rank'] === 1 ? 'gold' : ($item['rank'] === 2 ? 'silver' : ($item['rank'] === 3 ? 'bronze' : ''));
                @endphp
                <article class="lp-rank {{ $medal ? 'lp-rank--'.$medal : '' }}">
                    <div class="lp-rank__n">{{ $item['rank'] }}</div>
                    <div class="lp-rank__body">
                        <div class="lp-rank__top">
                            <div>
                                @if(!empty($item['user_id']))
                                    <button type="button"
                                        class="js-periodic-student-open lp-rank__name"
                                        data-user-id="{{ $item['user_id'] }}"
                                        data-nama="{{ $item['label'] }}">
                                        {{ $item['label'] }}
                                    </button>
                                @else
                                    <strong class="lp-rank__name">{{ $item['label'] }}</strong>
                                @endif
                                <div class="lp-rank__meta">{{ $item['meta'] }}</div>
                            </div>
                            <div class="lp-rank__value">
                                <div class="lp-rank__value-row">
                                    <strong>{{ $item['value_label'] }}</strong>
                                    @if($medal)
                                        <span class="lp-trophy lp-trophy--{{ $medal }}" title="Peringkat {{ $item['rank'] }}" aria-hidden="true">
                                            <i data-lucide="trophy"></i>
                                        </span>
                                    @endif
                                </div>
                                <small>{{ $rankings['metric'] }}</small>
                            </div>
                        </div>
                        @if($showLokerOnly && $barLoker > 0)
                            <div class="lp-rank__bars" title="Loker">
                                <i style="width:100%;background:#7c3aed;"></i>
                            </div>
                        @elseif(!$showLokerOnly && ($barHadir + $barTl + $barAlfa) > 0)
                            <div class="lp-rank__bars" title="Hadir / Tidak lengkap / Alfa">
                                <i style="width:{{ round(($barHadir / $barTotal) * 100, 1) }}%;background:#0f766e;"></i>
                                <i style="width:{{ round(($barTl / $barTotal) * 100, 1) }}%;background:#ea580c;"></i>
                                <i style="width:{{ round(($barAlfa / $barTotal) * 100, 1) }}%;background:#e11d48;"></i>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="lp-empty">Belum ada data rekor untuk kategori / filter ini.</div>
            @endforelse
        </div>
        <div class="lp-legend">
            @if($showLokerOnly)
                <span><i style="background:#7c3aed"></i> Loker</span>
            @else
                <span><i style="background:#0f766e"></i> Hadir</span>
                <span><i style="background:#ea580c"></i> Tidak lengkap</span>
                <span><i style="background:#e11d48"></i> Alfa</span>
            @endif
        </div>
    </section>

    <section class="lp-card animate-in">
        <div class="lp-card__head">
            <div>
                <h3>Aktivitas terbaru</h3>
                <p>Cuplikan 12 log terakhir di periode</p>
            </div>
        </div>
        <div class="lp-table-wrap">
            <table class="data-table lp-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aktivitas</th>
                        <th>Nama</th>
                        <th>Kelas / Tim</th>
                        <th>Mesin</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivities as $activity)
                        <tr>
                            <td class="lp-mono">{{ optional($activity['waktu'])->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $activity['aktivitas'] }}</td>
                            <td><strong>{{ $activity['nama'] }}</strong><div class="lp-sub">{{ $activity['nim'] }}</div></td>
                            <td>{{ $activity['kelas'] }} / {{ $activity['tim'] }}</td>
                            <td class="lp-mono">{{ $activity['id_mesin'] }}</td>
                            <td>
                                @if(($activity['tap_jenis'] ?? null) === 'masuk')
                                    <span class="tap-jenis-badge masuk">Masuk</span>
                                @elseif(($activity['tap_jenis'] ?? null) === 'pulang')
                                    <span class="tap-jenis-badge pulang">Pulang</span>
                                @else
                                    {{ $activity['status'] }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="lp-empty">Belum ada aktivitas pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div id="periodicStudentDetailConfig"
        data-url-template="{{ str_replace('/0/detail', '/__USER__/detail', route($routes['student_detail'], ['user' => 0])) }}"
        hidden></div>

    <div class="abs-modal-overlay" id="periodicStudentModal" role="dialog" aria-modal="true" aria-labelledby="periodicStudentModalTitle">
        <div class="abs-modal periodic-student-modal">
            <div class="abs-modal-h">
                <h3 id="periodicStudentModalTitle">Detail Mahasiswa</h3>
                <button type="button" class="access-modal-close" id="periodicStudentModalClose" aria-label="Tutup" style="position:static;width:36px;height:36px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--bg-main);cursor:pointer;font-size:22px;line-height:1;">×</button>
            </div>
            <div class="abs-modal-b periodic-student-modal__body" id="periodicStudentModalBody" style="max-height:min(72vh,720px);overflow:auto;">
                <p style="text-align:center;color:var(--text-muted);padding:32px 16px;">Pilih nama di daftar rekor.</p>
            </div>
        </div>
    </div>

    <div class="iot-modal-backdrop" id="lpHelpModal" role="dialog" aria-modal="true">
        <div class="iot-modal lp-help-modal">
            <button type="button" class="iot-modal-close" onclick="closeLpHelp()" aria-label="Tutup">×</button>
            <div class="lp-help-badge">PANDUAN</div>
            <h3 id="lpHelpTitle">Laporan Periodik</h3>
            <p id="lpHelpDesc" class="lp-help-desc"></p>
            <div class="lp-help-code" id="lpHelpCode"></div>
            <div class="lp-help-actions">
                <button type="button" class="lp-btn lp-btn--primary" onclick="closeLpHelp()">Mengerti</button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
.lp-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px;padding:14px 16px;border-radius:14px;border:1px solid var(--border-subtle);background:var(--bg-card)}
.lp-toolbar__left{display:flex;align-items:center;gap:12px}
.lp-toolbar__left strong{display:block;font-size:14px;color:var(--text-dark)}
.lp-toolbar__left small{display:block;margin-top:2px;font-size:12px;color:var(--text-muted)}
.lp-toolbar__right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.lp-live{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em}
.lp-live__dot{width:7px;height:7px;border-radius:999px;background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.2)}
.lp-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:0 13px;border-radius:10px;border:1px solid var(--border-subtle);background:transparent;color:var(--text-dark);font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap;font-family:inherit}
.lp-btn--primary{background:#0f766e;border-color:#0f766e;color:#fff}
.lp-btn--excel{background:#15803d;border-color:#15803d;color:#fff}
.lp-btn--pdf{background:#1e3a5f;border-color:#1e3a5f;color:#fff}
.lp-btn--word{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.lp-btn--ghost:hover{border-color:color-mix(in srgb,#0f766e 40%,var(--border-subtle))}
.lp-help-q{display:inline-grid;place-items:center;width:18px;height:18px;margin-left:4px;border:1px solid color-mix(in srgb,#0f766e 45%,var(--border-subtle));border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;font-size:11px;font-weight:800;line-height:1;cursor:pointer;padding:0}
.lp-help-q:hover,.lp-btn .lp-help-q{background:#0f766e;color:#fff;border-color:#0f766e}

.lp-filter{display:grid;gap:14px;margin-bottom:14px;padding:14px;border-radius:14px;border:1px solid var(--border-subtle);background:var(--bg-card)}
.lp-period__label,.lp-rekor-label{display:block;font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px}
.lp-period__chips{display:flex;flex-wrap:wrap;gap:8px}
.lp-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;border:1px solid var(--border-subtle);background:transparent;color:var(--text-muted);font-size:12px;font-weight:700;cursor:pointer}
.lp-chip input{position:absolute;opacity:0;pointer-events:none}
.lp-chip.is-active{background:rgba(15,118,110,.14);border-color:rgba(15,118,110,.4);color:var(--text-dark)}
.lp-filter__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:10px;align-items:end}
.lp-filter label{display:grid;gap:5px}
.lp-filter label.is-hidden{display:none}
.lp-filter span{font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em}
.lp-filter input,.lp-filter select{width:100%;min-height:38px;padding:0 10px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--bg-main);color:var(--text-dark);font-size:13px}
.lp-filter__actions{display:flex;gap:8px;flex-wrap:wrap}

.lp-export{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px;padding:14px 16px;border-radius:14px;border:1px dashed color-mix(in srgb,#0f766e 35%,var(--border-subtle));background:rgba(15,118,110,.06)}
.lp-export__info strong{display:block;font-size:13px;color:var(--text-dark)}
.lp-export__info small{display:block;margin-top:3px;font-size:12px;color:var(--text-muted);line-height:1.45}
.lp-export__btns{display:flex;gap:8px;flex-wrap:wrap}

.lp-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px}
.lp-kpi{padding:14px 16px;border-radius:14px;border:1px solid var(--border-subtle);background:var(--bg-card);border-left:3px solid #2563eb}
.lp-kpi--ok{border-left-color:#0f766e}
.lp-kpi--warn{border-left-color:#ea580c}
.lp-kpi--bad{border-left-color:#e11d48}
.lp-kpi--violet{border-left-color:#7c3aed}
.lp-kpi span{display:block;font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--text-muted)}
.lp-kpi strong{display:block;margin-top:4px;font-size:26px;color:var(--text-dark);line-height:1.1}
.lp-kpi small{display:block;margin-top:4px;font-size:11.5px;color:var(--text-muted)}

.lp-charts{display:grid;grid-template-columns:1.4fr .85fr;gap:14px;margin-bottom:16px}
.lp-card{margin-bottom:16px;padding:16px;border-radius:16px;border:1px solid var(--border-subtle);background:var(--bg-card)}
.lp-card--chart{margin-bottom:0}
.lp-card__head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px}
.lp-card__head--wrap{flex-wrap:wrap}
.lp-card__head h3{margin:0;font-size:16px;color:var(--text-dark)}
.lp-card__head p{margin:4px 0 0;font-size:12.5px;color:var(--text-muted)}
.lp-chart-body{position:relative;min-height:260px}
.lp-chart-body--donut{min-height:200px}
.lp-donut-legend{display:flex;flex-wrap:wrap;justify-content:center;gap:12px 16px;margin-top:8px;font-size:12.5px;font-weight:700;color:var(--text-dark)}
[data-theme="dark"] .lp-donut-legend{color:#f1f5f9}
.lp-donut-legend i{display:inline-block;width:10px;height:10px;border-radius:999px;margin-right:6px;vertical-align:middle}
.lp-machines{margin-top:14px;padding-top:12px;border-top:1px solid var(--border-subtle)}
.lp-machines strong{display:block;font-size:12px;color:var(--text-dark)}
.lp-machines__hint{display:block;margin:2px 0 8px;font-size:11px;color:var(--text-muted)}
.lp-machines__row{display:flex;justify-content:space-between;gap:8px;padding:7px 0;font-size:12.5px;border-bottom:1px dashed var(--border-subtle);color:var(--text-dark)}
.lp-machines__row:last-child{border-bottom:0}
.lp-machines__row code{font-size:11.5px}
.lp-empty-sm{margin:0;font-size:12px;color:var(--text-muted)}

.lp-rekor-block{margin-bottom:12px}
.lp-rekor-tabs{display:flex;flex-wrap:wrap;gap:8px}
.lp-rekor-tab{display:inline-flex;padding:8px 12px;border-radius:999px;border:1px solid var(--border-subtle);background:transparent;color:var(--text-muted);font-size:12px;font-weight:700;text-decoration:none}
.lp-rekor-tab.is-active{background:rgba(15,118,110,.14);border-color:rgba(15,118,110,.4);color:var(--text-dark)}

.lp-rank-list{display:grid;gap:10px}
.lp-rank{display:flex;gap:12px;align-items:stretch;padding:12px 14px;border-radius:14px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 28%,transparent)}
.lp-rank--gold{border-color:rgba(234,179,8,.35);background:rgba(234,179,8,.08)}
.lp-rank--silver{border-color:rgba(148,163,184,.35);background:rgba(148,163,184,.08)}
.lp-rank--bronze{border-color:rgba(180,83,9,.35);background:rgba(180,83,9,.08)}
.lp-rank__n{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;flex-shrink:0;background:rgba(15,118,110,.14);color:#0f766e;font-weight:800;font-size:14px}
.lp-rank--gold .lp-rank__n{background:rgba(234,179,8,.2);color:#ca8a04}
.lp-rank--silver .lp-rank__n{background:rgba(148,163,184,.22);color:#64748b}
.lp-rank--bronze .lp-rank__n{background:rgba(180,83,9,.2);color:#b45309}
.lp-rank__body{flex:1;min-width:0}
.lp-rank__top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.lp-rank__name{border:0;background:none;padding:0;font:inherit;font-weight:800;font-size:14px;color:var(--text-dark);cursor:pointer;text-align:left}
button.lp-rank__name{color:#0f766e;text-decoration:underline;text-underline-offset:3px;text-decoration-color:rgba(15,118,110,.35)}
.lp-rank__meta{margin-top:3px;font-size:12px;color:var(--text-muted)}
.lp-rank__value{text-align:right}
.lp-rank__value-row{display:flex;align-items:center;justify-content:flex-end;gap:8px}
.lp-rank__value strong{display:block;font-size:16px;color:var(--text-dark)}
.lp-rank__value small{font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;font-weight:800}
.lp-trophy{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:10px;flex-shrink:0}
.lp-trophy [data-lucide]{width:16px;height:16px}
.lp-trophy--gold{background:rgba(234,179,8,.2);color:#ca8a04}
.lp-trophy--silver{background:rgba(148,163,184,.22);color:#94a3b8}
.lp-trophy--bronze{background:rgba(180,83,9,.22);color:#c2410c}
.lp-rank__bars{display:flex;height:6px;border-radius:999px;overflow:hidden;margin-top:10px;background:rgba(148,163,184,.15)}
.lp-rank__bars i{display:block;height:100%}
.lp-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;font-size:11.5px;color:var(--text-muted)}
.lp-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:middle}

.lp-table-wrap{overflow-x:auto}
.lp-table{min-width:720px}
.lp-mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px}
.lp-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.lp-empty{text-align:center;color:var(--text-muted);padding:28px 14px}

.iot-modal-backdrop{position:fixed;inset:0;z-index:3000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.72);backdrop-filter:blur(8px)}
.iot-modal-backdrop.is-open{display:flex}
.iot-modal{width:min(560px,100%);position:relative;border:1px solid rgba(148,163,184,.28);border-radius:18px;padding:22px;background:var(--bg-card);color:var(--text-dark);box-shadow:0 24px 70px rgba(15,23,42,.38);max-height:min(90vh,720px);overflow:auto}
.iot-modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:999px;background:rgba(148,163,184,.14);color:var(--text-dark);font-size:20px;cursor:pointer}
.lp-help-badge{display:inline-flex;padding:5px 10px;border-radius:999px;margin-bottom:8px;background:rgba(15,118,110,.14);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em}
.lp-help-modal h3{margin:0 0 8px;font-size:18px}
.lp-help-desc{margin:0 0 12px;font-size:13px;line-height:1.6;color:var(--text-muted)}
.lp-help-code{font-family:ui-monospace,Consolas,monospace;font-size:12px;line-height:1.55;padding:12px 14px;border-radius:12px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 40%,transparent);color:var(--text-dark);white-space:pre-wrap;margin-bottom:8px}
.lp-help-code:empty{display:none}
.lp-help-actions{display:flex;justify-content:flex-end}
.periodic-student-modal{width:min(960px,100%);max-height:92vh;display:flex;flex-direction:column}
.periodic-student-modal__body{padding-top:8px!important}

@media (max-width:1100px){
    .lp-filter__grid{grid-template-columns:1fr 1fr}
    .lp-charts{grid-template-columns:1fr}
}
@media (max-width:720px){
    .lp-filter__grid{grid-template-columns:1fr}
    .lp-toolbar{align-items:stretch}
    .lp-toolbar__right{width:100%}
}
</style>
<script src="{{ asset('js/periodic-student-detail.js') }}?v=1" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/simalas-charts.js') }}?v=8"></script>
<script>
const LP_HELP = {
    general: {
        title: 'Laporan Periodik',
        desc: 'Filter dulu berdasarkan kalender (minggu/bulan), kelas, atau tim PBL. Export mengikuti filter itu — bukan dump semua mahasiswa sekaligus.',
        code: 'Periode     → minggu / bulan / tahun-bulan / custom\nKelas/Tim   → sempitkan data yang ditampilkan & diekspor\nExcel/PDF/Word → hanya format file, isi tetap sesuai filter'
    },
    chart: {
        title: 'Grafik & komposisi absensi',
        desc: 'Tren harian dan donut “Komposisi absensi” khusus status Hadir / Tidak lengkap / Alfa. Bukan keseluruhan mesin — Top mesin di bawahnya terpisah (log perangkat).',
        code: 'Hadir (x%)         = masuk + pulang lengkap\nTidak lengkap (x%) = masuk tanpa pulang\nAlfa (x%)          = tanpa tap hari wajib\n\nPersentase tampil di legenda tanpa perlu hover.'
    },
    rekor: {
        title: 'Rekor mahasiswa',
        desc: 'Pilih metrik dulu (Terajin, Alfa, dll), lalu cakupan: Semua mahasiswa → opsional pecah Per kelas / Per tim PBL.',
        code: '1. Metrik  → apa yang diukur\n2. Semua   → ranking individu\n3. Pecah   → agregat per kelas atau per tim'
    }
};

function openLpHelp(kind) {
    const data = LP_HELP[kind] || LP_HELP.general;
    const modal = document.getElementById('lpHelpModal');
    document.getElementById('lpHelpTitle').textContent = data.title;
    document.getElementById('lpHelpDesc').textContent = data.desc;
    document.getElementById('lpHelpCode').textContent = data.code || '';
    modal.style.display = 'flex';
    modal.classList.add('is-open');
}
function closeLpHelp() {
    const modal = document.getElementById('lpHelpModal');
    modal.style.display = 'none';
    modal.classList.remove('is-open');
}

function lpTogglePeriode() {
    const form = document.getElementById('lpFilterForm');
    if (!form) return;
    const mode = (form.querySelector('input[name="periode"]:checked') || {}).value || 'bulan_ini';
    form.querySelectorAll('.lp-chip').forEach((el) => {
        const input = el.querySelector('input');
        el.classList.toggle('is-active', input && input.value === mode);
    });
    const bulan = document.getElementById('lpBulanWrap');
    const dari = document.getElementById('lpDariWrap');
    const sampai = document.getElementById('lpSampaiWrap');
    if (bulan) bulan.classList.toggle('is-hidden', mode !== 'tahun_bulan');
    if (dari) dari.classList.toggle('is-hidden', mode !== 'custom');
    if (sampai) sampai.classList.toggle('is-hidden', mode !== 'custom');
}

document.addEventListener('DOMContentLoaded', function () {
    lpTogglePeriode();

    const canvas = document.getElementById('reportChartLine');
    const donut = document.getElementById('reportChartDonut');
    const labels = @json(collect($chart['labels'] ?? [])->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->values());
    const activity = @json($aktivitas);

    if (canvas && typeof SimalasCharts !== 'undefined') {
        if (activity === 'loker') {
            SimalasCharts.buildLineChart(canvas, {
                labels,
                activity: 'loker',
                loker: @json($chart['loker'] ?? []),
                lokerLabel: 'Akses Loker',
                fill: true,
            });
        } else {
            // Absensi & "semua": grafik fokus Hadir / TL / Alfa — tanpa Loker.
            SimalasCharts.buildMultiLineChart(canvas, {
                labels,
                series: [
                    { label: 'Hadir', data: @json($chart['hadir'] ?? []), color: '#0f766e' },
                    { label: 'Tidak Lengkap', data: @json($chart['tidak_lengkap'] ?? []), color: '#ea580c' },
                    { label: 'Alfa', data: @json($chart['alfa'] ?? []), color: '#e11d48' },
                ],
                fill: true,
            });
        }
    }

    if (donut && typeof SimalasCharts !== 'undefined' && activity !== 'loker') {
        SimalasCharts.buildDonutChart(donut, {
            labels: ['Hadir', 'Tidak lengkap', 'Alfa'],
            values: [
                {{ (int) ($summary['hadir'] ?? 0) }},
                {{ (int) ($summary['tidak_lengkap'] ?? 0) }},
                {{ (int) ($summary['alfa'] ?? 0) }},
            ],
            colors: ['#0f766e', '#ea580c', '#e11d48'],
            centerMain: {{ (int) (($summary['hadir'] ?? 0) + ($summary['tidak_lengkap'] ?? 0) + ($summary['alfa'] ?? 0)) }},
            centerSub: 'sesi-mahasiswa',
            hideLegend: true,
        });
    } else if (donut && typeof SimalasCharts !== 'undefined') {
        SimalasCharts.buildDonutChart(donut, {
            labels: ['Loker'],
            values: [{{ (int) ($summary['loker'] ?? 0) }}],
            colors: ['#7c3aed'],
            centerMain: {{ (int) ($summary['loker'] ?? 0) }},
            centerSub: 'Akses',
            hideLegend: true,
        });
    }

    const helpModal = document.getElementById('lpHelpModal');
    if (helpModal) {
        helpModal.addEventListener('click', (e) => { if (e.target === helpModal) closeLpHelp(); });
    }
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLpHelp(); });
    if (window.refreshLucideIcons) window.refreshLucideIcons();
});
</script>
@endsection
