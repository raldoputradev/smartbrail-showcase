import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';

export default function ScrollToTop() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const section = document.getElementById('tentang');
    if (!section) return undefined;

    const update = () => {
      const rect = section.getBoundingClientRect();
      setVisible(rect.bottom < 80);
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);

    return () => {
      window.removeEventListener('scroll', update);
      window.removeEventListener('resize', update);
    };
  }, []);

  const scrollUp = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <AnimatePresence>
      {visible && (
        <motion.button
          type="button"
          initial={{ opacity: 0, y: 12, scale: 0.9 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={{ opacity: 0, y: 12, scale: 0.9 }}
          transition={{ duration: 0.2 }}
          onClick={scrollUp}
          className="fixed bottom-24 right-5 z-[150] flex h-12 w-12 items-center justify-center rounded-2xl border border-blue-200/70 bg-white text-blue-600 shadow-lg shadow-blue-900/10 transition hover:bg-cyan-50 hover:text-cyan-600"
          aria-label="Kembali ke atas"
          title="Kembali ke atas"
        >
          <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
            <path d="M12 19V5M5 12l7-7 7 7" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </motion.button>
      )}
    </AnimatePresence>
  );
}
