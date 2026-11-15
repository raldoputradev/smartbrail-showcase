export const TZ = 'Asia/Jakarta';

/** "2026-11-11" -> "Selasa, 11 November 2026" */
export function tanggalHuman(iso?: string | null, opts: Intl.DateTimeFormatOptions = {}): string {
    if (!iso) return '—';
    const d = new Date(`${iso.slice(0, 10)}T00:00:00`);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', ...opts,
    }).format(d);
}

export function tanggalSingkat(iso?: string | null): string {
    if (!iso) return '—';
    const d = new Date(`${iso.slice(0, 10)}T00:00:00`);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

/** "09:00" + menit -> "11:00" (24 jam, tidak menyeberang hari). */
export function tambahMenit(hhmm: string, menit: number): string | null {
    const m = /^(\d{2}):(\d{2})/.exec(hhmm ?? '');
    if (!m) return null;
    const total = Number(m[1]) * 60 + Number(m[2]) + menit;
    if (total >= 24 * 60) return null;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

export function hhmm(v?: string | null): string {
    return (v ?? '').slice(0, 5) || '—';
}

export function initials(nama?: string | null): string {
    const parts = (nama ?? '?').trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? parts[0]?.[1] ?? '')).toUpperCase();
}
