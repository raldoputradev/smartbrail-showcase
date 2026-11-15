import { useRef, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays, CheckCircle2, Database, Eye, Globe, Info, Link2,
    ListChecks, Lock, Mail, Monitor, Paintbrush, Settings2, ShieldCheck,
    SlidersHorizontal, Trash2, Upload, XCircle, Zap,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { asset } from '@admin/lib/asset';
import { cn } from '@admin/lib/utils';
import type { PengaturanProps, SharedProps } from '@admin/types';

const TABS = [
    { key: 'umum', label: 'Umum', icon: <Settings2 size={14} /> },
    { key: 'keamanan', label: 'Keamanan', icon: <ShieldCheck size={14} /> },
    { key: 'integrasi', label: 'Integrasi', icon: <Link2 size={14} /> },
    { key: 'tampilan', label: 'Tampilan', icon: <Paintbrush size={14} /> },
    { key: 'data', label: 'Data & Pemeliharaan', icon: <Database size={14} /> },
] as const;
type TabKey = typeof TABS[number]['key'];

const QUICK = [
    { label: 'Pengaturan Email Sistem', desc: 'Status konfigurasi mailer', tab: 'integrasi' as TabKey, icon: <Mail size={15} /> },
    { label: 'Manajemen API & Integrasi', desc: 'Gemini, FCM, reCAPTCHA', tab: 'integrasi' as TabKey, icon: <Link2 size={15} /> },
    { label: 'Keamanan & Sesi', desc: 'Durasi sesi, proteksi login', tab: 'keamanan' as TabKey, icon: <Lock size={15} /> },
    { label: 'Zona Waktu & Bahasa', desc: 'Info regional sistem', tab: 'umum' as TabKey, icon: <Globe size={15} /> },
];

export default function Pengaturan(props: PengaturanProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState<TabKey>('umum');
    const fileRef = useRef<HTMLInputElement | null>(null);
    const [logoBusy, setLogoBusy] = useState(false);
    const [preview, setPreview] = useState<string | null>(null);

    const genForm = useForm({
        nama_sistem: props.general.nama_sistem,
        nama_laboratorium: props.general.nama_laboratorium,
    });
    const liburForm = useForm({ tanggal: '', keterangan: '' });
    const prodiForm = useForm({ kode: '', nama: '' });

    const uploadLogo = (file: File) => {
        setLogoBusy(true);
        router.post(props.urls.branding, { logo: file } as never, {
            forceFormData: true, preserveScroll: true,
            onFinish: () => { setLogoBusy(false); setPreview(null); if (fileRef.current) fileRef.current.value = ''; },
        });
    };
    const resetLogo = () => {
        if (!confirm('Kembalikan logo ke bawaan SmartBrail?')) return;
        router.delete(props.urls.branding_reset, { preserveScroll: true });
    };

    const logoNow = preview || props.page_branding.logo_url || asset('branding/smartbrail-logo.png');
    const info = props.info;

    // Label fitur BARU/SOON di sidebar — master toggle + batas semester + per fitur.
    const fb = props.feature_badges;
    const [fbEnabled, setFbEnabled] = useState(fb.enabled);
    const [fbUntil, setFbUntil] = useState(fb.until);
    const [fbItems, setFbItems] = useState<Record<string, string>>(
        Object.fromEntries(fb.items.map((i) => [i.key, i.state]))
    );
    const [fbBusy, setFbBusy] = useState(false);
    const saveFeatureBadges = () => {
        setFbBusy(true);
        router.post(props.urls.feature_badges, {
            enabled: fbEnabled, until: fbUntil, items: fbItems,
        } as never, { preserveScroll: true, onFinish: () => setFbBusy(false) });
    };

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Pengaturan Sistem"
            subtitle="Konfigurasi dasar, keamanan, performa, dan pemeliharaan sistem SmartBrail.">
            <Head title="Pengaturan Sistem" />

            {flash?.success ? <div className="ad-mesin-flash ad-mesin-flash--ok">{flash.success}</div> : null}
            {flash?.error ? <div className="ad-mesin-flash ad-mesin-flash--err">{flash.error}</div> : null}

            <section className="ad-nq-hero ad-set-hero"
                style={{ ['--ad-hero-img' as string]: `url('${asset('images/campus-polybatam.jpg')}')` }}>
                <div className="ad-nq-hero__body">
                    <h2>Pengaturan Sistem</h2>
                    <p>Kelola konfigurasi inti sistem seperti identitas, keamanan, integrasi, tampilan, serta pencadangan data.</p>
                </div>
            </section>

            <div className="ad-rg-tabs ad-set-tabs">
                {TABS.map((t) => (
                    <button key={t.key} type="button"
                        className={cn('ad-rg-tab', tab === t.key && 'is-active')}
                        onClick={() => setTab(t.key)}>
                        {t.icon} {t.label}
                    </button>
                ))}
            </div>

            {tab === 'umum' && (
                <div className="ad-set-split">
                    <div className="ad-set-col">
                        <div className="ad-panel">
                            <div className="ad-panel__head"><h3><Info size={15} /> Informasi Sistem</h3>
                                <small>Identitas dan versi sistem.</small></div>
                            <div className="ad-set-sysid">
                                <img src={logoNow} alt={info.nama_sistem} />
                                <div>
                                    <strong>{info.nama_sistem}</strong>
                                    <small>{info.deskripsi}</small>
                                </div>
                            </div>
                            <dl className="ad-set-kv">
                                <div><dt>Versi Sistem</dt><dd><span className="ad-pill ad-pill--info">v{info.versi}</span></dd></div>
                                <div><dt>Lingkungan</dt><dd><span className={cn('ad-pill', info.is_production ? 'ad-pill--ok' : 'ad-pill--pending')}>{info.lingkungan}</span></dd></div>
                                <div><dt>Terakhir Diperbarui</dt><dd>{info.diperbarui}</dd></div>
                                <div><dt>Status Layanan</dt><dd><span className="ad-pill ad-pill--ok">{info.status}</span></dd></div>
                            </dl>
                        </div>
                        <div className="ad-panel">
                            <div className="ad-panel__head"><h3><Zap size={15} /> Aksi Cepat</h3>
                                <small>Akses cepat ke pengaturan penting.</small></div>
                            <div className="ad-set-quick">
                                {QUICK.map((qk) => (
                                    <button key={qk.label} type="button" className="ad-set-quick__item"
                                        onClick={() => setTab(qk.tab)}>
                                        <span className="ad-set-quick__icon">{qk.icon}</span>
                                        <span><strong>{qk.label}</strong><small>{qk.desc}</small></span>
                                        <span className="ad-set-quick__arr">›</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="ad-set-col ad-set-col--wide">
                        <div className="ad-panel">
                            <div className="ad-panel__head">
                                <div><h3><Settings2 size={15} /> Konfigurasi Umum</h3>
                                    <small>Pengaturan dasar identitas dan informasi sistem.</small></div>
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--primary"
                                    disabled={genForm.processing}
                                    onClick={() => genForm.post(props.urls.update, { preserveScroll: true })}>
                                    {genForm.processing ? 'Menyimpan…' : 'Simpan Perubahan'}
                                </button>
                            </div>
                            <div className="ad-set-formgrid">
                                <label className="ad-formfield">
                                    <span>Nama Sistem *</span>
                                    <input value={genForm.data.nama_sistem}
                                        onChange={(e) => genForm.setData('nama_sistem', e.target.value)} />
                                    {errors.nama_sistem && <em className="ad-err">{errors.nama_sistem}</em>}
                                </label>
                                <label className="ad-formfield">
                                    <span>Nama Laboratorium</span>
                                    <input value={genForm.data.nama_laboratorium}
                                        onChange={(e) => genForm.setData('nama_laboratorium', e.target.value)} />
                                    {errors.nama_laboratorium && <em className="ad-err">{errors.nama_laboratorium}</em>}
                                </label>
                                <div className="ad-formfield"><span>Zona Waktu *</span>
                                    <div className="ad-set-static">{props.general.zona_waktu} — WIB, Jakarta</div></div>
                                <div className="ad-formfield"><span>Format Tanggal</span>
                                    <div className="ad-set-static">{props.general.format_tanggal}</div></div>
                                <div className="ad-formfield"><span>Bahasa Default</span>
                                    <div className="ad-set-static">{props.general.bahasa}</div></div>
                                <div className="ad-formfield"><span>Format Waktu</span>
                                    <div className="ad-set-static">{props.general.format_waktu}</div></div>
                            </div>
                            <small className="ad-set-note">Zona waktu, bahasa, dan format mengikuti konfigurasi server dan tidak dapat diubah dari sini.</small>
                        </div>

                        <div className="ad-panel">
                            <div className="ad-panel__head"><h3><SlidersHorizontal size={15} /> Pengaturan Sistem Tambahan</h3>
                                <small>Aturan umum dan preferensi operasional sistem.</small></div>
                            <div className="ad-set-toggles">
                                {props.security.slice(3).map((row) => (
                                    <div className="ad-set-toggle" key={row.label}>
                                        <span className="ad-set-toggle__ic"><ListChecks size={15} /></span>
                                        <span className="ad-set-toggle__tx">
                                            <strong>{row.label}</strong>
                                            <small>{row.value}</small>
                                        </span>
                                        <span className={cn('ad-pill', row.status === 'Aktif' ? 'ad-pill--ok' : 'ad-pill--muted')}>{row.status}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'keamanan' && (
                <div className="ad-panel">
                    <div className="ad-panel__head"><h3><ShieldCheck size={15} /> Postur Keamanan</h3>
                        <small>Ringkasan proteksi yang aktif pada sistem.</small></div>
                    <div className="ad-set-seclist">
                        {props.security.map((row) => (
                            <div className="ad-set-secrow" key={row.label}>
                                <span className="ad-set-toggle__ic"><Lock size={15} /></span>
                                <span className="ad-set-toggle__tx">
                                    <strong>{row.label}</strong><small>{row.value}</small>
                                </span>
                                <span className={cn('ad-pill', row.status === 'Aktif' ? 'ad-pill--ok' : 'ad-pill--muted')}>{row.status}</span>
                            </div>
                        ))}
                    </div>
                    <small className="ad-set-note">Nilai ambang proteksi dikelola di konfigurasi server; halaman ini menampilkan status aktualnya.</small>
                </div>
            )}

            {tab === 'integrasi' && (
                <div className="ad-panel">
                    <div className="ad-panel__head"><h3><Link2 size={15} /> Integrasi Layanan</h3>
                        <small>Status koneksi layanan eksternal — kredensial tidak ditampilkan.</small></div>
                    <div className="ad-set-intgrid">
                        {props.integrations.map((it) => (
                            <div className="ad-set-int" key={it.label}>
                                <div className="ad-set-int__head">
                                    <strong>{it.label}</strong>
                                    {it.configured
                                        ? <span className="ad-pill ad-pill--ok"><CheckCircle2 size={12} /> Terkonfigurasi</span>
                                        : <span className="ad-pill ad-pill--muted"><XCircle size={12} /> Belum diatur</span>}
                                </div>
                                <small>{it.detail}</small>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {tab === 'tampilan' && (
                <div className="ad-set-split">
                    <div className="ad-panel ad-set-col">
                        <div className="ad-panel__head"><h3><Monitor size={15} /> Logo Aplikasi</h3>
                            <small>Satu logo untuk semua portal.</small></div>
                        <div className="ad-set-logo">
                            <img src={logoNow} alt="Logo aplikasi" />
                        </div>
                        <p className="ad-set-note" style={{ marginTop: 10 }}>
                            {props.page_branding.has_custom || preview
                                ? 'Logo kustom aktif — dipakai di sidebar admin, portal mahasiswa, dan halaman login.'
                                : 'Masih memakai logo bawaan. Unggah logo baru untuk menggantinya di semua halaman sekaligus.'}
                        </p>
                        <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden
                            onChange={(e) => {
                                const f = e.target.files?.[0];
                                if (!f) return;
                                setPreview(URL.createObjectURL(f));
                                uploadLogo(f);
                            }} />
                        <div className="ad-set-logoact">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={logoBusy}
                                onClick={() => fileRef.current?.click()}>
                                <Upload size={14} /> {logoBusy ? 'Mengunggah…' : 'Unggah Logo Baru'}
                            </button>
                            {props.page_branding.has_custom && (
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--danger" onClick={resetLogo}>
                                    <Trash2 size={14} /> Kembalikan Bawaan
                                </button>
                            )}
                        </div>
                        {errors.logo && <em className="ad-err">{errors.logo}</em>}
                        <small className="ad-set-note">PNG, JPG, WEBP, atau SVG — maksimal 1 MB. Rasio persegi/mendekati disarankan.</small>
                    </div>
                    <div className="ad-panel ad-set-col--wide">
                        <div className="ad-panel__head"><h3><Eye size={15} /> Pratinjau Penerapan</h3>
                            <small>Titik-titik yang ikut berubah otomatis.</small></div>
                        <ul className="ad-set-applylist">
                            <li><CheckCircle2 size={14} /> Sidebar Portal Admin (React)</li>
                            <li><CheckCircle2 size={14} /> Sidebar dashboard lama (Blade)</li>
                            <li><CheckCircle2 size={14} /> Portal Mahasiswa (React)</li>
                            <li><CheckCircle2 size={14} /> Halaman login &amp; aktivasi</li>
                        </ul>
                        <small className="ad-set-note">Favicon dan materi cetak laporan tetap memakai aset bawaan.</small>
                    </div>
                    <div className="ad-panel ad-set-col--wide">
                        <div className="ad-panel__head"><h3><Zap size={15} /> Label Fitur di Sidebar</h3>
                            <small>Penanda BARU/SOON pada menu baru &amp; fitur dalam pengembangan — semua portal.</small></div>
                        <div className="ad-set-fb">
                            <label className="ad-set-fb__master">
                                <input type="checkbox" checked={fbEnabled} onChange={(e) => setFbEnabled(e.target.checked)} />
                                <span>Tampilkan label fitur</span>
                            </label>
                            <label className="ad-set-fb__until">
                                <span>Label <b>BARU</b> tampil sampai</span>
                                <input type="date" value={fbUntil} onChange={(e) => setFbUntil(e.target.value)} />
                            </label>
                        </div>
                        <div className="ad-set-fb__list">
                            {fb.items.map((it) => (
                                <div className="ad-set-fb__row" key={it.key}>
                                    <span className="ad-set-fb__name">{it.label}</span>
                                    <span className={cn('ad-set-fb__state', it.state === 'new' && 'is-new', it.state === 'soon' && 'is-soon')}>
                                        {it.state === 'new' ? 'BARU' : it.state === 'soon' ? 'SOON' : 'Mati'}
                                    </span>
                                    <select value={fbItems[it.key] ?? 'off'} onChange={(e) => setFbItems({ ...fbItems, [it.key]: e.target.value })}>
                                        <option value="new">Baru</option>
                                        <option value="soon">Soon</option>
                                        <option value="off">Mati</option>
                                    </select>
                                </div>
                            ))}
                        </div>
                        <small className="ad-set-note">
                            BARU berdenyut pelan dan otomatis hilang setelah tanggal di atas (akhir semester).
                            SOON untuk fitur yang sedang dikembangkan.
                        </small>
                        <div className="ad-set-logoact">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={fbBusy} onClick={saveFeatureBadges}>
                                {fbBusy ? 'Menyimpan…' : 'Simpan Label Fitur'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'data' && (
                <div className="ad-set-split">
                    <div className="ad-panel ad-set-col">
                        <div className="ad-panel__head"><h3><CalendarDays size={15} /> Kalender Libur</h3>
                            <small>Tanggal libur absensi — tap mesin ditolak &amp; tanpa alfa.</small></div>
                        <form className="ad-set-inlineform" onSubmit={(e) => {
                            e.preventDefault();
                            liburForm.post(props.urls.libur, {
                                preserveScroll: true,
                                onSuccess: () => liburForm.reset(),
                            });
                        }}>
                            <input type="date" required value={liburForm.data.tanggal}
                                onChange={(e) => liburForm.setData('tanggal', e.target.value)} />
                            <input type="text" placeholder="Keterangan" value={liburForm.data.keterangan}
                                onChange={(e) => liburForm.setData('keterangan', e.target.value)} />
                            <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary" disabled={liburForm.processing}>Tambah</button>
                        </form>
                        {errors.tanggal && <em className="ad-err">{errors.tanggal}</em>}
                        <ul className="ad-set-list">
                            {props.libur.map((l) => (
                                <li key={l.id}>
                                    <span><strong>{l.tanggal_label}</strong> — {l.keterangan ?? 'Libur'}</span>
                                    <button type="button" className="ad-linkbtn ad-linkbtn--danger"
                                        onClick={() => confirm('Hapus tanggal libur ini?') && router.delete(l.url, { preserveScroll: true })}>
                                        Hapus
                                    </button>
                                </li>
                            ))}
                            {props.libur.length === 0 && <li className="ad-set-list__empty">Belum ada tanggal libur</li>}
                        </ul>
                    </div>
                    <div className="ad-panel ad-set-col">
                        <div className="ad-panel__head"><h3><Database size={15} /> Program Studi</h3>
                            <small>Master data prodi mahasiswa.</small></div>
                        <form className="ad-set-inlineform" onSubmit={(e) => {
                            e.preventDefault();
                            prodiForm.post(props.urls.prodi, {
                                preserveScroll: true,
                                onSuccess: () => prodiForm.reset(),
                            });
                        }}>
                            <input type="text" placeholder="Kode" required maxLength={20} value={prodiForm.data.kode}
                                onChange={(e) => prodiForm.setData('kode', e.target.value)} />
                            <input type="text" placeholder="Nama prodi" required value={prodiForm.data.nama}
                                onChange={(e) => prodiForm.setData('nama', e.target.value)} />
                            <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary" disabled={prodiForm.processing}>Tambah</button>
                        </form>
                        {errors.kode && <em className="ad-err">{errors.kode}</em>}
                        <ul className="ad-set-list">
                            {props.prodi.map((pr) => (
                                <li key={pr.id}><span>{pr.nama} <small>({pr.kode})</small></span></li>
                            ))}
                            {props.prodi.length === 0 && <li className="ad-set-list__empty">Belum ada prodi</li>}
                        </ul>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
