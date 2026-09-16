@extends('layouts.admin')

@section('content')
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-400 hover:text-black transition-colors flex items-center gap-1 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Dashboard
                </a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-600 text-sm">Products</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Product Catalog</h1>
        </div>
        
        <a href="{{ route('admin.products.create') }}" class="btn-primary flex items-center gap-2 shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add New Product
        </a>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <form action="{{ route('admin.products.index') }}" method="GET" class="space-y-4">
        
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(16rem,1.5fr)_minmax(11rem,1fr)_minmax(11rem,1fr)_minmax(9rem,0.8fr)_auto] xl:items-center">
    <div class="relative w-full group">
        <label for="catalog-search" class="sr-only">Cari produk</label>
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400 group-focus-within:text-black transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <input id="catalog-search" type="search" name="search" value="{{ $catalogContext['search'] ?? '' }}"
            class="block w-full pl-11 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black text-sm transition-shadow placeholder-gray-400"
            placeholder="Cari nama produk atau brand...">
    </div>

    <div class="w-full relative">
        <label for="catalog-availability" class="sr-only">Filter ketersediaan</label>
        <select id="catalog-availability" name="availability" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2.5 px-4 pr-8 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-sm cursor-pointer">
            <option value="">Semua ketersediaan</option>
            <option value="unknown" {{ ($catalogContext['availability'] ?? null) === 'unknown' ? 'selected' : '' }}>Belum dikonfirmasi</option>
            <option value="available" {{ ($catalogContext['availability'] ?? null) === 'available' ? 'selected' : '' }}>Tersedia</option>
            <option value="sold_out" {{ ($catalogContext['availability'] ?? null) === 'sold_out' ? 'selected' : '' }}>Sold out</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
    </div>

    <div class="w-full relative">
        <label for="catalog-brand" class="sr-only">Filter brand</label>
        <select id="catalog-brand" name="brand_id" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2.5 px-4 pr-8 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-sm cursor-pointer">
            <option value="">All Brands</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}" {{ ($catalogContext['brand_id'] ?? null) === $brand->id ? 'selected' : '' }}>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
    </div>

    <div class="w-full relative">
        <label for="catalog-sort" class="sr-only">Urutkan produk</label>
        <select id="catalog-sort" name="sort" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2.5 px-4 pr-8 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-sm cursor-pointer">
            <option value="latest" {{ ! isset($catalogContext['sort']) ? 'selected' : '' }}>Terbaru</option>
            <option value="name_asc" {{ ($catalogContext['sort'] ?? null) === 'name_asc' ? 'selected' : '' }}>Nama A–Z</option>
            <option value="name_desc" {{ ($catalogContext['sort'] ?? null) === 'name_desc' ? 'selected' : '' }}>Nama Z–A</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
    </div>

    @if(isset($catalogContext['search']) || isset($catalogContext['brand_id']) || isset($catalogContext['availability']) || isset($catalogContext['sort']))
        <a href="{{ route('admin.products.index') }}" class="inline-flex min-h-10 items-center justify-center px-4 py-2.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm font-medium transition-colors">
            Reset
        </a>
    @endif
</div>

        <div class="text-sm text-gray-500 font-medium md:text-right">
            Menampilkan <span class="text-black">{{ $products->total() }}</span> produk
        </div>
    </form>
</div>

<div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
    <div>
        <table class="w-full">
            <thead class="hidden md:table-header-group">
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Product Info</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Category & Brand</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Price Range</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Availability</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50 transition-colors group">
                    <td class="px-4 py-5 md:px-6 md:py-4">
                        <div class="flex items-start md:items-center">
                            <div class="h-14 w-14 flex-shrink-0 rounded-md border border-gray-200 overflow-hidden bg-gray-100">
                                @if($product->primaryImage)
                                    <img src="{{ $product->primaryImage->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="flex items-center justify-center h-full text-xs text-gray-400">No Img</div>
                                @endif
                            </div>
                            <div class="ml-4 min-w-0 flex-1">
                                <div class="whitespace-normal text-sm font-bold text-gray-900 group-hover:text-gold transition-colors">{{ $product->name }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $product->gender }} @if($product->is_best_seller) • <span class="text-amber-600 font-bold">Terlaris</span> @endif</div>
                                <div class="mt-1 flex items-center gap-1.5 text-xs font-medium {{ $product->is_active ? 'text-emerald-700' : 'text-amber-700' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $product->is_active ? 'bg-emerald-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                                    {{ $product->is_active ? 'Aktif' : 'Diarsipkan' }}
                                </div>

                                <div class="mt-3 space-y-3 md:hidden">
                                    <div class="text-xs text-gray-500">
                                        <span class="font-medium text-gray-800">{{ $product->brand?->name ?? 'Brand belum diisi' }}</span>
                                        · {{ $product->category?->name ?? 'Kategori belum diisi' }}
                                    </div>
                                    <div class="font-mono text-sm font-medium text-gray-700">{{ $product->price_range }}</div>
                                    @include('admin.products._availability', ['product' => $product])
                                    @include('admin.products._actions', ['product' => $product, 'catalogReturnPath' => $catalogReturnPath])
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        <div class="text-sm text-gray-900 font-medium">{{ $product->brand?->name ?? 'Brand belum diisi' }}</div>
                        <div class="text-xs text-gray-500">{{ $product->category?->name ?? 'Kategori belum diisi' }}</div>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        <span class="text-sm font-mono text-gray-700 font-medium">{{ $product->price_range }}</span>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        @include('admin.products._availability', ['product' => $product])
                    </td>
                    <td class="hidden px-6 py-4 text-right text-sm font-medium md:table-cell">
                        <div class="flex justify-end">
                            @include('admin.products._actions', ['product' => $product, 'catalogReturnPath' => $catalogReturnPath])
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 bg-gray-50">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <p class="text-base font-medium text-gray-900">Produk tidak ditemukan</p>
                            <p class="text-sm text-gray-500 mt-1">Coba ubah pencarian atau filter.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        {{ $products->links('pagination::tailwind') }}
    </div>
    @endif
</div>
@endsection
