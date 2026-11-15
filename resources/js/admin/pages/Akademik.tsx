import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { createPortal } from 'react-dom';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft, ArrowRight, ArrowUpCircle, CalendarRange, Check, CheckCircle2,
    Download, Filter as FilterIcon, GraduationCap, Pencil, PlayCircle, Search,
    Trash2, UserCheck, UserX, Users,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { AkademikProps, KenaikanPreviewRow } from '@admin/types';

const STEPS = ['Atur Periode', 'Pilih Mahasiswa', 'Konfirmasi'];
const STATUS_LABEL: Record<string, string> = {
    aktif: 'Aktif', cuti: 'Cuti', mengulang: 'Mengulang', lulus: 'Lulus', keluar: 'Keluar',
};

/** Tooltip "?" — hover = pratinjau sesaat, klik = popup menetap untuk dibaca.
 *  Hanya satu popup terbuka; klik di luar / klik "?" lain / scroll menutupnya.
 *  Popup di-render via portal + position:fixed supaya tidak terpotong
 *  container overflow; posisi otomatis membalik bila dekat tepi layar. */
function Tip({ text }: { text: string }) {
    const [pin, setPin] = useState(false);
    const [hover, setHover] = useState(false);
    const [pos, setPos] = useState<{ left: number; top: number; up: boolean } | null>(null);
    const btnRef = useRef<HTMLButtonElement>(null);
    const idRef = useRef(Symbol('tip'));

    useEffect(() => {
        const onOpen = (e: Event) => {
            if ((e as CustomEvent).detail !== idRef.current) { setPin(false); setHover(false); }
        };
        const onDocDown = (e: MouseEvent) => {
            if (!(e.target as HTMLElement).closest?.('.ad-tip, .ad-tip__popf')) setPin(false);
        };
        const onScroll = () => { setPin(false); setHover(false); };
        document.addEventListener('ad-tip-open', onOpen);
        document.addEventListener('mousedown', onDocDown);
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onScroll);
        return () => {
            document.removeEventListener('ad-tip-open', onOpen);
            document.removeEventListener('mousedown', onDocDown);
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onScroll);
        };
    }, []);

    const place = () => {
        const r = btnRef.current?.getBoundingClientRect();
        if (!r) return;
        const W = Math.min(260, window.innerWidth - 20);
        const left = Math.max(10, Math.min(r.left + r.width / 2 - W / 2, window.innerWidth - W - 10));
        const up = window.innerHeight - r.bottom < 150 && r.top > 160;
        setPos({ left, top: up ? r.top - 8 : r.bottom + 8, up });
    };

    const shown = pin || hover;

    return (
        <span className="ad-tip"
            onMouseEnter={() => { place(); setHover(true); }}
            onMouseLeave={() => setHover(false)}>
            <button ref={btnRef} type="button" className={cn('ad-tip__btn', pin && 'is-open')} aria-label="Bantuan"
                onClick={(e) => {
                    e.preventDefault(); e.stopPropagation();
                    if (!pin) document.dispatchEvent(new CustomEvent('ad-tip-open', { detail: idRef.current }));
                    place();
                    setPin(!pin);
                }}
            >?</button>
            {shown && pos && createPortal(
                <span className="ad-tip__popf" role="note"
                    style={{ left: pos.left, top: pos.top, transform: pos.up ? 'translateY(-100%)' : 'none' }}
                    onClick={(e) => e.stopPropagation()}>{text}</span>,
                document.body)}
        </span>
    );
}

/** Opsi kode periode: TAHUN-ganjil/genap untuk tahun ajaran sekitar sekarang. */
const kodeOptions = () => {
    const y = new Date().getFullYear();
    const out: { kode: string; label: string; jenis: string; mulai: string; selesai: string }[] = [];
    for (const th of [y - 1, y, y + 1, y + 2, y + 3]) {
        out.push({
            kode: `${th}-ganjil`, label: `Ganjil ${th}/${th + 1}`, jenis: 'ganjil',
            mulai: `${th}-09-01`, selesai: `${th + 1}-01-31`,
        });
        out.push({
            kode: `${th}-genap`, label: `Genap ${th}/${th + 1}`, jenis: 'genap',
            mulai: `${th + 1}-02-01`, selesai: `${th + 1}-08-31`,
        });
    }
    return out;
};

function Stepper({ step }: { step: number }) {
    return (
        <ol className="ad-imp-steps" aria-label="Tahapan kenaikan semester">
            {STEPS.map((label, i) => {
                const n = i + 1;
                const st = n < step ? 'done' : n === step ? 'now' : 'todo';
                return (
                    <li key={label} className={`ad-imp-steps__item ad-imp-steps__item--${st}`}>
                        <span className="ad-imp-steps__dot">{st === 'done' ? <Check size={13} /> : n}</span>
                        <span className="ad-imp-steps__label">{label}</span>
                        {n < STEPS.length && <span className="ad-imp-steps__line" aria-hidden />}
                    </li>
                );
            })}
        </ol>
    );
}

export default function Akademik(props: AkademikProps) {
    const pv = props.preview;
    const activePeriod = props.periods.find((p) => p.is_active) ?? null;

    const [step, setStep] = useState(1);
    const [checked, setChecked] = useState<Set<number>>(
        () => new Set(pv.naik.filter((s) => s.usulan === 'naik').map((s) => s.id)),
    );
    const [statusDraft, setStatusDraft] = useState<Record<number, string>>({});
    const [q, setQ] = useState('');
    const [fKelas, setFKelas] = useState('');
    const [busy, setBusy] = useState(false);

    const kelasOpts = useMemo(() =>
        [...new Set(pv.naik.map((s) => s.kelas).filter((k): k is string => !!k))].sort(), [pv.naik]);

    const rows = useMemo(() => {
        const term = q.trim().toLowerCase();
        return pv.naik.filter((s) => {
            if (fKelas && s.kelas !== fKelas) return false;
            if (!term) return true;
            return (s.nama ?? '').toLowerCase().includes(term)
                || (s.nim ?? '').toLowerCase().includes(term)
                || (s.kelas ?? '').toLowerCase().includes(term);
        });
    }, [pv.naik, q, fKelas]);

    const toggle = (id: number) => setChecked((s) => {
        const n = new Set(s);
        if (n.has(id)) n.delete(id); else n.add(id);
        return n;
    });
    const allChecked = rows.length > 0 && rows.every((s) => checked.has(s.id));
    const toggleAll = () => setChecked((s) => {
        const n = new Set(s);
        if (allChecked) rows.forEach((r) => n.delete(r.id));
        else rows.forEach((r) => n.add(r.id));
        return n;
    });

    const setStatus = (id: number, v: string) => {
        setStatusDraft((d) => {
            const n = { ...d };
            if (v === 'aktif') delete n[id]; else n[id] = v;
            return n;
        });
        if (v !== 'aktif') setChecked((c) => { const n = new Set(c); n.delete(id); return n; });
    };

    const exportCsv = () => {
        const head = 'nama;nim;kelas;angkatan;semester_saat_ini;usulan\n';
        const body = rows.map((s) =>
            `${s.nama};${s.nim ?? ''};${s.kelas ?? ''};${s.angkatan ?? ''};${s.semester ?? ''} ${s.semester_type ?? ''};${s.usulan === 'lulus' ? 'lulus' : `semester ${(s.semester ?? 0) + 1}`}`
        ).join('\n');
        const blob = new Blob(['﻿' + head + body], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `kenaikan-semester-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
    };

    const apply = () => {
        setBusy(true);
        router.post(props.urls.kenaikan, {
            ids: Array.from(checked),
            status_updates: statusDraft,
        } as never, { preserveScroll: true, onFinish: () => { setBusy(false); setStep(1); } });
    };

    // (createPeriod versi FormData digantikan state periodeForm di atas)

    const activate = (id: number) =>
        router.post(props.urls.aktivasi.replace('__ID__', String(id)), {}, { preserveScroll: true });

    // Form periode: kode dropdown → auto-isi label + jenis + tanggal default.
    const emptyForm = { kode: '', label: '', jenis: 'ganjil', mulai: '', selesai: '' };
    const [periodeForm, setPeriodeForm] = useState(emptyForm);
    const [editId, setEditId] = useState<number | null>(null);
    const editRow = props.periods.find((p) => p.id === editId) ?? null;
    const existingKode = useMemo(() => new Set(props.periods.map((p) => p.kode)), [props.periods]);
    const kodeOpts = useMemo(() => {
        const all = kodeOptions();
        let opts = all.filter((o) => !existingKode.has(o.kode));
        const latest = props.periods[0]; // sudah diurut tanggal_mulai desc dari server
        if (latest) {
            // Maju saja: periode baru hanya boleh setelah periode terakhir yang ada.
            opts = opts.filter((o) => o.mulai > latest.tanggal_mulai);
        } else {
            // Belum ada periode: mulai dari periode kalender yang sedang berjalan.
            const today = new Date().toISOString().slice(0, 10);
            const cur = all.find((o) => o.mulai <= today && o.selesai >= today)
                ?? all.find((o) => o.selesai >= today);
            if (cur) opts = opts.filter((o) => o.mulai >= cur.mulai);
        }
        // Saat edit: kode milik baris ini tetap ditawarkan walau sudah ada.
        if (editRow && !opts.some((o) => o.kode === editRow.kode)) {
            opts.unshift({ kode: editRow.kode, label: editRow.label, jenis: editRow.jenis, mulai: editRow.tanggal_mulai, selesai: editRow.tanggal_selesai });
        }
        return opts;
    }, [existingKode, editRow, props.periods]);
    const pickKode = (kode: string) => {
        const o = kodeOpts.find((x) => x.kode === kode);
        setPeriodeForm(o
            ? { kode: o.kode, label: o.label, jenis: o.jenis, mulai: o.mulai, selesai: o.selesai }
            : { kode: '', label: '', jenis: 'ganjil', mulai: '', selesai: '' });
    };

    const startEdit = (p: AkademikProps['periods'][number]) => {
        setEditId(p.id);
        setPeriodeForm({ kode: p.kode, label: p.label, jenis: p.jenis, mulai: p.tanggal_mulai, selesai: p.tanggal_selesai });
    };
    const cancelEdit = () => { setEditId(null); setPeriodeForm(emptyForm); };

    const submitPeriod = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const body = {
            kode: periodeForm.kode, label: periodeForm.label, jenis: periodeForm.jenis,
            tanggal_mulai: periodeForm.mulai, tanggal_selesai: periodeForm.selesai,
        } as never;
        if (editId) {
            router.put(props.urls.periode_update.replace('__ID__', String(editId)), body,
                { preserveScroll: true, onSuccess: cancelEdit });
        } else {
            router.post(props.urls.periode, body,
                { preserveScroll: true, onSuccess: () => setPeriodeForm(emptyForm) });
        }
    };

    const deletePeriod = (p: AkademikProps['periods'][number]) => {
        if (!confirm(`Hapus periode "${p.label}"? Riwayat laporan lama tidak ikut terhapus (tersimpan per rentang tanggal).`)) return;
        if (editId === p.id) cancelEdit();
        router.delete(props.urls.periode_delete.replace('__ID__', String(p.id)), { preserveScroll: true });
    };

    // Ringkasan konfirmasi: kelompok per transisi semester.
    const transisi = useMemo(() => {
        const m = new Map<string, number>();
        pv.naik.filter((s) => checked.has(s.id)).forEach((s) => {
            const key = s.usulan === 'lulus' ? `Semester ${s.semester ?? '?'} → Lulus` : `Semester ${s.semester ?? '?'} → Semester ${(s.semester ?? 0) + 1}`;
            m.set(key, (m.get(key) ?? 0) + 1);
        });
        return [...m.entries()].sort((a, b) => a[0].localeCompare(b[0]));
    }, [pv.naik, checked]);

    const statusCount = Object.keys(statusDraft).length;

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Akademik"
            subtitle="Manajemen periode dan kenaikan semester mahasiswa">
            <Head title="Akademik" />

            <div className="ad-imp-hero">
                <div>
                    <h2>Kenaikan Semester</h2>
                    <p>Kelola proses kenaikan semester mahasiswa (ganjil → genap). Tentukan periode akademik, pilih mahasiswa, lalu terapkan kenaikan semester.</p>
                </div>
                <div className="ad-imp-hero__art" aria-hidden><GraduationCap size={40} strokeWidth={1.4} /></div>
            </div>

            {/* KPI */}
            <div className="ad-imp-kpi" style={{ margin: '12px 0' }}>
                <div className="ad-imp-kpi__item ad-imp-kpi__item--blue"><strong>{pv.naik.length + pv.tahan.length}</strong><span>Total Mahasiswa</span></div>
                <div className="ad-imp-kpi__item ad-imp-kpi__item--green"><strong>{pv.naik.filter((s) => s.usulan === 'naik').length}</strong><span>Siap Naik Semester</span></div>
                <div className="ad-imp-kpi__item ad-imp-kpi__item--amber"><strong>{pv.tahan.length}</strong><span>Dikecualikan</span></div>
                <div className="ad-imp-kpi__item">
                    <strong style={{ fontSize: 15 }}>{activePeriod ? activePeriod.label : '—'}</strong>
                    <span>Periode Aktif{activePeriod ? ` (${activePeriod.jenis})` : ''}</span>
                </div>
            </div>

            <Stepper step={step} />

            {/* ═══ LANGKAH 1 — Atur Periode ═══ */}
            {step === 1 && (
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2><CalendarRange size={15} style={{ verticalAlign: -2 }} /> Periode Akademik</h2>
                            <p>Atur rentang semester ganjil/genap sebagai dasar proses kenaikan semester dan filter laporan.</p>
                        </div>
                        <div className="ad-mesin-btnrow">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setStep(2)} disabled={props.periods.length === 0}>
                                Lanjut <ArrowRight size={14} />
                            </button>
                        </div>
                    </div>

                    {activePeriod && (
                        <div className="ad-imp-info" style={{ marginBottom: 12, borderColor: 'var(--ad-green)' }}>
                            <strong><CheckCircle2 size={14} style={{ verticalAlign: -2, color: 'var(--ad-green)' }} /> Periode Aktif: {activePeriod.label}</strong>
                            <p>{activePeriod.tanggal_mulai} s/d {activePeriod.tanggal_selesai} · {activePeriod.jenis}</p>
                        </div>
                    )}

                    <form className="ad-imp-tpl" onSubmit={submitPeriod}>
                        {editRow && (
                            <div className="ad-imp-info" style={{ marginBottom: 10 }}>
                                <strong><Pencil size={13} style={{ verticalAlign: -2 }} /> Mode edit: {editRow.label}</strong>
                                <p>Mengubah tanggal ikut menggeser rentang laporan untuk periode ini.</p>
                            </div>
                        )}
                        <div className="ad-akd-form">
                            <label className="ad-akd-field">
                                <span className="ad-usr-hint">Kode Periode <em className="ad-akd-req">wajib</em> <Tip text="Identitas unik periode akademik, format TAHUN-jenis (mis. 2026-ganjil). Hanya bisa dibuat MAJU dari periode terakhir — periode yang sudah lewat tidak ditawarkan, riwayatnya tetap terbaca di laporan. Koreksi isian lewat tombol Edit." /></span>
                                <select className="ad-imp-select" value={periodeForm.kode}
                                    onChange={(e) => pickKode(e.target.value)} required>
                                    <option value="">Pilih kode…</option>
                                    {kodeOpts.map((o) => <option key={o.kode} value={o.kode}>{o.kode}</option>)}
                                </select>
                            </label>
                            <label className="ad-akd-field ad-akd-field--wide">
                                <span className="ad-usr-hint">Label Periode <em className="ad-akd-opt">opsional</em> <Tip text="Nama tampil di laporan, filter, dan halaman ini. Otomatis terisi dari kode (mis. 'Ganjil 2025/2026') — boleh dikosongkan atau diganti bebas." /></span>
                                <input className="ad-imp-select" value={periodeForm.label}
                                    onChange={(e) => setPeriodeForm({ ...periodeForm, label: e.target.value })}
                                    placeholder="Otomatis dari kode" />
                            </label>
                            <label className="ad-akd-field">
                                <span className="ad-usr-hint">Jenis <em className="ad-akd-req">wajib</em> <Tip text="Ganjil = semester gasal (umumnya Sep–Jan), Genap = semester genap (umumnya Feb–Agu). Menentukan arah kenaikan: ganjil → genap." /></span>
                                <select className="ad-imp-select" value={periodeForm.jenis}
                                    onChange={(e) => setPeriodeForm({ ...periodeForm, jenis: e.target.value })} required>
                                    <option value="ganjil">Ganjil</option><option value="genap">Genap</option>
                                </select>
                            </label>
                            <label className="ad-akd-field">
                                <span className="ad-usr-hint">Tanggal Mulai <em className="ad-akd-req">wajib</em> <Tip text="Hari pertama periode akademik. Batas bawah saat laporan difilter 'Periode akademik (semester)'. Otomatis terisi dari kode." /></span>
                                <input type="date" className="ad-imp-select" value={periodeForm.mulai}
                                    onChange={(e) => setPeriodeForm({ ...periodeForm, mulai: e.target.value })} required />
                            </label>
                            <label className="ad-akd-field">
                                <span className="ad-usr-hint">Tanggal Selesai <em className="ad-akd-req">wajib</em> <Tip text="Hari terakhir periode. Absensi di luar rentang ini tidak masuk laporan periode ini. Otomatis terisi dari kode." /></span>
                                <input type="date" className="ad-imp-select" value={periodeForm.selesai}
                                    onChange={(e) => setPeriodeForm({ ...periodeForm, selesai: e.target.value })} required />
                            </label>
                            <span className="ad-akd-field ad-akd-field--act">
                                <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary" disabled={!periodeForm.kode}>
                                    {editId ? 'Perbarui Periode' : 'Simpan Periode'}
                                </button>
                                {editId && (
                                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={cancelEdit}>Batal</button>
                                )}
                                <Tip text={editId
                                    ? 'Menyimpan perubahan periode yang dipilih lewat tombol Edit. Rentang tanggal baru langsung dipakai laporan.'
                                    : 'Membuat periode akademik baru. Periode yang dibuat bisa dipilih sebagai filter laporan dan diaktifkan sebagai semester berjalan.'} />
                            </span>
                        </div>
                        {kodeOpts.length === 0 && (
                            <p className="ad-imp-tpl__hint">Belum ada periode lanjutan yang tersedia — periode baru hanya bisa dibuat maju dari periode terakhir. Koreksi periode lama lewat tombol Edit.</p>
                        )}
                    </form>

                    {props.periods.length > 0 && (
                        <div className="ad-imp-twrap" style={{ marginTop: 12 }}>
                            <table className="ad-imp-table">
                                <thead><tr><th>Label</th><th>Kode</th><th>Jenis</th><th>Mulai</th><th>Selesai</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    {props.periods.map((p) => (
                                        <tr key={p.id}>
                                            <td><b>{p.label}</b></td>
                                            <td><code>{p.kode}</code></td>
                                            <td><span className={cn('ad-pill', p.jenis === 'ganjil' ? 'ad-pill--info' : 'ad-pill--ok')}>{p.jenis}</span></td>
                                            <td>{p.tanggal_mulai}</td>
                                            <td>{p.tanggal_selesai}</td>
                                            <td>{p.is_active
                                                ? <span className="ad-pill ad-pill--ok"><CheckCircle2 size={11} /> Aktif</span>
                                                : <span className="ad-pill">—</span>}</td>
                                            <td>
                                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                    {!p.is_active && (
                                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => activate(p.id)}>
                                                            <PlayCircle size={13} /> Jadikan aktif
                                                        </button>
                                                    )}
                                                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost"
                                                        onClick={() => startEdit(p)} aria-label={`Edit ${p.label}`}
                                                        disabled={editId === p.id}>
                                                        <Pencil size={13} /> Edit
                                                    </button>
                                                    {!p.is_active && (
                                                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost"
                                                            style={{ color: 'var(--ad-red)' }}
                                                            onClick={() => deletePeriod(p)} aria-label={`Hapus ${p.label}`}>
                                                            <Trash2 size={13} /> Hapus
                                                        </button>
                                                    )}
                                                    <Tip text={p.is_active
                                                        ? 'Periode aktif tidak bisa dihapus — aktifkan periode lain dulu bila ingin menghapusnya.'
                                                        : "Jadikan aktif = periode ini jadi semester berjalan (periode lain otomatis nonaktif). Edit = koreksi kode/label/tanggal. Hapus = menghapus periode; riwayat laporan lama tetap aman karena tersimpan per rentang tanggal."} />
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}

            {/* ═══ LANGKAH 2 — Pilih Mahasiswa ═══ */}
            {step === 2 && (
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2><ArrowUpCircle size={15} style={{ verticalAlign: -2 }} /> Daftar Mahasiswa untuk Kenaikan Semester</h2>
                            <p>Daftar mahasiswa aktif yang akan naik satu semester (Ganjil → Genap; Semester 8 → Lulus). Tandai <b>cuti/mengulang</b> lewat kolom status agar tidak ikut naik.</p>
                        </div>
                        <div className="ad-mesin-btnrow">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setStep(1)}>
                                <ArrowLeft size={14} /> Periode
                            </button>
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => setStep(3)}>
                                Pratinjau Perubahan <ArrowRight size={14} />
                            </button>
                        </div>
                    </div>

                    <div className="ad-mesin-btnrow" style={{ marginBottom: 10 }}>
                        <label className="ad-usr-search" style={{ flex: '1 1 260px' }}>
                            <Search size={14} />
                            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari nama, NIM, atau kelas…" />
                        </label>
                        <select className="ad-imp-select" value={fKelas} onChange={(e) => setFKelas(e.target.value)}>
                            <option value="">Semua kelas</option>
                            {kelasOpts.map((k) => <option key={k} value={k}>{k}</option>)}
                        </select>
                        <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={exportCsv}>
                            <Download size={14} /> Export
                        </button>
                    </div>

                    <div className="ad-imp-twrap" style={{ maxHeight: 420, overflowY: 'auto' }}>
                        <table className="ad-imp-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 30 }}>
                                        <input type="checkbox" checked={allChecked} onChange={toggleAll} />
                                    </th>
                                    <th>No</th><th>Nama Mahasiswa</th><th>NIM</th><th>Kelas</th>
                                    <th>Angkatan</th>
                                    <th>Semester Saat Ini <Tip text="Semester mahasiswa saat ini di data akun (angka + jenis ganjil/genap)." /></th>
                                    <th>Usulan Semester <Tip text="Tujuan otomatis bila ikut kenaikan: semester+1 (ganjil↔genap bergantian). Mahasiswa semester 8 diusulkan 'Lulus', bukan semester 9." /></th>
                                    <th>Status Akademik <Tip text="Ubah ke Cuti/Mengulang untuk menahan mahasiswa agar TIDAK ikut naik. Pilihan non-Aktif otomatis menghapus centang baris itu." /></th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr><td colSpan={9}><div className="ad-empty">Tidak ada mahasiswa cocok filter.</div></td></tr>
                                )}
                                {rows.map((s, i) => (
                                    <tr key={s.id}>
                                        <td><input type="checkbox" checked={checked.has(s.id)} onChange={() => toggle(s.id)} /></td>
                                        <td>{i + 1}</td>
                                        <td><b>{s.nama}</b></td>
                                        <td>{s.nim ?? '—'}</td>
                                        <td>{s.kelas ?? '—'}</td>
                                        <td>{s.angkatan ?? '—'}</td>
                                        <td>{s.semester ?? '—'} {s.semester_type ? `(${s.semester_type.charAt(0).toUpperCase() + s.semester_type.slice(1)})` : ''}</td>
                                        <td>
                                            {s.usulan === 'lulus'
                                                ? <span className="ad-pill ad-pill--warn"><GraduationCap size={11} /> Lulus</span>
                                                : <span className="ad-pill ad-pill--info">{(s.semester ?? 0) + 1} ({((s.semester ?? 0) + 1) % 2 === 1 ? 'Ganjil' : 'Genap'})</span>}
                                        </td>
                                        <td>
                                            <select className="ad-imp-select" style={{ padding: '5px 26px 5px 8px', fontSize: 12 }}
                                                value={statusDraft[s.id] ?? 'aktif'}
                                                onChange={(e) => setStatus(s.id, e.target.value)}>
                                                {pv.status_options.map((o) => <option key={o} value={o}>{STATUS_LABEL[o] ?? o}</option>)}
                                            </select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="ad-mesin-btnrow" style={{ justifyContent: 'space-between', marginTop: 10 }}>
                        <span className="ad-usr-hint">
                            <b>{checked.size} mahasiswa dipilih</b>
                            {' · '}<a href="#" onClick={(e) => { e.preventDefault(); setChecked(new Set(pv.naik.map((r) => r.id))); }}>Pilih semua ({pv.naik.length})</a>
                            {' · '}<a href="#" onClick={(e) => { e.preventDefault(); setChecked(new Set()); }}>Bersihkan</a>
                        </span>
                        <span className="ad-usr-hint">
                            {pv.tahan.length} dikecualikan (cuti/mengulang) · {pv.selesai_count} lulus/keluar
                        </span>
                    </div>

                    {pv.tahan.length > 0 && (
                        <details style={{ marginTop: 10 }}>
                            <summary className="ad-usr-hint" style={{ cursor: 'pointer' }}>
                                <UserX size={13} style={{ verticalAlign: -2 }} /> {pv.tahan.length} mahasiswa ditahan — tidak ikut naik
                            </summary>
                            <div className="ad-imp-twrap" style={{ marginTop: 8 }}>
                                <table className="ad-imp-table">
                                    <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Kelas</th><th>Semester</th><th>Status</th></tr></thead>
                                    <tbody>
                                        {pv.tahan.map((s) => (
                                            <tr key={s.id}>
                                                <td><b>{s.nama}</b></td><td>{s.nim ?? '—'}</td>
                                                <td>{s.kelas ?? '—'}</td><td>{s.semester ?? '—'}</td>
                                                <td><span className="ad-pill ad-pill--warn">{STATUS_LABEL[s.status] ?? s.status}</span></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    )}
                </section>
            )}

            {/* ═══ LANGKAH 3 — Konfirmasi ═══ */}
            {step === 3 && (
                <section className="ad-panel">
                    <div className="ad-panel__head">
                        <div>
                            <h2><UserCheck size={15} style={{ verticalAlign: -2 }} /> Konfirmasi Kenaikan Semester</h2>
                            <p>Tinjau ringkasan perubahan sebelum diterapkan — tindakan ini langsung mengubah data semester mahasiswa dan tercatat di Jejak Aktivitas.</p>
                        </div>
                        <div className="ad-mesin-btnrow">
                            <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setStep(2)}>
                                <ArrowLeft size={14} /> Kembali
                            </button>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--primary"
                                    disabled={busy || (checked.size === 0 && statusCount === 0)} onClick={apply}>
                                    <CheckCircle2 size={14} /> {busy ? 'Menerapkan…' : 'Terapkan Kenaikan Semester'}
                                </button>
                                <Tip text="Menerapkan dalam satu transaksi: mahasiswa tercentang naik satu semester (sem. 8 → lulus) + semua perubahan status. Tercatat di Jejak Aktivitas." />
                            </span>
                        </div>
                    </div>

                    <div className="ad-imp-grid3">
                        <div className="ad-imp-info">
                            <span className="ad-imp-info__ic ad-imp-info__ic--green"><UserCheck size={16} /></span>
                            <strong>{checked.size} mahasiswa naik</strong>
                            <p>Masing-masing naik satu semester; semester 8 otomatis ditandai lulus.</p>
                        </div>
                        <div className="ad-imp-info">
                            <span className="ad-imp-info__ic ad-imp-info__ic--violet"><FilterIcon size={16} /></span>
                            <strong>{statusCount} status diubah</strong>
                            <p>Perubahan status akademik (cuti/mengulang/dll) ikut diterapkan.</p>
                        </div>
                        <div className="ad-imp-info">
                            <span className="ad-imp-info__ic ad-imp-info__ic--blue"><CalendarRange size={16} /></span>
                            <strong>{activePeriod ? activePeriod.label : 'Belum ada periode aktif'}</strong>
                            <p>Periode aktif saat ini — pastikan sudah sesuai sebelum menerapkan.</p>
                        </div>
                    </div>

                    {transisi.length > 0 && (
                        <div className="ad-imp-twrap" style={{ marginTop: 12 }}>
                            <table className="ad-imp-table">
                                <thead><tr><th>Transisi</th><th>Jumlah</th></tr></thead>
                                <tbody>
                                    {transisi.map(([k, n]) => (
                                        <tr key={k}><td><b>{k}</b></td><td>{n} mahasiswa</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {statusCount > 0 && (
                        <p className="ad-usr-hint" style={{ marginTop: 10 }}>
                            <Users size={13} style={{ verticalAlign: -2 }} /> Status baru:
                            {' '}{Object.entries(statusDraft).map(([id, st]) => {
                                const s = pv.naik.find((r) => r.id === Number(id)) ?? pv.tahan.find((r) => r.id === Number(id));
                                return s ? `${s.nama} → ${STATUS_LABEL[st] ?? st}` : null;
                            }).filter(Boolean).join(' · ')}
                        </p>
                    )}
                </section>
            )}
        </AdminShell>
    );
}
