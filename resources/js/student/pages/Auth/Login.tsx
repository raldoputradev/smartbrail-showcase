import { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    ArrowLeft, CalendarClock, ChevronDown, Eye, EyeOff, Fingerprint, FlaskConical,
    GraduationCap, Info, Loader2, Lock, LogIn, Mail, ShieldCheck, User, Users,
} from 'lucide-react';
import { asset } from '@/lib/asset';
import type { SharedProps } from '@/types';
import { cn } from '@/lib/utils';

type RoleKey = 'admin' | 'laboran' | 'dosen' | 'mahasiswa';

const ROLE_META: Record<RoleKey, {
    option: string; idLabel: string; ph: string; hint: string; info: string;
}> = {
    admin: {
        option: 'Admin',
        idLabel: 'Username / NITK',
        ph: 'Masukkan username atau NITK Anda',
        hint: 'Akses portal admin: manajemen akun, perangkat, kebijakan sistem, dan konfigurasi ruangan.',
        info: 'Gunakan username/NITK dan password akun admin Anda.',
    },
    laboran: {
        option: 'Laboran',
        idLabel: 'NITK',
        ph: 'Masukkan NITK Anda',
        hint: 'Akses portal laboran: persetujuan ruangan, jadwal petugas, fingerprint, dan laporan.',
        info: 'Gunakan NITK dan password akun laboran Anda.',
    },
    dosen: {
        option: 'Dosen',
        idLabel: 'NITK / NIDN',
        ph: 'Masukkan NITK/NIDN Anda',
        hint: 'Akses portal dosen: rekap absensi, proyek PBL, dan pelaporan.',
        info: 'Gunakan NITK/NIDN dan password akun SmartBrail Anda yang telah terdaftar di sistem.',
    },
    mahasiswa: {
        option: 'Mahasiswa',
        idLabel: 'NIM',
        ph: 'Masukkan NIM Anda',
        hint: 'Akses portal mahasiswa: absensi pribadi, tim PBL, dan notifikasi.',
        info: 'Gunakan NIM dan password akun SmartBrail Anda yang telah terdaftar di sistem.',
    },
};

const ROLE_ORDER: RoleKey[] = ['admin', 'laboran', 'dosen', 'mahasiswa'];

interface Props extends SharedProps {
    panel: 'forgot' | null;
    selectedRole: string;
    old: {
        nitk_nidn: string | null;
        role: string | null;
        remember: boolean;
        identifier: string | null;
    };
    csrf: string;
    recaptchaSiteKey: string | null;
}

const FEATURES = [
    { icon: Fingerprint, label: 'Absensi Biometrik' },
    { icon: CalendarClock, label: 'Pengajuan Ruangan' },
    { icon: Users, label: 'Tim PBL' },
    { icon: FlaskConical, label: 'Layanan Laboratorium' },
];

function initialRole(raw: string | null | undefined): RoleKey {
    if (raw && (ROLE_ORDER as string[]).includes(raw)) return raw as RoleKey;
    if (raw === 'admin_laboran') return 'admin';
    return 'mahasiswa';
}

export default function Login({ panel, selectedRole, old, csrf, recaptchaSiteKey }: Props) {
    const { errors, flash, branding } = usePage<Props>().props;
    const brandLogo = branding?.logo_url || asset('branding/smartbrail-logo.jpg');
    const [view, setView] = useState<'login' | 'forgot'>(panel === 'forgot' ? 'forgot' : 'login');
    const [role, setRole] = useState<RoleKey>(initialRole(old.role ?? selectedRole));
    const [showPw, setShowPw] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const meta = ROLE_META[role];

    const err = (key: string) => (errors as Record<string, string> | undefined)?.[key];
    const firstError = err('nitk_nidn') ?? err('role') ?? err('password');

    return (
        <div className="sb-auth">
            <Head title="Masuk — SmartBrail" />

            {/* Sisi kiri — foto kampus + overlay navy + gelombang */}
            <aside className="sb-auth-hero" aria-label="Tentang SmartBrail">
                <div className="sb-auth-hero-inner">
                    <div className="sb-auth-brand">
                        <img src={brandLogo} alt="" className="sb-auth-brand-mark" />
                        <span className="sb-auth-brand-text">
                            <strong className="italic bg-gradient-to-r from-sky-400 via-sky-500 to-blue-600 bg-clip-text text-transparent">SmartBrail</strong>
                            <small>Portal Mahasiswa</small>
                        </span>
                    </div>
                    <h1 className="sb-auth-title">
                        Satu Portal,<br />Berbagai Layanan<br />
                        <span className="sb-auth-title-accent">Mahasiswa</span>
                        <span className="sb-auth-title-underline" aria-hidden />
                    </h1>
                    <p className="sb-auth-desc">
                        Akses absensi, pengajuan ruangan, tim PBL, dan layanan laboratorium
                        dengan lebih mudah, cepat, dan terintegrasi.
                    </p>
                    <ul className="sb-auth-feats" aria-label="Layanan utama">
                        {FEATURES.map((f) => (
                            <li key={f.label}>
                                <f.icon size={22} aria-hidden />
                                <span>{f.label}</span>
                            </li>
                        ))}
                    </ul>
                </div>
                <Waves />
            </aside>

            {/* Sisi kanan — kartu form */}
            <main className="sb-auth-main">
                <div className="sb-auth-card">
                    <div className="sb-auth-card-brand">
                        <img src={brandLogo} alt="" className="sb-auth-card-mark" />
                        <span className="sb-auth-card-brand-text">
                            <strong className="italic bg-gradient-to-r from-sky-400 via-sky-500 to-blue-600 bg-clip-text text-transparent">SmartBrail</strong>
                            <small>Portal Mahasiswa</small>
                        </span>
                    </div>

                    {view === 'login' ? (
                        <>
                            <h2 className="sb-auth-card-title">Selamat Datang Kembali <span aria-hidden>👋</span></h2>
                            <p className="sb-auth-card-sub">Masuk ke akun Anda untuk mengakses layanan SmartBrail.</p>

                            {firstError && (
                                <div className="sb-auth-alert sb-auth-alert--err" role="alert">{firstError}</div>
                            )}
                            {flash?.success && (
                                <div className="sb-auth-alert sb-auth-alert--ok" role="status">{flash.success}</div>
                            )}

                            <form method="POST" action={route('login.post')}
                                onSubmit={(e) => {
                                    if (submitting) { e.preventDefault(); return; }
                                    const f = e.currentTarget;
                                    if (!f.checkValidity()) return; // biarkan validasi native
                                    setSubmitting(true);
                                }}
                            >
                                <input type="hidden" name="_token" value={csrf} />

                                <div className="sb-auth-field">
                                    <label htmlFor="lg-role">Masuk Sebagai</label>
                                    <div className="sb-auth-input-wrap">
                                        <GraduationCap size={17} className="sb-auth-input-icon" aria-hidden />
                                        <select id="lg-role" name="role" required
                                            value={role} onChange={(e) => setRole(e.target.value as RoleKey)}
                                            className="sb-auth-input sb-auth-select">
                                            {ROLE_ORDER.map((k) => (
                                                <option key={k} value={k}>{ROLE_META[k].option}</option>
                                            ))}
                                        </select>
                                        <ChevronDown size={15} className="sb-auth-select-chev" aria-hidden />
                                    </div>
                                </div>

                                <div className="sb-auth-field">
                                    <label htmlFor="lg-id">{meta.idLabel}</label>
                                    <div className="sb-auth-input-wrap">
                                        <User size={17} className="sb-auth-input-icon" aria-hidden />
                                        <input id="lg-id" name="nitk_nidn" type="text" required
                                            autoComplete="username" maxLength={50}
                                            defaultValue={old.nitk_nidn ?? ''}
                                            placeholder={meta.ph}
                                            className="sb-auth-input" />
                                    </div>
                                </div>

                                <div className="sb-auth-field">
                                    <label htmlFor="lg-pw">Password</label>
                                    <div className="sb-auth-input-wrap">
                                        <Lock size={17} className="sb-auth-input-icon" aria-hidden />
                                        <input id="lg-pw" name="password" required
                                            type={showPw ? 'text' : 'password'}
                                            autoComplete="current-password" maxLength={128}
                                            placeholder="Masukkan password Anda"
                                            className="sb-auth-input sb-auth-input--pw" />
                                        <button type="button"
                                            onClick={() => setShowPw((v) => !v)}
                                            aria-label={showPw ? 'Sembunyikan password' : 'Tampilkan password'}
                                            className="sb-auth-pw-toggle">
                                            {showPw ? <EyeOff size={17} aria-hidden /> : <Eye size={17} aria-hidden />}
                                        </button>
                                    </div>
                                </div>

                                <div className="sb-auth-row">
                                    <label className="sb-auth-remember">
                                        <input type="checkbox" name="remember" value="1" defaultChecked={old.remember} />
                                        <span>Ingat saya</span>
                                    </label>
                                    <button type="button" className="sb-auth-link" onClick={() => setView('forgot')}>
                                        Lupa password?
                                    </button>
                                </div>

                                <button type="submit" className="sb-auth-submit" disabled={submitting}>
                                    {submitting
                                        ? <Loader2 size={17} className="sb-spin" aria-hidden />
                                        : <LogIn size={17} aria-hidden />}
                                    <span>{submitting ? 'Memproses…' : 'Masuk'}</span>
                                </button>
                            </form>

                            <div className="sb-auth-info" role="note">
                                <Info size={16} aria-hidden />
                                <p>{meta.info}</p>
                            </div>
                        </>
                    ) : (
                        <ForgotPanel
                            csrf={csrf}
                            siteKey={recaptchaSiteKey}
                            oldIdentifier={old.identifier ?? ''}
                            flashSuccess={flash?.success ?? null}
                            error={err('identifier') ?? err('g-recaptcha-response')}
                            onBack={() => setView('login')}
                        />
                    )}
                </div>

                <p className="sb-auth-help">
                    <ShieldCheck size={14} aria-hidden />
                    Akun dikelola oleh admin/laboran. Butuh bantuan? Hubungi laboratorium terkait.
                </p>
            </main>
        </div>
    );
}

/** Panel Lupa Password — POST ke alur yang sudah ada (forgot-password.post). */
function ForgotPanel({ csrf, siteKey, oldIdentifier, flashSuccess, error, onBack }: {
    csrf: string;
    siteKey: string | null;
    oldIdentifier: string;
    flashSuccess: string | null;
    error: string | undefined;
    onBack: () => void;
}) {
    const [submitting, setSubmitting] = useState(false);
    const recapRef = useRef<HTMLDivElement>(null);
    const tokenRef = useRef<HTMLInputElement>(null);

    // reCAPTCHA hanya dimuat bila dikonfigurasi backend — lazy saat panel dibuka.
    useEffect(() => {
        if (!siteKey || !recapRef.current) return;
        const render = () => {
            const g = (window as any).grecaptcha;
            if (!g || !recapRef.current) return;
            g.render(recapRef.current, {
                sitekey: siteKey,
                hl: 'id',
                callback: (t: string) => { if (tokenRef.current) tokenRef.current.value = t; },
                'expired-callback': () => { if (tokenRef.current) tokenRef.current.value = ''; },
            });
        };
        if ((window as any).grecaptcha?.render) { render(); return; }
        (window as any).__sbRecaptchaOnload = render;
        const s = document.createElement('script');
        s.src = 'https://www.google.com/recaptcha/api.js?render=explicit&hl=id&onload=__sbRecaptchaOnload';
        s.async = true;
        document.head.appendChild(s);
    }, [siteKey]);

    return (
        <>
            <h2 className="sb-auth-card-title">Lupa Password</h2>
            <p className="sb-auth-card-sub">
                Masukkan NIM atau email terdaftar. Tautan atur ulang password dikirim ke email yang terikat pada akun Anda.
            </p>

            {flashSuccess && (
                <div className="sb-auth-alert sb-auth-alert--ok" role="status">{flashSuccess}</div>
            )}
            {error && (
                <div className="sb-auth-alert sb-auth-alert--err" role="alert">{error}</div>
            )}

            <form method="POST" action={route('forgot-password.post')}
                onSubmit={(e) => {
                    if (submitting) { e.preventDefault(); return; }
                    if (!e.currentTarget.checkValidity()) return;
                    setSubmitting(true);
                }}
            >
                <input type="hidden" name="_token" value={csrf} />
                <div className="sb-auth-field">
                    <label htmlFor="fg-id">NIM atau Email</label>
                    <div className="sb-auth-input-wrap">
                        <Mail size={17} className="sb-auth-input-icon" aria-hidden />
                        <input id="fg-id" name="identifier" type="text" required
                            autoComplete="username" maxLength={255}
                            defaultValue={oldIdentifier}
                            placeholder="NIM atau email terdaftar"
                            className="sb-auth-input" />
                    </div>
                </div>

                {/* Honeypot anti-bot — sama seperti formulir lama */}
                <div className="sb-auth-hp" aria-hidden="true">
                    <label>Website</label>
                    <input type="text" name="website" tabIndex={-1} autoComplete="off" />
                </div>

                {siteKey && (
                    <>
                        <div ref={recapRef} className="sb-auth-recap" />
                        <input ref={tokenRef} type="hidden" name="g-recaptcha-response" />
                    </>
                )}

                <button type="submit" className="sb-auth-submit" disabled={submitting}>
                    {submitting
                        ? <Loader2 size={17} className="sb-spin" aria-hidden />
                        : <Mail size={17} aria-hidden />}
                    <span>{submitting ? 'Mengirim…' : 'Kirim Tautan Reset'}</span>
                </button>
            </form>

            <button type="button" className="sb-auth-back" onClick={onBack}>
                <ArrowLeft size={15} aria-hidden /> Kembali ke Login
            </button>
        </>
    );
}

/** Gelombang cyan–biru khas yang menyambungkan sisi foto ke sisi form. */
function Waves() {
    return (
        <svg className="sb-auth-waves" viewBox="0 0 900 900" preserveAspectRatio="none" aria-hidden="true">
            <path
                d="M-40,900 C240,760 300,640 430,540 C560,440 640,300 760,180 C830,110 880,60 940,20 L940,900 Z"
                fill="rgba(56,189,248,0.16)"
            />
            <path
                d="M-40,900 C260,800 340,700 470,600 C600,500 680,380 800,260 C860,200 900,150 940,110 L940,900 Z"
                fill="rgba(59,130,246,0.20)"
            />
            <path
                d="M-40,900 C300,840 400,760 520,680 C640,600 720,500 840,400 C890,360 920,320 940,290 L940,900 Z"
                fill="rgba(34,211,238,0.22)"
            />
            <path
                d="M-40,900 C320,870 440,820 560,760 C680,700 780,640 900,560 L940,540 L940,900 Z"
                fill="rgba(147,197,253,0.18)"
            />
        </svg>
    );
}
