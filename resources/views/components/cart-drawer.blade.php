<dialog id="cartDrawer" class="modal modal-bottom sm:modal-middle lg:modal-right" aria-label="Keranjang Belanja"
    data-cart-url="{{ route('cart.data') }}"
    data-products-url="{{ route('products.index') }}">
    <div class="modal-box bg-white text-brand-black sm:h-full h-[85vh] w-full sm:w-[450px] max-w-none p-0 rounded-t-2xl sm:rounded-none flex flex-col">
        
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="font-mayluxa text-2xl">Keranjang Belanja</h3>
            <form method="dialog">
                <button type="submit" aria-label="Tutup keranjang"
                    class="hover:rotate-90 transition-transform duration-300 p-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold focus-visible:ring-offset-2 focus-visible:ring-offset-white">
                    <svg class="w-5 h-5 text-gray-400 hover:text-brand-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </form>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-[#FAFAFA]" id="drawerCartItems">
            <div class="flex flex-col items-center justify-center h-full text-center space-y-4">
                <div class="animate-spin w-6 h-6 border-2 border-brand-black border-t-transparent rounded-full"></div>
                <p class="text-xs uppercase tracking-widest text-gray-400">Memuat Keranjang...</p>
            </div>
        </div>
        
        <div class="border-t border-gray-100 bg-white p-6 space-y-4">
            <div class="flex justify-between items-center">
            <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Subtotal</span>
            <span class="font-mayluxa text-xl text-brand-black" id="drawerSubtotal">Rp 0</span>
            </div>
            
            <p class="text-[10px] text-gray-400 text-center font-light">
                Pajak termasuk. Ongkir dihitung saat checkout.
            </p>

            <div class="grid grid-cols-1 gap-3">
                {{-- <a href="{{ route('cart.checkout') }}" target="_blank" class="btn bg-brand-black text-white hover:bg-gray-800 border-none rounded-none h-12 uppercase tracking-widest text-xs flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.885m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    Checkout WhatsApp
                </a> --}}
                <a href="{{ route('cart.index') }}" class="btn bg-white text-brand-black border border-brand-black hover:bg-gray-50 rounded-none h-12 uppercase tracking-widest text-xs">
                    Lihat Keranjang
                </a>
            </div>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
        <button aria-label="Tutup keranjang">Tutup</button>
    </form>
</dialog>
