import { useLayoutEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    BookOpen, Calendar, CalendarCheck, ClipboardCheck, Cpu, Database,
    DoorOpen, FileText, Fingerprint, Server, Users,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { ChartSkeleton, DonutChart, LineChart } from '@admin/components/Charts';
import { MiniCalView, shiftMonth } from '@admin/components/MiniCalView';
import { asset } from '@admin/lib/asset';
import type { DashboardProps, LineChartData, MiniCal, SharedProps } from '@admin/types';

const KPI_ICONS: Record<string, typeof Users> = {
    users: Users, fingerprint: Fingerprint, 'door-open': DoorOpen,
    cpu: Cpu, 'calendar-check': CalendarCheck,
};
const STATUS_ICONS: Record<string, typeof Server> = {
    server: Server, database: Database, cpu: Cpu, 'clipboard-check': ClipboardCheck,
};
const ACTION_ICONS: Record<string, typeof Calendar> = {
    calendar: Calendar, 'door-open': DoorOpen, users: Users, 'file-text': FileText,
};

type Mode = 'hari' | 'minggu' | 'bulan' | 'tahun';
const MODES: { key: Mode; label: string }[] = [
    { key: 'hari', label: 'Hari' },
    { key: 'minggu', label: 'Minggu' },
    { key: 'bulan', label: 'Bulan' },
    { key: 'tahun', label: 'Tahun' },
];

export default function Dashboard(props: DashboardProps) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;
    const [mode, setMode] = useState<Mode>('minggu');
    const [line, setLine] = useState<LineChartData>(props.line);
    const [lineLoading, setLineLoading] = useState(false);
    const [lineError, setLineError] = useState(false);
    // Mini-kalender agenda: điều hướng tháng qua endpoint JSON, không reload trang.
    const [miniCal, setMiniCal] = useState<MiniCal>(props.kalender_mini);
    const [calBusy, setCalBusy] = useState(false);
    const goCalMonth = async (bulan: string) => {
        if (!props.urls.minical || bulan === miniCal.bulan || calBusy) return;
        setCalBusy(true);
        try {
            const res = await fetch(`${props.urls.minical}?bulan=${bulan}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            setMiniCal(await res.json());
        } catch {
            // Giữ nguyên tháng đang xem nếu tải lỗi.
        } finally {
            setCalBusy(false);
        }
    };

    // Selaraskan tinggi tabel aktivitas dengan bawah rail kanan (pengajuan + kalender + aksi).
    const feedWrapRef = useRef<HTMLDivElement | null>(null);
    const railRef = useRef<HTMLDivElement | null>(null);
    useLayoutEffect(() => {
        const measure = () => {
            const wrap = feedWrapRef.current;
            const rail = railRef.current;
            if (!wrap || !rail) return;
            if (window.innerWidth <= 1000) {
                wrap.style.maxHeight = '';
                return;
            }
            const space = rail.getBoundingClientRect().bottom - wrap.getBoundingClientRect().top;
            wrap.style.maxHeight = `${Math.max(280, Math.round(space - 12))}px`;
        };
        measure();
        const ro = new ResizeObserver(measure);
        if (railRef.current) ro.observe(railRef.current);
        window.addEventListener('resize', measure);
        return () => { ro.disconnect(); window.removeEventListener('resize', measure); };
    }, [props.feed.length, props.pengajuan.length, props.agenda_terdekat.length]);

    const kpis = [
        {
            key: 'users', label: 'Total Pengguna', value: props.stats.total_pengguna,
            sub: `${props.stats.pengguna_aktif} aktif · ${props.stats.pengguna_online ?? 0} online · ${props.stats.pengguna_pending} pending`,
            accent: '#0284c7', icon: 'users',
            url: props.is_admin ? route('admin.users') : route('admin.attendance'),
        },
        {
            key: 'fingerprint', label: 'Sidik Jari Terdaftar', value: props.stats.sidik_jari_terdaftar,
            sub: 'Mahasiswa aktif terdaftar', accent: '#7c3aed', icon: 'fingerprint',
            url: route('admin.fingerprint'),
        },
        {
            key: 'ruangan', label: 'Ruangan Aktif', value: props.stats.ruangan_aktif,
            sub: '— tidak berubah', accent: '#0f766e', icon: 'door-open',
            url: route('admin.ruangan.index'),
        },
        {
            key: 'mesin', label: 'Mesin IoT', value: `${props.stats.mesin_aktif}/${props.stats.mesin_total}`,
            sub: props.stats.mesin_meta || `${props.stats.mesin_online} online`,
            subClass: 'up', accent: '#b45309', icon: 'cpu',
            url: route('admin.mesin.index'),
        },
        {
            key: 'pengajuan', label: 'Pengajuan Pending', value: props.stats.pengajuan_pending,
            sub: props.stats.pengajuan_pending > 0 ? 'Menunggu keputusan' : 'Tidak ada antrean',
            subClass: props.stats.pengajuan_pending > 0 ? 'warn' : 'up',
            accent: '#be123c', icon: 'calendar-check',
            url: route('admin.pengajuan.index'),
        },
    ];

    const changeMode = async (m: Mode) => {
        setMode(m);
        setLineLoading(true);
        setLineError(false);
        try {
            const today = new Date().toISOString().slice(0, 10);
            const res = await fetch(
                `${props.urls.data}?mode=${m}&tanggal=${today}&aktivitas=semua`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
            );
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            setLine(json.line);
        } catch {
            setLineError(true);
        } finally {
            setLineLoading(false);
        }
    };

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Dashboard Admin">
            <Head title="Dashboard Admin" />

            {/* Zona hero — foto kampus menyatu sebagai background konten atas,
                memudar ke background halaman; bukan kartu/banner terpisah */}
            <div
                className="ad-hero-zone"
                style={{ ['--ad-hero-img' as string]: `url('${asset('images/campus-polybatam.jpg')}')` }}
            >
                <div className="ad-hero ad-animate">
                    <h2>Selamat datang, {user?.nama_lengkap ?? 'Admin'} 👋</h2>
                    <p>Kelola operasional laboratorium, absensi, ruangan, perangkat, dan pengguna SmartBrail secara terintegrasi.</p>
                </div>

                {/* KPI — tetap kartu tersendiri di atas zona background */}
                <div className="ad-kpi ad-animate">
                    {kpis.map((k) => {
                        const Icon = KPI_ICONS[k.icon];
                        return (
                            <a key={k.key} href={k.url} className="ad-kpi__item" style={{ ['--kpi-accent' as string]: k.accent }}>
                                <div className="ad-kpi__icon"><Icon /></div>
                                <div>
                                    <span className="k">{k.label}</span>
                                    <strong>{typeof k.value === 'number' ? k.value.toLocaleString('id-ID') : k.value}</strong>
                                    <small className={k.subClass ?? ''}>{k.sub}</small>
                                </div>
                            </a>
                        );
                    })}
                </div>
            </div>

            {/* Charts + status */}
            <div className="ad-charts">
                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Tren Aktivitas IoT</h3>
                            <small>{line.subtitle ?? 'Jumlah tap absensi per periode'}</small>
                        </div>
                        <div className="ad-period" role="group" aria-label="Periode grafik">
                            {MODES.map((m) => (
                                <button
                                    key={m.key} type="button"
                                    className={mode === m.key ? 'is-active' : ''}
                                    onClick={() => changeMode(m.key)}
                                >{m.label}</button>
                            ))}
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        {lineLoading ? (
                            <ChartSkeleton />
                        ) : lineError ? (
                            <div className="ad-empty">
                                Gagal memuat grafik.{' '}
                                <button type="button" className="ad-link" style={{ background: 'none', border: 0, cursor: 'pointer' }} onClick={() => changeMode(mode)}>
                                    Coba lagi
                                </button>
                            </div>
                        ) : (
                            <LineChart data={line} />
                        )}
                    </div>
                </div>

                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Distribusi Pengguna</h3>
                            <small>Komposisi akun dalam sistem</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <DonutChart data={props.donut} />
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 8 }}>
                            {props.donut.labels.map((label, i) => {
                                const val = props.donut.values[i] ?? 0;
                                const pct = Math.round((val / Math.max(1, props.donut.total)) * 1000) / 10;
                                return (
                                    <div key={label} style={{ display: 'grid', gridTemplateColumns: '8px 1fr auto', gap: 10, alignItems: 'center', fontSize: 12.5 }}>
                                        <span style={{ width: 8, height: 8, borderRadius: 999, background: props.donut.colors[i] ?? '#94a3b8' }} />
                                        <span>{label}</span>
                                        <span style={{ textAlign: 'right', fontVariantNumeric: 'tabular-nums', display: 'inline-flex', alignItems: 'center' }}>
                                            <strong>{val.toLocaleString('id-ID')}</strong>
                                            <span className="ad-donut-sep" aria-hidden="true" />
                                            <small style={{ color: 'var(--ad-muted)' }}>{pct}%</small>
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Status Sistem</h3>
                            <small>Kondisi layanan dan perangkat utama</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        {props.status_sistem.map((s) => {
                            const Icon = STATUS_ICONS[s.icon] ?? Server;
                            return (
                                <div key={s.nama} className="ad-status-row">
                                    <Icon />
                                    <span className="ad-status-row__name">{s.nama}</span>
                                    <span className="ad-status-row__val">{s.status}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Feed + pengajuan + aksi */}
            <div className="ad-feed-grid">
                <div className="ad-panel ad-animate ad-panel--fill">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Aktivitas Absensi Terbaru</h3>
                            <small>Snapshot tap absensi terbaru dari mesin IoT</small>
                        </div>
                        <a href={props.urls.feed_semua} className="ad-link">Lihat Semua →</a>
                    </div>
                    <div className="ad-table-wrap ad-feedlist" ref={feedWrapRef}>
                        <div className="ad-feedlist__head">
                            <span>Waktu</span><span>Nama</span><span>NIM</span><span>Kelas / Tim</span><span>Status</span>
                        </div>
                        {props.feed.length === 0 && (
                            <div className="ad-empty" style={{ flex: 1 }}>Belum ada tap absensi.</div>
                        )}
                        {props.feed.map((t, i) => (
                            <div key={i} className="ad-feedlist__row">
                                <span className="ad-feedlist__waktu">{t.waktu}</span>
                                <div className="ad-feed-user">
                                    <div className="ad-ava">
                                        {t.foto ? <img src={t.foto} alt="" /> : t.nama.charAt(0).toUpperCase()}
                                    </div>
                                    <strong>{t.nama}</strong>
                                </div>
                                <span className="ad-feedlist__nim">{t.nim}</span>
                                <span className="ad-feedlist__kelas">{t.kelas_tim}</span>
                                <span><span className="ad-pill ad-pill--ok">Masuk</span></span>
                            </div>
                        ))}
                    </div>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }} ref={railRef}>
                    <div className="ad-panel ad-animate">
                        <div className="ad-panel__head">
                            <div>
                                <h3>Pengajuan Ruangan Terbaru</h3>
                                <small>Permintaan penggunaan ruangan dari pengguna</small>
                            </div>
                            <a href={props.urls.pengajuan_semua} className="ad-link">Lihat Semua →</a>
                        </div>
                        <div className="ad-table-wrap">
                            <table className="ad-table">
                                <thead>
                                    <tr><th>Pemohon</th><th>Ruangan</th><th>Jadwal</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    {props.pengajuan.length === 0 && (
                                        <tr><td colSpan={4} className="ad-empty">Belum ada pengajuan ruangan.</td></tr>
                                    )}
                                    {props.pengajuan.map((p, i) => (
                                        <tr key={i}>
                                            <td>
                                                <div className="ad-feed-user">
                                                    <div className="ad-ava" style={{ background: 'rgba(74,144,217,.2)', color: '#2563eb' }}>
                                                        {p.foto ? <img src={p.foto} alt="" /> : p.pemohon.charAt(0).toUpperCase()}
                                                    </div>
                                                    <strong>{p.pemohon}</strong>
                                                </div>
                                            </td>
                                            <td>{p.ruangan}</td>
                                            <td style={{ whiteSpace: 'nowrap', fontSize: 11.5, color: 'var(--ad-muted)' }}>
                                                {p.tanggal}<br />{p.jam}
                                            </td>
                                            <td>
                                                <span className={`ad-pill ${p.status === 'disetujui' || p.status === 'selesai' ? 'ad-pill--ok' : p.status === 'ditolak' || p.status === 'dibatalkan' ? 'ad-pill--muted' : 'ad-pill--pending'}`}>
                                                    {p.status.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="ad-panel ad-animate">
                        <div className="ad-panel__head">
                            <div>
                                <h3>Kalender Agenda</h3>
                                <small>Berlaku ke kalender seluruh pengguna</small>
                            </div>
                            {props.urls.kalender && <a href={props.urls.kalender} className="ad-link">Buka Kalender →</a>}
                        </div>
                        <div className="ad-panel__body" style={{ paddingTop: 4 }}>
                            <MiniCalView cal={miniCal} onNav={goCalMonth} busy={calBusy} />
                        </div>
                    </div>

                    <div className="ad-panel ad-animate">
                        <div className="ad-panel__head">
                            <div>
                                <h3>Aksi Cepat</h3>
                                <small>Akses fitur utama dengan cepat</small>
                            </div>
                        </div>
                        <div className="ad-panel__body">
                            <div className="ad-actions-grid">
                                {props.aksi_cepat.map((a) => {
                                    const Icon = ACTION_ICONS[a.icon] ?? Calendar;
                                    return (
                                        <a key={a.url} href={a.url} className="ad-action">
                                            <Icon /> {a.label}
                                        </a>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
