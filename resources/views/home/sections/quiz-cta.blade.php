<section class="py-12 md:py-16 bg-white border-y border-gray-200">
    <div class="container mx-auto px-6">
        <div class="quiz-cta-card border border-brand-black/10 bg-brand-cream px-8 py-10 md:px-12 md:py-12 shadow-[0_20px_40px_rgba(0,0,0,0.08)]" data-reveal>
            <div class="grid gap-8 lg:gap-10 lg:grid-cols-[1.05fr,0.95fr] lg:items-center">
                <div class="max-w-xl lg:pl-4">
                    <p class="text-xs uppercase tracking-[0.35em] text-brand-black/40">Fragrance Finder</p>
                    <h2 class="font-mayluxa text-4xl md:text-5xl text-brand-black leading-tight mt-4">
                        Tes preferensi parfum<br> dalam 1 menit
                    </h2>
                    <p class="mt-5 text-base text-brand-black/65">
                        Cocok untuk yang masih bingung pilih aroma. Jawab beberapa pertanyaan ringan, lalu dapatkan rekomendasi langsung.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('quiz.index') }}"
                            class="inline-flex items-center justify-center bg-brand-black px-10 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-white hover:bg-brand-gold hover:text-brand-black transition-all">
                            Mulai Tes
                        </a>
                        <a href="{{ route('products.index') }}"
                            class="inline-flex items-center justify-center border border-brand-black/20 px-10 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-brand-black/70 hover:bg-brand-black hover:text-white transition-colors">
                            Lihat Katalog
                        </a>
                    </div>
                </div>
                <div class="relative flex justify-center lg:justify-end lg:h-[380px] xl:h-[420px] overflow-visible">
                    <img src="{{ asset('storage/images/animation/testparfumanimation.png') }}"
                        alt="Ilustrasi tes parfum" loading="lazy" decoding="async"
                        class="w-full max-w-[360px] md:max-w-[320px] lg:max-w-none lg:w-[380px] xl:w-[620px] lg:absolute lg:right-[-60px] lg:top-1/2 lg:-translate-y-[45%] animate-float" />
                </div>
            </div>
        </div>
    </div>
</section>
