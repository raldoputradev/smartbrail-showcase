import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity, AlertTriangle, Camera, ChevronDown, Cpu, DoorOpen, Eye, Fingerprint,
    HardHat, LayoutGrid, Video, VideoOff, Wifi, WifiOff,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart } from '@admin/components/Charts';
import { subscribePrivate } from '@admin/lib/live';
import { cn } from '@admin/lib/utils';
import type { MonRoomPanel, MonTypeCard, MonitoringOperasionalProps, MonitoringPayload, SharedProps } from '@admin/types';

const REFRESH_MS = 15_000;

function typeIcon(c: MonTypeCard) {
    if (c.is_vision) return <HardHat size={18} />;
    if (c.key === 'absensi') return <Fingerprint size={18} />;
    if (c.key === 'ruangan' || /ruang|meeting|mekanikal/.test(c.key)) return <DoorOpen size={18} />;
    return <Cpu size={18} />;
}

/* ── Telemetri kondisi ruangan ────────────────────────── */
const ROOM_ALERT_LABELS: Record<string, string> = {
    apd_tidak_dipakai: 'APD tidak dipakai',
    apd_kurang: 'Kepatuhan APD di bawah ambang',
    suhu_tinggi: 'Suhu ruangan tinggi',
    daya_anomali: 'Konsumsi daya anomali',
    printer_error: 'Printer 3D error',
    printer_selesai: 'Cetakan 3D selesai',
};

const ROOM_STATUS: Record<MonRoomPanel['status'], { label: string; pill: string; tone: string }> = {
    ok: { label: 'Normal', pill: 'ad-pill--ok', tone: '#34d399' },
    warn: { label: 'Peringatan', pill: 'ad-pill--pending', tone: '#fbbf24' },
    stale: { label: 'Data kedaluwarsa', pill: 'ad-pill--danger', tone: '#f87171' },
    nosensor: { label: 'Belum ada sensor', pill: 'ad-pill--off', tone: '#7c93b8' },
    inactive: { label: 'Nonaktif', pill: 'ad-pill--off', tone: '#64748b' },
};

/** Line-art ruangan saat tak ada foto — warna garis mengikuti status panel. */
function RoomArt({ seed, tone }: { seed: number; tone: string }) {
    const w = 1 + (seed % 3);            // lebar gedung bervariasi deterministik
    const wins = 2 + (seed % 4);         // kolom jendela
    const rows: number[] = [];
    for (let i = 0; i < wins * 4; i++) rows.push(i);
    return (
        <svg viewBox="0 0 320 130" preserveAspectRatio="xMidYMax slice" className="ad-mon-room__art" aria-hidden="true">
            <path d="M-10 46 Q 60 22 130 40 T 340 34" stroke="rgba(34,211,238,.4)" strokeWidth="1.6" fill="none" />
            <path d="M-10 60 Q 70 36 150 52 T 340 46" stroke="rgba(34,211,238,.22)" strokeWidth="1.2" fill="none" />
            <g stroke={tone} strokeWidth="1.4" fill="none" opacity="0.8">
                <path d={`M${120 - w * 14} 130 V${58 - w * 6} h${w * 28} v-10 l${8 + w} ${-(6 + w)} l${8 + w} ${6 + w} v10 h${w * 28} V130`} />
                {rows.map((i) => {
                    const col = i % wins; const row = Math.floor(i / wins);
                    const x = 128 - w * 8 + col * ((w * 44) / wins);
                    const y = 68 + row * 14;
                    return y < 122 ? <path key={i} d={`M${x} ${y}h${6 + w}`} /> : null;
                })}
                <path d={`M${152 - w * 3} 130 v-16 q${w * 3} -8 ${w * 6} 0 v16`} />
            </g>
        </svg>
    );
}

type RoomMetric = { label: string; value: string; warn?: boolean };

// Urutan kanonik — sama dengan Ruangan::MONITOR_KEYS + 'pintu' yang implisit.
const ROOM_METRIC_ORDER = ['pintu', 'apd', 'listrik', 'lingkungan', 'printer', 'solder'] as const;

function roomMetricRows(m: MonRoomPanel['metrics'], monitor: string[]): RoomMetric[] {
    // Hanya metrik yang dipilih untuk ruangan ini yang dirender — 'pintu' selalu
    // ada; nilai "—" menandai metrik dipantau tapi sensor belum mengirim data.
    const byKey: Record<string, RoomMetric> = { pintu: { label: 'Akses Pintu', value: '—' } };

    if (m.pintu_terbuka != null) {
        byKey.pintu = { label: 'Akses Pintu', value: m.pintu_terbuka ? 'Terbuka' : 'Tertutup', warn: !!m.pintu_terbuka };
    }
    byKey.apd = { label: 'Kepatuhan APD', value: '—' };
    if (m.apd_ok != null || m.apd_total != null) {
        const ok = Number(m.apd_ok ?? 0); const tot = Number(m.apd_total ?? 0);
        byKey.apd = { label: 'Kepatuhan APD', value: tot > 0 ? `${ok}/${tot} orang` : `${ok} orang`, warn: tot > 0 && ok < tot };
    }
    byKey.listrik = { label: 'Daya Listrik', value: '—' };
    if (m.listrik_watt != null || m.listrik_kwh != null || m.listrik_aktif != null) {
        byKey.listrik = {
            label: 'Daya Listrik',
            value: [
                m.listrik_aktif != null ? (m.listrik_aktif ? 'Aktif' : 'Padam') : null,
                m.listrik_watt != null ? `${m.listrik_watt} W` : null,
                m.listrik_kwh != null ? `${m.listrik_kwh} kWh` : null,
                m.listrik_sisa_menit != null ? `sisa ${m.listrik_sisa_menit} mnt` : null,
            ].filter(Boolean).join(' · '),
        };
    }
    byKey.lingkungan = { label: 'Lingkungan', value: '—' };
    if (m.suhu_c != null || m.kelembapan_pct != null) {
        byKey.lingkungan = {
            label: 'Lingkungan',
            value: [m.suhu_c != null ? `${m.suhu_c}°C` : null, m.kelembapan_pct != null ? `RH ${m.kelembapan_pct}%` : null]
                .filter(Boolean).join(' · '),
        };
    }
    byKey.printer = { label: '3D Printer', value: '—' };
    if (m.printer_state != null) {
        const bits = [String(m.printer_state)];
        if (m.printer_progress_pct != null) bits.push(`${m.printer_progress_pct}%`);
        if (m.printer_nozzle_c != null) bits.push(`nozzle ${m.printer_nozzle_c}°C`);
        if (m.printer_bed_c != null) bits.push(`bed ${m.printer_bed_c}°C`);
        if (m.printer_eta_s != null) bits.push(`sisa ~${Math.max(1, Math.round(Number(m.printer_eta_s) / 60))} mnt`);
        byKey.printer = { label: '3D Printer', value: bits.join(' · '), warn: m.printer_state === 'error' };
    }
    byKey.solder = { label: 'Solder', value: '—' };
    if (m.solder_active != null || m.solder_temp_c != null) {
        byKey.solder = {
            label: 'Solder',
            value: [m.solder_active ? 'Aktif' : 'Standby', m.solder_temp_c != null ? `${m.solder_temp_c}°C` : null]
                .filter(Boolean).join(' · '),
        };
    }
    const wanted = new Set(monitor && monitor.length ? monitor : ['pintu']);
    return ROOM_METRIC_ORDER.filter((k) => wanted.has(k)).map((k) => byKey[k]);
}

export default function MonitoringOperasional(props: MonitoringOperasionalProps) {
    const [data, setData] = useState<MonitoringPayload>({
        tab: props.tab, tabs: props.tabs, type_cards: props.type_cards, has_vision: props.has_vision,
        metrics: props.metrics, units: props.units, alerts: props.alerts,
        live_tiles: props.live_tiles, activities: props.activities,
        hourly: props.hourly, health: props.health, room_panels: props.room_panels,
    });
    const [live, setLive] = useState(true);
    const [lastRefresh, setLastRefresh] = useState<Date | null>(null);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);
    const { reverb } = usePage<SharedProps>().props;

    useEffect(() => {
        setData({
            tab: props.tab, tabs: props.tabs, type_cards: props.type_cards, has_vision: props.has_vision,
            metrics: props.metrics, units: props.units, alerts: props.alerts,
            live_tiles: props.live_tiles, activities: props.activities,
            hourly: props.hourly, health: props.health, room_panels: props.room_panels,
        });
    }, [props.tab, props.metrics, props.units, props.activities, props.room_panels]);

    const tick = useCallback(async () => {
        try {
            const res = await fetch(`${props.urls.feed}?tab=${encodeURIComponent(data.tab)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            setData({
                tab: json.tab, tabs: json.tabs, type_cards: json.type_cards, has_vision: json.has_vision,
                metrics: json.metrics, units: json.units, alerts: json.alerts,
                live_tiles: json.live_tiles, activities: json.activities,
                hourly: json.hourly, health: json.health, room_panels: json.room_panels ?? [],
            });
            setLive(true);
            setLastRefresh(new Date());
        } catch {
            setLive(false);
        }
    }, [props.urls.feed, data.tab]);

    useEffect(() => {
        timer.current = setInterval(tick, REFRESH_MS);
        return () => { if (timer.current) clearInterval(timer.current); };
    }, [props.urls.feed, data.tab]);

    // Realtime: snapshot telemetri baru dari edge → refresh panel seketika,
    // tak menunggu interval polling.
    useEffect(() => {
        if (!reverb) return;
        return subscribePrivate(reverb, 'absensi.staff.laboran', {
            'ruangan.telemetri': () => { void tick(); },
        });
    }, [reverb?.key, tick]);

    const goTab = (key: string) =>
        router.get(props.urls.index, key === 'overview' ? {} : { tab: key }, { preserveState: true, preserveScroll: true });

    const m = data.metrics;
    const maxHourly = Math.max(1, ...data.hourly.absensi);
    const isVisionTab = data.tab !== 'overview' && data.type_cards.find((c) => c.key === data.tab)?.is_vision;
    const isRoomTab = data.tab === 'kondisi-ruangan';
    const [openRooms, setOpenRooms] = useState<Set<number>>(new Set());

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

            {isRoomTab && (
                <div className="ad-mon-rooms">
                    {data.room_panels.length === 0 && (
                        <div className="ad-panel"><div className="ad-empty">Belum ada ruangan terdaftar.</div></div>
                    )}
                    {data.room_panels.map((r) => {
                        const rows = roomMetricRows(r.metrics, r.monitor);
                        const st = ROOM_STATUS[r.status] ?? ROOM_STATUS.nosensor;
                        const open = openRooms.has(r.id);
                        const toggleRoom = () => setOpenRooms((s) => {
                            const n = new Set(s);
                            if (n.has(r.id)) n.delete(r.id); else n.add(r.id);
                            return n;
                        });
                        return (
                            <section key={r.id}
                                className={cn('ad-panel ad-mon-room', 'is-clickable', r.status === 'warn' && 'is-warn', open && 'is-open')}
                                role="button" tabIndex={0} aria-expanded={open}
                                aria-label={`${r.nama} — ${open ? 'tutup' : 'buka'} data kondisi`}
                                onClick={toggleRoom}
                                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleRoom(); } }}>
                                <div className="ad-mon-room__media">
                                    {r.foto_url
                                        ? <img src={r.foto_url} alt={r.nama} loading="lazy" />
                                        : <RoomArt seed={r.id} tone={st.tone} />}
                                    <span className={cn('ad-pill ad-mon-room__status', st.pill)}>{st.label}</span>
                                    <div className="ad-mon-room__media-title">
                                        <h2>{r.nama}</h2>
                                        <p>{[r.kode, r.lokasi].filter(Boolean).join(' · ') || '—'}</p>
                                    </div>
                                </div>
                                {!open ? (
                                    <div className="ad-mon-room__peek">
                                        <span><Activity size={12} /> {r.update_label}</span>
                                        <span className="ad-mon-room__peekcta">Lihat data <ChevronDown size={13} /></span>
                                    </div>
                                ) : (
                                    <>
                                        <div className="ad-mon-room__metrics">
                                            {rows.map((row) => (
                                                <div key={row.label} className={cn('ad-mon-room__metric', row.warn && 'is-warn', row.value === '—' && 'is-empty')}>
                                                    <span>{row.label}</span>
                                                    <strong>{row.value}</strong>
                                                </div>
                                            ))}
                                        </div>
                                        {r.alerts.length > 0 && (
                                            <div className="ad-mon-room__alerts">
                                                {r.alerts.map((a, i) => (
                                                    <span key={i} className="ad-mon-room__alert">
                                                        <AlertTriangle size={12} /> {ROOM_ALERT_LABELS[a] ?? a}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        <div className="ad-mon-room__foot" title={r.update_exact ?? undefined}>
                                            <Activity size={12} /> {r.update_label}
                                            {r.id_mesin ? ` · ${r.id_mesin}` : ''}
                                            <a href={r.aktivitas_url} className="ad-mon-room__peekcta" style={{ marginLeft: 'auto' }}
                                                onClick={(e) => e.stopPropagation()}>Riwayat ›</a>
                                            <span className="ad-mon-room__peekcta">Tutup <ChevronDown size={13} style={{ transform: 'rotate(180deg)' }} /></span>
                                        </div>
                                    </>
                                )}
                            </section>
                        );
                    })}
                </div>
            )}

            {!isRoomTab && (<>
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
            </>)}
        </AdminShell>
    );
}
