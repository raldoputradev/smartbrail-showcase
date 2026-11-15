import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Bot, BookOpen, BrainCircuit, CheckCircle2, ClipboardList, FileText, HelpCircle,
    Layers, Pencil, Plus, Save, Search, Settings2, Sparkles, ThumbsDown, ThumbsUp, Trash2, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import type { AiTrainingProps, SharedProps } from '@admin/types';

type Respons = AiTrainingProps['feedback']['respons'][number];
type Tab = 'respons' | 'data' | 'eval' | 'knowledge' | 'topik' | 'atur';

const TABS: { key: Tab; label: string; icon: typeof FileText }[] = [
    { key: 'data', label: 'Data Pelatihan', icon: BrainCircuit },
    { key: 'respons', label: 'Data Respons', icon: ClipboardList },
    { key: 'eval', label: 'Umpan Balik & Evaluasi', icon: ThumbsUp },
    { key: 'knowledge', label: 'File Pengetahuan', icon: BookOpen },
    { key: 'topik', label: 'Topik & Kategori', icon: Layers },
    { key: 'atur', label: 'Pengaturan Pelatihan', icon: Settings2 },
];

const LEVEL_LABEL: Record<Respons['level'], string> = { bad: 'Buruk', ok: 'Cukup', great: 'Puas' };
const LEVEL_CLASS: Record<Respons['level'], string> = { bad: 'is-bad', ok: 'is-ok', great: 'is-great' };

export default function AiTraining(props: AiTrainingProps) {
    const { flash } = usePage<SharedProps & { flash: { success?: string } }>().props;
    const fb = props.feedback;
    const [knowledge, setKnowledge] = useState(props.knowledge);
    const [saving, setSaving] = useState(false);
    const [tab, setTab] = useState<Tab>('respons');
    const [cari, setCari] = useState('');
    const [fTopik, setFTopik] = useState('semua');
    const [fLevel, setFLevel] = useState('semua');
    const [sel, setSel] = useState<Respons | null>(null);

    const topikList = useMemo(() => [...new Set(fb.respons.map((r) => r.topik))].sort(), [fb.respons]);

    const filtered = useMemo(() => fb.respons.filter((r) => {
        if (fTopik !== 'semua' && r.topik !== fTopik) return false;
        if (fLevel !== 'semua' && r.level !== fLevel) return false;
        if (cari.trim() && !r.q.toLowerCase().includes(cari.trim().toLowerCase())) return false;
        return true;
    }), [fb.respons, cari, fTopik, fLevel]);

    const puas = fb.ringkasan.ok + fb.ringkasan.great;
    const totalRespons = fb.ringkasan.bad + fb.ringkasan.ok + fb.ringkasan.great;
    const totalDipelajari = props.unmatched.reduce((a, u) => a + u.n, 0);

    const simpan = (isi?: string) => {
        setSaving(true);
        router.post(props.urls.save_knowledge, { knowledge: isi ?? knowledge }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const jadikanPelatihan = (r: Respons) => {
        const aturan = `\n\n## ${r.q}\n${r.a}\n`;
        simpan(knowledge + aturan);
    };

    const editRespons = (r: Respons) => {
        setKnowledge((k) => `${k}\n\n## ${r.q}\n${r.a}\n`);
        setTab('knowledge');
    };

    const hapusRespons = (r: Respons) => {
        if (!confirm('Hapus entri respons ini dari log pelatihan?')) return;
        router.post(props.urls.delete_respons, { waktu: r.waktu, q: r.q }, {
            preserveScroll: true,
            onSuccess: () => setSel(null),
        });
    };

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Pelatihan AI">
            <Head title="Pelatihan AI" />

            <div className="ad-trainv2">
                {/* ── Hero ── */}
                <div className="ad-trv-hero">
                    <div className="ad-trv-hero__bot"><Bot size={64} strokeWidth={1.2} /><Sparkles className="ad-trv-hero__spark" size={18} /></div>
                    <div className="ad-trv-hero__txt">
                        <span className="ad-trv-hero__badge"><BrainCircuit size={12} /> Manajemen Pengetahuan Asisten AI</span>
                        <h2>Pelatihan AI SmartBrail</h2>
                        <p>Kelola data pelatihan, pantau respons, dan tingkatkan kualitas jawaban Asisten AI melalui umpan balik pengguna.</p>
                    </div>
                </div>

                {/* ── Statistik ── */}
                <div className="ad-trv-stats">
                    <div className="ad-trv-stat ad-trv-stat--blue">
                        <span className="ad-trv-stat__ic"><ClipboardList size={20} /></span>
                        <div><strong>{totalDipelajari}</strong><small>Pertanyaan Dipelajari</small></div>
                    </div>
                    <div className="ad-trv-stat ad-trv-stat--violet">
                        <span className="ad-trv-stat__ic"><Layers size={20} /></span>
                        <div><strong>{totalRespons}</strong><small>Respons Disimpan</small></div>
                    </div>
                    <div className="ad-trv-stat ad-trv-stat--red">
                        <span className="ad-trv-stat__ic"><ThumbsDown size={20} /></span>
                        <div><strong>{fb.ringkasan.bad}</strong><small>Respons Dinilai Buruk</small></div>
                    </div>
                    <div className="ad-trv-stat ad-trv-stat--green">
                        <span className="ad-trv-stat__ic"><ThumbsUp size={20} /></span>
                        <div><strong>{puas}</strong><small>Respons Dinilai Puas</small></div>
                    </div>
                </div>

                {flash?.success && <div className="ad-alert ad-alert--ok"><CheckCircle2 size={15} /> {flash.success}</div>}

                <div className="ad-trv-grid">
                    {/* ── Nav sub-halaman ── */}
                    <nav className="ad-trv-nav">
                        <small className="ad-trv-nav__cap">Data Pelatihan</small>
                        {TABS.map((t) => (
                            <button key={t.key} type="button"
                                className={tab === t.key ? 'ad-trv-nav__btn is-active' : 'ad-trv-nav__btn'}
                                onClick={() => setTab(t.key)}>
                                <t.icon size={15} /> {t.label}
                            </button>
                        ))}
                    </nav>

                    {/* ── Konten ── */}
                    <div className="ad-trv-main">
                        {tab === 'respons' && (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div>
                                        <h3>Data Respons Asisten AI</h3>
                                        <small>Daftar pertanyaan pengguna beserta respons AI yang dipelajari.</small>
                                    </div>
                                    <button type="button" className="ad-btn ad-btn--primary ad-btn--sm" onClick={() => setTab('knowledge')}>
                                        <Plus size={14} /> Tambah Data Pelatihan
                                    </button>
                                </div>
                                <div className="ad-trv-toolbar">
                                    <label className="ad-search">
                                        <Search size={14} />
                                        <input placeholder="Cari pertanyaan…" value={cari} onChange={(e) => setCari(e.target.value)} />
                                    </label>
                                    <select value={fTopik} onChange={(e) => setFTopik(e.target.value)}>
                                        <option value="semua">Semua Topik</option>
                                        {topikList.map((t) => <option key={t} value={t}>{t}</option>)}
                                    </select>
                                    <select value={fLevel} onChange={(e) => setFLevel(e.target.value)}>
                                        <option value="semua">Semua Penilaian</option>
                                        <option value="great">Puas</option>
                                        <option value="ok">Cukup</option>
                                        <option value="bad">Buruk</option>
                                    </select>
                                </div>
                                <div className="ad-trv-split">
                                    <div className="ad-trv-list">
                                        <small className="ad-trv-count">{filtered.length} data respons</small>
                                        {filtered.map((r, i) => (
                                            <button key={i} type="button"
                                                className={sel === r ? 'ad-trv-item is-active' : 'ad-trv-item'}
                                                onClick={() => setSel(r)}>
                                                <FileText size={14} className="ad-trv-item__ic" />
                                                <span className="ad-trv-item__q">{r.q}</span>
                                                <span className="ad-trv-item__meta">
                                                    <small>Topik: {r.topik}</small>
                                                    <small>{r.waktu.slice(0, 16)}</small>
                                                </span>
                                                <span className={`ad-trv-badge ${LEVEL_CLASS[r.level]}`}>{LEVEL_LABEL[r.level]}</span>
                                            </button>
                                        ))}
                                        {filtered.length === 0 && <div className="ad-empty">Belum ada respons yang dinilai pengguna.</div>}
                                    </div>
                                    <div className="ad-panel ad-trv-detail">
                                        <div className="ad-panel__head">
                                            <h3>Detail Pelatihan</h3>
                                            {sel && <span className={`ad-trv-badge ${LEVEL_CLASS[sel.level]}`}>{LEVEL_LABEL[sel.level]}</span>}
                                        </div>
                                        {sel ? (
                                            <>
                                                <div className="ad-trv-box">
                                                    <small><HelpCircle size={12} /> Pertanyaan Pengguna</small>
                                                    <p>{sel.q}</p>
                                                </div>
                                                <div className="ad-trv-box ad-trv-box--blue">
                                                    <small><Bot size={12} /> Respons Asisten AI</small>
                                                    <p>{sel.a}</p>
                                                </div>
                                                <div className="ad-trv-meta">
                                                    <div><small>Topik</small><strong>{sel.topik}</strong></div>
                                                    <div><small>Sumber</small><strong>Log Umpan Balik</strong></div>
                                                    <div><small>Terakhir Ditanya</small><strong>{sel.waktu.slice(0, 16)}</strong></div>
                                                </div>
                                                <div className="ad-trv-actions">
                                                    <button type="button" className="ad-btn ad-btn--sm" onClick={() => editRespons(sel)} disabled={!props.can_edit}>
                                                        <Pencil size={13} /> Edit Respons
                                                    </button>
                                                    <button type="button" className="ad-btn ad-btn--sm ad-btn--primary" onClick={() => jadikanPelatihan(sel)} disabled={!props.can_edit || saving}>
                                                        <Plus size={13} /> Jadikan Data Pelatihan
                                                    </button>
                                                    <button type="button" className="ad-btn ad-btn--sm ad-btn--danger" onClick={() => hapusRespons(sel)} disabled={!props.can_edit}>
                                                        <Trash2 size={13} /> Hapus
                                                    </button>
                                                </div>
                                            </>
                                        ) : (
                                            <div className="ad-empty">Pilih satu respons di sebelah kiri untuk melihat detail.</div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {tab === 'data' && (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div>
                                        <h3>Pertanyaan yang Gagal Dipahami</h3>
                                        <small>Otomasi tercatat saat asisten lokal tidak menemukan jawaban — kandidat intent/keyword baru.</small>
                                    </div>
                                    <HelpCircle size={20} className="text-sb-primary" />
                                </div>
                                <div className="ad-trv-list" style={{ padding: '4px 16px 16px' }}>
                                    {props.unmatched.map((u, i) => (
                                        <div key={i} className="ad-trv-item ad-trv-item--static">
                                            <FileText size={14} className="ad-trv-item__ic" />
                                            <span className="ad-trv-item__q">{u.q}</span>
                                            <span className="ad-trv-item__meta">
                                                <small>{u.n}× ditanya</small>
                                                <small>{u.terakhir.slice(0, 16)}</small>
                                            </span>
                                            {props.can_edit && (
                                                <button type="button" className="ad-btn ad-btn--sm"
                                                    onClick={() => { setKnowledge((k) => `${k}\n\n## ${u.q}\n`); setTab('knowledge'); }}>
                                                    <Plus size={12} /> Buat Jawaban
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    {props.unmatched.length === 0 && <div className="ad-empty">Semua pertanyaan berhasil dipahami — tidak ada yang tercatat gagal.</div>}
                                </div>
                            </div>
                        )}

                        {tab === 'eval' && (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div>
                                        <h3>Umpan Balik & Evaluasi</h3>
                                        <small>Penilaian pengguna per topik — topik dengan banyak 😞 butuh jawaban baru di File Pengetahuan.</small>
                                    </div>
                                </div>
                                <div className="ad-trv-topik" style={{ padding: '4px 16px 16px' }}>
                                    {fb.per_topik.map((t) => {
                                        const total = t.bad + t.ok + t.great;
                                        return (
                                            <div key={t.topik} className="ad-trv-topikrow">
                                                <div className="ad-trv-topikrow__head">
                                                    <strong>{t.topik}</strong>
                                                    <small>{total} penilaian</small>
                                                </div>
                                                <div className="ad-trv-bar">
                                                    <span className="is-great" style={{ width: `${(t.great / Math.max(total, 1)) * 100}%` }} />
                                                    <span className="is-ok" style={{ width: `${(t.ok / Math.max(total, 1)) * 100}%` }} />
                                                    <span className="is-bad" style={{ width: `${(t.bad / Math.max(total, 1)) * 100}%` }} />
                                                </div>
                                                <small className="ad-trv-topikrow__leg">😄 {t.great} · 😐 {t.ok} · 😞 {t.bad}</small>
                                            </div>
                                        );
                                    })}
                                    {fb.per_topik.length === 0 && <div className="ad-empty">Belum ada penilaian dari pengguna.</div>}
                                </div>
                            </div>
                        )}

                        {tab === 'knowledge' && (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div>
                                        <h3>File Pengetahuan Asisten</h3>
                                        <small>Format: baris <code>## kata kunci | kata kunci lain</code> lalu jawaban di baris berikutnya. Berlaku langsung untuk mahasiswa.</small>
                                    </div>
                                    <BookOpen size={20} className="text-sb-primary" />
                                </div>
                                <div style={{ padding: '0 16px 16px' }}>
                                    <textarea
                                        className="ad-train-editor"
                                        rows={16}
                                        value={knowledge}
                                        onChange={(e) => setKnowledge(e.target.value)}
                                        placeholder={'## jam kerja laboran | jam buka lab\nLaboran bertugas Senin–Jumat 08.00–17.00 WIB.'}
                                        disabled={!props.can_edit}
                                    />
                                    {props.can_edit && (
                                        <button type="button" className="ad-btn ad-btn--primary" style={{ marginTop: 12 }} onClick={() => simpan()} disabled={saving}>
                                            <Save size={14} /> {saving ? 'Menyimpan…' : 'Simpan Pengetahuan'}
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}

                        {tab === 'topik' && (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div>
                                        <h3>Topik & Kategori</h3>
                                        <small>Topik yang dikenali asisten lokal beserta sebaran penilaian.</small>
                                    </div>
                                    <Layers size={20} className="text-sb-primary" />
                                </div>
                                <div className="ad-trv-list" style={{ padding: '4px 16px 16px' }}>
                                    {fb.per_topik.map((t) => (
                                        <div key={t.topik} className="ad-trv-item ad-trv-item--static">
                                            <Layers size={14} className="ad-trv-item__ic" />
                                            <span className="ad-trv-item__q">{t.topik}</span>
                                            <span className="ad-trv-item__meta">
                                                <small>😄 {t.great}</small><small>😐 {t.ok}</small><small>😞 {t.bad}</small>
                                            </span>
                                        </div>
                                    ))}
                                    {fb.per_topik.length === 0 && <div className="ad-empty">Belum ada data topik.</div>}
                                </div>
                            </div>
                        )}

                        {tab === 'atur' && (
                            <div className="ad-panel">
                                <div className="ad-panel__head">
                                    <div>
                                        <h3>Pengaturan Pelatihan</h3>
                                        <small>Cara kerja siklus belajar asisten lokal.</small>
                                    </div>
                                    <Settings2 size={20} className="text-sb-primary" />
                                </div>
                                <div className="ad-trv-panduan">
                                    <div className="ad-trv-box">
                                        <small><BrainCircuit size={12} /> Siklus Belajar</small>
                                        <p>Pertanyaan yang gagal dipahami tercatat di <code>assistant-unmatched.log</code>; penilaian 😞/😐/😄 di <code>assistant-feedback.log</code> per topik. Data di halaman ini bersumber langsung dari kedua log itu.</p>
                                    </div>
                                    <div className="ad-trv-box ad-trv-box--blue">
                                        <small><BookOpen size={12} /> File Pengetahuan</small>
                                        <p>Tulis jawaban baru di tab <strong>File Pengetahuan</strong> — asisten membacanya saat intent bawaan tidak cocok, tanpa perlu deploy. Tombol <em>Jadikan Data Pelatihan</em> pada sebuah respons otomatis menambahkannya sebagai aturan.</p>
                                    </div>
                                    <div className="ad-trv-box">
                                        <small><X size={12} /> Catatan</small>
                                        <p>Perubahan file pengetahuan hanya berdampak pada <strong>asisten mahasiswa (lokal)</strong>. Copilot admin/dosen tetap memakai Gemini + tool data.</p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
