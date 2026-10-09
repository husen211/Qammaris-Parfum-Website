@extends('layouts.app')

@section('title', 'Tentang Qammaris — Experience Store Parfum di Palu')
@section('meta_description', 'Kenali cerita Qammaris Perfumes di Palu. Coba tester setiap produk, bandingkan aroma, dan berdiskusi dengan staf untuk menemukan parfum sesuai kebutuhan Anda.')
@section('canonical_url', route('store.about'))
@section('og_image', asset('images/store/facade-1200.webp'))

@push('styles')
    @vite('resources/css/about.css')
@endpush
@push('jsonld')
    <script type="application/ld+json">{!! json_encode($aboutSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
<div class="qammaris-about">
    {{-- Hero10: felipemenezes098 / 21st demo19079. One full Owner-supplied photo replaces the image fan. --}}
    <section class="about-hero10" aria-labelledby="about-title" data-about-hero10>
        <div class="about-container about-hero10-content">
            <div class="about-hero10-header">
                <h1 id="about-title">Coba dulu.<br><span>Temukan yang cocok.</span></h1>
                <p class="about-lead">Experience store parfum di Palu. Coba koleksinya langsung, bandingkan aroma, dan diskusikan pilihan Anda dengan kami.</p>
            </div>
            <div class="about-hero10-actions">
                <div class="about-actions"><a href="{{ route('store.location') }}" class="about-button about-button-primary">Kunjungi toko <x-icon name="arrow-up-right" /></a><a href="#cerita" class="about-button about-button-outline">Kenali Qammaris <x-icon name="arrow-down" /></a></div>
                <p class="about-hero10-hours">Sabtu–Kamis · 09.00–21.00 WITA</p>
            </div>
            <figure class="about-hero10-photo" data-hero10-photo>
                <img src="{{ asset('images/store/storefront-hero-1200.webp') }}" srcset="{{ asset('images/store/storefront-hero-480.webp') }} 480w, {{ asset('images/store/storefront-hero-768.webp') }} 768w, {{ asset('images/store/storefront-hero-1200.webp') }} {{ $aboutMedia['storefront-hero']['width'] }}w" sizes="(min-width: 408px) 360px, calc(100vw - 48px)" width="{{ $aboutMedia['storefront-hero']['width'] }}" height="{{ $aboutMedia['storefront-hero']['height'] }}" alt="Fasad toko Qammaris Perfumes di Palu dengan papan nama dan pintu masuk menuju ruang tester parfum" fetchpriority="high" decoding="async">
            </figure>
        </div>
    </section>

    {{-- ScrollSpy23554 + ScrollProgress18715: native links and a contained mobile rail. --}}
    <div class="about-nav-wrap">
        <nav class="about-section-nav about-container" aria-label="Bagian halaman tentang Qammaris" data-about-spy>
            <span class="about-nav-indicator" aria-hidden="true" hidden></span>
            <a href="#cerita">Cerita kami</a><a href="#pengalaman">Pengalaman di toko</a><a href="#perjalanan">Perjalanan</a><a href="#galeri">Galeri</a><a href="#ulasan">Ulasan</a><a href="#kunjungan">Kunjungan</a><a href="#faq">FAQ</a>
        </nav>
        <div class="about-scroll-progress" aria-hidden="true" data-about-progress></div>
    </div>

    <section id="cerita" class="about-section about-container about-story" aria-labelledby="story-title">
        <div><p class="about-eyebrow">Cerita kami</p><h2 id="story-title">Awal mula Qammaris</h2></div>
        <div class="about-prose">
            <p>Saat kuliah di luar Palu, Husein mulai mengenal parfum melalui obrolan dengan teman-temannya. Ia mencoba berbagai aroma dan mulai mempelajari karakter masing-masing parfum.</p>
            <p>Dari parfum lokal, designer, dan niche, ia kemudian mengenal parfum Timur Tengah. Saat kembali ke Palu, ia ingin menyediakan tempat untuk mencoba parfum sebelum membeli.</p>
            <p>Qammaris dibangun agar pelanggan bisa mencoba langsung, membandingkan pilihan, dan berdiskusi dengan staf. Tujuannya sederhana: membantu pelanggan memilih parfum yang sesuai selera dan kebutuhan.</p>
            <a class="about-text-link" href="{{ $aboutContent['story_url'] }}" target="_blank" rel="noopener noreferrer">Baca cerita Husein di LinkedIn <x-icon name="arrow-up-right" /></a>
        </div>
    </section>

    {{-- FeatureSection8706 + Steps6087: icon/copy grid and numbered connecting steps. --}}
    <section id="pengalaman" class="about-experience" aria-labelledby="experience-title">
        <div class="about-container">
            <div class="about-feature-heading"><p class="about-eyebrow">Experience store</p><h2 id="experience-title">Tidak harus paham parfum<br>untuk mulai mencoba.</h2><p class="about-lead">Kami siap membantu Anda mengenal pilihan aromanya. Mulai dari kebutuhan sehari-hari, acara tertentu, hingga hadiah.</p></div>
            <ol class="about-feature-grid">
                <li><span class="about-step-number" aria-hidden="true">1</span><div><h3>Ceritakan kebutuhan Anda</h3><p>Ceritakan selera, aktivitas, atau kesempatan pemakaiannya. Staf kami siap menjelaskan dan merekomendasikan pilihan yang sesuai.</p></div></li>
                <li><span class="about-step-number" aria-hidden="true">2</span><div><h3>Coba dan bandingkan</h3><p>Semua produk di toko tersedia testernya. Gunakan paper test untuk mencoba dan membandingkan aroma sebelum memutuskan.</p></div></li>
                <li><span class="about-step-number" aria-hidden="true">3</span><div><h3>Pilih yang terasa cocok</h3><p>Tanyakan dan luangkan waktu untuk mengenal aromanya. Kami melayani dengan sepenuh hati agar Anda nyaman selama memilih.</p></div></li>
            </ol>
            <div class="about-feature-help"><div><h3>Masih ingin tanya-tanya dulu?</h3><p>Kami juga bisa membantu melalui WhatsApp.</p></div><a class="about-button about-button-outline" href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer">Diskusi dengan kami <x-icon name="arrow-up-right" /></a></div>
        </div>
    </section>

    <section id="perjalanan" class="about-section about-container" aria-labelledby="journey-title">
        <div class="about-section-heading"><div><p class="about-eyebrow">Perjalanan Qammaris</p><h2 id="journey-title">Perjalanan membangun Qammaris.</h2></div><p>Dari ide dan persiapan interior hingga toko mulai buka.</p></div>
        {{-- Adapted from shadcnspace/timeline-01, 21st demo 28273. --}}
        <ol class="about-timeline" data-about-timeline>
            @foreach ($aboutContent['timeline'] as $milestone)
                <li>
                    <div class="about-timeline-copy"><p class="about-timeline-date">{{ $milestone['date'] }}</p><h3>{{ $milestone['title'] }}</h3><p>{{ $milestone['text'] }}</p></div>
                </li>
            @endforeach
        </ol>
        <a class="about-text-link" href="{{ $aboutContent['journey_url'] }}" target="_blank" rel="noopener noreferrer">Cerita pembangunan di LinkedIn <x-icon name="arrow-up-right" /></a>
    </section>

    <section id="galeri" class="about-gallery-section" aria-labelledby="gallery-title">
        <div class="about-container">
            <div class="about-section-heading"><div><p class="about-eyebrow">Galeri</p><h2 id="gallery-title">Di balik pembangunan toko.</h2></div><p>Dari rancangan interior hingga pengerjaan rak. Pilih foto untuk melihat detailnya tanpa potongan.</p></div>
            {{-- Adapted from 0xUrvish/fluid-expanding-grid, 21st demo 10467. --}}
            {{-- GalleryGridBlock10596 adds category filtering and a filter-aware lightbox. --}}
            <div class="about-gallery-filters" role="group" aria-label="Kategori foto" data-gallery-filters hidden>
                <button type="button" class="about-button" data-gallery-filter="Semua" aria-pressed="true">Semua</button>
                @foreach (collect($aboutContent['gallery'])->pluck('category')->unique() as $category)
                    <button type="button" class="about-button" data-gallery-filter="{{ $category }}" aria-pressed="false">{{ $category }}</button>
                @endforeach
            </div>
            <p class="about-gallery-count" role="status" data-gallery-count></p>
            <div class="about-gallery" data-fluid-gallery>
                @foreach ($aboutContent['gallery'] as $photo)
                    <figure data-gallery-item data-photo-category="{{ $photo['category'] }}">
                        <a class="about-gallery-link" href="{{ asset('images/store/'.$photo['key'].'-1200.webp') }}" data-about-photo data-photo-caption="{{ $photo['caption'] }}" data-photo-alt="{{ $photo['alt'] }}" aria-label="Perbesar foto: {{ $photo['title'] }}">
                            <img class="about-photo-contain" src="{{ asset('images/store/'.$photo['key'].'-480.webp') }}" srcset="{{ asset('images/store/'.$photo['key'].'-480.webp') }} 480w, {{ asset('images/store/'.$photo['key'].'-768.webp') }} 768w, {{ asset('images/store/'.$photo['key'].'-1200.webp') }} {{ $aboutMedia[$photo['key']]['width'] }}w" sizes="(min-width: 900px) 60vw, (min-width: 600px) 45vw, 100vw" width="{{ $aboutMedia[$photo['key']]['width'] }}" height="{{ $aboutMedia[$photo['key']]['height'] }}" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async"><span class="about-photo-hint" aria-hidden="true">Lihat foto <x-icon name="arrow-up-right" /></span>
                        </a><figcaption><h3>{{ $photo['title'] }}</h3><p>{{ $photo['caption'] }}</p><button type="button" class="about-gallery-expand" data-expand-photo aria-pressed="false" aria-label="Perbesar {{ $photo['title'] }} di galeri" hidden><span data-expand-label>Perbesar di galeri</span> <x-icon name="plus" /></button></figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    <section class="about-section about-container about-reel-layout" aria-labelledby="reel-title">
        <div><p class="about-eyebrow">Dari Instagram kami</p><h2 id="reel-title">Kenali suasananya,<br>sebelum datang.</h2><p class="about-lead">Lihat Qammaris melalui video singkat dari akun Instagram kami.</p><p class="about-small-copy">Video dimuat dari Instagram saat Anda memilih untuk menontonnya.</p><a class="about-text-link" href="{{ $aboutContent['reel_url'] }}" target="_blank" rel="noopener noreferrer">Buka Reels di Instagram <x-icon name="arrow-up-right" /></a></div>
        {{-- HeroVideoDialog1107: real thumbnail, centered play button and portrait modal. --}}
        <div class="about-reel" data-about-reel data-embed-url="{{ $aboutContent['reel_embed_url'] }}">
            <div class="about-reel-preview"><img src="{{ asset('images/store/visitors-768.webp') }}" srcset="{{ asset('images/store/visitors-480.webp') }} 480w, {{ asset('images/store/visitors-768.webp') }} 768w, {{ asset('images/store/visitors-1200.webp') }} {{ $aboutMedia['visitors']['width'] }}w" sizes="(min-width: 900px) 375px, 90vw" width="{{ $aboutMedia['visitors']['width'] }}" height="{{ $aboutMedia['visitors']['height'] }}" alt="Suasana interior Qammaris dengan pengunjung dan meja koleksi parfum" loading="lazy" decoding="async"><button type="button" class="about-video-play" data-load-reel aria-label="Tonton Reels suasana Qammaris" aria-haspopup="dialog" hidden><x-icon name="play" /><span>Tonton Reels</span></button><noscript><a class="about-button about-button-primary" href="{{ $aboutContent['reel_url'] }}" target="_blank" rel="noopener noreferrer">Tonton di Instagram <x-icon name="arrow-up-right" /></a></noscript></div>
            <p class="about-small-copy">Suasana di dalam toko Qammaris.</p>
            <a class="about-reel-fallback about-text-link" href="{{ $aboutContent['reel_url'] }}" target="_blank" rel="noopener noreferrer">Buka Reels di Instagram <x-icon name="arrow-up-right" /></a>
        </div>
    </section>

    <section id="ulasan" class="about-reviews-section" aria-labelledby="reviews-title">
        <div class="about-container about-reviews-layout">
            <div><p class="about-eyebrow">Ulasan pelanggan</p><h2 id="reviews-title">Apa kata<br>pelanggan kami.</h2><p class="about-lead">Baca pengalaman pengunjung Qammaris di Google Maps sebelum merencanakan kunjungan Anda.</p></div>
            @include('store._rating-card', ['rating' => $aboutContent['google_rating'], 'checkedAt' => $aboutContent['rating_checked_at'], 'reviewsUrl' => $aboutContent['reviews_url']])
        </div>
        @if ($aboutContent['reviews'])
            {{-- ClientFeedback6268: three columns of alternating quote/identity cards. --}}
            <div class="about-container about-review-columns">
                @foreach (collect($aboutContent['reviews'])->chunk(2) as $column)
                    <div class="about-review-column">
                        @foreach ($column as $review)
                            @include('store._review-card', ['review' => $review, 'reviewsUrl' => $aboutContent['reviews_url']])
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section id="kunjungan" class="about-section about-container about-visit" aria-labelledby="visit-title">
        <figure><img src="{{ asset('images/store/facade-768.webp') }}" srcset="{{ asset('images/store/facade-480.webp') }} 480w, {{ asset('images/store/facade-768.webp') }} 768w, {{ asset('images/store/facade-1200.webp') }} {{ $aboutMedia['facade']['width'] }}w" sizes="(min-width: 900px) 40vw, 100vw" width="{{ $aboutMedia['facade']['width'] }}" height="{{ $aboutMedia['facade']['height'] }}" alt="Tampak depan toko Qammaris Perfumes di Jalan Sis Aljufri Palu" loading="lazy" decoding="async"></figure>
        <div><p class="about-eyebrow">Kunjungi kami</p><h2 id="visit-title">Coba parfum langsung<br>di toko kami.</h2><p class="about-lead">Datang untuk mencoba koleksi, mengenal aroma baru, atau sekadar memulai percakapan tentang parfum.</p><dl class="about-store-details"><div><dt>Alamat</dt><dd>{{ $aboutAddress }}</dd></div><div><dt>Jam buka</dt><dd>Sabtu–Kamis · 09.00–21.00 WITA<br>Jumat tutup</dd></div></dl><div class="about-actions"><a class="about-button about-button-primary" href="{{ $aboutContent['reviews_url'] }}" target="_blank" rel="noopener noreferrer">Petunjuk arah <x-icon name="arrow-up-right" /></a><a class="about-text-link" href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer">Hubungi lewat WhatsApp <x-icon name="arrow-up-right" /></a></div><a class="about-text-link about-catalog-link" href="{{ route('products.index') }}">Jelajahi katalog parfum <x-icon name="arrow-right" /></a></div>
    </section>

    <section id="faq" class="about-section about-container about-faq" aria-labelledby="faq-title"><p class="about-eyebrow">Sebelum berkunjung</p><h2 id="faq-title">Yang mungkin ingin Anda tahu.</h2>
        <x-faq name="about-faq" :items="[
            ['question' => 'Apakah semua produk bisa dicoba?', 'answer' => 'Ya. Semua produk di toko tersedia testernya. Anda bisa mencoba dengan paper test dan membandingkan pilihan aromanya.'],
            ['question' => 'Saya belum paham parfum. Apakah bisa dibantu?', 'answer' => 'Tentu. Ceritakan aroma yang Anda sukai dan kebutuhan pemakaiannya. Staf kami siap membantu menjelaskan serta merekomendasikan pilihan.'],
            ['question' => 'Apakah bisa berdiskusi dulu sebelum memilih?', 'answer' => 'Bisa. Qammaris hadir sebagai ruang eksplorasi dan diskusi. Anda bisa bertanya, mencoba, serta membandingkan parfum dengan nyaman.'],
            ['question' => 'Kapan toko buka?', 'answer' => 'Kami buka Sabtu sampai Kamis, pukul 09.00–21.00 WITA. Jumat tutup. Untuk perubahan pada hari libur, cek Instagram atau hubungi kami.'],
            ['question' => 'Apakah bisa memesan tanpa datang ke toko?', 'answer' => 'Bisa. Jelajahi katalog website, tambahkan produk ke keranjang, lalu isi data penerima untuk melanjutkan pemesanan melalui WhatsApp.'],
        ]" />
    </section>

    {{-- Cta0118475, text balance via CSS; real catalog instead of a demo download. --}}
    <section class="about-container about-catalog-cta" aria-labelledby="catalog-cta-title"><h2 id="catalog-cta-title">Lihat koleksinya sebelum datang.</h2><p>Jelajahi katalog parfum, lalu coba pilihan Anda langsung di Qammaris.</p><a class="about-button about-button-primary" href="{{ route('products.index') }}">Jelajahi katalog <x-icon name="arrow-right" /></a></section>

    <dialog class="about-video-dialog" data-video-dialog aria-labelledby="video-dialog-title">
        <div class="about-dialog-toolbar"><h2 id="video-dialog-title">Suasana Qammaris</h2><button type="button" data-close-video>Tutup <x-icon name="x" /></button></div>
        <div data-video-frame></div><p class="about-reel-status" role="status" data-reel-status></p>
        <a class="about-text-link" href="{{ $aboutContent['reel_url'] }}" target="_blank" rel="noopener noreferrer">Jika video tidak tampil, buka di Instagram <x-icon name="arrow-up-right" /></a>
    </dialog>

    <dialog class="about-photo-dialog" data-about-dialog aria-labelledby="about-photo-caption">
        <div class="about-dialog-toolbar"><p data-photo-position></p><button type="button" data-close-photo aria-label="Tutup foto">Tutup <x-icon name="x" /></button></div>
        <figure><img data-dialog-photo alt=""><figcaption id="about-photo-caption"></figcaption></figure>
        <div class="about-dialog-controls"><button type="button" data-photo-prev aria-label="Foto sebelumnya"><x-icon name="arrow-left" /> Sebelumnya</button><button type="button" data-photo-next aria-label="Foto berikutnya">Berikutnya <x-icon name="arrow-right" /></button></div>
    </dialog>
</div>
@push('scripts')
    @vite('resources/js/about.js')
@endpush
@endsection
