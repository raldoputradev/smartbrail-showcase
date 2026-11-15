import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarClock, CheckCircle2, Clock3, FileText, GraduationCap, Info, Megaphone,
    Pin, Search, Send, Users, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import type { DosenAnnouncementCard, DosenPengumumanProps } from '@admin/types';

const PRIORITAS: Record<string, { label: string; cls: string }> = {
    normal: { label: 'Normal', cls: 'plan' },
    penting: { label: 'Penting', cls: 'eval' },
    urgent: { label: 'Urgent', cls: 'urgent' },
};

function ComposeModal({ storeUrl, timOptions, sisa, onClose }: {
    storeUrl: string; timOptions: string[]; sisa: number; onClose: () => void;
}) {
    const [form, setForm] = useState({ judul: '', isi: '', tujuan: 'mahasiswa', prioritas: 'normal', expires_at: '', action: 'publish' });
    const [busy, setBusy] = useState(false);
    const submit = () => {
        setBusy(true);
        router.post(storeUrl, form as never, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-pbl-modal">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <h3 style={{ margin: '0 0 2px' }}>Kirim Pengumuman</h3>
                <p style={{ margin: '0 0 14px', color: 'var(--ad-muted)', fontSize: 12 }}>
                    Tujuan: semua mahasiswa atau tim PBL bimbingan Anda. Sisa kuota hari ini: {sisa}.
                </p>
                <label className="ad-pbl-field">
                    <span>Judul pengumuman</span>
                    <input value={form.judul} maxLength={200} placeholder="mis. Jadwal Review Proyek PBL"
                        onChange={(e) => setForm({ ...form, judul: e.target.value })} />
                </label>
                <label className="ad-pbl-field">
                    <span>Isi pengumuman</span>
                    <textarea rows={5} maxLength={5000} value={form.isi} placeholder="Tulis isi pengumuman…"
                        onChange={(e) => setForm({ ...form, isi: e.target.value })} />
                </label>
                <div className="ad-pbl-grid2">
                    <label className="ad-pbl-field">
                        <span>Tujuan</span>
                        <select value={form.tujuan} onChange={(e) => setForm({ ...form, tujuan: e.target.value })}>
                            <option value="mahasiswa">Semua mahasiswa</option>
                            {timOptions.map((t) => <option key={t} value={`tim:${t}`}>Tim: {t}</option>)}
                        </select>
                    </label>
                    <label className="ad-pbl-field">
                        <span>Prioritas</span>
                        <select value={form.prioritas} onChange={(e) => setForm({ ...form, prioritas: e.target.value })}>
                            <option value="normal">Normal</option>
                            <option value="penting">Penting</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </label>
                </div>
                <label className="ad-pbl-field">
                    <span>Berlaku sampai (opsional, maks 30 hari)</span>
                    <input type="date" value={form.expires_at} onChange={(e) => setForm({ ...form, expires_at: e.target.value })} />
                </label>
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 6 }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" disabled={busy}
                        onClick={() => { setForm({ ...form, action: 'draft' }); setTimeout(submit, 0); }}>
                        Simpan draft
                    </button>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy || !form.judul.trim() || form.isi.trim().length < 10}
                        onClick={() => { setForm({ ...form, action: 'publish' }); setTimeout(submit, 0); }}>
                        <Send size={14} /> {busy ? 'Mengirim…' : 'Kirim Pengumuman'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function AnnCard({ a, mine }: { a: DosenAnnouncementCard; mine?: boolean }) {
    const pr = PRIORITAS[a.prioritas] ?? PRIORITAS.normal;
    const icon = mine ? (a.target_label.startsWith('Tim') ? Users : GraduationCap) : Megaphone;
    const Icon = icon;
    return (
        <article className={`ad-ann-card ad-ann-card--${a.prioritas}`}>
            <div className={`ad-ann-card__ic ad-ann-card__ic--${a.prioritas}`}><Icon size={17} /></div>
            <div className="ad-ann-card__body">
                <div className="ad-ann-card__top">
                    <h4>{a.judul}</h4>
                    <span className={`ad-pbl-badge ad-pbl-badge--${pr.cls}`}>{a.prioritas === 'urgent' ? <Pin size={10} /> : null}{pr.label}</span>
                    <span className={`ad-pbl-badge ${a.status === 'Terbit' ? 'ad-pbl-badge--run' : a.status === 'Draft' ? 'ad-pbl-badge--plan' : 'ad-pbl-badge--done'}`}>{a.status}</span>
                </div>
                <p>{a.isi}</p>
                <div className="ad-ann-card__meta">
                    <span><CalendarClock size={12} /> {a.tanggal ?? '—'}</span>
                    <span><Users size={12} /> {a.target_label}</span>
                    {a.penulis && <span><CheckCircle2 size={12} /> {a.penulis}</span>}
                </div>
            </div>
        </article>
    );
}

export default function DosenPengumuman(props: DosenPengumumanProps) {
    const [tab, setTab] = useState<'mine' | 'recv'>('mine');
    const [q, setQ] = useState('');
    const [tujuan, setTujuan] = useState('semua');
    const [status, setStatus] = useState('semua');
    const [composing, setComposing] = useState(false);
    const sisa = Math.max(0, props.batas.harian - props.batas.terpakai);

    const list = tab === 'mine' ? props.mine : props.received;
    const filtered = useMemo(() => list.filter((a) => {
        if (status !== 'semua' && a.status !== status) return false;
        if (tujuan !== 'semua' && !(tujuan === 'tim' ? a.target_label.startsWith('Tim') : a.target_label === tujuan)) return false;
        return q === '' || `${a.judul} ${a.isi_full}`.toLowerCase().includes(q.toLowerCase());
    }), [list, q, tujuan, status]);

    const stats = [
        { icon: Megaphone, label: 'Total Pengumuman', value: props.stats.total_mine, cls: 'blue' },
        { icon: Send, label: 'Dikirim ke Tim', value: props.stats.ke_tim, cls: 'violet' },
        { icon: GraduationCap, label: 'Dikirim ke Mahasiswa', value: props.stats.ke_mahasiswa, cls: 'green' },
        { icon: FileText, label: 'Pengumuman Diterima', value: props.stats.diterima, cls: 'amber' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Pengumuman"
            subtitle="Buat, kelola, dan bagikan informasi penting kepada tim proyek atau mahasiswa.">
            <Head title="Pengumuman" />

            <section className="ad-pbl-hero">
                <div className="ad-pbl-hero__main">
                    <div className="ad-pbl-hero__ic"><Megaphone size={34} /></div>
                    <div>
                        <h2>Kelola Pengumuman Akademik</h2>
                        <p>Sampaikan informasi, instruksi, atau pengumuman penting kepada tim proyek PBL atau mahasiswa.</p>
                    </div>
                </div>
                <button type="button" className="ad-mesin-btn ad-ann-cta" onClick={() => setComposing(true)}>
                    <Send size={15} /> Kirim Pengumuman
                </button>
            </section>

            <div className="ad-kpi ad-kpi--4">
                {stats.map((s) => (
                    <div key={s.label} className={`ad-kpi__item ad-kpi__item--${s.cls}`}>
                        <div className="ad-kpi__icon"><s.icon size={19} /></div>
                        <span className="k">{s.label}</span>
                        <strong>{s.value}</strong>
                    </div>
                ))}
            </div>

            <div className="ad-ann-layout">
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div className="ad-period" role="tablist">
                            <button type="button" role="tab" className={tab === 'mine' ? 'is-active' : ''} onClick={() => setTab('mine')}>
                                Buatan Saya ({props.mine.length})
                            </button>
                            <button type="button" role="tab" className={tab === 'recv' ? 'is-active' : ''} onClick={() => setTab('recv')}>
                                Diterima ({props.received.length})
                            </button>
                        </div>
                        <div className="ad-pbl-filters">
                            <select value={tujuan} onChange={(e) => setTujuan(e.target.value)}>
                                <option value="semua">Semua Tujuan</option>
                                <option value="tim">Tim PBL</option>
                                <option value="Mahasiswa">Mahasiswa</option>
                            </select>
                            <select value={status} onChange={(e) => setStatus(e.target.value)}>
                                <option value="semua">Semua Status</option>
                                <option value="Terbit">Terbit</option>
                                <option value="Draft">Draft</option>
                                <option value="Kedaluwarsa">Kedaluwarsa</option>
                            </select>
                            <div className="ad-pbl-search">
                                <Search size={14} />
                                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari judul atau isi…" />
                            </div>
                        </div>
                    </div>
                    {filtered.length === 0 ? (
                        <div className="ad-empty"><Megaphone size={18} /> {tab === 'mine' ? 'Belum ada pengumuman buatan Anda.' : 'Tidak ada pengumuman yang cocok.'}</div>
                    ) : (
                        <div className="ad-ann-list">
                            {filtered.map((a) => <AnnCard key={`${tab}-${a.id}`} a={a} mine={tab === 'mine'} />)}
                        </div>
                    )}
                </section>

                <aside className="ad-ann-side">
                    <div className="ad-panel ad-ann-side__card">
                        <div className="ad-ann-side__title"><Info size={15} /> Batasan &amp; Ketentuan Dosen</div>
                        <ul>
                            <li><strong>Tujuan Pengumuman</strong><br />Semua mahasiswa, atau Tim PBL yang Anda bimbing.</li>
                            <li><strong>Batas Pengiriman</strong><br />Maksimal {props.batas.harian} pengumuman per hari. Sisa hari ini: <strong>{sisa}</strong>.</li>
                            <li><strong>Masa Berlaku</strong><br />Pengumuman dapat dijadwalkan kedaluwarsa maksimal 30 hari.</li>
                        </ul>
                    </div>
                    <div className="ad-panel ad-ann-side__card">
                        <div className="ad-ann-side__title"><Clock3 size={15} /> Aktivitas Terbaru</div>
                        {props.aktivitas.length === 0 ? <div className="ad-empty" style={{ padding: 14 }}>Belum ada aktivitas.</div> : (
                            <ul className="ad-ann-timeline">
                                {props.aktivitas.map((a, i) => (
                                    <li key={i}><strong>{a.judul}</strong><span>{a.status} · {a.tanggal ?? '—'}</span></li>
                                ))}
                            </ul>
                        )}
                    </div>
                </aside>
            </div>

            {composing && <ComposeModal storeUrl={props.store_url} timOptions={props.tim_options} sisa={sisa} onClose={() => setComposing(false)} />}
        </AdminShell>
    );
}
