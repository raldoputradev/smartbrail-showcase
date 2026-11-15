import { useLayoutEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity, Ban, Check, CheckCircle2, Clock, Copy, Download, Eye, FileClock,
    FlaskConical, GraduationCap, KeyRound, Lock, Mail, Pencil, RotateCcw,
    Search, ShieldAlert, ShieldCheck, Trash2, Unlock, Upload, UserPlus,
    Users as UsersIcon, X, XCircle,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { DonutChart } from '@admin/components/Charts';
import { cn } from '@admin/lib/utils';
import type { SharedProps, UserRow, UsersProps } from '@admin/types';

const VERIFY_LABEL: Record<string, string> = {
    menunggu_aktivasi: 'Menunggu aktivasi', approved: 'Disetujui',
    rejected: 'Ditolak', tunggu_admin: 'Tunggu admin', tunggu_otp: 'Tunggu OTP',
};
const VERIFY_CLASS: Record<string, string> = {
    menunggu_aktivasi: 'ad-pill--info', approved: 'ad-pill--ok',
    rejected: 'ad-pill--danger', tunggu_admin: 'ad-pill--pending', tunggu_otp: 'ad-pill--pending',
};
const ENROLL_LABEL: Record<string, string> = {
    enrolled: 'Sudah Enroll', legacy: 'Legacy', pending: 'Belum Enroll', unassigned: 'Perlu Finger ID',
};
const ENROLL_CLASS: Record<string, string> = {
    enrolled: 'ad-pill--ok', legacy: 'ad-pill--danger', pending: 'ad-pill--pending', unassigned: 'ad-pill--danger',
};
const TONE_CLASS: Record<string, string> = {
    success: 'ad-pill--ok', danger: 'ad-pill--danger', warning: 'ad-pill--pending', info: 'ad-pill--info',
};
const ID_LABEL: Record<string, string> = {
    mahasiswa: 'NIM', dosen: 'NIDN', laboran: 'NITK', admin: 'ID',
};

function initials(nama: string): string {
    return nama.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

function Avatar({ u, size = 38 }: { u: UserRow; size?: number }) {
    return u.avatar_url
        ? <img className="ad-usr-ava" style={{ width: size, height: size }} src={u.avatar_url} alt="" />
        : <span className={cn('ad-usr-ava', `ad-usr-ava--${u.role}`)} style={{ width: size, height: size, fontSize: size * 0.36 }}>{initials(u.nama)}</span>;
}

/** Ilustrasi kampus seirama referensi. */
function CampusArt() {
    return (
        <svg className="ad-mesin-hero__art" viewBox="0 0 360 190" fill="none" aria-hidden="true">
            <path d="M0 158 Q 100 125 200 145 T 360 132" stroke="rgba(125,211,252,.35)" strokeWidth="1.5"/>
            <path d="M0 172 Q 110 145 210 158 T 360 148" stroke="rgba(125,211,252,.2)" strokeWidth="1.3"/>
            <g fill="rgba(13,27,54,.55)" stroke="rgba(147,197,253,.5)" strokeWidth="1.4">
                <rect x="150" y="60" width="120" height="96" rx="6"/>
                <rect x="90" y="90" width="60" height="66" rx="6"/>
                <rect x="270" y="98" width="52" height="58" rx="6"/>
            </g>
            <path d="M210 60 v-22 l8 8 v14" stroke="rgba(147,197,253,.6)" strokeWidth="1.6" fill="rgba(13,27,54,.4)"/>
            <g fill="rgba(125,211,252,.4)">
                {Array.from({ length: 3 }).map((_, r) =>
                    Array.from({ length: 5 }).map((_, c) => (
                        <rect key={`${r}${c}`} x={164 + c * 20} y={76 + r * 24} width="10" height="12" rx="2"/>
                    )))}
            </g>
            <g fill="rgba(125,211,252,.3)">
                <rect x="104" y="102" width="10" height="12" rx="2"/><rect x="124" y="102" width="10" height="12" rx="2"/>
                <rect x="104" y="126" width="10" height="12" rx="2"/><rect x="124" y="126" width="10" height="12" rx="2"/>
                <rect x="284" y="110" width="10" height="12" rx="2"/><rect x="300" y="110" width="10" height="12" rx="2"/>
            </g>
            <rect x="202" y="120" width="16" height="36" rx="3" fill="rgba(52,211,153,.5)"/>
            {/* Orang kecil */}
            <g>
                <circle cx="66" cy="140" r="8" fill="#7dd3fc" opacity=".9"/>
                <rect x="58" y="148" width="16" height="22" rx="7" fill="#38bdf8" opacity=".7"/>
                <circle cx="330" cy="146" r="7" fill="#a5f3fc" opacity=".85"/>
                <rect x="324" y="153" width="13" height="18" rx="6" fill="#38bdf8" opacity=".55"/>
            </g>
        </svg>
    );
}

type FormState = {
    mode: 'create' | 'edit';
    id: number | null;
    nama_lengkap: string; nitk_nidn: string; email: string; password: string;
    role: string; is_active: boolean;
    kelas: string; tim: string; angkatan: string; semester: string;
    semester_type: string; academic_status: string; program_studi_id: string; shift: string;
    kelas_cakupan: string[];
    no_hp: string; rfid_uid: string; akses_pin: string;
};

const EMPTY_FORM: FormState = {
    mode: 'create', id: null,
    nama_lengkap: '', nitk_nidn: '', email: '', password: '',
    role: 'mahasiswa', is_active: true,
    kelas: '', tim: '', angkatan: '', semester: '', semester_type: '', academic_status: 'aktif', program_studi_id: '', shift: 'pagi',
    kelas_cakupan: [],
    no_hp: '', rfid_uid: '', akses_pin: '',
};

export default function Users(props: UsersProps) {
    const { flash, errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [busy, setBusy] = useState(false);
    const [copied, setCopied] = useState(false);
    const [confirm, setConfirm] = useState<null | { title: string; body: string; label: string; danger?: boolean; action: () => void }>(null);
    const [form, setForm] = useState<FormState | null>(null);
    const [kcSearch, setKcSearch] = useState('');
    const tableWrapRef = useRef<HTMLDivElement>(null);
    const sideRef = useRef<HTMLElement>(null);

    // Batasi tinggi tabel agar bawahnya sejajar rail kanan (kartu Aktivitas Terbaru) — sisanya scroll internal.
    useLayoutEffect(() => {
        const apply = () => {
            const wrap = tableWrapRef.current, side = sideRef.current;
            if (!wrap || !side) return;
            if (window.innerWidth <= 900) { wrap.style.maxHeight = ''; return; }
            const limit = Math.round(side.getBoundingClientRect().bottom - wrap.getBoundingClientRect().top - 56);
            wrap.style.maxHeight = `${Math.max(limit, 280)}px`;
        };
        apply();
        window.addEventListener('resize', apply);
        return () => window.removeEventListener('resize', apply);
    });
    const activeRole = props.role_page ?? props.filters.role;

    const once = flash.activation_once ?? null;

    const post = (url: string, data: Record<string, unknown> = {}, method: 'post' | 'put' | 'delete' = 'post') => {
        setBusy(true);
        router[method](url, data as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) {
                    setConfirm(null); setForm(null);
                }
            },
        });
    };

    /** Gabungkan filter aktif dengan parameter baru lalu GET ke URL tujuan. */
    const nav = (url: string, params: Record<string, unknown>) => {
        router.get(url, {
            q: props.filters.q || undefined,
            status: props.filters.status || undefined,
            approval_status: props.filters.approval_status || undefined,
            verify_status: props.filters.verify_status || undefined,
            login_lock: props.filters.login_lock || undefined,
            angkatan: props.filters.angkatan || undefined,
            semester: props.filters.semester || undefined,
            per_page: props.filters.per_page !== 15 ? props.filters.per_page : undefined,
            ...params,
        }, { preserveState: true, preserveScroll: true });
    };

    const tabUrl = (role: string | null) => {
        if (role === null) return props.urls.index;
        if (role === 'admin') return props.urls.index;
        return `${props.urls.role_base}${role}`;
    };
    const goTab = (role: string | null) => {
        const url = tabUrl(role);
        const params: Record<string, unknown> = role === 'admin' ? { role: 'admin' } : {};
        nav(url, { ...params, page: undefined });
    };

    const submitFilter = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        nav(props.role_page ? `${props.urls.role_base}${props.role_page}` : props.urls.index, {
            q: fd.get('q') || undefined,
            status: fd.get('status') || undefined,
            approval_status: fd.get('approval_status') || undefined,
            verify_status: fd.get('verify_status') || undefined,
            login_lock: fd.get('login_lock') || undefined,
            angkatan: fd.get('angkatan') || undefined,
            semester: fd.get('semester') || undefined,
            role: props.role_page ? undefined : (props.filters.role || undefined),
            page: undefined,
        });
    };

    const quick = (params: Record<string, unknown>) => nav(props.urls.index, { ...params, page: undefined });



    const openCreate = () => { setKcSearch(''); setForm({ ...EMPTY_FORM }); };
    const openEdit = (u: UserRow) => {
        setKcSearch('');
        setForm({
            mode: 'edit', id: u.id,
            nama_lengkap: u.nama, nitk_nidn: u.nitk_nidn ?? '', email: u.email ?? '',
            password: '', role: u.role, is_active: u.is_active,
            kelas: u.kelas ?? '', tim: u.tim ?? '',
            angkatan: u.angkatan ? String(u.angkatan) : '',
            semester: u.semester ? String(u.semester) : '',
            semester_type: u.semester_type ?? '',
            academic_status: u.academic_status ?? 'aktif',
            program_studi_id: u.program_studi_id ? String(u.program_studi_id) : '',
            shift: u.shift ?? 'pagi',
            kelas_cakupan: u.kelas_cakupan ?? [],
            no_hp: u.no_hp ?? '', rfid_uid: u.rfid_uid ?? '', akses_pin: '',
        });
    };

    const submitForm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form) return;
        const payload: Record<string, unknown> = {
            nama_lengkap: form.nama_lengkap, nitk_nidn: form.nitk_nidn,
            email: form.email || null, role: form.role, is_active: form.is_active ? 1 : 0,
            kelas_cakupan: form.role === 'dosen' ? form.kelas_cakupan : undefined,
            no_hp: form.no_hp || null,
            rfid_uid: form.rfid_uid || null,
        };
        if (form.akses_pin) payload.akses_pin = form.akses_pin;
        if (form.password) payload.password = form.password;
        else if (form.mode === 'create') payload.password = '';
        if (form.role === 'mahasiswa') {
            Object.assign(payload, {
                kelas: form.kelas, tim: form.tim,
                angkatan: form.angkatan, semester: form.semester,
                semester_type: form.semester_type,
                academic_status: form.academic_status,
                program_studi_id: form.program_studi_id, shift: form.shift,
            });
        }
        if (form.mode === 'create') {
            post(props.urls.store, payload, 'post');
        } else {
            post(`${props.urls.user_base}${form.id}`, payload, 'put');
        }
    };

    const ask = (c: NonNullable<typeof confirm>) => setConfirm(c);

    const act = (u: UserRow, kind: 'toggle' | 'unlock' | 'code' | 'delete') => {
        const base = `${props.urls.user_base}${u.id}`;
        if (kind === 'toggle') {
            ask({
                title: u.is_active ? 'Nonaktifkan akun?' : 'Aktifkan kembali?',
                body: u.is_active
                    ? `${u.nama} tidak bisa login sampai diaktifkan lagi. Status persetujuan tetap Disetujui.`
                    : `${u.nama} bisa login lagi.`,
                label: u.is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
                danger: u.is_active,
                action: () => post(`${base}/toggle-active`),
            });
        } else if (kind === 'unlock') {
            ask({
                title: 'Buka kunci login?',
                body: `Pengguna ${u.nama} bisa login lagi setelah dibuka. Status persetujuan akun tidak berubah.`,
                label: 'Ya, Buka Kunci',
                action: () => post(`${base}/unlock-login`),
            });
        } else if (kind === 'code') {
            ask({
                title: 'Buat kredensial masuk baru?',
                body: `Kredensial lama ${u.nama} langsung tidak berlaku dan sesi aktif dicabut. Password sementara baru hanya ditampilkan sekali — salin dan bagikan ke pemilik akun.`,
                label: 'Ya, Buat Kredensial',
                action: () => post(`${base}/activation-code`),
            });
        } else {
            ask({
                title: 'Hapus user?',
                body: `User ${u.nama} akan dihapus permanen. Riwayat penghapusan tetap tersimpan untuk admin.`,
                label: 'Ya, Hapus', danger: true,
                action: () => post(base, {}, 'delete'),
            });
        }
    };

    const meta = props.users.meta;
    const gotoPage = (p: number) => nav(props.role_page ? `${props.urls.role_base}${props.role_page}` : props.urls.index, { page: p });

    const ROLE_TABS: { key: string | null; label: string; icon: React.ReactNode; count: number }[] = [
        { key: null, label: 'Semua Pengguna', icon: <UsersIcon size={15} />, count: props.stats.total },
        { key: 'mahasiswa', label: 'Mahasiswa', icon: <GraduationCap size={15} />, count: props.stats.mahasiswa },
        { key: 'dosen', label: 'Dosen', icon: <FileClock size={15} />, count: props.stats.dosen },
        { key: 'laboran', label: 'Laboran', icon: <FlaskConical size={15} />, count: props.stats.laboran },
        { key: 'admin', label: 'Admin', icon: <ShieldCheck size={15} />, count: props.stats.admin },
    ];
    const roleActive = (k: string | null) => (k === null ? !activeRole : activeRole === k);

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Data Pengguna"
            subtitle="Kelola akun mahasiswa, dosen, laboran, dan admin sistem secara terpusat.">
            <Head title="Data Pengguna" />

            <section className="ad-mesin-hero">
                <div className="ad-mesin-hero__text">
                    <h1>Manajemen Pengguna</h1>
                    <p>Kelola, verifikasi, dan pantau seluruh akun pengguna sistem SmartBrail.</p>
                </div>
                <CampusArt />
            </section>

            {flash.success && <div className="ad-mesin-flash ad-mesin-flash--ok">{flash.success}</div>}
            {flash.error && <div className="ad-mesin-flash ad-mesin-flash--err">{flash.error}</div>}

            <div className="ad-usr-layout">
                <div className="ad-usr-left">
                    {/* KPI */}
                    <div className="ad-usr-kpis">
                        <div className="ad-usr-kpi ad-usr-kpi--total">
                            <div className="ad-usr-kpi__ic"><UsersIcon size={18} /></div>
                            <div>
                                <small>Total Pengguna</small>
                                <strong>{props.stats.total} {props.stats.new_30d > 0 && <em className="ad-usr-kpi__delta">↑ +{props.stats.new_30d}</em>}</strong>
                                <small className="ad-usr-kpi__sub">30 hari terakhir</small>
                            </div>
                        </div>
                        {props.stats.online !== undefined && (
                            <div className="ad-usr-kpi ad-usr-kpi--online" title="Pengguna aktif dalam 5 menit terakhir — khusus admin">
                                <div className="ad-usr-kpi__ic"><span className="ad-usr-online-dot" /></div>
                                <div>
                                    <small>Online Sekarang</small>
                                    <strong>{props.stats.online}</strong>
                                    <small className="ad-usr-kpi__sub">5 menit terakhir</small>
                                </div>
                            </div>
                        )}
                        {([
                            ['mahasiswa', 'Mahasiswa', 'teal', <GraduationCap key="g" size={18} />],
                            ['dosen', 'Dosen', 'blue', <FileClock key="d" size={18} />],
                            ['laboran', 'Laboran', 'amber', <FlaskConical key="l" size={18} />],
                            ['admin', 'Admin', 'purple', <ShieldCheck key="a" size={18} />],
                        ] as const).map(([key, label, tone, ic]) => (
                            <div key={key} className={`ad-usr-kpi ad-usr-kpi--${tone}`}>
                                <div className="ad-usr-kpi__ic">{ic}</div>
                                <div>
                                    <small>{label}</small>
                                    <strong>{props.stats[key]}</strong>
                                    <small className="ad-usr-kpi__sub">
                                        ({props.stats.total > 0 ? Math.round((props.stats[key] / props.stats.total) * 100) : 0}%)
                                    </small>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Peringatan pipeline */}
                    {(props.alerts.awaiting_activation > 0 || props.alerts.pending_otp > 0 || props.alerts.login_locked > 0) && (
                        <div className="ad-usr-alerts">
                            {props.alerts.awaiting_activation > 0 && (
                                <button type="button" className="ad-usr-alert ad-usr-alert--info" onClick={() => quick({ approval_status: 'menunggu_aktivasi' })}>
                                    <KeyRound size={15} /><span><b>{props.alerts.awaiting_activation}</b> akun hasil import menunggu aktivasi</span>
                                </button>
                            )}
                            {props.alerts.pending_otp > 0 && (
                                <button type="button" className="ad-usr-alert ad-usr-alert--warn" onClick={() => quick({ verify_status: 'otp_pending' })}>
                                    <Mail size={15} /><span><b>{props.alerts.pending_otp}</b> akun menunggu verifikasi OTP</span>
                                </button>
                            )}
                            {props.alerts.login_locked > 0 && (
                                <button type="button" className="ad-usr-alert ad-usr-alert--danger" onClick={() => quick({ login_lock: 'locked' })}>
                                    <Lock size={15} /><span><b>{props.alerts.login_locked}</b> akun terkunci login</span>
                                </button>
                            )}
                        </div>
                    )}

                    {/* Tab role */}
                    <div className="ad-atur-tabs">
                        {ROLE_TABS.map((t) => (
                            <button key={t.key ?? 'all'} type="button"
                                className={cn('ad-atur-tab', roleActive(t.key) && 'is-active')}
                                onClick={() => goTab(t.key)}>
                                {t.icon} {t.label} <span className="ad-usr-tab__n">{t.count}</span>
                            </button>
                        ))}
                    </div>

                    {(
                        <section className="ad-panel">
                            <div className="ad-panel__head">
                                <div>
                                    <h2>Daftar Pengguna</h2>
                                    <p>{meta.total} akun · halaman {meta.page}</p>
                                </div>
                                <div className="ad-usr-headact">
                                    <a href={props.urls.import} className="ad-mesin-btn ad-mesin-btn--ghost"><Download size={15} /> Import Akun</a>
                                    {props.can.create && (
                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={openCreate}><UserPlus size={15} /> Tambah Pengguna</button>
                                    )}
                                </div>
                            </div>

                            <form className="ad-usr-toolbar" onSubmit={submitFilter}>
                                <div className="ad-usr-search">
                                    <Search size={15} />
                                    <input name="q" defaultValue={props.filters.q} placeholder="Cari nama, email, NIM, atau identitas..." />
                                </div>
                                <select name="status" defaultValue={props.filters.status}>
                                    <option value="">Status: Semua</option>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                                <select name="verify_status" defaultValue={props.filters.verify_status}>
                                    <option value="">Verifikasi: Semua</option>
                                    <option value="otp_pending">OTP pending</option>
                                    <option value="menunggu_aktivasi">Menunggu aktivasi</option>
                                    <option value="ready_approve">Siap disetujui</option>
                                    <option value="all">Semua tahap</option>
                                </select>
                                <select name="approval_status" defaultValue={props.filters.approval_status}>
                                    <option value="">Persetujuan</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                                <select name="login_lock" defaultValue={props.filters.login_lock}>
                                    <option value="">Login</option>
                                    <option value="locked">Terkunci</option>
                                </select>
                                {(!props.role_page || props.role_page === 'mahasiswa') && (
                                    <>
                                        <select name="angkatan" defaultValue={props.filters.angkatan}>
                                            <option value="">Angkatan</option>
                                            {props.options.angkatan.map((y) => <option key={y} value={y}>{y}</option>)}
                                        </select>
                                        <select name="semester" defaultValue={props.filters.semester}>
                                            <option value="">Sem</option>
                                            {Array.from({ length: 8 }, (_, i) => i + 1).map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                    </>
                                )}
                                <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary">Filter</button>
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => nav(props.role_page ? `${props.urls.role_base}${props.role_page}` : props.urls.index, {
                                    q: undefined, status: undefined, approval_status: undefined, verify_status: undefined,
                                    login_lock: undefined, angkatan: undefined, semester: undefined, role: undefined, page: undefined,
                                })}><RotateCcw size={14} /> Reset</button>
                            </form>

                            <div ref={tableWrapRef} className="ad-table-wrap ad-usr-desktop">
                                <table className="ad-table">
                                    <thead>
                                        <tr>
                                            <th>Pengguna</th><th>Identitas</th><th>Peran</th><th>Akademik</th>
                                            <th>Enroll</th><th>Verifikasi</th><th>Status</th><th className="is-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {props.users.data.length === 0 && (
                                            <tr><td colSpan={8} className="ad-empty">Belum ada data pengguna untuk filter ini.</td></tr>
                                        )}
                                        {props.users.data.map((u) => (
                                            <tr key={u.id}>
                                                <td>
                                                    <div className="ad-usr-name">
                                                        <span className="ad-usr-avawrap">
                                                            <Avatar u={u} />
                                                            {u.online === true && <span className="ad-usr-online-dot ad-usr-online-dot--ava" title={u.last_seen ? `Online — aktif ${u.last_seen}` : 'Online'} />}
                                                        </span>
                                                        <div>
                                                            <a href={u.profile_url} className="ad-link"><strong>{u.nama}</strong></a>
                                                            <small>{u.email || '—'}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div className="ad-usr-id">
                                                        <small>{ID_LABEL[u.role] ?? 'ID'}</small>
                                                        <code>{u.nitk_nidn || '—'}</code>
                                                    </div>
                                                </td>
                                                <td><span className={cn('ad-pill', `ad-pill--role-${u.role}`)}>{u.role_label}</span></td>
                                                <td>
                                                    {u.role === 'mahasiswa' ? (
                                                        <div className="ad-usr-akd">
                                                            <span>{u.kelas || '—'} · {u.tim || '—'}</span>
                                                            <small>Angkatan {u.angkatan || '—'} · Sem {u.semester || '—'}</small>
                                                        </div>
                                                    ) : (
                                                        <small className="ad-empty">Panel web</small>
                                                    )}
                                                </td>
                                                <td>
                                                    {u.enroll ? (
                                                        <span className={cn('ad-pill', ENROLL_CLASS[u.enroll] ?? 'ad-pill--muted')}>{ENROLL_LABEL[u.enroll] ?? u.enroll}</span>
                                                    ) : <small className="ad-empty">Panel web</small>}
                                                </td>
                                                <td><span className={cn('ad-pill', VERIFY_CLASS[u.verify] ?? 'ad-pill--muted')}>{VERIFY_LABEL[u.verify] ?? u.verify}</span></td>
                                                <td>
                                                    <span className={cn('ad-pill', u.is_active ? 'ad-pill--ok' : 'ad-pill--off')}>{u.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                                    {u.lock && <span className="ad-pill ad-pill--danger" title="Login terkunci"><Lock size={11} /></span>}
                                                </td>
                                                <td className="is-right">
                                                    <div className="ad-usr-acts">
                                                        <a href={u.profile_url} className="ad-icon-btn" title="Profil pengguna"><Eye size={15} /></a>
                                                        {u.can_issue_code && !u.is_self && (
                                                            <button type="button" className="ad-icon-btn" title="Buat kredensial masuk baru" onClick={() => act(u, 'code')}><KeyRound size={15} /></button>
                                                        )}
                                                        {u.lock && (
                                                            <button type="button" className="ad-icon-btn" title="Buka kunci login" onClick={() => act(u, 'unlock')}><Unlock size={15} /></button>
                                                        )}
                                                        {!u.is_self && (
                                                            <button type="button" className="ad-icon-btn" title={u.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'} onClick={() => act(u, 'toggle')}><Ban size={15} /></button>
                                                        )}
                                                        <button type="button" className="ad-icon-btn" title="Edit pengguna" onClick={() => openEdit(u)}><Pencil size={15} /></button>
                                                        {!u.is_self && (
                                                            <button type="button" className="ad-icon-btn ad-icon-btn--danger" title="Hapus user" onClick={() => act(u, 'delete')}><Trash2 size={15} /></button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Versi kartu untuk layar kecil */}
                            <div className="ad-mesin-cards">
                                {props.users.data.map((u) => (
                                    <article key={u.id} className="ad-usr-mcard">
                                        <div className="ad-usr-mcard__head">
                                            <Avatar u={u} />
                                            <div className="ad-usr-mcard__id">
                                                <a href={u.profile_url} className="ad-link"><strong>{u.nama}</strong></a>
                                                <small>{ID_LABEL[u.role] ?? 'ID'} {u.nitk_nidn || '—'} · {u.role_label}</small>
                                            </div>
                                            <span className={cn('ad-pill', u.is_active ? 'ad-pill--ok' : 'ad-pill--off')}>{u.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                        </div>
                                        <div className="ad-usr-mcard__body">
                                            <span>{u.role === 'mahasiswa' ? `${u.kelas || '—'} · ${u.tim || '—'}` : 'Panel web'}</span>
                                            <span className={cn('ad-pill', VERIFY_CLASS[u.verify] ?? 'ad-pill--muted')}>{VERIFY_LABEL[u.verify] ?? u.verify}</span>
                                            {u.enroll && <span className={cn('ad-pill', ENROLL_CLASS[u.enroll] ?? 'ad-pill--muted')}>{ENROLL_LABEL[u.enroll] ?? u.enroll}</span>}
                                        </div>
                                        <div className="ad-usr-mcard__acts">
                                            <a href={u.profile_url} className="ad-icon-btn" title="Profil"><Eye size={15} /></a>
                                            {u.can_issue_code && !u.is_self && <button type="button" className="ad-icon-btn" title="Kredensial baru" onClick={() => act(u, 'code')}><KeyRound size={15} /></button>}
                                            {u.lock && <button type="button" className="ad-icon-btn" title="Buka kunci" onClick={() => act(u, 'unlock')}><Unlock size={15} /></button>}
                                            {!u.is_self && <button type="button" className="ad-icon-btn" title="Aktif/nonaktif" onClick={() => act(u, 'toggle')}><Ban size={15} /></button>}
                                            <button type="button" className="ad-icon-btn" title="Edit" onClick={() => openEdit(u)}><Pencil size={15} /></button>
                                            {!u.is_self && <button type="button" className="ad-icon-btn ad-icon-btn--danger" title="Hapus" onClick={() => act(u, 'delete')}><Trash2 size={15} /></button>}
                                        </div>
                                    </article>
                                ))}
                            </div>
<div className="ad-mesin-pager">
                                <small>Menampilkan {props.users.data.length ? ((meta.page - 1) * meta.per_page) + 1 : 0}–{Math.min(meta.page * meta.per_page, meta.total)} dari {meta.total} pengguna</small>
                                <div className="ad-usr-foot__ctl">
                                    <select
                                        value={props.filters.per_page ?? meta.per_page}
                                        onChange={(e) => nav(props.role_page ? `${props.urls.role_base}${props.role_page}` : props.urls.index, {
                                            q: props.filters.q || undefined, status: props.filters.status || undefined,
                                            verify_status: props.filters.verify_status || undefined,
                                            approval_status: props.filters.approval_status || undefined,
                                            login_lock: props.filters.login_lock || undefined,
                                            angkatan: props.filters.angkatan || undefined,
                                            semester: props.filters.semester || undefined,
                                            per_page: Number(e.target.value), page: undefined,
                                        })}
                                        aria-label="Data per halaman"
                                    >
                                        {[5, 10, 15, 25, 50].map((n) => <option key={n} value={n}>{n} / halaman</option>)}
                                    </select>
                                    {meta.last_page > 1 && (
                                        <div className="ad-mesin-pager__btns">
                                            <button type="button" disabled={meta.page <= 1} onClick={() => gotoPage(meta.page - 1)} aria-label="Halaman sebelumnya">&lsaquo;</button>
                                            {Array.from({ length: meta.last_page }, (_, i) => i + 1)
                                                .filter((p) => p === 1 || p === meta.last_page || Math.abs(p - meta.page) <= 1)
                                                .reduce<(number | 'dots')[]>((acc, p) => {
                                                    const prev = acc[acc.length - 1];
                                                    if (typeof prev === 'number' && p - prev > 1) acc.push('dots');
                                                    acc.push(p);
                                                    return acc;
                                                }, [])
                                                .map((p, i) => p === 'dots'
                                                    ? <span key={`d${i}`} className="ad-mesin-page ad-mesin-page--dots">&hellip;</span>
                                                    : <button key={p} type="button" className={cn('ad-mesin-page', p === meta.page && 'is-active')} onClick={() => gotoPage(p)}>{p}</button>)}
                                            <button type="button" disabled={meta.page >= meta.last_page} onClick={() => gotoPage(meta.page + 1)} aria-label="Halaman berikutnya">&rsaquo;</button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}

                </div>

                {/* ============ KOLOM KANAN ============ */}
                <aside ref={sideRef} className="ad-usr-side">
                    <section className="ad-panel">
                        <h3 className="ad-usr-h3">Komposisi Pengguna</h3>
                        <DonutChart data={{
                            labels: props.role_chart.labels,
                            values: props.role_chart.values,
                            colors: props.role_chart.colors,
                            total: props.stats.total,
                            center_label: 'Pengguna',
                        }} />
                        <div className="ad-usr-legend">
                            {props.role_chart.labels.map((l, i) => (
                                <div key={l}>
                                    <span><i style={{ background: props.role_chart.colors[i] }} />{l}</span>
                                    <b>{props.role_chart.values[i]} ({props.stats.total > 0 ? Math.round((props.role_chart.values[i] / props.stats.total) * 100) : 0}%)</b>
                                </div>
                            ))}
                        </div>
                        <div className="ad-usr-enroll">
                            <div className="ad-usr-enroll__bar"><i style={{ width: `${props.stats.enroll_pct}%` }} /></div>
                            <small>{props.stats.enroll_pct}% mahasiswa enroll sidik jari</small>
                        </div>
                    </section>

                    <section className="ad-panel">
                        <h3 className="ad-usr-h3">Aksi Cepat</h3>
                        <div className="ad-usr-quick">
                            {props.can.create && (
                                <button type="button" onClick={openCreate}><UserPlus size={15} /><span>Tambah Pengguna</span></button>
                            )}
                            {props.can.create && (
                                <a href={props.urls.import}><Upload size={15} /><span>Import Akun (Excel/CSV)</span></a>
                            )}
                            <button type="button" onClick={() => quick({ verify_status: 'otp_pending' })}>
                                <Mail size={15} /><span>Tunggu OTP</span><b>{props.alerts.pending_otp}</b>
                            </button>
                            <button type="button" onClick={() => quick({ login_lock: 'locked' })}>
                                <Lock size={15} /><span>Login terkunci</span><b>{props.alerts.login_locked}</b>
                            </button>
                            <button type="button" onClick={() => quick({ approval_status: 'menunggu_aktivasi' })}>
                                <KeyRound size={15} /><span>Menunggu aktivasi</span><b>{props.alerts.awaiting_activation}</b>
                            </button>
                            <button type="button" onClick={() => nav(`${props.urls.role_base}mahasiswa`, { page: undefined })} className="ad-usr-quick--ghost">
                                <GraduationCap size={15} /><span>Filter mahasiswa →</span>
                            </button>
                        </div>
                    </section>

                    <section className="ad-panel">
                        <div className="ad-usr-h3row">
                            <h3 className="ad-usr-h3">Aktivitas Terbaru</h3>
                            {props.urls.riwayat && (
                                <a href={props.urls.riwayat} className="ad-usr-feed__all">Riwayat lengkap →</a>
                            )}
                        </div>
                        <div className="ad-usr-feed">
                            {props.aktivitas.length === 0 && <div className="ad-empty">Belum ada aktivitas.</div>}
                            {props.aktivitas.map((a, i) => (
                                <div key={i} className="ad-usr-feed__item">
                                    <span className={cn('ad-usr-feed__ic', `ad-usr-feed__ic--${a.tone}`)}>
                                        {a.tone === 'success' ? <CheckCircle2 size={14} /> : a.tone === 'danger' ? <XCircle size={14} /> : <Activity size={14} />}
                                    </span>
                                    <div>
                                        <strong>{a.label}</strong>
                                        <small>{a.subject}</small>
                                        <small className="ad-usr-feed__meta">{a.actor} · {a.waktu}</small>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </aside>
            </div>

            {/* ============ MODAL FORM USER ============ */}
            {form && (
                <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && setForm(null)}>
                    <div className="ad-mesin-setup ad-usr-modal">
                        <button type="button" className="ad-icon-btn ad-usr-x" onClick={() => setForm(null)}><X size={16} /></button>
                        <span className="ad-usr-modal__badge">{form.mode === 'create' ? 'TAMBAH USER' : 'EDIT USER'}</span>
                        <h3>{form.mode === 'create' ? 'Tambah Pengguna' : `Edit — ${form.nama_lengkap}`}</h3>
                        {form.mode === 'create' && (
                            <p className="ad-usr-modal__hint">
                                Centang <b>Aktifkan akun</b> agar akun langsung bisa dipakai. Kosongkan jika akun harus dibuat nonaktif dulu.
                            </p>
                        )}
                        <form onSubmit={submitForm}>
                            <div className="ad-usr-fgrid">
                                <label>Nama Lengkap
                                    <input required value={form.nama_lengkap} onChange={(e) => setForm({ ...form, nama_lengkap: e.target.value })} />
                                    {errors.nama_lengkap && <em className="ad-mesin-err">{errors.nama_lengkap}</em>}
                                </label>
                                <label>NITK/NIDN/NIM
                                    <input required value={form.nitk_nidn} onChange={(e) => setForm({ ...form, nitk_nidn: e.target.value })} />
                                    {errors.nitk_nidn && <em className="ad-mesin-err">{errors.nitk_nidn}</em>}
                                </label>
                                <label>Email <i className="ad-usr-opt">(opsional)</i>
                                    <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="Kosongkan jika verifikasi mandiri saat login" />
                                    <small className="ad-usr-hint">Jika kosong, pengguna mengisi &amp; memverifikasi email sendiri saat login pertama.</small>
                                    {errors.email && <em className="ad-mesin-err">{errors.email}</em>}
                                </label>
                                <label>Nomor HP <i className="ad-usr-opt">(opsional)</i>
                                    <input type="tel" value={form.no_hp} onChange={(e) => setForm({ ...form, no_hp: e.target.value })} placeholder="08xxxxxxxxxx" maxLength={30} />
                                    {errors.no_hp && <em className="ad-mesin-err">{errors.no_hp}</em>}
                                </label>
                                <label>Kartu Akses / RFID <i className="ad-usr-opt">(opsional)</i>
                                    <input value={form.rfid_uid} onChange={(e) => setForm({ ...form, rfid_uid: e.target.value.toUpperCase() })} placeholder="UID kartu, mis. 04A1B2C3" maxLength={64} />
                                    <small className="ad-usr-hint">Nomor kartu untuk tap akses ruangan di mesin — terpisah dari sidik jari absensi.</small>
                                    {errors.rfid_uid && <em className="ad-mesin-err">{errors.rfid_uid}</em>}
                                </label>
                                {form.role !== 'mahasiswa' && (
                                <label>PIN Akses Ruangan <i className="ad-usr-opt">{form.mode === 'edit' ? '(kosongkan jika tetap)' : '(opsional)'}</i>
                                    <input type="password" inputMode="numeric" minLength={4} maxLength={20} value={form.akses_pin} onChange={(e) => setForm({ ...form, akses_pin: e.target.value })} placeholder="PIN 4–20 digit untuk mesin ruangan" autoComplete="new-password" />
                                    <small className="ad-usr-hint">Disimpan sebagai hash — dipakai mesin saat buka akses ruangan. Khusus staf (admin/laboran/dosen); mahasiswa mendapat PIN per-izin otomatis.</small>
                                    {errors.akses_pin && <em className="ad-mesin-err">{errors.akses_pin}</em>}
                                </label>
                                )}
                                <label>Password <i className="ad-usr-opt">{form.mode === 'create' ? '(wajib)' : '(kosongkan jika tetap)'}</i>
                                    <input type="password" minLength={6} required={form.mode === 'create'} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder="Minimal 6 karakter" />
                                    {errors.password && <em className="ad-mesin-err">{errors.password}</em>}
                                </label>
                                <label>Role
                                    <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                                        <option value="admin">Admin</option>
                                        <option value="dosen">Dosen</option>
                                        <option value="mahasiswa">Mahasiswa</option>
                                        <option value="laboran">Laboran</option>
                                    </select>
                                    {errors.role && <em className="ad-mesin-err">{errors.role}</em>}
                                </label>
                                <label className="ad-usr-check">
                                    <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
                                    <span><b>Aktifkan akun</b><small>Jika tidak dicentang, user dibuat dalam keadaan nonaktif.</small></span>
                                </label>
                            </div>

                            {form.role === 'dosen' && (
                                <div className="ad-usr-subgrid">
                                    <label className="ad-usr-full">Kelas yang diampu <i className="ad-usr-opt">({form.kelas_cakupan.length} dipilih)</i>
                                        <input type="search" value={kcSearch} onChange={(e) => setKcSearch(e.target.value)} placeholder="Cari kelas…" />
                                        <div className="ad-usr-kclist">
                                            {props.options.kelas
                                                .filter((k) => k.toLowerCase().includes(kcSearch.toLowerCase()))
                                                .map((k) => (
                                                    <label key={k} className="ad-usr-kc">
                                                        <input type="checkbox" checked={form.kelas_cakupan.includes(k)}
                                                            onChange={(e) => setForm({
                                                                ...form,
                                                                kelas_cakupan: e.target.checked
                                                                    ? [...form.kelas_cakupan, k]
                                                                    : form.kelas_cakupan.filter((x) => x !== k),
                                                            })} />
                                                        <span>{k}</span>
                                                    </label>
                                                ))}
                                            {props.options.kelas.filter((k) => k.toLowerCase().includes(kcSearch.toLowerCase())).length === 0 && (
                                                <small className="ad-empty">Tidak ada kelas cocok.</small>
                                            )}
                                        </div>
                                        <small className="ad-usr-hint">Dosen hanya melihat rekap, direktori, AI, dan unduhan untuk kelas yang dicentang.</small>
                                    </label>
                                </div>
                            )}

                            {form.role === 'mahasiswa' && (
                                <div className="ad-usr-fgrid ad-usr-subgrid">
                                    <label>Kelas
                                        <select value={form.kelas} onChange={(e) => setForm({ ...form, kelas: e.target.value })}>
                                            <option value="">Pilih kelas</option>
                                            {props.options.kelas.map((k) => <option key={k} value={k}>{k}</option>)}
                                        </select>
                                        {errors.kelas && <em className="ad-mesin-err">{errors.kelas}</em>}
                                    </label>
                                    <label>Program Studi
                                        <select value={form.program_studi_id} onChange={(e) => setForm({ ...form, program_studi_id: e.target.value })}>
                                            <option value="">Pilih prodi</option>
                                            {props.options.program_studi.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                                        </select>
                                        {errors.program_studi_id && <em className="ad-mesin-err">{errors.program_studi_id}</em>}
                                    </label>
                                    <label>Shift absensi
                                        <select value={form.shift} onChange={(e) => setForm({ ...form, shift: e.target.value })}>
                                            <option value="pagi">Pagi</option>
                                            <option value="malam">Malam</option>
                                        </select>
                                        {errors.shift && <em className="ad-mesin-err">{errors.shift}</em>}
                                    </label>
                                    <label>Tim PBL
                                        <select value={form.tim} onChange={(e) => setForm({ ...form, tim: e.target.value })}>
                                            <option value="">Pilih tim PBL</option>
                                            {props.options.pbl_tim.map((t) => <option key={t} value={t}>{t}</option>)}
                                        </select>
                                        {errors.tim && <em className="ad-mesin-err">{errors.tim}</em>}
                                    </label>
                                    <label>Angkatan
                                        <select value={form.angkatan} onChange={(e) => setForm({ ...form, angkatan: e.target.value })}>
                                            <option value="">Pilih angkatan</option>
                                            {props.options.angkatan.map((y) => <option key={y} value={y}>{y}</option>)}
                                        </select>
                                        {errors.angkatan && <em className="ad-mesin-err">{errors.angkatan}</em>}
                                    </label>
                                    <label>Semester
                                        <select value={form.semester} onChange={(e) => setForm({ ...form, semester: e.target.value })}>
                                            <option value="">Pilih semester</option>
                                            {Array.from({ length: 8 }, (_, i) => i + 1).map((s) => <option key={s} value={s}>Semester {s}</option>)}
                                        </select>
                                        {errors.semester && <em className="ad-mesin-err">{errors.semester}</em>}
                                    </label>
                                    <label>Periode Semester
                                        <select value={form.semester_type} onChange={(e) => setForm({ ...form, semester_type: e.target.value })}>
                                            <option value="">Pilih periode</option>
                                            <option value="ganjil">Ganjil</option>
                                            <option value="genap">Genap</option>
                                        </select>
                                        {errors.semester_type && <em className="ad-mesin-err">{errors.semester_type}</em>}
                                    </label>
                                    <label>Status Akademik
                                        <select value={form.academic_status} onChange={(e) => setForm({ ...form, academic_status: e.target.value })}>
                                            <option value="aktif">Aktif</option>
                                            <option value="cuti">Cuti</option>
                                            <option value="mengulang">Mengulang</option>
                                            <option value="lulus">Lulus</option>
                                            <option value="keluar">Keluar</option>
                                        </select>
                                    </label>
                                    <div className="ad-usr-full">
                                        <span className="ad-usr-hint">Finger ID diisi otomatis saat mahasiswa disimpan. Ubah manual lewat menu <b>Fingerprint</b> jika diperlukan.</span>
                                    </div>
                                </div>
                            )}

                            <div className="ad-mesin-confirm__actions">
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setForm(null)}>Batal</button>
                                <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary" disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ============ MODAL KODE SEKALI TAMPIL ============ */}
            {once && (
                <div className="ad-mesin-scrim">
                    <div className="ad-mesin-confirm ad-usr-once">
                        <span className="ad-usr-modal__badge">SEKALI TAMPIL</span>
                        <h3>{once.jenis === 'password sementara' ? 'Password Sementara Baru' : 'Kode Aktivasi Baru'}</h3>
                        <p className="ad-usr-modal__hint">
                            {once.jenis} untuk <b>{once.nama}</b> ({once.nitk}). Simpan sekarang — tidak akan ditampilkan lagi setelah modal ini ditutup.
                        </p>
                        <div className="ad-usr-code">
                            <code>{once.code}</code>
                            <button
                                type="button"
                                title={copied ? 'Tersalin' : 'Salin'}
                                className={copied ? 'ad-usr-copy--ok' : undefined}
                                onClick={() => {
                                    navigator.clipboard?.writeText(once.code);
                                    setCopied(true);
                                    window.setTimeout(() => setCopied(false), 2000);
                                }}
                            >
                                {copied ? <Check size={15} /> : <Copy size={15} />}
                            </button>
                        </div>
                        <small className="ad-usr-hint">Berlaku sampai {once.expires_at} · Entri: {once.entry_url}</small>
                        <div className="ad-mesin-confirm__actions">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => router.reload()}>Selesai — kode sudah disimpan</button>
                        </div>
                    </div>
                </div>
            )}

            {/* ============ MODAL KONFIRMASI ============ */}
            {confirm && (
                <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && setConfirm(null)}>
                    <div className="ad-mesin-confirm">
                        <h3>{confirm.title}</h3>
                        <p>{confirm.body}</p>
                        <div className="ad-mesin-confirm__actions">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setConfirm(null)}>Batal</button>
                            <button type="button" className={cn('ad-mesin-btn', confirm.danger ? 'ad-mesin-btn--danger' : 'ad-mesin-btn--primary')} disabled={busy} onClick={confirm.action}>
                                {busy ? 'Memproses…' : confirm.label}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
