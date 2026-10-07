@extends('layouts.app')
@section('title', 'Qammaris Journal — Panduan dan cerita parfum')
@section('meta_description', 'Panduan, ulasan, dan cerita parfum dari Qammaris Journal.')
@section('robots', $search !== '' ? 'noindex,follow' : 'index,follow')
@php($listingCanonical = $category !== '' ? route('blog.category', \Illuminate\Support\Str::slug($category)) : route('blog.index'))
@section('canonical_url', $listingCanonical.($posts->currentPage() > 1 ? '?page='.$posts->currentPage() : ''))
@push('styles') @vite('resources/js/journal.js') @endpush

@section('content')
<div class="journal-public journal-landing">
    <header class="journal-intro">
        <p class="journal-kicker">Cerita di balik aroma</p>
        <h1>Qammaris Journal</h1>
        <p>Panduan dan ulasan untuk menemukan aroma pilihan Anda.</p>
    </header>

    <form method="GET" action="{{ route('blog.index') }}" class="journal-discovery" role="search" aria-label="Cari artikel Journal">
        <div><label for="journal-search">Cari artikel</label><input id="journal-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Judul, aroma, atau produk" autocomplete="off"></div>
        <div><label for="journal-category">Kategori</label><select id="journal-category" name="category"><option value="">Semua kategori</option>@foreach($categories as $cat)<option value="{{ \Illuminate\Support\Str::slug($cat) }}" @selected(\Illuminate\Support\Str::slug($category) === \Illuminate\Support\Str::slug($cat))>{{ $cat }}</option>@endforeach</select></div>
        <button type="submit" class="journal-button">Cari</button>
        @if($search !== '' || $category !== '')<a href="{{ route('blog.index') }}">Hapus filter</a>@endif
    </form>

    @if($featured)
        <section class="journal-featured" aria-labelledby="featured-title">
            <a href="{{ route('blog.show', $featured->slug) }}" class="journal-cover" aria-label="Baca {{ $featured->title }}"><img class="journal-image" src="{{ $featured->featured_image_url }}" alt="{{ $featured->featured_image_alt ?: $featured->title }}" width="1200" height="800" fetchpriority="high" decoding="async" data-journal-fallback="{{ asset('images/product-placeholder.svg') }}"></a>
            <div>
                <p class="journal-kicker">Pilihan editorial · {{ $featured->category }}</p>
                <h2 id="featured-title"><a href="{{ route('blog.show', $featured->slug) }}">{{ $featured->title }}</a></h2>
                <p>{{ $featured->excerpt }}</p>
                <p class="journal-meta"><time datetime="{{ $featured->published_at->toAtomString() }}">{{ $featured->published_date }}</time> · {{ $featured->reading_time }}</p>
                <a href="{{ route('blog.show', $featured->slug) }}" class="journal-text-link">Baca artikel →</a>
            </div>
        </section>
    @endif

    <section class="journal-results" aria-labelledby="journal-results-title">
        <div class="journal-section-heading"><h2 id="journal-results-title">{{ $search !== '' ? 'Hasil pencarian' : ($category !== '' ? 'Artikel '.ucfirst(str_replace('-', ' ', $category)) : 'Artikel terbaru') }}</h2><p>{{ $posts->total() }} artikel{{ $search !== '' ? ' untuk “'.$search.'”' : '' }}</p></div>
        @if($posts->isEmpty())
            <div class="journal-empty"><h3>{{ $search !== '' || $category !== '' ? 'Artikel tidak ditemukan' : 'Belum ada artikel' }}</h3><p>{{ $search !== '' || $category !== '' ? 'Coba kata lain atau tampilkan semua artikel.' : 'Cerita dan panduan baru akan hadir di sini.' }}</p><a class="journal-text-link" href="{{ route('blog.index') }}">{{ $search !== '' || $category !== '' ? 'Lihat semua artikel' : 'Muat ulang' }}</a></div>
        @else
            <div class="journal-grid">
                @foreach($posts as $post)
                    @unless($featured?->id === $post->id)
                        @include('blog._card')
                    @endunless
                @endforeach
            </div>
            @include('blog._pagination')
        @endif
    </section>
</div>
@endsection
