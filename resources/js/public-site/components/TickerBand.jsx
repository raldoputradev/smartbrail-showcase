const ITEMS = [
  { icon: '◆', text: 'Absensi biometrik ESP32-S3' },
  { icon: '◆', text: 'Akses ruangan pintar terintegrasi' },
  { icon: '◆', text: 'Aplikasi mobile mahasiswa' },
  { icon: '◆', text: 'BRAIL Polibatam' },
  { icon: '◆', text: 'HTTPS & APK terverifikasi' },
  { icon: '◆', text: 'OTP email & keamanan akun' },
  { icon: '◆', text: 'Portal dosen & laboran' },
  { icon: '◆', text: 'Notifikasi real-time' },
];

function TickerRow({ items }) {
  const track = [...items, ...items];

  return (
    <div className="overflow-hidden border-y border-rose-800/20 bg-gradient-to-r from-rose-700 via-rose-600 to-orange-600 dark:border-rose-400/10 dark:from-rose-900 dark:via-rose-800 dark:to-orange-800">
      <div className="flex w-max gap-10 py-3 animate-ticker">
        {track.map((item, i) => (
          <span
            key={`${item.text}-${i}`}
            className="inline-flex shrink-0 items-center gap-2 px-2 text-xs font-bold uppercase tracking-[0.12em] text-white sm:text-sm"
          >
            <span className="text-orange-200" aria-hidden>
              {item.icon}
            </span>
            {item.text}
          </span>
        ))}
      </div>
    </div>
  );
}

export default function TickerBand() {
  return (
    <div className="relative z-10" aria-hidden>
      <TickerRow items={ITEMS} />
    </div>
  );
}
