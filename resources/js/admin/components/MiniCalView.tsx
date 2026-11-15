import { useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { MiniCal } from '@admin/types';

const MCAL_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const MCAL_TONE: Record<string, string> = {
    libur: 'libur', peringatan: 'peringatan', ujian: 'ujian',
    perubahan_jam: 'khusus', agenda: 'info', informasi: 'info',
};
const MCAL_PRIO = ['libur', 'peringatan', 'ujian', 'perubahan_jam', 'agenda', 'informasi'];

export function shiftMonth(bulan: string, dir: number): string {
    const [y, m] = bulan.split('-').map(Number);
    const d = new Date(y, m - 1 + dir, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function MiniCalView({ cal, onNav, busy }: { cal: MiniCal; onNav?: (bulan: string) => void; busy?: boolean }) {
    const [sel, setSel] = useState<string | null>(cal.days.find((d) => d.today)?.tanggal ?? null);
    const [pickOpen, setPickOpen] = useState(false);
    const [pickYear, setPickYear] = useState<number | null>(null);
    const selDay = cal.days.find((d) => d.tanggal === sel);
    const tglLabel = (t: string) => `${Number(t.slice(8, 10))} ${cal.bulan_label}`;
    const [navYear, navMonth] = (cal.bulan ?? '').split('-').map(Number);
    const year = pickYear ?? navYear;

    return (
        <div className="ad-mcal">
            <div className="ad-mcal__nav">
                <button type="button" className="ad-icon-btn" disabled={busy || !onNav}
                    onClick={() => onNav?.(shiftMonth(cal.bulan, -1))} aria-label="Bulan sebelumnya">
                    <ChevronLeft size={14} />
                </button>
                <button type="button" disabled={!onNav} aria-expanded={pickOpen}
                    className={`ad-mcal__month ad-mcal__monthbtn${busy ? ' ad-mcal__monthbtn--busy' : ''}`}
                    onClick={() => { setPickYear(navYear); setPickOpen((o) => !o); }}>
                    {cal.bulan_label}
                </button>
                <button type="button" className="ad-icon-btn" disabled={busy || !onNav}
                    onClick={() => onNav?.(shiftMonth(cal.bulan, +1))} aria-label="Bulan berikutnya">
                    <ChevronRight size={14} />
                </button>
            </div>
            {pickOpen && onNav && (
                <div className="ad-mcal__pick">
                    <div className="ad-mcal__pick-year">
                        <button type="button" className="ad-icon-btn" onClick={() => setPickYear(year - 1)} aria-label="Tahun sebelumnya"><ChevronLeft size={13} /></button>
                        <strong>{year}</strong>
                        <button type="button" className="ad-icon-btn" onClick={() => setPickYear(year + 1)} aria-label="Tahun berikutnya"><ChevronRight size={13} /></button>
                    </div>
                    <div className="ad-mcal__pick-grid">
                        {MCAL_MONTHS.map((name, i) => (
                            <button key={name} type="button"
                                className={`ad-mcal__pick-m${year === navYear && i + 1 === navMonth ? ' ad-mcal__pick-m--on' : ''}`}
                                onClick={() => { setPickOpen(false); onNav(`${year}-${String(i + 1).padStart(2, '0')}`); }}>
                                {name}
                            </button>
                        ))}
                    </div>
                </div>
            )}
            <div className="ad-mcal__grid">
                {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((d) => (
                    <span key={d} className="ad-mcal__dow">{d}</span>
                ))}
                {Array.from({ length: cal.offset }).map((_, i) => <span key={`e${i}`} />)}
                {cal.days.map((d) => {
                    const tone = MCAL_PRIO.find((j) => d.agenda.some((a) => a.jenis === j));
                    return (
                        <button key={d.tanggal} type="button" onClick={() => setSel(d.tanggal)}
                            aria-pressed={sel === d.tanggal}
                            title={d.agenda.map((a) => a.keterangan).filter(Boolean).join(' · ') || tglLabel(d.tanggal)}
                            className={`ad-mcal__day ${tone ? `ad-mcal__day--${MCAL_TONE[tone]}` : ''} ${d.today ? 'ad-mcal__day--today' : ''} ${sel === d.tanggal ? 'ad-mcal__day--sel' : ''}`}>
                            {d.d}
                            {d.agenda.length > 0 && (
                                <span className="ad-mcal__dots">
                                    {d.agenda.slice(0, 3).map((a, i) => <i key={i} className={`ad-mcal__dot ad-mcal__dot--${MCAL_TONE[a.jenis] ?? 'info'}`} />)}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
            {selDay && (
                <div className="ad-mcal__info">
                    {selDay.agenda.length === 0 && (
                        <span className="ad-mcal__info-empty">Tidak ada agenda pada {tglLabel(selDay.tanggal)}.</span>
                    )}
                    {selDay.agenda.map((a, i) => (
                        <div key={i} className="ad-mcal__row">
                            <i className={`ad-mcal__dot ad-mcal__dot--${MCAL_TONE[a.jenis] ?? 'info'}`} />
                            <span>{a.keterangan}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
