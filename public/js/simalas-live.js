/**
 * Soket lokal untuk membangunkan snapshot absensi.
 * Event tidak mengganti tabel. Polling berjalan saat soket tidak tersambung.
 */
(function () {
    const seen = [];

    function remember(id) {
        if (!id || seen.indexOf(id) !== -1) {
            return false;
        }
        seen.push(id);
        if (seen.length > 400) {
            seen.splice(0, seen.length - 400);
        }
        return true;
    }

    function start(options) {
        const handle = options.handle;
        const channel = options.channel;
        const key = options.key;
        if (!handle || !channel || !key || !window.WebSocket) {
            return;
        }
        const scheme = options.scheme === 'https' ? 'wss' : 'ws';
        const url = scheme + '://' + options.host + ':' + options.port + '/app/' + encodeURIComponent(key) + '?protocol=7&client=simalas&version=1';
        let socket = null;
        let stopped = false;
        let attempt = 0;
        let coalesce = null;
        let subscribed = false;

        function csrf() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        function wake() {
            if (coalesce) {
                clearTimeout(coalesce);
            }
            coalesce = setTimeout(function () {
                coalesce = null;
                handle.refresh('socket');
            }, 400);
        }

        function subscribe(sock, socketId) {
            const body = new URLSearchParams();
            body.set('socket_id', socketId);
            body.set('channel_name', 'private-' + channel);
            fetch(options.authUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('auth');
                }
                return res.json();
            }).then(function (payload) {
                if (!payload || !payload.auth || sock.readyState !== WebSocket.OPEN) {
                    throw new Error('auth');
                }
                sock.send(JSON.stringify({
                    event: 'pusher:subscribe',
                    data: {
                        auth: payload.auth,
                        channel: 'private-' + channel,
                    },
                }));
            }).catch(function () {
                handle.setTransport('poll');
            });
        }

        function connect() {
            if (stopped) {
                return;
            }
            subscribed = false;
            handle.setTransport('poll');
            socket = new WebSocket(url);
            socket.addEventListener('open', function () {
                attempt = 0;
            });
            socket.addEventListener('message', function (message) {
                let packet = null;
                try {
                    packet = JSON.parse(message.data);
                } catch (error) {
                    return;
                }
                const name = packet.event || '';
                if (name === 'pusher:ping') {
                    socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                    return;
                }
                if (name === 'pusher:connection_established') {
                    const data = typeof packet.data === 'string' ? JSON.parse(packet.data) : packet.data;
                    subscribe(socket, data.socket_id);
                    return;
                }
                if (name === 'pusher_internal:subscription_succeeded') {
                    subscribed = true;
                    handle.setTransport('socket');
                    handle.refresh('socket');
                    return;
                }
                if (name !== 'snapshot.wake') {
                    return;
                }
                const data = typeof packet.data === 'string' ? JSON.parse(packet.data) : (packet.data || {});
                if (!remember(data.delivery_id)) {
                    return;
                }
                const surfaces = data.surfaces || [];
                if (surfaces.indexOf(handle.surface) === -1) {
                    return;
                }
                wake();
            });
            socket.addEventListener('close', function () {
                subscribed = false;
                handle.setTransport('poll');
                if (stopped) {
                    return;
                }
                attempt += 1;
                const delay = Math.min(10000, 1000 * Math.pow(2, Math.min(attempt, 3)));
                setTimeout(connect, delay);
            });
        }

        window.addEventListener('pagehide', function () {
            stopped = true;
            if (socket) {
                socket.close();
            }
        });
        connect();
    }

    window.SimalasLive = { start: start, remember: remember };
})();
