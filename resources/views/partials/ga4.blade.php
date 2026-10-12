@php($ga4Id = (string) config('analytics.ga4_measurement_id'))
{{-- Only discovery pages: never checkout, forms, private links, or the Admin. --}}
@if (app()->environment('production') && preg_match('/^G-[A-Z0-9]{10}$/', $ga4Id) && request()->routeIs('home', 'products.index', 'products.show', 'blog.index', 'blog.show', 'blog.category', 'store.location', 'store.about'))
    <aside data-ga4-consent data-measurement-id="{{ $ga4Id }}" aria-label="Pilihan statistik kunjungan" hidden
        class="fixed bottom-4 left-4 right-4 z-[110] border border-gray-300 bg-white p-4 text-sm text-brand-black shadow-sm sm:right-auto sm:max-w-md">
        <p class="font-semibold">Statistik kunjungan</p>
        <p class="mt-2 text-gray-700">Google Analytics memakai cookie untuk statistik kunjungan dan perkiraan kota. Anda bisa memilih untuk mengizinkannya.</p>
        <a href="https://policies.google.com/technologies/partner-sites?hl=id" target="_blank" rel="noopener noreferrer"
            class="mt-2 inline-flex min-h-11 items-center underline underline-offset-4">Tentang privasi Google Analytics</a>
        <div class="mt-2 flex flex-wrap gap-3">
            <button type="button" data-ga4-accept class="min-h-11 flex-1 border border-brand-black bg-brand-black px-4 py-2 font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold">Izinkan</button>
            <button type="button" data-ga4-decline class="min-h-11 flex-1 border border-brand-black bg-white px-4 py-2 font-medium text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold">Lewati</button>
        </div>
    </aside>
    <button type="button" data-ga4-preferences hidden class="fixed bottom-3 left-4 z-[110] min-h-11 border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold">Pilihan analytics</button>
    @vite('resources/js/ga4.js')
@endif
