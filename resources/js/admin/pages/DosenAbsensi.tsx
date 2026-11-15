import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    CalendarCheck, ChevronLeft, ChevronRight, Download, Printer, RotateCcw, Search,
    Users, Wifi, WifiOff,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart } from '@admin/components/Charts';
import { subscribePrivate } from '@admin/lib/live';
import type { DosenAbsensiProps, RekapRow, RekapSnapshot, SharedProps } from '@admin/types';

const TABS = [
    { key: 'all', label: 'Semua' },
    { key: 'hadir', label: 'Hadir' },
    { key: 'alfa', label: 'Alfa' },
    { key: 'tidak_lengkap', label: 'Tidak Lengkap' },
] as const;

const STATUS_META: Record<string, { label: string; cls: string }> = {
    lengkap: { label: 'Hadir', cls: 'ad-pill--ok' },
    terlambat: { label: 'Terlambat', cls: 'ad-pill--pending' },
    masuk: { label: 'Hanya Masuk', cls: 'ad-pill--info' },
    tidak_lengkap: { label: 'Tidak Lengkap', cls: 'ad-pill--pending' },
    alfa: { label: 'Tidak Hadir', cls: 'ad-pill--muted' },
    belum: { label: 'Belum Tap', cls: 'ad-pill--muted' },
    tidak_wajib: { label: 'Tidak Wajib', cls: 'ad-pill--muted' },
};

function StatusPill({ status }: { status: string }) {
    const meta = STATUS_META[status] ?? { label: status.replace(/_/g, ' '), cls: 'ad-pill--muted' };
    return <span className={`ad-pill ${meta.cls}`} style={{ textTransform: 'capitalize' }}>{meta.label}</span>;
}

export default function DosenAbsensi(props: DosenAbsensiProps) {
    const { reverb } = usePage<SharedProps>().props;
    const [snap, setSnap] = useState<RekapSnapshot>(props.initial);
    const [filter, setFilter] = useState({ ...props.filter });
    const [tab, setTab] = useState<string>(props.initial.tab ?? 'all');
    const [page, setPage] = useState(1);
    const [busy, setBusy] = useState(false);
    const [live, setLive] = useState<'on' | 'off'>('off');
    const stateRef = useRef({ filter, tab, page });
    stateRef.current = { filter, tab, page };

    const refresh = useCallback(async (over?: { filter?: typeof filter; tab?: string; page?: number }) => {
        const cur = { ...stateRef.current, ...over };
        const params = new URLSearchParams();
        Object.entries(cur.filter).forEach(([k, v]) => { if (v) params.set(k, String(v)); });
        params.set('tab', cur.tab);
        if (cur.page > 1) params.set('page', String(cur.page));
        setBusy(true);
        try {
            const res = await fetch(`${props.urls.snapshot}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            setSnap(await res.json());
        } catch {
            // Pertahankan data lama bila gagal.
        } finally {
            setBusy(false);
        }
    }, [props.urls.snapshot]);
    const refreshRef = useRef(refresh);
    refreshRef.current = refresh;

    // Realtime: wake event dari Reverb → refetch snapshot (tanpa polling ketat).
    useEffect(() => {
        if (!reverb) return;
        setLive('on');
        const off = subscribePrivate(reverb, 'absensi.staff.dosen', {
            'snapshot.wake': () => void refreshRef.current(),
        });
        return () => { setLive('off'); off(); };
    }, [reverb]);

    // Jaring pengaman ringan (90 dtk) bila WS padam — bukan polling utama.
    useEffect(() => {
        const id = setInterval(() => { if (live !== 'on') void refreshRef.current(); }, 90000);
        return () => clearInterval(id);
    }, [live]);

    const applyFilter = (patch: Partial<typeof filter>) => {
        const next = { ...filter, ...patch };
        setFilter(next);
        setPage(1);
        void refresh({ filter: next, page: 1 });
    };
    const changeTab = (t: string) => {
        setTab(t);
        setPage(1);
        void refresh({ tab: t, page: 1 });
    };
    const changePage = (p: number) => {
        setPage(p);
        void refresh({ page: p });
    };
    const resetFilter = () => {
        const cleared = { q: '', kelas: '', tim: '', angkatan: '', program_studi_id: '', sesi: '', shift: '', semester: '', status: '', date: props.filter.date };
        setFilter(cleared);
        setPage(1);
        void refresh({ filter: cleared, page: 1 });
    };

    const exportQuery = () => {
        const params = new URLSearchParams();
        Object.entries(filter).forEach(([k, v]) => { if (v) params.set(k, String(v)); });
        params.set('tab', tab);
        return params.toString();
    };

    const s = snap.summary;
    const pg = snap.pagination;
    const ringkasan = {
        labels: ['Hadir', 'Terlambat', 'Belum/Alfa'],
        values: [s.hadir, s.terlambat, Math.max(0, s.total - s.hadir - s.terlambat)],
        colors: ['#22c55e', '#d97706', '#64748b'],
        total: s.total,
        center_label: `${Math.round(s.persentase_lengkap)}%`,
    };
    const distribusi = snap.chart.labels.map((label, i) => ({
        label, value: snap.chart.values[i] ?? 0, color: snap.chart.colors[i] ?? '#94a3b8',
    })).filter((d) => d.value > 0);
    const distMax = Math.max(1, ...distribusi.map((d) => d.value));
    const startNo = (pg.page - 1) * pg.per_page;

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Rekap Absensi Mahasiswa"
            subtitle={`Pantau kehadiran mahasiswa laboratorium — ${props.meta.tanggal}`}>
            <Head title="Rekap Absensi" />

            {/* Bar aksi + status live */}
            <div className="ad-panel ad-animate" style={{ marginBottom: 14 }}>
                <div className="ad-panel__body" style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                    <div className="ad-dosen-search">
                        <Search size={15} />
                        <input
                            value={filter.q}
                            onChange={(e) => setFilter((f) => ({ ...f, q: e.target.value }))}
                            onKeyDown={(e) => { if (e.key === 'Enter') applyFilter({}); }}
                            onBlur={() => applyFilter({})}
                            placeholder="Cari mahasiswa, NIM…"
                        />
                    </div>
                    <span className={`ad-live${live === 'on' ? ' is-on' : ''}`} title={live === 'on' ? 'Terhubung realtime (Reverb)' : 'Realtime tidak aktif — data diperbarui berkala'}>
                        {live === 'on' ? <Wifi size={12} /> : <WifiOff size={12} />}
                        {live === 'on' ? 'Live' : 'Offline'}
                    </span>
                    <button type="button" className="ad-icon-btn" onClick={() => refresh()} disabled={busy}
                        aria-label="Muat ulang" title="Muat ulang">
                        <RotateCcw size={15} className={busy ? 'ad-spin' : ''} />
                    </button>
                    <span style={{ marginLeft: 'auto', display: 'inline-flex', gap: 8 }}>
                        <a href={`${props.urls.export}?${exportQuery()}`} className="ad-pill ad-pill--info" style={{ textDecoration: 'none', padding: '8px 14px' }}>
                            <Download size={13} style={{ marginRight: 5, verticalAlign: -2 }} />Ekspor
                        </a>
                        <a href={`${props.urls.print}?${exportQuery()}`} target="_blank" rel="noreferrer" className="ad-pill ad-pill--muted" style={{ textDecoration: 'none', padding: '8px 14px' }}>
                            <Printer size={13} style={{ marginRight: 5, verticalAlign: -2 }} />Cetak
                        </a>
                    </span>
                </div>
            </div>

            {snap.kelas_scope_empty && snap.kelas_scope_message && (
                <div className="ad-panel ad-animate" style={{ marginBottom: 14, borderColor: 'rgba(217,119,6,.4)' }}>
                    <div className="ad-panel__body" style={{ fontSize: 12.5, color: 'var(--ad-amber)' }}>
                        {snap.kelas_scope_message}
                    </div>
                </div>
            )}

            {/* Tab + filter */}
            <div className="ad-panel ad-animate" style={{ marginBottom: 14 }}>
                <div className="ad-panel__body" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <div className="ad-period" role="tablist" aria-label="Tab rekap">
                        {TABS.map((t) => (
                            <button key={t.key} type="button" role="tab"
                                aria-selected={tab === t.key}
                                className={tab === t.key ? 'is-active' : ''}
                                onClick={() => changeTab(t.key)}>
                                {t.label}
                                <span style={{ marginLeft: 6, opacity: .75, fontVariantNumeric: 'tabular-nums' }}>
                                    {snap.tab_counts[t.key]}
                                </span>
                            </button>
                        ))}
                    </div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
                        <select className="ad-dosen-select" value={filter.kelas} disabled={busy}
                            onChange={(e) => applyFilter({ kelas: e.target.value })} aria-label="Kelas">
                            <option value="">Semua Kelas</option>
                            {props.options.kelas.map((k) => <option key={k} value={k}>{k}</option>)}
                        </select>
                        <select className="ad-dosen-select" value={filter.tim} disabled={busy}
                            onChange={(e) => applyFilter({ tim: e.target.value })} aria-label="Tim">
                            <option value="">Semua Tim</option>
                            {props.options.tim.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                        <select className="ad-dosen-select" value={filter.program_studi_id} disabled={busy}
                            onChange={(e) => applyFilter({ program_studi_id: e.target.value })} aria-label="Program studi">
                            <option value="">Semua Prodi</option>
                            {props.options.prodi.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                        <select className="ad-dosen-select" value={filter.semester} disabled={busy}
                            onChange={(e) => applyFilter({ semester: e.target.value })} aria-label="Semester">
                            <option value="">Semua Semester</option>
                            {props.options.semester.map((sm) => <option key={sm} value={sm}>Semester {sm}</option>)}
                        </select>
                        <select className="ad-dosen-select" value={filter.sesi} disabled={busy}
                            onChange={(e) => applyFilter({ sesi: e.target.value })} aria-label="Sesi">
                            <option value="">Semua Sesi</option>
                            {props.options.sesi.map((x) => <option key={x} value={x}>{x[0].toUpperCase() + x.slice(1)}</option>)}
                        </select>
                        <button type="button" className="ad-icon-btn" onClick={resetFilter} disabled={busy} title="Reset filter">
                            <RotateCcw size={14} />
                        </button>
                    </div>
                </div>
            </div>

            {/* Ringkasan */}
            <div className="ad-charts">
                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Ringkasan Kehadiran</h3>
                            <small>{props.meta.tanggal} · {s.total.toLocaleString('id-ID')} mahasiswa</small>
                        </div>
                    </div>
                    <div className="ad-panel__body" style={{ display: 'flex', gap: 16, alignItems: 'center', flexWrap: 'wrap' }}>
                        <DonutChart data={ringkasan} size={150} />
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 130 }}>
                            {[
                                { l: 'Hadir', v: s.hadir, c: '#22c55e' },
                                { l: 'Terlambat', v: s.terlambat, c: '#d97706' },
                                { l: 'Tidak hadir', v: s.alfa, c: '#e11d48' },
                                { l: 'Belum tap', v: s.belum, c: '#94a3b8' },
                            ].map((d) => (
                                <div key={d.l} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12 }}>
                                    <span style={{ width: 8, height: 8, borderRadius: 99, background: d.c, flexShrink: 0 }} />
                                    <span style={{ flex: 1 }}>{d.l}</span>
                                    <strong>{d.v.toLocaleString('id-ID')}</strong>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Tap Masuk Terbaru</h3>
                            <small>Realtime dari mesin absensi</small>
                        </div>
                        <span className={`ad-live${live === 'on' ? ' is-on' : ''}`}>
                            {live === 'on' ? <Wifi size={11} /> : <WifiOff size={11} />}Live
                        </span>
                    </div>
                    <div className="ad-panel__body" style={{ paddingTop: 4 }}>
                        {snap.tap_masuk.length === 0 && <div className="ad-empty">Belum ada tap hari ini.</div>}
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, maxHeight: 230, overflowY: 'auto' }}>
                            {snap.tap_masuk.slice(0, 10).map((t) => (
                                <div key={t.urutan} className="ad-mcal__row">
                                    <span className="ad-tap-time">{t.waktu_tap}</span>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <strong style={{ fontSize: 12, display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{t.nama}</strong>
                                        <small style={{ color: 'var(--ad-muted)' }}>{t.nim} · {t.kelas}{t.sesi ? ` · ${t.sesi}` : ''}</small>
                                    </div>
                                    {t.menit_terlambat > 0 && <span className="ad-pill ad-pill--pending">+{t.menit_terlambat}m</span>}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Distribusi Status</h3>
                            <small>Berdasarkan {s.total.toLocaleString('id-ID')} mahasiswa</small>
                        </div>
                    </div>
                    <div className="ad-panel__body" style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {distribusi.length === 0 && <div className="ad-empty">Belum ada data.</div>}
                        {distribusi.map((d) => (
                            <div key={d.label}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11.5, marginBottom: 3 }}>
                                    <span>{d.label}</span>
                                    <strong>{d.value.toLocaleString('id-ID')}</strong>
                                </div>
                                <div style={{ height: 7, borderRadius: 99, background: 'color-mix(in srgb, var(--ad-muted) 14%, transparent)', overflow: 'hidden' }}>
                                    <div style={{ width: `${(d.value / distMax) * 100}%`, height: '100%', background: d.color, borderRadius: 99, transition: 'width .4s ease' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Tabel detail */}
            <div className="ad-panel ad-animate">
                <div className="ad-panel__head">
                    <div>
                        <h3><CalendarCheck size={15} style={{ verticalAlign: -2, marginRight: 6 }} />Detail Rekap Absensi</h3>
                        <small>Daftar kehadiran per mahasiswa — {pg.total.toLocaleString('id-ID')} data</small>
                    </div>
                    <a href={props.urls.reports} className="ad-link"><Users size={13} style={{ marginRight: 4, verticalAlign: -2 }} />Laporan Periodik →</a>
                </div>
                <div className="ad-table-wrap">
                    <table className="ad-table">
                        <thead>
                            <tr>
                                <th>No</th><th>NIM</th><th>Mahasiswa</th><th>Kelas</th><th>Prodi</th>
                                <th>Sesi</th><th>Status</th><th>Jam Masuk</th><th>Jam Pulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            {snap.rows.length === 0 && (
                                <tr><td colSpan={9} className="ad-empty">Tidak ada data untuk filter ini.</td></tr>
                            )}
                            {snap.rows.map((r: RekapRow, i: number) => (
                                <tr key={`${r.nim}-${r.sesi}-${i}`}>
                                    <td style={{ color: 'var(--ad-muted)' }}>{startNo + i + 1}</td>
                                    <td style={{ fontVariantNumeric: 'tabular-nums' }}>{r.nim}</td>
                                    <td>
                                        <strong>{r.nama}</strong>
                                        {r.pulang_lembur && <span className="ad-pill ad-pill--pending" style={{ marginLeft: 5, fontSize: 9.5 }}>Lembur</span>}
                                    </td>
                                    <td>{r.kelas ?? '—'}</td>
                                    <td style={{ fontSize: 11.5, color: 'var(--ad-muted)' }}>{r.prodi ?? '—'}</td>
                                    <td style={{ textTransform: 'capitalize' }}>{r.sesi ?? r.shift ?? '—'}</td>
                                    <td><StatusPill status={r.status} /></td>
                                    <td style={{ fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>
                                        {r.jam_masuk ?? '—'}
                                        {r.menit_terlambat_masuk > 0 && (
                                            <span className="ad-pill ad-pill--pending" style={{ marginLeft: 4, fontSize: 9.5 }}>+{r.menit_terlambat_masuk}m</span>
                                        )}
                                    </td>
                                    <td style={{ fontVariantNumeric: 'tabular-nums' }}>{r.jam_pulang ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {pg.last_page > 1 && (
                    <div className="ad-panel__body" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, paddingTop: 10 }}>
                        <button type="button" className="ad-icon-btn" disabled={busy || pg.page <= 1}
                            onClick={() => changePage(pg.page - 1)} aria-label="Halaman sebelumnya">
                            <ChevronLeft size={15} />
                        </button>
                        <span style={{ fontSize: 12, color: 'var(--ad-muted)' }}>
                            Halaman <strong>{pg.page}</strong> / {pg.last_page}
                        </span>
                        <button type="button" className="ad-icon-btn" disabled={busy || pg.page >= pg.last_page}
                            onClick={() => changePage(pg.page + 1)} aria-label="Halaman berikutnya">
                            <ChevronRight size={15} />
                        </button>
                    </div>
                )}
            </div>
        </AdminShell>
    );
}
