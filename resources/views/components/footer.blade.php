<footer class="qammaris-footer bg-brand-black text-white">
    <div class="mx-auto max-w-[1280px] px-5 pt-12 sm:px-8 md:pt-16 lg:px-12">
        <div class="grid gap-10 pb-10 md:pb-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.8fr)] lg:gap-16">
            <div>
                <a href="{{ route('home') }}" class="footer-link inline-flex min-h-11 items-center gap-3" aria-label="Qammaris — Beranda">
                    <img src="{{ asset('images/logo-black2.png') }}" alt="" width="40" height="40" loading="lazy" decoding="async" class="h-10 w-10 object-contain brightness-0 invert">
                    <span class="text-2xl font-medium tracking-tight text-white">Qammaris.</span>
                </a>
                <p class="mt-5 max-w-sm text-sm leading-6 text-white/75">Kurasi parfum original Timur Tengah. Temukan aroma pilihan Anda dari koleksi Qammaris.</p>
                @if(!empty($storeInfo->instagram_url) || !empty($storeInfo->facebook_url))
                    <ul class="mt-5 flex gap-2" aria-label="Media sosial Qammaris">
                        @if(!empty($storeInfo->instagram_url))
                            <li><a href="{{ $storeInfo->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram Qammaris (tab baru)" class="footer-link footer-social"><svg aria-hidden="true" focusable="false" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a></li>
                        @endif
                        @if(!empty($storeInfo->facebook_url))
                            <li><a href="{{ $storeInfo->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook Qammaris (tab baru)" class="footer-link footer-social"><svg aria-hidden="true" focusable="false" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a></li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="grid gap-x-6 gap-y-8 md:gap-x-8 {{ $footerCategories->isNotEmpty() ? 'grid-cols-2 md:grid-cols-3' : 'grid-cols-1 md:grid-cols-2' }}">
                <nav aria-labelledby="footer-explore">
                    <h2 id="footer-explore" class="footer-heading">Jelajahi</h2>
                    <ul class="footer-links">
                        <li><a href="{{ route('products.index') }}" class="footer-link">Katalog Parfum</a></li>
                        <li><a href="{{ route('quiz.index') }}" class="footer-link">Tes Parfum</a></li>
                        <li><a href="{{ route('blog.index') }}" class="footer-link">Qammaris Journal</a></li>
                        <li><a href="{{ route('store.location') }}" class="footer-link">Lokasi Toko</a></li>
                    </ul>
                </nav>
                @if($footerCategories->isNotEmpty())
                    <nav aria-labelledby="footer-collection">
                        <h2 id="footer-collection" class="footer-heading">Koleksi</h2>
                        <ul class="footer-links">
                            @foreach($footerCategories as $category)
                                <li><a href="{{ route('products.index', ['category' => $category->id]) }}" class="footer-link">{{ $category->name }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
                @if(!empty($storeInfo->whatsapp_number) || !empty($storeInfo->tokopedia_url) || !empty($storeInfo->shopee_url))
                    <nav aria-labelledby="footer-shopping" @class(['col-span-2 md:col-span-1' => $footerCategories->isNotEmpty()])>
                        <h2 id="footer-shopping" class="footer-heading">Bantuan &amp; belanja</h2>
                        <ul class="footer-links flex flex-wrap gap-x-6 md:block">
                            @if(!empty($storeInfo->whatsapp_number))
                                <li><a href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer" class="footer-link" aria-label="Bantuan WhatsApp (tab baru)">Bantuan WhatsApp</a></li>
                            @endif
                            @if(!empty($storeInfo->tokopedia_url))
                                <li><a href="{{ $storeInfo->tokopedia_url }}" target="_blank" rel="noopener noreferrer" class="footer-link" aria-label="Tokopedia (tab baru)">Tokopedia</a></li>
                            @endif
                            @if(!empty($storeInfo->shopee_url))
                                <li><a href="{{ $storeInfo->shopee_url }}" target="_blank" rel="noopener noreferrer" class="footer-link" aria-label="Shopee (tab baru)">Shopee</a></li>
                            @endif
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
        <div class="flex flex-col gap-3 border-t border-white/15 py-5 text-xs text-white/65 md:flex-row md:items-center md:justify-between">
            <p class="order-2 pb-2 leading-5 md:order-1 md:pb-0">&copy; {{ date('Y') }} Qammaris Perfumes. Hak cipta dilindungi.</p>
            <nav aria-label="Informasi dan keranjang" class="order-1 md:order-2">
                <ul class="flex flex-wrap gap-x-6">
                    <li><a href="{{ route('store.about') }}" class="footer-link">Tentang Qammaris</a></li>
                    <li><a href="{{ route('cart.index') }}" class="footer-link">Keranjang</a></li>
                </ul>
            </nav>
        </div>
    </div>
</footer>
