@use('App\Models\OnlineOrder')
{{-- ORD-04: website checkout waiting for Staff Order. The customer opening WhatsApp is not a confirmation. --}}
<details id="konfirmasi" class="{{ $card }} border-amber-300" @if ($openSection) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900">Konfirmasi pesanan website</span><span class="block text-sm text-gray-600">Belum masuk antrean packing</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'konfirmasi'])
    <dl class="mt-3 space-y-2 text-sm">
        <div><dt class="text-gray-500">Pengiriman</dt><dd class="font-medium text-gray-900">{{ config('orders.checkout_deliveries')[$order->fulfillment]['label'] ?? (OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? '-') }}</dd></div>
        @if ($order->fulfillment !== 'pickup')
            <div><dt class="text-gray-500">Alamat</dt><dd class="break-words text-gray-900">{{ $order->address }}{{ $order->subdistrict ? ', '.$order->subdistrict : '' }}{{ $order->district ? ', '.$order->district : '' }}{{ $order->postcode ? ' '.$order->postcode : '' }}</dd></div>
        @endif
        <div><dt class="text-gray-500">Paperbag · Pembayaran</dt><dd class="text-gray-900">{{ OnlineOrder::PACKAGING[$order->packaging] ?? '-' }} · {{ config('orders.payment_preferences')[$order->payment_preference] ?? '-' }}</dd></div>
        @if ($order->customer_note)<div><dt class="text-gray-500">Catatan customer</dt><dd class="break-words text-gray-900">{{ $order->customer_note }}</dd></div>@endif
    </dl>
    <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-700">
        <li>Cocokkan dengan chat WhatsApp customer (nomor {{ $order->code }}).</li>
        <li>Cek stok, lalu isi ongkir di bagian Pembayaran{{ $order->fulfillment === 'pickup' ? ' (ambil di toko: tanpa ongkir)' : '' }}.</li>
        <li>Konfirmasi setelah customer setuju. Pembayaran dan packing baru bisa dicatat sesudahnya.</li>
    </ol>
    <form method="POST" action="{{ route('admin.orders.v2.confirm-website', $order) }}" class="mt-3" data-confirm="Konfirmasi {{ $order->code }}? Pesanan masuk ke alur pembayaran dan packing.">
        @csrf {!! $hidden('konfirmasi') !!}
        <button class="{{ $primary }}" data-busy-label="Mengonfirmasi…" data-confirm-website>Konfirmasi pesanan</button>
    </form>
    <p class="mt-2 text-xs text-gray-500">Customer tidak membalas atau pesanan tidak jadi? Batalkan lewat Opsi lanjutan.</p>
</details>
