"""Add filter-toolbar to GET filter forms and strip inline input styles."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DIRS = [
    ROOT / "resources/views/admin",
    ROOT / "resources/views/dosen",
    ROOT / "resources/views/absensi",
]

for d in DIRS:
    if not d.exists():
        continue
    for path in d.rglob("*.blade.php"):
        t = path.read_text(encoding="utf-8")
        orig = t

        # GET filter forms with grid layout -> filter-toolbar
        t = re.sub(
            r'(<form\s+method="GET"[^>]*?)\s+style="display:\s*grid[^"]*"',
            lambda m: m.group(1) + ' class="filter-toolbar filter-toolbar-grid"' if 'filter-toolbar' not in m.group(0) else m.group(0),
            t,
            flags=re.I,
        )
        # If form has grid style but no class yet
        t = re.sub(
            r'<form(\s+method="GET"[^>]*?)\s+style="display:\s*grid[^"]*"',
            r'<form\1 class="filter-toolbar filter-toolbar-grid"',
            t,
            flags=re.I,
        )

        # Fingerprint / attendance filter emoji
        t = t.replace('<span>🔎</span> Filter', '@include(\'components.icon\', [\'name\' => \'search\'])<span>Filter</span>')
        t = t.replace('<span>🔎</span> Terapkan Filter', '@include(\'components.icon\', [\'name\' => \'search\'])<span>Terapkan</span>')
        t = t.replace('🔎 Terapkan', '@include(\'components.icon\', [\'name\' => \'search\']) Terapkan')
        t = t.replace('<span>↺</span> Reset', '@include(\'components.icon\', [\'name\' => \'rotate-ccw\'])<span>Reset</span>')

        # Strip inline styles on filter inputs inside card-body forms (conservative)
        def strip_input_styles(s):
            return re.sub(
                r'(<(?:input|select)\b[^>]*?)\s+style="(?:padding|border|width|background)[^"]*"',
                r'\1',
                s,
                flags=re.I,
            )

        if 'filter-toolbar' in t or 'method="GET"' in t:
            t = strip_input_styles(t)

        if t != orig:
            path.write_text(t, encoding="utf-8")
            print("updated", path.relative_to(ROOT))

# Mesin tambah form - add class to create form row
mesin = ROOT / "resources/views/admin/mesin.blade.php"
mt = mesin.read_text(encoding="utf-8")
mt = mt.replace('➕ Tambah Mesin', '@include(\'components.icon\', [\'name\' => \'plus\']) Tambah Mesin')
mt = mt.replace(
    'class="btn-logout-top" style="color:#fff;background:linear-gradient(135deg,#2d5a8e,#4a90d9);border:none;height:42px;padding:0 20px;border-radius:8px;font-weight:600;cursor:pointer;"',
    'class="btn-premium btn-premium--blue"',
)
mesin.write_text(mt, encoding="utf-8")
print("mesin buttons")

# Info strips class
for name in ["mesin.blade.php", "lockers.blade.php"]:
    p = ROOT / "resources/views/admin" / name
    c = p.read_text(encoding="utf-8")
    c = c.replace(
        '<motion class="card animate-in" style="margin-bottom:16px;border-left:4px solid #2563eb;">',
        '<div class="panel-info-strip animate-in">',
    )
    c = c.replace('<motion class="card animate-in" style="margin-bottom:16px;border-left:4px solid #2563eb;">', '<div class="panel-info-strip animate-in">')
    if 'border-left:4px solid #2563eb' in c and 'panel-info-strip' not in c:
        c = re.sub(
            r'<motion class="card animate-in" style="margin-bottom:16px;border-left:4px solid #2563eb;">\s*<motion class="card-body" style="font-size:13px[^"]*">',
            '<div class="panel-info-strip animate-in">',
            c,
            count=1,
        )
    p.write_text(c, encoding="utf-8")

print("done")
