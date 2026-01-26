<section class="relative h-screen min-h-[680px] w-full overflow-hidden bg-black">

    <div class="absolute inset-0 z-0 grid grid-cols-1 md:grid-cols-3">
        <div class="relative overflow-hidden hidden md:block">
            <video class="w-full h-full object-cover brightness-80 hover:brightness-100 transition duration-700" autoplay muted loop playsinline>
                <source src="{{ asset('images/video-hero1.mp4') }}" type="video/mp4">
            </video>
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 to-black/60"></div>
        </div>
        <div class="relative overflow-hidden">
            <video class="w-full h-full object-cover brightness-80 hover:brightness-100 transition duration-700 md:hidden" autoplay muted loop playsinline>
                <source src="{{ asset('images/video-hero-mobile.mp4') }}" type="video/mp4">
            </video>
            <video class="w-full h-full object-cover brightness-80 hover:brightness-100 transition duration-700 hidden md:block" autoplay muted loop playsinline>
                <source src="{{ asset('images/video-hero2.mp4') }}" type="video/mp4">
            </video>
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 to-black/60"></div>
        </div>
        <div class="relative overflow-hidden hidden md:block">
            <video class="w-full h-full object-cover brightness-75 hover:brightness-100 transition duration-700" autoplay muted loop playsinline>
                <source src="{{ asset('images/video-hero3.mp4') }}" type="video/mp4">
            </video>
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 to-black/60"></div>
        </div>

        <div class="absolute inset-y-0 left-1/3 w-px bg-white/10 hidden md:block"></div>
        <div class="absolute inset-y-0 left-2/3 w-px bg-white/10 hidden md:block"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/35 to-black/60"></div>
    </div>

    <div class="relative z-10 h-full flex items-center justify-center">
        <div class="text-center px-6 space-y-4">
            <p class="text-xs md:text-sm tracking-[0.4em] uppercase text-white/70">Qammaris Parfumes</p>
            <h1 class="font-mayluxa text-3xl md:text-4xl lg:text-5xl text-white leading-tight drop-shadow-xl">
                Redefining Middle East Scents
            </h1>
            <div class="flex justify-center">
                <a href="{{ route('products.index') }}"
                    class="px-7 py-3 border border-white/70 text-white text-xs md:text-sm font-semibold uppercase tracking-[0.18em] hover:bg-white hover:text-brand-black transition-colors duration-300">
                    Discover Our Fragrance
                </a>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-20 md:py-28">
    <div class="container mx-auto px-6 text-center space-y-6 md:space-y-8">
        <div class="flex justify-center">
            <img src="{{ asset('images/logo-black2.png') }}" alt="Qammaris Perfumes" class="w-16 md:w-20 h-auto">
        </div>
        <div class="space-y-3">
            <h2 class="font-mayluxa text-2xl md:text-3xl tracking-[0.3em] uppercase text-brand-black">About Qammaris</h2>
            <div class="flex justify-center">
                <span class="block w-16 h-[2px] bg-brand-gold"></span>
            </div>
            <p class="text-gray-500 text-base md:text-lg">Born in Palu, Curated for the World.</p>
        </div>
        <p class="text-gray-500 max-w-3xl mx-auto leading-relaxed text-sm md:text-base">
            Qammaris Perfumes lahir dari kecintaan pada aroma Timur Tengah dan keinginan menghadirkan pengalaman coba langsung di Palu. Kami mengkurasi setiap parfum dengan teliti agar Anda dapat menemukan karakter aroma yang paling sesuai, tanpa harus menebak.
        </p>
        <div class="flex justify-center pt-2">
            <a href="{{ route('store.about') }}" class="text-brand-gold font-semibold border-b border-brand-gold/70 pb-1 hover:text-brand-black transition-colors duration-200">
                See More
            </a>
        </div>
    </div>
</section>
