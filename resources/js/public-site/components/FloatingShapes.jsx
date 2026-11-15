import { motion, useMotionValue, useReducedMotion } from 'framer-motion';
import { useRef, useState } from 'react';

function DraggableShape({
  className = '',
  constraintsRef,
  idle,
  children,
}) {
  const [dragging, setDragging] = useState(false);
  const rotate = useMotionValue(0);

  return (
    <motion.div
      drag
      dragConstraints={constraintsRef}
      dragMomentum
      dragElastic={0.2}
      dragTransition={{ bounceStiffness: 240, bounceDamping: 20 }}
      onDragStart={() => setDragging(true)}
      onDrag={(_e, info) => {
        rotate.set(rotate.get() + info.delta.x * 0.5 + info.delta.y * 0.22);
      }}
      onDragEnd={() => setDragging(false)}
      style={{ rotate, cursor: 'grab', pointerEvents: 'auto', touchAction: 'none' }}
      className={`absolute select-none will-change-transform ${className}`}
      whileHover={{ scale: 1.1 }}
      whileDrag={{ scale: 1.16, cursor: 'grabbing', zIndex: 40 }}
      animate={dragging ? false : idle?.animate}
      transition={dragging ? { type: 'spring', stiffness: 320, damping: 24 } : idle?.transition}
    >
      {children}
    </motion.div>
  );
}

/**
 * Shape interaktif: idle float/putar + bisa digrab & diputar dengan cursor.
 * @param {'full' | 'sparse'} variant
 */
export default function FloatingShapes({ className = '', variant = 'full' }) {
  const reduce = useReducedMotion();
  const constraintsRef = useRef(null);
  if (reduce) return null;

  const sparse = variant === 'sparse';

  return (
    <div
      ref={constraintsRef}
      className={`pointer-events-none absolute inset-0 overflow-hidden ${className}`}
    >
      <DraggableShape
        constraintsRef={constraintsRef}
        className={`rounded-full border-2 border-blue-400/55 bg-white/40 shadow-sm ${
          sparse ? '-right-6 top-10 h-24 w-24' : 'left-[4%] top-[16%] h-36 w-36'
        }`}
        idle={{
          animate: { rotate: 360, y: [0, 14, 0] },
          transition: {
            rotate: { duration: 28, repeat: Infinity, ease: 'linear' },
            y: { duration: 7, repeat: Infinity, ease: 'easeInOut' },
          },
        }}
      >
        <span className="pointer-events-none absolute inset-3 rounded-full border border-dashed border-cyan-400/45" />
      </DraggableShape>

      {!sparse && (
        <DraggableShape
          constraintsRef={constraintsRef}
          className="left-[10%] top-[28%] h-20 w-20 rounded-full border border-dashed border-cyan-400/65 bg-cyan-50/50"
          idle={{
            animate: { rotate: -360 },
            transition: { duration: 20, repeat: Infinity, ease: 'linear' },
          }}
        />
      )}

      <DraggableShape
        constraintsRef={constraintsRef}
        className={`rounded-2xl border-2 border-blue-400/55 bg-gradient-to-br from-blue-100/80 to-cyan-100/60 shadow-sm ${
          sparse ? 'right-[8%] bottom-12 h-14 w-14' : 'right-[10%] top-[12%] h-16 w-16'
        }`}
        idle={{
          animate: { rotate: [8, -10, 8], y: [0, -14, 0] },
          transition: { duration: 8, repeat: Infinity, ease: 'easeInOut' },
        }}
      />

      <DraggableShape
        constraintsRef={constraintsRef}
        className={`rounded-xl bg-gradient-to-br from-cyan-500/75 to-blue-500/70 shadow-md ${
          sparse ? 'left-[10%] top-14 h-8 w-8' : 'right-[22%] top-[30%] h-10 w-10'
        }`}
        idle={{
          animate: { rotate: [0, 180, 360], y: [0, 12, 0] },
          transition: {
            rotate: { duration: 14, repeat: Infinity, ease: 'linear' },
            y: { duration: 6, repeat: Infinity, ease: 'easeInOut' },
          },
        }}
      />

      {!sparse && (
        <>
          <DraggableShape
            constraintsRef={constraintsRef}
            className="bottom-[14%] right-[6%] h-28 w-28 rounded-full border-2 border-cyan-400/55 bg-white/30"
            idle={{
              animate: { rotate: -360, scale: [1, 1.04, 1] },
              transition: {
                rotate: { duration: 32, repeat: Infinity, ease: 'linear' },
                scale: { duration: 6, repeat: Infinity, ease: 'easeInOut' },
              },
            }}
          />
          <DraggableShape
            constraintsRef={constraintsRef}
            className="bottom-[26%] left-[18%] h-0 w-0 border-l-[20px] border-r-[20px] border-b-[34px] border-l-transparent border-r-transparent border-b-blue-400/60"
            idle={{
              animate: { rotate: [0, 18, -12, 0], y: [0, -12, 0] },
              transition: { duration: 9, repeat: Infinity, ease: 'easeInOut' },
            }}
          />
        </>
      )}

      <DraggableShape
        constraintsRef={constraintsRef}
        className="left-[44%] top-[10%] h-3.5 w-3.5 rounded-full bg-blue-500 shadow"
        idle={{
          animate: { y: [0, -10, 0], opacity: [0.55, 1, 0.55] },
          transition: { duration: 4, repeat: Infinity, ease: 'easeInOut' },
        }}
      />
      <DraggableShape
        constraintsRef={constraintsRef}
        className="left-[50%] top-[18%] h-2.5 w-2.5 rounded-full bg-cyan-500 shadow"
        idle={{
          animate: { y: [0, 8, 0], opacity: [0.45, 1, 0.45] },
          transition: { duration: 3.6, repeat: Infinity, ease: 'easeInOut', delay: 0.4 },
        }}
      />
    </div>
  );
}
