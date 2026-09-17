<!DOCTYPE html>
<html lang="id" data-theme="qammaris" class="scroll-smooth">
<head>
    @php
        $decodeSection = static fn (string $value): string => html_entity_decode(
            trim($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $pageTitle = $decodeSection($__env->yieldContent('title', 'Qammaris Perfumes'));
        $pageDescription = $decodeSection($__env->yieldContent('meta_description', 'Qammaris Perfumes - Distributor parfum original Timur Tengah terpercaya'));
        $robotsDirective = $decodeSection($__env->yieldContent('robots', 'index,follow'));
        $openGraphType = $decodeSection($__env->yieldContent('og_type', 'website'));
        $openGraphImage = $decodeSection($__env->yieldContent('og_image', asset('images/logo.png')));
        $jsonLdFlags = JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR;
        $organizationSchema = [
            chr(64).'context' => 'https://schema.org',
            chr(64).'type' => 'Organization',
            'name' => 'Qammaris Perfumes',
            'url' => config('app.url'),
            'logo' => asset('images/logo.png'),
            'image' => asset('images/logo.png'),
        ];
        $websiteSchema = [
            chr(64).'context' => 'https://schema.org',
            chr(64).'type' => 'WebSite',
            'name' => 'Qammaris Perfumes',
            'url' => config('app.url'),
        ];
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="{{ $robotsDirective }}">
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>

    <link rel="icon" type="image/jpeg" href="{{ asset('images/logofav.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logofav.jpg') }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="Qammaris Perfumes">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $openGraphType }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $openGraphImage }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $openGraphImage }}">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
    @stack('styles')
    <script type="application/ld+json">
    {!! json_encode($organizationSchema, $jsonLdFlags) !!}
    </script>
    <script type="application/ld+json">
    {!! json_encode($websiteSchema, $jsonLdFlags) !!}
    </script>
    @stack('jsonld')


    
</head>
<body class="font-sans antialiased bg-white text-brand-black flex flex-col min-h-screen">
    <a href="#main-content"
        class="sr-only fixed left-4 top-4 z-[120] bg-brand-black px-4 py-3 text-sm font-semibold text-white focus:not-sr-only focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold">
        Lewati ke konten utama
    </a>

    @include('components.navbar')
    
    <main id="main-content" tabindex="-1" class="flex-grow focus:outline-none">
        @yield('content')
    </main>
    
    @include('components.footer')
    
    @include('components.cart-drawer')
    
    @stack('scripts')
    @include('components.toast')
</body>
</html>
