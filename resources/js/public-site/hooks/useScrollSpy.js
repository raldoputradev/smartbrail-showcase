import { useEffect, useState } from 'react';

/**
 * Mengembalikan id section yang sedang terlihat di viewport (untuk nav aktif).
 * Jika scroll di dekat atas, paksa section pertama (biasanya beranda/Home).
 */
export function useScrollSpy(sectionIds, rootMargin = '-40% 0px -50% 0px') {
  const ids = sectionIds.map((s) => s.replace('#', ''));
  const [active, setActive] = useState(ids[0] || '');

  useEffect(() => {
    const elements = ids
      .map((id) => document.getElementById(id))
      .filter(Boolean);

    if (!elements.length) return undefined;

    const visible = new Map(ids.map((id) => [id, false]));
    const TOP_EPS = 120;

    const resolveActive = () => {
      if (window.scrollY <= TOP_EPS && ids[0]) {
        setActive(ids[0]);
        return;
      }
      const current = ids.find((id) => visible.get(id));
      if (current) setActive(current);
    };

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          visible.set(entry.target.id, entry.isIntersecting);
        });
        resolveActive();
      },
      { rootMargin, threshold: 0 },
    );

    elements.forEach((el) => observer.observe(el));

    const onScroll = () => resolveActive();
    window.addEventListener('scroll', onScroll, { passive: true });
    resolveActive();

    return () => {
      observer.disconnect();
      window.removeEventListener('scroll', onScroll);
    };
    // sectionIds joined — stable for typical static nav lists
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sectionIds.join('|'), rootMargin]);

  return active;
}
