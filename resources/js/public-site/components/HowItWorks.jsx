import { useCallback, useEffect, useRef, useState } from 'react';
import { Reveal, SectionHeader } from './Reveal';

const STEPS = [
  {
    n: '1',
    title: 'Unduh Aplikasi',
    desc: 'Download APK dari halaman unduh resmi SIMALAS, lalu instal di perangkat Android.',
  },
  {
    n: '2',
    title: 'Daftar & Verifikasi OTP',
    desc: 'Mahasiswa mendaftar di app, verifikasi email OTP, lalu menunggu persetujuan admin.',
  },
  {
    n: '3',
    title: 'Absen & Akses Ruangan',
    desc: 'Enroll sidik jari di mesin ESP32, tap absensi, dan akses ruangan pintar dari aplikasi.',
  },
  {
    n: '4',
    title: 'Enroll Sidik Jari',
    desc: 'Daftarkan sidik jari pertama kali di mesin ESP32-S3 laboratorium sebelum absensi rutin.',
  },
  {
    n: '5',
    title: 'Pengumuman Lab',
    desc: 'Baca pengumuman resmi laboratorium langsung dari aplikasi — selalu terbaru.',
  },
  {
    n: '6',
    title: 'Riwayat Absensi',
    desc: 'Pantau histori tap absensi harian dan status kehadiran Anda kapan saja.',
  },
  {
    n: '7',
    title: 'Profil Akademik',
    desc: 'Kelola data diri, NIM, semester, dan informasi akademik di satu tempat.',
  },
  {
    n: '8',
    title: 'Lupa Password',
    desc: 'Reset kata sandi lewat OTP email jika lupa login — tanpa bantuan admin.',
  },
  {
    n: '9',
    title: 'CAPTCHA Keamanan',
    desc: 'Verifikasi CAPTCHA saat registrasi untuk melindungi dari pendaftaran otomatis.',
  },
  {
    n: '10',
    title: 'Notifikasi Penting',
    desc: 'Terima update status akun, pengumuman, dan informasi lab secara real-time.',
  },
];

function StepCard({ step }) {
  return (
    <div className="w-[280px] shrink-0 snap-center rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm select-none dark:border-white/10 dark:bg-[#1c2540]">
      <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-sm font-extrabold text-rose-600">
        {step.n}
      </div>
      <h3 className="text-base font-bold text-slate-900">{step.title}</h3>
      <p className="mt-2 text-sm leading-relaxed text-slate-600">{step.desc}</p>
    </div>
  );
}

function DraggableSlider({ items }) {
  const trackRef = useRef(null);
  const pausedRef = useRef(false);
  const draggingRef = useRef(false);
  const startXRef = useRef(0);
  const scrollStartRef = useRef(0);
  const pauseTimerRef = useRef(null);
  const [isDragging, setIsDragging] = useState(false);

  const loop = [...items, ...items];

  const pauseAuto = useCallback((ms = 4000) => {
    pausedRef.current = true;
    clearTimeout(pauseTimerRef.current);
    pauseTimerRef.current = setTimeout(() => {
      pausedRef.current = false;
    }, ms);
  }, []);

  useEffect(() => {
    const el = trackRef.current;
    if (!el) return undefined;

    const half = el.scrollWidth / 2;
    const tick = () => {
      if (!pausedRef.current && !draggingRef.current) {
        el.scrollLeft += 0.6;
        if (el.scrollLeft >= half) el.scrollLeft -= half;
      }
      raf = requestAnimationFrame(tick);
    };
    let raf = requestAnimationFrame(tick);

    return () => {
      cancelAnimationFrame(raf);
      clearTimeout(pauseTimerRef.current);
    };
  }, []);

  const onPointerDown = (e) => {
    const el = trackRef.current;
    if (!el) return;
    draggingRef.current = true;
    setIsDragging(true);
    pausedRef.current = true;
    startXRef.current = e.clientX;
    scrollStartRef.current = el.scrollLeft;
    el.setPointerCapture(e.pointerId);
  };

  const onPointerMove = (e) => {
    const el = trackRef.current;
    if (!el || !draggingRef.current) return;
    const dx = e.clientX - startXRef.current;
    el.scrollLeft = scrollStartRef.current - dx;
    const half = el.scrollWidth / 2;
    if (el.scrollLeft <= 0) el.scrollLeft += half;
    if (el.scrollLeft >= half) el.scrollLeft -= half;
  };

  const onPointerUp = (e) => {
    const el = trackRef.current;
    if (!el) return;
    draggingRef.current = false;
    setIsDragging(false);
    try {
      el.releasePointerCapture(e.pointerId);
    } catch {
      /* ignore */
    }
    pauseAuto(5000);
  };

  return (
    <div className="relative">
      <div className="pointer-events-none absolute inset-y-0 left-0 z-10 w-12 bg-gradient-to-r from-[#f6f8fb] to-transparent dark:from-[#141b2d]" />
      <div className="pointer-events-none absolute inset-y-0 right-0 z-10 w-12 bg-gradient-to-l from-[#f6f8fb] to-transparent dark:from-[#141b2d]" />
      <div
        ref={trackRef}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerLeave={onPointerUp}
        onPointerEnter={() => pauseAuto(8000)}
        className={`flex gap-4 overflow-x-auto pb-2 scrollbar-none ${
          isDragging ? 'cursor-grabbing' : 'cursor-grab'
        }`}
        style={{ scrollBehavior: isDragging ? 'auto' : undefined }}
      >
        {loop.map((step, i) => (
          <StepCard key={`${step.n}-${i}`} step={step} />
        ))}
      </div>
    </div>
  );
}

export default function HowItWorks() {
  return (
    <section id="cara-pakai" className="scroll-mt-24 overflow-hidden border-b border-slate-200/80 py-20 dark:border-white/10">
      <div className="mx-auto max-w-6xl px-5 lg:px-8">
        <Reveal>
          <SectionHeader
            eyebrow="Cara Pakai"
            title="Panduan lengkap aplikasi SIMALAS"
            subtitle="Semua langkah onboarding dan fitur dalam satu alur — geser manual atau biarkan slide berjalan otomatis."
          />
        </Reveal>
      </div>

      <div className="mt-10">
        <DraggableSlider items={STEPS} />
      </div>

      <p className="mx-auto mt-6 flex max-w-2xl items-center justify-center gap-2 px-5 text-center text-xs text-slate-500 dark:text-slate-400">
        <span className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 dark:border-white/10 dark:bg-white/5">
          <svg viewBox="0 0 16 16" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden>
            <path d="M2 8h12M8 2v12" opacity="0" />
            <path d="M3 8h10" strokeLinecap="round" />
            <path d="M5 6l-2 2 2 2M11 6l2 2-2 2" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
          Geser / seret
        </span>
        untuk menjelajahi · Hover untuk menjeda sementara
      </p>
    </section>
  );
}
