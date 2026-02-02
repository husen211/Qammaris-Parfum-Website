@extends('layouts.app')

@section('title', $product->name . ' - ' . $product->brand->name)
@section('meta_description', Str::limit($product->description, 160))

@section('content')

<section class="pt-24 pb-20 bg-white min-h-screen">
    <div class="container mx-auto px-4 lg:px-12">
        
        <nav class="text-[10px] md:text-xs uppercase tracking-widest text-gray-400 mb-8 flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="hover:text-brand-black transition-colors">Beranda</a>
            <span>/</span>
            <a href="{{ route('products.index') }}" class="hover:text-brand-black transition-colors">Katalog</a>
            <span>/</span>
            <a href="{{ route('products.index', ['brand' => $product->brand_id]) }}" class="hover:text-brand-black transition-colors">{{ $product->brand->name }}</a>
            <span>/</span>
            <span class="text-brand-black font-bold border-b border-brand-black pb-0.5">{{ $product->name }}</span>
        </nav>

        <div class="grid lg:grid-cols-2 gap-10 lg:gap-20">
            
            <div class="flex flex-col-reverse lg:flex-row gap-4">
                @if($product->images->count() > 1)
                <div class="flex lg:flex-col gap-3 overflow-x-auto lg:overflow-visible hide-scrollbar w-full lg:w-20 shrink-0 pb-2 lg:pb-0">
                    {{-- Primary thumbnail (first) --}}
                    <button onclick="changeMainImage('{{ $product->primaryImage?->image_url ?? $product->primaryImageSafe?->image_url }}')" class="w-16 h-16 lg:w-full lg:h-20 aspect-square border border-gray-200 hover:border-brand-black p-1 transition-all shrink-0 bg-white">
                        <img src="{{ $product->primaryImage?->image_url ?? ($product->primaryImageSafe?->image_url ?? 'https://placehold.co/400x500/F5F5F5/333?text=' . urlencode($product->brand->name)) }}" class="w-full h-full object-cover" alt="{{ $product->name }} thumbnail">
                    </button>

                    @foreach($product->images as $image)
                    <button onclick="changeMainImage('{{ $image->image_url }}')" class="w-16 h-16 lg:w-full lg:h-20 aspect-square border border-gray-200 hover:border-brand-black p-1 transition-all shrink-0 bg-white">
                        <img src="{{ $image->image_url ?? 'https://placehold.co/200x200/F5F5F5/333?text=No+Image' }}" class="w-full h-full object-cover" alt="{{ $product->name }} thumbnail">
                    </button>
                    @endforeach
                </div>
                @endif

                <div class="flex-1 bg-[#F9F9F9] relative aspect-4/5 lg:aspect-square overflow-hidden w-full">
                    <img id="mainImage" 
     src="{{ $product->primaryImage?->image_url ?? 'https://placehold.co/600x800/F5F5F5/333?text=' . urlencode($product->name) }}" 
     class="w-full h-full object-cover mix-blend-multiply transition-opacity duration-300" 
     alt="{{ $product->name }}">
                    
                    @if($product->is_best_seller)
                    <span class="absolute top-4 left-4 bg-brand-black text-white text-[10px] font-bold px-3 py-1 uppercase tracking-widest z-10">
                        Terlaris
                    </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-col h-full pt-2">
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <a href="{{ route('products.index', ['brand' => $product->brand_id]) }}" class="text-xs font-bold text-brand-emerald uppercase tracking-[0.2em] hover:underline">
                            {{ $product->brand->name }}
                        </a>
                    </div>
                    
                    <h1 class="font-mayluxa text-3xl lg:text-5xl text-brand-black mb-4 leading-tight">
                        {{ $product->name }}
                    </h1>
                    
                    <div class="flex items-end gap-3">
                        @if ($product->compare_at_price && $product->compare_at_price > $product->variants->min('price'))
                            <div class="text-xs md:text-sm text-gray-400 line-through" id="compareAtPrice" data-compare-at="{{ $product->compare_at_price }}">
                                {{ format_rupiah($product->compare_at_price) }}
                            </div>
                        @endif
                        <div class="text-2xl lg:text-3xl font-medium text-brand-black" id="displayPrice">
                            {{ format_rupiah($product->variants->first()->price) }}
                        </div>
                    </div>
                </div>

                <div class="w-full h-px bg-gray-100 my-6"></div>

                <div class="prose prose-sm max-w-none text-gray-600 font-light leading-relaxed mb-8 text-justify">
                    <p>{{ $product->description }}</p>
                </div>

                <div class="space-y-8 mb-8">
                    
                    <div>
                        <div class="flex justify-between mb-3">
                            <label class="text-xs font-bold uppercase tracking-widest text-brand-black">Select Size</label>
                            <span id="stockStatus" class="text-xs font-medium text-green-600">In Stock</span>
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            @foreach($product->variants as $variant)
                            <label class="cursor-pointer group relative">
                                <input type="radio" name="variant" value="{{ $variant->id }}" 
                                    data-price="{{ $variant->price }}"
                                    data-stock="{{ $variant->stock }}"
                                    class="peer sr-only variant-radio" 
                                    {{ $loop->first ? 'checked' : '' }}
                                    {{ $variant->stock <= 0 ? 'disabled' : '' }}>
                                
                                <div class="px-6 py-3 border border-gray-200 text-sm transition-all peer-checked:bg-brand-black peer-checked:text-white peer-checked:border-brand-black hover:border-brand-black 
                                            {{ $variant->stock <= 0 ? 'opacity-40 cursor-not-allowed bg-gray-50 decoration-dashed line-through' : '' }}">
                                    {{ $variant->volume }}ml
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest mb-3 text-brand-black">Quantity</label>
                        <div class="flex items-center border border-gray-200 w-fit h-12">
                            <button onclick="updateQty(-1)" class="w-12 h-full hover:bg-gray-50 text-gray-500 flex items-center justify-center text-lg">-</button>
                            <input type="number" id="quantity" value="1" min="1" class="w-12 text-center border-none focus:ring-0 p-0 text-sm font-medium appearance-none" readonly>
                            <button onclick="updateQty(1)" class="w-12 h-full hover:bg-gray-50 text-brand-black flex items-center justify-center text-lg">+</button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 mt-auto">
                    <button onclick="addToCart()" class="btn bg-brand-black text-white hover:bg-gray-800 w-full h-14 rounded-none text-sm uppercase tracking-widest flex items-center justify-center gap-3 transition-transform active:scale-[0.99]">
                        <span>Add to Cart</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </button>
                    
                    <a href="{{ $storeInfo->whatsapp_link }}" target="_blank" class="btn bg-white border border-brand-black text-brand-black hover:bg-brand-black hover:text-white w-full h-14 rounded-none text-sm uppercase tracking-widest flex items-center justify-center gap-3 transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        Order via WhatsApp
                    </a>
                </div>
                
                @if($product->fragrance_notes)
                <div class="mt-10 border-t border-gray-100">
                    <div class="collapse collapse-plus rounded-none border-b border-gray-100 group">
                        <input type="checkbox" /> 
                        <div class="collapse-title text-xs font-bold uppercase tracking-widest py-5 px-0 group-hover:text-brand-gold transition-colors">
                            Fragrance Notes
                        </div>
                        <div class="collapse-content px-0"> 
                            <div class="grid grid-cols-1 gap-3 text-sm font-light text-gray-600 pb-4">
                                @if(isset($product->fragrance_notes['top']))
                                <p><strong class="text-brand-black font-medium uppercase text-xs tracking-wider">Top:</strong> {{ implode(', ', $product->fragrance_notes['top']) }}</p>
                                @endif
                                @if(isset($product->fragrance_notes['middle']))
                                <p><strong class="text-brand-black font-medium uppercase text-xs tracking-wider">Heart:</strong> {{ implode(', ', $product->fragrance_notes['middle']) }}</p>
                                @endif
                                @if(isset($product->fragrance_notes['base']))
                                <p><strong class="text-brand-black font-medium uppercase text-xs tracking-wider">Base:</strong> {{ implode(', ', $product->fragrance_notes['base']) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</section>

@if($relatedProducts->count() > 0)
<section class="py-20 bg-[#F9F9F9] border-t border-gray-100">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="font-mayluxa text-3xl mb-2">You May Also Like</h2>
            <div class="w-10 h-0.5 bg-brand-black mx-auto"></div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-10 md:gap-8">
            @foreach($relatedProducts as $related)
            <div class="group flex flex-col">
                <div class="relative aspect-3/4 bg-white mb-4 overflow-hidden">
                    <a href="{{ route('products.show', $related->slug) }}" class="block w-full h-full">
                        <img src="{{ $related->primaryImage?->image_url ?? 'https://placehold.co/400x533/fff/333?text=No+Image' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 mix-blend-multiply" alt="{{ $related->name }}">
                    </a>
                </div>
                <div class="text-center">
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-1">{{ $related->brand->name }}</p>
                    <h3 class="font-mayluxa text-base md:text-lg mb-1 group-hover:text-brand-gold transition-colors">
                        <a href="{{ route('products.show', $related->slug) }}">{{ $related->name }}</a>
                    </h3>
                    @if ($related->compare_at_price && $related->compare_at_price > $related->cheapest_price)
                        <p class="text-[10px] text-gray-400 line-through">
                            {{ format_rupiah($related->compare_at_price) }}
                        </p>
                    @endif
                    <p class="text-sm font-medium">{{ format_rupiah($related->cheapest_price) }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection

@push('styles')
<style>
/* Hilangkan scrollbar tapi tetap bisa scroll */
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@endpush

@push('scripts')
<script>
/* ============================
   1) Change Main Image Logic
   ============================ */
function changeMainImage(url) {
    if (!url) return;
    const mainImg = document.getElementById('mainImage');
    if (!mainImg) return;

    mainImg.style.opacity = 0;
    setTimeout(() => {
        mainImg.src = url;
        mainImg.onload = () => {
            mainImg.style.opacity = 1;
            mainImg.onload = null;
        };
        // fallback: ensure it becomes visible even if onload fails
        setTimeout(() => { mainImg.style.opacity = 1; }, 1200);
    }, 120);
}

/* ============================
   2) Format Rupiah Helper
   ============================ */
function formatRupiah(amount) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
}

/* ============================
   3) Variant Selection Logic
   ============================ */
document.addEventListener('DOMContentLoaded', function () {
    const variantRadios = document.querySelectorAll('.variant-radio');
    const stockStatus = document.getElementById('stockStatus');
    const displayPrice = document.getElementById('displayPrice');
    const compareAtPrice = document.getElementById('compareAtPrice');

    function updateCompareAtVisibility(price) {
        if (!compareAtPrice) return;
        const compareValue = parseFloat(compareAtPrice.dataset.compareAt);
        const currentPrice = parseFloat(price);
        if (!compareValue || Number.isNaN(compareValue) || Number.isNaN(currentPrice)) {
            return;
        }

        if (compareValue > currentPrice) {
            compareAtPrice.classList.remove('hidden');
        } else {
            compareAtPrice.classList.add('hidden');
        }
    }

    if (variantRadios.length) {
        variantRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                const price = this.dataset.price;
                displayPrice.textContent = formatRupiah(price);
                updateCompareAtVisibility(price);

                const stock = parseInt(this.dataset.stock);
                if (stock > 0) {
                    stockStatus.textContent = 'In Stock';
                    stockStatus.className = 'text-xs font-bold uppercase tracking-widest text-green-600';
                    document.querySelector('button[onclick="addToCart()"]').disabled = false;
                } else {
                    stockStatus.textContent = 'Out of Stock';
                    stockStatus.className = 'text-xs font-bold uppercase tracking-widest text-red-600';
                    document.querySelector('button[onclick="addToCart()"]').disabled = true;
                }
                document.getElementById('quantity').value = 1;
            });
        });
    }

    const initialVariant = document.querySelector('.variant-radio:checked');
    if (initialVariant) {
        updateCompareAtVisibility(initialVariant.dataset.price);
    }
});

/* ============================
   4) Quantity Logic
   ============================ */
function updateQty(change) {
    const input = document.getElementById('quantity');
    const currentVariant = document.querySelector('.variant-radio:checked');
    const maxStock = currentVariant ? parseInt(currentVariant.dataset.stock) : 1;

    let newVal = parseInt(input.value) + change;
    if(newVal >= 1 && newVal <= maxStock) {
        input.value = newVal;
    }
}

/* ============================
   5) Add to Cart AJAX Logic
   ============================ */
async function addToCart() {
    const selectedVariant = document.querySelector('.variant-radio:checked');

    if (!selectedVariant) {
        alert('Please select a size first.');
        return;
    }

    const variantId = selectedVariant.value;
    const quantity = parseInt(document.getElementById('quantity').value);
    const btn = document.querySelector('button[onclick="addToCart()"]');
    const originalText = btn.innerHTML;

    btn.innerHTML = 'Adding...';
    btn.disabled = true;

    try {
        const response = await fetch('{{ route("cart.add") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                variant_id: variantId,
                quantity: quantity
            })
        });

        const data = await response.json();

        if (data.success) {
            const cartBadge = document.getElementById('cartBadge');
            if(cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.classList.remove('hidden');
            }

            if(document.getElementById('cartDrawer')) {
                document.getElementById('cartDrawer').showModal();
            } else {
                alert('Added to cart!');
            }
        } else {
            alert('Failed: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Something went wrong. Please try again.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>
@endpush
