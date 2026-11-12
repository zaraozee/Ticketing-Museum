<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Tema & PWA (FR-PWA-01) --}}
    <meta name="theme-color" content="{{ config('ticketing.pwa.theme_color', '#2563eb') }}">
    <meta name="color-scheme" content="light">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="{{ config('app.name') }}">

    {{-- SEO (FR-PWA-01, NFR-16).
         Tag default dirender server agar crawler tanpa JS tetap mendapat
         metadata. Atribut `data-inertia` membuat Inertia memperlakukan tag ini
         sebagai milik head-manager, sehingga `SeoHead` **menggantikan** nilai
         yang sama (bukan menambah tag duplikat) saat halaman publik dibuka. --}}
    <meta data-inertia="description" name="description" content="Pesan tiket museum online dengan e-ticket QR. Cari museum, pilih slot kunjungan, bayar online, dan masuk tanpa antre.">
    <meta name="author" content="{{ config('app.name') }}">
    <meta data-inertia="robots" name="robots" content="index, follow">
    <link data-inertia="canonical" rel="canonical" href="{{ url()->current() }}">

    <meta data-inertia="og:type" property="og:type" content="website">
    <meta data-inertia="og:title" property="og:title" content="{{ config('app.name') }}">
    <meta data-inertia="og:description" property="og:description" content="Pesan tiket museum online dengan e-ticket QR.">
    <meta data-inertia="og:url" property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta data-inertia="twitter:card" name="twitter:card" content="summary">
    <meta data-inertia="twitter:title" name="twitter:title" content="{{ config('app.name') }}">
    <meta data-inertia="twitter:description" name="twitter:description" content="Pesan tiket museum online dengan e-ticket QR.">

    <title inertia>{{ config('app.name') }}</title>

    {{-- Font: preconnect + preload agar LCP tidak menunggu stylesheet (NFR-02). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        rel="preload"
        as="style"
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    >
    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        media="print"
        onload="this.media='all'"
    >
    <noscript>
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        >
    </noscript>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="h-full font-sans antialiased text-slate-800 bg-slate-50">
    @inertia
</body>
</html>
