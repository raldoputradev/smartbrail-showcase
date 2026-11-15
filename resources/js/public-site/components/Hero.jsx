import { motion, useReducedMotion } from 'framer-motion';
import FlipWords from './FlipWords';
import PhoneMockup from './PhoneMockup';
import TrustBadges from './TrustBadges';
import FloatingShapes from './FloatingShapes';
import { CountUp } from './Reveal';

const ease = [0.22, 1, 0.36, 1];

export default function Hero({ urls, stats, appVersion, apkAvailable = true }) {
  const total = stats?.total_pengguna ?? stats?.mahasiswa ?? 0;
  const aktif = stats?.pengguna_aktif ?? stats?.mahasiswa ?? 0;
  const reduce = useReducedMotion();

  return (
    <section
      id="beranda"
      className="relative overflow-hidden border-b border-blue-200/40 bg-gradient-to-br from-cyan-50/90 via-white to-blue-50/50"
    >
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.16]"
        style={{
          backgroundImage:
            'linear-gradient(to right, #7dd3fc 1px, transparent 1px), linear-gradient(to bottom, #93c5fd 1px, transparent 1px)',
          backgroundSize: '56px 56px',
        }}
      />

      <FloatingShapes />

      <div className="relative mx-auto grid max-w-6xl gap-12 px-5 py-16 md:grid-cols-2 md:items-center md:py-24 lg:px-8">
        <div>
          <motion.span
            initial={reduce ? false : { opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, ease }}
            className="mb-4 inline-flex items-center gap-2 rounded-full border border-blue-200/80 bg-white/80 px-3 py-1 text-xs font-bold tracking-wide text-blue-700 shadow-sm backdrop-blur"
          >
            <span
              className={`inline-block h-1.5 w-1.5 rounded-full bg-blue-500 ${reduce ? '' : 'animate-soft-pulse'}`}
              aria-hidden
            />
            POLIBATAM · BRAIL · 2026
          </motion.span>

          <motion.h1
            initial={reduce ? false : { opacity: 0, y: 22 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.06, ease }}
            className="text-4xl font-extrabold leading-[1.12] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.25rem]"
          >
            <span className="block text-slate-900">Sistem Manajemen</span>
            <FlipWords />
          </motion.h1>

          <motion.p
            initial={reduce ? false : { opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55, delay: 0.14, ease }}
            className="mt-5 max-w-lg text-base leading-relaxed text-slate-600 sm:text-lg"
          >
            Platform laboratorium pintar untuk absensi sidik jari ESP32, akses ruangan
            berbasis izin, manajemen tim PBL, dan portal khusus setiap peran — admin,
            laboran, dosen, hingga mahasiswa.
          </motion.p>

          <motion.div
            initial={reduce ? false : { opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.22, ease }}
            className="mt-8 flex flex-wrap gap-3"
          >
            <a
              href={apkAvailable ? urls.download : urls.login}
              className="btn-unduh-attention inline-flex items-center rounded-xl bg-gradient-to-r from-blue-600 to-cyan-500 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-600/25 transition hover:-translate-y-0.5 hover:scale-[1.02] hover:from-blue-500 hover:to-cyan-400"
            >
              {apkAvailable ? 'Unduh Sekarang' : 'Masuk Portal'}
            </a>
            <a
              href="#cara-pakai"
              className="inline-flex items-center gap-2 rounded-xl border border-blue-200/80 bg-white/80 px-6 py-3.5 text-sm font-semibold text-slate-700 backdrop-blur transition hover:-translate-y-0.5 hover:border-cyan-300 hover:bg-white hover:shadow-md"
            >
              Cara Pakai
            </a>
          </motion.div>

          <motion.div
            initial={reduce ? false : { opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.3, ease }}
            className="mt-8 inline-flex items-stretch overflow-hidden rounded-2xl border border-blue-200/60 bg-white/80 shadow-sm backdrop-blur transition hover:shadow-md"
          >
            <UserStat label="Total Pengguna" value={total} />
            <div className="w-px self-stretch bg-blue-200/60" />
            <UserStat label="Pengguna Aktif" value={aktif} accent />
          </motion.div>

          <motion.div
            initial={reduce ? false : { opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.38, ease }}
            className="mt-8"
          >
            <TrustBadges compact />
          </motion.div>
        </div>

        <motion.div
          initial={reduce ? false : { opacity: 0, scale: 0.94, y: 24 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.18, ease }}
          className="flex justify-center md:justify-end"
        >
          <PhoneMockup version={appVersion} interactive logoMark={urls?.logoMark} />
        </motion.div>
      </div>
    </section>
  );
}

function UserStat({ label, value, accent = false }) {
  return (
    <div className="px-6 py-4">
      <div
        className={`text-2xl font-extrabold tabular-nums ${
          accent ? 'text-blue-600' : 'text-slate-900'
        }`}
      >
            <CountUp value={value} duration={1.8} />
      </div>
      <div className="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        {label}
      </div>
    </div>
  );
}
