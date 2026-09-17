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
    <div class="bg-white pt-20 pb-5 md:pt-24 md:pb-7">
        <div class="container mx-auto px-4">
            <div class="bg-brand-black text-white py-7 px-5 md:py-9 md:px-6 text-center relative overflow-hidden rounded-sm shadow-sm">
                <div class="absolute inset-x-5 top-4 h-px bg-white/10 md:inset-x-8" aria-hidden="true"></div>
                <div class="absolute inset-x-5 bottom-4 h-px bg-brand-gold/40 md:inset-x-8" aria-hidden="true"></div>
                <div class="relative z-10">
                    <h1 class="font-mayluxa text-3xl md:text-4xl tracking-wide mb-2 text-white">All Collections</h1>
                    <p class="text-[10px] md:text-xs uppercase tracking-[0.3em] text-brand-gold font-light">Premium Fragrances</p>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:hidden sticky top-[70px] z-30 bg-white border-y border-gray-100 shadow-sm">
        <div class="grid grid-cols-2 divide-x divide-gray-100">
            <button type="button" data-catalog-filter-trigger aria-controls="mobileFilter" aria-haspopup="dialog"
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
        data-search="{{ $catalogState->search }}"
        data-brand-ids="{{ implode(',', $catalogState->brandIds) }}"
        data-category="{{ $catalogState->categoryId }}"
        data-gender="{{ $catalogState->gender }}"
        data-price-min="{{ $catalogState->priceMin }}"
        data-price-max="{{ $catalogState->priceMax }}"
        data-availability="{{ $catalogState->availability }}"
        data-sort="{{ $catalogState->sort }}"
        class="bg-white pb-20 pt-8 min-h-screen" aria-label="Hasil katalog">
        <div class="container mx-auto px-4">
            <div class="hidden lg:flex justify-between items-end mb-8 border-b border-gray-100 pb-4">
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

            <div class="flex flex-col lg:flex-row gap-12">
                <aside class="hidden lg:block w-64 shrink-0 sticky top-32 h-fit" aria-label="Filter katalog">
                    <form method="GET" action="{{ route('products.index') }}" id="desktopFilterForm" data-catalog-form autocomplete="off" class="space-y-8">
                        @if ($catalogState->sort !== 'latest')
                            <input type="hidden" name="sort" value="{{ $catalogState->sort }}">
                        @endif

                        <div class="relative group">
                            <label for="desktop-catalog-search" class="sr-only">Cari produk atau brand</label>
                            <input id="desktop-catalog-search" type="search" name="search" value="{{ $catalogState->search }}"
                                maxlength="100" placeholder="Cari produk atau brand"
                                class="w-full min-h-11 border-b border-gray-300 py-2 pr-11 text-sm focus:outline-none focus:border-brand-black placeholder:text-gray-400 bg-transparent transition-colors">
                            <button type="submit" aria-label="Cari katalog"
                                class="absolute right-0 top-0 w-11 h-11 inline-flex items-center justify-center text-gray-400 group-hover:text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-black transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>

                        <fieldset>
                            <legend class="font-bold text-xs uppercase tracking-widest mb-3 text-brand-black">Kategori</legend>
                            <div class="space-y-1">
                                <label class="min-h-11 flex items-center cursor-pointer group">
                                    <input type="radio" name="category" value="" {{ $catalogState->categoryId === null ? 'checked' : '' }} data-catalog-autosubmit class="sr-only peer">
                                    <span class="text-sm text-gray-500 group-hover:text-brand-black peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-brand-black transition-colors {{ $catalogState->categoryId === null ? 'font-bold text-brand-black' : '' }}">Semua kategori</span>
                                </label>
                                @foreach ($categories as $category)
                                    <label class="min-h-11 flex items-center cursor-pointer group">
                                        <input type="radio" name="category" value="{{ $category->id }}" {{ $catalogState->categoryId === $category->id ? 'checked' : '' }} data-catalog-autosubmit class="sr-only peer">
                                        <span class="text-sm text-gray-500 group-hover:text-brand-black peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-brand-black transition-colors {{ $catalogState->categoryId === $category->id ? 'font-bold text-brand-black underline decoration-brand-gold decoration-2 underline-offset-4' : '' }}">{{ $category->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div>
                            <label for="desktop-catalog-gender" class="font-bold text-xs uppercase tracking-widest mb-3 text-brand-black block">Audience</label>
                            <select id="desktop-catalog-gender" name="gender" data-catalog-autosubmit class="select select-bordered w-full min-h-11 rounded-none bg-white border-gray-200 focus:outline-none focus:border-brand-black">
                                <option value="">Semua audience</option>
                                @foreach (['Unisex', 'Pria', 'Wanita'] as $gender)
                                    <option value="{{ $gender }}" {{ $catalogState->gender === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="desktop-catalog-availability" class="font-bold text-xs uppercase tracking-widest mb-3 text-brand-black block">Ketersediaan</label>
                            <select id="desktop-catalog-availability" name="availability" data-catalog-autosubmit class="select select-bordered w-full min-h-11 rounded-none bg-white border-gray-200 focus:outline-none focus:border-brand-black">
                                <option value="">Semua status</option>
                                <option value="available" {{ $catalogState->availability === 'available' ? 'selected' : '' }}>Tersedia saat diperiksa</option>
                                <option value="unknown" {{ $catalogState->availability === 'unknown' ? 'selected' : '' }}>Konfirmasi stok</option>
                                <option value="sold_out" {{ $catalogState->availability === 'sold_out' ? 'selected' : '' }}>Sold out</option>
                            </select>
                        </div>

                        <fieldset>
                            <legend class="font-bold text-xs uppercase tracking-widest mb-3 text-brand-black">Rentang harga</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="sr-only" for="desktop-price-min">Harga minimum</label>
                                <input id="desktop-price-min" type="number" name="price_min" value="{{ $catalogState->priceMin }}" min="0" step="1000" placeholder="Minimum" class="input input-bordered min-h-11 w-full rounded-none bg-white border-gray-200 px-3 text-sm focus:outline-none focus:border-brand-black">
                                <label class="sr-only" for="desktop-price-max">Harga maksimum</label>
                                <input id="desktop-price-max" type="number" name="price_max" value="{{ $catalogState->priceMax }}" min="0" step="1000" placeholder="Maksimum" class="input input-bordered min-h-11 w-full rounded-none bg-white border-gray-200 px-3 text-sm focus:outline-none focus:border-brand-black">
                            </div>
                            <button type="submit" class="mt-2 min-h-11 w-full border border-brand-black px-4 text-xs font-semibold uppercase tracking-wider hover:bg-brand-black hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black transition-colors">Terapkan harga</button>
                        </fieldset>

                        <fieldset>
                            <legend class="font-bold text-xs uppercase tracking-widest mb-3 text-brand-black">Brand</legend>
                            <div class="space-y-1 max-h-80 overflow-y-auto custom-scrollbar pr-2">
                                @foreach ($brands as $brand)
                                    <label class="min-h-11 flex items-center cursor-pointer gap-3 group">
                                        <input type="checkbox" name="brand[]" value="{{ $brand->id }}" {{ in_array($brand->id, $selectedBrands, true) ? 'checked' : '' }} data-catalog-autosubmit class="checkbox checkbox-sm rounded-none border-gray-300 checked:bg-brand-black checked:border-brand-black">
                                        <span class="text-sm text-gray-500 group-hover:text-brand-black transition-colors">{{ $brand->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        @if ($activeFilterCount > 0)
                            <a href="{{ route('products.index') }}" class="min-h-11 w-full inline-flex items-center justify-center border border-gray-300 text-xs font-semibold uppercase tracking-wider hover:border-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Hapus semua filter</a>
                        @endif
                    </form>
                </aside>

                <div class="flex-1 min-w-0">
                    @if ($products->count() > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-8 sm:gap-x-4 md:gap-x-6 md:gap-y-11">
                            @foreach ($products as $product)
                                @php
                                    $detailUrl = route('products.show', array_merge(['product' => $product->slug], $detailContext));
                                @endphp
                                @include('products._card', [
                                    'product' => $product,
                                    'detailUrl' => $detailUrl,
                                    'prioritizeImage' => $loop->index < 4,
                                ])
                            @endforeach
                        </div>

                        <nav class="mt-16 border-t border-gray-100 pt-10" aria-label="Pagination katalog">
                            <div class="flex flex-col items-center justify-center gap-6">
                                <p class="text-[10px] text-gray-400 uppercase tracking-widest font-light">Menampilkan {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} produk</p>
                                <div class="flex items-center gap-2 sm:gap-4">
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
        <div class="modal-box bg-white rounded-t-2xl sm:rounded-lg p-0 h-[88vh] flex flex-col">
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

            <form method="GET" action="{{ route('products.index') }}" data-catalog-form autocomplete="off" class="flex-1 overflow-y-auto p-5 space-y-7">
                @if ($catalogState->sort !== 'latest')
                    <input type="hidden" name="sort" value="{{ $catalogState->sort }}">
                @endif

                <div>
                    <label for="mobile-catalog-search" class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Cari</label>
                    <input id="mobile-catalog-search" type="search" name="search" value="{{ $catalogState->search }}" maxlength="100" placeholder="Nama produk atau brand" class="input input-bordered min-h-11 w-full rounded-none focus:outline-none focus:border-brand-black bg-gray-50 border-gray-200">
                </div>

                <div>
                    <label for="mobile-catalog-category" class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Kategori</label>
                    <select id="mobile-catalog-category" name="category" class="select select-bordered min-h-11 w-full rounded-none focus:outline-none focus:border-brand-black bg-gray-50 border-gray-200">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ $catalogState->categoryId === $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="mobile-catalog-gender" class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Audience</label>
                        <select id="mobile-catalog-gender" name="gender" class="select select-bordered min-h-11 w-full rounded-none focus:outline-none focus:border-brand-black bg-gray-50 border-gray-200">
                            <option value="">Semua audience</option>
                            @foreach (['Unisex', 'Pria', 'Wanita'] as $gender)
                                <option value="{{ $gender }}" {{ $catalogState->gender === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mobile-catalog-availability" class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Ketersediaan</label>
                        <select id="mobile-catalog-availability" name="availability" class="select select-bordered min-h-11 w-full rounded-none focus:outline-none focus:border-brand-black bg-gray-50 border-gray-200">
                            <option value="">Semua status</option>
                            <option value="available" {{ $catalogState->availability === 'available' ? 'selected' : '' }}>Tersedia saat diperiksa</option>
                            <option value="unknown" {{ $catalogState->availability === 'unknown' ? 'selected' : '' }}>Konfirmasi stok</option>
                            <option value="sold_out" {{ $catalogState->availability === 'sold_out' ? 'selected' : '' }}>Sold out</option>
                        </select>
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Rentang harga</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="sr-only" for="mobile-price-min">Harga minimum</label>
                        <input id="mobile-price-min" type="number" name="price_min" value="{{ $catalogState->priceMin }}" min="0" step="1000" placeholder="Minimum" class="input input-bordered min-h-11 w-full rounded-none bg-gray-50 border-gray-200 px-3 text-sm focus:outline-none focus:border-brand-black">
                        <label class="sr-only" for="mobile-price-max">Harga maksimum</label>
                        <input id="mobile-price-max" type="number" name="price_max" value="{{ $catalogState->priceMax }}" min="0" step="1000" placeholder="Maksimum" class="input input-bordered min-h-11 w-full rounded-none bg-gray-50 border-gray-200 px-3 text-sm focus:outline-none focus:border-brand-black">
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Brand</legend>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($brands as $brand)
                            <label class="min-h-11 flex items-center gap-3 px-3 py-2 border border-gray-200 cursor-pointer hover:border-brand-black focus-within:outline focus-within:outline-2 focus-within:outline-brand-black {{ in_array($brand->id, $selectedBrands, true) ? 'bg-brand-black text-white border-brand-black' : 'bg-white' }}">
                                <input type="checkbox" name="brand[]" value="{{ $brand->id }}" {{ in_array($brand->id, $selectedBrands, true) ? 'checked' : '' }} class="checkbox checkbox-sm rounded-none {{ in_array($brand->id, $selectedBrands, true) ? 'border-white' : 'border-gray-300' }}">
                                <span class="text-xs uppercase font-medium leading-tight">{{ $brand->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

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
