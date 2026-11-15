from pathlib import Path
from PIL import Image

src = Path(
    r"C:\Users\aldo\.cursor\projects\c-xampp-htdocs-Simalas\assets"
    r"\c__Users_aldo_AppData_Roaming_Cursor_User_workspaceStorage_empty-window_images"
    r"\logo-aiot-simalas-f51aa0f7-e632-4c70-9e2e-7d30ffc17f27.png"
)
out = Path(r"C:\xampp\htdocs\Simalas\public\branding")
mobile = Path(r"C:\xampp\htdocs\Simalas\simalas_mobile\assets\branding")
mobile.mkdir(parents=True, exist_ok=True)

im = Image.open(src).convert("RGBA")
bbox = im.getbbox()
lockup = im.crop(bbox)
print("lockup trimmed", lockup.size)

w, h = lockup.size
mark = lockup.crop((0, 0, w, int(h * 0.62))).copy()
mb = mark.getbbox()
if mb:
    mark = mark.crop(mb)
print("mark cropped", mark.size)

for p in [
    out / "aiot-simalas-logo-lockup-s.png",
    out / "aiot-simalas-logo-lockup.png",
    out / "aiot-simalas-logo.png",
    mobile / "aiot-simalas-logo.png",
]:
    lockup.save(p, "PNG")
    print("saved", p)

for p in [
    out / "aiot-simalas-mark-s.png",
    out / "aiot-simalas-mark.png",
    out / "aiot-simalas-loading-s.png",
    out / "aiot-simalas-loading.png",
    mobile / "aiot-simalas-mark.png",
]:
    mark.save(p, "PNG")
    print("saved", p)
