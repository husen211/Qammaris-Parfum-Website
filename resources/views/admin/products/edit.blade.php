@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-500 hover:text-black">Products</a>
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
        <h1 class="text-2xl font-bold text-gray-900">Edit Product</h1>
        <a href="{{ route('admin.products.index') }}" class="text-sm font-medium text-gray-500 hover:text-black transition-colors">
            &larr; Cancel
        </a>
    </div>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 p-4 mb-6 rounded-lg">
        <div class="flex">
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
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
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 pb-2 border-b border-gray-100">Basic Information</h3>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('name') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="e.g. Afnan 9 PM" required @error('name') aria-describedby="name-error" aria-invalid="true" @enderror>
                            @error('name')
                            <p id="name-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Brand <span class="text-red-500">*</span></label>
                                <select name="brand_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('brand_id') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" @error('brand_id') aria-describedby="brand-error" aria-invalid="true" @enderror>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id')
                                <p id="brand-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                                <select name="category_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('category_id') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" @error('category_id') aria-describedby="category-error" aria-invalid="true" @enderror>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="5" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('description') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="Short story about the scent profile, longevity, and sillage." required @error('description') aria-describedby="description-error" aria-invalid="true" @enderror>{{ old('description', $product->description) }}</textarea>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <select name="gender" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm">
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
                    </div>
                </div>

                @php
                    $notes = (array) ($product->fragrance_notes ?? []);
                    $topNotes = $notes['top'] ?? '';
                    $middleNotes = $notes['middle'] ?? '';
                    $baseNotes = $notes['base'] ?? '';
                    $top = is_array($topNotes) ? implode(', ', $topNotes) : $topNotes;
                    $middle = is_array($middleNotes) ? implode(', ', $middleNotes) : $middleNotes;
                    $base = is_array($baseNotes) ? implode(', ', $baseNotes) : $baseNotes;
                @endphp
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
                    <div class="flex justify-between items-center mb-5 pb-2 border-b border-gray-100">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Variants & Pricing</h3>
                            <p class="text-xs text-gray-500 mt-1">Add different sizes and prices for this product.</p>
                        </div>
                        <button type="button" onclick="addVariant()" class="text-xs bg-black text-white hover:bg-gray-800 px-3 py-1.5 rounded transition shadow-sm">+ Add Variant</button>
                    </div>
                    
                    <div id="variants-container" class="space-y-3">
                        @foreach($product->variants as $index => $variant)
                        <div class="variant-row grid grid-cols-1 sm:grid-cols-7 gap-3 items-end bg-gray-50 p-4 rounded-lg border border-gray-200" id="variant-db-{{ $index }}">
                            {{-- Hidden ID to identify existing record --}}
                            <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant->id }}">
                            
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Volume (ml)</label>
                                <input type="number" name="variants[{{ $index }}][volume]" value="{{ $variant->volume }}" min="1" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Price (Rp)</label>
                                <input type="number" name="variants[{{ $index }}][price]" value="{{ $variant->price }}" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Stock</label>
                                <input type="number" name="variants[{{ $index }}][stock]" value="{{ $variant->stock }}" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" required>
                            </div>
                            <div class="sm:col-span-1 flex justify-end sm:justify-center">
                                <button type="button" onclick="removeVariant('variant-db-{{ $index }}')" class="text-red-400 hover:text-red-600 p-2 rounded hover:bg-red-50 transition focus:outline-none focus:ring-2 focus:ring-red-300" aria-label="Remove variant" title="Remove variant">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Deleting a variant row will remove it from the database upon save.</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">Current Images</h3>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        @foreach($product->images as $image)
                        <div class="relative group aspect-square rounded overflow-hidden border border-gray-200">
                            <img src="{{ $image->image_url }}" alt="{{ $product->name }} image {{ $loop->iteration }}" class="w-full h-full object-cover" loading="lazy">
                            {{-- Delete Image Button --}}
                            @if(!$loop->first || $product->images->count() > 1) 
                            <button type="button" onclick="deleteImage({{ $image->id }})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300" aria-label="Delete image" title="Delete image">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <div class="border-t pt-4 mt-4">
                        <label for="new-images" class="block text-sm font-medium text-gray-700 mb-2">Add New Images</label>
                        <input id="new-images" type="file" name="new_images[]" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 transition">
                        <p class="mt-2 text-xs text-gray-400">You can upload multiple images at once.</p>
                    </div>
                </div>

                <div class="sticky top-6">
                    <button type="submit" class="w-full bg-black text-white text-lg font-bold uppercase tracking-widest py-4 rounded-xl shadow-xl hover:bg-gray-800 transform hover:-translate-y-1 transition-all duration-200">
                        Update Product
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <form id="delete-image-form" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>

<script>
    // Start index higher than existing db count to avoid clash
    let variantIndex = {{ $product->variants->count() + 100 }};

    function addVariant() {
        const container = document.getElementById('variants-container');
        const html = `
            <div class="variant-row grid grid-cols-1 sm:grid-cols-7 gap-3 items-end bg-blue-50 p-4 rounded-lg border border-blue-100 animate-fade-in-up" id="variant-new-${variantIndex}">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Volume (ml)</label>
                    <input type="number" name="variants[${variantIndex}][volume]" min="1" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Price (Rp)</label>
                    <input type="number" name="variants[${variantIndex}][price]" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Stock</label>
                    <input type="number" name="variants[${variantIndex}][stock]" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" required>
                </div>
                <div class="sm:col-span-1 flex justify-end sm:justify-center">
                    <button type="button" onclick="removeVariant('variant-new-${variantIndex}')" class="text-red-400 hover:text-red-600 p-2 rounded hover:bg-red-50 transition focus:outline-none focus:ring-2 focus:ring-red-300" aria-label="Remove variant" title="Remove variant">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        variantIndex++;
    }

    function removeVariant(id) {
        const row = document.getElementById(id);
        if (row) {
            row.remove();
        }
    }

    function deleteImage(imageId) {
        if(confirm('Delete this image permanently?')) {
            const form = document.getElementById('delete-image-form');
            form.action = `/admin/products/image/${imageId}`;
            form.submit();
        }
    }
</script>
@endsection
