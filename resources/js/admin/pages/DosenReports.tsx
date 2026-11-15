import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Activity, BarChart3, CalendarRange, ChevronLeft, ChevronRight, Cpu, Download,
    FileText, PieChart, Printer, RotateCcw, Search, SlidersHorizontal, Trophy,
    UserCheck, UserX, Users,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart } from '@admin/components/Charts';
import type { DosenReportsProps, PeriodicStudent } from '@admin/types';

const PERIODE_OPTS = [
    { key: 'minggu_ini', label: 'Minggu ini' },
    { key: 'minggu_lalu', label: 'Minggu lalu' },
    { key: 'bulan_ini', label: 'Bulan ini' },
    { key: 'bulan_lalu', label: 'Bulan lalu' },
    { key: 'tahun_bulan', label: 'Pilih bulan…' },
    { key: 'custom', label: 'Rentang kustom…' },
];

const TABS = [
    { key: 'ringkasan', label: 'Ringkasan', icon: PieChart },
    { key: 'mahasiswa', label: 'Per Mahasiswa', icon: Users },
    { key: 'kelas', label: 'Per Kelas', icon: BarChart3 },
    { key: 'tim', label: 'Per Tim PBL', icon: Users },
    { key: 'mesin', label: 'Per Mesin', icon: Cpu },
] as const;

const pct = (s: Pick<PeriodicStudent, 'hadir' | 'tidak_lengkap' | 'alfa'>) => {
    const total = s.hadir + s.tidak_lengkap + s.alfa;
    return total > 0 ? Math.round((s.hadir / total) * 1000) / 10 : null;
};

function PctPill({ value }: { value: number | null }) {
    if (value === null) return <span className="ad-pill ad-pill--muted">—</span>;
    const cls = value >= 90 ? 'ad-pill--ok' : value >= 75 ? 'ad-pill--info' : value >= 50 ? 'ad-pill--pending' : 'ad-pill--muted';
    return <span className={`ad-pill ${cls}`}>{value.toLocaleString('id-ID')}%</span>;
}

/** Stacked bar chart sederhana (SVG) — hadir/tidak_lengkap/alfa per hari. */
function TrendChart({ labels, hadir, tl, alfa }: { labels: string[]; hadir: number[]; tl: number[]; alfa: number[] }) {
    const max = Math.max(1, ...labels.map((_, i) => (hadir[i] ?? 0) + (tl[i] ?? 0) + (alfa[i] ?? 0)));
    const anyData = labels.some((_, i) => (hadir[i] ?? 0) + (tl[i] ?? 0) + (alfa[i] ?? 0) > 0);
    if (!anyData) {
        return <div className="ad-empty" style={{ padding: '30px 0' }}>Belum ada tap absensi pada periode ini.</div>;
    }
    return (
        <div className="ad-trend">
            {labels.map((lb, i) => {
                const h = hadir[i] ?? 0, t = tl[i] ?? 0, a = alfa[i] ?? 0, tot = h + t + a;
                const totalPct = tot > 0 ? Math.round((h / tot) * 100) : 0;
                const label = lb.includes('-') ? lb.slice(8, 10) + '/' + lb.slice(5, 7) : lb;
                return (
                    <div key={lb + i} className="ad-trend__col" title={`${lb}: hadir ${h}, tdk lengkap ${t}, alfa ${a}`}>
                        <span className="ad-trend__pct">{tot > 0 ? `${totalPct}%` : ''}</span>
                        <div className="ad-trend__bar">
                            <span style={{ height: `${(h / max) * 100}%`, background: '#22c55e' }} />
                            <span style={{ height: `${(t / max) * 100}%`, background: '#f59e0b' }} />
                            <span style={{ height: `${(a / max) * 100}%`, background: '#ef4444' }} />
                        </div>
                        <span className="ad-trend__day">{label}</span>
                    </div>
                );
            })}
            {labels.length === 0 && <div className="ad-empty" style={{ width: '100%' }}>Tidak ada data di periode ini.</div>}
        </div>
    );
}

export default function DosenReports(props: DosenReportsProps) {
    const r = props.report;
    const f = r.filter;
    const [tab, setTab] = useState<(typeof TABS)[number]['key']>('ringkasan');
    const [draft, setDraft] = useState({
        periode: f.periode, bulan: f.bulan, tanggal_mulai: f.tanggal_mulai,
        tanggal_akhir: f.tanggal_akhir, kelas: f.kelas, tim: f.tim,
    });
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const PER_PAGE = 10;

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

    const s = r.summary;
    const totalSesi = s.hadir + s.tidak_lengkap + s.alfa;
    const avgPct = totalSesi > 0 ? Math.round((s.hadir / totalSesi) * 1000) / 10 : 0;

    const students = useMemo(() => {
        const needle = q.trim().toLowerCase();
        const list = needle
            ? r.students.filter((x) => x.nama.toLowerCase().includes(needle) || x.nim.toLowerCase().includes(needle))
            : r.students;
        return list;
    }, [r.students, q]);
    const lastPage = Math.max(1, Math.ceil(students.length / PER_PAGE));
    const pageRows = students.slice((page - 1) * PER_PAGE, page * PER_PAGE);

    const groupBy = (key: 'kelas' | 'tim') => {
        const map = new Map<string, { nama: string; mhs: number; hadir: number; tl: number; alfa: number }>();
        for (const st of r.students) {
            const k = (st[key] || '—').toString();
            const cur = map.get(k) ?? { nama: k, mhs: 0, hadir: 0, tl: 0, alfa: 0 };
            cur.mhs += 1; cur.hadir += st.hadir; cur.tl += st.tidak_lengkap; cur.alfa += st.alfa;
            map.set(k, cur);
        }
        return [...map.values()].sort((a, b) => b.hadir - a.hadir);
    };

    const kpis = [
        { icon: Users, label: 'Total Mahasiswa', value: s.mahasiswa, accent: '#2563eb', sub: 'Dalam cakupan filter' },
        { icon: UserCheck, label: 'Hadir', value: s.hadir, accent: '#059669', sub: 'sesi-mahasiswa' },
        { icon: Activity, label: 'Tidak Lengkap', value: s.tidak_lengkap, accent: '#d97706', sub: 'sesi-mahasiswa' },
        { icon: UserX, label: 'Tidak Hadir', value: s.alfa, accent: '#e11d48', sub: 'sesi-mahasiswa' },
        { icon: CalendarRange, label: 'Hari Tercatat', value: r.chart.labels.length, accent: '#7c3aed', sub: r.periode_label },
    ];

    const donut = {
        labels: ['Hadir', 'Tidak Lengkap', 'Tidak Hadir'],
        values: [s.hadir, s.tidak_lengkap, s.alfa],
        colors: ['#22c55e', '#f59e0b', '#ef4444'],
        total: totalSesi,
        center_label: `${avgPct}%`,
    };

    const GroupTable = ({ rows, firstCol }: { rows: { nama: string; mhs: number; hadir: number; tl: number; alfa: number }[]; firstCol: string }) => (
        <div className="ad-table-wrap">
            <table className="ad-table">
                <thead><tr><th>No</th><th>{firstCol}</th><th>Mahasiswa</th><th>Hadir</th><th>Tdk Lengkap</th><th>Alfa</th><th>Persentase</th></tr></thead>
                <tbody>
                    {rows.length === 0 && <tr><td colSpan={7} className="ad-empty">Tidak ada data.</td></tr>}
                    {rows.map((g, i) => {
                        const tot = g.hadir + g.tl + g.alfa;
                        return (
                            <tr key={g.nama}>
                                <td style={{ color: 'var(--ad-muted)' }}>{i + 1}</td>
                                <td><strong>{g.nama}</strong></td>
                                <td>{g.mhs}</td>
                                <td>{g.hadir}</td>
                                <td>{g.tl}</td>
                                <td>{g.alfa}</td>
                                <td><PctPill value={tot > 0 ? Math.round((g.hadir / tot) * 1000) / 10 : null} /></td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Laporan Periodik Absensi"
            subtitle={`Analisis dan rekapitulasi absensi mahasiswa — ${r.periode_label}`}>
            <Head title="Laporan Periodik" />

            {props.scope_empty && props.scope_message && (
                <div className="ad-panel ad-animate" style={{ marginBottom: 14, borderColor: 'rgba(217,119,6,.4)' }}>
                    <div className="ad-panel__body" style={{ fontSize: 12.5, color: 'var(--ad-amber)' }}>
                        {props.scope_message}
                    </div>
                </div>
            )}

            {/* Filter */}
            <div className="ad-panel ad-animate" style={{ marginBottom: 14 }}>
                <div className="ad-panel__body" style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-end' }}>
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11, color: 'var(--ad-muted)' }}>
                        Periode Laporan
                        <select className="ad-dosen-select" value={draft.periode}
                            onChange={(e) => setDraft((d) => ({ ...d, periode: e.target.value }))}>
                            {PERIODE_OPTS.map((p) => <option key={p.key} value={p.key}>{p.label}</option>)}
                        </select>
                    </label>
                    {draft.periode === 'tahun_bulan' && (
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11, color: 'var(--ad-muted)' }}>
                            Bulan
                            <input type="month" className="ad-dosen-select" value={draft.bulan}
                                onChange={(e) => setDraft((d) => ({ ...d, bulan: e.target.value }))} />
                        </label>
                    )}
                    {draft.periode === 'custom' && (
                        <>
                            <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11, color: 'var(--ad-muted)' }}>
                                Dari
                                <input type="date" className="ad-dosen-select" value={draft.tanggal_mulai}
                                    onChange={(e) => setDraft((d) => ({ ...d, tanggal_mulai: e.target.value }))} />
                            </label>
                            <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11, color: 'var(--ad-muted)' }}>
                                Sampai
                                <input type="date" className="ad-dosen-select" value={draft.tanggal_akhir}
                                    onChange={(e) => setDraft((d) => ({ ...d, tanggal_akhir: e.target.value }))} />
                            </label>
                        </>
                    )}
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11, color: 'var(--ad-muted)' }}>
                        Kelas
                        <select className="ad-dosen-select" value={draft.kelas}
                            onChange={(e) => setDraft((d) => ({ ...d, kelas: e.target.value }))}>
                            <option value="">Semua Kelas</option>
                            {r.kelas_options.map((k) => <option key={k} value={k}>{k}</option>)}
                        </select>
                    </label>
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11, color: 'var(--ad-muted)' }}>
                        Tim PBL
                        <select className="ad-dosen-select" value={draft.tim}
                            onChange={(e) => setDraft((d) => ({ ...d, tim: e.target.value }))}>
                            <option value="">Semua Tim</option>
                            {r.tim_options.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </label>
                    <button type="button" className="ad-dosen-profile__btn" onClick={apply}>
                        <SlidersHorizontal size={13} /> Terapkan Filter
                    </button>
                    <button type="button" className="ad-icon-btn" onClick={reset} title="Reset">
                        <RotateCcw size={14} />
                    </button>
                </div>
            </div>

            {/* Tab + export */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center', marginBottom: 14 }}>
                <div className="ad-period" role="tablist">
                    {TABS.map((t) => (
                        <button key={t.key} type="button" role="tab" aria-selected={tab === t.key}
                            className={tab === t.key ? 'is-active' : ''} onClick={() => setTab(t.key)}>
                            <t.icon size={13} style={{ marginRight: 5, verticalAlign: -2 }} />{t.label}
                        </button>
                    ))}
                </div>
                <span style={{ marginLeft: 'auto', display: 'inline-flex', gap: 8 }}>
                    <a href={`${props.urls.excel}?${exportQuery()}`} className="ad-pill ad-pill--ok" style={{ textDecoration: 'none', padding: '8px 14px' }}>
                        <Download size={13} style={{ marginRight: 5, verticalAlign: -2 }} />Excel
                    </a>
                    <a href={`${props.urls.word}?${exportQuery()}`} className="ad-pill ad-pill--info" style={{ textDecoration: 'none', padding: '8px 14px' }}>
                        <FileText size={13} style={{ marginRight: 5, verticalAlign: -2 }} />Word
                    </a>
                    <a href={`${props.urls.print}?${exportQuery()}`} target="_blank" rel="noreferrer" className="ad-pill ad-pill--muted" style={{ textDecoration: 'none', padding: '8px 14px' }}>
                        <Printer size={13} style={{ marginRight: 5, verticalAlign: -2 }} />Cetak
                    </a>
                </span>
            </div>

            {/* KPI */}
            <div className="ad-kpi ad-report-kpis">
                {kpis.map((k) => (
                    <div key={k.label} className="ad-kpi__item" style={{ ['--kpi-accent' as string]: k.accent }}>
                        <div className="ad-kpi__icon"><k.icon /></div>
                        <div>
                            <span className="k">{k.label}</span>
                            <strong>{k.value.toLocaleString('id-ID')}</strong>
                            <small>{k.sub}</small>
                        </div>
                    </div>
                ))}
            </div>

            {tab === 'ringkasan' && (
                <>
                    <div className="ad-charts">
                        <div className="ad-panel ad-animate" style={{ gridColumn: 'span 2' }}>
                            <div className="ad-panel__head">
                                <div>
                                    <h3><BarChart3 size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Tren Kehadiran per Hari</h3>
                                    <small>Rekap status kehadiran selama {r.periode_label.toLowerCase()}</small>
                                </div>
                            </div>
                            <div className="ad-panel__body">
                                <TrendChart labels={r.chart.labels} hadir={r.chart.hadir} tl={r.chart.tidak_lengkap} alfa={r.chart.alfa} />
                                <div style={{ display: 'flex', gap: 14, marginTop: 10, fontSize: 11, color: 'var(--ad-muted)' }}>
                                    <span><i style={{ display: 'inline-block', width: 8, height: 8, borderRadius: 99, background: '#22c55e', marginRight: 5 }} />Hadir</span>
                                    <span><i style={{ display: 'inline-block', width: 8, height: 8, borderRadius: 99, background: '#f59e0b', marginRight: 5 }} />Tidak lengkap</span>
                                    <span><i style={{ display: 'inline-block', width: 8, height: 8, borderRadius: 99, background: '#ef4444', marginRight: 5 }} />Alfa</span>
                                </div>
                            </div>
                        </div>
                        <div className="ad-panel ad-animate">
                            <div className="ad-panel__head">
                                <div>
                                    <h3><PieChart size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Komposisi Kehadiran</h3>
                                    <small>Perbandingan status pada periode</small>
                                </div>
                            </div>
                            <div className="ad-panel__body" style={{ display: 'flex', justifyContent: 'center' }}>
                                <DonutChart data={donut} size={170} />
                            </div>
                        </div>
                    </div>
                    <div className="ad-charts">
                        <div className="ad-panel ad-animate">
                            <div className="ad-panel__head">
                                <div>
                                    <h3><Trophy size={15} style={{ verticalAlign: -2, marginRight: 6 }} />{r.rankings?.title ?? 'Rekor'}</h3>
                                    <small>{r.rankings?.subtitle ?? ''}</small>
                                </div>
                            </div>
                            <div className="ad-panel__body" style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                {(r.rankings?.items ?? []).length === 0 && <div className="ad-empty">Belum ada data rekor.</div>}
                                {(r.rankings?.items ?? []).slice(0, 8).map((it) => (
                                    <div key={it.rank} className="ad-mcal__row">
                                        <span className="ad-pill ad-pill--muted" style={{ minWidth: 24, justifyContent: 'center' }}>{it.rank}</span>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <strong style={{ fontSize: 12, display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{it.label}</strong>
                                            <small style={{ color: 'var(--ad-muted)' }}>{it.meta}</small>
                                        </div>
                                        <span className="ad-pill ad-pill--ok">{it.value_label}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="ad-panel ad-animate" style={{ gridColumn: 'span 2' }}>
                            <div className="ad-panel__head">
                                <div>
                                    <h3><Activity size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Aktivitas Terbaru</h3>
                                    <small>Tap absensi terakhir di periode ini</small>
                                </div>
                            </div>
                            <div className="ad-panel__body" style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                {r.recent.length === 0 && <div className="ad-empty">Belum ada aktivitas.</div>}
                                {r.recent.map((a, i) => (
                                    <div key={i} className="ad-mcal__row">
                                        <span className="ad-tap-time" style={{ minWidth: 74 }}>{a.waktu}</span>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <strong style={{ fontSize: 12, display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.nama}</strong>
                                            <small style={{ color: 'var(--ad-muted)' }}>{a.nim} · {a.kelas}{a.tim !== '-' ? ` · ${a.tim}` : ''}</small>
                                        </div>
                                        <span className="ad-pill ad-pill--info" style={{ textTransform: 'capitalize' }}>{a.status}</span>
                                        <span style={{ fontSize: 10.5, color: 'var(--ad-muted)' }}>{a.id_mesin}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {tab === 'mahasiswa' && (
                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3><Users size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Detail Rekap Mahasiswa</h3>
                            <small>{students.length.toLocaleString('id-ID')} mahasiswa · {r.periode_label}</small>
                        </div>
                        <div className="ad-dosen-search" style={{ maxWidth: 240 }}>
                            <Search size={14} />
                            <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Cari nama atau NIM…" />
                        </div>
                    </div>
                    <div className="ad-table-wrap">
                        <table className="ad-table">
                            <thead>
                                <tr><th>No</th><th>NIM</th><th>Mahasiswa</th><th>Kelas</th><th>Tim</th>
                                    <th>Hadir</th><th>Tdk Lengkap</th><th>Alfa</th><th>Persentase</th><th>Terakhir</th></tr>
                            </thead>
                            <tbody>
                                {pageRows.length === 0 && <tr><td colSpan={10} className="ad-empty">Tidak ada data.</td></tr>}
                                {pageRows.map((st, i) => (
                                    <tr key={st.user_id}>
                                        <td style={{ color: 'var(--ad-muted)' }}>{(page - 1) * PER_PAGE + i + 1}</td>
                                        <td style={{ fontVariantNumeric: 'tabular-nums' }}>{st.nim}</td>
                                        <td><strong>{st.nama}</strong></td>
                                        <td>{st.kelas}</td>
                                        <td style={{ fontSize: 11.5, color: 'var(--ad-muted)' }}>{st.tim}</td>
                                        <td><span className="ad-pill ad-pill--ok">{st.hadir}</span></td>
                                        <td><span className="ad-pill ad-pill--pending">{st.tidak_lengkap}</span></td>
                                        <td><span className="ad-pill ad-pill--muted">{st.alfa}</span></td>
                                        <td><PctPill value={pct(st)} /></td>
                                        <td style={{ fontSize: 11, color: 'var(--ad-muted)', whiteSpace: 'nowrap' }}>{st.terakhir ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {lastPage > 1 && (
                        <div className="ad-panel__body" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, paddingTop: 10 }}>
                            <button type="button" className="ad-icon-btn" disabled={page <= 1} onClick={() => setPage(page - 1)} aria-label="Sebelumnya"><ChevronLeft size={15} /></button>
                            <span style={{ fontSize: 12, color: 'var(--ad-muted)' }}>Halaman <strong>{page}</strong> / {lastPage}</span>
                            <button type="button" className="ad-icon-btn" disabled={page >= lastPage} onClick={() => setPage(page + 1)} aria-label="Berikutnya"><ChevronRight size={15} /></button>
                        </div>
                    )}
                </div>
            )}

            {tab === 'kelas' && (
                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head"><div><h3>Rekap per Kelas</h3><small>Agregasi kehadiran per kelas pada periode ini</small></div></div>
                    <GroupTable rows={groupBy('kelas')} firstCol="Kelas" />
                </div>
            )}
            {tab === 'tim' && (
                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head"><div><h3>Rekap per Tim PBL</h3><small>Agregasi kehadiran per tim pada periode ini</small></div></div>
                    <GroupTable rows={groupBy('tim')} firstCol="Tim PBL" />
                </div>
            )}
            {tab === 'mesin' && (
                <div className="ad-charts">
                    <div className="ad-panel ad-animate">
                        <div className="ad-panel__head"><div><h3><Cpu size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Mesin Tersibuk</h3><small>Total tap per mesin di periode ini · {s.mesin_online} mesin online</small></div></div>
                        <div className="ad-panel__body" style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {r.machines.length === 0 && <div className="ad-empty">Belum ada tap tercatat.</div>}
                            {r.machines.map((m) => {
                                const mx = Math.max(1, ...r.machines.map((x) => x.total));
                                return (
                                    <div key={m.id_mesin}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11.5, marginBottom: 3 }}>
                                            <span style={{ fontFamily: 'monospace' }}>{m.id_mesin}</span>
                                            <strong>{m.total.toLocaleString('id-ID')} tap</strong>
                                        </div>
                                        <div style={{ height: 7, borderRadius: 99, background: 'color-mix(in srgb, var(--ad-muted) 14%, transparent)', overflow: 'hidden' }}>
                                            <div style={{ width: `${(m.total / mx) * 100}%`, height: '100%', background: 'var(--ad-blue)', borderRadius: 99 }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                    <div className="ad-panel ad-animate" style={{ gridColumn: 'span 2' }}>
                        <div className="ad-panel__head"><div><h3><Activity size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Tap Terbaru per Mesin</h3><small>Aktivitas terakhir selama periode</small></div></div>
                        <div className="ad-panel__body" style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            {r.recent.length === 0 && <div className="ad-empty">Belum ada aktivitas.</div>}
                            {r.recent.map((a, i) => (
                                <div key={i} className="ad-mcal__row">
                                    <span className="ad-tap-time" style={{ minWidth: 74 }}>{a.waktu}</span>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <strong style={{ fontSize: 12, display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.nama}</strong>
                                        <small style={{ color: 'var(--ad-muted)' }}>{a.nim} · {a.kelas}</small>
                                    </div>
                                    <span className="ad-pill ad-pill--info" style={{ textTransform: 'capitalize' }}>{a.status}</span>
                                    <span style={{ fontSize: 10.5, color: 'var(--ad-muted)', fontFamily: 'monospace' }}>{a.id_mesin}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
