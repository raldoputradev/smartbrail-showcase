import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays, Check, CheckCircle2, ChevronRight, Clock, DoorOpen, History, Search, Send, UserPlus, Users, X,
} from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import { Badge, toneFor } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input, Select, Textarea } from '@/components/ui/input';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Avatar } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import type { Anggota, KetersediaanResp, Pengajuan, RuanganEntry, SharedProps, Slot, Tim } from '@/types';
import { apiGet } from '@/lib/api';
import { subscribePrivate } from '@/lib/live';
import { hhmm, tanggalHuman, tanggalSingkat } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Props extends SharedProps {
    tim: Tim | null;
    anggota: Anggota[];
    ruanganList: RuanganEntry[];
    pengajuan: Pengajuan[];
    tabRiwayat: boolean;
}

const STATUS_LABEL: Record<string, string> = {
    diajukan: 'Menunggu Keputusan',
    menunggu_penerimaan: 'Tawaran Waktu Pengganti',
    disetujui: 'Disetujui',
    ditolak: 'Ditolak',
    dibatalkan: 'Dibatalkan',
    selesai: 'Selesai',
    kedaluwarsa: 'Kedaluwarsa',
};

function statusLabel(p: Pengajuan): string {
    return STATUS_LABEL[p.status_efektif] ?? p.status_efektif;
}

const STATUS_STAMP: Record<string, { label: string; cls: string }> = {
    disetujui: { label: 'Disetujui', cls: '' },
    selesai: { label: 'Disetujui', cls: '' },
    ditolak: { label: 'Ditolak', cls: 'sb-stamp--danger' },
    dibatalkan: { label: 'Dibatalkan', cls: 'sb-stamp--muted' },
    kedaluwarsa: { label: 'Kedaluwarsa', cls: 'sb-stamp--warn' },
};

function StatusStamp({ status }: { status: string }) {
    const s = STATUS_STAMP[status];
    if (!s) return null;
    return <span className={`sb-stamp ${s.cls}`} aria-hidden>{s.label}</span>;
}

const BOOKING_STEPS = ['Dikirim', 'Persetujuan Laboran', 'Disetujui', 'Datang ke Ruangan'] as const;

/** Jumlah langkah yang sudah selesai untuk suatu status efektif; -1 = berakhir tanpa persetujuan. */
function stepDoneCount(status: string): number {
    switch (status) {
        case 'diajukan':
        case 'menunggu_penerimaan':
            return 1;
        case 'disetujui':
            return 3;
        case 'selesai':
            return 4;
        default:
            return -1;
    }
}

function Stepper({ status }: { status: string }) {
    const done = stepDoneCount(status);
    if (done < 0) {
        return (
            <div className="rounded-[10px] border border-sb-danger/30 bg-sb-danger-bg/50 px-3.5 py-2.5 text-[12.5px] font-semibold text-sb-danger">
                Alur berakhir — {STATUS_LABEL[status] ?? status}
            </div>
        );
    }
    return (
        <ol className="flex" aria-label="Tahapan pengajuan">
            {BOOKING_STEPS.map((label, i) => (
                <li key={label} className="relative flex flex-1 flex-col items-center gap-1.5 text-center">
                    {i > 0 && (
                        <span aria-hidden className={cn('absolute left-[calc(-50%+15px)] right-[calc(50%+15px)] top-[13px] h-[2px] rounded', i <= done ? 'bg-sb-ok' : 'bg-sb-border')} />
                    )}
                    <span className={cn('relative grid h-[26px] w-[26px] shrink-0 place-items-center rounded-full border-2 bg-sb-surface text-[11px] font-bold',
                        i < done ? 'border-sb-ok bg-sb-ok text-white'
                            : i === done ? 'border-sb-primary text-sb-primary'
                                : 'border-sb-border text-sb-muted')}>
                        {i < done ? <Check size={13} strokeWidth={3} /> : i + 1}
                    </span>
                    <span className={cn('text-[10.5px] font-semibold leading-tight', i <= done ? 'text-sb-text' : 'text-sb-muted')}>{label}</span>
                </li>
            ))}
        </ol>
    );
}

/** Kelompokkan slot 1-jam menjadi sesi: Pagi <12, Siang 12–15, Sore 15–18, Malam ≥18. */
function groupSlotsBySession(slots: Slot[]): Array<{ label: string; slots: Slot[] }> {
    const sesi = (jam: string) => {
        const h = Number(jam.slice(0, 2));
        if (h < 12) return 'Sesi Pagi';
        if (h < 15) return 'Sesi Siang';
        if (h < 18) return 'Sesi Sore';
        return 'Sesi Malam';
    };
    const out: Array<{ label: string; slots: Slot[] }> = [];
    for (const s of slots) {
        const label = sesi(s.mulai);
        const last = out[out.length - 1];
        if (last && last.label === label) last.slots.push(s);
        else out.push({ label, slots: [s] });
    }
    return out;
}

export default function RoomRequest({ tim, anggota, ruanganList, pengajuan, tabRiwayat }: Props) {
    const { flash, auth, reverb } = usePage<SharedProps>().props;
    const userId = auth.user!.id;
    const [tab, setTab] = useState<'ajukan' | 'riwayat'>(tabRiwayat ? 'riwayat' : 'ajukan');
    const [successOpen, setSuccessOpen] = useState(false);

    // Realtime: keputusan laboran langsung menyegarkan daftar pengajuan.
    useEffect(() => {
        if (!reverb?.key) return;
        return subscribePrivate(reverb, `mahasiswa.${userId}`, {
            'pengajuan.updated': () => router.reload({ only: ['pengajuan', 'navNotificationState'] }),
        });
    }, [reverb?.key, userId]);

    // Dialog sukses menutup sendiri — ring info tetap tampil di kartu flash di bawahnya.
    useEffect(() => {
        if (!successOpen) return;
        const t = setTimeout(() => setSuccessOpen(false), 4500);
        return () => clearTimeout(t);
    }, [successOpen]);

    const switchTab = (t: 'ajukan' | 'riwayat') => {
        setTab(t);
        router.get(route('mahasiswa.ruangan'), t === 'riwayat' ? { tab: 'riwayat' } : {}, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Ruangan' }, { label: tab === 'ajukan' ? 'Ajukan' : 'Riwayat' }]} />}
                title={tab === 'ajukan' ? 'Ajukan Ruangan' : 'Riwayat Pengajuan'}
                sub={tab === 'ajukan'
                    ? 'Pilih ruangan, jadwal, dan peserta tim untuk mengajukan penggunaan ruangan.'
                    : 'Keputusan Laboran atas pengajuan ruangan tim Anda.'}
            />

            <Dialog open={successOpen && !!flash.terkirim} onOpenChange={(o) => !o && setSuccessOpen(false)}>
                <DialogContent>
                    {flash.terkirim && (
                        <div className="px-5 py-6 text-center">
                            <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-sb-ok-bg text-sb-ok">
                                <CheckCircle2 size={34} />
                            </span>
                            <h3 className="mt-4 text-[18px] font-extrabold">Pengajuan Terkirim!</h3>
                            <p className="mt-1 text-[13px] leading-relaxed text-sb-text2">
                                Pengajuan #{flash.terkirim.id} sudah masuk antrian Laboran — Anda akan diberi tahu lewat notifikasi begitu ada keputusan.
                            </p>
                            <div className="mt-4 rounded-[12px] bg-sb-surface2 px-4 py-3 text-[12.5px] font-semibold">
                                {flash.terkirim.ruangan} · {tanggalHuman(flash.terkirim.tanggal)} · {hhmm(flash.terkirim.jam_mulai)}–{hhmm(flash.terkirim.jam_selesai)} · {flash.terkirim.peserta.length} peserta
                            </div>
                            <div className="mt-5">
                                <Stepper status="diajukan" />
                            </div>
                            <div className="mt-6 flex gap-2.5">
                                <Button variant="outline" className="flex-1" onClick={() => setSuccessOpen(false)}>Tutup</Button>
                                <Button className="flex-1" onClick={() => { setSuccessOpen(false); switchTab('riwayat'); }}>
                                    <History size={15} /> Lihat Riwayat
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {flash.terkirim && (
                <Card className="border-sb-ok/40">
                    <CardContent className="flex items-start gap-3 pt-5">
                        <CheckCircle2 size={20} className="mt-0.5 shrink-0 text-sb-ok" />
                        <div className="text-[13px]">
                            <p className="font-bold">Pengajuan #{flash.terkirim.id} terkirim — Menunggu keputusan Laboran</p>
                            <p className="mt-0.5 text-sb-text2">
                                {flash.terkirim.ruangan} · {tanggalHuman(flash.terkirim.tanggal)} · {hhmm(flash.terkirim.jam_mulai)}–{hhmm(flash.terkirim.jam_selesai)} · {flash.terkirim.peserta.length} peserta
                            </p>
                        </div>
                    </CardContent>
                </Card>
            )}
            {flash.status && (
                <p role="status" className="rounded-[10px] border border-sb-ok/30 bg-sb-ok-bg/60 px-3.5 py-2.5 text-[12.5px] font-medium text-sb-ok">{flash.status}</p>
            )}
            {flash.error && (
                <p role="alert" className="rounded-[10px] border border-sb-danger/30 bg-sb-danger-bg/60 px-3.5 py-2.5 text-[12.5px] font-medium text-sb-danger">{flash.error}</p>
            )}

            <div className="flex gap-1 rounded-[10px] bg-sb-surface2 p-1" role="tablist">
                {([['ajukan', Send, 'Ajukan Ruangan'], ['riwayat', History, `Riwayat (${pengajuan.length})`]] as const).map(([v, Icon, label]) => (
                    <button key={v} role="tab" aria-selected={tab === v} type="button" onClick={() => switchTab(v)}
                        className={cn('flex flex-1 items-center justify-center gap-2 rounded-[8px] px-4 py-2 text-[13px] font-semibold transition',
                            tab === v ? 'bg-sb-surface text-sb-primary shadow-sm' : 'text-sb-muted hover:text-sb-text')}>
                        <Icon size={15} /> {label}
                    </button>
                ))}
            </div>

            {tab === 'ajukan'
                ? <AjukanTab tim={tim} anggota={anggota} ruanganList={ruanganList} userId={userId} onSubmitted={() => setSuccessOpen(true)} />
                : <RiwayatTab pengajuan={pengajuan} />}
        </div>
    );
}

/** Slot ditawarkan per 1 jam; backend meng-clamp ke max_duration_minutes ruangan. */
const SLOT_MENIT = 60;

/* ---------------- Tab Ajukan ---------------- */

function AjukanTab({ tim, anggota, ruanganList, userId, onSubmitted }: {
    tim: Tim | null; anggota: Anggota[]; ruanganList: RuanganEntry[]; userId: number;
    onSubmitted?: () => void;
}) {
    const preview = usePage<SharedProps>().props.preview;
    const ketersediaanBase = preview?.ketersediaan_url ?? route('mahasiswa.ruangan.ketersediaan');
    const [ruanganIdx, setRuanganIdx] = useState(0);
    const entry = ruanganList[ruanganIdx] ?? null;
    const minAdv = entry?.aturan?.min_advance_days ?? 1;
    const maxAdv = entry?.aturan?.max_advance_days ?? 2;
    const tanggalMin = new Date(Date.now() + minAdv * 86400000).toISOString().slice(0, 10);
    const tanggalMax = new Date(Date.now() + maxAdv * 86400000).toISOString().slice(0, 10);
    const [tanggal, setTanggal] = useState(tanggalMin);
    const [avail, setAvail] = useState<KetersediaanResp | null>(null);
    const [availState, setAvailState] = useState<'idle' | 'loading' | 'error'>('idle');
    const [availErr, setAvailErr] = useState<string | null>(null);
    const [selRange, setSelRange] = useState<{ start: number; end: number } | null>(null);
    const [modalPeserta, setModalPeserta] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    const form = useForm({
        ruangan_id: entry?.model.id ?? 0,
        tanggal,
        jam_mulai: '',
        jam_selesai: '',
        tujuan: '',
        peserta: [userId] as number[],
    });

    const maxPeserta = entry?.aturan.max_peserta ?? entry?.model.kapasitas ?? null;

    const loadAvail = useCallback(async (rId: number, tgl: string) => {
        abortRef.current?.abort();
        const ctrl = new AbortController();
        abortRef.current = ctrl;
        setAvailState('loading');
        setAvailErr(null);
        try {
            // Slot 1 jam: backend meng-clamp ke max_duration_minutes ruangan bila lebih kecil.
            const url = `${ketersediaanBase}?ruangan_id=${rId}&tanggal=${tgl}&durasi=${SLOT_MENIT}`;
            const json = await apiGet<{ ketersediaan: KetersediaanResp }>(url, ctrl.signal);
            setAvail(json.ketersediaan);
            setAvailState('idle');
        } catch (e) {
            if ((e as Error).name === 'AbortError') return;
            setAvail(null);
            setAvailState('error');
            setAvailErr('Gagal memuat ketersediaan. Periksa koneksi lalu coba lagi.');
        }
    }, [ketersediaanBase]);

    // Muat ulang slot saat ruangan/tanggal berubah; hapus pilihan tak valid.
    useEffect(() => {
        if (!entry) return;
        setSelRange(null);
        loadAvail(entry.model.id, tanggal);
    }, [entry, tanggal, loadAvail]);

    // Validasi ulang rentang pilihan setelah data baru tiba.
    useEffect(() => {
        if (!avail || !selRange) return;
        const valid = selRange.end < avail.slots.length
            && avail.slots.slice(selRange.start, selRange.end + 1).every((s) => s.status === 'tersedia');
        if (!valid) setSelRange(null);
    }, [avail, selRange]);

    if (!tim) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center gap-2 py-14 text-center">
                    <Users className="text-sb-muted/50" size={30} />
                    <p className="text-[15px] font-bold">Belum ada tim PBL</p>
                    <p className="max-w-md text-[13px] text-sb-muted">
                        Pengajuan ruangan memerlukan keanggotaan tim PBL. Hubungi dosen pembimbing atau laboran.
                    </p>
                </CardContent>
            </Card>
        );
    }

    // Maks. jam berturutan: kebijakan 5 jam, dibatasi aturan ruangan.
    const maksJam = Math.max(1, Math.min(5, Math.floor((avail?.max_duration_minutes ?? 60) / SLOT_MENIT)));

    const pilihSlot = (idx: number) => {
        const slots = avail?.slots ?? [];
        if (slots[idx]?.status !== 'tersedia') return;
        let next: { start: number; end: number } | null;
        if (!selRange) {
            next = { start: idx, end: idx };
        } else if (idx >= selRange.start && idx <= selRange.end) {
            // Klik di dalam rentang → jadikan ujung baru; klik slot tunggal → hapus.
            next = idx === selRange.start && idx === selRange.end ? null : { start: selRange.start, end: idx };
        } else {
            // Perluas ke idx hanya bila seluruh slot di antaranya tersedia & ≤ maksJam;
            // bila tidak, mulai rentang baru dari idx (tidak melintasi slot terkunci).
            const from = Math.min(selRange.start, idx);
            const to = Math.max(selRange.end, idx);
            const mulus = slots.slice(from, to + 1).every((s) => s.status === 'tersedia');
            next = mulus && to - from + 1 <= maksJam ? { start: from, end: to } : { start: idx, end: idx };
        }
        setSelRange(next);
        const mulai = next ? slots[next.start].mulai : '';
        const selesai = next ? slots[next.end].selesai : '';
        form.setData({ ...form.data, ruangan_id: entry!.model.id, tanggal, jam_mulai: mulai, jam_selesai: selesai });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, ruangan_id: entry!.model.id, tanggal }));
        form.post(route('mahasiswa.ruangan.store'), {
            preserveScroll: true,
            onSuccess: () => onSubmitted?.(),
        });
    };

    const pesertaTerpilih = anggota.filter((a) => form.data.peserta.includes(a.id));
    const firstError = Object.values(form.errors)[0];
    const selSlots = selRange && avail ? avail.slots.slice(selRange.start, selRange.end + 1) : [];
    const totalJam = selSlots.length;
    const waktuLabel = selSlots.length > 0
        ? `${selSlots[0].mulai} – ${selSlots[selSlots.length - 1].selesai} WIB · ${totalJam} jam`
        : 'Pilih slot';

    return (
        <form onSubmit={submit} className="grid items-start gap-4 xl:grid-cols-[7fr_5fr]">
            {/* Kiri: ruangan + jadwal */}
            <div className="space-y-4">
                <Card>
                    <CardHeader className="pb-3">
                        <div>
                            <CardTitle>Pilih Ruangan</CardTitle>
                            <CardDescription>{ruanganList.length} ruangan aktif untuk tim {tim.nama}</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-2.5 sm:grid-cols-2">
                        {ruanganList.map((r, i) => (
                            <button key={r.model.id} type="button" onClick={() => setRuanganIdx(i)}
                                aria-pressed={i === ruanganIdx}
                                className={cn('flex items-center gap-3 rounded-[12px] border p-3 text-left transition',
                                    i === ruanganIdx ? 'border-sb-primary bg-sb-primary-soft/50 ring-1 ring-sb-primary/30' : 'border-sb-border bg-sb-surface hover:border-sb-primary/40')}>
                                {r.model.foto_url
                                    ? <img src={r.model.foto_url} alt="" className="h-14 w-14 shrink-0 rounded-[9px] object-cover" />
                                    : <span className="grid h-14 w-14 shrink-0 place-items-center rounded-[9px] bg-sb-surface2 text-sb-muted"><DoorOpen size={20} /></span>}
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[13.5px] font-bold">{r.model.nama}</span>
                                    <span className="block text-[11.5px] text-sb-muted">
                                        {[r.model.kapasitas ? `${r.model.kapasitas} orang` : null, ...(r.model.fasilitas ?? []).slice(0, 2)].filter(Boolean).join(' · ') || '—'}
                                    </span>
                                    <span className="mt-0.5 block text-[10.5px] font-semibold text-sb-info">
                                        {r.aturan.jam_buka}–{r.aturan.jam_tutup} · maks {r.aturan.max_duration_minutes / 60} jam
                                    </span>
                                </span>
                            </button>
                        ))}
                        {ruanganList.length === 0 && (
                            <p className="col-span-2 py-6 text-center text-[13px] text-sb-muted">Belum ada ruangan aktif yang dikonfigurasi.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <div>
                            <CardTitle>Pilih Jadwal</CardTitle>
                            <CardDescription>
                                {avail && !avail.boleh
                                    ? avail.alasan ?? 'Tidak tersedia pada tanggal ini.'
                                    : avail ? `Buka ${avail.jam_buka}–${avail.jam_tutup} · ${avail.min_advance_days > 0 ? `pengajuan paling lambat H-${avail.min_advance_days}` : 'bisa untuk hari ini'} · paling jauh H+${avail.max_advance_days}` : 'Pilih tanggal untuk melihat slot jam.'}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="max-w-[240px]">
                            <label htmlFor="rgTanggal" className="mb-1.5 block text-[12px] font-bold">Tanggal</label>
                            <Input id="rgTanggal" type="date" value={tanggal} min={tanggalMin} max={tanggalMax}
                                onChange={(e) => setTanggal(e.target.value)} />
                            <p className="mt-1.5 text-[11px] font-semibold text-sb-muted">
                                {minAdv > 0 ? `Pengajuan paling lambat H-${minAdv} (mulai ${tanggalHuman(tanggalMin)})` : 'Bisa diajukan untuk hari ini'} · paling jauh H+{maxAdv}
                            </p>
                        </div>

                        {/* Grid slot */}
                        <div aria-live="polite" aria-busy={availState === 'loading'}>
                            {availState === 'loading' && (
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                    {Array.from({ length: 8 }).map((_, i) => <Skeleton key={i} className="h-12" />)}
                                </div>
                            )}
                            {availState === 'error' && (
                                <div className="rounded-[10px] border border-sb-danger/30 bg-sb-danger-bg/40 p-4 text-center">
                                    <p className="text-[12.5px] font-medium text-sb-danger">{availErr}</p>
                                    <Button type="button" variant="ghost" size="sm" className="mt-2"
                                        onClick={() => entry && loadAvail(entry.model.id, tanggal)}>Coba Lagi</Button>
                                </div>
                            )}
                            {availState === 'idle' && avail && (
                                avail.boleh ? (
                                    <div className="space-y-3" role="radiogroup" aria-label="Slot waktu tersedia">
                                        <div className="flex flex-wrap items-baseline justify-between gap-1">
                                            <p className="text-[12px] font-bold">Pilih Jam</p>
                                            <p className="text-[11px] font-semibold text-sb-muted">maks. {maksJam} jam berurutan · klik awal &amp; akhir rentang</p>
                                        </div>
                                        {avail.habis_alasan && (
                                            <p className="rounded-[10px] border border-sb-warn/40 bg-sb-warn-bg/40 px-3.5 py-2.5 text-center text-[12.5px] font-medium text-sb-text2">
                                                {avail.habis_alasan}
                                            </p>
                                        )}
                                        {groupSlotsBySession(avail.slots).map((g) => (
                                            <div key={g.label}>
                                                <p className="mb-1.5 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-sb-muted">
                                                    {g.label}
                                                    <span className="h-px flex-1 bg-sb-border" aria-hidden="true" />
                                                </p>
                                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                                    {g.slots.map((s) => {
                                                        const idx = avail.slots.indexOf(s);
                                                        const dipilih = selRange !== null && idx >= selRange.start && idx <= selRange.end;
                                                        return (
                                                        <button key={s.mulai} type="button" role="checkbox" aria-checked={dipilih}
                                                            disabled={s.status !== 'tersedia'}
                                                            onClick={() => pilihSlot(idx)}
                                                            title={s.status === 'dipesan' ? `Sudah dibooking ${s.info ?? 'tim lain'} (${s.mulai}–${s.selesai})` : undefined}
                                                            className={cn('rounded-[10px] border px-2 py-2 text-center transition',
                                                                dipilih ? 'border-sb-primary bg-sb-primary text-white shadow-sm'
                                                                : s.status === 'tersedia' ? 'border-emerald-500/40 bg-emerald-500/8 text-sb-text hover:border-emerald-500 hover:bg-emerald-500/15'
                                                                : 'cursor-not-allowed border-sb-border/60 bg-sb-surface2 text-sb-muted/60')}>
                                                            <span className="block font-mono text-[12.5px] font-bold">{s.mulai} – {s.selesai}</span>
                                                            <span className="block truncate text-[10px]">
                                                                {dipilih ? 'Pilihan Anda'
                                                                : s.status === 'tersedia' ? 'Tersedia'
                                                                : s.status === 'dipesan' ? `Dipesan ${s.info ?? 'tim lain'}`
                                                                : s.status === 'petugas' ? 'Jadwal petugas' : 'Sudah lewat'}
                                                            </span>
                                                        </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        ))}
                                        {avail.slots.length === 0 && (
                                            <p className="py-5 text-center text-[12.5px] text-sb-muted">Tidak ada slot pada tanggal ini.</p>
                                        )}
                                        {/* Legenda status */}
                                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-[11px] font-semibold text-sb-muted">
                                            <span className="inline-flex items-center gap-1.5"><i className="h-2.5 w-2.5 rounded-[3px] border border-emerald-500/50 bg-emerald-500/15" /> Tersedia</span>
                                            <span className="inline-flex items-center gap-1.5"><i className="h-2.5 w-2.5 rounded-[3px] border border-sb-border/60 bg-sb-surface2" /> Sudah dipesan / diblokir</span>
                                            <span className="inline-flex items-center gap-1.5"><i className="h-2.5 w-2.5 rounded-[3px] bg-sb-primary" /> Pilihan Anda</span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="rounded-[10px] border border-sb-border bg-sb-surface2 p-4 text-center">
                                        <p className="text-[12.5px] font-medium text-sb-text2">{avail.alasan}</p>
                                        <p className="mt-1.5 text-[11.5px] text-sb-muted">
                                            {[avail.hari_label && avail.hari_label !== 'Setiap hari' ? `Beroperasi ${avail.hari_label}` : null,
                                                `${avail.jam_buka}–${avail.jam_tutup}`,
                                                avail.min_advance_days > 0 ? `pengajuan paling lambat H-${avail.min_advance_days}` : 'bisa untuk hari ini',
                                                `paling jauh H+${avail.max_advance_days}`].filter(Boolean).join(' · ')}
                                        </p>
                                        {avail.tanggal_tersedia_berikutnya && (
                                            <p className="mt-1 text-[11.5px] font-semibold text-sb-info">
                                                Hari operasional berikutnya: {tanggalHuman(avail.tanggal_tersedia_berikutnya)}
                                            </p>
                                        )}
                                    </div>
                                )
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Kanan: detail pengajuan */}
            <Card className="xl:sticky xl:top-4">
                <CardHeader className="pb-3">
                    <div>
                        <CardTitle>Detail Pengajuan</CardTitle>
                        <CardDescription>Periksa lalu kirim — Laboran yang memutuskan.</CardDescription>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <dl className="space-y-1.5 rounded-[10px] bg-sb-surface2 p-3.5 text-[12.5px]">
                        <div className="flex justify-between"><dt className="text-sb-muted">Ruangan</dt><dd className="font-semibold">{entry?.model.nama ?? '—'}</dd></div>
                        <div className="flex justify-between"><dt className="text-sb-muted">Tanggal</dt><dd className="font-semibold">{tanggalSingkat(tanggal)}</dd></div>
                        <div className="flex justify-between"><dt className="text-sb-muted">Waktu</dt><dd className="text-right font-mono font-semibold">{waktuLabel}</dd></div>
                        <div className="flex justify-between"><dt className="text-sb-muted">Tim</dt><dd className="font-semibold">{tim.nama}</dd></div>
                    </dl>

                    {/* Peserta */}
                    <div>
                        <div className="mb-1.5 flex items-center justify-between">
                            <span className="text-[12px] font-bold">Peserta ({form.data.peserta.length}{maxPeserta ? `/${maxPeserta}` : ''})</span>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setModalPeserta(true)}>
                                <UserPlus size={13} /> Tambah peserta
                            </Button>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {pesertaTerpilih.map((a) => (
                                <span key={a.id} className="flex items-center gap-1.5 rounded-full border border-sb-border bg-sb-surface2 py-1 pl-1 pr-2 text-[12px] font-medium">
                                    <Avatar nama={a.nama_lengkap} src={a.foto ?? a.profile_photo_url} className="h-[18px] w-[18px]" />
                                    {a.nama_lengkap?.split(' ')[0] ?? '—'}
                                    <button type="button" aria-label={`Hapus ${a.nama_lengkap}`}
                                        onClick={() => form.setData('peserta', form.data.peserta.filter((id) => id !== a.id))}
                                        className="text-sb-muted hover:text-sb-danger"><X size={11} /></button>
                                </span>
                            ))}
                            {pesertaTerpilih.length === 0 && <span className="text-[12px] text-sb-muted">Belum ada peserta.</span>}
                        </div>
                        {form.errors.peserta && <p className="mt-1.5 text-[12px] font-medium text-sb-danger">{form.errors.peserta}</p>}
                    </div>

                    <div>
                        <label htmlFor="rgTujuan" className="mb-1.5 block text-[12px] font-bold">Tujuan Penggunaan</label>
                        <Textarea id="rgTujuan" rows={3} maxLength={500} value={form.data.tujuan}
                            onChange={(e) => form.setData('tujuan', e.target.value)}
                            placeholder="Contoh: rapat progres PBL minggu ke-8…" />
                        {form.errors.tujuan && <p className="mt-1.5 text-[12px] font-medium text-sb-danger">{form.errors.tujuan}</p>}
                    </div>

                    {firstError && !form.errors.tujuan && !form.errors.peserta && (
                        <p role="alert" className="rounded-[9px] border border-sb-danger/30 bg-sb-danger-bg/50 px-3 py-2 text-[12px] font-medium text-sb-danger">{firstError}</p>
                    )}

                    <Button type="submit" className="w-full" loading={form.processing}
                        disabled={!selRange || !form.data.jam_selesai || form.data.peserta.length === 0 || !form.data.tujuan.trim()}>
                        <Send size={15} /> Periksa &amp; Kirim Pengajuan
                    </Button>
                    <p className="text-center text-[11px] text-sb-muted">
                        Backend memvalidasi ulang kapasitas, jadwal, dan keanggotaan sebelum pengajuan diteruskan ke Laboran.
                    </p>
                </CardContent>
            </Card>

            {/* Modal pilih peserta */}
            <PesertaModal open={modalPeserta} onClose={() => setModalPeserta(false)}
                anggota={anggota} selected={form.data.peserta} max={maxPeserta}
                onApply={(ids) => form.setData('peserta', ids)} />
        </form>
    );
}

function PesertaModal({ open, onClose, anggota, selected, max, onApply }: {
    open: boolean; onClose: () => void; anggota: Anggota[];
    selected: number[]; max: number | null; onApply: (ids: number[]) => void;
}) {
    const [q, setQ] = useState('');
    const [ids, setIds] = useState<number[]>(selected);
    useEffect(() => { if (open) { setIds(selected); setQ(''); } }, [open, selected]);

    const filtered = useMemo(() => {
        const n = q.trim().toLowerCase();
        return n ? anggota.filter((a) => `${a.nama_lengkap} ${a.nitk_nidn ?? ''}`.toLowerCase().includes(n)) : anggota;
    }, [anggota, q]);

    const toggle = (id: number) => {
        setIds((cur) => cur.includes(id) ? cur.filter((x) => x !== id)
            : max && cur.length >= max ? cur : [...cur, id]);
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent title="Tambah Peserta" description={`Pilih anggota tim${max ? ` — maksimal ${max} peserta` : ''}.`}>
                <div className="space-y-3 px-5 py-4">
                    <div className="relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-sb-muted" />
                        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari nama / NIM…" className="pl-8" autoFocus />
                    </div>
                    <div className="flex items-center justify-between text-[12px]">
                        <span className="font-semibold text-sb-muted">{ids.length} dipilih{max ? ` dari ${max}` : ''}</span>
                        {max && anggota.length <= max && (
                            <button type="button" onClick={() => setIds(ids.length === anggota.length ? [] : anggota.map((a) => a.id))}
                                className="font-semibold text-sb-primary hover:underline">
                                {ids.length === anggota.length ? 'Kosongkan' : 'Pilih semua anggota tim'}
                            </button>
                        )}
                    </div>
                    <ul className="max-h-[300px] space-y-1 overflow-y-auto" role="listbox" aria-multiselectable>
                        {filtered.map((a) => {
                            const on = ids.includes(a.id);
                            const full = !!max && !on && ids.length >= max;
                            return (
                                <li key={a.id}>
                                    <button type="button" role="option" aria-selected={on} disabled={full}
                                        onClick={() => toggle(a.id)}
                                        className={cn('flex w-full items-center gap-3 rounded-[10px] border p-2.5 text-left transition',
                                            on ? 'border-sb-primary/50 bg-sb-primary-soft/60'
                                            : full ? 'cursor-not-allowed border-sb-border/50 opacity-50'
                                            : 'border-sb-border hover:border-sb-primary/40')}>
                                        <Avatar nama={a.nama_lengkap} src={a.foto ?? a.profile_photo_url} className="h-8 w-8" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-[13px] font-semibold">{a.nama_lengkap}</span>
                                            <span className="block font-mono text-[11px] text-sb-muted">{a.nitk_nidn ?? '—'}</span>
                                        </span>
                                        <span className={cn('grid h-5 w-5 place-items-center rounded-[6px] border',
                                            on ? 'border-sb-primary bg-sb-primary text-white' : 'border-sb-border')}>
                                            {on && <CheckCircle2 size={12} />}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                        {filtered.length === 0 && <li className="py-6 text-center text-[12.5px] text-sb-muted">Tidak ada anggota yang cocok.</li>}
                    </ul>
                    <Button className="w-full" onClick={() => { onApply(ids); onClose(); }}>
                        Pakai {ids.length} Peserta
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/* ---------------- Tab Riwayat ---------------- */

const PERIODE_OPSI = [
    ['semua', 'Semua Periode'],
    ['7hari', '7 Hari Terakhir'],
    ['30hari', '30 Hari Terakhir'],
    ['bulanini', 'Bulan Ini'],
] as const;

function RiwayatTab({ pengajuan }: { pengajuan: Pengajuan[] }) {
    const [detailId, setDetailId] = useState<number | null>(null);
    const act = useForm({});
    // Simpan id saja — objek detail diambil dari props agar ikut segar saat reload realtime.
    const detail = useMemo(() => pengajuan.find((p) => p.id === detailId) ?? null, [pengajuan, detailId]);
    const setDetail = (p: Pengajuan | null) => setDetailId(p?.id ?? null);

    const [q, setQ] = useState('');
    const [periode, setPeriode] = useState<string>('semua');
    const [ruanganId, setRuanganId] = useState('semua');
    const [status, setStatus] = useState('semua');
    const [perPage, setPerPage] = useState(6);
    const [page, setPage] = useState(1);

    const ruanganOpsi = useMemo(() => {
        const map = new Map<number, string>();
        pengajuan.forEach((p) => { if (p.ruangan?.id) map.set(p.ruangan.id, p.ruangan.nama); });
        return [...map.entries()].sort((a, b) => a[1].localeCompare(b[1]));
    }, [pengajuan]);

    const filtered = useMemo(() => pengajuan.filter((p) => {
        if (q && !(p.ruangan?.nama ?? '').toLowerCase().includes(q.toLowerCase())) return false;
        if (ruanganId !== 'semua' && String(p.ruangan?.id) !== ruanganId) return false;
        if (status !== 'semua' && p.status_efektif !== status) return false;
        if (periode !== 'semua') {
            const tgl = new Date(p.tanggal);
            const now = new Date();
            if (periode === '7hari' && (now.getTime() - tgl.getTime()) > 7 * 86400000) return false;
            if (periode === '30hari' && (now.getTime() - tgl.getTime()) > 30 * 86400000) return false;
            if (periode === 'bulanini' && (tgl.getMonth() !== now.getMonth() || tgl.getFullYear() !== now.getFullYear())) return false;
        }
        return true;
    }), [pengajuan, q, periode, ruanganId, status]);

    const pageCount = Math.max(1, Math.ceil(filtered.length / perPage));
    const cur = Math.min(page, pageCount);
    const pageItems = filtered.slice((cur - 1) * perPage, cur * perPage);

    useEffect(() => { setPage(1); }, [q, periode, ruanganId, status, perPage]);

    const bolehBatal = (p: Pengajuan) => ['diajukan', 'menunggu_penerimaan', 'disetujui'].includes(p.status)
        && !['dibatalkan', 'ditolak', 'selesai', 'kedaluwarsa'].includes(p.status_efektif);

    const pesertaCount = (p: Pengajuan) => p.snapshot?.peserta_nama?.length ?? p.peserta?.length ?? 0;

    return (
        <div className="space-y-4">
            {/* Filter bar */}
            <Card>
                <CardContent className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-sb-muted" />
                        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari nama ruangan…" className="pl-9" />
                    </div>
                    <Select value={periode} onChange={(e) => setPeriode(e.target.value)}>
                        {PERIODE_OPSI.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </Select>
                    <Select value={ruanganId} onChange={(e) => setRuanganId(e.target.value)}>
                        <option value="semua">Semua Ruangan</option>
                        {ruanganOpsi.map(([id, nama]) => <option key={id} value={String(id)}>{nama}</option>)}
                    </Select>
                    <Select value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="semua">Semua Status</option>
                        {Object.entries(STATUS_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </Select>
                </CardContent>
            </Card>

            {/* Grid kartu */}
            {pageItems.length > 0 ? (
                <div className="grid gap-4 md:grid-cols-2">
                    {pageItems.map((p) => (
                        <div key={p.id} className="flex gap-3.5 rounded-[14px] border border-sb-border bg-sb-surface p-3 transition hover:border-sb-primary/40">
                            <div className="relative h-[104px] w-[120px] shrink-0 overflow-hidden rounded-[10px] bg-gradient-to-br from-sb-primary/80 to-sb-accent/70">
                                {p.ruangan?.foto_url
                                    ? <img src={p.ruangan.foto_url} alt={p.ruangan.nama} className="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                                    : <span className="absolute inset-0 grid place-items-center text-white/90"><DoorOpen size={26} /></span>}
                                <span className="absolute bottom-1.5 left-1.5 rounded-[6px] bg-black/45 px-1.5 py-0.5 text-[9.5px] font-bold text-white backdrop-blur-sm">{p.ruangan?.kode ?? '—'}</span>
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col">
                                <div className="flex items-start justify-between gap-2">
                                    <p className="min-w-0 truncate text-[13.5px] font-bold">{p.ruangan?.nama ?? '—'}</p>
                                    <Badge tone={toneFor(p.status_efektif)} className="shrink-0">{statusLabel(p)}</Badge>
                                </div>
                                <div className="mt-1.5 space-y-1 text-[11.5px] text-sb-text2">
                                    <p className="flex items-center gap-1.5"><CalendarDays size={12.5} className="text-sb-muted" /> {tanggalHuman(p.tanggal)}</p>
                                    <p className="flex items-center gap-1.5"><Clock size={12.5} className="text-sb-muted" /> {hhmm(p.jam_mulai)} – {hhmm(p.jam_selesai)}</p>
                                    <p className="flex items-center gap-1.5"><Users size={12.5} className="text-sb-muted" /> {pesertaCount(p)} peserta</p>
                                </div>
                                <Button variant="outline" size="sm" className="mt-auto w-full justify-center" onClick={() => setDetail(p)}>
                                    Lihat Detail <ChevronRight size={13} />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <Card>
                    <CardContent className="py-12 text-center text-[13px] text-sb-muted">
                        <History className="mx-auto mb-2 text-sb-muted/50" size={26} />
                        {pengajuan.length === 0
                            ? 'Belum ada pengajuan — ajukan ruangan lewat tab di atas.'
                            : 'Tidak ada riwayat yang cocok dengan filter.'}
                    </CardContent>
                </Card>
            )}

            {/* Pagination */}
            {filtered.length > 0 && (
                <div className="flex flex-wrap items-center gap-3 text-[12px] text-sb-muted">
                    <span>Menampilkan {(cur - 1) * perPage + 1}–{Math.min(cur * perPage, filtered.length)} dari {filtered.length} riwayat</span>
                    <div className="mx-auto flex items-center gap-1">
                        <button type="button" disabled={cur <= 1} onClick={() => setPage(cur - 1)}
                            className="grid h-8 w-8 place-items-center rounded-[8px] border border-sb-border text-sb-muted transition hover:border-sb-primary/40 hover:text-sb-text disabled:opacity-40">
                            <ChevronRight size={14} className="rotate-180" />
                        </button>
                        {Array.from({ length: pageCount }, (_, i) => i + 1).map((n) => (
                            <button key={n} type="button" onClick={() => setPage(n)}
                                className={cn('h-8 min-w-8 rounded-[8px] border px-2 text-[12px] font-bold transition',
                                    n === cur ? 'border-sb-primary bg-sb-primary text-white' : 'border-sb-border text-sb-muted hover:border-sb-primary/40 hover:text-sb-text')}>
                                {n}
                            </button>
                        ))}
                        <button type="button" disabled={cur >= pageCount} onClick={() => setPage(cur + 1)}
                            className="grid h-8 w-8 place-items-center rounded-[8px] border border-sb-border text-sb-muted transition hover:border-sb-primary/40 hover:text-sb-text disabled:opacity-40">
                            <ChevronRight size={14} />
                        </button>
                    </div>
                    <label className="flex items-center gap-1.5">
                        Tampilkan
                        <Select value={String(perPage)} onChange={(e) => setPerPage(Number(e.target.value))} className="h-8 w-auto py-0 text-[12px]">
                            {[6, 12, 24].map((n) => <option key={n} value={n}>{n} per halaman</option>)}
                        </Select>
                    </label>
                </div>
            )}

            <Dialog open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
                <DialogContent
                    title={
                        <span className="flex items-center gap-3 pr-6">
                            Pengajuan #{detail?.id}
                            {detail && <StatusStamp status={detail.status_efektif} />}
                        </span>
                    }
                    description={detail ? statusLabel(detail) : ''}
                >
                    {detail && (
                        <div className="space-y-4 px-5 py-4">
                            <Stepper status={detail.status_efektif} />
                            <dl className="grid grid-cols-2 gap-2.5 text-[12.5px]">
                                {[
                                    ['Ruangan', detail.ruangan?.nama ?? '—'],
                                    ['Tanggal', tanggalHuman(detail.tanggal)],
                                    ['Waktu', `${hhmm(detail.jam_mulai)} – ${hhmm(detail.jam_selesai)}`],
                                    ['Status', statusLabel(detail)],
                                    ['Pemohon', detail.pengaju_nama ?? '—'],
                                    ['Tim PBL', detail.tim_nama ?? '—'],
                                ].map(([k, v]) => (
                                    <div key={k} className="rounded-[9px] bg-sb-surface2 px-3 py-2">
                                        <dt className="text-[10.5px] font-bold uppercase text-sb-muted">{k}</dt>
                                        <dd className="mt-0.5 font-semibold">{v}</dd>
                                    </div>
                                ))}
                            </dl>
                            {detail.status === 'menunggu_penerimaan' && detail.pengganti_mulai && (
                                <div className="rounded-[10px] border border-sb-warn/40 bg-sb-warn-bg/50 p-3.5 text-[12.5px]">
                                    <p className="font-bold">Waktu pengganti ditawarkan</p>
                                    <p className="mt-0.5 font-mono">
                                        {new Date(detail.pengganti_mulai).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false })}
                                        {' – '}
                                        {detail.pengganti_selesai ? new Date(detail.pengganti_selesai).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }) : '?'}
                                    </p>
                                    {detail.pengganti_catatan && <p className="mt-1 text-sb-text2">{detail.pengganti_catatan}</p>}
                                    <Button size="sm" className="mt-3" loading={act.processing}
                                        onClick={() => act.post(route('mahasiswa.ruangan.terima-pengganti', detail.id), {
                                            onSuccess: () => setDetail(null),
                                        })}>
                                        Terima Waktu Pengganti
                                    </Button>
                                </div>
                            )}
                            <div className="text-[12.5px]">
                                <p className="font-bold">Tujuan</p>
                                <p className="mt-0.5 text-sb-text2">{detail.tujuan}</p>
                            </div>
                            {detail.diputus_oleh_nama && (
                                <div className="text-[12.5px]">
                                    <p className="font-bold">Diputus oleh</p>
                                    <p className="mt-0.5 text-sb-text2">
                                        {detail.diputus_oleh_nama}
                                        {detail.diputus_oleh_role && ` (${detail.diputus_oleh_role === 'dosen' ? 'Dosen' : 'Laboran'})`}
                                        {detail.diputus_pada && ` · ${new Date(detail.diputus_pada).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}`}
                                    </p>
                                </div>
                            )}
                            {detail.alasan_keputusan && (
                                <div className="rounded-[9px] bg-sb-surface2 p-3 text-[12.5px]">
                                    <p className="font-bold">Catatan keputusan</p>
                                    <p className="mt-0.5 text-sb-text2">{detail.alasan_keputusan}</p>
                                </div>
                            )}
                            {bolehBatal(detail) && (
                                <Button variant="danger" size="sm" loading={act.processing}
                                    onClick={() => {
                                        if (confirm('Batalkan pengajuan ini?')) {
                                            act.post(route('mahasiswa.ruangan.batal', detail.id), { onSuccess: () => setDetail(null) });
                                        }
                                    }}>
                                    Batalkan Pengajuan
                                </Button>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

RoomRequest.layout = (page: React.ReactNode) => <AppShell title="Ruangan">{page}</AppShell>;
