import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Check, Cpu, Eye, FileClock, Fingerprint as FpIcon, RefreshCw,
    Pencil, RotateCcw, Search, Share2, ShieldCheck, Trash2, Users, X, Zap,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart, LineChart } from '@admin/components/Charts';
import { cn } from '@admin/lib/utils';
import type { FingerprintProps, FpRow, SharedProps } from '@admin/types';

const STATUS_LABEL: Record<string, string> = {
    enrolled: 'Blob OK', legacy: 'Legacy', pending: 'Belum enroll',
    unassigned: 'Perlu Finger ID', tidak_aktif: 'Tidak aktif',
};
const STATUS_CLASS: Record<string, string> = {
    enrolled: 'ad-pill--ok', legacy: 'ad-pill--danger', pending: 'ad-pill--pending',
    unassigned: 'ad-pill--muted', tidak_aktif: 'ad-pill--danger',
};
const SIAP_CLASS: Record<string, string> = {
    enrolled: 'ad-fp-siap--ok', legacy: 'ad-fp-siap--danger', pending: 'ad-fp-siap--warn',
    unassigned: 'ad-fp-siap--danger', tidak_aktif: 'ad-fp-siap--muted',
};

/** Ilustrasi fingerprint — inline SVG seirama hero mesin. */
function FpArt() {
    return (
        <svg className="ad-mesin-hero__art" viewBox="0 0 360 190" fill="none" aria-hidden="true">
            <path d="M0 158 Q 100 125 200 145 T 360 132" stroke="rgba(125,211,252,.35)" strokeWidth="1.5"/>
            <path d="M0 172 Q 110 145 210 158 T 360 148" stroke="rgba(125,211,252,.2)" strokeWidth="1.3"/>
            {/* Kartu fingerprint utama */}
            <rect x="122" y="28" width="116" height="134" rx="16" fill="rgba(13,27,54,.55)" stroke="rgba(147,197,253,.6)" strokeWidth="1.6"/>
            <g stroke="#7dd3fc" strokeWidth="2.4" strokeLinecap="round" fill="none">
                <path d="M180 62 c-16 0 -26 12 -26 28 0 22 4 36 8 48"/>
                <path d="M180 62 c16 0 26 12 26 28 0 14 -1 26 -3 36"/>
                <path d="M180 74 c-10 0 -16 8 -16 17 0 17 3 29 6 40"/>
                <path d="M180 74 c10 0 16 8 16 17 0 12 -1 22 -2 30"/>
                <path d="M180 88 c-5 0 -8 4 -8 9 0 13 2 24 4 33"/>
                <path d="M180 88 c5 0 8 4 8 9 0 9 0 18 -1 25"/>
                <path d="M180 103 c0 12 1 21 2 28"/>
            </g>
            {/* Badge centang */}
            <circle cx="238" cy="140" r="20" fill="#059669" stroke="rgba(167,243,208,.7)" strokeWidth="2"/>
            <path d="M229 140 l6 6 12 -12" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
            {/* Kartu mesin kecil kiri */}
            <rect x="56" y="60" width="52" height="80" rx="10" fill="rgba(13,27,54,.45)" stroke="rgba(147,197,253,.4)" strokeWidth="1.4"/>
            <rect x="66" y="72" width="32" height="8" rx="4" fill="rgba(125,211,252,.35)"/>
            <circle cx="82" cy="102" r="13" fill="none" stroke="#7dd3fc" strokeWidth="1.8"/>
            <circle cx="82" cy="102" r="5" fill="#38bdf8"/>
            <rect x="68" y="124" width="28" height="6" rx="3" fill="rgba(52,211,153,.5)"/>
            {/* Gelombang sync kanan */}
            <g stroke="rgba(125,211,252,.55)" strokeWidth="2" strokeLinecap="round">
                <path d="M256 88 q10 -8 20 0 t20 0"/>
                <path d="M256 104 q10 -8 20 0 t20 0"/>
                <path d="M256 120 q10 -8 20 0 t20 0"/>
            </g>
        </svg>
    );
}

export default function Fingerprint(props: FingerprintProps) {
    const { flash, errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState(props.tab);
    const [busy, setBusy] = useState(false);
    const [confirm, setConfirm] = useState<null | { title: string; body: string; label: string; danger?: boolean; action: () => void }>(null);

    const post = (url: string, data: Record<string, unknown> = {}, method: 'post' | 'delete' = 'post') => {
        setBusy(true);
        router[method](url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => setConfirm(null),
        });
    };

    const nav = (params: Record<string, unknown>, only?: string[]) => {
        router.get(props.urls.index, {
            tab: tab !== 'ringkasan' ? tab : undefined,
            ...props.filters, ...params,
        }, {
            preserveState: true, preserveScroll: true,
            // Partial reload: hanya prop terkait yang diambil ulang + tanpa progress bar.
            ...(only ? { only, showProgress: false } : {}),
        });
    };

    const switchTab = (t: string) => {
        setTab(t as typeof tab);
        router.get(props.urls.index, { ...props.filters, tab: t === 'ringkasan' ? undefined : t },
            { preserveState: true, preserveScroll: true, replace: true, only: [] });
    };

    const TABS = [
        { key: 'ringkasan', label: 'Ringkasan', icon: ShieldCheck },
        { key: 'mahasiswa', label: 'Daftar Pengguna', icon: Users },
        { key: 'distribusi', label: 'Distribusi Mesin', icon: Share2 },
        { key: 'sinkronisasi', label: 'Sinkronisasi', icon: RefreshCw },
        { key: 'log', label: 'Log & Riwayat', icon: FileClock },
    ] as const;

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Fingerprint Enroll"
            subtitle="Status sidik jari pengguna & distribusi ke mesin.">
            <Head title="Fingerprint Enroll" />

            <div className="ad-mesin-hero ad-animate">
                <div className="ad-mesin-hero__text">
                    <h2>Fingerprint Enroll</h2>
                    <p>
                        Kelola data sidik jari mahasiswa & staf, status enroll, dan distribusi
                        template ke mesin absensi/ruangan secara terpusat.
                    </p>
                </div>
                <FpArt />
            </div>

            {flash.success && <div className="ad-mesin-flash ad-mesin-flash--ok"><Check /> {flash.success}</div>}
            {flash.error && <div className="ad-mesin-flash ad-mesin-flash--err"><X /> {flash.error}</div>}
            {flash.info && <div className="ad-mesin-flash" style={{ background: 'rgba(2,132,199,.1)', color: 'var(--ad-soft)', border: '1px solid rgba(2,132,199,.25)' }}><FpIcon /> {flash.info}</div>}
            {errors && Object.keys(errors).length > 0 && (
                <div className="ad-mesin-flash ad-mesin-flash--err"><X /> {Object.values(errors)[0]}</div>
            )}

            <div className="ad-atur-tabs ad-animate" role="tablist">
                {TABS.map((t) => {
                    const Icon = t.icon;
                    return (
                        <button key={t.key} type="button" role="tab" aria-selected={tab === t.key}
                            className={cn('ad-atur-tab', tab === t.key && 'is-active')}
                            onClick={() => switchTab(t.key)}>
                            <Icon /> {t.label}
                        </button>
                    );
                })}
            </div>

            {(tab === 'ringkasan' || tab === 'mahasiswa') && (
                <>
                    {tab === 'ringkasan' && <RingkasanTab props={props} busy={busy} post={post} setConfirm={setConfirm} goto={switchTab} />}
                    <MhsTable props={props} busy={busy} post={post} nav={nav} setConfirm={setConfirm} />
                </>
            )}
            {tab === 'distribusi' && <DistribusiTab props={props} busy={busy} post={post} setConfirm={setConfirm} />}
            {tab === 'sinkronisasi' && <SinkronisasiTab props={props} />}
            {tab === 'log' && <LogTab props={props} nav={nav} />}

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

/* ── Kartu KPI atas ── */
function KpiRow({ stats }: { stats: FingerprintProps['stats'] }) {
    const pct = (n: number) => stats.total > 0 ? ((n / stats.total) * 100).toFixed(1) : '0';
    const kpis = [
        { label: 'Sudah Enroll', value: stats.enrolled, sub: `${pct(stats.enrolled)}%`, cls: 'is-ok' },
        { label: 'Belum Enroll', value: stats.pending_enroll, sub: `${pct(stats.pending_enroll)}%`, cls: 'is-warn' },
        { label: 'Belum Assign', value: stats.unassigned, sub: `${pct(stats.unassigned)}%`, cls: 'is-danger' },
        { label: 'Kapasitas Slot', value: `${stats.capacity_used} / ${stats.capacity_total}`, sub: 'slot sensor terpakai', cls: 'is-info' },
    ];
    return (
        <div className="ad-fp-kpis">
            {kpis.map((k) => (
                <div key={k.label} className={cn('ad-fp-kpi', k.cls)}>
                    <span className="ad-fp-kpi__icon"><FpIcon /></span>
                    <span className="ad-fp-kpi__label">{k.label}</span>
                    <strong>{k.value}</strong>
                    <small>{k.sub}</small>
                </div>
            ))}
            <div className="ad-fp-kpi ad-fp-kpi--progress">
                <span className="ad-fp-kpi__label">Progress Enroll</span>
                <strong>{stats.completion_rate}%</strong>
                <div className="ad-fp-progress"><i style={{ width: `${Math.min(100, stats.completion_rate)}%` }} /></div>
                <small>{stats.enrolled} dari {stats.total} {(stats.role_label || 'pengguna').toLowerCase()}</small>
            </div>
        </div>
    );
}

/* ── Kartu distribusi template ── */
function DistribusiCard({ props, busy, post, setConfirm, expanded }: {
    props: FingerprintProps; busy: boolean;
    post: (url: string, data?: Record<string, unknown>) => void;
    setConfirm: (c: { title: string; body: string; label: string; danger?: boolean; action: () => void } | null) => void;
    expanded?: boolean;
}) {
    const auto = props.sync.mode === 'auto';
    const modeLabel = auto ? 'Otomatis' : props.sync.burst_active ? 'Manual · distribusi aktif' : 'Manual';
    return (
        <div className="ad-panel">
            <div className="ad-panel__head">
                <div><h3>Distribusi Template ke Mesin</h3><small>Kirim data sidik jari ke mesin absensi otomatis atau manual.</small></div>
                <span className={cn('ad-pill', auto ? 'ad-pill--ok' : 'ad-pill--pending')}>Mode: {modeLabel}</span>
            </div>
            <div className="ad-panel__body" style={{ display: 'grid', gap: 12 }}>
                <div className="ad-fp-steps">
                    {[
                        { n: 1, t: 'Mesin Absensi', d: 'Scan 2× → upload' },
                        { n: 2, t: 'Database', d: 'Template per ID' },
                        { n: 3, t: 'Mesin Lain', d: 'Pull template' },
                    ].map((s) => (
                        <div key={s.n} className="ad-fp-step">
                            <span className="ad-fp-step__n">{s.n}</span>
                            <div><strong>{s.t}</strong><small>{s.d}</small></div>
                        </div>
                    ))}
                </div>
                {props.can.update && (
                    <div className="ad-fp-syncbtns">
                        <button type="button" disabled={busy}
                            className={cn('ad-mesin-btn', auto ? 'ad-mesin-btn--primary' : 'ad-mesin-btn--ghost')}
                            onClick={() => post(props.urls.sync_mode, { mode: 'auto' })}>
                            <Zap /> Sync Otomatis
                        </button>
                        <button type="button" disabled={busy}
                            className={cn('ad-mesin-btn', !auto ? 'ad-mesin-btn--primary' : 'ad-mesin-btn--ghost')}
                            onClick={() => post(props.urls.sync_mode, { mode: 'manual' })}>
                            Manual (Pull)
                        </button>
                        <button type="button" disabled={busy}
                            className="ad-mesin-btn ad-mesin-btn--ghost"
                            onClick={() => setConfirm({
                                title: 'Distribusi template sekarang?',
                                body: 'Distribusi diaktifkan 20 menit — mesin absensi menerima template pada request berikutnya.',
                                label: 'Ya, Distribusikan',
                                action: () => post(props.urls.sync_distribute),
                            })}>
                            <Share2 /> Distribusi Sekarang
                        </button>
                    </div>
                )}
                {expanded && (
                    <div className="ad-fp-mesinlist">
                        <span className="ad-atur-mini__label">Mesin absensi penerima</span>
                        {props.options.mesin.length === 0 && <small className="ad-mesin-muted">Belum ada mesin tipe absensi terdaftar.</small>}
                        {props.options.mesin.map((m) => (
                            <div key={m.id_mesin} className="ad-fp-mesinitem">
                                <Cpu />
                                <div><strong>{m.id_mesin}</strong><small>{m.nama}</small></div>
                            </div>
                        ))}
                        <Link href={props.urls.mesin} className="ad-link">Kelola mesin →</Link>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ── Tab: Ringkasan ── */
function RingkasanTab({ props, busy, post, setConfirm, goto }: {
    props: FingerprintProps; busy: boolean;
    post: (url: string, data?: Record<string, unknown>) => void;
    setConfirm: (c: { title: string; body: string; label: string; danger?: boolean; action: () => void } | null) => void;
    goto: (t: string) => void;
}) {
    const donutTotal = props.charts.donut.values.reduce((a, b) => a + b, 0);
    return (
        <div className="ad-animate" style={{ display: 'grid', gap: 14, marginBottom: 14 }}>
            <KpiRow stats={props.stats} />
            <div className="ad-fp-midrow">
                <div className="ad-panel">
                    <div className="ad-panel__head"><div><h3>Status Enroll {props.stats.role_label || 'Mahasiswa'}</h3><small>Sudah · belum scan · belum assign.</small></div></div>
                    <div className="ad-panel__body">
                        <DonutChart data={{
                            total: Math.max(1, donutTotal),
                            labels: props.charts.donut.labels,
                            values: props.charts.donut.values,
                            colors: props.charts.donut.colors,
                            center_label: props.stats.role_label || 'Mahasiswa',
                        }} />
                        <ul className="ad-fp-legend">
                            {props.charts.donut.labels.map((l, i) => {
                                const v = props.charts.donut.values[i] ?? 0;
                                const p = donutTotal > 0 ? Math.round((v / donutTotal) * 1000) / 10 : 0;
                                return (
                                    <li key={l}>
                                        <span className="ad-fp-legend__dot" style={{ background: props.charts.donut.colors[i] }} />
                                        {l}
                                        <b>{v} ({p}%)</b>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>
                <div className="ad-panel">
                    <div className="ad-panel__head"><div><h3>Tren Enroll Kumulatif</h3><small>Template diperbarui per hari · 7 hari terakhir.</small></div></div>
                    <div className="ad-panel__body">
                        <LineChart height={210} data={{
                            labels: props.charts.trend.labels,
                            series: [{ key: 'enroll', label: 'Template diperbarui', color: '#14b8a6', data: props.charts.trend.values }],
                        }} />
                        <button type="button" className="ad-link" style={{ background: 'none', border: 0, cursor: 'pointer', padding: 0 }} onClick={() => goto('mahasiswa')}>
                            Lihat daftar {props.stats.role_label || 'pengguna'} →
                        </button>
                    </div>
                </div>
                <DistribusiCard props={props} busy={busy} post={post} setConfirm={setConfirm} />
            </div>
        </div>
    );
}

/* ── Daftar mahasiswa (dipakai tab ringkasan & mahasiswa) ── */
function MhsTable({ props, busy, post, nav, setConfirm }: {
    props: FingerprintProps; busy: boolean;
    post: (url: string, data?: Record<string, unknown>, method?: 'post' | 'delete') => void;
    nav: (params: Record<string, unknown>, only?: string[]) => void;
    setConfirm: (c: { title: string; body: string; label: string; danger?: boolean; action: () => void } | null) => void;
}) {
    const f = props.filters;
    const [draft, setDraft] = useState({ q: f.q, status: f.status, kelas: f.kelas, tim: f.tim, role: f.role });
    const [editId, setEditId] = useState<number | null>(null);
    const [editVal, setEditVal] = useState('');
    const meta = props.mahasiswa.meta;
    const roleLabel = props.stats.role_label || 'Mahasiswa';
    const isMhs = f.role === 'mahasiswa';

    const applyFilter = () => nav({ ...draft, page: undefined }, ['mahasiswa', 'filters', 'stats', 'charts']);
    const resetFilter = () => { setDraft({ q: '', status: '', kelas: '', tim: '', role: 'mahasiswa' }); nav({ q: '', status: '', kelas: '', tim: '', role: undefined, page: undefined }, ['mahasiswa', 'filters', 'stats', 'charts']); };
    const gotoPage = (p: number) => nav({ page: p }, ['mahasiswa']);

    const assign = (row: FpRow, val: string) => {
        post(`${props.urls.user_base}/${row.id}/assign`, { finger_id: val === '' ? null : Number(val) });
        setEditId(null);
    };

    return (
        <div className="ad-panel ad-animate">
            <div className="ad-panel__head">
                <div><h3>Daftar {roleLabel}</h3><small>{meta.total} hasil ditemukan</small></div>
                {props.can.update && isMhs && (
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" disabled={busy}
                        onClick={() => setConfirm({
                            title: 'Perbaiki shift kosong?',
                            body: 'Sistem mengisi shift pagi/malam otomatis untuk mahasiswa yang belum punya shift.',
                            label: 'Ya, Perbaiki',
                            action: () => post(props.urls.backfill),
                        })}>
                        <RotateCcw /> Perbaiki Shift Kosong
                    </button>
                )}
            </div>
            <div className="ad-fp-filters">
                <div className="ad-mesin-field ad-fp-filters__q">
                    <Search />
                    <input type="text" placeholder="Cari nama, NIM, atau email…" value={draft.q}
                        onChange={(e) => setDraft({ ...draft, q: e.target.value })}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilter()} />
                </div>
                <select value={draft.role} onChange={(e) => setDraft({ ...draft, role: e.target.value, kelas: '', tim: '' })}>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="laboran">Laboran</option>
                    <option value="dosen">Dosen</option>
                    <option value="admin">Admin</option>
                </select>
                <select value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                    <option value="">Semua Status</option>
                    <option value="enrolled">Blob OK</option>
                    <option value="pending_enroll">Belum enroll</option>
                    <option value="legacy_re_enroll">Legacy (enroll ulang)</option>
                    <option value="unassigned">Belum assign Finger ID</option>
                    <option value="inactive">Tidak aktif</option>
                </select>
                {draft.role === 'mahasiswa' && (
                    <>
                        <select value={draft.kelas} onChange={(e) => setDraft({ ...draft, kelas: e.target.value })}>
                            <option value="">Semua Kelas</option>
                            {props.options.kelas.map((k) => <option key={k} value={k}>{k}</option>)}
                        </select>
                        <select value={draft.tim} onChange={(e) => setDraft({ ...draft, tim: e.target.value })}>
                            <option value="">Semua Tim</option>
                            {props.options.tim.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </>
                )}
                <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={applyFilter}>Filter</button>
                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={resetFilter}>Reset</button>
            </div>
            <div className="ad-table-wrap ad-fp-desktop">
                <table className="ad-table">
                    <thead>
                        <tr><th>Nama</th><th>NIM</th><th>Kelas / Tim</th><th>Shift</th><th>Finger ID</th><th>Status</th><th>Kesiapan</th><th className="is-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                        {props.mahasiswa.data.length === 0 && (
                            <tr><td colSpan={8} className="ad-empty">Tidak ada pengguna sesuai filter.</td></tr>
                        )}
                        {props.mahasiswa.data.map((s) => (
                            <tr key={s.id}>
                                <td>
                                    <a href={s.profile_url} className="ad-link" style={{ fontWeight: 700 }}>{s.nama}</a>
                                    <div className="ad-mesin-sub">{s.email || '—'}</div>
                                </td>
                                <td className="ad-mono">{s.nim}</td>
                                <td><small>{s.role === 'mahasiswa'
                                    ? <>{s.kelas || '—'}{s.tim ? ` / ${s.tim}` : ''}</>
                                    : <span className="ad-pill ad-pill--info">{s.role}</span>}</small></td>
                                <td>
                                    {s.role !== 'mahasiswa' ? <small>—</small>
                                        : s.shift
                                            ? <span className="ad-pill ad-pill--ok">{s.shift === 'pagi' ? 'Pagi' : 'Malam'}</span>
                                            : <span className="ad-pill ad-pill--danger" title="Tap absen ditolak sampai shift diisi">Kosong</span>}
                                </td>
                                <td>
                                    {editId === s.id ? (
                                        <span className="ad-fp-assign">
                                            <input type="number" min={1} max={200} placeholder="auto" value={editVal} autoFocus
                                                onChange={(e) => setEditVal(e.target.value)}
                                                onKeyDown={(e) => { if (e.key === 'Enter') assign(s, editVal); if (e.key === 'Escape') setEditId(null); }} />
                                            <button type="button" className="ad-mesin-ic" title="Simpan" disabled={busy} onClick={() => assign(s, editVal)}><Check /></button>
                                            <button type="button" className="ad-mesin-ic" title="Batal" onClick={() => setEditId(null)}><X /></button>
                                        </span>
                                    ) : s.finger_id
                                        ? <span className="ad-fp-fid">#{s.finger_id}</span>
                                        : <span className="ad-pill ad-pill--pending">Belum assign</span>}
                                </td>
                                <td><span className={cn('ad-pill', STATUS_CLASS[s.status])}>{STATUS_LABEL[s.status]}</span></td>
                                <td><span className={cn('ad-fp-siap', SIAP_CLASS[s.status])}>{s.kesiapan}</span></td>
                                <td className="is-right">
                                    <div className="ad-mesin-actions" style={{ justifyContent: 'flex-end' }}>
                                        <a href={s.profile_url} className="ad-mesin-ic" title="Lihat profil"><Eye /></a>
                                        {props.can.update && (
                                            <button type="button" className="ad-mesin-ic" title="Assign / ubah Finger ID"
                                                onClick={() => { setEditId(s.id); setEditVal(s.finger_id ? String(s.finger_id) : ''); }}>
                                                <Pencil />
                                            </button>
                                        )}
                                        {props.can.update && s.can_clear && (
                                            <button type="button" className="ad-mesin-ic" title="Ulang enroll"
                                                onClick={() => setConfirm({
                                                    title: 'Ulang enroll sidik jari?',
                                                    body: `Slot #${s.finger_id} tetap sama. Status ${s.nama} di server jadi "Belum enroll" — mesin bisa scan 2× lagi.`,
                                                    label: 'Ya, Ulang Enroll',
                                                    action: () => post(`${props.urls.user_base}/${s.id}/clear-enroll`),
                                                })}>
                                                <RefreshCw />
                                            </button>
                                        )}
                                        {props.can.delete && s.finger_id && (
                                            <button type="button" className="ad-mesin-ic ad-mesin-ic--danger" title="Reset Finger ID + template"
                                                onClick={() => setConfirm({
                                                    title: 'Reset Finger ID?',
                                                    body: `Finger ID dan template ${s.nama} akan dikosongkan.`,
                                                    label: 'Ya, Reset', danger: true,
                                                    action: () => post(`${props.urls.user_base}/${s.id}/reset`, {}, 'delete'),
                                                })}>
                                                <Trash2 />
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {meta.last_page > 1 && (
                <div className="ad-mesin-pager">
                    <small>Menampilkan {meta.from}–{meta.to} dari {meta.total}</small>
                    <div className="ad-mesin-pager__btns">
                        <button type="button" disabled={meta.page <= 1} onClick={() => gotoPage(meta.page - 1)}>‹ Sebelumnya</button>
                        {Array.from({ length: meta.last_page }, (_, i) => i + 1)
                            .filter((p) => p === 1 || p === meta.last_page || Math.abs(p - meta.page) <= 1)
                            .reduce<(number | '…')[]>((acc, p, i, arr) => {
                                if (i > 0 && p - (arr[i - 1] as number) > 1) acc.push('…');
                                acc.push(p); return acc;
                            }, [])
                            .map((p, i) => p === '…'
                                ? <span key={`e${i}`} className="ad-mesin-pager__ellipsis">…</span>
                                : <button key={p} type="button" className={cn(meta.page === p && 'is-active')} onClick={() => gotoPage(p)}>{p}</button>)}
                        <button type="button" disabled={meta.page >= meta.last_page} onClick={() => gotoPage(meta.page + 1)}>Berikutnya ›</button>
                    </div>
                </div>
            )}
            {/* Kartu mobile */}
            <div className="ad-mesin-cards">
                {props.mahasiswa.data.length === 0 && <div className="ad-empty">Tidak ada pengguna sesuai filter.</div>}
                {props.mahasiswa.data.map((s) => (
                    <div key={s.id} className="ad-mesin-card">
                        <div className="ad-mesin-card__head">
                            <div>
                                <a href={s.profile_url} className="ad-link"><strong>{s.nama}</strong></a>
                                <small className="ad-mesin-sub">{s.nim} · {s.role === 'mahasiswa' ? (s.kelas || '—') : s.role}</small>
                            </div>
                            <span className={cn('ad-pill', STATUS_CLASS[s.status])}>{STATUS_LABEL[s.status]}</span>
                        </div>
                        <div className="ad-mesin-card__meta">
                            <span>Finger ID: <b>{s.finger_id ? `#${s.finger_id}` : '—'}</b></span>
                            <span>Shift: <b>{s.shift ?? 'kosong'}</b></span>
                            <span>{s.kesiapan}</span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ── Tab: Distribusi Mesin ── */
function DistribusiTab({ props, busy, post, setConfirm }: {
    props: FingerprintProps; busy: boolean;
    post: (url: string, data?: Record<string, unknown>) => void;
    setConfirm: (c: { title: string; body: string; label: string; danger?: boolean; action: () => void } | null) => void;
}) {
    return (
        <div className="ad-animate" style={{ display: 'grid', gap: 14 }}>
            <DistribusiCard props={props} busy={busy} post={post} setConfirm={setConfirm} expanded />
            <div className="ad-panel">
                <div className="ad-panel__head"><div><h3>Status Distribusi</h3><small>Kondisi burst dan antrean template.</small></div></div>
                <div className="ad-atur-opsstats" style={{ padding: 14 }}>
                    {[
                        { label: 'Mode saat ini', value: props.sync.mode === 'auto' ? 'Otomatis' : 'Manual', warn: props.sync.mode !== 'auto' },
                        { label: 'Distribusi manual aktif', value: props.sync.burst_active ? 'Ya' : 'Tidak', sub: props.sync.burst_until ? `s.d. ${props.sync.burst_until}` : '' },
                        { label: 'Sync hari ini', value: props.sync.stats.today },
                        { label: 'Pull hari ini', value: props.sync.stats.pull_today },
                        { label: 'Template terkirim', value: props.sync.stats.templates_today },
                    ].map((s) => (
                        <div key={s.label} className={cn('ad-atur-opsstat', s.warn && 'is-warn')}>
                            <span>{s.label}</span><strong>{s.value}</strong>{s.sub && <small>{s.sub}</small>}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ── Tab: Sinkronisasi ── */
function SinkronisasiTab({ props }: { props: FingerprintProps }) {
    return (
        <div className="ad-animate" style={{ display: 'grid', gap: 14 }}>
            <div className="ad-panel">
                <div className="ad-panel__head"><div><h3>Aktivitas Sync Mesin</h3><small>{props.charts.sync_bar.subtitle ?? 'Permintaan sync 7 hari terakhir'}.</small></div></div>
                <div className="ad-panel__body">
                    <LineChart height={230} data={{
                        labels: props.charts.sync_bar.labels,
                        series: [{ key: 'sync', label: 'Request sync', color: '#14b8a6', data: props.charts.sync_bar.values }],
                    }} />
                </div>
            </div>
            <div className="ad-panel">
                <div className="ad-panel__head"><div><h3>Enroll per Kelas</h3><small>{props.charts.kelas_bar.subtitle ?? 'Sudah enroll'}.</small></div></div>
                <div className="ad-panel__body">
                    {props.charts.kelas_bar.values.every((v) => v === 0) && <div className="ad-empty">Belum ada data enroll per kelas.</div>}
                    <ul className="ad-fp-kelas">
                        {props.charts.kelas_bar.labels.map((l, i) => {
                            const v = props.charts.kelas_bar.values[i] ?? 0;
                            const max = Math.max(1, ...props.charts.kelas_bar.values);
                            return (
                                <li key={l}>
                                    <span className="ad-fp-kelas__name">{l}</span>
                                    <span className="ad-fp-kelas__bar"><i style={{ width: `${(v / max) * 100}%`, background: props.charts.kelas_bar.colors[i] ?? '#0284c7' }} /></span>
                                    <b>{v}</b>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </div>
        </div>
    );
}

/* ── Tab: Log & Riwayat ── */
function LogTab({ props, nav }: { props: FingerprintProps; nav: (p: Record<string, unknown>, only?: string[]) => void }) {
    const f = props.filters;
    const [mesin, setMesin] = useState(f.sync_mesin);
    const meta = props.sync.log.meta;
    return (
        <div className="ad-panel ad-animate">
            <div className="ad-panel__head">
                <div>
                    <h3>Log Sync Template ({meta.total})</h3>
                    <small>Hari ini: {props.sync.stats.today} request · {props.sync.stats.pull_today} pull · {props.sync.stats.templates_today} template</small>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <select value={mesin} onChange={(e) => { setMesin(e.target.value); nav({ sync_mesin: e.target.value, sync_page: undefined }, ['sync', 'filters']); }}
                        style={{ padding: '7px 10px', borderRadius: 9, border: '1px solid var(--ad-border)', background: 'var(--ad-bg)', color: 'var(--ad-text)', font: 'inherit', fontSize: 12 }}>
                        <option value="">Semua mesin</option>
                        {props.options.mesin.map((m) => <option key={m.id_mesin} value={m.id_mesin}>{m.id_mesin}</option>)}
                    </select>
                </div>
            </div>
            <div className="ad-table-wrap">
                <table className="ad-table">
                    <thead>
                        <tr><th>Waktu</th><th>Mesin</th><th>Jenis</th><th>Delta</th><th>Template</th><th>Finger ID</th><th>IP</th><th>Hasil</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                        {props.sync.log.data.length === 0 && <tr><td colSpan={9} className="ad-empty">Belum ada aktivitas sync.</td></tr>}
                        {props.sync.log.data.map((r) => (
                            <tr key={r.id}>
                                <td><strong>{r.waktu}</strong><div className="ad-mesin-sub">{r.waktu_human}</div></td>
                                <td className="ad-mono">{r.id_mesin}</td>
                                <td><span className="ad-mesin-chip">{r.action}</span></td>
                                <td className="ad-mono"><small>{r.delta ?? '—'}</small></td>
                                <td><strong>{r.templates_count}</strong></td>
                                <td className="ad-mono"><small>{r.finger_ids || '—'}</small></td>
                                <td className="ad-mono"><small>{r.ip || '—'}</small></td>
                                <td>{r.success ? <span className="ad-pill ad-pill--ok">OK</span> : <span className="ad-pill ad-pill--danger">Gagal</span>}</td>
                                <td><small className="ad-mesin-sub">{r.message || '—'}</small></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {meta.last_page > 1 && (
                <div className="ad-mesin-pager">
                    <small>Halaman {meta.page} dari {meta.last_page}</small>
                    <div className="ad-mesin-pager__btns">
                        <button type="button" disabled={meta.page <= 1} onClick={() => nav({ sync_page: meta.page - 1 }, ['sync'])}>‹ Sebelumnya</button>
                        <button type="button" disabled={meta.page >= meta.last_page} onClick={() => nav({ sync_page: meta.page + 1 }, ['sync'])}>Berikutnya ›</button>
                    </div>
                </div>
            )}
        </div>
    );
}
