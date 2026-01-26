@extends('layouts.app')

@section('title', 'Jurnal - Qammaris Perfumes')
@section('meta_description', 'Insight, tips, dan cerita di balik dunia parfum eksklusif.')

@section('content')

<div class="pt-32 pb-12 bg-white text-center">
    <div class="container mx-auto px-4">
        <p class="text-xs md:text-sm font-bold uppercase tracking-[0.3em] text-brand-gold mb-4 animate-fade-in-up">
            Fragrance Insights
        </p>
        <h1 class="font-mayluxa text-5xl md:text-7xl text-brand-black mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
            Jurnal
        </h1>
        <p class="text-gray-500 font-light text-lg max-w-2xl mx-auto leading-relaxed animate-fade-in-up" style="animation-delay: 0.2s;">
            Temukan inspirasi, panduan aroma, dan cerita eksklusif dari dunia wewangian Timur Tengah.
        </p>
    </div>
</div>

@php($activeCategory = request('category') ?? (isset($category) ? \Illuminate\Support\Str::slug($category) : request()->route('category')))
<div class="bg-white border-b border-gray-100 sticky top-[70px] z-30">
    <div class="container mx-auto px-4 overflow-x-auto scrollbar-hide">
        <div class="flex justify-center min-w-max gap-8 py-4">
            <a href="{{ route('blog.index') }}" 
               class="text-xs font-bold uppercase tracking-widest transition-all hover:text-brand-gold 
               {{ !$activeCategory ? 'text-brand-black border-b-2 border-brand-black pb-1' : 'text-gray-400' }}">
                All Stories
            </a>
            @foreach($categories as $cat)
            @php($catSlug = \Illuminate\Support\Str::slug($cat))
            <a href="{{ route('blog.category', $catSlug) }}" 
               class="text-xs font-bold uppercase tracking-widest transition-all hover:text-brand-gold 
               {{ $activeCategory == $catSlug ? 'text-brand-black border-b-2 border-brand-black pb-1' : 'text-gray-400' }}">
                {{ $cat }}
            </a>
            @endforeach
        </div>
    </div>
</div>

<section class="py-20 bg-white min-h-screen">
    <div class="container mx-auto px-4 lg:px-12">
        
        @if($posts->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-16">
            @foreach($posts as $post)
            <article class="group flex flex-col h-full cursor-pointer">
                
                <div class="overflow-hidden aspect-[3/2] bg-gray-100 mb-6 relative">
                    <a href="{{ route('blog.show', $post->slug) }}" class="block w-full h-full">
                        <img 
                            src="{{ $post->featured_image_url }}" 
                            alt="{{ $post->title }}"
                            class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="absolute top-4 left-4 bg-white/90 backdrop-blur px-3 py-2 text-center min-w-[60px]">
                            <span class="block text-xl font-bold text-brand-black leading-none">{{ ($post->published_at ?? $post->created_at)->format('d') }}</span>
                            <span class="block text-[10px] uppercase tracking-wider text-gray-500">{{ ($post->published_at ?? $post->created_at)->format('M') }}</span>
                        </div>
                    </a>
                </div>
                
                <div class="flex flex-col flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-brand-emerald">
                            {{ $post->category ?? 'General' }}
                        </span>
                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        <span class="text-[10px] uppercase tracking-widest text-gray-400">
                            {{ $post->reading_time }}
                        </span>
                    </div>
                    
                    <h3 class="font-mayluxa text-2xl text-brand-black mb-3 leading-tight group-hover:text-brand-gold transition-colors">
                        <a href="{{ route('blog.show', $post->slug) }}">
                            {{ $post->title }}
                        </a>
                    </h3>
                    
                    <p class="text-gray-500 font-light text-sm leading-relaxed mb-6 line-clamp-3 flex-1">
                        {{ $post->excerpt }}
                    </p>
                    
                    <a href="{{ route('blog.show', $post->slug) }}" class="inline-flex items-center text-xs font-bold uppercase tracking-widest text-brand-black border-b border-gray-300 pb-1 hover:border-brand-black hover:text-brand-gold transition-all w-fit">
                        Baca Artikel
                    </a>
                </div>
                
            </article>
            @endforeach
        </div>
        
        <div class="mt-20 flex justify-center">
            {{ $posts->links('pagination::tailwind') }}
        </div>
        
        @else
        <div class="text-center py-20">
            <h3 class="font-mayluxa text-2xl text-gray-400 mb-2">No Stories Found</h3>
            <p class="text-gray-400 font-light text-sm">Check back later for new updates.</p>
        </div>
        @endif
        
    </div>
</section>

@endsection
