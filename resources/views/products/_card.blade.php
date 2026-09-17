@php
    $offer = $product->activeOffer;
    $hasImage = $product->primaryImage !== null;
    $imageUrl = $product->primaryImage?->image_url ?? asset('images/product-placeholder.svg');
    $effectiveAvailability = $product->effective_availability;
    $availability = match ($effectiveAvailability) {
        \App\Models\Product::AVAILABILITY_AVAILABLE => [
            'label' => 'Tersedia saat diperiksa',
            'dot' => 'bg-emerald-600',
            'text' => 'text-emerald-800',
        ],
        \App\Models\Product::AVAILABILITY_SOLD_OUT => [
            'label' => 'Sold out',
            'dot' => 'bg-gray-400',
            'text' => 'text-gray-500',
        ],
        default => [
            'label' => 'Konfirmasi stok',
            'dot' => 'bg-amber-500',
            'text' => 'text-amber-800',
        ],
    };
@endphp

<article class="group min-w-0" data-product-card data-effective-availability="{{ $effectiveAvailability }}">
    <a href="{{ $detailUrl }}"
        aria-label="Lihat detail {{ $product->name }}"
        class="flex h-full min-w-0 flex-col focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-black">
        <div class="relative mb-3 aspect-[3/4] overflow-hidden border border-gray-100 bg-[#F6F2EA] md:mb-4">
            <img src="{{ $imageUrl }}"
                alt="{{ $hasImage ? $product->name : 'Foto '.$product->name.' sedang dilengkapi' }}"
                width="480" height="640"
                @if ($prioritizeImage) fetchpriority="high" @else loading="lazy" @endif
                decoding="async"
                class="h-full w-full object-cover object-center opacity-95 transition duration-500 ease-out group-hover:scale-[1.025] group-hover:opacity-100 motion-reduce:transform-none {{ $effectiveAvailability === \App\Models\Product::AVAILABILITY_SOLD_OUT ? 'grayscale-[35%]' : '' }}">

            @if ($product->is_best_seller)
                <span class="absolute left-0 top-0 bg-brand-black px-2 py-1 text-[9px] font-bold uppercase tracking-widest text-white md:text-[10px]">Terlaris</span>
            @endif

            @unless ($hasImage)
                <span class="absolute inset-x-2 bottom-2 bg-white/90 px-2 py-1.5 text-center text-[9px] font-semibold uppercase tracking-[0.16em] text-gray-600 backdrop-blur-sm">
                    Foto sedang dilengkapi
                </span>
            @endunless
        </div>

        <div class="flex min-w-0 flex-1 flex-col border-b border-gray-200 pb-3 transition-colors group-hover:border-brand-gold md:pb-4">
            <p class="mb-1 truncate text-[9px] uppercase tracking-[0.18em] text-gray-500 md:text-[10px]">
                {{ $product->brand?->name ?? 'Brand belum diisi' }}
            </p>
            <h2 class="min-h-[2.5rem] text-sm font-semibold leading-5 text-brand-black line-clamp-2 transition-colors group-hover:text-brand-gold md:min-h-[3rem] md:text-base md:leading-6">
                {{ $product->name }}
            </h2>

            <div class="mt-2 min-h-[2.5rem] md:mt-3">
                @if ($offer)
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                        <span class="text-[10px] font-medium uppercase tracking-wider text-gray-500 md:text-xs">{{ $offer->volume }} ml</span>
                        <span class="text-sm font-semibold tabular-nums text-brand-black md:text-base">{{ format_rupiah($offer->price) }}</span>
                    </div>
                @else
                    <p class="text-xs font-medium text-gray-500">Data sedang dilengkapi</p>
                @endif
            </div>

            <p class="mt-2 flex items-start gap-2 text-[10px] font-medium leading-4 {{ $availability['text'] }} md:text-xs">
                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $availability['dot'] }}" aria-hidden="true"></span>
                <span>{{ $availability['label'] }}</span>
            </p>
        </div>
    </a>
</article>
