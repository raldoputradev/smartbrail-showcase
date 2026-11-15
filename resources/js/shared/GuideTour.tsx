import { useCallback, useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';

export interface TourStep {
    /** Selector elemen yang disorot, mis. '[data-tour="beranda"]'. Kosong = kartu tengah. */
    target?: string;
    title: string;
    body: string;
    /** Sisi kartu yang diutamakan relatif ke target. */
    side?: 'right' | 'left' | 'top' | 'bottom' | 'center';
}

interface GuideTourProps {
    steps: TourStep[];
    open: boolean;
    /** done = true bila pengguna menekan "Selesai", false bila "Lewati"/Escape. */
    onClose: (done: boolean) => void;
}

const PAD = 8;
const CARD_W = 340;
const CARD_H = 210;

function findTarget(step: TourStep): HTMLElement | null {
    if (!step.target) return null;
    const el = document.querySelector<HTMLElement>(step.target);
    if (!el) return null;
    const r = el.getBoundingClientRect();
    // Elemen di dalam grup nav tertutup / hidden tetap ada di DOM tetapi tanpa ukuran;
    // sidebar mobile yang tergeser keluar layar pun dianggap tidak ada (langkah dilewati).
    return r.width > 0 && r.height > 0 && r.right > 0 && r.left < window.innerWidth && r.bottom > 0 && r.top < window.innerHeight ? el : null;
}

/**
 * Tur sorotan (spotlight) mandiri — menyorot elemen nyata di halaman dengan
 * kartu penjelasan berurutan. Langkah yang targetnya tidak terlihat dilewati
 * otomatis sehingga aman untuk menu dinamis / responsif.
 */
export function GuideTour({ steps, open, onClose }: GuideTourProps) {
    const [idx, setIdx] = useState(0);
    const [rect, setRect] = useState<DOMRect | null>(null);
    const [resolved, setResolved] = useState<TourStep[]>([]);

    const resolve = useCallback(
        () => steps.filter((s) => !s.target || findTarget(s)),
        [steps],
    );

    useEffect(() => {
        if (!open) return;
        setIdx(0);
        setResolved(resolve());
    }, [open, resolve]);

    useEffect(() => {
        if (!open || resolved.length === 0) return;
        const step = resolved[Math.min(idx, resolved.length - 1)];
        const measure = () => {
            const el = findTarget(step);
            if (step.target && !el) {
                // Target hilang (mis. grup nav tertutup) — lompat ke langkah valid berikutnya.
                setResolved(resolve());
                setIdx((i) => Math.min(i, resolve().length - 1));
                return;
            }
            el?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            setRect(el ? el.getBoundingClientRect() : null);
        };
        measure();
        const t = setTimeout(measure, 120);
        const onWin = () => measure();
        window.addEventListener('resize', onWin);
        window.addEventListener('scroll', onWin, true);
        return () => {
            clearTimeout(t);
            window.removeEventListener('resize', onWin);
            window.removeEventListener('scroll', onWin, true);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, idx, resolved]);

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose(false);
            if (e.key === 'ArrowRight') setIdx((i) => Math.min(i + 1, resolved.length - 1));
            if (e.key === 'ArrowLeft') setIdx((i) => Math.max(i - 1, 0));
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, resolved.length, onClose]);

    if (!open || resolved.length === 0) return null;

    const step = resolved[Math.min(idx, resolved.length - 1)];
    const isLast = idx >= resolved.length - 1;

    // Posisi kartu relatif ke sorotan — pilih sisi yang muat di viewport.
    let top = 0; let left = 0;
    const vw = window.innerWidth; const vh = window.innerHeight;
    const w = Math.min(CARD_W, vw - 24);
    if (!rect || step.side === 'center') {
        top = Math.max(16, (vh - CARD_H) / 2);
        left = (vw - w) / 2;
    } else {
        const order: TourStep['side'][] = [step.side ?? 'right', 'right', 'bottom', 'left', 'top'];
        let placed = false;
        for (const side of order) {
            if (side === 'right' && vw - rect.right - PAD > w + 16) {
                left = rect.right + PAD + 4;
                top = Math.min(Math.max(12, rect.top + rect.height / 2 - CARD_H / 2), vh - CARD_H - 12);
                placed = true; break;
            }
            if (side === 'left' && rect.left - PAD > w + 16) {
                left = rect.left - PAD - 4 - w;
                top = Math.min(Math.max(12, rect.top + rect.height / 2 - CARD_H / 2), vh - CARD_H - 12);
                placed = true; break;
            }
            if (side === 'bottom' && vh - rect.bottom - PAD > CARD_H + 12) {
                top = rect.bottom + PAD + 4;
                left = Math.min(Math.max(12, rect.left + rect.width / 2 - w / 2), vw - w - 12);
                placed = true; break;
            }
            if (side === 'top' && rect.top - PAD > CARD_H + 12) {
                top = rect.top - PAD - 4 - CARD_H;
                left = Math.min(Math.max(12, rect.left + rect.width / 2 - w / 2), vw - w - 12);
                placed = true; break;
            }
        }
        if (!placed) {
            top = Math.max(16, vh - CARD_H - 16);
            left = (vw - w) / 2;
        }
    }

    const finish = () => onClose(true);

    return (
        <div className="sbt-root" role="dialog" aria-modal="true" aria-label="Panduan penggunaan portal">
            <style>{`
                .sbt-root{position:fixed;inset:0;z-index:9999;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}
                .sbt-spot{position:fixed;border-radius:14px;pointer-events:none;transition:all .35s cubic-bezier(.4,0,.2,1);
                    box-shadow:0 0 0 9999px rgba(6,13,26,.62),0 0 0 2px rgba(56,189,248,.95),0 0 22px 4px rgba(56,189,248,.45)}
                .sbt-spot::after{content:'';position:absolute;inset:-6px;border-radius:18px;border:2px solid rgba(56,189,248,.5);animation:sbt-pulse 1.8s ease-out infinite}
                @keyframes sbt-pulse{0%{opacity:.9;transform:scale(1)}70%{opacity:0;transform:scale(1.12)}100%{opacity:0;transform:scale(1.12)}}
                .sbt-dim{position:fixed;inset:0;background:rgba(6,13,26,.62)}
                .sbt-card{position:fixed;width:${w}px;max-width:calc(100vw - 24px);background:#0f1c33;border:1px solid rgba(56,189,248,.35);
                    border-radius:16px;box-shadow:0 24px 60px -12px rgba(0,0,0,.55);padding:18px;color:#e8effc;
                    animation:sbt-in .28s cubic-bezier(.34,1.4,.64,1)}
                @keyframes sbt-in{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:none}}
                .sbt-step{font-size:11px;font-weight:700;letter-spacing:.08em;color:#38bdf8;text-transform:uppercase}
                .sbt-title{font-size:15.5px;font-weight:800;margin:6px 0 6px;line-height:1.3}
                .sbt-body{font-size:13px;line-height:1.55;color:#b8c6e0}
                .sbt-foot{display:flex;align-items:center;gap:8px;margin-top:14px}
                .sbt-skip{background:none;border:0;color:#8fa3c8;font-size:12.5px;font-weight:600;cursor:pointer;padding:6px 4px}
                .sbt-skip:hover{color:#c8d6f0}
                .sbt-nav{margin-left:auto;display:flex;gap:8px}
                .sbt-btn{display:inline-flex;align-items:center;gap:5px;border-radius:10px;font-size:13px;font-weight:700;
                    padding:8px 14px;cursor:pointer;border:0;transition:background .15s}
                .sbt-prev{background:rgba(148,163,184,.14);color:#c8d6f0}
                .sbt-prev:hover{background:rgba(148,163,184,.24)}
                .sbt-next{background:linear-gradient(135deg,#0284c7,#2563eb);color:#fff}
                .sbt-next:hover{filter:brightness(1.1)}
                .sbt-close{position:absolute;top:10px;right:10px;background:none;border:0;color:#8fa3c8;cursor:pointer;padding:4px;border-radius:8px}
                .sbt-close:hover{color:#fff;background:rgba(148,163,184,.15)}
                .sbt-dots{display:flex;gap:5px}
                .sbt-dot{width:6px;height:6px;border-radius:99px;background:rgba(148,163,184,.35)}
                .sbt-dot.on{background:#38bdf8}
            `}</style>
            {rect ? (
                <div className="sbt-spot" aria-hidden="true"
                    style={{ top: rect.top - PAD / 2, left: rect.left - PAD / 2, width: rect.width + PAD, height: rect.height + PAD }} />
            ) : (
                <div className="sbt-dim" aria-hidden="true" />
            )}
            <div className="sbt-card" style={{ top, left }}>
                <button type="button" className="sbt-close" aria-label="Tutup panduan" onClick={() => onClose(false)}><X size={16} /></button>
                <div className="sbt-step">Langkah {idx + 1} dari {resolved.length}</div>
                <div className="sbt-title">{step.title}</div>
                <div className="sbt-body">{step.body}</div>
                <div className="sbt-foot">
                    <button type="button" className="sbt-skip" onClick={() => onClose(false)}>Lewati</button>
                    <div className="sbt-nav">
                        {idx > 0 && (
                            <button type="button" className="sbt-btn sbt-prev" onClick={() => setIdx(idx - 1)}>
                                <ChevronLeft size={15} /> Kembali
                            </button>
                        )}
                        <button type="button" className="sbt-btn sbt-next" onClick={() => (isLast ? finish() : setIdx(idx + 1))}>
                            {isLast ? 'Selesai' : 'Berikutnya'} <ChevronRight size={15} />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Tombol "?" pembuka panduan — dipasang di topbar masing-masing shell.
 * Menerima className dari shell agar gayanya konsisten dengan tombol sekitarnya.
 */
export function TourHelpButton({ onClick, className }: { onClick: () => void; className?: string }) {
    return (
        <button type="button" data-tour="help" onClick={onClick}
            aria-label="Buka panduan penggunaan" title="Panduan penggunaan"
            className={className}>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2"
                strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                <path d="M12 17h.01" />
            </svg>
        </button>
    );
}
