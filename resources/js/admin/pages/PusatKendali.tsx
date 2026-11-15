import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle, CalendarCheck, Cpu, DoorOpen, ListTodo,
    RefreshCw, Users, ClipboardList,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { PusatKendaliPayload, PusatKendaliProps } from '@admin/types';

const REFRESH_MS = 15_000;

const ROLE_LABEL: Record<string, string> = {
    admin: 'Admin', laboran: 'Laboran', dosen: 'Dosen', mahasiswa: 'Mahasiswa',
};

function initials(nama: string): string {
    return nama.split(' ').map((w) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

export default function PusatKendali(props: PusatKendaliProps) {
    const [data, setData] = useState<PusatKendaliPayload>({
        kpi: props.kpi, online_users: props.online_users, mesin_down: props.mesin_down,
        pending_users: props.pending_users, pengajuan_menunggu: props.pengajuan_menunggu,
        akses_ditolak: props.akses_ditolak, pbl_terakhir: props.pbl_terakhir,
        generated_at: props.generated_at,
    });
    const [live, setLive] = useState(true);
    const [lastRefresh, setLastRefresh] = useState<Date | null>(null);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        let cancelled = false;
        const tick = async () => {
            try {
                const res = await fetch(props.urls.feed, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                if (cancelled) return;
                setData({
                    kpi: json.kpi, online_users: json.online_users, mesin_down: json.mesin_down,
                    pending_users: json.pending_users, pengajuan_menunggu: json.pengajuan_menunggu,
                    akses_ditolak: json.akses_ditolak, pbl_terakhir: json.pbl_terakhir,
                    generated_at: json.generated_at,
                });
                setLive(true);
                setLastRefresh(new Date());
            } catch {
                if (!cancelled) setLive(false);
            }
        };
        timer.current = setInterval(tick, REFRESH_MS);
        return () => { cancelled = true; if (timer.current) clearInterval(timer.current); };
    }, [props.urls.feed]);

    const k = data.kpi;

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Pusat Kendali"
            subtitle="Pantauan lintas sistem — kehadiran pengguna, perangkat, antrian, dan aktivitas"
            actions={
                <span className={cn('ad-mon-live', !live && 'is-stale')}>
                    <i className="ad-mon-live__dot" />
                    {live ? 'Live' : 'Terputus'}
                </span>
            }>
            <Head title="Pusat Kendali" />

            {/* KPI */}
            <div className="ad-mon-kpis">
                <Link href={props.urls.users} className="ad-mon-kpi ad-pk-link">
                    <div className="ad-mon-kpi__ic ad-pk-ic--green"><Users size={18} /></div>
                    <div className="ad-mon-kpi__body">
                        <span className="ad-mon-kpi__label">Online Sekarang</span>
                        <strong>{k.online}</strong>
                        <small className="ad-mon-kpi__sub">pengguna aktif ≤5 mnt</small>
                    </div>
                </Link>
                <Link href={props.urls.monitoring} className="ad-mon-kpi ad-pk-link">
                    <div className="ad-mon-kpi__ic"><Cpu size={18} /></div>
                    <div className="ad-mon-kpi__body">
                        <span className="ad-mon-kpi__label">Mesin</span>
                        <strong>{k.mesin_online}/{k.mesin_total}</strong>
                        <small className="ad-mon-kpi__sub">
                            {k.mesin_down > 0
                                ? <><i className="ad-dot ad-dot--warn" /> {k.mesin_down} bermasalah</>
                                : <><i className="ad-dot ad-dot--ok" /> semua sehat</>}
                        </small>
                    </div>
                </Link>
                <Link href={props.urls.users} className="ad-mon-kpi ad-pk-link">
                    <div className="ad-mon-kpi__ic ad-pk-ic--amber"><ListTodo size={18} /></div>
                    <div className="ad-mon-kpi__body">
                        <span className="ad-mon-kpi__label">Antrian Approval</span>
                        <strong>{k.pending_approval}</strong>
                        <small className="ad-mon-kpi__sub">akun menunggu persetujuan</small>
                    </div>
                </Link>
                <Link href={props.urls.pengajuan} className="ad-mon-kpi ad-pk-link">
                    <div className="ad-mon-kpi__ic ad-pk-ic--violet"><CalendarCheck size={18} /></div>
                    <div className="ad-mon-kpi__body">
                        <span className="ad-mon-kpi__label">Pengajuan Ruangan</span>
                        <strong>{k.pengajuan_menunggu}</strong>
                        <small className="ad-mon-kpi__sub">menunggu keputusan</small>
                    </div>
                </Link>
                <Link href={props.urls.ruangan} className="ad-mon-kpi ad-pk-link">
                    <div className="ad-mon-kpi__ic ad-pk-ic--red"><DoorOpen size={18} /></div>
                    <div className="ad-mon-kpi__body">
                        <span className="ad-mon-kpi__label">Akses Ditolak</span>
                        <strong>{k.akses_ditolak_hari_ini}</strong>
                        <small className="ad-mon-kpi__sub">hari ini</small>
                    </div>
                </Link>
            </div>

            <div className="ad-pk-grid">
                {/* Pengguna online */}
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><Users size={14} /> Pengguna Online</h3>
                            <small>Aktivitas web/mobile dalam 5 menit terakhir</small>
                        </div>
                        <Link href={props.urls.users} className="ad-pk-more">Semua pengguna</Link>
                    </div>
                    <div className="ad-panel__body">
                        {data.online_users.length === 0 && <p className="ad-pk-empty">Belum ada yang online.</p>}
                        {data.online_users.map((u) => (
                            <Link key={u.id} href={route('profiles.show', u.id)} className="ad-pk-row">
                                {u.foto
                                    ? <img src={u.foto} alt="" className="ad-pk-ava" />
                                    : <span className="ad-pk-ava ad-pk-ava--init">{initials(u.nama)}</span>}
                                <span className="ad-pk-row__main">
                                    <strong>{u.nama}</strong>
                                    <small>{ROLE_LABEL[u.role] ?? u.role}</small>
                                </span>
                                <span className="ad-pk-row__meta">
                                    <i className="ad-dot ad-dot--ok" /> {u.terakhir ?? 'baru saja'}
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>

                {/* Mesin bermasalah */}
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><Cpu size={14} /> Mesin Bermasalah</h3>
                            <small>Aktif tapi tidak merespons ≥5 menit</small>
                        </div>
                        <Link href={props.urls.mesin} className="ad-pk-more">Kelola mesin</Link>
                    </div>
                    <div className="ad-panel__body">
                        {data.mesin_down.length === 0 && <p className="ad-pk-empty">Semua mesin sehat.</p>}
                        {data.mesin_down.map((m, i) => (
                            <div key={i} className="ad-pk-row">
                                <span className="ad-pk-ava ad-pk-ava--init ad-pk-ava--warn"><AlertTriangle size={14} /></span>
                                <span className="ad-pk-row__main">
                                    <strong>{m.nama}</strong>
                                    <small>{m.tipe}{m.ruangan ? ` · ${m.ruangan}` : ''}</small>
                                </span>
                                <span className="ad-pk-row__meta">{m.terakhir}</span>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Antrian approval */}
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><ListTodo size={14} /> Antrian & Keputusan</h3>
                            <small>Akun menunggu approval + pengajuan ruangan</small>
                        </div>
                        <Link href={props.urls.notifikasi} className="ad-pk-more">Antrian notifikasi</Link>
                    </div>
                    <div className="ad-panel__body">
                        {data.pending_users.map((u) => (
                            <Link key={u.id} href={route('profiles.show', u.id)} className="ad-pk-row">
                                <span className="ad-pk-ava ad-pk-ava--init">{initials(u.nama)}</span>
                                <span className="ad-pk-row__main">
                                    <strong>{u.nama}</strong>
                                    <small>{ROLE_LABEL[u.role] ?? u.role} · menunggu approval</small>
                                </span>
                                <span className="ad-pk-row__meta">{u.sejak ?? ''}</span>
                            </Link>
                        ))}
                        {data.pengajuan_menunggu.map((p, i) => (
                            <Link key={`p-${i}`} href={props.urls.pengajuan} className="ad-pk-row">
                                <span className="ad-pk-ava ad-pk-ava--init ad-pk-ava--violet"><CalendarCheck size={14} /></span>
                                <span className="ad-pk-row__main">
                                    <strong>{p.pemohon}</strong>
                                    <small>{p.ruangan} · {p.tanggal}</small>
                                </span>
                                <span className="ad-pk-row__meta">pengajuan</span>
                            </Link>
                        ))}
                        {data.pending_users.length === 0 && data.pengajuan_menunggu.length === 0 && (
                            <p className="ad-pk-empty">Tidak ada antrian.</p>
                        )}
                    </div>
                </section>

                {/* Akses ditolak */}
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><DoorOpen size={14} /> Akses Ruangan Ditolak</h3>
                            <small>Percobaan buka pintu yang ditolak sistem</small>
                        </div>
                        <Link href={props.urls.ruangan} className="ad-pk-more">Log akses</Link>
                    </div>
                    <div className="ad-panel__body">
                        {data.akses_ditolak.length === 0 && <p className="ad-pk-empty">Belum ada penolakan tercatat.</p>}
                        {data.akses_ditolak.map((e, i) => (
                            <div key={i} className="ad-pk-row">
                                <span className="ad-pk-ava ad-pk-ava--init ad-pk-ava--warn"><DoorOpen size={14} /></span>
                                <span className="ad-pk-row__main">
                                    <strong>{e.nama}</strong>
                                    <small>{e.ruangan} · {e.tipe} · {e.alasan}</small>
                                </span>
                                <span className="ad-pk-row__meta">{e.waktu ?? ''}</span>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Aktivitas PBL */}
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h3><ClipboardList size={14} /> Aktivitas PBL Terakhir</h3>
                            <small>Jejak audit — siapa melakukan apa</small>
                        </div>
                        <Link href={props.urls.pbl} className="ad-pk-more">Proyek PBL</Link>
                    </div>
                    <div className="ad-panel__body">
                        {data.pbl_terakhir.length === 0 && <p className="ad-pk-empty">Belum ada aktivitas tercatat.</p>}
                        {data.pbl_terakhir.map((l, i) => (
                            <div key={i} className="ad-pk-row">
                                <span className="ad-pk-ava ad-pk-ava--init">{initials(l.aktor)}</span>
                                <span className="ad-pk-row__main">
                                    <strong>{l.aktor} <em className="ad-pk-role">{ROLE_LABEL[l.role] ?? l.role}</em></strong>
                                    <small>{l.ringkasan ?? l.aksi}</small>
                                </span>
                                <span className="ad-pk-row__meta">{l.waktu ?? ''}</span>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            <p className="ad-pk-foot">
                <RefreshCw size={11} /> Diperbarui otomatis tiap 15 detik
                {lastRefresh && <> · terakhir {lastRefresh.toLocaleTimeString('id-ID')}</>}
            </p>
        </AdminShell>
    );
}
