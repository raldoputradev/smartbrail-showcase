@php
    $snapshotSurface = $snapshotSurface ?? '';
    $snapshotUrl = $snapshotUrl ?? '';
    $snapshotInterval = (int) ($snapshotInterval ?? 20000);
@endphp
@if($snapshotSurface !== '' && $snapshotUrl !== '')
    <div class="snap-bar">
        <span class="snap-status" data-snap-status="{{ $snapshotSurface }}" data-kind="wait">Menyiapkan pembaruan</span>
        <button type="button" class="snap-refresh" data-snap-refresh="{{ $snapshotSurface }}">Muat data terbaru</button>
    </div>
    <style>
        .snap-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0 0 12px; }
        .snap-status { font-size:12px; color:var(--text-muted, #64748b); }
        .snap-status[data-kind="bad"] { color:#be123c; }
        .snap-status[data-kind="ok"] { color:#0f766e; }
        .snap-refresh {
            border:1px solid rgba(15,118,110,.35);
            background:transparent;
            color:inherit;
            border-radius:999px;
            padding:4px 10px;
            font-size:12px;
            cursor:pointer;
        }
    </style>
    <script src="{{ asset('js/simalas-snapshot.js') }}?v=4"></script>
    <script src="{{ asset('js/simalas-live.js') }}?v=1"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.SimalasSnapshot) return;
            const handle = window.SimalasSnapshot.start({
                surface: @json($snapshotSurface),
                url: @json($snapshotUrl),
                intervalMs: {{ $snapshotInterval }},
            });
            if (!window.SimalasLive || !@json($snapshotChannel ?? '')) return;
            window.SimalasLive.start({
                handle: handle,
                channel: @json($snapshotChannel ?? ''),
                authUrl: @json(url('/broadcasting/auth')),
                key: @json((string) config('broadcasting.connections.reverb.key')),
                host: @json((string) (config('broadcasting.connections.reverb.options.host') ?: '127.0.0.1')),
                port: {{ (int) (config('broadcasting.connections.reverb.options.port') ?: 8080) }},
                scheme: @json((string) (config('broadcasting.connections.reverb.options.scheme') ?: 'http')),
            });
        });
    </script>
@endif
