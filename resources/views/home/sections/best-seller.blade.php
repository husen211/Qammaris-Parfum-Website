<section id="best-seller" aria-labelledby="best-seller-heading" data-home-best-sellers class="py-16 md:py-24 bg-white overflow-hidden relative">

    <div
        class="absolute top-0 left-0 w-full h-full flex justify-center pt-8 md:pt-10 select-none pointer-events-none overflow-hidden z-0">
        <span
            class="font-mayluxa text-[5rem] md:text-[10rem] lg:text-[12rem] leading-none text-brand-black opacity-[0.02] tracking-widest whitespace-nowrap transform -translate-y-1/2 md:translate-y-0">
            HIGHLIGHTS
        </span>
    </div>

    <div class="container mx-auto px-6 relative z-10">

        <div class="flex flex-col md:flex-row justify-between items-end mb-12 md:mb-16">
            <div class="w-full md:w-auto text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-3 mb-3">
                    <span class="h-px w-8 bg-brand-gold md:hidden"></span>
                        <span class="text-brand-gold text-[10px] md:text-xs font-bold uppercase tracking-[0.25em]">
                            Pilihan Eksklusif
                        </span>
                    <span class="h-px w-8 bg-brand-gold md:hidden"></span>
                </div>

                <h2 id="best-seller-heading" class="font-mayluxa text-4xl md:text-6xl text-brand-black leading-tight">
                    Produk Terlaris
                </h2>
            </div>

            @if ($bestSellers->isNotEmpty())
            <div class="hidden md:flex gap-4">
                <button type="button" aria-label="Geser produk ke kiri" aria-controls="scroller" data-best-seller-previous disabled
                    class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:border-brand-black hover:text-white disabled:opacity-40 disabled:pointer-events-none focus-visible:outline-2 focus-visible:outline-offset-4 transition-colors duration-300 group">
                    <svg class="w-5 h-5 transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button type="button" aria-label="Geser produk ke kanan" aria-controls="scroller" data-best-seller-next disabled
                    class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:border-brand-black hover:text-white disabled:opacity-40 disabled:pointer-events-none focus-visible:outline-2 focus-visible:outline-offset-4 transition-colors duration-300 group">
                    <svg class="w-5 h-5 transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            @endif
        </div>

        @if ($bestSellers->isNotEmpty())
        <div id="scroller" data-best-seller-rail role="group" aria-label="Pilihan produk terlaris" tabindex="0"
            class="flex overflow-x-auto gap-5 md:gap-8 pb-6 snap-x snap-mandatory scroll-px-6 md:scroll-px-0 scrollbar-hide -mx-6 px-6 md:mx-0 md:px-0 focus-visible:outline-2 focus-visible:outline-offset-4">
            @foreach ($bestSellers as $product)
                <div class="flex-none w-[240px] md:w-[280px] snap-start">
                    @include('products._catalog-card', [
                        'product' => $product,
                        'detailUrl' => route('products.show', $product->slug),
                        'prioritizeImage' => false,
                        'headingLevel' => 3,
                    ])
                </div>
            @endforeach
        </div>
        @else
            <p class="text-center text-sm text-gray-500">Pilihan produk terlaris sedang disiapkan. Jelajahi koleksi kami di katalog.</p>
        @endif

        <div class="mt-8 md:mt-12 text-center">
            <a href="{{ route('products.index') }}"
                class="inline-flex items-center gap-3 px-10 py-4 bg-brand-black text-white text-xs font-bold uppercase tracking-[0.2em] hover:bg-brand-gold transition-colors duration-300 shadow-lg hover:shadow-xl">
                <span>Lihat Katalog Lengkap</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>

    </div>
</section>
