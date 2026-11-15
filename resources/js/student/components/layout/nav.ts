import {
    BookOpen, Bot, CalendarDays, ClipboardCheck, DoorOpen, History, Home, LayoutList,
    MessageCircle, Rocket, Ticket, Users,
    type LucideIcon,
} from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    active: (url: string) => boolean;
    hint?: string;
    /** Nama section sidebar — ditampilkan sebagai konteks pada hasil pencarian */
    section?: string;
    /** true = halaman masih Blade (navigasi penuh, bukan Inertia) */
    external?: boolean;
    /** true = fitur rancangan — klik memunculkan popup "segera hadir", bukan navigasi */
    soon?: boolean;
    /** Kunci penanda angka dari navNotificationState (mis. 'replies' = balasan laporan belum dibaca) */
    badge?: 'replies';
    /** Kunci FeatureBadge (shared prop `features`) — label BARU/SOON dari admin */
    feature?: string;
    /** Kunci data-tour untuk sorotan panduan penggunaan */
    tour?: string;
}

export interface NavGroup {
    key: string;
    label: string;
    icon: LucideIcon;
    items: NavItem[];
}

const isRiwayat = (url: string) => url.includes('tab=riwayat');

export const navTop: NavItem[] = [
    {
        label: 'Beranda', href: route('mahasiswa.beranda'), icon: Home,
        active: (u) => u.startsWith('/mahasiswa/beranda'), tour: 'beranda',
    },
    {
        label: 'Absensi', href: route('mahasiswa.absensi'), icon: History, hint: 'Riwayat',
        active: (u) => u.startsWith('/mahasiswa/absensi'), tour: 'absensi',
    },
];

export const navRuangan: NavGroup = {
    key: 'ruangan', label: 'Ruangan', icon: DoorOpen,
    items: [
        {
            label: 'Ajukan Ruangan', href: route('mahasiswa.ruangan'), icon: DoorOpen,
            active: (u) => u.startsWith('/mahasiswa/ruangan') && !isRiwayat(u),
            feature: 'pengajuan-ruangan', tour: 'ajukan-ruangan',
        },
        {
            label: 'Riwayat Pengajuan', href: route('mahasiswa.ruangan', { tab: 'riwayat' }), icon: LayoutList,
            active: (u) => u.startsWith('/mahasiswa/ruangan') && isRiwayat(u),
        },
    ],
};

export const navMid: NavItem[] = [
    {
        label: 'Kalender', href: route('mahasiswa.calendar'), icon: CalendarDays,
        active: (u) => u.startsWith('/mahasiswa/kalender'), tour: 'kalender',
    },
    {
        label: 'Tim PBL', href: route('mahasiswa.team'), icon: Users,
        active: (u) => u.startsWith('/mahasiswa/tim'), feature: 'pbl-tim', tour: 'tim-pbl',
    },
];

export const navBantuan: NavGroup = {
    key: 'bantuan', label: 'Laporan & Bantuan', icon: Ticket,
    items: [
        {
            label: 'Riwayat Laporan', href: route('mahasiswa.suggestions'), icon: Ticket,
            active: (u) => u.startsWith('/mahasiswa/saran'), badge: 'replies', tour: 'laporan',
        },
        {
            label: 'Panduan Penggunaan', href: route('mahasiswa.guide'), icon: BookOpen,
            active: (u) => u.startsWith('/mahasiswa/panduan'), tour: 'panduan',
        },
    ],
};

export const navSegera: NavGroup = {
    key: 'segera', label: 'Coming Soon', icon: Rocket,
    items: [
        {
            label: 'Penilaian Tim', href: route('mahasiswa.coming-soon'), icon: ClipboardCheck,
            active: () => false, soon: true,
        },
        {
            label: 'Chat Grup Tim', href: route('mahasiswa.coming-soon'), icon: MessageCircle,
            active: () => false, soon: true,
        },
        {
            label: 'Riwayat Chat Asisten', href: route('mahasiswa.coming-soon'), icon: Bot,
            active: () => false, soon: true,
        },
    ],
};

export const allNavItems: NavItem[] = [
    ...navTop.map((i) => ({ ...i, section: 'Menu Utama' })),
    ...navRuangan.items.map((i) => ({ ...i, section: 'Ruangan' })),
    ...navMid.map((i) => ({ ...i, section: 'Menu Utama' })),
    ...navBantuan.items.map((i) => ({ ...i, section: 'Laporan & Bantuan' })),
    ...navSegera.items.map((i) => ({ ...i, section: 'Coming Soon' })),
];
