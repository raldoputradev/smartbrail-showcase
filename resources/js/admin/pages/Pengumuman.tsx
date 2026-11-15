import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Archive, BellRing, CheckCircle2, Clock, Eye, FileEdit, Megaphone,
    Pencil, Plus, Power, Search, Trash2, Users as UsersIcon, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { AnnRow, PengumumanProps, SharedProps } from '@admin/types';

const PRIO_PILL: Record<string, string> = {
    normal: 'ad-pill--info', penting: 'ad-pill--pending', urgent: 'ad-pill--danger',
};
const PRIO_LABEL: Record<string, string> = { normal: 'Normal', penting: 'Penting', urgent: 'Urgent' };
const STATUS_PILL: Record<string, string> = {
    aktif: 'ad-pill--ok', draft: 'ad-pill--muted', kadaluarsa: 'ad-pill--off',
};
const STATUS_LABEL: Record<string, string> = {
    aktif: 'Aktif', draft: 'Draft', kadaluarsa: 'Kadaluarsa',
};

type Composer = {
    id: number | null;
    judul: string; isi: string; target: string;
    prioritas: string; expires_at: string;
};

const kosong: Composer = { id: null, judul: '', isi: '', target: 'semua', prioritas: 'normal', expires_at: '' };

export default function Pengumuman(props: PengumumanProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const f = props.filters;
    const meta = props.items.meta;

    const [tab, setTab] = useState<'daftar' | 'buat' | 'arsip'>('daftar');
    const [busy, setBusy] = useState(false);
    const [composer, setComposer] = useState<Composer | null>(null);
    const [confirm, setConfirm] = useState<{ row: AnnRow; aksi: 'hapus' | 'toggle' } | null>(null);
    const [detail, setDetail] = useState<AnnRow | null>(null);

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params },
            { preserveState: true, preserveScroll: true, replace: true });

    const post = (url: string, data: Record<string, unknown>, onDone?: () => void) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) {
                    setConfirm(null); onDone?.();
                }
            },
        });
    };

    const openComposer = (row: AnnRow | null) => {
        setComposer(row ? {
            id: row.id, judul: row.judul, isi: row.isi,
            target: row.target, prioritas: row.prioritas,
            expires_at: row.expires_input ?? '',
        } : { ...kosong });
    };

    const submitComposer = (action: 'draft' | 'publish') => {
        if (!composer) return;
        const payload: Record<string, unknown> = {
            judul: composer.judul, isi: composer.isi,
            target: composer.target, prioritas: composer.prioritas,
            expires_at: composer.expires_at || '', action,
        };
        if (composer.id === null) {
            post(props.urls.store, payload, () => setComposer(null));
        } else {
            post(`${props.urls.base}${composer.id}`, { ...payload, _method: 'put' }, () => setComposer(null));
        }
    };

    const s = props.stats;
    const delta = s.delta_vs_bulan_lalu;
    const kpis = [
        { label: 'Total Pengumuman', value: s.total, sub: `${delta >= 0 ? '+' : ''}${delta} dibanding bulan lalu`, icon: <Megaphone size={18} />, accent: '#0284c7' },
        { label: 'Aktif', value: s.aktif, sub: `${s.aktif_pct}% dari total`, icon: <CheckCircle2 size={18} />, accent: '#059669' },
        { label: 'Draft', value: s.draft, sub: 'Belum dipublikasikan', icon: <Clock size={18} />, accent: '#d97706' },
        { label: 'Kadaluarsa', value: s.kadaluarsa, sub: `${s.kadaluarsa_pct}% dari total`, icon: <Archive size={18} />, accent: '#7c3aed' },
    ];

    const pages = useMemo(() => {
        const out: number[] = [];
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === 1 || i === meta.last_page || Math.abs(i - meta.page) <= 1) out.push(i);
        }
        return out;
    }, [meta]);

    const goBuat = () => { setTab('buat'); setComposer({ ...kosong }); };

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Pengumuman"
            subtitle="Kelola pengumuman laboratorium, informasi kegiatan, dan pemberitahuan resmi">
            <Head title="Manajemen Pengumuman" />

            <div className="ad-log-hero">
                <div>
                    <h1>Manajemen Pengumuman</h1>
                    <p>Buat, kelola, dan bagikan pengumuman kepada pengguna laboratorium secara cepat, terarah, dan terdokumentasi.</p>
                    <div className="ad-log-hero__chips">
                        <span><BellRing size={11} /> {s.aktif} aktif</span>
                        <span><FileEdit size={11} /> {s.draft} draft</span>
                        <span><UsersIcon size={11} /> Target terarah per role</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <svg viewBox="0 0 200 120" fill="none">
                        <path d="M40 55l60-30v60l-60-30z" fill="rgba(255,255,255,.25)" stroke="rgba(255,255,255,.5)" />
                        <rect x="30" y="48" width="14" height="34" rx="5" fill="rgba(255,255,255,.45)" />
                        <path d="M118 35q14 20 0 40M132 25q22 30 0 60" stroke="rgba(255,255,255,.5)" strokeWidth="4" strokeLinecap="round" />
                    </svg>
                </div>
            </div>

            {flash.success && <div className="ad-mesin-flash ad-mesin-flash--ok"><CheckCircle2 /> {flash.success}</div>}
            {flash.status && <div className="ad-mesin-flash ad-mesin-flash--ok"><CheckCircle2 /> {flash.status}</div>}
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
                <button className={cn('ad-rg-tab', tab === 'daftar' && 'is-active')} onClick={() => setTab('daftar')}>Daftar Pengumuman</button>
                {props.can.create && (
                    <button className={cn('ad-rg-tab', tab === 'buat' && 'is-active')} onClick={goBuat}>
                        <Plus size={13} /> Buat Pengumuman
                    </button>
                )}
                <button className={cn('ad-rg-tab', tab === 'arsip' && 'is-active')} onClick={() => setTab('arsip')}>
                    Arsip &amp; Kadaluarsa {(s.draft + s.kadaluarsa) > 0 && <span className="ad-rg-tab__n">{s.draft + s.kadaluarsa}</span>}
                </button>
            </div>

            {tab === 'daftar' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Daftar Pengumuman</h3>
                            <small>{meta.total} pengumuman terdaftar</small>
                        </div>
                        {props.can.create && (
                            <button className="ad-mesin-btn ad-mesin-btn--primary" onClick={goBuat}>
                                <Plus size={14} /> Buat Pengumuman
                            </button>
                        )}
                    </div>
                    <div className="ad-panel__body">
                        <form className="ad-mesin-filters" onSubmit={(e) => {
                            e.preventDefault();
                            const fd = new FormData(e.currentTarget);
                            nav({
                                q: fd.get('q') || undefined, target: fd.get('target') || undefined,
                                prioritas: fd.get('prioritas') || undefined, status: fd.get('status') || undefined,
                                from: fd.get('from') || undefined, to: fd.get('to') || undefined,
                                page: undefined,
                            });
                        }}>
                            <label className="ad-mesin-search"><Search />
                                <input name="q" defaultValue={f.q} placeholder="Cari judul atau isi pengumuman…" />
                            </label>
                            <select name="target" defaultValue={f.target}>
                                <option value="">Semua Target</option>
                                <option value="semua">Semua Pengguna</option>
                                <option value="dosen">Dosen</option>
                                <option value="mahasiswa">Mahasiswa</option>
                            </select>
                            <select name="prioritas" defaultValue={f.prioritas}>
                                <option value="">Semua Prioritas</option>
                                <option value="normal">Normal</option>
                                <option value="penting">Penting</option>
                                <option value="urgent">Urgent</option>
                            </select>
                            <select name="status" defaultValue={f.status}>
                                <option value="">Semua Status</option>
                                <option value="aktif">Aktif</option>
                                <option value="draft">Draft</option>
                                <option value="kadaluarsa">Kadaluarsa</option>
                            </select>
                            <input type="date" name="from" defaultValue={f.from} aria-label="Dari tanggal" className="ad-ann-date" />
                            <input type="date" name="to" defaultValue={f.to} aria-label="Sampai tanggal" className="ad-ann-date" />
                            <button className="ad-mesin-btn" type="submit">Filter</button>
                        </form>

                        <div className="ad-mesin-tablewrap">
                            <div className="ad-table-wrap ad-rg-freewrap">
                                <table className="ad-table">
                                    <thead><tr>
                                        <th>Judul Pengumuman</th><th>Target</th><th>Prioritas</th>
                                        <th>Status</th><th>Tanggal Publikasi</th><th>Tanggal Berakhir</th>
                                        <th className="is-right">Aksi</th>
                                    </tr></thead>
                                    <tbody>
                                        {props.items.data.length === 0 && (
                                            <tr><td colSpan={7} className="ad-empty">Belum ada pengumuman yang cocok.</td></tr>
                                        )}
                                        {props.items.data.map((a) => (
                                            <tr key={a.id}>
                                                <td>
                                                    <div className="ad-feed-user">
                                                        <span className="ad-rg-thumb"><Megaphone size={15} /></span>
                                                        <div><strong>{a.judul}</strong><small className="ad-mesin-muted">{a.preview}</small></div>
                                                    </div>
                                                </td>
                                                <td><span className="ad-rg-chip">{a.target_label}</span></td>
                                                <td><span className={cn('ad-pill', PRIO_PILL[a.prioritas] ?? 'ad-pill--muted')}>{PRIO_LABEL[a.prioritas] ?? a.prioritas}</span></td>
                                                <td><span className={cn('ad-pill', STATUS_PILL[a.status])}>{STATUS_LABEL[a.status]}</span></td>
                                                <td>{a.created ?? '—'}</td>
                                                <td>{a.expires ?? '—'}</td>
                                                <td className="is-right">
                                                    <div className="ad-mesin-actions" style={{ justifyContent: 'flex-end' }}>
                                                        <button className="ad-mesin-ic" title="Lihat" onClick={() => setDetail(a)}><Eye /></button>
                                                        {props.can.update && (
                                                            <>
                                                                <button className="ad-mesin-ic" title="Edit"
                                                                    onClick={() => { openComposer(a); setTab('buat'); }}><Pencil /></button>
                                                                <button className="ad-mesin-ic" title={a.status === 'draft' ? 'Publikasikan' : 'Jadikan draft'}
                                                                    onClick={() => setConfirm({ row: a, aksi: 'toggle' })}><Power /></button>
                                                            </>
                                                        )}
                                                        {props.can.delete && (
                                                            <button className="ad-mesin-ic ad-mesin-ic--danger" title="Hapus"
                                                                onClick={() => setConfirm({ row: a, aksi: 'hapus' })}><Trash2 /></button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="ad-mesin-cards">
                                {props.items.data.map((a) => (
                                    <div className="ad-mesin-card" key={a.id}>
                                        <div className="ad-mesin-card__head">
                                            <div className="ad-feed-user">
                                                <span className="ad-rg-thumb"><Megaphone size={15} /></span>
                                                <div><strong>{a.judul}</strong><small className="ad-mesin-muted">{a.preview}</small></div>
                                            </div>
                                            <span className={cn('ad-pill', STATUS_PILL[a.status])}>{STATUS_LABEL[a.status]}</span>
                                        </div>
                                        <div className="ad-mesin-card__meta">
                                            <span className="ad-mesin-sub"><UsersIcon size={10} /> {a.target_label} · <Clock size={10} /> {a.created}</span>
                                        </div>
                                        <div className="ad-mesin-actions">
                                            <button className="ad-mesin-btn ad-mesin-btn--sm" onClick={() => setDetail(a)}><Eye size={13} /> Lihat</button>
                                            {props.can.update && (
                                                <button className="ad-mesin-btn ad-mesin-btn--sm"
                                                    onClick={() => { openComposer(a); setTab('buat'); }}><Pencil size={13} /> Edit</button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="ad-mesin-pager">
                            <small>Menampilkan {meta.from}–{meta.to} dari {meta.total} pengumuman</small>
                            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                                <select value={f.per_page} onChange={(e) => nav({ per_page: e.target.value, page: undefined })}
                                    aria-label="Per halaman">
                                    {[10, 20, 50].map((n) => <option key={n} value={n}>Tampilkan {n}</option>)}
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
            )}

            {tab === 'buat' && composer && (
                <div className="ad-panel ad-ann-composer">
                    <div className="ad-panel__head">
                        <div>
                            <h3>{composer.id === null ? 'Buat Pengumuman Baru' : 'Edit Pengumuman'}</h3>
                            <small>{composer.id === null ? 'Publikasikan langsung atau simpan sebagai draft' : 'Perbarui isi, target, dan masa berlaku'}</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <form className="ad-ann-form" onSubmit={(e) => e.preventDefault()}>
                            <div className="ad-mesin-field">
                                <label>Judul Pengumuman <em>*</em></label>
                                <input value={composer.judul} required maxLength={200}
                                    placeholder="Judul pengumuman…"
                                    onChange={(e) => setComposer({ ...composer, judul: e.target.value })} />
                                {errors.judul && <span className="ad-mesin-err">{errors.judul}</span>}
                            </div>
                            <div className="ad-mesin-field">
                                <label>Isi Pengumuman <em>*</em></label>
                                <textarea rows={6} value={composer.isi} required
                                    placeholder="Tulis isi pengumuman secara lengkap dan jelas…"
                                    onChange={(e) => setComposer({ ...composer, isi: e.target.value })} />
                                {errors.isi && <span className="ad-mesin-err">{errors.isi}</span>}
                            </div>
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field">
                                    <label>Target Penerima <em>*</em></label>
                                    <select value={composer.target} onChange={(e) => setComposer({ ...composer, target: e.target.value })}>
                                        <option value="semua">Semua Pengguna</option>
                                        <option value="dosen">Dosen</option>
                                        <option value="mahasiswa">Mahasiswa</option>
                                    </select>
                                    {errors.target && <span className="ad-mesin-err">{errors.target}</span>}
                                </div>
                                <div className="ad-mesin-field">
                                    <label>Prioritas <em>*</em></label>
                                    <select value={composer.prioritas} onChange={(e) => setComposer({ ...composer, prioritas: e.target.value })}>
                                        <option value="normal">Normal</option>
                                        <option value="penting">Penting</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div className="ad-mesin-field" style={{ maxWidth: 320 }}>
                                <label>Berlaku Sampai (opsional)</label>
                                <input type="datetime-local" value={composer.expires_at}
                                    onChange={(e) => setComposer({ ...composer, expires_at: e.target.value })} />
                                <small>Kosongkan untuk pengumuman tanpa batas waktu.</small>
                                {errors.expires_at && <span className="ad-mesin-err">{errors.expires_at}</span>}
                            </div>
                            <div className="ad-mesin-panel__foot">
                                <button className="ad-mesin-btn ad-mesin-btn--ghost" disabled={busy}
                                    onClick={() => submitComposer('draft')}>
                                    <FileEdit size={14} /> Simpan Draft
                                </button>
                                <button className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}
                                    onClick={() => submitComposer('publish')}>
                                    <Megaphone size={14} /> {busy ? 'Menyimpan…' : 'Publikasikan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {tab === 'arsip' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Arsip &amp; Kadaluarsa</h3>
                            <small>Pengumuman draft atau yang sudah melewati masa berlaku</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <div className="ad-pgj-riwayat">
                            {props.arsip.length === 0 && <div className="ad-empty">Tidak ada arsip — semua pengumuman masih aktif.</div>}
                            {props.arsip.map((a) => (
                                <div className="ad-pgj-riv" key={a.id}>
                                    <span className={cn('ad-dot', a.status === 'draft' ? 'ad-dot--warn' : 'ad-dot--off')} />
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <strong>{a.judul}</strong>
                                        <small>{a.target_label} · oleh {a.author} · dibuat {a.created ?? '—'}</small>
                                        <small>{a.status === 'draft' ? 'Belum dipublikasikan' : `Berakhir ${a.expires ?? '—'}`}</small>
                                    </div>
                                    {props.can.update && (
                                        <button className="ad-mesin-btn ad-mesin-btn--sm"
                                            onClick={() => {
                                                const row = props.items.data.find((x) => x.id === a.id);
                                                if (row) { openComposer(row); setTab('buat'); }
                                                else nav({ q: undefined, status: undefined, page: undefined, id: a.id });
                                            }}>
                                            <Pencil size={13} /> Kelola
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {detail && (
                <div className="ad-mesin-scrim" onClick={() => setDetail(null)}>
                    <div className="ad-mesin-setup" onClick={(e) => e.stopPropagation()}>
                        <h4>{detail.judul}</h4>
                        <p style={{ color: 'var(--ad-muted)', fontSize: 12, marginBottom: 10 }}>
                            {detail.target_label} · {detail.author} · {detail.created ?? '—'}
                            {detail.expires ? ` · Berakhir ${detail.expires}` : ''}
                        </p>
                        <p style={{ whiteSpace: 'pre-wrap', fontSize: 13, lineHeight: 1.6 }}>{detail.isi}</p>
                        <div className="ad-mesin-confirm__actions" style={{ marginTop: 16 }}>
                            <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setDetail(null)}>Tutup</button>
                        </div>
                    </div>
                </div>
            )}

            {confirm && (
                <div className="ad-mesin-scrim" onClick={() => setConfirm(null)}>
                    <div className="ad-mesin-confirm" onClick={(e) => e.stopPropagation()}>
                        <h4>{confirm.aksi === 'hapus' ? 'Hapus pengumuman ini?' : 'Ubah status pengumuman?'}</h4>
                        <p>{confirm.aksi === 'hapus'
                            ? `“${confirm.row.judul}” akan dihapus permanen dan tidak bisa dikembalikan.`
                            : `“${confirm.row.judul}” akan ${confirm.row.status === 'draft' ? 'dipublikasikan' : 'diubah menjadi draft'}.`}</p>
                        <div className="ad-mesin-confirm__actions">
                            <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setConfirm(null)}>Batal</button>
                            <button className={cn('ad-mesin-btn', confirm.aksi === 'hapus' ? 'ad-mesin-btn--danger' : 'ad-mesin-btn--primary')}
                                disabled={busy}
                                onClick={() => confirm.aksi === 'hapus'
                                    ? post(`${props.urls.base}${confirm.row.id}`, { _method: 'delete' })
                                    : post(`${props.urls.base}${confirm.row.id}/toggle`, {})}>
                                {busy ? 'Memproses…' : 'Ya, lanjutkan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
