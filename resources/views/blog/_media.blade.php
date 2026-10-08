@php
    $sources = $media->sources();
    $largest = $sources ? $sources[count($sources)-1] : null;
    $src = $largest ? \Illuminate\Support\Facades\Storage::disk($media->disk)->url($largest['path']) : $media->url;
@endphp
<figure class="journal-media" data-journal-component>
    <img src="{{ $src }}" @if($media->srcset()) srcset="{{ $media->srcset() }}" sizes="{{ $sizes ?? '(max-width: 767px) calc(100vw - 40px), 720px' }}" @endif
        alt="{{ $media->alt ?: ($alt ?? '') }}" width="{{ $largest['width'] ?? $media->width }}" height="{{ $largest['height'] ?? $media->height }}"
        loading="{{ ($priority ?? false) ? 'eager' : 'lazy' }}" @if($priority ?? false) fetchpriority="high" @endif decoding="async" data-journal-fallback="{{ asset('images/product-placeholder.svg') }}">
    @if($media->caption || $media->credit)<figcaption>{{ $media->caption }}@if($media->credit) <span>Foto: {{ $media->credit }}</span>@endif</figcaption>@endif
</figure>
