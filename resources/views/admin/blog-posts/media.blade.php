@extends('layouts.admin')
@push('styles') @vite('resources/js/blog-editor.js') @endpush
@section('content')
<div class="journal-editor max-w-5xl">
    <div class="journal-heading"><div><p class="journal-eyebrow">QAMMARIS JOURNAL · MEDIA</p><h1>Media artikel</h1><p>{{ $blogPost->title }}</p></div><a href="{{ route('admin.blog-posts.edit', $blogPost) }}">Kembali ke editor</a></div>
    <p class="journal-notice">Simpan isian editor sebelum membuka halaman ini. Perubahan media memperbarui revision artikel. Setelah selesai, kembali ke editor untuk memuat data terbaru. Gambar asli dan crop lama tetap disimpan.</p>
    @unless($resizeAvailable)<p role="status" class="journal-notice">GD/WebP belum aktif pada server ini. Upload tetap menyimpan gambar asli; resize dan crop belum tersedia.</p>@endunless
    @if($errors->any())<div role="alert" class="journal-errors"><strong>Media belum disimpan.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@if($errors->has('revision'))<a href="{{ route('admin.blog-media.index', $blogPost) }}">Muat ulang media terbaru</a>@endif<p>Pilih ulang file untuk mencoba lagi.</p></div>@endif
    <details class="journal-section" @if($media->isEmpty() || ($errors->any() && !old('media_id'))) open @endif><summary>Upload media baru</summary><div class="journal-section-body">
        <form method="POST" action="{{ route('admin.blog-media.store', $blogPost) }}" enctype="multipart/form-data" data-media-form>
            @csrf <input type="hidden" name="revision" value="{{ old('revision', $blogPost->revision) }}">
            <label for="image">File gambar</label><input id="image" type="file" name="image" required accept="image/jpeg,image/png,image/webp" data-crop-file>
            <p>JPEG/PNG/WebP · maksimal 5 MB, 6000 px per sisi dan 12 megapiksel. Target utama 1600 × 900. Gambar kecil tidak diperbesar.</p>
            @include('admin.blog-posts._media-fields', ['item' => null, 'prefix' => 'upload'])
            <button type="submit" class="journal-primary">Upload media</button><p data-media-status role="status"></p>
        </form>
    </div></details>
    <h2>Media aktif ({{ $media->count() }})</h2>
    @forelse($media as $item)
        <details class="journal-section" @if($errors->any() && (int)old('media_id') === $item->id) open @endif><summary>{{ $item->alt ?: 'Media '.$item->id }}{{ $blogPost->featured_media_id === $item->id ? ' · Gambar utama' : '' }}</summary><div class="journal-section-body">
            <form method="POST" action="{{ route('admin.blog-media.update', [$blogPost, $item->id]) }}" data-media-form>
                @csrf @method('PATCH') <input type="hidden" name="media_id" value="{{ $item->id }}"><input type="hidden" name="revision" value="{{ (int)old('media_id') === $item->id ? old('revision', $blogPost->revision) : $blogPost->revision }}">
                @include('admin.blog-posts._media-fields', ['prefix' => 'media-'.$item->id])
                <p>{{ $item->width }} × {{ $item->height }} px · {{ count($item->variants ?? []) }} versi responsif/crop</p>
                @if($item->processing_warning)<p role="status" class="journal-notice">{{ $item->processing_warning }}</p>@endif
                <p><a href="{{ $item->url }}" target="_blank" rel="noopener">Lihat gambar asli <x-icon name="arrow-up-right" /></a></p>
                <button type="submit" class="journal-primary">Simpan metadata dan crop</button><p data-media-status role="status"></p>
            </form>
            <div class="journal-media-result">@include('blog._media', ['media' => $item])</div>
            @if($blogPost->featured_media_id !== $item->id)
                <form method="POST" action="{{ route('admin.blog-media.archive', [$blogPost, $item->id]) }}">@csrf @method('PATCH')<input type="hidden" name="media_id" value="{{ $item->id }}"><input type="hidden" name="revision" value="{{ (int)old('media_id') === $item->id ? old('revision', $blogPost->revision) : $blogPost->revision }}"><button type="submit">Arsipkan media (file tetap disimpan)</button></form>
            @else<p>Untuk arsip, pilih gambar utama pengganti di editor terlebih dahulu.</p>@endif
        </div></details>
    @empty<p>Belum ada media tambahan. Upload gambar, lalu pilih di editor untuk gambar atau galeri.</p>@endforelse
</div>
@endsection
