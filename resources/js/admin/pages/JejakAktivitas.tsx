import { Head, router } from '@inertiajs/react';
import { Activity, Download, FileSpreadsheet, History, RotateCcw, Search, ShieldCheck, UserRound } from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { JejakAktivitasProps } from '@admin/types';

const ROLE_PILL: Record<string, string> = {
    admin: 'ad-pill--info', laboran: 'ad-pill--ok',
};

export default function JejakAktivitas(props: JejakAktivitasProps) {
    const f = props.filters;
    const meta = props.logs.meta;

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params }, { preserveState: true, preserveScroll: true, replace: true });

    const exportUrl = (format: 'csv' | 'xls') => {
        const p = new URLSearchParams({ format });
        (['q', 'aksi', 'aktor', 'dari', 'sampai'] as const).forEach((k) => { if (f[k]) p.set(k, f[k]); });
        return `${props.urls.export}?${p.toString()}`;
    };

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Jejak Aktivitas"
            subtitle="Audit aksi admin &amp; laboran — siapa melakukan apa, kapan">
            <Head title="Jejak Aktivitas" />

            <div className="ad-log-hero">
                <div>
                    <h1>Jejak Aktivitas Panel</h1>
                    <p>Catatan transparan semua aksi admin dan laboran: kelola ruangan, keputusan pengajuan, binding perangkat, dan petugas.</p>
                    <div className="ad-log-hero__chips">
                        <span><Activity size={13} /> {meta.total.toLocaleString('id-ID')} aksi tercatat</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <History size={64} strokeWidth={1} style={{ color: 'rgba(255,255,255,.25)' }} />
                </div>
            </div>

            <section className="ad-panel">
                <div className="ad-panel__head">
                    <div><h2>Filter</h2></div>
                    <div className="ad-mesin-btnrow">
                        <a href={exportUrl('csv')} className="ad-mesin-btn ad-mesin-btn--ghost" title="Unduh CSV — sesuai filter aktif"><Download size={14} /> CSV</a>
                        <a href={exportUrl('xls')} className="ad-mesin-btn ad-mesin-btn--ghost" title="Unduh Excel — sesuai filter aktif"><FileSpreadsheet size={14} /> Excel</a>
                        <a href={props.urls.index} className="ad-mesin-btn ad-mesin-btn--ghost"><RotateCcw size={14} /> Reset</a>
                    </div>
                </div>
                <form className="ad-log-filter" onSubmit={(e) => {
                    e.preventDefault();
                    const fd = new FormData(e.currentTarget);
                    nav({
                        q: fd.get('q') || undefined, aksi: fd.get('aksi') || undefined,
                        aktor: fd.get('aktor') || undefined, dari: fd.get('dari') || undefined,
                        sampai: fd.get('sampai') || undefined, page: undefined,
                    });
                }}>
                    <div className="ad-usr-search">
                        <Search size={15} />
                        <input name="q" defaultValue={f.q} placeholder="Cari aktor, target, atau detail…" />
                    </div>
                    <select name="aksi" defaultValue={f.aksi}>
                        <option value="">Semua Aksi</option>
                        {props.aksi_options.map((o) => <option key={o.key} value={o.key}>{o.label}</option>)}
                    </select>
                    <select name="aktor" defaultValue={f.aktor}>
                        <option value="">Semua Role</option>
                        {props.aktor_options.map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                    <input type="date" name="dari" defaultValue={f.dari} />
                    <input type="date" name="sampai" defaultValue={f.sampai} />
                    <button className="ad-mesin-btn" type="submit">Terapkan</button>
                </form>

                <div className="ad-table-wrap">
                    <table className="ad-table">
                        <thead><tr>
                            <th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Target</th><th>Detail</th><th>IP</th>
                        </tr></thead>
                        <tbody>
                            {props.logs.data.length === 0 && (
                                <tr><td colSpan={6} className="ad-empty">Belum ada aktivitas yang tercatat.</td></tr>
                            )}
                            {props.logs.data.map((l) => (
                                <tr key={l.id}>
                                    <td style={{ whiteSpace: 'nowrap' }}>{l.waktu}</td>
                                    <td>
                                        <div className="ad-feed-user">
                                            <span className="ad-feed-ava"><UserRound size={13} /></span>
                                            <div>
                                                <strong>{l.aktor}</strong>
                                                <small>
                                                    <span className={cn('ad-pill', ROLE_PILL[l.role] ?? '')}>{l.role}</span>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span className="ad-pill ad-pill--info">
                                            <ShieldCheck size={10} /> {l.aksi_label}
                                        </span>
                                    </td>
                                    <td>{l.target ?? '—'}</td>
                                    <td><small>{l.detail ?? '—'}</small></td>
                                    <td><small className="ad-mesin-mono">{l.ip ?? '—'}</small></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="ad-mesin-pager">
                    <small>Menampilkan {meta.from ?? 0}–{meta.to ?? 0} dari {meta.total} aktivitas</small>
                    <div className="ad-mesin-pager__links">
                        <button className={cn('ad-mesin-page', meta.page <= 1 && 'is-disabled')}
                            onClick={() => meta.page > 1 && nav({ page: meta.page - 1 })}>‹</button>
                        <span className="ad-mesin-page is-on">{meta.page}</span>
                        <button className={cn('ad-mesin-page', meta.page >= meta.last_page && 'is-disabled')}
                            onClick={() => meta.page < meta.last_page && nav({ page: meta.page + 1 })}>›</button>
                    </div>
                </div>
            </section>
        </AdminShell>
    );
}
