import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    BarChart3, CheckCircle2, Clock, Flag, Inbox, Mail,
    MessageSquare, Pencil, RefreshCcw, Reply, Search, Trash2, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { SaranMasukanProps, SharedProps, SugRow } from '@admin/types';

const STATUS_LABEL: Record<string, string> = {
    baru: 'Baru', dibaca: 'Diproses', ditanggapi: 'Ditanggapi',
};
const STATUS_PILL: Record<string, string> = {
    baru: 'ad-pill--pending', dibaca: 'ad-pill--info', ditanggapi: 'ad-pill--ok',
};

function initials(nama: string) {
    return nama.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

export default function SaranMasukan(props: SaranMasukanProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const f = props.filters;
    const meta = props.items.meta;
    const sel = props.selected;

    const [tab, setTab] = useState<'daftar' | 'statistik'>('daftar');
    const [busy, setBusy] = useState(false);
    const [balas, setBalas] = useState<{ mode: 'create' | 'edit'; text: string } | null>(null);
    const [confirm, setConfirm] = useState<'hapus' | 'hapus-balasan' | null>(null);

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params },
            { preserveState: true, preserveScroll: true, replace: true });

    const post = (url: string, data: Record<string, unknown> = {}) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) {
                    setConfirm(null); setBalas(null);
                }
            },
        });
    };

    const pilih = (row: SugRow) => nav({ id: row.id });
    const ubahStatus = (status: string) => sel && post(`${props.urls.base}${sel.id}`, { _method: 'patch', status });
    const kirimBalasan = (mode: 'create' | 'edit', text: string) =>
        sel && post(`${props.urls.base}${sel.id}/reply`, { admin_reply: text, mode });
    const hapusBalasan = () => sel && post(`${props.urls.base}${sel.id}/reply`, { _method: 'delete' });
    const hapus = () => sel && post(`${props.urls.base}${sel.id}`, { _method: 'delete' });

    const s = props.stats;
    const delta = s.delta_vs_bulan_lalu;
    const kpis = [
        { label: 'Total Laporan', value: s.total, sub: `${delta >= 0 ? '+' : ''}${delta} dibanding periode sebelumnya`, icon: <Inbox size={18} />, accent: '#0284c7' },
        { label: 'Baru', value: s.baru, sub: 'Perlu ditinjau', icon: <Mail size={18} />, accent: '#e11d48' },
        { label: 'Diproses', value: s.diproses, sub: 'Sedang ditangani', icon: <Clock size={18} />, accent: '#d97706' },
        { label: 'Ditanggapi', value: s.ditanggapi, sub: 'Sudah diselesaikan', icon: <CheckCircle2 size={18} />, accent: '#059669' },
    ];

    const pages = useMemo(() => {
        const out: number[] = [];
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === 1 || i === meta.last_page || Math.abs(i - meta.page) <= 1) out.push(i);
        }
        return out;
    }, [meta]);

    const maxTren = Math.max(1, ...props.analisis.tren.values);
    const maxSumber = Math.max(1, ...props.analisis.per_sumber.map((x) => x.n));

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Saran & Masukan"
            subtitle="Kelola saran, masukan, dan laporan dari pengguna (mahasiswa, dosen, dan laboran)">
            <Head title="Pusat Saran & Masukan" />

            <div className="ad-log-hero">
                <div>
                    <h1>Pusat Saran &amp; Masukan</h1>
                    <p>Terima, tindak lanjuti, dan pantau seluruh saran, masukan, serta laporan pengguna secara terstruktur dan transparan.</p>
                    <div className="ad-log-hero__chips">
                        <span><Inbox size={11} /> {s.total} laporan</span>
                        <span><Clock size={11} /> {s.baru} baru</span>
                        <span><Reply size={11} /> Balasan dikirim ke pelapor</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <svg viewBox="0 0 200 120" fill="none">
                        <rect x="35" y="26" width="120" height="66" rx="12" fill="rgba(255,255,255,.16)" stroke="rgba(255,255,255,.4)" />
                        <path d="M62 92l-14 18 24-18" fill="rgba(255,255,255,.16)" />
                        <rect x="52" y="44" width="80" height="7" rx="3.5" fill="rgba(255,255,255,.4)" />
                        <rect x="52" y="60" width="60" height="7" rx="3.5" fill="rgba(255,255,255,.25)" />
                        <circle cx="150" cy="88" r="18" fill="rgba(5,150,105,.9)" />
                        <path d="M142 88l6 6 10-11" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </div>
            </div>

            {flash.success && <div className="ad-mesin-flash ad-mesin-flash--ok"><CheckCircle2 /> {flash.success}</div>}
            {Object.keys(errors).length > 0 && (
                <div className="ad-mesin-flash ad-mesin-flash--err"><X /> {Object.values(errors)[0] as string}</div>
            )}

            <div className="ad-log-kpis">
                {kpis.map((k) => (
                    <div className="ad-log-kpi" key={k.label}>
                        <span className="ad-log-kpi__ic" style={{ background: `${k.accent}1c`, color: k.accent }}>{k.icon}</span>
                        <div><small>{k.label}</small><strong>{k.value}</strong><em>{k.sub}</em></div>
                    </div>
                ))}
            </div>

            <div className="ad-rg-tabs">
                <button className={cn('ad-rg-tab', tab === 'daftar' && 'is-active')} onClick={() => setTab('daftar')}>
                    Daftar Laporan {s.baru > 0 && <span className="ad-rg-tab__n">{s.baru}</span>}
                </button>
                <button className={cn('ad-rg-tab', tab === 'statistik' && 'is-active')} onClick={() => setTab('statistik')}>
                    <BarChart3 size={13} /> Statistik &amp; Analisis
                </button>
            </div>

            {tab === 'daftar' && (
                <div className="ad-pgj-grid">
                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Daftar Laporan</h3><small>{meta.total} laporan pada filter ini</small></div>
                        </div>
                        <div className="ad-panel__body">
                            <form className="ad-mesin-filters" onSubmit={(e) => {
                                e.preventDefault();
                                const fd = new FormData(e.currentTarget);
                                nav({
                                    q: fd.get('q') || undefined, status: fd.get('status') || undefined,
                                    source: fd.get('source') || undefined,
                                    from: fd.get('from') || undefined, to: fd.get('to') || undefined,
                                    page: undefined, id: undefined,
                                });
                            }}>
                                <label className="ad-mesin-search"><Search />
                                    <input name="q" defaultValue={f.q} placeholder="Cari nama, judul, atau isi laporan…" />
                                </label>
                                <select name="source" defaultValue={f.source}>
                                    <option value="">Semua Sumber</option>
                                    <option value="public">Landing Publik</option>
                                    <option value="mahasiswa">Mahasiswa</option>
                                    <option value="dosen">Dosen</option>
                                </select>
                                <select name="status" defaultValue={f.status}>
                                    <option value="">Semua Status</option>
                                    <option value="baru">Baru</option>
                                    <option value="dibaca">Diproses</option>
                                    <option value="ditanggapi">Ditanggapi</option>
                                </select>
                                <input type="date" name="from" defaultValue={f.from} className="ad-ann-date" aria-label="Dari tanggal" />
                                <input type="date" name="to" defaultValue={f.to} className="ad-ann-date" aria-label="Sampai tanggal" />
                                <button className="ad-mesin-btn" type="submit">Filter</button>
                            </form>

                            <div className="ad-pgj-list">
                                {props.items.data.length === 0 && (
                                    <div className="ad-empty">Tidak ada laporan pada filter ini.</div>
                                )}
                                {props.items.data.map((r) => (
                                    <button key={r.id}
                                        className={cn('ad-pgj-item', sel?.id === r.id && 'is-active')}
                                        onClick={() => pilih(r)}>
                                        <div className="ad-pgj-item__top">
                                            <span className="ad-pgj-kode">{r.kode}{r.baru && <em className="ad-sug-new">BARU</em>}</span>
                                            <span className={cn('ad-pill', STATUS_PILL[r.status] ?? 'ad-pill--muted')}>
                                                {STATUS_LABEL[r.status] ?? r.status}
                                            </span>
                                        </div>
                                        <strong className="ad-pgj-judul">{r.judul}</strong>
                                        <div className="ad-pgj-meta">
                                            <span>{r.ringkasan}</span>
                                        </div>
                                        <div className="ad-pgj-pemohon">
                                            {r.avatar
                                                ? <img src={r.avatar} alt="" className="ad-pgj-ava" style={{ padding: 0, objectFit: 'cover' }} />
                                                : <span className="ad-pgj-ava">{initials(r.pelapor)}</span>}
                                            <span>{r.pelapor} · {r.pelapor_role}</span>
                                            <span className="ad-mesin-muted" style={{ marginLeft: 'auto', fontWeight: 400 }}>{r.tanggal}</span>
                                        </div>
                                    </button>
                                ))}
                            </div>

                            <div className="ad-mesin-pager">
                                <small>Menampilkan {meta.from}–{meta.to} dari {meta.total} laporan</small>
                                <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                                    <select value={f.per_page} onChange={(e) => nav({ per_page: e.target.value, page: undefined })}
                                        aria-label="Per halaman">
                                        {[10, 20, 50].map((n) => <option key={n} value={n}>{n}</option>)}
                                    </select>
                                    <div className="ad-mesin-pager__links">
                                        <button className={cn('ad-mesin-page', meta.page <= 1 && 'is-disabled')}
                                            disabled={meta.page <= 1} onClick={() => nav({ page: meta.page - 1 })}>‹</button>
                                        {pages.map((p) => (
                                            <button key={p} className={cn('ad-mesin-page', p === meta.page && 'is-active')}
                                                onClick={() => nav({ page: p })}>{p}</button>
                                        ))}
                                        <button className={cn('ad-mesin-page', meta.page >= meta.last_page && 'is-disabled')}
                                            disabled={meta.page >= meta.last_page} onClick={() => nav({ page: meta.page + 1 })}>›</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="ad-pgj-side">
                        {sel ? (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div><h3>Detail Laporan</h3><small>{sel.kode}</small></div>
                                    <span className={cn('ad-pill', STATUS_PILL[sel.status] ?? 'ad-pill--muted')}>
                                        {STATUS_LABEL[sel.status] ?? sel.status}
                                    </span>
                                </div>
                                <div className="ad-panel__body ad-pgj-detail">
                                    <div className="ad-pgj-pemohon" style={{ marginTop: 0, marginBottom: 12 }}>
                                        {sel.avatar
                                            ? <img src={sel.avatar} alt="" className="ad-pgj-ava" style={{ padding: 0, objectFit: 'cover' }} />
                                            : <span className="ad-pgj-ava">{initials(sel.pelapor)}</span>}
                                        <div>
                                            <strong style={{ display: 'block' }}>{sel.pelapor}</strong>
                                            <small className="ad-mesin-muted">{sel.pelapor_role} · {sel.tanggal ?? '—'}</small>
                                        </div>
                                    </div>
                                    <div className="ad-mesin-detail">
                                        <div className="ad-mesin-detail__row"><span>Judul</span><strong>{sel.judul}</strong></div>
                                        <div className="ad-mesin-detail__row"><span>Status</span>
                                            <strong>
                                                {props.can.update ? (
                                                    <select value={sel.status} disabled={busy} className="ad-sug-statussel"
                                                        onChange={(e) => ubahStatus(e.target.value)}>
                                                        <option value="baru">Baru</option>
                                                        <option value="dibaca">Diproses</option>
                                                        <option value="ditanggapi">Ditanggapi</option>
                                                    </select>
                                                ) : (STATUS_LABEL[sel.status] ?? sel.status)}
                                            </strong>
                                        </div>
                                    </div>
                                    <div className="ad-sug-isi">
                                        <small>Isi Laporan</small>
                                        <p>{sel.isi}</p>
                                        {sel.lampiran_url && (
                                            <a href={sel.lampiran_url} target="_blank" rel="noreferrer" className="ad-sug-lampiran">
                                                <img src={sel.lampiran_url} alt="Foto bukti laporan" loading="lazy" />
                                                <span>Foto bukti — klik untuk memperbesar</span>
                                            </a>
                                        )}
                                    </div>

                                    <div className="ad-sug-riwayat">
                                        <small>Riwayat Penanganan</small>
                                        <div className="ad-pgj-riv">
                                            <span className="ad-dot ad-dot--ok" />
                                            <div>
                                                <strong>Laporan Dibuat</strong>
                                                <small>Oleh {sel.pelapor} · {sel.tanggal ?? '—'}</small>
                                            </div>
                                        </div>
                                        {sel.balasan && (
                                            <div className="ad-pgj-riv">
                                                <span className="ad-dot ad-dot--ok" />
                                                <div>
                                                    <strong>Ditanggapi</strong>
                                                    <small>Oleh {sel.dibalas_oleh ?? 'Admin'} · {sel.dibalas_pada ?? '—'}</small>
                                                    {sel.user_baca_balasan && <small>Sudah dibaca pelapor</small>}
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {sel.balasan && balas === null && (
                                        <div className="ad-sug-balasan">
                                            <small>Balasan Admin</small>
                                            <p>{sel.balasan}</p>
                                        </div>
                                    )}

                                    {props.can.update && balas === null && (
                                        <div className="ad-pgj-actions">
                                            {props.can.delete && (
                                                <button className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy}
                                                    onClick={() => setConfirm('hapus')}>
                                                    <Flag size={14} /> Hapus / Spam
                                                </button>
                                            )}
                                            {sel.balasan && (
                                                <>
                                                    <button className="ad-mesin-btn" disabled={busy}
                                                        onClick={() => setBalas({ mode: 'edit', text: sel.balasan ?? '' })}>
                                                        <Pencil size={14} /> Edit Balasan
                                                    </button>
                                                    <button className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy}
                                                        onClick={() => setConfirm('hapus-balasan')}>
                                                        <Trash2 size={14} /> Hapus Balasan
                                                    </button>
                                                </>
                                            )}
                                            {!sel.balasan && (
                                                <button className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}
                                                    onClick={() => setBalas({ mode: 'create', text: '' })}>
                                                    <Reply size={14} /> Balas Laporan
                                                </button>
                                            )}
                                        </div>
                                    )}

                                    {props.can.update && balas && (
                                        <div className="ad-mesin-field" style={{ marginTop: 12 }}>
                                            <label>{balas.mode === 'edit' ? 'Edit Balasan' : 'Balas Laporan'}</label>
                                            <textarea rows={5} value={balas.text} maxLength={4000}
                                                placeholder="Tulis balasan yang akan dilihat pelapor…"
                                                onChange={(e) => setBalas({ ...balas, text: e.target.value })} />
                                            {errors.admin_reply && <span className="ad-mesin-err">{errors.admin_reply}</span>}
                                            <div className="ad-mesin-panel__foot" style={{ borderTop: 0, paddingTop: 8 }}>
                                                <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setBalas(null)}>Batal</button>
                                                <button className="ad-mesin-btn ad-mesin-btn--primary"
                                                    disabled={busy || balas.text.trim().length < 2}
                                                    onClick={() => kirimBalasan(balas.mode, balas.text)}>
                                                    {busy ? 'Mengirim…' : 'Kirim Balasan'}
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="ad-panel"><div className="ad-empty">Pilih laporan untuk melihat detail.</div></div>
                        )}
                    </div>
                </div>
            )}

            {tab === 'statistik' && (
                <div className="ad-sug-statgrid">
                    <div className="ad-panel">
                        <div className="ad-panel__head"><div><h3>Tren Laporan 6 Bulan</h3><small>Jumlah laporan masuk per bulan</small></div></div>
                        <div className="ad-panel__body">
                            <div className="ad-sug-bars">
                                {props.analisis.tren.labels.map((l, i) => (
                                    <div className="ad-sug-bar" key={l + i}>
                                        <span className="ad-sug-bar__n">{props.analisis.tren.values[i]}</span>
                                        <div className="ad-sug-bar__fill" style={{ height: `${Math.round(props.analisis.tren.values[i] / maxTren * 100)}%` }} />
                                        <small>{l}</small>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="ad-panel">
                        <div className="ad-panel__head"><div><h3>Distribusi</h3><small>Per sumber &amp; per status</small></div></div>
                        <div className="ad-panel__body">
                            <div className="ad-sug-dist">
                                {props.analisis.per_sumber.map((r) => (
                                    <div className="ad-sug-distrow" key={r.label}>
                                        <span>{r.label}</span>
                                        <div className="ad-sug-disttrack"><div style={{ width: `${r.n / maxSumber * 100}%` }} /></div>
                                        <strong>{r.n}</strong>
                                    </div>
                                ))}
                            </div>
                            <div className="ad-rg-chips" style={{ marginTop: 14 }}>
                                {props.analisis.per_status.map((r) => (
                                    <span key={r.label} className="ad-rg-chip">{r.label}: {r.n}</span>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="ad-panel">
                        <div className="ad-panel__head"><div><h3>Waktu Respons</h3><small>Rata-rata dari laporan dibuat hingga dibalas</small></div></div>
                        <div className="ad-panel__body ad-sug-respon">
                            {props.analisis.respon_jam !== null ? (
                                <>
                                    <strong>{props.analisis.respon_jam < 24
                                        ? `${props.analisis.respon_jam} jam`
                                        : `${Math.round(props.analisis.respon_jam / 24 * 10) / 10} hari`}</strong>
                                    <small>rata-rata waktu tanggapan admin</small>
                                </>
                            ) : <div className="ad-empty">Belum ada balasan tercatat.</div>}
                        </div>
                    </div>
                </div>
            )}

            {confirm && (
                <div className="ad-mesin-scrim" onClick={() => setConfirm(null)}>
                    <div className="ad-mesin-confirm" onClick={(e) => e.stopPropagation()}>
                        <h4>{confirm === 'hapus' ? 'Hapus laporan ini?' : 'Hapus balasan?'}</h4>
                        <p>{confirm === 'hapus'
                            ? 'Laporan akan dihapus permanen (ditandai spam/tidak relevan) dan tidak bisa dikembalikan.'
                            : 'Balasan admin akan dihapus — status laporan kembali ke Diproses.'}</p>
                        <div className="ad-mesin-confirm__actions">
                            <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setConfirm(null)}>Batal</button>
                            <button className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy}
                                onClick={() => confirm === 'hapus' ? hapus() : hapusBalasan()}>
                                {busy ? 'Memproses…' : 'Ya, lanjutkan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
