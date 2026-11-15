import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Bell, CalendarDays, CalendarRange, ChevronLeft, ChevronRight, Clock,
    Flag, ListTodo, Megaphone, Pencil, Plus, Trash2, X,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { asset } from '@admin/lib/asset';
import { cn } from '@admin/lib/utils';
import type { KalAgendaItem, KalDay, KalenderAgendaProps, SharedProps } from '@admin/types';

const DOW = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const JENIS_CLASS: Record<string, string> = {
    libur: 'kal-c--libur', peringatan: 'kal-c--peringatan', agenda: 'kal-c--agenda',
    ujian: 'kal-c--ujian', perubahan_jam: 'kal-c--khusus', informasi: 'kal-c--info',
};
const TABS = [
    { key: 'kalender', label: 'Kalender', icon: <CalendarDays size={14} /> },
    { key: 'jadwal', label: 'Jadwal Absensi', icon: <Clock size={14} /> },
    { key: 'libur', label: 'Hari Libur', icon: <CalendarRange size={14} /> },
    { key: 'peringatan', label: 'Peringatan Nasional', icon: <Flag size={14} /> },
    { key: 'agenda', label: 'Agenda Kegiatan', icon: <ListTodo size={14} /> },
] as const;
type TabKey = typeof TABS[number]['key'];
const LIST_JENIS: Record<string, string[]> = {
    libur: ['libur'], peringatan: ['peringatan', 'informasi'],
    agenda: ['agenda', 'ujian', 'perubahan_jam'],
};

export default function KalenderAgenda(props: KalenderAgendaProps) {
    const { errors, flash } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState<TabKey>('kalender');
    const [selDay, setSelDay] = useState<KalDay | null>(
        props.days.find((d) => d.is_today) ?? props.days.find((d) => d.agenda.length > 0) ?? null);
    const [form, setForm] = useState<'closed' | 'add' | 'edit'>('closed');

    const goMonth = (m: string) =>
        router.get(props.urls.index, { month: m }, { preserveState: true, preserveScroll: true, replace: true });

    const agendaForm = useForm({
        tanggal: '', tanggal_selesai: '', tanggal_list: [] as string[], jenis: 'agenda',
        dampak_absensi: '', prioritas: 'normal', jam_masuk_khusus: '', jam_pulang_khusus: '', keterangan: '',
    });
    const [editItem, setEditItem] = useState<KalAgendaItem | null>(null);
    const [marked, setMarked] = useState<Set<string>>(new Set());
    const [pickMonth, setPickMonth] = useState(props.month.value);
    const toggleMark = (tgl: string) => setMarked((s) => {
        const n = new Set(s);
        n.has(tgl) ? n.delete(tgl) : n.add(tgl);
        return n;
    });

    const namaHistory = useMemo(
        () => [...new Set(props.agenda.map((a) => a.keterangan).filter((k): k is string => !!k))],
        [props.agenda]);

    const pickDays = useMemo(() => {
        const [y, m] = pickMonth.split('-').map(Number);
        const dim = new Date(y, m, 0).getDate();
        const first = (new Date(y, m - 1, 1).getDay() + 6) % 7;
        return { first, days: [...Array(dim)].map((_, i) => `${pickMonth}-${String(i + 1).padStart(2, '0')}`) };
    }, [pickMonth]);
    const shiftPick = (dir: number) => {
        const [y, m] = pickMonth.split('-').map(Number);
        const d = new Date(y, m - 1 + dir, 1);
        setPickMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    };
    const agendaByDate = useMemo(() => {
        const map = new Map<string, string[]>();
        props.days.forEach((d) => d.agenda.forEach((a) => {
            map.set(d.tanggal, [...(map.get(d.tanggal) ?? []), a.jenis]);
        }));
        return map;
    }, [props.days]);
    const pickMonthLabel = useMemo(() => {
        const [y, m] = pickMonth.split('-').map(Number);
        return new Date(y, m - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }, [pickMonth]);

    const openAdd = (day?: KalDay | null) => {
        setEditItem(null);
        agendaForm.setData({ tanggal: '', tanggal_selesai: '', tanggal_list: [], jenis: 'agenda', dampak_absensi: '', prioritas: 'normal', jam_masuk_khusus: '', jam_pulang_khusus: '', keterangan: '' });
        setMarked(day ? new Set([day.tanggal]) : new Set());
        setPickMonth(day?.tanggal.slice(0, 7) ?? props.month.value);
        setForm('add');
    };
    const openEdit = (item: KalAgendaItem) => {
        setEditItem(item);
        agendaForm.setData({
            tanggal: item.tanggal, tanggal_selesai: item.tanggal_selesai ?? '', tanggal_list: [],
            jenis: item.jenis, dampak_absensi: item.dampak, prioritas: item.prioritas,
            jam_masuk_khusus: item.jam_khusus?.split(' – ')[0] ?? '',
            jam_pulang_khusus: item.jam_khusus?.split(' – ')[1] ?? '',
            keterangan: item.keterangan ?? '',
        });
        setForm('edit');
    };
    const submitAgenda = (e: React.FormEvent) => {
        e.preventDefault();
        const dates = [...marked].sort();
        agendaForm.transform((d) => ({
            ...d,
            tanggal: form === 'edit' ? d.tanggal : undefined,
            tanggal_selesai: form === 'edit' ? (d.tanggal_selesai || d.tanggal) : undefined,
            tanggal_list: form === 'add' ? dates : undefined,
            dampak_absensi: d.dampak_absensi || undefined,
            prioritas: d.prioritas || 'normal',
        }));
        const opts = {
            preserveScroll: true,
            onSuccess: (page: any) => {
                if (Object.keys((page.props.errors ?? {}) as Record<string, string>).length === 0) {
                    setForm('closed');
                    setMarked(new Set());
                }
            },
        };
        if (form === 'edit' && editItem) {
            agendaForm.patch(`${props.urls.update_base}/${editItem.id}`, opts);
        } else {
            agendaForm.post(props.urls.store, opts);
        }
    };
    const destroyItem = (item: KalAgendaItem) => {
        if (!confirm(`Hapus agenda "${item.keterangan ?? item.jenis_label}"?`)) return;
        router.delete(`${props.urls.destroy_base}/${item.id}`, { preserveScroll: true });
        if (selDay) setSelDay({ ...selDay, agenda: selDay.agenda.filter((a) => a.id !== item.id) });
    };

    const listItems = useMemo(() => tab === 'kalender' || tab === 'jadwal'
        ? [] : props.agenda.filter((a) => (LIST_JENIS[tab] ?? []).includes(a.jenis)),
        [tab, props.agenda]);

    const k = props.kpi;
    const cards = [
        { label: 'Agenda Bulan Ini', value: k.agenda, sub: props.month.label, icon: <CalendarDays size={18} />, accent: '#0284c7' },
        { label: 'Jadwal Absensi Aktif', value: `${k.hari_aktif} Hari`, sub: k.hari_label, icon: <Clock size={18} />, accent: '#059669' },
        { label: 'Hari Libur', value: k.libur, sub: `pada ${props.month.label}`, icon: <CalendarRange size={18} />, accent: '#e11d48' },
        { label: 'Peringatan Nasional', value: k.peringatan, sub: `pada ${props.month.label}`, icon: <Flag size={18} />, accent: '#7c3aed' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif}
            title="Kalender & Agenda Laboratorium"
            subtitle="Kelola kalender, jadwal absensi, hari libur, dan peringatan nasional untuk semua pengguna.">
            <Head title="Kalender & Agenda" />

            {flash?.success ? <div className="ad-mesin-flash ad-mesin-flash--ok">{flash.success}</div> : null}
            {flash?.error ? <div className="ad-mesin-flash ad-mesin-flash--err">{flash.error}</div> : null}

            <section className="ad-nq-hero"
                style={{ ['--ad-hero-img' as string]: `url('${asset('images/campus-polybatam.jpg')}')` }}>
                <div className="ad-nq-hero__body">
                    <h2>Manajemen Kalender Laboratorium</h2>
                    <p>Atur jadwal absensi, hari libur, peringatan nasional, dan agenda kegiatan laboratorium — otomatis tampil di kalender semua pengguna.</p>
                </div>
            </section>

            <section className="ad-log-kpis">
                {cards.map((c) => (
                    <div className="ad-log-kpi" key={c.label}>
                        <span className="ad-log-kpi__ic" style={{ color: c.accent, background: `${c.accent}1f` }}>{c.icon}</span>
                        <div><small>{c.label}</small><strong>{c.value}</strong><em className="ad-log-kpi__sub">{c.sub}</em></div>
                    </div>
                ))}
            </section>

            <div className="ad-nq-tabrow">
                <div className="ad-rg-tabs">
                    {TABS.map((t) => (
                        <button key={t.key} type="button" className={cn('ad-rg-tab', tab === t.key && 'is-active')}
                            onClick={() => setTab(t.key)}>{t.icon} {t.label}</button>
                    ))}
                </div>
                {props.can_update && (
                    <button type="button" className="ad-mesin-btn ad-mesin-btn--primary" onClick={() => openAdd()}>
                        <Plus size={14} /> Tambah Agenda
                    </button>
                )}
            </div>

            {tab === 'kalender' && (
                <div className="kal-split">
                    <div className="ad-panel kal-main">
                        <div className="kal-monthbar">
                            <div className="kal-monthnav">
                                <button type="button" className="ad-icon-btn" onClick={() => goMonth(props.month.prev)} aria-label="Bulan sebelumnya"><ChevronLeft size={15} /></button>
                                <button type="button" className="ad-icon-btn" onClick={() => goMonth(props.month.next)} aria-label="Bulan berikutnya"><ChevronRight size={15} /></button>
                                <strong>{props.month.label}</strong>
                                {!props.month.is_current && (
                                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost ad-mesin-btn--sm"
                                        onClick={() => goMonth(new Date().toISOString().slice(0, 7))}>Hari Ini</button>
                                )}
                            </div>
                        </div>
                        <div className="kal-grid">
                            {DOW.map((d) => <div key={d} className="kal-dow">{d}</div>)}
                            {[...Array(props.first_offset)].map((_, i) => <div key={`e${i}`} className="kal-cell kal-cell--out" />)}
                            {props.days.map((d) => (
                                <button key={d.tanggal} type="button"
                                    className={cn('kal-cell', d.is_today && 'is-today',
                                        selDay?.tanggal === d.tanggal && 'is-sel',
                                        d.agenda.some((a) => a.dampak === 'batal_wajib') && 'is-libur')}
                                    onClick={() => setSelDay(d)}>
                                    <span className={cn('kal-day', (d.dow === 0 || d.agenda.some((a) => a.jenis === 'libur')) && 'is-sun')}>{d.day}</span>
                                    {d.is_wajib && <span className="kal-chip kal-c--absensi">Absensi</span>}
                                    {d.agenda.slice(0, 2).map((a) => (
                                        <span key={a.id} className={cn('kal-chip', JENIS_CLASS[a.jenis])} data-prio={a.prioritas}>{a.keterangan ?? a.jenis_label}</span>
                                    ))}
                                    {d.agenda.length > 2 && <span className="kal-more">+{d.agenda.length - 2}</span>}
                                </button>
                            ))}
                        </div>
                        <div className="kal-legend">
                            <span><i className="kal-dot kal-c--absensi" /> Hari Absensi</span>
                            <span><i className="kal-dot kal-c--agenda" /> Agenda Kegiatan</span>
                            <span><i className="kal-dot kal-c--libur" /> Hari Libur</span>
                            <span><i className="kal-dot kal-c--peringatan" /> Peringatan Nasional</span>
                            <span><i className="kal-dot kal-c--khusus" /> Absensi Khusus</span>
                            <span className="kal-legend-sep" />
                            <span><i className="kal-prio kal-prio--mendesak" /> Mendesak</span>
                            <span><i className="kal-prio kal-prio--penting" /> Penting</span>
                            <span><i className="kal-prio kal-prio--normal" /> Normal</span>
                            <span><i className="kal-prio kal-prio--rendah" /> Rendah</span>
                        </div>
                    </div>

                    <aside className="kal-side">
                        <div className="ad-panel">
                            <div className="ad-panel__head"><h3><Clock size={15} /> Jadwal Absensi</h3>
                                <span className="ad-pill ad-pill--ok">Aktif</span></div>
                            <dl className="ad-nq-kv kal-sidekv">
                                <div><dt>Hari Pelaksanaan</dt><dd>{props.jadwal.hari_label}</dd></div>
                                <div><dt>Jam Absensi</dt><dd>{props.jadwal.jam ?? '—'}</dd></div>
                                <div><dt>Toleransi Terlambat</dt><dd>{props.jadwal.toleransi != null ? `${props.jadwal.toleransi} menit` : '—'}</dd></div>
                            </dl>
                            <div style={{ padding: '0 16px 16px' }}>
                                <a className="ad-mesin-btn ad-mesin-btn--ghost" href={props.urls.atur_absensi} style={{ width: '100%', justifyContent: 'center' }}>
                                    Kelola Jadwal Absensi
                                </a>
                            </div>
                        </div>

                        <div className="ad-panel">
                            <div className="ad-panel__head">
                                <h3><Bell size={15} /> Detail Agenda Terpilih</h3>
                                {props.can_update && selDay && selDay.agenda.length === 0 && (
                                    <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost ad-mesin-btn--sm"
                                        onClick={() => openAdd(selDay)}><Plus size={13} /> Tambah</button>
                                )}
                            </div>
                            {selDay ? (
                                <div style={{ padding: '0 16px 16px' }}>
                                    <div className="kal-selday">{selDay.day} {props.month.label}</div>
                                    {selDay.is_wajib && <div className="kal-selinfo">Wajib absensi · {selDay.jam}</div>}
                                    {selDay.agenda.length === 0 && (
                                        <div className="ad-empty" style={{ padding: '14px 0' }}>Tidak ada agenda pada tanggal ini.</div>
                                    )}
                                    {selDay.agenda.map((a) => (
                                        <div key={a.id} className="kal-agitem">
                                            <div className="kal-agitem__head">
                                                <span className={cn('kal-chip', JENIS_CLASS[a.jenis])} data-prio={a.prioritas}>{a.jenis_label}</span>
                                                {props.can_update && (
                                                    <span className="kal-agitem__act">
                                                        <button type="button" className="ad-icon-btn" title="Ubah" onClick={() => openEdit(a)}><Pencil size={13} /></button>
                                                        <button type="button" className="ad-icon-btn" title="Hapus" onClick={() => destroyItem(a)}><Trash2 size={13} /></button>
                                                    </span>
                                                )}
                                            </div>
                                            <strong>{a.keterangan ?? a.jenis_label}</strong>
                                            <small>{a.rentang}{a.jam_khusus ? ` · ${a.jam_khusus}` : ''}</small>
                                            <em>{a.dampak_label}</em>
                                            {a.prioritas !== 'normal' && (
                                                <em className={`kal-prio-text kal-prio-text--${a.prioritas}`}>Prioritas: {a.prioritas_label}</em>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="ad-empty" style={{ padding: 24 }}>Pilih tanggal pada kalender.</div>
                            )}
                        </div>
                    </aside>
                </div>
            )}

            {tab === 'jadwal' && (
                <div className="ad-panel" style={{ padding: '4px 0 16px' }}>
                    <div className="ad-panel__head"><h3><Clock size={15} /> Jadwal Absensi Mingguan</h3>
                        <small>Diatur pada halaman Atur Absensi — berlaku ke semua pengguna.</small></div>
                    <div className="ad-table-wrap ad-usr-free">
                        <table className="ad-table">
                            <thead><tr><th>Hari</th><th>Status</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Keterangan</th></tr></thead>
                            <tbody>
                                {props.days.filter((d, i) => i < 7).length === 0 && null}
                                {(['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']).map((nama, i) => {
                                    const dow = (i + 1) % 7;
                                    const ref = props.days.find((d) => d.dow === dow);
                                    const wajib = ref?.is_wajib ?? false;
                                    return (
                                        <tr key={nama}>
                                            <td><strong>{nama}</strong></td>
                                            <td><span className={cn('ad-pill', wajib ? 'ad-pill--ok' : 'ad-pill--muted')}>{wajib ? 'Wajib' : 'Libur'}</span></td>
                                            <td>{wajib ? (ref?.jam?.split(' – ')[0] ?? '—') : '—'}</td>
                                            <td>{wajib ? (ref?.jam?.split(' – ')[1] ?? '—') : '—'}</td>
                                            <td>{wajib ? 'Sesi absensi reguler' : 'Tidak wajib'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {(tab === 'libur' || tab === 'peringatan' || tab === 'agenda') && (
                <div className="ad-panel" style={{ padding: '4px 0 16px' }}>
                    <div className="ad-panel__head">
                        <h3>{TABS.find((t) => t.key === tab)?.label} — {props.month.label}</h3>
                        <small>{listItems.length} entri</small>
                    </div>
                    <div className="kal-list">
                        {listItems.map((a) => (
                            <div key={a.id} className="kal-agitem kal-agitem--row">
                                <div className="kal-agitem__head">
                                    <span className={cn('kal-chip', JENIS_CLASS[a.jenis])} data-prio={a.prioritas}>{a.jenis_label}</span>
                                    {props.can_update && (
                                        <span className="kal-agitem__act">
                                            <button type="button" className="ad-icon-btn" onClick={() => openEdit(a)}><Pencil size={13} /></button>
                                            <button type="button" className="ad-icon-btn" onClick={() => destroyItem(a)}><Trash2 size={13} /></button>
                                        </span>
                                    )}
                                </div>
                                <strong>{a.keterangan ?? a.jenis_label}</strong>
                                <small>{a.rentang}{a.jam_khusus ? ` · ${a.jam_khusus}` : ''} · {a.dampak_label}</small>
                                {a.prioritas !== 'normal' && (
                                    <em className={`kal-prio-text kal-prio-text--${a.prioritas}`}>Prioritas: {a.prioritas_label}</em>
                                )}
                            </div>
                        ))}
                        {listItems.length === 0 && (
                            <div className="ad-empty">Belum ada entri pada bulan ini.</div>
                        )}
                    </div>
                </div>
            )}

            {form !== 'closed' && (
                <div className="ad-mesin-scrim" onClick={(e) => e.target === e.currentTarget && setForm('closed')}>
                    <div className="ad-mesin-setup kal-form">
                        <button type="button" className="ad-icon-btn ad-usr-x" onClick={() => setForm('closed')}><X size={16} /></button>
                        <span className="ad-usr-modal__badge">{form === 'add' ? 'TAMBAH AGENDA' : 'UBAH AGENDA'}</span>
                        <h3>{form === 'add' ? 'Agenda Kalender Baru' : `Ubah — ${editItem?.keterangan ?? editItem?.jenis_label}`}</h3>
                        <form onSubmit={submitAgenda} className="kal-formgrid">
                            <label className="ad-formfield"><span>Jenis Agenda *</span>
                                <select value={agendaForm.data.jenis}
                                    onChange={(e) => agendaForm.setData('jenis', e.target.value as never)}>
                                    <option value="agenda">Agenda Kegiatan</option>
                                    <option value="libur">Hari Libur</option>
                                    <option value="peringatan">Peringatan Nasional</option>
                                    <option value="ujian">Ujian</option>
                                    <option value="perubahan_jam">Absensi Khusus (ubah jam)</option>
                                    <option value="informasi">Informasi</option>
                                </select>
                            </label>
                            <label className="ad-formfield"><span>Nama Agenda</span>
                                <input type="text" maxLength={255} list="kal-nama-history"
                                    placeholder="Tulis sendiri atau pilih riwayat…"
                                    value={agendaForm.data.keterangan}
                                    onChange={(e) => agendaForm.setData('keterangan', e.target.value)} />
                                <datalist id="kal-nama-history">
                                    {namaHistory.map((n) => <option key={n} value={n} />)}
                                </datalist>
                            </label>

                            {form === 'edit' ? (
                                <>
                                    <label className="ad-formfield"><span>Tanggal *</span>
                                        <input type="date" required value={agendaForm.data.tanggal}
                                            onChange={(e) => agendaForm.setData('tanggal', e.target.value)} />
                                        {errors.tanggal && <em className="ad-err">{errors.tanggal}</em>}
                                    </label>
                                    <label className="ad-formfield"><span>Sampai Tanggal</span>
                                        <input type="date" value={agendaForm.data.tanggal_selesai}
                                            onChange={(e) => agendaForm.setData('tanggal_selesai', e.target.value)} />
                                    </label>
                                </>
                            ) : (
                                <div className="ad-formfield kal-span2 kal-pickwrap">
                                    <span>Tandai Tanggal * <small>(klik tanggal — bisa beberapa, beda bulan)</small></span>
                                    <div className="kal-pick">
                                        <div className="kal-pick__nav">
                                            <button type="button" className="ad-icon-btn" onClick={() => shiftPick(-1)}><ChevronLeft size={13} /></button>
                                            <strong>{pickMonthLabel}</strong>
                                            <button type="button" className="ad-icon-btn" onClick={() => shiftPick(1)}><ChevronRight size={13} /></button>
                                        </div>
                                        <div className="kal-pick__grid">
                                            {DOW.map((d) => <div key={d} className="kal-pick__dow">{d.slice(0, 2)}</div>)}
                                            {[...Array(pickDays.first)].map((_, i) => <div key={`e${i}`} />)}
                                            {pickDays.days.map((tgl) => (
                                                <button key={tgl} type="button"
                                                    className={cn('kal-pick__day', marked.has(tgl) && 'is-marked',
                                                        agendaByDate.has(tgl) && 'has-agenda')}
                                                    title={agendaByDate.get(tgl)?.join(', ')}
                                                    onClick={() => toggleMark(tgl)}>
                                                    {Number(tgl.slice(-2))}
                                                </button>
                                            ))}
                                        </div>
                                        <div className="kal-pick__sel">
                                            {marked.size === 0
                                                ? <em>Belum ada tanggal ditandai.</em>
                                                : [...marked].sort().map((t) => (
                                                    <span key={t} className="kal-chip kal-c--agenda" style={{ cursor: 'pointer' }}
                                                        onClick={() => toggleMark(t)} title="Klik untuk hapus">
                                                        {new Date(t + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })} ×
                                                    </span>
                                                ))}
                                        </div>
                                    </div>
                                    {errors.tanggal && <em className="ad-err">{errors.tanggal}</em>}
                                    {errors['tanggal_list'] && <em className="ad-err">{errors['tanggal_list']}</em>}
                                </div>
                            )}
                            {agendaForm.data.jenis === 'perubahan_jam' && (
                                <>
                                    <label className="ad-formfield"><span>Jam Masuk Khusus</span>
                                        <input type="time" value={agendaForm.data.jam_masuk_khusus}
                                            onChange={(e) => agendaForm.setData('jam_masuk_khusus', e.target.value)} />
                                    </label>
                                    <label className="ad-formfield"><span>Jam Pulang Khusus</span>
                                        <input type="time" value={agendaForm.data.jam_pulang_khusus}
                                            onChange={(e) => agendaForm.setData('jam_pulang_khusus', e.target.value)} />
                                    </label>
                                </>
                            )}
                            <label className="ad-formfield"><span>Prioritas</span>
                                <select value={agendaForm.data.prioritas}
                                    onChange={(e) => agendaForm.setData('prioritas', e.target.value as never)}>
                                    <option value="rendah">⚪ Rendah — informasi saja</option>
                                    <option value="normal">🔵 Normal — agenda biasa</option>
                                    <option value="penting">🟠 Penting — perlu diperhatikan</option>
                                    <option value="mendesak">🔴 Mendesak — sangat penting</option>
                                </select>
                            </label>
                            {(agendaForm.data.jenis === 'libur' || agendaForm.data.jenis === 'agenda' || agendaForm.data.jenis === 'peringatan') && (
                                <label className="ad-formfield"><span>Dampak Absensi</span>
                                    <select value={agendaForm.data.dampak_absensi}
                                        onChange={(e) => agendaForm.setData('dampak_absensi', e.target.value)}>
                                        {agendaForm.data.jenis === 'libur'
                                            ? <><option value="batal_wajib">Absensi tidak wajib (libur)</option><option value="tetap_wajib">Absensi tetap wajib</option></>
                                            : <><option value="tetap_wajib">Tidak mengubah kewajiban</option><option value="batal_wajib">Batalkan kewajiban (jadikan libur)</option></>}
                                    </select>
                                </label>
                            )}
                            <div className="ad-mesin-confirm__actions kal-span2">
                                <button type="button" className="ad-mesin-btn ad-mesin-btn--ghost" onClick={() => setForm('closed')}>Batal</button>
                                <button type="submit" className="ad-mesin-btn ad-mesin-btn--primary"
                                    disabled={agendaForm.processing || (form === 'add' && marked.size === 0)}>
                                    {agendaForm.processing ? 'Menyimpan…' : form === 'add' && marked.size > 1
                                        ? `Simpan ${marked.size} Tanggal` : 'Simpan Agenda'}
                                </button>
                            </div>
                        </form>
                        <small className="ad-set-note" style={{ paddingTop: 8 }}>
                            Agenda langsung tampil di kalender semua pengguna (mahasiswa, dosen, laboran).
                        </small>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
