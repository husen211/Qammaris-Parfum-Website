@use('App\Actions\Orders\OnlineOrderFulfillment')
@use('App\Support\OnlineOrderLabels')
{{-- ORD-14: instant courier booked through Biteship. Status, driver and tracking come from Biteship; staff refresh or cancel here. --}}
@php
    $booking = $order->courier_booking ?? [];
    $state = $booking['state'] ?? null;
    $quote = $order->shipping_quote ?? [];
    $customerCourier = ($quote['provider'] ?? null) === 'grab' ? 'grab' : 'gojek';
    // The customer's courier is always the default; staff switch for a retry (for example after "driver tidak ditemukan").
    $chosen = old('courier', $customerCourier);
    $stale = $state === 'booking' && now()->getTimestamp() - (int) ($booking['started_at'] ?? 0) >= 120;
    $carrying = in_array($booking['status'] ?? null, OnlineOrderFulfillment::BITESHIP_CARRYING, true);
@endphp
@if ($state === 'active')
    <div class="mt-3 rounded-lg border border-gray-200 p-3" data-biteship-status="{{ $booking['status'] ?? '' }}">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ OnlineOrderFulfillment::biteshipLabel($booking['courier'] ?? null) }} · Biteship</p>
                <p class="mt-0.5 text-base font-semibold text-gray-900">{{ OnlineOrderLabels::BITESHIP[$booking['status'] ?? ''] ?? 'Menunggu status' }}</p>
            </div>
            @if (! empty($booking['price']))<p class="text-sm tabular-nums text-gray-700">Ongkir kurir {{ format_rupiah($booking['price']) }}</p>@endif
        </div>
        @if (! empty($booking['driver_name']))
            <p class="mt-2 text-sm text-gray-800" data-biteship-driver>
                Driver <span class="font-semibold">{{ $booking['driver_name'] }}</span>@if (! empty($booking['driver_plate'])) · <span class="font-mono">{{ $booking['driver_plate'] }}</span>@endif
                @if (! empty($booking['driver_phone'])) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking['driver_phone']) }}" class="underline underline-offset-2">{{ $booking['driver_phone'] }}</a>@endif
            </p>
        @else
            <p class="mt-2 text-sm text-gray-600">Driver belum ditemukan. Status berubah sendiri saat Biteship mengabari.</p>
        @endif
        <div class="mt-3 flex flex-wrap gap-2">
            @if (! empty($booking['link']))<a href="{{ $booking['link'] }}" target="_blank" rel="noopener noreferrer" class="{{ $secondary }}" data-biteship-track>Lacak driver</a>@endif
            <form method="POST" action="{{ route('admin.orders.v2.biteship.refresh', $order) }}">
                @csrf {!! $hidden('pengiriman') !!}
                <button class="{{ $secondary }}" data-busy-label="Memperbarui…" data-biteship-refresh>Perbarui status</button>
            </form>
        </div>
        @if ($pending && ! $carrying && $active)
            <details class="mt-3 border-t border-gray-100 pt-2" @if (old('_section') === 'pengiriman' && old('reason') !== null) open @endif>
                <summary class="{{ $quiet }} -ml-3 cursor-pointer text-red-700">Batalkan kurir…</summary>
                <form method="POST" action="{{ route('admin.orders.v2.biteship.cancel', $order) }}" class="mt-2 space-y-2" data-confirm="Batalkan driver ini? Saldo Biteship dikembalikan oleh Biteship." data-biteship-cancel>
                    @csrf {!! $hidden('pengiriman') !!}
                    <label class="block text-sm font-medium text-gray-700">Alasan
                        <input name="reason" required maxlength="200" value="{{ old('reason') }}" placeholder="Misalnya: driver terlalu lama" class="{{ $input }}">
                    </label>
                    @error('reason') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button class="{{ $secondary }}" data-busy-label="Membatalkan…">Batalkan kurir</button>
                </form>
            </details>
        @endif
    </div>
@elseif ($pending && $active && $order->courier_status === 'unassigned')
    <form method="POST" action="{{ route('admin.orders.v2.biteship.book', $order) }}" class="mt-3 rounded-lg border border-gray-200 p-3" data-biteship-book data-confirm="Pesan driver sekarang? Saldo Biteship terpotong sesuai ongkir kurir.">
        @csrf {!! $hidden('pengiriman') !!}
        <p class="text-sm font-semibold text-gray-900">Pesan kurir lewat Biteship</p>
        <p class="mt-0.5 text-xs text-gray-600">Customer memilih {{ OnlineOrderFulfillment::biteshipLabel($customerCourier) }}@if (isset($quote['destination']['distance_km'])) · titik antar ±{{ str_replace('.', ',', (string) $quote['destination']['distance_km']) }} km dari toko @endif · ongkir customer {{ format_rupiah($order->shipping_fee) }}</p>
        @if ($state === 'failed')
            <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert" data-biteship-error>Pemesanan terakhir gagal: {{ $booking['error'] ?? 'Biteship tidak menjawab.' }}</p>
        @elseif ($state === 'ended')
            <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" data-biteship-ended>Kurir sebelumnya: {{ OnlineOrderLabels::BITESHIP[$booking['status'] ?? ''] ?? 'selesai' }}. Pesan ulang bila perlu.</p>
        @elseif ($state === 'booking')
            <p class="mt-2 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700" data-biteship-pending>{{ $stale ? 'Hasil pemesanan sebelumnya belum pasti. Cek dashboard Biteship dulu supaya tidak dobel, lalu pesan ulang bila belum ada.' : 'Sedang memesan kurir… muat ulang halaman sebentar lagi.' }}</p>
        @endif
        <fieldset class="mt-3">
            <legend class="text-sm text-gray-700">Kurir</legend>
            <div class="mt-1 grid grid-cols-2 gap-2">
                @foreach (['gojek', 'grab'] as $value)
                    <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium has-[:checked]:border-black has-[:checked]:bg-gray-50">
                        <input type="radio" name="courier" value="{{ $value }}" @checked($chosen === $value) class="h-4 w-4 shrink-0">
                        <span class="min-w-0"><span class="block">{{ OnlineOrderFulfillment::biteshipLabel($value) }}</span>@if ($value === $customerCourier)<span class="block text-xs font-normal text-gray-500">pilihan customer</span>@endif</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <button class="{{ $primary }} mt-3" @disabled($state === 'booking' && ! $stale) data-busy-label="Memesan…">Pesan kurir sekarang</button>
    </form>
@endif
