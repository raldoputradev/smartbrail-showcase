import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Calendar, ClipboardCheck, GraduationCap, LogIn, LogOut, Megaphone, Users,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { ChartSkeleton, DonutChart, LineChart } from '@admin/components/Charts';
import { MiniCalView, shiftMonth } from '@admin/components/MiniCalView';
import { asset } from '@admin/lib/asset';
import type {
    DosenDashboardProps, DosenAnalytics, LineChartData, DonutData, MiniCal, SharedProps,
} from '@admin/types';

type Mode = 'hari' | 'minggu' | 'bulan' | 'tahun';
const MODES: { key: Mode; label: string }[] = [
    { key: 'hari', label: 'Hari' },
    { key: 'minggu', label: 'Minggu' },
    { key: 'bulan', label: 'Bulan' },
    { key: 'tahun', label: 'Tahun' },
];

const JENIS_TONE: Record<string, string> = {
    libur: 'libur', peringatan: 'peringatan', ujian: 'ujian',
    perubahan_jam: 'khusus', agenda: 'info', informasi: 'info',
};

function toLineData(a: DosenAnalytics): LineChartData {
    return {
        labels: a.line.labels,
        subtitle: a.line.subtitle,
        series: [
            { key: 'absensi', label: 'Absensi', color: '#0f766e', data: a.line.absensi ?? a.line.values },
        ],
    };
}

function toDonutData(a: DosenAnalytics): DonutData {
    return {
        labels: a.donut.labels,
        values: a.donut.values,
        colors: ['#22c55e', '#64748b'],
        total: a.donut.values.reduce((s, v) => s + v, 0),
        center_label: 'Hadir unik',
    };
}

export default function DosenDashboard(props: DosenDashboardProps) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;
    const [analytics, setAnalytics] = useState<DosenAnalytics>(props.analytics);
    const [miniCal, setMiniCal] = useState<MiniCal>(props.kalender_mini);
    const [busy, setBusy] = useState(false);
    const [calBusy, setCalBusy] = useState(false);
    const [loadError, setLoadError] = useState(false);

    const qs = (a: DosenAnalytics) =>
        `mode=${a.mode}&tanggal=${a.tanggal}&kelas=${encodeURIComponent(a.kelas)}` +
        `&tim=${encodeURIComponent(a.tim)}&aktivitas=${a.aktivitas}` +
        (a.weekday ? `&weekday=${a.weekday}` : '');

    const apply = async (patch: Partial<DosenAnalytics>) => {
        const next = { ...analytics, ...patch };
        setBusy(true);
        setLoadError(false);
        try {
            const res = await fetch(`${props.urls.data}?${qs(next)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            setAnalytics(await res.json());
        } catch {
            setLoadError(true);
        } finally {
            setBusy(false);
        }
    };

    const goCalMonth = async (bulan: string) => {
        if (bulan === miniCal.bulan || calBusy) return;
        setCalBusy(true);
        try {
            const res = await fetch(`${props.urls.minical}?bulan=${bulan}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            setMiniCal(await res.json());
        } catch {
            // Pertahankan bulan yang sedang tampil bila gagal.
        } finally {
            setCalBusy(false);
        }
    };

    const s = analytics.stats;
    const kpis = [
        {
            key: 'mahasiswa', label: 'Mahasiswa Aktif', value: s.total_mahasiswa,
            sub: 'Semua kelas · periode ini', accent: '#0284c7', icon: Users,
            url: props.urls.mahasiswa,
        },
        {
            key: 'hadir', label: '% Kehadiran Unik', value: `${s.persen_hadir}%`,
            sub: s.persen_meta || 'Persentase sama dengan periode sebelumnya',
            subClass: s.persen_delta > 0 ? 'up' : s.persen_delta < 0 ? 'warn' : undefined,
            accent: '#22c55e', icon: ClipboardCheck, url: props.urls.rekap,
        },
        {
            key: 'tap', label: 'Tap Absensi', value: s.tap_absensi,
            sub: s.tap_meta || 'Tap sidik jari periode ini', accent: '#b45309', icon: Calendar,
            url: props.urls.rekap,
        },
        {
            key: 'pbl', label: 'Tim PBL Bimbingan', value: props.pbl.tim_keys.length,
            sub: props.pbl.has_pbl ? props.pbl.tim_keys.join(', ') : 'Belum ada tim diampu',
            accent: '#7c3aed', icon: GraduationCap, url: props.urls.mahasiswa,
        },
    ];

    return (
        <AdminShell
            menu={props.menu}
            notif={props.notif}
            title="Dashboard Dosen"
            subtitle="Absensi mahasiswa · tim PBL Anda"
        >
            <Head title="Dashboard Dosen" />

            <div className="ad-hero-zone" style={{ ['--ad-hero-img' as string]: `url('${asset('images/campus-polybatam.jpg')}')` }}>
                <div className="ad-hero ad-animate">
                    <h2>Selamat datang, {user?.nama_lengkap ?? 'Dosen'}</h2>
                    <p>Pantau kehadiran mahasiswa, rekap absensi, dan aktivitas tim PBL Anda di satu tempat.</p>
                </div>

                <div className="ad-kpi ad-animate">
                    {kpis.map((k) => {
                        const Icon = k.icon;
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

            {/* Bar filter analitik */}
            <div className="ad-panel ad-animate" style={{ marginBottom: 14 }}>
                <div className="ad-panel__body" style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                    <span className="ad-pill ad-pill--ok" style={{ textTransform: 'none' }}>
                        <Calendar size={13} style={{ marginRight: 5, verticalAlign: -2 }} />
                        {analytics.period_label}
                    </span>
                    <div className="ad-period" role="group" aria-label="Periode">
                        {MODES.map((m) => (
                            <button key={m.key} type="button"
                                className={analytics.mode === m.key ? 'is-active' : ''}
                                disabled={busy}
                                onClick={() => apply({ mode: m.key })}
                            >{m.label}</button>
                        ))}
                    </div>
                    <select value={analytics.kelas} disabled={busy} className="ad-dosen-select"
                        onChange={(e) => apply({ kelas: e.target.value })} aria-label="Filter kelas">
                        <option value="">Semua Kelas</option>
                        {props.kelas_options.map((k) => <option key={k} value={k}>{k}</option>)}
                    </select>
                    <select value={analytics.tim} disabled={busy} className="ad-dosen-select"
                        onChange={(e) => apply({ tim: e.target.value })} aria-label="Filter tim">
                        <option value="">Semua Tim</option>
                        {props.tim_options.map((t) => <option key={t} value={t}>{t}</option>)}
                    </select>
                    <select value={analytics.aktivitas} disabled={busy} className="ad-dosen-select"
                        onChange={(e) => apply({ aktivitas: e.target.value as DosenAnalytics['aktivitas'] })} aria-label="Jenis aktivitas">
                        <option value="semua">Semua Aktivitas</option>
                        <option value="absensi">Absensi</option>
                    </select>
                    <span style={{ marginLeft: 'auto', fontSize: 11.5, color: 'var(--ad-muted)', display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                        <i style={{ width: 7, height: 7, borderRadius: 99, background: '#22c55e', boxShadow: '0 0 0 3px rgba(34,197,94,.18)' }} />
                        Data absensi periode ini
                    </span>
                </div>
            </div>

            {/* Charts + distribusi + kalender */}
            <div className="ad-charts">
                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Grafik Kehadiran</h3>
                            <small>{analytics.line.subtitle ?? 'Persentase mahasiswa yang tap absensi'}</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        {busy ? (
                            <ChartSkeleton />
                        ) : loadError ? (
                            <div className="ad-empty">
                                Gagal memuat data.{' '}
                                <button type="button" className="ad-link" style={{ background: 'none', border: 0, cursor: 'pointer' }} onClick={() => apply({})}>
                                    Coba lagi
                                </button>
                            </div>
                        ) : (
                            <LineChart data={toLineData(analytics)} />
                        )}
                    </div>
                </div>

                <div className="ad-panel ad-animate">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Distribusi Kehadiran</h3>
                            <small>Mahasiswa unik hadir vs belum</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <DonutChart data={toDonutData(analytics)} />
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 8 }}>
                            {analytics.donut.labels.map((label, i) => {
                                const val = analytics.donut.values[i] ?? 0;
                                const total = Math.max(1, analytics.donut.values.reduce((sum, v) => sum + v, 0));
                                const pct = Math.round((val / total) * 1000) / 10;
                                return (
                                    <div key={label} style={{ display: 'grid', gridTemplateColumns: '8px 1fr auto', gap: 10, alignItems: 'center', fontSize: 12.5 }}>
                                        <span style={{ width: 8, height: 8, borderRadius: 999, background: ['#22c55e', '#64748b'][i] ?? '#94a3b8' }} />
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
                            <h3>Kalender Akademik</h3>
                            <small>Agenda resmi kampus untuk semua pengguna</small>
                        </div>
                    </div>
                    <div className="ad-panel__body" style={{ paddingTop: 4 }}>
                        <MiniCalView cal={miniCal} onNav={goCalMonth} busy={calBusy} />
                    </div>
                </div>
            </div>

            {/* Feed + agenda + pengumuman */}
            <div className="ad-feed-grid">
                <div className="ad-panel ad-animate ad-panel--fill">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Live Feed Absensi</h3>
                            <small>Tap mesin terbaru — masuk & pulang</small>
                        </div>
                        <a href={props.urls.rekap} className="ad-link">Lihat Semua →</a>
                    </div>
                    <div className="ad-table-wrap">
                        <table className="ad-table">
                            <thead>
                                <tr><th>Mahasiswa</th><th>Jenis</th><th>Shift</th><th>Waktu</th></tr>
                            </thead>
                            <tbody>
                                {props.absensi_terbaru.length === 0 && (
                                    <tr><td colSpan={4} className="ad-empty">Belum ada absensi tercatat.</td></tr>
                                )}
                                {props.absensi_terbaru.map((t) => (
                                    <tr key={t.id}>
                                        <td>
                                            <div className="ad-feed-user">
                                                <div className="ad-ava">
                                                    {t.foto ? <img src={t.foto} alt="" /> : t.nama.charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    <strong>{t.nama}</strong>
                                                    <div style={{ fontSize: 11, color: 'var(--ad-muted)' }}>{t.nitk} · Tim {t.tim}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {t.jenis === 'masuk' ? (
                                                <span className="ad-pill ad-pill--ok"><LogIn size={12} style={{ marginRight: 4, verticalAlign: -2 }} />Masuk</span>
                                            ) : t.jenis === 'pulang' ? (
                                                <span className="ad-pill ad-pill--pending"><LogOut size={12} style={{ marginRight: 4, verticalAlign: -2 }} />Pulang</span>
                                            ) : (
                                                <span className="ad-pill ad-pill--muted">{(t.jenis || 'Tap').toUpperCase()}</span>
                                            )}
                                            {t.menit_terlambat > 0 && (
                                                <span className="ad-pill ad-pill--pending" style={{ marginLeft: 4 }} title="Menit terlambat">+{t.menit_terlambat}m</span>
                                            )}
                                            {t.pulang_lembur && (
                                                <span className="ad-pill ad-pill--pending" style={{ marginLeft: 4 }} title="Pulang lembur">Lembur</span>
                                            )}
                                        </td>
                                        <td><span className="ad-pill ad-pill--muted" style={{ textTransform: 'capitalize' }}>{t.sesi}</span></td>
                                        <td style={{ whiteSpace: 'nowrap', color: 'var(--ad-muted)', fontVariantNumeric: 'tabular-nums' }}>{t.waktu}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <div className="ad-panel ad-animate">
                        <div className="ad-panel__head">
                            <div>
                                <h3>Agenda Terdekat</h3>
                                <small>Kalender akademik yang akan datang</small>
                            </div>
                        </div>
                        <div className="ad-panel__body" style={{ paddingTop: 6 }}>
                            {props.agenda_terdekat.length === 0 && (
                                <div className="ad-empty">Belum ada agenda terdekat.</div>
                            )}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                {props.agenda_terdekat.map((a) => (
                                    <div key={a.id} className="ad-mcal__row" style={{ alignItems: 'flex-start' }}>
                                        <i className={`ad-mcal__dot ad-mcal__dot--${JENIS_TONE[a.jenis] ?? 'info'}`} style={{ marginTop: 4 }} />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                                <strong style={{ fontSize: 12 }}>{a.keterangan}</strong>
                                                {a.hari_ini && <span className="ad-pill ad-pill--ok">Hari ini</span>}
                                            </div>
                                            <small style={{ color: 'var(--ad-muted)' }}>{a.jenis_label} · {a.rentang}</small>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="ad-panel ad-animate">
                        <div className="ad-panel__head">
                            <div>
                                <h3>Pengumuman Terbaru</h3>
                                <small>Informasi resmi untuk dosen</small>
                            </div>
                            <a href={props.urls.announcements} className="ad-link">Lihat Semua →</a>
                        </div>
                        <div className="ad-panel__body" style={{ paddingTop: 6 }}>
                            {props.pengumuman.length === 0 && (
                                <div className="ad-empty">Belum ada pengumuman.</div>
                            )}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                {props.pengumuman.map((p) => (
                                    <div key={p.id} style={{ borderLeft: '3px solid var(--ad-primary, #2563eb)', paddingLeft: 10 }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                            <Megaphone size={13} style={{ color: 'var(--ad-muted)', flexShrink: 0 }} />
                                            <strong style={{ fontSize: 12.5 }}>{p.judul}</strong>
                                        </div>
                                        <p style={{ margin: '3px 0 0', fontSize: 11.5, color: 'var(--ad-muted)', lineHeight: 1.45 }}>{p.isi}</p>
                                        <small style={{ fontSize: 10.5, color: 'var(--ad-muted)' }}>{p.author} · {p.waktu}</small>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
