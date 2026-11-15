import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle, ArrowRight, BookOpen, CheckCircle2, DoorOpen, Fingerprint, Info,
    KeyRound, LifeBuoy, ListChecks, LogIn, Mail, Search, Sparkles, Ticket, Users, X,
    type LucideIcon,
} from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHead, Crumb } from '@/components/PageHead';
import type { SharedProps } from '@/types';
import { cn } from '@/lib/utils';

interface Props extends SharedProps {
    scheduleMeta: { active_label?: string };
}

type MockKind = 'login' | 'finger' | 'status' | 'ticket' | 'ruangan' | 'email';

interface Guide {
    id: string;
    icon: LucideIcon;
    kategori: string;
    judul: string;
    deskripsi: string;
    ringkasan: React.ReactNode;
    langkah: string[];
    ketentuan: string[];
    mock: MockKind;
    kataKunci: string;
}

const GUIDES = (hariWajib: string): Guide[] => [
    {
        id: 'masuk', icon: LogIn, kategori: 'Akun & Keamanan',
        judul: 'Masuk dan Password Pertama',
        deskripsi: 'Panduan untuk login ke portal, penggunaan password awal, dan cara menggantinya.',
        ringkasan: <>Mahasiswa masuk menggunakan NIM dan password awal dari laboran. Setelah login pertama, Anda <strong>wajib mengganti password</strong> dengan yang baru untuk menjaga keamanan akun.</>,
        langkah: [
            'Buka portal Smart BRAIL melalui URL resmi kampus.',
            'Masukkan NIM dan password awal dari laboran.',
            'Setelah berhasil login, sistem akan meminta Anda mengganti password.',
            'Buat password baru yang kuat, lalu simpan perubahan.',
        ],
        ketentuan: [
            'Minimal 8 karakter.',
            'Gunakan kombinasi huruf, angka, dan simbol.',
            'Jangan gunakan password yang mudah ditebak (mis. tanggal lahir).',
        ],
        mock: 'login',
        kataKunci: 'login masuk password nim keamanan',
    },
    {
        id: 'absensi', icon: Fingerprint, kategori: 'Absensi',
        judul: 'Absensi dengan Sidik Jari',
        deskripsi: 'Cara dan aturan absensi sidik jari di laboratorium.',
        ringkasan: <>Absensi dilakukan dengan menempelkan jari pada mesin sidik jari di lab — <strong>satu tap saat datang (masuk)</strong> dan <strong>satu tap saat pulang</strong>. Hari wajib saat ini: <strong>{hariWajib}</strong>.</>,
        langkah: [
            'Datang ke laboratorium pada hari wajib sesuai jadwal.',
            'Tempelkan jari yang sudah direkam pada mesin — tunggu konfirmasi bunyi/lampu.',
            'Lakukan aktivitas lab seperti biasa.',
            'Sebelum meninggalkan lab, tap sekali lagi untuk jam pulang.',
        ],
        ketentuan: [
            'Jika profil berstatus "belum rekam sidik jari", hubungi laboran sebelum memakai mesin.',
            'Tap masuk tanpa tap pulang tercatat "Tidak lengkap" saat sesi ditutup.',
            'Rekaman "Lengkap" tetap dapat berstatus Terlambat atau Pulang lebih awal.',
        ],
        mock: 'finger',
        kataKunci: 'absensi sidik jari fingerprint tap mesin lab',
    },
    {
        id: 'status', icon: ListChecks, kategori: 'Absensi',
        judul: 'Arti Status Absensi',
        deskripsi: 'Penjelasan status dan ketentuan pada riwayat absensi.',
        ringkasan: <>Setiap hari absensi mendapat satu status kelengkapan. Status ini berbeda dengan <em>ketepatan waktu</em> — rekaman "Lengkap" tetap bisa berstatus Terlambat atau Pulang lebih awal.</>,
        langkah: [],
        ketentuan: [
            'Kelengkapan dinilai dari ada-tidaknya tap masuk dan pulang.',
            'Ketepatan dinilai dari jam aktual tap terhadap jadwal efektif.',
            'Periksa halaman Riwayat Absensi untuk rincian per sesi.',
        ],
        mock: 'status',
        kataKunci: 'status lengkap alfa terlambat tap pulang',
    },
    {
        id: 'ruangan', icon: DoorOpen, kategori: 'Ruangan',
        judul: 'Mengajukan Pemakaian Ruangan',
        deskripsi: 'Alur mengajukan ruangan untuk tim PBL Anda.',
        ringkasan: <>Pengajuan ruangan dilakukan atas nama <strong>tim PBL</strong> — <strong>paling lambat H-1</strong> sebelum pemakaian dan paling jauh sesuai batas ruangan. Ruangan dianggap <em>selesai</em> hanya bila tim benar-benar datang dan melakukan tap pada mesin ruangan.</>,
        langkah: [
            'Buka menu Ruangan → Ajukan Ruangan, pilih ruangan, tanggal, jam, dan peserta.',
            'Laboran memeriksa dan memutuskan — pantau statusnya di Riwayat Pengajuan.',
            'Jika disetujui, izin terbit untuk setiap peserta pada jam yang diajukan.',
            'Di hari-H, tap sidik jari/RFID/PIN pada mesin ruangan — tap inilah bukti kehadiran.',
        ],
        ketentuan: [
            'Tanpa tap mesin pada jadwalnya, pengajuan berakhir "Kedaluwarsa" — bukan "Selesai".',
            'Pengajuan bisa dibatalkan sebelum digunakan lewat Riwayat Pengajuan.',
            'Maksimal satu pengajuan menunggu per tim per ruangan.',
        ],
        mock: 'ruangan',
        kataKunci: 'ruangan pengajuan booking pinjam lab izin h-1',
    },
    {
        id: 'laporan', icon: Ticket, kategori: 'Laporan & Bantuan',
        judul: 'Melaporkan Kendala',
        deskripsi: 'Alur pelaporan masalah akun atau absensi.',
        ringkasan: <>Laporan resmi langsung menghasilkan <strong>nomor tiket pelacakan</strong> yang tercatat di sistem — status dan balasan laboran bisa dipantau kapan saja.</>,
        langkah: [
            'Buka Riwayat Absensi lalu pilih Lapor pada sesi terkait, atau buka widget Bantuan di kanan bawah.',
            'Pilih kategori kendala, tulis uraian singkat dan jelas.',
            'Kirim — sistem menerbitkan nomor tiket #ID.',
            'Pantau status dan balasan resmi di Riwayat Laporan.',
        ],
        ketentuan: [
            'Lampirkan tanggal kejadian agar verifikasi lebih cepat.',
            'Satu tiket untuk satu kendala agar mudah dilacak.',
            'Balasan laboran masuk juga sebagai notifikasi di lonceng atas.',
        ],
        mock: 'ticket',
        kataKunci: 'lapor kendala tiket saran bantuan masalah',
    },
    {
        id: 'pemulihan', icon: KeyRound, kategori: 'Akun & Keamanan',
        judul: 'Pemulihan Akun & Lupa Password',
        deskripsi: 'Cara memulihkan akses bila lupa password.',
        ringkasan: <>Kode OTP pemulihan dikirim ke <strong>email terverifikasi</strong> Anda. Tambahkan dan verifikasi email di Profil sekarang agar pemulihan mandiri siap dipakai sewaktu-waktu.</>,
        langkah: [
            'Di halaman login, pilih tautan Lupa password.',
            'Masukkan NIM — kode OTP dikirim ke email terverifikasi.',
            'Masukkan kode OTP, lalu buat password baru.',
            'Login kembali dengan password baru.',
        ],
        ketentuan: [
            'OTP berlaku terbatas — segera gunakan setelah diterima.',
            'Belum punya email terverifikasi? Hubungi laboran untuk reset.',
            'Jangan bagikan kode OTP kepada siapa pun.',
        ],
        mock: 'email',
        kataKunci: 'lupa password otp email pemulihan reset akun',
    },
];

/* ---------- Mockup mini (kartu ilustrasi CSS) ---------- */

function BrowserFrame({ children, title }: { children: React.ReactNode; title: string }) {
    return (
        <div className="overflow-hidden rounded-[12px] border border-white/10 bg-[#0d1b33] shadow-xl">
            <div className="flex items-center gap-1.5 border-b border-white/10 px-3 py-2">
                <span className="h-2 w-2 rounded-full bg-red-400/80" />
                <span className="h-2 w-2 rounded-full bg-amber-400/80" />
                <span className="h-2 w-2 rounded-full bg-emerald-400/80" />
                <span className="ml-2 truncate text-[10px] font-medium text-white/40">{title}</span>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function MockCard({ kind, hariWajib }: { kind: MockKind; hariWajib: string }) {
    switch (kind) {
        case 'login':
            return (
                <BrowserFrame title="portal.smartbrail.ac.id/login">
                    <div className="mx-auto max-w-[190px] space-y-2.5">
                        <p className="text-center text-[12px] font-extrabold text-white">Smart<span className="text-sb-cyan">Brail</span></p>
                        <div className="rounded-[8px] border border-white/15 bg-white/5 px-2.5 py-1.5 text-[10px] text-white/50">NIM · 4222301xx</div>
                        <div className="rounded-[8px] border border-white/15 bg-white/5 px-2.5 py-1.5 text-[10px] text-white/50">Password · ••••••••</div>
                        <div className="rounded-[8px] bg-sb-primary py-1.5 text-center text-[10px] font-bold text-white">Masuk</div>
                        <p className="text-center text-[9px] text-white/35">→ Anda akan diminta mengganti password</p>
                    </div>
                </BrowserFrame>
            );
        case 'finger':
            return (
                <div className="rounded-[12px] border border-white/10 bg-[#0d1b33] p-4 shadow-xl">
                    <div className="mx-auto grid w-fit place-items-center">
                        <span className="sb-guide-fpring">
                            <Fingerprint size={34} className="text-sb-cyan" />
                        </span>
                    </div>
                    <p className="mt-3 text-center text-[11px] font-bold text-white">Tempelkan jari pada mesin</p>
                    <div className="mt-2.5 space-y-1.5">
                        <div className="flex items-center justify-between rounded-[8px] bg-white/5 px-2.5 py-1.5 text-[10px]">
                            <span className="text-white/60">Tap masuk</span>
                            <span className="font-bold text-emerald-400">07:58 ✓</span>
                        </div>
                        <div className="flex items-center justify-between rounded-[8px] bg-white/5 px-2.5 py-1.5 text-[10px]">
                            <span className="text-white/60">Tap pulang</span>
                            <span className="font-bold text-white/35">belum ada</span>
                        </div>
                    </div>
                    <p className="mt-2 text-center text-[9px] text-white/35">Hari wajib: {hariWajib}</p>
                </div>
            );
        case 'status':
            return (
                <div className="space-y-1.5 rounded-[12px] border border-white/10 bg-[#0d1b33] p-4 shadow-xl">
                    {[
                        ['Lengkap', 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30', 'Masuk & pulang tercatat'],
                        ['Hanya masuk', 'bg-sky-500/15 text-sky-400 border-sky-500/30', 'Pulang belum ada'],
                        ['Tidak lengkap', 'bg-amber-500/15 text-amber-400 border-amber-500/30', 'Pulang tak tercatat s.d. tutup'],
                        ['Alfa', 'bg-red-500/15 text-red-400 border-red-500/30', 'Tak ada tap di hari wajib'],
                    ].map(([label, cls, desc]) => (
                        <div key={label} className="flex items-center gap-2.5 rounded-[8px] bg-white/5 px-2.5 py-1.5">
                            <span className={cn('shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-extrabold', cls)}>{label}</span>
                            <span className="text-[10px] text-white/55">{desc}</span>
                        </div>
                    ))}
                </div>
            );
        case 'ticket':
            return (
                <div className="rounded-[12px] border border-white/10 bg-[#0d1b33] p-4 shadow-xl">
                    <div className="flex items-center justify-between">
                        <p className="font-mono text-[12px] font-extrabold text-sb-cyan">#SM-2026-0042</p>
                        <span className="rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2 py-0.5 text-[9px] font-extrabold text-emerald-400">Ditanggapi</span>
                    </div>
                    <p className="mt-2 rounded-[8px] bg-white/5 px-2.5 py-1.5 text-[10px] text-white/60">"Tap pulang kemarin tidak tercatat padahal saya sudah tap…"</p>
                    <div className="mt-2 flex items-start gap-1.5 rounded-[8px] border border-sb-primary/30 bg-sb-primary/10 px-2.5 py-1.5">
                        <LifeBuoy size={11} className="mt-0.5 shrink-0 text-sb-cyan" />
                        <p className="text-[10px] text-white/70"><span className="font-bold text-sb-cyan">Laboran:</span> Sudah diverifikasi, tap pulang dicatat manual.</p>
                    </div>
                    <p className="mt-2 text-center text-[9px] text-white/35">Pantau status di Riwayat Laporan</p>
                </div>
            );
        case 'ruangan':
            return (
                <div className="rounded-[12px] border border-white/10 bg-[#0d1b33] p-4 shadow-xl">
                    <div className="space-y-1.5">
                        {[
                            ['Ajukan (maks. H-1)', true],
                            ['Persetujuan laboran', true],
                            ['Izin terbit per peserta', true],
                            ['Tap mesin di hari-H', false],
                        ].map(([label, done], i) => (
                            <div key={String(label)} className="flex items-center gap-2.5">
                                <span className={cn('grid h-5 w-5 shrink-0 place-items-center rounded-full text-[9px] font-extrabold',
                                    done ? 'bg-emerald-500/20 text-emerald-400' : 'border border-white/25 text-white/50')}>
                                    {done ? '✓' : i + 1}
                                </span>
                                <span className={cn('text-[10.5px] font-semibold', done ? 'text-white/80' : 'text-white/50')}>{label}</span>
                            </div>
                        ))}
                    </div>
                    <p className="mt-3 rounded-[8px] border border-amber-500/25 bg-amber-500/10 px-2.5 py-1.5 text-center text-[9.5px] text-amber-300">
                        Tanpa tap mesin → status jadi <b>Kedaluwarsa</b>, bukan Selesai
                    </p>
                </div>
            );
        case 'email':
            return (
                <BrowserFrame title="Email masuk — SmartBrail">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <span className="grid h-7 w-7 place-items-center rounded-full bg-sb-primary/30 text-sb-cyan"><Mail size={13} /></span>
                            <div>
                                <p className="text-[10.5px] font-bold text-white">Kode Pemulihan SmartBrail</p>
                                <p className="text-[9px] text-white/40">kepada: anda@polibatam.ac.id</p>
                            </div>
                        </div>
                        <div className="rounded-[8px] border border-dashed border-sb-cyan/40 bg-sb-cyan/10 py-2 text-center font-mono text-[15px] font-extrabold tracking-[0.3em] text-sb-cyan">482 916</div>
                        <p className="text-center text-[9px] text-white/35">Kode berlaku terbatas — jangan dibagikan</p>
                    </div>
                </BrowserFrame>
            );
    }
}

/* ---------- Halaman ---------- */

export default function Guide({ scheduleMeta }: Props) {
    const hariWajib = scheduleMeta?.active_label || 'sesuai jadwal aktif';
    const guides = useMemo(() => GUIDES(hariWajib), [hariWajib]);
    const [aktif, setAktif] = useState(guides[0].id);
    const [q, setQ] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return guides;
        return guides.filter((g) => `${g.judul} ${g.deskripsi} ${g.kategori} ${g.kataKunci}`.toLowerCase().includes(needle));
    }, [guides, q]);

    const g = guides.find((x) => x.id === aktif) ?? guides[0];

    // Ctrl+K / Cmd+K memfokuskan pencarian panduan.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                searchRef.current?.focus();
            }
            if (e.key === 'Escape' && document.activeElement === searchRef.current) {
                setQ('');
                searchRef.current?.blur();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div className="space-y-4 sb-fade-in">
            <PageHead
                crumb={<Crumb items={[{ label: 'Laporan & Bantuan' }, { label: 'Panduan' }]} />}
                title="Panduan Penggunaan"
                sub="Pelajari cara menggunakan seluruh fitur portal mahasiswa Smart BRAIL."
            />

            {/* Hero */}
            <div className="relative overflow-hidden rounded-[16px] border border-sb-border bg-gradient-to-br from-[#0d1f3f] via-[#123264] to-[#0b2a55] px-6 py-7 text-white sm:px-8">
                <div aria-hidden className="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-sb-cyan/15 blur-3xl" />
                <div aria-hidden className="pointer-events-none absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-sb-primary/25 blur-3xl" />
                <div aria-hidden className="sb-guide-float absolute right-8 top-1/2 hidden -translate-y-1/2 md:block">
                    <span className="grid h-24 w-24 place-items-center rounded-[20px] border border-white/15 bg-white/10 backdrop-blur-sm">
                        <BookOpen size={44} className="text-sb-cyan" />
                    </span>
                </div>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10.5px] font-bold uppercase tracking-wide text-sb-cyan">
                    <Sparkles size={11} /> Pusat Bantuan &amp; Panduan
                </span>
                <h2 className="mt-3 max-w-[480px] text-[24px] font-extrabold leading-tight">Panduan Penggunaan Portal Smart BRAIL</h2>
                <p className="mt-1.5 max-w-[460px] text-[13px] text-white/65">
                    Pelajari cara menggunakan seluruh fitur, aturan, dan alur layanan portal mahasiswa dengan mudah dan terstruktur.
                </p>
                <div className="relative mt-4 max-w-[420px]">
                    <Search size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-white/40" />
                    <input
                        ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)}
                        placeholder='Cari panduan, contoh: "absensi", "ruangan", "laporan"…'
                        className="w-full rounded-[11px] border border-white/15 bg-white/10 py-2.5 pl-10 pr-16 text-[13px] text-white placeholder:text-white/35 focus:border-sb-cyan/60 focus:outline-none"
                        aria-label="Cari panduan"
                    />
                    <kbd className="absolute right-3 top-1/2 hidden -translate-y-1/2 rounded-[6px] border border-white/15 bg-white/10 px-1.5 py-0.5 text-[9.5px] font-bold text-white/50 sm:block">Ctrl K</kbd>
                </div>
            </div>

            {/* Panel dua kolom */}
            <div className="grid items-start gap-4 lg:grid-cols-[300px_minmax(0,1fr)]">
                {/* Daftar panduan */}
                <div className="rounded-[14px] border border-sb-border bg-sb-surface p-2.5">
                    <p className="flex items-center justify-between px-2.5 pb-2 pt-1 text-[11px] font-bold uppercase tracking-wide text-sb-muted">
                        Daftar Panduan <ArrowRight size={13} />
                    </p>
                    <div className="space-y-1">
                        {filtered.map((item) => (
                            <button key={item.id} type="button" onClick={() => setAktif(item.id)}
                                className={cn('flex w-full items-center gap-3 rounded-[10px] px-3 py-2.5 text-left transition',
                                    item.id === g.id ? 'bg-sb-primary-soft text-sb-primary' : 'text-sb-text hover:bg-sb-hover')}>
                                <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-[9px]',
                                    item.id === g.id ? 'bg-sb-primary text-white' : 'bg-sb-surface2 text-sb-muted')}>
                                    <item.icon size={16} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className={cn('block truncate text-[13px] font-bold', item.id === g.id ? 'text-sb-primary' : 'text-sb-text')}>{item.judul}</span>
                                    <span className="block truncate text-[11px] text-sb-muted">{item.deskripsi}</span>
                                </span>
                            </button>
                        ))}
                        {filtered.length === 0 && (
                            <p className="px-3 py-6 text-center text-[12px] text-sb-muted">
                                Tidak ada panduan yang cocok dengan <b>"{q}"</b>.
                            </p>
                        )}
                    </div>
                </div>

                {/* Konten panduan — key memicu animasi ulang saat ganti topik */}
                <div key={g.id} className="sb-guide-panel rounded-[14px] border border-sb-border bg-sb-surface p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="flex items-center gap-3">
                            <span className="grid h-11 w-11 place-items-center rounded-[12px] bg-sb-primary text-white shadow-md shadow-sb-primary/25">
                                <g.icon size={20} />
                            </span>
                            <div>
                                <h3 className="text-[17px] font-extrabold">{g.judul}</h3>
                                <p className="text-[12px] text-sb-muted">{g.deskripsi}</p>
                            </div>
                        </div>
                        <span className="rounded-full border border-sb-primary/25 bg-sb-primary-soft px-3 py-1 text-[10.5px] font-extrabold text-sb-primary">{g.kategori}</span>
                    </div>

                    <div className="mt-4 flex gap-2.5 rounded-[11px] border border-sb-info/25 bg-sb-info-bg/60 px-4 py-3">
                        <Info size={15} className="mt-0.5 shrink-0 text-sb-info" />
                        <div>
                            <p className="text-[12px] font-extrabold text-sb-info">Ringkasan</p>
                            <p className="mt-0.5 text-[12.5px] leading-relaxed text-sb-text2">{g.ringkasan}</p>
                        </div>
                    </div>

                    <div className="mt-5 grid items-start gap-5 md:grid-cols-[minmax(0,1fr)_280px]">
                        {g.langkah.length > 0 && (
                            <div>
                                <p className="text-[13px] font-extrabold">Langkah-Langkah</p>
                                <ol className="mt-3 space-y-3">
                                    {g.langkah.map((s, i) => (
                                        <li key={i} className="sb-guide-step flex items-start gap-3" style={{ animationDelay: `${i * 90}ms` }}>
                                            <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-sb-primary text-[11px] font-extrabold text-white">{i + 1}</span>
                                            <p className="pt-0.5 text-[13px] leading-relaxed text-sb-text2">{s}</p>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        )}
                        <div className="sb-guide-mock">
                            <MockCard kind={g.mock} hariWajib={hariWajib} />
                        </div>
                    </div>

                    <div className="mt-5 flex gap-2.5 rounded-[11px] border border-sb-ok/25 bg-sb-ok-bg/50 px-4 py-3">
                        <CheckCircle2 size={15} className="mt-0.5 shrink-0 text-sb-ok" />
                        <div>
                            <p className="text-[12px] font-extrabold text-sb-ok">Ketentuan</p>
                            <ul className="mt-1 space-y-1">
                                {g.ketentuan.map((k, i) => (
                                    <li key={i} className="flex items-start gap-1.5 text-[12px] text-sb-text2">
                                        <span className="mt-[7px] h-1 w-1 shrink-0 rounded-full bg-sb-ok" />{k}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

Guide.layout = (page: React.ReactNode) => <AppShell title="Panduan">{page}</AppShell>;
