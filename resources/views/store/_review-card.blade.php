{{-- Quote/footer/identity/stars composition adapted from 21st CardTestimonialDemo. --}}
<figure class="about-review-card">
    <blockquote><p>{{ $review['quote'] }}</p></blockquote>
    <figcaption><div><strong>{{ $review['author'] }}</strong><a href="{{ $reviewsUrl }}" target="_blank" rel="noopener noreferrer">Ulasan Google Maps ↗</a></div><span class="about-rating-stars" aria-label="{{ $review['stars'] }} dari 5 bintang">{{ str_repeat('★', $review['stars']) }}</span></figcaption>
</figure>
