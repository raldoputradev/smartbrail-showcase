import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowDownToLine, ArrowUpFromLine, Cpu, DoorOpen, Fingerprint,
    Layers, LogIn, LogOut, RotateCcw, Search, Users as UsersIcon,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { LogTapProps } from '@admin/types';

const REFRESH_MS = 15_000;
const JENIS_ICON: Record<string, React.ReactNode> = {
    absensi: <Fingerprint size={15} />, ruangan: <DoorOpen size={15} />,
};

function initials(nama: string) {
    return nama.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

export default function LogTap(props: LogTapProps) {
    const f = props.filters;
    const meta = props.logs.meta;
    const [live, setLive] = useState(true);
    const [lastRefresh, setLastRefresh] = useState<Date | null>(null);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        let cancelled = false;
        timer.current = setInterval(() => {
            if (cancelled || document.hidden) return;
            router.reload({
                only: ['logs', 'stats', 'rincian', 'tren'],
                onSuccess: () => { setLive(true); setLastRefresh(new Date()); },
                onError: () => setLive(false),
            });
        }, REFRESH_MS);
        return () => { cancelled = true; if (timer.current) clearInterval(timer.current); };
    }, []);

    const s = props.stats;
    const maxTren = Math.max(1, ...props.tren.values);
    const kpis = [
        { label: 'Total Akses', value: s.total, sub: 'Rentang filter saat ini', icon: <Layers size={18} />, accent: '#0284c7' },
        { label: 'Akses Masuk', value: s.masuk, sub: `${s.masuk_pct}%`, icon: <LogIn size={18} />, accent: '#059669' },
        { label: 'Akses Keluar', value: s.pulang, sub: `${s.pulang_pct}%`, icon: <LogOut size={18} />, accent: '#7c3aed' },
        { label: 'Total Perangkat', value: s.perangkat, sub: 'Semua jenis terdaftar', icon: <Cpu size={18} />, accent: '#d97706' },
        { label: 'Pengguna Unik', value: s.unik, sub: 'Pada rentang filter', icon: <UsersIcon size={18} />, accent: '#0891b2' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Log Tap"
            subtitle="Riwayat semua aktivitas akses dari perangkat secara real-time">
            <Head title="Log Tap" />

            {/* Hero */}
            <div className="ad-log-hero">
                <div>
                    <h1>Log Akses Operasional</h1>
                    <p>Pantau seluruh aktivitas akses dari mesin absensi dan perangkat ruangan secara real-time — waktu, pengguna, dan status akses.</p>
                    <div className="ad-log-hero__chips">
                        <span><ArrowDownToLine size={13} /> {s.today} akses hari ini</span>
                        <span><ArrowUpFromLine size={13} /> {s.minggu_ini} minggu ini</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <svg viewBox="0 0 220 90" fill="none">
                        <rect x="40" y="18" width="140" height="62" rx="6" fill="rgba(255,255,255,.14)" />
                        <rect x="52" y="30" width="26" height="38" rx="3" fill="rgba(255,255,255,.22)" />
                        <rect x="86" y="30" width="26" height="38" rx="3" fill="rgba(255,255,255,.22)" />
                        <rect x="120" y="30" width="26" height="38" rx="3" fill="rgba(255,255,255,.22)" />
                        <rect x="154" y="30" width="14" height="50" rx="3" fill="rgba(255,255,255,.3)" />
                        <rect x="30" y="76" width="160" height="4" rx="2" fill="rgba(255,255,255,.25)" />
                        <circle cx="195" cy="22" r="10" fill="rgba(255,255,255,.18)" />
                        <rect x="15" y="34" width="14" height="46" rx="3" fill="rgba(255,255,255,.18)" />
                    </svg>
                </div>
            </div>

            {/* KPI */}
            <div className="ad-log-kpis">
                {kpis.map((k) => (
                    <div key={k.label} className="ad-log-kpi">
                        <span className="ad-log-kpi__ic" style={{ color: k.accent, background: `${k.accent}14` }}>{k.icon}</span>
                        <div>
                            <small>{k.label}</small>
                            <strong>{k.value.toLocaleString('id-ID')}</strong>
                            <span className="ad-log-kpi__sub">{k.sub}</span>
                        </div>
                    </div>
                ))}
            </div>

            {/* Rincian + tren */}
            <div className="ad-log-mid">
                <section className="ad-panel">
                    <div className="ad-panel__head"><div><h2>Rincian Akses per Jenis</h2></div></div>
                    <div className="ad-log-rincian">
                        {props.rincian.length === 0 && <div className="ad-empty">Belum ada akses pada rentang ini.</div>}
                        {props.rincian.map((r) => (
                            <button key={r.key} type="button"
                                className={cn('ad-log-jenis', f.jenis === r.key && 'is-active')}
                                onClick={() => nav({ jenis: f.jenis === r.key ? undefined : r.key, page: undefined })}>
                                <span className="ad-log-jenis__ic">{JENIS_ICON[r.key] ?? <Cpu size={15} />}</span>
                                <div>
                                    <strong>{r.label}</strong>
                                    <small>{r.total.toLocaleString('id-ID')} akses ({r.pct}%)</small>
                                </div>
                            </button>
                        ))}
                    </div>
                </section>
                <section className="ad-panel">
                    <div className="ad-panel__head"><div><h2>Tren Akses (Semua Jenis)</h2><p>Per jam pada rentang filter</p></div></div>
                    <div className="ad-mon-bars">
                        {props.tren.labels.map((l, i) => (
                            <div key={l} className="ad-mon-bar">
                                <span className="ad-mon-bar__v">{props.tren.values[i] || ''}</span>
                                <i style={{ height: `${Math.round((props.tren.values[i] / maxTren) * 100)}%` }} />
                                <small>{i % 2 === 0 ? l : ''}</small>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            {/* Filter */}
            <section className="ad-panel">
                <div className="ad-panel__head"><div><h2>Filter Data</h2></div>
                    <a href={props.urls.index} className="ad-mesin-btn ad-mesin-btn--ghost"><RotateCcw size={14} /> Reset Filter</a>
                </div>
                <form className="ad-log-filter" onSubmit={(e) => { e.preventDefault(); nav({ page: undefined }); }}>
                    <div className="ad-usr-search">
                        <Search size={15} />
                        <input name="q" defaultValue={f.q} placeholder="Cari nama, NIM/NIDN, atau ID pengguna…" />
                    </div>
                    <select name="jenis" defaultValue={f.jenis}>
                        <option value="">Semua Jenis</option>
                        {props.options.jenis.map((j) => <option key={j.key} value={j.key}>{j.label}</option>)}
                    </select>
                    <select name="id_mesin" defaultValue={f.id_mesin}>
                        <option value="">Semua Perangkat</option>
                        {props.options.mesin.map((m) => <option key={m.id} value={m.id}>{m.nama}</option>)}
                    </select>
                    <select name="arah" defaultValue={f.arah}>
                        <option value="">Semua Arah</option>
                        <option value="masuk">Masuk</option>
                        <option value="pulang">Keluar</option>
                    </select>
                    <input type="date" name="tanggal_mulai" defaultValue={f.tanggal_mulai} />
                    <span className="ad-log-sd">s/d</span>
                    <input type="date" name="tanggal_akhir" defaultValue={f.tanggal_akhir} />
                    <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary">Filter</button>
                </form>
            </section>

            {/* Tabel log */}
            <section className="ad-panel">
                <div className="ad-panel__head">
                    <div>
                        <h2>Data Log Akses Operasional
                            <span className={cn('ad-mon-live', live ? 'is-on' : 'is-off')}>{live ? 'Real-time (MQTT, Live)' : 'TERPUTUS'}</span>
                        </h2>
                        <p>{lastRefresh ? `Sinkron ${lastRefresh.toLocaleTimeString('id-ID')}` : 'Diperbarui otomatis tiap 15 detik'}</p>
                    </div>
                </div>
                <div className="ad-table-wrap ad-usr-free">
                    <table className="ad-table">
                        <thead>
                            <tr>
                                <th>No</th><th>Waktu Akses</th><th>Pengguna</th><th>Identitas</th>
                                <th>Jenis Akses</th><th>Arah</th><th>Perangkat/Ruangan</th>
                                <th>Status</th><th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {props.logs.data.length === 0 && (
                                <tr><td colSpan={9} className="ad-empty">Tidak ada log akses untuk filter ini.</td></tr>
                            )}
                            {props.logs.data.map((l, i) => (
                                <tr key={l.id}>
                                    <td>{meta.from + i}</td>
                                    <td>{l.waktu}</td>
                                    <td>
                                        <div className="ad-usr-name">
                                            {l.pengguna.foto
                                                ? <img className="ad-usr-ava" style={{ width: 32, height: 32 }} src={l.pengguna.foto} alt="" />
                                                : <span className={cn('ad-usr-ava', `ad-usr-ava--${l.pengguna.role || 'mahasiswa'}`)} style={{ width: 32, height: 32, fontSize: 11 }}>{initials(l.pengguna.nama)}</span>}
                                            <div>
                                                <strong>{l.pengguna.nama}</strong>
                                                <small>{l.pengguna.email}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code>{l.pengguna.identitas}</code></td>
                                    <td><span className="ad-pill ad-pill--role-mahasiswa">{l.jenis}</span></td>
                                    <td>
                                        {l.arah === null ? '—' : (
                                            <span className={cn('ad-pill', l.arah === 'masuk' ? 'ad-pill--ok' : 'ad-pill--danger')}>
                                                {l.arah === 'masuk' ? '+ Masuk' : '− Keluar'}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <strong>{l.perangkat.nama}</strong>
                                        {l.perangkat.ruangan && <small className="ad-mon-sub">{l.perangkat.ruangan}</small>}
                                    </td>
                                    <td><span className="ad-pill ad-pill--ok">✓ {l.status}</span></td>
                                    <td>{l.keterangan}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="ad-mesin-pager">
                    <small>Menampilkan {meta.from}–{meta.to} dari {meta.total.toLocaleString('id-ID')} log</small>
                    <div className="ad-usr-perpage">
                        <span>Tampilkan</span>
                        <select value={f.per_page} onChange={(e) => nav({ per_page: e.target.value, page: undefined })}>
                            {[10, 20, 50].map((n) => <option key={n} value={n}>{n}</option>)}
                        </select>
                    </div>
                    {meta.last_page > 1 && (
                        <div className="ad-mesin-pager__btns">
                            <button type="button" disabled={meta.page <= 1} onClick={() => nav({ page: meta.page - 1 })}>‹</button>
                            {Array.from({ length: meta.last_page }, (_, i) => i + 1)
                                .filter((p) => p === 1 || p === meta.last_page || Math.abs(p - meta.page) <= 1)
                                .reduce<(number | '…')[]>((acc, p, i, arr) => {
                                    if (i > 0 && p - (arr[i - 1] as number) > 1) acc.push('…');
                                    acc.push(p); return acc;
                                }, [])
                                .map((p, i) => p === '…'
                                    ? <span key={`e${i}`} className="ad-mesin-pager__ellipsis">…</span>
                                    : <button key={p} type="button" className={cn(meta.page === p && 'is-active')} onClick={() => nav({ page: p })}>{p}</button>)}
                            <button type="button" disabled={meta.page >= meta.last_page} onClick={() => nav({ page: meta.page + 1 })}>›</button>
                        </div>
                    )}
                </div>
            </section>
        </AdminShell>
    );
}
