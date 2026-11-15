import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { CalendarDays, ChevronRight, Flag } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import { Badge, toneFor } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Select } from '@/components/ui/input';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import type { SharedProps } from '@/types';
import { tanggalSingkat } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Row {
    tanggal: string;
    tanggal_human?: string;
    hari_nama: string;
    jam_masuk: string | null;
    jam_pulang: string | null;
    jadwal_masuk?: string | null;
    jadwal_pulang?: string | null;
    jadwal_label?: string | null;
    status: string;
    kelengkapan_kode?: string;
    kelengkapan_label: string;
    kelengkapan_tone: string;
    kelengkapan_desc?: string;
    ketepatan_masuk_label?: string | null;
    ketepatan_masuk_tone?: string | null;
    ketepatan_pulang_label?: string | null;
    ketepatan_pulang_tone?: string | null;
    has_punctuality_note?: boolean;
    ringkasan_ketepatan?: string | null;
    jadwal_konsisten?: boolean;
    status_hari?: string;
    sumber_aturan?: string;
}

interface Props extends SharedProps {
    rows: Row[];
    summary: {
        lengkap: number; tidak_lengkap: number; alfa: number;
        catatan_waktu: number; total: number;
        range_label: string; kebijakan_label: string;
    };
    availableMonths: Array<{ value: string; label: string }>;
    filterStatus: string;
    filterBulan: string;
}

const FILTERS = [
    ['semua', 'Semua'],
    ['lengkap', 'Lengkap'],
    ['tidak_lengkap', 'Tidak Lengkap'],
    ['alfa', 'Alfa'],
    ['catatan_waktu', 'Catatan Waktu'],
] as const;

export default function Attendance({ rows, summary, availableMonths, filterStatus, filterBulan }: Props) {
    const [detail, setDetail] = useState<Row | null>(null);

    const applyFilter = (status: string, bulan: string) => {
        router.get(route('mahasiswa.absensi'), { status, bulan }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };

    const stats = useMemo(() => [
        { k: 'lengkap', label: 'Lengkap', v: summary.lengkap, tone: 'ok' as const },
        { k: 'tidak_lengkap', label: 'Tidak Lengkap', v: summary.tidak_lengkap, tone: 'info' as const },
        { k: 'alfa', label: 'Alfa', v: summary.alfa, tone: 'danger' as const },
        { k: 'catatan_waktu', label: 'Catatan Waktu', v: summary.catatan_waktu, tone: 'warn' as const },
    ], [summary]);

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Absensi' }, { label: 'Riwayat' }]} />}
                title="Riwayat Absensi"
                sub={`${summary.range_label} · ${summary.kebijakan_label}. Kelengkapan (masuk + pulang) dinilai terpisah dari catatan waktu.`}
            />

            {/* Kartu statistik = filter cepat */}
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {stats.map((s) => (
                    <button
                        key={s.k} type="button"
                        onClick={() => applyFilter(filterStatus === s.k ? 'semua' : s.k, filterBulan)}
                        aria-pressed={filterStatus === s.k}
                        className={cn(
                            'rounded-[14px] border bg-sb-surface p-4 text-left shadow-sb transition hover:border-sb-primary/50',
                            filterStatus === s.k ? 'border-sb-primary ring-2 ring-sb-primary/20' : 'border-sb-border',
                        )}
                    >
                        <p className="text-[26px] font-extrabold leading-none">{s.v}</p>
                        <Badge tone={s.tone} className="mt-2.5">{s.label}</Badge>
                    </button>
                ))}
            </div>

            {/* Filter bar */}
            <Card>
                <CardContent className="flex flex-wrap items-center gap-3 pt-5">
                    <div className="flex flex-wrap gap-1.5 rounded-[10px] bg-sb-surface2 p-1">
                        {FILTERS.map(([v, l]) => (
                            <button key={v} type="button"
                                onClick={() => applyFilter(v, filterBulan)}
                                className={cn(
                                    'rounded-[8px] px-3 py-1.5 text-[12.5px] font-semibold transition',
                                    filterStatus === v ? 'bg-sb-surface text-sb-primary shadow-sm' : 'text-sb-muted hover:text-sb-text',
                                )}
                            >{l}</button>
                        ))}
                    </div>
                    <Select
                        value={filterBulan}
                        onChange={(e) => applyFilter(filterStatus, e.target.value)}
                        className="w-auto min-w-[170px] text-[13px]"
                        aria-label="Filter bulan"
                    >
                        <option value="semua">Semua bulan</option>
                        {availableMonths.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                    </Select>
                    <span className="ml-auto text-[12.5px] text-sb-muted">{rows.length} catatan</span>
                </CardContent>
            </Card>

            {/* Tabel desktop */}
            <Card className="max-md:hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-sb-border text-[11px] uppercase tracking-wide text-sb-muted">
                                <th className="px-5 py-3 font-bold">Tanggal</th>
                                <th className="px-3 py-3 font-bold">Masuk</th>
                                <th className="px-3 py-3 font-bold">Pulang</th>
                                <th className="px-3 py-3 font-bold">Jadwal Efektif</th>
                                <th className="px-3 py-3 font-bold">Kelengkapan</th>
                                <th className="px-3 py-3 font-bold">Catatan Waktu</th>
                                <th className="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.tanggal} className="border-b border-sb-border last:border-0 hover:bg-sb-hover/60">
                                    <td className="px-5 py-3">
                                        <span className={cn('mr-2.5 inline-block h-8 w-1 rounded-full align-middle',
                                            r.kelengkapan_tone === 'ok' ? 'bg-sb-ok' : r.kelengkapan_tone === 'danger' ? 'bg-sb-danger' : 'bg-sb-warn')} />
                                        <span className="align-middle font-semibold">{r.hari_nama}, {tanggalSingkat(r.tanggal)}</span>
                                    </td>
                                    <td className="px-3 py-3 font-mono">{r.jam_masuk ?? '—'}</td>
                                    <td className="px-3 py-3 font-mono">{r.jam_pulang ?? '—'}</td>
                                    <td className="px-3 py-3 font-mono text-[12.5px] text-sb-text2">
                                        {r.jadwal_masuk && r.jadwal_pulang ? `${r.jadwal_masuk} – ${r.jadwal_pulang}` : '—'}
                                    </td>
                                    <td className="px-3 py-3"><Badge tone={toneFor(r.kelengkapan_kode ?? r.status)}>{r.kelengkapan_label}</Badge></td>
                                    <td className="px-3 py-3 text-[12.5px]">
                                        {r.ringkasan_ketepatan
                                            ? <span className="text-sb-warn">{r.ringkasan_ketepatan}</span>
                                            : <span className="text-sb-muted">—</span>}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <button type="button" onClick={() => setDetail(r)} aria-label={`Rincian ${r.tanggal}`}
                                            className="rounded-lg p-1.5 text-sb-muted hover:bg-sb-hover hover:text-sb-text">
                                            <ChevronRight size={16} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {rows.length === 0 && (
                        <p className="px-5 py-10 text-center text-[13px] text-sb-muted">
                            <CalendarDays className="mx-auto mb-2 text-sb-muted/50" size={26} />
                            Tidak ada catatan untuk filter ini.
                        </p>
                    )}
                </div>
            </Card>

            {/* Kartu HP */}
            <div className="space-y-2.5 md:hidden">
                {rows.map((r) => (
                    <button key={r.tanggal} type="button" onClick={() => setDetail(r)}
                        className="flex w-full items-center gap-3 rounded-[12px] border border-sb-border bg-sb-surface p-3.5 text-left shadow-sb"
                    >
                        <span className={cn('h-10 w-1 shrink-0 rounded-full',
                            r.kelengkapan_tone === 'ok' ? 'bg-sb-ok' : r.kelengkapan_tone === 'danger' ? 'bg-sb-danger' : 'bg-sb-warn')} />
                        <span className="min-w-0 flex-1">
                            <span className="block text-[13.5px] font-bold">{r.hari_nama}, {tanggalSingkat(r.tanggal)}</span>
                            <span className="block font-mono text-[12px] text-sb-muted">{r.jam_masuk ?? '—'} – {r.jam_pulang ?? '—'}</span>
                        </span>
                        <Badge tone={toneFor(r.kelengkapan_kode ?? r.status)}>{r.kelengkapan_label}</Badge>
                    </button>
                ))}
                {rows.length === 0 && (
                    <p className="rounded-[12px] border border-sb-border bg-sb-surface p-8 text-center text-[13px] text-sb-muted">
                        Tidak ada catatan untuk filter ini.
                    </p>
                )}
            </div>

            {/* Modal rincian */}
            <Dialog open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
                <DialogContent title="Rincian Absensi" description={detail?.tanggal_human ?? detail?.tanggal}>
                    {detail && (
                        <div className="space-y-4 px-5 py-4">
                            <dl className="grid grid-cols-2 gap-3 text-[13px]">
                                {[
                                    ['Tap Masuk', detail.jam_masuk ?? '—'],
                                    ['Tap Pulang', detail.jam_pulang ?? '—'],
                                    ['Jadwal Efektif', detail.jadwal_masuk && detail.jadwal_pulang ? `${detail.jadwal_masuk} – ${detail.jadwal_pulang}` : '—'],
                                    ['Sumber Jadwal', detail.jadwal_label ?? detail.sumber_aturan ?? '—'],
                                ].map(([k, v]) => (
                                    <div key={k} className="rounded-[9px] bg-sb-surface2 px-3 py-2.5">
                                        <dt className="text-[11px] font-bold uppercase text-sb-muted">{k}</dt>
                                        <dd className="mt-0.5 font-mono text-[13px] font-semibold">{v}</dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="space-y-1.5 text-[13px]">
                                <p><strong>Kelengkapan:</strong> {detail.kelengkapan_label}{detail.kelengkapan_desc ? ` — ${detail.kelengkapan_desc}` : ''}</p>
                                {detail.ketepatan_masuk_label && <p className="text-sb-warn">Masuk: {detail.ketepatan_masuk_label}</p>}
                                {detail.ketepatan_pulang_label && <p className="text-sb-warn">Pulang: {detail.ketepatan_pulang_label}</p>}
                                {detail.jadwal_konsisten === false && (
                                    <p className="text-sb-info">Penilaian mengikuti jadwal efektif tanggal tersebut (bukan jadwal mingguan baku).</p>
                                )}
                            </div>
                            <a href={route('mahasiswa.suggestions')}
                                className="flex items-center justify-center gap-2 rounded-[10px] border border-sb-border bg-sb-surface2 px-4 py-2.5 text-[13px] font-semibold text-sb-text2 hover:bg-sb-hover">
                                <Flag size={14} /> Laporkan Kendala untuk Tanggal Ini
                            </a>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

Attendance.layout = (page: React.ReactNode) => <AppShell title="Riwayat Absensi">{page}</AppShell>;
