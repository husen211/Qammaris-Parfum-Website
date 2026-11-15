{{--
    ORD-23 (Owner 2026-11-15): staff place the delivery pin on the same map as the customer (from a Sharelok the
    customer sent, for example). The point is written into the "Link Google Maps" field as a Google Maps link with
    coordinates, which Biteship booking reads, and is saved with the surrounding form.
--}}
@php
    $pinPoint ??= null;
    $config = ['origin' => [config('orders.shipping_quotes.origin.latitude'), config('orders.shipping_quotes.origin.longitude')],
        'kecamatan' => config('orders.shipping_quotes.kecamatan'), 'serviceArea' => config('orders.shipping_quotes.service_area'),
        'placeSearchUrl' => 'https://photon.komoot.io/api/', 'googleKey' => config('services.google_maps.browser_key') ?: null, 'point' => $pinPoint ? [$pinPoint['latitude'], $pinPoint['longitude']] : null];
@endphp
<div class="rounded-lg border border-gray-200 bg-gray-50 p-3" data-pin-field data-pin-url="{{ $urlInputId }}" data-pin-chosen-text="Titik dipilih. Simpan formulir ini supaya titik tersimpan." data-config='@json($config)'>
    <p class="text-sm font-medium text-gray-700">Titik antar <span class="font-normal text-gray-500">(Pengiriman Dalam Kota)</span></p>
    <p class="mt-0.5 text-sm text-gray-600" data-pin-status role="status" aria-live="polite">{{ $pinPoint ? 'Titik antar terpasang. Kurir Biteship menuju titik ini.' : 'Belum ada titik. Pasang di peta, atau tempel Sharelok / koordinat dari customer di kolom di bawah.' }}</p>
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
        <button type="button" data-open-map class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-900 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <span data-pin-empty @if ($pinPoint) hidden @endif>Pasang titik di peta</span>
            <span data-pin-chosen @unless ($pinPoint) hidden @endunless>Ubah titik</span>
        </button>
        <a href="{{ $pinPoint ? 'https://www.google.com/maps?q='.$pinPoint['latitude'].','.$pinPoint['longitude'] : '#' }}" target="_blank" rel="noopener noreferrer" data-pin-maps data-pin-chosen @unless ($pinPoint) hidden @endunless class="inline-flex min-h-11 items-center text-sm font-semibold text-gray-900 underline underline-offset-2">Lihat di Maps</a>
    </div>
</div>
@once
    @push('scripts')
        @include('cart._map_dialog')
    @endpush
    @vite('resources/js/order-pin.js')
@endonce
