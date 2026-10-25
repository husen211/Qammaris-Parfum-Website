{{-- UX-02: every brand on sale. A paused carousel (brand-carousel.js) moves one brand at a time; without JS the row scrolls. --}}
@if ($distributorBrands->isNotEmpty())
<section aria-labelledby="brands-heading" class="bg-white border-b border-gray-100 py-8 md:py-10">
    <div class="container mx-auto px-6">
        <h2 id="brands-heading" class="text-center text-[10px] md:text-xs text-gray-400 uppercase tracking-[0.2em] mb-6 font-normal" data-reveal>
            Brand pilihan di Qammaris
        </h2>

        <div class="brand-carousel max-w-5xl mx-auto" data-brand-carousel data-reveal data-reveal-delay="120">
            <ul class="brand-carousel__track" data-brand-track aria-label="Brand yang dijual di Qammaris">
                @foreach ($distributorBrands as $brand)
                    <li class="brand-carousel__item">
                        @if ($brand->logo_mark)
                            <img src="{{ $brand->logo_mark['src'] }}" alt="{{ $brand->name }}" width="{{ $brand->logo_mark['width'] }}"
                                height="{{ $brand->logo_mark['height'] }}" loading="lazy" decoding="async" class="brand-carousel__logo">
                        @else
                            <span class="brand-carousel__name font-mayluxa">{{ $brand->name }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
@endif
