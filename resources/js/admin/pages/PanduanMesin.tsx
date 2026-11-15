import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Activity, Check, ChevronRight, Copy, Cpu, DoorOpen, Download,
    FileCode, HeartPulse, KeyRound, Plus, Search, Settings2, Wifi, WifiOff,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { cn } from '@admin/lib/utils';
import type { PanduanMesinItem, PanduanMesinProps } from '@admin/types';

type PageTab = 'program' | 'endpoint' | 'sketch' | 'panduan';
type SubTab = 'konfigurasi' | 'endpoint' | 'sketch' | 'log';

type Ep = { method: string; path: string; body: string; desc: string };
const EP_UMUM: Ep[] = [
    { method: 'GET/POST', path: '/heartbeat', body: '{ "id_mesin": "M-XXX-01" }', desc: 'Sinyal hidup berkala — wajib agar status Online' },
];
const EP_ABSENSI: Ep[] = [
    { method: 'POST', path: '/tap', body: '{ "finger_id": 12, "id_mesin": "…", "event_id": "…", "waktu_tap": "…" }', desc: 'Kirim tap fingerprint — server memutuskan masuk/pulang/terlambat' },
    { method: 'GET', path: '/fingerprint/sync', body: '—', desc: 'Sinkronisasi template sidik jari ke sensor' },
    { method: 'GET', path: '/fingerprint/resolve', body: '?finger_id=12', desc: 'Resolve identitas user dari fingerprint' },
    { method: 'POST', path: '/fingerprint/enroll', body: '{ "finger_id": 12, "template": "…" }', desc: 'Enroll / tambah template sidik jari' },
    { method: 'DELETE', path: '/fingerprint/{finger_id}', body: '—', desc: 'Hapus slot fingerprint di server & sensor' },
];
const EP_PINTU: Ep[] = [
    { method: 'POST', path: '/ruangan/access/check', body: '{ "tipe": "rfid|pin|fingerprint", "nilai": "…" }', desc: 'Tanya izin — respons { diizinkan, alasan }. Ruangan dari binding mesin di server' },
    { method: 'POST', path: '/ruangan/access/event', body: '{ "jenis": "buka|tolak|telemetri", "hasil": "…" }', desc: 'Lapor kejadian nyata perangkat (pintu dibuka/ditolak)' },
];

const CONN_DOT: Record<string, string> = { online: '#34d399', offline: '#fbbf24', inactive: '#64748b' };

export default function PanduanMesin(props: PanduanMesinProps) {
    const [pageTab, setPageTab] = useState<PageTab>('program');
    const [subTab, setSubTab] = useState<SubTab>('konfigurasi');
    const [q, setQ] = useState('');
    const [selId, setSelId] = useState<number | null>(props.mesin[0]?.id ?? null);
    const [copied, setCopied] = useState<string | null>(null);
    const [connTest, setConnTest] = useState<{ state: 'idle' | 'busy' | 'done' | 'fail'; label: string; ms: number | null }>({ state: 'idle', label: '', ms: null });
    const [openEp, setOpenEp] = useState<string | null>(null);

    const copy = (t: string, k: string) => {
        navigator.clipboard?.writeText(t).catch(() => {});
        setCopied(k);
        setTimeout(() => setCopied((c) => (c === k ? null : c)), 1400);
    };

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return props.mesin;
        return props.mesin.filter((m) =>
            `${m.nama_mesin} ${m.id_mesin} ${m.tipe_label}`.toLowerCase().includes(needle));
    }, [props.mesin, q]);

    const sel = props.mesin.find((m) => m.id === selId) ?? props.mesin[0] ?? null;
    const selEps = sel ? (sel.tipe === 'absensi' ? [...EP_UMUM, ...EP_ABSENSI] : [...EP_UMUM, ...EP_PINTU]) : [];

    const pick = (m: PanduanMesinItem) => {
        setSelId(m.id);
        setSubTab('konfigurasi');
        setConnTest({ state: 'idle', label: '', ms: null });
    };

    const ujiKoneksi = async () => {
        if (!sel) return;
        setConnTest({ state: 'busy', label: 'Menguji…', ms: null });
        const t0 = performance.now();
        try {
            const r = await fetch(sel.conn_url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            const ms = Math.round(performance.now() - t0);
            const label = j.conn === 'online'
                ? `Koneksi berhasil — heartbeat ${j.last_seen_label}`
                : j.conn === 'offline'
                    ? `Offline — ${j.last_seen_label}`
                    : 'Mesin dinonaktifkan admin';
            setConnTest({ state: 'done', label, ms });
        } catch {
            setConnTest({ state: 'fail', label: 'Permintaan gagal — cek server', ms: null });
        }
    };

    const epRows = (rows: Ep[], prefix: string) => rows.map((e) => {
        const key = `${prefix}${e.path}`;
        const open = openEp === key;
        return (
            <div className="pm-eprow" key={key}>
                <button type="button" className="pm-eprow__btn" onClick={() => setOpenEp(open ? null : key)}>
                    <span className={cn('pm-ep__method', `pm-ep__method--${e.method.split('/')[0].toLowerCase()}`)}>{e.method}</span>
                    <code className="pm-eprow__path">{e.path}</code>
                    <span className="pm-eprow__desc">{e.desc}</span>
                    <ChevronRight size={14} className={cn('pm-eprow__caret', open && 'is-open')} />
                </button>
                {open && <pre className="pm-eprow__payload">{e.body}</pre>}
            </div>
        );
    });

    const pageTabs: { key: PageTab; label: string; icon: typeof FileCode }[] = [
        { key: 'program', label: 'Program Mesin', icon: FileCode },
        { key: 'endpoint', label: 'Endpoint API', icon: Activity },
        { key: 'sketch', label: 'Template Sketch', icon: Download },
        { key: 'panduan', label: 'Panduan Integrasi', icon: HeartPulse },
    ];
    const subTabs: { key: SubTab; label: string }[] = [
        { key: 'konfigurasi', label: 'Konfigurasi' },
        { key: 'endpoint', label: 'Endpoint API' },
        { key: 'sketch', label: 'Sketch / Firmware' },
        { key: 'log', label: 'Log & Riwayat' },
    ];

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Program Mesin" subtitle="Kelola program, endpoint, dan integrasi setiap mesin laboratorium.">
            <Head title="Program Mesin" />

            {/* Hero + stat */}
            <div className="pm-hero">
                <div className="pm-hero__txt">
                    <h1>Kelola Integrasi &amp; Program Mesin</h1>
                    <p>Atur endpoint, program, dan sketch untuk setiap mesin laboratorium — seperti mesin absensi, mesin pintu, dan perangkat IoT lainnya.</p>
                </div>
                <div className="pm-hero__stats">
                    <span><Cpu size={14} />{props.stats.total} <em>Total Mesin</em></span>
                    <span className="is-ok"><i />{props.stats.online} <em>Online</em></span>
                    <span className="is-off"><i />{props.stats.offline} <em>Offline</em></span>
                </div>
                <FileCode className="pm-hero__icon" />
            </div>

            {/* Page tabs + tambah */}
            <div className="pm-tabbar">
                {pageTabs.map((t) => (
                    <button key={t.key} type="button" className={cn('pm-tab', pageTab === t.key && 'is-active')} onClick={() => setPageTab(t.key)}>
                        <t.icon size={14} />{t.label}
                    </button>
                ))}
                <a href={props.urls.mesin} className="pm-add"><Plus size={14} />Tambah Mesin</a>
            </div>

            {pageTab === 'program' && (
                <div className="pm-master">
                    {/* Daftar mesin */}
                    <div className="pm-list pm-panel">
                        <div className="pm-panel__title">Daftar Mesin</div>
                        <div className="pm-panel__sub">Pilih mesin untuk mengatur program &amp; integrasinya.</div>
                        <div className="pm-search">
                            <Search size={14} />
                            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari nama mesin…" />
                        </div>
                        {filtered.map((m) => (
                            <button key={m.id} type="button" className={cn('pm-item', sel?.id === m.id && 'is-active')} onClick={() => pick(m)}>
                                <span className="pm-item__ico">{m.tipe === 'absensi' ? <Cpu size={16} /> : <DoorOpen size={16} />}</span>
                                <span className="pm-item__txt">
                                    <strong>{m.nama_mesin}</strong>
                                    <small>{m.id_mesin}</small>
                                </span>
                                <span className="pm-item__conn" style={{ color: CONN_DOT[m.conn] }}>
                                    <i style={{ background: CONN_DOT[m.conn] }} />{m.conn_label}
                                </span>
                            </button>
                        ))}
                        {filtered.length === 0 && <div className="pm-empty">Tidak ada mesin cocok.</div>}
                    </div>

                    {/* Detail mesin */}
                    {sel ? (
                        <div className="pm-detail">
                            <div className="pm-panel pm-detail__head">
                                <span className="pm-item__ico pm-item__ico--big">{sel.tipe === 'absensi' ? <Cpu size={20} /> : <DoorOpen size={20} />}</span>
                                <div className="pm-detail__idtxt">
                                    <div className="pm-detail__name">{sel.nama_mesin} <code>{sel.id_mesin}</code></div>
                                    <div className="pm-detail__sub">
                                        {sel.tipe === 'absensi'
                                            ? 'Mesin absensi berbasis fingerprint untuk pencatat kehadiran.'
                                            : 'Mesin pintu akses ruangan — keputusan izin di server.'}
                                    </div>
                                </div>
                                <span className={cn('pm-conn', `pm-conn--${sel.conn}`)}><i />{sel.conn_label}</span>
                            </div>

                            <div className="pm-subtabs">
                                {subTabs.map((t) => (
                                    <button key={t.key} type="button" className={cn('pm-subtab', subTab === t.key && 'is-active')} onClick={() => setSubTab(t.key)}>
                                        {t.label}
                                    </button>
                                ))}
                            </div>

                            {subTab === 'konfigurasi' && (
                                <div className="pm-detailgrid">
                                    <div className="pm-panel">
                                        <div className="pm-panel__title"><Settings2 size={14} /> Informasi Mesin</div>
                                        <div className="pm-kv2"><span>Tipe Mesin</span><b className="pm-badge">{sel.tipe_label}</b></div>
                                        <div className="pm-kv2"><span>Lokasi / Ruangan</span><b>{sel.ruangan ?? <em>Belum terikat</em>}</b></div>
                                        <div className="pm-kv2"><span>Status Koneksi</span><b style={{ color: CONN_DOT[sel.conn] }}>{sel.conn_label}</b></div>
                                        <div className="pm-kv2"><span>Terakhir Aktif</span><b>{sel.last_seen_exact ?? sel.last_seen_label}</b></div>
                                        <div className="pm-kv2"><span>Aktivitas Terakhir</span><b>{sel.last_tap_label}</b></div>
                                        <a className="pm-linkedit" href={props.urls.mesin}>Edit informasi di halaman Mesin <ChevronRight size={13} /></a>
                                    </div>
                                    <div className="pm-panel">
                                        <div className="pm-panel__title"><KeyRound size={14} /> Kredensial &amp; Koneksi</div>
                                        <div className="pm-kv2"><span>Base URL API</span>
                                            <span className="pm-copycode"><code>{props.api_base}</code>
                                                <button type="button" onClick={() => copy(props.api_base, 'base')} aria-label="Salin">{copied === 'base' ? <Check size={13} /> : <Copy size={13} />}</button>
                                            </span>
                                        </div>
                                        <div className="pm-kv2"><span>API Key</span>
                                            <span className="pm-copycode"><code>••••••••••••••••</code>
                                                <button type="button" onClick={() => copy('X-API-KEY: ', 'hdr')} aria-label="Salin header">{copied === 'hdr' ? <Check size={13} /> : <Copy size={13} />}</button>
                                            </span>
                                        </div>
                                        <div className="pm-keynote">Key hanya tampil sekali saat mesin dibuat — regenerate lewat halaman Mesin bila hilang.</div>
                                        <div className="pm-kv2"><span>Interval Heartbeat</span><b>{props.threshold_minutes} menit (ambang server)</b></div>
                                        <div className="pm-kv2"><span>Uji Koneksi</span>
                                            <button type="button" className="pm-test" onClick={ujiKoneksi} disabled={connTest.state === 'busy'}>
                                                <Wifi size={13} />{connTest.state === 'busy' ? 'Menguji…' : 'Uji Koneksi'}
                                            </button>
                                        </div>
                                        {connTest.state !== 'idle' && connTest.state !== 'busy' && (
                                            <div className={cn('pm-testresult', connTest.state === 'done' && sel.conn === 'online' ? 'is-ok' : 'is-warn')}>
                                                {connTest.state === 'done' && sel.conn === 'online' ? <Check size={13} /> : <WifiOff size={13} />}
                                                {connTest.label}{connTest.ms != null && <em> · Respons 200 OK · {connTest.ms} ms</em>}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {subTab === 'endpoint' && (
                                <div className="pm-panel">
                                    <div className="pm-panel__title"><Activity size={14} /> Program &amp; Aksi Mesin</div>
                                    <div className="pm-panel__sub">Endpoint yang dipakai {sel.nama_mesin} — klik untuk lihat payload.</div>
                                    {epRows(selEps, 'd-')}
                                </div>
                            )}

                            {subTab === 'sketch' && (
                                <div className="pm-panel">
                                    <div className="pm-panel__title"><FileCode size={14} /> Template Sketch</div>
                                    <div className="pm-panel__sub">Program Arduino/ESP32 untuk mesin ini — ID mesin &amp; base URL sudah terisi.</div>
                                    <div className="pm-file">
                                        <span className="pm-file__ico"><FileCode size={18} /></span>
                                        <div className="pm-file__txt">
                                            <strong>smartbrail_{sel.id_mesin.toLowerCase()}_{sel.tipe === 'absensi' ? 'absensi' : 'pintu'}.ino</strong>
                                            <small>{sel.tipe === 'absensi' ? 'Update firmware absensi — tambah heartbeat, tap & enroll tidak berubah' : 'Sketch lengkap mesin pintu — RFID → access/check → relay + stub PIN/fingerprint'}</small>
                                        </div>
                                        <a className="pm-dl" href={sel.sketch_url}><Download size={13} /> Unduh</a>
                                    </div>
                                    <div className="pm-note">Isi <code>WIFI_SSID</code>, <code>WIFI_PASS</code>, dan <code>MESIN_API_KEY</code> sebelum flash. API key tidak disertakan — tempel dari kartu setup mesin.</div>
                                </div>
                            )}

                            {subTab === 'log' && (
                                <div className="pm-panel">
                                    <div className="pm-panel__title"><Activity size={14} /> Log &amp; Riwayat</div>
                                    <div className="pm-panel__sub">{sel.riwayat.length ? `${sel.riwayat.length} aktivitas terakhir mesin ini.` : 'Belum ada aktivitas tercatat.'}</div>
                                    {sel.riwayat.map((r, i) => (
                                        <div className="pm-logrow" key={i}>
                                            <span className="pm-logrow__label">{r.label}</span>
                                            <span className={cn('pm-logrow__hasil', /tolak|ditolak|alfa|gagal/i.test(r.hasil) ? 'is-bad' : 'is-ok')}>{r.hasil}</span>
                                            <span className="pm-logrow__waktu">{r.waktu}</span>
                                        </div>
                                    ))}
                                    {sel.riwayat.length === 0 && <div className="pm-empty">Aktivitas akan muncul setelah perangkat mulai berkomunikasi.</div>}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="pm-panel pm-empty">Belum ada mesin — <a href={props.urls.mesin}>daftarkan dulu</a>.</div>
                    )}
                </div>
            )}

            {pageTab === 'endpoint' && (
                <div className="pm-panel">
                    <div className="pm-panel__title"><Activity size={14} /> Referensi Endpoint API</div>
                    <div className="pm-panel__sub">Semua request memakai header <code>X-API-KEY</code> ke <code>{props.api_base}</code>.</div>
                    <div className="pm-ep__title" style={{ color: '#38bdf8' }}>Semua mesin</div>
                    {epRows(EP_UMUM, 'u-')}
                    <div className="pm-ep__title" style={{ color: '#34d399' }}>Mesin Absensi</div>
                    {epRows(EP_ABSENSI, 'a-')}
                    <div className="pm-ep__title" style={{ color: '#a78bfa' }}>Mesin Pintu / Akses Ruangan</div>
                    {epRows(EP_PINTU, 'p-')}
                </div>
            )}

            {pageTab === 'sketch' && (
                <div className="pm-panel">
                    <div className="pm-panel__title"><Download size={14} /> Template Sketch per Mesin</div>
                    <div className="pm-panel__sub">File .ino siap-flash — tinggal isi WiFi &amp; API key.</div>
                    {props.mesin.map((m) => (
                        <div className="pm-file" key={m.id}>
                            <span className="pm-file__ico">{m.tipe === 'absensi' ? <Cpu size={18} /> : <DoorOpen size={18} />}</span>
                            <div className="pm-file__txt">
                                <strong>smartbrail_{m.id_mesin.toLowerCase()}_{m.tipe === 'absensi' ? 'absensi' : 'pintu'}.ino</strong>
                                <small>{m.nama_mesin} · {m.tipe_label}{m.ruangan ? ` · ${m.ruangan}` : ' · belum terikat ruangan'}</small>
                            </div>
                            <a className="pm-dl" href={m.sketch_url}><Download size={13} /> Unduh .ino</a>
                        </div>
                    ))}
                    <div className="pm-note">Mesin pintu tanpa ruangan terikat akan selalu ditolak server (<code>mesin_tidak_terikat_ruangan</code>) — ikat dulu di menu Ruangan sebelum uji.</div>
                </div>
            )}

            {pageTab === 'panduan' && (
                <div className="pm-panel">
                    <div className="pm-panel__title"><HeartPulse size={14} /> Panduan Integrasi &amp; Uji</div>
                    <ol className="pm-steps">
                        <li>Unduh sketch mesin, isi <code>WIFI_SSID</code>, <code>WIFI_PASS</code>, <code>SERVER_BASE</code> ({props.api_base}), dan <code>MESIN_API_KEY</code> dari kartu setup.</li>
                        <li>Flash ke ESP32, buka Serial Monitor — pastikan <code>POST /heartbeat → 200</code>; status mesin berubah <strong>Online</strong> (ambang {props.threshold_minutes} menit).</li>
                        <li>Mesin pintu: tempel kartu RFID → perangkat kirim <code>access/check</code>; server memutuskan sesuai izin (staf bebas, mahasiswa butuh izin aktif, petugas sesuai jadwal).</li>
                        <li>Bila diizinkan, relay membuka + perangkat lapor <code>access/event</code> jenis <code>buka</code>; semua percobaan tercatat di log akses.</li>
                        <li>Uji kartu tak dikenal / mahasiswa tanpa izin → harus <code>diizinkan:false</code>, buzzer tolak, tercatat tolak.</li>
                        <li>Mesin absensi: cukup update konfigurasi + heartbeat — fungsi tap &amp; enroll fingerprint tidak berubah.</li>
                    </ol>
                    <div className="pm-note">
                        <HeartPulse size={13} /> Pantau status tiap mesin di <a href={props.urls.monitoring}>Monitoring Operasional</a> · daftar mesin di <a href={props.urls.mesin}>halaman Mesin</a>.
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
