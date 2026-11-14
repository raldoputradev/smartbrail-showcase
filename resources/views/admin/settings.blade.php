@extends('layouts.dashboard')

@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan Sistem')
@section('page-subtitle', 'Libur, program studi, dan pendaftaran mandiri')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @php
        $libur = $libur ?? collect();
        $prodi = $prodi ?? collect();
    @endphp

    <div class="sys-toolbar animate-in">
        <div class="sys-toolbar__left">
            <span class="sys-live"><span class="sys-live__dot"></span>SISTEM</span>
            <div>
                <strong>Pengaturan</strong>
                <small>Kalender libur · program studi · pendaftaran mandiri</small>
            </div>
        </div>
        <div class="sys-toolbar__right">
            <a href="{{ route('admin.absensi.settings') }}" class="sys-btn sys-btn--ghost">Atur Absensi</a>
        </div>
    </div>

    @if(session('success'))
        <div class="sys-flash sys-flash--ok animate-in">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="sys-flash sys-flash--info animate-in">{{ session('info') }}</div>
    @endif

    <div class="sys-split animate-in">
        <section class="sys-card">
            <div class="sys-card__head">
                <div>
                    <h3>Kalender libur</h3>
                    <p>Tanggal libur absensi</p>
                </div>
                <button type="button" class="sys-help-q" onclick="openSysHelp('libur')" title="Panduan" aria-label="Panduan libur">?</button>
            </div>
            <form method="POST" action="{{ route('admin.absensi.libur') }}" class="sys-inline-form">
                @csrf
                <input type="date" name="tanggal" required>
                <input type="text" name="keterangan" placeholder="Keterangan">
                <button type="submit" class="sys-btn sys-btn--primary">Tambah</button>
            </form>
            <ul class="sys-list">
                @forelse($libur as $l)
                    <li>
                        <span><strong>{{ $l->tanggal->format('d/m/Y') }}</strong> — {{ $l->keterangan }}</span>
                        <form method="POST" action="{{ route('admin.absensi.libur.destroy', $l) }}" onsubmit="return confirm('Hapus tanggal libur?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="sys-list__del">Hapus</button>
                        </form>
                    </li>
                @empty
                    <li class="sys-list__empty">Belum ada tanggal libur</li>
                @endforelse
            </ul>
        </section>

        <section class="sys-card">
            <div class="sys-card__head">
                <div>
                    <h3>Program studi</h3>
                    <p>Master data prodi mahasiswa</p>
                </div>
                <button type="button" class="sys-help-q" onclick="openSysHelp('prodi')" title="Panduan" aria-label="Panduan prodi">?</button>
            </div>
            <form method="POST" action="{{ route('admin.absensi.prodi') }}" class="sys-inline-form">
                @csrf
                <input type="text" name="kode" placeholder="Kode" required maxlength="20">
                <input type="text" name="nama" placeholder="Nama prodi" required>
                <button type="submit" class="sys-btn sys-btn--primary">Tambah</button>
            </form>
            <ul class="sys-list">
                @forelse($prodi as $p)
                    <li>
                        <span>{{ $p->nama }} <small>({{ $p->kode }})</small></span>
                    </li>
                @empty
                    <li class="sys-list__empty">Belum ada prodi</li>
                @endforelse
            </ul>
        </section>
    </div>

    @php
        $registrasi = $registrasi ?? ['dosen' => true, 'mahasiswa' => true];
        $canEditRegistrasi = \App\Support\PermissionRegistry::canCurrent('settings', 'update');
    @endphp
    <section class="sys-card animate-in" id="registrasi-mandiri">
        <div class="sys-card__head">
            <div>
                <h3>Pendaftaran mandiri</h3>
                <p>Terpisah dari jadwal absensi. Login akun aktif dan aktivasi akun hasil import tetap berjalan saat pendaftaran ditutup.</p>
            </div>
            <button type="button" class="sys-help-q" onclick="openSysHelp('registrasi')" title="Panduan" aria-label="Panduan pendaftaran">?</button>
        </div>
        <form method="POST" action="{{ route('admin.settings.registration') }}" class="sys-loker-form">
            @csrf
            <div class="sys-loker-top">
                <label class="sys-check">
                    <input type="checkbox" name="dosen" value="1" @checked($registrasi['dosen']) @disabled(!$canEditRegistrasi)>
                    <span>
                        <strong>Dosen (web) — {{ $registrasi['dosen'] ? 'dibuka' : 'ditutup' }}</strong>
                        <small>Form Daftar di halaman masuk. Saat dibuka tetap perlu OTP + persetujuan admin.</small>
                    </span>
                </label>
                <label class="sys-check">
                    <input type="checkbox" name="mahasiswa" value="1" @checked($registrasi['mahasiswa']) @disabled(!$canEditRegistrasi)>
                    <span>
                        <strong>Mahasiswa (APK) — {{ $registrasi['mahasiswa'] ? 'dibuka' : 'ditutup' }}</strong>
                        <small>Tab Daftar di aplikasi. Saat ditutup APK menampilkan pesan dan server menolak pendaftaran.</small>
                    </span>
                </label>
            </div>
            @if($canEditRegistrasi)
                <div class="sys-form-actions">
                    <button type="submit" class="sys-btn sys-btn--primary">Simpan pendaftaran</button>
                </div>
            @endif
        </form>
    </section>

    <div class="iot-modal-backdrop" id="sysHelpModal" role="dialog" aria-modal="true">
        <div class="iot-modal sys-help-modal">
            <button type="button" class="iot-modal-close" onclick="closeSysHelp()" aria-label="Tutup">×</button>
            <div class="sys-help-badge">PANDUAN</div>
            <h3 id="sysHelpTitle">Panduan</h3>
            <p id="sysHelpDesc" class="sys-help-desc"></p>
            <div class="sys-help-code" id="sysHelpCode"></div>
            <div class="sys-form-actions">
                <button type="button" class="sys-btn sys-btn--primary" onclick="closeSysHelp()">Mengerti</button>
            </div>
        </div>
    </div>

    <style>
        .sys-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:6px;
            min-height:38px;padding:0 14px;border-radius:10px;border:1px solid var(--border-subtle);
            background:transparent;color:var(--text-dark);font-size:13px;font-weight:700;
            cursor:pointer;text-decoration:none;white-space:nowrap;
        }
        .sys-btn--primary{background:#0f766e;border-color:#0f766e;color:#fff}
        .sys-btn--ghost:hover{border-color:color-mix(in srgb,#0f766e 40%,var(--border-subtle))}
        .sys-help-q{
            display:inline-grid;place-items:center;width:18px;height:18px;
            border:1px solid color-mix(in srgb,#0f766e 45%,var(--border-subtle));
            border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;
            font-size:11px;font-weight:800;line-height:1;cursor:pointer;padding:0;
        }
        .sys-help-q:hover{background:#0f766e;color:#fff;border-color:#0f766e}

        .sys-toolbar{
            display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
            margin-bottom:16px;padding:14px 16px;border-radius:14px;
            border:1px solid var(--border-subtle);background:var(--bg-card);
        }
        .sys-toolbar__left{display:flex;align-items:center;gap:12px}
        .sys-toolbar__left strong{display:block;font-size:14px;color:var(--text-dark)}
        .sys-toolbar__left small{display:block;margin-top:2px;font-size:12px;color:var(--text-muted)}
        .sys-live{
            display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;
            background:rgba(15,118,110,.12);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;
        }
        .sys-live__dot{width:7px;height:7px;border-radius:999px;background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.2)}

        .sys-flash{margin-bottom:14px;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;color:var(--text-dark)}
        .sys-flash--ok{background:rgba(15,118,110,.12);border:1px solid rgba(15,118,110,.28)}
        .sys-flash--info{background:rgba(37,99,235,.1);border:1px solid rgba(37,99,235,.25)}

        .sys-split{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
        .sys-card{
            padding:16px;border-radius:16px;border:1px solid var(--border-subtle);background:var(--bg-card);margin-bottom:16px;
        }
        .sys-split .sys-card{margin-bottom:0}
        .sys-card__head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:14px}
        .sys-card__head h3{margin:0;font-size:16px;color:var(--text-dark)}
        .sys-card__head p{margin:4px 0 0;font-size:12.5px;color:var(--text-muted)}

        .sys-inline-form{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
        .sys-inline-form input{
            flex:1;min-width:110px;padding:9px 10px;border-radius:10px;border:1px solid var(--border-subtle);
            background:var(--bg-main);color:var(--text-dark);font-size:13px;
        }
        .sys-list{list-style:none;margin:0;padding:0;max-height:280px;overflow:auto}
        .sys-list li{
            display:flex;justify-content:space-between;align-items:center;gap:10px;
            padding:10px 0;border-bottom:1px solid var(--border-subtle);font-size:13px;color:var(--text-dark);
        }
        .sys-list li:last-child{border-bottom:0}
        .sys-list small{opacity:.7}
        .sys-list__del{border:0;background:none;color:#f87171;font-size:12px;font-weight:700;cursor:pointer;padding:0}
        .sys-list__empty{color:var(--text-muted)!important;justify-content:center}

        .sys-loker-form{display:grid;gap:14px}
        .sys-loker-top{display:grid;grid-template-columns:1.4fr 1fr;gap:12px}
        .sys-check{
            display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:12px;
            border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 30%,transparent);
        }
        .sys-check input{margin-top:3px}
        .sys-check strong{display:block;font-size:13px;color:var(--text-dark)}
        .sys-check small{display:block;margin-top:2px;font-size:12px;color:var(--text-muted)}
        .sys-check--inline{align-items:center;margin-bottom:12px;padding:10px 12px}
        .sys-field{display:grid;gap:6px}
        .sys-field span{font-size:11.5px;font-weight:700;color:var(--text-muted)}
        .sys-field input{
            width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-subtle);
            background:var(--bg-main);color:var(--text-dark);font-size:13px;
        }
        .sys-loker-panel{padding:14px;border-radius:14px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 28%,transparent)}
        .sys-time-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .sys-time-grid label{display:grid;gap:5px}
        .sys-time-grid span{font-size:11.5px;font-weight:700;color:var(--text-muted)}
        .sys-time-grid input{
            width:100%;padding:9px 10px;border-radius:10px;border:1px solid var(--border-subtle);
            background:var(--bg-main);color:var(--text-dark);font-size:13px;
        }
        .sys-form-actions{display:flex;justify-content:flex-end}

        .iot-modal-backdrop{
            position:fixed;inset:0;z-index:3000;display:none;align-items:center;justify-content:center;
            padding:18px;background:rgba(15,23,42,.72);backdrop-filter:blur(8px);
        }
        .iot-modal-backdrop.is-open{display:flex}
        .iot-modal{
            width:min(520px,100%);position:relative;border:1px solid rgba(148,163,184,.28);
            border-radius:18px;padding:22px;background:var(--bg-card);color:var(--text-dark);
            box-shadow:0 24px 70px rgba(15,23,42,.38);max-height:min(90vh,720px);overflow:auto;
        }
        .iot-modal-close{
            position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:999px;
            background:rgba(148,163,184,.14);color:var(--text-dark);font-size:20px;cursor:pointer;
        }
        .sys-help-badge{
            display:inline-flex;padding:5px 10px;border-radius:999px;margin-bottom:8px;
            background:rgba(15,118,110,.14);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;
        }
        .sys-help-modal h3{margin:0 0 8px;font-size:18px}
        .sys-help-desc{margin:0 0 12px;font-size:13px;line-height:1.6;color:var(--text-muted)}
        .sys-help-code{
            font-size:12.5px;line-height:1.55;padding:12px 14px;border-radius:12px;
            border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 40%,transparent);
            color:var(--text-dark);white-space:pre-wrap;margin-bottom:8px;
        }
        .sys-help-code:empty{display:none}

        @media (max-width:900px){
            .sys-split,.sys-loker-top,.sys-time-grid{grid-template-columns:1fr}
        }
    </style>
@endsection

@section('scripts')
<script>
    const SYS_HELP = {
        libur: {
            title: 'Kalender libur',
            desc: 'Tanggal yang ditandai libur tidak dihitung alfa, dan tap absensi di mesin ditolak untuk tanggal tersebut.',
            code: 'Contoh: libur nasional, cuti bersama lab, hari besar.\nDampak: status hari = libur · tanpa alfa.'
        },
        prodi: {
            title: 'Program studi',
            desc: 'Master data prodi yang dipakai saat daftar / edit mahasiswa. Kode sebaiknya singkat dan unik.',
            code: 'Contoh kode: MEKA, TRE, ROB\nNama: Teknik Mekatronika, …'
        },
        registrasi: {
            title: 'Pendaftaran mandiri',
            desc: 'Menutup pendaftaran hanya menolak pembuatan akun baru oleh pengguna sendiri. Akun dibuat admin lewat Tambah User atau Import Akun.',
            code: 'Tetap berjalan saat ditutup:\n- login akun yang sudah aktif\n- aktivasi akun hasil import (/aktivasi)\n- verifikasi OTP pendaftaran yang sudah terlanjur dibuat'
        }
    };

    function openSysHelp(kind) {
        const data = SYS_HELP[kind] || SYS_HELP.libur;
        const modal = document.getElementById('sysHelpModal');
        const title = document.getElementById('sysHelpTitle');
        const desc = document.getElementById('sysHelpDesc');
        const code = document.getElementById('sysHelpCode');
        if (!modal || !title || !desc || !code) return;
        title.textContent = data.title;
        desc.textContent = data.desc;
        code.textContent = data.code || '';
        modal.style.display = 'flex';
        modal.classList.add('is-open');
    }

    function closeSysHelp() {
        const modal = document.getElementById('sysHelpModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('sysHelpModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeSysHelp();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSysHelp();
        });
    });
</script>
@endsection
