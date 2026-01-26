<section id="how-to-order" class="relative overflow-hidden py-24 bg-brand-cream border-y border-gray-200">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -top-24 right-0 h-56 w-56 bg-brand-gold/15 blur-3xl"></div>
        <div class="absolute -bottom-24 left-0 h-56 w-56 bg-white/60 blur-3xl"></div>
    </div>
    <div class="container relative mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto" data-reveal>
            <h2 class="font-mayluxa text-4xl md:text-5xl text-brand-black">
                How it <span class="text-brand-gold">goes</span>
            </h2>
            <div class="mx-auto mt-4 h-0.5 w-40 bg-brand-gold/60"></div>
            <p class="mt-6 text-sm text-brand-black/60">
                Tiga langkah simpel untuk menemukan aroma terbaik dan checkout dengan cepat.
            </p>
        </div>

        <div class="mt-12 grid gap-8 md:grid-cols-3">
            <div class="group flex flex-col border border-brand-black/10 bg-white/80 p-6 shadow-[0_18px_36px_rgba(0,0,0,0.08)] transition duration-300 hover:-translate-y-1 hover:border-brand-gold/60" data-reveal data-reveal-delay="0">
                <span class="text-sm font-semibold text-brand-gold">1.</span>
                <h3 class="font-mayluxa text-2xl text-brand-black mt-4">Discover</h3>
                <p class="text-sm text-brand-black/60 mt-3">
                    Jelajahi katalog lengkap kami dan temukan signature scent yang paling sesuai.
                </p>
                <div class="mt-auto pt-6 flex justify-end">
                    <img src="{{ asset('storage/images/animation/step1.png') }}" alt="Ilustrasi langkah discover"
                        loading="lazy" decoding="async" class="h-36 w-auto object-contain" />
                </div>
            </div>

            <div class="group flex flex-col border border-brand-black/10 bg-white/80 p-6 shadow-[0_18px_36px_rgba(0,0,0,0.08)] transition duration-300 hover:-translate-y-1 hover:border-brand-gold/60" data-reveal data-reveal-delay="80">
                <span class="text-sm font-semibold text-brand-gold">2.</span>
                <h3 class="font-mayluxa text-2xl text-brand-black mt-4">Select</h3>
                <p class="text-sm text-brand-black/60 mt-3">
                    Pilih varian yang diinginkan, atur jumlah, lalu masukkan ke keranjang belanja.
                </p>
                <div class="mt-auto pt-6 flex justify-end">
                    <img src="{{ asset('storage/images/animation/step2.png') }}" alt="Ilustrasi langkah select"
                        loading="lazy" decoding="async" class="h-36 w-auto object-contain" />
                </div>
            </div>

            <div class="group flex flex-col border border-brand-black/10 bg-white/80 p-6 shadow-[0_18px_36px_rgba(0,0,0,0.08)] transition duration-300 hover:-translate-y-1 hover:border-brand-gold/60" data-reveal data-reveal-delay="160">
                <span class="text-sm font-semibold text-brand-gold">3.</span>
                <h3 class="font-mayluxa text-2xl text-brand-black mt-4">WhatsApp Checkout</h3>
                <p class="text-sm text-brand-black/60 mt-3">
                    Pesanan Anda terkirim otomatis ke WhatsApp admin untuk konfirmasi cepat.
                </p>
                <div class="mt-auto pt-6 flex justify-end">
                    <img src="{{ asset('storage/images/animation/step3.png') }}" alt="Ilustrasi langkah checkout"
                        loading="lazy" decoding="async" class="h-36 w-auto object-contain" />
                </div>
            </div>
        </div>

        <div class="mt-12 border border-white/10 bg-brand-black px-8 py-10 text-white shadow-[0_24px_50px_rgba(0,0,0,0.25)]" data-reveal>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h3 class="font-mayluxa text-3xl md:text-4xl">Butuh keyakinan lebih?</h3>
                    <p class="text-sm text-white/70 mt-4 max-w-xl">
                        Tim kami siap membantu memilih aroma yang paling cocok untuk Anda.
                    </p>
                </div>
                @if($storeInfo->whatsapp_link)
                <a href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center justify-center border border-white/30 px-6 py-3 text-xs font-semibold uppercase tracking-widest text-white/90 hover:bg-brand-gold hover:text-brand-black hover:border-brand-gold transition-colors">
                    Chat WhatsApp
                </a>
                @else
                <a href="{{ route('products.index') }}"
                    class="inline-flex items-center justify-center border border-white/30 px-6 py-3 text-xs font-semibold uppercase tracking-widest text-white/90 hover:bg-brand-gold hover:text-brand-black hover:border-brand-gold transition-colors">
                    Jelajahi Katalog
                </a>
                @endif
            </div>
        </div>
    </div>
</section>
