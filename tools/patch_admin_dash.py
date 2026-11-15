from pathlib import Path

p = Path(r"c:\xampp\htdocs\Simalas\resources\views\dashboards\admin.blade.php")
t = p.read_text(encoding="utf-8")

idx = t.find('        <div class="adm-period-pill"')
end = t.find('        <motion class="adm-mini-grid">')
if end < 0:
    end = t.find('        <motion class="adm-mini-grid">')
if end < 0:
    end = t.find('        <div class="adm-mini-grid">')

new_block = """        <div class="period-pill adm-period-pill" id="adm-period-label">
            @include('components.icon', ['name' => 'calendar-range'])
            <span>{{ $a['period_label'] ?? '' }}</span>
        </div>

        <div class="stat-cards">
            @include('partials.stat-card', ['tone' => 'blue', 'icon' => 'users', 'label' => 'Total pengguna', 'value' => $s['total_user'], 'valueId' => 'g-user', 'meta' => $s['user_aktif'].' aktif · '.$s['user_pending'].' pending', 'metaId' => 'g-user-d'])
            @include('partials.stat-card', ['tone' => 'violet', 'icon' => 'cpu', 'label' => 'Mesin IoT', 'value' => $s['mesin_aktif'].'/'.$s['mesin_total'], 'valueId' => 'g-mesin', 'meta' => $s['mesin_absensi'].' absensi · '.$s['mesin_loker'].' loker', 'metaId' => 'g-mesin-d'])
            @include('partials.stat-card', ['tone' => 'teal', 'icon' => 'clipboard-check', 'label' => 'Tap absensi', 'value' => $s['tap_absensi'], 'valueId' => 'g-tap', 'meta' => 'Δ '.(($s['tap_delta'] ?? 0) >= 0 ? '+' : '').($s['tap_delta'] ?? 0).' vs sebelumnya', 'metaId' => 'g-tap-d', 'metaClass' => ($s['tap_delta'] ?? 0) >= 0 ? 'up' : 'down'])
            @include('partials.stat-card', ['tone' => 'amber', 'icon' => 'lock', 'label' => 'Akses loker', 'value' => $s['akses_loker'], 'valueId' => 'g-lok', 'meta' => 'Δ '.(($s['loker_delta'] ?? 0) >= 0 ? '+' : '').($s['loker_delta'] ?? 0).' vs sebelumnya', 'metaId' => 'g-lok-d', 'metaClass' => ($s['loker_delta'] ?? 0) >= 0 ? 'up' : 'down'])
        </div>

        <div class="dash-chart-grid">
            <div class="chart-panel animate-in">
                <div class="chart-panel__head">
                    <h3>Aktivitas IoT</h3>
                    <span class="chart-panel__sub" id="adm-line-subtitle">{{ $a['line']['subtitle'] ?? '' }}</span>
                </div>
                <div class="chart-panel__body">
                    <canvas id="admChartLine"></canvas>
                </div>
            </div>
            <div class="chart-panel animate-in">
                <div class="chart-panel__head">
                    <h3>Distribusi role</h3>
                    <span class="chart-panel__sub">Mahasiswa / dosen / laboran</span>
                </div>
                <div class="chart-panel__body chart-panel__body--donut">
                    <canvas id="admChartDonut"></canvas>
                </div>
            </div>
        </div>

"""

print("start", idx, "end", end)
assert idx >= 0 and end > idx
t = t[:idx] + new_block + t[end:]
p.write_text(t, encoding="utf-8")
print("ok")
