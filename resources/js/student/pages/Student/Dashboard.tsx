import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Clock3, DoorOpen, Fingerprint, Users } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { Badge, toneFor } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MiniCalendar, shiftMonth } from '@/components/MiniCalendar';
import { apiGet } from '@/lib/api';
import type { AuthUser, CalendarData, Pengajuan, SharedProps, Tim } from '@/types';
import { asset } from '@/lib/asset';
import { hhmm, tanggalHuman, tanggalSingkat } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Props extends SharedProps {
    user: AuthUser & { program_studi?: { nama: string } | null };
    prodiName: string | null;
    todaySchedule: { tanggal: string; hari_nama: string; jam_masuk: string; jam_pulang: string; is_wajib: boolean; is_libur: boolean } | null;
    todayRecord: { masuk?: string | null; pulang?: string | null } | null;
    todayState: { judul: string; keterangan: string; kode: string; tone: string; sumber: string; pengecualian: boolean };
    periodSummary: {
        lengkap: number; tidak_lengkap: number; alfa: number; catatan_ketepatan: number;
        terlambat: number; pulang_cepat: number; total_sesi: number;
        range_label: string; kebijakan_label: string; days: number;
        options: Record<string, string>;
    };
    recentRows: Array<{ tanggal: string; hari_nama: string; jam_masuk: string | null; jam_pulang: string | null; kelengkapan_label: string; kelengkapan_tone: string }>;
    calendar: CalendarData;
    scheduleMeta: { active_label: string; pending_weekly: boolean; upcoming_weekly?: string; effective_from_label?: string };
    tim: Tim | null;
    bms: {
        tim: Tim | null;
        anggota_count: number;
        ketua: string | null;
        berikutnya: Pengajuan | null;
        izin_berlaku: boolean;
        perhatian: Pengajuan[];
    };
    showContactPrompt: boolean;
}

export default function Dashboard(props: Props) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user!;
    const { todaySchedule, todayRecord, todayState, periodSummary: ps, recentRows, calendar, scheduleMeta, bms } = props;
    const next = bms.berikutnya;
    // Mini-calendar dashboard: điều hướng tháng qua endpoint JSON, không reload trang.
    const [cal, setCal] = useState<CalendarData>(calendar);
    const [calBusy, setCalBusy] = useState(false);
    const goCalMonth = async (bulan: string) => {
        if (bulan === cal.bulan || calBusy) return;
        setCalBusy(true);
        try {
            const r = await apiGet<{ calendar: CalendarData }>(`${route('mahasiswa.calendar.month')}?bulan=${bulan}`);
            setCal(r.calendar);
        } catch {
            // Giữ nguyên tháng đang xem nếu tải lỗi.
        } finally {
            setCalBusy(false);
        }
    };

    return (
        <div className="space-y-4 sb-fade-in">
            {/* Sapaan + CTA */}
            <section className="sb-pagehead flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-[12.5px] font-semibold uppercase tracking-wide text-sb-muted">
                        {tanggalHuman(todaySchedule?.tanggal)}
                    </p>
                    <h2 className="mt-1 text-[22px] font-extrabold tracking-tight">
                        Halo, {user?.nama_lengkap?.split(' ')[0] ?? 'Mahasiswa'} — selamat datang di SmartBrail
                    </h2>
                    <p className="mt-1 text-[13.5px] text-sb-text2">
                        {[props.prodiName, user.kelas, bms.tim ? `Tim ${bms.tim.nama}` : null].filter(Boolean).join(' · ') || 'Portal Mahasiswa'}
                    </p>
                </div>
                <Link href={route('mahasiswa.ruangan')}>
                    <Button><DoorOpen size={16} /> Ajukan Ruangan</Button>
                </Link>
            </section>

            {/* Absensi hari ini + Ruangan berikutnya */}
            <div className="grid items-stretch gap-4 lg:grid-cols-2">
                <Card className="flex h-full flex-col">
                    <CardHeader className="pb-3">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-wide text-sb-muted">Absensi hari ini</p>
                            <CardTitle className="mt-1 text-[17px]">{todaySchedule?.hari_nama}, {tanggalSingkat(todaySchedule?.tanggal)}</CardTitle>
                        </div>
                        <Badge tone={todayState.tone === 'ok' ? 'ok' : todayState.tone === 'warn' ? 'warn' : 'info'}>
                            {todayState.judul}
                        </Badge>
                    </CardHeader>
                    <CardContent className="flex flex-1 flex-col">
                        <p className="text-[13px] text-sb-text2">{todayState.keterangan}</p>
                        <div className="mt-3 grid grid-cols-3 divide-x divide-sb-border rounded-[10px] border border-sb-border bg-sb-surface2 text-center">
                            {[
                                ['Jadwal efektif', `${hhmm(todaySchedule?.jam_masuk)} – ${hhmm(todaySchedule?.jam_pulang)}`, todayState.sumber],
                                ['Masuk', todayRecord?.masuk ? hhmm(todayRecord.masuk) : '—', todayRecord?.masuk ? null : 'Belum ada tap'],
                                ['Pulang', todayRecord?.pulang ? hhmm(todayRecord.pulang) : '—', todayRecord?.pulang ? null : 'Belum ada tap'],
                            ].map(([label, val, sub], i) => (
                                <div key={i} className="px-2 py-3">
                                    <p className="text-[10.5px] font-bold uppercase tracking-wide text-sb-muted">{label}</p>
                                    <p className="mt-1 font-mono text-[14px] font-semibold">{val}</p>
                                    {sub && <p className="text-[11px] text-sb-muted">{sub}</p>}
                                </div>
                            ))}
                        </div>
                        <p className="mt-3 flex items-center gap-1.5 text-[12px] text-sb-muted">
                            <Fingerprint size={13} />
                            Sidik jari: {user.has_fingerprint ? 'terdaftar' : user.finger_id ? `ID #${user.finger_id} dialokasikan (belum rekam)` : 'belum dialokasikan'}
                        </p>
                        <div className="mt-auto flex items-center justify-between pt-3">
                            <Link href={route('mahasiswa.absensi')} className="text-[13px] font-semibold text-sb-primary hover:underline">
                                Lihat Riwayat Absensi
                            </Link>
                            <Link href={route('mahasiswa.suggestions')} className="text-[12.5px] font-medium text-sb-muted hover:text-sb-text">
                                Laporkan Kendala Hari Ini
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <Card className="flex h-full flex-col">
                    <CardHeader className="pb-3">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-wide text-sb-muted">Ruangan berikutnya</p>
                            <CardTitle className="mt-1 text-[17px]">{next ? 'Penggunaan terjadwal' : 'Belum ada jadwal'}</CardTitle>
                        </div>
                        {next && (
                            <Badge tone={bms.izin_berlaku ? 'ok' : 'warn'}>
                                {bms.izin_berlaku ? 'Izin sedang berlaku' : 'Disetujui'}
                            </Badge>
                        )}
                    </CardHeader>
                    <CardContent className="flex flex-1 flex-col">
                        {next?.ruangan?.foto_url && (
                            <div className="relative mb-3">
                                <img src={next.ruangan.foto_url} alt={`Foto ${next.ruangan.nama ?? 'ruangan'}`}
                                    className="h-32 w-full rounded-[10px] object-cover" />
                                <span className="absolute left-2.5 top-2.5 rounded-full bg-black/55 px-2.5 py-1 text-[10.5px] font-bold text-white backdrop-blur-sm">
                                    {next.ruangan.kode}
                                </span>
                            </div>
                        )}
                        {next ? (
                            <ul className="space-y-2.5 text-[13.5px]">
                                <li className="flex items-center gap-2.5"><DoorOpen size={15} className="text-sb-muted" /><strong>{next.ruangan?.nama ?? '—'}</strong></li>
                                <li className="flex items-center gap-2.5"><CalendarDays size={15} className="text-sb-muted" />{tanggalHuman(next.tanggal)}</li>
                                <li className="flex items-center gap-2.5"><Clock3 size={15} className="text-sb-muted" /><span className="font-mono">{hhmm(next.jam_mulai)} – {hhmm(next.jam_selesai)}</span></li>
                                <li className="flex items-center gap-2.5"><Users size={15} className="text-sb-muted" />{(next.snapshot?.peserta_nama?.length ?? next.peserta?.length ?? 0)} peserta</li>
                            </ul>
                        ) : (
                            <p className="text-[13px] text-sb-muted">
                                Tidak ada pengajuan disetujui yang akan datang. Ajukan ruangan untuk tim Anda lewat halaman Ruangan.
                            </p>
                        )}
                        <div className="mt-auto pt-4">
                            <Link href={route('mahasiswa.ruangan')} className="text-[13px] font-semibold text-sb-primary hover:underline">
                                Buka Halaman Ruangan
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Perlu perhatian */}
            {bms.perhatian.length > 0 && (
                <Card>
                    <CardHeader className="pb-3">
                        <div>
                            <CardTitle>Perlu Perhatian</CardTitle>
                            <CardDescription>Tawaran waktu pengganti dan keputusan terbaru pengajuan ruangan</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-2.5">
                        {bms.perhatian.map((p) => (
                            <div key={p.id} className="flex flex-wrap items-center gap-3 rounded-[10px] border border-sb-border bg-sb-surface2 px-3.5 py-3">
                                <Badge tone={toneFor(p.status === 'menunggu_penerimaan' ? p.status : p.status_efektif)}>
                                    {p.status === 'menunggu_penerimaan' ? 'Tawaran waktu pengganti' : ({
                                        disetujui: 'Disetujui', ditolak: 'Ditolak', selesai: 'Selesai',
                                        kedaluwarsa: 'Kedaluwarsa', dibatalkan: 'Dibatalkan', diajukan: 'Menunggu keputusan',
                                    } as Record<string, string>)[p.status_efektif] ?? p.status_efektif}
                                </Badge>
                                <div className="min-w-0 flex-1 text-[13px]">
                                    <span className="font-semibold">{p.ruangan?.nama}</span>
                                    <span className="text-sb-muted"> · {tanggalSingkat(p.tanggal)} · {hhmm(p.jam_mulai)}–{hhmm(p.jam_selesai)}</span>
                                </div>
                                <Link href={route('mahasiswa.ruangan', { tab: 'riwayat' })} className="text-[12.5px] font-semibold text-sb-primary hover:underline">
                                    Lihat
                                </Link>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* Dua kolom seimbang: Kalender+Ringkasan | Tim+Aktivitas — tanpa lubang */}
            <div className="grid items-start gap-4 xl:grid-cols-[7fr_5fr]">
                <div className="space-y-4">
                <Card>
                    <CardHeader className="pb-2">
                        <div>
                            <CardTitle>Kalender</CardTitle>
                            <CardDescription>Absensi, agenda, dan penggunaan ruangan per tanggal</CardDescription>
                        </div>
                        <Link href={route('mahasiswa.calendar')} className="flex items-center gap-1 text-[13px] font-semibold text-sb-primary hover:underline">
                            Jadwal Lengkap <ArrowRight size={13} />
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <MiniCalendar
                            calendar={cal}
                            compact
                            nav={{
                                bulan: cal.bulan,
                                label: cal.bulan_label ?? cal.bulan,
                                busy: calBusy,
                                onPrev: () => void goCalMonth(shiftMonth(cal.bulan, -1)),
                                onNext: () => void goCalMonth(shiftMonth(cal.bulan, +1)),
                                onPick: (b) => void goCalMonth(b),
                            }}
                        />
                        {(cal.agendas?.length ?? 0) > 0 && (
                            <div className="mt-4 space-y-1.5 border-t border-sb-border pt-3">
                                <p className="text-[11px] font-bold uppercase tracking-wide text-sb-muted">Agenda {cal.bulan_label ? `· ${cal.bulan_label}` : 'tháng này'}</p>
                                {cal.agendas!.slice(0, 5).map((a) => (
                                    <div key={a.id} className="flex items-center gap-2 text-[12px]">
                                        <span className={cn('h-2 w-2 shrink-0 rounded-full',
                                            a.jenis === 'libur' ? 'bg-sb-danger'
                                            : a.jenis === 'peringatan' ? 'bg-sb-violet'
                                            : a.jenis === 'ujian' ? 'bg-sb-warn'
                                            : 'bg-sb-info')} />
                                        <span className="min-w-0 flex-1 truncate font-semibold">{a.keterangan}</span>
                                        <span className="shrink-0 text-[11px] text-sb-muted">
                                            {tanggalSingkat(a.tanggal)}{a.tanggal_selesai && a.tanggal_selesai !== a.tanggal ? `–${tanggalSingkat(a.tanggal_selesai)}` : ''}
                                        </span>
                                    </div>
                                ))}
                                {cal.agendas!.length > 5 && (
                                    <Link href={route('mahasiswa.calendar', { bulan: cal.bulan })} className="block text-[11.5px] font-semibold text-sb-primary hover:underline">
                                        +{cal.agendas!.length - 5} agenda lainnya →
                                    </Link>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Ringkasan */}
                <Card>
                    <CardHeader className="pb-3">
                        <div>
                            <CardTitle>Ringkasan Absensi</CardTitle>
                            <CardDescription>{ps.range_label} · {ps.total_sesi} catatan hari terjadwal</CardDescription>
                        </div>
                        <form method="get">
                            <select
                                name="periode" defaultValue={String(ps.days)}
                                onChange={(e) => e.currentTarget.form?.submit()}
                                aria-label="Pilih periode ringkasan"
                                className="rounded-[9px] border border-sb-border bg-sb-surface px-3 py-1.5 text-[12.5px] font-medium text-sb-text outline-none focus:border-sb-primary"
                            >
                                {Object.entries(ps.options).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </form>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {[
                                ['Lengkap', ps.lengkap, 'ok'],
                                ['Tidak Lengkap', ps.tidak_lengkap, 'info'],
                                ['Alfa', ps.alfa, 'danger'],
                                ['Catatan Waktu', ps.catatan_ketepatan, 'warn'],
                            ].map(([l, v, tone]) => (
                                <div key={l as string} className="rounded-[10px] border border-sb-border bg-sb-surface2 px-3.5 py-3">
                                    <p className="text-[26px] font-extrabold leading-none">{v}</p>
                                    <Badge tone={tone as 'ok'} className="mt-2">{l}</Badge>
                                </div>
                            ))}
                        </div>
                        <p className="mt-3 text-[12px] text-sb-muted">
                            Kelengkapan (masuk+pulang) dinilai terpisah dari catatan waktu (terlambat {ps.terlambat}, pulang cepat {ps.pulang_cepat}) · {ps.kebijakan_label}.
                        </p>
                    </CardContent>
                </Card>
                </div>

                {/* Kolom kanan: Tim + Aktivitas */}
                <div className="space-y-4">
                <Card>
                    <CardHeader className="pb-3">
                        <div>
                            <CardTitle>Tim PBL</CardTitle>
                            <CardDescription>Tim Anda saat ini</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {bms.tim ? (
                            <>
                                <div className="flex items-center gap-3">
                                    <span className="grid h-11 w-11 place-items-center rounded-[11px] bg-sb-primary-soft">
                                        <img src={asset('branding/smartbrail-mark.svg')} alt="" className="h-7 w-7" />
                                    </span>
                                    <div>
                                        <p className="text-[14.5px] font-bold">{bms.tim.nama}</p>
                                        {bms.tim.manpro && <p className="text-[12px] text-sb-muted">Manpro: {bms.tim.manpro}</p>}
                                    </div>
                                </div>
                                <dl className="mt-4 space-y-2.5 text-[13px]">
                                    {[
                                        ['Ketua Tim', bms.ketua ?? '—'],
                                        ['Anggota', `${bms.anggota_count} mahasiswa`],
                                        ['Pembimbing', bms.tim.co_manpro ?? bms.tim.manpro ?? '—'],
                                    ].map(([k, v]) => (
                                        <div key={k} className="flex justify-between gap-3">
                                            <dt className="text-sb-muted">{k}</dt>
                                            <dd className="font-semibold">{v}</dd>
                                        </div>
                                    ))}
                                </dl>
                                <Link href={route('mahasiswa.team')} className="mt-4 flex items-center gap-1 text-[13px] font-semibold text-sb-primary hover:underline">
                                    Lihat Detail Tim <ArrowRight size={13} />
                                </Link>
                            </>
                        ) : (
                            <p className="text-[13px] text-sb-muted">
                                Anda belum tergabung dalam tim PBL. Pengajuan ruangan tim tersedia setelah Anda masuk tim — hubungi dosen pembimbing atau laboran.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Aktivitas */}
                <Card>
                    <CardHeader className="pb-3">
                        <div>
                            <CardTitle>Aktivitas Terbaru</CardTitle>
                            <CardDescription>5 catatan absensi terakhir</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-1">
                        {recentRows.length === 0 && <p className="text-[13px] text-sb-muted">Belum ada catatan.</p>}
                        {recentRows.map((r) => (
                            <div key={r.tanggal} className="flex items-center gap-3 rounded-[9px] px-2 py-2 hover:bg-sb-hover">
                                <span className={cn('h-2 w-2 shrink-0 rounded-full', r.kelengkapan_tone === 'ok' ? 'bg-sb-ok' : r.kelengkapan_tone === 'danger' ? 'bg-sb-danger' : 'bg-sb-warn')} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-[13px] font-semibold">{r.hari_nama}, {tanggalSingkat(r.tanggal)}</p>
                                    <p className="font-mono text-[11.5px] text-sb-muted">{hhmm(r.jam_masuk)} – {hhmm(r.jam_pulang)}</p>
                                </div>
                                <span className="text-[11.5px] font-medium text-sb-muted">{r.kelengkapan_label}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppShell title="Beranda">{page}</AppShell>;
