import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen, Bot, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, CircleAlert,
    ClipboardList, DoorOpen, Fingerprint, Flag, HelpCircle, Home, Loader2, Maximize2,
    Minimize2, Paperclip, RotateCcw, SendHorizontal, Ticket, Users, X, type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { apiGet, apiPost } from '../lib/api';
import { Skeleton } from './ui/skeleton';
import type { SharedProps, Slot, KetersediaanResp } from '../types';

// ---------- Data konteks dari backend (semuanya milik user login) ----------
interface Konteks {
    user: { nama_pendek: string };
    jadwal: { hari_nama: string | null; label_jadwal: string | null; label_status_hari: string | null; is_wajib: boolean };
    tim: { nama: string; kode: string | null; manpro: string | null; anggota_count: number } | null;
    anggota: { id: number; nama_lengkap: string; nitk_nidn: string | null }[];
    ruangan: {
        id: number; nama: string; kode: string | null; kapasitas: number | null;
        jam_buka: string; jam_tutup: string; max_duration_minutes: number;
        min_advance_days: number; max_advance_days: number; durasi_opsi: number[];
    }[];
    pengajuan: { id: number; ruangan: string; tanggal: string | null; tanggal_human: string | null; jam: string; status: string; status_label: string }[];
    laporan: { id: number; ref: string; judul: string; status: string; status_label: string }[];
}

interface AiResp {
    ok: boolean;
    reply?: string;
    mode?: string;
    suggest_report?: boolean;
    report_data?: Partial<ReportDraft> & { referensi_absensi?: string };
}

interface ReportDraft {
    kategori: 'absensi' | 'akun' | 'saran' | 'lainnya';
    tanggal: string;
    referensi: string;
    subjek: string;
    pesan: string;
    lampiran_token?: string;
    lampiran_nama?: string;
}

interface RoomDraft {
    ruangan_id: number | null;
    ruangan_nama: string;
    tanggal: string;
    durasi: number;
    mulai: string;
    selesai: string;
    peserta: number[];
    tujuan: string;
}

type Card =
    | { t: 'options'; title?: string; desc?: string; layout?: 'grid' | 'list'; options: Opt[] }
    | { t: 'list'; title?: string; items: { label: string; sub?: string; badge?: string; tone?: 'ok' | 'warn' | 'bad' | 'info' }[]; url?: string; urlLabel?: string }
    | { t: 'report'; draft: ReportDraft; step: 'edit' | 'confirm'; sending?: boolean; err?: string }
    | { t: 'done'; title: string; lines: string[]; url?: string; urlLabel?: string }
    | { t: 'error'; message: string; retry?: () => void };

interface Opt { key: string; label: string; desc?: string; icon?: LucideIcon; href?: string; onClick?: () => void; sticky?: boolean }

interface Msg {
    id: number;
    role: 'a' | 'u';
    ts: number;
    text?: string;
    card?: Card;
    options?: Opt[];
    done?: boolean;
    /** Pertanyaan pengguna yang dijawab pesan ini — untuk umpan balik 3 tingkat. */
    q?: string;
    fb?: 'bad' | 'ok' | 'great';
    /** Pratinjau gambar yang dikirim pengguna (object URL lokal). */
    img?: string;
}

const fmtJam = (ts: number) => new Date(ts).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
const fmtDurasi = (m: number) => (m >= 60 ? `${m / 60} jam${m % 60 ? ` ${m % 60} mnt` : ''}` : `${m} mnt`);
const todayStr = () => new Date(Date.now() + 7 * 3600e3).toISOString().slice(0, 10); // WIB
const addDays = (iso: string, n: number) => {
    const d = new Date(iso + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
};
const tglHuman = (iso: string) => new Date(iso + 'T00:00:00Z').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });

export function AssistantWidget() {
    const { auth, ai_attachments } = usePage<SharedProps>().props;
    // Lampiran gambar asisten dinonaktifkan sementara (hemat AI vision).
    const attOn = ai_attachments !== false;
    const user = auth.user;
    const uid = user?.id ?? 0;
    const firstName = user?.nama_lengkap?.split(' ')[0] ?? 'Mahasiswa';

    const [open, setOpen] = useState(false);
    const [wide, setWide] = useState(false);
    const [pillIdx, setPillIdx] = useState(0);
    const [pillOff, setPillOff] = useState(() => {
        try { return sessionStorage.getItem(`sb_asst_pill_${auth.user?.id ?? 0}`) === '1'; } catch { return false; }
    });
    const [booted, setBooted] = useState(false);
    const [loadingCtx, setLoadingCtx] = useState(false);
    const [ctx, setCtx] = useState<Konteks | null>(null);
    const [ctxErr, setCtxErr] = useState(false);
    const [msgs, setMsgs] = useState<Msg[]>([]);
    const [draft, setDraft] = useState('');
    const [typing, setTyping] = useState(false);
    const [pending, setPending] = useState<{ token: string; nama: string; url: string } | null>(null);
    const [attachBusy, setAttachBusy] = useState(false);
    const [attachErr, setAttachErr] = useState<string | null>(null);
    const idRef = useRef(1);
    const histRef = useRef<{ role: 'user' | 'assistant'; text: string }[]>([]);
    const roomRef = useRef<RoomDraft>({ ruangan_id: null, ruangan_nama: '', tanggal: '', durasi: 60, mulai: '', selesai: '', peserta: [], tujuan: '' });
    const logRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const imgFileRef = useRef<HTMLInputElement>(null);
    const pendingRef = useRef<{ token: string; nama: string; url: string } | null>(null);
    pendingRef.current = pending;

    // ---------- Lampiran gambar chat (upload staged, ikut token saat kirim) ----------
    const stageImage = useCallback(async (f: File | null) => {
        if (!f) return;
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { setAttachErr('Format gambar: JPG, PNG, atau WebP.'); return; }
        if (f.size > 3 * 1024 * 1024) { setAttachErr('Ukuran gambar maksimal 3 MB.'); return; }
        setAttachErr(null); setAttachBusy(true);
        try {
            const fd = new FormData();
            fd.append('foto', f);
            const res = await fetch(route('mahasiswa.help.lampiran'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data?.errors?.foto?.[0] ?? data?.message ?? 'Gagal mengunggah gambar.');
            setPending({ token: data.token, nama: data.nama, url: URL.createObjectURL(f) });
        } catch (e: any) {
            setAttachErr(e?.message ?? 'Gagal mengunggah gambar.');
        } finally {
            setAttachBusy(false);
            if (imgFileRef.current) imgFileRef.current.value = '';
        }
    }, []);

    const onPasteImg = (e: React.ClipboardEvent<HTMLInputElement>) => {
        if (!attOn) return;
        const item = Array.from(e.clipboardData.items).find((i) => i.type.startsWith('image/'));
        const f = item?.getAsFile();
        if (!f) return;
        e.preventDefault();
        void stageImage(f);
    };

    // ---------- FAB bisa digeser (posisi tersimpan per browser) ----------
    const fabRef = useRef<HTMLDivElement>(null);
    const dragRef = useRef<{ sx: number; sy: number; left: number; top: number; moved: boolean } | null>(null);
    const suppressClick = useRef(false);
    const [fabPos, setFabPos] = useState<{ left: number; top: number } | null>(() => {
        try {
            const s = localStorage.getItem('sb-asst-fab-pos');
            return s ? (JSON.parse(s) as { left: number; top: number }) : null;
        } catch { return null; }
    });

    const onFabPointerDown = (e: React.PointerEvent<HTMLDivElement>) => {
        const el = fabRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        dragRef.current = { sx: e.clientX, sy: e.clientY, left: rect.left, top: rect.top, moved: false };
        const onMove = (ev: PointerEvent) => {
            const d = dragRef.current;
            if (!d) return;
            const dx = ev.clientX - d.sx;
            const dy = ev.clientY - d.sy;
            if (!d.moved && Math.hypot(dx, dy) < 6) return;
            d.moved = true;
            setFabPos({
                left: Math.min(Math.max(8, d.left + dx), window.innerWidth - el.offsetWidth - 8),
                top: Math.min(Math.max(8, d.top + dy), window.innerHeight - el.offsetHeight - 8),
            });
        };
        const onUp = () => {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            if (dragRef.current?.moved) {
                suppressClick.current = true;
                setTimeout(() => { suppressClick.current = false; }, 80);
                setFabPos((p) => {
                    try { localStorage.setItem('sb-asst-fab-pos', JSON.stringify(p)); } catch { /* abaikan */ }
                    return p;
                });
            }
            dragRef.current = null;
        };
        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    };

    const nid = () => idRef.current++;
    const push = useCallback((m: Omit<Msg, 'id' | 'ts'>) => {
        setMsgs((l) => [...l, { id: nid(), ts: Date.now(), ...m }]);
    }, []);
    const markDone = useCallback((id: number) => {
        setMsgs((l) => l.map((m) => (m.id === id ? { ...m, done: true } : m)));
    }, []);
    const patchCard = useCallback((id: number, part: Partial<Card>) => {
        setMsgs((l) => l.map((m) => (m.id === id && m.card ? { ...m, card: { ...m.card, ...part } as Card } : m)));
    }, []);

    // Gulir ke pesan terbaru
    useEffect(() => {
        const el = logRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [msgs, typing, open]);

    useEffect(() => {
        if (open) setTimeout(() => inputRef.current?.focus(), 250);
    }, [open]);

    // Sapaan gelembung berputar di atas launcher — berhenti saat panel terbuka/ditutup permanen.
    const GREETINGS = [
        'Butuh bantuan?',
        'Ada kendala atau masalah?',
        'Tanya jadwal & absensi?',
        'Mau ajukan ruangan?',
        'Ada yang bisa saya bantu?',
    ];
    useEffect(() => {
        if (open || pillOff) return;
        const t = setInterval(() => setPillIdx((i) => (i + 1) % GREETINGS.length), 4500);
        return () => clearInterval(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, pillOff]);

    const dismissPill = () => {
        setPillOff(true);
        try { sessionStorage.setItem(`sb_asst_pill_${uid}`, '1'); } catch { /* */ }
    };

    // ---------- helpers ----------
    const showMenu = useCallback(() => {
        push({
            role: 'a', text: 'Pilih kebutuhan Anda — atau ketik pertanyaan langsung di kolom bawah.',
            card: {
                t: 'options',
                title: 'Pilih kebutuhan Anda',
                desc: 'Silakan pilih salah satu agar saya bisa memberikan bantuan yang tepat.',
                layout: 'grid',
                options: [
                    { key: 'absensi', label: 'Absensi', desc: 'Cek jadwal & riwayat', icon: Fingerprint, onClick: () => subAbsensi() },
                    { key: 'tim', label: 'Tim PBL', desc: 'Informasi tim & anggota', icon: Users, onClick: () => subTim() },
                    { key: 'ruangan', label: 'Ruangan', desc: 'Ajukan & cek status', icon: DoorOpen, onClick: () => subRuangan() },
                    { key: 'panduan', label: 'Panduan', desc: 'Cara penggunaan portal', icon: BookOpen, onClick: () => subPanduan() },
                    { key: 'laporan', label: 'Laporan & Kendala', desc: 'Buat laporan atau lihat riwayat', icon: Flag, onClick: () => subLaporan() },
                    { key: 'lainnya', label: 'Lainnya', desc: 'Pertanyaan umum', icon: HelpCircle, onClick: () => subLainnya() },
                ],
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ctx]);

    const navBtns = (back?: () => void): Opt[] => [
        { key: 'back', label: 'Kembali', icon: ChevronLeft, sticky: true, onClick: back ?? (() => showMenu()) },
        { key: 'home', label: 'Menu utama', icon: Home, sticky: true, onClick: () => showMenu() },
    ];

    const subCard = (title: string, options: Opt[], desc?: string) => {
        push({ role: 'a', card: { t: 'options', title, desc, options: [...options, ...navBtns()] } });
    };

    // ---------- Submenu ----------
    const subAbsensi = () => {
        push({ role: 'u', text: 'Absensi' });
        subCard('Absensi', [
            { key: 'jadwal', label: 'Jadwal Hari Ini', icon: CalendarDays, onClick: () => showJadwal() },
            { key: 'riwayat', label: 'Riwayat Absensi Saya', icon: ClipboardList, href: route('mahasiswa.absensi') },
            { key: 'aturan', label: 'Ketentuan Kehadiran', icon: HelpCircle, onClick: () => sendPrompt('Jelaskan ketentuan jam tap masuk, tap pulang, toleransi keterlambatan, dan status kelengkapan absensi.') },
            { key: 'lapor', label: 'Laporkan Kendala Absensi', icon: Flag, onClick: () => startReport({ kategori: 'absensi' }) },
        ], 'Baik! Anda ingin melakukan apa terkait absensi?');
    };

    const subTim = () => {
        push({ role: 'u', text: 'Tim PBL' });
        subCard('Tim PBL', [
            { key: 'info', label: 'Informasi Tim Saya', icon: Users, onClick: () => showTim() },
            { key: 'belum', label: 'Belum punya tim?', icon: HelpCircle, onClick: () => sendPrompt('Saya belum punya tim PBL — bagaimana cara mendapatkan penugasan?') },
            { key: 'halaman', label: 'Buka Halaman Tim PBL', icon: ChevronRight, href: route('mahasiswa.team') },
            { key: 'aturan', label: 'Aturan Tim PBL', icon: HelpCircle, onClick: () => sendPrompt('Bagaimana aturan tim PBL, khususnya kaitannya dengan pengajuan ruangan lab?') },
        ], 'Baik! Anda ingin melakukan apa terkait tim PBL?');
    };

    const subRuangan = () => {
        push({ role: 'u', text: 'Ruangan' });
        subCard('Ruangan', [
            { key: 'ajukan', label: 'Ajukan Ruangan', icon: DoorOpen, onClick: () => startRoom() },
            { key: 'status', label: 'Cek Status Pengajuan', icon: ClipboardList, onClick: () => showPengajuan() },
            { key: 'jadwal', label: 'Lihat Jadwal Ruangan', icon: CalendarDays, href: route('mahasiswa.ruangan') },
            { key: 'aturan', label: 'Ketentuan & Panduan', icon: HelpCircle, onClick: () => showAturanRuangan() },
        ], 'Baik! Anda ingin melakukan apa terkait ruangan?');
    };

    const subPanduan = () => {
        push({ role: 'u', text: 'Panduan' });
        subCard('Panduan', [
            { key: 'cara', label: 'Cara Menggunakan Portal', icon: BookOpen, onClick: () => sendPrompt('Jelaskan cara menggunakan portal mahasiswa SmartBrail: absensi, kalender, pengajuan ruangan, dan profil.') },
            { key: 'halaman', label: 'Buka Halaman Panduan', icon: ChevronRight, href: route('mahasiswa.guide') },
        ], 'Materi panduan penggunaan portal:');
    };

    const subLaporan = () => {
        push({ role: 'u', text: 'Laporan & Kendala' });
        subCard('Laporan & Kendala', [
            { key: 'buat', label: 'Buat Laporan Baru', icon: Flag, onClick: () => startReport({ kategori: 'saran' }) },
            { key: 'absensi', label: 'Kendala Absensi', icon: Fingerprint, onClick: () => startReport({ kategori: 'absensi' }) },
            { key: 'riwayat', label: 'Riwayat Laporan Saya', icon: Ticket, onClick: () => showLaporan() },
            { key: 'halaman', label: 'Buka Halaman Riwayat', icon: ChevronRight, href: route('mahasiswa.suggestions') },
        ], 'Baik! Anda ingin melakukan apa terkait laporan?');
    };

    const subLainnya = () => {
        push({ role: 'u', text: 'Lainnya' });
        subCard('Lainnya', [
            { key: 'bisa', label: 'Kamu bisa apa saja?', icon: HelpCircle, onClick: () => sendPrompt('Kamu bisa membantu apa saja?') },
            { key: 'jam', label: 'Jam masuk hari ini?', icon: CalendarDays, onClick: () => sendPrompt('Jam berapa jadwal masuk dan pulang saya hari ini?') },
            { key: 'tentang', label: 'Apa itu SmartBrail?', icon: HelpCircle, onClick: () => sendPrompt('Apa itu SmartBrail dan layanan apa saja yang tersedia untuk mahasiswa?') },
            { key: 'bebas', label: 'Tanya Bebas', icon: ChevronRight, onClick: () => { push({ role: 'a', text: 'Silakan — ketik pertanyaan Anda di kolom bawah, saya akan bantu menjawab. ✍️' }); inputRef.current?.focus(); } },
        ], 'Pilih pertanyaan cepat atau ketik pertanyaan bebas Anda:');
    };

    // ---------- Kartu data nyata ----------
    const showJadwal = async () => {
        push({ role: 'u', text: 'Jadwal Hari Ini' });
        const j = (ctx ?? await loadContext(true))?.jadwal;
        if (!j) {
            push({ role: 'a', card: { t: 'error', message: 'Data jadwal belum termuat. Coba lagi sebentar ya.', retry: () => loadContext(true) }, options: navBtns() });
            return;
        }
        push({
            role: 'a',
            card: {
                t: 'list', title: `Jadwal — ${j.hari_nama ?? 'Hari ini'}`,
                items: [
                    { label: 'Jam efektif', sub: j.label_jadwal ?? '-' },
                    { label: 'Status hari', sub: j.label_status_hari ?? '-', badge: j.is_wajib ? 'Wajib absensi' : 'Tidak wajib', tone: j.is_wajib ? 'info' : 'warn' },
                ],
                url: route('mahasiswa.absensi'), urlLabel: 'Lihat riwayat absensi',
            },
            options: navBtns(),
        });
    };

    const showTim = async () => {
        push({ role: 'u', text: 'Informasi Tim Saya' });
        const c = ctx ?? await loadContext(true);
        if (!c) {
            push({ role: 'a', card: { t: 'error', message: 'Data tim belum termuat.', retry: () => loadContext(true) }, options: navBtns() });
            return;
        }
        const t = c.tim;
        if (!t) {
            push({
                role: 'a',
                card: {
                    t: 'list', title: 'Belum punya tim PBL',
                    items: [
                        { label: 'Status', sub: 'Anda belum ditugaskan ke tim mana pun', badge: 'Perlu tindakan', tone: 'warn' },
                        { label: 'Penugasan oleh', sub: 'Dosen pengampu atau laboran/admin — bukan mahasiswa' },
                        { label: 'Langkah', sub: 'Konfirmasi ke dosen pengampu, atau kirim laporan resmi' },
                    ],
                },
                options: [
                    { key: 'lapor', label: 'Laporkan ke Laboran', icon: Flag, onClick: () => startReport({ kategori: 'akun' }) },
                    ...navBtns(),
                ],
            });
            return;
        }
        push({
            role: 'a',
            card: {
                t: 'list', title: `Tim ${t.nama}`,
                items: [
                    ...(t.manpro ? [{ label: 'Manpro', sub: t.manpro }] : []),
                    { label: 'Jumlah anggota', sub: `${t.anggota_count} mahasiswa` },
                    ...c.anggota.slice(0, 8).map((a) => ({ label: a.nama_lengkap, sub: a.nitk_nidn ?? '' })),
                ],
                url: route('mahasiswa.team'), urlLabel: 'Lihat detail tim',
            },
            options: navBtns(),
        });
    };

    const showPengajuan = async () => {
        push({ role: 'u', text: 'Cek Status Pengajuan' });
        // Kalau konteks belum sempat termuat, muat dulu otomatis — jangan langsung error.
        const c = ctx ?? await loadContext(true);
        if (!c) {
            push({ role: 'a', card: { t: 'error', message: 'Data pengajuan belum termuat.', retry: () => loadContext(true) }, options: navBtns() });
            return;
        }
        const list = c.pengajuan ?? [];
        if (list.length === 0) {
            push({ role: 'a', text: 'Belum ada pengajuan ruangan untuk tim Anda. Ingin mengajukan sekarang?', options: [{ key: 'ajukan', label: 'Ajukan Ruangan', icon: DoorOpen, onClick: () => startRoom() }, ...navBtns()] });
            return;
        }
        push({
            role: 'a',
            card: {
                t: 'list', title: 'Pengajuan terakhir tim Anda',
                items: list.map((p) => ({
                    label: `${p.ruangan} · ${p.tanggal_human ?? p.tanggal ?? '-'}`,
                    sub: p.jam, badge: p.status_label,
                    tone: p.status === 'disetujui' || p.status === 'selesai' ? 'ok' : p.status === 'ditolak' || p.status === 'dibatalkan' ? 'bad' : 'info',
                })),
                url: route('mahasiswa.ruangan', { tab: 'riwayat' }), urlLabel: 'Lihat semua pengajuan',
            },
            options: navBtns(),
        });
    };

    const showLaporan = async () => {
        push({ role: 'u', text: 'Riwayat Laporan Saya' });
        const c = ctx ?? await loadContext(true);
        if (!c) {
            push({ role: 'a', card: { t: 'error', message: 'Data laporan belum termuat.', retry: () => loadContext(true) }, options: navBtns() });
            return;
        }
        const list = c.laporan ?? [];
        if (list.length === 0) {
            push({ role: 'a', text: 'Anda belum punya laporan. Saya bisa bantu membuatkan laporan baru untuk dikirim ke Admin Laboran.', options: [{ key: 'buat', label: 'Buat Laporan Baru', icon: Flag, onClick: () => startReport({ kategori: 'saran' }) }, ...navBtns()] });
            return;
        }
        push({
            role: 'a',
            card: {
                t: 'list', title: 'Laporan terakhir Anda',
                items: list.map((l) => ({
                    label: `${l.ref} · ${l.judul}`, badge: l.status_label,
                    tone: l.status === 'ditanggapi' ? 'ok' : l.status === 'dibaca' ? 'info' : 'warn',
                })),
                url: route('mahasiswa.suggestions'), urlLabel: 'Buka Riwayat Laporan',
            },
            options: navBtns(),
        });
    };

    const showAturanRuangan = () => {
        const list = ctx?.ruangan ?? [];
        push({ role: 'u', text: 'Ketentuan & Panduan' });
        push({
            role: 'a',
            card: {
                t: 'list', title: 'Ketentuan pengajuan ruangan',
                items: [
                    { label: 'Jam operasional', sub: list.length ? `${list[0].jam_buka}–${list[0].jam_tutup} WIB` : '-' },
                    { label: 'Durasi maksimal', sub: list.length ? fmtDurasi(list[0].max_duration_minutes) : '-' },
                    { label: 'Pengajuan maksimal', sub: list.length ? `H+${list[0].max_advance_days} dari hari ini` : '-' },
                    { label: 'Proses', sub: 'Diajukan → ditinjau Laboran/Admin → izin aktif bila disetujui.' },
                ],
            },
            options: [{ key: 'ajukan', label: 'Ajukan Ruangan', icon: DoorOpen, onClick: () => startRoom() }, ...navBtns()],
        });
    };

    // ---------- Kirim pesan bebas ke asisten ----------
    const sendPrompt = useCallback(async (text: string, img?: { token: string; nama: string; url: string } | null) => {
        if (typing) return;
        push({ role: 'u', text: text || undefined, img: img?.url });
        setTyping(true);
        const t0 = performance.now();
        try {
            const data = await apiPost<AiResp>(route('mahasiswa.help.ai'), {
                message: text,
                image_token: img?.token ?? undefined,
                history: histRef.current.slice(-8),
            });
            // Jeda minimal agar animasi "berpikir" terasa natural, bukan pop instan.
            const wait = Math.max(0, 700 - (performance.now() - t0));
            if (wait) await new Promise((r) => setTimeout(r, wait));
            const reply = data.reply || 'Maaf, asisten belum dapat merespons saat ini.';
            let opts: Opt[] | undefined;
            let fullText = reply;
            if (data.suggest_report && data.report_data) {
                const draft = img
                    ? { ...data.report_data, lampiran_token: img.token, lampiran_nama: img.nama }
                    : data.report_data;
                fullText = reply + '\n\nMau saya buatkan draft laporan resmi ke Admin Laboran? Tidak ada yang terkirim tanpa persetujuan Anda.';
                opts = [
                    { key: 'buat-draft', label: 'Ya, Buatkan Draft', icon: Flag, onClick: () => startReport(draft) },
                    { key: 'tidak', label: 'Tidak Perlu', icon: ChevronRight, onClick: () => push({ role: 'a', text: 'Baik, tidak ada laporan yang dibuat.', options: navBtns() }) },
                    ...navBtns(),
                ];
            }
            push({ role: 'a', text: fullText, options: opts, q: text });
            histRef.current.push({ role: 'user', text }, { role: 'assistant', text: reply });
            histRef.current = histRef.current.slice(-10);
        } catch {
            push({
                role: 'a',
                card: { t: 'error', message: 'Gagal menghubungi asisten. Periksa koneksi Anda.', retry: () => void sendPrompt(text, img) },
            });
        } finally {
            setTyping(false);
        }
    }, [typing]);

    const onSend = () => {
        const text = draft.trim();
        const img = pendingRef.current;
        if ((!text && !img) || typing || attachBusy) return;
        setDraft('');
        setPending(null);
        setAttachErr(null);
        void sendPrompt(text, img);
    };

    // ---------- Umpan balik jawaban asisten: buruk / cukup / sangat puas ----------
    const FB_LEVELS: { lv: 'bad' | 'ok' | 'great'; emoji: string; label: string }[] = [
        { lv: 'bad', emoji: '😞', label: 'Buruk' },
        { lv: 'ok', emoji: '😐', label: 'Cukup' },
        { lv: 'great', emoji: '😄', label: 'Sangat puas' },
    ];
    const FB_THANKS: Record<string, string> = {
        bad: 'Ditandai — jawaban ini akan diperbaiki',
        ok: 'Terima kasih atas masukannya',
        great: 'Senang bisa membantu',
    };
    const sendFeedback = (m: Msg, level: 'bad' | 'ok' | 'great') => {
        if (m.fb || !m.q || !m.text) return;
        setMsgs((prev) => prev.map((x) => (x.id === m.id ? { ...x, fb: level } : x)));
        void apiPost(route('mahasiswa.help.feedback'), {
            level,
            question: m.q.slice(0, 600),
            answer: m.text.slice(0, 4000),
        }).catch(() => undefined);
    };

    // ---------- Alur laporan (transaksi dengan konfirmasi) ----------
    const startReport = (pre?: Partial<ReportDraft> & { referensi_absensi?: string; tanggal_absensi?: string }) => {
        const d: ReportDraft = {
            kategori: pre?.kategori === 'absensi' || pre?.kategori === 'akun' || pre?.kategori === 'lainnya' ? pre.kategori : 'saran',
            tanggal: pre?.tanggal ?? pre?.tanggal_absensi ?? todayStr(),
            referensi: pre?.referensi ?? pre?.referensi_absensi ?? '',
            subjek: pre?.subjek ?? '',
            pesan: pre?.pesan ?? '',
        };
        push({ role: 'a', text: 'Saya bantu susun laporan resmi ke Admin Laboran. Lengkapi data berikut, lalu konfirmasi sebelum dikirim — tidak ada yang terkirim tanpa persetujuan Anda.', card: { t: 'report', draft: d, step: 'edit' } });
    };

    const reportConfirm = (id: number, d: ReportDraft) => {
        if (d.pesan.trim().length < 10) {
            patchCard(id, { err: 'Uraian laporan minimal 10 karakter.' } as Partial<Card>);
            return;
        }
        patchCard(id, { step: 'confirm', err: undefined } as Partial<Card>);
    };

    const reportSubmit = async (id: number, d: ReportDraft) => {
        patchCard(id, { sending: true, err: undefined } as Partial<Card>);
        try {
            const res = await apiPost<{ success: boolean; ticket_ref?: string; subjek?: string; created_at?: string; history_url?: string; message?: string }>(
                route('mahasiswa.suggestions.store'),
                { kategori: d.kategori, tanggal_absensi: d.kategori === 'absensi' ? (d.tanggal || null) : null, referensi_absensi: d.referensi || null, subjek: d.subjek || null, pesan: d.pesan, lampiran_token: d.lampiran_token || null },
            );
            markDone(id);
            push({
                role: 'a',
                card: {
                    t: 'done', title: `Laporan ${res.ticket_ref ?? ''} berhasil dikirim`,
                    lines: [res.subjek ?? 'Laporan Anda', `Tercatat ${res.created_at ?? ''} · status Menunggu`, 'Anda dapat memantau tindak lanjutnya di halaman Riwayat Laporan.'],
                    url: res.history_url ?? route('mahasiswa.suggestions'), urlLabel: 'Buka Riwayat Laporan',
                },
                options: navBtns(),
            });
        } catch (e: any) {
            const msg = e?.data?.message ?? (e?.data?.errors ? Object.values(e.data.errors as Record<string, string[]>).flat()[0] : 'Gagal mengirim laporan.');
            patchCard(id, { step: 'edit', sending: false, err: msg } as Partial<Card>);
        }
    };

    // ---------- Alur pengajuan ruangan (berdata nyata + konfirmasi) ----------
    const startRoom = () => {
        if (!ctx) { loadContext(); push({ role: 'a', text: 'Sebentar, saya muat data ruangan dulu… lalu pilih lagi ya.', options: navBtns() }); return; }
        if (!ctx.tim) {
            push({ role: 'a', text: 'Pengajuan ruangan dilakukan atas nama tim PBL. Anda belum tergabung dalam tim — hubungi dosen pembimbing atau laboran.', options: navBtns() });
            return;
        }
        if (ctx.ruangan.length === 0) {
            push({ role: 'a', text: 'Belum ada ruangan aktif yang bisa diajukan saat ini.', options: navBtns() });
            return;
        }
        roomRef.current = { ruangan_id: null, ruangan_nama: '', tanggal: '', durasi: 60, mulai: '', selesai: '', peserta: [uid], tujuan: '' };
        push({
            role: 'a', text: `Baik! Pengajuan atas nama tim ${ctx.tim.nama}. Langkah 1 — pilih ruangan:`,
            card: {
                t: 'options',
                options: [
                    ...ctx.ruangan.map((r) => ({
                        key: `r${r.id}`, label: r.nama, desc: [r.kapasitas ? `${r.kapasitas} kursi` : null, `${r.jam_buka}–${r.jam_tutup}`].filter(Boolean).join(' · '),
                        icon: DoorOpen, onClick: () => { roomRef.current.ruangan_id = r.id; roomRef.current.ruangan_nama = r.nama; push({ role: 'u', text: r.nama }); roomDate(r); },
                    })),
                    { key: 'batal', label: 'Batal', icon: X, onClick: () => { push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }); } },
                ],
            },
        });
    };

    const roomDate = (r: Konteks['ruangan'][number]) => {
        const minAdv = r.min_advance_days ?? 0;
        const maxAdv = r.max_advance_days ?? 2;
        const t0 = todayStr();
        push({
            role: 'a', text: `Langkah 2 — pilih tanggal (${minAdv > 0 ? `paling lambat H-${minAdv}` : 'bisa hari ini'} · maks. H+${maxAdv}):`,
            card: {
                t: 'options',
                options: [
                    ...Array.from({ length: maxAdv - minAdv + 1 }, (_, i) => addDays(t0, i + minAdv)).map((iso, i) => ({
                        key: iso, label: i + minAdv === 0 ? `Hari ini · ${tglHuman(iso)}` : i + minAdv === 1 ? `Besok · ${tglHuman(iso)}` : tglHuman(iso),
                        icon: CalendarDays, onClick: () => { roomRef.current.tanggal = iso; push({ role: 'u', text: tglHuman(iso) }); void roomSlots(r); },
                    })),
                    { key: 'batal', label: 'Batal', icon: X, onClick: () => push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }) },
                ],
            },
        });
    };

    const roomSlots = async (r: Konteks['ruangan'][number]) => {
        const d = roomRef.current;
        const id = nid();
        setMsgs((l) => [...l, { id, role: 'a', ts: Date.now(), text: 'Memeriksa slot yang tersedia…', card: { t: 'list', title: 'Memuat ketersediaan', items: [{ label: '…' }] } }]);
        try {
            const res = await apiGet<KetersediaanResp>(`${route('mahasiswa.ruangan.ketersediaan')}?ruangan_id=${r.id}&tanggal=${d.tanggal}&durasi=${d.durasi}`);
            const tersediaIdx = res.slots.map((s, i) => s.status === 'tersedia' ? i : -1).filter((i) => i >= 0);
            if (!res.boleh || tersediaIdx.length === 0) {
                patchCard(id, {
                    t: 'error',
                    message: res.alasan ?? res.habis_alasan ?? 'Semua slot hari itu sudah terisi atau diblokir — ruangan tidak tersedia. Coba tanggal lain.',
                } as Partial<Card>);
                setMsgs((l) => l.map((m) => m.id === id ? { ...m, options: navBtns() } : m));
                return;
            }
            patchCard(id, {
                t: 'options',
                title: `Slot tersedia — ${tglHuman(d.tanggal)} · maks. ${roomMaksJam(r)} jam`,
                options: [
                    ...tersediaIdx.slice(0, 12).map((i) => ({
                        key: res.slots[i].mulai, label: `Mulai ${res.slots[i].mulai}`,
                        onClick: () => { push({ role: 'u', text: `Mulai ${res.slots[i].mulai}` }); roomSlotEnd(r, res.slots, i); },
                    })),
                    { key: 'batal', label: 'Batal', icon: X, onClick: () => push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }) },
                ],
            } as Partial<Card>);
            setMsgs((l) => l.map((m) => m.id === id ? { ...m, text: 'Langkah 3 — pilih jam mulai:' } : m));
        } catch {
            patchCard(id, { t: 'error', message: 'Gagal memuat ketersediaan.', retry: () => void roomSlots(r) } as Partial<Card>);
            setMsgs((l) => l.map((m) => m.id === id ? { ...m, options: navBtns() } : m));
        }
    };

    /** Maks. jam berurutan: kebijakan 5 jam dibatasi aturan ruangan. */
    const roomMaksJam = (r: Konteks['ruangan'][number]) => Math.max(1, Math.min(5, Math.floor((r.max_duration_minutes ?? 60) / 60)));

    /** Pilih ujung rentang — hanya slot 'tersedia' berurutan setelah jam mulai. */
    const roomSlotEnd = (r: Konteks['ruangan'][number], slots: Slot[], startIdx: number) => {
        const d = roomRef.current;
        const start = slots[startIdx];
        const ends: { slot: Slot; jam: number }[] = [];
        for (let i = startIdx; i < slots.length && ends.length < roomMaksJam(r); i++) {
            if (slots[i].status !== 'tersedia') break;
            ends.push({ slot: slots[i], jam: i - startIdx + 1 });
        }
        const pilih = (end: Slot, jam: number) => {
            d.mulai = start.mulai; d.selesai = end.selesai;
            push({ role: 'u', text: `${start.mulai} – ${end.selesai}${jam > 1 ? ` (${jam} jam)` : ''}` });
            roomPeserta();
        };
        if (ends.length <= 1) {
            pilih(start, 1);
            return;
        }
        push({
            role: 'a', text: `Sampai jam berapa? (mulai ${start.mulai}, maks. ${ends.length} jam berurutan)`,
            card: {
                t: 'options',
                options: [
                    ...ends.map((e) => ({
                        key: e.slot.selesai, label: `${start.mulai} – ${e.slot.selesai} (${e.jam} jam)`,
                        onClick: () => pilih(e.slot, e.jam),
                    })),
                    { key: 'batal', label: 'Batal', icon: X, onClick: () => push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }) },
                ],
            },
        });
    };

    const roomPeserta = () => {
        const anggota = ctx?.anggota ?? [];
        const id = nid();
        setMsgs((l) => [...l, {
            id, role: 'a', ts: Date.now(),
            text: 'Langkah 4 — pilih peserta (Anda otomatis ikut):',
            card: {
                t: 'options',
                options: [
                    ...anggota.filter((a) => a.id !== uid).map((a) => ({
                        key: `p${a.id}`, label: a.nama_lengkap, icon: Users, sticky: true,
                        onClick: () => {
                            const d = roomRef.current;
                            d.peserta = d.peserta.includes(a.id) ? d.peserta.filter((x) => x !== a.id) : [...d.peserta, a.id];
                            setMsgs((ls) => ls.map((m) => m.id === id ? { ...m, options: m.options?.map((o) => o.key === `p${a.id}` ? { ...o, label: `${d.peserta.includes(a.id) ? '✓ ' : ''}${a.nama_lengkap}` } : o) } : m));
                        },
                    })),
                    {
                        key: 'lanjut', label: 'Lanjut', icon: ChevronRight,
                        onClick: () => { push({ role: 'u', text: `${roomRef.current.peserta.length} peserta` }); roomTujuan(); },
                    },
                    { key: 'batal', label: 'Batal', icon: X, onClick: () => push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }) },
                ],
            },
        }]);
    };

    const roomTujuan = () => {
        const id = nid();
        setMsgs((l) => [...l, {
            id, role: 'a', ts: Date.now(),
            text: 'Langkah 5 — tulis tujuan penggunaan ruangan (mis. "Rapat mingguan tim"):',
            card: {
                t: 'options',
                options: [{
                    key: 'batal', label: 'Batal', icon: X,
                    onClick: () => { pendingTujuanRef.current = null; push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }); },
                }],
            },
        }]);
        // input bawah sementara dipakai untuk tujuan
        roomRef.current.tujuan = '';
        setDraft('');
        pendingTujuanRef.current = id;
    };

    const pendingTujuanRef = useRef<number | null>(null);

    const roomConfirm = () => {
        const d = roomRef.current;
        markDone(pendingTujuanRef.current ?? -1);
        pendingTujuanRef.current = null;
        const id = nid();
        setMsgs((l) => [...l, {
            id, role: 'a', ts: Date.now(),
            card: {
                t: 'list', title: 'Konfirmasi pengajuan ruangan',
                items: [
                    { label: 'Ruangan', sub: d.ruangan_nama },
                    { label: 'Tanggal', sub: `${tglHuman(d.tanggal)} · ${d.mulai}–${d.selesai}` },
                    { label: 'Peserta', sub: `${d.peserta.length} orang` },
                    { label: 'Tujuan', sub: d.tujuan },
                ],
            },
            options: [
                { key: 'ok', label: 'Konfirmasi & Ajukan', icon: CheckCircle2, onClick: () => void roomSubmit(id) },
                { key: 'ubah', label: 'Ubah', icon: RotateCcw, onClick: () => { markDone(id); startRoom(); } },
                { key: 'batal', label: 'Batal', icon: X, onClick: () => { markDone(id); push({ role: 'a', text: 'Pengajuan dibatalkan.', options: navBtns() }); } },
            ],
        }]);
    };

    const roomSubmit = async (confirmId: number) => {
        const d = roomRef.current;
        setMsgs((l) => l.map((m) => m.id === confirmId ? { ...m, options: m.options?.map((o) => ({ ...o, onClick: undefined })) } : m));
        try {
            const res = await apiPost<{ success: boolean; pengajuan?: { ruangan: string; tanggal: string; jam_mulai: string; jam_selesai: string; status_label: string }; history_url?: string; message?: string }>(
                route('mahasiswa.ruangan.store'),
                { ruangan_id: d.ruangan_id, tanggal: d.tanggal, jam_mulai: d.mulai, jam_selesai: d.selesai, tujuan: d.tujuan, peserta: d.peserta },
            );
            markDone(confirmId);
            push({
                role: 'a',
                card: {
                    t: 'done', title: 'Pengajuan ruangan terkirim',
                    lines: [
                        `${res.pengajuan?.ruangan ?? d.ruangan_nama} · ${tglHuman(d.tanggal)}`,
                        `Jam ${d.mulai}–${d.selesai} · ${d.peserta.length} peserta`,
                        'Status: Menunggu keputusan petugas — Anda akan dinotifikasi.',
                    ],
                    url: res.history_url ?? route('mahasiswa.ruangan'), urlLabel: 'Lihat status pengajuan',
                },
                options: navBtns(),
            });
            loadContext(true); // segarkan status pengajuan
        } catch (e: any) {
            const msg = e?.data?.message ?? 'Pengajuan gagal dikirim.';
            markDone(confirmId);
            push({ role: 'a', card: { t: 'error', message: msg, retry: () => startRoom() }, options: navBtns() });
        }
    };

    // ---------- Boot & konteks ----------
    const loadContext = useCallback(async (silent = false): Promise<Konteks | null> => {
        if (!silent) setLoadingCtx(true);
        setCtxErr(false);
        try {
            // Satu retry internal — kegagalan transient tak langsung jadi kartu error.
            let data: Konteks | null = null;
            for (let i = 0; i < 2 && !data; i++) {
                try {
                    data = await apiGet<Konteks>(route('mahasiswa.help.context'));
                } catch { /* lanjut retry */ }
            }
            if (data) { setCtx(data); return data; }
            setCtxErr(true);
            return null;
        } finally {
            setLoadingCtx(false);
        }
    }, []);

    useEffect(() => {
        if (!open || booted) return;
        setBooted(true);
        push({ role: 'a', text: `Halo, ${firstName}! 👋\nAda yang bisa saya bantu hari ini?` });
        void loadContext();
        const t = setTimeout(() => showMenu(), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // Reset bila akun berganti (pengaman antar-user)
    const lastUid = useRef(uid);
    useEffect(() => {
        if (lastUid.current !== uid) {
            lastUid.current = uid;
            setMsgs([]); histRef.current = []; setCtx(null); setBooted(false); setOpen(false);
        }
    }, [uid]);

    // ---------- kirim: teks biasa atau tujuan ruangan ----------
    const onSubmitInput = () => {
        const text = draft.trim();
        if (!text && !pendingRef.current) return;
        if (pendingTujuanRef.current !== null) {
            if (!text) return;
            roomRef.current.tujuan = text;
            push({ role: 'u', text });
            setDraft('');
            roomConfirm();
            return;
        }
        onSend();
    };

    // ---------- render ----------
    const renderCard = (m: Msg) => {
        const c = m.card!;
        switch (c.t) {
            case 'options':
                return (
                    <div className={`sb-asst-opts ${c.layout === 'grid' ? 'sb-asst-opts--grid' : ''}`}>
                        {c.title && <p className="sb-asst-opts-title">{c.title}</p>}
                        {c.desc && <p className="sb-asst-opts-desc">{c.desc}</p>}
                        {c.options.map((o) => (
                            <OptBtn key={o.key} opt={o} disabled={m.done ?? false} consume={() => markDone(m.id)} />
                        ))}
                    </div>
                );
            case 'list':
                return (
                    <div className="sb-asst-list">
                        {c.title && <p className="sb-asst-opts-title">{c.title}</p>}
                        {c.items.map((it, i) => (
                            <div key={i} className="sb-asst-list-row">
                                <div className="min-w-0">
                                    <p className="sb-asst-list-label">{it.label}</p>
                                    {it.sub && <p className="sb-asst-list-sub">{it.sub}</p>}
                                </div>
                                {it.badge && <span className={`sb-asst-badge sb-asst-badge--${it.tone ?? 'info'}`}>{it.badge}</span>}
                            </div>
                        ))}
                        {c.url && <a className="sb-asst-more" href={c.url}>{c.urlLabel ?? 'Lihat selengkapnya'} <ChevronRight size={12} /></a>}
                    </div>
                );
            case 'report':
                return <ReportCard msg={m} card={c} onPatch={patchCard} onConfirm={reportConfirm} onSubmit={reportSubmit} onCancel={(id) => { markDone(id); push({ role: 'a', text: 'Laporan dibatalkan.', options: navBtns() }); }} />;
            case 'done':
                return (
                    <div className="sb-asst-done">
                        <CheckCircle2 size={20} className="text-emerald-500" />
                        <p className="sb-asst-done-title">{c.title}</p>
                        {c.lines.map((l, i) => <p key={i} className="sb-asst-done-line">{l}</p>)}
                        {c.url && <a className="sb-asst-more" href={c.url}>{c.urlLabel ?? 'Lihat detail'} <ChevronRight size={12} /></a>}
                    </div>
                );
            case 'error':
                return (
                    <div className="sb-asst-error">
                        <CircleAlert size={16} />
                        <span>{c.message}</span>
                        {c.retry && <button type="button" className="sb-asst-retry" onClick={c.retry}><RotateCcw size={12} /> Coba lagi</button>}
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <>
            {/* Launcher + gelembung sapaan */}
            {!open && (
                <div ref={fabRef}
                    className={`sb-asst-fab${fabPos ? ' sb-asst-fab--drag' : ''}`}
                    style={fabPos ? { left: fabPos.left, top: fabPos.top, right: 'auto', bottom: 'auto' } : undefined}
                    onPointerDown={onFabPointerDown}>
                    {!pillOff && (
                        <div className="sb-asst-pill" role="button" tabIndex={0}
                            onClick={() => { if (!suppressClick.current) setOpen(true); }}
                            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(true); } }}
                            aria-label="Buka Asisten SmartBrail">
                            <span className="sb-asst-pill-ava"><Bot size={16} /></span>
                            <span key={pillIdx} className="sb-asst-pill-text">{GREETINGS[pillIdx]}</span>
                            <button type="button" className="sb-asst-pill-x" aria-label="Sembunyikan sapaan"
                                onClick={(e) => { e.stopPropagation(); dismissPill(); }}>
                                <X size={11} />
                            </button>
                        </div>
                    )}
                    <button type="button" className="sb-asst-launcher" onClick={() => { if (!suppressClick.current) setOpen(true); }}
                        aria-label="Buka Asisten SmartBrail" aria-expanded="false">
                        <Bot size={22} />
                    </button>
                </div>
            )}

            {/* Panel */}
            {open && (
                <aside className={`sb-asst${wide ? ' sb-asst--wide' : ''}`} role="dialog" aria-label="Asisten SmartBrail">
                    <header className="sb-asst-head">
                        <div className="sb-asst-ava"><Bot size={22} /></div>
                        <div className="min-w-0 flex-1">
                            <p className="sb-asst-title">Asisten SmartBrail <span className="sb-asst-beta">Beta</span></p>
                            <p className="sb-asst-sub">Siap membantu Anda menggunakan portal.</p>
                        </div>
                        <button type="button" className="sb-asst-headbtn" aria-label={wide ? 'Kembalikan ukuran' : 'Perbesar'} onClick={() => setWide((w) => !w)}>
                            {wide ? <Minimize2 size={15} /> : <Maximize2 size={15} />}
                        </button>
                        <button type="button" className="sb-asst-headbtn" aria-label="Tutup asisten" onClick={() => setOpen(false)}>
                            <X size={16} />
                        </button>
                    </header>

                    <div ref={logRef} className="sb-asst-log" aria-live="polite">
                        {!booted || (msgs.length === 0 && loadingCtx) ? (
                            <div className="space-y-3 p-4" role="status" aria-label="Memuat asisten">
                                <Skeleton className="h-10 w-3/4 rounded-2xl" />
                                <Skeleton className="h-24 w-full rounded-2xl" />
                                <Skeleton className="h-10 w-2/3 rounded-2xl" />
                            </div>
                        ) : (
                            msgs.map((m) => (
                                <div key={m.id} className={`sb-asst-row sb-asst-row--${m.role}`}>
                                    {m.role === 'a' && <span className="sb-asst-mini-ava"><Bot size={15} /></span>}
                                    <div className="sb-asst-col">
                                        {m.img && (
                                            <div className="sb-asst-bubble sb-asst-bubble--u sb-asst-bubble--img">
                                                <img src={m.img} alt="Lampiran gambar" className="sb-asst-img" />
                                            </div>
                                        )}
                                        {m.text && (
                                            <div className={`sb-asst-bubble ${m.role === 'u' ? 'sb-asst-bubble--u' : ''}`}>
                                                {m.text.split('\n').map((ln, i) => <p key={i}>{renderMd(ln)}</p>)}
                                            </div>
                                        )}
                                        {m.card && renderCard(m)}
                                        {m.options && (
                                            <div className="sb-asst-opts">
                                                {m.options.map((o) => <OptBtn key={o.key} opt={o} disabled={m.done ?? false} consume={() => markDone(m.id)} />)}
                                            </div>
                                        )}
                                        <span className="sb-asst-time">
                                            {fmtJam(m.ts)}
                                            {m.role === 'a' && m.q && (
                                                <span className="sb-asst-fb" role="group" aria-label="Umpan balik jawaban">
                                                    {m.fb ? (
                                                        <span className="sb-asst-fb-thanks">{FB_THANKS[m.fb]}</span>
                                                    ) : (
                                                        FB_LEVELS.map((f) => (
                                                            <button key={f.lv} type="button" className="sb-asst-fb-btn" title={f.label} aria-label={f.label}
                                                                onClick={() => sendFeedback(m, f.lv)}><span className="sb-asst-fb-emoji">{f.emoji}</span></button>
                                                        ))
                                                    )}
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ))
                        )}
                        {typing && (
                            <div className="sb-asst-row sb-asst-row--a">
                                <span className="sb-asst-mini-ava sb-asst-mini-ava--think"><Bot size={15} /></span>
                                <div className="sb-asst-bubble sb-asst-typing" aria-label="Asisten sedang berpikir">
                                    <i /><i /><i />
                                    <span className="sb-asst-typing-text">berpikir…</span>
                                </div>
                            </div>
                        )}
                        {ctxErr && (
                            <button type="button" className="sb-asst-ctxerr" onClick={() => loadContext()}>
                                <CircleAlert size={13} /> Data konteks gagal dimuat — ketuk untuk coba lagi.
                            </button>
                        )}
                    </div>

                    <footer className="sb-asst-inputwrap">
                        {pending && (
                            <div className="sb-asst-pendchip">
                                <img src={pending.url} alt="" className="sb-asst-pendchip-img" />
                                <span className="sb-asst-pendchip-name">{pending.nama}</span>
                                <button type="button" aria-label="Hapus gambar" onClick={() => setPending(null)}>
                                    <X size={12} />
                                </button>
                            </div>
                        )}
                        {attachErr && <p className="sb-asst-errtext">{attachErr}</p>}
                        <div className="sb-asst-inputrow">
                            <input ref={imgFileRef} type="file" accept="image/jpeg,image/png,image/webp" hidden
                                onChange={(e) => void stageImage(e.target.files?.[0] ?? null)} />
                            {attOn && (
                            <button type="button" className="sb-asst-attach" aria-label="Lampirkan gambar"
                                title="Lampirkan gambar — atau tempel screenshot langsung (Ctrl+V)"
                                disabled={attachBusy || typing}
                                onClick={() => imgFileRef.current?.click()}>
                                {attachBusy ? <Loader2 size={15} className="animate-spin" /> : <Paperclip size={15} />}
                            </button>
                            )}
                            <input
                                ref={inputRef}
                                className="sb-asst-input"
                                placeholder={pendingTujuanRef.current !== null ? 'Tulis tujuan penggunaan…' : 'Ketik pertanyaan atau tempel screenshot…'}
                                value={draft}
                                onChange={(e) => setDraft(e.target.value)}
                                onPaste={onPasteImg}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); onSubmitInput(); } }}
                                aria-label="Pesan untuk asisten"
                                maxLength={600}
                            />
                            <button type="button" className="sb-asst-send" onClick={onSubmitInput}
                                disabled={(!draft.trim() && !pending) || typing || attachBusy} aria-label="Kirim pesan">
                                {typing ? <Loader2 size={17} className="animate-spin" /> : <SendHorizontal size={17} />}
                            </button>
                        </div>
                    </footer>
                </aside>
            )}
        </>
    );
}

/** Tombol opsi dalam kartu. Opsi non-sticky/non-link menandai kartu selesai setelah dipilih. */
function OptBtn({ opt, disabled, consume }: { opt: Opt; disabled: boolean; consume: () => void }) {
    const cls = 'sb-asst-opt';
    if (opt.href) {
        return (
            <Link className={cls} href={opt.href}>
                {opt.icon && <opt.icon size={15} className="sb-asst-opt-ic" />}
                <span className="min-w-0"><span className="sb-asst-opt-label">{opt.label}</span>
                    {opt.desc && <span className="sb-asst-opt-desc">{opt.desc}</span>}</span>
            </Link>
        );
    }
    return (
        <button type="button" className={cls} disabled={disabled}
            onClick={() => { opt.onClick?.(); if (!opt.sticky) consume(); }}>
            {opt.icon && <opt.icon size={15} className="sb-asst-opt-ic" />}
            <span className="min-w-0"><span className="sb-asst-opt-label">{opt.label}</span>
                {opt.desc && <span className="sb-asst-opt-desc">{opt.desc}</span>}</span>
        </button>
    );
}

/** Kartu form laporan (edit → konfirmasi). */
function ReportCard({ msg, card, onPatch, onConfirm, onSubmit, onCancel }: {
    msg: Msg;
    card: Extract<Card, { t: 'report' }>;
    onPatch: (id: number, p: Partial<Card>) => void;
    onConfirm: (id: number, d: ReportDraft) => void;
    onSubmit: (id: number, d: ReportDraft) => void;
    onCancel: (id: number) => void;
}) {
    const d = card.draft;
    const setD = (part: Partial<ReportDraft>) => onPatch(msg.id, { draft: { ...d, ...part } } as Partial<Card>);

    if (card.step === 'confirm') {
        const katLabel = { absensi: 'Kendala Absensi', akun: 'Bantuan Akun', saran: 'Saran & Masukan', lainnya: 'Lainnya' }[d.kategori];
        return (
            <div className="sb-asst-list">
                <p className="sb-asst-opts-title">Konfirmasi laporan</p>
                <div className="sb-asst-list-row"><p className="sb-asst-list-label">Kategori</p><span className="sb-asst-badge sb-asst-badge--info">{katLabel}</span></div>
                {d.kategori === 'absensi' && <div className="sb-asst-list-row"><p className="sb-asst-list-label">Tanggal</p><p className="sb-asst-list-sub">{d.tanggal || '-'}</p></div>}
                <div className="sb-asst-list-row"><p className="sb-asst-list-label">Subjek</p><p className="sb-asst-list-sub">{d.subjek || '(otomatis)'}</p></div>
                <div className="sb-asst-list-row"><p className="sb-asst-list-label">Uraian</p><p className="sb-asst-list-sub whitespace-pre-wrap">{d.pesan}</p></div>
                {d.lampiran_nama && <div className="sb-asst-list-row"><p className="sb-asst-list-label">Foto bukti</p><p className="sb-asst-list-sub">📎 {d.lampiran_nama}</p></div>}
                {card.err && <p className="sb-asst-errtext">{card.err}</p>}
                <div className="sb-asst-acts">
                    <button type="button" className="sb-asst-btn-p" disabled={card.sending || msg.done} onClick={() => onSubmit(msg.id, d)}>
                        {card.sending ? <Loader2 size={14} className="animate-spin" /> : <CheckCircle2 size={14} />} {card.sending ? 'Mengirim…' : 'Konfirmasi & Kirim'}
                    </button>
                    <button type="button" className="sb-asst-btn-g" disabled={card.sending || msg.done} onClick={() => onPatch(msg.id, { step: 'edit', err: undefined } as Partial<Card>)}>Ubah</button>
                    <button type="button" className="sb-asst-btn-g" disabled={card.sending || msg.done} onClick={() => onCancel(msg.id)}>Batal</button>
                </div>
            </div>
        );
    }

    return (
        <div className="sb-asst-form">
            <label className="sb-asst-flabel">Kategori</label>
            <select className="sb-asst-field" value={d.kategori} disabled={msg.done}
                onChange={(e) => setD({ kategori: e.target.value as ReportDraft['kategori'] })}>
                <option value="absensi">Kendala Absensi</option>
                <option value="akun">Bantuan Akun</option>
                <option value="saran">Saran & Masukan</option>
                <option value="lainnya">Lainnya</option>
            </select>
            {d.kategori === 'absensi' && (
                <>
                    <label className="sb-asst-flabel">Tanggal kejadian</label>
                    <input type="date" className="sb-asst-field" value={d.tanggal} disabled={msg.done} max={todayStr()}
                        onChange={(e) => setD({ tanggal: e.target.value })} />
                </>
            )}
            <label className="sb-asst-flabel">Subjek (opsional)</label>
            <input type="text" className="sb-asst-field" value={d.subjek} disabled={msg.done} maxLength={150}
                placeholder="Ringkasan singkat laporan"
                onChange={(e) => setD({ subjek: e.target.value })} />
            <label className="sb-asst-flabel">Uraian *</label>
            <textarea className="sb-asst-field" rows={3} value={d.pesan} disabled={msg.done} maxLength={4000}
                placeholder="Jelaskan kendala atau masukan Anda (minimal 10 karakter)…"
                onChange={(e) => setD({ pesan: e.target.value })} />
            <LampiranPicker draft={d} setD={setD} disabled={msg.done ?? false} />
            {card.err && <p className="sb-asst-errtext">{card.err}</p>}
            <div className="sb-asst-acts">
                <button type="button" className="sb-asst-btn-p" disabled={msg.done} onClick={() => onConfirm(msg.id, d)}>Lanjut ke Konfirmasi</button>
                <button type="button" className="sb-asst-btn-g" disabled={msg.done} onClick={() => onCancel(msg.id)}>Batal</button>
            </div>
        </div>
    );
}

/** Pemilih foto bukti di kartu laporan — upload staged, token ikut saat submit. */
function LampiranPicker({ draft, setD, disabled }: {
    draft: ReportDraft;
    setD: (p: Partial<ReportDraft>) => void;
    disabled: boolean;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState<string | null>(null);

    const pick = async (f: File | null) => {
        if (!f) return;
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { setErr('Format harus JPG, PNG, atau WebP.'); return; }
        if (f.size > 3 * 1024 * 1024) { setErr('Ukuran foto maksimal 3 MB.'); return; }
        setErr(null); setBusy(true);
        try {
            const fd = new FormData();
            fd.append('foto', f);
            const res = await fetch(route('mahasiswa.help.lampiran'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data?.errors?.foto?.[0] ?? data?.message ?? 'Gagal mengunggah foto.');
            setD({ lampiran_token: data.token, lampiran_nama: data.nama });
        } catch (e: any) {
            setErr(e?.message ?? 'Gagal mengunggah foto.');
        } finally {
            setBusy(false);
            if (fileRef.current) fileRef.current.value = '';
        }
    };

    return (
        <div className="sb-asst-lampiran">
            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" hidden
                onChange={(e) => void pick(e.target.files?.[0] ?? null)} />
            {draft.lampiran_nama ? (
                <span className="sb-asst-lampiran-chip">
                    📎 {draft.lampiran_nama}
                    {!disabled && (
                        <button type="button" aria-label="Hapus lampiran"
                            onClick={() => setD({ lampiran_token: undefined, lampiran_nama: undefined })}>×</button>
                    )}
                </span>
            ) : (
                <button type="button" className="sb-asst-btn-g sb-asst-lampiran-btn" disabled={disabled || busy}
                    onClick={() => fileRef.current?.click()}>
                    {busy ? <Loader2 size={13} className="animate-spin" /> : <Paperclip size={13} />}
                    {busy ? 'Mengunggah…' : 'Lampirkan foto bukti (opsional)'}
                </button>
            )}
            {err && <p className="sb-asst-errtext">{err}</p>}
        </div>
    );
}

/** Render **bold** sederhana dari balasan AI. */
function renderMd(text: string) {
    const parts = text.split(/(\*\*[^*]+\*\*)/g);
    return parts.map((p, i) => (p.startsWith('**') && p.endsWith('**') ? <strong key={i}>{p.slice(2, -2)}</strong> : <span key={i}>{p}</span>));
}
