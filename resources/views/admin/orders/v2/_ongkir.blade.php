@use('App\Models\OnlineOrder')
{{-- Ongkir charged to the customer (ORD-03/04). Shown open while it is still empty, collapsed once set. What the courier
     is paid, and whose money, is entered when the courier is booked (ORD-08, Pengiriman). --}}
@php
    $canChargeShipping = auth()->user()->can('orders.charge-shipping', $order);
    $ongkirSet = $order->shipping_fee !== null && $order->shipping_payer !== null;
@endphp
@if ($order->fulfillment !== 'pickup')
    <details class="mt-3 rounded-lg border border-gray-200 p-3" @if (! $ongkirSet || (old('_section') === $section && old('shipping_payer') !== null)) open @endif data-ongkir>
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 text-sm">
            <span><span class="font-semibold text-gray-900">Ongkir</span> <span class="text-gray-600">{{ $order->shipping_fee !== null ? format_rupiah($order->shipping_fee) : 'belum diisi' }}{{ $order->shipping_payer ? ' · '.OnlineOrder::SHIPPING_PAYERS[$order->shipping_payer] : '' }}</span></span>
            <span aria-hidden="true" class="text-gray-400">{{ $ongkirSet ? 'Ubah' : '' }} ▾</span>
        </summary>
        @if ($canChargeShipping)
            <form method="POST" action="{{ route('admin.orders.v2.shipping', $order) }}" class="mt-2 space-y-3">
                @csrf {!! $hidden($section) !!}
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium text-gray-700">Ongkir (Rp)<input name="shipping_fee" data-rupiah-input inputmode="numeric" value="{{ old('shipping_fee', $order->shipping_fee !== null ? (int) $order->shipping_fee : null) }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-gray-700">Dibayar
                        <select name="shipping_payer" class="{{ $input }}"><option value="">Belum ditentukan</option>@foreach (OnlineOrder::SHIPPING_PAYERS as $value => $label)<option value="{{ $value }}" @selected(old('shipping_payer', $order->shipping_payer) === $value)>{{ $label }}</option>@endforeach</select>
                    </label>
                </div>
                <button class="{{ $ongkirSet || $section === 'konfirmasi' ? $secondary : $primary }}" data-busy-label="Menyimpan…">Simpan ongkir</button>
            </form>
        @else
            <p class="mt-2 text-sm text-gray-600">{{ $received > 0 ? 'Sudah ada pembayaran. Ubah total lewat Opsi lanjutan → penyesuaian harga.' : 'Ongkir diatur Super Admin.' }}</p>
        @endif
    </details>
@endif
