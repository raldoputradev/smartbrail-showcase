@auth
    @php
        $guardEnabled = (bool) config('session_guard.enabled', true);
        $absolute = \App\Support\WebSessionGuard::absoluteExpiresAt(request());
    @endphp
    @if($guardEnabled)
        <script type="application/json" id="session-guard-config">
            {!! json_encode([
                'enabled' => true,
                'idleMinutes' => (int) config('session_guard.idle_minutes'),
                'warnMinutes' => (int) config('session_guard.warn_before_minutes'),
                'pingUrl' => route('session.ping'),
                'logoutUrl' => route('logout'),
                'csrf' => csrf_token(),
                'absoluteExpiresAt' => $absolute?->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES) !!}
        </script>
        <script src="{{ asset('js/session-idle-guard.js') }}?v=2" defer></script>
    @endif
@endauth
