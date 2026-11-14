<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mahasiswa — Gunakan Aplikasi Mobile</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 45%, #0f766e 100%);
            color: #f8fafc;
            padding: 24px;
        }
        .card {
            max-width: 480px;
            width: 100%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 32px 28px;
            backdrop-filter: blur(12px);
            text-align: center;
        }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin-bottom: 10px; }
        p { font-size: 14px; line-height: 1.6; color: #cbd5e1; margin-bottom: 20px; }
        .actions { display: flex; flex-direction: column; gap: 10px; }
        a, button {
            display: block;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .primary { background: linear-gradient(135deg,#2dd4bf,#2d5a8e); color: #fff; }
        .ghost { background: transparent; color: #94a3b8; border: 1px solid rgba(255,255,255,0.2); }
        .info { background: rgba(45,212,191,0.15); color: #99f6e4; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">📱</div>
        <h1>Gunakan Aplikasi Mobile SIMALAS</h1>
        @if(session('info'))
            <div class="info">{{ session('info') }}</div>
        @endif
        <p>
            Website SIMALAS hanya untuk <strong>admin laboran</strong> dan <strong>dosen</strong>.
            Sebagai mahasiswa, gunakan <strong>aplikasi mobile SIMALAS</strong> untuk absensi, pengumuman, dan profil.
        </p>
        <div class="actions">
            <a href="{{ route('download') }}" class="primary" style="text-align:center;">Unduh Aplikasi Mobile</a>
            <a href="{{ route('beranda') }}" class="ghost">Kembali ke Beranda</a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="primary" style="width:100%;">Keluar dari akun</button>
                </form>
            @endauth
        </div>
    </div>
</body>
</html>
