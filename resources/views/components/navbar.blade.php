@php
    $navItems = [
        [
            'label' => 'Jelajahi',
            'bgColor' => '#171717',
            'textColor' => '#FFFFFF',
            'links' => [
                ['label' => 'Beranda', 'href' => route('home'), 'ariaLabel' => 'Beranda'],
                ['label' => 'Tes Parfum', 'href' => route('quiz.index'), 'ariaLabel' => 'Tes Parfum'],
            ],
        ],
        [
            'label' => 'Belanja',
            'bgColor' => '#1F1F1F',
            'textColor' => '#FFFFFF',
            'links' => [
                ['label' => 'Katalog', 'href' => route('products.index'), 'ariaLabel' => 'Katalog'],
                ['label' => 'Lokasi', 'href' => route('store.location'), 'ariaLabel' => 'Lokasi'],
            ],
        ],
        [
            'label' => 'Insight',
            'bgColor' => '#282828',
            'textColor' => '#FFFFFF',
            'links' => [
                ['label' => 'Jurnal', 'href' => route('blog.index'), 'ariaLabel' => 'Jurnal'],
                ['label' => 'Tentang', 'href' => route('store.about'), 'ariaLabel' => 'Tentang Qammaris'],
            ],
        ],
    ];
@endphp

<div
    id="card-nav-root"
    data-logo="{{ asset('images/logo-black2.png') }}"
    data-logo-alt="Qammaris Perfumes"
    data-items='@json($navItems)'
    data-base-color="#FDFCF9"
    data-menu-color="#1A1A1A"
    data-button-label="Katalog"
    data-button-href="{{ route('products.index') }}"
    data-button-bg="#1A1A1A"
    data-button-text="#FFFFFF"
    data-active-url="{{ url()->current() }}"
    data-cart-count="{{ cart_count() }}"
    data-home-href="{{ route('home') }}"
></div>

@if (! app()->environment('testing'))
    @vite('resources/js/reactbits/card-nav.jsx')
@endif
