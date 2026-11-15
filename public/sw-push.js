/* SmartBrail Push — service worker notifikasi browser */
self.addEventListener('push', (event) => {
    let data = { title: 'SmartBrail', body: 'Ada notifikasi baru.', url: '/', icon: '/branding/app-logo.png' };
    try {
        if (event.data) data = { ...data, ...event.data.json() };
    } catch (e) { /* payload non-JSON */ }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon,
            badge: data.icon,
            tag: data.tag || 'smartbrail',
            data: { url: data.url },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if ('focus' in c) { c.navigate(url); return c.focus(); }
            }
            return clients.openWindow(url);
        })
    );
});
