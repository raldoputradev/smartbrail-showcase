import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft, CheckCircle2, Clock, DoorOpen, Layers, ShieldAlert,
    ShieldCheck, TriangleAlert, XCircle,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { RuanganAktivitasProps } from '@admin/types';

const JENIS_PILL: Record<string, string> = {
    buka: 'ad-pill--ok', check: 'ad-pill--info', tolak: 'ad-pill--off',
    telemetri: 'ad-pill--info', pintu_paksa: 'ad-pill--warn',
    ajar: 'ad-pill--warn', manual: 'ad-pill--info', tutup: 'ad-pill--off',
};

export default function RuanganAktivitas(props: RuanganAktivitasProps) {
    const f = props.filters;
    const meta = props.events.meta;
    const r = props.ruangan;

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params }, { preserveState: true, preserveScroll: true, replace: true });

    const kpis = [
        { label: 'Total Event', value: props.stats.total, icon: <Layers size={17} />, accent: '#0284c7' },
        { label: 'Bulan Ini', value: props.stats.bulan_ini, icon: <Clock size={17} />, accent: '#7c3aed' },
        { label: 'Diizinkan', value: props.stats.izinkan, icon: <CheckCircle2 size={17} />, accent: '#059669' },
        { label: 'Ditolak', value: props.stats.tolak, icon: <XCircle size={17} />, accent: '#dc2626' },
        { label: 'Pintu Dipaksa', value: props.stats.paksa, icon: <ShieldAlert size={17} />, accent: '#d97706' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif} title={`Aktivitas ${r.nama}`}
            subtitle="Riwayat akses dan kejadian perangkat ruangan ini">
            <Head title={`Aktivitas ${r.nama}`} />

            <div className="ad-log-hero">
                <div>
                    <a href={props.urls.back} className="ad-mesin-btn ad-mesin-btn--ghost ad-mesin-btn--sm" style={{ marginBottom: 10 }}>
                        <ArrowLeft size={13} /> Daftar Ruangan
                    </a>
                    <h1>Riwayat {r.nama}</h1>
                    <p>{r.kode}{r.lokasi ? ` · ${r.lokasi}` : ''} — semua event akses, keputusan izin, dan kejadian pintu.</p>
                    <div className="ad-log-hero__chips">
                        <span><ShieldCheck size={13} /> {props.stats.bulan_ini.toLocaleString('id-ID')} event bulan ini</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    {r.foto_url
                        ? <img src={r.foto_url} alt={r.nama} style={{ width: 190, height: 110, objectFit: 'cover', borderRadius: 12, border: '1px solid rgba(255,255,255,.3)' }} />
                        : <DoorOpen size={64} strokeWidth={1} style={{ color: 'rgba(255,255,255,.25)' }} />}
                </div>
            </div>

            <div className="ad-log-kpis">
                {kpis.map((k) => (
                    <div key={k.label} className="ad-log-kpi">
                        <span className="ad-log-kpi__ic" style={{ color: k.accent, background: `${k.accent}14` }}>{k.icon}</span>
                        <div><small>{k.label}</small><strong>{k.value.toLocaleString('id-ID')}</strong></div>
                    </div>
                ))}
            </div>

            <section className="ad-panel">
                <div className="ad-panel__head"><div><h2>Filter</h2></div></div>
                <form className="ad-log-filter" onSubmit={(e) => {
                    e.preventDefault();
                    const fd = new FormData(e.currentTarget);
                    nav({ jenis: fd.get('jenis') || undefined, dari: fd.get('dari') || undefined, sampai: fd.get('sampai') || undefined, page: undefined });
                }}>
                    <select name="jenis" defaultValue={f.jenis}>
                        <option value="">Semua Jenis</option>
                        {props.jenis_options.map((j) => <option key={j} value={j}>{j}</option>)}
                    </select>
                    <input type="date" name="dari" defaultValue={f.dari} />
                    <input type="date" name="sampai" defaultValue={f.sampai} />
                    <button className="ad-mesin-btn" type="submit">Terapkan</button>
                </form>

                <div className="ad-table-wrap">
                    <table className="ad-table">
                        <thead><tr>
                            <th>Waktu</th><th>Subjek</th><th>Kejadian</th><th>Hasil</th><th>Mesin</th><th>Keterangan</th>
                        </tr></thead>
                        <tbody>
                            {props.events.data.length === 0 && (
                                <tr><td colSpan={6} className="ad-empty">Belum ada aktivitas tercatat untuk ruangan ini.</td></tr>
                            )}
                            {props.events.data.map((e) => (
                                <tr key={e.id} className={cn(e.jenis === 'pintu_paksa' && 'is-warnrow')}>
                                    <td style={{ whiteSpace: 'nowrap' }}>{e.waktu}</td>
                                    <td><strong>{e.nama}</strong></td>
                                    <td>
                                        <span className={cn('ad-pill', JENIS_PILL[e.jenis] ?? 'ad-pill--info')}>
                                            {e.jenis === 'pintu_paksa' && <TriangleAlert size={10} />}
                                            {e.jenis_label}
                                        </span>
                                    </td>
                                    <td>{e.hasil ?? '—'}</td>
                                    <td><small className="ad-mesin-mono">{e.mesin ?? '—'}</small></td>
                                    <td><small>{e.keterangan ?? '—'}</small></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="ad-mesin-pager">
                    <small>Menampilkan {meta.from ?? 0}–{meta.to ?? 0} dari {meta.total} event</small>
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
