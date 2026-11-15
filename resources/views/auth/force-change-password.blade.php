<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Amankan Akun - SmartBrail</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:linear-gradient(160deg,#f0fdfa 0%,#f8fafc 45%,#eef2ff 100%);color:#0f172a;min-height:100vh}
        .act-shell{max-width:560px;margin:0 auto;padding:28px 16px 40px}
        .act-brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
        .act-brand img{height:34px;width:auto}
        .act-brand span{font-size:12px;font-weight:700;color:#0f766e;letter-spacing:.06em;text-transform:uppercase}
        .act-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;box-shadow:0 12px 40px rgba(15,23,42,.08)}
        h1{font-size:22px;margin:0 0 6px}
        .act-sub{margin:0 0 16px;color:#475569;font-size:14px;line-height:1.55}
        label{display:block;font-size:12.5px;font-weight:700;color:#334155;margin:12px 0 6px}
        input[type=password]{width:100%;padding:12px 13px;border:1px solid #cbd5e1;border-radius:11px;font-size:15px;background:#fff;color:#0f172a}
        input:focus{outline:2px solid #14b8a6;outline-offset:1px;border-color:#14b8a6}
        .act-hint{font-size:12px;color:#64748b;margin-top:5px;line-height:1.45}
        .act-btn{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:46px;margin-top:16px;border:0;border-radius:12px;background:#0f766e;color:#fff;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none}
        .act-alert{padding:11px 13px;border-radius:11px;font-size:13.5px;line-height:1.5;margin-bottom:14px}
        .act-alert--err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .act-alert--ok{background:#f0fdfa;border:1px solid #99f6e4;color:#115e59}
        .act-alert--info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a}
        .act-field-err{color:#b91c1c;font-size:12.5px;margin-top:5px}
        .act-who{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;margin-bottom:4px}
        .act-who strong{display:block}
        .act-who small{color:#64748b}
        .act-foot{margin-top:16px;font-size:12px;color:#64748b;text-align:center;line-height:1.5}
        .act-list{margin:10px 0 0;padding-left:18px;font-size:14px;color:#334155;line-height:1.65}
        .act-logout{display:block;text-align:center;margin-top:12px;font-size:12.5px;color:#94a3b8;text-decoration:none}
        .act-logout:hover{color:#64748b}
    </style>
</head>
<body>
<main class="act-shell">
    <div class="act-brand">
        <img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/aiot-simalas-logo-lockup-s.png')) }}" alt="SmartBrail">
        <span>Amankan Akun</span>
    </div>

    <div class="act-card">
        @if(session('success'))<div class="act-alert act-alert--ok">{{ session('success') }}</div>@endif
        @if(session('info'))<div class="act-alert act-alert--info">{{ session('info') }}</div>@endif
        @if($errors->any())<div class="act-alert act-alert--err">Periksa kembali isian Anda — ada yang belum sesuai.</div>@endif

        <div class="act-who">
            <div>
                <strong>{{ $user->nama_lengkap }}</strong>
                <small>{{ strtoupper($user->role) }} &middot; {{ $user->nitk_nidn }}</small>
            </div>
        </div>

        <h1>Buat password pribadi</h1>
        <p class="act-sub">Akun Anda dibuat lewat import dan saat ini memakai <strong>password sementara</strong>. Demi keamanan, buat password pribadi baru terlebih dahulu — setelah itu Anda masuk ke halaman utama.</p>

        <form method="POST" action="{{ route('account.password.update') }}">
            @csrf
            <label for="pw">Password baru</label>
            <input id="pw" type="password" name="password" required maxlength="128" autocomplete="new-password" autofocus>
            @error('password')<div class="act-field-err">{{ $message }}</div>@enderror
            <div class="act-hint">Minimal 8 karakter; kombinasikan huruf besar, huruf kecil, dan angka.</div>

            <label for="pw2">Ulangi password baru</label>
            <input id="pw2" type="password" name="password_confirmation" required maxlength="128" autocomplete="new-password">

            <button type="submit" class="act-btn">Simpan &amp; lanjutkan</button>
        </form>

        <a href="{{ $dashboard ?? '/dashboard' }}" class="act-logout">Nanti saja — lanjut ke halaman utama</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="act-logout" style="border:0;background:none;cursor:pointer;width:100%">Bukan akun Anda? Keluar</button>
        </form>

        <ul class="act-list">
            <li>Anda tetap bisa memakai semua fitur; pengingat kuning akan tampil di akun sampai password diganti.</li>
            <li>Password sementara tidak berlaku lagi setelah Anda menggantinya.</li>
            <li>Kendala login atau akses ruangan? Laporkan ke <strong>laboran</strong> atau <strong>dosen</strong> Anda.</li>
        </ul>
    </div>

    <p class="act-foot">SmartBrail &middot; Laboratorium Terpadu</p>
</main>
</body>
</html>
