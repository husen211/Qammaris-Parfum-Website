<article class="journal-card">
    <a href="{{ route('blog.show', $post->slug) }}" class="journal-cover" aria-label="Baca {{ $post->title }}">
        <img class="journal-image" src="{{ $post->featured_image_url }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="768" height="512" loading="lazy" decoding="async" data-journal-fallback="{{ asset('images/product-placeholder.svg') }}">
    </a>
    <p class="journal-kicker">{{ $post->category }} · {{ $post->reading_time }}</p>
    <h3><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h3>
    <p>{{ $post->excerpt }}</p>
    <time class="journal-meta" datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_date }}</time>
</article>
