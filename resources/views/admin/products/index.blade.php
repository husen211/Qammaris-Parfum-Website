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
    <form action="{{ route('admin.products.index') }}" method="GET" class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
    <div class="relative w-full md:w-80 group">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400 group-focus-within:text-black transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <input type="text" name="search" value="{{ request('search') }}" 
            class="block w-full pl-11 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black text-sm transition-shadow placeholder-gray-400"
            placeholder="Search by name, SKU...">
    </div>

    <div class="w-full md:w-56 relative">
        <select name="brand_id" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2.5 px-4 pr-8 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-sm cursor-pointer">
            <option value="">All Brands</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
    </div>

    @if(request('search') || request('brand_id'))
        <a href="{{ route('admin.products.index') }}" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm font-medium transition-colors">
            Reset
        </a>
    @endif
</div>

        <div class="text-sm text-gray-500 font-medium">
            Showing <span class="text-black">{{ $products->total() }}</span> Products
        </div>
    </form>
</div>

<div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full whitespace-nowrap">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Product Info</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Category & Brand</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Price Range</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Inventory</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50 transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="h-14 w-14 flex-shrink-0 rounded-md border border-gray-200 overflow-hidden bg-gray-100">
                                @if($product->primaryImage)
                                    <img src="{{ $product->primaryImage->image_url }}" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="flex items-center justify-center h-full text-xs text-gray-400">No Img</div>
                                @endif
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-gray-900 group-hover:text-gold transition-colors">{{ $product->name }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $product->gender }} @if($product->is_best_seller) • <span class="text-amber-600 font-bold">Terlaris</span> @endif</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 font-medium">{{ $product->brand->name }}</div>
                        <div class="text-xs text-gray-500">{{ $product->category->name }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-sm font-mono text-gray-700 font-medium">{{ $product->price_range }}</span>
                    </td>
                    <td class="px-6 py-4">
                        @php $stock = $product->variants->sum('stock'); @endphp
                        <div class="flex items-center">
                            @if($stock == 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Out of Stock
                                </span>
                            @elseif($stock < 10)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Low: {{ $stock }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    In Stock: {{ $stock }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-medium">
                        <div class="flex justify-end items-center gap-2">
                            <a href="{{ route('admin.products.edit', $product->id) }}" class="text-gray-500 hover:text-black p-2 rounded-full hover:bg-gray-100 transition-colors" title="Edit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </a>
                            <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-gray-500 hover:text-red-600 p-2 rounded-full hover:bg-red-50 transition-colors" title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 bg-gray-50">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <p class="text-base font-medium text-gray-900">No products found</p>
                            <p class="text-sm text-gray-500 mt-1">Try adjusting your search or filters.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        {{ $products->appends(request()->query())->links('pagination::tailwind') }}
    </div>
    @endif
</div>
@endsection
