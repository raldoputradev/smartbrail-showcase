import { useEffect, useRef, useState, type PropsWithChildren, type ReactNode } from 'react';
import { usePage, router } from '@inertiajs/react';
import {
    Activity, Bell, CheckCheck, ChevronRight, LayoutDashboard, LogOut, Menu, Moon,
    ShieldAlert, ShieldCheck, Sparkles, Sun, UserRound, X, Zap,
    Cpu, Calendar, Fingerprint, ScrollText, DoorOpen, CalendarCheck, Brush,
    GraduationCap, BookOpen, FlaskConical, Users, Megaphone, MessageSquare,
    ListTodo, ClipboardCheck, BarChart3, Settings,
    ClipboardList, Building2, FileText, FileCode, Check, User, Radar,
} from 'lucide-react';
import type {
    AdminNotif, AuthUser, NotifOpsItem, SharedProps,
    SidebarGroup, SidebarLink, SidebarSection,
} from '@admin/types';
import { asset } from '@admin/lib/asset';
import { subscribePrivate } from '@admin/lib/live';
import { cn } from '@admin/lib/utils';

const ICONS: Record<string, typeof LayoutDashboard> = {
    'layout-dashboard': LayoutDashboard, 'sparkles': Sparkles, 'cpu': Cpu,
    'calendar': Calendar, 'fingerprint': Fingerprint, 'scroll-text': ScrollText,
    'door-open': DoorOpen, 'calendar-check': CalendarCheck, 'brush': Brush,
    'graduation-cap': GraduationCap, 'book-open': BookOpen, 'flask-conical': FlaskConical,
    'users': Users, 'megaphone': Megaphone, 'message-square': MessageSquare,
    'list-todo': ListTodo, 'clipboard-check': ClipboardCheck, 'bar-chart-3': BarChart3,
    'settings': Settings, 'activity': Activity,
    'clipboard-list': ClipboardList, 'building-2': Building2, 'file-text': FileText,
    'file-code': FileCode, 'shield-check': ShieldCheck, 'user': User, 'radar': Radar,
};

function soonAlert(e: React.MouseEvent) {
    e.preventDefault();
    const Swal = (window as unknown as { Swal?: { fire: (o: object) => void } }).Swal;
    if (Swal) {
        Swal.fire({
            icon: 'info',
            title: 'Segera hadir',
            text: 'Fitur ini masih tahap pengembangan.',
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#4a90d9',
            background: document.documentElement.dataset.theme === 'dark' ? '#101c33' : undefined,
            color: document.documentElement.dataset.theme === 'dark' ? '#e8effc' : undefined,
        });
    }
}

/** Aksen warna ikon menu — deterministik per key agar konsisten antar-halaman. */
function accentOf(key: string): number {
    let h = 0;
    for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;
    return h % 10;
}

function NavLink({ item, child = false }: { item: SidebarLink; child?: boolean }) {
    const Icon = ICONS[item.icon] ?? LayoutDashboard;
    return (
        <a
            href={item.url}
            data-accent={accentOf(item.label)}
            className={cn('ad-nav-link', child ? 'ad-nav-link--child' : 'ad-nav-link--top', item.active && 'is-active')}
            onClick={item.soon ? soonAlert : undefined}
        >
            <Icon />
            <span className="ad-nav-link__label">{item.label}</span>
            {item.tag ? <span className={cn('ad-nav-link__tag', item.tag === 'Baru' && 'is-new')}>{item.tag}</span> : null}
            {item.badge != null && item.badge > 0 && (
                <span className="ad-nav-link__badge">{item.badge > 9 ? '9+' : item.badge}</span>
            )}
        </a>
    );
}

/** Grup submenu collapsible (controlled) — accordion: hanya satu grup terbuka. */
function NavGroup({ group, open, onToggle }: { group: SidebarGroup; open: boolean; onToggle: () => void }) {
    const Icon = ICONS[group.icon] ?? LayoutDashboard;
    // Penanda di judul grup bila ada submenu berbadge — terlihat walau grup tertutup.
    const groupBadge = group.children.reduce((s, c) => s + (c.badge ?? 0), 0);
    return (
        <div className={cn('ad-nav-group', open && 'open', group.active && 'is-parent-active')}>
            <button
                type="button" className="ad-nav-group__btn"
                data-accent={accentOf(group.key ?? group.label)}
                onClick={onToggle} aria-expanded={open}
            >
                <Icon />
                <span className="ad-nav-group__label">{group.label}</span>
                {groupBadge > 0 && (
                    <span className="ad-nav-group__badge" aria-label={`${groupBadge} item belum dibaca`}>
                        {groupBadge > 9 ? '9+' : groupBadge}
                    </span>
                )}
                <ChevronRight className="ad-nav-group__caret" />
            </button>
            <div className="ad-nav-sub">
                {group.children.map((child) => <NavLink key={child.url} item={child} child />)}
            </div>
        </div>
    );
}

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** Lonceng notifikasi — antrian operasional + pengumuman belum dibaca. */
function NotifBell({ notif }: { notif: AdminNotif }) {
    const [open, setOpen] = useState(false);
    const [ops, setOps] = useState<NotifOpsItem[]>(notif.ops);
    const [anns, setAnns] = useState(notif.announcements);
    const [total, setTotal] = useState(notif.total);
    const [busy, setBusy] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const post = async (url: string, body?: unknown) => {
        const res = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json', 'Content-Type': 'application/json',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        if (!res.ok) throw new Error('notif request failed');
        return res.json();
    };

    const clickOps = async (e: React.MouseEvent<HTMLAnchorElement>, it: NotifOpsItem) => {
        if (it.id === 'ops-suggest') return; // persistent — langsung navigasi
        e.preventDefault();
        try {
            const d = await post(notif.urls.ops_dismiss, {
                ops_key: it.id, fingerprint: it.fingerprint ?? `${it.id}:`,
            });
            setOps(d.ops ?? []); setTotal(d.total_unread_count ?? 0);
        } catch { /* tetap navigasi */ }
        window.location.href = it.url;
    };

    const dismissOne = async (e: React.MouseEvent, it: NotifOpsItem) => {
        e.preventDefault(); e.stopPropagation();
        try {
            const d = await post(notif.urls.ops_dismiss, {
                ops_key: it.id, fingerprint: it.fingerprint ?? `${it.id}:`,
            });
            setOps(d.ops ?? []); setTotal(d.total_unread_count ?? 0);
        } catch { /* abaikan */ }
    };

    const readOne = async (e: React.MouseEvent, id: number) => {
        e.preventDefault(); e.stopPropagation();
        try {
            const d = await post(`${notif.urls.read_one}/${id}/read`);
            setAnns((xs) => xs.filter((x) => x.id !== id));
            setTotal(d.unread_count ?? 0);
        } catch { /* abaikan */ }
    };

    const markAll = async () => {
        if (busy) return;
        setBusy(true);
        try {
            const d = await post(notif.urls.read_all);
            setAnns([]); setOps(d.ops ?? ops);
            setTotal(d.total_unread_count ?? 0);
        } catch { setAnns([]); }
        setBusy(false);
    };

    return (
        <div className="ad-notif" ref={wrapRef}>
            <button
                type="button" className={cn('ad-icon-btn', total > 0 && 'has-unread')} aria-label="Notifikasi"
                aria-expanded={open} onClick={() => setOpen((o) => !o)}
            >
                <Bell />
                {total > 0 && <span className="ad-notif__dot">{total > 9 ? '9+' : total}</span>}
            </button>
            {open && (
                <div className="ad-notif__panel" role="dialog" aria-label="Panel notifikasi">
                    <div className="ad-notif__head">Notifikasi</div>
                    <div className="ad-notif__scroll">
                        {ops.length > 0 && (
                            <>
                                <div className="ad-notif__sec">
                                    <Zap style={{ width: 13, height: 13 }} /> Antrian operasional
                                    <span className="ad-notif__sec-count">{ops.length} item</span>
                                </div>
                                {ops.map((it) => (
                                    <a key={it.id} href={it.url} className="ad-notif__item" onClick={(e) => clickOps(e, it)}>
                                        <div className="ad-notif__item-title"><span className="ad-notif__item-t">{it.title}</span></div>
                                        <div className="ad-notif__item-body">{it.body}</div>
                                        {it.id !== 'ops-suggest' && (
                                            <button type="button" className="ad-notif__read" title="Tandai dibaca"
                                                aria-label="Tandai notifikasi dibaca" onClick={(e) => dismissOne(e, it)}>
                                                <Check />
                                            </button>
                                        )}
                                    </a>
                                ))}
                            </>
                        )}
                        <div className="ad-notif__sec">
                            <Megaphone style={{ width: 13, height: 13 }} /> Pengumuman
                            {anns.length > 0 && (
                                <button type="button" className="ad-notif__clear" onClick={markAll} disabled={busy}>
                                    <CheckCheck style={{ width: 12, height: 12 }} /> Tandai dibaca
                                </button>
                            )}
                        </div>
                        {anns.length === 0 ? (
                            <div className="ad-notif__empty">
                                Tidak ada pengumuman baru.
                                <span>Riwayat ada di menu Pengumuman.</span>
                            </div>
                        ) : anns.map((a) => (
                            <div key={a.id} className="ad-notif__item ad-notif__item--ann">
                                <div className="ad-notif__item-title">
                                    <span className="ad-notif__item-t">{a.judul}</span>
                                    {a.prioritas !== 'normal' && <span className="ad-notif__prio">{a.prioritas}</span>}
                                </div>
                                <div className="ad-notif__item-body">{a.isi}</div>
                                <div className="ad-notif__item-meta">{a.author}{a.waktu ? ` · ${a.waktu}` : ''}</div>
                                <button type="button" className="ad-notif__read" title="Tandai dibaca"
                                    aria-label="Tandai pengumuman dibaca" onClick={(e) => readOne(e, a.id)}>
                                    <Check />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="ad-notif__foot">
                        <a href={notif.urls.announcements}>Riwayat pengumuman</a>
                        <a href={notif.urls.notifications}>Antrian notifikasi</a>
                    </div>
                </div>
            )}
        </div>
    );
}

/** Dropdown profil di topbar — avatar+nama+role+chevron → Profil / Ganti Password / Keluar. */
function ProfileDropdown({ user, onLogout, logoutBusy }: {
    user: AuthUser | null; onLogout: () => void; logoutBusy: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [pwBusy, setPwBusy] = useState(false);
    const [pwNote, setPwNote] = useState<string | null>(null);
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const roleLabel = { admin: 'Admin', laboran: 'Laboran', dosen: 'Dosen', mahasiswa: 'Mahasiswa' }[user?.role ?? ''] ?? (user?.role ?? '—');

    const gantiPassword = async () => {
        if (pwBusy) return;
        setPwBusy(true); setPwNote(null);
        try {
            const res = await fetch(route('profile.password.reset-link'), {
                method: 'POST', credentials: 'same-origin', redirect: 'follow',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
            setPwNote(res.ok
                ? `Tautan "Atur Ulang Password" dikirim ke ${user?.email ?? 'email Anda'}.`
                : 'Gagal mengirim tautan reset. Coba lagi.');
        } catch {
            setPwNote('Gagal mengirim tautan reset. Coba lagi.');
        }
        setPwBusy(false);
    };

    return (
        <div className="ad-profmenu" ref={wrapRef}>
            <button
                type="button" className="ad-profmenu__btn"
                aria-haspopup="menu" aria-expanded={open}
                onClick={() => { setOpen((o) => !o); setPwNote(null); }}
            >
                <span className="ad-sidebar__ava" style={{ width: 34, height: 34, position: 'relative' }}>
                    {user?.profile_photo_url
                        ? <img src={user.profile_photo_url} alt="" />
                        : (user?.nama_lengkap ?? '?').charAt(0).toUpperCase()}
                    {user?.must_change_password && (
                        <span style={{ position: 'absolute', top: -2, right: -2, width: 9, height: 9, borderRadius: '50%', background: '#d97706', border: '2px solid var(--ad-pop)' }} aria-label="Password sementara aktif" />
                    )}
                </span>
                <span className="ad-profmenu__id">
                    <span className="ad-profmenu__name">{user?.nama_lengkap ?? '—'}</span>
                    <span className="ad-profmenu__role">{roleLabel}</span>
                </span>
                <ChevronRight className={cn('ad-profmenu__caret', open && 'open')} />
            </button>
            {open && (
                <div className="ad-profmenu__drop" role="menu" aria-label="Menu profil">
                    <a className="ad-profmenu__item" role="menuitem" href={user ? route('profiles.show', user.id) : '#'}>
                        <UserRound style={{ width: 14, height: 14 }} /> Profil Saya
                    </a>
                    {user?.must_change_password && (
                        <a className="ad-profmenu__item" role="menuitem" href="/ganti-password" style={{ color: '#d97706', fontWeight: 600 }}>
                            <ShieldAlert style={{ width: 14, height: 14 }} /> Amankan Akun
                            <span style={{ marginLeft: 'auto', width: 7, height: 7, borderRadius: '50%', background: '#d97706' }} aria-hidden="true" />
                        </a>
                    )}
                    <button
                        type="button" className="ad-profmenu__item" role="menuitem"
                        onClick={gantiPassword} disabled={pwBusy}
                    >
                        <ShieldCheck style={{ width: 14, height: 14 }} /> {pwBusy ? 'Mengirim…' : 'Ganti Password'}
                    </button>
                    {pwNote && <div className="ad-profmenu__note">{pwNote}</div>}
                    <div className="ad-profmenu__sep" />
                    <button
                        type="button" className="ad-profmenu__item ad-profmenu__item--danger" role="menuitem"
                        onClick={onLogout} disabled={logoutBusy}
                    >
                        <LogOut style={{ width: 14, height: 14 }} /> Keluar
                    </button>
                </div>
            )}
        </div>
    );
}

/** Menu profil — popover di atas kartu user: detail singkat + profil + keluar. */
function UserMenu({ user, onLogout, logoutBusy }: {
    user: AuthUser | null; onLogout: () => void; logoutBusy: boolean;
}) {
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const roleLabel = { admin: 'Admin', laboran: 'Laboran', dosen: 'Dosen', mahasiswa: 'Mahasiswa' }[user?.role ?? ''] ?? (user?.role ?? '—');
    const ava = user?.profile_photo_url
        ? <img src={user.profile_photo_url} alt="" />
        : (user?.nama_lengkap ?? '?').charAt(0).toUpperCase();

    return (
        <div className="ad-usermenu" ref={wrapRef}>
            {open && (
                <div className="ad-userpop" role="dialog" aria-label="Menu profil">
                    <div className="ad-userpop__head">
                        <div className="ad-sidebar__ava">{ava}</div>
                        <div style={{ minWidth: 0 }}>
                            <div className="ad-userpop__name">{user?.nama_lengkap ?? '—'}</div>
                            <div className="ad-userpop__role">{roleLabel}</div>
                        </div>
                    </div>
                    <div className="ad-userpop__meta">
                        <div><span>NITK/NIDN</span><strong>{user?.nitk_nidn || '—'}</strong></div>
                        <div><span>Email</span><strong>{user?.email || '—'}</strong></div>
                    </div>
                    <a className="ad-userpop__link" href={user ? route('profiles.show', user.id) : '#'}>
                        <UserRound style={{ width: 14, height: 14 }} /> Lihat Profil Lengkap
                    </a>
                    <button type="button" className="ad-userpop__out" onClick={onLogout} disabled={logoutBusy}>
                        <LogOut style={{ width: 14, height: 14 }} /> Keluar
                    </button>
                </div>
            )}
            <button
                type="button" className="ad-sidebar__user ad-sidebar__user--btn"
                onClick={() => setOpen((o) => !o)} aria-expanded={open} title="Menu profil"
            >
                <div className="ad-sidebar__ava">{ava}</div>
                <div style={{ minWidth: 0, textAlign: 'left' }}>
                    <div className="ad-sidebar__uname" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {user?.nama_lengkap ?? '—'}
                    </div>
                    <div className="ad-sidebar__urole">{roleLabel}</div>
                </div>
                <ChevronRight className={cn('ad-usermenu__caret', open && 'open')} />
            </button>
        </div>
    );
}

function useTheme() {
    const [theme, setTheme] = useState<'light' | 'dark'>(() =>
        (document.documentElement.dataset.theme as 'light' | 'dark') || 'light');
    const toggle = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('dashboard_theme', next); } catch { /* abaikan */ }
        setTheme(next);
    };
    return { theme, toggle };
}

function CampusArt() {
    return (
        <div className="ad-sidebar__art" aria-hidden="true">
            <svg viewBox="0 0 252 150" preserveAspectRatio="xMidYMax slice" fill="none" strokeLinecap="round">
                <path d="M-30 58 Q 55 30 130 52 T 290 46" stroke="rgba(34,211,238,.45)" strokeWidth="1.8"/>
                <path d="M-30 72 Q 65 46 145 64 T 290 56" stroke="rgba(34,211,238,.25)" strokeWidth="1.4"/>
                <g stroke="rgba(147,197,253,.5)" strokeWidth="1.3">
                    <path d="M8 150 V108 h30 v-12 l10-8 10 8 v12 h22 V150"/>
                    <path d="M16 116h14M16 126h14M16 136h14"/><path d="M60 116h12M60 126h12M60 136h12"/>
                </g>
                <g stroke="rgba(147,197,253,.65)" strokeWidth="1.4">
                    <path d="M108 150 V64 h36 v-12 l14-10 14 10 v12 h36 V150"/>
                    <path d="M158 42 V28"/><circle cx="158" cy="24" r="3" stroke="rgba(34,211,238,.7)"/>
                    <path d="M116 76h8M116 86h8M116 96h8M116 106h8M116 116h8M116 126h8"/>
                    <path d="M132 76h8M132 86h8M132 96h8M132 106h8M132 116h8M132 126h8"/>
                    <path d="M178 76h8M178 86h8M178 96h8M178 106h8M178 116h8M178 126h8"/>
                    <path d="M194 76h8M194 86h8M194 96h8M194 106h8M194 116h8M194 126h8"/>
                    <path d="M150 150 v-16 q8-10 16 0 v16"/>
                </g>
                <g stroke="rgba(147,197,253,.5)" strokeWidth="1.3">
                    <path d="M214 150 V96 h26 v-10 l8-7 8 7 v10 h18 v54"/>
                    <path d="M222 104h12M222 114h12M222 124h12M222 134h12"/>
                    <path d="M256 104h10M256 114h10M256 124h10M256 134h10"/>
                </g>
                <path d="M0 150 H252" stroke="rgba(147,197,253,.4)" strokeWidth="1.5"/>
            </svg>
        </div>
    );
}

interface ShellProps extends PropsWithChildren {
    menu: SidebarSection[];
    notif: AdminNotif;
    title: string;
    subtitle?: string;
    actions?: ReactNode;
}

export function AdminShell({ menu, notif, title, subtitle, actions, children }: ShellProps) {
    const { auth, branding, reverb, flash } = usePage<SharedProps>().props;
    const user = auth.user;

    // Popup sambutan setelah login — sekali per sesi login (flash dikonsumsi server-side).
    useEffect(() => {
        if (!flash?.login_welcome) return;
        const Swal = (window as unknown as { Swal?: { fire: (o: object) => void } }).Swal;
        if (!Swal) return;
        const dark = document.documentElement.dataset.theme === 'dark';
        const peran = { admin: 'Administrator', laboran: 'Laboran', dosen: 'Dosen', mahasiswa: 'Mahasiswa' }[user?.role ?? ''] ?? 'Pengguna';
        Swal.fire({
            icon: 'success',
            title: `Selamat datang, ${user?.nama_lengkap?.split(' ')[0] ?? 'Sobat'}!`,
            text: `Login berhasil — Anda masuk sebagai ${peran} SmartBrail.`,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: dark ? '#101c33' : undefined,
            color: dark ? '#e8effc' : undefined,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Realtime: pengajuan ruangan baru/keputusan memicu refresh badge & lonceng
    // laboran/admin tanpa reload halaman.
    const isStaff = user?.role === 'admin' || user?.role === 'laboran';
    useEffect(() => {
        if (!reverb || !isStaff) return;
        return subscribePrivate(reverb, 'absensi.staff.laboran', {
            'pengajuan.updated': () => router.reload({ only: ['notif', 'menu'] }),
            'notifikasi.baru': () => router.reload({ only: ['notif', 'menu'] }),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reverb?.key, isStaff]);

    // Kanal bersama: pengumuman baru menyegarkan lonceng semua role
    // (admin/laboran/dosen) tanpa reload halaman.
    useEffect(() => {
        if (!reverb) return;
        return subscribePrivate(reverb, 'notifikasi.semua', {
            'notifikasi.baru': () => router.reload({ only: ['notif', 'menu'] }),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reverb?.key]);

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

    const logoSrc = branding?.logo_url || asset('branding/smartbrail-logo.png');
    const appName = branding?.app_name || 'SmartBrail';
    const { theme, toggle } = useTheme();
    const [navOpen, setNavOpen] = useState(false);
    const [logoutBusy, setLogoutBusy] = useState(false);
    // Mode rail (sidebar menyempit ke ikon) — key localStorage disamakan
    // dengan sidebar Blade agar status bertahan lintas React ↔ Blade.
    const [rail, setRail] = useState<boolean>(() => {
        try { return localStorage.getItem('dashboard_sidebar_collapsed') === '1'; } catch { return false; }
    });
    const toggleRail = () => setRail((r) => {
        try { localStorage.setItem('dashboard_sidebar_collapsed', r ? '0' : '1'); } catch { /* */ }
        return !r;
    });

    // Accordion: hanya satu grup terbuka. Key localStorage disamakan dengan
    // sidebar Blade agar status bertahan lintas navigasi React ↔ Blade.
    const groups = menu.flatMap((s) => s.nodes).filter((n): n is SidebarGroup => n.kind === 'group');
    const activeGroupKey = groups.find((g) => g.active)?.key ?? null;
    const [openKey, setOpenKey] = useState<string | null>(() => {
        if (activeGroupKey) return activeGroupKey;
        try {
            return groups.find((g) => localStorage.getItem(`dashboard_sidebar_group_${g.key}`) === 'open')?.key ?? null;
        } catch { return null; }
    });
    const openKeyRef = useRef(openKey);
    const toggleGroup = (key: string) => {
        const next = openKeyRef.current === key ? null : key;
        openKeyRef.current = next;
        setOpenKey(next);
        try {
            groups.forEach((g) =>
                localStorage.setItem(`dashboard_sidebar_group_${g.key}`, g.key === next ? 'open' : 'closed'));
        } catch { /* abaikan */ }
    };

    const logout = () => {
        if (logoutBusy) return;
        setLogoutBusy(true);
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('logout');
        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token';
        csrf.value = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        form.appendChild(csrf);
        document.body.appendChild(form);
        form.submit();
    };

    return (
        <div className={cn('ad-shell', navOpen && 'nav-open', rail && 'ad-shell--rail')}>
            <div className="ad-overlay" onClick={() => setNavOpen(false)} aria-hidden="true" />
            <aside className="ad-sidebar">
                <div className="ad-sidebar__brand">
                    <img src={logoSrc} alt={appName} className="ad-sidebar__logo" />
                    <div className="ad-sidebar__brand-txt">
                        <span className="ad-sidebar__brand-name">{appName}</span>
                        <div className="ad-sidebar__brand-sub">Portal {{ admin: 'Admin', laboran: 'Laboran', dosen: 'Dosen' }[user?.role ?? ''] ?? 'Staf'}</div>
                    </div>
                    <button
                        type="button" className="ad-icon-btn ad-close-nav"
                        style={{ marginLeft: 'auto', border: 0, color: 'var(--ad-sidebar-muted)' }}
                        onClick={() => setNavOpen(false)} aria-label="Tutup menu"
                    ><X /></button>
                </div>
                <nav className="ad-sidebar__nav" aria-label="Navigasi admin">
                    {menu.map((section, si) => (
                        <div key={section.title ?? `s${si}`} className="ad-nav-section">
                            {section.title ? <div className="ad-nav-title">{section.title}</div> : null}
                            {section.nodes.map((node, ni) => node.kind === 'divider'
                                ? <div key={node.key ?? `d${ni}`} className="ad-nav-divider" />
                                : node.kind === 'group'
                                    ? <NavGroup key={node.key} group={node} open={openKey === node.key}
                                        onToggle={() => { if (rail) { toggleRail(); } toggleGroup(node.key); }} />
                                    : <NavLink key={node.url} item={node} />)}
                        </div>
                    ))}
                </nav>
                <CampusArt />
                <div className="ad-sidebar__copy">SmartBrail v2.1.0 · by AIOT 2026</div>
                <UserMenu user={user} onLogout={logout} logoutBusy={logoutBusy} />
                <button type="button" className="ad-sidebar__logout" onClick={logout} disabled={logoutBusy}>
                    <LogOut style={{ width: 15, height: 15 }} /> <span>Logout</span>
                </button>
            </aside>

            <div className="ad-main">
                <header className="ad-topbar">
                    <button
                        type="button" className="ad-icon-btn ad-burger"
                        onClick={() => { if (window.innerWidth < 1024) setNavOpen(true); else toggleRail(); }}
                        aria-label={rail ? 'Perbesar sidebar' : 'Perkecil sidebar'}
                        title={rail ? 'Perbesar sidebar' : 'Perkecil sidebar'}
                    ><Menu /></button>
                    <div style={{ minWidth: 0 }}>
                        <div className="ad-topbar__title">{title}</div>
                        {subtitle ? <div className="ad-topbar__sub">{subtitle}</div> : null}
                    </div>
                    <div className="ad-topbar__spacer" />
                    <button
                        type="button" className="ad-icon-btn" aria-label="Ganti tema" onClick={toggle}
                    >{theme === 'dark' ? <Moon /> : <Sun />}</button>
                    <NotifBell notif={notif} />
                    <ProfileDropdown user={user} onLogout={logout} logoutBusy={logoutBusy} />
                </header>
                <main className="ad-content">
                    {user?.must_change_password && (
                        <div className="ad-mesin-flash" style={{ background: 'color-mix(in srgb, #d97706 12%, transparent)', borderColor: 'color-mix(in srgb, #d97706 35%, transparent)', color: 'var(--ad-text)', display: 'flex', gap: 10, alignItems: 'flex-start', marginBottom: 14 }}>
                            <ShieldAlert style={{ width: 17, height: 17, flexShrink: 0, color: '#d97706', marginTop: 1 }} />
                            <span>
                                <strong>Akun masih memakai password sementara.</strong>
                                {' '}Anda bebas memakai semua fitur, tetapi sebaiknya segera amankan akun —{' '}
                                <a href="/ganti-password" style={{ color: '#d97706', fontWeight: 700, textDecoration: 'underline', textUnderlineOffset: 2 }}>ganti password sekarang</a>.
                            </span>
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
