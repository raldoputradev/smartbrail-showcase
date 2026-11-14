import { motion, useInView, useReducedMotion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';

/** Count-up 0 → n saat refresh / masuk viewport (angka kecil naik per langkah). */
export function CountUp({ value = 0, className = '', duration = 1.6 }) {
  const reduce = useReducedMotion();
  const ref = useRef(null);
  const inView = useInView(ref, { once: true, amount: 0.4 });
  const target = Math.max(0, Math.round(Number(value) || 0));
  const [display, setDisplay] = useState(0);

  useEffect(() => {
    if (!inView) return undefined;
    if (reduce || target === 0) {
      setDisplay(target);
      return undefined;
    }

    setDisplay(0);
    let current = 0;
    let timer = 0;
    let raf = 0;

    // Angka kecil: naik 1,2,3,…n biar kelihatan hidup
    if (target <= 200) {
      const stepMs = Math.max(18, Math.min(55, (duration * 1000) / target));
      timer = window.setInterval(() => {
        current += 1;
        setDisplay(current);
        if (current >= target) window.clearInterval(timer);
      }, stepMs);
      return () => window.clearInterval(timer);
    }

    // Angka besar: interpolasi halus
    const start = performance.now();
    const dur = Math.max(0.6, duration) * 1000;
    const tick = (now) => {
      const p = Math.min(1, (now - start) / dur);
      const eased = 1 - (1 - p) ** 3;
      setDisplay(Math.round(target * eased));
      if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [inView, target, duration, reduce]);

  return (
    <span ref={ref} className={className}>
      {display.toLocaleString('id-ID')}
    </span>
  );
}

export function Reveal({ children, className = '', delay = 0 }) {
  const ref = useRef(null);
  const inView = useInView(ref, { once: true, margin: '-60px' });
  const reduce = useReducedMotion();

  if (reduce) {
    return <div className={className}>{children}</div>;
  }

  return (
    <motion.div
      ref={ref}
      initial={{ opacity: 0, y: 28, filter: 'blur(4px)' }}
      animate={inView ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}}
      transition={{ duration: 0.6, delay, ease: [0.22, 1, 0.36, 1] }}
      className={className}
    >
      {children}
    </motion.div>
  );
}

export function SectionHeader({ eyebrow, title, subtitle }) {
  return (
    <div className="mx-auto mb-12 max-w-2xl text-center">
      <span className="text-xs font-bold uppercase tracking-[0.2em] text-rose-600">
        {eyebrow}
      </span>
      <h2 className="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
        {title}
      </h2>
      {subtitle && (
        <p className="mt-4 text-base leading-relaxed text-slate-600">{subtitle}</p>
      )}
    </div>
  );
}
