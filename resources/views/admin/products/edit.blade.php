@extends('layouts.admin')

@section('content')
@php($editingPublished = $product->isPublished())
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ $catalogReturnPath }}" class="text-sm text-gray-500 hover:text-black">Products</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <span class="ml-1 text-sm font-medium text-gray-800 md:ml-2">Edit: {{ $product->name }}</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Produk</h1>
            <div class="mt-1">@include('admin.products._publication', ['product' => $product])</div>
        </div>
        <a href="{{ $catalogReturnPath }}" class="text-sm font-medium text-gray-500 hover:text-black transition-colors">
            &larr; Cancel
        </a>
    </div>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 p-4 mb-6 rounded-lg">
        <div class="flex">
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Periksa kembali data produk</h3>
                <ul class="mt-2 list-disc list-inside text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r">
        <p class="text-sm text-red-700">{{ session('error') }}</p>
    </div>
    @endif

    <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="return_to" value="{{ $catalogReturnPath }}">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 pb-2 border-b border-gray-100">Informasi Dasar</h3>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('name') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="e.g. Afnan 9 PM" required @error('name') aria-describedby="name-error" aria-invalid="true" @enderror>
                            @error('name')
                            <p id="name-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Brand @if($editingPublished)<span class="text-red-500">*</span>@else<span class="text-xs font-normal text-gray-400">Wajib saat publish</span>@endif</label>
                                <select name="brand_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('brand_id') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" @error('brand_id') aria-describedby="brand-error" aria-invalid="true" @enderror>
                                    <option value="">Pilih brand</option>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id')
                                <p id="brand-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori @if($editingPublished)<span class="text-red-500">*</span>@else<span class="text-xs font-normal text-gray-400">Wajib saat publish</span>@endif</label>
                                <select name="category_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('category_id') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" @error('category_id') aria-describedby="category-error" aria-invalid="true" @enderror>
                                    <option value="">Pilih kategori</option>
                                    @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                <p id="category-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi @if($editingPublished)<span class="text-red-500">*</span>@else<span class="text-xs font-normal text-gray-400">Wajib saat publish</span>@endif</label>
                            <textarea name="description" rows="5" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('description') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="Profil aroma dan informasi penting produk." @if($editingPublished) required @endif @error('description') aria-describedby="description-error" aria-invalid="true" @enderror>{{ old('description', $product->description) }}</textarea>
                            @error('description')
                            <p id="description-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Compare At Price (Rp) <span class="text-gray-400 text-xs">(Optional)</span></label>
                            <input type="number" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" min="0" step="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('compare_at_price') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="750000" @error('compare_at_price') aria-describedby="compare-at-price-error" aria-invalid="true" @enderror>
                            <p class="mt-1 text-xs text-gray-400">Kosongkan jika tidak ingin harga coret.</p>
                            @error('compare_at_price')
                            <p id="compare-at-price-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender @if($editingPublished)<span class="text-red-500">*</span>@else<span class="text-xs font-normal text-gray-400">Wajib saat publish</span>@endif</label>
                                <select name="gender" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm">
                                    <option value="">Pilih gender</option>
                                    <option value="Unisex" {{ old('gender', $product->gender) == 'Unisex' ? 'selected' : '' }}>Unisex</option>
                                    <option value="Pria" {{ old('gender', $product->gender) == 'Pria' ? 'selected' : '' }}>Pria</option>
                                    <option value="Wanita" {{ old('gender', $product->gender) == 'Wanita' ? 'selected' : '' }}>Wanita</option>
                                </select>
                            </div>
                            <div class="pb-3">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_best_seller" value="1" class="rounded border-gray-300 text-black shadow-sm focus:border-black focus:ring focus:ring-black" {{ old('is_best_seller', $product->is_best_seller) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-700">Tandai sebagai Terlaris</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-5">
                            <div class="mb-3">
                                <h4 class="text-sm font-semibold text-gray-900">Ketersediaan katalog</h4>
                                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                                    Bukan stok live. Status tersedia kembali menjadi belum dikonfirmasi setelah 36 jam; sold out tetap sampai diperbarui.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                <div>
                                    <label for="availability-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select id="availability-status" name="availability_status" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('availability_status') border-red-500 @enderror" @error('availability_status') aria-describedby="availability-status-error" aria-invalid="true" @enderror>
                                        <option value="unknown" {{ old('availability_status', $product->availability_status ?? 'unknown') === 'unknown' ? 'selected' : '' }}>Belum dikonfirmasi</option>
                                        <option value="available" {{ old('availability_status', $product->availability_status) === 'available' ? 'selected' : '' }}>Tersedia</option>
                                        <option value="sold_out" {{ old('availability_status', $product->availability_status) === 'sold_out' ? 'selected' : '' }}>Sold out</option>
                                    </select>
                                    @error('availability_status')
                                    <p id="availability-status-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                                    <input type="hidden" name="availability_confirmed" value="0">
                                    <input type="checkbox" name="availability_confirmed" value="1" class="mt-0.5 rounded border-gray-300 text-black shadow-sm focus:border-black focus:ring focus:ring-black" {{ old('availability_confirmed') ? 'checked' : '' }}>
                                    <span>
                                        <span class="block font-medium text-gray-900">Konfirmasi ulang sekarang</span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">Perbarui waktu pengecekan walaupun status tidak berubah.</span>
                                    </span>
                                </label>
                            </div>

                            <p class="mt-3 text-xs text-gray-500">
                                Efektif saat ini:
                                <span class="font-semibold text-gray-700">
                                    {{ match ($product->effective_availability) {
                                        'available' => 'Tersedia',
                                        'sold_out' => 'Sold out',
                                        default => 'Belum dikonfirmasi',
                                    } }}
                                </span>
                                @if($product->availability_checked_at)
                                    · diperiksa {{ $product->availability_checked_at->format('d M Y, H:i') }}
                                    @if($product->availability_source)
                                        melalui {{ $product->availability_source }}
                                    @endif
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                @php($notes = (array) ($product->fragrance_notes ?? []))
                @php($topNotes = $notes['top'] ?? '')
                @php($middleNotes = $notes['middle'] ?? '')
                @php($baseNotes = $notes['base'] ?? '')
                @php($top = is_array($topNotes) ? implode(', ', $topNotes) : $topNotes)
                @php($middle = is_array($middleNotes) ? implode(', ', $middleNotes) : $middleNotes)
                @php($base = is_array($baseNotes) ? implode(', ', $baseNotes) : $baseNotes)
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 pb-2 border-b border-gray-100">Fragrance Notes</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Top Notes</label>
                            <input type="text" name="top_notes" value="{{ old('top_notes', $top) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm" placeholder="Citrus, Bergamot, Apple">
                            <p class="mt-1 text-xs text-gray-400">Use commas to separate notes.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Middle Notes (Heart)</label>
                            <input type="text" name="middle_notes" value="{{ old('middle_notes', $middle) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm" placeholder="Rose, Jasmine, Saffron">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Base Notes</label>
                            <input type="text" name="base_notes" value="{{ old('base_notes', $base) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm" placeholder="Vanilla, Musk, Oud, Amber">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="mb-5 pb-2 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">Ukuran & Harga</h3>
                        <p class="text-xs text-gray-500 mt-1">Draft boleh belum mempunyai offer; satu ukuran dan harga wajib sebelum publish.</p>
                    </div>

                    @php($offer = $product->variants->first())
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        @if($offer)
                        <input type="hidden" name="variants[0][id]" value="{{ $offer->id }}">
                        @endif

                        <div>
                            <label for="offer-volume" class="block text-xs font-medium text-gray-600 mb-1">Ukuran (ml) @if($editingPublished)<span class="text-red-500">*</span>@else<span class="text-gray-400">Wajib saat publish</span>@endif</label>
                            <input id="offer-volume" type="number" name="variants[0][volume]" value="{{ old('variants.0.volume', $offer?->volume) }}" min="1" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black @error('variants.0.volume') border-red-500 @enderror" placeholder="100" @if($editingPublished) required @endif>
                            @error('variants.0.volume')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="offer-price" class="block text-xs font-medium text-gray-600 mb-1">Harga Jual (Rp) @if($editingPublished)<span class="text-red-500">*</span>@else<span class="text-gray-400">Wajib saat publish</span>@endif</label>
                            <input id="offer-price" type="number" name="variants[0][price]" value="{{ old('variants.0.price', $offer?->price) }}" min="1" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black @error('variants.0.price') border-red-500 @enderror" placeholder="500000" @if($editingPublished) required @endif>
                            @error('variants.0.price')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="offer-stock" class="block text-xs font-medium text-gray-600 mb-1">Snapshot Stok <span class="text-gray-400">Opsional</span></label>
                            <input id="offer-stock" type="number" name="variants[0][stock]" value="{{ old('variants.0.stock', $offer?->stock ?? 0) }}" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black @error('variants.0.stock') border-red-500 @enderror">
                            <p class="mt-1 text-xs text-gray-400">Bukan jaminan stok live.</p>
                            @error('variants.0.stock')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-bold text-gray-900">Kesiapan publish</h3>
                        @include('admin.products._publication', ['product' => $product])
                    </div>

                    @if($publicationBlockers === [])
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            Semua syarat katalog sudah lengkap.
                        </div>
                    @else
                        <p class="mt-3 text-xs leading-relaxed text-gray-500">
                            @if($editingPublished)
                                Produk legacy ini tetap tayang. Lengkapi kekurangannya secara bertahap.
                            @else
                                Lengkapi hal berikut sebelum produk dapat ditayangkan.
                            @endif
                        </p>
                        <ul class="mt-3 space-y-2 text-xs text-amber-800">
                            @foreach($publicationBlockers as $blocker)
                                <li class="flex items-start gap-2">
                                    <span class="mt-1 h-1.5 w-1.5 flex-none rounded-full bg-amber-500" aria-hidden="true"></span>
                                    <span>{{ $blocker }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">Current Images</h3>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        @foreach($product->images as $image)
                        <div class="relative group aspect-square rounded overflow-hidden border border-gray-200">
                            <img src="{{ $image->image_url }}" alt="{{ $product->name }} image {{ $loop->iteration }}" class="w-full h-full object-cover" loading="lazy">
                        </div>
                        @endforeach
                    </div>

                    @if($product->images->isNotEmpty())
                        <p class="mb-4 text-xs leading-relaxed text-amber-700">
                            Penghapusan gambar dinonaktifkan sementara agar file dan metadata tetap aman. Penggantian gambar akan disiapkan pada tahap media berikutnya.
                        </p>
                    @endif

                    <div class="border-t pt-4 mt-4">
                        <label for="new-images" class="block text-sm font-medium text-gray-700 mb-2">Add New Images</label>
                        <input id="new-images" type="file" name="new_images[]" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 transition">
                        <p class="mt-2 text-xs text-gray-400">You can upload multiple images at once.</p>
                    </div>
                </div>

                <div class="sticky top-6 space-y-3">
                    <button type="submit" name="publication_action" value="save" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-800 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
                        {{ $editingPublished ? 'Simpan perubahan' : ($product->publication_status === 'draft' ? 'Simpan draft' : 'Simpan perubahan') }}
                    </button>

                    @if(! $editingPublished)
                        <button type="submit" name="publication_action" value="published" class="w-full rounded-xl bg-black px-4 py-3 text-sm font-bold uppercase tracking-widest text-white shadow-lg transition-colors hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
                            {{ $product->publication_status === 'archived' ? 'Publish kembali' : 'Publish produk' }}
                        </button>
                        <p class="text-center text-xs leading-relaxed text-gray-500">Publish akan ditolak bila syarat katalog belum lengkap.</p>
                    @endif
                </div>
            </div>
        </div>
    </form>
    
</div>

@endsection
