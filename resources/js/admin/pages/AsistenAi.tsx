import { useEffect, useMemo, useRef, useState } from 'react';
import {
    ArrowUp, BarChart3, BookOpen, CalendarCheck, Check, ClipboardList, Cpu,
    DoorOpen, FileText, History, Lightbulb, Megaphone, MessageSquare,
    PanelLeft, Paperclip, Pencil, Plus, ShieldCheck, Sparkles, Square, Trash2, Users, X, Zap,
} from 'lucide-react';
import { AdminShell } from '@admin/components/AdminShell';
import { asset } from '@admin/lib/asset';
import { cn } from '@admin/lib/utils';
import type {
    AiActionCard, AiBlock, AiChartPayload, AiConversationItem,
    AiExample, AiMessageItem, AsistenAiProps,
} from '@admin/types';

const ICONS = {
    'bar-chart-3': BarChart3, 'users': Users, 'door-open': DoorOpen,
    'cpu': Cpu, 'file-text': FileText, 'calendar-check': CalendarCheck,
    'clipboard-list': ClipboardList, 'megaphone': Megaphone,
    'message-square': MessageSquare, 'book-open': BookOpen,
} as const;

const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const SLASH_COMMANDS = [
    { cmd: '/help', desc: 'Lihat semua kemampuan & opsi jelajah' },
    { cmd: '/menu rekap', desc: 'Pilihan rekap absensi (hari/minggu/bulan)' },
    { cmd: '/menu akun', desc: 'Akun, approval & pencarian mahasiswa' },
    { cmd: '/menu ruangan', desc: 'Status ruangan, pengajuan & petugas' },
    { cmd: '/menu perangkat', desc: 'Mesin IoT, fingerprint & log tap' },
    { cmd: '/menu laporan', desc: 'Susun laporan + tombol unduh' },
    { cmd: '/menu aksi', desc: 'Aksi yang bisa dijalankan (butuh konfirmasi)' },
    { cmd: '/menu', desc: 'Tampilkan semua topik menu' },
    { cmd: '/grafik', desc: 'Tren absensi sebagai kartu grafik' },
    { cmd: '/pengguna', desc: 'Ringkasan data pengguna sebagai kartu', fill: true },
    { cmd: '/impor', desc: 'Info fitur impor file akun' },
    { cmd: '/baru', desc: 'Mulai obrolan baru' },
];

// Dosen: hanya menu dalam hak aksesnya (tanpa approval, mesin, aksi tulis, impor).
const DOSEN_COMMANDS = [
    { cmd: '/help', desc: 'Lihat semua kemampuan & opsi jelajah' },
    { cmd: '/menu rekap', desc: 'Pilihan rekap absensi (hari/minggu/bulan)' },
    { cmd: '/menu akun', desc: 'Mahasiswa bimbingan & pencarian' },
    { cmd: '/menu pbl', desc: 'Tim PBL & pengajuan ruangan tim' },
    { cmd: '/menu ruangan', desc: 'Pengajuan ruangan tim & agenda' },
    { cmd: '/menu laporan', desc: 'Susun laporan + tombol unduh' },
    { cmd: '/menu', desc: 'Tampilkan semua topik menu' },
    { cmd: '/grafik', desc: 'Tren absensi sebagai kartu grafik' },
    { cmd: '/pengguna', desc: 'Ringkasan mahasiswa sebagai kartu', fill: true },
    { cmd: '/baru', desc: 'Mulai obrolan baru' },
];

const uuid = () =>
    (crypto as { randomUUID?: () => string }).randomUUID?.()
    ?? `c-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

/** Teks sederhana → paragraf + **tebal** + `kode`; daftar "- " jadi list. */
function AiText({ text }: { text: string }) {
    const inline = (s: string, key: number) => {
        const parts = s.split(/(\*\*[^*]+\*\*|`[^`]+`)/g).filter(Boolean);
        return <span key={key}>{parts.map((p, i) =>
            p.startsWith('**') && p.endsWith('**')
                ? <strong key={i}>{p.slice(2, -2)}</strong>
                : p.startsWith('`') && p.endsWith('`')
                    ? <code key={i}>{p.slice(1, -1)}</code>
                    : p,
        )}</span>;
    };
    const lines = text.split('\n');
    const out: React.ReactNode[] = [];
    let list: string[] = [];
    const flush = (k: number) => {
        if (list.length) {
            out.push(<ul key={`ul${k}`}>{list.map((li, i) => <li key={i}>{inline(li, i)}</li>)}</ul>);
            list = [];
        }
    };
    lines.forEach((ln, i) => {
        const t = ln.trim();
        if (/^[-•*]\s+/.test(t)) { list.push(t.replace(/^[-•*]\s+/, '')); return; }
        flush(i);
        if (t === '') { out.push(<div key={i} style={{ height: 6 }} />); return; }
        out.push(<p key={i} style={{ margin: 0 }}>{inline(ln, i)}</p>);
    });
    flush(lines.length);
    return <div className="ad-ai-text">{out}</div>;
}

/** Grafik blok dari tool chart_absensi — SVG bar/line ringan. */
function AiChart({ chart }: { chart: AiChartPayload }) {
    const W = 520, H = 170, pad = { t: 14, r: 12, b: 30, l: 36 };
    const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
    const vals = chart.values ?? [];
    const max = Math.max(1, ...vals);
    const n = Math.max(1, vals.length);
    const step = iw / n;
    const pts = vals.map((v, i) => [pad.l + step * i + step / 2, pad.t + ih - (v / max) * ih] as const);
    const line = pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(' ');
    const labEvery = Math.ceil(n / 8);
    return (
        <div className="ad-ai-chart">
            <div className="ad-ai-chart__title">{chart.title}</div>
            {chart.subtitle ? <div className="ad-ai-chart__sub">{chart.subtitle}</div> : null}
            <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={chart.title}>
                {[0, 0.5, 1].map((f) => (
                    <g key={f}>
                        <line x1={pad.l} x2={W - pad.r} y1={pad.t + ih * (1 - f)} y2={pad.t + ih * (1 - f)} stroke="var(--ad-border)" strokeDasharray="3 4" />
                        <text x={pad.l - 6} y={pad.t + ih * (1 - f) + 3} textAnchor="end" fontSize="9" fill="var(--ad-muted)">{Math.round(max * f)}</text>
                    </g>
                ))}
                {chart.type === 'bar'
                    ? vals.map((v, i) => (
                        <rect key={i} x={pad.l + step * i + step * 0.18} y={pad.t + ih - (v / max) * ih}
                            width={step * 0.64} height={Math.max(1, (v / max) * ih)} rx={4}
                            fill={chart.color || '#0284c7'} opacity={0.85} />
                    ))
                    : (
                        <>
                            <path d={`${line} L${pts[pts.length - 1]?.[0] ?? pad.l} ${pad.t + ih} L${pad.l + step / 2} ${pad.t + ih} Z`} fill={chart.color || '#0284c7'} opacity={0.12} />
                            <path d={line} fill="none" stroke={chart.color || '#0284c7'} strokeWidth={2.2} strokeLinecap="round" />
                            {pts.map((p, i) => <circle key={i} cx={p[0]} cy={p[1]} r={3} fill={chart.color || '#0284c7'} />)}
                        </>
                    )}
                {(chart.labels ?? []).map((lb, i) => i % labEvery === 0 && (
                    <text key={i} x={pad.l + step * i + step / 2} y={H - 10} textAnchor="middle" fontSize="9" fill="var(--ad-muted)">{lb}</text>
                ))}
            </svg>
            {chart.note ? <div className="ad-ai-chart__note">{chart.note}</div> : null}
        </div>
    );
}

type BlockHead = { title?: string; subtitle?: string };

function BlockTitle({ head }: { head: BlockHead }) {
    if (!head.title && !head.subtitle) return null;
    return (
        <div className="ad-ai-blk__head">
            {head.title ? <div className="ad-ai-blk__title">{head.title}</div> : null}
            {head.subtitle ? <div className="ad-ai-blk__sub">{head.subtitle}</div> : null}
        </div>
    );
}

function StatsBlock({ block }: { block: AiBlock }) {
    const b = block as { title?: string; subtitle?: string; items?: { label: string; value: string | number; meta?: string }[] };
    const items = b.items ?? [];
    if (!items.length) return null;
    return (
        <div className="ad-ai-blk">
            <BlockTitle head={b} />
            <div className="ad-ai-stats">
                {items.map((s, i) => (
                    <div key={i} className="ad-ai-stat">
                        <span className="ad-ai-stat__value">{s.value}</span>
                        <span className="ad-ai-stat__label">{s.label}</span>
                        {s.meta ? <span className="ad-ai-stat__meta">{s.meta}</span> : null}
                    </div>
                ))}
            </div>
        </div>
    );
}

function TableBlock({ block }: { block: AiBlock }) {
    const b = block as {
        title?: string; subtitle?: string; note?: string; empty?: string; total?: number;
        columns?: { key: string; label: string; align?: string }[];
        rows?: Record<string, string | number | null>[];
    };
    const cols = b.columns ?? [];
    const rows = b.rows ?? [];
    return (
        <div className="ad-ai-blk">
            <BlockTitle head={b} />
            {rows.length === 0 ? (
                <div className="ad-ai-blk__empty">{b.empty ?? 'Tidak ada data.'}</div>
            ) : (
                <div className="ad-ai-table-wrap">
                    <table className="ad-ai-table">
                        <thead><tr>{cols.map((c) => <th key={c.key} className={c.align === 'end' ? 'is-end' : ''}>{c.label}</th>)}</tr></thead>
                        <tbody>
                            {rows.map((r, ri) => (
                                <tr key={ri}>{cols.map((c) => <td key={c.key} className={c.align === 'end' ? 'is-end' : ''}>{r[c.key] ?? '—'}</td>)}</tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {b.note ? <div className="ad-ai-blk__note">{b.note}</div> : null}
        </div>
    );
}

function LinksBlock({ block }: { block: AiBlock }) {
    const items = (block as { items?: { label: string; href: string; kind?: string }[] }).items ?? [];
    if (!items.length) return null;
    return (
        <div className="ad-ai-links">
            {items.map((l, i) => (
                <a key={i} href={l.href} target={l.kind === 'download' ? '_self' : '_blank'} rel="noreferrer"
                    className={cn('ad-ai-link', l.kind === 'download' && 'is-download')}>
                    <FileText size={15} />
                    <span>{l.label}</span>
                </a>
            ))}
        </div>
    );
}

function ProposalCard({ block, cp }: { block: AiBlock; cp?: AsistenAiProps['cp'] }) {
    const p = (block as { proposal?: import('@admin/types').AiProposalPayload }).proposal;
    const [status, setStatus] = useState<string>(p?.status ?? 'pending');
    const [busy, setBusy] = useState(false);
    if (!p) return null;
    const act = async (kind: 'confirm' | 'cancel') => {
        const tpl = kind === 'confirm' ? cp?.proposal_confirm : cp?.proposal_cancel;
        if (!tpl) return;
        setBusy(true);
        try {
            const res = await fetch(tpl.replace('/0/', `/${p.id}/`), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            const d = await res.json().catch(() => ({}));
            setStatus(d?.proposal?.status ?? (kind === 'confirm' ? 'confirmed' : 'cancelled'));
        } catch {
            setStatus('failed');
        } finally {
            setBusy(false);
        }
    };
    const label: Record<string, string> = {
        pending: 'Menunggu konfirmasi', confirmed: 'Dijalankan', cancelled: 'Dibatalkan', failed: 'Gagal', expired: 'Kedaluwarsa',
    };
    return (
        <div className={cn('ad-ai-proposal', p.dangerous && 'is-danger', status !== 'pending' && 'is-done')}>
            <div className="ad-ai-proposal__head">
                <ShieldCheck size={15} />
                <span className="ad-ai-proposal__title">{p.title}</span>
                <span className={cn('ad-ai-proposal__status', `is-${status}`)}>{label[status] ?? status}</span>
            </div>
            <div className="ad-ai-proposal__summary">{p.summary}</div>
            {status === 'pending' ? (
                <div className="ad-ai-proposal__actions">
                    <button type="button" className="ad-ai-btn is-primary" disabled={busy} onClick={() => act('confirm')}>
                        <Check size={14} /> {p.action_label ?? 'Konfirmasi'}
                    </button>
                    <button type="button" className="ad-ai-btn" disabled={busy} onClick={() => act('cancel')}>
                        <X size={14} /> Batalkan
                    </button>
                </div>
            ) : null}
        </div>
    );
}

function OptionsBlock({ block, onPick }: { block: AiBlock; onPick?: (msg: string) => void }) {
    const b = block as { title?: string; items?: { label: string; message: string }[] };
    const items = b.items ?? [];
    if (!items.length) return null;
    return (
        <div className="ad-ai-opts">
            {b.title ? <div className="ad-ai-opts__title">{b.title}</div> : null}
            <div className="ad-ai-opts__grid">
                {items.map((o, i) => (
                    <button key={i} type="button" className="ad-ai-opt" onClick={() => onPick?.(o.message)}>
                        <Zap style={{ width: 12, height: 12 }} /> {o.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

function BlockView({ block, cp, onPick }: { block: AiBlock; cp?: AsistenAiProps['cp']; onPick?: (msg: string) => void }) {
    if (block.type === 'chart' && 'chart' in block && block.chart) {
        return <AiChart chart={block.chart as AiChartPayload} />;
    }
    if (block.type === 'stats') return <StatsBlock block={block} />;
    if (block.type === 'table') return <TableBlock block={block} />;
    if (block.type === 'links') return <LinksBlock block={block} />;
    if (block.type === 'options') return <OptionsBlock block={block} onPick={onPick} />;
    if (block.type === 'proposal') return <ProposalCard block={block} cp={cp} />;
    if (block.type === 'archived_action') {
        return <div className="ad-ai-arch">Aksi tersimpan: {(block as { title?: string }).title ?? 'Aksi'}</div>;
    }
    return null;
}

/** Riwayat dikelompokkan: Hari ini / 7 Hari Terakhir / Lebih lama. */
function groupConversations(items: AiConversationItem[]) {
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const week = new Date(today); week.setDate(week.getDate() - 7);
    const groups: { label: string; items: AiConversationItem[] }[] = [
        { label: 'Hari ini', items: [] },
        { label: '7 Hari Terakhir', items: [] },
        { label: 'Lebih lama', items: [] },
    ];
    for (const c of items) {
        const d = c.last_message_at ? new Date(c.last_message_at) : null;
        if (d && d >= today) groups[0].items.push(c);
        else if (d && d >= week) groups[1].items.push(c);
        else groups[2].items.push(c);
    }
    return groups.filter((g) => g.items.length > 0);
}

const fmtConv = (iso: string | null) => {
    if (!iso) return '';
    const d = new Date(iso);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return d >= today
        ? d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};

export default function AsistenAi(props: AsistenAiProps) {
    const [view, setView] = useState<'welcome' | 'chat'>('welcome');
    const [msgs, setMsgs] = useState<AiMessageItem[]>([]);
    const [convId, setConvId] = useState<number | null>(null);
    const [history, setHistory] = useState<AiConversationItem[]>(props.conversations);
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);
    const [status, setStatus] = useState<string | null>(null);
    const [loadErr, setLoadErr] = useState<string | null>(null);
    const [sideOpen, setSideOpen] = useState(false);
    const [attachInfo, setAttachInfo] = useState(false);
    const [pendingFiles, setPendingFiles] = useState<{ id: string; name: string; uploading?: boolean }[]>([]);
    // Lampiran asisten dinonaktifkan sementara (hemat kuota AI vision).
    const attOn = props.cp.attachments_enabled !== false;
    const [zoomAtt, setZoomAtt] = useState<{ id: string; name: string } | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const [loadingConv, setLoadingConv] = useState(false);
    const [renamingId, setRenamingId] = useState<number | null>(null);
    const [renameVal, setRenameVal] = useState('');
    const [confirmDelId, setConfirmDelId] = useState<number | null>(null);
    const [capsOpen, setCapsOpen] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const lastSent = useRef<{ text: string; clientId: string } | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const taRef = useRef<HTMLTextAreaElement>(null);

    const groups = useMemo(() => groupConversations(history), [history]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [msgs, status]);

    useEffect(() => () => abortRef.current?.abort(), []);

    // Status selalu mencerminkan tahap nyata: terkirim → menunggu → ambil data → menyusun → mengetik.
    const statusLabel = status ?? 'Menghubungi layanan AI…';

    // ?c=<id> — buka percakapan milik sendiri secara eksplisit saat halaman dimuat.
    const didInit = useRef(false);
    useEffect(() => {
        if (didInit.current) return;
        didInit.current = true;
        if (props.initial_conversation) void openConv(props.initial_conversation);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const autosize = () => {
        const ta = taRef.current;
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = `${Math.min(140, ta.scrollHeight)}px`;
    };

    const upsertHistory = (c: { id: number; title?: string; last_message_at?: string | null }) => {
        setHistory((h) => {
            const rest = h.filter((x) => x.id !== c.id);
            return [{
                id: c.id,
                title: c.title ?? 'Percakapan baru',
                last_message_at: c.last_message_at ?? new Date().toISOString(),
            }, ...rest];
        });
    };

    /** Unggah lampiran staged (gambar/teks) — id dipakai saat pesan berikutnya dikirim. */
    const uploadFiles = async (files: FileList | null) => {
        if (!files) return;
        const room = Math.max(0, 3 - pendingFiles.length);
        for (const f of Array.from(files).slice(0, room)) {
            const label = f.name === 'image.png'
                ? `Screenshot ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}.png`
                : f.name;
            const temp = { id: 'tmp-' + Math.random().toString(36).slice(2), name: label, uploading: true };
            setPendingFiles((ps) => [...ps, temp]);
            try {
                const fd = new FormData();
                fd.append('file', f);
                const res = await fetch(props.cp.attachment ?? '', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: fd,
                });
                const d = await res.json().catch(() => null);
                if (!res.ok || !d?.ok) throw new Error(String(res.status));
                setPendingFiles((ps) => ps.map((p) => (p.id === temp.id ? { id: String(d.id), name: label } : p)));
            } catch {
                setPendingFiles((ps) => ps.filter((p) => p.id !== temp.id));
                setLoadErr(`Gagal mengunggah lampiran: ${f.name} (maks 4 MB; JPG/PNG/WebP/TXT/CSV).`);
            }
        }
        if (fileRef.current) fileRef.current.value = '';
    };

    /** Jalankan stream SSE untuk satu pesan user (assistant bubble = elemen terakhir). */
    const runStream = async (text: string, clientId: string, cid: number | null, atts: { id: string; name: string }[] = []) => {
        setBusy(true); setLoadErr(null); setStatus('Mengirim pertanyaan…');
        abortRef.current?.abort();
        const abort = new AbortController() as AbortController & { byUser?: boolean };
        abortRef.current = abort;
        let watchdogDone = () => {};
        try {
            const res = await fetch(props.cp.stream, {
                method: 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: {
                    'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/event-stream', 'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message: text, conversation_id: cid, client_id: clientId, attachments: atts }),
            });
            const ct = res.headers.get('content-type') ?? '';
            if (!res.ok || !res.body || !ct.includes('text/event-stream')) {
                throw new Error(res.ok ? 'bukan-sse' : `HTTP ${res.status}`);
            }
            setStatus('Menunggu jawaban…');

            const reader = res.body.getReader();
            const dec = new TextDecoder();
            let buf = '';
            // Watchdog: stream boleh lama, tapi tidak boleh menggantung diam.
            const WATCHDOG_MS = 60000;
            let lastChunkAt = Date.now();
            const watchdog = setInterval(() => {
                if (Date.now() - lastChunkAt > WATCHDOG_MS) {
                    clearInterval(watchdog);
                    abort.abort();
                }
            }, 2000);
            watchdogDone = () => clearInterval(watchdog);
            const patchLast = (fn: (m: AiMessageItem) => AiMessageItem) =>
                setMsgs((ms) => {
                    // Target bubble streaming milik request ini — jangan sentuh bubble lain
                    // (mis. info paperclip) walau ia jadi pesan terakhir.
                    let idx = -1;
                    for (let i = ms.length - 1; i >= 0; i--) {
                        if (ms[i].role === 'assistant' && ms[i].status === 'streaming') { idx = i; break; }
                    }
                    if (idx === -1) return ms;
                    return ms.map((m, i) => (i === idx ? fn(m) : m));
                });

            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                buf += dec.decode(value, { stream: true });
                lastChunkAt = Date.now();
                const chunks = buf.split('\n\n');
                buf = chunks.pop() ?? '';
                for (const chunk of chunks) {
                    let ev = 'message', data = '';
                    for (const ln of chunk.split('\n')) {
                        if (ln.startsWith('event:')) ev = ln.slice(6).trim();
                        else if (ln.startsWith('data:')) data += ln.slice(5).trim();
                    }
                    if (!data) continue;
                    let d: Record<string, unknown> = {};
                    try { d = JSON.parse(data); } catch { continue; }

                    if (ev === 'meta') {
                        const c = d.conversation as { id: number; title: string; last_message_at?: string };
                        setConvId(c.id); upsertHistory(c);
                    } else if (ev === 'status') {
                        setStatus(String(d.label ?? 'Memproses…'));
                    } else if (ev === 'delta') {
                        setStatus(null);
                        patchLast((m) => ({ ...m, content: m.content + String(d.text ?? '') }));
                    } else if (ev === 'block') {
                        setStatus('Menampilkan data…');
                        patchLast((m) => ({ ...m, blocks: [...(m.blocks ?? []), d.block as AiBlock] }));
                    } else if (ev === 'replay') {
                        const rm = d.message as AiMessageItem;
                        patchLast(() => ({ ...rm, status: 'complete' }));
                    } else if (ev === 'error') {
                        patchLast((m) => ({ ...m, status: 'error', error: String(d.message ?? 'Gagal memuat jawaban.') }));
                    } else if (ev === 'done') {
                        patchLast((m) => ({ ...m, status: String(d.status ?? 'complete') }));
                    }
                }
            }
            // Stream berakhir tanpa event done — jangan biarkan bubble menggantung.
            patchLast((m) => m.status === 'streaming'
                ? {
                    ...m,
                    status: 'error',
                    error: m.content
                        ? 'Koneksi terputus sebelum jawaban selesai. Coba kirim ulang.'
                        : 'Tidak ada jawaban dari server — mungkin sesi Anda berakhir. Muat ulang lalu coba lagi.',
                }
                : m);
        } catch (e) {
            if (!abort.signal.aborted) {
                setMsgs((ms) => ms.map((m, i) => (i === ms.length - 1 && m.role === 'assistant'
                    ? { ...m, status: 'error', error: 'Koneksi terputus. Coba kirim ulang.' }
                    : m)));
            } else if (!abort.byUser) {
                // Di-abort watchdog — bukan oleh pengguna: beri info gagal yang jelas.
                setMsgs((ms) => ms.map((m, i) => {
                    const isStreaming = m.role === 'assistant' && m.status === 'streaming';
                    return (i === ms.length - 1 || isStreaming) && m.role === 'assistant'
                        ? { ...m, status: 'error', error: 'Server berhenti merespons lebih dari 60 detik — coba kirim ulang.' }
                        : m;
                }));
            }
        } finally {
            watchdogDone();
            setBusy(false); setStatus(null);
        }
    };

    const send = (raw?: string) => {
        const text = (raw ?? input).trim();
        const atts = pendingFiles.filter((f) => !f.uploading);
        if (!text || busy || pendingFiles.some((f) => f.uploading)) return;
        if (text === '/baru') { newChat(); return; }
        const shown = text;
        const msgAtts = atts.map((a) => ({ id: a.id, name: a.name, image: /\.(png|jpe?g|webp)$/i.test(a.id) }));
        const clientId = uuid();
        lastSent.current = { text: shown, clientId };
        setView('chat');
        setMsgs((ms) => [...ms,
            { role: 'user', content: shown, client_id: clientId, attachments: msgAtts.length ? msgAtts : undefined },
            { role: 'assistant', content: '', status: 'streaming', blocks: [] },
        ]);
        setInput(''); setPendingFiles([]); autosize();
        void runStream(shown, clientId, convId, atts);
    };

    const stop = () => {
        const c = abortRef.current as (AbortController & { byUser?: boolean }) | null;
        if (c) c.byUser = true;
        abortRef.current?.abort();
        setMsgs((ms) => ms.map((m, i) => (i === ms.length - 1 && m.role === 'assistant' && m.status === 'streaming'
            ? { ...m, status: 'stopped', error: m.content ? null : 'Jawaban dihentikan.' }
            : m)));
        setBusy(false); setStatus(null);
    };

    const retry = () => {
        const last = lastSent.current;
        if (!last || busy) return;
        setMsgs((ms) => ms.map((m, i) => (i === ms.length - 1 && m.role === 'assistant'
            ? { ...m, content: '', status: 'streaming', error: null, blocks: [] }
            : m)));
        void runStream(last.text, last.clientId, convId);
    };

    const newChat = () => {
        const c = abortRef.current as (AbortController & { byUser?: boolean }) | null;
        if (c) c.byUser = true;
        abortRef.current?.abort();
        setConvId(null); setMsgs([]); setView('welcome');
        setLoadErr(null); setInput(''); setSideOpen(false);
    };

    const submitRename = async (id: number) => {
        const title = renameVal.trim();
        if (title === '') { setRenamingId(null); return; }
        try {
            const res = await fetch(`${props.cp.conversations}/${id}`, {
                method: 'PATCH', credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json', 'Content-Type': 'application/json',
                },
                body: JSON.stringify({ title }),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const d = await res.json();
            setHistory((h) => h.map((x) => x.id === id
                ? { ...x, title: d.conversation?.title ?? title }
                : x));
        } catch {
            setLoadErr('Judul percakapan gagal diubah.');
        } finally {
            setRenamingId(null);
        }
    };

    const deleteConv = async (id: number) => {
        try {
            const res = await fetch(`${props.cp.conversations}/${id}`, {
                method: 'DELETE', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            setHistory((h) => h.filter((x) => x.id !== id));
            if (convId === id) newChat();
        } catch {
            setLoadErr('Percakapan gagal dihapus.');
        } finally {
            setConfirmDelId(null);
        }
    };

    const openConv = async (id: number) => {
        if (busy) {
            const c = abortRef.current as (AbortController & { byUser?: boolean }) | null;
            if (c) c.byUser = true;
            abortRef.current?.abort();
        }
        setLoadingConv(true); setLoadErr(null); setSideOpen(false);
        try {
            const res = await fetch(`${props.cp.conversations}/${id}`, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const d = await res.json();
            setConvId(d.conversation.id);
            setMsgs(d.messages ?? []);
            setView('chat');
        } catch {
            setLoadErr('Percakapan gagal dimuat. Coba lagi.');
        } finally {
            setLoadingConv(false);
        }
    };

    const pick = (a: AiActionCard | AiExample) => {
        if ('fill' in a && a.fill !== undefined) {
            setView('chat');
            setInput(a.fill);
            requestAnimationFrame(() => { taRef.current?.focus(); autosize(); });
        } else if ('message' in a && a.message) {
            send(a.message);
        } else if ('text' in a) {
            send(a.text);
        }
    };

    const convLabel = convId !== null ? history.find((c) => c.id === convId)?.title : null;

    return (
        <AdminShell menu={props.menu} notif={props.notif} title="Asisten AI">
            <div className={cn('ad-ai', sideOpen && 'ad-ai--side-open')}>
                {/* ── Panel kiri: obrolan baru + riwayat + tips ── */}
                <aside className="ad-ai__side" aria-label="Panel percakapan">
                    <button type="button" className="ad-ai__new" onClick={newChat}>
                        <Plus style={{ width: 16, height: 16 }} /> Obrolan Baru
                    </button>
                    <div className="ad-ai__hist-title">Riwayat Percakapan</div>
                    <div className="ad-ai__hist">
                        {groups.length === 0 ? (
                            <div className="ad-ai__hist-empty">Belum ada percakapan.</div>
                        ) : groups.map((g) => (
                            <div key={g.label} className="ad-ai__hist-group">
                                <div className="ad-ai__hist-label">{g.label}</div>
                                {g.items.map((c) => (
                                    <div
                                        key={c.id}
                                        className={cn('ad-ai__hist-item', convId === c.id && 'is-active')}
                                    >
                                        {renamingId === c.id ? (
                                            <div className="ad-ai__hist-rename">
                                                <input
                                                    autoFocus
                                                    value={renameVal}
                                                    maxLength={120}
                                                    onChange={(e) => setRenameVal(e.target.value)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') void submitRename(c.id);
                                                        if (e.key === 'Escape') setRenamingId(null);
                                                    }}
                                                    onBlur={() => void submitRename(c.id)}
                                                    aria-label="Ubah judul percakapan"
                                                />
                                                <button type="button" onClick={() => void submitRename(c.id)} aria-label="Simpan judul"><Check style={{ width: 13, height: 13 }} /></button>
                                                <button type="button" onClick={() => setRenamingId(null)} aria-label="Batal"><X style={{ width: 13, height: 13 }} /></button>
                                            </div>
                                        ) : confirmDelId === c.id ? (
                                            <div className="ad-ai__hist-rename">
                                                <span className="ad-ai__hist-confirm">Hapus chat ini?</span>
                                                <button type="button" className="ad-ai__hist-danger" onClick={() => void deleteConv(c.id)}>Hapus</button>
                                                <button type="button" onClick={() => setConfirmDelId(null)} aria-label="Batal"><X style={{ width: 13, height: 13 }} /></button>
                                            </div>
                                        ) : (
                                            <>
                                                <button type="button" className="ad-ai__hist-open" onClick={() => openConv(c.id)}>
                                                    <MessageSquare style={{ width: 13, height: 13 }} />
                                                    <span className="ad-ai__hist-name">{c.title}</span>
                                                    <span className="ad-ai__hist-time">{fmtConv(c.last_message_at)}</span>
                                                </button>
                                                <span className="ad-ai__hist-acts">
                                                    <button
                                                        type="button" aria-label="Ubah judul"
                                                        onClick={(e) => { e.stopPropagation(); setRenamingId(c.id); setRenameVal(c.title); setConfirmDelId(null); }}
                                                    ><Pencil style={{ width: 12, height: 12 }} /></button>
                                                    <button
                                                        type="button" aria-label="Hapus percakapan"
                                                        onClick={(e) => { e.stopPropagation(); setConfirmDelId(c.id); setRenamingId(null); }}
                                                    ><Trash2 style={{ width: 12, height: 12 }} /></button>
                                                </span>
                                            </>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                    <div className="ad-ai__tips">
                        <div className="ad-ai__tips-title"><Lightbulb style={{ width: 14, height: 14 }} /> Tips Menggunakan AI</div>
                        <ul>
                            <li>Gunakan bahasa yang jelas dan spesifik.</li>
                            <li>Sebutkan periode waktu (mis. &ldquo;minggu ini&rdquo;).</li>
                            <li>Tanyakan laporan, status perangkat, atau data pengguna.</li>
                        </ul>
                    </div>
                </aside>
                <div className="ad-ai__scrim" onClick={() => setSideOpen(false)} aria-hidden="true" />

                {/* ── Area utama ── */}
                <section className="ad-ai__main">
                    <button type="button" className="ad-icon-btn ad-ai__histbtn" onClick={() => setSideOpen(true)} aria-label="Buka riwayat">
                        <PanelLeft />
                    </button>
                    <div className="ad-ai__scroll" ref={scrollRef}>
                        {view === 'welcome' ? (
                            <div className="ad-ai-hero">
                                <img src={asset('images/hero-ai-orb.svg')} alt="Asisten AI SmartBrail" className="ad-ai-hero__bot" />
                                <h1 className="ad-ai-hero__title">Halo, {props.greeting_name} 👋</h1>
                                <p className="ad-ai-hero__sub">
                                    Saya Asisten AI SmartBrail, siap membantu Anda mengelola data absensi,
                                    ruangan, pengguna, laporan, dan status perangkat secara cepat.
                                </p>
                                {!props.ai_enabled && (
                                    <div className="ad-ai-hero__warn">
                                        Provider AI belum terkonfigurasi — jawaban mungkin terbatas pada data lokal.
                                    </div>
                                )}

                                <button type="button" className="ad-ai-caps__toggle" onClick={() => setCapsOpen((v) => !v)}
                                    aria-expanded={capsOpen}>
                                    <ShieldCheck style={{ width: 13, height: 13 }} />
                                    Kemampuan &amp; batasan saya {capsOpen ? '▲' : '▼'}
                                </button>
                                {capsOpen && (
                                    <div className="ad-ai-caps">
                                        <div className="ad-ai-caps__col">
                                            <p className="ad-ai-caps__head ad-ai-caps__head--ok">Bisa saya bantu</p>
                                            <ul>
                                                <li>Rekap &amp; grafik absensi; cari mahasiswa, pengguna, status akun.</li>
                                                <li>Status mesin, ruangan, pengajuan, petugas, agenda kalender, log tap.</li>
                                                <li>Laporan periode + tombol unduh Excel/Word.</li>
                                                <li><strong>Aksi nyata via kartu konfirmasi</strong>: setujui/tolak/nonaktifkan akun, daftarkan ruangan, pengumuman, toggle mesin, koreksi absensi.</li>
                                                <li>Ketik <code>/help</code> untuk melihat ini lagi.</li>
                                            </ul>
                                        </div>
                                        <div className="ad-ai-caps__col">
                                            <p className="ad-ai-caps__head ad-ai-caps__head--no">Batasan</p>
                                            <ul>
                                                <li>Tidak menghapus data; setiap aksi menunggu konfirmasi Anda.</li>
                                                <li>Impor file (Excel/CSV) tetap lewat halaman Impor Akun — saya bisa arahkan.</li>
                                                <li>Nama/NIM/email disamarkan ke penyedia AI.</li>
                                                <li>Akurasi bergantung data sumber; selalu cek hasil penting.</li>
                                            </ul>
                                        </div>
                                    </div>
                                )}
                                {props.actions.length > 0 && (
                                    <div className="ad-ai-cards">
                                        {props.actions.map((a) => {
                                            const Icon = ICONS[a.icon as keyof typeof ICONS] ?? Sparkles;
                                            return (
                                                <button key={a.label} type="button" className="ad-ai-card" onClick={() => pick(a)}>
                                                    <div className="ad-ai-card__ic"><Icon /></div>
                                                    <div className="ad-ai-card__label">{a.label}</div>
                                                    <div className="ad-ai-card__desc">{a.desc}</div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                                {props.examples.length > 0 && (
                                    <div className="ad-ai-examples">
                                        <div className="ad-ai-examples__title"><Zap style={{ width: 14, height: 14 }} /> Contoh pertanyaan populer</div>
                                        <div className="ad-ai-examples__grid">
                                            {props.examples.map((e) => {
                                                const Icon = ICONS[e.icon as keyof typeof ICONS] ?? Sparkles;
                                                return (
                                                    <button key={e.text} type="button" className="ad-ai-chip" onClick={() => pick(e)}>
                                                        <Icon style={{ width: 14, height: 14 }} /> {e.text}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="ad-ai-chat">
                                {convLabel && <div className="ad-ai-chat__title">{convLabel}</div>}
                                {loadingConv ? (
                                    <div className="ad-ai-chat__load">
                                        {[0, 1, 2].map((i) => <div key={i} className="ad-skel" style={{ height: 52, borderRadius: 14, width: `${68 - i * 12}%`, marginBottom: 10 }} />)}
                                    </div>
                                ) : loadErr ? (
                                    <div className="ad-ai-chat__err">
                                        {loadErr}
                                        <button type="button" className="ad-ai-retry" onClick={() => convId && openConv(convId)}>Coba lagi</button>
                                    </div>
                                ) : msgs.length === 0 ? (
                                    <div className="ad-ai-chat__empty">
                                        <History style={{ width: 20, height: 20 }} />
                                        Percakapan kosong — kirim pertanyaan pertama Anda di bawah.
                                    </div>
                                ) : msgs.map((m, i) => (
                                    <div key={i} className={cn('ad-ai-msg', `ad-ai-msg--${m.role}`)}>
                                        <div className="ad-ai-msg__bubble">
                                            {(m.attachments ?? []).length > 0 && props.cp.attachment && (
                                                <div className="ad-ai-atts">
                                                    {(m.attachments ?? []).map((a) => a.image || /\.(png|jpe?g|webp)$/i.test(a.id) ? (
                                                        <button key={a.id} type="button" className="ad-ai-att__img" title="Klik untuk perbesar"
                                                            onClick={() => setZoomAtt({ id: a.id, name: a.name })}>
                                                            <img src={`${props.cp.attachment}/${a.id}`} alt={a.name} loading="lazy" />
                                                        </button>
                                                    ) : (
                                                        <a key={a.id} href={`${props.cp.attachment}/${a.id}`} target="_blank" rel="noreferrer" className="ad-ai-att__file">
                                                            <Paperclip style={{ width: 11, height: 11 }} /> {a.name}
                                                        </a>
                                                    ))}
                                                </div>
                                            )}
                                            {m.role === 'assistant' && m.status === 'streaming' && m.content === '' ? (
                                                <div className="ad-ai-typing"><span /><span /><span /></div>
                                            ) : (
                                                <AiText text={m.content} />
                                            )}
                                            {(m.blocks ?? []).map((b, bi) => <BlockView key={bi} block={b} cp={props.cp} onPick={(msg) => send(msg)} />)}
                                            {m.status === 'error' && (
                                                <div className="ad-ai-msg__err">
                                                    {m.error ?? 'Gagal memuat jawaban.'}
                                                    <button type="button" className="ad-ai-retry" onClick={retry}>Coba lagi</button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* ── Input ── */}
                    <div className="ad-ai-inputwrap">
                        {(busy || status) && <div className="ad-ai-status"><span className="ad-ai-status__dot" /> {statusLabel}</div>}
                        {input.startsWith('/') && (
                            <div className="ad-ai-slash" role="listbox" aria-label="Perintah cepat">
                                {(() => {
                                    const token = input.trim().toLowerCase().split(/\s+/)[0];
                                    const commands = props.cp?.role === 'dosen' ? DOSEN_COMMANDS : SLASH_COMMANDS;
                                    const matched = commands.filter((c) => c.cmd === token || c.cmd.startsWith(token));
                                    return matched.length === 0
                                        ? <div className="ad-ai-slash__empty">Perintah tidak dikenal — coba /help</div>
                                        : matched.map((c) => (
                                            <button key={c.cmd} type="button" className="ad-ai-slash__item"
                                                onClick={() => {
                                                    if (c.cmd === '/baru') { setInput(''); newChat(); return; }
                                                    if ('fill' in c && c.fill) {
                                                        setInput(c.cmd + ' ');
                                                        requestAnimationFrame(() => { taRef.current?.focus(); autosize(); });
                                                        return;
                                                    }
                                                    setInput(''); send(c.cmd);
                                                }}>
                                                <span className="ad-ai-slash__cmd">{c.cmd}</span>
                                                <span className="ad-ai-slash__desc">{c.desc}</span>
                                            </button>
                                        ));
                                })()}
                            </div>
                        )}
                        {pendingFiles.length > 0 && (
                            <div className="ad-ai-files">
                                {pendingFiles.map((f) => (
                                    <span key={f.id} className={f.uploading ? 'ad-ai-file is-up' : 'ad-ai-file'}>
                                        <Paperclip style={{ width: 11, height: 11 }} />
                                        {f.uploading ? 'Mengunggah… ' : ''}{f.name}
                                        <button type="button" aria-label="Hapus lampiran"
                                            onClick={() => setPendingFiles((ps) => ps.filter((p) => p.id !== f.id))}>
                                            <X style={{ width: 11, height: 11 }} />
                                        </button>
                                    </span>
                                ))}
                            </div>
                        )}
                        <div className="ad-ai-input">
                            <span className="ad-ai-input__icon"><Sparkles style={{ width: 15, height: 15 }} /></span>
                            <textarea
                                ref={taRef}
                                value={input}
                                onChange={(e) => { setInput(e.target.value); autosize(); }}
                                onPaste={(e) => {
                                    if (attOn && e.clipboardData.files.length > 0) {
                                        e.preventDefault();
                                        void uploadFiles(e.clipboardData.files);
                                    }
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
                                }}
                                placeholder="Tanyakan absensi, paste/lampirkan gambar, atau ketik / …"
                                rows={1}
                                aria-label="Pesan untuk Asisten AI"
                            />
                            <input
                                ref={fileRef} type="file" multiple hidden
                                accept=".jpg,.jpeg,.png,.webp,.txt,.md,.csv,.log"
                                onChange={(e) => { void uploadFiles(e.target.files); }}
                            />
                            {attOn && (
                            <button
                                type="button" className="ad-ai-attach"
                                title="Lampirkan gambar/file teks (JPG, PNG, WebP, TXT, CSV — maks 4 MB)"
                                disabled={busy || pendingFiles.length >= 3}
                                onClick={() => setAttachInfo((v) => !v)}
                                aria-label="Lampirkan file"
                            ><Paperclip style={{ width: 15, height: 15 }} /></button>
                            )}
                            {attachInfo && (
                                <div className="ad-ai-attachpop" role="dialog" aria-label="Info lampiran">
                                    <div className="ad-ai-attachpop__title">Lampiran file</div>
                                    <p className="ad-ai-attachpop__text">
                                        Lampirkan <strong>gambar (JPG/PNG/WebP)</strong> atau <strong>file teks (TXT/CSV/LOG)</strong>, maks 4 MB per file, 3 file per pesan.
                                        Screenshot bisa langsung <strong>di-paste (Ctrl+V)</strong> ke kolom pesan. Impor akun Excel tetap lewat halaman impor.
                                    </p>
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <button type="button" className="ad-ai-attachpop__link" style={{ border: 'none', background: 'none', cursor: 'pointer', padding: 0 }}
                                            onClick={() => { setAttachInfo(false); fileRef.current?.click(); }}>
                                            Pilih file…
                                        </button>
                                        {props.cp.import_page && (
                                            <a className="ad-ai-attachpop__link" href={props.cp.import_page}>Impor Akun</a>
                                        )}
                                    </div>
                                    <button type="button" className="ad-ai-attachpop__close" onClick={() => setAttachInfo(false)} aria-label="Tutup"><X style={{ width: 13, height: 13 }} /></button>
                                </div>
                            )}
                            {busy ? (
                                <button
                                    type="button" className="ad-ai-send ad-ai-send--stop"
                                    onClick={stop} aria-label="Hentikan jawaban"
                                ><Square /></button>
                            ) : (
                                <button
                                    type="button" className="ad-ai-send"
                                    onClick={() => send()} disabled={input.trim() === ''}
                                    aria-label="Kirim pesan"
                                ><ArrowUp /></button>
                            )}
                        </div>
                        <div className="ad-ai-note">
                            {props.ai_enabled
                                ? 'Pertanyaan & lampiran diproses Gemini (Google); nama/NIM/email disamarkan. Aksi apa pun tetap menunggu konfirmasi Anda.'
                                : 'Provider AI belum aktif — jawaban dibatasi pada data lokal sistem; lampiran gambar belum bisa dianalisis.'}
                        </div>
                    </div>
                </section>
            </div>

            {zoomAtt && props.cp.attachment && (
                <div className="ad-ai-zoom" role="dialog" aria-label={zoomAtt.name}
                    onClick={() => setZoomAtt(null)}>
                    <button type="button" className="ad-ai-zoom__close" aria-label="Tutup"
                        onClick={() => setZoomAtt(null)}><X style={{ width: 20, height: 20 }} /></button>
                    <img src={`${props.cp.attachment}/${zoomAtt.id}`} alt={zoomAtt.name}
                        onClick={(e) => e.stopPropagation()} />
                    <span className="ad-ai-zoom__cap">{zoomAtt.name}</span>
                </div>
            )}
        </AdminShell>
    );
}
