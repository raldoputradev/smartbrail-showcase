import { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Ban, CalendarCheck2, CheckCircle2, Clock, DoorOpen, History,
    RefreshCcw, Search, Send, Users as UsersIcon, X, XCircle,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { SharedProps, PgjRow, PengajuanRuanganProps } from '@admin/types';

const STATUS_LABEL: Record<string, string> = {
    menunggu: 'Menunggu', diajukan: 'Menunggu', pengganti_ditawarkan: 'Pengganti Ditawarkan',
    menunggu_penerimaan: 'Pengganti Ditawarkan',
    disetujui: 'Disetujui', ditolak: 'Ditolak', dibatalkan: 'Dibatalkan',
    selesai: 'Selesai', kedaluwarsa: 'Kedaluwarsa', berlangsung: 'Berlangsung',
};
const STATUS_PILL: Record<string, string> = {
    menunggu: 'ad-pill--pending', diajukan: 'ad-pill--pending', pengganti_ditawarkan: 'ad-pill--info',
    menunggu_penerimaan: 'ad-pill--info',
    disetujui: 'ad-pill--ok', ditolak: 'ad-pill--danger', dibatalkan: 'ad-pill--off',
    selesai: 'ad-pill--info', kedaluwarsa: 'ad-pill--off', berlangsung: 'ad-pill--ok',
};

const TABS: { key: string; label: string }[] = [
    { key: 'menunggu', label: 'Menunggu' }, { key: 'disetujui', label: 'Disetujui' },
    { key: 'ditolak', label: 'Ditolak' }, { key: 'dibatalkan', label: 'Dibatalkan' },
    { key: 'semua', label: 'Semua' },
];

function initials(nama: string) {
    return nama.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

type TolakState = { row: PgjRow } | null;
type GantiState = { row: PgjRow; mode: 'jadwal' | 'prioritas'; mulai: string; selesai: string; catatan: string } | null;

export default function PengajuanRuangan(props: PengajuanRuanganProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const f = props.filters;
    const meta = props.pengajuan.meta;
    const [busy, setBusy] = useState(false);
    const [sel, setSel] = useState<number | null>(props.pengajuan.data[0]?.id ?? null);
    const [tolak, setTolak] = useState<TolakState>(null);
    const [ganti, setGanti] = useState<GantiState>(null);
    const [alasanTolak, setAlasanTolak] = useState('');
    const [confirm, setConfirm] = useState<{ row: PgjRow; aksi: 'setujui' | 'cabut' } | null>(null);
    const [done, setDone] = useState<{
        icon: 'ok' | 'warn'; title: string; kode: string; nama: string; body: string;
    } | null>(null);

    // Modal hasil aksi menutup sendiri setelah jeda singkat — tidak wajib klik.
    useEffect(() => {
        if (!done) return;
        const t = setTimeout(() => setDone(null), 4500);
        return () => clearTimeout(t);
    }, [done]);

    const nav = (params: Record<string, unknown>) =>
        router.get(props.urls.index, { ...f, ...params },
            { preserveState: true, preserveScroll: true, replace: true });

    const post = (url: string, data: Record<string, unknown> = {}, onDone?: () => void) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) {
                    setConfirm(null); setTolak(null); setGanti(null); onDone?.();
                }
            },
        });
    };

    const selected = useMemo(
        () => props.pengajuan.data.find((p) => p.id === sel) ?? props.pengajuan.data[0] ?? null,
        [sel, props.pengajuan.data],
    );

    const s = props.stats;
    const kpis = [
        { label: 'Total Pengajuan', value: s.total, sub: 'Sepanjang waktu', icon: <Send size={18} />, accent: '#0284c7' },
        { label: 'Menunggu', value: s.menunggu, sub: `+${s.menunggu_7d} dalam 7 hari`, icon: <Clock size={18} />, accent: '#d97706' },
        { label: 'Disetujui', value: s.disetujui, sub: `${s.disetujui_pct}% dari total`, icon: <CheckCircle2 size={18} />, accent: '#059669' },
        { label: 'Ditolak', value: s.ditolak, sub: `${s.ditolak_pct}% dari total`, icon: <XCircle size={18} />, accent: '#e11d48' },
        { label: 'Berlangsung', value: s.berlangsung, sub: 'Terjadwal hari ini', icon: <DoorOpen size={18} />, accent: '#7c3aed' },
    ];

    const pages = useMemo(() => {
        const out: number[] = [];
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === 1 || i === meta.last_page || Math.abs(i - meta.page) <= 1) out.push(i);
        }
        return out;
    }, [meta]);

    const aksiPost = {
        setujui: (p: PgjRow) => post(`${props.urls.setujui_base}${p.id}/setujui`, {}, () => setDone({
            icon: 'ok', title: 'Pengajuan Disetujui!', kode: p.kode, nama: p.pemohon.nama,
            body: `${p.ruangan} · ${p.tanggal} ${p.jam} kini dikunci untuk tim ini. Mahasiswa langsung menerima notifikasi atas nama Anda.`,
        })),
        tolak: (p: PgjRow, alasan: string) => post(`${props.urls.tolak_base}${p.id}/tolak`, { alasan }, () => setDone({
            icon: 'warn', title: 'Pengajuan Ditolak', kode: p.kode, nama: p.pemohon.nama,
            body: `Slot ${p.tanggal} ${p.jam} tetap bebas. Penolakan tercatat atas nama Anda dan mahasiswa diberi notifikasi.`,
        })),
        pengganti: (g: NonNullable<GantiState>) => post(`${props.urls.pengganti_base}${g.row.id}/pengganti`, {
            pengganti_mulai: g.mode === 'jadwal' ? g.mulai : '',
            pengganti_selesai: g.mode === 'jadwal' ? g.selesai : '',
            catatan: g.catatan,
        }, () => setDone({
            icon: 'ok', title: g.mode === 'jadwal' ? 'Waktu Pengganti Ditawarkan' : 'Tawaran Prioritas Terkirim',
            kode: g.row.kode, nama: g.row.pemohon.nama,
            body: g.mode === 'jadwal'
                ? 'Tawaran jadwal pengganti terkirim — mahasiswa diminta menerima atau menolak dari halaman pengajuannya.'
                : 'Mahasiswa diminta konfirmasi masih berminat — bila ya, pengajuan kembali ke antrean Anda dengan catatan prioritas.',
        })),
        cabut: (p: PgjRow) => post(`${props.urls.cabut_base}${p.id}/cabut`, {}, () => setDone({
            icon: 'warn', title: 'Persetujuan Dicabut', kode: p.kode, nama: p.pemohon.nama,
            body: 'Izin peserta diakhiri dan slot jadwal dilepas kembali. Keputusan tercatat atas nama Anda.',
        })),
    };

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Persetujuan Pengajuan"
            subtitle="Tinjau dan putuskan pengajuan pemakaian ruangan dari tim mahasiswa">
            <Head title="Persetujuan Pengajuan" />

            <div className="ad-log-hero">
                <div>
                    <h1>Persetujuan Pengajuan Ruangan</h1>
                    <p>Setiap pengajuan dicek konflik jadwal secara atomik — keputusan Anda langsung mengikat jadwal ruangan.</p>
                    <div className="ad-log-hero__chips">
                        <span><Clock size={11} /> {s.menunggu} menunggu</span>
                        <span><CheckCircle2 size={11} /> {s.berlangsung} berlangsung hari ini</span>
                        <span><History size={11} /> Riwayat keputusan tersimpan</span>
                    </div>
                </div>
                <div className="ad-log-hero__art" aria-hidden>
                    <svg viewBox="0 0 200 120" fill="none">
                        <rect x="30" y="18" width="96" height="90" rx="8" fill="rgba(255,255,255,.14)" stroke="rgba(255,255,255,.4)" />
                        <rect x="30" y="18" width="96" height="24" rx="8" fill="rgba(255,255,255,.22)" />
                        <rect x="44" y="52" width="20" height="16" rx="3" fill="rgba(255,255,255,.4)" />
                        <rect x="70" y="52" width="20" height="16" rx="3" fill="rgba(255,255,255,.25)" />
                        <rect x="96" y="52" width="20" height="16" rx="3" fill="rgba(255,255,255,.25)" />
                        <rect x="44" y="74" width="20" height="16" rx="3" fill="rgba(255,255,255,.25)" />
                        <circle cx="148" cy="84" r="20" fill="rgba(5,150,105,.9)" />
                        <path d="M139 84l6 6 11-12" stroke="#fff" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" />
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
                {TABS.map((t) => (
                    <button key={t.key} className={cn('ad-rg-tab', f.status === t.key && 'is-active')}
                        onClick={() => nav({ status: t.key === 'menunggu' ? undefined : t.key, page: undefined })}>
                        {t.label}
                        {t.key === 'menunggu' && s.menunggu > 0 && <span className="ad-rg-tab__n">{s.menunggu}</span>}
                    </button>
                ))}
            </div>

            <div className="ad-pgj-grid">
                <div className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3>Daftar Pengajuan</h3>
                            <small>{meta.total} pengajuan pada tab ini</small>
                        </div>
                    </div>
                    <div className="ad-panel__body">
                        <form className="ad-mesin-filters" onSubmit={(e) => {
                            e.preventDefault();
                            const fd = new FormData(e.currentTarget);
                            nav({ q: fd.get('q') || undefined, page: undefined });
                        }}>
                            <label className="ad-mesin-search"><Search />
                                <input name="q" defaultValue={f.q} placeholder="Cari kegiatan / pemohon / NIM…" />
                            </label>
                            <button className="ad-mesin-btn" type="submit">Cari</button>
                        </form>

                        <div className="ad-pgj-list">
                            {props.pengajuan.data.length === 0 && (
                                <div className="ad-empty">Tidak ada pengajuan pada status ini.</div>
                            )}
                            {props.pengajuan.data.map((p) => (
                                <button key={p.id}
                                    className={cn('ad-pgj-item', selected?.id === p.id && 'is-active')}
                                    onClick={() => setSel(p.id)}>
                                    <div className="ad-pgj-item__top">
                                        <span className="ad-pgj-kode">{p.kode}</span>
                                        <span className={cn('ad-pill', STATUS_PILL[p.status_efektif] ?? 'ad-pill--muted')}>
                                            {STATUS_LABEL[p.status_efektif] ?? p.status_efektif}
                                        </span>
                                    </div>
                                    <strong className="ad-pgj-judul">{p.judul}</strong>
                                    <div className="ad-pgj-meta">
                                        <span><DoorOpen size={11} /> {p.ruangan}</span>
                                        <span><CalendarCheck2 size={11} /> {p.hari}, {p.tanggal}</span>
                                        <span><Clock size={11} /> {p.jam}</span>
                                        <span><UsersIcon size={11} /> {p.peserta_n} peserta</span>
                                    </div>
                                    <div className="ad-pgj-pemohon">
                                        <span className="ad-pgj-ava">{initials(p.pemohon.nama)}</span>
                                        <span>{p.pemohon.nama}{p.pemohon.tim ? ` · ${p.pemohon.tim}` : ''}</span>
                                    </div>
                                </button>
                            ))}
                        </div>

                        <div className="ad-mesin-pager">
                            <small>{meta.from}–{meta.to} dari {meta.total}</small>
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

                <div className="ad-pgj-side">
                    {selected ? (
                        <div className="ad-panel">
                            <div className="ad-panel__head">
                                <div>
                                    <h3>Detail Pengajuan</h3>
                                    <small>{selected.kode} · diajukan {selected.dibuat ?? '—'}</small>
                                </div>
                                <span className={cn('ad-pill', STATUS_PILL[selected.status_efektif] ?? 'ad-pill--muted')}>
                                    {STATUS_LABEL[selected.status_efektif] ?? selected.status_efektif}
                                </span>
                            </div>
                            <div className="ad-panel__body ad-pgj-detail">
                                <div className="ad-pgj-quote">
                                    <span>Catatan pemohon</span>
                                    <p>&ldquo;{selected.judul}&rdquo;</p>
                                </div>
                                <div className="ad-mesin-detail">
                                    <div className="ad-mesin-detail__row"><span>Ruangan</span><strong>{selected.ruangan}</strong></div>
                                    <div className="ad-mesin-detail__row"><span>Jadwal</span><strong>{selected.hari}, {selected.tanggal} · {selected.jam}</strong></div>
                                    <div className="ad-mesin-detail__row"><span>Pemohon</span><strong>{selected.pemohon.nama}</strong></div>
                                    {selected.pemohon.tim && <div className="ad-mesin-detail__row"><span>Tim</span><strong>{selected.pemohon.tim}</strong></div>}
                                    {selected.pembimbing && <div className="ad-mesin-detail__row"><span>Pembimbing</span><strong>{selected.pembimbing}</strong></div>}
                                    <div className="ad-mesin-detail__row"><span>Peserta</span><strong>{selected.peserta_n} orang</strong></div>
                                </div>
                                {selected.peserta.length > 0 && (
                                    <div className="ad-rg-chips" style={{ marginTop: 10 }}>
                                        {selected.peserta.map((n) => <span key={n} className="ad-rg-chip">{n}</span>)}
                                    </div>
                                )}
                                {selected.pengganti && (
                                    <div className="ad-pgj-ganti">
                                        <strong><RefreshCcw size={12} /> {selected.pengganti.mulai ? 'Jadwal Pengganti Ditawarkan' : 'Tawaran Prioritas (Tanpa Jadwal)'}</strong>
                                        {selected.pengganti.mulai && <span>{selected.pengganti.mulai} – {selected.pengganti.selesai ?? '—'}</span>}
                                        {selected.pengganti.catatan && <small>{selected.pengganti.catatan}</small>}
                                    </div>
                                )}
                                {selected.alasan_status && (
                                    <div className="ad-pgj-alasan"><strong>Kenapa {selected.status_efektif === 'kedaluwarsa' ? 'kedaluwarsa' : 'berakhir'}?</strong> {selected.alasan_status}</div>
                                )}
                                {selected.alasan && (
                                    <div className="ad-pgj-alasan"><strong>Catatan keputusan:</strong> {selected.alasan}</div>
                                )}
                                {selected.diputus_oleh && (
                                    <small className="ad-mesin-muted">Diputus oleh {selected.diputus_oleh} · {selected.diputus_pada}</small>
                                )}

                                {props.can.update && selected.boleh_putus && (
                                    <div className="ad-pgj-actions">
                                        <button className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}
                                            onClick={() => setConfirm({ row: selected, aksi: 'setujui' })}>
                                            <CheckCircle2 size={14} /> Setujui
                                        </button>
                                        <button className="ad-mesin-btn" disabled={busy}
                                            onClick={() => setGanti({ row: selected, mode: 'jadwal', mulai: '', selesai: '', catatan: '' })}>
                                            <RefreshCcw size={14} /> Tawarkan Pengganti
                                        </button>
                                        <button className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy}
                                            onClick={() => { setTolak({ row: selected }); setAlasanTolak(''); }}>
                                            <XCircle size={14} /> Tolak
                                        </button>
                                    </div>
                                )}
                                {props.can.update && selected.status_efektif === 'disetujui' && (
                                    <div className="ad-pgj-actions">
                                        <button className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy}
                                            onClick={() => setConfirm({ row: selected, aksi: 'cabut' })}>
                                            <Ban size={14} /> Cabut Persetujuan
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="ad-panel"><div className="ad-empty">Pilih pengajuan untuk melihat detail.</div></div>
                    )}

                    <div className="ad-panel">
                        <div className="ad-panel__head">
                            <div><h3>Riwayat Keputusan</h3><small>Audit keputusan terakhir</small></div>
                        </div>
                        <div className="ad-panel__body ad-pgj-riwayat">
                            {props.riwayat.length === 0 && <div className="ad-empty">Belum ada keputusan tercatat.</div>}
                            {props.riwayat.map((r, i) => (
                                <div className="ad-pgj-riv" key={i}>
                                    <span className={cn('ad-dot', r.keputusan === 'disetujui' ? 'ad-dot--ok' : 'ad-dot--warn')} />
                                    <div>
                                        <strong>{r.kode} — {r.kegiatan}</strong>
                                        <small>{r.ruangan} · {r.pemohon}</small>
                                        <small>{STATUS_LABEL[r.keputusan] ?? r.keputusan} oleh {r.oleh} · {r.waktu}</small>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {done && (
                <div className="ad-mesin-scrim" onClick={() => setDone(null)}>
                    <div className="ad-mesin-confirm ad-pgj-done" onClick={(e) => e.stopPropagation()}>
                        <div className={cn('ad-pgj-done__icon', done.icon === 'ok' ? 'is-ok' : 'is-warn')}>
                            {done.icon === 'ok' ? <CheckCircle2 size={30} /> : <XCircle size={30} />}
                        </div>
                        <h4>{done.title}</h4>
                        <p className="ad-pgj-done__meta">{done.kode} · {done.nama}</p>
                        <p>{done.body}</p>
                        <div className="ad-mesin-confirm__actions" style={{ justifyContent: 'center' }}>
                            <button className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setDone(null)}>Selesai</button>
                        </div>
                    </div>
                </div>
            )}

            {confirm && (
                <div className="ad-mesin-scrim" onClick={() => setConfirm(null)}>
                    <div className="ad-mesin-confirm" onClick={(e) => e.stopPropagation()}>
                        <h4>{confirm.aksi === 'setujui' ? 'Setujui pengajuan ini?' : 'Cabut persetujuan?'}</h4>
                        <p>{confirm.aksi === 'setujui'
                            ? `Jadwal ${confirm.row.ruangan} pada ${confirm.row.tanggal} ${confirm.row.jam} akan dikunci untuk ${confirm.row.pemohon.nama}.`
                            : `Persetujuan untuk ${confirm.row.kode} akan ditarik kembali dan slot jadwal dilepas.`}</p>
                        <div className="ad-mesin-confirm__actions">
                            <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setConfirm(null)}>Batal</button>
                            <button className={cn('ad-mesin-btn', confirm.aksi === 'setujui' ? 'ad-mesin-btn--primary' : 'ad-mesin-btn--danger')}
                                disabled={busy}
                                onClick={() => confirm.aksi === 'setujui' ? aksiPost.setujui(confirm.row) : aksiPost.cabut(confirm.row)}>
                                {busy ? 'Memproses…' : 'Ya, lanjutkan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {tolak && (
                <div className="ad-mesin-scrim" onClick={() => setTolak(null)}>
                    <div className="ad-mesin-confirm" onClick={(e) => e.stopPropagation()}>
                        <h4>Tolak pengajuan {tolak.row.kode}?</h4>
                        <div className="ad-mesin-field" style={{ marginBottom: 14 }}>
                            <label>Alasan penolakan <em>*</em></label>
                            <textarea rows={3} value={alasanTolak} maxLength={500}
                                placeholder="Wajib — mahasiswa melihat ini. Misal: jadwal bentrok dengan kegiatan lab…"
                                onChange={(e) => setAlasanTolak(e.target.value)} />
                        </div>
                        <div className="ad-mesin-confirm__actions">
                            <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setTolak(null)}>Batal</button>
                            <button className="ad-mesin-btn ad-mesin-btn--danger" disabled={busy || alasanTolak.trim().length < 3}
                                onClick={() => aksiPost.tolak(tolak.row, alasanTolak)}>
                                {busy ? 'Memproses…' : 'Tolak Pengajuan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {ganti && (
                <div className="ad-mesin-scrim" onClick={() => setGanti(null)}>
                    <div className="ad-mesin-confirm ad-pgj-offer" onClick={(e) => e.stopPropagation()}>
                        <h4>Tawarkan ke {ganti.row.pemohon.nama}</h4>
                        <div className="ad-pgj-offer__ctx">
                            <span className="ad-pgj-kode">{ganti.row.kode}</span>
                            <span><DoorOpen size={12} /> {ganti.row.ruangan}</span>
                            <span><Clock size={12} /> {ganti.row.hari}, {ganti.row.tanggal} · {ganti.row.jam}</span>
                        </div>

                        <div className="ad-pgj-offer__modes" role="tablist" aria-label="Bentuk tawaran">
                            {([
                                ['jadwal', 'Jadwal Pengganti', 'Tawarkan slot konkret — diterima = langsung disetujui.'],
                                ['prioritas', 'Prioritas / Berlaku', 'Tanpa tanggal — minta konfirmasi berminat, lalu antre prioritas.'],
                            ] as const).map(([m, label, hint]) => (
                                <button key={m} type="button" role="tab" aria-selected={ganti.mode === m}
                                    className={cn('ad-pgj-offer__mode', ganti.mode === m && 'is-active')}
                                    onClick={() => setGanti({ ...ganti, mode: m })}>
                                    <b>{label}</b>
                                    <small>{hint}</small>
                                </button>
                            ))}
                        </div>

                        {ganti.mode === 'jadwal' ? (
                            <div className="ad-rg-grid2" style={{ marginBottom: 10 }}>
                                <div className="ad-mesin-field">
                                    <label>Mulai <em>*</em></label>
                                    <input type="datetime-local" value={ganti.mulai}
                                        onChange={(e) => setGanti({ ...ganti, mulai: e.target.value })} />
                                </div>
                                <div className="ad-mesin-field">
                                    <label>Selesai <em>*</em></label>
                                    <input type="datetime-local" value={ganti.selesai}
                                        onChange={(e) => setGanti({ ...ganti, selesai: e.target.value })} />
                                </div>
                            </div>
                        ) : (
                            <div className="ad-pgj-offer__note">
                                <RefreshCcw size={13} />
                                Dipakai saat slot diminta ada prioritas lain — mahasiswa yang mengonfirmasi
                                kembali ke antrean dan tampil menunggu keputusan Anda.
                            </div>
                        )}

                        <div className="ad-mesin-field" style={{ marginBottom: 14 }}>
                            <label>Alasan untuk pemohon <em>*</em></label>
                            <textarea rows={3} value={ganti.catatan} maxLength={500}
                                placeholder="Misal: slot ini dipakai kegiatan lab prioritas — kami tawarkan sebagai gantinya…"
                                onChange={(e) => setGanti({ ...ganti, catatan: e.target.value })} />
                            <small className="ad-pgj-offer__count">{ganti.catatan.length}/500 — wajib, tampil ke mahasiswa beserta pilihan Terima/Tolak.</small>
                        </div>

                        <div className="ad-mesin-confirm__actions">
                            <button className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setGanti(null)}>Batal</button>
                            <button className="ad-mesin-btn ad-mesin-btn--primary"
                                disabled={busy || ganti.catatan.trim().length < 5
                                    || (ganti.mode === 'jadwal' && (!ganti.mulai || !ganti.selesai))}
                                onClick={() => aksiPost.pengganti(ganti)}>
                                {busy ? 'Memproses…' : <><Send size={14} /> Kirim Tawaran</>}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
