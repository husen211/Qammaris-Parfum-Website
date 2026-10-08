{{-- Adapted from clevision/Card Studio rating demo, retrieved through 21st MCP (8993). --}}
<article class="about-rating-card" aria-label="Rating Qammaris di Google Maps">
    <p class="about-eyebrow">Google Maps</p>
    <div class="about-rating-value"><strong>{{ $rating }}</strong><span>/ 5</span></div>
    <p class="about-rating-stars" aria-hidden="true">★★★★★</p>
    <p class="about-rating-description">Rating pengunjung Qammaris Perfumes</p>
    <footer><div><strong>Qammaris Perfumes</strong><p>Dilihat {{ $checkedAt }} · rating dapat berubah</p></div><a class="about-text-link" href="{{ $reviewsUrl }}" target="_blank" rel="noopener noreferrer">Baca ulasan asli ↗</a></footer>
</article>
