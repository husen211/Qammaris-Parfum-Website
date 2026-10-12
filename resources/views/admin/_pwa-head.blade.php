{{-- Qammaris Admin PWA (ORD-02b). Admin layout and admin login only; public pages never load this. --}}
@if (config('admin.pwa_enabled'))
    <link rel="manifest" href="{{ route('admin.pwa.manifest', [], false) }}">
    <meta name="theme-color" content="#1A1A1A">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="QAM Admin">
    <link rel="apple-touch-icon" href="/images/pwa/admin-apple-touch-icon.png">
@endif
@vite('resources/js/admin-pwa.js')
