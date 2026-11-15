import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { useEffect, useMemo, useState } from 'react';

const WORDS = [
  'SmartBrail',
  'Absensi Biometrik',
  'Akses Ruangan Pintar',
  'Manajemen PBL',
  'Portal Multi-Peran',
];

const HOLD_MS = 3200;
const STAGGER = 0.05;
const FLIP_EASE = [0.65, 0, 0.35, 1];

function pickMode(cycle) {
  // Mostly letter-by-letter; sometimes reverse or all-at-once
  const bag = ['wave', 'wave', 'wave', 'reverse', 'sync', 'wave', 'sync', 'reverse'];
  return bag[cycle % bag.length];
}

const letterVariants = {
  hidden: { rotateX: 90, opacity: 0 },
  visible: {
    rotateX: 0,
    opacity: 1,
    transition: { duration: 0.52, ease: FLIP_EASE },
  },
  exit: {
    rotateX: -90,
    opacity: 0,
    transition: { duration: 0.36, ease: FLIP_EASE },
  },
};

function containerVariants(mode) {
  const stagger = mode === 'sync' ? 0 : STAGGER;
  const dir = mode === 'reverse' ? -1 : 1;
  return {
    hidden: {},
    visible: {
      transition: { staggerChildren: stagger, staggerDirection: dir },
    },
    exit: {
      transition: {
        staggerChildren: stagger * 0.8,
        staggerDirection: dir * -1,
        when: 'afterChildren',
      },
    },
  };
}

function FlipChars({ word, mode }) {
  const chars = useMemo(() => Array.from(word), [word]);
  const variants = useMemo(() => containerVariants(mode), [mode]);

  return (
    <motion.span
      className="absolute left-0 top-0 inline-flex whitespace-nowrap"
      style={{ transformStyle: 'preserve-3d' }}
      variants={variants}
      initial="hidden"
      animate="visible"
      exit="exit"
      aria-hidden="true"
    >
      {chars.map((ch, i) => (
        <motion.span
          key={`${i}-${ch === ' ' ? 'sp' : ch}`}
          variants={letterVariants}
          className="inline-block bg-gradient-to-r from-blue-600 via-blue-500 to-cyan-500 bg-clip-text font-extrabold text-transparent"
          style={{
            transformOrigin: '50% 58%',
            transformStyle: 'preserve-3d',
            backfaceVisibility: 'hidden',
            width: ch === ' ' ? '0.28em' : undefined,
          }}
        >
          {ch === ' ' ? '\u00a0' : ch}
        </motion.span>
      ))}
    </motion.span>
  );
}

export default function FlipWords() {
  const reduceMotion = useReducedMotion();
  const [index, setIndex] = useState(0);
  const [cycle, setCycle] = useState(0);
  const mode = pickMode(cycle);
  const word = WORDS[index];

  const reserve = useMemo(
    () => WORDS.reduce((a, b) => (b.length > a.length ? b : a), WORDS[0]),
    [],
  );

  useEffect(() => {
    if (reduceMotion) return undefined;
    const id = setInterval(() => {
      setIndex((i) => (i + 1) % WORDS.length);
      setCycle((c) => c + 1);
    }, HOLD_MS);
    return () => clearInterval(id);
  }, [reduceMotion]);

  if (reduceMotion) {
    return (
      <span className="mt-1 block bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-transparent">
        {WORDS[0]}
      </span>
    );
  }

  return (
    <span
      className="relative mt-1.5 block min-h-[1.22em]"
      style={{ perspective: '1000px' }}
      aria-live="polite"
      aria-atomic="true"
    >
      <span className="invisible block whitespace-nowrap font-extrabold" aria-hidden="true">
        {reserve}
      </span>
      <span className="sr-only">{word}</span>
      <AnimatePresence mode="wait" initial={false}>
        <FlipChars key={word} word={word} mode={mode} />
      </AnimatePresence>
    </span>
  );
}
