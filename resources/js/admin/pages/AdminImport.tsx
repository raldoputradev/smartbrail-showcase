import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, ArrowLeft, ArrowRight, BadgeCheck, Check, CheckCircle2,
    ClipboardCheck, CloudUpload, Download, FileSpreadsheet, FileUp, Info,
    PartyPopper, ShieldCheck, Upload, Users, X, XCircle,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import type { AdminImportProps, AdminImportRow } from '@admin/types';

const STEPS = ['Informasi', 'Unggah File', 'Validasi', 'Hasil Validasi', 'Konfirmasi', 'Selesai'];
const CHECKS = [
    'Membaca file data pengguna',
    'Memetakan format kolom',
    'Validasi kelengkapan data',
    'Mengecek duplikasi data',
    'Menyiapkan hasil validasi',
];
const ROLE_DESC: Record<string, string> = {
    mahasiswa: 'NIM, nama, kelas, tim, angkatan, semester, shift, prodi',
    dosen: 'NIDN / NITK dan nama lengkap',
    laboran: 'NITK dan nama lengkap',
    semua: 'File campur ala Template_Import_Akun — kolom nim/nidn/nitk terpisah, role disimpulkan otomatis; kolom mahasiswa wajib untuk baris mahasiswa',
    petugas: 'ID internal, nama, tipe_kredensial, kredensial — petugas/CS akses lab lewat mesin, tanpa akun & login web',
};
const PAGE_SIZE = 10;

const chipCls = (s: string) =>
    s === 'siap' || s === 'dibuat' ? 'ok' : s === 'duplikat' ? 'dup' : s === 'gagal' ? 'err' : 'skip';

function Stepper({ step }: { step: number }) {
    return (
        <ol className="ad-imp-steps" aria-label="Tahapan import akun">
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

export default function AdminImport(props: AdminImportProps) {
    const { errors } = usePage().props as unknown as { errors: Record<string, string> };
    const applied = props.payload?.state === 'applied';
    const [step, setStep] = useState<number>(
        props.mode === 'result' ? (applied ? 6 : 4) : (errors.file || errors.jenis ? 2 : 1),
    );
    const [jenis, setJenis] = useState<string>(props.selected || 'mahasiswa');
    const [file, setFile] = useState<File | null>(null);
    const [drag, setDrag] = useState(false);
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    const [tab, setTab] = useState<'all' | 'siap' | 'duplikat' | 'gagal'>('all');
    const [page, setPage] = useState(1);
    const fileRef = useRef<HTMLInputElement>(null);
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

    // Respons server baru (preview/apply) → maju ke langkah hasil yang sesuai.
    useEffect(() => {
        if (props.mode === 'result' && props.payload) {
            setStep(props.payload.state === 'applied' ? 6 : 4);
            setBusy(false);
            setProgress(100);
        }
    }, [props.payload?.token, props.payload?.state, props.mode]);

    // Animasi progres validasi selama POST berjalan (maks 92%, penuh saat server merespons).
    useEffect(() => {
        if (step !== 3 || !busy) return;
        const t = setInterval(() => setProgress((p) => Math.min(92, p + Math.max(1, (92 - p) * 0.06))), 120);
        return () => clearInterval(t);
    }, [step, busy]);

    const totals = props.payload?.totals;
    const rows = useMemo(() => props.payload?.rows ?? [], [props.payload]);
    const filtered = useMemo(() => {
        if (tab === 'all') return rows;
        if (tab === 'gagal') return rows.filter((r) => r.status === 'gagal' || r.status === 'dilewati');
        return rows.filter((r) => r.status === tab);
    }, [rows, tab]);
    const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const pageRows = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
    useEffect(() => setPage(1), [tab]);

    const pick = (f: File | null) => {
        if (!f) return;
        if (!/\.(xlsx|csv)$/i.test(f.name)) { alert('Gunakan file .xlsx atau .csv.'); return; }
        if (f.size > 2 * 1024 * 1024) { alert('Ukuran file melebihi batas 2 MB.'); return; }
        setFile(f);
    };

    const submitPreview = () => {
        if (!file) return;
        setBusy(true);
        setProgress(8);
        setStep(3);
        router.post(props.urls.preview, { jenis, file }, {
            forceFormData: true,
            onError: () => { setBusy(false); setStep(2); },
            onFinish: () => setBusy(false),
        });
    };

    const submitApply = () => {
        if (!props.result_urls) return;
        setBusy(true);
        router.post(props.result_urls.apply, {}, { onFinish: () => setBusy(false) });
    };

    const labels = props.labels ?? {};
    const siap = totals?.siap ?? 0;
    const checkCount = Math.round((progress / 100) * CHECKS.length);

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Import Akun Pengguna"
            subtitle="Tambahkan banyak akun dari Excel/CSV dengan panduan yang benar.">
            <Head title="Import Akun Pengguna" />
            <Stepper step={step} />

            {/* ============ 1. Informasi & Template ============ */}
            {step === 1 && (
                <section className="ad-imp-panel">
                    <div className="ad-imp-hero">
                        <div className="ad-imp-hero__txt">
                            <h2>Import Akun Pengguna</h2>
                            <p>Proses penambahan akun massal untuk Mahasiswa, Dosen, dan Laboran menggunakan file Excel (.xlsx) atau CSV (.csv).</p>
                        </div>
                        <div className="ad-imp-hero__art" aria-hidden>
                            <FileSpreadsheet size={34} />
                            <Upload size={46} />
                        </div>
                    </div>

                    <div className="ad-imp-grid3">
                        <div className="ad-imp-info">
                            <span className="ad-imp-info__ic ad-imp-info__ic--blue"><BadgeCheck size={18} /></span>
                            <strong>Pendaftaran mandiri</strong>
                            <p>Nomor, email, &amp; password pemilik akun diisi sendiri lewat aktivasi — admin tidak mengisi.</p>
                        </div>
                        <div className="ad-imp-info">
                            <span className="ad-imp-info__ic ad-imp-info__ic--cyan"><ShieldCheck size={18} /></span>
                            <strong>Validasi otomatis</strong>
                            <p>Cek format &amp; duplikasi baris sebelum akun dibuat — tidak ada yang diproses asal.</p>
                        </div>
                        <div className="ad-imp-info">
                            <span className="ad-imp-info__ic ad-imp-info__ic--violet"><ClipboardCheck size={18} /></span>
                            <strong>Laporan hasil</strong>
                            <p>Ringkasan &amp; rincian per baris bisa diunduh setelah import selesai.</p>
                        </div>
                    </div>

                    <div className="ad-imp-tpl">
                        <div className="ad-imp-tpl__head">
                            <div>
                                <h3><Download size={16} /> Unduh Template</h3>
                                <p>Setiap jenis akun punya file template sendiri (sheet PETUNJUK + IMPORT_AKUN). Akun dibuat dengan password sementara — pemilik wajib menggantinya saat login pertama.</p>
                            </div>
                            <button type="button" className="ad-imp-btn ad-imp-btn--ghost">Informasi</button>
                        </div>
                        <div className="ad-imp-tpl__row">
                            <select value={jenis} onChange={(e) => setJenis(e.target.value)} className="ad-imp-select" aria-label="Jenis akun">
                                {Object.entries(props.types).map(([k, t]) => (
                                    <option key={k} value={k} disabled={!t.allowed}>{t.label}</option>
                                ))}
                            </select>
                            <a className="ad-imp-btn" href={props.urls.templates[jenis]?.xlsx}><FileSpreadsheet size={15} /> Template .xlsx</a>
                            <a className="ad-imp-btn ad-imp-btn--ghost" href={props.urls.templates[jenis]?.csv}><Download size={15} /> Template .csv</a>
                        </div>
                        <p className="ad-imp-tpl__hint"><Info size={13} /> Kolom {props.types[jenis]?.label ?? jenis}: {ROLE_DESC[jenis] ?? props.types[jenis]?.columns.join(', ')}</p>
                    </div>

                    <div className="ad-imp-foot">
                        <span />
                        <button type="button" className="ad-imp-btn ad-imp-btn--next" onClick={() => setStep(2)}>
                            Lanjutkan <ArrowRight size={16} />
                        </button>
                    </div>
                </section>
            )}

            {/* ============ 2. Unggah File ============ */}
            {step === 2 && (
                <section className="ad-imp-panel">
                    <h2 className="ad-imp-h2">Unggah File</h2>
                    <p className="ad-imp-sub">Pilih file unggah file Excel (.xlsx) atau CSV (.csv) yang berisi data pengguna.</p>

                    {(errors.file || errors.jenis) && (
                        <div className="ad-imp-alert ad-imp-alert--err" role="alert">
                            <XCircle size={15} /> {errors.file ?? errors.jenis}
                        </div>
                    )}

                    <label className="ad-imp-field">
                        <span>Jenis akun</span>
                        <select value={jenis} onChange={(e) => setJenis(e.target.value)} className="ad-imp-select">
                            {Object.entries(props.types).map(([k, t]) => (
                                <option key={k} value={k} disabled={!t.allowed}>{t.label}</option>
                            ))}
                        </select>
                    </label>

                    <div
                        className={`ad-imp-drop ${drag ? 'ad-imp-drop--drag' : ''}`}
                        onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
                        onDragLeave={() => setDrag(false)}
                        onDrop={(e) => { e.preventDefault(); setDrag(false); pick(e.dataTransfer.files?.[0] ?? null); }}
                        onClick={() => fileRef.current?.click()}
                        role="button" tabIndex={0}
                        onKeyDown={(e) => e.key === 'Enter' && fileRef.current?.click()}
                    >
                        <input ref={fileRef} type="file" accept=".xlsx,.csv" hidden
                            onChange={(e) => pick(e.target.files?.[0] ?? null)} />
                        <span className="ad-imp-drop__ic"><CloudUpload size={40} /></span>
                        <strong>Seret dan jatuhkan file di sini</strong>
                        <p>atau klik untuk memilih file</p>
                        <span className="ad-imp-btn ad-imp-btn--mini">Pilih File</span>
                        <small>Format: .xlsx atau .csv · Maks. 10 MB</small>
                    </div>

                    {file && (
                        <div className="ad-imp-file">
                            <FileSpreadsheet size={17} />
                            <div className="ad-imp-file__meta">
                                <strong>{file.name}</strong>
                                <small>{(file.size / 1024).toFixed(1)} KB</small>
                            </div>
                            <button type="button" className="ad-imp-file__x" onClick={() => setFile(null)} aria-label="Hapus file"><X size={15} /></button>
                        </div>
                    )}

                    <div className="ad-imp-foot">
                        <button type="button" className="ad-imp-btn ad-imp-btn--ghost" onClick={() => setStep(1)}>
                            <ArrowLeft size={15} /> Kembali
                        </button>
                        <button type="button" className="ad-imp-btn ad-imp-btn--next" disabled={!file} onClick={submitPreview}>
                            Lanjutkan <ArrowRight size={16} />
                        </button>
                    </div>
                </section>
            )}

            {/* ============ 3. Proses Validasi ============ */}
            {step === 3 && (
                <section className="ad-imp-panel ad-imp-panel--center">
                    <span className="ad-imp-val__ic" aria-hidden><FileSpreadsheet size={44} /></span>
                    <h2 className="ad-imp-h2">Memvalidasi Data…</h2>
                    <p className="ad-imp-sub">Sistem sedang memeriksa format, kelengkapan, dan duplikasi data.</p>
                    <div className="ad-imp-bar" role="progressbar" aria-valuenow={Math.round(progress)} aria-valuemin={0} aria-valuemax={100}>
                        <div className="ad-imp-bar__fill" style={{ width: `${progress}%` }} />
                    </div>
                    <span className="ad-imp-bar__pct">{Math.round(progress)}%</span>
                    <ul className="ad-imp-checks">
                        {CHECKS.map((c, i) => {
                            const st = i < checkCount ? 'done' : i === checkCount ? 'run' : 'todo';
                            return (
                                <li key={c} className={`ad-imp-checks__item ad-imp-checks__item--${st}`}>
                                    {st === 'done' ? <CheckCircle2 size={15} /> : st === 'run' ? <span className="ad-imp-spin" /> : <span className="ad-imp-checks__dot" />}
                                    {c}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}

            {/* ============ 4. Hasil Validasi ============ */}
            {step === 4 && props.payload && totals && (
                <section className="ad-imp-panel">
                    <h2 className="ad-imp-h2">Hasil Validasi Data</h2>
                    <p className="ad-imp-sub">
                        {props.payload.file_name} · {props.type_label} ·
                        {props.payload.notices.length > 0 && ` ${props.payload.notices.join(' ')}`}
                    </p>

                    <div className="ad-imp-kpi">
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--blue"><strong>{totals.total}</strong><span>Total Baris</span></div>
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--green"><strong>{totals.siap}</strong><span>Data Valid</span></div>
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--amber"><strong>{totals.gagal + totals.dilewati}</strong><span>Perlu Diperbaiki</span></div>
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--red"><strong>{totals.duplikat}</strong><span>Duplikat</span></div>
                    </div>

                    <div className="ad-imp-tabs" role="tablist">
                        {([
                            ['all', `Semua Data (${totals.total})`],
                            ['siap', `Data Valid (${totals.siap})`],
                            ['gagal', `Perlu Diperbaiki (${totals.gagal + totals.dilewati})`],
                            ['duplikat', `Duplikat (${totals.duplikat})`],
                        ] as const).map(([k, l]) => (
                            <button key={k} type="button" role="tab" aria-selected={tab === k}
                                className={`ad-imp-tabs__btn ${tab === k ? 'ad-imp-tabs__btn--on' : ''}`}
                                onClick={() => setTab(k)}>{l}</button>
                        ))}
                    </div>

                    <div className="ad-imp-twrap">
                        <table className="ad-imp-table">
                            <thead>
                                <tr><th>No</th><th>{props.id_label ?? 'Identitas'}</th><th>Nama</th><th>Status</th><th>Catatan</th></tr>
                            </thead>
                            <tbody>
                                {pageRows.map((r: AdminImportRow, i) => (
                                    <tr key={`${r.line}-${i}`}>
                                        <td>{(page - 1) * PAGE_SIZE + i + 1}</td>
                                        <td className="mono">{r.identifier || '—'}</td>
                                        <td>{r.nama || '—'}</td>
                                        <td><span className={`ad-imp-chip ad-imp-chip--${chipCls(r.status)}`}>{labels[r.status] ?? r.status}</span></td>
                                        <td className="ad-imp-table__note">{r.reason}</td>
                                    </tr>
                                ))}
                                {pageRows.length === 0 && (
                                    <tr><td colSpan={5} className="ad-imp-table__empty">Tidak ada baris pada kategori ini.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {pages > 1 && (
                        <div className="ad-imp-pager">
                            {Array.from({ length: pages }, (_, i) => i + 1).map((n) => (
                                <button key={n} type="button"
                                    className={`ad-imp-pager__btn ${n === page ? 'ad-imp-pager__btn--on' : ''}`}
                                    onClick={() => setPage(n)}>{n}</button>
                            ))}
                        </div>
                    )}

                    <div className="ad-imp-foot">
                        <a className="ad-imp-btn ad-imp-btn--ghost" href={props.urls.index}><ArrowLeft size={15} /> Kembali</a>
                        <button type="button" className="ad-imp-btn ad-imp-btn--next" disabled={siap === 0} onClick={() => setStep(5)}>
                            Lanjutkan ke Konfirmasi <ArrowRight size={16} />
                        </button>
                    </div>
                    {siap === 0 && <p className="ad-imp-warnline"><AlertTriangle size={13} /> Tidak ada baris valid — perbaiki file lalu unggah ulang.</p>}
                </section>
            )}

            {/* ============ 5. Konfirmasi Pembuatan Akun ============ */}
            {step === 5 && props.payload && totals && (
                <section className="ad-imp-panel ad-imp-panel--center">
                    <h2 className="ad-imp-h2">Konfirmasi Pembuatan Akun</h2>
                    <p className="ad-imp-sub">Pastikan kembali data yang akan dibuat akunnya.</p>

                    <div className="ad-imp-sum">
                        <span className="ad-imp-sum__ic"><Users size={26} /></span>
                        <p>Akun yang akan dibuat</p>
                        <strong className="ad-imp-sum__n">{totals.siap} akun</strong>
                        <ul>
                            <li>Jenis akun: <b>{props.type_label}</b></li>
                            <li>Sumber file: <b>{props.payload.file_name}</b></li>
                        </ul>
                    </div>

                    <ul className="ad-imp-notes">
                        <li><Info size={14} /> Hanya data yang valid akan dibuatkan akun.</li>
                        {totals.gagal + totals.dilewati > 0 && <li><AlertTriangle size={14} /> {totals.gagal + totals.dilewati} data perlu diperbaiki, tidak akan diproses.</li>}
                        {totals.duplikat > 0 && <li><AlertTriangle size={14} /> {totals.duplikat} data duplikat tidak akan diproses.</li>}
                        <li><CheckCircle2 size={14} /> Anda bisa mengunduh laporan lengkap setelah proses selesai.</li>
                    </ul>

                    <div className="ad-imp-foot">
                        <button type="button" className="ad-imp-btn ad-imp-btn--ghost" onClick={() => setStep(4)} disabled={busy}>
                            <ArrowLeft size={15} /> Kembali
                        </button>
                        {props.can_apply
                            ? <button type="button" className="ad-imp-btn ad-imp-btn--next" onClick={submitApply} disabled={busy}>
                                {busy ? 'Memproses…' : <><Check size={16} /> Buat Akun ({totals.siap}) <ArrowRight size={16} /></>}
                            </button>
                            : <span className="ad-imp-warnline"><AlertTriangle size={13} /> Anda tidak berwenang membuat akun {props.type_label}.</span>}
                    </div>
                </section>
            )}

            {/* ============ 6. Selesai — Laporan ============ */}
            {step === 6 && props.payload && totals && (
                <section className="ad-imp-panel ad-imp-panel--center">
                    <span className="ad-imp-done" aria-hidden><CheckCircle2 size={64} /><PartyPopper size={22} className="ad-imp-done__pop" /></span>
                    <h2 className="ad-imp-h2">Import Akun Berhasil!</h2>
                    <p className="ad-imp-sub">Proses import telah selesai dan {totals.dibuat} akun berhasil dibuat.</p>

                    <div className="ad-imp-kpi">
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--green"><strong>{totals.dibuat}</strong><span>Akun Berhasil Dibuat</span></div>
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--amber"><strong>{totals.gagal + totals.dilewati}</strong><span>Perlu Diperbaiki</span></div>
                        <div className="ad-imp-kpi__item ad-imp-kpi__item--red"><strong>{totals.duplikat}</strong><span>Data Duplikat</span></div>
                    </div>

                    <div className="ad-imp-actions">
                        <a className="ad-imp-btn" href={props.result_urls?.report}><Download size={15} /> Unduh Laporan Import</a>
                        {props.cards_available && props.result_urls && (
                            <form method="POST" action={props.result_urls.cards} className="ad-imp-actions__form">
                                <input type="hidden" name="_token" value={csrfToken} />
                                <button type="submit" className="ad-imp-btn ad-imp-btn--ghost"><FileUp size={15} /> Kartu Aktivasi / Kredensial (Excel)</button>
                            </form>
                        )}
                        <a className="ad-imp-btn ad-imp-btn--ghost" href={props.urls.index}><Upload size={15} /> Import File Lain</a>
                        <a className="ad-imp-btn ad-imp-btn--next" href={props.urls.users}>Ke Data Pengguna <ArrowRight size={16} /></a>
                    </div>
                    <p className="ad-imp-sub">Laporan = audit semua baris (tanpa password). Kartu aktivasi = ID login + password sementara per akun untuk dibagikan ke pengguna — hanya bisa diunduh sekali.</p>
                </section>
            )}
        </AdminShell>
    );
}
