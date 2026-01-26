@extends('layouts.app')

@section('title', 'Our Boutique - Qammaris Perfumes')
@section('meta_description', 'Kunjungi butik offline Qammaris Perfumes. Rasakan langsung kemewahan aroma Timur Tengah.')

@section('content')

<div class="pt-32 pb-12 bg-white text-center">
    <div class="container mx-auto px-4">
        <p class="text-xs md:text-sm font-bold uppercase tracking-[0.3em] text-brand-gold mb-4 animate-fade-in-up">
            Visit Us
        </p>
        <h1 class="font-mayluxa text-5xl md:text-7xl text-brand-black mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
            Our Boutique
        </h1>
        <p class="text-gray-500 font-light text-lg max-w-2xl mx-auto leading-relaxed animate-fade-in-up" style="animation-delay: 0.2s;">
            Rasakan langsung kemewahan aroma Timur Tengah di butik offline kami. Konsultasikan preferensi aroma Anda dengan fragrance specialist kami.
        </p>
    </div>
</div>

<section class="py-20 bg-white">
    <div class="container mx-auto px-4 lg:px-12">
        <div class="grid lg:grid-cols-2 gap-16 items-start">

            <div class="space-y-12">
                
                <div>
                    <h3 class="font-mayluxa text-3xl text-brand-black mb-8 border-b border-gray-100 pb-4">Contact & Address</h3>
                    
                    <div class="space-y-8">
                        @php($hasWhatsapp = !empty($whatsappLink))
                        <div class="flex gap-6 group">
                            <div class="w-10 h-10 border border-gray-200 flex items-center justify-center rounded-full flex-shrink-0 group-hover:border-brand-black transition-colors">
                                <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-brand-black mb-1">Toko Utama</p>
                                <p class="text-gray-600 font-light leading-relaxed">
                                    {!! $displayAddress ?? '' !!}
                                </p>
                                <a href="{{ $mapsLink ?: 'https://maps.google.com' }}" target="_blank" rel="noopener noreferrer" class="inline-block mt-2 text-xs font-medium uppercase tracking-widest border-b border-gray-300 pb-0.5 hover:border-brand-gold hover:text-brand-gold transition-all">
                                    Get Directions
                                </a>
                            </div>
                        </div>

                        <div class="flex gap-6 group">
                            <div class="w-10 h-10 border border-gray-200 flex items-center justify-center rounded-full flex-shrink-0 group-hover:border-brand-black transition-colors">
                                <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-brand-black mb-1">WhatsApp & Call</p>
                                <p class="text-gray-600 font-light tracking-wide">{{ $phoneDisplay ?? '' }}</p>
                                <a href="{{ $whatsappLink ?: '#' }}" target="_blank" rel="noopener noreferrer"
                                    class="inline-block mt-2 text-xs font-medium uppercase tracking-widest border-b border-gray-300 pb-0.5 hover:border-brand-gold hover:text-brand-gold transition-all {{ $hasWhatsapp ? '' : 'pointer-events-none opacity-60' }}"
                                    @if (! $hasWhatsapp) aria-disabled="true" @endif>
                                    Chat Now
                                </a>
                            </div>
                        </div>

                        <div class="flex gap-6 group">
                            <div class="w-10 h-10 border border-gray-200 flex items-center justify-center rounded-full flex-shrink-0 group-hover:border-brand-black transition-colors">
                                <svg class="w-4 h-4 text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-brand-black mb-1">Email</p>
                                <p class="text-gray-600 font-light">{{ $storeInfo->email ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-mayluxa text-3xl text-brand-black mb-8 border-b border-gray-100 pb-4">Opening Hours</h3>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-1">Mon - Sat</p>
                            <p class="text-brand-black font-medium">09:00 - 21:00 WIB</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-1">Sunday</p>
                            <p class="text-brand-black font-medium opacity-50">Closed</p>
                        </div>
                    </div>
                    <div class="mt-8 p-4 bg-[#FAFAFA] border border-gray-100 flex items-start gap-3">
                        <svg class="w-5 h-5 text-brand-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Layanan chat WhatsApp tetap tersedia 24/7. Untuk hari libur nasional, silakan cek Instagram kami untuk update jam operasional terbaru.
                        </p>
                    </div>
                </div>

            </div>

            <div class="h-[500px] lg:h-full min-h-[500px] bg-gray-100 relative overflow-hidden group">
                @if ($mapsEmbedSrc)
                    <iframe 
                        src="{{ $mapsEmbedSrc }}"
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade"
                        class="w-full h-full grayscale group-hover:grayscale-0 transition-all duration-700 ease-in-out"
                    ></iframe>
                @else
                    <div class="w-full h-full flex items-center justify-center text-xs font-semibold uppercase tracking-[0.3em] text-gray-400">
                        Map belum tersedia
                    </div>
                @endif
                
                <a href="{{ $mapsLink ?: 'https://maps.google.com' }}" target="_blank" rel="noopener noreferrer" class="absolute bottom-6 right-6 bg-white text-brand-black px-6 py-3 text-xs font-bold uppercase tracking-widest shadow-xl hover:bg-brand-black hover:text-white transition-colors z-10">
                    Open in Google Maps
                </a>
            </div>

        </div>
    </div>
</section>

<section class="py-20 bg-[#FAFAFA] border-t border-gray-100">
    <div class="container mx-auto px-4 text-center">
        <h2 class="font-mayluxa text-3xl text-brand-black mb-12">Connect & Shop Online</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 max-w-5xl mx-auto">
            
            <a href="{{ $instagramUrl ?: '#' }}" target="_blank" rel="noopener noreferrer"
                class="group bg-white p-8 border border-gray-100 hover:border-brand-black transition-all duration-300 flex flex-col items-center justify-center {{ $instagramUrl ? '' : 'pointer-events-none opacity-60' }}"
                @if (! $instagramUrl) aria-disabled="true" @endif>
                <img src="https://cdn.simpleicons.org/instagram/1A1A1A" alt="Instagram"
                    class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" loading="lazy" decoding="async">
                <h4 class="font-bold text-sm uppercase tracking-wider mb-1">Instagram</h4>
                <span class="text-[10px] text-gray-400 group-hover:text-brand-gold transition-colors">@qammarisperfumes</span>
            </a>

            <a href="{{ $tokopediaUrl ?: '#' }}" target="_blank" rel="noopener noreferrer"
                class="group bg-white p-8 border border-gray-100 hover:border-[#03AC0E] transition-all duration-300 flex flex-col items-center justify-center {{ $tokopediaUrl ? '' : 'pointer-events-none opacity-60' }}"
                @if (! $tokopediaUrl) aria-disabled="true" @endif>
                <span class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform inline-flex items-center justify-center" aria-hidden="true">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192" class="w-8 h-8 fill-current text-brand-black"><path fill-rule="evenodd" d="M96 28c-9.504 0-17.78 5.307-22.008 13.127C82.736 42.123 88.89 44 96 47.332c7.11-3.332 13.264-5.209 22.008-6.205C113.781 33.31 105.506 28 96 28Zm0-12c-15.973 0-29.568 10.117-34.754 24.28C52.932 40 42.462 40 28.53 40H28a6 6 0 0 0-6 6v124a6 6 0 0 0 6 6h92c27.614 0 50-22.386 50-50V46a6 6 0 0 0-6-6h-.531c-13.931 0-24.401 0-32.715.28C125.566 26.113 111.97 16 96 16ZM34 52.001V164h86c20.987 0 38-17.013 38-38V52.001c-18.502.009-29.622.098-37.872.966-8.692.915-13.999 2.677-21.445 6.4a6 6 0 0 1-5.366 0c-7.446-3.723-12.753-5.485-21.445-6.4-8.25-.868-19.37-.957-37.872-.966ZM50 96c0-9.941 8.059-18 18-18s18 8.059 18 18-8.059 18-18 18-18-8.059-18-18Zm18-30c-16.569 0-30 13.431-30 30 0 16.569 13.431 30 30 30 1.126 0 2.238-.062 3.332-.183l20.425 20.426a6 6 0 0 0 8.486 0l20.425-20.426c1.094.121 2.206.183 3.332.183 16.569 0 30-13.431 30-30 0-16.569-13.431-30-30-30-12.764 0-23.666 7.971-28 19.207C91.666 73.971 80.764 66 68 66Zm40.082 55.433A30.1 30.1 0 0 1 96 106.793a30.101 30.101 0 0 1-12.082 14.64L96 133.515l12.082-12.082ZM124 78c-9.941 0-18 8.059-18 18s8.059 18 18 18 18-8.059 18-18-8.059-18-18-18ZM76 96a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm48 8a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" clip-rule="evenodd"/></svg>
                </span>
                <h4 class="font-bold text-sm uppercase tracking-wider mb-1">Tokopedia</h4>
                <span class="text-[10px] text-gray-400">Toko Resmi</span>
            </a>

            <a href="{{ $shopeeUrl ?: '#' }}" target="_blank" rel="noopener noreferrer"
                class="group bg-white p-8 border border-gray-100 hover:border-[#EE4D2D] transition-all duration-300 flex flex-col items-center justify-center {{ $shopeeUrl ? '' : 'pointer-events-none opacity-60' }}"
                @if (! $shopeeUrl) aria-disabled="true" @endif>
                <img src="https://cdn.simpleicons.org/shopee/1A1A1A" alt="Shopee"
                    class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" loading="lazy" decoding="async">
                <h4 class="font-bold text-sm uppercase tracking-wider mb-1">Shopee</h4>
                <span class="text-[10px] text-gray-400">Official Shop</span>
            </a>

            <a href="{{ $tiktokUrl ?: '#' }}" target="_blank" rel="noopener noreferrer"
                class="group bg-white p-8 border border-gray-100 hover:border-gray-700 transition-all duration-300 flex flex-col items-center justify-center {{ $tiktokUrl ? '' : 'pointer-events-none opacity-60' }}"
                @if (! $tiktokUrl) aria-disabled="true" @endif>
                <img src="https://cdn.simpleicons.org/tiktok/1A1A1A" alt="TikTok"
                    class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" loading="lazy" decoding="async">
                <h4 class="font-bold text-sm uppercase tracking-wider mb-1">TikTok</h4>
                <span class="text-[10px] text-gray-400">@qammaris.parfum</span>
            </a>

            <a href="{{ $tiktokShopUrl ?: '#' }}" target="_blank" rel="noopener noreferrer"
                class="group bg-white p-8 border border-gray-100 hover:border-gray-700 transition-all duration-300 flex flex-col items-center justify-center {{ $tiktokShopUrl ? '' : 'pointer-events-none opacity-60' }}"
                @if (! $tiktokShopUrl) aria-disabled="true" @endif>
                <img src="https://cdn.simpleicons.org/tiktok/1A1A1A" alt="TikTok Shop"
                    class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" loading="lazy" decoding="async">
                <h4 class="font-bold text-sm uppercase tracking-wider mb-1">TikTok Shop</h4>
                <span class="text-[10px] text-gray-400">Mall Qammaris</span>
            </a>

            <a href="{{ $facebookUrl ?: '#' }}" target="_blank" rel="noopener noreferrer"
                class="group bg-white p-8 border border-gray-100 hover:border-blue-600 transition-all duration-300 flex flex-col items-center justify-center {{ $facebookUrl ? '' : 'pointer-events-none opacity-60' }}"
                @if (! $facebookUrl) aria-disabled="true" @endif>
                <img src="https://cdn.simpleicons.org/facebook/1A1A1A" alt="Facebook"
                    class="w-8 h-8 mb-4 group-hover:scale-110 transition-transform" loading="lazy" decoding="async">
                <h4 class="font-bold text-sm uppercase tracking-wider mb-1">Facebook</h4>
                <span class="text-[10px] text-gray-400">Qammaris Perfumes</span>
            </a>
        </div>
    </div>
</section>

@endsection
