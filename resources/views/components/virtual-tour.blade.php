{{-- TOUR-01: walkable 360 store tour. A cover with "Masuk" on the page; the tour opens full screen. --}}
@php
    $tour = \App\Support\StoreVirtualTour::data();
    $start = collect($tour['spots'])->firstWhere('id', $tour['start']);
@endphp

@pushOnce('styles')
    @vite('resources/css/virtual-tour.css')
@endPushOnce
@pushOnce('scripts')
    @vite('resources/js/virtual-tour.js')
@endPushOnce

<div class="vt" id="virtual-tour" data-virtual-tour>
    <script type="application/json" data-tour-data>{!! json_encode($tour, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

    <div class="vt-cover" data-tour-cover>
        <img src="{{ asset('images/virtual-tour/cover.webp') }}" width="2400" height="992" loading="lazy" decoding="async"
             alt="Bagian dalam toko Qammaris di Palu, dilihat dari pintu masuk" class="vt-cover-img">
        <div class="vt-cover-body">
            <p class="vt-eyebrow">Virtual tour 360°</p>
            <h2 class="vt-title" id="virtual-tour-title">Masuk ke toko Qammaris</h2>
            <p class="vt-lead">Lihat rak parfum kami di Palu dan berjalan dari pintu masuk sampai meja kasir, langsung dari layar Anda.</p>
            <div class="vt-cover-actions">
                <button type="button" class="vt-enter" data-tour-enter aria-describedby="virtual-tour-title">
                    <span>Masuk</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <p class="vt-meta">{{ count($tour['spots']) }} area · {{ collect($tour['spots'])->pluck('name')->join(', ', ' dan ') }}</p>
            </div>
        </div>
    </div>

    <div class="vt-fallback" data-tour-fallback hidden>
        <p class="vt-fallback-note">Perangkat ini tidak bisa menampilkan foto 360. Berikut tampilan setiap area toko.</p>
        <div class="vt-fallback-grid">
            @foreach($tour['spots'] as $spot)
                <figure>
                    <img src="{{ $spot['preview'] }}" width="1200" height="675" loading="lazy" decoding="async" alt="Area {{ $spot['name'] }} di toko Qammaris Palu">
                    <figcaption>{{ $spot['name'] }}</figcaption>
                </figure>
            @endforeach
        </div>
    </div>
    <noscript><style>.vt-enter{display:none!important}.vt-fallback{display:block!important}.vt-fallback-note{display:none}</style></noscript>

    <dialog class="vt-dialog" data-tour-dialog aria-label="Virtual tour toko Qammaris">
        {{-- Square render of the start view (100° wide, same yaw/pitch); the viewer matches its zoom to hand over seamlessly. --}}
        <img class="vt-intro" data-tour-intro src="{{ asset('images/virtual-tour/intro.webp') }}" alt="" width="1400" height="1400" loading="lazy" decoding="async" data-tan="1.19175">
        <div class="vt-stage" data-tour-stage tabindex="0" role="application"
             aria-label="Foto 360 toko. Gunakan tombol panah untuk melihat sekeliling, tombol plus dan minus untuk zoom.">
            <div class="vt-canvas" data-tour-canvas></div>
            <div class="vt-hotspots" data-tour-hotspots></div>
        </div>

        <div class="vt-top">
            <div>
                <p class="vt-top-count" data-tour-count>Area 1 dari {{ count($tour['spots']) }}</p>
                <p class="vt-top-name" data-tour-name>{{ $start['name'] }}</p>
            </div>
            <button type="button" class="vt-close" data-tour-close>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span>Keluar</span>
            </button>
        </div>

        <p class="vt-status" data-tour-status>Memuat foto 360…</p>
        <div class="vt-error" data-tour-error hidden>
            <p>Foto 360 gagal dimuat. Periksa koneksi internet Anda.</p>
            <button type="button" class="vt-retry" data-tour-retry>Coba lagi</button>
        </div>
        <p class="vt-hint" data-tour-hint hidden>Geser untuk melihat sekeliling · ketuk panah di lantai untuk berjalan</p>

        <div class="vt-bottom">
            <div class="vt-zoom" role="group" aria-label="Zoom kamera">
                <button type="button" data-tour-zoom="0.5" aria-pressed="true" aria-label="Zoom 0,5 kali, paling lebar">0,5×</button>
                <button type="button" data-tour-zoom="1" aria-pressed="false" aria-label="Zoom 1 kali">1×</button>
            </div>
            <div class="vt-areas" role="group" aria-label="Pilih area">
                @foreach($tour['spots'] as $spot)
                    <button type="button" class="vt-area" data-tour-goto="{{ $spot['id'] }}" aria-pressed="{{ $spot['id'] === $tour['start'] ? 'true' : 'false' }}">{{ $spot['name'] }}</button>
                @endforeach
            </div>
        </div>
        <p class="sr-only" aria-live="polite" data-tour-live></p>
    </dialog>
</div>
