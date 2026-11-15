import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Bell, Camera, CheckCircle2, Fingerprint, KeyRound, Lock, MailCheck,
    Moon, Settings2, ShieldCheck, Sun, Trash2, User,
} from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Avatar } from '@/components/ui/avatar';
import type { AuthUser, SharedProps } from '@/types';

interface Props extends SharedProps {
    user: AuthUser & {
        angkatan?: number | null;
        semester?: number | null;
        semester_type?: string | null;
        email_verified_at?: string | null;
        profile_photo_path?: string | null;
        is_active?: boolean;
    };
    prodiName: string | null;
    pendingEmailOtp: boolean;
    kartuAkses?: string | null;
    izinPins?: { ruangan: string; mulai: string | null; selesai: string | null; pin: string | null }[];
}

type TabKey = 'profil' | 'kontak' | 'biometrik' | 'preferensi';

const TABS: { key: TabKey; label: string; icon: React.ElementType }[] = [
    { key: 'profil', label: 'Informasi Profil', icon: User },
    { key: 'kontak', label: 'Kontak & Keamanan', icon: ShieldCheck },
    { key: 'biometrik', label: 'Biometrik & Absensi', icon: Fingerprint },
    { key: 'preferensi', label: 'Preferensi', icon: Settings2 },
];

export default function Profile({ user, prodiName, pendingEmailOtp, kartuAkses, izinPins = [] }: Props) {
    const { flash, auth } = usePage<SharedProps>().props;
    const has_fingerprint = auth.user?.has_fingerprint;
    const fileRef = useRef<HTMLInputElement>(null);
    const [photoBusy, setPhotoBusy] = useState(false);
    const [tab, setTab] = useState<TabKey>('profil');
    const [theme, setTheme] = useState<'light' | 'dark'>(() =>
        (document.documentElement.dataset.theme as 'light' | 'dark') || 'light');

    // Notifikasi browser (Web Push) — SbPush dari /js/webpush.js
    const [pushState, setPushState] = useState<'loading' | 'unsupported' | 'denied' | 'on' | 'off'>('loading');
    const [pushBusy, setPushBusy] = useState(false);
    useEffect(() => {
        const p = (window as any).SbPush;
        if (!p?.supported?.()) { setPushState('unsupported'); return; }
        if (Notification.permission === 'denied') { setPushState('denied'); return; }
        p.subscription().then((s: any) => setPushState(s ? 'on' : 'off')).catch(() => setPushState('off'));
    }, []);
    const togglePush = async () => {
        const p = (window as any).SbPush;
        if (!p || pushBusy) return;
        setPushBusy(true);
        try {
            if (pushState !== 'on') {
                const res = await p.enable();
                setPushState(res.ok ? 'on' : (res.reason === 'denied' ? 'denied' : 'off'));
            } else {
                await p.disable();
                setPushState('off');
            }
        } finally {
            setPushBusy(false);
        }
    };

    // Preferensi server (kategori push, digest email, format tanggal) — kolom users.preferences
    const [prefs, setPrefs] = useState<Record<string, any>>(() => (auth.user as any)?.preferences ?? {});
    const [prefBusy, setPrefBusy] = useState(false);
    const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const savePrefs = async (patch: Record<string, any>, reload = false) => {
        if (prefBusy) return;
        setPrefBusy(true);
        setPrefs((prev) => ({ ...prev, ...patch }));
        try {
            await fetch(route('mahasiswa.preferensi.update'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(patch),
                credentials: 'same-origin',
            });
            if (reload) router.reload();
        } finally {
            setPrefBusy(false);
        }
    };
    const pushKat = (k: string) => prefs?.push_kategori?.[k] !== false;
    const setPushKat = (k: string, v: boolean) =>
        savePrefs({ push_kategori: { ...prefs?.push_kategori, [k]: v } });

    // Ukuran teks — preferensi lokal perangkat (zoom ringan)
    const [textSize, setTextSize] = useState<'normal' | 'besar'>(() =>
        (localStorage.getItem('smartbrail_student_fs') as 'normal' | 'besar') || 'normal');
    const setAppTextSize = (s: 'normal' | 'besar') => {
        document.documentElement.style.zoom = s === 'besar' ? '1.12' : '';
        try { localStorage.setItem('smartbrail_student_fs', s); } catch { /* abaikan */ }
        setTextSize(s);
    };

    const contact = useForm({ email: user.email ?? '', no_hp: user.no_hp ?? '' });
    const otp = useForm({ otp_code: '' });
    const resend = useForm({});

    const isEmailVerified = !!user.email && !!user.email_verified_at;
    const emailLocked = isEmailVerified;
    const phoneLocked = !!user.no_hp;
    const hasFingerId = !!user.finger_id;
    const enrolled = !!has_fingerprint;

    const pickPhoto = (f: File | undefined) => {
        if (!f) return;
        setPhotoBusy(true);
        router.post(route('profiles.photo.update'), { photo: f } as never, {
            forceFormData: true,
            onFinish: () => setPhotoBusy(false),
        });
    };

    const removePhoto = () => {
        setPhotoBusy(true);
        router.delete(route('profiles.photo.destroy'), { onFinish: () => setPhotoBusy(false) });
    };

    const setAppTheme = (t: 'light' | 'dark') => {
        document.documentElement.dataset.theme = t;
        try { localStorage.setItem('smartbrail_student_theme', t); } catch { /* abaikan */ }
        setTheme(t);
    };

    const identitasCard = (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>Identitas Akademik</CardTitle>
                    <CardDescription>Informasi akademik Anda di lingkungan Politeknik Negeri Batam.</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
                    {[
                        ['Nama Lengkap', user.nama_lengkap],
                        ['NIM', user.nitk_nidn ?? '—'],
                        ['Program Studi', prodiName ?? '—'],
                        ['Kelas / Angkatan', `${user.kelas ?? '—'}${user.angkatan ? ` · Angkatan ${user.angkatan}` : ''}`],
                        ['Semester / Sesi', `${user.semester ? `Semester ${user.semester} (${user.semester_type ?? '-'})` : '—'} · ${(user.shift ?? 'pagi').toUpperCase()}`],
                        ['Tim PBL', user.tim ?? '—'],
                    ].map(([k, v]) => (
                        <div key={k} className="rounded-[10px] bg-sb-surface2 px-3.5 py-2.5">
                            <dt className="text-[11px] font-bold uppercase tracking-wide text-sb-muted">{k}</dt>
                            <dd className="mt-0.5 text-[13.5px] font-semibold">{v}</dd>
                        </div>
                    ))}
                </dl>
                <p className="mt-3 text-[12px] text-sb-muted">
                    Data akademik dikelola admin. Jika ada yang keliru, hubungi laboran.
                </p>
            </CardContent>
        </Card>
    );

    const kontakCard = (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>Kontak &amp; Pemulihan Akun</CardTitle>
                    <CardDescription>Kelola email dan nomor HP untuk pemulihan akun Anda.</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {flash.success && (
                    <p role="status" className="mb-3 rounded-[10px] border border-sb-ok/30 bg-sb-ok-bg/60 px-3.5 py-2.5 text-[12.5px] font-medium text-sb-ok">
                        {flash.success}
                    </p>
                )}
                <form onSubmit={(e) => { e.preventDefault(); contact.post(route('mahasiswa.profile.contact'), { preserveScroll: true }); }} className="space-y-3.5">
                    <div>
                        <label htmlFor="profileEmail" className="mb-1.5 flex items-center justify-between text-[12.5px] font-bold">
                            Email (opsional)
                            {emailLocked && (
                                <span className="inline-flex items-center gap-1 text-[11px] font-bold text-sb-ok">
                                    <CheckCircle2 size={13} /> Terverifikasi
                                </span>
                            )}
                        </label>
                        {emailLocked ? (
                            <div className="flex items-center gap-2.5 rounded-[10px] border border-sb-ok/30 bg-sb-ok-bg/40 px-3.5 py-2.5 text-[13.5px] font-semibold">
                                <Lock size={14} className="shrink-0 text-sb-ok" />
                                <span className="truncate">{user.email}</span>
                            </div>
                        ) : (
                            <Input id="profileEmail" type="email" value={contact.data.email}
                                onChange={(e) => contact.setData('email', e.target.value)}
                                placeholder="nama@mahasiswa.polibatam.ac.id" autoComplete="email" />
                        )}
                        {contact.errors.email && <p className="mt-1.5 text-[12px] font-medium text-sb-danger">{contact.errors.email}</p>}
                    </div>
                    <div>
                        <label htmlFor="profilePhone" className="mb-1.5 flex items-center justify-between text-[12.5px] font-bold">
                            No. HP (opsional)
                            {phoneLocked && (
                                <span className="inline-flex items-center gap-1 text-[11px] font-bold text-sb-ok">
                                    <CheckCircle2 size={13} /> Tersimpan
                                </span>
                            )}
                        </label>
                        {phoneLocked ? (
                            <div className="flex items-center gap-2.5 rounded-[10px] border border-sb-ok/30 bg-sb-ok-bg/40 px-3.5 py-2.5 text-[13.5px] font-semibold">
                                <Lock size={14} className="shrink-0 text-sb-ok" />
                                <span className="truncate">{user.no_hp}</span>
                            </div>
                        ) : (
                            <Input id="profilePhone" type="tel" value={contact.data.no_hp}
                                onChange={(e) => contact.setData('no_hp', e.target.value)}
                                placeholder="081234567890" autoComplete="tel" />
                        )}
                        {contact.errors.no_hp && <p className="mt-1.5 text-[12px] font-medium text-sb-danger">{contact.errors.no_hp}</p>}
                    </div>
                    {(!emailLocked || !phoneLocked) && (
                        <Button type="submit" loading={contact.processing}>Simpan Kontak</Button>
                    )}
                    {(emailLocked || phoneLocked) && (
                        <p className="text-[12px] text-sb-muted">
                            Kontak yang sudah tersimpan/terverifikasi terkunci dan tidak dapat diubah sendiri.
                            Ada kendala? Laporkan ke laboran/admin.
                        </p>
                    )}
                </form>

                {pendingEmailOtp && (
                    <div className="mt-4 space-y-3 rounded-[12px] border border-sb-warn/30 bg-sb-warn-bg/40 p-4">
                        <div className="flex items-center gap-2">
                            <Badge tone="warn">Verifikasi Email Tertunda</Badge>
                        </div>
                        <p className="text-[12.5px] text-sb-text2">
                            Kode verifikasi telah dikirimkan ke <strong>{user.email}</strong>. Masukkan kode 6 digit:
                        </p>
                        <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); otp.post(route('mahasiswa.profile.email.verify'), { preserveScroll: true }); }}>
                            <Input inputMode="numeric" pattern="[0-9]{6}" maxLength={6} placeholder="000000"
                                value={otp.data.otp_code} onChange={(e) => otp.setData('otp_code', e.target.value)}
                                className="w-32 text-center font-mono text-[16px] tracking-[0.3em]" aria-label="Kode OTP" />
                            <Button type="submit" size="sm" loading={otp.processing}><MailCheck size={14} /> Verifikasi</Button>
                        </form>
                        {otp.errors.otp_code && <p className="text-[12px] font-medium text-sb-danger">{otp.errors.otp_code}</p>}
                        <button type="button" onClick={() => resend.post(route('mahasiswa.profile.email.resend'))}
                            disabled={resend.processing}
                            className="text-[12px] font-medium text-sb-primary underline underline-offset-2 disabled:opacity-60">
                            Belum menerima email? Kirim ulang kode OTP
                        </button>
                    </div>
                )}
            </CardContent>
        </Card>
    );

    const keamananCard = (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>Keamanan Akun</CardTitle>
                    <CardDescription>Atur password dan keamanan akun Anda.</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {auth.user?.must_change_password && (
                    <div className="mb-3 flex items-start gap-2 rounded-[10px] border border-sb-warn/35 bg-sb-warn-bg px-3 py-2.5 text-[12.5px] leading-snug">
                        <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-sb-warn" aria-hidden="true" />
                        <span>
                            <strong className="font-bold">Password sementara masih aktif.</strong>
                            {' '}Ganti dengan password pribadi agar peringatan ini hilang dan akun Anda benar-benar aman.
                        </span>
                    </div>
                )}
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2.5 text-[13.5px]">
                        <KeyRound size={15} className="text-sb-muted" />
                        <div>
                            <p className="font-bold">Password</p>
                            <p className="font-mono tracking-widest text-sb-muted">••••••••</p>
                        </div>
                    </div>
                    <Link href={route('mahasiswa.password.change')}>
                        <Button variant="ghost" size="sm" className="border border-sb-border">Ubah Password</Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    );

    const biometrikCard = (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>Status Biometrik &amp; Mesin Absensi</CardTitle>
                    <CardDescription>Status pendaftaran sidik jari untuk keperluan absensi laboratorium.</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <div className={`flex items-start gap-3 rounded-[12px] border p-4 ${
                    enrolled ? 'border-sb-ok/30 bg-sb-ok-bg/40'
                    : hasFingerId ? 'border-sb-warn/30 bg-sb-warn-bg/40'
                    : 'border-sb-border bg-sb-surface2'}`}>
                    {enrolled
                        ? <CheckCircle2 size={20} className="mt-0.5 shrink-0 text-sb-ok" />
                        : <Fingerprint size={20} className="mt-0.5 shrink-0 text-sb-warn" />}
                    <div className="text-[13px]">
                        {enrolled ? (
                            <>
                                <p className="font-bold">Sidik Jari Terdaftar</p>
                                <p className="mt-0.5 text-sb-text2">
                                    ID #{user.finger_id} — Sidik jari Anda sudah terdaftar dan siap digunakan.
                                </p>
                            </>
                        ) : hasFingerId ? (
                            <>
                                <p className="font-bold">ID #{user.finger_id} dialokasikan, belum rekam sidik jari</p>
                                <p className="mt-0.5 text-sb-text2">Hubungi laboran untuk merekam sidik jari di mesin.</p>
                            </>
                        ) : (
                            <>
                                <p className="font-bold">Belum terdaftar di mesin absensi</p>
                                <p className="mt-0.5 text-sb-text2">Hubungi laboran untuk alokasi ID dan perekaman sidik jari.</p>
                            </>
                        )}
                    </div>
                </div>
                <p className="mt-3 text-[12px] text-sb-muted">
                    Jika data kelas/sesi tidak sesuai atau mesin tidak membaca jari Anda, hubungi laboran untuk sinkronisasi ulang.
                </p>

                <div className="mt-4 rounded-[12px] border border-sb-border bg-sb-surface2 p-4 text-[13px]">
                    <p className="font-bold">Kartu Akses Ruangan</p>
                    <p className="mt-1.5 flex items-baseline gap-2">
                        Nomor seri kartu Anda:
                        <strong className="font-mono text-[14px] tracking-wide">{kartuAkses ?? 'Belum diterbitkan'}</strong>
                    </p>
                    <p className="mt-1 text-[12px] text-sb-muted">
                        Gunakan nomor ini untuk mencari kartu Anda di mesin ruangan.
                    </p>
                    <div className="mt-3 border-t border-sb-border pt-3">
                        <p className="font-bold">PIN Akses Ruangan</p>
                        {izinPins.length > 0 ? (
                            <ul className="mt-1.5 space-y-2">
                                {izinPins.map((z, i) => (
                                    <li key={i} className="rounded-[10px] border border-sb-border bg-sb-surface px-3 py-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold">{z.ruangan}</span>
                                            <strong className="font-mono text-[15px] tracking-[0.15em]">{z.pin}</strong>
                                        </div>
                                        <p className="mt-0.5 text-[11.5px] text-sb-muted">
                                            Berlaku {z.mulai ?? '—'} s.d. {z.selesai ?? '—'} — PIN mati otomatis setelah izin berakhir.
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-1.5 text-[12.5px] text-sb-muted">
                                Tidak ada PIN aktif. PIN dibuat otomatis saat pengajuan ruangan Anda disetujui dan hanya berlaku selama masa izin.
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    const preferensiCard = (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>Preferensi Tampilan</CardTitle>
                    <CardDescription>Atur tampilan portal sesuai kenyamanan Anda.</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="text-[13.5px] font-bold">Tema</p>
                        <p className="text-[12px] text-sb-muted">Pilih mode terang atau gelap — tersimpan di perangkat ini.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" variant={theme === 'light' ? 'primary' : 'ghost'}
                            className={theme === 'light' ? '' : 'border border-sb-border'}
                            onClick={() => setAppTheme('light')} aria-pressed={theme === 'light'}>
                            <Sun size={14} /> Terang
                        </Button>
                        <Button size="sm" variant={theme === 'dark' ? 'primary' : 'ghost'}
                            className={theme === 'dark' ? '' : 'border border-sb-border'}
                            onClick={() => setAppTheme('dark')} aria-pressed={theme === 'dark'}>
                            <Moon size={14} /> Gelap
                        </Button>
                    </div>
                </div>

                <div className="mt-5 flex items-center justify-between gap-3 border-t border-sb-border pt-5">
                    <div>
                        <p className="flex items-center gap-1.5 text-[13.5px] font-bold"><Bell size={14} /> Notifikasi browser</p>
                        <p className="text-[12px] text-sb-muted">
                            Info pengajuan ruangan, pengumuman &amp; balasan laporan langsung ke perangkat — walau portal tidak dibuka.
                        </p>
                        {pushState === 'denied' && (
                            <p className="mt-1 text-[11.5px] text-sb-danger">
                                Izin diblokir browser — aktifkan lewat ikon gembok/pengaturan situs di bilah alamat.
                            </p>
                        )}
                    </div>
                    {pushState === 'unsupported' ? (
                        <span className="text-[12px] text-sb-muted">Tidak didukung browser ini</span>
                    ) : pushState === 'denied' ? (
                        <span className="rounded-full border border-sb-border px-3 py-1 text-[11.5px] font-semibold text-sb-muted">Diblokir</span>
                    ) : (
                        <button type="button" role="switch" aria-checked={pushState === 'on'}
                            aria-label="Notifikasi browser"
                            disabled={pushBusy || pushState === 'loading'}
                            onClick={togglePush}
                            className={`relative h-6 w-11 flex-none rounded-full transition disabled:opacity-50 ${pushState === 'on' ? 'bg-sb-primary' : 'bg-sb-border'}`}
                        >
                            <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${pushState === 'on' ? 'left-[22px]' : 'left-0.5'}`} />
                        </button>
                    )}
                </div>

                {/* Kategori push */}
                <div className="mt-5 border-t border-sb-border pt-5">
                    <p className="text-[13.5px] font-bold">Kategori notifikasi browser</p>
                    <p className="text-[12px] text-sb-muted">Pilih jenis info yang dikirim sebagai notifikasi push ke perangkat ini.</p>
                    <div className="mt-3 grid gap-2 sm:grid-cols-3">
                        {([
                            ['pengajuan', 'Pengajuan Ruangan', 'Keputusan & tawaran jadwal'],
                            ['pengumuman', 'Pengumuman', 'Info dari laboran/admin'],
                            ['laporan', 'Balasan Laporan', 'Tanggapan atas laporan Anda'],
                        ] as const).map(([k, label, desc]) => (
                            <label key={k}
                                className={`flex cursor-pointer items-start gap-2.5 rounded-[10px] border p-3 transition ${pushKat(k) ? 'border-sb-primary/50 bg-sb-primary-soft/40' : 'border-sb-border'}`}
                            >
                                <input type="checkbox" className="mt-0.5 accent-sb-primary"
                                    checked={pushKat(k)} disabled={prefBusy}
                                    onChange={(e) => setPushKat(k, e.target.checked)} />
                                <span>
                                    <span className="block text-[12.5px] font-bold">{label}</span>
                                    <span className="block text-[11px] text-sb-muted">{desc}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                </div>

                {/* Ringkasan email harian */}
                <div className="mt-5 flex items-center justify-between gap-3 border-t border-sb-border pt-5">
                    <div>
                        <p className="text-[13.5px] font-bold">Ringkasan email harian</p>
                        <p className="text-[12px] text-sb-muted">
                            Rekap absensi, keputusan pengajuan & notifikasi tiap sore (~17:20 WIB)
                            {isEmailVerified ? ` ke ${user.email}` : ' — butuh email terverifikasi'}.
                        </p>
                    </div>
                    {isEmailVerified ? (
                        <button type="button" role="switch" aria-checked={!!prefs?.digest_email}
                            aria-label="Ringkasan email harian" disabled={prefBusy}
                            onClick={() => savePrefs({ digest_email: !prefs?.digest_email })}
                            className={`relative h-6 w-11 flex-none rounded-full transition disabled:opacity-50 ${prefs?.digest_email ? 'bg-sb-primary' : 'bg-sb-border'}`}
                        >
                            <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${prefs?.digest_email ? 'left-[22px]' : 'left-0.5'}`} />
                        </button>
                    ) : (
                        <span className="text-[12px] text-sb-muted">Verifikasi email dulu</span>
                    )}
                </div>

                {/* Format tanggal */}
                <div className="mt-5 flex items-center justify-between gap-3 border-t border-sb-border pt-5">
                    <div>
                        <p className="text-[13.5px] font-bold">Format tanggal</p>
                        <p className="text-[12px] text-sb-muted">Gaya penulisan tanggal di seluruh portal.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" variant={(prefs?.format_tanggal ?? 'teks') === 'teks' ? 'primary' : 'ghost'}
                            className={(prefs?.format_tanggal ?? 'teks') === 'teks' ? '' : 'border border-sb-border'}
                            disabled={prefBusy}
                            onClick={() => savePrefs({ format_tanggal: 'teks' }, true)}>
                            16 Nov 2026
                        </Button>
                        <Button size="sm" variant={prefs?.format_tanggal === 'angka' ? 'primary' : 'ghost'}
                            className={prefs?.format_tanggal === 'angka' ? '' : 'border border-sb-border'}
                            disabled={prefBusy}
                            onClick={() => savePrefs({ format_tanggal: 'angka' }, true)}>
                            16/11/2026
                        </Button>
                    </div>
                </div>

                {/* Ukuran teks */}
                <div className="mt-5 flex items-center justify-between gap-3 border-t border-sb-border pt-5">
                    <div>
                        <p className="text-[13.5px] font-bold">Ukuran teks</p>
                        <p className="text-[12px] text-sb-muted">Perbesar tampilan agar lebih nyaman dibaca — tersimpan di perangkat ini.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" variant={textSize === 'normal' ? 'primary' : 'ghost'}
                            className={textSize === 'normal' ? '' : 'border border-sb-border'}
                            onClick={() => setAppTextSize('normal')} aria-pressed={textSize === 'normal'}>
                            Normal
                        </Button>
                        <Button size="sm" variant={textSize === 'besar' ? 'primary' : 'ghost'}
                            className={textSize === 'besar' ? '' : 'border border-sb-border'}
                            onClick={() => setAppTextSize('besar')} aria-pressed={textSize === 'besar'}>
                            Besar
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Akun' }, { label: 'Profil' }]} />}
                title="Profil Saya"
                sub="Kelola informasi pribadi, kontak, keamanan akun, dan data biometrik Anda."
            />

            {/* Header profil */}
            <Card>
                <CardContent className="flex flex-wrap items-center gap-5 pt-6">
                    <div className="relative">
                        <Avatar nama={user.nama_lengkap} src={user.profile_photo_url} className="h-[76px] w-[76px]" />
                        <button
                            type="button" onClick={() => fileRef.current?.click()}
                            disabled={photoBusy}
                            aria-label="Ganti foto profil"
                            className="absolute -bottom-1 -right-1 grid h-8 w-8 place-items-center rounded-full border border-sb-border bg-sb-surface text-sb-muted shadow-sb transition hover:text-sb-primary disabled:opacity-60"
                        >
                            <Camera size={14} />
                        </button>
                        <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" hidden
                            onChange={(e) => pickPhoto(e.target.files?.[0])} />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="truncate text-[19px] font-extrabold">{user.nama_lengkap}</h3>
                        <p className="mt-0.5 text-[13px] text-sb-text2">
                            NIM <strong className="font-mono">{user.nitk_nidn}</strong> · {prodiName ?? 'Program Studi belum diatur'}
                        </p>
                        <div className="mt-2.5 flex flex-wrap gap-1.5">
                            <Badge tone="ok">Mahasiswa Aktif</Badge>
                            <Badge tone={isEmailVerified ? 'ok' : 'warn'}>
                                {isEmailVerified ? 'Email Terverifikasi' : user.email ? 'Email Belum Diverifikasi' : 'Email Belum Diisi'}
                            </Badge>
                            <Badge tone={user.no_hp ? 'ok' : 'neutral'}>
                                {user.no_hp ? 'No. HP Tersimpan' : 'No. HP Belum Diisi'}
                            </Badge>
                        </div>
                    </div>
                    <div className="flex flex-col gap-2">
                        <Button variant="ghost" size="sm" loading={photoBusy}
                            onClick={() => fileRef.current?.click()} className="border border-sb-border">
                            <Camera size={14} /> Ubah Foto
                        </Button>
                        {user.profile_photo_path && (
                            <Button variant="ghost" size="sm" loading={photoBusy} onClick={removePhoto} className="text-sb-danger">
                                <Trash2 size={14} /> Hapus Foto
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Tab navigasi */}
            <div role="tablist" aria-label="Bagian profil" className="flex flex-wrap gap-2">
                {TABS.map(({ key, label, icon: Icon }) => (
                    <button
                        key={key} type="button" role="tab" aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={`inline-flex items-center gap-2 rounded-[10px] px-4 py-2.5 text-[13px] font-bold transition ${
                            tab === key
                                ? 'bg-sb-primary text-white shadow-sb'
                                : 'border border-sb-border bg-sb-surface text-sb-text2 hover:border-sb-primary/40 hover:text-sb-primary'
                        }`}
                    >
                        <Icon size={15} /> {label}
                    </button>
                ))}
            </div>

            {tab === 'profil' && (
                <div className="grid items-start gap-4 xl:grid-cols-2">
                    <div className="space-y-4">
                        {identitasCard}
                        {biometrikCard}
                    </div>
                    <div className="space-y-4">
                        {kontakCard}
                        {keamananCard}
                    </div>
                </div>
            )}

            {tab === 'kontak' && (
                <div className="grid items-start gap-4 xl:grid-cols-2">
                    {kontakCard}
                    {keamananCard}
                </div>
            )}

            {tab === 'biometrik' && biometrikCard}
            {tab === 'preferensi' && preferensiCard}
        </div>
    );
}

Profile.layout = (page: React.ReactNode) => <AppShell title="Profil Saya">{page}</AppShell>;
