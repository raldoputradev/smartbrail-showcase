import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity, Check, Copy, Cpu, Eye, KeyRound, Pencil, Plus, Power, ScrollText,
    Search, Trash2, Wifi, WifiOff, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { MesinItem, MesinProps, SharedProps } from '@admin/types';

const CONN_STYLE: Record<string, { cls: string; icon: typeof Wifi }> = {
    online: { cls: 'ad-pill--ok', icon: Wifi },
    offline: { cls: 'ad-pill--pending', icon: WifiOff },
    inactive: { cls: 'ad-pill--muted', icon: Power },
};

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** Ilustrasi perangkat absensi (ESP32 + panel fingerprint) — inline SVG. */
function DeviceArt() {
    return (
        <svg className="ad-mesin-hero__art" viewBox="0 0 360 190" fill="none" aria-hidden="true">
            <path d="M0 155 Q 90 120 185 140 T 360 128" stroke="rgba(125,211,252,.4)" strokeWidth="1.6"/>
            <path d="M0 170 Q 100 140 200 155 T 360 145" stroke="rgba(125,211,252,.22)" strokeWidth="1.3"/>
            {/* Terminal utama */}
            <rect x="118" y="34" width="104" height="150" rx="14" fill="rgba(13,27,54,.55)" stroke="rgba(147,197,253,.6)" strokeWidth="1.6"/>
            <rect x="132" y="50" width="76" height="58" rx="8" fill="rgba(8,20,44,.9)" stroke="rgba(125,211,252,.5)" strokeWidth="1.2"/>
            {/* Sidik jari di layar */}
            <g stroke="#38bdf8" strokeWidth="2" strokeLinecap="round" opacity=".95">
                <path d="M156 88 a14 14 0 0 1 28 0"/>
                <path d="M160 88 a10 10 0 0 1 20 0 v10"/>
                <path d="M164 88 a6 6 0 0 1 12 0 v14"/>
                <path d="M170 88 v18"/>
                <path d="M156 88 v6 M184 88 v6"/>
            </g>
            {/* Tombol & indikator */}
            <circle cx="170" cy="128" r="9" fill="none" stroke="rgba(125,211,252,.6)" strokeWidth="1.6"/>
            <circle cx="170" cy="128" r="3.4" fill="#34d399"/>
            <rect x="140" y="148" width="60" height="8" rx="4" fill="rgba(125,211,252,.28)"/>
            <rect x="140" y="162" width="42" height="8" rx="4" fill="rgba(125,211,252,.16)"/>
            {/* Modul kecil kiri */}
            <rect x="52" y="80" width="52" height="82" rx="10" fill="rgba(13,27,54,.45)" stroke="rgba(147,197,253,.4)" strokeWidth="1.4"/>
            <rect x="63" y="92" width="30" height="24" rx="5" fill="rgba(8,20,44,.85)" stroke="rgba(125,211,252,.4)"/>
            <circle cx="78" cy="104" r="5" fill="none" stroke="#38bdf8" strokeWidth="1.6"/>
            <rect x="63" y="126" width="30" height="6" rx="3" fill="rgba(125,211,252,.22)"/>
            <rect x="63" y="138" width="22" height="6" rx="3" fill="rgba(125,211,252,.14)"/>
            {/* Modul kanan */}
            <rect x="248" y="66" width="60" height="96" rx="10" fill="rgba(13,27,54,.45)" stroke="rgba(147,197,253,.4)" strokeWidth="1.4"/>
            <circle cx="278" cy="92" r="13" fill="none" stroke="rgba(125,211,252,.5)" strokeWidth="1.6"/>
            <circle cx="278" cy="92" r="6" fill="none" stroke="#38bdf8" strokeWidth="1.6"/>
            <path d="M265 128 h26 M265 140 h26 M265 152 h18" stroke="rgba(125,211,252,.3)" strokeWidth="5" strokeLinecap="round"/>
            {/* Sinyal */}
            <g stroke="#34d399" strokeWidth="1.6" strokeLinecap="round" opacity=".8">
                <path d="M232 22 a10 10 0 0 1 10 10 M232 14 a18 18 0 0 1 18 18"/>
                <circle cx="230" cy="34" r="2.4" fill="#34d399" stroke="none"/>
            </g>
        </svg>
    );
}

type PanelState =
    | { mode: 'closed' }
    | { mode: 'create' }
    | { mode: 'edit'; mesin: MesinItem }
    | { mode: 'detail'; mesin: MesinItem };

export default function Mesin(props: MesinProps) {
    const { flash, errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [panel, setPanel] = useState<PanelState>({ mode: 'closed' });
    const [setup, setSetup] = useState(props.setup);
    const [confirm, setConfirm] = useState<null | {
        title: string; body: string; action: () => void; danger?: boolean; label: string;
    }>(null);
    const [busy, setBusy] = useState(false);
    const [filtering, setFiltering] = useState(false);
    const [q, setQ] = useState(props.filters.q);
    const [copied, setCopied] = useState<string | null>(null);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => setSetup(props.setup), [props.setup]);

    const applyFilter = (next: Partial<typeof props.filters> = {}) => {
        const params = { q, tipe: props.filters.tipe, status: props.filters.status, ...next };
        setFiltering(true);
        router.get(props.urls.index, params as Record<string, string>, {
            preserveState: true, replace: true, preserveScroll: true,
            onFinish: () => setFiltering(false),
        });
    };

    const onSearch = (val: string) => {
        setQ(val);
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => applyFilter({ q: val }), 450);
    };

    const visit = (method: 'post' | 'put' | 'delete', url: string, data?: Record<string, unknown>) => {
        setBusy(true);
        router[method](url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => { setPanel({ mode: 'closed' }); setConfirm(null); },
        });
    };

    const copy = async (text: string, label: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(label);
            setTimeout(() => setCopied((c) => (c === label ? null : c)), 1600);
        } catch { /* clipboard bisa ditolak */ }
    };

    const kpis = [
        { label: 'Total Mesin', value: props.stats.total, sub: 'Terdaftar di sistem', accent: '#0284c7', icon: Cpu, url: props.urls.index },
        { label: 'Online', value: props.stats.online, sub: `Heartbeat ≤ ${props.threshold_minutes} mnt`, accent: '#059669', icon: Wifi, url: `${props.urls.index}?status=online` },
        { label: 'Offline', value: props.stats.offline, sub: 'Aktif, belum heartbeat', accent: '#d97706', icon: WifiOff, url: `${props.urls.index}?status=offline` },
        { label: 'Nonaktif', value: props.stats.inactive, sub: 'Dinonaktifkan admin', accent: '#64748b', icon: Power, url: `${props.urls.index}?status=nonaktif` },
    ];

    const act = (m: MesinItem) => ({
        toggle: () => setConfirm({
            title: m.is_active ? `Nonaktifkan ${m.id_mesin}?` : `Aktifkan ${m.id_mesin}?`,
            body: m.is_active
                ? 'Mesin nonaktif tidak dihitung online dan heartbeat-nya diabaikan sampai diaktifkan kembali.'
                : 'Mesin akan kembali dihitung dalam monitoring koneksi.',
            label: m.is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
            action: () => visit('post', route('admin.mesin.toggle', m.id)),
        }),
        regenerate: () => setConfirm({
            title: `Generate ulang API key ${m.id_mesin}?`,
            body: 'API key lama langsung tidak berlaku. Key baru ditampilkan sekali — siapkan firmware untuk diperbarui.',
            label: 'Ya, Generate',
            action: () => visit('post', route('admin.mesin.regenerate', m.id)),
        }),
        destroy: () => setConfirm({
            title: `Hapus mesin ${m.id_mesin}?`,
            body: 'Mesin dihapus dari sistem. Riwayat log tap lama tetap tersimpan.',
            label: 'Ya, Hapus', danger: true,
            action: () => visit('delete', route('admin.mesin.destroy', m.id)),
        }),
    });

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Manajemen Mesin" subtitle="Registrasi ESP32, binding ruangan & status perangkat">
            <Head title="Manajemen Mesin" />

            {/* Hero biru sesuai referensi */}
            <div className="ad-mesin-hero ad-animate">
                <div className="ad-mesin-hero__text">
                    <h2>Kelola Mesin Laboratorium</h2>
                    <p>
                        Daftarkan dan pantau perangkat IoT absensi & akses ruangan. Status koneksi
                        dihitung dari heartbeat nyata (≤ {props.threshold_minutes} menit), bukan flag database.
                    </p>
                </div>
                <DeviceArt />
            </div>

            {/* Flash */}
            {flash.success && <div className="ad-mesin-flash ad-mesin-flash--ok"><Check /> {flash.success}</div>}
            {flash.error && <div className="ad-mesin-flash ad-mesin-flash--err"><X /> {flash.error}</div>}

            {/* KPI */}
            <div className="ad-kpi ad-mesin-kpi ad-animate">
                {kpis.map((k) => {
                    const Icon = k.icon;
                    return (
                        <a key={k.label} href={k.url} className="ad-kpi__item" style={{ ['--kpi-accent' as string]: k.accent }}>
                            <div className="ad-kpi__icon"><Icon /></div>
                            <div>
                                <span className="k">{k.label}</span>
                                <strong>{k.value.toLocaleString('id-ID')}</strong>
                                <small>{k.sub}</small>
                            </div>
                        </a>
                    );
                })}
            </div>

            {/* Daftar */}
            <div className="ad-panel ad-animate">
                <div className="ad-panel__head">
                    <div>
                        <h3>Daftar Mesin ({props.mesin.meta.total})</h3>
                        <small>Urut heartbeat terbaru · Online ≠ aktif di database</small>
                    </div>
                    <div className="ad-mesin-tools">
                        <Link href={props.urls.monitoring} className="ad-mesin-btn ad-mesin-btn--ghost">
                            <Activity /> Monitoring
                        </Link>
                        <a href={props.urls.log} className="ad-mesin-btn ad-mesin-btn--ghost">
                            <ScrollText /> Log Tap
                        </a>
                        {props.can.create && (
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setPanel({ mode: 'create' })}>
                                <Plus /> Daftar Mesin Baru
                            </button>
                        )}
                    </div>
                </div>
                <div className="ad-mesin-filters">
                    <div className="ad-mesin-search">
                        <Search />
                        <input
                            value={q} onChange={(e) => onSearch(e.target.value)}
                            placeholder="Cari ID / nama mesin…" aria-label="Cari mesin"
                        />
                    </div>
                    <select value={props.filters.tipe} onChange={(e) => applyFilter({ tipe: e.target.value })} aria-label="Filter tipe">
                        <option value="">Semua Tipe</option>
                        {Object.entries(props.type_options).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <select value={props.filters.status} onChange={(e) => applyFilter({ status: e.target.value })} aria-label="Filter status">
                        <option value="">Semua Status</option>
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                    {(props.filters.q || props.filters.tipe || props.filters.status) && (
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost ad-mesin-btn--sm" onClick={() => { setQ(''); applyFilter({ q: '', tipe: '', status: '' }); }}>
                            Reset
                        </button>
                    )}
                </div>

                <div className={cn('ad-mesin-tablewrap', filtering && 'is-loading')}>
                    {/* Tabel desktop */}
                    <table className="ad-table ad-mesin-table">
                        <thead>
                            <tr>
                                <th>Nama Mesin</th><th>Tipe</th><th>Ruangan</th>
                                <th>Status Koneksi</th><th>Tap Terakhir</th><th>Status</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {props.mesin.data.length === 0 && (
                                <tr><td colSpan={7} className="ad-empty">
                                    {props.filters.q || props.filters.tipe || props.filters.status
                                        ? 'Tidak ada mesin yang cocok dengan filter.'
                                        : 'Belum ada mesin terdaftar.'}
                                </td></tr>
                            )}
                            {props.mesin.data.map((m) => {
                                const conn = CONN_STYLE[m.conn];
                                const ConnIcon = conn.icon;
                                return (
                                    <tr key={m.id}>
                                        <td>
                                            <div className="ad-mesin-id">
                                                <span className={cn('ad-mesin-dot', `ad-mesin-dot--${m.conn}`)} />
                                                <div style={{ minWidth: 0 }}>
                                                    <strong>{m.nama_mesin}</strong>
                                                    <small>ID: {m.id_mesin}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span className="ad-mesin-chip">{m.tipe_label}</span></td>
                                        <td>{m.ruangan ?? <span className="ad-mesin-muted">—</span>}</td>
                                        <td>
                                            <span className={cn('ad-pill', conn.cls)}><ConnIcon style={{ width: 11, height: 11 }} /> {m.conn_label}</span>
                                            <small className="ad-mesin-sub" title={m.last_seen_exact ?? ''}>{m.last_seen_label}</small>
                                        </td>
                                        <td><small className="ad-mesin-sub" title={m.last_tap_exact ?? ''}>{m.last_tap_label}</small></td>
                                        <td>
                                            <span className={cn('ad-pill', m.is_active ? 'ad-pill--ok' : 'ad-pill--muted')}>
                                                {m.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="ad-mesin-actions">
                                                <button type="button" className="ad-mesin-ic" title="Detail" onClick={() => setPanel({ mode: 'detail', mesin: m })}><Eye /></button>
                                                {props.can.update && <button type="button" className="ad-mesin-ic" title="Edit" onClick={() => setPanel({ mode: 'edit', mesin: m })}><Pencil /></button>}
                                                {props.can.update && <button type="button" className="ad-mesin-ic" title="Regenerate API key" onClick={() => act(m).regenerate()}><KeyRound /></button>}
                                                {props.can.update && <button type="button" className="ad-mesin-ic" title={m.is_active ? 'Nonaktifkan' : 'Aktifkan'} onClick={() => act(m).toggle()}><Power /></button>}
                                                {props.can.delete && <button type="button" className="ad-mesin-ic ad-mesin-ic--danger" title="Hapus" onClick={() => act(m).destroy()}><Trash2 /></button>}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    {/* Kartu HP */}
                    <div className="ad-mesin-cards">
                        {props.mesin.data.map((m) => {
                            const conn = CONN_STYLE[m.conn];
                            return (
                                <div key={m.id} className="ad-mesin-card">
                                    <div className="ad-mesin-card__head">
                                        <div className="ad-mesin-id">
                                            <span className={cn('ad-mesin-dot', `ad-mesin-dot--${m.conn}`)} />
                                            <div style={{ minWidth: 0 }}>
                                                <strong>{m.nama_mesin}</strong>
                                                <small>ID: {m.id_mesin} · {m.tipe_label}</small>
                                            </div>
                                        </div>
                                        <span className={cn('ad-pill', conn.cls)}>{m.conn_label}</span>
                                    </div>
                                    <div className="ad-mesin-card__meta">
                                        <span>Ruangan: {m.ruangan ?? '—'}</span>
                                        <span>Heartbeat: {m.last_seen_label}</span>
                                        <span>Tap: {m.last_tap_label}</span>
                                        <span>Status: {m.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                    </div>
                                    <div className="ad-mesin-actions" style={{ marginTop: 10 }}>
                                        <button type="button" className="ad-mesin-ic" title="Detail" onClick={() => setPanel({ mode: 'detail', mesin: m })}><Eye /></button>
                                        {props.can.update && <button type="button" className="ad-mesin-ic" title="Edit" onClick={() => setPanel({ mode: 'edit', mesin: m })}><Pencil /></button>}
                                        {props.can.update && <button type="button" className="ad-mesin-ic" title="Regenerate API key" onClick={() => act(m).regenerate()}><KeyRound /></button>}
                                        {props.can.update && <button type="button" className="ad-mesin-ic" title={m.is_active ? 'Nonaktifkan' : 'Aktifkan'} onClick={() => act(m).toggle()}><Power /></button>}
                                        {props.can.delete && <button type="button" className="ad-mesin-ic ad-mesin-ic--danger" title="Hapus" onClick={() => act(m).destroy()}><Trash2 /></button>}
                                    </div>
                                </div>
                            );
                        })}
                        {props.mesin.data.length === 0 && <div className="ad-empty">Tidak ada mesin yang cocok.</div>}
                    </div>
                </div>

                {/* Pagination */}
                {props.mesin.meta.last_page > 1 && (
                    <div className="ad-mesin-pager">
                        <small>Menampilkan {props.mesin.meta.from}–{props.mesin.meta.to} dari {props.mesin.meta.total} mesin</small>
                        <div className="ad-mesin-pager__links">
                            {props.mesin.links.map((l, i) => l.url ? (
                                <Link
                                    key={i} href={l.url} preserveScroll
                                    className={cn('ad-mesin-page', l.active && 'is-active')}
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ) : (
                                <span key={i} className="ad-mesin-page is-disabled" dangerouslySetInnerHTML={{ __html: l.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Panel kanan */}
            {panel.mode !== 'closed' && (
                <>
                    <div className="ad-mesin-scrim" onClick={() => setPanel({ mode: 'closed' })} />
                    <MachinePanel
                        panel={panel} props={props} errors={errors}
                        busy={busy}
                        onClose={() => setPanel({ mode: 'closed' })}
                        onSubmit={(data) => {
                            if (panel.mode === 'create') visit('post', props.urls.store, data);
                            else if (panel.mode === 'edit') visit('put', route('admin.mesin.update', panel.mesin.id), data);
                        }}
                        onEdit={(m) => setPanel({ mode: 'edit', mesin: m })}
                        can={props.can}
                    />
                </>
            )}

            {/* Modal setup — API key sekali tampil */}
            {setup && (
                <SetupModal
                    setup={setup} apiBase={props.api_base} threshold={props.threshold_minutes}
                    copied={copied} copy={copy}
                    onClose={() => setSetup(null)}
                />
            )}

            {/* Konfirmasi aksi */}
            {confirm && (
                <div className="ad-mesin-scrim" style={{ zIndex: 95 }} onClick={() => setConfirm(null)}>
                    <div className="ad-mesin-confirm" role="alertdialog" aria-label={confirm.title} onClick={(e) => e.stopPropagation()}>
                        <h4>{confirm.title}</h4>
                        <p>{confirm.body}</p>
                        <div className="ad-mesin-confirm__actions">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setConfirm(null)}>Batal</button>
                            <button
                                type="button" disabled={busy}
                                className={cn('ad-mesin-btn', confirm.danger ? 'ad-mesin-btn--danger' : 'ad-mesin-btn--primary')}
                                onClick={confirm.action}
                            >{busy ? 'Memproses…' : confirm.label}</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}

/** Panel kanan — daftar baru / edit / detail. */
function MachinePanel({ panel, props, errors, busy, onClose, onSubmit, onEdit, can }: {
    panel: PanelState; props: MesinProps; errors: Record<string, string>;
    busy: boolean; onClose: () => void;
    onSubmit: (data: Record<string, unknown>) => void;
    onEdit: (m: MesinItem) => void;
    can: MesinProps['can'];
}) {
    const editing = panel.mode === 'edit' ? panel.mesin : null;
    const detail = panel.mode === 'detail' ? panel.mesin : null;
    const [idMesin, setIdMesin] = useState('');
    const [nama, setNama] = useState(editing?.nama_mesin ?? '');
    const [tipe, setTipe] = useState(editing?.tipe ?? 'absensi');
    const [tipeCustom, setTipeCustom] = useState('');
    const [aktif, setAktif] = useState(editing ? editing.is_active : true);
    const [ruanganId, setRuanganId] = useState<string>(editing?.ruangan_id ? String(editing.ruangan_id) : '');

    const tipeKnown = useMemo(() => Object.keys(props.type_options), [props.type_options]);
    useEffect(() => {
        if (editing && !tipeKnown.includes(editing.tipe)) setTipe('__custom'), setTipeCustom(editing.tipe);
    }, []);

    const err = (k: string) => errors[k] ? <small className="ad-mesin-err">{errors[k]}</small> : null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const data: Record<string, unknown> = {
            nama_mesin: nama.trim(),
            tipe: tipe === '__custom' ? '__custom' : tipe,
            tipe_custom: tipe === '__custom' ? tipeCustom.trim() : undefined,
            is_active: aktif ? 1 : 0,
            ruangan_id: ruanganId || null,
        };
        if (panel.mode === 'create') data.id_mesin = idMesin.trim().toUpperCase();
        onSubmit(data);
    };

    const conn = detail ? CONN_STYLE[detail.conn] : null;
    const title = panel.mode === 'create' ? 'Daftar Mesin Baru' : panel.mode === 'edit' ? `Edit ${editing?.id_mesin}` : (detail?.nama_mesin ?? '');

    return (
        <aside className="ad-mesin-panel" role="dialog" aria-label={title}>
            <div className="ad-mesin-panel__head">
                <div className="ad-mesin-panel__icon"><Cpu /></div>
                <div style={{ minWidth: 0 }}>
                    <h3>{title}</h3>
                    <small>
                        {panel.mode === 'create' && 'Tambahkan mesin/perangkat baru ke dalam sistem.'}
                        {panel.mode === 'edit' && 'Ubah nama, tipe, ruangan, dan status aktif.'}
                        {panel.mode === 'detail' && 'Detail perangkat dan status koneksi.'}
                    </small>
                </div>
                <button type="button" className="ad-icon-btn" onClick={onClose} aria-label="Tutup panel"><X /></button>
            </div>

            {detail && conn && (
                <div className="ad-mesin-panel__body">
                    <div className="ad-mesin-detail">
                        <div className="ad-mesin-detail__row"><span>ID Mesin</span><strong>{detail.id_mesin}</strong></div>
                        <div className="ad-mesin-detail__row"><span>Tipe</span><strong>{detail.tipe_label}</strong></div>
                        <div className="ad-mesin-detail__row"><span>Ruangan</span><strong>{detail.ruangan ?? '—'}</strong></div>
                        <div className="ad-mesin-detail__row">
                            <span>Koneksi</span>
                            <strong><span className={cn('ad-pill', conn.cls)}>{detail.conn_label}</span></strong>
                        </div>
                        <div className="ad-mesin-detail__row"><span>Heartbeat terakhir</span><strong>{detail.last_seen_label}</strong></div>
                        <div className="ad-mesin-detail__row"><span>Tap terakhir</span><strong>{detail.last_tap_label}</strong></div>
                        <div className="ad-mesin-detail__row"><span>Status admin</span><strong>{detail.is_active ? 'Aktif' : 'Nonaktif'}</strong></div>
                        <div className="ad-mesin-detail__row"><span>Terakhir diubah</span><strong>{detail.updated_label ?? '—'}</strong></div>
                    </div>
                    {can.update && (
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" style={{ marginTop: 14 }} onClick={() => onEdit(detail)}>
                            <Pencil /> Edit Mesin
                        </button>
                    )}
                </div>
            )}

            {panel.mode !== 'detail' && (
                <form className="ad-mesin-panel__body" onSubmit={submit}>
                    {panel.mode === 'create' && (
                        <div className="ad-mesin-field">
                            <label>ID Mesin <em>*</em></label>
                            <input
                                value={idMesin} onChange={(e) => setIdMesin(e.target.value.toUpperCase())}
                                placeholder="Contoh: M-ABS-001" required maxLength={100}
                                className="ad-mesin-mono" autoComplete="off" spellCheck={false}
                            />
                            <small>Wajib huruf besar · unik · huruf/angka/strip/underscore</small>
                            {err('id_mesin')}
                        </div>
                    )}
                    {panel.mode === 'edit' && (
                        <div className="ad-mesin-field">
                            <label>ID Mesin</label>
                            <input value={editing?.id_mesin} readOnly className="ad-mesin-mono" />
                            <small>ID tetap — riwayat log & firmware tidak putus.</small>
                        </div>
                    )}
                    <div className="ad-mesin-field">
                        <label>Nama Mesin</label>
                        <input value={nama} onChange={(e) => setNama(e.target.value)} placeholder="Contoh: Mesin Absensi Lab A1" maxLength={150} />
                        {err('nama_mesin')}
                    </div>
                    <div className="ad-mesin-field">
                        <label>Tipe Mesin <em>*</em></label>
                        <select value={tipe} onChange={(e) => setTipe(e.target.value)}>
                            {Object.entries(props.type_options).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            <option value="__custom">+ Tipe baru</option>
                        </select>
                        {err('tipe')}
                    </div>
                    {tipe === '__custom' && (
                        <div className="ad-mesin-field">
                            <label>Nama Tipe Baru</label>
                            <input value={tipeCustom} onChange={(e) => setTipeCustom(e.target.value)} placeholder="contoh: pintu" maxLength={50} />
                            {err('tipe_custom')}
                        </div>
                    )}
                    {props.ruangan_options.length > 0 && (
                        <div className="ad-mesin-field">
                            <label>Ruangan</label>
                            <select value={ruanganId} onChange={(e) => setRuanganId(e.target.value)}>
                                <option value="">Tidak terikat ruangan</option>
                                {props.ruangan_options.map((r) => <option key={r.id} value={r.id}>{r.nama}</option>)}
                            </select>
                            <small>Untuk mesin akses ruangan — menentukan ruangan yang diperiksa saat tap.</small>
                            {err('ruangan_id')}
                        </div>
                    )}
                    <label className="ad-mesin-check">
                        <input type="checkbox" checked={aktif} onChange={(e) => setAktif(e.target.checked)} />
                        Mesin aktif
                    </label>
                    <div className="ad-mesin-panel__foot">
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={onClose}>Batal</button>
                        <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}>
                            {busy ? 'Menyimpan…' : panel.mode === 'create' ? 'Simpan & Lanjut Setup' : 'Simpan'}
                        </button>
                    </div>
                </form>
            )}
        </aside>
    );
}

/** Modal sekali-tampil: API key + info firmware setelah create/regenerate. */
function SetupModal({ setup, apiBase, threshold, copied, copy, onClose }: {
    setup: NonNullable<MesinProps['setup']>; apiBase: string; threshold: number;
    copied: string | null; copy: (t: string, l: string) => void;
    onClose: () => void;
}) {
    const isRegen = setup.mode === 'regenerate';
    const curl = `curl -X POST "${apiBase}/heartbeat" -H "X-API-KEY: ${setup.key}"`;
    return (
        <div className="ad-mesin-scrim" style={{ zIndex: 96 }}>
            <div className="ad-mesin-setup" role="dialog" aria-modal="true" aria-label="Setup mesin">
                <button type="button" className="ad-icon-btn" style={{ position: 'absolute', top: 12, right: 12 }} onClick={onClose} aria-label="Tutup"><X /></button>
                <div className="ad-mesin-setup__ok">
                    <span className="ad-mesin-setup__okicon"><Check /></span>
                    <div>
                        <strong>{isRegen ? 'API key baru siap' : 'Mesin berhasil didaftarkan'}</strong>
                        <small>{setup.name} · {setup.id}</small>
                    </div>
                </div>

                <h4>Salin API key (sekali tampil)</h4>
                <p className="ad-mesin-setup__hint">Key hanya ditampilkan sekarang — sistem hanya menyimpan hash dan key tidak bisa dilihat ulang.</p>
                <div className="ad-mesin-copy">
                    <code>{setup.key}</code>
                    <button type="button" onClick={() => copy(setup.key, 'key')}>{copied === 'key' ? 'Tersalin ✓' : <><Copy /> Salin</>}</button>
                </div>
                <div className="ad-mesin-copy">
                    <code>X-API-KEY: {setup.key}</code>
                    <button type="button" onClick={() => copy(`X-API-KEY: ${setup.key}`, 'header')}>{copied === 'header' ? 'Tersalin ✓' : <><Copy /> Salin</>}</button>
                </div>

                <h4>Konfigurasi firmware ESP32</h4>
                <div className="ad-mesin-setup__grid">
                    <div className="ad-mesin-code">
                        <div className="ad-mesin-code__head"><span>Base URL API</span><button type="button" onClick={() => copy(apiBase, 'base')}>{copied === 'base' ? '✓' : <Copy />}</button></div>
                        <code>{apiBase}</code>
                    </div>
                    <div className="ad-mesin-code">
                        <div className="ad-mesin-code__head"><span>ID mesin</span><button type="button" onClick={() => copy(setup.id, 'id')}>{copied === 'id' ? '✓' : <Copy />}</button></div>
                        <code>{setup.id}</code>
                    </div>
                    <div className="ad-mesin-code ad-mesin-code--full">
                        <div className="ad-mesin-code__head"><span>Endpoint penting</span></div>
                        <pre>{`POST/GET  ${apiBase}/heartbeat
${setup.tipe === 'absensi' ? `POST      ${apiBase}/tap\n` : ''}GET       ${apiBase}/fingerprint/sync`}</pre>
                    </div>
                    <div className="ad-mesin-code ad-mesin-code--full">
                        <div className="ad-mesin-code__head"><span>Contoh heartbeat (curl)</span><button type="button" onClick={() => copy(curl, 'curl')}>{copied === 'curl' ? '✓' : <Copy />}</button></div>
                        <pre>{curl}</pre>
                    </div>
                </div>

                <div className="ad-mesin-setup__warn">
                    Jangan bagikan key di chat publik — simpan di secrets firmware/EEPROM. Setelah firmware terpasang,
                    mesin harus heartbeat ≤ {threshold} menit agar status koneksi menjadi Online.
                </div>

                <div className="ad-mesin-confirm__actions">
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={onClose}>Selesai, saya sudah simpan</button>
                </div>
            </div>
        </div>
    );
}
