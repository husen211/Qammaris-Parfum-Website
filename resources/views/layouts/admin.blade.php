<!DOCTYPE html>
<html lang="id" @include('admin._pwa-attributes')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="@yield('robots', 'noindex,nofollow')">
    <title>Qammaris Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('admin._pwa-head')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    @stack('styles')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .active-nav { background-color: #F3F4F6; color: #111827; border-right: 3px solid #111827; }
    </style>
</head>
<body data-navigation-shell="admin" class="bg-gray-50 text-gray-800 antialiased">

    <div class="flex h-dvh overflow-hidden">
        
        <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col">
            <div class="h-16 flex items-center px-8 border-b border-gray-100">
                <span class="font-bold text-xl tracking-widest uppercase">Qammaris</span>
                <span class="text-[10px] bg-black text-white px-2 py-0.5 ml-2 rounded">ADMIN</span>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1">
                @can('dashboard.view')
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Dashboard
                </a>
                @endcan
                
                @can('catalog.manage')
                <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products*') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Products
                </a>

                <a href="{{ route('admin.app-products.index') }}" class="{{ request()->routeIs('admin.app-products*') ? 'active-nav' : '' }} flex min-h-11 items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">Produk dari aplikasi</a>

                <a href="{{ route('admin.brands.index') }}" class="{{ request()->routeIs('admin.brands*') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M3 11l8.586-8.586A2 2 0 0113 2h6a2 2 0 012 2v6a2 2 0 01-.586 1.414L11.828 20a2 2 0 01-2.828 0l-6-6a2 2 0 010-2.828z"/></svg>
                    Brand
                </a>

                <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories*') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    Kategori
                </a>

                <a href="{{ route('admin.shopee-imports.index') }}" class="{{ request()->routeIs('admin.product-imports*', 'admin.shopee-imports*') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9A5 5 0 0115.9 6H16a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3"/></svg>
                    Import Produk
                </a>
                @endcan

                @can('blog.manage')
                <a href="{{ route('admin.blog-posts.index') }}" class="{{ request()->routeIs('admin.blog-posts*') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 5H5a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2zM7 9h10M7 13h10M7 17h6"/></svg>
                    Blog Posts
                </a>
                @endcan

                @can('orders.manage')
                <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders*') ? 'active-nav' : '' }} flex items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Pesanan Online
                </a>
                @endcan
                @can('integrations.manage')
                <a href="{{ route('admin.integrations.orders') }}" class="{{ request()->routeIs('admin.integrations*') ? 'active-nav' : '' }} flex min-h-11 items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">Integrasi App</a>
                @endcan
                @can('users.manage')
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users*') ? 'active-nav' : '' }} flex min-h-11 items-center px-4 py-3 text-sm font-medium text-gray-600 rounded hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Pengguna &amp; Role
                </a>
                @endcan
            </nav>

            <div class="p-4 border-t border-gray-100">
                <div class="px-4 pb-3 text-sm">
                    <p class="truncate font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500">{{ auth()->user()->roleLabel() }}</p>
                    <a href="{{ route('admin.account.password') }}" class="mt-1 inline-flex min-h-9 items-center text-xs text-gray-600 underline underline-offset-2 hover:text-black">Ganti password</a>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
            <header class="relative md:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <span class="block font-bold tracking-widest">QAMMARIS</span>
                    {{-- Shared store phones: always show whose account is signed in. --}}
                    <span class="block truncate text-xs text-gray-500">{{ auth()->user()->name }} · {{ auth()->user()->roleLabel() }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                @canany(['dashboard.view', 'catalog.manage', 'blog.manage', 'users.manage'])
                <details class="group">
                    <summary class="cursor-pointer list-none rounded border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700">Menu</summary>
                    <nav class="absolute right-4 top-14 z-50 w-56 rounded-lg border border-gray-200 bg-white p-2 shadow-xl">
                        @can('dashboard.view')
                        <a href="{{ route('admin.dashboard') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.dashboard') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Dashboard</a>
                        @endcan
                        @can('catalog.manage')
                        <a href="{{ route('admin.products.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.products*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Products</a>
                        <a href="{{ route('admin.app-products.index') }}" class="flex min-h-11 items-center rounded px-3 py-2 text-sm {{ request()->routeIs('admin.app-products*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Produk dari aplikasi</a>
                        <a href="{{ route('admin.brands.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.brands*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Brand</a>
                        <a href="{{ route('admin.categories.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.categories*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Kategori</a>
                        <a href="{{ route('admin.shopee-imports.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.product-imports*', 'admin.shopee-imports*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Import Produk</a>
                        @endcan
                        @can('blog.manage')
                        <a href="{{ route('admin.blog-posts.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.blog-posts*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Blog Posts</a>
                        @endcan
                        @can('orders.manage')
                        <a href="{{ route('admin.orders.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.orders*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Pesanan Online</a>
                        @endcan
                        @can('users.manage')<a href="{{ route('admin.users.index') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.users*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Pengguna &amp; Role</a>@endcan
                        <a href="{{ route('admin.account') }}" class="block rounded px-3 py-2 text-sm {{ request()->routeIs('admin.account*') ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">Akun</a>
                    </nav>
                </details>
                @endcanany
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="min-h-11 rounded border border-gray-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Keluar</button></form>
                </div>
            </header>

            <div data-admin-offline hidden tabindex="-1" role="status" class="sticky top-0 z-30 border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 focus:outline-none">
                Tidak ada koneksi internet. Perubahan tidak bisa dikirim sampai HP online lagi.
            </div>

            <div data-navigation-content class="container mx-auto px-4 py-6 sm:px-6 sm:py-8 @can('orders.manage') pb-28 md:pb-8 @endcan">
                @if(session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
                @endif

                @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
                @endif

                @yield('content')
            </div>
        </main>

    </div>

    @can('orders.manage')
    @php
        $bottomNav = [
            ['route' => 'admin.orders.index', 'active' => request()->routeIs('admin.orders.index', 'admin.orders.show'), 'label' => 'Pesanan',
                'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h4'],
            ['route' => 'admin.orders.create', 'active' => request()->routeIs('admin.orders.create'), 'label' => 'Buat',
                'icon' => 'M12 5v14M5 12h14'],
            ['route' => 'admin.account', 'active' => request()->routeIs('admin.account*'), 'label' => 'Akun',
                'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ];
    @endphp
    <nav aria-label="Navigasi pesanan" data-admin-bottom-nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
        <ul class="grid grid-cols-3">
            @foreach ($bottomNav as $item)
                <li>
                    <a href="{{ route($item['route']) }}" @if ($item['active']) aria-current="page" @endif
                        class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-semibold focus-visible:bg-gray-100 focus-visible:outline-none {{ $item['active'] ? 'text-black' : 'text-gray-500' }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $item['active'] ? '2.25' : '1.75' }}" d="{{ $item['icon'] }}"/></svg>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
    @endcan
    @stack('scripts')
    @include('components.navigation-skeleton')
</body>
</html>
