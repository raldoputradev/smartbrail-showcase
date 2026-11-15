<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Password — SmartBrail</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --font:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;
            --card:#ffffff;--input-bg:#f1f5f9;--text:#0e1e3c;--text-muted:#64748b;--label:#334155;
            --radius:18px;--ri:12px;--tr:all .25s cubic-bezier(.4,0,.2,1);
            --acc1:#1d6ff2;--acc2:#38bdf8;--acc-shadow:rgba(29,111,242,.32);
        }
        body.role-dosen{--acc1:#7c3aed;--acc2:#a78bfa;--acc-shadow:rgba(124,58,237,.32)}
        body.role-mahasiswa{--acc1:#059669;--acc2:#34d399;--acc-shadow:rgba(5,150,105,.32)}
        html,body{height:100%;font-family:var(--font);color:var(--text)}
        body{min-height:100vh;margin:0}

        .rp{display:flex;min-height:100vh}
        /* ── Hero kiri ── */
        .rp-hero{
            position:relative;flex:0 0 46%;overflow:hidden;color:#fff;
            background:url('{{ asset('images/campus-polybatam.jpg') }}') center/cover no-repeat;
            display:flex;flex-direction:column;justify-content:space-between;padding:34px 44px 44px;
        }
        .rp-hero::before{content:'';position:absolute;inset:0;background:linear-gradient(160deg,rgba(12,31,71,.82) 0%,rgba(15,45,105,.68) 45%,rgba(10,21,38,.86) 100%)}
        .rp-hero>*{position:relative;z-index:1}
        .rp-brand{display:flex;align-items:center;gap:11px}
        .rp-brand img{height:34px;width:auto;filter:drop-shadow(0 2px 8px rgba(0,0,0,.35))}
        .rp-brand-name{font-size:17px;font-weight:800;letter-spacing:.01em}
        .rp-brand-sub{font-size:11px;font-weight:500;opacity:.75;letter-spacing:.06em;text-transform:uppercase}
        .rp-hero-bottom{max-width:520px}
        .rp-hero-badge{display:inline-flex;align-items:center;gap:7px;padding:6px 13px;border-radius:99px;font-size:11px;font-weight:700;letter-spacing:.12em;background:rgba(56,189,248,.16);border:1px solid rgba(125,211,252,.35);color:#bae6fd;margin-bottom:16px}
        .rp-hero-title{font-size:34px;font-weight:800;line-height:1.18;letter-spacing:-.02em;margin-bottom:10px}
        .rp-hero-sub{font-size:14.5px;font-weight:500;opacity:.82;margin-bottom:30px}
        .rp-feats{display:flex;gap:34px}
        .rp-feat{display:flex;flex-direction:column;align-items:flex-start;gap:8px;font-size:12.5px;font-weight:600;opacity:.9}
        .rp-feat i{width:19px;height:19px;color:#7dd3fc}

        /* ── Panel form kanan ── */
        .rp-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 20px;background:linear-gradient(160deg,#eef4fc 0%,#f8fbff 55%,#eaf1fb 100%)}
        .rp-card{width:100%;max-width:470px;background:var(--card);border-radius:22px;box-shadow:0 20px 55px rgba(15,45,105,.13),0 0 0 1px rgba(148,163,184,.14);padding:38px 38px 32px;text-align:center}
        .rp-lock{width:62px;height:62px;border-radius:18px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--acc1),var(--acc2));box-shadow:0 10px 24px var(--acc-shadow)}
        .rp-lock i{width:27px;height:27px;color:#fff}
        .rp-title{font-size:22px;font-weight:800;letter-spacing:-.02em;margin-bottom:7px}
        .rp-sub{font-size:13.5px;color:var(--text-muted);line-height:1.55;margin-bottom:24px}
        .rp-role{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:99px;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--acc1);background:color-mix(in srgb,var(--acc1) 9%,#fff);border:1px solid color-mix(in srgb,var(--acc1) 24%,#fff);margin-bottom:14px}
        .rp-role i{width:13px;height:13px}

        .fg{margin-bottom:15px;text-align:left}
        .fl{display:block;font-size:13px;font-weight:700;color:var(--label);margin-bottom:7px}
        .pf{position:relative}
        .fi{width:100%;padding:12.5px 44px 12.5px 14px;font-size:14px;font-family:var(--font);background:var(--input-bg);border:1.5px solid transparent;border-radius:var(--ri);color:var(--text);transition:var(--tr);outline:none}
        .fi::placeholder{color:#94a3b8;font-weight:400}
        .fi:focus{border-color:var(--acc2);background:#fff;box-shadow:0 0 0 3.5px var(--acc-shadow)}
        .pt{position:absolute;right:11px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#64748b;cursor:pointer;padding:5px;border-radius:7px;transition:var(--tr);display:flex}
        .pt:hover{color:#0f172a;background:rgba(148,163,184,.16)}
        .pt i{width:17px;height:17px}

        .segs{display:flex;gap:5px;margin-top:9px}
        .seg{flex:1;height:5px;border-radius:99px;background:#e2e8f0;transition:background .25s ease}
        .seg.on{background:#10b981}
        .meter-row{display:flex;align-items:center;justify-content:flex-end;gap:5px;margin-top:6px;font-size:12px;font-weight:700;color:#10b981;min-height:16px}
        .meter-row i{width:13px;height:13px}
        .meter-row.is-weak{color:#e11d48}.meter-row.is-weak i{color:#e11d48}
        .meter-row.is-mid{color:#d97706}

        .match{display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;margin-top:7px;min-height:17px}
        .match i{width:14px;height:14px}
        .match.ok{color:#059669}.match.bad{color:#e11d48}

        .rules{margin:16px 0 6px;padding:14px 16px;background:#f8fafc;border:1px solid #e7edf5;border-radius:12px;text-align:left}
        .rules-title{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:800;color:var(--label);margin-bottom:9px}
        .rules-title i{width:15px;height:15px;color:var(--acc1)}
        .rules li{list-style:none;display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-muted);margin:5.5px 0;transition:color .2s}
        .rules li i{width:14px;height:14px;color:#cbd5e1;flex-shrink:0}
        .rules li.is-ok{color:#065f46}
        .rules li.is-ok i{color:#10b981}

        .ferr{margin-top:6px;font-size:12px;color:#e11d48;font-weight:600;text-align:left}
        .alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;text-align:left;line-height:1.5}
        .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}

        .btn{width:100%;padding:13.5px;font-size:14.5px;font-weight:800;font-family:var(--font);color:#fff;background:linear-gradient(135deg,var(--acc1),var(--acc2));border:none;border-radius:var(--ri);cursor:pointer;transition:var(--tr);box-shadow:0 5px 18px var(--acc-shadow);display:flex;align-items:center;justify-content:center;gap:9px;margin-top:12px;letter-spacing:.01em;text-decoration:none}
        .btn:hover{transform:translateY(-1px);box-shadow:0 9px 26px var(--acc-shadow)}
        .btn i{width:17px;height:17px}
        .btn-ghost{display:inline-flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:11px;font-size:13.5px;font-weight:700;font-family:var(--font);color:var(--acc1);background:transparent;border:none;cursor:pointer;text-decoration:none;margin-top:8px}
        .btn-ghost:hover{text-decoration:underline}

        .ic{width:66px;height:66px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
        .ic i{width:30px;height:30px}
        .ic-ok{background:#ecfdf5;color:#059669}
        .ic-err{background:#fef2f2;color:#e11d48}
        .foot{margin-top:22px;font-size:12px;color:#94a3b8}

        @media(max-width:900px){
            .rp{flex-direction:column}
            .rp-hero{flex:0 0 auto;min-height:270px;padding:22px 24px 28px}
            .rp-hero-title{font-size:24px}.rp-feats{gap:20px}.rp-hero-sub{margin-bottom:18px}
            .rp-card{padding:28px 22px 24px}
        }
    </style>
</head>
@php
    $roleClass = match($accountRole ?? null) {
        'dosen' => 'role-dosen',
        'mahasiswa' => 'role-mahasiswa',
        default => '',
    };
    $roleNoun = match($accountRole ?? null) {
        'admin', 'laboran' => 'Admin',
        'dosen' => 'Dosen',
        'mahasiswa' => 'Mahasiswa',
        default => null,
    };
    $roleIcon = match($accountRole ?? null) {
        'admin', 'laboran' => 'shield-check',
        'dosen' => 'graduation-cap',
        'mahasiswa' => 'user',
        default => 'user',
    };
@endphp
<body class="{{ $roleClass }}">
    <div class="rp">
        <aside class="rp-hero">
            <div class="rp-brand">
                <img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/smartbrail-logo.png')) }}" alt="SmartBrail">
                <div>
                    <div class="rp-brand-name">SmartBrail</div>
                    <div class="rp-brand-sub">Portal Administrasi</div>
                </div>
            </div>
            <div class="rp-hero-bottom">
                <span class="rp-hero-badge">SISTEM LABORATORIUM</span>
                <h1 class="rp-hero-title">Smart Laboratory<br>Management System</h1>
                <p class="rp-hero-sub">Politeknik Negeri Batam</p>
                <div class="rp-feats">
                    <span class="rp-feat"><i data-lucide="shield-check"></i>Aman</span>
                    <span class="rp-feat"><i data-lucide="zap"></i>Cepat</span>
                    <span class="rp-feat"><i data-lucide="layers"></i>Terintegrasi</span>
                </div>
            </div>
        </aside>

        <main class="rp-panel">
            <div class="rp-card">
                @if(!empty($done))
                    <div class="ic ic-ok"><i data-lucide="check"></i></div>
                    <h2 class="rp-title">Password Berhasil Diperbarui</h2>
                    <p class="rp-sub">Password akun Anda sudah diganti dan seluruh sesi lama dicabut. Tautan reset ini sudah tidak berlaku lagi. Silakan masuk dengan password baru.</p>
                    <a href="{{ route('login') }}" class="btn"><i data-lucide="log-in"></i><span>Kembali ke Halaman Masuk</span></a>
                @elseif(!($tokenValid ?? false))
                    <div class="ic ic-err"><i data-lucide="alert-circle"></i></div>
                    <h2 class="rp-title">Tautan Tidak Berlaku</h2>
                    <p class="rp-sub">Tautan reset password ini tidak valid, sudah dipakai, atau sudah kedaluwarsa. Minta tautan baru dari halaman Lupa Password.</p>
                    <a href="{{ route('login', ['panel' => 'forgot']) }}" class="btn"><i data-lucide="mail"></i><span>Minta Tautan Baru</span></a>
                    <a href="{{ route('login') }}" class="btn-ghost"><span>Kembali ke Login</span></a>
                @else
                    <div class="rp-lock"><i data-lucide="lock"></i></div>
                    @if($roleLabel)
                        <span class="rp-role"><i data-lucide="{{ $roleIcon }}"></i>Akun {{ $roleLabel }}</span>
                    @endif
                    <h2 class="rp-title">Ubah Password{{ $roleNoun ? ' ' . $roleNoun : '' }}</h2>
                    <p class="rp-sub">Perbarui password akun{{ $accountName ? ' ' . $accountName : '' }} untuk menjaga keamanan sistem. Tautan ini hanya bisa dipakai satu kali.</p>

                    @error('token')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror

                    <form method="POST" action="{{ route('password.reset.update') }}" id="resetForm">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="fg">
                            <label class="fl" for="password">Password Baru</label>
                            <div class="pf">
                                <input type="password" name="password" id="password" class="fi" placeholder="Masukkan password baru" required autocomplete="new-password" maxlength="128">
                                <button type="button" class="pt" data-toggle-password="password" aria-label="Tampilkan password"><i data-lucide="eye"></i></button>
                            </div>
                            <div class="segs" id="pwdSegs" aria-hidden="true">
                                <span class="seg"></span><span class="seg"></span><span class="seg"></span><span class="seg"></span>
                            </div>
                            <div class="meter-row" id="pwdMeterLabel"></div>
                            @error('password')
                                <p class="ferr">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="fg">
                            <label class="fl" for="password_confirmation">Konfirmasi Password</label>
                            <div class="pf">
                                <input type="password" name="password_confirmation" id="password_confirmation" class="fi" placeholder="Ulangi password baru" required autocomplete="new-password" maxlength="128">
                                <button type="button" class="pt" data-toggle-password="password_confirmation" aria-label="Tampilkan konfirmasi password"><i data-lucide="eye"></i></button>
                            </div>
                            <div class="match" id="pwdMatchHint"></div>
                        </div>
                        <ul class="rules" aria-label="Ketentuan password">
                            <span class="rules-title"><i data-lucide="shield"></i>Ketentuan Password</span>
                            <li data-rule="len"><i data-lucide="check-circle-2"></i>Minimal 8 karakter</li>
                            <li data-rule="upper"><i data-lucide="check-circle-2"></i>Mengandung huruf besar (A–Z)</li>
                            <li data-rule="lower"><i data-lucide="check-circle-2"></i>Mengandung huruf kecil (a–z)</li>
                            <li data-rule="num"><i data-lucide="check-circle-2"></i>Mengandung angka (0–9)</li>
                            <li data-rule="sym"><i data-lucide="check-circle-2"></i>Mengandung simbol (mis. !@#$%^&*)</li>
                        </ul>
                        <button type="submit" class="btn"><i data-lucide="save"></i><span>Simpan Perubahan</span></button>
                    </form>
                    <a href="{{ route('login') }}" class="btn-ghost"><span>Batalkan & kembali ke login</span></a>
                @endif
                <p class="foot">Copyright © SIMALAS {{ date('Y') }}</p>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) lucide.createIcons();

            document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = document.getElementById(this.getAttribute('data-toggle-password'));
                    if (!input) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                    // Swap ikon eye ↔ eye-off (lucide sudah merender <i> menjadi <svg>)
                    this.innerHTML = '<i data-lucide="' + (show ? 'eye-off' : 'eye') + '"></i>';
                    if (window.lucide) lucide.createIcons();
                });
            });

            const pwd = document.getElementById('password');
            const conf = document.getElementById('password_confirmation');
            const label = document.getElementById('pwdMeterLabel');
            const match = document.getElementById('pwdMatchHint');
            const segs = document.querySelectorAll('#pwdSegs .seg');
            const rules = {
                len: v => v.length >= 8,
                upper: v => /[A-Z]/.test(v),
                lower: v => /[a-z]/.test(v),
                num: v => /\d/.test(v),
                sym: v => /[^A-Za-z0-9]/.test(v),
            };

            function refresh() {
                const v = pwd ? pwd.value : '';
                let score = 0;
                for (const key in rules) {
                    const ok = rules[key](v);
                    const li = document.querySelector('[data-rule="' + key + '"]');
                    if (li) li.classList.toggle('is-ok', ok);
                    if (ok) score++;
                }
                const filled = v ? Math.max(1, Math.round(score / 5 * segs.length)) : 0;
                segs.forEach((s, i) => s.classList.toggle('on', i < filled));
                if (label) {
                    label.className = 'meter-row';
                    if (!v) {
                        label.textContent = '';
                    } else if (score <= 2) {
                        label.classList.add('is-weak');
                        label.innerHTML = '<i data-lucide="alert-circle"></i> Lemah';
                    } else if (score <= 4) {
                        label.classList.add('is-mid');
                        label.textContent = 'Sedang';
                    } else {
                        label.innerHTML = '<i data-lucide="check-circle-2"></i> Kuat';
                    }
                    if (window.lucide) lucide.createIcons();
                }
                if (match && conf) {
                    match.className = 'match';
                    if (!conf.value) {
                        match.textContent = '';
                    } else if (conf.value === v) {
                        match.classList.add('ok');
                        match.innerHTML = '<i data-lucide="check-circle-2"></i> Password sesuai';
                    } else {
                        match.classList.add('bad');
                        match.innerHTML = '<i data-lucide="x-circle"></i> Password belum sesuai';
                    }
                    if (window.lucide) lucide.createIcons();
                }
            }
            if (pwd) pwd.addEventListener('input', refresh);
            if (conf) conf.addEventListener('input', refresh);
            refresh();
        });
    </script>
</body>
</html>
