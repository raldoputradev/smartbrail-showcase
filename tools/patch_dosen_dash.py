from pathlib import Path

p = Path(r"c:\xampp\htdocs\Simalas\resources\views\dashboards\dosen.blade.php")
t = p.read_text(encoding="utf-8")

# Remove old gcard/chart css block lines
css_start = t.find('        .dosen-gcards {')
css_end = t.find('        .dosen-period-pill {')
if css_start >= 0 and css_end > css_start:
    t = t[:css_start] + '        .dosen-period-pill { margin-bottom: 8px; }\n' + t[css_end + len('        .dosen-period-pill {'):]
    # fix - that broke. simpler approach
p.write_text(t, encoding='utf-8')

p = Path(r"c:\xampp\htdocs\Simalas\resources\views\dashboards\dosen.blade.php")
t = p.read_text(encoding="utf-8")

idx = t.find('            <div class="dosen-period-pill"')
if idx < 0:
    idx = t.find('            <motion class="dosen-period-pill"')
end = t.find('            <div class="dosen-chart-grid">')
if end < 0:
    end = t.find('            @if(($pengumumanTerbaru')

new_block = """            <div class="period-pill dosen-period-pill" id="dash-period-label">
                @include('components.icon', ['name' => 'calendar-range'])
                <span>{{ $a['period_label'] ?? '' }}</span>
            </div>

            <div class="stat-cards" id="dash-gcards">
                @include('partials.stat-card', ['tone' => 'blue', 'icon' => 'users', 'label' => 'Mahasiswa aktif', 'value' => $s['total_mahasiswa'], 'valueId' => 'g-total', 'meta' => 'Sesuai filter kelas / tim'])
                @include('partials.stat-card', ['tone' => 'cyan', 'icon' => 'percent', 'label' => '% kehadiran unik', 'value' => $s['persen_hadir'].'%', 'valueId' => 'g-pct', 'meta' => 'vs periode sebelumnya: '.(($s['persen_delta'] ?? 0) >= 0 ? '+' : '').($s['persen_delta'] ?? 0).' poin', 'metaId' => 'g-pct-d', 'metaClass' => ($s['persen_delta'] ?? 0) >= 0 ? 'up' : 'down'])
                @include('partials.stat-card', ['tone' => 'teal', 'icon' => 'fingerprint', 'label' => 'Tap absensi', 'value' => $s['tap_absensi'], 'valueId' => 'g-tap', 'meta' => 'Δ '.(($s['tap_delta'] ?? 0) >= 0 ? '+' : '').($s['tap_delta'] ?? 0).' vs sebelumnya', 'metaId' => 'g-tap-d', 'metaClass' => ($s['tap_delta'] ?? 0) >= 0 ? 'up' : 'down'])
                @include('partials.stat-card', ['tone' => 'violet', 'icon' => 'lock', 'label' => 'Akses loker', 'value' => $s['akses_loker'], 'valueId' => 'g-lok', 'meta' => 'Δ '.(($s['loker_delta'] ?? 0) >= 0 ? '+' : '').($s['loker_delta'] ?? 0).' vs sebelumnya', 'metaId' => 'g-lok-d', 'metaClass' => ($s['loker_delta'] ?? 0) >= 0 ? 'up' : 'down'])
            </div>

            <div class="dash-chart-grid">
                <div class="chart-panel animate-in dosen-chart-card">
                    <div class="chart-panel__head">
                        <h3>Grafik kehadiran</h3>
                        <span class="chart-panel__sub" id="line-subtitle">{{ $a['line']['subtitle'] ?? '' }}</span>
                    </div>
                    <div class="chart-panel__body">
                        <canvas id="chartLine"></canvas>
                    </div>
                </div>
                <motion class="chart-panel animate-in dosen-chart-card">
                    <div class="chart-panel__head">
                        <h3>Distribusi rentang</h3>
                        <span class="chart-panel__sub">Mahasiswa unik hadir vs belum</span>
                    </div>
                    <div class="chart-panel__body chart-panel__body--donut">
                        <canvas id="chartDonut"></canvas>
                    </div>
                </div>
            </motion>

""".replace('<motion class="chart-panel', '<div class="chart-panel').replace('            </motion>\n', '            </div>\n')

print('idx', idx, 'end', end)
t = t[:idx] + new_block + t[end:]
# strip old dosen-gcards css
import re
t = re.sub(r'        \.dosen-gcards \{[^}]+\}[^@]*?        \.dosen-period-pill \{[^}]+\}\n', '        .dosen-period-pill { margin-bottom: 8px; }\n', t, count=1)
p.write_text(t, encoding='utf-8')
print('ok')
