@extends('layouts.app')

@section('title', 'Virtual Tour Toko Parfum Qammaris di Palu - Qammaris Perfumes')
@section('meta_description', 'Jelajahi toko parfum Qammaris di Palu dalam foto 360. Berjalan dari pintu masuk sampai meja kasir dan lihat rak parfum Timur Tengah dan lokal sebelum berkunjung.')
@section('canonical_url', route('store.virtual-tour'))
@section('og_image', $spots->firstWhere('id', $tour['start'])['preview'])

@push('styles')
    @vite('resources/css/virtual-tour.css')
@endpush

@push('jsonld')
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
<div class="pt-28 md:pt-32 pb-6 md:pb-8 bg-white text-center">
    <div class="container mx-auto px-4">
        <nav aria-label="Breadcrumb" class="mb-5 flex justify-center gap-2 text-[10px] uppercase tracking-widest text-gray-500">
            <a href="{{ route('home') }}" class="hover:text-brand-black">Beranda</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('store.location') }}" class="hover:text-brand-black">Lokasi Toko</a>
            <span aria-hidden="true">/</span>
            <span class="font-semibold text-brand-black" aria-current="page">Virtual Tour</span>
        </nav>
        <h1 class="mx-auto max-w-3xl font-mayluxa text-3xl md:text-5xl leading-tight text-brand-black mb-3">
            Virtual Tour Toko Qammaris
        </h1>
        <p class="mx-auto max-w-2xl text-gray-500 font-light md:text-lg leading-relaxed">
            Geser foto untuk melihat sekeliling, lalu ketuk panah di lantai untuk berjalan dari pintu masuk sampai meja kasir.
        </p>
    </div>
</div>

{{-- TOUR-01: tour data is static config; the viewer reads it from this JSON block. --}}
<script type="application/json" id="virtual-tour-data">{!! json_encode($tour, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

<section class="bg-white pb-12 md:pb-16" aria-label="Virtual tour">
    <div class="container mx-auto px-4 lg:px-12">
        <div class="vt-stage" data-tour-stage tabindex="0" role="application"
             aria-label="Foto 360 toko. Gunakan tombol panah untuk melihat sekeliling, tombol plus dan minus untuk zoom.">
            <div class="vt-canvas" data-tour-canvas></div>
            <div class="vt-hotspots" data-tour-hotspots></div>
            <p class="vt-status" data-tour-status>Memuat foto 360…</p>
            <div class="vt-error" data-tour-error hidden>
                <p>Foto 360 gagal dimuat. Periksa koneksi internet Anda.</p>
                <button type="button" class="vt-retry" data-tour-retry>Coba lagi</button>
            </div>
            <div class="vt-plan" data-tour-plan hidden>
                <svg viewBox="0 0 100 172" role="group" aria-label="Denah toko, pilih area"></svg>
            </div>
            <p class="vt-hint" data-tour-hint hidden>Geser untuk melihat sekeliling</p>
        </div>

        <div class="vt-fallback" data-tour-fallback hidden>
            <p class="vt-fallback-note" data-tour-fallback-note>Perangkat ini tidak bisa menampilkan foto 360. Berikut tampilan setiap area toko.</p>
            <div class="vt-fallback-grid">
                @foreach($spots as $spot)
                    <figure>
                        <img src="{{ $spot['preview'] }}" width="1200" height="675" loading="lazy" decoding="async" alt="Area {{ $spot['name'] }} di toko Qammaris Palu">
                        <figcaption>{{ $spot['name'] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
        <noscript><style>[data-tour-stage],[data-tour-areas]{display:none!important}[data-tour-fallback]{display:block!important}[data-tour-fallback-note]{display:none}</style></noscript>

        <div class="vt-areas" data-tour-areas>
            <p class="vt-areas-label" id="vt-areas-label">Pilih area</p>
            <div class="vt-area-list" role="group" aria-labelledby="vt-areas-label">
                @foreach($spots as $spot)
                    <button type="button" class="vt-area" data-tour-goto="{{ $spot['id'] }}" aria-pressed="{{ $spot['id'] === $tour['start'] ? 'true' : 'false' }}">{{ $spot['name'] }}</button>
                @endforeach
            </div>
            <p class="sr-only" aria-live="polite" data-tour-live></p>
        </div>

        <div class="mt-10 md:mt-12 flex flex-col sm:flex-row items-center justify-center gap-3 text-center">
            <a href="{{ route('store.location') }}" class="vt-cta">Lihat alamat dan jam buka</a>
            <a href="{{ route('products.index') }}" class="vt-cta vt-cta--quiet">Lihat katalog parfum</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    @vite('resources/js/virtual-tour.js')
@endpush
