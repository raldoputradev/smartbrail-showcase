<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ rtrim(url('/'), '/') }}">
    <title>SmartBrail — Sistem Manajemen Laboratorium Pintar | BRAIL Polibatam</title>
    <meta name="description" content="SmartBrail adalah sistem manajemen laboratorium pintar Politeknik Negeri Batam: absensi sidik jari, akses ruangan via RFID & fingerprint, persetujuan ruangan, manajemen PBL, dan notifikasi realtime.">
    <meta name="robots" content="index, follow">
    <meta name="keywords" content="SmartBrail, smartbrail, smart laboratory, sistem laboratorium, absensi sidik jari, fingerprint attendance, akses ruangan IoT, Politeknik Negeri Batam, Polibatam, BRAIL, PBL, manajemen ruangan">
    <meta name="author" content="Rayendra Aldo Putra — SmartBrail, BRAIL Politeknik Negeri Batam">
    <meta name="theme-color" content="#0284c7">
    <link rel="canonical" href="{{ rtrim(url('/'), '/') }}/">

    {{-- Verifikasi Google Search Console: isi GOOGLE_SITE_VERIFICATION di .env --}}
    @if(config('services.google.site_verification'))
        <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
    @endif

    {{-- Open Graph — preview saat link dibagikan --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SmartBrail">
    <meta property="og:locale" content="id_ID">
    <meta property="og:url" content="{{ rtrim(url('/'), '/') }}/">
    <meta property="og:title" content="SmartBrail — Sistem Manajemen Laboratorium Pintar | Polibatam">
    <meta property="og:description" content="Absensi sidik jari, akses ruangan IoT, persetujuan ruangan, manajemen PBL, dan notifikasi realtime dalam satu platform laboratorium cerdas.">
    <meta property="og:image" content="{{ rtrim(url('/'), '/') }}/branding/smartbrail-logo.png">

    {{-- Twitter / X card --}}
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="SmartBrail — Sistem Manajemen Laboratorium Pintar">
    <meta name="twitter:description" content="Absensi sidik jari, akses ruangan IoT, persetujuan ruangan, dan manajemen PBL untuk Politeknik Negeri Batam.">
    <meta name="twitter:image" content="{{ rtrim(url('/'), '/') }}/branding/smartbrail-logo.png">

    {{-- Structured data — bantu Google mengenali entitas "SmartBrail" --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "WebSite",
        "name": "SmartBrail",
        "alternateName": "SmartBrail Polibatam",
        "url": "{{ rtrim(url('/'), '/') }}/",
        "description": "Sistem manajemen laboratorium pintar: absensi sidik jari, akses ruangan IoT, persetujuan ruangan, dan manajemen PBL.",
        "disambiguatingDescription": "SmartBrail (satu kata) — portal resmi BRAIL, Barelang Robotics & AI Lab, Politeknik Negeri Batam. Bukan 'smart brain'.",
        "inLanguage": "id-ID",
        "publisher": {
            "@type": "Organization",
            "name": "SmartBrail",
            "url": "{{ rtrim(url('/'), '/') }}/",
            "logo": "{{ rtrim(url('/'), '/') }}/branding/smartbrail-logo.png"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "SmartBrail",
        "applicationCategory": "EducationalApplication",
        "operatingSystem": "Web",
        "url": "{{ rtrim(url('/'), '/') }}/",
        "offers": { "@type": "Offer", "price": "0", "priceCurrency": "IDR" },
        "author": {
            "@type": "Person",
            "name": "Rayendra Aldo Putra",
            "url": "https://github.com/raldoputradev",
            "affiliation": { "@type": "Organization", "name": "Politeknik Negeri Batam" }
        },
        "maintainer": {
            "@type": "Organization",
            "name": "BRAIL — Barelang Robotics & AI Lab",
            "url": "https://www.instagram.com/brailpolibatam/"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Organization",
        "name": "BRAIL — Barelang Robotics & AI Lab",
        "alternateName": "BRAIL Polibatam",
        "url": "{{ rtrim(url('/'), '/') }}/",
        "sameAs": [
            "https://www.instagram.com/brailpolibatam/",
            "https://www.polibatam.ac.id/"
        ],
        "parentOrganization": {
            "@type": "CollegeOrUniversity",
            "name": "Politeknik Negeri Batam",
            "url": "https://www.polibatam.ac.id/"
        }
    }
    </script>
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
