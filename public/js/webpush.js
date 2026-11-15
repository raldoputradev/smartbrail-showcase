/* Web Push helper — dipakai React & Blade (vanilla, tanpa dependensi). */
(function () {
    const SW_URL = '/sw-push.js';

    function supported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    function b64ToUint8(base64) {
        const pad = '='.repeat((4 - (base64.length % 4)) % 4);
        const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, (c) => c.charCodeAt(0));
    }

    async function vapidKey() {
        const res = await fetch('/push/vapid-key', { headers: { Accept: 'application/json' } });
        return (await res.json()).key;
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async function subscription() {
        if (!supported()) return null;
        const reg = await navigator.serviceWorker.getRegistration(SW_URL)
            || await navigator.serviceWorker.register(SW_URL);
        return reg.pushManager.getSubscription();
    }

    async function enable() {
        if (!supported()) return { ok: false, reason: 'unsupported' };
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') return { ok: false, reason: perm };

        const reg = await navigator.serviceWorker.register(SW_URL);
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: b64ToUint8(await vapidKey()),
        });
        const json = sub.toJSON();

        await fetch('/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({
                endpoint: sub.endpoint,
                keys: json.keys,
                contentEncoding: (PushManager.supportedContentEncodings || [])[0] || 'aesgcm',
            }),
        });
        return { ok: true };
    }

    async function disable() {
        const sub = await subscription();
        if (sub) {
            await fetch('/push/unsubscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });
            await sub.unsubscribe();
        }
        return { ok: true };
    }

    window.SbPush = { supported, subscription, enable, disable };
})();
