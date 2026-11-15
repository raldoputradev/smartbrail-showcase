import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity, AlertTriangle, Camera, Cpu, DoorOpen, Eye, Fingerprint,
    HardHat, LayoutGrid, Video, VideoOff, Wifi, WifiOff,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart } from '@admin/components/Charts';
import { cn } from '@admin/lib/utils';
import type { MonTypeCard, MonitoringOperasionalProps, MonitoringPayload } from '@admin/types';

const REFRESH_MS = 15_000;

function typeIcon(c: MonTypeCard) {
    if (c.is_vision) return <HardHat size={18} />;
    if (c.key === 'absensi') return <Fingerprint size={18} />;
    if (c.key === 'ruangan' || /ruang|meeting|mekanikal/.test(c.key)) return <DoorOpen size={18} />;
    return <Cpu size={18} />;
}

export default function MonitoringOperasional(props: MonitoringOperasionalProps) {
    const [data, setData] = useState<MonitoringPayload>({
        tab: props.tab, tabs: props.tabs, type_cards: props.type_cards, has_vision: props.has_vision,
        metrics: props.metrics, units: props.units, alerts: props.alerts,
        live_tiles: props.live_tiles, activities: props.activities,
        hourly: props.hourly, health: props.health,
    });
    const [live, setLive] = useState(true);
    const [lastRefresh, setLastRefresh] = useState<Date | null>(null);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        setData({
            tab: props.tab, tabs: props.tabs, type_cards: props.type_cards, has_vision: props.has_vision,
            metrics: props.metrics, units: props.units, alerts: props.alerts,
            live_tiles: props.live_tiles, activities: props.activities,
            hourly: props.hourly, health: props.health,
        });
    }, [props.tab, props.metrics, props.units, props.activities]);

    useEffect(() => {
        let cancelled = false;
        const tick = async () => {
            try {
                const res = await fetch(`${props.urls.feed}?tab=${encodeURIComponent(data.tab)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                if (cancelled) return;
                setData({
                    tab: json.tab, tabs: json.tabs, type_cards: json.type_cards, has_vision: json.has_vision,
                    metrics: json.metrics, units: json.units, alerts: json.alerts,
                    live_tiles: json.live_tiles, activities: json.activities,
                    hourly: json.hourly, health: json.health,
                });
                setLive(true);
                setLastRefresh(new Date());
            } catch {
                if (!cancelled) setLive(false);
            }
        };
        timer.current = setInterval(tick, REFRESH_MS);
        return () => { cancelled = true; if (timer.current) clearInterval(timer.current); };
    }, [props.urls.feed, data.tab]);

    const goTab = (key: string) =>
        router.get(props.urls.index, key === 'overview' ? {} : { tab: key }, { preserveState: true, preserveScroll: true });

    const m = data.metrics;
    const maxHourly = Math.max(1, ...data.hourly.absensi);
    const isVisionTab = data.tab !== 'overview' && data.type_cards.find((c) => c.key === data.tab)?.is_vision;

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Monitoring Operasional"
            subtitle="Pantau mesin, ruangan, dan status perangkat secara real-time">
            <Head title="Monitoring Operasional" />

            {/* Tab jenis */}
            <div className="ad-atur-tabs">
                {data.tabs.map((t) => (
                    <button key={t.key} type="button"
                        className={cn('ad-atur-tab', data.tab === t.key && 'is-active')}
                        onClick={() => goTab(t.key)}>
                        {t.key === 'overview' ? <LayoutGrid size={14} /> : t.key === 'absensi' ? <Fingerprint size={14} /> : t.key === 'vision' || /cam|kamera|vision|cctv/i.test(t.key) ? <HardHat size={14} /> : /ruang|meeting|mekanikal/.test(t.key) ? <DoorOpen size={14} /> : <Cpu size={14} />}
                        {t.label} <span className="ad-usr-tab__n">{t.count}</span>
                    </button>
                ))}
            </div>

            {/* KPI per jenis */}
            <div className="ad-mon-kpis">
                {data.type_cards.map((c) => (
                    <div key={c.key} className={cn('ad-mon-kpi', c.is_vision && c.total === 0 && 'is-empty')}>
                        <div className="ad-mon-kpi__ic">{typeIcon(c)}</div>
                        <div className="ad-mon-kpi__body">
                            <span className="ad-mon-kpi__label">{c.label}</span>
                            <strong>{c.total}</strong>
                            {c.total === 0 ? (
                                <small className="ad-mon-kpi__sub">Belum ada perangkat</small>
                            ) : (
                                <small className="ad-mon-kpi__sub">
                                    <i className="ad-dot ad-dot--ok" /> {c.online} Online
                                    <i className="ad-dot ad-dot--warn" /> {c.offline} Offline
                                    {c.inactive > 0 && <><i className="ad-dot ad-dot--off" /> {c.inactive} Nonaktif</>}
                                </small>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {/* Grafik + status + peringatan */}
            <div className="ad-mon-grid3">
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2>Aktivitas Tap Absensi</h2>
                            <p>Jumlah akses per jam (hari ini)</p>
                        </div>
                    </div>
                    <div className="ad-mon-bars">
                        {data.hourly.labels.map((l, i) => (
                            <div key={l} className="ad-mon-bar">
                                <span className="ad-mon-bar__v">{data.hourly.absensi[i] || ''}</span>
                                <i style={{ height: `${Math.round((data.hourly.absensi[i] / maxHourly) * 100)}%` }} />
                                <small>{i % 2 === 0 ? l + ':00' : ''}</small>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2>Status Perangkat & Ruangan</h2>
                            <p>Total {m.machine_total} unit</p>
                        </div>
                    </div>
                    <div className="ad-mon-health">
                        <DonutChart data={{ ...data.health, total: m.machine_total, center_label: 'Unit' }} />
                        <ul className="ad-mon-health__legend">
                            {data.health.labels.map((l, i) => (
                                <li key={l}><i style={{ background: data.health.colors[i] }} /> {l} <b>{data.health.values[i]}</b></li>
                            ))}
                        </ul>
                    </div>
                </section>

                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2>Peringatan & Kejadian</h2>
                            <p>{data.alerts.length > 0 ? `${data.alerts.length} perlu perhatian` : 'Tidak ada peringatan'}</p>
                        </div>
                    </div>
                    <div className="ad-mon-alerts">
                        {data.alerts.length === 0 && (
                            <div className="ad-empty">Semua perangkat aktif berjalan normal.</div>
                        )}
                        {data.alerts.map((a, i) => (
                            <div key={i} className="ad-mon-alert">
                                <span className="ad-mon-alert__ic"><AlertTriangle size={15} /></span>
                                <div>
                                    <strong>{a.title}</strong>
                                    <small>{a.desc}</small>
                                </div>
                                <time>{a.time}</time>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            {/* Monitoring langsung */}
            <section className="ad-panel">
                <div className="ad-panel__head">
                    <div>
                        <h2 className="ad-mon-livetitle"><Video size={16} /> Monitoring Langsung (Kamera)
                            <span className={cn('ad-mon-live', live ? 'is-on' : 'is-off')}>{live ? 'LIVE' : 'TERPUTUS'}</span>
                        </h2>
                        <p>{lastRefresh ? `Sinkron ${lastRefresh.toLocaleTimeString('id-ID')}` : `Heartbeat ≤ ${props.threshold_minutes} menit dianggap online`}</p>
                    </div>
                </div>
                {data.live_tiles.length === 0 ? (
                    <div className="ad-mon-nostream">
                        <VideoOff size={30} />
                        <strong>Belum ada siaran langsung</strong>
                        <p>Belum ada perangkat kamera/vision terdaftar. Layar monitoring baru akan muncul otomatis di sini saat perangkat vision ditambahkan dan terhubung.</p>
                    </div>
                ) : (
                    <div className="ad-mon-tiles">
                        {data.live_tiles.map((t, i) => (
                            <div key={i} className="ad-mon-tile">
                                <div className="ad-mon-tile__screen">
                                    {t.status === 'live' ? <Camera size={26} /> : <VideoOff size={26} />}
                                    <span>{t.status === 'live' ? 'Siaran belum dikonfigurasi' : 'Tidak ada siaran'}</span>
                                </div>
                                <div className="ad-mon-tile__meta">
                                    <div><strong>{t.nama}</strong>{t.ruangan && <small>{t.ruangan}</small>}</div>
                                    <span className={cn('ad-pill', t.status === 'live' ? 'ad-pill--ok' : t.status === 'offline' ? 'ad-pill--pending' : 'ad-pill--off')}>
                                        {t.status === 'live' ? 'Online' : t.status === 'offline' ? 'Offline' : 'Nonaktif'}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {/* Tabel status + ringkasan APD */}
            <div className="ad-mon-bottom">
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2>Status Perangkat & Ruangan</h2>
                            <p>Daftar perangkat, ruangan, dan status real-time</p>
                        </div>
                    </div>
                    <div className="ad-table-wrap">
                        <table className="ad-table">
                            <thead>
                                <tr>
                                    <th>Nama</th><th>Jenis</th><th>Lokasi</th><th>Status</th><th>Terakhir Update</th><th className="is-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.units.length === 0 && (
                                    <tr><td colSpan={6} className="ad-empty">
                                        {isVisionTab ? 'Belum ada perangkat vision terdaftar.' : 'Belum ada unit untuk jenis ini.'}
                                    </td></tr>
                                )}
                                {data.units.map((u) => (
                                    <tr key={u.key}>
                                        <td>
                                            <strong>{u.nama}</strong>
                                            {u.sub && <small className="ad-mon-sub">{u.sub}</small>}
                                        </td>
                                        <td><span className={cn('ad-pill', u.kind === 'ruangan' ? 'ad-pill--info' : 'ad-pill--role-mahasiswa')}>{u.jenis}</span></td>
                                        <td>{u.lokasi}</td>
                                        <td>
                                            <span className={cn('ad-pill',
                                                (u.status === 'online' || u.status === 'tersedia') && 'ad-pill--ok',
                                                u.status === 'offline' && 'ad-pill--danger',
                                                u.status === 'inactive' && 'ad-pill--off')}>
                                                {u.status_label}
                                            </span>
                                        </td>
                                        <td>{u.update_label}</td>
                                        <td className="is-right">
                                            <a href={u.url} className="ad-mesin-ic" title="Lihat detail"><Eye size={14} /></a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2>Ringkasan Deteksi APD (Vision)</h2>
                            <p>Kepatuhan alat pelindung diri</p>
                        </div>
                    </div>
                    <div className="ad-mon-apd">
                        <HardHat size={34} />
                        <strong>{data.has_vision ? 'Perangkat vision terdaftar' : 'Fitur vision belum aktif'}</strong>
                        <p>{data.has_vision
                            ? 'Perangkat vision sudah terdaftar, tetapi integrasi deteksi APD belum diaktifkan. Ringkasan kepatuhan akan tampil di sini setelah tersedia.'
                            : 'Belum ada perangkat vision/kamera di sistem. Ringkasan deteksi APD (helm, rompi, kacamata, masker, sarung tangan) akan tampil di sini saat perangkat dipasang.'}</p>
                    </div>
                </section>
            </div>

            {/* Aktivitas terbaru */}
            <section className="ad-panel">
                <div className="ad-panel__head">
                    <div>
                        <h2>Aktivitas Terbaru</h2>
                        <p>Tap akses masuk dari seluruh perangkat</p>
                    </div>
                    <Link href={props.urls.log} className="ad-mesin-btn ad-mesin-btn--ghost"><Activity size={14} /> Lihat Log Tap</Link>
                </div>
                <div className="ad-mon-feed">
                    {data.activities.length === 0 && <div className="ad-empty">Belum ada aktivitas.</div>}
                    {data.activities.map((a, i) => (
                        <div key={i} className="ad-mon-feed__row">
                            <div className="ad-feed-user">
                                <strong>{a.student}</strong>
                                <small>{a.identifier} · {a.machine}</small>
                            </div>
                            <div className="ad-mon-feed__right">
                                <span className="ad-pill ad-pill--ok">{a.status}</span>
                                <small title={a.time_exact}>{a.time_label}</small>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </AdminShell>
    );
}
