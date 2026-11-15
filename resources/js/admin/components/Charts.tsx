import { useMemo, useState } from 'react';
import type { DonutData, LineChartData } from '@admin/types';

/* Line chart multi-seri ringan — SVG, responsif via viewBox. */
export function LineChart({ data, height = 230 }: { data: LineChartData; height?: number }) {
    const W = 640, H = 200, PAD_L = 34, PAD_R = 10, PAD_T = 12, PAD_B = 26;
    const innerW = W - PAD_L - PAD_R, innerH = H - PAD_T - PAD_B;
    const [hover, setHover] = useState<number | null>(null);

    const { max, paths, yTicks } = useMemo(() => {
        const all = data.series.flatMap((s) => s.data);
        const maxV = Math.max(1, ...all);
        const nice = Math.max(1, Math.ceil(maxV));
        const n = Math.max(1, data.labels.length - 1);
        const paths = data.series.map((s) => {
            const pts = s.data.map((v, i) => {
                const x = PAD_L + (i / n) * innerW;
                const y = PAD_T + innerH - (v / nice) * innerH;
                return [x, y] as const;
            });
            const d = pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
            const area = `${d} L${pts[pts.length - 1][0].toFixed(1)},${PAD_T + innerH} L${pts[0][0].toFixed(1)},${PAD_T + innerH} Z`;
            return { pts, d, area };
        });
        return { max: nice, paths, yTicks: [0, Math.round(nice / 2), nice] };
    }, [data, innerW, innerH]);

    if (!data.labels.length) {
        return <div className="ad-empty">Belum ada data aktivitas.</div>;
    }

    return (
        <div style={{ position: 'relative' }}>
            <svg
                viewBox={`0 0 ${W} ${H}`} style={{ width: '100%', height }}
                onMouseLeave={() => setHover(null)}
                role="img" aria-label="Grafik tren aktivitas"
            >
                {yTicks.map((t) => {
                    const y = PAD_T + innerH - (t / max) * innerH;
                    return (
                        <g key={t}>
                            <line x1={PAD_L} x2={W - PAD_R} y1={y} y2={y} stroke="var(--ad-border)" strokeWidth="1" />
                            <text x={PAD_L - 6} y={y + 4} textAnchor="end" fontSize="10" fill="var(--ad-muted)">{t}</text>
                        </g>
                    );
                })}
                {data.labels.map((l, i) => {
                    const x = PAD_L + (i / Math.max(1, data.labels.length - 1)) * innerW;
                    return <text key={i} x={x} y={H - 8} textAnchor="middle" fontSize="10" fill="var(--ad-muted)">{l}</text>;
                })}
                {paths.map((p, si) => (
                    <g key={si}>
                        <path d={p.area} fill={data.series[si].color} opacity="0.12" />
                        <path d={p.d} fill="none" stroke={data.series[si].color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                        {p.pts.map((pt, i) => (
                            <circle
                                key={i} cx={pt[0]} cy={pt[1]} r={hover === i ? 4 : 2.5}
                                fill={data.series[si].color}
                            />
                        ))}
                    </g>
                ))}
                {/* kolom hover */}
                {data.labels.map((_, i) => {
                    const n = Math.max(1, data.labels.length - 1);
                    const x = PAD_L + (i / n) * innerW;
                    const w = innerW / Math.max(1, data.labels.length);
                    return (
                        <rect
                            key={i} x={x - w / 2} y={PAD_T} width={w} height={innerH}
                            fill="transparent" onMouseEnter={() => setHover(i)}
                        />
                    );
                })}
            </svg>
            <div style={{ display: 'flex', gap: 14, justifyContent: 'center', marginTop: 6, flexWrap: 'wrap' }}>
                {data.series.map((s) => (
                    <span key={s.key} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 11, color: 'var(--ad-muted)', fontWeight: 600 }}>
                        <span style={{ width: 8, height: 8, borderRadius: 999, background: s.color }} />
                        {s.label}
                    </span>
                ))}
            </div>
            {hover !== null && (
                <div
                    style={{
                        position: 'absolute', top: 8, right: 16,
                        background: 'var(--ad-card)', border: '1px solid var(--ad-border)',
                        borderRadius: 8, padding: '6px 10px', fontSize: 11, boxShadow: '0 4px 14px rgba(0,0,0,.12)',
                    }}
                >
                    <strong>{data.labels[hover]}</strong>
                    {data.series.map((s) => (
                        <div key={s.key} style={{ color: s.color, fontVariantNumeric: 'tabular-nums' }}>
                            {s.label}: {s.data[hover] ?? 0}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/* Donut chart dengan pusat total + legenda di bawah (render di parent). */
export function DonutChart({ data, size = 170 }: { data: DonutData; size?: number }) {
    const R = 42, C = 2 * Math.PI * R;
    let acc = 0;
    const total = Math.max(1, data.total);
    return (
        <div style={{ position: 'relative', width: size, height: size, margin: '0 auto' }}>
            <svg viewBox="0 0 100 100" style={{ width: '100%', height: '100%' }}>
                <circle cx="50" cy="50" r={R} fill="none" stroke="var(--ad-border)" strokeWidth="12" />
                {data.values.map((v, i) => {
                    const frac = v / total;
                    const dash = `${(frac * C).toFixed(2)} ${C.toFixed(2)}`;
                    const off = -acc * C;
                    acc += frac;
                    return (
                        <circle
                            key={i} cx="50" cy="50" r={R} fill="none"
                            stroke={data.colors[i] ?? '#94a3b8'} strokeWidth="12"
                            strokeDasharray={dash} strokeDashoffset={off.toFixed(2)}
                            transform="rotate(-90 50 50)"
                        />
                    );
                })}
            </svg>
            <div style={{
                position: 'absolute', inset: 0, display: 'grid', placeContent: 'center',
                textAlign: 'center', pointerEvents: 'none',
            }}>
                <strong style={{ display: 'block', fontSize: 24, letterSpacing: '-0.03em' }}>
                    {data.total.toLocaleString('id-ID')}
                </strong>
                <small style={{ display: 'block', marginTop: 3, fontSize: 10.5, fontWeight: 700, color: 'var(--ad-muted)' }}>
                    {data.center_label ?? 'Pengguna'}
                </small>
            </div>
        </div>
    );
}

export function ChartSkeleton({ height = 230 }: { height?: number }) {
    return <div className="ad-skel" style={{ height, width: '100%' }} />;
}
