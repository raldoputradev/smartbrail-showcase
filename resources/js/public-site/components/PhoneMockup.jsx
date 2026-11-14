import { AnimatePresence, motion, useMotionValue, useSpring, useTransform } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';

const W = 300;
const H = Math.round(W * (19.5 / 9));

function useLiveClock() {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const tick = () => setNow(new Date());
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, []);

  return now.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

function StatusBar() {
  const time = useLiveClock();

  return (
    <div className="flex items-center justify-between px-5 pb-2 pt-10 text-[10px] font-medium text-white/60">
      <span>{time}</span>
      <span className="flex items-center gap-1">
        <span className="inline-block h-2 w-3 rounded-sm border border-white/50" />
        100%
      </span>
    </div>
  );
}

function ScreenHome({ logoMark }) {
  return (
    <div className="flex h-full flex-col">
      <StatusBar />
      <div className="flex-1 px-4 pb-6">
        <div className="mb-5 flex items-center gap-3">
          <img src={logoMark} alt="" className="h-12 w-12 rounded-2xl bg-white p-1 object-contain" />
          <div>
            <div className="text-base font-bold">AIoT SIMALAS</div>
            <div className="text-[11px] text-slate-400">Aplikasi Mahasiswa</div>
          </div>
        </div>
        <motion.div
          initial={{ scale: 0.95, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          className="mb-4 rounded-2xl border border-sky-400/25 bg-gradient-to-br from-blue-600/50 to-teal-500/35 p-4"
        >
          <div className="text-[10px] font-bold uppercase tracking-wider text-sky-200/90">Absensi hari ini</div>
          <motion.div
            animate={{ scale: [1, 1.04, 1] }}
            transition={{ duration: 2, repeat: Infinity }}
            className="mt-1 text-xl font-extrabold text-emerald-300"
          >
            Tercatat ✓
          </motion.div>
          <div className="mt-1 text-[11px] text-slate-300">Tap sidik jari · ESP32</div>
        </motion.div>
        {['Loker Pintar', 'Pengumuman Lab', 'Profil & Riwayat'].map((label, i) => (
          <motion.div
            key={label}
            initial={{ x: -12, opacity: 0 }}
            animate={{ x: 0, opacity: 1 }}
            transition={{ delay: 0.1 + i * 0.08 }}
            className="mb-2 flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.06] px-3.5 py-3 text-[12px] font-medium"
          >
            <span>{label}</span>
            <span className="text-slate-500">›</span>
          </motion.div>
        ))}
      </div>
    </div>
  );
}

function ScreenOtp() {
  return (
    <div className="flex h-full flex-col">
      <StatusBar />
      <div className="flex flex-1 flex-col items-center justify-center px-5 pb-8 text-center">
        <div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-sky-400">Verifikasi Email</div>
        <div className="text-lg font-bold">Masukkan OTP</div>
        <p className="mt-2 text-[11px] text-slate-400">Kode 6 digit dikirim ke email Anda</p>
        <div className="mt-6 flex gap-2">
          {[3, 7, 1, 9, 2, 5].map((d, i) => (
            <motion.div
              key={i}
              initial={{ y: 8, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: i * 0.07 }}
              className="flex h-10 w-9 items-center justify-center rounded-lg border border-sky-500/40 bg-sky-500/10 text-sm font-bold text-sky-200"
            >
              {d}
            </motion.div>
          ))}
        </div>
        <motion.div
          animate={{ opacity: [0.5, 1, 0.5] }}
          transition={{ duration: 1.8, repeat: Infinity }}
          className="mt-6 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold"
        >
          Verifikasi
        </motion.div>
      </div>
    </div>
  );
}

function ScreenLocker() {
  return (
    <div className="flex h-full flex-col">
      <StatusBar />
      <div className="flex-1 px-4 pb-6">
        <div className="text-sm font-bold">Loker Pintar</div>
        <p className="text-[11px] text-slate-400">BRAIL · Lantai 2</p>
        <motion.div
          animate={{ boxShadow: ['0 0 0 0 rgba(52,211,153,0.4)', '0 0 0 12px rgba(52,211,153,0)', '0 0 0 0 rgba(52,211,153,0)'] }}
          transition={{ duration: 2, repeat: Infinity }}
          className="mt-6 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-5 text-center"
        >
          <div className="text-4xl">🔓</div>
          <div className="mt-2 text-lg font-extrabold text-emerald-300">Loker #12 Terbuka</div>
          <div className="mt-1 text-[11px] text-slate-400">Akses 15 menit · ESP32</div>
        </motion.div>
        <div className="mt-4 grid grid-cols-4 gap-2">
          {Array.from({ length: 8 }, (_, i) => (
            <div
              key={i}
              className="rounded-lg bg-white/5 py-3 text-center text-[10px] font-bold text-slate-500"
            >
              {i + 9}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function ScreenAnnounce() {
  const items = [
    { tag: 'PENTING', title: 'Jadwal praktikum minggu ini', time: '2 jam lalu' },
    { tag: 'INFO', title: 'Maintenance mesin ESP32', time: 'Kemarin' },
    { tag: 'LAB', title: 'Pendaftaran asisten lab dibuka', time: '3 hari lalu' },
  ];
  return (
    <div className="flex h-full flex-col">
      <StatusBar />
      <div className="flex-1 px-4 pb-6">
        <div className="text-sm font-bold">Pengumuman Lab</div>
        <p className="text-[11px] text-slate-400">3 pengumuman baru</p>
        <div className="mt-4 space-y-2">
          {items.map((item, i) => (
            <motion.div
              key={item.title}
              initial={{ x: 20, opacity: 0 }}
              animate={{ x: 0, opacity: 1 }}
              transition={{ delay: i * 0.1 }}
              className="rounded-xl border border-white/10 bg-white/[0.06] p-3"
            >
              <span className="rounded bg-blue-500/30 px-1.5 py-0.5 text-[9px] font-bold text-sky-200">
                {item.tag}
              </span>
              <div className="mt-1.5 text-[12px] font-semibold leading-snug">{item.title}</div>
              <div className="mt-1 text-[10px] text-slate-500">{item.time}</div>
            </motion.div>
          ))}
        </div>
      </div>
    </div>
  );
}

function ScreenRegister() {
  return (
    <div className="flex h-full flex-col">
      <StatusBar />
      <div className="flex-1 px-4 pb-6">
        <div className="text-sm font-bold">Daftar Akun</div>
        <p className="text-[11px] text-slate-400">Data Diri · Langkah 1 dari 3</p>
        <div className="mt-4 space-y-2.5">
          {['Nama Lengkap', 'Email Polibatam', 'NIM', 'Semester'].map((label, i) => (
            <motion.div
              key={label}
              initial={{ opacity: 0, y: 6 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.08 }}
              className="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5"
            >
              <div className="text-[9px] text-slate-500">{label}</div>
              <div className="mt-0.5 h-2 w-3/4 rounded bg-white/10" />
            </motion.div>
          ))}
        </div>
        <div className="mt-4 flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2">
          <span className="flex h-4 w-4 items-center justify-center rounded bg-emerald-500 text-[9px] text-white">✓</span>
          <span className="text-[10px] text-emerald-200">CAPTCHA terverifikasi</span>
        </div>
      </div>
    </div>
  );
}

const SCREENS = [
  { id: 'home', label: 'Beranda', Component: ScreenHome },
  { id: 'otp', label: 'OTP', Component: ScreenOtp },
  { id: 'locker', label: 'Loker', Component: ScreenLocker },
  { id: 'news', label: 'Info', Component: ScreenAnnounce },
  { id: 'register', label: 'Daftar', Component: ScreenRegister },
];

export default function PhoneMockup({
  version = '1.3.8',
  interactive = true,
  autoRotate = true,
  logoMark,
}) {
  const markSrc = logoMark || `${import.meta.env.BASE_URL}branding/aiot-simalas-mark-s.png`;
  const ref = useRef(null);
  const [screenIdx, setScreenIdx] = useState(0);
  const mx = useMotionValue(0);
  const my = useMotionValue(0);
  const rotateX = useSpring(useTransform(my, [-0.5, 0.5], [8, -8]), { stiffness: 140, damping: 20 });
  const rotateY = useSpring(useTransform(mx, [-0.5, 0.5], [-10, 10]), { stiffness: 140, damping: 20 });

  useEffect(() => {
    const id = setInterval(() => {
      setScreenIdx((i) => (i + 1) % SCREENS.length);
    }, 3800);
    return () => clearInterval(id);
  }, []);

  const onMove = (e) => {
    if (!interactive) return;
    const el = ref.current;
    if (!el) return;
    const r = el.getBoundingClientRect();
    mx.set((e.clientX - r.left) / r.width - 0.5);
    my.set((e.clientY - r.top) / r.height - 0.5);
  };

  const onLeave = () => {
    mx.set(0);
    my.set(0);
  };

  const ActiveScreen = SCREENS[screenIdx].Component;
  const screenProps = ActiveScreen === ScreenHome ? { logoMark: markSrc } : {};

  return (
    <div className="relative mx-auto shrink-0 py-4" style={{ width: W, height: H + 48, perspective: 1200 }}>
      <motion.div
        ref={ref}
        onMouseMove={onMove}
        onMouseLeave={onLeave}
        style={{
          width: W,
          height: H,
          rotateX: interactive ? rotateX : 0,
          rotateY: interactive ? rotateY : undefined,
          transformStyle: 'preserve-3d',
        }}
        animate={
          autoRotate
            ? { y: [0, -14, 0], rotateY: interactive ? undefined : [-8, 8, -8] }
            : { y: [0, -10, 0] }
        }
        transition={{
          y: { duration: 5.5, repeat: Infinity, ease: 'easeInOut' },
          rotateY: { duration: 7, repeat: Infinity, ease: 'easeInOut' },
        }}
        className="relative mx-auto"
      >
        <div
          className="absolute inset-0 rounded-[2.75rem] bg-gradient-to-b from-slate-600 via-slate-800 to-slate-950 p-[10px] shadow-[0_50px_100px_-24px_rgba(15,23,42,0.65)] ring-1 ring-white/10 dark:from-slate-500 dark:via-slate-700 dark:to-black"
          style={{ width: W, height: H }}
        >
          <div className="absolute left-1/2 top-[18px] z-20 h-[26px] w-[100px] -translate-x-1/2 rounded-full bg-black shadow-inner" />
          <div className="relative h-full w-full overflow-hidden rounded-[2.35rem] border border-black/40 bg-[#0c1222] text-white">
            <AnimatePresence mode="wait">
              <motion.div
                key={SCREENS[screenIdx].id}
                initial={{ opacity: 0, x: 28, scale: 0.98 }}
                animate={{ opacity: 1, x: 0, scale: 1 }}
                exit={{ opacity: 0, x: -28, scale: 0.98 }}
                transition={{ duration: 0.42, ease: [0.22, 1, 0.36, 1] }}
                className="absolute inset-0"
              >
                <ActiveScreen {...screenProps} />
              </motion.div>
            </AnimatePresence>
            <div className="absolute bottom-3 left-0 right-0 z-10 flex justify-center gap-1.5">
              {SCREENS.map((s, i) => (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => setScreenIdx(i)}
                  className={`h-1.5 rounded-full transition-all ${
                    i === screenIdx ? 'w-4 bg-rose-400' : 'w-1.5 bg-white/30'
                  }`}
                  aria-label={s.label}
                />
              ))}
            </div>
          </div>
          <div className="absolute -left-[3px] top-[28%] h-12 w-[3px] rounded-l bg-slate-600" />
          <div className="absolute -left-[3px] top-[40%] h-16 w-[3px] rounded-l bg-slate-600" />
          <div className="absolute -right-[3px] top-[34%] h-20 w-[3px] rounded-r bg-slate-600" />
        </div>

        <motion.div
          animate={{ y: [0, -6, 0] }}
          transition={{ duration: 3.8, repeat: Infinity, ease: 'easeInOut' }}
          className="absolute -right-2 -top-2 z-30 rounded-xl border border-white/40 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-lg dark:bg-slate-800 dark:text-white"
        >
          v{version}
        </motion.div>
      </motion.div>
    </div>
  );
}
