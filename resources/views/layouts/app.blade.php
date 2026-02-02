<!DOCTYPE html>
<html lang="id" data-theme="qammaris" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <meta name="description" content="@yield('meta_description', 'Qammaris Perfumes - Distributor parfum original Timur Tengah terpercaya')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Qammaris Perfumes')</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
    @stack('styles')


    
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
