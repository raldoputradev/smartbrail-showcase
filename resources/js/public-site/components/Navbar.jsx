import { useEffect, useState } from 'react';
import { useScrollSpy } from '../hooks/useScrollSpy';

const LINKS = [
  { href: '#beranda', id: 'beranda', label: 'Home' },
  { href: '#tentang', id: 'tentang', label: 'Tentang' },
  { href: '#fitur', id: 'fitur', label: 'Fitur' },
  { href: '#cara-pakai', id: 'cara-pakai', label: 'Cara Pakai' },
  { href: '#faq', id: 'faq', label: 'FAQ' },
  { href: '#kontak', id: 'kontak', label: 'Kontak' },
];

export default function Navbar({ urls, variant = 'landing', apkAvailable = true }) {
  const home = urls?.beranda || '/';
  const [menuOpen, setMenuOpen] = useState(false);
  const activeSection = useScrollSpy(
    LINKS.map((l) => l.href),
    '-42% 0px -52% 0px',
  );

  const goHome = (e) => {
    e.preventDefault();
    setMenuOpen(false);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (window.history?.replaceState) {
      window.history.replaceState(null, '', '#beranda');
    }
  };

  const closeMenu = () => setMenuOpen(false);

  useEffect(() => {
    if (!menuOpen) return undefined;
    const onKey = (e) => {
      if (e.key === 'Escape') setMenuOpen(false);
    };
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [menuOpen]);

  useEffect(() => {
    const onResize = () => {
      if (window.innerWidth >= 768) setMenuOpen(false);
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  return (
    <header className="sticky top-0 z-50 border-b border-blue-200/50 bg-[#f0f7ff]/90 backdrop-blur-xl">
      <div
        className={`mx-auto grid h-[72px] w-full max-w-[1440px] items-center gap-4 px-4 sm:px-6 lg:px-10 xl:px-12 ${
          variant === 'landing'
            ? 'grid-cols-[1fr_auto] md:grid-cols-[1fr_auto_1fr]'
            : 'grid-cols-[1fr_auto]'
        }`}
      >
        {/* Kiri: logo */}
        <a href={home} className="group flex min-w-0 items-center justify-self-start gap-3">
          <img
            src={urls?.logoLockup || urls?.logoMark || `${import.meta.env.BASE_URL}branding/app-logo.png`}
            alt="SmartBrail"
            className="h-10 w-auto max-w-[min(34vw,150px)] object-contain sm:h-14 sm:max-w-[180px]"
          />
          <span
            className="hidden h-10 w-px shrink-0 bg-blue-200 sm:block"
            aria-hidden
          />
          <span className="flex min-w-0 flex-col leading-tight">
            <span className="whitespace-nowrap bg-gradient-to-r from-cyan-500 to-blue-600 bg-clip-text pr-1 text-[15px] font-extrabold italic tracking-tight text-transparent">
              SmartBrail
            </span>
            <span className="hidden truncate text-[11px] font-medium text-slate-500 sm:block">
              BRAIL · Politeknik Negeri Batam
            </span>
          </span>
        </a>

        {/* Tengah: menu (benar-benar center viewport bar) */}
        {variant === 'landing' && (
          <nav
            className="hidden items-center justify-self-center gap-1 md:flex"
            aria-label="Navigasi utama"
          >
            {LINKS.map((l) => {
              const isActive = activeSection === l.id;
              const isHome = l.id === 'beranda';
              return (
                <a
                  key={l.href}
                  href={l.href}
                  onClick={isHome ? goHome : undefined}
                  className={`relative rounded-lg px-3 py-2 text-sm font-medium transition xl:px-3.5 ${
                    isActive
                      ? 'text-blue-600'
                      : 'text-slate-600 hover:bg-white hover:text-slate-900'
                  }`}
                >
                  {l.label}
                  <span
                    className={`absolute bottom-0 left-3 right-3 h-0.5 rounded-full bg-gradient-to-r from-blue-600 to-cyan-500 transition-all duration-300 ${
                      isActive ? 'opacity-100 scale-x-100' : 'opacity-0 scale-x-0'
                    }`}
                    aria-hidden
                  />
                </a>
              );
            })}
          </nav>
        )}

        {/* Kanan: CTA + hamburger */}
        <div className="flex shrink-0 items-center justify-self-end gap-2">
          <a
            href={variant === 'download' ? home : (apkAvailable ? urls.download : urls.login)}
            className={`hidden rounded-xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-600/25 transition hover:-translate-y-0.5 hover:from-blue-500 hover:to-cyan-400 md:inline-flex${
              variant === 'landing' ? ' btn-unduh-attention' : ''
            }`}
          >
            {variant === 'download' ? 'Kembali ke Beranda' : (apkAvailable ? 'Unduh Sekarang' : 'Masuk Portal')}
          </a>

          {variant === 'landing' && (
            <button
              type="button"
              onClick={() => setMenuOpen((v) => !v)}
              className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-blue-200/80 bg-white text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-600 md:hidden"
              aria-label={menuOpen ? 'Tutup menu' : 'Buka menu'}
              aria-expanded={menuOpen}
              aria-controls="mobile-nav"
            >
              {menuOpen ? (
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
                  <path d="M18 6L6 18M6 6l12 12" strokeLinecap="round" />
                </svg>
              ) : (
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
                  <path d="M4 7h16M4 12h16M4 17h16" strokeLinecap="round" />
                </svg>
              )}
            </button>
          )}
        </div>
      </div>

      {variant === 'landing' && menuOpen && (
        <>
          <button
            type="button"
            className="fixed inset-0 z-40 bg-slate-900/25 backdrop-blur-[2px] md:hidden"
            aria-label="Tutup menu"
            onClick={closeMenu}
          />
          <div
            id="mobile-nav"
            className="absolute left-0 right-0 top-full z-50 border-b border-blue-200/60 bg-[#f0f7ff] px-5 pb-5 pt-2 shadow-xl md:hidden"
          >
            <nav className="flex flex-col gap-1" aria-label="Navigasi mobile">
              {LINKS.map((l) => {
                const isActive = activeSection === l.id;
                const isHome = l.id === 'beranda';
                return (
                  <a
                    key={l.href}
                    href={l.href}
                    onClick={(e) => {
                      if (isHome) goHome(e);
                      else closeMenu();
                    }}
                    className={`rounded-xl px-4 py-3 text-[15px] font-semibold transition ${
                      isActive
                        ? 'bg-gradient-to-r from-blue-50 to-cyan-50 text-blue-700'
                        : 'text-slate-700 hover:bg-white'
                    }`}
                  >
                    {l.label}
                  </a>
                );
              })}
              <a
                href={apkAvailable ? urls.download : urls.login}
                onClick={closeMenu}
                className="btn-unduh-attention mt-2 inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-3.5 text-sm font-bold text-white shadow-md shadow-blue-600/25"
              >
                {apkAvailable ? 'Unduh Sekarang' : 'Masuk Portal'}
              </a>
            </nav>
          </div>
        </>
      )}
    </header>
  );
}
