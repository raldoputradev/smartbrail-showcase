import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { AppShell } from '@/components/layout/AppShell';
import {
    CalendarDays, CheckCheck, CheckCircle2, ChevronLeft, ChevronRight, ClipboardList,
    Clock4, FileText, FileWarning, Hourglass, Inbox, MailCheck, MessageSquareText,
    RotateCcw, Search, SearchX, TriangleAlert, UserRoundCog,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input, Select } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import type { LaporanItem, LaporanStats, Paginated, SharedProps } from '@/types';
import { cn } from '@/lib/utils';

interface Filters {
    q: string;
    status: string;
    kategori: string;
    tanggal: string;
}

interface Props extends SharedProps {
    items: Paginated<LaporanItem>;
    stats: LaporanStats;
    filters: Filters;
    selected: LaporanItem | null;
}

const STATUS_TONE: Record<string, 'warn' | 'info' | 'ok'> = {
    baru: 'warn',
    dibaca: 'info',
    ditanggapi: 'ok',
};

const KATEGORI_META: Record<string, { icon: typeof FileText; box: string }> = {
    absensi: { icon: FileWarning, box: 'bg-sb-danger-bg text-sb-danger' },
    akun: { icon: UserRoundCog, box: 'bg-sb-primary-soft text-sb-primary' },
    saran: { icon: MessageSquareText, box: 'bg-sb-ok-bg text-sb-ok' },
};

function visit(filters: Filters, extra: Record<string, unknown> = {}, hooks: Record<string, unknown> = {}) {
    router.get(route('mahasiswa.suggestions'), { ...filters, ...extra }, {
        preserveState: true, preserveScroll: true, replace: true, ...hooks,
    });
}

export default function ReportHistory({ items, stats, filters, selected }: Props) {
    const [q, setQ] = useState(filters.q);
    const [detail, setDetail] = useState<LaporanItem | null>(selected);
    const [detailOpen, setDetailOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState(false);

    useEffect(() => {
        setDetail(selected);
    }, [selected, items.current_page, items.data]);

    // Pencarian debounce — hindari request per ketukan.
    useEffect(() => {
        if (q === filters.q) return;
        const t = setTimeout(() => apply({ q }), 420);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const hooks = {
        onStart: () => { setBusy(true); setErr(false); },
        onFinish: () => setBusy(false),
        onError: () => setErr(true),
        onException: () => { setErr(true); setBusy(false); },
    };

    const apply = (patch: Partial<Filters>) =>
        visit({ ...filters, q, ...patch }, {}, hooks);

    const hasFilter = !!(filters.q || filters.status || filters.kategori || filters.tanggal);

    const pick = (item: LaporanItem) => {
        setDetail(item);
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
            setDetailOpen(true);
        }
    };

    const markRead = (d: LaporanItem) => {
        router.post(route('mahasiswa.suggestions.read', { suggestion: d.id }), {}, {
            preserveScroll: true, preserveState: true,
            onSuccess: () => setDetail((cur) => cur ? { ...cur, balasan_belum_dibaca: false } : cur),
        });
    };

    const statCards = [
        { label: 'Total Laporan', v: stats.total, icon: ClipboardList, box: 'bg-sb-primary-soft text-sb-primary' },
        { label: 'Menunggu', v: stats.baru, icon: Clock4, box: 'bg-sb-warn-bg text-sb-warn' },
        { label: 'Diproses', v: stats.dibaca, icon: Hourglass, box: 'bg-sb-info-bg text-sb-info' },
        { label: 'Selesai', v: stats.ditanggapi, icon: CheckCircle2, box: 'bg-sb-ok-bg text-sb-ok' },
    ];

    return (
        <div className="space-y-4 sb-fade-in">
            {/* Banner biru — foto kampus memudar dari kanan (referensi) */}
            <header className="sb-lap-hero px-6 py-7 sm:px-8">
                <p className="text-[11px] font-bold tracking-[0.16em] text-sky-200/90 uppercase">
                    Pusat Informasi &amp; Tindak Lanjut
                </p>
                <div className="mt-3 flex items-center gap-4">
                    <span className="grid h-14 w-14 shrink-0 place-items-center rounded-[14px] bg-white/12 ring-1 ring-white/25 backdrop-blur-sm">
                        <ClipboardList size={26} aria-hidden />
                    </span>
                    <div>
                        <h2 className="text-[24px] font-extrabold tracking-tight sm:text-[28px]">Riwayat Laporan</h2>
                        <p className="mt-1 max-w-xl text-[13.5px] text-sky-100/90">
                            Lihat seluruh laporan kendala, saran, dan masukan beserta status penanganannya.
                        </p>
                    </div>
                </div>
            </header>

            {/* Kartu ringkasan */}
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan laporan">
                {statCards.map((s) => (
                    <Card key={s.label}>
                        <CardContent className="flex items-center gap-3.5 pt-5">
                            <span className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-[12px]', s.box)}>
                                <s.icon size={20} aria-hidden />
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-[12.5px] font-semibold text-sb-muted">{s.label}</p>
                                <p className="text-[24px] font-extrabold leading-tight">{s.v}</p>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-[7fr_5fr]">
                {/* Kolom kiri — daftar riwayat */}
                <Card className="min-w-0">
                    <CardContent className="pt-5">
                        <h3 className="mb-3.5 text-[15px] font-bold">Daftar Riwayat Laporan</h3>

                        {/* Pencarian + filter */}
                        <div className="relative mb-2.5">
                            <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sb-muted" aria-hidden />
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Cari berdasarkan judul, deskripsi, atau referensi…"
                                aria-label="Cari laporan"
                                className="pl-9"
                            />
                        </div>
                        <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <Select value={filters.kategori} onChange={(e) => apply({ kategori: e.target.value })} aria-label="Filter kategori">
                                <option value="">Semua Kategori</option>
                                <option value="absensi">Kendala Absensi</option>
                                <option value="akun">Bantuan Akun</option>
                                <option value="saran">Saran &amp; Masukan</option>
                            </Select>
                            <Select value={filters.status} onChange={(e) => apply({ status: e.target.value })} aria-label="Filter status">
                                <option value="">Semua Status</option>
                                <option value="baru">Menunggu</option>
                                <option value="dibaca">Diproses</option>
                                <option value="ditanggapi">Selesai</option>
                            </Select>
                            <Input
                                type="date" value={filters.tanggal}
                                onChange={(e) => apply({ tanggal: e.target.value })}
                                aria-label="Filter tanggal pengiriman"
                                className="col-span-2 sm:col-span-1"
                            />
                        </div>

                        {err && (
                            <div className="mb-4 flex items-center justify-between gap-3 rounded-[12px] border border-sb-danger/30 bg-sb-danger-bg px-4 py-3" role="alert">
                                <p className="flex items-center gap-2 text-[13px] font-semibold text-sb-danger">
                                    <TriangleAlert size={15} aria-hidden /> Gagal memuat riwayat.
                                </p>
                                <Button size="sm" variant="outline" onClick={() => visit(filters)}>
                                    <RotateCcw size={13} className="mr-1.5" aria-hidden /> Coba Lagi
                                </Button>
                            </div>
                        )}

                        {/* Daftar */}
                        {busy ? (
                            <ul className="space-y-3" aria-busy="true" aria-label="Memuat daftar laporan">
                                {[...Array(5)].map((_, i) => (
                                    <li key={i} className="flex items-center gap-3 rounded-[12px] border border-sb-border p-3.5">
                                        <Skeleton className="h-10 w-10 rounded-[10px]" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-3 w-16" />
                                            <Skeleton className="h-4 w-2/3" />
                                            <Skeleton className="h-3 w-32" />
                                        </div>
                                        <Skeleton className="h-6 w-20 rounded-full" />
                                    </li>
                                ))}
                            </ul>
                        ) : items.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 rounded-[14px] border border-dashed border-sb-border px-6 py-12 text-center">
                                {hasFilter ? <SearchX size={34} className="text-sb-muted" aria-hidden /> : <Inbox size={34} className="text-sb-muted" aria-hidden />}
                                <p className="text-[14px] font-bold">{hasFilter ? 'Tidak ada laporan yang cocok' : 'Belum ada laporan'}</p>
                                <p className="max-w-sm text-[12.5px] text-sb-muted">
                                    {hasFilter
                                        ? 'Coba ubah kata kunci atau filter yang dipakai.'
                                        : 'Laporan kendala, saran, atau masukan yang Anda kirim lewat bantuan akan tercatat di sini.'}
                                </p>
                                {hasFilter && (
                                    <Button size="sm" variant="outline" onClick={() => { setQ(''); visit({ q: '', status: '', kategori: '', tanggal: '' }, {}, hooks); }}>
                                        <RotateCcw size={13} className="mr-1.5" aria-hidden /> Reset Filter
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <ul className="space-y-2" aria-label="Daftar laporan" aria-busy={busy}>
                                {items.data.map((it) => {
                                    const meta = KATEGORI_META[it.kategori] ?? KATEGORI_META.saran;
                                    const active = detail?.id === it.id;
                                    return (
                                        <li key={it.id}>
                                            <button
                                                type="button"
                                                onClick={() => pick(it)}
                                                aria-current={active}
                                                className={cn(
                                                    'flex w-full items-center gap-3 rounded-[12px] border p-3.5 text-left transition',
                                                    active
                                                        ? 'border-sb-primary/60 bg-sb-primary-soft/60 shadow-sm'
                                                        : 'border-transparent hover:border-sb-border hover:bg-sb-hover',
                                                )}
                                            >
                                                <span className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-[10px]', meta.box)}>
                                                    <meta.icon size={18} aria-hidden />
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-[11px] font-semibold text-sb-muted">Ref. {it.ref}</span>
                                                    <span className="block truncate text-[14px] font-bold text-sb-text">{it.judul}</span>
                                                    <span className="mt-0.5 flex min-w-0 items-center gap-1.5 text-[12px] text-sb-muted">
                                                        <meta.icon size={11} className="shrink-0" aria-hidden />
                                                        <span className="truncate">{it.kategori_label}</span>
                                                        {it.balasan_belum_dibaca && (
                                                            <Badge tone="info" className="ml-1 shrink-0">Balasan Baru</Badge>
                                                        )}
                                                    </span>
                                                </span>
                                                <span className="flex shrink-0 flex-col items-end gap-1.5">
                                                    <span className="hidden text-[11.5px] text-sb-muted sm:block">{it.tanggal_dikirim}</span>
                                                    <span className="flex items-center gap-2">
                                                        <Badge tone={STATUS_TONE[it.status] ?? 'neutral'}>{it.status_label}</Badge>
                                                        <ChevronRight size={15} className="text-sb-muted" aria-hidden />
                                                    </span>
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        {/* Pagination */}
                        {items.last_page > 1 && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-sb-border pt-4">
                                <p className="text-[12px] text-sb-muted" aria-live="polite">
                                    Menampilkan {items.from}–{items.to} dari {items.total} laporan
                                </p>
                                <nav className="flex items-center gap-1" aria-label="Navigasi halaman">
                                    {items.links.map((l, i) => {
                                        const isPrev = l.label.includes('Previous') || l.label.includes('&laquo;');
                                        const isNext = l.label.includes('Next') || l.label.includes('&raquo;');
                                        const label = isPrev ? <ChevronLeft size={15} /> : isNext ? <ChevronRight size={15} /> : l.label;
                                        return (
                                            <button
                                                key={i} type="button"
                                                disabled={!l.url || busy}
                                                aria-label={isPrev ? 'Halaman sebelumnya' : isNext ? 'Halaman berikutnya' : `Halaman ${l.label}`}
                                                aria-current={l.active ? 'page' : undefined}
                                                onClick={() => l.url && router.get(l.url, {}, { preserveState: true, preserveScroll: true })}
                                                className={cn(
                                                    'grid h-8 min-w-8 place-items-center rounded-[8px] px-2 text-[12.5px] font-semibold transition disabled:opacity-40',
                                                    l.active ? 'bg-sb-primary text-white' : 'text-sb-text2 hover:bg-sb-hover',
                                                )}
                                            >{label}</button>
                                        );
                                    })}
                                </nav>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Kolom kanan — detail (desktop) */}
                <div className="min-w-0 max-lg:hidden lg:sticky lg:top-5">
                    {detail ? (
                        <DetailPanel d={detail} onMarkRead={markRead} />
                    ) : (
                        <Card>
                            <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                                <FileText size={34} className="text-sb-muted" aria-hidden />
                                <p className="text-[13.5px] font-semibold text-sb-muted">Pilih laporan untuk melihat detailnya.</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {/* Detail — dialog pada HP */}
            <Dialog open={detailOpen} onOpenChange={setDetailOpen}>
                <DialogContent title="Detail Laporan" description={detail ? `Ref. ${detail.ref} · ${detail.tanggal_dikirim}` : undefined}>
                    <div className="px-5 pb-6 pt-4">
                        {detail && <DetailPanel d={detail} onMarkRead={markRead} bare />}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}

/** Panel detail laporan — dipakai kolom kanan desktop & dialog HP. */
function DetailPanel({ d, onMarkRead, bare = false }: { d: LaporanItem; onMarkRead: (d: LaporanItem) => void; bare?: boolean }) {
    const meta = KATEGORI_META[d.kategori] ?? KATEGORI_META.saran;

    const steps = [
        {
            label: 'Laporan Diterima', at: d.tanggal_dikirim,
            desc: 'Laporan Anda sudah tercatat dan masuk antrean tinjauan.',
            state: 'done' as const,
        },
        {
            label: 'Dalam Penanganan', at: null,
            desc: d.status === 'baru' ? 'Menunggu ditinjau Admin Laboran.' : 'Sedang ditinjau Admin Laboran.',
            state: d.status === 'baru' ? 'todo' as const : 'active' as const,
        },
        {
            label: 'Selesai', at: d.admin_replied_at,
            desc: d.admin_reply ? 'Admin Laboran sudah memberikan tanggapan resmi.' : 'Menunggu tanggapan resmi admin.',
            state: d.admin_replied_at ? 'done' as const : 'todo' as const,
        },
    ];

    const body = (
        <>
            <div className={cn(!bare && 'px-5 pt-5', 'space-y-3')}>
                <Row label="Judul Laporan"><span className="font-bold">{d.judul}</span></Row>
                <Row label="Kategori">
                    <span className="flex items-center gap-1.5"><meta.icon size={14} className="text-sb-muted" aria-hidden /> {d.kategori_label}</span>
                </Row>
                {d.tanggal_absensi && (
                    <Row label="Tanggal Absensi">
                        <span className="flex items-center gap-1.5"><CalendarDays size={14} className="text-sb-muted" aria-hidden /> {d.tanggal_absensi}</span>
                    </Row>
                )}
                <Row label="Status"><Badge tone={STATUS_TONE[d.status] ?? 'neutral'}>{d.status_label}</Badge></Row>
                <Row label="Tanggal Dikirim">
                    <span className="flex items-center gap-1.5"><CalendarDays size={14} className="text-sb-muted" aria-hidden /> {d.tanggal_dikirim}</span>
                </Row>

                <div className="pt-1">
                    <p className="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-sb-muted">Uraian Laporan</p>
                    <p className="whitespace-pre-line rounded-[10px] bg-sb-surface2 px-4 py-3 text-[13px] leading-relaxed text-sb-text2">{d.pesan}</p>
                    {d.lampiran_url && (
                        <a href={d.lampiran_url} target="_blank" rel="noreferrer" className="mt-2 block">
                            <img src={d.lampiran_url} alt="Foto bukti laporan" loading="lazy"
                                className="max-h-56 rounded-[10px] border border-sb-border object-cover transition hover:opacity-90" />
                        </a>
                    )}
                </div>

                {d.admin_reply && (
                    <div className="rounded-[10px] border border-sb-ok/25 bg-sb-ok-bg/60 px-4 py-3">
                        <p className="flex items-center justify-between gap-2 text-[12px] font-bold text-sb-ok">
                            <span className="flex items-center gap-1.5"><MailCheck size={13} aria-hidden /> Balasan Admin Laboran</span>
                            {d.admin_replied_at && <span className="font-medium text-sb-muted">{d.admin_replied_at}</span>}
                        </p>
                        <p className="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed text-sb-text2">{d.admin_reply}</p>
                        {d.balasan_belum_dibaca && (
                            <Button size="sm" variant="outline" className="mt-2.5" onClick={() => onMarkRead(d)}>
                                <CheckCheck size={13} className="mr-1.5" aria-hidden /> Tandai Balasan Sudah Dibaca
                            </Button>
                        )}
                    </div>
                )}

                {/* Timeline tindak lanjut — hanya fakta yang tersedia */}
                <div className="pt-1">
                    <p className="mb-2 text-[12px] font-bold uppercase tracking-wide text-sb-muted">Tindak Lanjut</p>
                    <ol className="relative space-y-4 border-l-2 border-sb-border pl-5">
                        {steps.map((s) => (
                            <li key={s.label} className="relative">
                                <span className={cn(
                                    'absolute -left-[27px] grid h-5 w-5 place-items-center rounded-full ring-4 ring-sb-surface',
                                    s.state === 'done' && 'bg-sb-ok text-white',
                                    s.state === 'active' && 'bg-sb-primary text-white',
                                    s.state === 'todo' && 'bg-sb-border text-sb-muted',
                                )}>
                                    {s.state === 'done' ? <CheckCircle2 size={11} aria-hidden /> : <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden />}
                                </span>
                                <p className={cn('text-[13.5px] font-bold', s.state === 'todo' && 'text-sb-muted')}>{s.label}</p>
                                {s.at && <p className="text-[12px] text-sb-muted">{s.at}</p>}
                                <p className="text-[12.5px] text-sb-muted">{s.desc}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </>
    );

    if (bare) return body;
    return (
        <Card>
            <div className="flex items-center justify-between border-b border-sb-border px-5 py-4">
                <h3 className="flex items-center gap-2 text-[15px] font-bold">
                    <FileText size={16} className="text-sb-primary" aria-hidden /> Detail Laporan
                </h3>
                <Badge tone="ok">Ref. {d.ref}</Badge>
            </div>
            {body}
            <div className="h-5" />
        </Card>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-dashed border-sb-border pb-2.5 last:border-0 last:pb-0">
            <span className="shrink-0 text-[12.5px] font-medium text-sb-muted">{label}</span>
            <span className="text-right text-[13.5px] text-sb-text">{children}</span>
        </div>
    );
}

ReportHistory.layout = (page: React.ReactNode) => <AppShell title="Riwayat Laporan">{page}</AppShell>;
