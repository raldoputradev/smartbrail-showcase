import { Reveal } from './Reveal';
import MapEmbed from './MapEmbed';

export default function Contact({ map }) {
  return (
    <section id="kontak" className="scroll-mt-24 bg-white py-20">
      <div className="mx-auto max-w-6xl px-5 lg:px-8">
        <Reveal>
          <div className="mx-auto max-w-3xl text-center">
            <span className="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">
              Kontak
            </span>
            <h2 className="mt-3 text-3xl font-extrabold text-slate-900 sm:text-4xl">
              Temui kami di Polibatam
            </h2>
            <p className="mt-4 text-sm leading-relaxed text-slate-600 sm:text-base">
              BRAIL — Jl. Ahmad Yani, Tlk. Tering, Kec. Batam Kota, Kota Batam, Kepulauan Riau
              29461.
            </p>
            <a
              href={map?.url || `https://www.google.com/maps/search/?api=1&query=${map?.lat ?? 1.118671},${map?.lng ?? 104.04845}`}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-4 inline-flex text-sm font-semibold text-cyan-600 hover:text-blue-600 hover:underline"
            >
              Buka di Google Maps →
            </a>
          </div>
          <MapEmbed map={map} tall />
        </Reveal>
      </div>
    </section>
  );
}
