import { Reveal, SectionHeader } from './Reveal';
import FloatingShapes from './FloatingShapes';

function IconFingerprint({ className }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <path d="M12 11a3 3 0 0 0-3 3v2.5" strokeLinecap="round" />
      <path d="M12 7a7 7 0 0 0-7 7v1" strokeLinecap="round" />
      <path d="M12 7a7 7 0 0 1 7 7v1" strokeLinecap="round" />
      <path d="M9.5 21c.6-2.2 1.4-3.5 2.5-3.5s1.9 1.3 2.5 3.5" strokeLinecap="round" />
      <path d="M6 21c.8-3.2 2-5.5 4-6.2" strokeLinecap="round" />
      <path d="M18 21c-.8-3.2-2-5.5-4-6.2" strokeLinecap="round" />
      <path d="M12 3.5a10 10 0 0 0-8.5 14" strokeLinecap="round" />
      <path d="M12 3.5a10 10 0 0 1 8.5 14" strokeLinecap="round" />
    </svg>
  );
}

function IconLock({ className }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <rect x="4.5" y="10.5" width="15" height="10" rx="2.5" />
      <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" strokeLinecap="round" />
      <circle cx="12" cy="15.5" r="1.3" fill="currentColor" stroke="none" />
    </svg>
  );
}

function IconSmartphone({ className }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <rect x="7" y="2.5" width="10" height="19" rx="2.5" />
      <path d="M11 5.5h2" strokeLinecap="round" />
      <circle cx="12" cy="18.5" r="0.9" fill="currentColor" stroke="none" />
    </svg>
  );
}

function IconDashboard({ className }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <rect x="3.5" y="3.5" width="7" height="7" rx="1.8" />
      <rect x="13.5" y="3.5" width="7" height="4.5" rx="1.5" />
      <rect x="13.5" y="10.5" width="7" height="10" rx="1.8" />
      <rect x="3.5" y="13" width="7" height="7.5" rx="1.8" />
    </svg>
  );
}

function IconBell({ className }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <path d="M7.5 17.5h9" strokeLinecap="round" />
      <path d="M6 17.5a6.5 6.5 0 0 1 6-10.5 6.5 6.5 0 0 1 6 10.5" strokeLinecap="round" />
      <path d="M10 17.5a2 2 0 0 0 4 0" strokeLinecap="round" />
      <path d="M12 4.5V3.2" strokeLinecap="round" />
    </svg>
  );
}

function IconShield({ className }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <path d="M12 3.2l7.5 3.2v5.4c0 4.6-3.1 8.6-7.5 9.8-4.4-1.2-7.5-5.2-7.5-9.8V6.4L12 3.2z" strokeLinejoin="round" />
      <path d="M9.2 12.1l1.9 1.9 3.7-3.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

const FEATURES = [
  {
    title: 'Absensi Sidik Jari',
    desc: 'Tap biometrik ESP32-S3 terhubung real-time ke server Laravel.',
    Icon: IconFingerprint,
    tone: 'from-rose-500 to-orange-500',
    glow: 'group-hover:shadow-rose-500/25',
    iconColor: 'text-white',
  },
  {
    title: 'Akses Ruangan Pintar',
    desc: 'Kelola akses ruangan laboratorium lewat aplikasi mobile.',
    Icon: IconLock,
    tone: 'from-orange-500 to-amber-500',
    glow: 'group-hover:shadow-orange-500/25',
    iconColor: 'text-white',
  },
  {
    title: 'Aplikasi Mahasiswa',
    desc: 'Registrasi, OTP, profil, pengumuman, dan riwayat absensi di satu app.',
    Icon: IconSmartphone,
    tone: 'from-fuchsia-500 to-rose-500',
    glow: 'group-hover:shadow-fuchsia-500/25',
    iconColor: 'text-white',
  },
  {
    title: 'Dashboard Dosen & Laboran',
    desc: 'Rekap absensi, laporan, dan monitoring mesin via portal staff terpisah.',
    Icon: IconDashboard,
    tone: 'from-rose-600 to-red-500',
    glow: 'group-hover:shadow-rose-600/25',
    iconColor: 'text-white',
  },
  {
    title: 'Notifikasi Real-time',
    desc: 'Push notification pengumuman dan update penting laboratorium.',
    Icon: IconBell,
    tone: 'from-amber-500 to-orange-600',
    glow: 'group-hover:shadow-amber-500/25',
    iconColor: 'text-white',
  },
  {
    title: 'Keamanan Terenkripsi',
    desc: 'API key mesin, template sidik jari, dan sesi login diamankan.',
    Icon: IconShield,
    tone: 'from-orange-600 to-rose-600',
    glow: 'group-hover:shadow-orange-600/25',
    iconColor: 'text-white',
  },
];

export default function Features() {
  return (
    <section id="fitur" className="relative scroll-mt-24 overflow-hidden bg-white py-20 dark:bg-[#111827]">
      <FloatingShapes variant="sparse" />
      <div className="relative mx-auto max-w-6xl px-5 lg:px-8">
        <Reveal>
          <SectionHeader
            eyebrow="Fitur"
            title="Semua yang kamu butuhkan"
            subtitle="Ekosistem lengkap untuk laboratorium pintar berbasis AIoT di Polibatam."
          />
        </Reveal>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((f, i) => (
            <Reveal key={f.title} delay={i * 0.05}>
              <article className="group h-full cursor-pointer rounded-2xl border border-slate-200/80 bg-[#fff8f6] p-6 transition duration-300 hover:-translate-y-1 hover:border-rose-200 hover:bg-white hover:shadow-xl">
                <div
                  className={`mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br ${f.tone} shadow-md transition duration-300 ${f.glow} group-hover:scale-110 group-hover:shadow-lg`}
                >
                  <f.Icon className={`h-7 w-7 ${f.iconColor}`} />
                </div>
                <h3 className="font-bold text-slate-900 transition group-hover:text-rose-700">
                  {f.title}
                </h3>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{f.desc}</p>
              </article>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
