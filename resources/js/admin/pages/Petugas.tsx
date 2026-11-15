import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Brush, CheckCircle2, Clock, DoorOpen, History, KeyRound,
    Pencil, Plus, Search, Users as UsersIcon, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { SharedProps, PetugasProps, PtgRow } from '@admin/types';

const HARI: { v: number; l: string }[] = [
    { v: 1, l: 'Sen' }, { v: 2, l: 'Sel' }, { v: 3, l: 'Rab' },
    { v: 4, l: 'Kam' }, { v: 5, l: 'Jum' }, { v: 6, l: 'Sab' }, { v: 7, l: 'Min' },
];

type FormState = {
    id: number | null;
    nama: string; id_internal: string;
    tipe_kredensial: string; kredensial: string;
    status: string;
    berlaku_mulai: string; berlaku_selesai: string;
    jadwal_ruangan_id: string; jadwal_hari: number[];
    jadwal_jam_mulai: string; jadwal_jam_selesai: string;
    jadwal: { ruangan_id: string; hari: number[]; jam_mulai: string; jam_selesai: string }[];
};

const kosong: FormState = {
    id: null, nama: '', id_internal: '', tipe_kredensial: 'pin', kredensial: '',
    status: 'aktif', berlaku_mulai: '', berlaku_selesai: '',
    jadwal_ruangan_id: '', jadwal_hari: [1, 2, 3, 4, 5],
    jadwal_jam_mulai: '17:00', jadwal_jam_selesai: '19:00', jadwal: [],
};

function dariRow(p: PtgRow): FormState {
    return {
        id: p.id, nama: p.nama, id_internal: p.id_internal,
        tipe_kredensial: p.tipe_kredensial, kredensial: '',
        status: p.status,
        berlaku_mulai: '', berlaku_selesai: '',
        jadwal_ruangan_id: '', jadwal_hari: [],
        jadwal_jam_mulai: '', jadwal_jam_selesai: '',
        jadwal: p.jadwal.map((j) => ({
            ruangan_id: j.ruangan_id?.toString() ?? '', hari: j.hari,
            jam_mulai: j.jam_mulai ?? '', jam_selesai: j.jam_selesai ?? '',
        })),
    };
}

function initials(nama: string) {
    return nama.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

export default function Petugas(props: PetugasProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [busy, setBusy] = useState(false);
    const [form, setForm] = useState<FormState | null>(null);

    const tab = props.tab;
    const q = props.filters.q;

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { q: q || undefined, tab: tab !== 'daftar' ? tab : undefined, ...params },
            { preserveState: true, preserveScroll: true, replace: true });

    const post = (url: string, data: Record<string, unknown>) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) setForm(null);
            },
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form) return;
        if (form.id === null) {
            post(props.urls.store, {
                nama: form.nama, id_internal: form.id_internal,
                tipe_kredensial: form.tipe_kredensial, kredensial: form.kredensial,
                berlaku_mulai: form.berlaku_mulai || undefined,
                berlaku_selesai: form.berlaku_selesai || undefined,
                jadwal_ruangan_id: form.jadwal_ruangan_id,
                jadwal_hari: form.jadwal_hari,
                jadwal_jam_mulai: form.jadwal_jam_mulai,
                jadwal_jam_selesai: form.jadwal_jam_selesai,
            });
        } else {
            post(`${props.urls.update_base}${form.id}`, {
                _method: 'put',
                nama: form.nama, status: form.status,
                berlaku_mulai: form.berlaku_mulai || undefined,
                berlaku_selesai: form.berlaku_selesai || undefined,
                kredensial: form.kredensial || undefined,
                jadwal: form.jadwal
                    .filter((j) => j.ruangan_id)
                    .map((j) => ({
                        ruangan_id: Number(j.ruangan_id), hari: j.hari,
                        jam_mulai: j.jam_mulai, jam_selesai: j.jam_selesai,
                    })),
            });
        }
    };

    const s = props.stats;
    const kpis = [
        { label: 'Total Petugas', value: s.total, sub: 'Identitas mesin non-portal', icon: <Brush size={18} />, accent: '#0284c7' },
        { label: 'Aktif', value: s.aktif, sub: 'Kredensial berlaku', icon: <CheckCircle2 size={18} />, accent: '#059669' },
        { label: 'Nonaktif', value: s.nonaktif, sub: 'Akses dinonaktifkan', icon: <X size={18} />, accent: '#d97706' },
        { label: 'Akses Bulan Ini', value: s.akses_bulan_ini, sub: 'Event ruangan tercatat', icon: <DoorOpen size={18} />, accent: '#7c3aed' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Petugas Ruangan"
            subtitle="Identitas petugas kebersihan/service pada mesin — bukan pengguna portal">
            <Head title="Petugas Ruangan" />

            <div className="ad-log-hero">
                <div>
                    <h1>Petugas Ruangan</h1>
                    <p>Petugas kebersihan &amp; service terdaftar sebagai identitas mesin (PIN/RFID/fingerprint) untuk akses ruangan — tanpa akun portal.</p>
                    <div className="ad-log-hero__chips">
                        <span><Brush size={11} /> {s.total} petugas</span>
                        <span><KeyRound size={11} /> PIN · RFID · Fingerprint</span>
                        <span><Clock size={11} /> Jadwal per ruangan</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <svg viewBox="0 0 200 120" fill="none">
                        <circle cx="60" cy="42" r="18" fill="rgba(255,255,255,.2)" />
                        <circle cx="60" cy="36" r="8" fill="#fff" opacity=".9" />
                        <path d="M46 58c3-8 25-8 28 0" stroke="#fff" strokeWidth="5" strokeLinecap="round" />
                        <rect x="100" y="26" width="76" height="66" rx="8" fill="rgba(255,255,255,.12)" stroke="rgba(255,255,255,.35)" />
                        <rect x="112" y="38" width="52" height="8" rx="4" fill="rgba(255,255,255,.4)" />
                        <rect x="112" y="52" width="36" height="6" rx="3" fill="rgba(255,255,255,.25)" />
                        <rect x="112" y="66" width="44" height="14" rx="7" fill="rgba(5,150,105,.85)" />
                    </svg>
                </div>
            </div>

            {flash.status && <div className="ad-mesin-flash ad-mesin-flash--ok"><CheckCircle2 /> {flash.status}</div>}
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
                {([['daftar', 'Daftar Petugas'], ['akses', 'Akses Ruangan'], ['riwayat', 'Riwayat Akses']] as const).map(([k, l]) => (
                    <button key={k} className={cn('ad-rg-tab', tab === k && 'is-active')}
                        onClick={() => nav({ tab: k === 'daftar' ? undefined : k })}>{l}</button>
                ))}
            </div>

            {tab === 'daftar' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Daftar Petugas</h3>
                            <small>{props.petugas.length} petugas terdaftar — kredensial tersimpan ter-hash</small>
                        </div>
                        {props.can.create && (
                            <button className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setForm({ ...kosong })}>
                                <Plus size={14} /> Tambah Petugas
                            </button>
                        )}
                    </div>
                    <div className="ad-panel__body">
                        <form className="ad-mesin-filters" onSubmit={(e) => {
                            e.preventDefault();
                            const fd = new FormData(e.currentTarget);
                            nav({ q: fd.get('q') || undefined });
                        }}>
                            <label className="ad-mesin-search"><Search />
                                <input name="q" defaultValue={q} placeholder="Cari nama / ID internal…" />
                            </label>
                            <button className="ad-mesin-btn" type="submit">Cari</button>
                        </form>

                        <div className="ad-mesin-tablewrap">
                            <div className="ad-table-wrap ad-rg-freewrap">
                                <table className="ad-table">
                                    <thead><tr>
                                        <th>Petugas</th><th>ID Internal</th><th>Kredensial</th>
                                        <th>Akses Ruangan</th><th>Jadwal</th><th>Berlaku</th><th>Status</th>
                                        {props.can.update && <th className="is-right">Aksi</th>}
                                    </tr></thead>
                                    <tbody>
                                        {props.petugas.length === 0 && (
                                            <tr><td colSpan={8} className="ad-empty">Belum ada petugas terdaftar.</td></tr>
                                        )}
                                        {props.petugas.map((p) => (
                                            <tr key={p.id}>
                                                <td>
                                                    <div className="ad-feed-user">
                                                        <span className="ad-pgj-ava">{initials(p.nama)}</span>
                                                        <strong>{p.nama}</strong>
                                                    </div>
                                                </td>
                                                <td><span className="ad-mesin-mono">{p.id_internal}</span></td>
                                                <td><span className="ad-pill ad-pill--info"><KeyRound size={10} /> {p.kredensial_label}</span></td>
                                                <td>
                                                    <div className="ad-rg-chips">
                                                        {p.ruangan_akses.length === 0 && <span className="ad-mesin-muted">—</span>}
                                                        {p.ruangan_akses.slice(0, 2).map((r) => <span key={r} className="ad-rg-chip">{r}</span>)}
                                                        {p.ruangan_akses.length > 2 && <span className="ad-rg-chip ad-rg-chip--more">+{p.ruangan_akses.length - 2}</span>}
                                                    </div>
                                                </td>
                                                <td>
                                                    {p.jadwal.slice(0, 1).map((j, i) => (
                                                        <span key={i} className="ad-rg-jadwal"><Clock size={11} /> {j.hari_label} · {j.jam_label}</span>
                                                    ))}
                                                    {p.jadwal.length > 1 && <small className="ad-mesin-muted"> +{p.jadwal.length - 1} jadwal</small>}
                                                    {p.jadwal.length === 0 && '—'}
                                                </td>
                                                <td>
                                                    {p.berlaku_mulai || p.berlaku_selesai
                                                        ? `${p.berlaku_mulai ?? '…'} – ${p.berlaku_selesai ?? '…'}`
                                                        : <span className="ad-mesin-muted">Tanpa batas</span>}
                                                </td>
                                                <td>
                                                    <span className={cn('ad-pill', p.status === 'aktif' ? 'ad-pill--ok' : 'ad-pill--off')}>
                                                        {p.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                                                    </span>
                                                </td>
                                                {props.can.update && (
                                                    <td className="is-right">
                                                        <div className="ad-mesin-actions" style={{ justifyContent: 'flex-end' }}>
                                                            <button className="ad-mesin-ic" title="Edit petugas" onClick={() => setForm(dariRow(p))}>
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
                                {props.petugas.map((p) => (
                                    <div className="ad-mesin-card" key={p.id}>
                                        <div className="ad-mesin-card__head">
                                            <div className="ad-feed-user">
                                                <span className="ad-pgj-ava">{initials(p.nama)}</span>
                                                <div><strong>{p.nama}</strong><small className="ad-mesin-mono">{p.id_internal}</small></div>
                                            </div>
                                            <span className={cn('ad-pill', p.status === 'aktif' ? 'ad-pill--ok' : 'ad-pill--off')}>
                                                {p.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <div className="ad-mesin-card__meta">
                                            <span className="ad-mesin-sub"><KeyRound size={10} /> {p.kredensial_label}</span>
                                            <span className="ad-mesin-sub"><DoorOpen size={10} /> {p.ruangan_akses.join(', ') || '—'}</span>
                                            {p.jadwal[0] && <span className="ad-mesin-sub"><Clock size={10} /> {p.jadwal[0].hari_label} · {p.jadwal[0].jam_label}</span>}
                                        </div>
                                        {props.can.update && (
                                            <button className="ad-mesin-btn ad-mesin-btn--sm" onClick={() => setForm(dariRow(p))}>
                                                <Pencil size={13} /> Edit
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'akses' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Matriks Akses Ruangan</h3>
                            <small>Ruangan yang dapat diakses tiap petugas berdasarkan jadwal</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <div className="ad-table-wrap ad-rg-freewrap">
                            <table className="ad-table">
                                <thead><tr>
                                    <th>Petugas</th><th>ID Internal</th><th>Ruangan</th><th>Hari</th><th>Jam</th><th>Status</th>
                                </tr></thead>
                                <tbody>
                                    {props.akses.length === 0 && (
                                        <tr><td colSpan={6} className="ad-empty">Belum ada akses ruangan terjadwal.</td></tr>
                                    )}
                                    {props.akses.map((a, i) => (
                                        <tr key={i}>
                                            <td><strong>{a.petugas}</strong></td>
                                            <td><span className="ad-mesin-mono">{a.id_internal}</span></td>
                                            <td>{a.ruangan}</td>
                                            <td>{a.hari_label}</td>
                                            <td>{a.jam_label}</td>
                                            <td>
                                                <span className={cn('ad-pill', a.status === 'aktif' ? 'ad-pill--ok' : 'ad-pill--off')}>
                                                    {a.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'riwayat' && (
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Riwayat Akses Ruangan</h3>
                            <small>Event akses terbaru dari perangkat ruangan</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <div className="ad-pgj-riwayat">
                            {props.riwayat.length === 0 && <div className="ad-empty">Belum ada event akses tercatat.</div>}
                            {props.riwayat.map((r, i) => (
                                <div className="ad-pgj-riv" key={i}>
                                    <span className={cn('ad-dot', r.hasil === 'ok' || r.hasil === 'berhasil' ? 'ad-dot--ok' : r.hasil ? 'ad-dot--warn' : 'ad-dot--off')} />
                                    <div>
                                        <strong>{r.nama} — {r.jenis_label}</strong>
                                        <small>{r.ruangan} · {r.waktu}</small>
                                        {r.keterangan !== '—' && <small>{r.keterangan}</small>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {form && (
                <>
                    <div className="ad-mesin-scrim" onClick={() => setForm(null)} />
                    <aside className="ad-mesin-panel" role="dialog" aria-modal="true">
                        <div className="ad-mesin-panel__head">
                            <span className="ad-mesin-panel__icon"><Brush /></span>
                            <div>
                                <h3>{form.id === null ? 'Tambah Petugas' : `Edit ${form.nama}`}</h3>
                                <small>{form.id === null ? 'Daftarkan identitas akses mesin baru' : `ID ${form.id_internal}`}</small>
                            </div>
                            <button className="ad-icon-btn" onClick={() => setForm(null)} aria-label="Tutup"><X /></button>
                        </div>
                        <form className="ad-mesin-panel__body" onSubmit={submit}>
                            <div className="ad-mesin-field">
                                <label>Nama Petugas <em>*</em></label>
                                <input value={form.nama} required maxLength={120} placeholder="Nama lengkap petugas"
                                    onChange={(e) => setForm({ ...form, nama: e.target.value })} />
                                {errors.nama && <span className="ad-mesin-err">{errors.nama}</span>}
                            </div>
                            {form.id === null && (
                                <>
                                    <div className="ad-mesin-field">
                                        <label>ID Internal <em>*</em></label>
                                        <input value={form.id_internal} required maxLength={60} className="ad-mesin-mono" placeholder="CS-001"
                                            onChange={(e) => setForm({ ...form, id_internal: e.target.value.toUpperCase() })} />
                                        {errors.id_internal && <span className="ad-mesin-err">{errors.id_internal}</span>}
                                    </div>
                                    <div className="ad-rg-grid2">
                                        <div className="ad-mesin-field">
                                            <label>Tipe Kredensial <em>*</em></label>
                                            <select value={form.tipe_kredensial}
                                                onChange={(e) => setForm({ ...form, tipe_kredensial: e.target.value })}>
                                                <option value="pin">PIN</option>
                                                <option value="rfid">RFID</option>
                                                <option value="fingerprint">Fingerprint</option>
                                            </select>
                                        </div>
                                        <div className="ad-mesin-field">
                                            <label>Kredensial <em>*</em></label>
                                            <input value={form.kredensial} required maxLength={128}
                                                placeholder={form.tipe_kredensial === 'pin' ? 'PIN 4–8 digit' : form.tipe_kredensial === 'rfid' ? 'UID kartu RFID' : 'Finger ID di mesin'}
                                                onChange={(e) => setForm({ ...form, kredensial: e.target.value })} />
                                            {errors.kredensial && <span className="ad-mesin-err">{errors.kredensial}</span>}
                                        </div>
                                    </div>
                                </>
                            )}
                            {form.id !== null && (
                                <>
                                    <div className="ad-mesin-field">
                                        <label>Status <em>*</em></label>
                                        <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                                            <option value="aktif">Aktif</option>
                                            <option value="nonaktif">Nonaktif</option>
                                        </select>
                                    </div>
                                    <div className="ad-mesin-field">
                                        <label>Reset Kredensial (opsional)</label>
                                        <input value={form.kredensial} maxLength={128}
                                            placeholder={`Kosongkan bila tidak diganti — tipe ${form.tipe_kredensial.toUpperCase()}`}
                                            onChange={(e) => setForm({ ...form, kredensial: e.target.value })} />
                                        <small>Kredensial tersimpan ter-hash; nilai lama tidak bisa ditampilkan.</small>
                                        {errors.kredensial && <span className="ad-mesin-err">{errors.kredensial}</span>}
                                    </div>
                                </>
                            )}
                            <div className="ad-rg-grid2">
                                <div className="ad-mesin-field"><label>Berlaku Mulai</label>
                                    <input type="date" value={form.berlaku_mulai}
                                        onChange={(e) => setForm({ ...form, berlaku_mulai: e.target.value })} /></div>
                                <div className="ad-mesin-field"><label>Berlaku Selesai</label>
                                    <input type="date" value={form.berlaku_selesai}
                                        onChange={(e) => setForm({ ...form, berlaku_selesai: e.target.value })} /></div>
                            </div>

                            {form.id === null && (
                                <>
                                    <div className="ad-mesin-field">
                                        <label>Ruangan Bertugas <em>*</em></label>
                                        <select value={form.jadwal_ruangan_id} required
                                            onChange={(e) => setForm({ ...form, jadwal_ruangan_id: e.target.value })}>
                                            <option value="">— Pilih ruangan —</option>
                                            {props.ruangan_options.map((r) => (
                                                <option key={r.id} value={r.id}>{r.nama} ({r.kode})</option>
                                            ))}
                                        </select>
                                        {errors.jadwal_ruangan_id && <span className="ad-mesin-err">{errors.jadwal_ruangan_id}</span>}
                                    </div>
                                    <div className="ad-mesin-field">
                                        <label>Hari Bertugas</label>
                                        <div className="ad-rg-harigrid">
                                            {HARI.map((h) => (
                                                <label key={h.v} className={cn('ad-rg-hari', form.jadwal_hari.includes(h.v) && 'is-on')}>
                                                    <input type="checkbox" checked={form.jadwal_hari.includes(h.v)}
                                                        onChange={(e) => setForm({
                                                            ...form,
                                                            jadwal_hari: e.target.checked
                                                                ? [...form.jadwal_hari, h.v].sort()
                                                                : form.jadwal_hari.filter((x) => x !== h.v),
                                                        })} />
                                                    {h.l}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="ad-rg-grid2">
                                        <div className="ad-mesin-field"><label>Jam Mulai <em>*</em></label>
                                            <input type="time" required value={form.jadwal_jam_mulai}
                                                onChange={(e) => setForm({ ...form, jadwal_jam_mulai: e.target.value })} /></div>
                                        <div className="ad-mesin-field"><label>Jam Selesai <em>*</em></label>
                                            <input type="time" required value={form.jadwal_jam_selesai}
                                                onChange={(e) => setForm({ ...form, jadwal_jam_selesai: e.target.value })} /></div>
                                    </div>
                                    {errors.jadwal_jam_selesai && <span className="ad-mesin-err">{errors.jadwal_jam_selesai}</span>}
                                </>
                            )}
                            {form.id !== null && (
                                <div className="ad-mesin-field">
                                    <label>Jadwal Ruangan</label>
                                    <div className="ad-rg-jadwallist">
                                        {form.jadwal.map((j, i) => (
                                            <div className="ad-rg-jadwalrow" key={i}>
                                                <select value={j.ruangan_id} required
                                                    onChange={(e) => {
                                                        const nj = [...form.jadwal];
                                                        nj[i] = { ...j, ruangan_id: e.target.value };
                                                        setForm({ ...form, jadwal: nj });
                                                    }}>
                                                    <option value="">— Ruangan —</option>
                                                    {props.ruangan_options.map((r) => (
                                                        <option key={r.id} value={r.id}>{r.nama}</option>
                                                    ))}
                                                </select>
                                                <input type="time" value={j.jam_mulai} required
                                                    onChange={(e) => {
                                                        const nj = [...form.jadwal];
                                                        nj[i] = { ...j, jam_mulai: e.target.value };
                                                        setForm({ ...form, jadwal: nj });
                                                    }} />
                                                <input type="time" value={j.jam_selesai} required
                                                    onChange={(e) => {
                                                        const nj = [...form.jadwal];
                                                        nj[i] = { ...j, jam_selesai: e.target.value };
                                                        setForm({ ...form, jadwal: nj });
                                                    }} />
                                                <button type="button" className="ad-mesin-ic ad-mesin-ic--danger" title="Hapus jadwal"
                                                    onClick={() => setForm({ ...form, jadwal: form.jadwal.filter((_, x) => x !== i) })}>
                                                    <X />
                                                </button>
                                            </div>
                                        ))}
                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--sm"
                                            onClick={() => setForm({
                                                ...form,
                                                jadwal: [...form.jadwal, { ruangan_id: '', hari: [1, 2, 3, 4, 5], jam_mulai: '17:00', jam_selesai: '19:00' }],
                                            })}>
                                            <Plus size={13} /> Tambah Jadwal
                                        </button>
                                    </div>
                                </div>
                            )}

                            <div className="ad-mesin-panel__foot">
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setForm(null)}>Batal</button>
                                <button className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}>
                                    {busy ? 'Menyimpan…' : form.id === null ? 'Simpan Petugas' : 'Simpan Perubahan'}
                                </button>
                            </div>
                        </form>
                    </aside>
                </>
            )}
        </AdminShell>
    );
}
