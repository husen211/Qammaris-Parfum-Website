<footer class="relative overflow-hidden bg-brand-black text-white">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -top-40 right-0 h-80 w-80 rounded-full bg-brand-gold/10 blur-3xl"></div>
        <div class="absolute -bottom-48 left-0 h-80 w-80 rounded-full bg-white/5 blur-3xl"></div>
    </div>
    <div class="container relative mx-auto px-6 py-16 md:py-20">
        <div class="h-px w-full bg-gradient-to-r from-transparent via-white/20 to-transparent mb-12"></div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 xl:gap-16">

            <div class="space-y-6">
                <div class="space-y-3">
                    <p class="text-xs uppercase tracking-[0.3em] text-white/40">Qammaris Perfumes</p>
                    <h3 class="font-mayluxa text-3xl text-white tracking-wide">Qammaris.</h3>
                </div>
                <p class="text-white/65 text-sm leading-relaxed font-light max-w-md">
                    Kurasi parfum premium Timur Tengah. Menghadirkan kemewahan dan keharuman otentik dalam setiap tetesnya. 100% Original.
                </p>

                <div class="flex items-center space-x-3 pt-2">
                    @if(!empty($storeInfo->instagram_url))
                    <a href="{{ $storeInfo->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
                        class="w-10 h-10 rounded-full border border-white/15 bg-white/5 flex items-center justify-center hover:bg-brand-gold hover:text-brand-black hover:border-brand-gold transition-all duration-300">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    @endif
                    @if(!empty($storeInfo->facebook_url))
                    <a href="{{ $storeInfo->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook"
                        class="w-10 h-10 rounded-full border border-white/15 bg-white/5 flex items-center justify-center hover:bg-brand-gold hover:text-brand-black hover:border-brand-gold transition-all duration-300">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    @endif
                </div>
            </div>

            <div>
                <h4 class="font-bold text-xs uppercase tracking-widest text-white/50 mb-6">Jelajahi</h4>
                <ul class="space-y-4 text-sm font-light">
                    <li>
                        <a href="{{ route('products.index') }}" class="group inline-flex items-center text-white/65 hover:text-brand-gold transition-colors">
                            <span class="h-px w-5 bg-white/30 mr-3 transition-all group-hover:w-8 group-hover:bg-brand-gold"></span>
                            Katalog Parfum
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('blog.index') }}" class="group inline-flex items-center text-white/65 hover:text-brand-gold transition-colors">
                            <span class="h-px w-5 bg-white/30 mr-3 transition-all group-hover:w-8 group-hover:bg-brand-gold"></span>
                            Jurnal
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('store.location') }}" class="group inline-flex items-center text-white/65 hover:text-brand-gold transition-colors">
                            <span class="h-px w-5 bg-white/30 mr-3 transition-all group-hover:w-8 group-hover:bg-brand-gold"></span>
                            Lokasi Toko
                        </a>
                    </li>
                    <li>
                        <a href="#how-to-order" class="group inline-flex items-center text-white/65 hover:text-brand-gold transition-colors">
                            <span class="h-px w-5 bg-white/30 mr-3 transition-all group-hover:w-8 group-hover:bg-brand-gold"></span>
                            Cara Pesan
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-xs uppercase tracking-widest text-white/50 mb-6">Koleksi</h4>
                <ul class="space-y-4 text-sm font-light">
                    @foreach($footerCategories as $category)
                    <li>
                        <a href="{{ route('products.index', ['category' => $category->id]) }}" class="group inline-flex items-center text-white/65 hover:text-brand-gold transition-colors">
                            <span class="h-px w-5 bg-white/30 mr-3 transition-all group-hover:w-8 group-hover:bg-brand-gold"></span>
                            {{ $category->name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-xs uppercase tracking-widest text-white/50 mb-6">Belanja Online</h4>
                <div class="space-y-3">
                    @if(!empty($storeInfo->tokopedia_url))
                    <a href="{{ $storeInfo->tokopedia_url }}" target="_blank" rel="noopener noreferrer"
                        class="flex items-center space-x-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white/70 hover:text-green-400 hover:border-white/20 transition-colors group">
                        <span class="h-9 w-9 rounded-full bg-white/10 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </span>
                        <span class="text-sm font-medium">Tokopedia</span>
                    </a>
                    @endif

                    @if(!empty($storeInfo->shopee_url))
                    <a href="{{ $storeInfo->shopee_url }}" target="_blank" rel="noopener noreferrer"
                        class="flex items-center space-x-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white/70 hover:text-orange-400 hover:border-white/20 transition-colors group">
                        <span class="h-9 w-9 rounded-full bg-white/10 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </span>
                        <span class="text-sm font-medium">Shopee</span>
                    </a>
                    @endif
                </div>

                @if(!empty($storeInfo->whatsapp_number))
                <a href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer"
                    class="mt-8 inline-flex items-center justify-center w-full rounded-full px-6 py-3 border border-white/20 text-sm font-semibold text-white/90 hover:bg-brand-gold hover:text-brand-black hover:border-brand-gold transition-all duration-300">
                    <span>Bantuan WhatsApp</span>
                </a>
                @endif
            </div>

        </div>
    </div>

    <div class="border-t border-white/10 bg-black/30">
        <div class="container mx-auto px-6 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center text-xs text-white/50">
                <p>&copy; {{ date('Y') }} Qammaris Perfumes. Hak cipta dilindungi.</p>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="#" class="hover:text-white transition-colors">Kebijakan Privasi</a>
                    <a href="#" class="hover:text-white transition-colors">Syarat &amp; Ketentuan</a>
                </div>
            </div>
        </div>
    </div>
</footer>
