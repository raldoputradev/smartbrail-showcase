import { useEffect, useRef, useState, type PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Bell, ChevronDown, ChevronRight, KeyRound, LogOut, Menu, Search, ShieldAlert, UserRound, X,
} from 'lucide-react';
import {
    navTop, navRuangan, navMid, navBantuan, navSegera, allNavItems, type NavItem, type NavGroup,
} from '@/components/layout/nav';
import type { SharedProps } from '@/types';
import { cn } from '@/lib/utils';
import { asset } from '@/lib/asset';
import { Avatar } from '@/components/ui/avatar';
import { AssistantWidget } from '@/components/AssistantWidget';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { skeletonFor, GenericSkeleton } from '@/components/skeletons';
import { LoadingRegion } from '@/components/ui/skeleton';
import { subscribePrivate } from '@/lib/live';

/* ── Tema ─────────────────────────────────────────────── */
function useTheme() {
    const [theme, setTheme] = useState<'light' | 'dark'>(() =>
        (document.documentElement.dataset.theme as 'light' | 'dark') || 'light');
    const toggle = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('smartbrail_student_theme', next); } catch { /* abaikan */ }
        setTheme(next);
    };
    return { theme, toggle };
}

function ThemeToggle() {
    const { theme, toggle } = useTheme();
    return (
        <button
            type="button" onClick={toggle} aria-label="Ganti tema"
            className="grid h-9 w-9 place-items-center rounded-[10px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text"
        >
            {theme === 'dark' ? (
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            ) : (
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            )}
        </button>
    );
}

/* ── Pencarian menu ───────────────────────────────────── */
function MenuSearch() {
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const [idx, setIdx] = useState(0);
    const ref = useRef<HTMLDivElement>(null);

    const results = q.trim()
        ? allNavItems.filter((i) =>
            `${i.label} ${i.section ?? ''} ${i.hint ?? ''}`.toLowerCase().includes(q.trim().toLowerCase()))
        : allNavItems;

    useEffect(() => {
        const onDoc = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    const go = (item: NavItem) => {
        setOpen(false); setQ('');
        if (item.soon) { soonAlert(); return; }
        if (item.external) window.location.href = item.href;
        else router.visit(item.href);
    };

    return (
        <div ref={ref} className="relative w-[min(480px,52vw)] max-sm:hidden">
            <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sb-muted" />
            <input
                value={q}
                onChange={(e) => { setQ(e.target.value); setOpen(true); setIdx(0); }}
                onFocus={() => setOpen(true)}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') { e.preventDefault(); setIdx((i) => Math.min(i + 1, results.length - 1)); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); setIdx((i) => Math.max(i - 1, 0)); }
                    else if (e.key === 'Enter' && results[idx]) go(results[idx]);
                    else if (e.key === 'Escape') setOpen(false);
                }}
                placeholder="Cari menu…"
                aria-label="Cari menu"
                role="combobox" aria-expanded={open} aria-controls="sb-menusearch"
                className="w-full rounded-[10px] border border-sb-border bg-sb-surface2 py-2 pl-9 pr-3 text-[13px] text-sb-text outline-none transition placeholder:text-sb-muted/70 focus:border-sb-primary focus:bg-sb-surface focus:ring-[3px] focus:ring-sb-primary/20"
            />
            {open && (
                <ul id="sb-menusearch" role="listbox"
                    className="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-72 overflow-y-auto rounded-[12px] border border-sb-border bg-sb-surface p-1.5 shadow-sb-pop sb-scroll">
                    {results.length === 0 && (
                        <li className="px-3 py-2.5 text-[13px] text-sb-muted">Tidak ada menu “{q}”.</li>
                    )}
                    {results.map((item, i) => (
                        <li key={item.label} role="option" aria-selected={i === idx}>
                            <button type="button"
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-[8px] px-3 py-2 text-left',
                                    i === idx && 'bg-sb-hover',
                                )}
                                onMouseEnter={() => setIdx(i)}
                                onClick={() => go(item)}
                            >
                                <item.icon size={15} className="shrink-0 text-sb-muted" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[13.5px] font-medium text-sb-text">{item.label}</span>
                                    {(item.section || item.hint) && (
                                        <span className="block truncate text-[11px] text-sb-muted">
                                            {[item.section, item.hint].filter(Boolean).join(' · ')}
                                        </span>
                                    )}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/* ── Lonceng notifikasi ───────────────────────────────── */
function NotificationsBell() {
    const { navNotificationState } = usePage<SharedProps>().props;
    const state = navNotificationState;
    const count = state?.unread_count ?? 0;
    // Goyangan ekstra singkat saat jumlah notifikasi bertambah.
    const prevCount = useRef(count);
    const [justIn, setJustIn] = useState(false);
    useEffect(() => {
        if (count > prevCount.current) {
            setJustIn(true);
            const t = setTimeout(() => setJustIn(false), 2400);
            prevCount.current = count;
            return () => clearTimeout(t);
        }
        prevCount.current = count;
    }, [count]);
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button type="button" aria-label="Notifikasi"
                    className={cn(
                        'relative grid h-9 w-9 place-items-center rounded-[10px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text',
                        count > 0 && 'sb-bell--unread',
                        justIn && 'sb-bell--justin',
                    )}>
                    <Bell size={17} />
                    {count > 0 && (
                        <span key={count} className="sb-bell-badge absolute right-1.5 top-1.5 grid min-h-[15px] min-w-[15px] place-items-center rounded-full bg-sb-danger px-1 text-[9.5px] font-bold text-white">
                            {count > 9 ? '9+' : count}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[330px] max-w-[calc(100vw-20px)]">
                <div className="px-3 py-2 text-[12.5px] font-bold">Notifikasi</div>
                <DropdownMenuSeparator />
                <div className="max-h-[320px] overflow-y-auto sb-scroll">
                    {!state || state.items.length === 0 ? (
                        <p className="px-3 py-4 text-center text-[12.5px] text-sb-muted">Belum ada notifikasi.</p>
                    ) : state.items.map((n) => (
                        <a key={n.uid} href={n.action_url ?? route('mahasiswa.notifications')}
                            className={cn('block rounded-[8px] px-3 py-2.5 hover:bg-sb-hover', !n.is_read && 'bg-sb-primary-soft/50')}
                        >
                            <span className="block text-[13px] font-semibold leading-snug">{n.title}</span>
                            <span className="mt-0.5 line-clamp-2 block text-[12px] text-sb-muted">{n.excerpt}</span>
                            <span className="mt-1 block text-[11px] text-sb-muted">
                                {n.category_label}{n.meta ? ` · ${n.meta}` : ''} · {n.time_human}
                            </span>
                        </a>
                    ))}
                </div>
                <DropdownMenuSeparator />
                <a href={route('mahasiswa.notifications')} className="block rounded-[8px] px-3 py-2 text-center text-[12.5px] font-semibold text-sb-primary hover:bg-sb-hover">
                    Lihat semua
                </a>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/* ── Dropdown akun ────────────────────────────────────── */
function AccountMenu() {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;
    const needPw = !!user?.must_change_password;
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button type="button" className="flex items-center gap-2.5 rounded-[10px] py-1 pl-1 pr-2 transition hover:bg-sb-hover" aria-label="Menu akun">
                    <span className="relative">
                        <Avatar nama={user?.nama_lengkap} src={user?.profile_photo_url} className="h-8 w-8 text-[11px]" />
                        {needPw && <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-sb-warn ring-2 ring-sb-surface" aria-label="Password sementara aktif" />}
                    </span>
                    <span className="hidden text-left sm:block">
                        <span className="block max-w-[120px] truncate text-[13px] font-bold leading-tight">{user?.nama_lengkap?.split(' ')[0] ?? 'Mahasiswa'}</span>
                        <span className="block text-[11px] text-sb-muted">Mahasiswa</span>
                    </span>
                    <ChevronDown size={14} className="text-sb-muted" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <div className="px-3 py-2">
                    <p className="truncate text-[13px] font-bold">{user?.nama_lengkap}</p>
                    <p className="font-mono text-[11px] text-sb-muted">{user?.nitk_nidn ?? '—'}</p>
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('mahasiswa.profile')}><UserRound /> Profil Saya</Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={route('mahasiswa.password.change')}>
                        <KeyRound /> Keamanan
                        {needPw && <span className="ml-auto h-2 w-2 rounded-full bg-sb-warn" aria-label="Perlu ganti password" />}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('mahasiswa.logout')} method="post" as="button"
                        className="w-full text-sb-danger [&_svg]:!text-sb-danger">
                        <LogOut /> Keluar
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/* ── Sidebar ──────────────────────────────────────────── */
function soonAlert() {
    const Swal = (window as unknown as { Swal?: { fire: (o: object) => void } }).Swal;
    if (Swal) {
        Swal.fire({
            icon: 'info',
            title: 'Segera hadir',
            text: 'Fitur ini masih tahap pengembangan — akan hadir pada pembaruan berikutnya.',
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#7c3aed',
            background: document.documentElement.dataset.theme === 'dark' ? '#101c33' : undefined,
            color: document.documentElement.dataset.theme === 'dark' ? '#e8effc' : undefined,
        });
    }
}

function NavLinkItem({ item, rail, onNavigate }: { item: NavItem; rail: boolean; onNavigate?: () => void }) {
    const { url } = usePage();
    const { navNotificationState, features } = usePage<SharedProps>().props;
    const badgeCount = item.badge === 'replies' ? (navNotificationState?.reply_unread_count ?? 0) : 0;
    const featureState = item.feature ? features?.[item.feature] : undefined;
    const active = item.active(url);
    const cls = cn(
        'group relative flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-[13.5px] font-semibold text-sb-sidebar-text/85 transition hover:bg-white/10 hover:text-white',
        active && 'bg-sb-primary text-white shadow-sm hover:bg-sb-primary hover:text-white',
        rail && 'justify-center px-0 py-3',
    );
    const inner = (
        <>
            <span className="relative shrink-0">
                <item.icon size={19} strokeWidth={1.9} />
                {rail && badgeCount > 0 && <span className="absolute -right-1 -top-1 h-2 w-2 rounded-full bg-sb-danger" aria-hidden="true" />}
            </span>
            {!rail && (
                <span className="leading-tight">
                    {item.label}
                    {item.hint && <span className="block text-[10.5px] font-medium text-sb-sidebar-muted">{item.hint}</span>}
                </span>
            )}
            {!rail && badgeCount > 0 && (
                <span className="ml-auto grid min-h-[18px] min-w-[18px] place-items-center rounded-full bg-sb-danger px-1 text-[10px] font-bold text-white"
                    aria-label={`${badgeCount} balasan belum dibaca`}>
                    {badgeCount > 9 ? '9+' : badgeCount}
                </span>
            )}
            {featureState === 'new' && !rail && (
                <span className="sb-tag-new ml-auto rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-300">baru</span>
            )}
            {(item.soon || featureState === 'soon') && !rail && (
                <span className="ml-auto rounded-full bg-violet-500/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-300">soon</span>
            )}
        </>
    );
    if (item.soon) {
        return (
            <li>
                <button type="button" className={cn(cls, 'w-full text-left')} title={rail ? `${item.label} (segera hadir)` : undefined}
                    onClick={() => { soonAlert(); onNavigate?.(); }}>
                    {inner}
                </button>
            </li>
        );
    }
    if (item.external) {
        return <li><a href={item.href} className={cls} title={rail ? item.label : undefined}>{inner}</a></li>;
    }
    return (
        <li>
            <Link href={item.href} className={cls} title={rail ? item.label : undefined}
                aria-current={active ? 'page' : undefined} onClick={onNavigate}>
                {inner}
            </Link>
        </li>
    );
}

function NavGroupBlock({ group, rail, onNavigate, open, onToggle }: { group: NavGroup; rail: boolean; onNavigate?: () => void; open: boolean; onToggle: () => void }) {
    const { navNotificationState } = usePage<SharedProps>().props;
    const groupBadge = group.items.some((i) => i.badge === 'replies')
        ? (navNotificationState?.reply_unread_count ?? 0) : 0;
    if (rail) {
        return (
            <ul className="space-y-1">
                {group.items.map((i) => <NavLinkItem key={i.label} item={i} rail onNavigate={onNavigate} />)}
            </ul>
        );
    }
    return (
        <div>
            <button type="button" onClick={onToggle} aria-expanded={open}
                className="flex w-full items-center gap-3 rounded-[10px] px-3 py-2.5 text-[13.5px] font-semibold text-sb-sidebar-text/85 transition hover:bg-white/10 hover:text-white"
            >
                <group.icon size={19} strokeWidth={1.9} className="shrink-0" />
                <span className="flex-1 text-left leading-tight">{group.label}</span>
                {groupBadge > 0 && (
                    <span className="grid min-h-[18px] min-w-[18px] place-items-center rounded-full bg-sb-danger px-1 text-[10px] font-bold text-white"
                        aria-label={`${groupBadge} balasan belum dibaca`}>
                        {groupBadge > 9 ? '9+' : groupBadge}
                    </span>
                )}
                <ChevronRight size={14} className={cn('text-sb-sidebar-muted transition-transform duration-300', open && 'rotate-90')} />
            </button>
            <div className={cn('grid transition-[grid-template-rows,opacity] duration-[350ms] ease-in-out', open ? 'mt-1 grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0')}>
                <ul className="min-h-0 space-y-1 overflow-hidden pl-3">
                {group.items.map((i) => (
                    <NavLinkItem key={i.label} item={{ ...i, label: `— ${i.label}` as string }} rail={false} onNavigate={onNavigate} />
                ))}
                </ul>
            </div>
        </div>
    );
}

function NavSection({ label, rail, children }: PropsWithChildren<{ label: string; rail: boolean }>) {
    return (
        <div className="mt-5 first:mt-0">
            <div className={cn('px-1 pb-1.5', rail && 'px-2')}>
                {rail ? (
                    <div className="border-t border-white/15" aria-hidden="true" />
                ) : (
                    <div className="flex items-center gap-2.5">
                        <span className="text-[10px] font-bold uppercase tracking-[0.08em] text-sb-sidebar-muted">{label}</span>
                        <span className="h-px flex-1 bg-white/10" aria-hidden="true" />
                    </div>
                )}
            </div>
            {children}
        </div>
    );
}

const navGroups = [navRuangan, navBantuan, navSegera];

function Sidebar({ rail, onNavigate }: { rail: boolean; onNavigate?: () => void }) {
    const { branding } = usePage<SharedProps>().props;
    const { url } = usePage();
    const brandLogo = branding?.logo_url || asset('branding/smartbrail-mark.svg');
    const appName = branding?.app_name || 'SmartBrail';
    // Satu grup terbuka dalam satu waktu; grup aktif mengikuti URL.
    const [openGroup, setOpenGroup] = useState<string | null>(() =>
        navGroups.find((g) => g.items.some((i) => i.active(url)))?.key ?? null
    );
    useEffect(() => {
        const active = navGroups.find((g) => g.items.some((i) => i.active(url)));
        if (active) setOpenGroup(active.key);
    }, [url]);
    const groupProps = (key: string) => ({
        open: openGroup === key,
        onToggle: () => setOpenGroup((cur) => (cur === key ? null : key)),
    });
    return (
        <aside
            className={cn(
                'relative z-40 flex h-full flex-col overflow-hidden bg-sb-navy text-sb-sidebar-text transition-[width] duration-200',
                rail ? 'w-[76px]' : 'w-[252px]',
                'max-lg:fixed max-lg:inset-y-0 max-lg:left-0 max-lg:w-[252px] max-lg:shadow-sb-pop max-lg:transition-transform max-lg:duration-200',
            )}
            aria-label="Navigasi portal mahasiswa"
        >
            <div className="flex items-center gap-2.5 px-4 py-4">
                <img src={brandLogo} alt="" className="h-9 w-9 rounded-[12px] object-cover ring-1 ring-white/15 shadow-[0_6px_18px_-4px_rgba(37,99,235,0.55)]" />
                {!rail && (
                    <span className="leading-tight">
                        <strong className="block text-[15px] font-extrabold italic tracking-wide bg-gradient-to-r from-sky-300 via-sky-400 to-blue-600 bg-clip-text text-transparent">{appName}</strong>
                        <small className="text-[11px] font-medium text-sb-sidebar-muted">Portal Mahasiswa</small>
                    </span>
                )}
            </div>
            <nav className="sb-scroll-none min-h-0 flex-1 overflow-y-auto px-3 pb-[192px]" aria-label="Menu utama">
                <NavSection label="Menu Utama" rail={rail}>
                    <ul className="space-y-1">
                        {navTop.map((i) => <NavLinkItem key={i.label} item={i} rail={rail} onNavigate={onNavigate} />)}
                    </ul>
                    <NavGroupBlock group={navRuangan} rail={rail} onNavigate={onNavigate} {...groupProps(navRuangan.key)} />
                    <ul className="space-y-1">
                        {navMid.map((i) => <NavLinkItem key={i.label} item={i} rail={rail} onNavigate={onNavigate} />)}
                    </ul>
                </NavSection>
                <NavSection label="Laporan & Bantuan" rail={rail}>
                    <NavGroupBlock group={navBantuan} rail={rail} onNavigate={onNavigate} {...groupProps(navBantuan.key)} />
                </NavSection>
                <NavSection label="Coming Soon" rail={rail}>
                    <NavGroupBlock group={navSegera} rail={rail} onNavigate={onNavigate} {...groupProps(navSegera.key)} />
                </NavSection>
            </nav>
            {/* Dekorasi line-art kampus + gelombang — noninteraktif, di atas strip footer */}
            <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-8 h-[150px] opacity-55">
                <svg viewBox="0 0 252 150" className="h-full w-full" preserveAspectRatio="xMidYMax slice" fill="none" strokeLinecap="round">
                    {/* Gelombang latar */}
                    <path d="M-30 58 Q 55 30 130 52 T 290 46" stroke="rgba(34,211,238,.45)" strokeWidth="1.8"/>
                    <path d="M-30 72 Q 65 46 145 64 T 290 56" stroke="rgba(34,211,238,.25)" strokeWidth="1.4"/>
                    {/* Sayap kiri — gedung rendah bertingkat */}
                    <g stroke="rgba(147,197,253,.5)" strokeWidth="1.3">
                        <path d="M8 150 V108 h30 v-12 l10-8 10 8 v12 h22 V150"/>
                        <path d="M16 116h14M16 126h14M16 136h14"/>
                        <path d="M60 116h12M60 126h12M60 136h12"/>
                    </g>
                    {/* Menara utama — tower dengan puncak */}
                    <g stroke="rgba(147,197,253,.65)" strokeWidth="1.4">
                        <path d="M108 150 V64 h36 v-12 l14-10 14 10 v12 h36 V150"/>
                        <path d="M158 42 V28"/>
                        <circle cx="158" cy="24" r="3" stroke="rgba(34,211,238,.7)"/>
                        {/* Kolom jendela menara */}
                        <path d="M116 76h8M116 86h8M116 96h8M116 106h8M116 116h8M116 126h8"/>
                        <path d="M132 76h8M132 86h8M132 96h8M132 106h8M132 116h8M132 126h8"/>
                        <path d="M178 76h8M178 86h8M178 96h8M178 106h8M178 116h8M178 126h8"/>
                        <path d="M194 76h8M194 86h8M194 96h8M194 106h8M194 116h8M194 126h8"/>
                        {/* Pintu utama */}
                        <path d="M150 150 v-16 q8-10 16 0 v16"/>
                    </g>
                    {/* Sayap kanan */}
                    <g stroke="rgba(147,197,253,.5)" strokeWidth="1.3">
                        <path d="M214 150 V96 h26 v-10 l8-7 8 7 v10 h18 v54"/>
                        <path d="M222 104h12M222 114h12M222 124h12M222 134h12"/>
                        <path d="M256 104h10M256 114h10M256 124h10M256 134h10"/>
                    </g>
                    {/* Tanah / dasar */}
                    <path d="M0 150 H252" stroke="rgba(147,197,253,.4)" strokeWidth="1.5"/>
                    <path d="M30 150 q6-9 12 0M84 150 q6-9 12 0M216 150 q6-9 12 0" stroke="rgba(147,197,253,.35)" strokeWidth="1.2"/>
                </svg>
            </div>
            {/* Footer sidebar — strip paling bawah di bawah ilustrasi gedung */}
            {!rail && (
                <div className="absolute inset-x-0 bottom-0 flex h-8 items-center justify-center border-t border-white/10 bg-sb-navy/80 text-[10.5px] font-medium tracking-wide text-sb-sidebar-muted backdrop-blur-sm">
                    by BRAIL · SmartBrail 2026
                </div>
            )}
        </aside>
    );
}

/* ── Shell ────────────────────────────────────────────── */
export function AppShell({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { auth, flash, reverb } = usePage<SharedProps>().props;

    // Popup sambutan setelah login — sekali per sesi login (flash dikonsumsi server-side).
    useEffect(() => {
        if (!flash?.login_welcome) return;
        const Swal = (window as unknown as { Swal?: { fire: (o: object) => void } }).Swal;
        if (!Swal) return;
        Swal.fire({
            icon: 'success',
            title: `Selamat datang, ${auth.user?.nama_lengkap?.split(' ')[0] ?? 'Mahasiswa'}!`,
            text: 'Login berhasil — selamat belajar di SmartBrail.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: document.documentElement.dataset.theme === 'dark' ? '#101c33' : undefined,
            color: document.documentElement.dataset.theme === 'dark' ? '#e8effc' : undefined,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const [rail, setRail] = useState<boolean>(() => {
        try { return localStorage.getItem('smartbrail_student_rail') === '1'; } catch { return false; }
    });
    const [drawer, setDrawer] = useState(false);
    const [navSkeleton, setNavSkeleton] = useState<React.ReactNode | null>(null);
    const mainRef = useRef<HTMLElement>(null);
    const { url } = usePage();

    // Skeleton konten saat navigasi Inertia — hanya area main, delay 150ms anti-kilat.
    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | null = null;
        const offStart = router.on('start', (e) => {
            const target = e.detail.visit.url?.pathname ?? '';
            timer = setTimeout(() => setNavSkeleton(skeletonFor(target)), 150);
        });
        const offFinish = router.on('finish', () => {
            if (timer) clearTimeout(timer);
            setNavSkeleton(null);
        });
        return () => { offStart(); offFinish(); if (timer) clearTimeout(timer); };
    }, []);

    useEffect(() => { setDrawer(false); }, [url]);

    // Heartbeat presence — ping ringan tiap ~2,5 menit agar last_seen_at tetap
    // segar walau pengguna pasif (tab dibiarkan terbuka).
    useEffect(() => {
        const ping = () => fetch(route('session.ping'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(() => { /* presence saja */ });
        const t = setInterval(ping, 150000);
        return () => clearInterval(t);
    }, []);

    // Realtime: balasan saran / pengumuman / status pengajuan menyegarkan lonceng.
    useEffect(() => {
        const uid = auth.user?.id;
        if (!reverb || !uid) return;
        const refresh = () => router.reload({ only: ['navNotificationState'] });
        return subscribePrivate(reverb, `mahasiswa.${uid}`, {
            'notifikasi.baru': refresh,
            'pengajuan.updated': refresh,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reverb?.key, auth.user?.id]);

    const toggleRail = () => {
        setRail((r) => {
            try { localStorage.setItem('smartbrail_student_rail', r ? '0' : '1'); } catch { /* */ }
            return !r;
        });
    };

    // Reset scroll konten saat navigasi internal
    useEffect(() => { mainRef.current?.scrollTo({ top: 0 }); }, [url]);

    return (
        <div className="flex h-dvh overflow-hidden">
            {drawer && (
                <div className="fixed inset-0 z-30 bg-sb-navy-2/50 lg:hidden" onClick={() => setDrawer(false)} aria-hidden="true" />
            )}
            <div className={cn('h-full max-lg:transition-none', !drawer && 'max-lg:-translate-x-full max-lg:[&_aside]:-translate-x-full')}
                style={{ display: 'contents' }}>
                <div className={cn('h-full', drawer ? 'max-lg:[&>aside]:translate-x-0' : 'max-lg:[&>aside]:-translate-x-full')}>
                    <Sidebar rail={rail} onNavigate={() => setDrawer(false)} />
                </div>
            </div>

            <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
                <header className="z-20 flex h-[62px] shrink-0 items-center gap-3 border-b border-sb-border bg-sb-surface/90 px-4 backdrop-blur sm:px-5">
                    <button type="button" aria-label={drawer ? 'Tutup menu' : 'Buka menu'}
                        onClick={() => { if (window.innerWidth < 1024) setDrawer((d) => !d); else toggleRail(); }}
                        className="grid h-9 w-9 place-items-center rounded-[10px] text-sb-muted transition hover:bg-sb-hover hover:text-sb-text"
                    >
                        {drawer ? <X size={18} className="lg:hidden" /> : <Menu size={18} />}
                    </button>
                    <h1 className="truncate text-[16px] font-bold">{title ?? ''}</h1>
                    {/* Pencarian menu — di tengah topbar, lebar agar hasil berkonteks terbaca */}
                    <div className="hidden flex-1 justify-center px-4 sm:flex">
                        <MenuSearch />
                    </div>
                    <div className="ml-auto flex items-center gap-1.5">
                        <NotificationsBell />
                        <ThemeToggle />
                        <AccountMenu />
                    </div>
                </header>
                <main ref={mainRef} className="sb-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-10 pt-3 sm:px-6 sm:pt-5" id="sbMain">
                    <div className="mx-auto flex min-h-full max-w-[1360px] flex-col">
                        <div className="flex-1">
                            {auth.user?.must_change_password && (
                                <div className="mb-4 flex items-start gap-3 rounded-[12px] border border-sb-warn/35 bg-sb-warn-bg px-4 py-3 text-[13px] leading-snug text-sb-text">
                                    <ShieldAlert size={17} className="mt-0.5 shrink-0 text-sb-warn" />
                                    <span>
                                        <strong className="font-bold">Akun masih memakai password sementara.</strong>
                                        {' '}Anda bebas memakai semua fitur, tetapi sebaiknya segera amankan akun —{' '}
                                        <Link href={route('mahasiswa.password.change')} className="font-bold text-sb-warn underline underline-offset-2">ganti password sekarang</Link>.
                                    </span>
                                </div>
                            )}
                            {navSkeleton ? (
                                <LoadingRegion label="Memuat halaman">{navSkeleton}</LoadingRegion>
                            ) : children}
                        </div>
                        <footer className="mt-10 border-t border-sb-border/70 pb-2 pt-4 text-center text-[11.5px] text-sb-muted">
                            <p>
                                <strong className="font-semibold text-sb-text2">SmartBrail</strong>
                                {' — Sistem Informasi Manajemen BRAIL · '}
                                <a href="https://www.polibatam.ac.id" target="_blank" rel="noreferrer" className="hover:text-sb-primary hover:underline">Politeknik Negeri Batam</a>
                            </p>
                            <p className="mt-0.5 text-sb-muted/80">Butuh bantuan? Buka menu Laporan &amp; Bantuan di sidebar.</p>
                        </footer>
                    </div>
                </main>
            </div>

            <AssistantWidget />
        </div>
    );
}
