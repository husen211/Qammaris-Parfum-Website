@extends('layouts.app')

@section('title', 'Katalog Parfum Original - Qammaris Perfumes')

@php
    $selectedBrands = $catalogState->brandIds;
    $activeFilterCount = $catalogState->activeFilterCount();
    $detailContext = $catalogState->query();
    $sortLabels = [
        'latest' => 'Terbaru',
        'price_low' => 'Harga terendah',
        'price_high' => 'Harga tertinggi',
        'popular' => 'Populer',
    ];
@endphp

@section('content')
    <header class="catalog-header">
        <div class="catalog-container">
            <p class="catalog-header__eyebrow">Parfum pilihan Qammaris</p>
            <h1 class="catalog-header__title font-mayluxa">Koleksi Parfum</h1>
        </div>
    </header>

    <div class="catalog-toolbar lg:hidden sticky z-30 bg-white border-y border-gray-200">
        <div class="grid grid-cols-2 divide-x divide-gray-100">
            <button type="button" data-catalog-filter-trigger aria-controls="mobileFilter" aria-haspopup="dialog" aria-expanded="false"
                class="min-h-11 px-3 flex items-center justify-center gap-2 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-black transition-colors">
                <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-[11px] font-bold uppercase tracking-widest text-brand-black">
                    Filter{{ $activeFilterCount > 0 ? ' ('.$activeFilterCount.')' : '' }}
                </span>
            </button>

            <form method="GET" action="{{ route('products.index') }}" data-catalog-form autocomplete="off" class="relative min-h-11">
                @include('products._catalog-state-inputs', ['catalogState' => $catalogState, 'exclude' => ['sort']])
                <label for="mobile-catalog-sort" class="sr-only">Urutkan katalog</label>
                <select id="mobile-catalog-sort" name="sort" data-catalog-sort
                    class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                    @foreach ($sortLabels as $value => $label)
                        <option value="{{ $value }}" {{ $catalogState->sort === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="min-h-11 px-3 flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors pointer-events-none">
                    <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    <span class="text-[11px] font-bold uppercase tracking-widest text-brand-black">{{ $sortLabels[$catalogState->sort] }}</span>
                </div>
                <button type="submit" class="sr-only">Terapkan urutan</button>
            </form>
        </div>
    </div>

    <section data-catalog-discovery
        data-catalog-url="{{ route('products.index', $catalogState->query()) }}"
        data-search="{{ $catalogState->search }}"
        data-brand-ids="{{ implode(',', $catalogState->brandIds) }}"
        data-category="{{ $catalogState->categoryId }}"
        data-gender="{{ $catalogState->gender }}"
        data-price-min="{{ $catalogState->priceMin }}"
        data-price-max="{{ $catalogState->priceMax }}"
        data-availability="{{ $catalogState->availability }}"
        data-sort="{{ $catalogState->sort }}"
        class="catalog-surface pb-20 pt-6 min-h-screen" aria-label="Hasil katalog">
        <div class="catalog-container">
            <div class="lg:flex lg:items-center lg:gap-8 lg:mb-7">
                <form method="GET" action="{{ route('products.index') }}" data-catalog-form autocomplete="off" class="catalog-search relative mb-5 lg:mb-0 lg:flex-1 lg:max-w-xl">
                    @include('products._catalog-state-inputs', ['catalogState' => $catalogState, 'exclude' => ['search']])
                    <label for="catalog-search" class="sr-only">Cari produk atau brand</label>
                    <input id="catalog-search" type="search" name="search" value="{{ $catalogState->search }}"
                        data-catalog-quick-search maxlength="100" placeholder="Cari produk atau brand"
                        class="catalog-filter-control pr-12">
                    <button type="submit" aria-label="Cari katalog" class="absolute right-0 top-0 w-11 h-11 inline-flex items-center justify-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-black">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </form>
                <div class="hidden lg:flex lg:flex-1 justify-between items-center gap-4">
                    <p class="text-sm text-gray-500" aria-live="polite">
                        <span class="font-bold text-brand-black">{{ $products->total() }}</span> produk ditemukan
                    </p>

                    <form method="GET" action="{{ route('products.index') }}" data-catalog-form autocomplete="off" class="flex items-center gap-3">
                        @include('products._catalog-state-inputs', ['catalogState' => $catalogState, 'exclude' => ['sort']])
                        <label for="desktop-catalog-sort" class="text-xs uppercase tracking-widest text-gray-400">Urutkan:</label>
                        <select id="desktop-catalog-sort" name="sort" data-catalog-sort
                            class="min-h-11 text-sm font-medium bg-transparent border-none focus:ring-2 focus:ring-brand-black cursor-pointer hover:text-brand-gold transition-colors">
                            @foreach ($sortLabels as $value => $label)
                                <option value="{{ $value }}" {{ $catalogState->sort === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="sr-only">Terapkan urutan</button>
                    </form>
                </div>
            </div>

            @if ($activeFilterCount > 0)
                <div class="mb-6 flex items-center justify-between gap-4 border-y border-gray-100 py-3" aria-live="polite">
                    <p class="text-xs text-gray-600">
                        <span class="font-semibold text-brand-black">{{ $activeFilterCount }} filter aktif</span>
                        <span class="mx-1 text-gray-300" aria-hidden="true">·</span>
                        {{ $products->total() }} hasil
                    </p>
                    <a href="{{ route('products.index') }}"
                        class="min-h-11 inline-flex items-center text-xs font-semibold uppercase tracking-wider underline underline-offset-4 hover:text-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                        Hapus semua
                    </a>
                </div>
            @else
                <p class="lg:hidden mb-5 text-xs text-gray-500" aria-live="polite">
                    <span class="font-semibold text-brand-black">{{ $products->total() }}</span> produk ditemukan
                </p>
            @endif

            <div class="catalog-layout">
                <aside class="catalog-sidebar hidden lg:block shrink-0 sticky top-32 h-fit" aria-label="Filter katalog">
                    <form method="GET" action="{{ route('products.index') }}" id="desktopFilterForm" data-catalog-form autocomplete="off" class="space-y-5">
                        @if ($catalogState->sort !== 'latest')
                            <input type="hidden" name="sort" value="{{ $catalogState->sort }}">
                        @endif

                        <input type="hidden" name="search" value="{{ $catalogState->search }}">
                        @include('products._catalog-filters', ['filterSurface' => 'desktop'])

                        @if ($activeFilterCount > 0)
                            <a href="{{ route('products.index') }}" class="min-h-11 w-full inline-flex items-center justify-center border border-gray-300 text-xs font-semibold uppercase tracking-wider hover:border-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Hapus semua filter</a>
                        @endif
                    </form>
                </aside>

                <div class="flex-1 min-w-0">
                    @if ($products->count() > 0)
                        <div class="catalog-grid">
                            @foreach ($products as $product)
                                @php
                                    $detailUrl = route('products.show', array_merge(['product' => $product->slug], $detailContext));
                                @endphp
                                @include('products._catalog-card', [
                                    'product' => $product,
                                    'detailUrl' => $detailUrl,
                                    'prioritizeImage' => $loop->first,
                                ])
                            @endforeach
                        </div>

                        <nav class="mt-16 border-t border-gray-100 pt-10" aria-label="Pagination katalog">
                            <div class="flex flex-col items-center justify-center gap-6">
                                <p class="text-[10px] text-gray-400 uppercase tracking-widest font-light">Menampilkan {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} produk</p>
                                <div class="catalog-pagination">
                                    @if ($products->onFirstPage())
                                        <span aria-disabled="true" class="min-h-11 px-4 sm:px-8 border border-gray-100 text-gray-300 text-xs uppercase tracking-widest inline-flex items-center cursor-not-allowed">Sebelumnya</span>
                                    @else
                                        <a href="{{ $products->previousPageUrl() }}" class="min-h-11 px-4 sm:px-8 border border-brand-black text-brand-black hover:bg-brand-black hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black transition-colors text-xs uppercase tracking-widest inline-flex items-center">Sebelumnya</a>
                                    @endif

                                    <span class="font-mayluxa text-xl px-2 text-brand-black" aria-current="page">{{ $products->currentPage() }}</span>

                                    @if ($products->hasMorePages())
                                        <a href="{{ $products->nextPageUrl() }}" class="min-h-11 px-4 sm:px-8 bg-brand-black text-white hover:bg-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black transition-colors text-xs uppercase tracking-widest shadow-lg inline-flex items-center">Berikutnya</a>
                                    @else
                                        <span aria-disabled="true" class="min-h-11 px-4 sm:px-8 border border-gray-100 text-gray-300 text-xs uppercase tracking-widest inline-flex items-center cursor-not-allowed">Akhir katalog</span>
                                    @endif
                                </div>
                            </div>
                        </nav>
                    @else
                        <div class="flex flex-col items-center justify-center py-20 text-center" role="status">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4" aria-hidden="true">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <h2 class="font-mayluxa text-2xl mb-2">Produk tidak ditemukan</h2>
                            <p class="text-gray-500 text-sm font-light mb-6 max-w-md">Coba ubah kata pencarian atau hapus beberapa filter untuk melihat hasil lain.</p>
                            <a href="{{ route('products.index') }}" class="min-h-11 inline-flex items-center border border-brand-black px-5 text-xs font-semibold uppercase tracking-widest hover:bg-brand-black hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black transition-colors">Hapus semua filter</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <dialog id="mobileFilter" class="modal modal-bottom sm:modal-middle" aria-labelledby="mobile-filter-title">
        <div class="modal-box bg-white rounded-t-2xl sm:rounded-lg p-0 max-h-[88dvh] flex flex-col">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h2 id="mobile-filter-title" class="font-mayluxa text-xl">Filter katalog</h2>
                    <p class="mt-1 text-xs text-gray-500">{{ $products->total() }} hasil saat ini</p>
                </div>
                <button type="button" data-catalog-filter-close aria-label="Tutup filter" class="w-11 h-11 inline-flex items-center justify-center text-gray-500 hover:text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-black">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="GET" action="{{ route('products.index') }}" data-catalog-form autocomplete="off" class="min-h-0 flex-1 overflow-y-auto p-5 space-y-5">
                @if ($catalogState->sort !== 'latest')
                    <input type="hidden" name="sort" value="{{ $catalogState->sort }}">
                @endif

                <input type="hidden" name="search" value="{{ $catalogState->search }}">
                @include('products._catalog-filters', ['filterSurface' => 'mobile'])

                <div class="sticky bottom-0 -mx-5 -mb-5 mt-8 grid grid-cols-2 gap-3 border-t border-gray-100 bg-white p-5">
                    <a href="{{ route('products.index') }}" class="min-h-11 inline-flex items-center justify-center border border-gray-300 px-3 text-xs font-semibold uppercase tracking-wider hover:border-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-black">Hapus semua</a>
                    <button type="submit" class="min-h-11 bg-brand-black text-white px-3 text-xs font-semibold uppercase tracking-wider hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Tampilkan hasil</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop bg-black/50">
            <button aria-label="Tutup filter">Tutup</button>
        </form>
    </dialog>
@endsection
