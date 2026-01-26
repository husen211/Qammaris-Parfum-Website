@extends('layouts.app')

@section('title', 'Hasil Rekomendasi Parfum')

@section('content')
    <section class="py-16 md:py-24 bg-brand-cream border-b border-gray-200">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center" data-reveal>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Hasil Rekomendasi</p>
                <h1 class="font-mayluxa text-4xl md:text-5xl text-brand-black mt-3">
                    Parfum yang cocok untukmu
                </h1>
                <p class="mt-4 text-sm text-brand-black/60">
                    Rekomendasi berdasarkan jawaban kamu di tes preferensi.
                </p>
            </div>

            <div class="mt-10 grid gap-8 lg:grid-cols-[1.2fr,2fr]">
                <aside class="border border-brand-black/10 bg-white/80 p-8 shadow-[0_18px_36px_rgba(0,0,0,0.08)]" data-reveal>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Ringkasan pilihan</p>
                    <h2 class="font-mayluxa text-2xl text-brand-black mt-3">Profil kamu</h2>
                    <p class="mt-3 text-sm text-brand-black/60">{{ $result['summary'] }}</p>

                    <div class="mt-6 space-y-4 text-sm text-brand-black/70">
                        @foreach ($summary as $item)
                            <div class="flex items-start justify-between gap-4 border-b border-brand-black/10 pb-3">
                                <span class="text-brand-black/60">{{ $item['label'] }}</span>
                                <span class="font-semibold text-brand-black">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Aroma utama</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($result['tag_labels'] as $tag)
                                <span class="border border-brand-gold/40 bg-brand-gold/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-brand-gold">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </aside>

                <div class="border border-brand-black/10 bg-white px-8 py-10 shadow-[0_18px_36px_rgba(0,0,0,0.08)]" data-reveal>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Rekomendasi kami</p>
                            <h2 class="font-mayluxa text-3xl text-brand-black mt-2">
                                {{ collect($result['tag_labels'])->take(2)->implode(' + ') }}
                            </h2>
                        </div>
                        <a href="{{ route('products.index') }}"
                            class="inline-flex items-center justify-center border border-brand-black/20 px-6 py-3 text-[11px] font-semibold uppercase tracking-widest text-brand-black/70 hover:bg-brand-black hover:text-white transition-colors">
                            Lihat Semua Produk
                        </a>
                    </div>

                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        @foreach ($result['products'] as $product)
                            <div class="border border-brand-black/10 bg-white p-4 transition hover:-translate-y-1 hover:shadow-lg">
                                <div class="aspect-[4/5] bg-[#F9F9F9] overflow-hidden">
                                    <img src="{{ $product->primaryImage?->image_url ?? 'https://placehold.co/400x500/F5F5F5/333?text=' . urlencode($product->brand->name) }}"
                                        alt="{{ $product->name }}" loading="lazy" decoding="async"
                                        class="w-full h-full object-cover object-center">
                                </div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-[0.2em] mt-4">
                                    {{ $product->brand->name }}
                                </p>
                                <h3 class="font-mayluxa text-lg text-brand-black mt-2">
                                    <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                                </h3>
                                <a href="{{ route('products.show', $product->slug) }}"
                                    class="mt-4 inline-flex items-center text-[10px] font-bold uppercase tracking-widest text-brand-gold">
                                    Lihat Produk
                                    <span class="ml-2">→</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
