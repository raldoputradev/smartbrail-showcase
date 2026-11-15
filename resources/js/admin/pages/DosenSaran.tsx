import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarClock, CheckCircle2, Inbox, MailOpen, MessageSquareText, PenSquare,
    Reply, Search, Send, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import type { DosenSaranItem, DosenSaranProps } from '@admin/types';

const STATUS_CLS: Record<string, string> = { Baru: 'plan', 'Dalam Proses': 'eval', Ditanggapi: 'run' };

function ComposeModal({ storeUrl, onClose }: { storeUrl: string; onClose: () => void }) {
    const [form, setForm] = useState({ subjek: '', pesan: '' });
    const [busy, setBusy] = useState(false);
    const submit = () => {
        setBusy(true);
        router.post(storeUrl, form as never, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: onClose });
    };
    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-pbl-modal">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={16} /></button>
                <h3 style={{ margin: '0 0 2px' }}>Kirim Saran &amp; Masukan</h3>
                <p style={{ margin: '0 0 14px', color: 'var(--ad-muted)', fontSize: 12 }}>
                    Disampaikan ke admin/laboran. Balasan akan tampil di riwayat Anda.
                </p>
                <label className="ad-pbl-field">
                    <span>Subjek</span>
                    <input value={form.subjek} maxLength={180} placeholder="mis. Usulan jadwal lab tambahan"
                        onChange={(e) => setForm({ ...form, subjek: e.target.value })} />
                </label>
                <label className="ad-pbl-field">
                    <span>Pesan (min. 10 karakter)</span>
                    <textarea rows={6} maxLength={4000} value={form.pesan} placeholder="Tuliskan saran atau masukan Anda…"
                        onChange={(e) => setForm({ ...form, pesan: e.target.value })} />
                </label>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy || form.pesan.trim().length < 10} onClick={submit}>
                        <Send size={14} /> {busy ? 'Mengirim…' : 'Kirim'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function ReplyBox({ item, replyUrl }: { item: DosenSaranItem; replyUrl: string }) {
    const [open, setOpen] = useState(false);
    const [pesan, setPesan] = useState('');
    const [busy, setBusy] = useState(false);
    if (!open) {
        return (
            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setOpen(true)}>
                <Reply size={13} /> Balas tanggapan
            </button>
        );
    }
    const submit = () => {
        setBusy(true);
        router.post(replyUrl.replace('/0/', `/${item.id}/`), { pesan }, {
            preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setOpen(false),
        });
    };
    return (
        <div className="ad-saran-replybox">
            <textarea rows={3} value={pesan} maxLength={4000} placeholder="Tulis tanggapan lanjutan…"
                onChange={(e) => setPesan(e.target.value)} />
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setOpen(false)}>Batal</button>
                <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy || pesan.trim().length < 10} onClick={submit}>
                    <Send size={13} /> {busy ? 'Mengirim…' : 'Kirim balasan'}
                </button>
            </div>
        </div>
    );
}

export default function DosenSaran(props: DosenSaranProps) {
    const [q, setQ] = useState('');
    const [status, setStatus] = useState('semua');
    const [composing, setComposing] = useState(false);
    const [expanded, setExpanded] = useState<number | null>(null);

    const filtered = useMemo(() => props.items.filter((s) => {
        if (status !== 'semua' && s.status_label !== status) return false;
        return q === '' || `${s.subjek} ${s.pesan} ${s.tiket}`.toLowerCase().includes(q.toLowerCase());
    }), [props.items, q, status]);

    const stats = [
        { icon: Inbox, label: 'Semua Saran', value: props.stats.semua, cls: 'blue' },
        { icon: CalendarClock, label: 'Dalam Proses', value: props.stats.proses, cls: 'amber' },
        { icon: CheckCircle2, label: 'Ditanggapi', value: props.stats.ditanggapi, cls: 'green' },
        { icon: MailOpen, label: 'Balasan Belum Dibaca', value: props.stats.belum_dibaca, cls: 'violet' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Saran &amp; Masukan"
            subtitle="Sampaikan usulan, kritik, atau masukan kepada admin/laboran dan pantau tanggapannya.">
            <Head title="Saran &amp; Masukan" />

            <section className="ad-pbl-hero">
                <div className="ad-pbl-hero__main">
                    <div className="ad-pbl-hero__ic"><MessageSquareText size={34} /></div>
                    <div>
                        <h2>Suara Anda Penting</h2>
                        <p>Setiap masukan membantu meningkatkan layanan laboratorium, penjadwalan, dan fasilitas.</p>
                    </div>
                </div>
                <button type="button" className="ad-mesin-btn ad-ann-cta" onClick={() => setComposing(true)}>
                    <PenSquare size={15} /> Tulis Saran
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

            <section className="ad-panel">
                <div className="ad-panel__head">
                    <h3>Riwayat Saran Anda</h3>
                    <div className="ad-pbl-filters">
                        <select value={status} onChange={(e) => setStatus(e.target.value)}>
                            <option value="semua">Semua Status</option>
                            <option value="Baru">Baru</option>
                            <option value="Dalam Proses">Dalam Proses</option>
                            <option value="Ditanggapi">Ditanggapi</option>
                        </select>
                        <div className="ad-pbl-search">
                            <Search size={14} />
                            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari tiket atau isi…" />
                        </div>
                    </div>
                </div>
                {filtered.length === 0 ? (
                    <div className="ad-empty"><MessageSquareText size={18} /> Belum ada saran yang cocok.</div>
                ) : (
                    <div className="ad-ann-list">
                        {filtered.map((s) => (
                            <article key={s.id} className="ad-ann-card">
                                <div className="ad-ann-card__ic ad-ann-card__ic--normal"><MessageSquareText size={17} /></div>
                                <div className="ad-ann-card__body">
                                    <div className="ad-ann-card__top">
                                        <h4>{s.subjek}</h4>
                                        <span className={`ad-pbl-badge ad-pbl-badge--${STATUS_CLS[s.status_label] ?? 'plan'}`}>{s.status_label}</span>
                                        {s.unread_reply && <span className="ad-pbl-badge ad-pbl-badge--urgent">Balasan baru</span>}
                                    </div>
                                    <p>{s.pesan}</p>
                                    <div className="ad-ann-card__meta">
                                        <span><CalendarClock size={12} /> {s.tanggal ?? '—'}</span>
                                        <span>Tiket {s.tiket}</span>
                                    </div>
                                    {s.admin_reply && (
                                        <div className="ad-saran-admin">
                                            <div className="ad-saran-admin__head">
                                                <CheckCircle2 size={13} />
                                                Tanggapan {s.admin_replied_role === 'laboran' ? 'laboran' : 'admin'}{s.admin_replied_by ? ` · ${s.admin_replied_by}` : ''} · {s.admin_replied_at ?? ''}
                                            </div>
                                            <p>{s.admin_reply}</p>
                                        </div>
                                    )}
                                    <div style={{ marginTop: 8, display: 'flex', gap: 8 }}>
                                        {s.admin_reply ? <ReplyBox item={s} replyUrl={props.reply_url} /> : null}
                                        {s.unread_reply && (
                                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost"
                                                onClick={() => router.post(props.read_url.replace('/0/', `/${s.id}/`), {}, { preserveScroll: true })}>
                                                <MailOpen size={13} /> Tandai dibaca
                                            </button>
                                        )}
                                        {s.pesan.length > 220 && (
                                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost"
                                                onClick={() => setExpanded(expanded === s.id ? null : s.id)}>
                                                {expanded === s.id ? 'Tutup' : 'Selengkapnya'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>

            {composing && <ComposeModal storeUrl={props.store_url} onClose={() => setComposing(false)} />}
        </AdminShell>
    );
}
