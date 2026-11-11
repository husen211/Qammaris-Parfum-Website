{{--
    ORD-13/ORD-16: how to receive, the address (destination area, full address, and for instant couriers a map pin), then
    the priced options grouped Instan (GoSend, GrabExpress) and Reguler (J&T). The hidden `delivery` follows the chosen
    option. The radio value is a quote id the server priced and kept for this cart; "manual" means staff confirm ongkir.
--}}
@php
    $selectedKind = $shippingSelected['kind'] ?? null;
    $manualSelected = old('shipping_option') === 'manual';
    $panelClass = 'space-y-3 border border-gray-200 bg-white p-4';
    $optionCard = 'flex min-h-16 cursor-pointer items-center gap-3 border border-gray-300 bg-white p-4 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] active:bg-gray-50 has-[:checked]:border-brand-black has-[:checked]:bg-[#FAF8F3] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-black';
    $config = [
        'quoteUrl' => route('cart.checkout.shipping'),
        'areaUrl' => route('cart.checkout.areas'),
        'origin' => [config('orders.shipping_quotes.origin.latitude'), config('orders.shipping_quotes.origin.longitude')],
        'kecamatan' => config('orders.shipping_quotes.kecamatan'),
        // Place search over OpenStreetMap data (Photon), limited to the service area.
        'placeSearchUrl' => 'https://photon.komoot.io/api/',
        'serviceArea' => config('orders.shipping_quotes.service_area'),
        'logos' => \App\Support\PartnerLogos::urls(),
    ];
@endphp
@vite('resources/js/checkout-shipping.js')
@php
    // ORD-16 (Owner 2026-11-11): marketplace order: how to receive → address → shipping options with prices.
    $receive = $delivery === 'pickup' ? 'pickup' : ($delivery ? 'delivery' : null);
    $areaLabel = old('delivery_area_label', $selectedKind === 'jnt' ? ($shippingSelected['area_label'] ?? '') : '');
    $sectionTitle = 'font-mayluxa text-xl text-brand-black';
@endphp
<div data-shipping-quotes data-config='@json($config)' class="space-y-5">
    <input type="hidden" name="delivery" value="{{ $delivery }}" data-delivery-value>
    <fieldset @if ($errors->has('delivery')) aria-describedby="delivery-error" @endif>
        <legend class="text-sm font-medium text-brand-black">Cara menerima <span aria-hidden="true">*</span></legend>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="{{ $choiceClass }}">
                <input type="radio" name="receive" value="delivery" @checked($receive === 'delivery') required class="mt-1 h-5 w-5 shrink-0 accent-brand-black" data-receive>
                <span class="min-w-0 flex-1">
                    <span class="block text-base font-medium text-brand-black">Dikirim ke alamat</span>
                    <span class="mt-0.5 block text-sm leading-5 text-gray-600">Instan di Palu, atau reguler ke seluruh Indonesia.</span>
                    <span class="mt-2 flex flex-wrap items-center gap-3"><x-partner-logo name="gosend" class="h-4" /><x-partner-logo name="grab" class="h-4" /><x-partner-logo name="jnt" class="h-4" /></span>
                </span>
            </label>
            <label class="{{ $choiceClass }}">
                <input type="radio" name="receive" value="pickup" @checked($receive === 'pickup') required class="mt-1 h-5 w-5 shrink-0 accent-brand-black" data-receive>
                <span class="min-w-0 flex-1">
                    <span class="block text-base font-medium text-brand-black">Ambil di toko</span>
                    <span class="mt-0.5 block text-sm leading-5 text-gray-600">Ambil sendiri di toko Qammaris setelah pesanan dikonfirmasi. Tanpa ongkir.</span>
                </span>
            </label>
        </div>
        @error('delivery') <p id="delivery-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
    </fieldset>

    <div data-delivery-section @if ($receive !== 'delivery') hidden @endif class="space-y-6">
        {{-- Address first: the destination decides which couriers can go there. --}}
        <section class="{{ $panelClass }}" aria-labelledby="address-title">
            <h3 id="address-title" class="{{ $sectionTitle }}">Alamat pengiriman</h3>
            <div>
                <label for="delivery-area" class="block text-sm font-medium text-brand-black">Kecamatan atau kota <span aria-hidden="true">*</span></label>
                <div class="relative mt-2" data-area-combobox>
                    <input id="delivery-area" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="delivery-area-list" autocomplete="off" enterkeyhint="search"
                        value="{{ $areaLabel }}" placeholder="Contoh: Palu Barat atau Makassar" aria-describedby="delivery-area-help"
                        class="min-h-12 w-full border border-gray-300 bg-white px-3 py-3 text-base text-brand-black focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black">
                    <ul id="delivery-area-list" role="listbox" hidden class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto border border-brand-black bg-white shadow-lg"></ul>
                </div>
                <input type="hidden" name="delivery_area_id" value="{{ old('delivery_area_id') }}" data-area-id>
                <input type="hidden" name="delivery_area_label" value="{{ $areaLabel }}" data-area-label>
                <p id="delivery-area-help" class="mt-2 text-xs leading-5 text-gray-500">Ketik minimal 3 huruf, lalu pilih dari daftar.</p>
            </div>
            <div>
                <label for="customer_address" class="block text-sm font-medium text-brand-black">Alamat lengkap <span aria-hidden="true">*</span></label>
                <textarea id="customer_address" name="customer_address" rows="3" @if ($receive === 'delivery') required @endif minlength="10" maxlength="500" autocomplete="street-address" aria-describedby="address-help{{ $errors->has('customer_address') ? ' customer_address-error' : '' }}" aria-invalid="{{ $errors->has('customer_address') ? 'true' : 'false' }}" class="mt-2 w-full resize-y border border-gray-300 bg-white px-3 py-3 text-base leading-6 focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black">{{ old('customer_address') }}</textarea>
                <p id="address-help" class="mt-2 text-xs leading-5 text-gray-500">Nama jalan, nomor rumah, dan patokan supaya kurir mudah menemukan alamat.</p>
                @error('customer_address') <p id="customer_address-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            {{-- Instant couriers need the exact spot; only offered for destinations they reach. --}}
            <div data-instant-pin @if ($selectedKind !== 'instant') hidden @endif class="space-y-2">
                <p class="text-sm font-medium text-brand-black">Titik antar <span class="font-normal text-gray-500">(untuk kurir instan)</span></p>
                <button type="button" data-open-location @if ($selectedKind === 'instant') hidden @endif class="flex min-h-12 w-full items-center justify-center gap-2 border border-brand-black bg-white px-4 text-base font-medium text-brand-black [touch-action:manipulation] active:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                    <svg aria-hidden="true" class="h-5 w-5 text-[#8A6A2E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    Tentukan titik di peta
                </button>
                <div data-pin-summary @if ($selectedKind !== 'instant') hidden @endif class="flex flex-wrap items-center justify-between gap-2 bg-[#FAF8F3] px-3 py-2 text-sm text-brand-black">
                    <span class="flex items-center gap-2"><svg aria-hidden="true" class="h-4 w-4 shrink-0 text-[#8A6A2E]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a8 8 0 0 0-8 8c0 6 8 12 8 12s8-6 8-12a8 8 0 0 0-8-8Zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg><span data-pin-text>{{ isset($shippingSelected['distance_km']) ? 'Titik antar dipilih · ±'.number_format($shippingSelected['distance_km'], 1, ',', '.').' km dari toko' : 'Titik antar dipilih' }}</span></span>
                    <button type="button" data-change-pin class="min-h-11 px-2 font-medium underline underline-offset-4">Ubah titik</button>
                </div>
            </div>
        </section>

        {{-- Then the shipping options with their prices, grouped like marketplaces: Instan, Reguler. --}}
        <section class="{{ $panelClass }}" aria-labelledby="shipping-title" data-shipping-choices>
            <h3 id="shipping-title" class="{{ $sectionTitle }}">Pilih pengiriman</h3>
            <p data-shipping-hint class="text-sm leading-6 text-gray-600" @if ($selectedKind) hidden @endif>Isi kecamatan atau kota dulu, lalu pilihan pengiriman dan ongkirnya muncul di sini.</p>
            <div data-shipping-group="instant" @if ($selectedKind !== 'instant') hidden @endif class="space-y-2">
                <p class="text-sm font-semibold text-brand-black">Instan <span class="font-normal text-gray-600">· tiba di hari yang sama</span></p>
                <div data-shipping-options role="radiogroup" aria-label="Kurir instan" class="space-y-2">
                    @if ($selectedKind === 'instant')@include('cart._shipping_option', ['option' => $shippingSelected, 'checked' => true])@endif
                </div>
                <p data-shipping-status role="status" aria-live="polite" class="text-sm leading-6 text-gray-600"></p>
            </div>
            <div data-shipping-group="jnt" @if ($selectedKind !== 'jnt') hidden @endif class="space-y-2">
                <p class="text-sm font-semibold text-brand-black">Reguler <span class="font-normal text-gray-600">· tiba 2–3 hari</span></p>
                <div data-shipping-options role="radiogroup" aria-label="Kurir reguler" class="space-y-2">
                    @if ($selectedKind === 'jnt')@include('cart._shipping_option', ['option' => $shippingSelected, 'checked' => true])@endif
                </div>
                <p data-shipping-status role="status" aria-live="polite" class="text-sm leading-6 text-gray-600"></p>
            </div>

            {{-- Fallback when no courier price is available: staff confirm ongkir in WhatsApp, as before ORD-13. --}}
            <label data-manual-option @if (! $manualSelected) hidden @endif class="{{ $optionCard }}">
                <input type="radio" name="shipping_option" value="manual" @checked($manualSelected) @disabled(! $manualSelected) class="h-5 w-5 shrink-0 accent-brand-black" data-shipping-choice data-kind="manual" data-charged="">
                <span class="flex-1"><span class="block text-base font-medium text-brand-black">Ongkir dikonfirmasi admin</span><span class="block text-sm leading-5 text-gray-600">Admin menghitung ongkir dan mengabarinya di WhatsApp.</span></span>
            </label>
            @error('shipping_option') <p id="shipping_option-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
        </section>
    </div>

    <template data-option-template>@include('cart._shipping_option', ['option' => null, 'checked' => false])</template>
</div>

{{-- Location pop-up: our own explanation before the phone's permission prompt. Centered, never full width. --}}
<dialog data-location-dialog aria-labelledby="location-title" class="m-auto w-[calc(100%-3rem)] max-w-xs bg-white p-0 text-brand-black shadow-2xl">
    <div class="relative max-h-[calc(100dvh-2rem)] overflow-y-auto px-6 pb-6 pt-7 text-center">
        <button type="button" data-dialog-close aria-label="Tutup" class="absolute right-2 top-2 flex h-11 w-11 items-center justify-center text-gray-500 active:bg-gray-100">
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#FAF8F3]">
            <svg aria-hidden="true" class="h-7 w-7 text-[#8A6A2E]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a8 8 0 0 0-8 8c0 6 8 12 8 12s8-6 8-12a8 8 0 0 0-8-8Zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>
        </span>
        <h2 id="location-title" class="mt-4 font-mayluxa text-2xl leading-tight">Antar ke lokasimu sekarang?</h2>
        <p class="mt-2 text-sm leading-6 text-gray-700">Izinkan lokasi supaya ongkir GoSend dan GrabExpress langsung dihitung dari titikmu. Lokasi hanya dipakai untuk pesanan ini.</p>
        <button type="button" data-allow-location class="mt-5 flex min-h-14 w-full items-center justify-center gap-2 bg-brand-black px-4 text-base font-semibold text-white [touch-action:manipulation] active:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="7"/></svg>
            Izinkan lokasi
        </button>
        <button type="button" data-pick-manually class="mt-3 flex min-h-12 w-full items-center justify-center border border-gray-300 bg-white px-4 text-base font-medium text-brand-black [touch-action:manipulation] active:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
            Pilih titik sendiri di peta
        </button>
        <p class="mt-4 text-xs leading-5 text-gray-500">Setelah ini HP menanyakan izin lokasi. Pilih <strong class="font-semibold text-gray-700">Izinkan</strong>.</p>
    </div>
</dialog>

{{-- Map: the pin stays in the middle and the customer moves the map under it, like ride-hailing apps. --}}
<dialog data-map-dialog aria-labelledby="map-title" class="m-auto h-[min(42rem,calc(100dvh-1.5rem))] w-[calc(100%-1.5rem)] max-w-2xl bg-white p-0 text-brand-black shadow-2xl">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between gap-2 border-b border-gray-200 py-1 pl-4 pr-1">
            <h2 id="map-title" class="font-mayluxa text-xl">Titik antar</h2>
            <button type="button" data-dialog-close aria-label="Tutup peta" class="flex h-11 w-11 items-center justify-center text-gray-500 active:bg-gray-100">
                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="relative px-4 pt-3" data-place-search>
            <label for="place-search" class="sr-only">Cari alamat, jalan, atau tempat</label>
            <svg aria-hidden="true" class="pointer-events-none absolute left-7 top-1/2 mt-1.5 h-5 w-5 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="place-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="place-search-list" autocomplete="off" enterkeyhint="search"
                placeholder="Cari jalan, gedung, atau tempat" class="min-h-12 w-full border border-gray-300 bg-white pl-10 pr-3 text-base text-brand-black focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black">
            <ul id="place-search-list" role="listbox" hidden class="absolute inset-x-4 top-full z-[1100] mt-1 max-h-64 overflow-y-auto border border-brand-black bg-white shadow-lg"></ul>
        </div>
        <div class="flex gap-2 overflow-x-auto border-b border-gray-100 px-4 py-2 [scrollbar-width:none]" aria-label="Lompat ke kecamatan" data-kecamatan-list></div>
        <div class="relative min-h-0 flex-1 bg-[#eef0ea]">
            <div data-map class="qm-map absolute inset-0" aria-label="Peta. Geser peta sampai pin berada di titik antar." role="application"></div>
            <div data-map-pin class="pointer-events-none absolute left-1/2 top-1/2 z-[500] flex -translate-x-1/2 -translate-y-full flex-col items-center">
                <span class="mb-1 whitespace-nowrap bg-brand-black px-2.5 py-1 text-xs font-semibold text-white">Antar ke sini</span>
                <svg aria-hidden="true" width="34" height="44" viewBox="0 0 34 44"><path d="M17 43C17 43 33 27.5 33 16.5A16 16 0 0 0 1 16.5C1 27.5 17 43 17 43Z" fill="#C5A059" stroke="#1a1a1a" stroke-width="2"/><circle cx="17" cy="16.5" r="6" fill="#fff" stroke="#1a1a1a" stroke-width="2"/></svg>
            </div>
            <div class="pointer-events-none absolute left-1/2 top-1/2 z-[499] h-2 w-4 -translate-x-1/2 -translate-y-1/2 rounded-[50%] bg-black/25"></div>
            <button type="button" data-locate-me class="absolute bottom-4 left-4 z-[500] flex min-h-11 items-center gap-2 rounded-full border border-gray-300 bg-white py-2 pl-3 pr-4 text-sm font-semibold text-brand-black shadow-md [touch-action:manipulation] active:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                <svg aria-hidden="true" class="h-5 w-5 shrink-0 text-[#8A6A2E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="7"/></svg>
                Temukan lokasi saya
            </button>
            <p data-map-loading class="absolute inset-0 z-[400] flex items-center justify-center text-sm text-gray-600">Memuat peta…</p>
        </div>
        <div class="border-t border-gray-200 px-4 pb-4 pt-3">
            <p data-map-message role="status" aria-live="polite" class="text-sm leading-5 text-gray-700">Cari alamat, pilih kecamatan, atau geser peta sampai pin tepat di titik antar.</p>
            <button type="button" data-use-pin class="mt-3 flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-base font-semibold text-white [touch-action:manipulation] active:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:bg-gray-300 disabled:text-gray-600">Pakai titik ini</button>
        </div>
    </div>
</dialog>
