<section id="best-seller" class="py-16 md:py-24 bg-white overflow-hidden relative">

    <div
        class="absolute top-0 left-0 w-full h-full flex justify-center pt-8 md:pt-10 select-none pointer-events-none overflow-hidden z-0">
        <span
            class="font-mayluxa text-[5rem] md:text-[10rem] lg:text-[12rem] leading-none text-brand-black opacity-[0.02] tracking-widest whitespace-nowrap transform -translate-y-1/2 md:translate-y-0">
            HIGHLIGHTS
        </span>
    </div>

    <div class="container mx-auto px-6 relative z-10">

        <div class="flex flex-col md:flex-row justify-between items-end mb-12 md:mb-16" data-reveal>
            <div class="w-full md:w-auto text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-3 mb-3">
                    <span class="h-px w-8 bg-brand-gold md:hidden"></span>
                        <span class="text-brand-gold text-[10px] md:text-xs font-bold uppercase tracking-[0.25em]">
                            Pilihan Eksklusif
                        </span>
                    <span class="h-px w-8 bg-brand-gold md:hidden"></span>
                </div>

                <h2 class="font-mayluxa text-4xl md:text-6xl text-brand-black leading-tight">
                    Produk Terlaris
                </h2>
            </div>

            <div class="hidden md:flex gap-4">
                <button type="button" aria-label="Geser produk ke kiri"
                    onclick="document.getElementById('scroller').scrollBy({left: -350, behavior: 'smooth'})"
                    class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:border-brand-black hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5 group-hover:-translate-x-0.5 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button type="button" aria-label="Geser produk ke kanan"
                    onclick="document.getElementById('scroller').scrollBy({left: 350, behavior: 'smooth'})"
                    class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:border-brand-black hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="scroller"
            class="flex overflow-x-auto gap-6 md:gap-8 pb-12 snap-x snap-mandatory scrollbar-hide -mx-6 px-6 md:mx-0 md:px-0 scroll-smooth">
            @foreach ($bestSellers as $product)
                <div class="flex-none w-[260px] md:w-[350px] snap-center group cursor-pointer" data-reveal data-reveal-delay="{{ $loop->index * 80 }}">
                    <div
                        class="relative aspect-[4/5] bg-[#F9F9F9] mb-6 overflow-hidden rounded-sm transition-all duration-500 hover:shadow-xl">
                        <img src="{{ $product->primaryImage?->image_url ?? 'https://placehold.co/400x500/F5F5F5/333?text=' . urlencode($product->brand->name) }}"
                            alt="{{ $product->name }}" loading="lazy" decoding="async"
                            class="w-full h-full object-cover object-center group-hover:scale-110 transition-transform duration-1000 ease-out mix-blend-multiply relative z-0">

                        @if ($product->is_best_seller)
                            <div class="absolute top-4 left-4 z-20"><span
                                    class="px-3 py-1 bg-white/90 backdrop-blur-sm text-[10px] font-bold uppercase tracking-widest text-brand-black">Terlaris</span></div>
                        @endif

                        <div
                            class="absolute bottom-0 left-0 w-full p-6 translate-y-full group-hover:translate-y-0 transition-transform duration-500 z-20 hidden md:block">
                            <a href="{{ route('products.show', $product->slug) }}"
                                class="block w-full py-3 bg-white text-brand-black text-center text-xs uppercase tracking-widest hover:bg-brand-black hover:text-white transition-colors">
                                Lihat Produk
                            </a>
                        </div>
                    </div>

                    <div class="text-center px-4 group-hover:-translate-y-1 transition-transform duration-500">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-[0.2em] mb-2">
                            {{ $product->brand->name }}</p>
                        <h3 class="font-mayluxa text-xl md:text-2xl text-brand-black leading-tight">
                            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                        </h3>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 md:mt-12 text-center" data-reveal data-reveal-delay="160">
            <a href="{{ route('products.index') }}"
                class="inline-flex items-center gap-3 px-10 py-4 bg-brand-black text-white text-xs font-bold uppercase tracking-[0.2em] hover:bg-brand-gold hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-xl">
                <span>Lihat Katalog Lengkap</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>

    </div>
</section>
