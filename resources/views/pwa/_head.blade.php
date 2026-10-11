{{-- Qammaris Perfumes public PWA (PWA-01). Public layout only; admin pages and admin login keep their own Admin PWA head. --}}
@if (config('store.pwa_enabled'))
    <link rel="manifest" href="{{ route('pwa.manifest', [], false) }}">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Qammaris">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/pwa/public-apple-touch-icon.png">
@else
    <link rel="apple-touch-icon" href="{{ asset('images/logofav.jpg') }}">
@endif
@vite('resources/js/public-pwa.js')
