export default function Footer({ urls }) {
  const year = new Date().getFullYear();

  return (
    <footer className="relative overflow-hidden border-t border-slate-200 bg-slate-50 pb-10 pt-16">
      <div className="relative mx-auto max-w-6xl px-5 lg:px-8">
        <div className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div className="sm:col-span-2 lg:col-span-1">
              <img
                src={urls?.logoLockup || `${import.meta.env.BASE_URL}branding/aiot-simalas-logo-lockup-s.png`}
                alt="AIoT SIMALAS"
                className="mb-4 h-14 w-auto max-w-[220px] object-contain"
              />
              <p className="text-sm leading-relaxed text-slate-600">
                Sistem manajemen laboratorium pintar berbasis AIoT — Politeknik Negeri Batam, BRAIL.
              </p>
            </div>
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Layanan
              </h4>
              <ul className="mt-4 space-y-2.5 text-sm text-slate-700">
                <li><a href="#beranda" className="hover:text-rose-600">Home</a></li>
                <li><a href="#tentang" className="hover:text-rose-600">Tentang</a></li>
                <li><a href="#fitur" className="hover:text-rose-600">Fitur</a></li>
                <li><a href="#cara-pakai" className="hover:text-rose-600">Cara Pakai</a></li>
                <li><a href="#faq" className="hover:text-rose-600">FAQ</a></li>
              </ul>
            </div>
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Aplikasi
              </h4>
              <ul className="mt-4 space-y-2.5 text-sm text-slate-700">
                <li>
                  <a
                    href={urls.download}
                    className="inline-flex items-center gap-1.5 font-bold text-rose-600 hover:text-orange-600"
                  >
                    Unduh APK
                    <span className="rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-rose-700">
                      Utama
                    </span>
                  </a>
                </li>
                <li>
                  <a href={urls.download} className="hover:text-rose-600">
                    Panduan Instal
                  </a>
                </li>
                <li>
                  <a href={urls.beranda} className="hover:text-rose-600">
                    Beranda
                  </a>
                </li>
              </ul>
            </div>
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Bantuan
              </h4>
              <ul className="mt-4 space-y-2.5 text-sm text-slate-700">
                <li><a href="#kontak" className="hover:text-rose-600">Lokasi & Peta</a></li>
                <li><a href="#hubungi" className="hover:text-rose-600">Hubungi Tim</a></li>
                <li><a href="#faq" className="hover:text-rose-600">FAQ</a></li>
              </ul>
            </div>
          </div>

          <div className="mt-8 flex flex-col items-center justify-between gap-4 border-t border-slate-200 pt-6 sm:flex-row">
            <p className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
              <svg viewBox="0 0 20 20" className="h-3.5 w-3.5 text-emerald-600" fill="currentColor" aria-hidden>
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clipRule="evenodd"
                />
              </svg>
              Situs resmi & terenkripsi HTTPS
            </p>
            <p className="text-xs text-slate-500">
              © {year} AIoT SIMALAS · Politeknik Negeri Batam
            </p>
          </div>
        </div>

        <p className="mt-6 text-center text-[11px] text-slate-500">
          BRAIL · Politeknik Negeri Batam
        </p>
      </div>
    </footer>
  );
}
