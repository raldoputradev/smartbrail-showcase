import { useMemo, useRef, useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    BarChart3, CalendarRange, CheckCircle2, ChevronDown, ClipboardList, Clock, Cpu,
    Download, FileSpreadsheet, FileText, Filter, Printer, RotateCcw, Search, Users, XCircle,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { LineChart, DonutChart } from '@admin/components/Charts';
import type { LaporanPeriodikProps } from '@admin/types';

const PERIODE_OPTS = [
    { key: 'minggu_ini', label: 'Minggu ini' },
    { key: 'minggu_lalu', label: 'Minggu lalu' },
    { key: 'bulan_ini', label: 'Bulan ini' },
    { key: 'bulan_lalu', label: 'Bulan lalu' },
    { key: 'tahun_bulan', label: 'Tahun-bulan' },
    { key: 'custom', label: 'Rentang kustom' },
];

const fmtDate = (iso: string) => {
    const d = new Date(`${iso}T00:00:00`);
    return isNaN(d.getTime()) ? iso : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
const daySpan = (a: string, b: string) => {
    const ms = new Date(`${b}T00:00:00`).getTime() - new Date(`${a}T00:00:00`).getTime();
    return isNaN(ms) ? 0 : Math.round(ms / 86400000) + 1;
};

export default function LaporanPeriodik(props: LaporanPeriodikProps) {
    const r = props.report;
    const f = r.filter;
    const [draft, setDraft] = useState({
        periode: f.periode, bulan: f.bulan, tanggal_mulai: f.tanggal_mulai,
        tanggal_akhir: f.tanggal_akhir, kelas: f.kelas, tim: f.tim,
    });
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const [view, setView] = useState<'grafik' | 'tabel'>('grafik');
    const [dlOpen, setDlOpen] = useState(false);
    const dlRef = useRef<HTMLDivElement>(null);
    const PER_PAGE = 10;

    useEffect(() => {
        if (!dlOpen) return;
        const onDown = (e: MouseEvent) => {
            if (dlRef.current && !dlRef.current.contains(e.target as Node)) setDlOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [dlOpen]);

    const apply = () => {
        const params: Record<string, string> = { periode: draft.periode, kelas: draft.kelas, tim: draft.tim };
        if (draft.periode === 'tahun_bulan') params.bulan = draft.bulan;
        if (draft.periode === 'custom') {
            params.tanggal_mulai = draft.tanggal_mulai;
            params.tanggal_akhir = draft.tanggal_akhir;
        }
        router.get(props.urls.index, params, { preserveState: true });
    };
    const reset = () => router.get(props.urls.index, { periode: 'bulan_ini' }, { preserveState: false });

    const exportQuery = () => {
        const p = new URLSearchParams({ periode: f.periode, kelas: f.kelas, tim: f.tim });
        if (f.periode === 'tahun_bulan') p.set('bulan', f.bulan);
        if (f.periode === 'custom') {
            p.set('tanggal_mulai', f.tanggal_mulai);
            p.set('tanggal_akhir', f.tanggal_akhir);
        }
        return p.toString();
    };
    const dl = (base: string) => `${base}?${exportQuery()}`;

    const s = r.summary;
    const totalSesi = s.hadir + s.tidak_lengkap + s.alfa;
    const pctOf = (n: number) => (totalSesi > 0 ? `${((n / totalSesi) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%` : '0%');

    const kpis = [
        { icon: Users, label: 'Mahasiswa dalam Cakupan', value: s.mahasiswa, sub: 'sesuai filter', cls: 'lpr-kpi--blue' },
        { icon: ClipboardList, label: 'Mahasiswa dalam Laporan', value: r.students.length, sub: 'punya catatan di periode', cls: 'lpr-kpi--violet' },
        { icon: CheckCircle2, label: 'Hadir', value: s.hadir, sub: pctOf(s.hadir), cls: 'lpr-kpi--green' },
        { icon: Clock, label: 'Tidak Lengkap', value: s.tidak_lengkap, sub: pctOf(s.tidak_lengkap), cls: 'lpr-kpi--amber' },
        { icon: XCircle, label: 'Alfa', value: s.alfa, sub: pctOf(s.alfa), cls: 'lpr-kpi--red' },
    ];

    const lineData = useMemo(() => ({
        labels: r.chart.labels.map((l) => (l.includes('-') ? `${l.slice(8, 10)}/${l.slice(5, 7)}` : l)),
        series: [
            { key: 'hadir', label: 'Hadir', data: r.chart.hadir, color: '#34d399' },
            { key: 'tl', label: 'Tidak Lengkap', data: r.chart.tidak_lengkap, color: '#fbbf24' },
            { key: 'alfa', label: 'Alfa', data: r.chart.alfa, color: '#fb7185' },
        ],
    }), [r.chart]);

    const donut = {
        labels: ['Hadir', 'Tidak Lengkap', 'Alfa'],
        values: [s.hadir, s.tidak_lengkap, s.alfa],
        colors: ['#34d399', '#fbbf24', '#fb7185'],
        total: totalSesi,
        center_label: 'Total Sesi',
    };

    const students = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return needle
            ? r.students.filter((x) => x.nama.toLowerCase().includes(needle) || x.nim.toLowerCase().includes(needle))
            : r.students;
    }, [r.students, q]);
    const lastPage = Math.max(1, Math.ceil(students.length / PER_PAGE));
    const pageRows = students.slice((page - 1) * PER_PAGE, page * PER_PAGE);

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Laporan Periodik" subtitle="Laporan & Audit › Laporan Periodik">
            <Head title="Laporan Periodik" />
            <div className="lpr">
                {/* ── Hero ── */}
                <div className="lpr-hero">
                    <div className="lpr-hero__text">
                        <span className="lpr-hero__badge">SmartBrail</span>
                        <h1>Laporan Absensi Laboratorium</h1>
                        <p>Rekap kehadiran, analisis tren, dan evaluasi aktivitas laboratorium berdasarkan periode, kelas, dan tim PBL.</p>
                    </div>
                    <div className="lpr-hero__info">
                        <span className="lpr-hero__info-label"><CalendarRange size={15} /> Periode Laporan</span>
                        <strong>{fmtDate(f.tanggal_mulai)} – {fmtDate(f.tanggal_akhir)}</strong>
                        <small>⏱ Durasi: {daySpan(f.tanggal_mulai, f.tanggal_akhir)} hari · {r.activity_label}</small>
                    </div>
                </div>

                {/* ── Filter ── */}
                <div className="lpr-card">
                    <div className="lpr-card__head"><Filter size={15} /><strong>Filter Laporan</strong></div>
                    <div className="lpr-filter">
                        <label>
                            <span>Periode</span>
                            <select value={draft.periode} onChange={(e) => setDraft({ ...draft, periode: e.target.value })}>
                                {PERIODE_OPTS.map((o) => <option key={o.key} value={o.key}>{o.label}</option>)}
                            </select>
                        </label>
                        {draft.periode === 'tahun_bulan' && (
                            <label>
                                <span>Bulan</span>
                                <input type="month" value={draft.bulan} onChange={(e) => setDraft({ ...draft, bulan: e.target.value })} />
                            </label>
                        )}
                        {draft.periode === 'custom' && (
                            <>
                                <label><span>Dari</span><input type="date" value={draft.tanggal_mulai} onChange={(e) => setDraft({ ...draft, tanggal_mulai: e.target.value })} /></label>
                                <label><span>Sampai</span><input type="date" value={draft.tanggal_akhir} onChange={(e) => setDraft({ ...draft, tanggal_akhir: e.target.value })} /></label>
                            </>
                        )}
                        <label>
                            <span>Kelas</span>
                            <select value={draft.kelas} onChange={(e) => setDraft({ ...draft, kelas: e.target.value })}>
                                <option value="">Semua kelas</option>
                                {r.kelas_options.map((k) => <option key={k} value={k}>{k}</option>)}
                            </select>
                        </label>
                        <label>
                            <span>Tim PBL</span>
                            <select value={draft.tim} onChange={(e) => setDraft({ ...draft, tim: e.target.value })}>
                                <option value="">Semua tim</option>
                                {r.tim_options.map((t) => <option key={t} value={t}>{t}</option>)}
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

                {/* ── Grafik + Komposisi ── */}
                <div className="lpr-charts">
                    <div className="lpr-card">
                        <div className="lpr-card__head">
                            <div>
                                <strong><BarChart3 size={15} style={{ verticalAlign: -2 }} /> Tren Kehadiran Harian</strong>
                                <small>Perkembangan jumlah sesi kehadiran, tidak lengkap, dan alfa selama periode.</small>
                            </div>
                            <div className="lpr-seg">
                                <button type="button" className={view === 'grafik' ? 'is-active' : ''} onClick={() => setView('grafik')}>
                                    <BarChart3 size={13} /> Grafik
                                </button>
                                <button type="button" className={view === 'tabel' ? 'is-active' : ''} onClick={() => setView('tabel')}>
                                    <ClipboardList size={13} /> Tabel
                                </button>
                            </div>
                        </div>
                        {view === 'grafik' ? (
                            <>
                                <LineChart data={lineData} height={250} />
                                <div className="lpr-legend">
                                    <span><i style={{ background: '#34d399' }} /> Hadir</span>
                                    <span><i style={{ background: '#fbbf24' }} /> Tidak Lengkap</span>
                                    <span><i style={{ background: '#fb7185' }} /> Alfa</span>
                                </div>
                            </>
                        ) : (
                            <div className="lpr-tablewrap" style={{ maxHeight: 280 }}>
                                <table className="lpr-table">
                                    <thead><tr><th>Tanggal</th><th>Hadir</th><th>Tidak Lengkap</th><th>Alfa</th><th>Total Sesi</th></tr></thead>
                                    <tbody>
                                        {r.chart.labels.map((tgl, i) => (
                                            <tr key={tgl}>
                                                <td>{fmtDate(tgl)}</td>
                                                <td>{(r.chart.hadir[i] ?? 0).toLocaleString('id-ID')}</td>
                                                <td>{(r.chart.tidak_lengkap[i] ?? 0).toLocaleString('id-ID')}</td>
                                                <td>{(r.chart.alfa[i] ?? 0).toLocaleString('id-ID')}</td>
                                                <td>{((r.chart.hadir[i] ?? 0) + (r.chart.tidak_lengkap[i] ?? 0) + (r.chart.alfa[i] ?? 0)).toLocaleString('id-ID')}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div className="lpr-card">
                        <div className="lpr-card__head">
                            <div>
                                <strong>Komposisi Absensi</strong>
                                <small>Proporsi total sesi-mahasiswa pada periode yang dipilih.</small>
                            </div>
                        </div>
                        <div className="lpr-donut">
                            <DonutChart data={donut} size={180} />
                        </div>
                        <div className="lpr-dlegend">
                            <span><i style={{ background: '#34d399' }} /> Hadir <b>{s.hadir.toLocaleString('id-ID')}</b> <em>({pctOf(s.hadir)})</em></span>
                            <span><i style={{ background: '#fbbf24' }} /> Tidak Lengkap <b>{s.tidak_lengkap.toLocaleString('id-ID')}</b> <em>({pctOf(s.tidak_lengkap)})</em></span>
                            <span><i style={{ background: '#fb7185' }} /> Alfa <b>{s.alfa.toLocaleString('id-ID')}</b> <em>({pctOf(s.alfa)})</em></span>
                        </div>
                        {r.machines.length > 0 && (
                            <div className="lpr-machines">
                                <strong>Top mesin absensi</strong>
                                {r.machines.slice(0, 4).map((m) => (
                                    <div key={m.id_mesin} className="lpr-machines__row">
                                        <span><Cpu size={12} /> {m.aktivitas} · {m.id_mesin}</span>
                                        <b>{m.total.toLocaleString('id-ID')}</b>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* ── Rekap per mahasiswa ── */}
                <div className="lpr-card">
                    <div className="lpr-card__head">
                        <div>
                            <strong>Rekap per Mahasiswa (Dalam Laporan)</strong>
                            <small>Daftar kehadiran setiap mahasiswa sesuai filter yang diterapkan.</small>
                        </div>
                        <div className="lpr-rekap-tools">
                            <div className="lpr-search">
                                <Search size={14} />
                                <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Cari nama, NIM, atau tim PBL..." />
                            </div>
                            <div className="lpr-dd" ref={dlRef}>
                                <button type="button" className="lpr-btn lpr-btn--primary" onClick={() => setDlOpen(!dlOpen)}>
                                    <Download size={14} /> Unduh Data <ChevronDown size={13} />
                                </button>
                                {dlOpen && (
                                    <div className="lpr-dd__menu">
                                        <a href={dl(props.urls.excel)}><FileSpreadsheet size={14} /> Excel (.xls)</a>
                                        <a href={dl(props.urls.word)}><FileText size={14} /> Word (.doc)</a>
                                        <a href={dl(props.urls.print)} target="_blank" rel="noreferrer"><Printer size={14} /> Cetak / PDF</a>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="lpr-tablewrap">
                        <table className="lpr-table">
                            <thead>
                                <tr>
                                    <th rowSpan={2}>No</th><th rowSpan={2}>Nama</th><th rowSpan={2}>NIM</th>
                                    <th rowSpan={2}>Kelas</th><th rowSpan={2}>Tim PBL</th>
                                    <th colSpan={3} className="lpr-th-group">Rekap Absensi (Sesi)</th>
                                    <th rowSpan={2}>Total Sesi</th><th rowSpan={2}>Persentase Hadir</th><th rowSpan={2}>Terakhir</th>
                                </tr>
                                <tr>
                                    <th>Hadir</th><th>Tidak Lengkap</th><th>Alfa</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pageRows.map((st, i) => {
                                    const tot = st.hadir + st.tidak_lengkap + st.alfa;
                                    const pct = tot > 0 ? `${((st.hadir / tot) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%` : '0,0%';
                                    return (
                                        <tr key={st.user_id}>
                                            <td>{(page - 1) * PER_PAGE + i + 1}</td>
                                            <td><strong>{st.nama}</strong></td>
                                            <td className="lpr-mono">{st.nim}</td>
                                            <td>{st.kelas}</td>
                                            <td>{st.tim}</td>
                                            <td>{st.hadir}</td>
                                            <td>{st.tidak_lengkap}</td>
                                            <td>{st.alfa}</td>
                                            <td>{tot}</td>
                                            <td>{pct}</td>
                                            <td className="lpr-mono">{st.terakhir ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                                {pageRows.length === 0 && (
                                    <tr><td colSpan={11} className="ad-empty">Tidak ada mahasiswa dalam laporan pada filter ini.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {lastPage > 1 && (
                        <div className="lpr-pager">
                            <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>‹ Sebelumnya</button>
                            <span>Halaman {page} dari {lastPage}</span>
                            <button type="button" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Berikutnya ›</button>
                        </div>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
