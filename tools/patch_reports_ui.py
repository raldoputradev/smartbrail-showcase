from pathlib import Path

p = Path(__file__).resolve().parents[1] / "resources/views/admin/reports.blade.php"
t = p.read_text(encoding="utf-8")

t = t.replace(
    "@section('page-subtitle', 'Pusat rekap resmi absensi dan loker')",
    "@section('page-subtitle', 'Agregat periode — tap absensi, loker, dan grafik resmi')",
)

t = t.replace(
    "@section('content')\n    <div class=\"card animate-in\"",
    "@section('content')\n    @include('partials.module-hint-rekap-laporan', ['variant' => 'laporan'])\n\n    <div class=\"card animate-in\"",
)

old_filter_btns = """                <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                    <button type="submit" class="topbar-btn" title="Terapkan Filter"><span>🔎</span> Filter</button>
                    <a href="{{ route('admin.reports') }}" class="topbar-btn" title="Reset Filter" style="text-decoration:none;"><span>↺</span> Reset</a>
                    <a href="{{ route('admin.reports.export.excel', request()->query()) }}" class="export-btn excel" title="Export Excel" style="text-decoration:none;"><span class="export-btn-icon">📊</span> Excel</a>
                    <a href="{{ route('admin.reports.print', request()->query()) }}" target="_blank" class="export-btn pdf" title="Cetak / Simpan PDF" style="text-decoration:none;"><span class="export-btn-icon">🧾</span> PDF</a>
                </div>"""

new_filter_btns = """                <div class="report-filter-actions">
                    <button type="submit" class="topbar-btn report-filter-btn" title="Terapkan Filter">
                        @include('components.icon', ['name' => 'search'])<span>Filter</span>
                    </button>
                    <a href="{{ route('admin.reports') }}" class="topbar-btn report-filter-btn" title="Reset Filter" style="text-decoration:none;">
                        @include('components.icon', ['name' => 'rotate-ccw'])<span>Reset</span>
                    </a>
                    <a href="{{ route('admin.reports.export.excel', request()->query()) }}" class="export-btn excel" title="Export Excel" style="text-decoration:none;">
                        <span class="export-btn-icon">@include('components.icon', ['name' => 'file-spreadsheet'])</span><span>Excel</span>
                    </a>
                    <a href="{{ route('admin.reports.print', request()->query()) }}" target="_blank" class="export-btn pdf" title="Cetak / Simpan PDF" style="text-decoration:none;">
                        <span class="export-btn-icon">@include('components.icon', ['name' => 'file-text'])</span><span>PDF</span>
                    </a>
                    <a href="{{ route('admin.reports.export.word', request()->query()) }}" class="export-btn word" title="Export Word" style="text-decoration:none;">
                        <span class="export-btn-icon">@include('components.icon', ['name' => 'file-type'])</span><span>Word</span>
                    </a>
                </motion>"""

# fix motion typo in new string
new_filter_btns = new_filter_btns.replace("</motion>", "</div>")

if old_filter_btns not in t:
    raise SystemExit("filter buttons block not found")
t = t.replace(old_filter_btns, new_filter_btns)

old_cards = """    <div class="report-cards">
        <div class="report-card blue">
            <span>Mahasiswa</span>
            <strong>{{ number_format($summary['mahasiswa']) }}</strong>
            <small>Sesuai filter kelas/tim</small>
        </div>
        <div class="report-card green">
            <span>Total Absensi</span>
            <strong>{{ number_format($summary['absensi']) }}</strong>
            <small>Tap fingerprint</small>
        </div>
        <div class="report-card amber">
            <span>Total Loker</span>
            <strong>{{ number_format($summary['loker']) }}</strong>
            <small>Akses buka/tutup</small>
        </div>
        <motion class="report-card purple">
            <span>Mesin Online</span>
            <strong>{{ number_format($summary['mesin_online']) }}</strong>
            <small>Heartbeat 5 menit</small>
        </motion>
    </motion>"""

old_cards = old_cards.replace("<motion", "<motion").replace("</motion>", "</div>")
old_cards = old_cards.replace("report-card purple", "report-card purple")  # keep
# rewrite old_cards correctly
old_cards = """    <div class="report-cards">
        <div class="report-card blue">
            <span>Mahasiswa</span>
            <strong>{{ number_format($summary['mahasiswa']) }}</strong>
            <small>Sesuai filter kelas/tim</small>
        </div>
        <motion class="report-card green">
            <span>Total Absensi</span>
            <strong>{{ number_format($summary['absensi']) }}</strong>
            <small>Tap fingerprint</small>
        </motion>
        <div class="report-card amber">
            <span>Total Loker</span>
            <strong>{{ number_format($summary['loker']) }}</strong>
            <small>Akses buka/tutup</small>
        </div>
        <div class="report-card purple">
            <span>Mesin Online</span>
            <strong>{{ number_format($summary['mesin_online']) }}</strong>
            <small>Heartbeat 5 menit</small>
        </div>
    </div>"""

# I'm making this too complicated - read file and find report-cards block
start = t.find('    <div class="report-cards">')
end = t.find('    <div class="report-grid">', start)
if start < 0 or end < 0:
    raise SystemExit(f"report-cards not found {start} {end}")

new_cards = """    <div class="stat-cards">
        @include('partials.stat-card', [
            'tone' => 'blue',
            'icon' => 'users',
            'label' => 'Mahasiswa',
            'value' => number_format($summary['mahasiswa']),
            'meta' => 'Sesuai filter kelas/tim',
        ])
        @include('partials.stat-card', [
            'tone' => 'teal',
            'icon' => 'fingerprint',
            'label' => 'Total Absensi',
            'value' => number_format($summary['absensi']),
            'meta' => 'Tap fingerprint',
        ])
        @include('partials.stat-card', [
            'tone' => 'amber',
            'icon' => 'lock-keyhole',
            'label' => 'Total Loker',
            'value' => number_format($summary['loker']),
            'meta' => 'Akses buka/tutup',
        ])
        @include('partials.stat-card', [
            'tone' => 'violet',
            'icon' => 'cpu',
            'label' => 'Mesin Online',
            'value' => number_format($summary['mesin_online']),
            'meta' => 'Heartbeat 5 menit',
        ])
    </div>

"""

t = t[:start] + new_cards + t[end:]

# Replace chart card body - find from card-header Grafik to end of card before Top Mesin
chart_start = t.find('<h3>Grafik Aktivitas Harian</h3>')
if chart_start < 0:
    raise SystemExit("chart header not found")
body_start = t.rfind('<div class="card-body">', 0, chart_start)
body_end = t.find('        </div>\n        </div>\n\n        <div class="card animate-in">', chart_start)
if body_end < 0:
    body_end = t.find('        <div class="card animate-in">\n            <div class="card-header">\n                <h3>Top Mesin</h3>', chart_start)
if body_end < 0:
    raise SystemExit("chart body end not found")

new_chart_body = """            <div class="card-body" style="padding:0;">
                @if(count($chart['labels']) > 0)
                    <div class="chart-panel" style="border:none;box-shadow:none;background:transparent;">
                        <div class="chart-panel__body" style="min-height:300px;">
                            <canvas id="reportChartLine" aria-label="Grafik aktivitas harian"></canvas>
                        </div>
                    </div>
                @else
                    <div class="empty-report" style="margin:16px;">Belum ada aktivitas pada rentang filter ini.</div>
                @endif
            </div>
"""

# Find the card containing chart - replace entire card with chart-panel version
card_start = t.rfind('<div class="card animate-in">', 0, chart_start)
card_end = t.find('        <motion class="card animate-in">', card_start)
if card_end < 0:
    card_end = t.find('        <motion class="card animate-in">', card_start)
if card_end < 0:
    card_end = t.find('        <div class="card animate-in">', card_start + 10)

new_chart_card = """        <div class="chart-panel animate-in">
            <div class="chart-panel__head">
                <h3>Grafik Aktivitas Harian</h3>
                <span class="chart-panel__sub">{{ $filter['tanggal_mulai'] }} s/d {{ $filter['tanggal_akhir'] }}</span>
            </div>
            @if(count($chart['labels']) > 0)
                <div class="chart-panel__body">
                    <canvas id="reportChartLine" aria-label="Grafik aktivitas harian"></canvas>
                </div>
            @else
                <div class="empty-report">Belum ada aktivitas pada rentang filter ini.</div>
            @endif
        </div>

"""

if card_start < 0 or card_end < 0:
    raise SystemExit(f"chart card bounds {card_start} {card_end}")

t = t[:card_start] + new_chart_card + t[card_end:]

# Append scripts before @endsection of scripts section
scripts_marker = "@section('scripts')\n<style>"
if scripts_marker not in t:
    raise SystemExit("scripts section not found")

chart_script = """
    .report-filter-actions{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
    .report-filter-btn{display:inline-flex;align-items:center;gap:6px}
    .export-btn.word{background:linear-gradient(135deg,#1d4ed8,#60a5fa);color:#fff}
"""

t = t.replace(scripts_marker, scripts_marker + chart_script)

# Add chart.js at end before closing @endsection
if 'reportChartLine' in t and 'SimalasCharts.buildLineChart' not in t:
    inject = """
</style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/simalas-charts.js') }}?v=1"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('reportChartLine');
        if (!canvas || typeof SimalasCharts === 'undefined') return;
        const labels = @json(collect($chart['labels'] ?? [])->map(fn ($d) => \\Carbon\\Carbon::parse($d)->format('d/m'))->values());
        SimalasCharts.buildLineChart(canvas, {
            labels: labels,
            activity: @json($filter['aktivitas']),
            absensi: @json($chart['absensi'] ?? []),
            loker: @json($chart['loker'] ?? []),
            absensiLabel: 'Tap Absensi',
            lokerLabel: 'Akses Loker',
        });
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    });
    </script>
"""
    # Replace single @endsection at end of scripts - the file has @section('scripts') with style and @endsection
    last_end = t.rfind('@endsection')
    if last_end < 0:
        raise SystemExit("no endsection")
    # Find if we already have chart script
    if 'SimalasCharts.buildLineChart' not in t:
        # insert before final @endsection of scripts section
        scripts_end = t.rfind('</style>\n@endsection')
        if scripts_end < 0:
            raise SystemExit("scripts end not found")
        t = t[:scripts_end + len('</style>')] + inject + t[scripts_end + len('</style>'):]

p.write_text(t, encoding="utf-8")
print("patched reports")
