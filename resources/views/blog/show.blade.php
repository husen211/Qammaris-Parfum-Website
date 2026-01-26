@extends('layouts.app')

@section('title', $post->title . ' - Jurnal')
@section('meta_description', $post->meta_description ?? $post->excerpt)

@section('content')
<article class="bg-white">
    <header class="pt-32 pb-10">
        <div class="container mx-auto px-4 max-w-4xl text-center">
            <nav class="flex flex-wrap justify-center items-center gap-2 text-[10px] uppercase tracking-widest text-gray-400 mb-6">
                <a href="{{ route('blog.index') }}" class="hover:text-brand-black transition-colors">Jurnal</a>
                <span>/</span>
                <span class="text-brand-black">{{ $post->category ?? 'General' }}</span>
            </nav>

            <h1 class="font-mayluxa text-4xl md:text-6xl text-brand-black mb-6 leading-tight">
                {{ $post->title }}
            </h1>

            <div class="flex flex-wrap justify-center items-center gap-4 text-xs font-medium text-gray-500 uppercase tracking-widest border-y border-gray-100 py-4 mx-auto max-w-2xl">
                <span>{{ $post->published_date }}</span>
                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                <span>By {{ $post->author ?? 'Qammaris Team' }}</span>
                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                <span>{{ $post->reading_time }}</span>
                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                <span>{{ $post->view_count }} views</span>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-0 md:px-4 max-w-5xl mb-12">
        <div class="aspect-video md:aspect-[21/9] overflow-hidden md:rounded-sm relative">
            <img
                src="{{ $post->featured_image_url }}"
                alt="{{ $post->title }}"
                class="w-full h-full object-cover"
                loading="lazy"
                decoding="async"
            >
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
        </div>
    </div>

    <div class="container mx-auto px-4 max-w-3xl pb-16">
        <div class="text-lg md:text-xl font-mayluxa text-brand-black leading-relaxed mb-10 text-center border-b border-gray-100 pb-10">
            "{{ $post->excerpt }}"
        </div>

        <div class="prose prose-lg max-w-none prose-headings:font-mayluxa prose-headings:font-normal prose-p:font-light prose-p:leading-loose prose-p:text-gray-600 prose-a:text-brand-gold prose-a:no-underline hover:prose-a:underline prose-img:rounded-sm prose-blockquote:border-l-2 prose-blockquote:border-brand-black prose-blockquote:font-mayluxa prose-blockquote:not-italic prose-blockquote:text-2xl">
            {!! $post->content !!}
        </div>

        <div class="mt-16 pt-8 border-t border-gray-100 flex flex-col items-center">
            <span class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-6">Share this article</span>
            <div class="flex gap-4">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener noreferrer" class="w-10 h-10 border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:text-white hover:border-brand-black transition-all rounded-full text-gray-500">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener noreferrer" class="w-10 h-10 border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:text-white hover:border-brand-black transition-all rounded-full text-gray-500">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                </a>
                <a href="https://wa.me/?text={{ urlencode($post->title . ' - ' . request()->url()) }}" target="_blank" rel="noopener noreferrer" class="w-10 h-10 border border-gray-200 flex items-center justify-center hover:bg-brand-black hover:text-white hover:border-brand-black transition-all rounded-full text-gray-500">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                </a>
            </div>
        </div>
    </div>

    @if($relatedPosts->count() > 0)
    <div class="bg-[#FAFAFA] py-20 border-t border-gray-100">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12">
                <h2 class="font-mayluxa text-3xl text-brand-black">Read Next</h2>
                <a href="{{ route('blog.index') }}" class="text-xs font-bold uppercase tracking-widest border-b border-brand-black pb-1 hover:text-brand-gold hover:border-brand-gold transition-all">Lihat Jurnal</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($relatedPosts as $related)
                <a href="{{ route('blog.show', $related->slug) }}" class="group block">
                    <div class="aspect-[3/2] overflow-hidden bg-gray-200 mb-4 relative">
                        <img
                            src="{{ $related->featured_image_url }}"
                            alt="{{ $related->title }}"
                            class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-brand-emerald mb-2 block">
                        {{ $related->category ?? 'General' }}
                    </span>
                    <h3 class="font-mayluxa text-xl text-brand-black group-hover:text-brand-gold transition-colors">
                        {{ $related->title }}
                    </h3>
                </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</article>
@endsection
