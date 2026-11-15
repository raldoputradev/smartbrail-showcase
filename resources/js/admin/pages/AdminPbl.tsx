import { useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Activity, Boxes, CheckCircle2, Crown, Eye, FileUp, GraduationCap, LayoutGrid,
    List, Pencil, Plus, Search, Shield, Trash2, UserPlus, Users, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { ActivityTimeline, ActivityTitle, MemberProfilePopup } from '@admin/components/PblShared';
import type { AdminPblProject, AdminPblProps } from '@admin/types';

type RouteBag = AdminPblProps['routes'];

const STATUS: Record<string, string> = { Perencanaan: 'plan', Berjalan: 'run', Evaluasi: 'eval', Selesai: 'done', Nonaktif: 'plan' };

const initials = (n?: string | null) => (n ?? '?').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();

function Avatar({ foto, nama, size = 30 }: { foto?: string | null; nama?: string | null; size?: number }) {
    return foto
        ? <img src={foto} alt="" width={size} height={size} className="ad-apbl-ava" />
        : <span className="ad-apbl-ava ad-apbl-ava--ph" style={{ width: size, height: size, fontSize: size * 0.36 }}>{initials(nama)}</span>;
}

function ProgressBar({ value }: { value: number }) {
    const cls = value >= 80 ? 'done' : value >= 40 ? 'run' : 'low';
    return (
        <div className="ad-apbl-progress">
            <div className={`ad-apbl-progress__bar ad-apbl-progress__bar--${cls}`} style={{ width: `${value}%` }} />
            <span>{value}%</span>
        </div>
    );
}

/* ---------- Drawer wizard: Daftarkan Proyek PBL Baru ---------- */
interface Wiz { kode: string; nama: string; deskripsi: string; tahun_ajaran: string; kategori: string; semester: string; periode_mulai: string; periode_selesai: string; manpro_user_id: string; co_manpro_user_id: string; shift_sesi: string; progress: string }

const EMPTY_WIZ: Wiz = { kode: '', nama: '', deskripsi: '', tahun_ajaran: '', kategori: '', semester: 'Ganjil', periode_mulai: '', periode_selesai: '', manpro_user_id: '', co_manpro_user_id: '', shift_sesi: 'pagi', progress: '0' };

function WizardDrawer({ routes, dosen, edit, onClose }: {
    routes: RouteBag; dosen: AdminPblProps['dosen_options']; edit?: AdminPblProject | null; onClose: () => void;
}) {
    const [step, setStep] = useState(0);
    const [busy, setBusy] = useState(false);
    const [f, setF] = useState<Wiz>(edit ? {
        kode: edit.kode, nama: edit.nama, deskripsi: edit.deskripsi ?? '',
        tahun_ajaran: edit.tahun_ajaran ?? '', kategori: edit.kategori ?? '',
        semester: edit.semester ?? 'Ganjil',
        periode_mulai: edit.periode_mulai ?? '', periode_selesai: edit.periode_selesai ?? '',
        manpro_user_id: String(edit.manpro_id ?? ''), co_manpro_user_id: String(edit.co_manpro_id ?? ''),
        shift_sesi: edit.shift_sesi || 'pagi', progress: String(edit.progress ?? 0),
    } : EMPTY_WIZ);

    const steps = ['Informasi Proyek', 'Dosen Pembimbing', 'Pengaturan Tim', 'Konfirmasi'];
    const okStep = [
        f.kode.trim() !== '' && f.nama.trim() !== '',
        f.manpro_user_id !== '' && f.co_manpro_user_id !== f.manpro_user_id,
        true,
        true,
    ][step];

    const submit = () => {
        setBusy(true);
        const payload: Record<string, string | number | boolean | null> = {
            kode: f.kode, nama: f.nama, shift_sesi: f.shift_sesi,
            manpro_user_id: Number(f.manpro_user_id),
            co_manpro_user_id: f.co_manpro_user_id ? Number(f.co_manpro_user_id) : null,
            aktif: edit ? edit.aktif : true,
            deskripsi: f.deskripsi || null, tahun_ajaran: f.tahun_ajaran || null,
            kategori: f.kategori || null, semester: f.semester || null,
            periode_mulai: f.periode_mulai || null, periode_selesai: f.periode_selesai || null,
            progress: Number(f.progress) || 0, status: edit?.status_label !== 'Selesai' ? (edit?.status_label ?? null) : 'Selesai',
        };
        const url = edit ? routes.update.replace('/admin/pbl/0', `/admin/pbl/${edit.id}`) : routes.store;
        if (edit) { payload._method = 'PUT'; }
        router.post(url, payload as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: onClose,
        });
    };

    const manpro = dosen.find((d) => String(d.id) === f.manpro_user_id);
    const co = dosen.find((d) => String(d.id) === f.co_manpro_user_id);

    return (
        <div className="ad-apbl-drawer-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <aside className="ad-apbl-drawer">
                <div className="ad-apbl-drawer__head">
                    <div>
                        <h3>{edit ? 'Ubah Proyek PBL' : 'Daftarkan Proyek PBL Baru'}</h3>
                        <p>{edit ? 'Perbarui informasi dan pengampu proyek.' : 'Buat proyek PBL baru dan tetapkan dosen pembimbing.'}</p>
                    </div>
                    <button type="button" className="ad-icon-btn" onClick={onClose}><X size={16} /></button>
                </div>
                <div className="ad-apbl-steps">
                    {steps.map((s, i) => (
                        <div key={s} className={`ad-apbl-step ${i === step ? 'is-active' : ''} ${i < step ? 'is-done' : ''}`}>
                            <span className="ad-apbl-step__no">{i < step ? '✓' : i + 1}</span>
                            <span className="ad-apbl-step__label">{s}</span>
                        </div>
                    ))}
                </div>

                <div className="ad-apbl-drawer__body">
                    {step === 0 && (
                        <>
                            <div className="ad-pbl-grid2">
                                <label className="ad-pbl-field"><span>Kode Proyek *</span>
                                    <input value={f.kode} placeholder="Contoh: PBL-RE-023" onChange={(e) => setF({ ...f, kode: e.target.value })} /></label>
                                <label className="ad-pbl-field"><span>Tahun Ajaran</span>
                                    <input value={f.tahun_ajaran} placeholder="2026/2027" onChange={(e) => setF({ ...f, tahun_ajaran: e.target.value })} /></label>
                            </div>
                            <label className="ad-pbl-field"><span>Nama Proyek *</span>
                                <input value={f.nama} placeholder="Masukkan nama proyek PBL" onChange={(e) => setF({ ...f, nama: e.target.value })} /></label>
                            <label className="ad-pbl-field"><span>Deskripsi</span>
                                <textarea rows={4} value={f.deskripsi} placeholder="Jelaskan tujuan, ruang lingkup, dan output proyek…"
                                    onChange={(e) => setF({ ...f, deskripsi: e.target.value })} /></label>
                            <div className="ad-pbl-grid2">
                                <label className="ad-pbl-field"><span>Kategori / Bidang</span>
                                    <input value={f.kategori} placeholder="mis. Robotika, IoT" onChange={(e) => setF({ ...f, kategori: e.target.value })} /></label>
                                <label className="ad-pbl-field"><span>Semester</span>
                                    <select value={f.semester} onChange={(e) => setF({ ...f, semester: e.target.value })}>
                                        <option>Ganjil</option><option>Genap</option><option>Antara</option>
                                    </select></label>
                            </div>
                            <div className="ad-pbl-grid2">
                                <label className="ad-pbl-field"><span>Tanggal mulai</span>
                                    <input type="date" value={f.periode_mulai} onChange={(e) => setF({ ...f, periode_mulai: e.target.value })} /></label>
                                <label className="ad-pbl-field"><span>Tanggal selesai</span>
                                    <input type="date" value={f.periode_selesai} onChange={(e) => setF({ ...f, periode_selesai: e.target.value })} /></label>
                            </div>
                        </>
                    )}
                    {step === 1 && (
                        <>
                            <label className="ad-pbl-field"><span>Dosen Pembimbing Utama (Manpro) *</span>
                                <select value={f.manpro_user_id} onChange={(e) => setF({ ...f, manpro_user_id: e.target.value })}>
                                    <option value="">Pilih dosen…</option>
                                    {dosen.map((d) => <option key={d.id} value={d.id}>{d.nama}{d.nip ? ` — ${d.nip}` : ''}</option>)}
                                </select></label>
                            <label className="ad-pbl-field"><span>Co-Manpro (opsional)</span>
                                <select value={f.co_manpro_user_id} onChange={(e) => setF({ ...f, co_manpro_user_id: e.target.value })}>
                                    <option value="">Tidak ada</option>
                                    {dosen.filter((d) => String(d.id) !== f.manpro_user_id).map((d) => <option key={d.id} value={d.id}>{d.nama}</option>)}
                                </select></label>
                            <p className="ad-pbl-note">Manpro mengelola tim, penilaian, dan tampilan proyek dari portal dosen.</p>
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <div className="ad-pbl-grid2">
                                <label className="ad-pbl-field"><span>Shift / Sesi</span>
                                    <select value={f.shift_sesi} onChange={(e) => setF({ ...f, shift_sesi: e.target.value })}>
                                        <option value="pagi">Pagi</option><option value="malam">Malam</option>
                                    </select></label>
                                <label className="ad-pbl-field"><span>Progress awal (%)</span>
                                    <input type="number" min={0} max={100} value={f.progress} onChange={(e) => setF({ ...f, progress: e.target.value })} /></label>
                            </div>
                            <p className="ad-pbl-note">Anggota tim bisa ditambahkan setelah proyek dibuat — lewat tombol detail pada baris proyek.</p>
                        </>
                    )}
                    {step === 3 && (
                        <div className="ad-apbl-review">
                            <dl>
                                <dt>Kode / Nama</dt><dd>{f.kode} — {f.nama}</dd>
                                <dt>Tahun Ajaran</dt><dd>{f.tahun_ajaran || '—'} · {f.semester}</dd>
                                <dt>Kategori</dt><dd>{f.kategori || '—'}</dd>
                                <dt>Periode</dt><dd>{f.periode_mulai || '—'} s.d. {f.periode_selesai || '—'}</dd>
                                <dt>Manpro</dt><dd>{manpro?.nama ?? '—'}</dd>
                                <dt>Co-Manpro</dt><dd>{co?.nama ?? '—'}</dd>
                                <dt>Shift</dt><dd>{f.shift_sesi}</dd>
                                <dt>Progress awal</dt><dd>{f.progress}%</dd>
                            </dl>
                        </div>
                    )}
                </div>

                <div className="ad-apbl-drawer__foot">
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={step === 0 ? onClose : () => setStep(step - 1)}>
                        {step === 0 ? 'Batal' : 'Kembali'}
                    </button>
                    {step < 3 ? (
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={!okStep} onClick={() => setStep(step + 1)}>
                            Lanjutkan →
                        </button>
                    ) : (
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy} onClick={submit}>
                            {busy ? 'Menyimpan…' : edit ? 'Simpan Perubahan' : 'Daftarkan Proyek'}
                        </button>
                    )}
                </div>
            </aside>
        </div>
    );
}

/* ---------- Modal detail proyek (anggota + aksi tim) ---------- */
function DetailModal({ p, routes, unassigned, onClose, onMedia }: { p: AdminPblProject; routes: RouteBag; unassigned: AdminPblProps['unassigned']; onClose: () => void; onMedia: (p: AdminPblProject) => void }) {
    const [pick, setPick] = useState('');
    const [profilId, setProfilId] = useState<number | null>(null);
    const act = (url: string, data: Record<string, unknown> = {}, method: 'post' | 'delete' = 'post') =>
        method === 'delete'
            ? router.delete(url, { preserveScroll: true })
            : router.post(url, data as never, { preserveScroll: true });
    const P = (tpl: string) => tpl.replace('/admin/pbl/0', `/admin/pbl/${p.id}`);

    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-pbl-modal ad-apbl-detail">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <div className="ad-apbl-detail__head">
                    <div>
                        <div className="ad-apbl-detail__kode">{p.kode}</div>
                        <h3>{p.nama}</h3>
                        <div className="ad-ann-card__meta">
                            <span><Users size={12} /> {p.jumlah_anggota} anggota</span>
                            <span>Shift {p.shift_sesi}</span>
                            <span className={`ad-pbl-badge ad-pbl-badge--${STATUS[p.status_label] ?? 'plan'}`}>{p.status_label}</span>
                        </div>
                    </div>
                    {p.progress !== null && <ProgressBar value={p.progress} />}
                </div>
                <div className="ad-apbl-detail__staff">
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
                    <div><span>Periode</span><strong>{p.periode_mulai ?? '—'} s.d. {p.periode_selesai ?? '—'}</strong></div>
                </div>
                {p.deskripsi && <p className="ad-apbl-detail__desc">{p.deskripsi}</p>}

                <div className="ad-pbl-detail__list">
                    {p.members.length === 0 && <div className="ad-empty">Belum ada anggota — tambahkan lewat dropdown di bawah.</div>}
                    {p.members.map((m) => (
                        <div key={m.id} className="ad-pbl-detail__row">
                            <button type="button" className="ad-pbl-detail__who-btn" title="Lihat profil" onClick={() => setProfilId(m.id)}>
                                <Avatar foto={m.foto} nama={m.nama} size={32} />
                            </button>
                            <div className="ad-pbl-detail__who">
                                <button type="button" className="ad-pbl-detail__who-name" title="Lihat profil" onClick={() => setProfilId(m.id)}>{m.nama ?? '—'}</button>
                                <span>{m.nim ?? '—'} · {m.kelas ?? '—'}</span>
                            </div>
                            <span className={`ad-pbl-badge ${m.role === 'ketua' ? 'ad-pbl-badge--eval' : m.role === 'wakil' ? 'ad-pbl-badge--role' : 'ad-pbl-badge--plan'}`}>
                                {m.role === 'ketua' ? 'Ketua' : m.role === 'wakil' ? 'Wakil' : 'Anggota'}
                            </span>
                            <div className="ad-pbl-detail__acts">
                                {m.role !== 'ketua' && <button type="button" className="ad-icon-btn" title="Jadikan ketua" onClick={() => act(P(routes.ketua), { user_id: m.id })}><Crown size={14} /></button>}
                                {m.role !== 'wakil' && <button type="button" className="ad-icon-btn" title="Jadikan wakil" onClick={() => act(P(routes.wakil), { user_id: m.id })}><Shield size={14} /></button>}
                                {m.role !== 'anggota' && <button type="button" className="ad-icon-btn" title="Turunkan ke anggota" onClick={() => act(P(routes.clear_role), { role: m.role })}><Users size={14} /></button>}
                                <button type="button" className="ad-icon-btn ad-pbl-detail__rm" title="Keluarkan dari tim"
                                    onClick={() => act(routes.clear_tim.replace('/0/', `/${m.id}/`), {}, 'post')}><X size={14} /></button>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="ad-pbl-detail__add">
                    <select value={pick} onChange={(e) => setPick(e.target.value)}>
                        <option value="">Pilih mahasiswa belum bertim…</option>
                        {unassigned.map((m) => <option key={m.id} value={m.id}>{m.nama} · {m.nim ?? '—'}</option>)}
                    </select>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={!pick}
                        onClick={() => { act(routes.assign, { user_id: Number(pick), pbl_project_id: p.id }); setPick(''); }}>
                        <UserPlus size={14} /> Tambah
                    </button>
                </div>

                <ActivityTitle />
                <ActivityTimeline url={routes.aktivitas.replace('/pbl/0/', `/pbl/${p.id}/`)} />
                <div className="ad-pbl-log__note">Riwayat tersimpan permanen dan tidak dapat dihapus — bukti transparan setiap aksi.</div>
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, padding: '0 18px 16px' }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => { onClose(); onMedia(p); }}>
                        <FileUp size={14} /> Kelola tampilan
                    </button>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={onClose}>Tutup</button>
                </div>
            </div>
            {profilId !== null && <MemberProfilePopup userId={profilId} onClose={() => setProfilId(null)} />}
        </div>
    );
}

/* ---------- Modal kelola tampilan (foto/deskripsi/status/progress) ---------- */
const MEDIA_STATUS_OPTS = ['Perencanaan', 'Berjalan', 'Evaluasi', 'Selesai'];

function MediaModal({ p, routes, onClose }: { p: AdminPblProject; routes: RouteBag; onClose: () => void }) {
    const [deskripsi, setDeskripsi] = useState(p.deskripsi ?? '');
    const [progress, setProgress] = useState<number>(p.progress ?? 0);
    const [status, setStatus] = useState(p.status_label && MEDIA_STATUS_OPTS.includes(p.status_label) ? p.status_label : 'Berjalan');
    const [foto, setFoto] = useState<File | null>(null);
    const [hapusFoto, setHapusFoto] = useState(false);
    const [busy, setBusy] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const preview = foto ? URL.createObjectURL(foto) : (hapusFoto ? null : p.foto_url);
    const url = routes.media.replace('/pbl/0/', `/pbl/${p.id}/`);

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
                        : <div className="ad-pbl-upload__ph"><FileUp size={26} /><span>Klik untuk pilih foto proyek</span><small>JPG/PNG/WebP, maks 2 MB</small></div>}
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
                            {MEDIA_STATUS_OPTS.map((s) => <option key={s} value={s}>{s}</option>)}
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

/* ---------- Modal impor CSV ---------- */
function ImportModal({ routes, onClose }: { routes: RouteBag; onClose: () => void }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const submit = () => {
        const file = fileRef.current?.files?.[0];
        if (!file) return;
        setBusy(true);
        router.post(routes.import, { csv_projects: file } as never, {
            preserveScroll: true, forceFormData: true,
            onFinish: () => setBusy(false), onSuccess: onClose,
        });
    };
    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <h3 style={{ margin: '0 0 4px' }}>Impor Proyek dari CSV</h3>
                <p style={{ margin: '0 0 12px', color: 'var(--ad-muted)', fontSize: 12 }}>
                    Unduh <a href={routes.csv_template} style={{ color: '#6fb2f2' }}>template CSV</a>, isi, lalu unggah di sini.
                </p>
                <input ref={fileRef} type="file" accept=".csv,.txt" className="ad-pbl-field" style={{ display: 'block' }} />
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy} onClick={submit}>
                        <FileUp size={14} /> {busy ? 'Mengunggah…' : 'Impor'}
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ---------- Halaman ---------- */
export default function AdminPbl(props: AdminPblProps) {
    const [tab, setTab] = useState<'proyek' | 'dosen' | 'monitor'>('proyek');
    const [view, setView] = useState<'list' | 'grid'>('list');
    const [q, setQ] = useState('');
    const [fStatus, setFStatus] = useState('semua');
    const [fTa, setFTa] = useState('semua');
    const [fManpro, setFManpro] = useState('semua');
    const [wizard, setWizard] = useState<null | { edit?: AdminPblProject }>(null);
    const [detail, setDetail] = useState<AdminPblProject | null>(null);
    const [media, setMedia] = useState<AdminPblProject | null>(null);
    const [importing, setImporting] = useState(false);

    const filtered = useMemo(() => props.projects.filter((p) => {
        if (fStatus !== 'semua' && p.status_label !== fStatus) return false;
        if (fTa !== 'semua' && p.tahun_ajaran !== fTa) return false;
        if (fManpro !== 'semua' && p.manpro !== fManpro) return false;
        return q === '' || `${p.kode} ${p.nama} ${p.manpro ?? ''}`.toLowerCase().includes(q.toLowerCase());
    }), [props.projects, q, fStatus, fTa, fManpro]);

    const manproNames = useMemo(() => [...new Set(props.projects.map((p) => p.manpro).filter(Boolean))] as string[], [props.projects]);
    const statusOptions = ['Perencanaan', 'Berjalan', 'Evaluasi', 'Selesai', 'Nonaktif'];

    const kpis = [
        { icon: Boxes, label: 'Total Proyek', value: props.stats.total, cls: 'blue' },
        { icon: Activity, label: 'Proyek Aktif', value: props.stats.aktif, cls: 'green' },
        { icon: CheckCircle2, label: 'Proyek Selesai', value: props.stats.selesai, cls: 'violet' },
        { icon: Users, label: 'Total Anggota Tim', value: props.stats.anggota, cls: 'amber' },
    ];

    const del = (p: AdminPblProject) => {
        if (confirm(`Hapus proyek ${p.kode}? Mahasiswa di tim ini akan kehilangan penempatan.`)) {
            router.delete(props.routes.destroy.replace('/admin/pbl/0', `/admin/pbl/${p.id}`), { preserveScroll: true });
        }
    };

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Kelola Proyek PBL"
            subtitle="Manajemen, pendaftaran, dan penetapan dosen pembimbing proyek PBL.">
            <Head title="Kelola Proyek PBL" />

            <section className="ad-pbl-hero">
                <div className="ad-pbl-hero__main">
                    <div className="ad-pbl-hero__ic"><Boxes size={34} /></div>
                    <div>
                        <h2>Manajemen Proyek PBL</h2>
                        <p>Kelola seluruh proyek, tetapkan dosen pembimbing, atur tim, dan pantau progres pelaksanaan PBL.</p>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setImporting(true)}>
                        <FileUp size={14} /> Impor CSV
                    </button>
                    <button type="button" className="ad-mesin-btn ad-ann-cta" onClick={() => setWizard({})}>
                        <Plus size={15} /> Daftarkan Proyek
                    </button>
                </div>
            </section>

            <div className="ad-kpi ad-kpi--4">
                {kpis.map((s) => (
                    <div key={s.label} className={`ad-kpi__item ad-kpi__item--${s.cls}`}>
                        <div className="ad-kpi__icon"><s.icon size={19} /></div>
                        <span className="k">{s.label}</span>
                        <strong>{s.value}</strong>
                    </div>
                ))}
            </div>

            <section className="ad-panel">
                <div className="ad-panel__head">
                    <div className="ad-period" role="tablist">
                        <button type="button" className={tab === 'proyek' ? 'is-active' : ''} onClick={() => setTab('proyek')}>Daftar Proyek</button>
                        <button type="button" className={tab === 'dosen' ? 'is-active' : ''} onClick={() => setTab('dosen')}>Penetapan Dosen</button>
                        <button type="button" className={tab === 'monitor' ? 'is-active' : ''} onClick={() => setTab('monitor')}>Monitoring Progress</button>
                    </div>
                </div>

                {tab === 'proyek' && (
                    <>
                        <div className="ad-apbl-toolbar">
                            <div className="ad-pbl-search"><Search size={14} /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari proyek…" /></div>
                            <select value={fStatus} onChange={(e) => setFStatus(e.target.value)}>
                                <option value="semua">Semua Status</option>
                                {statusOptions.map((s) => <option key={s}>{s}</option>)}
                            </select>
                            <select value={fTa} onChange={(e) => setFTa(e.target.value)}>
                                <option value="semua">Semua Tahun Ajaran</option>
                                {props.tahun_ajaran_options.map((t) => <option key={t}>{t}</option>)}
                            </select>
                            <select value={fManpro} onChange={(e) => setFManpro(e.target.value)}>
                                <option value="semua">Semua Manpro</option>
                                {manproNames.map((m) => <option key={m}>{m}</option>)}
                            </select>
                            <div className="ad-apbl-viewtoggle">
                                <button type="button" className={view === 'grid' ? 'is-active' : ''} onClick={() => setView('grid')} title="Tampilan kartu"><LayoutGrid size={15} /></button>
                                <button type="button" className={view === 'list' ? 'is-active' : ''} onClick={() => setView('list')} title="Tampilan tabel"><List size={15} /></button>
                            </div>
                        </div>

                        {filtered.length === 0 ? (
                            <div className="ad-empty"><Boxes size={18} /> Tidak ada proyek yang cocok.</div>
                        ) : view === 'list' ? (
                            <div className="ad-apbl-tablewrap">
                                <table className="ad-apbl-table">
                                    <thead>
                                        <tr><th>Kode PBL</th><th>Nama Proyek</th><th>Manpro</th><th>Co-Manpro</th><th>Tim / Mhs</th><th>Status</th><th>Progress</th><th>Aksi</th></tr>
                                    </thead>
                                    <tbody>
                                        {filtered.map((p) => (
                                            <tr key={p.id}>
                                                <td><span className="ad-apbl-kode">{p.kode}</span></td>
                                                <td><strong>{p.nama}</strong><div className="ad-apbl-sub">{p.kategori ?? p.tahun_ajaran ?? ''}</div></td>
                                                <td>{p.manpro ? <div className="ad-apbl-person"><Avatar nama={p.manpro} size={26} /><span>{p.manpro}</span></div> : '—'}</td>
                                                <td>{p.co_manpro ? <div className="ad-apbl-person"><Avatar nama={p.co_manpro} size={26} /><span>{p.co_manpro}</span></div> : '—'}</td>
                                                <td><button type="button" className="ad-apbl-members" onClick={() => setDetail(p)}><Users size={12} /> {p.jumlah_anggota} mhs</button></td>
                                                <td><span className={`ad-pbl-badge ad-pbl-badge--${STATUS[p.status_label] ?? 'plan'}`}>{p.status_label}</span></td>
                                                <td>{p.progress !== null ? <ProgressBar value={p.progress} /> : '—'}</td>
                                                <td>
                                                    <div className="ad-pbl-detail__acts">
                                                        <button type="button" className="ad-icon-btn" title="Detail & anggota" onClick={() => setDetail(p)}><Eye size={14} /></button>
                                                        <button type="button" className="ad-icon-btn" title="Ubah" onClick={() => setWizard({ edit: p })}><Pencil size={14} /></button>
                                                        <button type="button" className="ad-icon-btn ad-pbl-detail__rm" title="Hapus" onClick={() => del(p)}><Trash2 size={14} /></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="ad-pbl-grid">
                                {filtered.map((p) => (
                                    <article key={p.id} className="ad-pbl-card ad-pbl-card--mini">
                                        <div className="ad-pbl-img">
                                            {p.foto_url ? <img src={p.foto_url} alt="" /> : <span className="ad-pbl-img__ph"><Boxes size={24} /></span>}
                                            <span className="ad-pbl-img__kode">{p.kode}</span>
                                        </div>
                                        <div className="ad-pbl-card__body">
                                            <div className="ad-ann-card__top">
                                                <h4>{p.nama}</h4>
                                                <span className={`ad-pbl-badge ad-pbl-badge--${STATUS[p.status_label] ?? 'plan'}`}>{p.status_label}</span>
                                            </div>
                                            <div className="ad-ann-card__meta">
                                                <span><GraduationCap size={12} /> {p.manpro ?? '—'}</span>
                                                <span><Users size={12} /> {p.jumlah_anggota} mhs</span>
                                            </div>
                                            {p.progress !== null && <ProgressBar value={p.progress} />}
                                            <div className="ad-pbl-detail__acts" style={{ marginTop: 'auto' }}>
                                                <button type="button" className="ad-icon-btn" onClick={() => setDetail(p)}><Eye size={14} /></button>
                                                <button type="button" className="ad-icon-btn" onClick={() => setWizard({ edit: p })}><Pencil size={14} /></button>
                                                <button type="button" className="ad-icon-btn ad-pbl-detail__rm" onClick={() => del(p)}><Trash2 size={14} /></button>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </>
                )}

                {tab === 'dosen' && (
                    <div className="ad-apbl-tablewrap">
                        <table className="ad-apbl-table">
                            <thead><tr><th>Dosen</th><th>NIP/NITK</th><th>Sebagai Manpro</th><th>Sebagai Co-Manpro</th><th>Proyek</th></tr></thead>
                            <tbody>
                                {props.dosen_options.map((d) => (
                                    <tr key={d.id}>
                                        <td><div className="ad-apbl-person"><Avatar nama={d.nama} size={28} /><strong>{d.nama}</strong></div></td>
                                        <td>{d.nip ?? '—'}</td>
                                        <td><span className="ad-pbl-badge ad-pbl-badge--eval">{d.sebagai_manpro} proyek</span></td>
                                        <td><span className="ad-pbl-badge ad-pbl-badge--role">{d.sebagai_co} proyek</span></td>
                                        <td className="ad-apbl-sub">
                                            {props.projects.filter((p) => p.manpro_id === d.id || p.co_manpro_id === d.id).map((p) => p.kode).join(', ') || '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {tab === 'monitor' && (
                    <div className="ad-pbl-grid">
                        {filtered.map((p) => (
                            <article key={p.id} className="ad-pbl-card ad-pbl-card--mini">
                                <div className="ad-pbl-img">
                                    {p.foto_url ? <img src={p.foto_url} alt="" /> : <span className="ad-pbl-img__ph"><Boxes size={24} /></span>}
                                    <span className="ad-pbl-img__kode">{p.kode}</span>
                                </div>
                                <div className="ad-pbl-card__body">
                                    <div className="ad-ann-card__top">
                                        <h4>{p.nama}</h4>
                                        <span className={`ad-pbl-badge ad-pbl-badge--${STATUS[p.status_label] ?? 'plan'}`}>{p.status_label}</span>
                                    </div>
                                    <div className="ad-ann-card__meta">
                                        <span>{p.periode_mulai ?? '—'} s.d. {p.periode_selesai ?? '—'}</span>
                                        <span><Users size={12} /> {p.jumlah_anggota}</span>
                                    </div>
                                    <ProgressBar value={p.progress ?? 0} />
                                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" style={{ marginTop: 'auto' }} onClick={() => setDetail(p)}>
                                        <Eye size={13} /> Detail tim
                                    </button>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>

            {wizard && <WizardDrawer routes={props.routes} dosen={props.dosen_options} edit={wizard.edit ?? null} onClose={() => setWizard(null)} />}
            {detail && <DetailModal p={detail} routes={props.routes} unassigned={props.unassigned} onClose={() => setDetail(null)} onMedia={(p) => setMedia(p)} />}
            {media && <MediaModal p={media} routes={props.routes} onClose={() => setMedia(null)} />}
            {importing && <ImportModal routes={props.routes} onClose={() => setImporting(false)} />}
        </AdminShell>
    );
}
