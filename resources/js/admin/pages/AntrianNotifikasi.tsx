import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Bell, CalendarClock, CheckCircle2, ChevronLeft, ChevronRight, Clock,
    Eye, Filter, HelpCircle, Hourglass, Layers, Search, Send, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { PushToggle } from '@admin/components/PushToggle';
import { asset } from '@admin/lib/asset';
import { cn } from '@admin/lib/utils';
import type { AntrianNotifikasiProps, NotifQueueItem, SharedProps } from '@admin/types';

const PRIO_PILL: Record<string, string> = {
    Urgent: 'ad-pill--danger', Tinggi: 'ad-pill--pending', Normal: 'ad-pill--info',
};
const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export default function AntrianNotifikasi(props: AntrianNotifikasiProps) {
    const { flash } = usePage<SharedProps>().props;
    const [tab, setTab] = useState<'antrian' | 'terjadwal' | 'riwayat'>('antrian');
    const [sel, setSel] = useState<NotifQueueItem | null>(props.items[0] ?? null);
    const [q, setQ] = useState('');
    const [fTarget, setFTarget] = useState('');
    const [fPrio, setFPrio] = useState('');
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [help, setHelp] = useState(false);

    const targets = useMemo(() => [...new Set(props.items.map((i) => i.target))], [props.items]);
    const filtered = useMemo(() => props.items.filter((i) => {
        const hay = `${i.judul} ${i.isi}`.toLowerCase();
        return (!q || hay.includes(q.toLowerCase()))
            && (!fTarget || i.target === fTarget)
            && (!fPrio || i.prioritas === fPrio);
    }), [props.items, q, fTarget, fPrio]);

    const dismiss = async (item: NotifQueueItem) => {
        if (busyKey || !item.can_dismiss) return;
        setBusyKey(item.ops_key);
        try {
            await fetch(props.urls.dismiss, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ops_key: item.ops_key, fingerprint: item.fingerprint }),
            });
            router.reload({ only: ['items', 'kpi'] });
            if (sel?.ops_key === item.ops_key) setSel(null);
        } finally {
            setBusyKey(null);
        }
    };

    const kpi = props.kpi;
    const cards = [
        { label: 'Total Antrian', value: kpi.total, sub: 'Item menunggu tindak lanjut', icon: <Layers size={18} />, accent: '#0284c7' },
        { label: 'Menunggu Proses', value: kpi.menunggu, sub: 'Perlu ditinjau', icon: <Hourglass size={18} />, accent: '#d97706' },
        { label: 'Telah Diproses', value: kpi.diproses, sub: 'Ditutup hari ini', icon: <CheckCircle2 size={18} />, accent: '#059669' },
        { label: 'Terjadwal', value: kpi.terjadwal, sub: 'Akan dikirim sesuai jadwal', icon: <CalendarClock size={18} />, accent: '#7c3aed' },
    ];

    const meta = props.activity.meta;
    const pages = useMemo(() => {
        const out: number[] = [];
        for (let i = Math.max(1, meta.page - 2); i <= Math.min(meta.last_page, meta.page + 2); i++) out.push(i);
        return out;
    }, [meta]);
    const goPage = (p: number) => router.get(window.location.pathname, { activity_page: p }, { preserveState: true, preserveScroll: true });

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Antrian Notifikasi"
            subtitle="Kelola dan proses permintaan pengiriman notifikasi ke pengguna (mahasiswa, dosen, dll).">
            <Head title="Antrian Notifikasi" />

            {flash?.success ? <div className="ad-mesin-flash ad-mesin-flash--ok">{flash.success}</div> : null}
            {flash?.error ? <div className="ad-mesin-flash ad-mesin-flash--err">{flash.error}</div> : null}

            <section className="ad-nq-hero"
                style={{ ['--ad-hero-img' as string]: `url('${asset('images/campus-polybatam.jpg')}')` }}>
                <div className="ad-nq-hero__body">
                    <h2>Pusat Antrian Notifikasi</h2>
                    <p>Kelola permintaan pengiriman notifikasi, tentukan prioritas, setujui penjadwalan, dan pantau riwayat pengiriman.</p>
                </div>
            </section>

            <section className="ad-log-kpis">
                {cards.map((c) => (
                    <div className="ad-log-kpi" key={c.label}>
                        <span className="ad-log-kpi__ic" style={{ color: c.accent, background: `${c.accent}1f` }}>{c.icon}</span>
                        <div><small>{c.label}</small><strong>{c.value}</strong><em className="ad-log-kpi__sub">{c.sub}</em></div>
                    </div>
                ))}
            </section>

            <PushToggle />

            <div className="ad-nq-tabrow">
                <div className="ad-rg-tabs">
                    <button type="button" className={cn('ad-rg-tab', tab === 'antrian' && 'is-active')}
                        onClick={() => setTab('antrian')}>
                        <Layers size={14} /> Antrian Notifikasi
                        {props.items.length > 0 && <span className="ad-rg-tab__n">{props.items.length}</span>}
                    </button>
                    <button type="button" className={cn('ad-rg-tab', tab === 'terjadwal' && 'is-active')}
                        onClick={() => setTab('terjadwal')}>
                        <CalendarClock size={14} /> Terjadwal
                    </button>
                    <button type="button" className={cn('ad-rg-tab', tab === 'riwayat' && 'is-active')}
                        onClick={() => setTab('riwayat')}>
                        <Clock size={14} /> Riwayat Pengiriman
                    </button>
                </div>
                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setHelp(true)}>
                    <HelpCircle size={14} /> Panduan
                </button>
            </div>

            {tab === 'antrian' && (
                <div className="ad-nq-split">
                    <div className="ad-panel">
                        <div className="ad-mesin-filters ad-nq-filters">
                            <label className="ad-mesin-search">
                                <Search size={14} />
                                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari judul atau isi notifikasi…" />
                            </label>
                            <select className="ad-nq-sel" value={fTarget} onChange={(e) => setFTarget(e.target.value)}>
                                <option value="">Semua Target</option>
                                {targets.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                            <select className="ad-nq-sel" value={fPrio} onChange={(e) => setFPrio(e.target.value)}>
                                <option value="">Semua Prioritas</option>
                                <option>Urgent</option><option>Tinggi</option><option>Normal</option>
                            </select>
                            <span className="ad-field--static">Semua Status</span>
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost"><Filter size={14} /> Filter</button>
                        </div>

                        <div className="ad-table-wrap ad-usr-free">
                            <table className="ad-table ad-nq-table">
                                <thead>
                                    <tr>
                                        <th>#</th><th>Judul Notifikasi</th><th>Target</th><th>Prioritas</th>
                                        <th>Status</th><th>Permintaan Oleh</th><th>Waktu Permintaan</th><th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((it, i) => (
                                        <tr key={it.ops_key} className={cn(sel?.ops_key === it.ops_key && 'is-sel')}
                                            onClick={() => setSel(it)}>
                                            <td className="ad-mono">{i + 1}</td>
                                            <td>
                                                <strong className="ad-nq-title">{it.judul}</strong>
                                                <small className="ad-nq-desc">{it.isi}</small>
                                            </td>
                                            <td>{it.target}</td>
                                            <td><span className={cn('ad-pill', PRIO_PILL[it.prioritas])}>{it.prioritas}</span></td>
                                            <td><span className="ad-pill ad-pill--pending">Menunggu</span></td>
                                            <td>{it.peminta}</td>
                                            <td>{it.waktu}</td>
                                            <td>
                                                <button type="button" className="ad-icon-btn" title="Lihat detail"
                                                    onClick={(e) => { e.stopPropagation(); setSel(it); }}>
                                                    <Eye size={15} />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {filtered.length === 0 && (
                                        <tr><td colSpan={8} className="ad-empty">Antrian kosong — tidak ada notifikasi menunggu proses.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="ad-mesin-pager ad-nq-pagerline">
                            <span>Menampilkan {filtered.length} dari {props.items.length} antrian</span>
                        </div>
                    </div>

                    <aside className="ad-panel ad-nq-detail">
                        {sel ? (
                            <>
                                <div className="ad-nq-detail__head">
                                    <h3><Bell size={15} /> Detail Antrian</h3>
                                    <span className="ad-nq-code">#{sel.kode}</span>
                                </div>
                                <h4 className="ad-nq-detail__title">{sel.judul}</h4>
                                <p className="ad-nq-detail__desc">{sel.isi}</p>
                                <dl className="ad-nq-kv">
                                    <div><dt>Target</dt><dd>{sel.target}</dd></div>
                                    <div><dt>Prioritas</dt><dd><span className={cn('ad-pill', PRIO_PILL[sel.prioritas])}>{sel.prioritas}</span></dd></div>
                                    <div><dt>Status</dt><dd><span className="ad-pill ad-pill--pending">Menunggu Proses</span></dd></div>
                                    <div><dt>Permintaan Oleh</dt><dd>{sel.peminta}</dd></div>
                                    <div><dt>Waktu Permintaan</dt><dd>{sel.waktu}</dd></div>
                                    {sel.jumlah > 0 && <div><dt>Jumlah Terkait</dt><dd>{sel.jumlah} item</dd></div>}
                                </dl>
                                <div className="ad-nq-isi">
                                    <h5>Isi Notifikasi</h5>
                                    <p>{sel.isi}</p>
                                </div>
                                <div className="ad-nq-detail__actions">
                                    {sel.can_dismiss ? (
                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--danger"
                                            disabled={busyKey === sel.ops_key}
                                            onClick={() => dismiss(sel)}>
                                            <X size={14} /> {busyKey === sel.ops_key ? 'Memproses…' : 'Tolak'}
                                        </button>
                                    ) : (
                                        <span className="ad-pill ad-pill--info" title="Item ini selalu tampil sampai saran ditanggapi">Tidak dapat ditolak</span>
                                    )}
                                    {sel.url && (
                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary"
                                            onClick={() => router.visit(sel.url!)}>
                                            <Send size={14} /> Proses &amp; Tindak Lanjuti
                                        </button>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className="ad-empty" style={{ padding: 30 }}>
                                Pilih antrian di tabel untuk melihat detail dan aksi.
                            </div>
                        )}
                    </aside>
                </div>
            )}

            {tab === 'terjadwal' && (
                <div className="ad-panel ad-nq-emptytab">
                    <CalendarClock size={34} />
                    <h3>Belum ada notifikasi terjadwal</h3>
                    <p>Penjadwalan pengiriman belum tersedia. Notifikasi yang muncul di antrian diproses dari halaman tindak lanjut masing-masing.</p>
                </div>
            )}

            {tab === 'riwayat' && (
                <div className="ad-panel">
                    <div className="ad-nq-riwayat-head">
                        <h3>Riwayat aktivitas sistem</h3>
                        <a className="ad-mesin-btn ad-mesin-btn--ghost" href={props.urls.riwayat_hapus}>Riwayat hapus →</a>
                    </div>
                    <div className="ad-table-wrap ad-usr-free">
                        <table className="ad-table">
                            <thead>
                                <tr><th>Waktu</th><th>User / Subjek</th><th>Aksi</th><th>Oleh</th><th>Catatan</th></tr>
                            </thead>
                            <tbody>
                                {props.activity.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="ad-mono">{log.waktu}</td>
                                        <td>
                                            <strong>{log.subjek}</strong>
                                            <small className="ad-nq-desc">{log.subjek_meta}</small>
                                        </td>
                                        <td><span className={cn('ad-pill', `ad-pill--${log.tone}`)}>{log.aksi}</span></td>
                                        <td>{log.oleh}</td>
                                        <td className="ad-nq-note">{log.catatan ?? '—'}</td>
                                    </tr>
                                ))}
                                {props.activity.data.length === 0 && (
                                    <tr><td colSpan={5} className="ad-empty">Belum ada riwayat aktivitas.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ad-mesin-pager ad-nq-pagerline">
                        <span>Menampilkan {meta.from}–{meta.to} dari {meta.total} aktivitas</span>
                        <div className="ad-mesin-pager__btns">
                            <button type="button" className="ad-icon-btn" disabled={meta.page <= 1}
                                onClick={() => goPage(meta.page - 1)}><ChevronLeft size={15} /></button>
                            {pages.map((p) => (
                                <button key={p} type="button"
                                    className={cn(p === meta.page && 'is-active')}
                                    onClick={() => goPage(p)}>{p}</button>
                            ))}
                            <button type="button" className="ad-icon-btn" disabled={meta.page >= meta.last_page}
                                onClick={() => goPage(meta.page + 1)}><ChevronRight size={15} /></button>
                        </div>
                    </div>
                </div>
            )}

            {help && (
                <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && setHelp(false)}>
                    <div className="ad-mesin-confirm ad-nq-help">
                        <button type="button" className="ad-icon-btn ad-usr-x" onClick={() => setHelp(false)}><X size={16} /></button>
                        <span className="ad-nq-code">PANDUAN</span>
                        <h3 style={{ margin: '8px 0 10px' }}>Antrian Notifikasi</h3>
                        <p style={{ color: 'var(--ad-muted)', fontSize: 13, lineHeight: 1.6 }}>
                            Halaman ini pusat tindak lanjut admin — bukan inbox chat. Setiap antrian
                            mengarah ke halaman aksi (approval akun, balas saran, cek mesin, buka kunci login, enroll fingerprint).
                            <br /><br />
                            <strong>Tolak</strong> menutup item antrian untuk Anda (muncul lagi bila kondisinya berubah).
                            <strong> Proses &amp; Tindak Lanjuti</strong> membuka halaman aksi sekaligus menutup antrian.
                        </p>
                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setHelp(false)}>Mengerti</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
