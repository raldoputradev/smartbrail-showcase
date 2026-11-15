from pathlib import Path

p = Path(__file__).resolve().parents[1] / "resources/views/absensi/rekap.blade.php"
t = p.read_text(encoding="utf-8")
marker_start = '    <div class="absensi-stat-grid">'
marker_end = '    <div class="card animate-in" style="margin-bottom:20px;">'

start = t.find(marker_start)
end = t.find(marker_end, start)
if start < 0 or end < 0:
    raise SystemExit(f"markers not found start={start} end={end}")

replacement = """    @if($panel['is_laboran'] ?? false)
        @include('partials.module-hint-rekap-laporan', ['variant' => 'rekap'])
    @endif

    @include('partials.absensi-summary-stats', ['summary' => $summary])

"""

t = t[:start] + replacement + t[end:]
t = t.replace(
    'style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;align-items:end;"',
    'class="rekap-filter-form"',
    1,
)
t = t.replace(
    """                <button type="submit" class="btn-absensi primary">Terapkan</button>
                @include('absensi._export_buttons', ['panel' => $panel])""",
    """                <div class="rekap-filter-actions">
                    <button type="submit" class="btn-absensi primary">Terapkan</button>
                    @include('absensi._export_buttons', ['panel' => $panel])
                </div>""",
    1,
)
p.write_text(t, encoding="utf-8")
print("patched", p)
