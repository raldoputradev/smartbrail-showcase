import { motion, useMotionTemplate, useMotionValue, useReducedMotion } from 'framer-motion';
import { useCallback } from 'react';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';
import PhoneMockup from '../components/PhoneMockup';
import InstallGuide from '../components/InstallGuide';
import TrustBadges from '../components/TrustBadges';
import ChatWidget from '../components/ChatWidget';
import FloatingShapes from '../components/FloatingShapes';
import { Reveal } from '../components/Reveal';

export default function DownloadPage({
  version,
  versionCode,
  changelog,
  apkUrl,
  apkExists,
  apkSizeMb,
  urls,
  appVersion,
}) {
  const reduce = useReducedMotion();
  const mouseX = useMotionValue(0);
  const mouseY = useMotionValue(0);

  const onMouseMove = useCallback(
    (e) => {
      if (reduce) return;
      const rect = e.currentTarget.getBoundingClientRect();
      mouseX.set(e.clientX - rect.left);
      mouseY.set(e.clientY - rect.top);
    },
    [mouseX, mouseY, reduce],
  );

  const spotlight = useMotionTemplate`radial-gradient(520px circle at ${mouseX}px ${mouseY}px, rgba(251, 113, 133, 0.18), transparent 55%)`;

  return (
    <>
      <Navbar urls={urls} variant="download" />

      <div
        className="relative isolate overflow-hidden"
        onMouseMove={onMouseMove}
      >
        {/* Atmospheric base */}
        <div className="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-orange-50 via-[#fff8f6] to-rose-100/80" />

        {/* Soft mesh blobs */}
        <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden>
          <div className="dl-orb dl-orb--a" />
          <div className="dl-orb dl-orb--b" />
          <div className="dl-orb dl-orb--c" />
        </div>

        {/* Tech grid */}
        <div
          className="pointer-events-none absolute inset-0 -z-10 opacity-[0.22]"
          style={{
            backgroundImage:
              'linear-gradient(to right, #fdba74 1px, transparent 1px), linear-gradient(to bottom, #fda4af 1px, transparent 1px)',
            backgroundSize: '56px 56px',
            maskImage: 'radial-gradient(ellipse 80% 70% at 50% 30%, black 20%, transparent 75%)',
            WebkitMaskImage:
              'radial-gradient(ellipse 80% 70% at 50% 30%, black 20%, transparent 75%)',
          }}
        />

        {/* Cursor spotlight */}
        {!reduce && (
          <motion.div
            className="pointer-events-none absolute inset-0 -z-10 transition-opacity duration-300"
            style={{ background: spotlight }}
          />
        )}

        {/* Interactive floating shapes */}
        <FloatingShapes className="z-0" />

        <main className="relative z-10 mx-auto max-w-4xl px-5 py-12 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55 }}
            className="text-center"
          >
            <span className="inline-flex items-center gap-2 rounded-full border border-rose-200/80 bg-white/80 px-3 py-1 text-xs font-bold text-rose-700 shadow-sm backdrop-blur">
              <span
                className={`inline-block h-1.5 w-1.5 rounded-full bg-rose-500 ${reduce ? '' : 'animate-soft-pulse'}`}
                aria-hidden
              />
              APLIKASI MOBILE
            </span>
            <h1 className="mt-4 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
              Unduh AIoT SIMALAS
            </h1>
            <p className="mx-auto mt-3 max-w-lg text-slate-600">
              Aplikasi Android untuk mahasiswa — absensi biometrik, loker pintar, dan pengumuman
              laboratorium.
            </p>
          </motion.div>

          <Reveal className="mt-12">
            <div className="rounded-3xl border border-rose-200/70 bg-white/90 p-6 shadow-xl shadow-rose-900/10 backdrop-blur-md sm:p-8">
              <div className="flex flex-col items-center gap-10 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex w-full justify-center lg:w-auto lg:shrink-0">
                  <PhoneMockup
                    version={appVersion}
                    interactive={false}
                    autoRotate
                    logoMark={urls?.logoMark}
                  />
                </div>
                <div className="w-full min-w-0 flex-1">
                  <div className="flex flex-wrap gap-2">
                    <Chip label={`Versi ${version}`} />
                    <Chip label={`Build ${versionCode}`} />
                    {apkSizeMb && <Chip label={`${apkSizeMb} MB`} />}
                  </div>
                  {changelog && (
                    <div className="mt-5 rounded-2xl border border-orange-100 bg-orange-50/90 p-4">
                      <h3 className="text-xs font-bold uppercase tracking-wide text-orange-700">
                        Apa yang baru
                      </h3>
                      <p className="mt-2 text-sm leading-relaxed text-slate-700">{changelog}</p>
                    </div>
                  )}
                  <div className="mt-6">
                    {apkExists ? (
                      <a
                        href={apkUrl}
                        className="btn-unduh-attention inline-flex rounded-xl bg-gradient-to-r from-rose-600 to-orange-500 px-8 py-3.5 text-sm font-bold text-white shadow-lg shadow-rose-600/30 transition hover:-translate-y-0.5 hover:from-rose-500 hover:to-orange-400"
                      >
                        Unduh Sekarang
                      </a>
                    ) : (
                      <button
                        type="button"
                        disabled
                        className="rounded-xl bg-slate-300 px-8 py-3.5 text-sm font-bold text-slate-600"
                      >
                        APK belum tersedia
                      </button>
                    )}
                  </div>
                  <p className="mt-5 text-xs leading-relaxed text-slate-500">
                    File resmi dari server SIMALAS. Jika browser memblokir, izinkan unduhan dan buka
                    dari folder Unduhan perangkat Anda.
                  </p>
                  <div className="mt-6">
                    <TrustBadges />
                  </div>
                </div>
              </div>
            </div>
          </Reveal>

          <div className="relative z-10 mt-16">
            <InstallGuide />
          </div>
        </main>
      </div>

      <Footer urls={urls} />
      <ChatWidget urls={urls} />

      <style>{`
        .dl-orb {
          position: absolute;
          border-radius: 9999px;
          filter: blur(64px);
          opacity: 0.55;
          will-change: transform;
        }
        .dl-orb--a {
          width: 28rem;
          height: 28rem;
          top: -8%;
          left: -6%;
          background: radial-gradient(circle, rgba(251, 146, 60, 0.55), transparent 70%);
          animation: dl-drift-a 18s ease-in-out infinite;
        }
        .dl-orb--b {
          width: 24rem;
          height: 24rem;
          top: 18%;
          right: -8%;
          background: radial-gradient(circle, rgba(251, 113, 133, 0.45), transparent 70%);
          animation: dl-drift-b 22s ease-in-out infinite;
        }
        .dl-orb--c {
          width: 20rem;
          height: 20rem;
          bottom: 8%;
          left: 35%;
          background: radial-gradient(circle, rgba(253, 186, 116, 0.4), transparent 70%);
          animation: dl-drift-c 16s ease-in-out infinite;
        }
        @keyframes dl-drift-a {
          0%, 100% { transform: translate(0, 0) scale(1); }
          50% { transform: translate(40px, 30px) scale(1.08); }
        }
        @keyframes dl-drift-b {
          0%, 100% { transform: translate(0, 0) scale(1); }
          50% { transform: translate(-36px, 24px) scale(1.06); }
        }
        @keyframes dl-drift-c {
          0%, 100% { transform: translate(0, 0); }
          50% { transform: translate(24px, -28px); }
        }
        @media (prefers-reduced-motion: reduce) {
          .dl-orb--a, .dl-orb--b, .dl-orb--c { animation: none; }
        }
      `}</style>
    </>
  );
}

function Chip({ label }) {
  return (
    <span className="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur">
      {label}
    </span>
  );
}
