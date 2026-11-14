import { Reveal } from './Reveal';

export default function MapEmbed({ map, tall = false }) {
  if (!map) return null;

  const { lat, lng, zoom = 15, label, url } = map;
  const src = `https://maps.google.com/maps?q=${lat},${lng}&z=${zoom}&hl=id&output=embed`;
  const mapsHref =
    url || `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;

  return (
    <div className="mt-8 overflow-hidden rounded-2xl border border-rose-200/60 bg-slate-100 shadow-sm">
      <iframe
        title={label || 'Lokasi BRAIL Polibatam'}
        src={src}
        className={`block w-full border-0 ${tall ? 'h-[360px] sm:h-[440px] lg:h-[480px]' : 'h-[280px] sm:h-[320px]'}`}
        loading="lazy"
        referrerPolicy="no-referrer-when-downgrade"
        allowFullScreen
      />
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rose-100 bg-white px-4 py-3 sm:px-5">
        <p className="text-xs text-slate-600 sm:text-sm">
          <span className="font-semibold text-slate-900">{label}</span>
          <br />
          {lat.toFixed(5)}° LU, {lng.toFixed(5)}° BT
        </p>
        <a
          href={mapsHref}
          target="_blank"
          rel="noopener noreferrer"
          className="rounded-lg bg-gradient-to-r from-rose-600 to-orange-500 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:from-rose-500 hover:to-orange-400"
        >
          Buka Maps
        </a>
      </div>
    </div>
  );
}
