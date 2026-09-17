@extends('layouts.app')

@php
    $brandName = $product->brand?->name ?? 'Brand belum diisi';
    $catalogUrl = route('products.index', $catalogState->query());
    $detailContext = $catalogState->query();
    $offer = $product->activeOffer;
    $displayPrice = $offer?->price;
    $descriptionText = trim((string) $product->description);
    $effectiveAvailability = $product->effective_availability;
    $availability = match ($effectiveAvailability) {
        \App\Models\Product::AVAILABILITY_AVAILABLE => [
            'label' => 'Tersedia saat diperiksa',
            'detail' => 'Konfirmasi kembali sebelum berkunjung atau memesan.',
            'dot' => 'bg-emerald-600',
            'text' => 'text-emerald-800',
        ],
        \App\Models\Product::AVAILABILITY_SOLD_OUT => [
            'label' => 'Sold out',
            'detail' => 'Produk tetap dapat ditanyakan untuk informasi restock.',
            'dot' => 'bg-gray-400',
            'text' => 'text-gray-600',
        ],
        default => [
            'label' => 'Konfirmasi stok',
            'detail' => 'Website tidak menampilkan stok secara real-time.',
            'dot' => 'bg-amber-500',
            'text' => 'text-amber-800',
        ],
    };
    $availabilityCheckedLabel = $product->availability_checked_at
        ? $product->availability_checked_at->locale('id')->diffForHumans()
        : null;
    $galleryImages = $product->images
        ->sortBy([
            ['is_primary', 'desc'],
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])
        ->values();
    $primaryImage = $product->primaryImage ?? $galleryImages->first();
    $mainImageUrl = $primaryImage?->image_url ?? asset('images/product-placeholder.svg');
    $hasProductImage = $primaryImage !== null;
    $notes = collect([
        'Top notes' => $product->fragrance_notes['top'] ?? [],
        'Middle notes' => $product->fragrance_notes['middle'] ?? [],
        'Base notes' => $product->fragrance_notes['base'] ?? [],
    ])->filter(fn ($values) => is_array($values) && count($values) > 0);
    $productJsonLd = [
        chr(64).'context' => 'https://schema.org',
        chr(64).'type' => 'Product',
        'name' => $product->name,
        'description' => Str::limit($descriptionText, 200),
        'image' => $mainImageUrl,
        'brand' => [
            chr(64).'type' => 'Brand',
            'name' => $brandName,
        ],
    ];

    if ($displayPrice !== null) {
        $productJsonLd['offers'] = [
            chr(64).'type' => 'Offer',
            'priceCurrency' => 'IDR',
            'price' => $displayPrice,
            'url' => route('products.show', $product->slug),
        ];
    }

    $productJsonLdFlags = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR;
@endphp

@section('title', $product->name.' - '.$brandName)
@section('meta_description', Str::limit($descriptionText, 160))
@section('og_type', 'product')
@section('og_image', $mainImageUrl)

@section('content')
    <section class="min-h-screen bg-white pb-16 pt-20 md:pb-20 md:pt-24" aria-labelledby="product-title">
        <div class="container mx-auto px-4 lg:px-12">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 md:mb-7">
                <a href="{{ $catalogUrl }}"
                    class="inline-flex min-h-11 items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-brand-black underline decoration-gray-300 underline-offset-4 transition-colors hover:text-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                    <span aria-hidden="true">←</span>
                    Kembali ke hasil
                </a>

                <nav aria-label="Breadcrumb" class="hidden min-w-0 items-center gap-2 text-[10px] uppercase tracking-widest text-gray-400 md:flex">
                    <a href="{{ route('home') }}" class="hover:text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Beranda</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ $catalogUrl }}" class="hover:text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Katalog</a>
                    <span aria-hidden="true">/</span>
                    <span class="max-w-72 truncate font-semibold text-brand-black" aria-current="page">{{ $product->name }}</span>
                </nav>
            </div>

            <div class="grid items-start gap-6 md:gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(24rem,0.95fr)] lg:gap-14 xl:gap-20">
                <section class="order-1 min-w-0" aria-label="Galeri {{ $product->name }}" data-product-gallery>
                    <div class="mx-auto w-full max-w-[15rem] lg:max-w-sm">
                        <div class="relative aspect-[4/5] overflow-hidden bg-white">
                            <img id="mainImage" src="{{ $mainImageUrl }}"
                                alt="{{ $hasProductImage ? $product->name : 'Foto '.$product->name.' sedang dilengkapi' }}"
                                width="720" height="900" fetchpriority="high" decoding="async"
                                class="h-full w-full object-contain object-center opacity-95 transition-opacity duration-300 motion-reduce:transition-none {{ $hasProductImage ? '' : 'p-8 lg:p-12' }} {{ $effectiveAvailability === \App\Models\Product::AVAILABILITY_SOLD_OUT ? 'grayscale-[25%]' : '' }}">

                            @if ($product->is_best_seller)
                                <span class="absolute left-0 top-0 bg-brand-black px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-white">Terlaris</span>
                            @endif

                            @unless ($hasProductImage)
                                <span class="absolute inset-x-3 bottom-3 bg-white/90 px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-600 backdrop-blur-sm">
                                    Foto sedang dilengkapi
                                </span>
                            @endunless
                        </div>

                        @if ($galleryImages->count() > 1)
                            <div class="mt-3 flex justify-center gap-3 overflow-x-auto pb-2 lg:justify-start" aria-label="Pilihan foto produk">
                                @foreach ($galleryImages as $image)
                                    <button type="button" data-gallery-thumbnail
                                        data-gallery-src="{{ $image->image_url }}"
                                        data-gallery-alt="{{ $product->name }} — foto {{ $loop->iteration }}"
                                        aria-label="Tampilkan foto {{ $loop->iteration }} dari {{ $product->name }}"
                                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                        class="h-14 w-14 shrink-0 overflow-hidden border bg-white p-1 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black md:h-16 md:w-16 {{ $loop->first ? 'border-brand-black' : 'border-gray-200 hover:border-gray-500' }}">
                                        <img src="{{ $image->image_url }}" alt="" width="64" height="64" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                <section class="order-2 min-w-0 lg:sticky lg:top-28" aria-labelledby="product-title">
                    @if ($product->brand_id)
                        <a href="{{ route('products.index', ['brand' => [$product->brand_id]]) }}"
                            class="inline-flex min-h-11 items-center text-[11px] font-bold uppercase tracking-[0.2em] text-brand-emerald hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                            {{ $brandName }}
                        </a>
                    @else
                        <p class="flex min-h-11 items-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-400">{{ $brandName }}</p>
                    @endif

                    <h1 id="product-title" class="font-mayluxa text-3xl leading-tight text-brand-black sm:text-4xl lg:text-5xl">
                        {{ $product->name }}
                    </h1>

                    @if ($offer)
                        <div class="mt-5 flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-gray-200 pb-5 md:mt-6 md:pb-6">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400">Ukuran</p>
                                <p class="mt-1 text-base font-medium text-brand-black">{{ $offer->volume }} ml</p>
                            </div>
                            <div class="text-right">
                                @if ($product->compare_at_price && $product->compare_at_price > $offer->price)
                                    <p class="text-xs text-gray-400 line-through">{{ format_rupiah($product->compare_at_price) }}</p>
                                @endif
                                <p class="mt-1 text-2xl font-semibold tabular-nums text-brand-black lg:text-3xl">{{ format_rupiah($offer->price) }}</p>
                            </div>
                        </div>
                    @else
                        <div class="mt-5 border-y border-gray-200 py-4 md:mt-6">
                            <p class="text-sm font-semibold text-brand-black">Data sedang dilengkapi</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Ukuran dan harga belum tersedia untuk ditampilkan.</p>
                        </div>
                    @endif

                    <div class="flex items-start gap-3 border-b border-gray-200 py-4" data-detail-availability="{{ $effectiveAvailability }}">
                        <span class="mt-2 h-2 w-2 shrink-0 rounded-full {{ $availability['dot'] }}" aria-hidden="true"></span>
                        <div>
                            <p class="text-sm font-semibold {{ $availability['text'] }}">{{ $availability['label'] }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-gray-500">
                                {{ $availability['detail'] }}
                                @if ($availabilityCheckedLabel && $effectiveAvailability !== \App\Models\Product::AVAILABILITY_UNKNOWN)
                                    <span class="block">Pemeriksaan terakhir {{ $availabilityCheckedLabel }}.</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 md:mt-6">
                        @if ($offer && $effectiveAvailability !== \App\Models\Product::AVAILABILITY_SOLD_OUT)
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <label for="quantity" class="block text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500">Jumlah</label>
                                    <div class="mt-2 flex h-11 w-fit items-center border border-gray-300">
                                        <button type="button" data-quantity-change="-1" aria-label="Kurangi jumlah" class="flex h-11 w-11 items-center justify-center text-lg text-gray-500 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-black">−</button>
                                        <input type="number" id="quantity" value="1" min="1" class="h-11 w-11 border-none p-0 text-center text-sm font-semibold focus:ring-0" readonly>
                                        <button type="button" data-quantity-change="1" aria-label="Tambah jumlah" class="flex h-11 w-11 items-center justify-center text-lg text-brand-black hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-black">+</button>
                                    </div>
                                </div>
                                <p class="max-w-44 text-right text-[10px] leading-4 text-gray-400">Ketersediaan tetap perlu dikonfirmasi.</p>
                            </div>

                            <button type="button" data-add-to-cart data-variant-id="{{ $offer->id }}" data-max-quantity="{{ max(1, $offer->stock) }}"
                                class="flex min-h-14 w-full items-center justify-center gap-3 bg-brand-black px-5 text-xs font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:cursor-not-allowed disabled:bg-gray-300">
                                Tambah ke keranjang
                            </button>
                        @elseif ($effectiveAvailability === \App\Models\Product::AVAILABILITY_SOLD_OUT)
                            <button type="button" disabled class="flex min-h-14 w-full cursor-not-allowed items-center justify-center bg-gray-200 px-5 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                                Produk sold out
                            </button>
                        @else
                            <button type="button" disabled class="flex min-h-14 w-full cursor-not-allowed items-center justify-center bg-gray-200 px-5 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                                Data produk belum lengkap
                            </button>
                        @endif

                        <a href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer"
                            class="flex min-h-14 w-full items-center justify-center gap-3 border border-brand-black bg-white px-5 text-xs font-semibold uppercase tracking-[0.16em] text-brand-black transition-colors hover:bg-brand-black hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                            Pesan via WhatsApp
                        </a>
                        <p data-cart-feedback role="status" aria-live="polite" class="min-h-5 text-xs text-gray-500"></p>
                    </div>
                </section>
            </div>

            <div class="mt-10 grid gap-8 border-t border-gray-200 pt-8 md:mt-14 md:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)] md:gap-12 md:pt-10">
                <section aria-labelledby="description-title">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-gold">Tentang parfum</p>
                    <h2 id="description-title" class="mt-2 font-mayluxa text-2xl text-brand-black md:text-3xl">Deskripsi</h2>
                    @if ($descriptionText !== '')
                        <p class="mt-4 max-w-3xl whitespace-pre-line text-sm font-light leading-7 text-gray-600 md:text-base">{{ $descriptionText }}</p>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Deskripsi sedang dilengkapi.</p>
                    @endif
                </section>

                <section aria-labelledby="notes-title">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-gold">Karakter aroma</p>
                    <h2 id="notes-title" class="mt-2 font-mayluxa text-2xl text-brand-black md:text-3xl">Fragrance notes</h2>
                    @if ($notes->isNotEmpty())
                        <dl class="mt-4 divide-y divide-gray-200 border-y border-gray-200">
                            @foreach ($notes as $label => $values)
                                <div class="grid grid-cols-[7rem_1fr] gap-3 py-3 text-sm">
                                    <dt class="font-semibold text-brand-black">{{ $label }}</dt>
                                    <dd class="leading-6 text-gray-600">{{ implode(', ', $values) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Informasi notes sedang dilengkapi.</p>
                    @endif
                </section>
            </div>
        </div>
    </section>

    @if ($relatedProducts->count() > 0)
        <section class="border-t border-gray-200 bg-[#F9F7F2] py-14 md:py-20" aria-labelledby="related-title">
            <div class="container mx-auto px-4 lg:px-12">
                <div class="mb-8 flex items-end justify-between gap-4 md:mb-10">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-gold">Dari brand yang sama</p>
                        <h2 id="related-title" class="mt-2 font-mayluxa text-3xl text-brand-black">Produk terkait</h2>
                    </div>
                    @if ($product->brand_id)
                        <a href="{{ route('products.index', ['brand' => [$product->brand_id]]) }}" class="hidden min-h-11 items-center text-xs font-semibold uppercase tracking-[0.16em] underline underline-offset-4 hover:text-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black sm:inline-flex">Lihat semua</a>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-4 md:grid-cols-4 md:gap-6">
                    @foreach ($relatedProducts as $related)
                        @include('products._card', [
                            'product' => $related,
                            'detailUrl' => route('products.show', array_merge(['product' => $related->slug], $detailContext)),
                            'prioritizeImage' => false,
                        ])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mainImage = document.getElementById('mainImage');
    const thumbnails = document.querySelectorAll('[data-gallery-thumbnail]');

    thumbnails.forEach((thumbnail) => {
        thumbnail.addEventListener('click', () => {
            if (!mainImage) return;

            mainImage.classList.add('opacity-40');
            mainImage.src = thumbnail.dataset.gallerySrc;
            mainImage.alt = thumbnail.dataset.galleryAlt;
            mainImage.onload = () => mainImage.classList.remove('opacity-40');

            thumbnails.forEach((item) => {
                const selected = item === thumbnail;
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
                item.classList.toggle('border-brand-black', selected);
                item.classList.toggle('border-gray-200', !selected);
            });
        });
    });

    const quantityInput = document.getElementById('quantity');
    const addButton = document.querySelector('[data-add-to-cart]');
    const feedback = document.querySelector('[data-cart-feedback]');
    const maxQuantity = Number.parseInt(addButton?.dataset.maxQuantity ?? '1', 10);

    document.querySelectorAll('[data-quantity-change]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!quantityInput) return;

            const change = Number.parseInt(button.dataset.quantityChange, 10);
            const current = Number.parseInt(quantityInput.value, 10);
            quantityInput.value = Math.min(Math.max(current + change, 1), Math.max(maxQuantity, 1));
        });
    });

    addButton?.addEventListener('click', async () => {
        const originalText = addButton.textContent;
        addButton.textContent = 'Menambahkan…';
        addButton.disabled = true;
        if (feedback) feedback.textContent = '';

        try {
            const response = await fetch('{{ route('cart.add') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    variant_id: Number.parseInt(addButton.dataset.variantId, 10),
                    quantity: Number.parseInt(quantityInput?.value ?? '1', 10),
                }),
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Produk belum dapat ditambahkan.');
            }

            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.classList.remove('hidden');
            }

            if (feedback) feedback.textContent = 'Produk ditambahkan ke keranjang.';
            document.getElementById('cartDrawer')?.showModal();
        } catch (error) {
            if (feedback) feedback.textContent = error.message || 'Produk belum dapat ditambahkan. Coba lagi.';
        } finally {
            addButton.textContent = originalText;
            addButton.disabled = false;
        }
    });
});
</script>
@endpush

@push('jsonld')
<script type="application/ld+json">
{!! json_encode($productJsonLd, $productJsonLdFlags) !!}
</script>
@endpush
