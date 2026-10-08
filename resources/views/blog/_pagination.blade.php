@if($posts->hasPages())
    <nav class="journal-pagination" aria-label="Halaman artikel">
        @if($posts->onFirstPage())<span aria-disabled="true">Sebelumnya</span>@else<a href="{{ $posts->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
        <span>Halaman {{ $posts->currentPage() }} dari {{ $posts->lastPage() }}</span>
        @if($posts->hasMorePages())<a href="{{ $posts->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span aria-disabled="true">Berikutnya</span>@endif
    </nav>
@endif
