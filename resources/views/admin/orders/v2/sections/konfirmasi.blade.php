@use('App\Models\OnlineOrder')
{{-- ORD-04: website checkout waiting for Staff Order. The customer opening WhatsApp is not a confirmation. --}}
<details id="konfirmasi" class="{{ $card }}" @if ($openSection) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900">Konfirmasi pesanan website</span><span class="block text-sm text-gray-600">Belum masuk antrean packing</span></span>
        @include('admin.orders.v2._chevron')
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'konfirmasi'])
    <dl class="mt-3 space-y-2 text-sm">
        <div><dt class="text-gray-500">Pengiriman</dt><dd class="font-medium text-gray-900">{{ $order->shipping_quote
            ? $order->shipping_quote['label'].' · '.($order->shipping_quote['kind'] === 'instant' ? 'kurir instan' : ($order->shipping_quote['destination']['area_label'] ?? 'J&T'))
            : (config('orders.checkout_deliveries')[$order->fulfillment]['label'] ?? (OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? '-')) }}</dd></div>
        @if ($order->fulfillment !== 'pickup')
            <div><dt class="text-gray-500">Alamat</dt><dd class="break-words text-gray-900">{{ $order->address }}{{ $order->subdistrict ? ', '.$order->subdistrict : '' }}{{ $order->district ? ', '.$order->district : '' }}{{ $order->postcode ? ' '.$order->postcode : '' }}</dd></div>
        @endif
        <div><dt class="text-gray-500">Paperbag · Pembayaran</dt><dd class="text-gray-900">{{ OnlineOrder::PACKAGING[$order->packaging] ?? '-' }} · {{ config('orders.payment_preferences')[$order->payment_preference] ?? 'belum dipilih' }}</dd></div>
        @if ($order->customer_note)<div><dt class="text-gray-500">Catatan customer</dt><dd class="break-words text-gray-900">{{ $order->customer_note }}</dd></div>@endif
    </dl>
    {{-- ORD-13: whether the customer may already pay, or what staff must check first. --}}
    @if ($order->source === 'website' && config('orders.shipping_quotes.enabled'))
        @php($multiple = $order->items->where('quantity', '>', 1))
        @if ($order->payableBeforeConfirmation())
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-900" data-pay-now-note>Customer sudah bisa bayar langsung (semua produk 1 pcs, ongkir final). Setelah bukti bayar masuk di WhatsApp: konfirmasi pesanan, lalu Tandai Lunas.</p>
        @elseif ($multiple->isNotEmpty())
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" data-stock-check-note>Perlu cek stok: <strong>{{ $multiple->map(fn ($item) => $item->label().' × '.$item->quantity)->join(', ') }}</strong>. Customer bisa bayar setelah pesanan dikonfirmasi.</p>
        @endif
    @endif
    <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-700">
        <li>Cocokkan dengan chat WhatsApp customer (nomor {{ $order->code }}).</li>
        <li>Cek stok {{ $order->items->sum('quantity') }} barang di Produk dipesan{{ match (true) {
            $order->fulfillment === 'pickup' => ' (ambil di toko: tanpa ongkir)',
            $order->shipping_quote !== null => ' (ongkir '.$order->shipping_quote['label'].' sudah dihitung otomatis)',
            $order->shipping_payer === 'cash_to_driver' => ' (ongkir dibayar customer langsung ke driver)',
            default => ', lalu isi ongkir di bawah',
        } }}.</li>
        <li>Konfirmasi setelah customer setuju. Pembayaran dan packing baru bisa dicatat sesudahnya.</li>
    </ol>
    @include('admin.orders.v2._ongkir', ['section' => 'konfirmasi'])
    <form method="POST" action="{{ route('admin.orders.v2.confirm-website', $order) }}" class="mt-3" data-confirm="Konfirmasi {{ $order->code }}? Pesanan masuk ke alur pembayaran dan packing.">
        @csrf {!! $hidden('konfirmasi') !!}
        <button class="{{ $primary }}" data-busy-label="Mengonfirmasi…" data-confirm-website>Konfirmasi pesanan</button>
    </form>
    <p class="mt-2 text-xs text-gray-500">Customer tidak membalas atau pesanan tidak jadi? Batalkan lewat Opsi lanjutan.</p>
</details>
