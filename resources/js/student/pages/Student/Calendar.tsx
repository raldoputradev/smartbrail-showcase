import { useMemo, useState } from 'react';
import {
    AlertTriangle, CalendarDays, CalendarOff, ChevronDown, ChevronLeft, ChevronRight, ChevronRight as ChevronRightSm,
    Clock, DoorOpen, FileText, Info, UserCheck, X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { router } from '@inertiajs/react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import { Card, CardContent } from '@/components/ui/card';
import { MiniCalendar } from '@/components/MiniCalendar';
import type { CalendarData, CalDay, SharedProps } from '@/types';
import { cn } from '@/lib/utils';
import { hhmm } from '@/lib/format';

interface Props extends SharedProps {
    calendar: CalendarData;
    scheduleMeta: { active_label?: string; pending_weekly?: boolean; upcoming_weekly?: string };
}

type Prio = 'tinggi' | 'sedang' | 'normal' | 'rutin';

const PRIO_META: Record<Prio, { label: string; dot: string; pill: string; iconBox: string; bar: string }> = {
    tinggi: { label: 'Tinggi', dot: 'bg-sb-danger', pill: 'bg-sb-danger-bg text-sb-danger border-sb-danger/25', iconBox: 'bg-sb-danger-bg text-sb-danger', bar: 'bg-sb-danger' },
    sedang: { label: 'Sedang', dot: 'bg-sb-warn', pill: 'bg-sb-warn-bg text-sb-warn border-sb-warn/25', iconBox: 'bg-sb-warn-bg text-sb-warn', bar: 'bg-sb-warn' },
    normal: { label: 'Normal', dot: 'bg-sb-info', pill: 'bg-sb-info-bg text-sb-info border-sb-info/25', iconBox: 'bg-sb-info-bg text-sb-info', bar: 'bg-sb-info' },
    rutin:  { label: 'Rutin',  dot: 'bg-sb-ok',    pill: 'bg-sb-ok-bg text-sb-ok border-sb-ok/25',           iconBox: 'bg-sb-ok-bg text-sb-ok',       bar: 'bg-sb-ok' },
};

const AGENDA_META: Record<string, { prio: Prio; icon: LucideIcon }> = {
    libur:         { prio: 'tinggi', icon: CalendarOff },
    peringatan:    { prio: 'tinggi', icon: AlertTriangle },
    ujian:         { prio: 'tinggi', icon: FileText },
    perubahan_jam: { prio: 'sedang', icon: Clock },
    agenda:        { prio: 'sedang', icon: CalendarDays },
    informasi:     { prio: 'normal', icon: Info },
};

interface DetailItem {
    key: string;
    icon: LucideIcon;
    name: string;
    time?: string;
    prio: Prio;
    pill?: string;
}

const BULAN_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const BULAN_FULL = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function shiftMonth(bulan: string, dir: number): string {
    const [y, m] = bulan.split('-').map(Number);
    const d = new Date(y, m - 1 + dir, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function dayLabel(tanggal: string): { day: string; mon: string } {
    const [y, m, d] = tanggal.split('-').map(Number);
    return { day: String(d), mon: BULAN_SHORT[m - 1] ?? String(m) };
}

/** Susun item "Detail Tanggal" untuk satu hari kalender. */
function itemsOfDay(d: CalDay): DetailItem[] {
    const items: DetailItem[] = [];
    const hasLiburAgenda = d.agenda.some((a) => a.jenis === 'libur');
    if (d.is_libur && !hasLiburAgenda) {
        items.push({ key: 'libur', icon: CalendarOff, name: 'Libur', time: d.sumber_label || undefined, prio: 'tinggi', pill: 'Libur' });
    }
    if (d.is_wajib && !d.is_libur) {
        items.push({
            key: 'absensi', icon: UserCheck, name: 'Absensi Perkuliahan',
            time: `${hhmm(d.jam_masuk)} – ${hhmm(d.jam_pulang)}`, prio: 'rutin', pill: 'Rutin',
        });
    }
    (d.ruangan ?? []).forEach((r, i) => {
        items.push({ key: `rg-${i}`, icon: DoorOpen, name: r.ruangan, time: r.jam, prio: 'sedang', pill: r.status_label });
    });
    (d.agenda ?? []).forEach((a) => {
        const meta = AGENDA_META[a.jenis] ?? { prio: 'normal' as Prio, icon: Info };
        items.push({ key: `ag-${a.id}`, icon: meta.icon, name: a.keterangan, time: a.jenis_label, prio: meta.prio, pill: a.jenis_label });
    });
    return items;
}

export default function Calendar({ calendar, scheduleMeta }: Props) {
    const days = calendar.days ?? [];
    const [selected, setSelected] = useState<string | null>(days.find((d) => d.is_today)?.tanggal ?? null);
    const sel = days.find((d) => d.tanggal === selected);
    const selItems = sel ? itemsOfDay(sel) : [];
    const todayStr = days.find((d) => d.is_today)?.tanggal ?? null;

    const [pickOpen, setPickOpen] = useState(false);
    const [pickYear, setPickYear] = useState(() => Number(calendar.bulan.split('-')[0]));

    const go = (dir: number) => {
        setSelected(null);
        router.get(route('mahasiswa.calendar'), { bulan: shiftMonth(calendar.bulan, dir) }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };
    const gotoMonth = (ym: string) => {
        setSelected(null); setPickOpen(false);
        router.get(route('mahasiswa.calendar'), { bulan: ym }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };
    const goToday = () => {
        setPickOpen(false);
        if (!todayStr) {
            router.get(route('mahasiswa.calendar'), {}, { preserveState: true, preserveScroll: true, replace: true });
        } else {
            setSelected(todayStr);
        }
    };

    // Agenda terdekat: agenda admin + booking ruangan + hari wajib absensi, urut tanggal.
    const upcoming = useMemo(() => {
        const out: Array<{ tanggal: string; name: string; sub: string; prio: Prio }> = [];
        days.forEach((d) => {
            if (todayStr && d.tanggal < todayStr) return;
            (d.agenda ?? []).forEach((a) => {
                const meta = AGENDA_META[a.jenis] ?? { prio: 'normal' as Prio };
                out.push({ tanggal: d.tanggal, name: a.keterangan, sub: `${d.tanggal_human} · ${a.jenis_label}`, prio: meta.prio });
            });
            (d.ruangan ?? []).forEach((r) => {
                out.push({ tanggal: d.tanggal, name: r.ruangan, sub: `${d.tanggal_human} · ${r.jam}`, prio: 'sedang' });
            });
            if (d.is_wajib && !d.is_libur) {
                out.push({ tanggal: d.tanggal, name: 'Absensi Perkuliahan', sub: `${d.tanggal_human} · ${hhmm(d.jam_masuk)} – ${hhmm(d.jam_pulang)}`, prio: 'rutin' });
            }
        });
        out.sort((a, b) => a.tanggal.localeCompare(b.tanggal));
        return out.slice(0, 6);
    }, [days, todayStr]);

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Jadwal' }, { label: 'Kalender' }]} />}
                title="Kalender Kegiatan"
                sub={`Jadwal absensi, agenda laboratorium, hari libur, dan peringatan penting.${scheduleMeta.active_label ? ` ${scheduleMeta.active_label}` : ''}`}
            />

            <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_330px]">
                {/* Kolom kiri — grid kalender + agenda terdekat */}
                <div className="min-w-0 space-y-4">
                    <Card>
                        <CardContent className="pt-5">
                            <div className="mb-4 flex items-center justify-between">
                                <button type="button" onClick={() => go(-1)} aria-label="Bulan sebelumnya"
                                    className="grid h-9 w-9 place-items-center rounded-[9px] border border-sb-border bg-sb-surface text-sb-text2 transition hover:border-sb-primary/50 hover:text-sb-primary">
                                    <ChevronLeft size={16} />
                                </button>
                                <div className="relative">
                                    <button type="button" aria-expanded={pickOpen} aria-label="Pilih bulan"
                                        onClick={() => { setPickYear(Number(calendar.bulan.split('-')[0])); setPickOpen((o) => !o); }}
                                        className="flex items-center gap-1.5 rounded-[9px] px-2.5 py-1.5 text-[16px] font-extrabold transition hover:bg-sb-hover">
                                        {calendar.bulan_label ?? calendar.bulan}
                                        <ChevronDown size={15} className={cn('text-sb-muted transition-transform duration-200', pickOpen && 'rotate-180')} />
                                    </button>
                                    {pickOpen && (
                                        <>
                                            <button type="button" aria-label="Tutup pilih bulan" className="fixed inset-0 z-20 cursor-default" onClick={() => setPickOpen(false)} />
                                            <div className="absolute left-1/2 top-full z-30 mt-2 w-[236px] -translate-x-1/2 rounded-[12px] border border-sb-border bg-sb-surface p-3 shadow-xl">
                                                <div className="mb-2.5 flex items-center justify-between">
                                                    <button type="button" onClick={() => setPickYear((y) => y - 1)} aria-label="Tahun sebelumnya"
                                                        className="grid h-7 w-7 place-items-center rounded-[7px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text">
                                                        <ChevronLeft size={14} />
                                                    </button>
                                                    <p className="text-[13px] font-extrabold">{pickYear}</p>
                                                    <button type="button" onClick={() => setPickYear((y) => y + 1)} aria-label="Tahun berikutnya"
                                                        className="grid h-7 w-7 place-items-center rounded-[7px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text">
                                                        <ChevronRight size={14} />
                                                    </button>
                                                </div>
                                                <div className="grid grid-cols-3 gap-1.5">
                                                    {BULAN_FULL.map((b, i) => {
                                                        const ym = `${pickYear}-${String(i + 1).padStart(2, '0')}`;
                                                        const cur = calendar.bulan === ym;
                                                        return (
                                                            <button key={b} type="button" onClick={() => gotoMonth(ym)}
                                                                className={cn('rounded-[8px] px-1 py-1.5 text-[11.5px] font-bold transition',
                                                                    cur ? 'bg-sb-primary text-white' : 'text-sb-text2 hover:bg-sb-hover')}>
                                                                {b}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={goToday}
                                        className="rounded-[9px] border border-sb-border bg-sb-surface px-3 py-2 text-[12px] font-bold text-sb-text2 transition hover:border-sb-primary/50 hover:text-sb-primary">
                                        Hari Ini
                                    </button>
                                    <button type="button" onClick={() => go(1)} aria-label="Bulan berikutnya"
                                        className="grid h-9 w-9 place-items-center rounded-[9px] border border-sb-border bg-sb-surface text-sb-text2 transition hover:border-sb-primary/50 hover:text-sb-primary">
                                        <ChevronRight size={16} />
                                    </button>
                                </div>
                            </div>

                            <MiniCalendar calendar={calendar} selected={selected} onSelect={setSelected} showDetail={false} hideHeader />

                            <div className="mt-5 flex flex-wrap gap-4 border-t border-sb-border pt-4 text-[11.5px] text-sb-muted">
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-sb-ok" /> Absensi lengkap</span>
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-sb-warn" /> Tidak lengkap / catatan</span>
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-sb-danger" /> Alfa</span>
                                <span className="flex items-center gap-1.5"><span className="grid h-3 w-3 place-items-center rounded-[3px] bg-sb-info-bg text-[7px] font-extrabold text-sb-info">R</span> Penggunaan ruangan</span>
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-sb-info" /> Agenda / info</span>
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-sb-violet" /> Peringatan</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Agenda terdekat */}
                    <Card>
                        <CardContent className="pt-5">
                            <div className="flex items-center justify-between gap-2">
                                <div>
                                    <h3 className="text-[15px] font-extrabold">Agenda Terdekat</h3>
                                    <p className="text-[11.5px] text-sb-muted">
                                        {upcoming.length > 0 ? `${upcoming.length} kegiatan di ${calendar.bulan_label ?? calendar.bulan}.` : `Belum ada agenda di ${calendar.bulan_label ?? calendar.bulan}.`}
                                    </p>
                                </div>
                            </div>
                            <div className="mt-3 space-y-1.5">
                                {upcoming.length === 0 && (
                                    <p className="rounded-[10px] border border-dashed border-sb-border px-3 py-4 text-center text-[12px] text-sb-muted">
                                        Tidak ada agenda terjadwal pada periode ini.
                                    </p>
                                )}
                                {upcoming.map((u, i) => {
                                    const t = dayLabel(u.tanggal);
                                    const meta = PRIO_META[u.prio];
                                    return (
                                        <div key={`${u.tanggal}-${i}`} className="flex items-center gap-3 rounded-[11px] px-1.5 py-2 transition hover:bg-sb-surface2">
                                            <div className="grid h-11 w-10 shrink-0 place-items-center rounded-[10px] bg-sb-surface2 text-center leading-tight">
                                                <div>
                                                    <p className="text-[15px] font-extrabold leading-none">{t.day}</p>
                                                    <p className="mt-0.5 text-[9.5px] font-bold uppercase text-sb-muted">{t.mon}</p>
                                                </div>
                                            </div>
                                            <span className={cn('h-2 w-2 shrink-0 rounded-full', meta.dot, `sb-prio-blink--${u.prio}`)} />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-[13px] font-bold">{u.name}</p>
                                                <p className="truncate text-[11px] text-sb-muted">{u.sub}</p>
                                            </div>
                                            <span className={cn('shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold', meta.pill)}>
                                                {meta.label}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Kolom kanan — detail tanggal + legenda prioritas */}
                <div className="min-w-0 space-y-4">
                    <Card>
                        <CardContent className="pt-5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <CalendarDays size={15} className="text-sb-info" />
                                    <h3 className="text-[14.5px] font-extrabold">Detail Tanggal</h3>
                                </div>
                                {sel && (
                                    <button type="button" onClick={() => setSelected(null)} aria-label="Tutup detail"
                                        className="grid h-7 w-7 place-items-center rounded-[8px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text">
                                        <X size={15} />
                                    </button>
                                )}
                            </div>

                            {!sel && (
                                <p className="mt-4 rounded-[10px] border border-dashed border-sb-border px-3 py-6 text-center text-[12px] text-sb-muted">
                                    Pilih tanggal di kalender untuk melihat rincian kegiatan.
                                </p>
                            )}

                            {sel && (
                                <>
                                    <div className="mt-3 flex items-center gap-3">
                                        <div className="grid w-12 shrink-0 place-items-center rounded-[11px] bg-sb-surface2 py-1.5 text-center leading-tight">
                                            <div>
                                                <p className="text-[10px] font-bold uppercase text-sb-muted">{sel.hari_nama.slice(0, 3)}</p>
                                                <p className="text-[19px] font-extrabold leading-tight">{sel.day_num}</p>
                                            </div>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-[13.5px] font-extrabold">{sel.tanggal_human}</p>
                                            <p className="text-[11.5px] text-sb-muted">
                                                {selItems.length > 0 ? `${selItems.length} kegiatan terjadwal` : 'Tidak ada kegiatan terjadwal'}
                                                {sel.is_today ? ' · Hari ini' : ''}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-3 space-y-2">
                                        {selItems.map((it) => {
                                            const meta = PRIO_META[it.prio];
                                            const Icon = it.icon;
                                            return (
                                                <div key={it.key}
                                                    className="relative flex items-center gap-3 overflow-hidden rounded-[12px] border border-sb-border bg-sb-surface2 px-3 py-2.5">
                                                    <span className={cn('absolute inset-y-0 left-0 w-1', meta.bar)} />
                                                    <span className={cn('grid h-8 w-8 shrink-0 place-items-center rounded-[9px]', meta.iconBox)}>
                                                        <Icon size={15} />
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-[12.5px] font-bold">{it.name}</p>
                                                        {it.time && <p className="truncate text-[11px] text-sb-muted">{it.time}</p>}
                                                    </div>
                                                    {it.pill && (
                                                        <span className={cn('shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold', meta.pill)}>
                                                            {it.pill}
                                                        </span>
                                                    )}
                                                    <ChevronRightSm size={14} className="shrink-0 text-sb-muted/60" />
                                                </div>
                                            );
                                        })}
                                        {sel.absensi && (
                                            <div className="rounded-[12px] border border-sb-border bg-sb-surface2 px-3 py-2.5 text-[12px]">
                                                <p className="font-bold">Rekaman: {sel.absensi.kelengkapan_label}</p>
                                                <p className="font-mono text-[11.5px] text-sb-muted">
                                                    {hhmm(sel.absensi.jam_masuk)} – {hhmm(sel.absensi.jam_pulang)}
                                                </p>
                                                {sel.absensi.ketepatan_masuk_label && <p className="mt-0.5 text-[11px] text-sb-warn">{sel.absensi.ketepatan_masuk_label}</p>}
                                                {sel.absensi.ketepatan_pulang_label && <p className="text-[11px] text-sb-warn">{sel.absensi.ketepatan_pulang_label}</p>}
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Keterangan & prioritas */}
                    <Card>
                        <CardContent className="pt-5">
                            <div className="flex items-center gap-2">
                                <Info size={14} className="text-sb-info" />
                                <h3 className="text-[14.5px] font-extrabold">Keterangan &amp; Prioritas</h3>
                            </div>
                            <div className="mt-3 space-y-3">
                                {([
                                    ['tinggi', 'Kegiatan penting & wajib (ujian, deadline, hari libur).'],
                                    ['sedang', 'Kegiatan operasional (rapat, agenda lab, sosialisasi).'],
                                    ['normal', 'Kegiatan tambahan (informasi, pengumuman, kegiatan tim).'],
                                    ['rutin', 'Jadwal berulang (absensi perkuliahan/praktikum).'],
                                ] as Array<[Prio, string]>).map(([p, desc]) => (
                                    <div key={p} className="flex items-start gap-2.5">
                                        <span className={cn('mt-1 h-2.5 w-2.5 shrink-0 rounded-full', PRIO_META[p].dot)} />
                                        <div>
                                            <p className="text-[12.5px] font-bold">{PRIO_META[p].label}</p>
                                            <p className="text-[11px] leading-snug text-sb-muted">{desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

Calendar.layout = (page: React.ReactNode) => <AppShell title="Kalender">{page}</AppShell>;
