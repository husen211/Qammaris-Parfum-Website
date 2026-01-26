<section class="py-24 bg-gradient-to-b from-white to-brand-cream">
    <div class="container mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            <div id="about-lanyard"
                class="relative w-full h-[480px] sm:h-[600px] md:h-[720px] lg:h-[780px] rounded-2xl overflow-hidden bg-white/80 border border-gray-200 shadow-[0_24px_60px_rgba(0,0,0,0.12)]"
                data-reveal
                data-fallback-image="{{ asset('images/about-section.jpg') }}"
                data-fallback-alt="Produk parfum Qammaris">
                <div class="absolute inset-0 flex items-center justify-center bg-white/70 text-[11px] font-semibold uppercase tracking-[0.3em] text-gray-400 animate-pulse"
                    data-lanyard-placeholder>
                    Loading 3D...
                </div>
                <noscript>
                    <figure class="w-full h-full">
                        <img src="{{ asset('images/about-section.jpg') }}" alt="Produk parfum Qammaris"
                            loading="lazy" decoding="async" class="w-full h-full object-cover" />
                    </figure>
                </noscript>
            </div>
            <!-- Content Side -->
            <div data-reveal data-reveal-delay="120">
                <span class="inline-block text-brand-emerald text-xs font-bold uppercase tracking-widest mb-4">
                    Tentang Qammaris
                </span>

                <h2 class="font-mayluxa text-4xl md:text-5xl text-brand-black mb-6 leading-tight">
                    Ruang tepercaya memilih parfum Middle East <br>
                    <span class="italic text-brand-gold">untuk warga Palu</span>
                </h2>

                <div class="space-y-4 text-gray-600 leading-relaxed mb-8">
                    <p>
                        <strong class="text-brand-black">Qammaris Perfumes</strong> adalah toko parfum Middle East di Palu,
                        Sulawesi Tengah. Kami hadir untuk memudahkan Anda memilih aroma yang tepat tanpa menebak dari layar.
                    </p>

                    <p>
                        Di toko, Anda bisa mencoba langsung sebelum memutuskan. Pendekatan ini membantu Anda lebih yakin
                        dengan pilihan yang akan dibawa pulang.
                    </p>
                </div>

                <!-- Key Features -->
                <div class="grid grid-cols-2 gap-6 mb-8">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-brand-gold flex-shrink-0 mt-1" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-brand-black text-sm mb-1">Keaslian Produk</h4>
                            <p class="text-xs text-gray-500">Produk dari sumber resmi dan jelas</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-brand-gold flex-shrink-0 mt-1" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-brand-black text-sm mb-1">Respons Cepat</h4>
                            <p class="text-xs text-gray-500">Siap bantu rekomendasi dan info</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-brand-gold flex-shrink-0 mt-1" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-brand-black text-sm mb-1">Kurasi Koleksi</h4>
                            <p class="text-xs text-gray-500">Pilihan aroma disusun rapi</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-brand-gold flex-shrink-0 mt-1" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-brand-black text-sm mb-1">Coba Langsung</h4>
                            <p class="text-xs text-gray-500">Datang dan sniff sebelum membeli</p>
                        </div>
                    </div>
                </div>

                @if ($showCta ?? true)
                    <a href="{{ route('store.about') }}"
                        class="inline-block px-8 py-4 bg-brand-black text-white font-medium uppercase tracking-widest text-sm hover:bg-brand-gold transition-all duration-300">
                        Tentang Qammaris
                    </a>
                @endif
            </div>

        </div>
    </div>
</section>

@push('scripts')
    @vite('resources/js/reactbits/about-lanyard-loader.js')
@endpush
