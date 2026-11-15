import { Reveal, SectionHeader } from './Reveal';

export default function Team({ team }) {
  if (!team?.length) return null;

  return (
    <section id="tim" className="scroll-mt-24 border-t border-slate-200/80 bg-white py-20 dark:border-white/10 dark:bg-[#111827]">
      <div className="mx-auto max-w-6xl px-5 lg:px-8">
        <Reveal>
          <SectionHeader
            eyebrow="Tim"
            title="Orang-orang di balik SmartBrail"
            subtitle="Tim multidisiplin electrical, software, mobile, vision, dan komunikasi di Polibatam."
          />
        </Reveal>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {team.map((m, i) => (
            <Reveal key={m.slug} delay={i * 0.05}>
              <div className="rounded-2xl border border-slate-200/80 bg-[#f6f8fb] p-5 dark:border-white/10 dark:bg-[#1c2540]">
                <div className="mb-4 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-blue-500 to-teal-500 text-sm font-bold text-white">
                  {m.photo ? (
                    <img src={m.photo} alt={m.nama} loading="lazy" decoding="async" className="h-full w-full object-cover" />
                  ) : (
                    m.inisial
                  )}
                </div>
                <h3 className="font-bold text-slate-900 dark:text-white">{m.nama}</h3>
                {m.tags?.length > 0 ? (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {m.tags.map((tag) => (
                      <span
                        key={tag}
                        className="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700 dark:bg-blue-500/15 dark:text-sky-300"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                ) : (
                  <p className="mt-1 text-xs font-bold uppercase tracking-wide text-blue-600 dark:text-sky-400">
                    {m.role}
                  </p>
                )}
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{m.desc}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
