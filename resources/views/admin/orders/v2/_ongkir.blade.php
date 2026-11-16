@use('App\Models\OnlineOrder')
{{-- Ongkir charged to the customer (ORD-03/04). Shown open while it is still empty, collapsed once set. What the courier
     is paid, and whose money, is entered when the courier is booked (ORD-08, Pengiriman). --}}
@php
    $canChargeShipping = auth()->user()->can('orders.charge-shipping', $order);
    $ongkirSet = \App\Support\OnlineOrderStepper::totalIsFinal($order);
    // ORD-26 (A): Biteship prices for an order staff filled, shown after "Hitung ongkir otomatis".
    $staffQuotes = session('staff_shipping_quotes.order') === $order->id ? session('staff_shipping_quotes.options', []) : [];
    $autoPricing = $canChargeShipping && \App\Actions\Orders\ShippingQuotes::pricesOrders();
@endphp
@if ($order->fulfillment !== 'pickup')
    <details class="mt-3 rounded-lg border border-gray-200 p-3" @if (! $ongkirSet || $staffQuotes || (old('_section') === $section && old('shipping_payer') !== null)) open @endif data-ongkir>
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 text-sm">
            <span><span class="font-semibold text-gray-900">Ongkir</span> <span class="text-gray-600">{{ implode(' · ', array_filter([
                $order->shipping_fee !== null ? format_rupiah($order->shipping_fee) : ($order->shipping_payer === 'cash_to_driver' ? null : 'belum diisi'),
                $order->shipping_payer ? OnlineOrder::SHIPPING_PAYERS[$order->shipping_payer] : null,
            ])) }}</span></span>
            <span class="flex items-center gap-1 text-sm text-gray-500">{{ $ongkirSet ? 'Ubah' : '' }}@include('admin.orders.v2._chevron')</span>
        </summary>
        @if ($canChargeShipping)
            <form method="POST" action="{{ route('admin.orders.v2.shipping', $order) }}" class="mt-2 space-y-3">
                @csrf {!! $hidden($section) !!}
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium text-gray-700">Ongkir (Rp)<input name="shipping_fee" data-rupiah-input inputmode="numeric" value="{{ old('shipping_fee', $order->shipping_fee !== null ? (int) $order->shipping_fee : null) }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-gray-700">Metode pembayaran ongkir
                        <select name="shipping_payer" class="{{ $input }}"><option value="">Belum ditentukan</option>@foreach (OnlineOrder::SHIPPING_PAYERS as $value => $label)<option value="{{ $value }}" @selected(old('shipping_payer', $order->shipping_payer) === $value)>{{ $label }}</option>@endforeach</select>
                    </label>
                </div>
                {{-- ORD-10: the customer picks this on the order link or at checkout; staff only change it on request. --}}
                @if ($order->shipping_fee === null)
                    <p class="text-xs leading-5 text-gray-600" data-ongkir-hint>{{ match ($order->shipping_payer) {
                        'cash_to_driver' => 'Customer bayar ongkir langsung ke driver, jadi total sudah final. Nominal di sini opsional; ongkir kurir dicatat saat pesan kurir.',
                        null => 'Customer belum memilih metode. Tanyakan di chat.',
                        default => 'Sudah dipilih customer. Cukup isi nominal ongkir; ubah metode hanya bila customer minta lain.',
                    } }}</p>
                @endif
                <button class="{{ $ongkirSet || $section === 'konfirmasi' ? $secondary : $primary }}" data-busy-label="Menyimpan…">Simpan ongkir</button>
            </form>
            {{-- ORD-26 (Owner 2026-11-16, A): the same Biteship prices and discount as the checkout, for an order staff filled. --}}
            @if ($autoPricing && $order->staffPricesShipping())
                <div class="mt-3 border-t border-gray-100 pt-3" data-ongkir-auto>
                    @if ($staffQuotes)
                        <p class="text-sm font-medium text-gray-900">Pilih ongkir dari Biteship</p>
                        <div class="mt-2 space-y-2">
                            @foreach ($staffQuotes as $option)
                                <form method="POST" action="{{ route('admin.orders.v2.shipping.quote', $order) }}" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2" data-ongkir-option>
                                    @csrf {!! $hidden($section) !!} <input type="hidden" name="option" value="{{ $option['id'] }}">
                                    <span class="min-w-0 text-sm">
                                        <span class="font-semibold text-gray-900">{{ $option['label'] }}</span>@if ($option['eta'])<span class="text-gray-600"> · {{ $option['eta'] }}</span>@endif
                                        <span class="block tabular-nums text-gray-700">@if ($option['discount'] > 0)<s class="text-gray-400">{{ format_rupiah($option['price']) }}</s> @endif{{ format_rupiah($option['charged']) }}@if ($option['discount'] > 0)<span class="text-gray-500"> · potongan {{ $option['percent'] }}%</span>@endif</span>
                                    </span>
                                    <button class="{{ $secondary }} shrink-0" data-busy-label="Menyimpan…">Pakai</button>
                                </form>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Harga berlaku 30 menit, dengan potongan yang sama seperti checkout website.</p>
                    @else
                        <form method="POST" action="{{ route('admin.orders.v2.shipping.quotes', $order) }}">
                            @csrf {!! $hidden($section) !!}
                            <button class="{{ $secondary }} w-full sm:w-auto" data-busy-label="Menghitung…">Hitung ongkir otomatis</button>
                            <p class="mt-1 text-xs text-gray-500">{{ $order->fulfillment === 'local_delivery' ? 'Harga GoSend dan GrabExpress ke titik antar.' : 'Harga J&T ke kode pos '.$order->postcode.'.' }}</p>
                        </form>
                    @endif
                </div>
            @elseif ($autoPricing && $order->shipping_quote === null && $received === 0 && in_array($order->fulfillment, ['local_delivery', 'intercity'], true))
                <p class="text-xs leading-5 text-gray-600" data-ongkir-auto-needs>{{ $order->fulfillment === 'local_delivery'
                    ? 'Ongkir otomatis butuh titik antar: pasang di Data penerima, atau minta customer mengisi link pesanan.'
                    : 'Ongkir otomatis J&T butuh kode pos di Data penerima.' }}</p>
            @endif
        @else
            <p class="mt-2 text-sm text-gray-600">{{ $received > 0 ? 'Sudah ada pembayaran. Ubah total lewat Opsi lanjutan → penyesuaian harga.' : 'Ongkir diatur Super Admin.' }}</p>
        @endif
    </details>
    @if ($quote = $order->shipping_quote)
        {{-- ORD-13: priced at checkout and locked for the customer; staff may lower it, never raise it. --}}
        <div class="mt-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700" data-ongkir-quote>
            <p><span class="font-semibold text-gray-900">{{ $quote['label'] }}</span>{{ $quote['eta'] ? ' · '.$quote['eta'] : '' }} · {{ match ($quote['chosen_by'] ?? null) { 'customer_link' => 'dipilih customer di link pesanan', 'staff' => 'dihitung otomatis oleh admin', default => 'dihitung otomatis di checkout' } }}</p>
            <p class="mt-0.5 tabular-nums">Harga kurir {{ format_rupiah($quote['price']) }}@if ($quote['discount'] > 0) · potongan {{ format_rupiah($quote['discount']) }}@endif · ditagih {{ format_rupiah($quote['charged']) }}</p>
            @if (isset($quote['destination']['latitude']))
                <a href="https://www.google.com/maps?q={{ $quote['destination']['latitude'] }},{{ $quote['destination']['longitude'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex min-h-9 items-center font-semibold text-gray-900 underline underline-offset-2">Buka titik antar di Maps</a>
            @elseif (isset($quote['destination']['area_label']))
                <p class="mt-0.5">Tujuan: {{ $quote['destination']['area_label'] }}</p>
            @endif
            <p class="mt-1 text-xs text-gray-500">{{ isset($quote['chosen_by']) ? 'Bisa dihitung ulang (Hitung ongkir otomatis) selama belum ada pembayaran.' : 'Tidak bisa dinaikkan ke customer; selisih biaya kurir ditanggung toko.' }}</p>
        </div>
    @endif
@endif
