<dialog id="cartDrawer" class="modal modal-bottom sm:modal-middle lg:modal-right" aria-labelledby="cart-drawer-title"
    data-cart-url="{{ route('cart.data') }}"
    data-products-url="{{ route('products.index') }}">
    <div class="modal-box bg-white text-brand-black sm:h-full h-[85vh] w-full sm:w-[450px] max-w-none p-0 rounded-t-2xl sm:rounded-none flex flex-col">
        
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <div>
                <h2 id="cart-drawer-title" class="font-mayluxa text-2xl">Daftar Inquiry</h2>
                <p class="mt-1 text-xs text-gray-500">Produk yang ingin ditanyakan ke admin.</p>
            </div>
            <form method="dialog">
                <button type="submit" aria-label="Tutup daftar inquiry"
                    class="flex h-11 w-11 items-center justify-center hover:rotate-90 motion-reduce:transform-none transition-transform duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold focus-visible:ring-offset-2 focus-visible:ring-offset-white">
                    <svg class="w-5 h-5 text-gray-400 hover:text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </form>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-[#FAFAFA]" id="drawerCartItems" aria-live="polite" aria-busy="true">
            <div class="flex flex-col items-center justify-center h-full text-center space-y-4" role="status">
                <div class="animate-spin motion-reduce:animate-none w-6 h-6 border-2 border-brand-black border-t-transparent rounded-full" aria-hidden="true"></div>
                <p class="text-xs uppercase tracking-widest text-gray-400">Memuat daftar inquiry...</p>
            </div>
        </div>
        
        <div class="border-t border-gray-100 bg-white px-6 pt-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] space-y-4">
            <div class="flex justify-between items-center">
            <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Estimasi nilai</span>
            <span class="font-mayluxa text-xl text-brand-black" id="drawerSubtotal">Rp 0</span>
            </div>
            
            <p class="text-[10px] text-gray-400 text-center font-light">
                Harga dan stok perlu dikonfirmasi. Daftar ini bukan reservasi.
            </p>

            <div class="grid grid-cols-1 gap-3">
                <a href="{{ route('cart.index') }}" class="btn bg-white text-brand-black border border-brand-black hover:bg-gray-50 rounded-none h-12 uppercase tracking-widest text-xs">
                    Tinjau daftar inquiry
                </a>
            </div>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
        <button aria-label="Tutup daftar inquiry">Tutup</button>
    </form>
</dialog>
