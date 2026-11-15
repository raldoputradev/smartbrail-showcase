import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, CalendarDays, Check, ChevronRight, Clock, FileText, Info,
    MonitorCheck, Pencil, Plus, Settings, ShieldCheck, Sun, Trash2, X, Zap,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { AturAbsensiProps, AturDayRule, SharedProps } from '@admin/types';

const DOW_ORDER = ['1', '2', '3', '4', '5', '6', '0'];
const JENIS_LABEL: Record<string, string> = {
    libur: 'Hari Libur', perubahan_jam: 'Perubahan Jam', ujian: 'Periode Ujian', informasi: 'Informasi',
};
const DAMPAK_LABEL: Record<string, string> = {
    batal_wajib: 'Libur (tidak wajib)', tetap_wajib: 'Tetap wajib', ubah_jam: 'Ubah jam',
};
const MODE_OPTS = [
    { value: 'harian', label: 'Setiap Hari Kerja', desc: 'Senin–Jumat mengikuti jam sesi standar' },
    { value: 'kamis', label: 'Hanya Kamis', desc: '1 hari wajib per minggu (Kamis)' },
    { value: 'jadwal_mingguan', label: 'Jadwal Mingguan Per Hari', desc: 'Jam masuk & pulang berbeda per hari' },
];

/** Ilustrasi kalender & jam — inline SVG seirama hero mesin. */
function CalendarArt() {
    return (
        <svg className="ad-mesin-hero__art" viewBox="0 0 360 190" fill="none" aria-hidden="true">
            <path d="M0 158 Q 100 125 200 145 T 360 132" stroke="rgba(125,211,252,.35)" strokeWidth="1.5"/>
            <path d="M0 172 Q 110 145 210 158 T 360 148" stroke="rgba(125,211,252,.2)" strokeWidth="1.3"/>
            {/* Kartu kalender utama */}
            <rect x="108" y="26" width="144" height="132" rx="14" fill="rgba(13,27,54,.55)" stroke="rgba(147,197,253,.6)" strokeWidth="1.6"/>
            <rect x="108" y="26" width="144" height="34" rx="14" fill="rgba(29,111,242,.5)"/>
            <rect x="108" y="46" width="144" height="14" fill="rgba(13,27,54,.55)"/>
            <circle cx="138" cy="43" r="4" fill="rgba(191,219,254,.9)"/>
            <circle cx="222" cy="43" r="4" fill="rgba(191,219,254,.9)"/>
            {/* Grid tanggal */}
            {[0,1,2].map((r) => [0,1,2,3,4].map((c) => (
                <rect key={`${r}-${c}`} x={124 + c * 23} y={74 + r * 24} width="16" height="15" rx="4"
                    fill={r === 1 && c === 2 ? '#34d399' : 'rgba(125,211,252,.18)'}
                    stroke={r === 1 && c === 2 ? 'none' : 'rgba(125,211,252,.35)'} strokeWidth="1"/>
            )))}
            {/* Jam kecil kanan bawah */}
            <circle cx="262" cy="138" r="26" fill="rgba(13,27,54,.7)" stroke="rgba(147,197,253,.6)" strokeWidth="1.6"/>
            <path d="M262 126 v12 l9 6" stroke="#38bdf8" strokeWidth="2.4" strokeLinecap="round"/>
            {/* Checklist kiri */}
            <rect x="46" y="66" width="46" height="88" rx="10" fill="rgba(13,27,54,.45)" stroke="rgba(147,197,253,.4)" strokeWidth="1.4"/>
            {[0,1,2].map((i) => (
                <g key={i}>
                    <rect x="55" y={80 + i * 24} width="9" height="9" rx="2.5" fill="none" stroke="#34d399" strokeWidth="1.5"/>
                    <rect x="69" y={82 + i * 24} width="15" height="5" rx="2.5" fill="rgba(125,211,252,.3)"/>
                </g>
            ))}
            {/* Lonceng kanan atas */}
            <g transform="translate(286 40)" stroke="#fbbf24" strokeWidth="2" strokeLinecap="round" fill="none">
                <path d="M0 18 a11 11 0 0 1 22 0 v6 l3 5 h-28 l3-5 z" fill="rgba(251,191,36,.18)"/>
                <path d="M8 33 a3 3 0 0 0 6 0"/>
            </g>
        </svg>
    );
}

export default function AturAbsensi(props: AturAbsensiProps) {
    const { flash, errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState(props.tab);
    const [busy, setBusy] = useState(false);
    const [confirm, setConfirm] = useState<null | { title: string; body: string; label: string; danger?: boolean; action: () => void }>(null);

    const post = (url: string, data: Record<string, unknown>, method: 'post' | 'delete' = 'post') => {
        setBusy(true);
        router[method](url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => setConfirm(null),
        });
    };

    const switchTab = (t: string) => {
        setTab(t as typeof tab);
        router.get(props.urls.index, { tab: t }, { preserveState: true, preserveScroll: true, replace: true, only: [] });
    };

    const TABS = [
        { key: 'ringkasan', label: 'Ringkasan', icon: FileText },
        { key: 'jadwal', label: 'Jadwal & Jam Kerja', icon: Clock },
        { key: 'kalender', label: 'Kalender Akademik', icon: CalendarDays },
        { key: 'operasional', label: 'Kesiapan Operasional', icon: ShieldCheck },
    ] as const;

    const issueCount = props.ops.issues.length;

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Pengaturan Absensi"
            subtitle="Kelola kebijakan, jadwal, kalender akademik, dan kesiapan operasional absensi.">
            <Head title="Pengaturan Absensi" />

            <div className="ad-mesin-hero ad-animate">
                <div className="ad-mesin-hero__text">
                    <h2>Pengaturan Absensi</h2>
                    <p>
                        Atur jadwal, jam kerja, dan kebijakan absensi laboratorium sesuai
                        kebutuhan operasional dan akademik.
                    </p>
                </div>
                <CalendarArt />
            </div>

            {flash.success && <div className="ad-mesin-flash ad-mesin-flash--ok"><Check /> {flash.success}</div>}
            {flash.error && <div className="ad-mesin-flash ad-mesin-flash--err"><X /> {flash.error}</div>}
            {(flash.info || flash.status) && <div className="ad-mesin-flash" style={{ background: 'rgba(2,132,199,.1)', color: 'var(--ad-soft)', border: '1px solid rgba(2,132,199,.25)' }}><Info /> {flash.info ?? flash.status}</div>}
            {errors && Object.keys(errors).length > 0 && (
                <div className="ad-mesin-flash ad-mesin-flash--err"><AlertTriangle /> {Object.values(errors)[0]}</div>
            )}

            {/* Tab navigasi */}
            <div className="ad-atur-tabs ad-animate" role="tablist">
                {TABS.map((t) => {
                    const Icon = t.icon;
                    return (
                        <button key={t.key} type="button" role="tab" aria-selected={tab === t.key}
                            className={cn('ad-atur-tab', tab === t.key && 'is-active')}
                            onClick={() => switchTab(t.key)}>
                            <Icon /> {t.label}
                            {t.key === 'operasional' && issueCount > 0 && <span className="ad-atur-tab__badge">{issueCount}</span>}
                        </button>
                    );
                })}
            </div>

            {tab === 'ringkasan' && <RingkasanTab props={props} goto={switchTab} />}
            {tab === 'jadwal' && <JadwalTab props={props} busy={busy} post={post} />}
            {tab === 'kalender' && <KalenderTab props={props} busy={busy} post={post} setConfirm={setConfirm} />}
            {tab === 'operasional' && <OpsTab props={props} busy={busy} post={post} setConfirm={setConfirm} />}

            {confirm && (
                <div className="ad-mesin-scrim" style={{ zIndex: 95 }} onClick={() => setConfirm(null)}>
                    <div className="ad-mesin-confirm" role="alertdialog" aria-label={confirm.title} onClick={(e) => e.stopPropagation()}>
                        <h4>{confirm.title}</h4>
                        <p>{confirm.body}</p>
                        <div className="ad-mesin-confirm__actions">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setConfirm(null)}>Batal</button>
                            <button type="button" disabled={busy}
                                className={cn('ad-mesin-btn', confirm.danger ? 'ad-mesin-btn--danger' : 'ad-mesin-btn--primary')}
                                onClick={confirm.action}>{busy ? 'Memproses…' : confirm.label}</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}

/* ── Tab: Ringkasan ── */
function RingkasanTab({ props, goto }: { props: AturAbsensiProps; goto: (t: string) => void }) {
    const pagi = props.sesi.find((s) => s.shift === 'pagi');
    const malam = props.sesi.find((s) => s.shift === 'malam');
    const issueCount = props.ops.issues.length;
    const [sesiTab, setSesiTab] = useState<'pagi' | 'malam'>(pagi?.aktif ? 'pagi' : (malam ? 'malam' : 'pagi'));
    const sesiShown = props.sesi.find((s) => s.shift === sesiTab) ?? pagi ?? malam;

    return (
        <div className="ad-atur-grid ad-animate">
            <div className="ad-atur-main">
                {/* 4 kartu ringkasan */}
                <div className="ad-panel">
                    <div className="ad-panel__head"><div><h3>Ringkasan Pengaturan</h3><small>Status utama konfigurasi absensi laboratorium.</small></div></div>
                    <div className="ad-atur-sum">
                        {[
                            { icon: CalendarDays, label: 'Jadwal Aktif', value: `${props.ringkasan.hari_aktif} hari`, sub: props.ringkasan.hari_label },
                            { icon: Clock, label: 'Sesi Per Hari', value: `${props.ringkasan.slot_per_hari} sesi`, sub: `± ${props.ringkasan.slot_menit} menit` },
                            {
                                icon: Sun, label: 'Kalender Akademik',
                                value: props.ringkasan.kalender_aktif ? 'Aktif' : props.ringkasan.kalender_berikut ? 'Terjadwal' : 'Kosong',
                                sub: props.ringkasan.kalender_aktif?.keterangan ?? props.ringkasan.kalender_berikut?.keterangan ?? 'Belum ada agenda',
                                ok: !!props.ringkasan.kalender_aktif,
                            },
                            {
                                icon: MonitorCheck, label: 'Scheduler VPS',
                                value: props.ops.heartbeat.is_ok ? 'Aktif' : 'Nonaktif',
                                sub: props.ops.heartbeat.at_label, ok: props.ops.heartbeat.is_ok,
                            },
                        ].map((k) => {
                            const Icon = k.icon;
                            return (
                                <div key={k.label} className="ad-atur-sum__item">
                                    <span className="ad-atur-sum__icon"><Icon /></span>
                                    <span className="ad-atur-sum__label">{k.label}</span>
                                    <strong className="ad-atur-sum__value">
                                        {k.value}
                                        {'ok' in k && <i className={cn('ad-atur-dot', k.ok ? 'is-ok' : 'is-warn')} />}
                                    </strong>
                                    <small>{k.sub}</small>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Jadwal mingguan read-only */}
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Jadwal Mingguan Absensi</h3>
                            <small>Aturan kehadiran, jam masuk, dan jam pulang per hari · {props.kebijakan.mode_label}</small>
                        </div>
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => goto('jadwal')}>
                            <Pencil /> Edit Jadwal
                        </button>
                    </div>
                    <div className="ad-table-wrap">
                        <table className="ad-table">
                            <thead>
                                <tr><th>Hari</th><th>Status</th><th>Wajib Absensi</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Keterangan</th></tr>
                            </thead>
                            <tbody>
                                {DOW_ORDER.map((dow) => {
                                    const r = props.kebijakan.jadwal_mingguan[dow] as AturDayRule | undefined;
                                    const aktif = !!r?.aktif;
                                    return (
                                        <tr key={dow}>
                                            <td><strong>{props.hari_nama[dow]}</strong></td>
                                            <td><span className={cn('ad-pill', aktif ? 'ad-pill--ok' : 'ad-pill--muted')}>{aktif ? 'Aktif' : 'Nonaktif'}</span></td>
                                            <td>{aktif ? 'Wajib' : <span className="ad-mesin-muted">—</span>}</td>
                                            <td style={{ fontVariantNumeric: 'tabular-nums' }}>{aktif ? r?.jam_masuk : '—'}</td>
                                            <td style={{ fontVariantNumeric: 'tabular-nums' }}>{aktif ? r?.jam_pulang : '—'}</td>
                                            <td><small className="ad-mesin-sub">{aktif ? (r?.keterangan || '—') : (r?.keterangan || 'Libur')}</small></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Baris bawah: kalender + slot + riwayat */}
                <div className="ad-atur-bottom">
                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Kalender Akademik</h3><small>Agenda & pengecualian yang berlaku.</small></div>
                            {props.ringkasan.kalender_aktif && <span className="ad-pill ad-pill--ok">Aktif</span>}
                        </div>
                        <div className="ad-panel__body" style={{ display: 'grid', gap: 10 }}>
                            {props.ringkasan.kalender_aktif && (
                                <div className="ad-atur-mini">
                                    <span className="ad-atur-mini__label">Sedang berlangsung</span>
                                    <strong>{props.ringkasan.kalender_aktif.keterangan}</strong>
                                    <small>{props.ringkasan.kalender_aktif.rentang}</small>
                                </div>
                            )}
                            {props.ringkasan.kalender_berikut && (
                                <div className="ad-atur-mini">
                                    <span className="ad-atur-mini__label">Agenda berikutnya</span>
                                    <strong>{props.ringkasan.kalender_berikut.keterangan}</strong>
                                    <small>Mulai {props.ringkasan.kalender_berikut.tanggal}</small>
                                </div>
                            )}
                            {!props.ringkasan.kalender_aktif && !props.ringkasan.kalender_berikut && (
                                <div className="ad-empty" style={{ padding: 18 }}>Belum ada agenda kalender terdaftar.</div>
                            )}
                            <div className="ad-atur-mini">
                                <span className="ad-atur-mini__label">Kategori agenda</span>
                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 4 }}>
                                    {['Libur', 'Ujian', 'Perubahan Jam', 'Informasi'].map((k) => <span key={k} className="ad-mesin-chip">{k}</span>)}
                                </div>
                            </div>
                            <button type="button" className="ad-link" style={{ background: 'none', border: 0, cursor: 'pointer', padding: 0, textAlign: 'left' }} onClick={() => goto('kalender')}>
                                Kelola kalender ({props.ringkasan.kalender_total} entri) →
                            </button>
                        </div>
                    </div>

                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Sesi Lab {pagi ? 'Pagi' : ''}</h3><small>Rincian waktu mesi & standar ({props.ringkasan.slot_per_hari} × {props.ringkasan.slot_menit} menit).</small></div>
                            <ChevronRight style={{ color: 'var(--ad-muted)' }} />
                        </div>
                        <div className="ad-panel__body" style={{ display: 'grid', gap: 10 }}>
                            <div className="ad-atur-mini">
                                <span className="ad-atur-mini__label">{props.ringkasan.slot_per_hari} Slot × {props.ringkasan.slot_menit} menit</span>
                                <strong>{props.ringkasan.sesi_rentang ?? '—'}</strong>
                                <small>Toleransi masuk {pagi?.toleransi_masuk_menit ?? '—'} menit · grace {pagi?.grace_alfa_menit ?? '—'} menit</small>
                            </div>
                            <div className="ad-atur-slotgrid">
                                {props.slot_lab.slice(0, 10).map((s) => (
                                    <span key={s.no} className="ad-atur-slot"><b>{s.no}</b> {s.mulai}</span>
                                ))}
                            </div>
                            <button type="button" className="ad-link" style={{ background: 'none', border: 0, cursor: 'pointer', padding: 0, textAlign: 'left' }} onClick={() => goto('jadwal')}>Atur jam sesi →</button>
                        </div>
                    </div>

                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Riwayat Perubahan</h3><small>Perubahan konfigurasi terakhir.</small></div>
                        </div>
                        <div className="ad-panel__body">
                            {props.riwayat.length === 0 && <div className="ad-empty" style={{ padding: 18 }}>Belum ada riwayat perubahan tercatat.</div>}
                            <ul className="ad-atur-hist">
                                {props.riwayat.map((r, i) => (
                                    <li key={i}>
                                        <span className="ad-atur-hist__dot" />
                                        <div>
                                            <strong>{r.label}</strong>
                                            <small>{r.time_label} · {r.time_exact} · {r.by}</small>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {/* Kolom kanan */}
            <div className="ad-atur-side">
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div><h3>Perlu Perhatian</h3><small>Isu konfigurasi & operasional.</small></div>
                        {issueCount > 0
                            ? <span className="ad-pill ad-pill--danger">{issueCount}</span>
                            : <span className="ad-pill ad-pill--ok">OK</span>}
                    </div>
                    <div className="ad-panel__body" style={{ display: 'grid', gap: 8 }}>
                        {props.ops.issues.length === 0 && (
                            <div className="ad-empty" style={{ padding: 14 }}>Tidak ada isu — konfigurasi siap dipakai.</div>
                        )}
                        {props.ops.issues.map((is, i) => (
                            <div key={i} className={cn('ad-atur-issue', `ad-atur-issue--${is.level}`)}>
                                <span className="ad-atur-issue__dot" />
                                <div>
                                    <strong>{is.title}</strong>
                                    <small>{is.detail}</small>
                                </div>
                                <ChevronRight />
                            </div>
                        ))}
                    </div>
                </div>

                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div><h3>Pengaturan Sesi</h3><small>Jam standar per shift.</small></div>
                    </div>
                    <div className="ad-atur-sesitabs">
                        {props.sesi.map((s) => (
                            <button key={s.shift} type="button"
                                className={cn('ad-atur-sesitab', sesiTab === s.shift && 'is-active')}
                                onClick={() => setSesiTab(s.shift as 'pagi' | 'malam')}>
                                Sesi {s.shift === 'pagi' ? 'Pagi' : 'Malam'}
                            </button>
                        ))}
                    </div>
                    {sesiShown && (
                        <div className="ad-panel__body" style={{ display: 'grid', gap: 8 }}>
                            <div className="ad-mesin-detail__row" style={{ border: 0, padding: '6px 0' }}>
                                <span><Clock style={{ width: 13, height: 13, verticalAlign: -2 }} /> Jam Masuk</span>
                                <strong>{sesiShown.jam_masuk}</strong>
                            </div>
                            {sesiShown.shift === 'pagi' && (
                                <div className="ad-mesin-detail__row" style={{ border: 0, padding: '6px 0' }}>
                                    <span>Mulai tap masuk</span><strong>{sesiShown.jam_mulai_tap_masuk}</strong>
                                </div>
                            )}
                            <div className="ad-mesin-detail__row" style={{ border: 0, padding: '6px 0' }}>
                                <span>Jam Pulang</span><strong>{sesiShown.jam_pulang}</strong>
                            </div>
                            <div className="ad-mesin-detail__row" style={{ border: 0, padding: '6px 0' }}>
                                <span>Toleransi / grace</span><strong>{sesiShown.toleransi_masuk_menit} / {sesiShown.grace_alfa_menit} mnt</strong>
                            </div>
                            <div className="ad-mesin-detail__row" style={{ border: 0, padding: '6px 0' }}>
                                <span>Status sesi</span>
                                <strong><span className={cn('ad-pill', sesiShown.aktif ? 'ad-pill--ok' : 'ad-pill--muted')}>{sesiShown.aktif ? 'Aktif' : 'Nonaktif'}</span></strong>
                            </div>
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" style={{ marginTop: 6 }} onClick={() => goto('jadwal')}>
                                <Pencil /> Ubah Sesi
                            </button>
                        </div>
                    )}
                </div>

                <div className="ad-panel">
                    <div className="ad-panel__head"><div><h3>Kebijakan Absensi</h3><small>Mode kewajiban yang berlaku.</small></div></div>
                    <div className="ad-panel__body" style={{ display: 'grid', gap: 10 }}>
                        {MODE_OPTS.map((m) => (
                            <div key={m.value} className={cn('ad-atur-mode', props.kebijakan.mode === m.value && 'is-active')}>
                                <span className="ad-atur-mode__radio" />
                                <div>
                                    <strong>{m.label}</strong>
                                    <small>{m.desc}</small>
                                </div>
                            </div>
                        ))}
                        {props.kebijakan.effective_from && (
                            <small className="ad-mesin-muted">Berlaku sejak {props.kebijakan.effective_from}</small>
                        )}
                        <div className="ad-atur-mini">
                            <span className="ad-atur-mini__label">Tutup sesi otomatis (WIB)</span>
                            <strong>{props.ops.close_hints.length > 0 ? 'Terjadwal' : 'Tidak ada sesi aktif'}</strong>
                            <small>
                                {props.ops.close_hints.map((h) => `${h.shift}: alfa ~${h.alfa_at}, tidak lengkap ~${h.tidak_lengkap_at}`).join(' · ') || 'Aktifkan sesi untuk menjadwalkan penutupan.'}
                            </small>
                        </div>
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => goto('jadwal')}>
                            <Settings /> Ubah Kebijakan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/* ── Tab: Jadwal & Jam Kerja ── */
function JadwalTab({ props, busy, post }: {
    props: AturAbsensiProps; busy: boolean;
    post: (url: string, data: Record<string, unknown>) => void;
}) {
    const [mode, setMode] = useState(props.kebijakan.mode);
    const [effFrom, setEffFrom] = useState(props.kebijakan.effective_from ?? '');
    const [days, setDays] = useState<Record<string, AturDayRule>>(() =>
        Object.fromEntries(DOW_ORDER.map((d) => [d, { ...(props.kebijakan.jadwal_mingguan[d] ?? { aktif: false, jam_masuk: '07:50', jam_pulang: '17:00', jam_mulai_tap_masuk: '06:30', keterangan: '' }) }]))
    );
    const [sesiForms, setSesiForms] = useState(() =>
        Object.fromEntries(props.sesi.map((s) => [s.shift, { ...s }]))
    );

    const setDay = (dow: string, patch: Partial<AturDayRule>) =>
        setDays((d) => ({ ...d, [dow]: { ...d[dow], ...patch } }));

    const saveKebijakan = () => {
        const payload: Record<string, unknown> = { mode, effective_from: effFrom || null };
        if (mode === 'jadwal_mingguan') {
            payload.jadwal_mingguan = Object.fromEntries(
                DOW_ORDER.map((d) => [d, {
                    aktif: days[d].aktif ? 1 : 0,
                    jam_masuk: days[d].jam_masuk,
                    jam_pulang: days[d].jam_pulang,
                    jam_mulai_tap_masuk: days[d].jam_mulai_tap_masuk,
                    keterangan: days[d].keterangan,
                }])
            );
        }
        post(props.urls.kebijakan, payload);
    };

    const saveSesi = (shift: string) => {
        const s = sesiForms[shift];
        post(props.urls.sesi, {
            shift,
            jam_masuk: s.jam_masuk,
            jam_mulai_tap_masuk: s.jam_mulai_tap_masuk,
            jam_pulang: s.jam_pulang,
            toleransi_masuk_menit: s.toleransi_masuk_menit,
            toleransi_pulang_menit: s.toleransi_pulang_menit,
            grace_alfa_menit: s.grace_alfa_menit,
            aktif: s.aktif ? 1 : 0,
            timezone: s.timezone,
        });
    };

    return (
        <div className="ad-atur-grid ad-animate">
            <div className="ad-atur-main">
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div><h3>Kebijakan & jadwal mingguan</h3><small>Pilih mode kewajiban lalu atur jam per hari.</small></div>
                    </div>
                    <div className="ad-panel__body" style={{ display: 'grid', gap: 14 }}>
                        <div className="ad-atur-modes">
                            {MODE_OPTS.map((m) => (
                                <button key={m.value} type="button" disabled={!props.can.update}
                                    className={cn('ad-atur-mode ad-atur-mode--btn', mode === m.value && 'is-active')}
                                    onClick={() => setMode(m.value)}>
                                    <span className="ad-atur-mode__radio" />
                                    <div><strong>{m.label}</strong><small>{m.desc}</small></div>
                                </button>
                            ))}
                        </div>

                        {mode === 'jadwal_mingguan' && (
                            <>
                                <div className="ad-mesin-field" style={{ maxWidth: 320 }}>
                                    <label>Tanggal mulai berlaku</label>
                                    <input type="date" value={effFrom} onChange={(e) => setEffFrom(e.target.value)} />
                                    <small>Riwayat sebelum tanggal ini tidak ikut berubah.</small>
                                </div>
                                <div className="ad-table-wrap">
                                    <table className="ad-table">
                                        <thead>
                                            <tr><th>Hari</th><th>Wajib</th><th>Mulai Tap</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Keterangan</th></tr>
                                        </thead>
                                        <tbody>
                                            {DOW_ORDER.map((dow) => (
                                                <tr key={dow} className={cn(!days[dow].aktif && 'ad-atur-row-off')}>
                                                    <td><strong>{props.hari_nama[dow]}</strong></td>
                                                    <td>
                                                        <label className="ad-mesin-check">
                                                            <input type="checkbox" checked={days[dow].aktif} disabled={!props.can.update}
                                                                onChange={(e) => setDay(dow, { aktif: e.target.checked })} />
                                                            Wajib
                                                        </label>
                                                    </td>
                                                    <td><input type="time" className="ad-atur-time" value={days[dow].jam_mulai_tap_masuk} disabled={!props.can.update} onChange={(e) => setDay(dow, { jam_mulai_tap_masuk: e.target.value })} /></td>
                                                    <td><input type="time" className="ad-atur-time" value={days[dow].jam_masuk} disabled={!props.can.update} onChange={(e) => setDay(dow, { jam_masuk: e.target.value })} /></td>
                                                    <td><input type="time" className="ad-atur-time" value={days[dow].jam_pulang} disabled={!props.can.update} onChange={(e) => setDay(dow, { jam_pulang: e.target.value })} /></td>
                                                    <td><input type="text" className="ad-atur-ket" value={days[dow].keterangan} maxLength={120} disabled={!props.can.update} placeholder="Keterangan" onChange={(e) => setDay(dow, { keterangan: e.target.value })} /></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                        {props.can.update && (
                            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy} onClick={saveKebijakan}>
                                    {busy ? 'Menyimpan…' : 'Simpan kebijakan & jadwal'}
                                </button>
                            </div>
                        )}
                        {!props.can.update && <small className="ad-mesin-muted">Anda hanya dapat melihat konfigurasi — perubahan memerlukan akses admin.</small>}
                    </div>
                </div>

                {props.slot_lab.length > 0 && (
                    <div className="ad-panel">
                        <div className="ad-panel__head"><div><h3>Slot lab pagi ({props.slot_lab.length} × 55 menit)</h3><small>Indikator waktu dari jam standar — bukan kewajiban tap per slot.</small></div></div>
                        <div className="ad-table-wrap">
                            <table className="ad-table">
                                <thead><tr><th>Slot</th><th>Mulai</th><th>Selesai</th></tr></thead>
                                <tbody>
                                    {props.slot_lab.map((s) => (
                                        <tr key={s.no}><td><strong>{s.no}</strong></td><td>{s.mulai}</td><td>{s.selesai}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            <div className="ad-atur-side">
                {props.sesi.map((s) => {
                    const f = sesiForms[s.shift];
                    return (
                        <div key={s.shift} className="ad-panel">
                            <div className="ad-panel__head">
                                <div><h3>Sesi {s.shift === 'pagi' ? 'Pagi' : 'Malam'}</h3><small>Zona waktu {s.timezone}</small></div>
                                <span className={cn('ad-pill', f.aktif ? 'ad-pill--ok' : 'ad-pill--muted')}>{f.aktif ? 'Aktif' : 'Nonaktif'}</span>
                            </div>
                            <div className="ad-panel__body" style={{ display: 'grid', gap: 10 }}>
                                <div className="ad-mesin-field"><label>Jam standar masuk</label>
                                    <input type="time" value={f.jam_masuk} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, jam_masuk: e.target.value } }))} /></div>
                                {s.shift === 'pagi' && (
                                    <div className="ad-mesin-field"><label>Mulai tap masuk</label>
                                        <input type="time" value={f.jam_mulai_tap_masuk} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, jam_mulai_tap_masuk: e.target.value } }))} />
                                        <small>Tap masuk paling awal sebelum jam standar.</small></div>
                                )}
                                <div className="ad-mesin-field"><label>Jam pulang</label>
                                    <input type="time" value={f.jam_pulang} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, jam_pulang: e.target.value } }))} /></div>
                                <div className="ad-atur-duo">
                                    <div className="ad-mesin-field"><label>Toleransi masuk (mnt)</label>
                                        <input type="number" min={0} max={180} value={f.toleransi_masuk_menit} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, toleransi_masuk_menit: Number(e.target.value) } }))} /></div>
                                    <div className="ad-mesin-field"><label>Toleransi pulang (mnt)</label>
                                        <input type="number" min={0} max={180} value={f.toleransi_pulang_menit} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, toleransi_pulang_menit: Number(e.target.value) } }))} /></div>
                                </div>
                                <div className="ad-mesin-field"><label>Grace tidak lengkap (mnt)</label>
                                    <input type="number" min={15} max={360} value={f.grace_alfa_menit} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, grace_alfa_menit: Number(e.target.value) } }))} /></div>
                                <label className="ad-mesin-check">
                                    <input type="checkbox" checked={f.aktif} disabled={!props.can.update} onChange={(e) => setSesiForms((p) => ({ ...p, [s.shift]: { ...f, aktif: e.target.checked } }))} />
                                    Sesi aktif
                                </label>
                                {props.can.update && (
                                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy} onClick={() => saveSesi(s.shift)}>
                                        {busy ? 'Menyimpan…' : `Simpan sesi ${s.shift}`}
                                    </button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/* ── Tab: Kalender Akademik ── */
function KalenderTab({ props, busy, post, setConfirm }: {
    props: AturAbsensiProps; busy: boolean;
    post: (url: string, data: Record<string, unknown>, method?: 'post' | 'delete') => void;
    setConfirm: (c: { title: string; body: string; label: string; danger?: boolean; action: () => void } | null) => void;
}) {
    const [form, setForm] = useState({
        tanggal: '', tanggal_selesai: '', jenis: 'libur', dampak_absensi: 'batal_wajib',
        jam_masuk_khusus: '07:50', jam_pulang_khusus: '12:00', keterangan: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(props.urls.libur, { ...form, tanggal_selesai: form.tanggal_selesai || null, keterangan: form.keterangan || null });
    };

    return (
        <div className="ad-animate" style={{ display: 'grid', gap: 14 }}>
            {props.can.update && (
                <div className="ad-panel">
                    <div className="ad-panel__head"><div><h3>Tambah Agenda / Pengecualian</h3><small>Libur, perubahan jam khusus, periode ujian, atau informasi akademik.</small></div></div>
                    <form className="ad-panel__body" onSubmit={submit}>
                        <div className="ad-atur-calform">
                            <div className="ad-mesin-field"><label>Tanggal Mulai <em>*</em></label>
                                <input type="date" required value={form.tanggal} onChange={(e) => setForm({ ...form, tanggal: e.target.value })} /></div>
                            <div className="ad-mesin-field"><label>Tanggal Selesai</label>
                                <input type="date" value={form.tanggal_selesai} min={form.tanggal} onChange={(e) => setForm({ ...form, tanggal_selesai: e.target.value })} /></div>
                            <div className="ad-mesin-field"><label>Kategori Agenda</label>
                                <select value={form.jenis} onChange={(e) => {
                                    const j = e.target.value;
                                    setForm({ ...form, jenis: j, dampak_absensi: j === 'perubahan_jam' ? 'ubah_jam' : j === 'libur' ? 'batal_wajib' : 'tetap_wajib' });
                                }}>
                                    <option value="libur">Hari Libur Nasional / Kampus</option>
                                    <option value="perubahan_jam">Perubahan Jam Khusus</option>
                                    <option value="ujian">Periode Ujian (UTS / UAS)</option>
                                    <option value="informasi">Informasi / Kegiatan Akademik</option>
                                </select></div>
                            <div className="ad-mesin-field"><label>Dampak Absensi</label>
                                <select value={form.dampak_absensi} onChange={(e) => setForm({ ...form, dampak_absensi: e.target.value })}>
                                    <option value="batal_wajib">Membatalkan wajib (libur / tanpa alfa)</option>
                                    <option value="tetap_wajib">Tetap wajib sesuai jadwal</option>
                                    <option value="ubah_jam">Mengubah jam masuk & pulang</option>
                                </select></div>
                            {form.dampak_absensi === 'ubah_jam' && (
                                <>
                                    <div className="ad-mesin-field"><label>Jam Masuk Khusus</label>
                                        <input type="time" value={form.jam_masuk_khusus} onChange={(e) => setForm({ ...form, jam_masuk_khusus: e.target.value })} /></div>
                                    <div className="ad-mesin-field"><label>Jam Pulang Khusus</label>
                                        <input type="time" value={form.jam_pulang_khusus} onChange={(e) => setForm({ ...form, jam_pulang_khusus: e.target.value })} /></div>
                                </>
                            )}
                            <div className="ad-mesin-field" style={{ gridColumn: '1 / -1' }}><label>Keterangan</label>
                                <input type="text" maxLength={255} placeholder="Mis. Libur Nasional / Ujian Tengah Semester" value={form.keterangan} onChange={(e) => setForm({ ...form, keterangan: e.target.value })} /></div>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 10 }}>
                            <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}><Plus /> {busy ? 'Menyimpan…' : 'Simpan Tanggal'}</button>
                        </div>
                    </form>
                </div>
            )}

            <div className="ad-panel">
                <div className="ad-panel__head"><div><h3>Daftar Kalender ({props.kalender.length})</h3><small>Agenda & pengecualian terdaftar, terbaru di atas.</small></div></div>
                <div className="ad-table-wrap">
                    <table className="ad-table">
                        <thead><tr><th>Rentang Tanggal</th><th>Kategori</th><th>Dampak</th><th>Keterangan</th><th>Diubah</th>{props.can.update && <th>Aksi</th>}</tr></thead>
                        <tbody>
                            {props.kalender.length === 0 && <tr><td colSpan={6} className="ad-empty">Belum ada agenda kalender.</td></tr>}
                            {props.kalender.map((k) => (
                                <tr key={k.id}>
                                    <td><strong>{k.rentang}</strong></td>
                                    <td><span className="ad-mesin-chip">{JENIS_LABEL[k.jenis] ?? k.jenis}</span></td>
                                    <td>
                                        {k.dampak === 'batal_wajib' && <span className="ad-pill ad-pill--muted">Libur</span>}
                                        {k.dampak === 'tetap_wajib' && <span className="ad-pill ad-pill--ok">Tetap wajib</span>}
                                        {k.dampak === 'ubah_jam' && <span className="ad-pill ad-pill--pending">Ubah jam {k.jam_khusus}</span>}
                                    </td>
                                    <td>{k.keterangan}</td>
                                    <td><small className="ad-mesin-sub">{k.updated_label ?? '—'}</small></td>
                                    {props.can.update && (
                                        <td>
                                            <button type="button" className="ad-mesin-ic ad-mesin-ic--danger" title="Hapus"
                                                onClick={() => setConfirm({
                                                    title: 'Hapus agenda kalender?',
                                                    body: `"${k.keterangan}" (${k.rentang}) akan dihapus dari kalender akademik.`,
                                                    label: 'Ya, Hapus', danger: true,
                                                    action: () => post(`${props.urls.libur}/${k.id}`, {}, 'delete'),
                                                })}>
                                                <Trash2 />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

/* ── Tab: Kesiapan Operasional ── */
function OpsTab({ props, busy, post, setConfirm }: {
    props: AturAbsensiProps; busy: boolean;
    post: (url: string, data: Record<string, unknown>) => void;
    setConfirm: (c: { title: string; body: string; label: string; danger?: boolean; action: () => void } | null) => void;
}) {
    const [tgl, setTgl] = useState(props.ops.yesterday);
    const s = props.ops.stats;
    const stats = [
        { label: 'Scheduler VPS', value: props.ops.heartbeat.is_ok ? 'Aktif' : 'Tidak aktif', sub: props.ops.heartbeat.at_label, bad: !props.ops.heartbeat.is_ok },
        { label: 'Tap hari ini', value: s.taps_hari_ini },
        { label: 'Alfa kemarin', value: s.alfa_kemarin },
        { label: 'Mhs tanpa shift', value: s.tanpa_shift, warn: s.tanpa_shift > 0 },
        { label: 'Lengkap hari ini', value: s.lengkap_hari_ini },
    ];

    return (
        <div className="ad-animate" style={{ display: 'grid', gap: 14 }}>
            <div className={cn('ad-atur-opsbanner', props.ops.kamis_ready ? 'is-ready' : 'is-alert')}>
                <div>
                    <span className="ad-atur-opsbanner__eyebrow">Kesiapan operasional</span>
                    <h3>{props.ops.kamis_ready ? 'Siap produksi' : 'Perlu tindakan'}</h3>
                    <small>
                        {props.ops.kebijakan_label} · {props.ops.today}
                        {props.ops.hari_wajib_hari_ini && ' · Hari wajib absen'}
                    </small>
                </div>
                <span className={cn('ad-pill', props.ops.kamis_ready ? 'ad-pill--ok' : 'ad-pill--danger')}>
                    {props.ops.kamis_ready ? 'Siap' : 'Cek isu'}
                </span>
            </div>

            <div className="ad-atur-opsstats">
                {stats.map((st) => (
                    <div key={st.label} className={cn('ad-atur-opsstat', st.bad && 'is-bad', st.warn && 'is-warn')}>
                        <span>{st.label}</span>
                        <strong>{st.value}</strong>
                        {st.sub && <small>{st.sub}</small>}
                    </div>
                ))}
            </div>

            {props.ops.close_hints.length > 0 && (
                <div className="ad-atur-mini" style={{ padding: '10px 14px' }}>
                    <span className="ad-atur-mini__label">Tutup sesi otomatis (WIB)</span>
                    <strong style={{ fontSize: 13 }}>
                        {props.ops.close_hints.map((h) => `${h.shift}: alfa ~${h.alfa_at}, tidak lengkap ~${h.tidak_lengkap_at}`).join('  ·  ')}
                    </strong>
                </div>
            )}

            {(props.ops.issues.length > 0 || props.ops.pbl.issues.length > 0) && (
                <div className="ad-panel">
                    <div className="ad-panel__head"><div><h3>Isu yang perlu ditindaklanjuti</h3><small>Kesiapan absensi & data PBL.</small></div></div>
                    <div className="ad-panel__body" style={{ display: 'grid', gap: 8 }}>
                        {[...props.ops.issues, ...props.ops.pbl.issues].map((is, i) => (
                            <div key={i} className={cn('ad-atur-issue', `ad-atur-issue--${is.level}`)}>
                                <span className="ad-atur-issue__dot" />
                                <div><strong>{is.title}</strong><small>{is.detail}</small></div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="ad-panel">
                <div className="ad-panel__head"><div><h3>Aksi operasional</h3><small>Jalankan penutupan sesi manual bila scheduler belum aktif.</small></div></div>
                <div className="ad-panel__body ad-atur-opsact">
                    <div className="ad-mesin-field" style={{ maxWidth: 220 }}>
                        <label>Tutup sesi tanggal</label>
                        <input type="date" value={tgl} onChange={(e) => setTgl(e.target.value)} />
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy || !props.can.update}
                            onClick={() => setConfirm({
                                title: 'Tutup sesi & hitung alfa?',
                                body: `Menjalankan penutupan sesi untuk ${tgl}. Entri alfa akan dibuat untuk yang belum tap.`,
                                label: 'Ya, Jalankan',
                                action: () => post(props.urls.tutup_sesi, { tanggal: tgl }),
                            })}>
                            <Zap /> Tutup sesi / hitung alfa
                        </button>
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" disabled={busy || !props.can.update}
                            onClick={() => setConfirm({
                                title: 'Cadangan alfa 3 hari?',
                                body: 'Memproses penutupan sesi untuk 3 hari ke belakang pada hari wajib.',
                                label: 'Ya, Proses',
                                action: () => post(props.urls.generate_alfa, { days: 3 }),
                            })}>
                            Cadangan alfa (3 hari)
                        </button>
                        <Link href={props.urls.pbl} className="ad-mesin-btn ad-mesin-btn--ghost">Proyek PBL</Link>
                        <a href={props.urls.rekap} className="ad-mesin-btn ad-mesin-btn--ghost">Rekap absensi</a>
                        <a href={props.urls.fingerprint} className="ad-mesin-btn ad-mesin-btn--ghost">Fingerprint / shift</a>
                    </div>
                </div>
            </div>
        </div>
    );
}
