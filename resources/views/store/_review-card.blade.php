{{-- Quote/footer/identity/stars composition adapted from 21st CardTestimonialDemo. --}}
<figure class="about-review-card">
    <blockquote><p>{{ $review['quote'] }}</p></blockquote>
    <figcaption><div><strong>{{ $review['author'] }}</strong><a href="{{ $reviewsUrl }}" target="_blank" rel="noopener noreferrer">Ulasan Google Maps <x-icon name="arrow-up-right" /></a></div><span class="about-rating-stars" aria-label="{{ $review['stars'] }} dari 5 bintang">@for ($star = 0; $star < $review['stars']; $star++)<x-icon name="star" :filled="true" />@endfor</span></figcaption>
</figure>
