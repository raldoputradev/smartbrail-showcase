import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2, Clock, Cpu, DoorOpen, Link2, MapPin, Pencil,
    Plus, Search, Settings2, Unlink, Users as UsersIcon, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { SharedProps } from '@admin/types';
import type { RgRow, RuanganProps } from '@admin/types';

const HARI: { v: number; l: string }[] = [
    { v: 1, l: 'Senin' }, { v: 2, l: 'Selasa' }, { v: 3, l: 'Rabu' },
    { v: 4, l: 'Kamis' }, { v: 5, l: 'Jumat' }, { v: 6, l: 'Sabtu' }, { v: 7, l: 'Minggu' },
];

type FormState = {
    id: number | null;
    kode: string; nama: string; lokasi: string;
    kapasitas: string; fasilitas: string;
    foto: File | null; foto_preview: string | null; hapus_foto: boolean;
    aktif: boolean;
    hari: number[]; jam_buka: string; jam_tutup: string;
    min_advance_days: string; max_advance_days: string; max_duration_minutes: string;
    max_pending_per_team: string; max_peserta: string;
};

const kosong: FormState = {
    id: null, kode: '', nama: '', lokasi: '', kapasitas: '', fasilitas: '',
    foto: null, foto_preview: null, hapus_foto: false, aktif: true,
    hari: [1, 2, 3, 4, 5], jam_buka: '07:00', jam_tutup: '21:00',
    min_advance_days: '1', max_advance_days: '2', max_duration_minutes: '120',
    max_pending_per_team: '1', max_peserta: '',
};

function dariRow(r: RgRow): FormState {
    return {
        id: r.id, kode: r.kode, nama: r.nama, lokasi: r.lokasi ?? '',
        kapasitas: r.kapasitas?.toString() ?? '', fasilitas: r.fasilitas.join(', '),
        foto: null, foto_preview: r.foto_url, hapus_foto: false, aktif: r.aktif,
        hari: r.aturan.hari.length ? r.aturan.hari : [1, 2, 3, 4, 5],
        jam_buka: r.aturan.jam_buka, jam_tutup: r.aturan.jam_tutup,
        min_advance_days: String(r.aturan.min_advance_days ?? 1),
        max_advance_days: String(r.aturan.max_advance_days),
        max_duration_minutes: String(r.aturan.max_duration_minutes),
        max_pending_per_team: String(r.aturan.max_pending_per_team),
        max_peserta: r.aturan.max_peserta?.toString() ?? '',
    };
}

export default function Ruangan(props: RuanganProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState<'daftar' | 'binding' | 'akses'>('daftar');
    const [busy, setBusy] = useState(false);
    const [form, setForm] = useState<FormState | null>(null);
    const [aksesRoom, setAksesRoom] = useState<number | null>(null);
    const [bindVal, setBindVal] = useState<Record<number, string>>({});

    const f = props.filters;
    const meta = props.ruangan.meta;

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params },
            { preserveState: true, preserveScroll: true, replace: true });

    const post = (url: string, data: Record<string, unknown>) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) setForm(null);
            },
        });
    };

    const submitForm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form) return;
        const payload: Record<string, unknown> = {
            nama: form.nama, lokasi: form.lokasi || undefined,
            kapasitas: form.kapasitas || undefined,
            fasilitas: form.fasilitas || undefined,
            aktif: form.aktif ? 1 : 0,
            hari: form.hari, jam_buka: form.jam_buka, jam_tutup: form.jam_tutup,
            min_advance_days: form.min_advance_days || undefined,
            max_advance_days: form.max_advance_days || undefined,
            max_duration_minutes: form.max_duration_minutes || undefined,
            max_pending_per_team: form.max_pending_per_team || undefined,
            max_peserta: form.max_peserta || undefined,
        };
        if (form.foto) payload.foto = form.foto;
        if (form.hapus_foto) payload.hapus_foto = 1;
        if (form.id === null) {
            payload.kode = form.kode;
            post(props.urls.store, payload);
        } else {
            post(`${props.urls.update_base}${form.id}`, { ...payload, _method: 'put' });
        }
    };

    const bind = (mesinId: number) => {
        setBusy(true);
        router.post(`${props.urls.bind_base}${mesinId}/ruangan`.replace('//', '/'),
            { ruangan_id: bindVal[mesinId] || null } as never,
            { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const aksesTarget = useMemo(
        () => props.ruangan.data.find((r) => r.id === aksesRoom) ?? props.ruangan.data[0] ?? null,
        [aksesRoom, props.ruangan.data],
    );
    const [aksesForm, setAksesForm] = useState<FormState | null>(null);
    const openAkses = (r: RgRow) => { setAksesRoom(r.id); setAksesForm(dariRow(r)); };
    const aksesShown = aksesForm && aksesTarget && aksesForm.id === aksesTarget.id ? aksesForm : (aksesTarget ? dariRow(aksesTarget) : null);

    const s = props.stats;
    const kpis = [
        { label: 'Total Ruangan', value: s.total, sub: 'Terdaftar di sistem', icon: <DoorOpen size={18} />, accent: '#0284c7' },
        { label: 'Aktif', value: s.aktif, sub: 'Dapat dipesan', icon: <CheckCircle2 size={18} />, accent: '#059669' },
        { label: 'Nonaktif', value: s.nonaktif, sub: 'Ditutup sementara', icon: <Clock size={18} />, accent: '#d97706' },
        { label: 'Terikat Perangkat', value: s.terikat, sub: `${s.terikat_pct}% dari total`, icon: <Link2 size={18} />, accent: '#7c3aed' },
    ];

    const pages = useMemo(() => {
        const out: number[] = [];
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === 1 || i === meta.last_page || Math.abs(i - meta.page) <= 1) out.push(i);
        }
        return out;
    }, [meta]);

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Manajemen Ruangan"
            subtitle="Kelola ruangan laboratorium dan aturan aksesnya">
            <Head title="Manajemen Ruangan" />

            <div className="ad-log-hero">
                <div>
                    <h1>Manajemen Ruangan</h1>
                    <p>Daftar ruangan, binding perangkat akses, dan pengaturan jam operasional pemesanan ruangan PBL.</p>
                    <div className="ad-log-hero__chips">
                        <span><DoorOpen size={11} /> {s.total} ruangan</span>
                        <span><Link2 size={11} /> {s.terikat} terikat perangkat</span>
                        <span><Settings2 size={11} /> Aturan per ruangan</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <svg viewBox="0 0 200 120" fill="none">
                        <rect x="18" y="22" width="70" height="86" rx="8" fill="rgba(255,255,255,.14)" stroke="rgba(255,255,255,.4)" />
                        <rect x="30" y="34" width="46" height="30" rx="4" fill="rgba(255,255,255,.2)" />
                        <circle cx="78" cy="66" r="3.5" fill="#fff" />
                        <rect x="104" y="36" width="78" height="52" rx="8" fill="rgba(255,255,255,.1)" stroke="rgba(255,255,255,.35)" />
                        <rect x="112" y="46" width="62" height="6" rx="3" fill="rgba(255,255,255,.4)" />
                        <rect x="112" y="58" width="44" height="6" rx="3" fill="rgba(255,255,255,.25)" />
                        <circle cx="150" cy="76" r="9" fill="none" stroke="#fff" strokeWidth="2.5" />
                        <path d="M146 76l3 3 5-6" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </div>
            </div>

            {flash.status && (
                <div className="ad-mesin-flash ad-mesin-flash--ok"><CheckCircle2 /> {flash.status}</div>
            )}
            {Object.keys(errors).length > 0 && (
                <div className="ad-mesin-flash ad-mesin-flash--err"><X /> {Object.values(errors)[0] as string}</div>
            )}

            <div className="ad-log-kpis">
                {kpis.map((k) => (
                    <div className="ad-log-kpi" key={k.label}>
                        <span className="ad-log-kpi__ic" style={{ background: `${k.accent}1c`, color: k.accent }}>{k.icon}</span>
                        <div><small>{k.label}</small><strong>{k.value}</strong><em>{k.sub}</em></div>
                    </div>
                ))}
            </div>

            <div className="ad-rg-tabs">
                {([['daftar', 'Daftar Ruangan'], ['binding', 'Binding Perangkat'], ['akses', 'Pengaturan Akses']] as const).map(([k, l]) => (
                    <button key={k} className={cn('ad-rg-tab', tab === k && 'is-active')} onClick={() => setTab(k)}>{l}</button>
                ))}
            </div>

            {tab === 'daftar' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Daftar Ruangan</h3>
                            <small>{meta.total} ruangan terdaftar — kelola profil, fasilitas, dan status</small>
                        </div>
                        {props.can.create && (
                            <button className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setForm({ ...kosong })}>
                                <Plus size={14} /> Tambah Ruangan
                            </button>
                        )}
                    </div>
                    <div className="ad-panel__body">
                        <form className="ad-mesin-filters" onSubmit={(e) => {
                            e.preventDefault();
                            const fd = new FormData(e.currentTarget);
                            nav({ q: fd.get('q') || undefined, status: fd.get('status') || undefined, page: undefined });
                        }}>
                            <label className="ad-mesin-search"><Search />
                                <input name="q" defaultValue={f.q} placeholder="Cari nama / kode / lokasi…" />
                            </label>
                            <select name="status" defaultValue={f.status}>
                                <option value="">Semua Status</option>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                            <button className="ad-mesin-btn" type="submit">Terapkan</button>
                        </form>

                        <div className="ad-mesin-tablewrap">
                            <div className="ad-table-wrap ad-rg-freewrap">
                                <table className="ad-table ad-rg-table">
                                    <thead><tr>
                                        <th>Ruangan</th><th>Lokasi</th><th>Kapasitas</th><th>Fasilitas</th>
                                        <th>Perangkat</th><th>Jadwal Operasional</th><th>Status</th>
                                        {props.can.update && <th className="is-right">Aksi</th>}
                                    </tr></thead>
                                    <tbody>
                                        {props.ruangan.data.length === 0 && (
                                            <tr><td colSpan={8} className="ad-empty">Belum ada ruangan yang cocok.</td></tr>
                                        )}
                                        {props.ruangan.data.map((r) => (
                                            <tr key={r.id}>
                                                <td>
                                                    <div className="ad-feed-user">
                                                        <span className="ad-rg-thumb">
                                                            {r.foto_url
                                                                ? <img src={r.foto_url} alt={r.nama} />
                                                                : <DoorOpen size={15} />}
                                                        </span>
                                                        <div><strong>{r.nama}</strong><small className="ad-mesin-mono">{r.kode}</small></div>
                                                    </div>
                                                </td>
                                                <td>{r.lokasi ? <span className="ad-rg-loc"><MapPin size={11} /> {r.lokasi}</span> : '—'}</td>
                                                <td>{r.kapasitas ? `${r.kapasitas} org` : '—'}</td>
                                                <td>
                                                    <div className="ad-rg-chips">
                                                        {r.fasilitas.length === 0 && <span className="ad-mesin-muted">—</span>}
                                                        {r.fasilitas.slice(0, 3).map((fa) => <span key={fa} className="ad-rg-chip">{fa}</span>)}
                                                        {r.fasilitas.length > 3 && <span className="ad-rg-chip ad-rg-chip--more">+{r.fasilitas.length - 3}</span>}
                                                    </div>
                                                </td>
                                                <td>
                                                    {r.perangkat_total > 0
                                                        ? <span className="ad-pill ad-pill--info"><Cpu size={10} /> {r.perangkat_total} terikat</span>
                                                        : <span className="ad-mesin-muted">Belum ada</span>}
                                                </td>
                                                <td><span className="ad-rg-jadwal"><Clock size={11} /> {r.jadwal_label}</span></td>
                                                <td>
                                                    <span className={cn('ad-pill', r.aktif ? 'ad-pill--ok' : 'ad-pill--off')}>
                                                        {r.aktif ? 'Aktif' : 'Nonaktif'}
                                                    </span>
                                                    {r.last_event && <small className="ad-rg-event">{r.last_event.label}</small>}
                                                </td>
                                                {props.can.update && (
                                                    <td className="is-right">
                                                        <div className="ad-mesin-actions" style={{ justifyContent: 'flex-end' }}>
                                                            <button className="ad-mesin-ic" title="Edit ruangan" onClick={() => setForm(dariRow(r))}>
                                                                <Pencil />
                                                            </button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="ad-mesin-cards">
                                {props.ruangan.data.map((r) => (
                                    <div className="ad-mesin-card" key={r.id}>
                                        <div className="ad-mesin-card__head">
                                            <div className="ad-feed-user">
                                                <span className="ad-rg-thumb">
                                                    {r.foto_url ? <img src={r.foto_url} alt={r.nama} /> : <DoorOpen size={15} />}
                                                </span>
                                                <div><strong>{r.nama}</strong><small className="ad-mesin-mono">{r.kode}</small></div>
                                            </div>
                                            <span className={cn('ad-pill', r.aktif ? 'ad-pill--ok' : 'ad-pill--off')}>
                                                {r.aktif ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <div className="ad-mesin-card__meta">
                                            {r.lokasi && <span className="ad-mesin-sub"><MapPin size={10} /> {r.lokasi}</span>}
                                            <span className="ad-mesin-sub"><UsersIcon size={10} /> {r.kapasitas ?? '—'} orang · <Cpu size={10} /> {r.perangkat_total} perangkat</span>
                                            <span className="ad-mesin-sub"><Clock size={10} /> {r.jadwal_label}</span>
                                        </div>
                                        {props.can.update && (
                                            <button className="ad-mesin-btn ad-mesin-btn--sm" onClick={() => setForm(dariRow(r))}>
                                                <Pencil size={13} /> Edit
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="ad-mesin-pager">
                            <small>Menampilkan {meta.from}–{meta.to} dari {meta.total} ruangan</small>
                            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                                <select value={f.per_page} onChange={(e) => nav({ per_page: e.target.value, page: undefined })}
                                    className="ad-mesin-filters__perpage" aria-label="Per halaman">
                                    {[5, 10, 25].map((n) => <option key={n} value={n}>{n} / hal</option>)}
                                </select>
                                <div className="ad-mesin-pager__links">
                                    <button className={cn('ad-mesin-page', meta.page <= 1 && 'is-disabled')}
                                        disabled={meta.page <= 1} onClick={() => nav({ page: meta.page - 1 })}>‹</button>
                                    {pages.map((p) => (
                                        <button key={p} className={cn('ad-mesin-page', p === meta.page && 'is-active')}
                                            onClick={() => nav({ page: p })}>{p}</button>
                                    ))}
                                    <button className={cn('ad-mesin-page', meta.page >= meta.last_page && 'is-disabled')}
                                        disabled={meta.page >= meta.last_page} onClick={() => nav({ page: meta.page + 1 })}>›</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'binding' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Binding Perangkat ke Ruangan</h3>
                            <small>Ikatkan mesin IoT ke ruangan agar tap dan event tercatat per ruangan</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        {!props.is_admin && (
                            <div className="ad-rg-note">Binding perangkat memerlukan kewenangan admin — Anda dapat melihat daftar tetapi tidak mengubahnya.</div>
                        )}
                        <div className="ad-rg-bindlist">
                            {props.mesin_list.length === 0 && <div className="ad-empty">Belum ada perangkat terdaftar.</div>}
                            {props.mesin_list.map((m) => (
                                <div className="ad-rg-bindrow" key={m.id}>
                                    <div className="ad-feed-user">
                                        <span className={cn('ad-mesin-dot', m.is_active ? 'ad-mesin-dot--online' : 'ad-mesin-dot--offline')} />
                                        <div>
                                            <strong>{m.nama}</strong>
                                            <small className="ad-mesin-mono">{m.id_mesin} · {m.tipe}</small>
                                        </div>
                                    </div>
                                    <div className="ad-rg-bindrow__ctl">
                                        <select disabled={!props.is_admin || busy}
                                            value={bindVal[m.id] ?? (m.ruangan_id?.toString() ?? '')}
                                            onChange={(e) => setBindVal({ ...bindVal, [m.id]: e.target.value })}>
                                            <option value="">— Tidak terikat —</option>
                                            {props.ruangan_options.map((r) => (
                                                <option key={r.id} value={r.id}>{r.nama} ({r.kode})</option>
                                            ))}
                                        </select>
                                        {props.is_admin && (
                                            <button className="ad-mesin-btn ad-mesin-btn--sm" disabled={busy}
                                                onClick={() => bind(m.id)}>
                                                {(bindVal[m.id] ?? String(m.ruangan_id ?? '')) ? <Link2 size={13} /> : <Unlink size={13} />}
                                                Simpan
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {tab === 'akses' && aksesShown && (
                <div className="ad-rg-aksesgrid">
                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Pilih Ruangan</h3><small>Aturan akses berlaku per ruangan</small></div>
                        </div>
                        <div className="ad-panel__body ad-rg-roomlist">
                            {props.ruangan.data.map((r) => (
                                <button key={r.id} className={cn('ad-rg-roomitem', aksesTarget?.id === r.id && 'is-active')}
                                    onClick={() => openAkses(r)}>
                                    <strong>{r.nama}</strong>
                                    <small>{r.jadwal_label}</small>
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Pengaturan Akses — {aksesTarget?.nama}</h3><small>Hari, jam operasional, dan batas pemesanan</small></div>
                        </div>
                        <div className="ad-panel__body">
                            {!props.can.update && <div className="ad-rg-note">Anda tidak memiliki izin mengubah pengaturan akses.</div>}
                            <form onSubmit={(e) => {
                                e.preventDefault();
                                if (!aksesShown) return;
                                post(`${props.urls.update_base}${aksesShown.id}`, {
                                    _method: 'put',
                                    nama: aksesShown.nama, lokasi: aksesShown.lokasi || undefined,
                                    aktif: aksesShown.aktif ? 1 : 0,
                                    hari: aksesShown.hari, jam_buka: aksesShown.jam_buka, jam_tutup: aksesShown.jam_tutup,
                                    min_advance_days: aksesShown.min_advance_days || undefined,
                                    max_advance_days: aksesShown.max_advance_days || undefined,
                                    max_duration_minutes: aksesShown.max_duration_minutes || undefined,
                                    max_pending_per_team: aksesShown.max_pending_per_team || undefined,
                                    max_peserta: aksesShown.max_peserta || undefined,
                                });
                            }} className="ad-rg-aksesform">
                                <div className="ad-mesin-field">
                                    <label>Hari Operasional</label>
                                    <div className="ad-rg-harigrid">
                                        {HARI.map((h) => (
                                            <label key={h.v} className={cn('ad-rg-hari', aksesShown.hari.includes(h.v) && 'is-on')}>
                                                <input type="checkbox" disabled={!props.can.update}
                                                    checked={aksesShown.hari.includes(h.v)}
                                                    onChange={(e) => setAksesForm({
                                                        ...aksesShown,
                                                        hari: e.target.checked
                                                            ? [...aksesShown.hari, h.v].sort()
                                                            : aksesShown.hari.filter((x) => x !== h.v),
                                                    })} />
                                                {h.l}
                                            </label>
                                        ))}
                                    </div>
                                    <small>Kosongkan semua untuk mengizinkan setiap hari.</small>
                                </div>
                                <div className="ad-rg-grid2">
                                    <div className="ad-mesin-field"><label>Jam Buka</label>
                                        <input type="time" value={aksesShown.jam_buka} disabled={!props.can.update}
                                            onChange={(e) => setAksesForm({ ...aksesShown, jam_buka: e.target.value })} /></div>
                                    <div className="ad-mesin-field"><label>Jam Tutup</label>
                                        <input type="time" value={aksesShown.jam_tutup} disabled={!props.can.update}
                                            onChange={(e) => setAksesForm({ ...aksesShown, jam_tutup: e.target.value })} /></div>
                                </div>
                                <div className="ad-rg-grid2">
                                    <div className="ad-mesin-field"><label>Min. Pesan di Muka (hari)</label>
                                        <input type="number" min={0} max={90} value={aksesShown.min_advance_days} disabled={!props.can.update}
                                            onChange={(e) => setAksesForm({ ...aksesShown, min_advance_days: e.target.value })} /></div>
                                    <div className="ad-mesin-field"><label>Maks. Pesan di Muka (hari)</label>
                                        <input type="number" min={0} max={90} value={aksesShown.max_advance_days} disabled={!props.can.update}
                                            onChange={(e) => setAksesForm({ ...aksesShown, max_advance_days: e.target.value })} /></div>
                                </div>
                                <div className="ad-rg-grid2">
                                    <div className="ad-mesin-field"><label>Maks. Durasi (menit)</label>
                                        <input type="number" min={15} max={720} step={15} value={aksesShown.max_duration_minutes} disabled={!props.can.update}
                                            onChange={(e) => setAksesForm({ ...aksesShown, max_duration_minutes: e.target.value })} /></div>
                                </div>
                                <div className="ad-rg-grid2">
                                    <div className="ad-mesin-field"><label>Maks. Pending per Tim</label>
                                        <input type="number" min={1} max={10} value={aksesShown.max_pending_per_team} disabled={!props.can.update}
                                            onChange={(e) => setAksesForm({ ...aksesShown, max_pending_per_team: e.target.value })} /></div>
                                    <div className="ad-mesin-field"><label>Maks. Peserta</label>
                                        <input type="number" min={1} max={1000} value={aksesShown.max_peserta} disabled={!props.can.update}
                                            placeholder="Ikut kapasitas ruangan"
                                            onChange={(e) => setAksesForm({ ...aksesShown, max_peserta: e.target.value })} /></div>
                                </div>
                                {props.can.update && (
                                    <div className="ad-mesin-panel__foot">
                                        <button className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}>
                                            {busy ? 'Menyimpan…' : 'Simpan Pengaturan'}
                                        </button>
                                    </div>
                                )}
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {form && (
                <>
                    <div className="ad-mesin-scrim" onClick={() => setForm(null)} />
                    <aside className="ad-mesin-panel" role="dialog" aria-modal="true">
                        <div className="ad-mesin-panel__head">
                            <span className="ad-mesin-panel__icon"><DoorOpen /></span>
                            <div>
                                <h3>{form.id === null ? 'Tambah Ruangan' : `Edit ${form.nama}`}</h3>
                                <small>{form.id === null ? 'Daftarkan ruangan baru beserta aturan aksesnya' : `Kode ${form.kode}`}</small>
                            </div>
                            <button className="ad-icon-btn" onClick={() => setForm(null)} aria-label="Tutup"><X /></button>
                        </div>
                        <form className="ad-mesin-panel__body" onSubmit={submitForm}>
                            {form.id === null && (
                                <div className="ad-mesin-field">
                                    <label>Kode Ruangan <em>*</em></label>
                                    <input value={form.kode} required maxLength={40} className="ad-mesin-mono"
                                        placeholder="LAB-301" onChange={(e) => setForm({ ...form, kode: e.target.value.toUpperCase() })} />
                                    {errors.kode && <span className="ad-mesin-err">{errors.kode}</span>}
                                </div>
                            )}
                            <div className="ad-mesin-field">
                                <label>Nama Ruangan <em>*</em></label>
                                <input value={form.nama} required maxLength={120} placeholder="Ruang Meeting A"
                                    onChange={(e) => setForm({ ...form, nama: e.target.value })} />
                                {errors.nama && <span className="ad-mesin-err">{errors.nama}</span>}
                            </div>
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field"><label>Lokasi</label>
                                    <input value={form.lokasi} maxLength={160} placeholder="Gedung B Lt. 2"
                                        onChange={(e) => setForm({ ...form, lokasi: e.target.value })} /></div>
                                <div className="ad-mesin-field"><label>Kapasitas (orang)</label>
                                    <input type="number" min={1} max={1000} value={form.kapasitas}
                                        onChange={(e) => setForm({ ...form, kapasitas: e.target.value })} /></div>
                            </div>
                            <div className="ad-mesin-field">
                                <label>Fasilitas</label>
                                <textarea rows={2} value={form.fasilitas} placeholder="Proyektor, AC, Whiteboard (pisah dengan koma)"
                                    onChange={(e) => setForm({ ...form, fasilitas: e.target.value })} />
                                <small>Pisahkan dengan koma — tampil sebagai chip di daftar.</small>
                            </div>
                            <div className="ad-mesin-field">
                                <label>Foto Ruangan</label>
                                {form.foto_preview && !form.hapus_foto && (
                                    <div className="ad-rg-fotoprev">
                                        <img src={form.foto_preview} alt="Pratinjau" />
                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--sm ad-mesin-btn--danger"
                                            onClick={() => setForm({ ...form, hapus_foto: true, foto: null, foto_preview: null })}>
                                            Hapus Foto
                                        </button>
                                    </div>
                                )}
                                {(!form.foto_preview || form.hapus_foto) && (
                                    <input type="file" accept="image/jpeg,image/png,image/webp"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0] ?? null;
                                            setForm({ ...form, foto: file, hapus_foto: false, foto_preview: file ? URL.createObjectURL(file) : null });
                                        }} />
                                )}
                                {form.hapus_foto && <small>Foto lama akan dihapus saat disimpan.</small>}
                                {errors.foto && <span className="ad-mesin-err">{errors.foto}</span>}
                            </div>
                            <label className="ad-mesin-check">
                                <input type="checkbox" checked={form.aktif} onChange={(e) => setForm({ ...form, aktif: e.target.checked })} />
                                Ruangan aktif (dapat dipesan)
                            </label>
                            <div className="ad-mesin-field">
                                <label>Hari Operasional</label>
                                <div className="ad-rg-harigrid">
                                    {HARI.map((h) => (
                                        <label key={h.v} className={cn('ad-rg-hari', form.hari.includes(h.v) && 'is-on')}>
                                            <input type="checkbox" checked={form.hari.includes(h.v)}
                                                onChange={(e) => setForm({
                                                    ...form,
                                                    hari: e.target.checked ? [...form.hari, h.v].sort() : form.hari.filter((x) => x !== h.v),
                                                })} />
                                            {h.l}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field"><label>Jam Buka</label>
                                    <input type="time" value={form.jam_buka} onChange={(e) => setForm({ ...form, jam_buka: e.target.value })} /></div>
                                <div className="ad-mesin-field"><label>Jam Tutup</label>
                                    <input type="time" value={form.jam_tutup} onChange={(e) => setForm({ ...form, jam_tutup: e.target.value })} /></div>
                            </div>
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field"><label>Min. Pesan di Muka (hari)</label>
                                    <input type="number" min={0} max={90} value={form.min_advance_days}
                                        onChange={(e) => setForm({ ...form, min_advance_days: e.target.value })} /></div>
                                <div className="ad-mesin-field"><label>Maks. Pesan di Muka (hari)</label>
                                    <input type="number" min={0} max={90} value={form.max_advance_days}
                                        onChange={(e) => setForm({ ...form, max_advance_days: e.target.value })} /></div>
                            </div>
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field"><label>Maks. Durasi (menit)</label>
                                    <input type="number" min={15} max={720} step={15} value={form.max_duration_minutes}
                                        onChange={(e) => setForm({ ...form, max_duration_minutes: e.target.value })} /></div>
                            </div>
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field"><label>Maks. Pending/Tim</label>
                                    <input type="number" min={1} max={10} value={form.max_pending_per_team}
                                        onChange={(e) => setForm({ ...form, max_pending_per_team: e.target.value })} /></div>
                                <div className="ad-mesin-field"><label>Maks. Peserta</label>
                                    <input type="number" min={1} max={1000} value={form.max_peserta} placeholder="Ikut kapasitas"
                                        onChange={(e) => setForm({ ...form, max_peserta: e.target.value })} /></div>
                            </div>
                            <div className="ad-mesin-panel__foot">
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setForm(null)}>Batal</button>
                                <button className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}>
                                    {busy ? 'Menyimpan…' : form.id === null ? 'Simpan Ruangan' : 'Simpan Perubahan'}
                                </button>
                            </div>
                        </form>
                    </aside>
                </>
            )}
        </AdminShell>
    );
}
