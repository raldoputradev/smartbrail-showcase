import { useEffect, useState } from 'react';
import { History, ShieldCheck, UserRound, X } from 'lucide-react';

function Avatar({ nama, foto, size = 34 }: { nama?: string | null; foto?: string | null; size?: number }) {
    const initials = (nama ?? '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    return foto
        ? <img src={foto} alt="" width={size} height={size} style={{ borderRadius: '50%', objectFit: 'cover', flexShrink: 0 }} />
        : <span className="ad-pbl-ava" style={{ width: size, height: size, fontSize: size * 0.36, flexShrink: 0 }}>{initials}</span>;
}

interface RingkasProfil {
    nama: string | null;
    identitas: string | null;
    role: string;
    kelas: string | null;
    prodi: string | null;
    semester: number | null;
    tim: string | null;
    shift: string | null;
    foto: string | null;
    status: string;
    terdaftar: string | null;
    online?: boolean;
    last_seen?: string | null;
}

interface LogItem {
    id: number;
    aksi: string;
    ringkasan: string;
    actor: string;
    actor_role: string | null;
    sebelum: Record<string, unknown> | null;
    sesudah: Record<string, unknown> | null;
    waktu: string | null;
}

const AKSI_LABEL: Record<string, string> = {
    buat_proyek: 'Proyek dibuat',
    ubah_proyek: 'Proyek diubah',
    hapus_proyek: 'Proyek dihapus',
    ubah_pengampu: 'Pengampu diubah',
    ubah_media: 'Tampilan diubah',
    tambah_anggota: 'Anggota masuk',
    keluarkan_anggota: 'Anggota keluar',
    set_ketua: 'Ketua ditetapkan',
    set_wakil: 'Wakil ditetapkan',
    clear_role: 'Peran dikosongkan',
};

/** Popup profil ringkas saat nama anggota/dosen diklik — data non-sensitif. */
export function MemberProfilePopup({ userId, onClose }: { userId: number; onClose: () => void }) {
    const [profil, setProfil] = useState<RingkasProfil | null>(null);
    const [gagal, setGagal] = useState(false);

    useEffect(() => {
        let alive = true;
        fetch(route('profiles.ringkas', userId), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((j) => { if (alive) setProfil(j.profil); })
            .catch(() => { if (alive) setGagal(true); });
        return () => { alive = false; };
    }, [userId]);

    const fields: [string, string][] = profil ? [
        ['Identitas', profil.identitas ?? '—'],
        ['Peran', profil.role],
        ['Kelas', profil.kelas ?? '—'],
        ['Program Studi', profil.prodi ?? '—'],
        ['Semester', profil.semester ? String(profil.semester) : '—'],
        ['Tim PBL', profil.tim ?? '—'],
        ['Shift', profil.shift ? profil.shift[0].toUpperCase() + profil.shift.slice(1) : '—'],
        ['Status', profil.status],
        ['Terdaftar', profil.terdaftar ?? '—'],
    ] : [];

    return (
        <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="ad-mesin-confirm ad-profpop">
                <button type="button" className="ad-icon-btn ad-usr-x" onClick={onClose}><X size={15} /></button>
                {!profil && !gagal && <div className="ad-empty">Memuat profil…</div>}
                {gagal && <div className="ad-empty">Profil tidak dapat ditampilkan.</div>}
                {profil && (
                    <>
                        <div className="ad-profpop__head">
                            <span style={{ position: 'relative', display: 'inline-flex' }}>
                                <Avatar nama={profil.nama} foto={profil.foto} size={52} />
                                {profil.online === true && <span className="ad-usr-online-dot" style={{ position: 'absolute', right: 0, bottom: 2 }} title="Online" />}
                            </span>
                            <div>
                                <strong className="ad-profpop__nama">{profil.nama ?? '—'}</strong>
                                <span className="ad-profpop__role"><UserRound size={12} /> {profil.role}</span>
                                {profil.online !== undefined && (
                                    <span className="ad-profpop__role" style={{ color: profil.online ? '#34d399' : undefined }}>
                                        {profil.online ? '● Online sekarang' : (profil.last_seen ? `Aktif ${profil.last_seen}` : 'Belum pernah aktif')}
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="ad-profpop__grid">
                            {fields.map(([k, v]) => (
                                <div key={k} className="ad-profpop__item">
                                    <span>{k}</span>
                                    <strong>{v}</strong>
                                </div>
                            ))}
                        </div>
                        <div className="ad-profpop__note">
                            <ShieldCheck size={13} /> Data dibatasi — informasi sensitif tidak ditampilkan.
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

/** Daftar riwayat aktivitas proyek — diambil saat dibuka, append-only di server. */
export function ActivityTimeline({ url }: { url: string }) {
    const [logs, setLogs] = useState<LogItem[] | null>(null);
    const [gagal, setGagal] = useState(false);

    useEffect(() => {
        let alive = true;
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((j) => { if (alive) setLogs(j.logs); })
            .catch(() => { if (alive) setGagal(true); });
        return () => { alive = false; };
    }, [url]);

    if (gagal) return <div className="ad-empty" style={{ padding: '10px 14px' }}>Riwayat belum dapat dimuat.</div>;
    if (!logs) return <div className="ad-empty" style={{ padding: '10px 14px' }}>Memuat riwayat…</div>;
    if (logs.length === 0) return <div className="ad-empty" style={{ padding: '10px 14px' }}>Belum ada aktivitas tercatat.</div>;

    return (
        <div className="ad-pbl-log">
            {logs.map((l) => (
                <div key={l.id} className="ad-pbl-log__row">
                    <span className="ad-pbl-log__dot" />
                    <div className="ad-pbl-log__body">
                        <div className="ad-pbl-log__top">
                            <span className="ad-pbl-log__aksi">{AKSI_LABEL[l.aksi] ?? l.aksi}</span>
                            <span className="ad-pbl-log__waktu">{l.waktu ?? ''}</span>
                        </div>
                        <div className="ad-pbl-log__ring">{l.ringkasan}</div>
                        <div className="ad-pbl-log__actor">oleh {l.actor}{l.actor_role ? ` · ${l.actor_role}` : ''}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Judul seksi riwayat dengan ikon — dipakai kedua halaman PBL. */
export function ActivityTitle() {
    return <div className="ad-pbl-detail__sec"><History size={14} /> Riwayat Aktivitas</div>;
}
