@extends('layouts.app')

@section('title', 'Keranjang Belanja - Qammaris Perfumes')
@section('robots', 'noindex,nofollow')

@section('content')

    <section class="pt-32 pb-20 bg-white min-h-screen">
        <div class="container mx-auto px-4 lg:px-20">

            <div class="text-center mb-16">
                <h1 class="font-mayluxa text-4xl lg:text-5xl mb-4">Keranjang Belanja</h1>
                <div class="w-12 h-px bg-brand-black mx-auto"></div>
            </div>

            @if (cart_count() > 0)
                <div class="flex flex-col lg:flex-row gap-12 lg:gap-24">

                    <div class="flex-1">
                        <div class="hidden md:grid grid-cols-12 border-b border-gray-200 pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">
                            <div class="col-span-6">Produk</div>
                            <div class="col-span-2 text-center">Jumlah</div>
                            <div class="col-span-4 text-right">Total</div>
                        </div>

                        <div class="space-y-8 md:space-y-0 mt-6 md:mt-0">
                            @php $cart = session('cart', []); @endphp
                            @foreach ($cart as $id => $item)
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 md:gap-0 items-center py-6 border-b border-gray-100 cart-item-row"
                                    data-id="{{ $id }}">

                                    <div class="col-span-6 flex gap-6">
                                        <div class="w-24 h-32 bg-[#FAFAFA] flex-shrink-0 border border-gray-100">
                                            <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" loading="lazy" decoding="async"
                                                class="w-full h-full object-cover mix-blend-multiply">
                                        </div>
                                        <div class="flex flex-col justify-center">
                                            <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-1">
                                                {{ $item['brand_name'] }}</p>
                                            <h3 class="font-mayluxa text-lg text-brand-black mb-1">
                                                <a href="#" class="hover:text-brand-gold transition-colors">{{ $item['product_name'] }}</a>
                                            </h3>
                                            <p class="text-sm text-gray-500 mb-3">{{ $item['volume'] }}ml</p>
                                            <button onclick="removeItem('{{ $id }}')"
                                                class="text-[10px] uppercase tracking-widest text-red-400 hover:text-red-600 text-left w-fit border-b border-transparent hover:border-red-600 transition-all">
                                                Hapus
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-span-2 flex justify-center">
                                        <div class="flex items-center border border-gray-200 w-fit h-10">
                                            <button type="button" onclick="updateQuantity('{{ $id }}', 'decrease')"
                                                class="w-8 h-full hover:bg-gray-50 text-gray-500 flex items-center justify-center text-lg transition-colors">-</button>

                                            <input type="number" id="qty-{{ $id }}" value="{{ $item['quantity'] }}"
                                                class="w-10 text-center border-none focus:ring-0 p-0 text-sm font-medium appearance-none" readonly>

                                            <button type="button" onclick="updateQuantity('{{ $id }}', 'increase')"
                                                class="w-8 h-full hover:bg-gray-50 text-brand-black flex items-center justify-center text-lg transition-colors">+</button>
                                        </div>
                                    </div>

                                    <div class="col-span-4 text-right">
                                        <p class="font-medium text-brand-black text-lg">
                                            {{ format_rupiah($item['price'] * $item['quantity']) }}
                                        </p>
                                        @if ($item['quantity'] > 1)
                                            <p class="text-xs text-gray-400 mt-1">{{ format_rupiah($item['price']) }} per item</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-between items-center mt-8">
                            <a href="{{ route('products.index') }}"
                                class="text-xs font-bold uppercase tracking-widest border-b border-brand-black pb-1 hover:text-brand-gold hover:border-brand-gold transition-all">
                                Lanjut Belanja
                            </a>

                            <form action="{{ route('cart.clear') }}" method="POST"
                                onsubmit="return confirm('Yakin ingin mengosongkan keranjang?')">
                                @csrf
                                <button type="submit"
                                    class="text-xs text-gray-400 hover:text-brand-black uppercase tracking-widest transition-colors">
                                    Kosongkan Keranjang
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="w-full lg:w-96 flex-shrink-0">
                        <div class="bg-[#FAFAFA] p-8 sticky top-32 border border-gray-100">
                            <h3 class="font-mayluxa text-xl mb-6">Data Pembeli</h3>

                            <form action="{{ route('cart.checkout') }}" method="POST">
                                @csrf
                                
                                <div class="space-y-5 mb-8">
                                    <div class="group">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-400 mb-1 group-focus-within:text-brand-black transition-colors">Nama Lengkap</label>
                                        <input type="text" name="customer_name" required
                                            class="w-full bg-transparent border-b border-gray-300 py-2 text-sm focus:border-brand-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                                            placeholder="Nama Anda">
                                    </div>

                                    <div class="group">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-400 mb-1 group-focus-within:text-brand-black transition-colors">Nomor WhatsApp</label>
                                        <input type="tel" name="customer_phone" required
                                            class="w-full bg-transparent border-b border-gray-300 py-2 text-sm focus:border-brand-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                                            placeholder="0812...">
                                    </div>

                                    <div class="group">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-400 mb-1 group-focus-within:text-brand-black transition-colors">Alamat Pengiriman</label>
                                        <textarea name="customer_address" rows="2" required
                                            class="w-full bg-transparent border-b border-gray-300 py-2 text-sm focus:border-brand-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300 resize-none"
                                            placeholder="Jalan, Kota, Provinsi"></textarea>
                                    </div>

                                    <div class="group">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-400 mb-1 group-focus-within:text-brand-black transition-colors">Catatan (Opsional)</label>
                                        <input type="text" name="customer_note"
                                            class="w-full bg-transparent border-b border-gray-300 py-2 text-sm focus:border-brand-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                                            placeholder="Catatan khusus...">
                                    </div>
                                </div>

                                <h3 class="font-mayluxa text-xl mb-4">Ringkasan</h3>
                                
                                <div class="space-y-3 mb-6 border-b border-gray-200 pb-6">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Subtotal</span>
                                        <span class="font-medium">{{ format_rupiah(cart_total()) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Pengiriman</span>
                                        <span class="text-xs text-gray-400 italic">Dihitung via WhatsApp</span>
                                    </div>
                                </div>

                                <div class="flex justify-between items-end mb-8">
                                    <span class="text-sm font-bold uppercase tracking-widest">Total</span>
                                    <span class="font-mayluxa text-2xl">{{ format_rupiah(cart_total()) }}</span>
                                </div>

                                <button type="submit"
                                    class="btn bg-brand-black text-white hover:bg-gray-800 w-full h-14 rounded-none uppercase tracking-widest text-sm flex items-center justify-center gap-3 mb-4 transition-transform active:scale-[0.98]">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                    </svg>
                                    Kirim Pesanan
                                </button>
                            </form>
                            <div class="text-center space-y-2">
                                <p class="text-[10px] text-gray-400 uppercase tracking-widest">Checkout aman via WhatsApp</p>
                            </div>
                        </div>
                    </div>

                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-20 h-20 border border-gray-200 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <h2 class="font-mayluxa text-2xl mb-3">Keranjang masih kosong</h2>
                    <p class="text-gray-500 font-light mb-8 max-w-md">Sepertinya kamu belum menemukan signature scent-mu.</p>
                    <a href="{{ route('products.index') }}"
                        class="btn bg-brand-black text-white hover:bg-gray-800 px-12 h-12 rounded-none uppercase tracking-widest text-xs">
                        Lihat Koleksi
                    </a>
                </div>
            @endif

        </div>
    </section>

@endsection

@push('scripts')
<script>
async function updateQuantity(itemId, action) {
    const input = document.getElementById(`qty-${itemId}`);
    let currentQty = parseInt(input.value);
    
    if (action === 'increase') {
        currentQty++;
    } else if (action === 'decrease') {
        if (currentQty > 1) currentQty--;
        else return;
    }

    input.value = currentQty;

    try {
        const response = await fetch(`/cart/update/${itemId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ quantity: currentQty })
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload(); 
        } else {
            alert('Gagal update: ' + data.message);
            location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function removeItem(itemId) {
    if (!confirm('Hapus produk ini dari keranjang?')) return;
    
    try {
        const response = await fetch(`/cart/remove/${itemId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Gagal menghapus item');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
</script>
@endpush
