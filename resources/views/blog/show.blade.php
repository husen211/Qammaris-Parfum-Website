@extends('layouts.app')
@php
    $preview = $isPreview ?? false;
    $canonical = $preview ? route('blog.index') : \App\Support\JournalMetadata::canonical($post);
    $shareUrl = $preview ? '' : route('blog.show', $post->slug);
    $ogImage = \App\Support\JournalMetadata::validHttps($post->og_image_url) ? $post->og_image_url : ($previewImage ?? $post->featured_image_url);
@endphp
@section('title', ($post->seo_title ?: $post->title).' — Qammaris Journal')
@section('robots', $preview ? 'noindex,nofollow' : \App\Support\JournalMetadata::robots($post))
@section('meta_description', $post->meta_description ?: $post->excerpt)
@section('canonical_url', $canonical)
@section('og_type', 'article')
@section('og_title', $post->og_title ?: ($post->seo_title ?: $post->title))
@section('og_description', $post->og_description ?: ($post->meta_description ?: $post->excerpt))
@section('og_image', $ogImage)
@push('styles') @vite('resources/js/journal.js') @endpush
@unless($preview)
    @push('jsonld')
        @foreach(\App\Support\JournalMetadata::schemas($post) as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        @endforeach
    @endpush
@endunless

@section('content')
<article class="journal-public journal-article">
    <nav class="journal-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('blog.index') }}">Qammaris Journal</a><span aria-hidden="true">/</span>
        <a href="{{ route('blog.category', \Illuminate\Support\Str::slug($post->category ?? 'Tips')) }}">{{ $post->category ?? 'Tips' }}</a>
    </nav>
    <header class="journal-article-header">
        <p class="journal-kicker">{{ $post->category }}</p>
        <h1>{{ $post->title }}</h1>
        @if($post->subtitle ?: $post->excerpt)<p class="journal-dek">{{ $post->subtitle ?: $post->excerpt }}</p>@endif
        <div class="journal-meta"><span>{{ $post->author ?: 'Qammaris Editorial' }}</span><span aria-hidden="true">·</span>
            @if($post->published_at)<time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_date }}</time><span aria-hidden="true">·</span>@endif
            <span>{{ $post->reading_time }}</span>
        </div>
        @if($post->content_updated_at && $post->published_at && $post->content_updated_at->gt($post->published_at))
            <p class="journal-meta">Diperbarui <time datetime="{{ $post->content_updated_at->toAtomString() }}">{{ $post->content_updated_at->translatedFormat('d F Y') }}</time></p>
        @endif
    </header>
    <div class="journal-hero"><img class="journal-image" src="{{ $previewImage ?? $post->featured_image_url }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="1600" height="900" fetchpriority="high" decoding="async" data-journal-fallback="{{ asset('images/product-placeholder.svg') }}"></div>

    <div @class(['journal-reading-layout', 'journal-with-aside' => count($toc ?? []) || ($linkedProducts ?? collect())->isNotEmpty()])>
        @if(count($toc ?? []) || ($linkedProducts ?? collect())->isNotEmpty())
            <aside class="journal-aside" aria-label="Navigasi artikel">
                @if(count($toc ?? []))
                    <details class="journal-toc" open><summary>Daftar isi</summary><nav aria-label="Daftar isi"><ol>@foreach($toc as $entry)<li class="{{ $entry['level'] === 'h3' ? 'journal-toc-child' : '' }}"><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>@endforeach</ol></nav></details>
                @endif
                @if(($linkedProducts ?? collect())->isNotEmpty())
                    <div class="journal-linked-products"><h2>Produk dalam artikel</h2><ul>@foreach($linkedProducts as $product)<li><a href="{{ route('products.show', $product->slug) }}">{{ $product->name }} →</a></li>@endforeach</ul></div>
                @endif
            </aside>
        @endif
        <div class="journal-reading-main">
            <div class="journal-body">{!! $post->content !!}</div>
            @unless($preview)
                <section class="journal-share" aria-labelledby="share-heading" data-journal-share data-share-url="{{ $shareUrl }}" data-share-title="{{ $post->title }}">
                    <h2 id="share-heading">Bagikan artikel</h2>
                    <div class="journal-share-actions">
                        <a href="https://wa.me/?text={{ rawurlencode($post->title.' '.$shareUrl) }}" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a>
                        <button type="button" data-journal-copy>Salin tautan</button>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer">Facebook ↗</a>
                        <a href="https://x.com/intent/post?url={{ rawurlencode($shareUrl) }}&text={{ rawurlencode($post->title) }}" target="_blank" rel="noopener noreferrer">X ↗</a>
                        <button type="button" data-journal-native hidden>Bagikan…</button>
                    </div>
                    <p role="status" data-share-status></p><input type="text" readonly aria-label="Tautan artikel untuk disalin" value="{{ $shareUrl }}" data-share-fallback hidden>
                </section>
                <section class="journal-store-cta"><h2>Temukan aroma pilihan Anda</h2><p>Jelajahi koleksi parfum Qammaris atau kunjungi toko untuk mencoba langsung.</p><a class="journal-button" href="{{ route('products.index') }}">Lihat katalog</a><a class="journal-text-link" href="{{ route('store.location') }}">Lokasi toko →</a></section>
            @endunless
        </div>
    </div>

    @unless($preview)
        @if($relatedPosts->isNotEmpty())
            <section class="journal-related"><div class="journal-section-heading"><h2>Artikel terkait</h2><a href="{{ route('blog.index') }}">Lihat Journal →</a></div><div class="journal-grid">@foreach($relatedPosts as $relatedPost) @include('blog._card', ['post' => $relatedPost]) @endforeach</div></section>
        @endif
        @if($nextPost ?? null)<nav class="journal-next" aria-label="Artikel berikutnya"><span>Artikel berikutnya</span><a href="{{ route('blog.show', $nextPost->slug) }}">{{ $nextPost->title }} →</a></nav>@endif
    @endunless
</article>
@endsection
