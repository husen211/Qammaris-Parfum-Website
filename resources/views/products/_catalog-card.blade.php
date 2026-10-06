@php
    $offer = $product->activeOffer;
    $imageUrl = $product->primaryImage?->image_url ?? asset('images/product-placeholder.svg');
    $hasImage = $product->primaryImage !== null && !str_ends_with($imageUrl, 'product-placeholder.svg');
    $availability = $product->effective_availability;
@endphp

<article class="catalog-card" data-product-card data-effective-availability="{{ $availability }}">
    <a href="{{ $detailUrl }}" class="catalog-card__link" aria-label="Lihat detail {{ $product->name }}">
        <div class="catalog-media" data-catalog-media>
            <img src="{{ $imageUrl }}"
                alt="{{ $hasImage ? $product->name : 'Foto '.$product->name.' sedang dilengkapi' }}"
                width="480" height="480"
                @if ($prioritizeImage) fetchpriority="high" @else loading="lazy" @endif
                decoding="async" class="catalog-media__image"
                @if ($hasImage) data-catalog-image @endif
                data-fallback-src="{{ asset('images/product-placeholder.svg') }}">
            <span class="catalog-media__fallback" data-image-fallback @if ($hasImage) hidden @endif>Foto sedang dilengkapi</span>
        </div>
        <div class="catalog-card__details">
            <div class="catalog-card__brand-row">
                <p class="catalog-card__brand">{{ $product->brand?->name ?? 'Brand belum diisi' }}</p>
                @if ($product->is_best_seller)
                    <span class="catalog-card__badge">Terlaris</span>
                @endif
            </div>
            <h2 class="catalog-card__name">{{ $product->name }}</h2>
            <div class="catalog-card__offer">
                @if ($offer)
                    <p class="catalog-card__size">{{ $offer->volume }} ml</p>
                    <p class="catalog-card__price">{{ format_rupiah($offer->price) }}</p>
                @else
                    <p class="catalog-card__size">Data sedang dilengkapi</p>
                @endif
            </div>
            <p class="catalog-card__availability" data-status="{{ $availability }}">
                <span class="catalog-card__dot" aria-hidden="true"></span>
                <span>{{ \App\Support\CatalogAvailability::label($product) }}</span>
            </p>
        </div>
    </a>
</article>
