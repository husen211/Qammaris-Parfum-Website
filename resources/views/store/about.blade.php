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
    <section class="about-hero about-container" aria-labelledby="about-title">
        <div class="about-hero-copy">
            <p class="about-eyebrow">Qammaris Perfumes · Palu</p>
            <h1 id="about-title">Aroma yang tepat<br>dimulai dari<br><span>pengalaman.</span></h1>
            <p class="about-lead">Kami hadir bukan hanya untuk menjual parfum. Qammaris adalah ruang untuk mencoba, membandingkan, dan berdiskusi sampai Anda menemukan aroma yang terasa pas.</p>
            <div class="about-actions">
                <a href="{{ route('store.location') }}" class="about-button about-button-primary">Kunjungi toko <x-icon name="arrow-up-right" /></a>
                <a href="#cerita" class="about-text-link">Kenali cerita kami <x-icon name="arrow-down" /></a>
            </div>
            <p class="about-hero-note">Experience store parfum di Palu, Sulawesi Tengah.</p>
        </div>
        <figure class="about-hero-photo">
            <img src="{{ asset('images/store/shelves-1200.webp') }}" srcset="{{ asset('images/store/shelves-480.webp') }} 480w, {{ asset('images/store/shelves-768.webp') }} 768w, {{ asset('images/store/shelves-1200.webp') }} {{ $aboutMedia['shelves']['width'] }}w" sizes="(min-width: 900px) 45vw, 100vw" width="{{ $aboutMedia['shelves']['width'] }}" height="{{ $aboutMedia['shelves']['height'] }}" alt="Rak lengkung dan koleksi parfum di dalam experience store Qammaris" fetchpriority="high" decoding="async">
            <figcaption>Ruang untuk menemukan aroma pilihan Anda.</figcaption>
        </figure>
    </section>

    <nav class="about-section-nav about-container" aria-label="Bagian halaman tentang Qammaris">
        <a href="#cerita">Cerita kami</a><a href="#pengalaman">Pengalaman di toko</a><a href="#perjalanan">Perjalanan</a><a href="#galeri">Galeri</a><a href="#ulasan">Ulasan</a><a href="#kunjungan">Kunjungan</a>
    </nav>

    <section id="cerita" class="about-section about-container about-story" aria-labelledby="story-title">
        <div><p class="about-eyebrow">Cerita kami</p><h2 id="story-title">Berawal dari rasa ingin tahu.<br>Tumbuh menjadi sebuah ruang.</h2><p class="about-signature">Cerita Husein, perintis Qammaris</p></div>
        <div class="about-prose">
            <p>Saat merantau ke Jakarta untuk kuliah, Husein mulai mengenal parfum melalui obrolan dengan teman-temannya. Parfum bukan lagi sekadar wangi yang enak, tetapi juga bagian dari rasa percaya diri dan cara mengekspresikan diri.</p>
            <p>Eksplorasi itu membawanya dari parfum lokal, designer, dan niche ke parfum Timur Tengah. Ketika kembali ke Palu, ia merasakan satu hal yang berbeda: tidak selalu mudah menemukan tempat untuk mencoba aroma sebelum membeli.</p>
            <p>Membeli online sering berarti berharap aromanya cocok ketika paket tiba. Padahal, pengalaman setiap orang terhadap aroma berbeda. Dari situ muncul gagasan untuk membangun Qammaris: tempat orang bisa mencoba, membandingkan, dan memilih berdasarkan pengalamannya sendiri.</p>
            <a class="about-text-link" href="{{ $aboutContent['story_url'] }}" target="_blank" rel="noopener noreferrer">Baca cerita Husein di LinkedIn <x-icon name="arrow-up-right" /></a>
        </div>
    </section>

    <section id="pengalaman" class="about-experience" aria-labelledby="experience-title">
        <div class="about-container about-experience-layout">
            <figure class="about-experience-photo"><img src="{{ asset('images/store/visitors-768.webp') }}" srcset="{{ asset('images/store/visitors-480.webp') }} 480w, {{ asset('images/store/visitors-768.webp') }} 768w, {{ asset('images/store/visitors-1200.webp') }} {{ $aboutMedia['visitors']['width'] }}w" sizes="(min-width: 900px) 40vw, 100vw" width="{{ $aboutMedia['visitors']['width'] }}" height="{{ $aboutMedia['visitors']['height'] }}" alt="Pengunjung mencoba dan berdiskusi tentang parfum di dalam toko Qammaris" loading="lazy" decoding="async"></figure>
            <div><p class="about-eyebrow">Experience store</p><h2 id="experience-title">Datang, coba,<br>ceritakan kebutuhan Anda.</h2><p class="about-lead">Anda tidak perlu sudah mengerti parfum untuk datang ke Qammaris. Kami siap menemani proses menemukan aroma yang Anda sukai.</p>
                <ol class="about-experience-list">
                    <li><span aria-hidden="true">01</span><div><h3>Tester untuk setiap produk</h3><p>Semua produk di toko tersedia testernya. Gunakan paper test untuk mencoba dan membandingkan aroma sebelum memutuskan.</p></div></li>
                    <li><span aria-hidden="true">02</span><div><h3>Rekomendasi sesuai kebutuhan</h3><p>Ceritakan selera, aktivitas, atau kesempatan pemakaiannya. Staf kami siap membantu menjelaskan dan merekomendasikan pilihan yang sesuai.</p></div></li>
                    <li><span aria-hidden="true">03</span><div><h3>Ruang untuk berdiskusi</h3><p>Tanyakan, eksplorasi, dan luangkan waktu untuk mengenal aromanya. Kami melayani dengan sepenuh hati agar Anda merasa nyaman selama memilih.</p></div></li>
                </ol>
            </div>
        </div>
    </section>

    <section id="perjalanan" class="about-section about-container" aria-labelledby="journey-title">
        <div class="about-section-heading"><div><p class="about-eyebrow">Perjalanan Qammaris</p><h2 id="journey-title">Dari gagasan,<br>menjadi tempat bertemu.</h2></div><p>Setiap sudut dibangun untuk memberi ruang pada pengalaman memilih parfum.</p></div>
        {{-- Adapted from shadcnspace/timeline-01, 21st demo 28273. --}}
        <ol class="about-timeline" data-about-timeline>
            @foreach ($aboutContent['timeline'] as $milestone)
                <li>
                    <div class="about-timeline-copy"><p class="about-timeline-date">{{ $milestone['date'] }}</p><h3>{{ $milestone['title'] }}</h3><p>{{ $milestone['text'] }}</p></div>
                    <figure class="about-timeline-photo"><img src="{{ asset('images/store/'.$milestone['photo'].'-768.webp') }}" width="{{ $aboutMedia[$milestone['photo']]['width'] }}" height="{{ $aboutMedia[$milestone['photo']]['height'] }}" alt="{{ collect($aboutContent['gallery'])->firstWhere('key', $milestone['photo'])['alt'] }}" loading="lazy" decoding="async">@if($loop->first)<figcaption>Eksplorasi aroma yang kini bisa Anda lakukan di Qammaris.</figcaption>@endif</figure>
                </li>
            @endforeach
        </ol>
        <a class="about-text-link" href="{{ $aboutContent['journey_url'] }}" target="_blank" rel="noopener noreferrer">Cerita pembangunan di LinkedIn <x-icon name="arrow-up-right" /></a>
    </section>

    <section id="galeri" class="about-gallery-section" aria-labelledby="gallery-title">
        <div class="about-container">
            <div class="about-section-heading"><div><p class="about-eyebrow">Galeri</p><h2 id="gallery-title">Ruang, proses,<br>dan pengalaman.</h2></div><p>Lihat toko hari ini dan dokumentasi di balik pembangunannya. Pilih foto untuk melihat lebih dekat.</p></div>
            {{-- Adapted from 0xUrvish/fluid-expanding-grid, 21st demo 10467. --}}
            <div class="about-gallery" data-fluid-gallery>
                @foreach ($aboutContent['gallery'] as $photo)
                    <figure data-gallery-item>
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
        <div class="about-reel" data-about-reel data-embed-url="{{ $aboutContent['reel_embed_url'] }}">
            <div class="about-reel-preview"><img src="{{ asset('images/store/skin-test-480.webp') }}" width="{{ $aboutMedia['skin-test']['width'] }}" height="{{ $aboutMedia['skin-test']['height'] }}" alt="Mencoba parfum langsung di Qammaris" loading="lazy" decoding="async"><button type="button" class="about-button about-button-primary" data-load-reel hidden>Tonton Reels <x-icon name="play" /></button><noscript><a class="about-button about-button-primary" href="{{ $aboutContent['reel_url'] }}" target="_blank" rel="noopener noreferrer">Tonton di Instagram <x-icon name="arrow-up-right" /></a></noscript></div>
            <p class="about-reel-status" role="status" data-reel-status></p>
            <a class="about-reel-fallback about-text-link" href="{{ $aboutContent['reel_url'] }}" target="_blank" rel="noopener noreferrer">Jika video tidak tampil, buka di Instagram <x-icon name="arrow-up-right" /></a>
        </div>
    </section>

    <section id="ulasan" class="about-reviews-section" aria-labelledby="reviews-title">
        <div class="about-container about-reviews-layout">
            <div><p class="about-eyebrow">Ulasan pelanggan</p><h2 id="reviews-title">Pengalaman mereka,<br>cerita untuk Anda.</h2><p class="about-lead">Baca pengalaman pengunjung Qammaris di Google Maps sebelum merencanakan kunjungan Anda.</p></div>
            @include('store._rating-card', ['rating' => $aboutContent['google_rating'], 'checkedAt' => $aboutContent['rating_checked_at'], 'reviewsUrl' => $aboutContent['reviews_url']])
        </div>
        @if ($aboutContent['reviews'])
            <div class="about-container about-review-grid">
                @foreach ($aboutContent['reviews'] as $review)
                    @include('store._review-card', ['review' => $review, 'reviewsUrl' => $aboutContent['reviews_url']])
                @endforeach
            </div>
        @endif
    </section>

    <section id="kunjungan" class="about-section about-container about-visit" aria-labelledby="visit-title">
        <figure><img src="{{ asset('images/store/facade-768.webp') }}" srcset="{{ asset('images/store/facade-480.webp') }} 480w, {{ asset('images/store/facade-768.webp') }} 768w, {{ asset('images/store/facade-1200.webp') }} {{ $aboutMedia['facade']['width'] }}w" sizes="(min-width: 900px) 40vw, 100vw" width="{{ $aboutMedia['facade']['width'] }}" height="{{ $aboutMedia['facade']['height'] }}" alt="Tampak depan toko Qammaris Perfumes di Jalan Sis Aljufri Palu" loading="lazy" decoding="async"></figure>
        <div><p class="about-eyebrow">Kunjungi kami</p><h2 id="visit-title">Mari temukan aroma<br>yang terasa seperti Anda.</h2><p class="about-lead">Datang untuk mencoba koleksi, mengenal aroma baru, atau sekadar memulai percakapan tentang parfum.</p><dl class="about-store-details"><div><dt>Alamat</dt><dd>{{ $aboutAddress }}</dd></div><div><dt>Jam buka</dt><dd>Sabtu–Kamis · 09.00–21.00 WITA<br>Jumat tutup</dd></div></dl><div class="about-actions"><a class="about-button about-button-primary" href="{{ $aboutContent['reviews_url'] }}" target="_blank" rel="noopener noreferrer">Petunjuk arah <x-icon name="arrow-up-right" /></a><a class="about-text-link" href="{{ $storeInfo->whatsapp_link }}" target="_blank" rel="noopener noreferrer">Hubungi lewat WhatsApp <x-icon name="arrow-up-right" /></a></div><a class="about-text-link about-catalog-link" href="{{ route('products.index') }}">Jelajahi katalog parfum <x-icon name="arrow-right" /></a></div>
    </section>

    <section class="about-section about-container about-faq" aria-labelledby="faq-title"><p class="about-eyebrow">Sebelum berkunjung</p><h2 id="faq-title">Yang mungkin ingin Anda tahu.</h2>
        <x-faq name="about-faq" :items="[
            ['question' => 'Apakah semua produk bisa dicoba?', 'answer' => 'Ya. Semua produk di toko tersedia testernya. Anda bisa mencoba dengan paper test dan membandingkan pilihan aromanya.'],
            ['question' => 'Saya belum paham parfum. Apakah bisa dibantu?', 'answer' => 'Tentu. Ceritakan aroma yang Anda sukai dan kebutuhan pemakaiannya. Staf kami siap membantu menjelaskan serta merekomendasikan pilihan.'],
            ['question' => 'Apakah bisa berdiskusi dulu sebelum memilih?', 'answer' => 'Bisa. Qammaris hadir sebagai ruang eksplorasi dan diskusi. Anda bisa bertanya, mencoba, serta membandingkan parfum dengan nyaman.'],
            ['question' => 'Kapan toko buka?', 'answer' => 'Kami buka Sabtu sampai Kamis, pukul 09.00–21.00 WITA. Jumat tutup. Untuk perubahan pada hari libur, cek Instagram atau hubungi kami.'],
            ['question' => 'Apakah bisa memesan tanpa datang ke toko?', 'answer' => 'Bisa. Jelajahi katalog website, tambahkan produk ke keranjang, lalu isi data penerima untuk melanjutkan pemesanan melalui WhatsApp.'],
        ]" />
    </section>

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
