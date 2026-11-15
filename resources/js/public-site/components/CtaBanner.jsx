import { motion } from 'framer-motion';
import { Reveal } from './Reveal';

export default function CtaBanner({ urls }) {
  return (
    <section className="py-16">
      <div className="mx-auto max-w-6xl px-5 lg:px-8">
        <Reveal>
          <motion.div
            whileHover={{ scale: 1.01 }}
            className="relative overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 px-8 py-12 text-center shadow-sm"
          >
            <div
              className="pointer-events-none absolute inset-0"
              style={{
                backgroundImage:
                  'radial-gradient(ellipse 70% 55% at 15% 20%, rgba(148,163,184,0.18), transparent 55%), radial-gradient(ellipse 55% 50% at 90% 80%, rgba(100,116,139,0.1), transparent 50%)',
              }}
            />
            <div className="relative">
              <h2 className="text-3xl font-extrabold text-slate-900 sm:text-4xl">
                Siap pakai laboratorium pintar?
              </h2>
              <p className="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-slate-600">
                Unduh aplikasi mobile SIMALAS sekarang — registrasi mahasiswa, absensi
                biometrik, dan akses ruangan pintar dalam satu app.
              </p>
              <a
                href={urls.download}
                className="btn-unduh-attention mt-8 inline-flex rounded-xl bg-gradient-to-r from-rose-600 to-orange-500 px-8 py-3.5 text-sm font-bold text-white shadow-lg shadow-rose-500/25 transition hover:-translate-y-0.5 hover:from-rose-500 hover:to-orange-400"
              >
                Unduh Sekarang
              </a>
            </div>
          </motion.div>
        </Reveal>
      </div>
    </section>
  );
}
