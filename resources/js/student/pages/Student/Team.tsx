import { useMemo, useState } from 'react';
import { LayoutGrid, List, Search, Users, X, ZoomIn } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Avatar } from '@/components/ui/avatar';
import { asset } from '@/lib/asset';
import type { AuthUser, SharedProps } from '@/types';
import { cn } from '@/lib/utils';

interface Member {
    user_id: number;
    nama_lengkap: string;
    nitk_nidn: string | null;
    kelas: string | null;
    role: string | null;
    profile_photo_url?: string | null;
}

interface Props extends SharedProps {
    team: {
        has_pbl_team: boolean;
        role?: string;
        project?: {
            nama?: string; kode?: string; deskripsi?: string;
            manpro?: string; co_manpro?: string; shift_sesi?: string;
        };
        members?: Member[];
    };
    projects: unknown;
    user: AuthUser;
}

const roleLabel = (r?: string | null) =>
    r === 'ketua' ? 'Ketua' : r === 'wakil' ? 'Wakil' : r === 'manpro' ? 'Manpro' : r === 'co_manpro' ? 'Co-Manpro' : 'Anggota';

export default function Team({ team, user, branding }: Props) {
    const brandLogo = branding?.logo_url || asset('branding/smartbrail-logo.jpg');
    const appName = branding?.app_name || 'SmartBrail';
    const [q, setQ] = useState('');
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [detail, setDetail] = useState<Member | null>(null);
    const [zoom, setZoom] = useState<string | null>(null);

    const hasTeam = !!team?.has_pbl_team;
    const project = team?.project ?? {};
    const members = useMemo(() => team?.members ?? [], [team]);
    const myRole = team?.role ?? 'anggota';

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return members;
        return members.filter((m) =>
            `${m.nama_lengkap} ${m.nitk_nidn ?? ''} ${m.kelas ?? ''}`.toLowerCase().includes(needle));
    }, [members, q]);

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Kolaborasi' }, { label: 'Tim PBL' }]} />}
                title="Tim PBL Saya"
                sub="Informasi proyek dan anggota tim berdasarkan penugasan PBL."
            />

            {!hasTeam ? (
                <Card>
                    <CardContent className="flex flex-col items-center gap-2 py-14 text-center">
                        <Users className="text-sb-muted/50" size={30} />
                        <p className="text-[15px] font-bold">Belum ada tim PBL</p>
                        <p className="max-w-md text-[13px] text-sb-muted">
                            Anda belum ditugaskan ke proyek PBL. Hubungi dosen pengampu atau laboran jika memerlukan penugasan.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <>
                    <Card>
                        <CardContent className="flex flex-wrap gap-5 pt-6">
                            <span className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-[14px] border border-sb-border bg-sb-surface2">
                                <img src={brandLogo} alt={appName} className="h-full w-full object-cover" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-[18px] font-extrabold">{project.nama ?? 'Proyek PBL'}</h3>
                                    {project.kode && <Badge tone="info">{project.kode}</Badge>}
                                </div>
                                {project.deskripsi && <p className="mt-1.5 max-w-2xl text-[13px] text-sb-text2">{project.deskripsi}</p>}
                                <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-5">
                                    {[
                                        ['Peran Saya', roleLabel(myRole)],
                                        ['Shift', project.shift_sesi === 'malam' ? 'Malam' : 'Pagi'],
                                        ['Manpro', project.manpro || 'Belum ditentukan'],
                                        ['Co-Manpro', project.co_manpro || 'Belum ditentukan'],
                                        ['Anggota', `${members.length} orang`],
                                    ].map(([k, v]) => (
                                        <div key={k}>
                                            <dt className="text-[11px] font-bold uppercase tracking-wide text-sb-muted">{k}</dt>
                                            <dd className="mt-0.5 text-[13.5px] font-semibold">{v}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div>
                                <CardTitle>Anggota Tim ({members.length})</CardTitle>
                                <CardDescription>Daftar anggota resmi dari penugasan PBL.</CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-sb-muted" />
                                    <Input value={q} onChange={(e) => setQ(e.target.value)}
                                        placeholder="Cari anggota tim…" aria-label="Cari anggota tim"
                                        className="w-[200px] pl-8 text-[13px] max-sm:w-[150px]" />
                                </div>
                                <div className="flex rounded-[9px] bg-sb-surface2 p-1">
                                    {([['grid', LayoutGrid], ['list', List]] as const).map(([v, Icon]) => (
                                        <button key={v} type="button" onClick={() => setView(v)}
                                            aria-pressed={view === v} aria-label={`Tampilan ${v}`}
                                            className={cn('rounded-[7px] p-1.5 transition',
                                                view === v ? 'bg-sb-surface text-sb-primary shadow-sm' : 'text-sb-muted hover:text-sb-text')}>
                                            <Icon size={15} />
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className={cn(view === 'grid'
                                ? 'grid gap-3 sm:grid-cols-2 lg:grid-cols-3'
                                : 'space-y-2')}>
                                {filtered.map((m) => (
                                    <button key={m.user_id} type="button" onClick={() => setDetail(m)}
                                        className={cn(
                                        'flex items-center gap-3 rounded-[12px] border p-3.5 text-left transition',
                                        m.user_id === user.id ? 'border-sb-primary/40 bg-sb-primary-soft/50' : 'border-sb-border bg-sb-surface hover:border-sb-primary/30',
                                        view === 'list' && 'py-2.5',
                                    )}>
                                        <Avatar nama={m.nama_lengkap} src={m.profile_photo_url} className="h-[42px] w-[42px]" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-[13.5px] font-bold">
                                                {m.nama_lengkap}{m.user_id === user.id && <span className="ml-1.5 text-[11px] font-semibold text-sb-primary">(Anda)</span>}
                                            </p>
                                            <p className="truncate text-[11.5px] text-sb-muted">
                                                {m.nitk_nidn ?? '—'}{m.kelas ? ` · ${m.kelas}` : ''}
                                            </p>
                                        </div>
                                        <Badge tone={m.role === 'ketua' || m.role === 'wakil' ? 'warn' : 'neutral'}>
                                            {roleLabel(m.role)}
                                        </Badge>
                                    </button>
                                ))}
                            </div>
                            {filtered.length === 0 && (
                                <p className="py-8 text-center text-[13px] text-sb-muted">
                                    {q ? 'Tidak ada anggota yang cocok dengan pencarian.' : 'Belum ada anggota yang terdaftar.'}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}

            {/* Popup profil anggota — data non-sensitif saja */}
            <Dialog open={!!detail} onOpenChange={(o) => { if (!o) setDetail(null); }}>
                <DialogContent title={detail?.nama_lengkap ?? ''} description="Profil anggota tim">
                    {detail && (
                        <div className="space-y-4 px-5 py-4">
                            <div className="flex items-center gap-4">
                                <button type="button" onClick={() => detail.profile_photo_url && setZoom(detail.profile_photo_url)}
                                    className="group relative shrink-0" aria-label="Perbesar foto"
                                    disabled={!detail.profile_photo_url}>
                                    <Avatar nama={detail.nama_lengkap} src={detail.profile_photo_url} className="h-20 w-20 text-xl" />
                                    {detail.profile_photo_url && (
                                        <span className="absolute inset-0 grid place-items-center rounded-full bg-black/40 opacity-0 transition group-hover:opacity-100">
                                            <ZoomIn size={18} className="text-white" />
                                        </span>
                                    )}
                                </button>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-[16px] font-extrabold">
                                        {detail.nama_lengkap}
                                        {detail.user_id === user.id && <span className="ml-1.5 text-[11px] font-semibold text-sb-primary">(Anda)</span>}
                                    </p>
                                    <p className="mt-0.5 text-[12.5px] text-sb-muted">{detail.nitk_nidn ?? '—'}</p>
                                    <Badge tone={detail.role === 'ketua' || detail.role === 'wakil' ? 'warn' : 'neutral'} className="mt-1.5">
                                        {roleLabel(detail.role)} Tim
                                    </Badge>
                                </div>
                            </div>
                            <dl className="grid grid-cols-2 gap-2.5 text-[12.5px]">
                                {[
                                    ['Kelas', detail.kelas ?? '—'],
                                    ['Tim PBL', project.nama ? `${project.nama}${project.kode ? ` · ${project.kode}` : ''}` : '—'],
                                    ['Manpro', project.manpro ?? 'Belum ditentukan'],
                                    ['Co-Manpro', project.co_manpro ?? 'Belum ditentukan'],
                                    ['Shift / Sesi', project.shift_sesi ?? '—'],
                                    ['Peran', roleLabel(detail.role)],
                                ].map(([k, v]) => (
                                    <div key={k} className="rounded-[9px] bg-sb-surface2 px-3 py-2">
                                        <dt className="text-[10.5px] font-bold uppercase text-sb-muted">{k}</dt>
                                        <dd className="mt-0.5 font-semibold">{v}</dd>
                                    </div>
                                ))}
                            </dl>
                            {detail.profile_photo_url && (
                                <p className="text-center text-[11px] text-sb-muted">Ketuk foto untuk memperbesar.</p>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Zoom foto layar penuh */}
            {zoom && (
                <div className="fixed inset-0 z-[70] grid place-items-center bg-black/85 p-6" role="dialog" aria-label="Foto diperbesar"
                    onClick={() => setZoom(null)}>
                    <button type="button" aria-label="Tutup" onClick={() => setZoom(null)}
                        className="absolute right-5 top-5 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                        <X size={20} />
                    </button>
                    <img src={zoom} alt="Foto anggota" className="max-h-[85vh] max-w-[90vw] rounded-[14px] object-contain shadow-2xl" />
                </div>
            )}
        </div>
    );
}

Team.layout = (page: React.ReactNode) => <AppShell title="Tim PBL">{page}</AppShell>;
