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
