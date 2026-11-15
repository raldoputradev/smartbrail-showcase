/**
 * Klien WebSocket ringan untuk Reverb (protokol Pusher) — tanpa dependensi.
 * Subscribe channel privat `private-<nama>`; event dipetakan ke handler.
 * Reconnect backoff eksponensial; cleanup menghentikan semuanya.
 */

import { router } from '@inertiajs/react';

export interface LiveConfig {
    key: string;
    host: string;
    port: number;
    scheme: string;
    auth_url: string;
}

type Handler = (data: Record<string, unknown>) => void;

/**
 * Dedup delivery_id harus per-koneksi (bukan module-scope): satu halaman bisa
 * membuka beberapa socket ke kanal yang sama (mis. AppShell + halaman) dan
 * broadcast membawa delivery_id identik di tiap koneksi. Bila seen dipakai
 * bersama, koneksi yang menerima lebih dulu akan menelan event untuk handler
 * milik koneksi lain.
 */
function makeSeen() {
    const seen: string[] = [];
    return (id: unknown): boolean => {
        if (!id || typeof id !== 'string' || seen.includes(id)) return false;
        seen.push(id);
        if (seen.length > 400) seen.splice(0, seen.length - 400);
        return true;
    };
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

let pendingOnly = new Set<string>();
let pendingTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * Gabungkan reload realtime yang tiba berbarengan menjadi satu visit Inertia.
 * Satu event broadcast bisa ditangani beberapa socket (shell + halaman) — tanpa
 * penggabungan, beberapa router.reload menembak bersamaan dan visit saling
 * berpotongan (skeleton navigasi bisa menempel, request dobel).
 */
export function scheduleReload(only: string[]): void {
    only.forEach((p) => pendingOnly.add(p));
    if (pendingTimer) return;
    pendingTimer = setTimeout(() => {
        pendingTimer = null;
        const props = [...pendingOnly];
        pendingOnly.clear();
        router.reload({ only: props });
    }, 250);
}

/** Subscribe channel privat; kembalikan fungsi unsubscribe/cleanup. */
export function subscribePrivate(
    cfg: LiveConfig,
    channel: string,
    events: Record<string, Handler>,
): () => void {
    const scheme = cfg.scheme === 'https' ? 'wss' : 'ws';
    const url = `${scheme}://${cfg.host}:${cfg.port}/app/${encodeURIComponent(cfg.key)}?protocol=7&client=simalas&version=1`;
    const remember = makeSeen();
    let socket: WebSocket | null = null;
    let stopped = false;
    let attempt = 0;
    let retryTimer: ReturnType<typeof setTimeout> | null = null;

    function subscribe(sock: WebSocket, socketId: string) {
        const body = new URLSearchParams();
        body.set('socket_id', socketId);
        body.set('channel_name', 'private-' + channel);
        fetch(cfg.auth_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        }).then(async (res) => {
            if (!res.ok) throw new Error('auth');
            const payload = await res.json();
            if (!payload?.auth || sock.readyState !== WebSocket.OPEN) throw new Error('auth');
            sock.send(JSON.stringify({
                event: 'pusher:subscribe',
                data: { auth: payload.auth, channel: 'private-' + channel },
            }));
        }).catch(() => {
            // Auth gagal — tutup agar siklus reconnect tetap berjalan.
            try { sock.close(); } catch { /* abaikan */ }
        });
    }

    function connect() {
        if (stopped) return;
        socket = new WebSocket(url);
        socket.addEventListener('message', (message) => {
            let packet: { event?: string; data?: unknown } | null = null;
            try { packet = JSON.parse(message.data); } catch { return; }
            const name = packet?.event ?? '';
            if (name === 'pusher:ping') {
                socket?.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                return;
            }
            if (name === 'pusher:connection_established') {
                const data = typeof packet?.data === 'string' ? JSON.parse(packet.data) : packet?.data as { socket_id: string };
                subscribe(socket!, data.socket_id);
                return;
            }
            const handler = events[name];
            if (!handler) return;
            const data = typeof packet?.data === 'string' ? JSON.parse(packet.data) : (packet?.data ?? {}) as Record<string, unknown>;
            if (data.delivery_id && !remember(data.delivery_id)) return;
            handler(data);
        });
        socket.addEventListener('close', () => {
            if (stopped) return;
            attempt += 1;
            const delay = Math.min(10000, 1000 * Math.pow(2, Math.min(attempt, 3)));
            retryTimer = setTimeout(connect, delay);
        });
    }

    connect();

    return () => {
        stopped = true;
        if (retryTimer) clearTimeout(retryTimer);
        try { socket?.close(); } catch { /* abaikan */ }
    };
}
