import { Reveal } from './Reveal';

const HIGHLIGHTS = [
  'Absensi sidik jari via perangkat ESP32 di laboratorium',
  'Akses ruangan berbasis izin, jadwal, dan audit lengkap',
  'Portal khusus per peran: admin, laboran, dosen, mahasiswa',
  'Manajemen tim PBL dengan jejak aktivitas tercatat',
];

export default function About() {
  return (
    <section
      id="tentang"
      className="scroll-mt-24 border-b border-blue-100/80 bg-white/60 py-20 backdrop-blur-sm dark:border-white/10 dark:bg-[#0f172a]/60"
    >
      <div className="mx-auto max-w-3xl px-5 text-center lg:px-8">
        <Reveal>
          <span className="text-xs font-bold uppercase tracking-[0.2em] text-blue-600 dark:text-sky-400">
            Tentang
          </span>
          <h2 className="mt-3 text-3xl font-extrabold text-slate-900 dark:text-white sm:text-4xl">
            Laboratorium pintar terintegrasi
          </h2>
          <p className="mt-5 text-base leading-relaxed text-slate-600 dark:text-slate-300">
            SmartBrail menggabungkan perangkat ESP32 di edge, backend Laravel terpusat, dan
            portal web + aplikasi mobile. Dikembangkan di BRAIL, Politeknik Negeri Batam —
            untuk operasional laboratorium yang lebih efisien, aman, dan terukur.
          </p>
          <ul className="mx-auto mt-8 grid max-w-xl gap-3 text-left sm:grid-cols-2">
            {HIGHLIGHTS.map((item) => (
              <li
                key={item}
                className="flex items-start gap-2.5 rounded-xl border border-blue-100/80 bg-blue-50/50 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
              >
                <span className="mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden>
                  ✓
                </span>
                {item}
              </li>
            ))}
          </ul>
        </Reveal>
      </div>
    </section>
  );
}
