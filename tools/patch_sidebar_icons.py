from pathlib import Path
import re

MAP = {
    '🏠': 'layout-dashboard',
    '📡': 'radio',
    '🤖': 'cpu',
    '🔐': 'lock',
    '✅': 'clipboard-check',
    '🚫': 'circle-x',
    '📝': 'file-text',
    '📅': 'calendar',
    '👆': 'fingerprint',
    '📈': 'bar-chart-3',
    '📋': 'scroll-text',
    '🗝️': 'key-round',
    '🔔': 'bell',
    '💬': 'message-square',
    '⚙️': 'settings',
    '👥': 'users',
    '📁': 'folder-kanban',
    '🛡️': 'shield',
    '🎓': 'graduation-cap',
    '⚠️': 'alert-triangle',
    '📢': 'megaphone',
    '⚡': 'zap',
    '👤': 'user',
}

def patch_file(path: Path):
    t = path.read_text(encoding='utf-8')
    for emoji, name in MAP.items():
        t = t.replace(
            f'<span class="sidebar-link-icon">{emoji}</span>',
            f'<span class="sidebar-link-icon"><i data-lucide="{name}"></i></span>',
        )
    path.write_text(t, encoding='utf-8')
    print('patched', path.name)

for f in [
    Path(r'c:\xampp\htdocs\Simalas\resources\views\admin\_sidebar.blade.php'),
    Path(r'c:\xampp\htdocs\Simalas\resources\views\dosen\_sidebar.blade.php'),
]:
    patch_file(f)
