import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Activity, BarChart3, CalendarDays, CheckCircle2, ChevronDown, ChevronLeft, ChevronRight,
    ClipboardList, Clock, Download, FileSpreadsheet, Filter, Hourglass, Printer,
    RotateCcw, Search, Users, XCircle,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart } from '@admin/components/Charts';
import type { RekapHarianProps } from '@admin/types';

const STATUS_META: Record<string, { label: string; color: string }> = {
    lengkap: { label: 'Hadir', color: '#34d399' },
    terlambat: { label: 'Terlambat', color: '#fbbf24' },
    masuk: { label: 'Masuk', color: '#38bdf8' },
    tidak_lengkap: { label: 'Tidak Lengkap', color: '#fb923c' },
    alfa: { label: 'Alfa', color: '#fb7185' },
    belum: { label: 'Belum Tap', color: '#94a3b8' },
    tidak_wajib: { label: 'Tidak Wajib', color: '#64748b' },
    libur: { label: 'Libur', color: '#64748b' },
};
const statusOf = (s: string) => STATUS_META[s] ?? { label: s, color: '#94a3b8' };

const durasi = (masuk: string, pulang: string) => {
    const t = (v: string) => {
        const [h, m, s] = v.split(':').map(Number);
        return (h || 0) * 3600 + (m || 0) * 60 + (s || 0);
    };
    if (masuk === '—' || pulang === '—' || !masuk || !pulang) return '—';
    const d = t(pulang) - t(masuk);
    if (d <= 0) return '—';
    return `${Math.floor(d / 3600)}j ${Math.round((d % 3600) / 60)}m`;
};

const TABS = [
    { key: 'all', label: 'Semua' },
    { key: 'hadir', label: 'Hadir' },
    { key: 'terlambat', label: 'Terlambat' },
    { key: 'tidak_lengkap', label: 'Tidak Lengkap' },
    { key: 'alfa', label: 'Alfa' },
    { key: 'tidak_wajib', label: 'Tidak Wajib' },
] as const;
const tabMatch = (status: string, tab: string) => {
    switch (tab) {
        case 'hadir': return status === 'lengkap' || status === 'masuk';
        case 'terlambat': return status === 'terlambat';
        case 'tidak_lengkap': return status === 'tidak_lengkap';
        case 'alfa': return status === 'alfa' || status === 'belum';
        case 'tidak_wajib': return status === 'tidak_wajib' || status === 'libur';
        default: return true;
    }
};

export default function RekapHarian(props: RekapHarianProps) {
    const { filter, options, meta, urls } = props;
    const [snap, setSnap] = useState(props.initial);
    const [syncedAt, setSyncedAt] = useState<string>('');
    const [now, setNow] = useState(() => new Date());
    const s = snap.summary;
    const [draft, setDraft] = useState({ date: filter.date, kelas: filter.kelas, tim: filter.tim });
    const [q, setQ] = useState('');
    const [tab, setTab] = useState<(typeof TABS)[number]['key']>('all');
    const [page, setPage] = useState(1);
    const [view, setView] = useState<'grafik' | 'tabel'>('grafik');
    const [exOpen, setExOpen] = useState(false);
    const exRef = useRef<HTMLDivElement>(null);
    const PER_PAGE = 10;

    // Polling realtime — tap masuk/pulang dari mesin langsung muncul tanpa refresh.
    useEffect(() => {
        let alive = true;
        const pull = async () => {
            if (document.hidden) return;
            try {
                const p = new URLSearchParams();
                if (filter.date) p.set('date', filter.date);
                if (filter.kelas) p.set('kelas', filter.kelas);
                if (filter.tim) p.set('tim', filter.tim);
                const res = await fetch(`${urls.live}?${p.toString()}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const json = await res.json();
                if (alive && json?.snapshot) {
                    setSnap(json.snapshot);
                    setSyncedAt(json.synced_at ?? '');
                }
            } catch { /* jaringan putus — coba lagi siklus berikut */ }
        };
        const id = window.setInterval(pull, 15000);
        pull();
        return () => { alive = false; window.clearInterval(id); };
    }, [urls.live, filter.date, filter.kelas, filter.tim]);

    useEffect(() => {
        if (!exOpen) return;
        const onDown = (e: MouseEvent) => {
            if (exRef.current && !exRef.current.contains(e.target as Node)) setExOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [exOpen]);

    // Jam berjalan tiap detik — data tetap sinkron lewat polling di atas.
    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(id);
    }, []);

    const apply = () => {
        const params: Record<string, string> = {};
        if (draft.date) params.date = draft.date;
        if (draft.kelas) params.kelas = draft.kelas;
        if (draft.tim) params.tim = draft.tim;
        router.get(urls.index, params, { preserveState: true });
    };
    const reset = () => router.get(urls.index, {}, { preserveState: false });
    const qs = () => {
        const p = new URLSearchParams();
        if (filter.date) p.set('date', filter.date);
        if (filter.kelas) p.set('kelas', filter.kelas);
        if (filter.tim) p.set('tim', filter.tim);
        const str = p.toString();
        return str ? `?${str}` : '';
    };

    const pctOf = (n: number) => (s.total > 0 ? `${((n / s.total) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%` : '0%');
    const tlTotal = s.tidak_lengkap + s.masuk;
    const alfaTotal = s.alfa + s.belum;

    const kpis = [
        { icon: Users, label: 'Total Mahasiswa', value: s.total, sub: 'Dalam Cakupan', cls: 'lpr-kpi--blue' },
        { icon: CheckCircle2, label: 'Hadir', value: s.hadir, sub: pctOf(s.hadir), cls: 'lpr-kpi--green' },
        { icon: Clock, label: 'Terlambat', value: s.terlambat, sub: pctOf(s.terlambat), cls: 'lpr-kpi--amber' },
        { icon: Hourglass, label: 'Tidak Lengkap', value: tlTotal, sub: pctOf(tlTotal), cls: 'lpr-kpi--violet' },
        { icon: XCircle, label: 'Alfa', value: alfaTotal, sub: pctOf(alfaTotal), cls: 'lpr-kpi--red' },
    ];

    const donut = {
        labels: ['Hadir', 'Terlambat', 'Tidak Lengkap', 'Alfa', 'Tidak Wajib'],
        values: [s.hadir, s.terlambat, tlTotal, alfaTotal, s.tidak_wajib],
        colors: ['#34d399', '#fbbf24', '#a78bfa', '#fb7185', '#64748b'],
        total: s.total,
        center_label: 'Mahasiswa',
    };

    const trend = snap.sesi_trend ?? [];
    const trendMax = Math.max(1, ...trend.map((t) => t.total));

    const recent = useMemo(() => [...(snap.tap_masuk ?? [])].slice(-6).reverse(), [snap.tap_masuk]);

    const counts = useMemo(() => {
        const c: Record<string, number> = { all: snap.rows.length };
        for (const t of TABS) if (t.key !== 'all') c[t.key] = snap.rows.filter((r) => tabMatch(r.status, t.key)).length;
        return c;
    }, [snap.rows]);

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return snap.rows.filter((r) =>
            tabMatch(r.status, tab)
            && (!needle || r.nama.toLowerCase().includes(needle) || r.nim.toLowerCase().includes(needle) || r.tim.toLowerCase().includes(needle)));
    }, [snap.rows, tab, q]);
    const lastPage = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
    const pageRows = filtered.slice((page - 1) * PER_PAGE, page * PER_PAGE);

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Rekap Absensi Harian" subtitle="Laporan & Audit › Rekap Harian">
            <Head title="Rekap Harian" />
            <div className="lpr">
                {/* ── Hero ── */}
                <div className="lpr-hero">
                    <div className="lpr-hero__text">
                        <span className="lpr-hero__badge">SmartBrail</span>
                        <h1>Laporan Absensi Harian</h1>
                        <p>Rekap kehadiran, keterlambatan, dan status akses laboratorium pada tanggal yang dipilih.</p>
                    </div>
                    <div className="lpr-hero__info">
                        <span className="lpr-hero__info-label"><CalendarDays size={15} /> Tanggal Laporan</span>
                        <strong>{meta.tanggal}</strong>
                        <small>⏱ {meta.jadwal_label} · {meta.kebijakan_label}</small>
                    </div>
                </div>

                {/* ── Filter ── */}
                <div className="lpr-card">
                    <div className="lpr-card__head"><Filter size={15} /><strong>Filter Data</strong></div>
                    <div className="lpr-filter">
                        <label><span>Tanggal</span>
                            <input type="date" value={draft.date} onChange={(e) => setDraft({ ...draft, date: e.target.value })} />
                        </label>
                        <label><span>Kelas</span>
                            <select value={draft.kelas} onChange={(e) => setDraft({ ...draft, kelas: e.target.value })}>
                                <option value="">Semua kelas</option>
                                {options.kelas.map((k) => <option key={k} value={k}>{k}</option>)}
                            </select>
                        </label>
                        <label><span>Tim PBL</span>
                            <select value={draft.tim} onChange={(e) => setDraft({ ...draft, tim: e.target.value })}>
                                <option value="">Semua tim</option>
                                {options.tim.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </label>
                        <div className="lpr-filter__acts">
                            <button type="button" className="lpr-btn lpr-btn--primary" onClick={apply}>▶ Terapkan Filter</button>
                            <button type="button" className="lpr-btn lpr-btn--ghost" onClick={reset}><RotateCcw size={14} /> Reset</button>
                        </div>
                    </div>
                </div>

                {/* ── KPI ── */}
                <div className="lpr-kpis">
                    {kpis.map((k) => (
                        <div key={k.label} className={`lpr-kpi ${k.cls}`}>
                            <k.icon size={20} className="lpr-kpi__icon" />
                            <strong>{k.value.toLocaleString('id-ID')}</strong>
                            <span>{k.label}</span>
                            <small>{k.sub}</small>
                        </div>
                    ))}
                </div>

                {/* ── Distribusi + Tren per sesi ── */}
                <div className="lpr-charts">
                    <div className="lpr-card">
                        <div className="lpr-card__head">
                            <div>
                                <strong>Distribusi Kehadiran</strong>
                                <small>Komposisi status absensi {meta.hari_ini_wajib ? 'hari ini' : 'tanggal ini'}.</small>
                            </div>
                        </div>
                        <div className="lpr-donut"><DonutChart data={donut} size={170} /></div>
                        <div className="lpr-dlegend">
                            {donut.labels.map((lb, i) => (
                                <span key={lb}><i style={{ background: donut.colors[i] }} /> {lb}
                                    <b>{donut.values[i].toLocaleString('id-ID')}</b> <em>({pctOf(donut.values[i])})</em></span>
                            ))}
                        </div>
                    </div>

                    <div className="lpr-card">
                        <div className="lpr-card__head">
                            <div>
                                <strong><Activity size={15} style={{ verticalAlign: -2 }} /> Tren Kehadiran per Sesi</strong>
                                <small>Perbandingan jumlah mahasiswa di setiap slot waktu hari ini.</small>
                            </div>
                            <div className="lpr-seg">
                                <button type="button" className={view === 'grafik' ? 'is-active' : ''} onClick={() => setView('grafik')}><BarChart3 size={13} /> Grafik</button>
                                <button type="button" className={view === 'tabel' ? 'is-active' : ''} onClick={() => setView('tabel')}><ClipboardList size={13} /> Tabel</button>
                            </div>
                        </div>
                        {view === 'grafik' ? (
                            trend.length ? (
                                <div className="rkh-bars">
                                    {trend.map((t) => (
                                        <div key={t.slot} className="rkh-bars__col" title={`${t.slot}: ${t.total} mahasiswa`}>
                                            <span className="rkh-bars__val">{t.total}</span>
                                            <div className="rkh-bars__bar" style={{ height: `${Math.max(4, (t.total / trendMax) * 100)}%` }} />
                                            <span className="rkh-bars__slot">{t.slot}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : <div className="ad-empty" style={{ padding: '28px 0' }}>Belum ada tap masuk pada tanggal ini.</div>
                        ) : (
                            <div className="lpr-tablewrap" style={{ maxHeight: 240 }}>
                                <table className="lpr-table">
                                    <thead><tr><th>Slot Waktu</th><th>Mahasiswa</th></tr></thead>
                                    <tbody>
                                        {trend.map((t) => <tr key={t.slot}><td>{t.slot}</td><td>{t.total}</td></tr>)}
                                        {!trend.length && <tr><td colSpan={2} className="ad-empty">Belum ada tap masuk.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>

                {/* ── Aktivitas + Rekap ── */}
                <div className="rkh-bottom">
                    <div className="lpr-card rkh-activity">
                        <div className="lpr-card__head">
                            <div>
                                <strong>Aktivitas Terbaru</strong>
                                <small>Riwayat absensi dan akses hari ini.</small>
                            </div>
                        </div>
                        <div className="rkh-feed">
                            {recent.map((t, i) => (
                                <div key={i} className="rkh-feed__item">
                                    <span className="rkh-feed__avatar"><CheckCircle2 size={15} /></span>
                                    <div>
                                        <strong>{t.nama}</strong>
                                        <small>Masuk laboratorium · {t.kelas}</small>
                                    </div>
                                    <time>{t.waktu_tap}</time>
                                </div>
                            ))}
                            {!recent.length && <div className="ad-empty">Belum ada aktivitas pada tanggal ini.</div>}
                        </div>
                        <a className="rkh-feed__more" href={`${urls.index}${qs()}`}>Lihat Semua Aktivitas →</a>
                    </div>

                    <div className="lpr-card">
                        <div className="lpr-card__head">
                            <div>
                                <strong>Rekap Mahasiswa</strong>
                                <small>Daftar status absensi mahasiswa pada tanggal yang dipilih.</small>
                            </div>
                            <div className="lpr-rekap-tools">
                                <span className="rkh-live" title={`Data disegarkan otomatis tiap 15 detik${syncedAt ? ` · sinkron terakhir ${syncedAt}` : ''}`}>
                                    <i /> Live · {now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                                </span>
                                <div className="lpr-search">
                                    <Search size={14} />
                                    <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Cari nama atau NIM..." />
                                </div>
                                <div className="lpr-dd" ref={exRef}>
                                    <button type="button" className="lpr-btn lpr-btn--primary" onClick={() => setExOpen(!exOpen)}>
                                        <Download size={14} /> Export Laporan <ChevronDown size={13} />
                                    </button>
                                    {exOpen && (
                                        <div className="lpr-dd__menu">
                                            <a href={`${urls.export}${qs()}`}><FileSpreadsheet size={14} /> Excel (.xls)</a>
                                            <a href={`${urls.print}${qs()}`} target="_blank" rel="noreferrer"><Printer size={14} /> Cetak / PDF</a>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                        <div className="rkh-tabs">
                            {TABS.map((t) => (
                                <button key={t.key} type="button"
                                    className={`rkh-tab ${tab === t.key ? 'is-active' : ''}`}
                                    onClick={() => { setTab(t.key); setPage(1); }}>
                                    {t.label} <b>{counts[t.key] ?? 0}</b>
                                </button>
                            ))}
                        </div>
                        <div className="lpr-tablewrap rkh-tablebox">
                            <table className="lpr-table rkh-table">
                                <colgroup>
                                    <col style={{ width: '42px' }} />
                                    <col />
                                    <col style={{ width: '108px' }} />
                                    <col style={{ width: '92px' }} />
                                    <col style={{ width: '150px' }} />
                                    <col style={{ width: '112px' }} />
                                    <col style={{ width: '76px' }} />
                                    <col style={{ width: '76px' }} />
                                    <col style={{ width: '72px' }} />
                                </colgroup>
                                <thead>
                                    <tr><th>No</th><th>Nama</th><th>NIM</th><th>Kelas</th><th>Tim PBL</th>
                                        <th>Status</th><th>Masuk</th><th>Pulang</th><th>Durasi</th></tr>
                                </thead>
                                <tbody>
                                    {pageRows.map((r, i) => {
                                        const st = statusOf(r.status);
                                        return (
                                            <tr key={`${r.nim}-${i}`}>
                                                <td>{(page - 1) * PER_PAGE + i + 1}</td>
                                                <td><strong>{r.nama}</strong></td>
                                                <td className="lpr-mono">{r.nim}</td>
                                                <td>{r.kelas}</td>
                                                <td>{r.tim}</td>
                                                <td><span className="rkh-status" style={{ color: st.color, background: `color-mix(in srgb, ${st.color} 13%, transparent)` }}>{st.label.toUpperCase()}</span></td>
                                                <td className="lpr-mono">{r.jam_masuk}</td>
                                                <td className="lpr-mono">{r.jam_pulang}</td>
                                                <td className="lpr-mono">{durasi(r.jam_masuk, r.jam_pulang)}</td>
                                            </tr>
                                        );
                                    })}
                                    {!pageRows.length && <tr><td colSpan={9} className="ad-empty">Tidak ada data pada filter ini.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                        <div className="lpr-pager">
                            <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}><ChevronLeft size={13} /> Sebelumnya</button>
                            <span>Menampilkan {Math.min(filtered.length, (page - 1) * PER_PAGE + 1)}–{Math.min(filtered.length, page * PER_PAGE)} dari {filtered.length} mahasiswa</span>
                            <button type="button" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Berikutnya <ChevronRight size={13} /></button>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
