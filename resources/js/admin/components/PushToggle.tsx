import { useEffect, useState } from 'react';
import { BellRing, Loader2 } from 'lucide-react';

declare global {
    interface Window {
        SbPush?: {
            supported: () => boolean;
            subscription: () => Promise<PushSubscription | null>;
            enable: () => Promise<{ ok: boolean; reason?: string }>;
            disable: () => Promise<{ ok: boolean }>;
        };
    }
}

/** Toggle Web Push — dimuat lewat /js/webpush.js (vanilla, dipakai juga Blade). */
export function PushToggle() {
    const [ready, setReady] = useState(false);
    const [supported, setSupported] = useState(true);
    const [subscribed, setSubscribed] = useState(false);
    const [denied, setDenied] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let alive = true;
        const init = async () => {
            if (!window.SbPush) {
                await new Promise<void>((resolve) => {
                    const s = document.createElement('script');
                    s.src = '/js/webpush.js';
                    s.onload = () => resolve();
                    s.onerror = () => resolve();
                    document.head.appendChild(s);
                });
            }
            if (!alive) return;
            if (!window.SbPush?.supported()) { setSupported(false); setReady(true); return; }
            const sub = await window.SbPush.subscription().catch(() => null);
            if (!alive) return;
            setSubscribed(!!sub);
            setDenied(Notification.permission === 'denied');
            setReady(true);
        };
        void init();
        return () => { alive = false; };
    }, []);

    if (!ready || !supported) return null;

    const toggle = async () => {
        if (!window.SbPush || busy) return;
        setBusy(true);
        try {
            if (subscribed) {
                await window.SbPush.disable();
                setSubscribed(false);
            } else {
                const r = await window.SbPush.enable();
                if (r.ok) {
                    setSubscribed(true);
                } else {
                    setDenied(Notification.permission === 'denied');
                }
            }
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className="ad-panel" style={{
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            gap: 12, flexWrap: 'wrap', padding: '14px 18px',
        }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <span style={{
                    display: 'inline-flex', padding: 8, borderRadius: 10,
                    color: '#0284c7', background: 'rgba(2,132,199,0.12)',
                }}>
                    <BellRing size={18} />
                </span>
                <div>
                    <strong style={{ fontSize: 14 }}>Notifikasi browser</strong>
                    <div style={{ fontSize: 12.5, color: 'var(--ad-muted, #64748b)' }}>
                        {denied
                            ? 'Izin notifikasi ditolak — aktifkan lewat pengaturan situs di browser Anda.'
                            : subscribed
                                ? 'Aktif — perangkat ini menerima push untuk antrian, pengajuan ruangan, dan saran baru.'
                                : 'Terima pemberitahuan langsung di perangkat ini walau SmartBrail tidak dibuka.'}
                    </div>
                </div>
            </div>
            <button type="button"
                className={subscribed ? 'ad-mesin-btn ad-mesin-btn--ghost' : 'ad-mesin-btn ad-mesin-btn--primary'}
                disabled={busy || denied}
                onClick={toggle}>
                {busy && <Loader2 size={14} className="animate-spin" />}
                {subscribed ? 'Nonaktifkan' : denied ? 'Diblokir browser' : 'Aktifkan'}
            </button>
        </section>
    );
}
