<!DOCTYPE html>
<html lang="id" data-theme="qammaris">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Tugas pesanan - Qammaris')</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logofav.jpg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-brand-black antialiased">
    <header class="border-b border-gray-200 px-4 py-3">
        <p class="mx-auto max-w-xl text-sm font-semibold uppercase tracking-widest">Qammaris · Tugas staf</p>
    </header>
    <main>@yield('content')</main>
    @stack('scripts')
</body>
</html>
