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
                    <span class="ml-1 text-sm font-medium text-gray-800 md:ml-2">Add New</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Add New Product</h1>
        <a href="{{ route('admin.products.index') }}" class="text-sm font-medium text-gray-500 hover:text-black transition-colors">
            &larr; Cancel & Back
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

    <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 pb-2 border-b border-gray-100">Basic Information</h3>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('name') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="e.g. Afnan 9 PM" required @error('name') aria-describedby="name-error" aria-invalid="true" @enderror>
                            @error('name')
                            <p id="name-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Brand <span class="text-red-500">*</span></label>
                                <select name="brand_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('brand_id') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" @error('brand_id') aria-describedby="brand-error" aria-invalid="true" @enderror>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
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
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                <p id="category-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="5" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('description') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="Short story about the scent profile, longevity, and sillage." required @error('description') aria-describedby="description-error" aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                            @error('description')
                            <p id="description-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Compare At Price (Rp) <span class="text-gray-400 text-xs">(Optional)</span></label>
                            <input type="number" name="compare_at_price" value="{{ old('compare_at_price') }}" min="0" step="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm @error('compare_at_price') border-red-500 focus:ring-red-500 focus:border-red-500 @enderror" placeholder="750000" @error('compare_at_price') aria-describedby="compare-at-price-error" aria-invalid="true" @enderror>
                            <p class="mt-1 text-xs text-gray-400">Kosongkan jika tidak ingin harga coret.</p>
                            @error('compare_at_price')
                            <p id="compare-at-price-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <select name="gender" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm">
                                    <option value="Unisex" {{ old('gender') == 'Unisex' ? 'selected' : '' }}>Unisex</option>
                                    <option value="Pria" {{ old('gender') == 'Pria' ? 'selected' : '' }}>Pria</option>
                                    <option value="Wanita" {{ old('gender') == 'Wanita' ? 'selected' : '' }}>Wanita</option>
                                </select>
                            </div>
                            <div class="pb-3">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_best_seller" value="1" class="rounded border-gray-300 text-black shadow-sm focus:border-black focus:ring focus:ring-black focus:ring-opacity-50" {{ old('is_best_seller') ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-700">Tandai sebagai Terlaris</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 pb-2 border-b border-gray-100">Fragrance Notes</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Top Notes</label>
                            <input type="text" name="top_notes" value="{{ old('top_notes') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm" placeholder="Citrus, Bergamot, Apple">
                            <p class="mt-1 text-xs text-gray-400">Use commas to separate notes.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Middle Notes (Heart)</label>
                            <input type="text" name="middle_notes" value="{{ old('middle_notes') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm" placeholder="Rose, Jasmine, Saffron">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Base Notes</label>
                            <input type="text" name="base_notes" value="{{ old('base_notes') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-black focus:border-black sm:text-sm" placeholder="Vanilla, Musk, Oud, Amber">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center mb-5 pb-2 border-b border-gray-100">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Variants & Pricing</h3>
                            <p class="text-xs text-gray-500 mt-1">Add at least one size and price.</p>
                        </div>
                        <button type="button" onclick="addVariant()" class="text-xs bg-black text-white hover:bg-gray-800 px-3 py-1.5 rounded transition shadow-sm">+ Add Variant</button>
                    </div>
                    
                    <div id="variants-container" class="space-y-3">
                        {{-- Default first variant --}}
                        <div class="variant-row grid grid-cols-1 sm:grid-cols-7 gap-3 items-end bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Volume (ml)</label>
                                <input type="number" name="variants[0][volume]" min="1" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" placeholder="100" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Price (Rp)</label>
                                <input type="number" name="variants[0][price]" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" placeholder="500000" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Stock</label>
                                <input type="number" name="variants[0][stock]" min="0" step="1" class="w-full border-gray-300 rounded shadow-sm text-sm focus:ring-black focus:border-black" placeholder="10" required>
                            </div>
                            <div class="sm:col-span-1"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">Images</h3>
                    
                    <label for="images" class="block text-sm font-medium text-gray-700 mb-2">Images <span class="text-red-500">*</span></label>
                    <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:bg-gray-50 transition-colors group focus-within:ring-2 focus-within:ring-black">
                        <input id="images" name="images[]" type="file" multiple accept="image/*" required
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            onchange="previewImages(this)" @error('images') aria-describedby="images-error" aria-invalid="true" @enderror>
                            
                        <div class="space-y-3">
                            <div class="mx-auto h-12 w-12 text-gray-400 group-hover:text-black transition-colors">
                                <svg stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div class="text-sm text-gray-600">
                                <span class="font-bold text-black hover:underline">Click to Upload</span>
                            </div>
                            <p class="text-xs text-gray-400">PNG, JPG up to 2MB</p>
                        </div>
                    </div>
                    @error('images')
                    <p id="images-error" class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <div id="image-preview-container" class="grid grid-cols-3 gap-2 mt-4 hidden"></div>
                </div>

                <div class="sticky top-6">
                    <button type="submit" class="w-full bg-black text-white text-lg font-bold uppercase tracking-widest py-4 rounded-xl shadow-xl hover:bg-gray-800 transform hover:-translate-y-1 transition-all duration-200 flex justify-center items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Save Product
                    </button>
                    <p class="text-xs text-center text-gray-500 mt-3">Double check all details before saving.</p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let variantIndex = 1;

    function addVariant() {
        const container = document.getElementById('variants-container');
        const html = `
            <div class="variant-row grid grid-cols-1 sm:grid-cols-7 gap-3 items-end bg-gray-50 p-4 rounded-lg border border-gray-200 animate-fade-in-up relative" id="variant-${variantIndex}">
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
                    <button type="button" onclick="removeVariant(${variantIndex})" class="text-red-400 hover:text-red-600 p-2 rounded hover:bg-red-50 transition focus:outline-none focus:ring-2 focus:ring-red-300" aria-label="Remove variant" title="Remove variant">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        variantIndex++;
    }

    function removeVariant(index) {
        const row = document.getElementById(`variant-${index}`);
        if (row) {
            row.remove();
        }
    }

    function previewImages(input) {
        const container = document.getElementById('image-preview-container');
        container.innerHTML = '';
        
        if (input.files) {
            container.classList.remove('hidden');
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square rounded-lg overflow-hidden border border-gray-200';
                    div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                    container.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        } else {
            container.classList.add('hidden');
        }
    }
</script>
@endsection
