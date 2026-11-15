const ITEMS = [
  { icon: '◆', text: 'Absensi biometrik ESP32' },
  { icon: '◆', text: 'Akses ruangan berbasis izin' },
  { icon: '◆', text: 'Manajemen tim PBL' },
  { icon: '◆', text: 'BRAIL Polibatam' },
  { icon: '◆', text: 'Portal admin · laboran · dosen · mahasiswa' },
  { icon: '◆', text: 'Notifikasi real-time' },
  { icon: '◆', text: 'Asisten AI bawaan' },
  { icon: '◆', text: 'Audit akses tercatat' },
];

function TickerRow({ items }) {
  const track = [...items, ...items];

  return (
    <div className="overflow-hidden border-y border-blue-800/20 bg-gradient-to-r from-blue-700 via-blue-600 to-cyan-600 dark:border-blue-400/10 dark:from-blue-900 dark:via-blue-800 dark:to-cyan-800">
      <div className="flex w-max gap-10 py-3 animate-ticker">
        {track.map((item, i) => (
          <span
            key={`${item.text}-${i}`}
            className="inline-flex shrink-0 items-center gap-2 px-2 text-xs font-bold uppercase tracking-[0.12em] text-white sm:text-sm"
          >
            <span className="text-cyan-200" aria-hidden>
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
