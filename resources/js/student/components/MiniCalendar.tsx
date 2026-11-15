import { useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, CalendarDays } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { CalendarData, CalDay } from '@/types';
import { cn } from '@/lib/utils';
import { hhmm } from '@/lib/format';

const DOW_LABELS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function shiftMonth(bulan: string, dir: number): string {
    const [y, m] = bulan.split('-').map(Number);
    const d = new Date(y, m - 1 + dir, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export interface CalNav {
    bulan: string;          // 'YYYY-MM' đang hiển thị
    label: string;          // 'November 2026'
    busy?: boolean;
    onPrev: () => void;
    onNext: () => void;
    onPick: (bulan: string) => void;
}

export function MiniCalendar({ calendar, compact = false, selected: controlledSelected, onSelect, showDetail = true, hideHeader = false, nav }: {
    calendar: CalendarData;
    compact?: boolean;
    selected?: string | null;
    onSelect?: (tanggal: string | null) => void;
    showDetail?: boolean;
    hideHeader?: boolean;
    nav?: CalNav;
}) {
    const days = calendar.days ?? [];
    const firstDow = days[0] ? (days[0].dow + 6) % 7 : 0; // Senin = 0
    const [internalSelected, setInternalSelected] = useState<string | null>(days.find((d) => d.is_today)?.tanggal ?? null);
    const selected = controlledSelected !== undefined ? controlledSelected : internalSelected;
    const pick = (tanggal: string | null) => (onSelect ? onSelect(tanggal) : setInternalSelected(tanggal));
    const [filter, setFilter] = useState<'semua' | 'absensi' | 'ruangan'>('semua');
    const [pickOpen, setPickOpen] = useState(false);
    const [pickYear, setPickYear] = useState<number | null>(null);

    const sel: CalDay | undefined = useMemo(() => days.find((d) => d.tanggal === selected), [days, selected]);

    const showTag = (d: CalDay, t: 'A' | 'R') =>
        filter === 'semua' || (filter === 'absensi' ? t === 'A' : t === 'R');

    const [navYear, navMonth] = nav ? nav.bulan.split('-').map(Number) : [0, 0];
    const year = pickYear ?? navYear;

    return (
        <div className="relative">
            {nav && (
                <div className="mb-2 flex items-center gap-1">
                    <button type="button" onClick={nav.onPrev} disabled={nav.busy} aria-label="Bulan sebelumnya"
                        className="grid h-7 w-7 place-items-center rounded-[7px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text disabled:opacity-40">
                        <ChevronLeft size={15} />
                    </button>
                    <button type="button" onClick={() => { setPickYear(navYear); setPickOpen((o) => !o); }}
                        className={cn('flex items-center gap-1.5 rounded-[7px] px-2.5 py-1 text-[12.5px] font-bold text-sb-text transition hover:bg-sb-hover',
                            nav.busy && 'animate-pulse opacity-60')}
                        aria-expanded={pickOpen}>
                        <CalendarDays size={13} className="text-sb-muted" />{nav.label}
                    </button>
                    <button type="button" onClick={nav.onNext} disabled={nav.busy} aria-label="Bulan berikutnya"
                        className="grid h-7 w-7 place-items-center rounded-[7px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text disabled:opacity-40">
                        <ChevronRight size={15} />
                    </button>
                </div>
            )}
            {nav && pickOpen && (
                <div className="absolute left-0 top-9 z-50 w-64 rounded-[12px] border border-sb-border bg-sb-surface p-3 shadow-sb-pop">
                    <div className="mb-2 flex items-center justify-between">
                        <button type="button" onClick={() => setPickYear((year ?? navYear) - 1)} aria-label="Tahun sebelumnya"
                            className="grid h-7 w-7 place-items-center rounded-[7px] text-sb-muted hover:bg-sb-hover"><ChevronLeft size={14} /></button>
                        <span className="text-[13px] font-extrabold">{year}</span>
                        <button type="button" onClick={() => setPickYear((year ?? navYear) + 1)} aria-label="Tahun berikutnya"
                            className="grid h-7 w-7 place-items-center rounded-[7px] text-sb-muted hover:bg-sb-hover"><ChevronRight size={14} /></button>
                    </div>
                    <div className="grid grid-cols-4 gap-1">
                        {MONTH_NAMES.map((name, i) => {
                            const active = year === navYear && i + 1 === navMonth;
                            return (
                                <button key={name} type="button"
                                    onClick={() => { setPickOpen(false); nav.onPick(`${year}-${String(i + 1).padStart(2, '0')}`); }}
                                    className={cn('rounded-[7px] py-1.5 text-[12px] font-semibold transition hover:bg-sb-hover',
                                        active ? 'bg-sb-primary text-white hover:bg-sb-primary' : 'text-sb-text')}>
                                    {name}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
            {!compact && !hideHeader && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 className="text-[15px] font-bold">{calendar.bulan_label ?? calendar.bulan}</h3>
                    <div className="flex gap-1 rounded-[9px] bg-sb-surface2 p-1">
                        {(['semua', 'absensi', 'ruangan'] as const).map((f) => (
                            <button key={f} type="button" onClick={() => setFilter(f)}
                                className={cn(
                                    'rounded-[7px] px-3 py-1 text-[12px] font-semibold capitalize transition',
                                    filter === f ? 'bg-sb-surface text-sb-primary shadow-sm' : 'text-sb-muted hover:text-sb-text',
                                )}
                            >
                                {f === 'semua' ? 'Semua' : f === 'absensi' ? 'Absensi' : 'Ruangan'}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className="grid grid-cols-7 gap-1 text-center">
                {DOW_LABELS.map((d) => (
                    <div key={d} className="py-1 text-[11px] font-bold uppercase text-sb-muted">{d}</div>
                ))}
                {[...Array(firstDow)].map((_, i) => <div key={`e${i}`} />)}
                {days.map((d, idx) => {
                    const hasA = d.is_wajib || d.absensi;
                    const hasR = (d.ruangan?.length ?? 0) > 0;
                    const col = (firstDow + idx) % 7;

                    // Prioritas warna agenda: libur > peringatan > ujian > lainnya.
                    const jenisAgenda = d.agenda?.some((a) => a.jenis === 'libur') ? 'libur'
                        : d.agenda?.some((a) => a.jenis === 'peringatan') ? 'peringatan'
                        : d.agenda?.some((a) => a.jenis === 'ujian') ? 'ujian'
                        : d.agenda?.some((a) => a.jenis === 'perubahan_jam') ? 'perubahan_jam'
                        : (d.agenda?.length ?? 0) > 0 ? 'agenda' : null;
                    const agendaTone = jenisAgenda === 'libur' ? 'bg-sb-danger'
                        : jenisAgenda === 'peringatan' ? 'bg-sb-violet'
                        : jenisAgenda === 'ujian' ? 'bg-sb-warn'
                        : jenisAgenda === 'perubahan_jam' ? 'bg-sb-info'
                        : jenisAgenda ? 'bg-sb-primary' : null;
                    const agendaBg = jenisAgenda === 'libur' ? 'bg-sb-danger-bg border-sb-danger/25'
                        : jenisAgenda === 'peringatan' ? 'bg-sb-violet-bg border-sb-violet/25'
                        : jenisAgenda === 'ujian' ? 'bg-sb-warn-bg border-sb-warn/25'
                        : jenisAgenda ? 'bg-sb-info-bg border-sb-info/25' : null;
                    const agendaChip = jenisAgenda === 'libur' ? 'bg-sb-danger/90 text-white'
                        : jenisAgenda === 'peringatan' ? 'bg-sb-violet/90 text-white'
                        : jenisAgenda === 'ujian' ? 'bg-sb-warn/90 text-white'
                        : jenisAgenda === 'perubahan_jam' ? 'bg-sb-info/90 text-white'
                        : jenisAgenda ? 'bg-sb-primary/90 text-white' : null;

                    // Info tooltip saat hover/focus — status hari + agenda + rekaman.
                    const tips: string[] = [];
                    if (d.is_libur) tips.push('Libur');
                    else if (d.is_wajib) tips.push(`Wajib absensi · ${hhmm(d.jam_masuk)}–${hhmm(d.jam_pulang)}`);
                    if (d.absensi) tips.push(`Rekaman: ${d.absensi.kelengkapan_label}`);
                    (d.ruangan ?? []).forEach((r) => tips.push(`${r.ruangan} · ${r.jam}`));
                    (d.agenda ?? []).forEach((a) => tips.push(a.keterangan ? `${a.jenis_label}: ${a.keterangan}` : a.jenis_label));
                    if (tips.length === 0) tips.push('Tidak ada jadwal');

                    return (
                        <button
                            key={d.tanggal} type="button"
                            onClick={() => pick(d.tanggal)}
                            aria-pressed={selected === d.tanggal}
                            aria-label={`${d.tanggal_human}${d.is_libur ? ', libur' : ''}`}
                            className={cn(
                                'group relative flex min-h-[46px] min-w-0 flex-col items-center justify-center rounded-[10px] border border-transparent py-1.5 text-[13px] font-semibold transition',
                                !compact && jenisAgenda && 'min-h-[64px] justify-start pt-1',
                                agendaBg && selected !== d.tanggal && agendaBg,
                                d.is_today && 'border-sb-primary/40 bg-sb-primary-soft/60',
                                selected === d.tanggal && 'border-sb-primary bg-sb-primary text-white shadow-sm',
                                d.is_libur && selected !== d.tanggal && !agendaBg && 'text-sb-muted/70',
                                !d.is_today && selected !== d.tanggal && 'hover:bg-sb-hover',
                            )}
                        >
                            {/* Tooltip info — muncul saat hover/focus, non-interaktif */}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'pointer-events-none absolute bottom-full z-40 mb-1.5 hidden w-44 rounded-[10px] bg-sb-navy px-3 py-2 text-left shadow-sb-pop',
                                    'group-hover:block group-focus-visible:block',
                                    col === 0 ? 'left-0' : col === 6 ? 'right-0' : 'left-1/2 -translate-x-1/2',
                                )}
                            >
                                <span className="block text-[11px] font-bold text-white">{d.tanggal_human}</span>
                                {tips.slice(0, 3).map((t, i) => (
                                    <span key={i} className="mt-0.5 block truncate text-[10.5px] text-white/75">{t}</span>
                                ))}
                                {tips.length > 3 && <span className="mt-0.5 block text-[10.5px] text-white/60">+{tips.length - 3} lainnya</span>}
                            </span>
                            {d.day_num}
                            <span className="mt-0.5 flex gap-1">
                                {hasA && showTag(d, 'A') && (
                                    <span className={cn('h-1.5 w-1.5 rounded-full',
                                        selected === d.tanggal ? 'bg-white/80'
                                        : d.absensi?.kelengkapan_tone === 'ok' ? 'bg-sb-ok'
                                        : d.absensi?.kelengkapan_tone === 'danger' ? 'bg-sb-danger'
                                        : 'bg-sb-warn')} />
                                )}
                                {hasR && showTag(d, 'R') && (
                                    <span className={cn('grid h-3.5 w-3.5 place-items-center rounded-[4px] text-[8px] font-extrabold',
                                        selected === d.tanggal ? 'bg-white/25 text-white' : 'bg-sb-info-bg text-sb-info')}>R</span>
                                )}
                                {compact && agendaTone && (
                                    <span className={cn('h-2 w-2 rounded-full ring-1 ring-white/60',
                                        selected === d.tanggal ? 'bg-white/80' : agendaTone)} />
                                )}
                            </span>
                            {/* Mode penuh — nama agenda tampil sebagai chip di sel tanggal */}
                            {!compact && jenisAgenda && (
                                <span className="mt-0.5 flex w-full flex-col gap-0.5 px-0.5">
                                    {d.agenda!.slice(0, 2).map((a) => (
                                        <span key={a.id}
                                            className={cn('block w-full truncate rounded-[4px] px-1 py-px text-left text-[8.5px] font-bold leading-tight',
                                                selected === d.tanggal ? 'bg-white/25 text-white' : agendaChip)}>
                                            {a.keterangan}
                                        </span>
                                    ))}
                                    {d.agenda!.length > 2 && (
                                        <span className={cn('w-full truncate text-[8px] font-bold', selected === d.tanggal ? 'text-white/80' : 'text-sb-muted')}>
                                            +{d.agenda!.length - 2} lainnya
                                        </span>
                                    )}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {/* Detail tanggal terpilih */}
            {showDetail && sel && (
                <div className="mt-4 rounded-[11px] border border-sb-border bg-sb-surface2 p-4">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-[13.5px] font-bold">{sel.tanggal_human}{sel.is_today ? ' · Hari ini' : ''}</p>
                        <Badge tone={sel.is_wajib ? 'primary' : 'neutral'}>
                            {sel.is_libur ? 'Libur' : sel.is_wajib ? 'Wajib absensi' : 'Tidak wajib'}
                        </Badge>
                    </div>
                    {!sel.is_libur && (
                        <p className="mt-1.5 font-mono text-[12.5px] text-sb-text2">
                            {hhmm(sel.jam_masuk)} – {hhmm(sel.jam_pulang)}
                            <span className="ml-2 font-sans text-[11.5px] text-sb-muted">{sel.sumber_label}</span>
                        </p>
                    )}
                    {sel.absensi && (
                        <div className="mt-2.5 border-t border-sb-border pt-2.5 text-[12.5px]">
                            <p className="font-semibold">Rekaman: {sel.absensi.kelengkapan_label}</p>
                            <p className="font-mono text-[12px] text-sb-muted">
                                {hhmm(sel.absensi.jam_masuk)} – {hhmm(sel.absensi.jam_pulang)}
                            </p>
                            {sel.absensi.ketepatan_masuk_label && <p className="text-[11.5px] text-sb-warn">{sel.absensi.ketepatan_masuk_label}</p>}
                            {sel.absensi.ketepatan_pulang_label && <p className="text-[11.5px] text-sb-warn">{sel.absensi.ketepatan_pulang_label}</p>}
                        </div>
                    )}
                    {!sel.absensi && sel.is_wajib && !sel.is_libur && (
                        <p className="mt-2 text-[12px] text-sb-muted">Tidak ada rekaman pada tanggal ini.</p>
                    )}
                    {sel.ruangan?.map((r, i) => (
                        <div key={i} className="mt-2 flex items-center justify-between gap-2 border-t border-sb-border pt-2 text-[12.5px]">
                            <span><strong>{r.ruangan}</strong> <span className="font-mono text-sb-muted">{r.jam}</span></span>
                            <Badge tone={r.status === 'disetujui' ? 'ok' : 'warn'}>{r.status_label}</Badge>
                        </div>
                    ))}
                    {sel.agenda?.map((a) => (
                        <p key={a.id} className="mt-2 border-t border-sb-border pt-2 text-[12px] text-sb-info">
                            {a.jenis_label}: {a.keterangan}
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}
