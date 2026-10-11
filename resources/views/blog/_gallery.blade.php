<div data-journal-component @class(['journal-gallery' => $gallery, 'journal-single-media' => !$gallery]) @if($gallery) role="region" aria-label="Galeri artikel; geser untuk melihat foto berikutnya" tabindex="0" @endif>
    @foreach($items as $media) @include('blog._media', ['media' => $media]) @endforeach
</div>
@if($gallery)<div class="journal-gallery-controls" hidden><button type="button" data-gallery-prev aria-label="Foto sebelumnya"><x-icon name="arrow-left" /></button><p class="journal-gallery-help" role="status">Geser untuk melihat foto berikutnya.</p><button type="button" data-gallery-next aria-label="Foto berikutnya"><x-icon name="arrow-right" /></button></div>@endif
