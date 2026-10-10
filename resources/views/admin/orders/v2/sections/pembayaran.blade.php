@use('App\Models\OnlineOrder')
@use('App\Support\OnlineOrderLabels')
{{-- ORD-03 payment: one form (amount, method, Majoo). The ledger keeps the audit source internally. Ongkir sits here because it changes the total. --}}
@php
    $paymentSummary = match (true) {
        $order->payment_status === 'paid' => 'Lunas'.($order->recorded_in_majoo ? ' · tercatat di Majoo' : ''),
        $received > 0 => 'Dibayar '.$rp($received).' · sisa '.$rp($remainingCents),
        default => 'Belum dibayar · '.$rp($totalCents),
    };
    $canChargeShipping = auth()->user()->can('orders.charge-shipping', $order);
@endphp
<details id="pembayaran" class="{{ $card }}" @if ($openSection) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900">Pembayaran</span><span class="block text-sm text-gray-600">{{ $paymentSummary }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'pembayaran'])
    @if ($order->payments->isNotEmpty())
        <ul class="mt-3 divide-y divide-gray-100 text-sm" aria-label="Catatan pembayaran">
            @foreach ($order->payments as $entry)
                @php($reversed = $order->payments->contains('reverses_id', $entry->id))
                <li class="flex flex-wrap items-start justify-between gap-2 py-2 {{ $reversed ? 'text-gray-400 line-through' : '' }}">
                    <span class="min-w-0">
                        <span class="block font-medium {{ $entry->basis === 'reversal' ? 'text-red-700' : 'text-gray-900' }}">{{ $entry->basis === 'reversal' ? 'Pembatalan entri' : ($entry->type === 'refund' ? 'Refund' : 'Pembayaran') }}</span>
                        <span class="block text-xs text-gray-500">{{ OnlineOrder::PAYMENT_METHODS[$entry->method] ?? '' }} · {{ $entry->recorder?->name }} · {{ $time($entry->created_at) }}</span>
                    </span>
                    <span class="shrink-0 font-semibold tabular-nums">{{ $entry->basis === 'reversal' ? '−' : '' }}{{ format_rupiah($entry->amount) }}</span>
                    @can('orders.refund')
                        @if ($entry->basis !== 'reversal' && ! $reversed)
                            <details class="w-full">
                                <summary class="inline-flex min-h-9 cursor-pointer items-center text-xs font-semibold text-red-700">Batalkan entri ini…</summary>
                                <form method="POST" action="{{ route('admin.orders.money.reverse', [$order, $entry->id]) }}" class="mt-2 flex flex-wrap items-end gap-2" data-confirm="Batalkan entri ini? Entri asli tetap terlihat.">
                                    @csrf {!! $hidden('pembayaran') !!}
                                    <label class="min-w-0 flex-1 text-xs font-medium text-gray-700">Alasan<input name="reason" required maxlength="300" class="{{ $input }}"></label>
                                    <button class="{{ $secondary }}" data-busy-label="Membatalkan…">Batalkan entri</button>
                                </form>
                            </details>
                        @endif
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @if ($order->lifecycle === 'draft')
        <p class="mt-3 text-sm text-gray-600">Pembayaran dicatat setelah pesanan website dikonfirmasi.@if ($order->payment_preference) Customer memilih: <strong>{{ config('orders.payment_preferences')[$order->payment_preference] ?? $order->payment_preference }}</strong>.@endif</p>
    @elseif ($order->lifecycle !== 'cancelled' && $remainingCents > 0)
        <form method="POST" action="{{ route('admin.orders.v2.payments', $order) }}" class="mt-3 space-y-3">
            @csrf {!! $hidden('pembayaran') !!}
            @if ($order->payment_preference)
                <p class="text-sm text-gray-700">Customer memilih: <strong>{{ config('orders.payment_preferences')[$order->payment_preference] ?? $order->payment_preference }}</strong></p>
            @endif
            <div class="grid grid-cols-2 gap-3">
                <label class="text-sm font-medium text-gray-700">Nominal (Rp)
                    <input name="amount" inputmode="numeric" required value="{{ old('_section') === 'pembayaran' ? old('amount') : (int) ($remainingCents / 100) }}" class="{{ $input }}">
                </label>
                <label class="text-sm font-medium text-gray-700">Metode
                    <select name="payment_method" required class="{{ $input }}">
                        @foreach (OnlineOrder::PAYMENT_METHODS as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', $order->payment_preference ?? $order->payment_method ?? 'transfer') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="flex min-h-11 items-center gap-3 text-sm text-gray-700"><input type="checkbox" name="recorded_in_majoo" value="1" @checked($order->recorded_in_majoo) class="h-5 w-5"> Sudah dicatat di Majoo</label>
            <button class="{{ $primary }}" data-busy-label="Menyimpan…">{{ $received === 0 ? 'Tandai Lunas' : 'Catat pembayaran' }}</button>
        </form>
    @endif

    {{-- Ongkir charged to the customer. --}}
    <details class="mt-3 border-t border-gray-100 pt-2" @if (old('_section') === 'pembayaran' && old('shipping_payer') !== null) open @endif>
        <summary class="{{ $quiet }} cursor-pointer">Ongkir: {{ $order->shipping_fee !== null ? format_rupiah($order->shipping_fee) : 'belum diisi' }}{{ $order->shipping_payer ? ' · '.OnlineOrder::SHIPPING_PAYERS[$order->shipping_payer] : '' }}</summary>
        @if ($canChargeShipping)
            <form method="POST" action="{{ route('admin.orders.v2.shipping', $order) }}" class="mt-2 space-y-3">
                @csrf {!! $hidden('pembayaran') !!}
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium text-gray-700">Ongkir (Rp)<input name="shipping_fee" inputmode="numeric" value="{{ old('shipping_fee', $order->shipping_fee !== null ? (int) $order->shipping_fee : null) }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-gray-700">Dibayar
                        <select name="shipping_payer" class="{{ $input }}"><option value="">Belum ditentukan</option>@foreach (OnlineOrder::SHIPPING_PAYERS as $value => $label)<option value="{{ $value }}" @selected(old('shipping_payer', $order->shipping_payer) === $value)>{{ $label }}</option>@endforeach</select>
                    </label>
                </div>
                @can('orders.finance')
                    <label class="block text-sm font-medium text-gray-700">Toko bayar driver dengan
                        <select name="driver_funding" class="{{ $input }}"><option value="">Tidak perlu / belum ditentukan</option>@foreach (OnlineOrder::DRIVER_FUNDING as $value => $label)<option value="{{ $value }}" @selected(old('driver_funding', $order->driver_funding) === $value)>{{ $label }}</option>@endforeach</select>
                    </label>
                @endcan
                <button class="{{ $secondary }}" data-busy-label="Menyimpan…">Simpan ongkir</button>
            </form>
        @else
            <p class="mt-2 text-sm text-gray-600">{{ $received > 0 ? 'Sudah ada pembayaran. Ubah total lewat Opsi lanjutan → penyesuaian harga (disetujui Super Admin).' : 'Ongkir diatur Super Admin.' }}</p>
        @endif
    </details>
</details>
