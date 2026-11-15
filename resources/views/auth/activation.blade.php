<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Aktivasi Akun - AIoT SIMALAS</title>
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
        .act-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:0 0 18px;padding:0;list-style:none}
        .act-steps li{font-size:11px;font-weight:700;color:#94a3b8;text-align:center;padding-top:8px;border-top:3px solid #e2e8f0;line-height:1.3}
        .act-steps li.is-done{color:#0f766e;border-color:#0f766e}
        .act-steps li.is-now{color:#0f172a;border-color:#14b8a6}
        label{display:block;font-size:12.5px;font-weight:700;color:#334155;margin:12px 0 6px}
        input[type=text],input[type=password],input[type=email],input[type=tel]{width:100%;padding:12px 13px;border:1px solid #cbd5e1;border-radius:11px;font-size:15px;background:#fff;color:#0f172a}
        input:focus{outline:2px solid #14b8a6;outline-offset:1px;border-color:#14b8a6}
        .act-hint{font-size:12px;color:#64748b;margin-top:5px;line-height:1.45}
        .act-btn{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:46px;margin-top:16px;border:0;border-radius:12px;background:#0f766e;color:#fff;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none}
        .act-btn--ghost{background:transparent;color:#0f766e;border:1px solid #99f6e4}
        .act-btn--link{background:none;border:0;color:#0f766e;font-weight:700;font-size:13px;cursor:pointer;padding:0;width:auto;min-height:0;margin:0}
        .act-alert{padding:11px 13px;border-radius:11px;font-size:13.5px;line-height:1.5;margin-bottom:14px}
        .act-alert--err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .act-alert--ok{background:#f0fdfa;border:1px solid #99f6e4;color:#115e59}
        .act-alert--info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a}
        .act-field-err{color:#b91c1c;font-size:12.5px;margin-top:5px}
        .act-divider{display:flex;align-items:center;gap:10px;margin:22px 0 4px;color:#94a3b8;font-size:12px;font-weight:700}
        .act-divider::before,.act-divider::after{content:"";flex:1;height:1px;background:#e2e8f0}
        details.act-alt summary{cursor:pointer;font-size:13.5px;font-weight:700;color:#0f766e;margin-top:6px}
        .act-who{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;margin-bottom:4px}
        .act-who strong{display:block}
        .act-who small{color:#64748b}
        .act-otp{letter-spacing:.4em;text-align:center;font-size:22px!important;font-weight:700}
        .act-foot{margin-top:16px;font-size:12px;color:#64748b;text-align:center;line-height:1.5}
        .act-list{margin:10px 0 0;padding-left:18px;font-size:14px;color:#334155;line-height:1.65}
        @media (max-width:420px){.act-card{padding:18px 15px}h1{font-size:20px}.act-steps li{font-size:10px}}
    </style>
</head>
<body>
@php
    $order = ['masuk' => 1, 'profil' => 2, 'otp' => 3, 'selesai' => 4];
    $now = $order[$step] ?? 1;
    $stepLabels = [1 => 'Kode & password', 2 => 'Email & HP', 3 => 'Verifikasi email', 4 => 'Selesai'];
@endphp
<main class="act-shell">
    <div class="act-brand">
        <img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/aiot-simalas-logo-lockup-s.png')) }}" alt="AIoT SIMALAS">
        <span>Aktivasi Akun</span>
    </div>

    <div class="act-card">
        <ol class="act-steps" aria-label="Tahapan aktivasi">
            @foreach($stepLabels as $i => $label)
                <li class="{{ $i < $now ? 'is-done' : ($i === $now ? 'is-now' : '') }}">{{ $i }}. {{ $label }}</li>
            @endforeach
        </ol>

        @if(session('success'))
            <div class="act-alert act-alert--ok" role="status">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="act-alert act-alert--info" role="status">{{ session('info') }}</div>
        @endif
        @error('activation')
            <div class="act-alert act-alert--err" role="alert">{{ $message }}</div>
        @enderror

        @if($step === 'masuk')
            <h1>Aktifkan akun SIMALAS</h1>
            <p class="act-sub">Akun Anda dibuat oleh admin laboran. Masukkan ID login dan kode aktivasi dari admin, lalu buat password Anda sendiri. Kode hanya berlaku sekali.</p>

            <form method="POST" action="{{ route('activation.start') }}" autocomplete="off" novalidate>
                @csrf
                <label for="act-id">ID login</label>
                <input id="act-id" type="text" name="nitk_nidn" value="{{ old('nitk_nidn', $identifier) }}" required maxlength="50" autocomplete="username" inputmode="text">
                <div class="act-hint">Mahasiswa: NIM. Dosen/laboran: NIDN/NITK yang dipakai untuk login.</div>
                @error('nitk_nidn')<div class="act-field-err">{{ $message }}</div>@enderror

                <label for="act-code">Kode aktivasi</label>
                <input id="act-code" type="text" name="kode_aktivasi" required maxlength="40" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX">
                @error('kode_aktivasi')<div class="act-field-err">{{ $message }}</div>@enderror

                <label for="act-pw">Password baru</label>
                <input id="act-pw" type="password" name="password" required maxlength="128" autocomplete="new-password">
                <div class="act-hint">Minimal 8 karakter dengan huruf besar, huruf kecil, angka, dan simbol.</div>
                @error('password')<div class="act-field-err">{{ $message }}</div>@enderror

                <label for="act-pw2">Ulangi password</label>
                <input id="act-pw2" type="password" name="password_confirmation" required maxlength="128" autocomplete="new-password">

                <button type="submit" class="act-btn">Mulai aktivasi</button>
            </form>

            <div class="act-divider">ATAU</div>
            <details class="act-alt" @if($errors->has('resume_password')) open @endif>
                <summary>Sudah membuat password? Lanjutkan aktivasi</summary>
                <form method="POST" action="{{ route('activation.resume') }}" novalidate>
                    @csrf
                    <label for="act-rid">ID login</label>
                    <input id="act-rid" type="text" name="nitk_nidn" value="{{ old('nitk_nidn', $identifier) }}" required maxlength="50" autocomplete="username">
                    <label for="act-rpw">Password yang sudah dibuat</label>
                    <input id="act-rpw" type="password" name="password" required maxlength="128" autocomplete="current-password">
                    @error('resume_password')<div class="act-field-err">{{ $message }}</div>@enderror
                    <button type="submit" class="act-btn act-btn--ghost">Lanjutkan aktivasi</button>
                </form>
            </details>
            <p class="act-foot">Akun sudah aktif? <a href="{{ route('login') }}">Masuk di halaman login</a> (mahasiswa, dosen, atau admin/laboran).</p>

        @elseif($step === 'profil')
            <h1>Lengkapi kontak Anda</h1>
            <p class="act-sub">Isi email dan nomor HP milik Anda sendiri. Kode verifikasi akan dikirim ke email ini. Nomor HP disimpan untuk kontak dan tidak diverifikasi otomatis.</p>
            <div class="act-who">
                <div><strong>{{ $user->nama_lengkap }}</strong><small>{{ ucfirst($user->role) }} · {{ $user->nitk_nidn }}</small></div>
                <form method="POST" action="{{ route('activation.leave') }}">@csrf<button class="act-btn--link" type="submit">Keluar</button></form>
            </div>
            <form method="POST" action="{{ route('activation.profile') }}" novalidate>
                @csrf
                <label for="act-email">Email</label>
                <input id="act-email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" inputmode="email">
                @error('email')<div class="act-field-err">{{ $message }}</div>@enderror

                <label for="act-hp">Nomor HP</label>
                <input id="act-hp" type="tel" name="no_hp" value="{{ old('no_hp', $user->no_hp) }}" required maxlength="25" autocomplete="tel" inputmode="tel" placeholder="081234567890">
                <div class="act-hint">9–15 digit, boleh diawali +. Spasi dan tanda hubung diabaikan.</div>
                @error('no_hp')<div class="act-field-err">{{ $message }}</div>@enderror

                <button type="submit" class="act-btn">Simpan &amp; kirim kode verifikasi</button>
            </form>

        @elseif($step === 'otp')
            <h1>Verifikasi email</h1>
            <p class="act-sub">Masukkan 6 digit kode yang dikirim ke <strong>{{ $user->email }}</strong>. Kode berlaku {{ \App\Support\AccountEmailVerification::OTP_TTL_MINUTES }} menit; jika kedaluwarsa, kirim ulang tanpa mengulang aktivasi.</p>
            <div class="act-who">
                <div><strong>{{ $user->nama_lengkap }}</strong><small>{{ ucfirst($user->role) }} · HP {{ $user->no_hp }}</small></div>
                <a class="act-btn--link" href="{{ route('activation.show', ['ubah' => 1]) }}">Ubah email/HP</a>
            </div>
            <form method="POST" action="{{ route('activation.otp') }}" novalidate>
                @csrf
                <label for="act-otp">Kode verifikasi</label>
                <input id="act-otp" class="act-otp" type="text" name="otp_code" required maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code">
                @error('otp_code')<div class="act-field-err">{{ $message }}</div>@enderror
                <button type="submit" class="act-btn">Verifikasi &amp; aktifkan akun</button>
            </form>
            <form method="POST" action="{{ route('activation.otp.resend') }}" style="margin-top:10px;">
                @csrf
                <button type="submit" class="act-btn act-btn--ghost">Kirim ulang kode</button>
            </form>
            <form method="POST" action="{{ route('activation.leave') }}" style="margin-top:12px;text-align:center;">
                @csrf<button class="act-btn--link" type="submit">Keluar dan lanjutkan nanti</button>
            </form>

        @else
            @php
                $role = $done['role'] ?? 'mahasiswa';
                $roleLabel = match ($role) {
                    'laboran' => 'Admin / Laboran',
                    'dosen' => 'Dosen',
                    default => 'Mahasiswa',
                };
            @endphp
            <h1>Akun aktif</h1>
            <p class="act-sub">Terima kasih, {{ $done['nama'] ?? '' }}. Aktivasi selesai dan akun Anda sudah aktif penuh.</p>
            <ul class="act-list">
                <li>Masuk di halaman login dengan <strong>{{ $role === 'mahasiswa' ? 'NIM' : 'ID login' }}</strong> dan <strong>password baru</strong> yang tadi Anda buat.</li>
                <li>Pilih peran <strong>{{ $roleLabel }}</strong> saat login (atau gunakan aplikasi <strong>SIMALAS</strong> di HP untuk mahasiswa).</li>
                <li>Kode aktivasi sudah tidak berlaku lagi.</li>
            </ul>
            <a href="{{ route('login', ['role' => in_array($role, ['mahasiswa', 'dosen', 'laboran'], true) ? $role : 'mahasiswa']) }}" class="act-btn">Ke halaman masuk</a>
        @endif
    </div>
    <p class="act-foot">Jangan bagikan kode aktivasi, password, atau kode verifikasi kepada siapa pun, termasuk admin.</p>
</main>
</body>
</html>
