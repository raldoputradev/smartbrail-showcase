import { useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Boxes, ClipboardList, Crown, Eye, GraduationCap, ImagePlus, LayoutGrid,
    List, Plus, Search, ShieldCheck, Star, TrendingUp, UserMinus, UserPlus, Users, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { ActivityTimeline, ActivityTitle, MemberProfilePopup } from '@admin/components/PblShared';
import type { DosenPblProps, PblProjectCard } from '@admin/types';

const STATUS_OPTS = ['Perencanaan', 'Berjalan', 'Evaluasi', 'Selesai'];

function initials(nama: string | null | undefined) {
    return (nama ?? '?').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

function Avatar({ nama, foto }: { nama: string | null; foto: string | null }) {
    return foto
        ? <img className="ad-pbl-ava" src={foto} alt={nama ?? ''} title={nama ?? ''} />
        : <span className="ad-pbl-ava ad-pbl-ava--ini" title={nama ?? ''}>{initials(nama)}</span>;
}

function ProjectImage({ p, large }: { p: PblProjectCard; large?: boolean }) {
    return (
        <div className={large ? 'ad-pbl-img ad-pbl-img--lg' : 'ad-pbl-img'}>
            {p.foto_url
                ? <img src={p.foto_url} alt={p.nama} />
                : <div className="ad-pbl-img__ph"><Boxes size={large ? 44 : 30} /></div>}
            <span className="ad-pbl-img__kode">{p.kode}</span>
        </div>
    );
}

function ProgressBar({ value }: { value: number }) {
    return (
        <div className="ad-pbl-prog">
            <div className="ad-pbl-prog__row"><span>Progres Proyek</span><strong>{value}%</strong></div>
            <div className="ad-pbl-prog__track"><span style={{ width: `${value}%` }} /></div>
        </div>
    );
}

/** Modal kelola tampilan proyek: foto, deskripsi, status, progress. */
function MediaModal({ p, mediaUrl, onClose }: { p: PblProjectCard; mediaUrl: string; onClose: () => void }) {
    const [deskripsi, setDeskripsi] = useState(p.deskripsi ?? '');
    const [progress, setProgress] = useState<number>(p.progress ?? 0);
    const [status, setStatus] = useState(p.status_label && STATUS_OPTS.includes(p.status_label) ? p.status_label : 'Berjalan');
    const [foto, setFoto] = useState<File | null>(null);
    const [hapusFoto, setHapusFoto] = useState(false);
    const [busy, setBusy] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const preview = foto ? URL.createObjectURL(foto) : (hapusFoto ? null : p.foto_url);
    const url = mediaUrl.replace('/pbl/0/', `/pbl/${p.id}/`);

    const submit = () => {
        setBusy(true);
        router.post(url, {
            foto, deskripsi, progress, status,
            hapus_foto: hapusFoto ? 1 : 0,
        } as never, { forceFormData: true, preserveScroll: true, onFinish: () => { setBusy(false); onClose(); } });
    };

    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-pbl-modal">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <h3 style={{ margin: '0 0 2px' }}>Tampilan Proyek</h3>
                <p style={{ margin: '0 0 14px', color: 'var(--ad-muted)', fontSize: 12 }}>{p.kode} · {p.nama} — foto tampil juga di sisi mahasiswa</p>
                <label className="ad-pbl-upload" onClick={() => fileRef.current?.click()}>
                    {preview
                        ? <img src={preview} alt="preview" />
                        : <div className="ad-pbl-upload__ph"><ImagePlus size={26} /><span>Klik untuk pilih foto proyek</span><small>JPG/PNG/WebP, maks 2 MB</small></div>}
                    <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" hidden
                        onChange={(e) => { setFoto(e.target.files?.[0] ?? null); setHapusFoto(false); }} />
                </label>
                {(p.foto_url || foto) && (
                    <label className="ad-pbl-flag">
                        <input type="checkbox" checked={hapusFoto} onChange={(e) => { setHapusFoto(e.target.checked); if (e.target.checked) setFoto(null); }} />
                        Hapus foto saat ini
                    </label>
                )}
                <label className="ad-pbl-field">
                    <span>Deskripsi singkat</span>
                    <textarea rows={3} maxLength={500} value={deskripsi} placeholder="Gambaran proyek untuk kartu tim…"
                        onChange={(e) => setDeskripsi(e.target.value)} />
                </label>
                <div className="ad-pbl-grid2">
                    <label className="ad-pbl-field">
                        <span>Status proyek</span>
                        <select value={status} onChange={(e) => setStatus(e.target.value)}>
                            {STATUS_OPTS.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </label>
                    <label className="ad-pbl-field">
                        <span>Progres — {progress}%</span>
                        <input type="range" min={0} max={100} value={progress} onChange={(e) => setProgress(Number(e.target.value))} />
                    </label>
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 14 }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={onClose}>Batal</button>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy} onClick={submit}>
                        {busy ? 'Menyimpan…' : 'Simpan tampilan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

const ROLE_LABEL: Record<string, string> = { ketua: 'Ketua Tim', wakil: 'Wakil Ketua', anggota: 'Anggota' };

interface MemberRoutes { anggota: string; ketua: string; wakil: string; hapus: string; aktivitas: string }
interface Candidate { id: number; nama: string; nim: string | null; kelas: string | null }

const urlFor = (tpl: string, pblId: number, userId?: number) =>
    tpl.replace('/pbl/0/', `/pbl/${pblId}/`).replace('/members/0', `/members/${userId ?? 0}`);

/** Modal detail proyek — info lengkap + seluruh anggota tim + kelola tim (manpro). */
function DetailModal({ p, routes, candidates, onClose, onEdit }: {
    p: PblProjectCard; routes: MemberRoutes; candidates: Candidate[];
    onClose: () => void; onEdit: (p: PblProjectCard) => void;
}) {
    const [pick, setPick] = useState('');
    const [busy, setBusy] = useState(false);
    const [profilId, setProfilId] = useState<number | null>(null);
    const members = [...p.members].sort((a, b) => {
        const order = (r: string) => (r === 'ketua' ? 0 : r === 'wakil' ? 1 : 2);
        return order(a.role) - order(b.role) || (a.nama ?? '').localeCompare(b.nama ?? '');
    });
    const memberIds = new Set(p.members.map((m) => m.id));
    const options = candidates.filter((c) => !memberIds.has(c.id));

    const act = (url: string, data: Record<string, unknown> = {}, method: 'post' | 'delete' = 'post') => {
        setBusy(true);
        const fn = method === 'delete' ? router.delete : router.post;
        fn(url, data as never, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-pbl-detail">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <div className="ad-pbl-detail__hero">
                    {p.foto_url
                        ? <img src={p.foto_url} alt={p.nama} />
                        : <div className="ad-pbl-img__ph"><Boxes size={40} /></div>}
                    <div className="ad-pbl-detail__heroOv">
                        <span className="ad-pbl-img__kode" style={{ position: 'static' }}>{p.kode}</span>
                        <h3>{p.nama}</h3>
                        <div className="ad-pbl-detail__badges">
                            <span className={`ad-pbl-badge ad-pbl-badge--${p.status_label === 'Berjalan' ? 'run' : p.status_label === 'Evaluasi' ? 'eval' : p.status_label === 'Selesai' ? 'done' : 'plan'}`}>{p.status_label}</span>
                            {p.my_role && <span className="ad-pbl-badge ad-pbl-badge--role"><Crown size={11} /> {p.my_role === 'co_manpro' ? 'Co-Manpro Anda' : 'Manpro Anda'}</span>}
                        </div>
                    </div>
                </div>
                <div className="ad-pbl-detail__body">
                    {p.deskripsi && <p className="ad-pbl-detail__desc">{p.deskripsi}</p>}
                    <div className="ad-pbl-detail__facts">
                        <div><span>Manpro</span>
                            {p.manpro_id
                                ? <button type="button" className="ad-pbl-detail__who-name" title="Lihat profil" onClick={() => setProfilId(p.manpro_id!)}><strong>{p.manpro ?? '—'}</strong></button>
                                : <strong>{p.manpro ?? '—'}</strong>}
                        </div>
                        <div><span>Co-Manpro</span>
                            {p.co_manpro_id
                                ? <button type="button" className="ad-pbl-detail__who-name" title="Lihat profil" onClick={() => setProfilId(p.co_manpro_id!)}><strong>{p.co_manpro ?? '—'}</strong></button>
                                : <strong>{p.co_manpro ?? '—'}</strong>}
                        </div>
                        <div><span>Sesi</span><strong>{p.shift_sesi ?? '—'}</strong></div>
                        <div><span>Anggota</span><strong>{p.jumlah_anggota} mahasiswa</strong></div>
                    </div>
                    {p.progress !== null && <ProgressBar value={p.progress} />}
                    <div className="ad-pbl-detail__sec"><Users size={14} /> Anggota Tim ({members.length})</div>
                    {members.length === 0 ? (
                        <div className="ad-empty" style={{ padding: '14px' }}>Belum ada anggota di tim ini.</div>
                    ) : (
                        <div className="ad-pbl-detail__list">
                            {members.map((m) => (
                                <div key={m.id} className="ad-pbl-detail__row">
                                    <button type="button" className="ad-pbl-detail__who-btn" title="Lihat profil" onClick={() => setProfilId(m.id)}>
                                        <Avatar nama={m.nama} foto={m.foto} />
                                    </button>
                                    <div className="ad-pbl-detail__who">
                                        <button type="button" className="ad-pbl-detail__who-name" title="Lihat profil" onClick={() => setProfilId(m.id)}><strong>{m.nama}</strong></button>
                                        <span>{m.nim ?? '—'}{m.kelas ? ` · ${m.kelas}` : ''}</span>
                                    </div>
                                    <span className={`ad-pbl-badge ${m.role === 'ketua' ? 'ad-pbl-badge--role' : m.role === 'wakil' ? 'ad-pbl-badge--eval' : 'ad-pbl-badge--plan'}`}>
                                        {ROLE_LABEL[m.role] ?? 'Anggota'}
                                    </span>
                                    {p.is_mine && (
                                        <div className="ad-pbl-detail__acts">
                                            {m.role !== 'ketua' && (
                                                <button type="button" className="ad-icon-btn" title="Jadikan ketua tim" disabled={busy}
                                                    onClick={() => act(urlFor(routes.ketua, p.id), { user_id: m.id })}><Crown size={13} /></button>
                                            )}
                                            {m.role !== 'wakil' && m.role !== 'ketua' && (
                                                <button type="button" className="ad-icon-btn" title="Jadikan wakil ketua" disabled={busy}
                                                    onClick={() => act(urlFor(routes.wakil, p.id), { user_id: m.id })}><ShieldCheck size={13} /></button>
                                            )}
                                            <button type="button" className="ad-icon-btn ad-pbl-detail__rm" title="Keluarkan dari tim" disabled={busy}
                                                onClick={() => { if (window.confirm(`Keluarkan ${m.nama} dari tim ${p.nama}?`)) act(urlFor(routes.hapus, p.id, m.id), {}, 'delete'); }}>
                                                <UserMinus size={13} />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                    {p.is_mine && options.length > 0 && (
                        <div className="ad-pbl-detail__add">
                            <select value={pick} onChange={(e) => setPick(e.target.value)} disabled={busy}>
                                <option value="">Pilih mahasiswa belum bertim…</option>
                                {options.map((c) => <option key={c.id} value={c.id}>{c.nama}{c.nim ? ` — ${c.nim}` : ''}{c.kelas ? ` (${c.kelas})` : ''}</option>)}
                            </select>
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary ad-mesin-btn--sm" disabled={busy || pick === ''}
                                onClick={() => { act(urlFor(routes.anggota, p.id), { user_id: Number(pick) }); setPick(''); }}>
                                <UserPlus size={13} /> Tambah
                            </button>
                        </div>
                    )}
                    {p.is_mine && options.length === 0 && members.length === 0 && (
                        <p className="ad-pbl-detail__note">Semua mahasiswa aktif sudah punya tim.</p>
                    )}
                    {p.is_mine && (
                        <>
                            <ActivityTitle />
                            <ActivityTimeline url={urlFor(routes.aktivitas, p.id)} />
                            <div className="ad-pbl-log__note">Riwayat tersimpan permanen dan tidak dapat dihapus — bukti transparan setiap aksi.</div>
                        </>
                    )}
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, padding: '0 18px 16px' }}>
                    {p.is_mine && (
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => { onClose(); onEdit(p); }}>
                            <ImagePlus size={14} /> Kelola tampilan
                        </button>
                    )}
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={onClose}>Tutup</button>
                </div>
            </div>
            {profilId !== null && <MemberProfilePopup userId={profilId} onClose={() => setProfilId(null)} />}
        </div>
    );
}

/** Modal buat tim/proyek PBL baru — pembuat otomatis jadi manpro. */
function CreateModal({ createUrl, onClose }: { createUrl: string; onClose: () => void }) {
    const [form, setForm] = useState({ kode: '', nama: '', shift_sesi: 'pagi' });
    const [busy, setBusy] = useState(false);
    const submit = () => {
        setBusy(true);
        router.post(createUrl, form as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };
    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-pbl-modal">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <h3 style={{ margin: '0 0 2px' }}>Buat Tim PBL Baru</h3>
                <p style={{ margin: '0 0 14px', color: 'var(--ad-muted)', fontSize: 12 }}>Anda otomatis terdaftar sebagai manpro tim ini.</p>
                <label className="ad-pbl-field">
                    <span>Kode proyek</span>
                    <input value={form.kode} maxLength={40} placeholder="mis. PBL-RE-046"
                        onChange={(e) => setForm({ ...form, kode: e.target.value.toUpperCase() })} />
                </label>
                <label className="ad-pbl-field">
                    <span>Nama tim / proyek</span>
                    <input value={form.nama} maxLength={200} placeholder="mis. AGV Sumitomo"
                        onChange={(e) => setForm({ ...form, nama: e.target.value })} />
                </label>
                <label className="ad-pbl-field">
                    <span>Sesi</span>
                    <select value={form.shift_sesi} onChange={(e) => setForm({ ...form, shift_sesi: e.target.value })}>
                        <option value="pagi">Pagi</option>
                        <option value="malam">Malam</option>
                    </select>
                </label>
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 12 }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={onClose}>Batal</button>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy || !form.kode.trim() || !form.nama.trim()} onClick={submit}>
                        <Plus size={14} /> {busy ? 'Membuat…' : 'Buat tim'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function MineCard({ p, onEdit, onDetail }: { p: PblProjectCard; onEdit: (p: PblProjectCard) => void; onDetail: (p: PblProjectCard) => void }) {
    return (
        <article className="ad-pbl-card">
            <button type="button" className="ad-pbl-imgbtn" onClick={() => onDetail(p)}><ProjectImage p={p} large /></button>
            <div className="ad-pbl-card__body">
                <div className="ad-pbl-card__top">
                    <span className="ad-pbl-badge ad-pbl-badge--role">
                        <Crown size={11} /> {p.my_role === 'co_manpro' ? 'Co-Manpro Anda' : 'Manpro Anda'}
                    </span>
                    <Star size={15} className="ad-pbl-star" />
                </div>
                <h3 className="ad-pbl-card__nama">{p.nama}</h3>
                <p className="ad-pbl-card__desc">{p.deskripsi ?? `Tim PBL ${p.nama} — sesi ${p.shift_sesi ?? '-'}.`}</p>
                <div className="ad-pbl-card__meta">
                    <span><Users size={13} /> {p.jumlah_anggota} Anggota</span>
                    {p.ketua && <span><Crown size={13} /> Ketua: {p.ketua}</span>}
                    <span><GraduationCap size={13} /> {p.shift_sesi ?? '—'}</span>
                </div>
                <button type="button" className="ad-pbl-card__ava ad-pbl-avalist" onClick={() => onDetail(p)}>
                    {p.members.slice(0, 5).map((a) => <Avatar key={a.id} nama={a.nama} foto={a.foto} />)}
                    {p.jumlah_anggota > 5 && <span className="ad-pbl-ava ad-pbl-ava--more">+{p.jumlah_anggota - 5}</span>}
                    <span className="ad-pbl-avalist__hint">Lihat tim</span>
                </button>
                {p.progress !== null && <ProgressBar value={p.progress} />}
                <button type="button" className="ad-mesin-btn ad-mesin-btn--primary ad-pbl-manage" onClick={() => onEdit(p)}>
                    <ImagePlus size={14} /> Kelola tampilan &amp; progres
                </button>
            </div>
        </article>
    );
}

function AllCard({ p, onEdit, onDetail }: { p: PblProjectCard; onEdit: (p: PblProjectCard) => void; onDetail: (p: PblProjectCard) => void }) {
    return (
        <article className="ad-pbl-mini ad-pbl-mini--click" onClick={() => onDetail(p)}>
            <ProjectImage p={p} />
            <div className="ad-pbl-mini__body">
                <div className="ad-pbl-mini__top">
                    <span className={`ad-pbl-badge ad-pbl-badge--${p.status_label === 'Berjalan' ? 'run' : p.status_label === 'Evaluasi' ? 'eval' : p.status_label === 'Selesai' ? 'done' : 'plan'}`}>
                        {p.status_label}
                    </span>
                    {p.is_mine && <span className="ad-pbl-badge ad-pbl-badge--role"><Crown size={10} /> Anda</span>}
                </div>
                <h4>{p.nama}</h4>
                <p>{p.deskripsi ?? `Manpro: ${p.manpro ?? '—'}`}</p>
                <div className="ad-pbl-mini__foot">
                    <span><Users size={12} /> {p.jumlah_anggota} Mahasiswa</span>
                    {p.is_mine ? (
                        <button type="button" className="ad-pbl-editlink" onClick={(e) => { e.stopPropagation(); onEdit(p); }}><ImagePlus size={12} /> Foto/Info</button>
                    ) : (
                        <span className="ad-pbl-editlink"><Eye size={12} /> Detail</span>
                    )}
                </div>
            </div>
        </article>
    );
}

export default function DosenPbl(props: DosenPblProps) {
    const [tab, setTab] = useState<'mine' | 'all'>('mine');
    const [q, setQ] = useState('');
    const [status, setStatus] = useState('semua');
    const [editing, setEditing] = useState<PblProjectCard | null>(null);
    const [detail, setDetail] = useState<PblProjectCard | null>(null);
    const [creating, setCreating] = useState(false);

    const filtered = useMemo(() => props.projects.filter((p) => {
        if (status !== 'semua' && p.status_label !== status) return false;
        if (q === '') return true;
        const s = q.toLowerCase();
        return [p.nama, p.kode, p.manpro ?? '', p.co_manpro ?? ''].join(' ').toLowerCase().includes(s);
    }), [props.projects, q, status]);

    const stats = [
        { icon: Boxes, label: 'Total Proyek', value: props.stats.total, cls: 'blue' },
        { icon: GraduationCap, label: 'Proyek Dibimbing', value: props.stats.mine, cls: 'violet' },
        { icon: List, label: 'Total Tim', value: props.stats.tim, cls: 'green' },
        { icon: Users, label: 'Total Mahasiswa', value: props.stats.mahasiswa, cls: 'amber' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Proyek PBL"
            subtitle="Kelola, pantau, dan evaluasi seluruh proyek PBL yang Anda bimbing.">
            <Head title="Proyek PBL" />

            {/* Hero + fitur ringkas */}
            <section className="ad-pbl-hero">
                <div className="ad-pbl-hero__main">
                    <div className="ad-pbl-hero__ic"><Boxes size={34} /></div>
                    <div>
                        <h2>Proyek PBL</h2>
                        <p>Manajemen proyek berbasis Project Based Learning (PBL)</p>
                    </div>
                </div>
                <div className="ad-pbl-hero__feats">
                    <div><LayoutGrid size={18} /><strong>Kelola Proyek</strong><span>Atur dan pantau proyek yang dibimbing</span></div>
                    <div><Users size={18} /><strong>Pantau Tim</strong><span>Lihat progres dan aktivitas tim</span></div>
                    <div><TrendingUp size={18} /><strong>Evaluasi Kinerja</strong><span>Berikan penilaian dan umpan balik</span></div>
                </div>
            </section>

            {/* KPI */}
            <div className="ad-kpi ad-kpi--4">
                {stats.map((s) => (
                    <div key={s.label} className={`ad-kpi__item ad-kpi__item--${s.cls}`}>
                        <div className="ad-kpi__icon"><s.icon size={19} /></div>
                        <span className="k">{s.label}</span>
                        <strong>{s.value}</strong>
                    </div>
                ))}
            </div>

            {/* Tab */}
            <div className="ad-period" role="tablist">
                <button type="button" role="tab" aria-selected={tab === 'mine'} className={tab === 'mine' ? 'is-active' : ''} onClick={() => setTab('mine')}>
                    Proyek Saya ({props.mine.length})
                </button>
                <button type="button" role="tab" aria-selected={tab === 'all'} className={tab === 'all' ? 'is-active' : ''} onClick={() => setTab('all')}>
                    Semua Proyek ({props.projects.length})
                </button>
            </div>

            {tab === 'mine' ? (
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><ClipboardList size={16} /> Proyek PBL yang Saya Bimbing</h3>
                            <p>Daftar proyek di mana Anda berperan sebagai Manpro / Co-Manpro.</p>
                        </div>
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setCreating(true)}>
                            <Plus size={14} /> Buat Proyek Baru
                        </button>
                    </div>
                    {props.mine.length === 0 ? (
                        <div className="ad-empty"><Eye size={18} /> Anda belum terdaftar sebagai manpro/co-manpro proyek manapun.</div>
                    ) : (
                        <div className="ad-pbl-grid-mine">
                            {props.mine.map((p) => <MineCard key={p.id} p={p} onEdit={setEditing} onDetail={setDetail} />)}
                        </div>
                    )}
                </section>
            ) : (
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><Boxes size={16} /> Semua Proyek</h3>
                            <p>Seluruh proyek PBL di sistem (dibimbing atau tidak dibimbing).</p>
                        </div>
                        <div className="ad-pbl-filters">
                            <select value={status} onChange={(e) => setStatus(e.target.value)}>
                                <option value="semua">Semua Status</option>
                                {STATUS_OPTS.map((s) => <option key={s} value={s}>{s}</option>)}
                                <option value="Nonaktif">Nonaktif</option>
                            </select>
                            <div className="ad-pbl-search">
                                <Search size={14} />
                                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari proyek…" />
                            </div>
                        </div>
                    </div>
                    {filtered.length === 0 ? (
                        <div className="ad-empty">Tidak ada proyek yang cocok dengan filter.</div>
                    ) : (
                        <div className="ad-pbl-grid-all">
                            {filtered.map((p) => <AllCard key={p.id} p={p} onEdit={setEditing} onDetail={setDetail} />)}
                        </div>
                    )}
                </section>
            )}

            {creating && <CreateModal createUrl={props.create_url} onClose={() => setCreating(false)} />}
            {detail && <DetailModal p={detail} routes={props.member_routes} candidates={props.unassigned_mahasiswa} onClose={() => setDetail(null)} onEdit={(p) => { setDetail(null); setEditing(p); }} />}
            {editing && <MediaModal p={editing} mediaUrl={props.media_url} onClose={() => setEditing(null)} />}
        </AdminShell>
    );
}
