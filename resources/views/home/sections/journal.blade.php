@if ($featuredPosts->count() > 0)
    <section class="py-24 bg-white">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-end mb-12" data-reveal>
                <h2 class="font-mayluxa text-4xl text-brand-black">Jurnal</h2>
                <a href="{{ route('blog.index') }}"
                    class="text-sm uppercase tracking-widest border-b border-black pb-1 hover:text-brand-gold hover:border-brand-gold transition-colors">Baca
                    Semua</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach ($featuredPosts as $post)
                    <article class="group cursor-pointer" data-reveal data-reveal-delay="{{ $loop->index * 80 }}">
                        <div class="overflow-hidden mb-4">
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy" decoding="async"
                                class="w-full aspect-video object-cover group-hover:scale-105 transition-transform duration-700">
                        </div>
                        <div class="text-xs font-bold text-brand-emerald uppercase tracking-widest mb-2">
                            {{ $post->category }}
                        </div>
                        <h3
                            class="font-mayluxa text-2xl mb-3 leading-tight group-hover:text-brand-gold transition-colors">
                            <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                        </h3>
                        <p class="text-gray-500 text-sm line-clamp-2 font-light mb-4">{{ $post->excerpt }}</p>
                        <a href="{{ route('blog.show', $post->slug) }}"
                            class="text-xs uppercase tracking-widest border-b border-gray-300 pb-1 group-hover:border-brand-gold transition-colors">Baca
                            Artikel</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
