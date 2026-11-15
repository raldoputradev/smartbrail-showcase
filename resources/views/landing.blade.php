<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ rtrim(url('/'), '/') }}">
    <title>SmartBrail — Sistem Manajemen Laboratorium Pintar</title>
    <meta name="description" content="SmartBrail — sistem manajemen laboratorium pintar untuk absensi sidik jari, akses ruangan IoT, dan manajemen PBL di Politeknik Negeri Batam.">
    <meta property="og:title" content="SmartBrail — Politeknik Negeri Batam">
    <meta property="og:description" content="Transformasi manajemen laboratorium berbasis IoT: absensi sidik jari, akses ruangan IoT, dan aplikasi mobile mahasiswa.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>try{localStorage.removeItem('simalas-public-theme');document.documentElement.classList.remove('dark');}catch(e){}</script>
    @vite(['resources/js/public-site/main.jsx'])
</head>
<body class="antialiased">
    <div id="public-root" data-page="landing" data-props='@json($pageProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'></div>
</body>
</html>
