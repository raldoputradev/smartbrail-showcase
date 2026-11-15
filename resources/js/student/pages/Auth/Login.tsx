import { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    ArrowLeft, CalendarClock, ChevronDown, Eye, EyeOff, Fingerprint, FlaskConical,
    GraduationCap, Info, Loader2, Lock, LogIn, Mail, MailCheck, Send, ShieldCheck, User, Users,
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
    const [view, setViewState] = useState<'login' | 'forgot'>(panel === 'forgot' ? 'forgot' : 'login');
    // ?panel=forgot di URL — refresh tetap di panel lupa password (deep-link sudah didukung server).
    const setView = (v: 'login' | 'forgot') => {
        setViewState(v);
        const url = new URL(window.location.href);
        if (v === 'forgot') url.searchParams.set('panel', 'forgot');
        else url.searchParams.delete('panel');
        window.history.replaceState({}, '', url.toString());
    };
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

type FgCheck = { state: 'idle' | 'checking' | 'found' | 'no_account' | 'no_email' | 'error'; email?: string };

/** Panel Lupa Password bertahap: NIM → email tersamarkan → captcha → kirim. */
function ForgotPanel({ csrf, siteKey, oldIdentifier, flashSuccess, error, onBack }: {
    csrf: string;
    siteKey: string | null;
    oldIdentifier: string;
    flashSuccess: string | null;
    error: string | undefined;
    onBack: () => void;
}) {
    const [submitting, setSubmitting] = useState(false);
    const [identifier, setIdentifier] = useState(oldIdentifier);
    const [check, setCheck] = useState<FgCheck>({ state: 'idle' });
    const [captchaOk, setCaptchaOk] = useState(false);
    const recapRef = useRef<HTMLDivElement>(null);
    const tokenRef = useRef<HTMLInputElement>(null);
    const widgetRef = useRef<number | null>(null);
    const reqSeq = useRef(0);

    // Cek NIM/username → petunjuk email terikat (disamarkan). Debounce + abaikan respons basi.
    useEffect(() => {
        const id = identifier.trim();
        if (id.length < 4) {
            reqSeq.current += 1;
            setCheck({ state: 'idle' });
            return;
        }
        const seq = ++reqSeq.current;
        const t = setTimeout(() => {
            setCheck({ state: 'checking' });
            fetch(route('forgot-password.cek'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ identifier: id }),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (seq !== reqSeq.current) return;
                    if (d?.state === 'found') setCheck({ state: 'found', email: d.email_masked });
                    else if (d?.state === 'no_email') setCheck({ state: 'no_email' });
                    else if (d?.state === 'no_account') setCheck({ state: 'no_account' });
                    else setCheck({ state: 'error' });
                })
                .catch(() => { if (seq === reqSeq.current) setCheck({ state: 'error' }); });
        }, 500);
        return () => clearTimeout(t);
    }, [identifier, csrf]);

    // Captcha baru muncul setelah email terkonfirmasi; reset saat status berubah.
    useEffect(() => {
        if (check.state !== 'found') {
            setCaptchaOk(false);
            widgetRef.current = null;
            if (tokenRef.current) tokenRef.current.value = '';
            return;
        }
        if (!siteKey || widgetRef.current != null) return;
        const tryRender = () => {
            const g = (window as any).grecaptcha;
            if (!g?.render || !recapRef.current || widgetRef.current != null) return;
            widgetRef.current = g.render(recapRef.current, {
                sitekey: siteKey,
                hl: 'id',
                callback: (t: string) => {
                    if (tokenRef.current) tokenRef.current.value = t;
                    setCaptchaOk(true);
                },
                'expired-callback': () => {
                    if (tokenRef.current) tokenRef.current.value = '';
                    setCaptchaOk(false);
                },
                'error-callback': () => setCaptchaOk(false),
            });
        };
        if ((window as any).grecaptcha?.render) { tryRender(); return; }
        (window as any).__sbRecaptchaOnload = tryRender;
        if (!document.querySelector('script[data-sb-recaptcha]')) {
            const s = document.createElement('script');
            s.src = 'https://www.google.com/recaptcha/api.js?render=explicit&hl=id&onload=__sbRecaptchaOnload';
            s.async = true;
            s.dataset.sbRecaptcha = '1';
            document.head.appendChild(s);
        }
    }, [check.state, siteKey]);

    const canSubmit = check.state === 'found' && (!siteKey || captchaOk) && !submitting;

    return (
        <>
            <h2 className="sb-auth-card-title">Lupa Password</h2>
            <p className="sb-auth-card-sub">
                Masukkan NIM atau username — kami tampilkan email yang terikat (disamarkan) untuk Anda konfirmasi.
            </p>

            {flashSuccess && (
                <div className="sb-auth-alert sb-auth-alert--ok" role="status">{flashSuccess}</div>
            )}
            {error && (
                <div className="sb-auth-alert sb-auth-alert--err" role="alert">{error}</div>
            )}

            <form method="POST" action={route('forgot-password.post')}
                onSubmit={(e) => {
                    if (!canSubmit || !e.currentTarget.checkValidity()) {
                        e.preventDefault();
                        return;
                    }
                    setSubmitting(true);
                }}
            >
                <input type="hidden" name="_token" value={csrf} />
                <div className="sb-auth-field">
                    <label htmlFor="fg-id">NIM / Username</label>
                    <div className="sb-auth-input-wrap">
                        <User size={17} className="sb-auth-input-icon" aria-hidden />
                        <input id="fg-id" name="identifier" type="text" required
                            autoComplete="username" maxLength={255}
                            value={identifier}
                            onChange={(e) => setIdentifier(e.target.value)}
                            placeholder="NIM atau username akun"
                            className="sb-auth-input" />
                    </div>
                </div>

                {/* Honeypot anti-bot — sama seperti formulir lama */}
                <div className="sb-auth-hp" aria-hidden="true">
                    <label>Website</label>
                    <input type="text" name="website" tabIndex={-1} autoComplete="off" />
                </div>

                {check.state !== 'idle' && (
                    <div className={cn('sb-auth-hint', `sb-auth-hint--${check.state}`)} role="status">
                        {check.state === 'found'
                            ? <MailCheck size={19} aria-hidden />
                            : check.state === 'checking'
                                ? <Loader2 size={19} className="sb-spin" aria-hidden />
                                : <Info size={19} aria-hidden />}
                        <div>
                            {check.state === 'checking' && <span>Memeriksa akun…</span>}
                            {check.state === 'found' && (
                                <>
                                    <strong>Email terdaftar</strong>
                                    <code>{check.email}</code>
                                    <span>Benar email Anda? Centang verifikasi di bawah lalu kirim.</span>
                                </>
                            )}
                            {check.state === 'no_account' && <span>NIM/username ini tidak terdaftar — periksa kembali penulisannya.</span>}
                            {check.state === 'no_email' && <span>Akun ditemukan, tetapi belum memiliki email — minta admin/laboran menambahkan email Anda.</span>}
                            {check.state === 'error' && <span>Gagal memeriksa — periksa koneksi lalu coba lagi.</span>}
                        </div>
                    </div>
                )}

                {check.state === 'found' && siteKey && (
                    <div ref={recapRef} className="sb-auth-recap" />
                )}
                <input ref={tokenRef} type="hidden" name="g-recaptcha-response" />

                <button type="submit" className="sb-auth-submit" disabled={!canSubmit}>
                    {submitting
                        ? <Loader2 size={17} className="sb-spin" aria-hidden />
                        : <Send size={17} aria-hidden />}
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
