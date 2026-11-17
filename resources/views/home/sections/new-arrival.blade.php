{{-- HOME-NEW-ARRIVAL (Owner 2026-11-17): the newest first publications; no price in this section. Without JS the stage
     shows product 01 and every row is a plain product link. --}}
@if ($newArrivals->isNotEmpty())
@php
    $placeholder = asset('images/product-placeholder.svg');
    $arrivals = $newArrivals->values()->map(fn ($product, $index) => [
        'product' => $product,
        'url' => route('products.show', $product->slug),
        'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
        'label' => 'Baru · '.$product->published_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M'),
        'brand' => $product->brand?->name ?? '',
        'size' => $product->activeOffer->volume.' ml',
        'image' => $product->primaryImage->image_url,
    ]);
    $first = $arrivals->first();
@endphp
<section id="new-arrival" aria-labelledby="new-arrival-heading" data-new-arrival class="bg-brand-cream py-16 md:py-24">
    <div class="container mx-auto px-6">
        <div class="mb-10 flex flex-col gap-5 md:mb-14 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.25em] text-brand-gold md:text-xs">New Arrival</p>
                <h2 id="new-arrival-heading" class="font-mayluxa text-4xl leading-tight text-brand-black md:text-6xl">Baru Tiba</h2>
            </div>
            <a href="{{ route('products.index', ['sort' => 'latest']) }}" data-new-arrival-all
                class="inline-flex items-center gap-2 self-start border-b border-brand-black/30 pb-1 text-xs font-bold uppercase tracking-[0.2em] text-brand-black transition-colors duration-300 [touch-action:manipulation] hover:border-brand-black md:self-auto focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-black">
                <span>Lihat semua produk baru</span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
            </a>
        </div>

        <div class="grid gap-8 md:grid-cols-[minmax(0,11fr)_minmax(0,9fr)] md:items-start md:gap-12 lg:gap-16">
            <div class="relative min-w-0">
                <a href="{{ $first['url'] }}" data-new-arrival-stage
                    class="group block [touch-action:pan-y] [-webkit-tap-highlight-color:transparent] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-black">
                    <div class="relative h-[320px] overflow-hidden bg-white md:h-[360px]">
                        @foreach ($arrivals as $index => $arrival)
                            <img src="{{ $arrival['image'] }}" alt="{{ $arrival['product']->name }}" width="640" height="640"
                                loading="lazy" decoding="async" data-new-arrival-layer data-fallback-src="{{ $placeholder }}"
                                @if ($index > 0) aria-hidden="true" @endif
                                class="absolute inset-0 h-full w-full object-contain px-12 pb-5 pt-[4.75rem] transition-opacity md:px-14 md:py-8 duration-[900ms] ease-out motion-reduce:transition-none {{ $index === 0 ? 'opacity-100' : 'opacity-0' }}">
                        @endforeach
                        <span data-new-arrival-number aria-hidden="true"
                            class="pointer-events-none absolute left-4 top-2 select-none font-mayluxa text-6xl leading-none text-transparent [-webkit-text-stroke:1px_var(--color-brand-gold)] md:left-6 md:text-8xl">{{ $first['number'] }}</span>
                        <span data-new-arrival-label class="absolute right-4 top-4 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-emerald md:right-6 md:top-6">{{ $first['label'] }}</span>
                    </div>
                    <div class="border-t border-brand-black/10 bg-white px-5 py-5 md:px-6">
                        <p data-new-arrival-brand class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-600">{{ $first['brand'] }}</p>
                        <h3 data-new-arrival-name class="mt-2 font-mayluxa text-2xl leading-tight text-brand-black md:text-3xl">{{ $first['product']->name }}</h3>
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <p data-new-arrival-size class="text-sm text-gray-600">{{ $first['size'] }}</p>
                            <span class="text-xs font-bold uppercase tracking-[0.2em] text-brand-emerald group-hover:underline group-hover:underline-offset-4">Lihat produk <span aria-hidden="true">→</span></span>
                        </div>
                    </div>
                </a>
                <button type="button" data-new-arrival-toggle hidden aria-label="Jeda pergantian produk"
                    class="absolute right-3 top-[calc(320px-3rem)] flex h-9 w-9 items-center justify-center rounded-full border border-brand-black/15 bg-white text-brand-black transition-colors duration-300 [touch-action:manipulation] hover:border-brand-black md:top-[calc(360px-3rem)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                    <svg data-new-arrival-pause-icon class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5h3v14H7zM14 5h3v14h-3z" /></svg>
                    <svg data-new-arrival-play-icon class="hidden h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                </button>
            </div>

            <ol class="min-w-0 border-t border-brand-black/10" aria-label="Produk baru tiba">
                @foreach ($arrivals as $index => $arrival)
                    <li class="border-b border-brand-black/10">
                        <a href="{{ $arrival['url'] }}" data-new-arrival-item data-active="{{ $index === 0 ? 'true' : 'false' }}"
                            data-number="{{ $arrival['number'] }}" data-label="{{ $arrival['label'] }}" data-brand="{{ $arrival['brand'] }}"
                            data-name="{{ $arrival['product']->name }}" data-size="{{ $arrival['size'] }}"
                            class="group relative flex items-center gap-4 py-4 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] active:bg-white/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black md:py-5">
                            <span aria-hidden="true" class="w-7 flex-none font-mayluxa text-xl text-brand-gold">{{ $arrival['number'] }}</span>
                            <img src="{{ $arrival['image'] }}" alt="" width="56" height="56" loading="lazy" decoding="async" data-fallback-src="{{ $placeholder }}"
                                class="h-14 w-14 flex-none bg-white object-contain p-1">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 group-data-[active=true]:text-gray-700">{{ $arrival['brand'] }}</span>
                                <span class="mt-1 block truncate text-sm font-medium text-gray-700 transition-colors duration-300 group-hover:text-brand-black group-data-[active=true]:text-brand-emerald md:text-base">{{ $arrival['product']->name }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 group-data-[active=true]:text-gray-700">{{ $arrival['size'] }}</span>
                            </span>
                            <span data-new-arrival-progress aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-0.5 w-full origin-left scale-x-0 bg-brand-gold"></span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
@endif
