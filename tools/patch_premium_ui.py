"""Patch admin/dosen views for premium filter toolbar and stats."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

# --- Rekap filter ---
rekap = ROOT / "resources/views/absensi/rekap.blade.php"
t = rekap.read_text(encoding="utf-8")
old_form_start = 'class="rekap-filter-form">'
if old_form_start in t and 'filter-toolbar' not in t.split(old_form_start)[1][:80]:
    t = t.replace('class="rekap-filter-form"', 'class="rekap-filter-form filter-toolbar"', 1)

# Remove inline styles on inputs in rekap filter block
import re
form_start = t.find('<form method="GET" action="{{ route($panel[\'route_rekap\']) }}"')
form_end = t.find('</form>', form_start)
if form_start > 0 and form_end > 0:
    block = t[form_start:form_end]
    block_new = re.sub(r'\s+style="[^"]*"', '', block)
    block_new = block_new.replace(
        """                <motion class="rekap-filter-actions">
                    <button type="submit" class="btn-absensi primary">Terapkan</button>
                    @include('absensi._export_buttons', ['panel' => $panel])
                </motion>""",
        """                <button type="submit" class="btn-premium btn-premium--primary">
                    @include('components.icon', ['name' => 'search'])<span>Terapkan</span>
                </button>
                @include('absensi._export_buttons', ['panel' => $panel])""",
    )
    block_new = block_new.replace('<div class="rekap-filter-actions">', '').replace(
        """                    <button type="submit" class="btn-absensi primary">Terapkan</button>
                    @include('absensi._export_buttons', ['panel' => $panel])
                </div>""",
        """                <button type="submit" class="btn-premium btn-premium--primary">
                    @include('components.icon', ['name' => 'search'])<span>Terapkan</span>
                </button>
                @include('absensi._export_buttons', ['panel' => $panel])""",
    )
    t = t[:form_start] + block_new + t[form_end:]
    rekap.write_text(t, encoding="utf-8")
    print("patched rekap filter")

# --- Monitoring stats -> stat-cards ---
mon = ROOT / "resources/views/admin/monitoring.blade.php"
mt = mon.read_text(encoding="utf-8")
if 'monitor-stats' in mt and 'partials.stat-card' not in mt:
    stats_block = """    <motion class="stat-cards">
        @include('partials.stat-card', [
            'tone' => 'teal',
            'icon' => 'wifi',
            'label' => 'Mesin Online',
            'value' => number_format($metrics['machine_online']),
            'meta' => 'Dari ' . number_format($metrics['machine_total']) . ' mesin terdaftar',
        ])
        @include('partials.stat-card', [
            'tone' => 'amber',
            'icon' => 'wifi-off',
            'label' => 'Mesin Offline',
            'value' => number_format($metrics['machine_offline']),
            'meta' => 'Aktif tapi belum heartbeat',
        ])
        @include('partials.stat-card', [
            'tone' => 'blue',
            'icon' => 'fingerprint',
            'label' => 'Tap Absensi Hari Ini',
            'value' => number_format($metrics['absensi_today']),
            'meta' => 'Masuk ke log absensi',
        ])
        @include('partials.stat-card', [
            'tone' => 'violet',
            'icon' => 'lock-keyhole',
            'label' => 'Akses Loker Hari Ini',
            'value' => number_format($metrics['loker_today']),
            'meta' => 'Masuk ke log loker',
        ])
    </motion>"""
    stats_block = stats_block.replace("<motion", "<div").replace("</motion>", "</motion>")
    stats_block = stats_block.replace("</motion>", "</div>")
    start = mt.find('    <div class="monitor-stats">')
    end = mt.find('    <div class="monitor-grid">', start)
    if start > 0 and end > 0:
        mt = mt[:start] + stats_block + "\n\n" + mt[end:]
        # Activity icons lucide
        mt = mt.replace(
            """<div class="activity-icon {{ strtolower($activity['type']) }}">{{ $activity['type'] === 'Absensi' ? 'A' : 'L' }}</div>""",
            """<div class="activity-icon {{ strtolower($activity['type']) }}">@include('components.icon', ['name' => $activity['type'] === 'Absensi' ? 'fingerprint' : 'lock-keyhole'])</div>""",
        )
        mt = mt.replace(
            'class="monitor-hero-link">Kelola Mesin IoT</a>',
            'class="monitor-hero-link btn-premium">@include(\'components.icon\', [\'name\' => \'cpu\'])<span>Kelola Mesin IoT</span></a>',
        )
        mon.write_text(mt, encoding="utf-8")
        print("patched monitoring")

# --- Lockers stats ---
lock = ROOT / "resources/views/admin/lockers.blade.php"
lt = lock.read_text(encoding="utf-8")
if 'stats-locker-row' in lt and "partials.stat-card" not in lt:
    new_stats = """    <div class="stat-cards">
        @include('partials.stat-card', ['tone' => 'blue', 'icon' => 'key-round', 'label' => 'Total loker', 'value' => $lockerStats['total_loker'], 'meta' => 'Pintu pada mesin yang dipilih'])
        @include('partials.stat-card', ['tone' => 'teal', 'icon' => 'circle-check', 'label' => 'Siap pakai', 'value' => $lockerStats['siap_pakai'], 'meta' => 'Mode siap pakai & mesin operasional'])
        @include('partials.stat-card', ['tone' => 'amber', 'icon' => 'calendar', 'label' => 'Booking', 'value' => $lockerStats['booking'], 'meta' => 'Dicadangkan untuk tim'])
        @include('partials.stat-card', ['tone' => 'rose', 'icon' => 'wrench', 'label' => 'Perawatan', 'value' => $lockerStats['maintenance'], 'meta' => 'Slot perawatan atau mesin off'])
    </div>"""
    start = lt.find('    <div class="stats-locker-row">')
    end = lt.find('    <div class="locker-board-wrap">', start)
    if start > 0 and end > 0:
        lt = lt[:start] + new_stats + "\n\n" + lt[end:]
        lock.write_text(lt, encoding="utf-8")
        print("patched lockers stats")

# --- Batch: filter forms in admin ---
FILTER_PATCHES = [
    (
        ROOT / "resources/views/admin/mesin.blade.php",
        'style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:center;"',
        'class="filter-toolbar filter-toolbar-grid"',
    ),
    (
        ROOT / "resources/views/admin/mesin.blade.php",
        """                <div style="display:flex;gap:8px;">
                    <button type="submit" class="topbar-btn" title="Filter">🔎</button>
                    <a href="{{ route('admin.mesin.index') }}" class="topbar-btn" title="Reset" style="text-decoration:none;">↺</a>
                </div>""",
        """                @include('partials.filter-actions', ['resetUrl' => route('admin.mesin.index'), 'submitLabel' => 'Filter'])""",
    ),
]

for path, old, new in FILTER_PATCHES:
    if not path.exists():
        continue
    content = path.read_text(encoding="utf-8")
    if old in content:
        path.write_text(content.replace(old, new, 1), encoding="utf-8")
        print("patched", path.name)

# Generic emoji filter buttons in admin folder
emoji_filter = '<button type="submit" class="topbar-btn" title="Filter">🔎</button>'
lucide_filter = """<button type="submit" class="btn-premium btn-premium--primary" title="Filter">@include('components.icon', ['name' => 'search'])</button>"""
for blade in (ROOT / "resources/views/admin").glob("*.blade.php"):
    c = blade.read_text(encoding="utf-8")
    if emoji_filter in c:
        c = c.replace(emoji_filter, lucide_filter)
        blade.write_text(c, encoding="utf-8")
        print("emoji filter ->", blade.name)

reset_emoji = '<a href="{{ route(\'admin.mesin.index\') }}" class="topbar-btn" title="Reset" style="text-decoration:none;">↺</a>'
# skip - mesin already has filter-actions

print("done")
