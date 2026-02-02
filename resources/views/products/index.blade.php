@extends('layouts.app')

@section('title', 'Katalog Parfum Original - Qammaris Perfumes')

@section('content')

    {{-- 1. HERO SECTION (Header Katalog) --}}
    <div class="bg-white pt-24 pb-8">
        <div class="container mx-auto px-4">
            <div class="bg-brand-black text-white py-14 px-6 text-center relative overflow-hidden rounded-sm shadow-sm">
                {{-- Background Pattern Subtle --}}
                <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>

                <div class="relative z-10">
                    <h1 class="font-mayluxa text-4xl lg:text-6xl tracking-wide mb-3 text-white">All Collections</h1>
                    <p class="text-xs md:text-sm uppercase tracking-[0.3em] text-brand-gold font-light">Premium Fragrances</p>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:hidden sticky top-[70px] z-30 bg-white border-y border-gray-100 shadow-sm">
        <div class="grid grid-cols-2 divide-x divide-gray-100">
            <button onclick="mobileFilter.showModal()"
                class="py-3 flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-xs font-bold uppercase tracking-widest text-brand-black">Filter & Search</span>
            </button>

            <div class="relative">
                <select onchange="window.location.href = updateUrlParameter('sort', this.value)"
                    class="absolute inset-0 w-full h-full opacity-0 z-10">
                    <option value="latest">Newest</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="popular">Terlaris</option>
                </select>
                <div class="py-3 flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors h-full">
                    <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    <span class="text-xs font-bold uppercase tracking-widest text-brand-black">Sort By</span>
                </div>
            </div>
        </div>
    </div>

    @php
        $selectedBrands = (array) request('brand', []);
    @endphp

    <section class="bg-white pb-20 pt-8 min-h-screen">
        <div class="container mx-auto px-4">

            <div class="hidden lg:flex justify-between items-end mb-8 border-b border-gray-100 pb-4">
                <p class="text-sm text-gray-500">Showing <span
                        class="font-bold text-brand-black">{{ $products->count() }}</span> products</p>

                <div class="flex items-center gap-3">
                    <span class="text-xs uppercase tracking-widest text-gray-400">Sort By:</span>
                    <select onchange="window.location.href = updateUrlParameter('sort', this.value)"
                        class="text-sm font-medium bg-transparent border-none focus:ring-0 cursor-pointer hover:text-brand-gold transition-colors">
                        <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Newest Arrivals</option>
                        <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High
                        </option>
                        <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to
                            Low</option>
                        <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Terlaris</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-12">

                <aside class="hidden lg:block w-64 shrink-0 sticky top-32 h-fit">
                    <form method="GET" action="{{ route('products.index') }}" id="desktopFilterForm" class="space-y-10">

                        <div class="relative group">
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="SEARCH PRODUCT..."
                                class="w-full border-b border-gray-300 py-2 text-sm focus:outline-none focus:border-brand-black uppercase placeholder:text-gray-400 bg-transparent transition-colors">
                            <button type="submit"
                                class="absolute right-0 top-2 text-gray-400 group-hover:text-brand-black transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>

                        <div>
                            <h3 class="font-bold text-xs uppercase tracking-widest mb-4 text-brand-black">Category</h3>
                            <div class="space-y-3">
                                <label class="flex items-center cursor-pointer group">
                                    <input type="radio" name="category" value=""
                                        {{ !request('category') ? 'checked' : '' }} onchange="this.form.submit()"
                                        class="hidden">
                                    <span
                                        class="text-sm text-gray-500 group-hover:text-brand-black transition-colors {{ !request('category') ? 'font-bold text-brand-black' : '' }}">
                                        All Categories
                                    </span>
                                </label>
                                @foreach ($categories as $category)
                                    <label class="flex items-center cursor-pointer group">
                                        <input type="radio" name="category" value="{{ $category->id }}"
                                            {{ request('category') == $category->id ? 'checked' : '' }}
                                            onchange="this.form.submit()" class="hidden">
                                        <span
                                            class="text-sm text-gray-500 group-hover:text-brand-black transition-colors {{ request('category') == $category->id ? 'font-bold text-brand-black underline decoration-brand-gold decoration-2 underline-offset-4' : '' }}">
                                            {{ $category->name }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h3 class="font-bold text-xs uppercase tracking-widest mb-4 text-brand-black">Brands</h3>
                            <div class="space-y-2 max-h-80 overflow-y-auto custom-scrollbar pr-2">
                                @foreach ($brands as $brand)
                                    <label class="flex items-center cursor-pointer gap-3 group">
                                        <input type="checkbox" name="brand[]" value="{{ $brand->id }}"
                                            {{ in_array($brand->id, $selectedBrands) ? 'checked' : '' }}
                                            onchange="this.form.submit()"
                                            class="checkbox checkbox-xs rounded-none border-gray-300 checked:bg-brand-black checked:border-brand-black">
                                        <span
                                            class="text-sm text-gray-500 group-hover:text-brand-black transition-colors">{{ $brand->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </form>
                </aside>

                <div class="flex-1">
                    @if ($products->count() > 0)

                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-4 gap-y-10 md:gap-x-6 md:gap-y-12">
                            @foreach ($products as $product)
                                <div class="group flex flex-col">
                                    <div class="relative aspect-[3/4] bg-[#F9F9F9] mb-4 overflow-hidden">
                                        <a href="{{ route('products.show', $product->slug) }}" class="block w-full h-full">
                                            {{-- FIX: Menggunakan image_url agar gambar muncul --}}
                                            <img src="{{ $product->primaryImage?->image_url ?? 'https://placehold.co/400x533/F5F5F5/333?text=No+Image' }}"
                                                alt="{{ $product->name }}"
                                                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out mix-blend-multiply opacity-95 group-hover:opacity-100">
                                        </a>

                                        @if ($product->is_best_seller)
                                            <div
                                                class="absolute top-0 left-0 bg-brand-black text-white text-[9px] md:text-[10px] font-bold px-2 py-1 uppercase tracking-widest z-10">
                                                Terlaris
                                            </div>
                                        @endif

                                        <div
                                            class="absolute bottom-0 left-0 w-full translate-y-full group-hover:translate-y-0 transition-transform duration-300 hidden lg:block">
                                            <a href="{{ route('products.show', $product->slug) }}"
                                                class="bg-white/90 backdrop-blur text-brand-black text-xs uppercase tracking-widest w-full py-3 block text-center hover:bg-brand-black hover:text-white transition-colors font-medium border-t border-gray-100">
                                                Quick View
                                            </a>
                                        </div>
                                    </div>

                                    <div class="text-center flex-1 flex flex-col">
                                        <div class="text-[10px] md:text-xs text-gray-400 uppercase tracking-widest mb-1.5">
                                            {{ $product->brand->name }}
                                        </div>

                                        <h3
                                            class="font-mayluxa text-base md:text-lg text-brand-black mb-2 leading-tight line-clamp-2 group-hover:text-brand-gold transition-colors">
                                            <a href="{{ route('products.show', $product->slug) }}">
                                                {{ $product->name }}
                                            </a>
                                        </h3>

                                        <div class="mt-auto">
                                            @if ($product->compare_at_price && $product->compare_at_price > $product->cheapest_price)
                                                <div class="text-[10px] md:text-xs text-gray-400 line-through">
                                                    {{ format_rupiah($product->compare_at_price) }}
                                                </div>
                                            @endif
                                            <div class="text-sm md:text-base font-medium text-brand-black">
                                                {{ format_rupiah($product->cheapest_price) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-16 border-t border-gray-100 pt-10">
                            <div class="flex flex-col items-center justify-center gap-6">

                                <p class="text-[10px] text-gray-400 uppercase tracking-widest font-light">
                                    Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of
                                    {{ $products->total() }} Products
                                </p>

                                <div class="flex items-center gap-4">
                                    @if ($products->onFirstPage())
                                        <button disabled
                                            class="px-8 py-3 border border-gray-100 text-gray-300 text-xs uppercase tracking-widest cursor-not-allowed">
                                            Previous
                                        </button>
                                    @else
                                        <a href="{{ $products->previousPageUrl() }}"
                                            class="px-8 py-3 border border-brand-black text-brand-black hover:bg-brand-black hover:text-white transition-all duration-300 text-xs uppercase tracking-widest">
                                            Previous
                                        </a>
                                    @endif

                                    <span class="font-mayluxa text-xl px-2 text-brand-black">
                                        {{ $products->currentPage() }}
                                    </span>

                                    @if ($products->hasMorePages())
                                        <a href="{{ $products->nextPageUrl() }}"
                                            class="px-8 py-3 bg-brand-black text-white hover:bg-brand-gold hover:-translate-y-1 transition-all duration-300 text-xs uppercase tracking-widest shadow-lg">
                                            Next Page
                                        </a>
                                    @else
                                        <button disabled
                                            class="px-8 py-3 border border-gray-100 text-gray-300 text-xs uppercase tracking-widest cursor-not-allowed">
                                            End of Catalog
                                        </button>
                                    @endif
                                </div>

                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <h3 class="font-mayluxa text-2xl mb-2">No Products Found</h3>
                            <p class="text-gray-500 text-sm font-light mb-6">We couldn't find what you're looking for.</p>
                            <a href="{{ route('products.index') }}"
                                class="btn btn-outline btn-sm rounded-none uppercase tracking-widest">Clear Filters</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <dialog id="mobileFilter" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box bg-white rounded-t-2xl sm:rounded-lg p-0 h-[80vh] flex flex-col">
            <div class="flex justify-between items-center p-5 border-b border-gray-100 flex-shrink-0">
                <h3 class="font-mayluxa text-xl">Filter & Search</h3>
                <form method="dialog">
                    <button class="outline-none p-2"><svg class="w-6 h-6 text-gray-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg></button>
                </form>
            </div>

            <form method="GET" action="{{ route('products.index') }}" class="flex-1 overflow-y-auto p-5">
                <div class="mb-8">
                    <label class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Search</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Type perfume name..."
                            class="input input-bordered w-full rounded-none focus:outline-none focus:border-brand-black bg-gray-50 border-gray-200">
                        <div class="absolute right-3 top-3 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Category</label>
                    <select name="category"
                        class="select select-bordered w-full rounded-none focus:outline-none focus:border-brand-black bg-gray-50 border-gray-200">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}"
                                {{ request('category') == $category->id ? 'selected' : '' }}>{{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-8">
                    <label class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Brands</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($brands as $brand)
                            <label
                                class="flex items-center space-x-3 p-3 border border-gray-100 rounded hover:border-brand-black cursor-pointer {{ in_array($brand->id, $selectedBrands) ? 'bg-brand-black text-white' : 'bg-white' }}">
                                <input type="checkbox" name="brand[]" value="{{ $brand->id }}"
                                    {{ in_array($brand->id, $selectedBrands) ? 'checked' : '' }}
                                    class="checkbox checkbox-xs rounded-none checkbox-primary hidden">
                                <span class="text-xs uppercase font-medium">{{ $brand->name }}</span>
                                @if (in_array($brand->id, $selectedBrands))
                                    <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                @if (request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif

                <button type="submit"
                    class="btn bg-brand-black text-white hover:bg-gray-800 w-full rounded-none h-12 uppercase tracking-widest">
                    Show Results
                </button>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop bg-black/50">
            <button>close</button>
        </form>
    </dialog>

@endsection

@push('scripts')
    <script>
        // Helper untuk update parameter URL tanpa reload (khusus sort di desktop)
        function updateUrlParameter(key, value) {
            const url = new URL(window.location);
            url.searchParams.set(key, value);
            return url.toString();
        }
    </script>
@endpush
