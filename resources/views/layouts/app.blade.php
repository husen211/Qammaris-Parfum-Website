<!DOCTYPE html>
<html lang="id" data-theme="qammaris" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <meta name="description" content="@yield('meta_description', 'Qammaris Perfumes - Distributor parfum original Timur Tengah terpercaya')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Qammaris Perfumes')</title>

    <link rel="icon" type="image/jpeg" href="{{ asset('images/logofav.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logofav.jpg') }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="Qammaris Perfumes">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'Qammaris Perfumes')">
    <meta property="og:description" content="@yield('meta_description', 'Qammaris Perfumes - Distributor parfum original Timur Tengah terpercaya')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/logo.png'))">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Qammaris Perfumes')">
    <meta name="twitter:description" content="@yield('meta_description', 'Qammaris Perfumes - Distributor parfum original Timur Tengah terpercaya')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/logo.png'))">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
    @stack('styles')
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Qammaris Perfumes",
        "url": "{{ config('app.url') }}",
        "logo": "{{ asset('images/logo.png') }}",
        "image": "{{ asset('images/logo.png') }}"
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Qammaris Perfumes",
        "url": "{{ config('app.url') }}"
    }
    </script>
    @stack('jsonld')


    
</head>
<body class="font-sans antialiased bg-white text-brand-black flex flex-col min-h-screen">
    
    @include('components.navbar')
    
    <main class="flex-grow">
        @yield('content')
    </main>
    
    @include('components.footer')
    
    @include('components.cart-drawer')
    
    @stack('scripts')
    @include('components.toast')
</body>
</html>
