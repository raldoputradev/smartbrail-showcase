function CheckIcon() {
  return (
    <svg viewBox="0 0 20 20" className="h-4 w-4 shrink-0" fill="none" aria-hidden>
      <circle cx="10" cy="10" r="10" className="fill-blue-500/15 dark:fill-blue-400/20" />
      <path
        d="M6 10.2l2.4 2.4L14 7.2"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="text-blue-600 dark:text-blue-400"
      />
    </svg>
  );
}

const BADGES = [
  { label: 'Situs Resmi SmartBrail', sub: 'BRAIL Polibatam' },
  { label: 'Koneksi HTTPS Aman', sub: 'Terenkripsi SSL/TLS' },
  { label: 'APK Terverifikasi Server', sub: 'Unduhan langsung dari VPS resmi' },
  { label: 'Verifikasi OTP Email', sub: 'Keamanan akun mahasiswa' },
  { label: 'Proyek Polibatam', sub: 'BRAIL · Terakreditasi' },
];

export default function TrustBadges({ compact = false }) {
  if (compact) {
    return (
      <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
        {BADGES.slice(0, 3).map((b) => (
          <span
            key={b.label}
            className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 transition hover:text-blue-700 dark:text-slate-300"
          >
            <CheckIcon />
            {b.label}
          </span>
        ))}
      </div>
    );
  }

  return (
    <div className="rounded-2xl border border-blue-200/80 bg-blue-50/60 p-4 dark:border-blue-500/20 dark:bg-blue-500/5 sm:p-5">
      <div className="mb-3 flex items-center gap-2">
        <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-cyan-500 text-white">
          <svg viewBox="0 0 20 20" className="h-4 w-4" fill="currentColor" aria-hidden>
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clipRule="evenodd"
            />
          </svg>
        </span>
        <div>
          <p className="text-sm font-bold text-blue-900 dark:text-blue-100">Terverifikasi & Terpercaya</p>
          <p className="text-xs text-blue-700/80 dark:text-blue-300/70">
            Platform resmi laboratorium BRAIL Polibatam
          </p>
        </div>
      </div>
      <ul className="grid gap-2 sm:grid-cols-2">
        {BADGES.map((b) => (
          <li
            key={b.label}
            className="flex items-start gap-2 rounded-xl bg-white/70 px-3 py-2 dark:bg-white/5"
          >
            <CheckIcon />
            <span>
              <span className="block text-xs font-semibold text-slate-800 dark:text-slate-100">
                {b.label}
              </span>
              <span className="text-[11px] text-slate-500 dark:text-slate-400">{b.sub}</span>
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
