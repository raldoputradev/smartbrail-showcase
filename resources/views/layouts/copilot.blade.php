<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <script>
    (function () {
        try {
            var saved = localStorage.getItem('dashboard_theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Copilot') · AIoT SIMALAS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @yield('styles')
</head>
<body class="cp-body">
    @if(session('email_verification_pending') && !Auth::user()->email_verified_at)
        <div class="cp-banner" role="status">
            <span>Email belum diverifikasi.</span>
            <a href="{{ $backUrl ?? url('/dashboard') }}">Verifikasi di SIMALAS</a>
        </div>
    @endif

    @yield('content')

    @yield('scripts')
    @include('partials.session-idle-guard')
</body>
</html>
