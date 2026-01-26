@extends('layouts.app')

@section('title', 'Tentang Qammaris Perfumes')
@section('meta_description', 'Tentang Qammaris Perfumes, toko parfum Middle East di Palu dengan pengalaman coba langsung.')

@section('content')
    <div class="pt-24 md:pt-28">
    <section class="pb-12 bg-white">
        <div class="container mx-auto px-6 text-center">
            <p class="text-xs md:text-sm font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">
                Tentang Qammaris
            </p>
            <h1 class="font-mayluxa text-4xl md:text-6xl text-brand-black mb-4">
                Tentang Qammaris Perfumes
            </h1>
            <p class="text-gray-500 font-light text-base md:text-lg max-w-2xl mx-auto leading-relaxed">
                Toko parfum Middle East di Palu, Sulawesi Tengah, dengan pengalaman coba langsung sebelum membeli.
            </p>
        </div>
    </section>

    <section class="py-14 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">Preview Produk</p>
                <h2 class="font-mayluxa text-2xl md:text-3xl text-brand-black mb-6">
                    Lihat detail sebelum mencoba langsung
                </h2>
                <div class="mx-auto w-full max-w-[520px]">
                    <div class="relative aspect-square rounded-2xl overflow-hidden bg-white border border-gray-200 shadow-sm">
                        <div id="about-lanyard"
                            class="absolute inset-0 w-full h-full"
                            data-fallback-image="{{ asset('images/about-section.jpg') }}"
                            data-fallback-alt="Produk parfum Qammaris">
                            <div class="absolute inset-0 flex items-center justify-center bg-white/70 text-[11px] font-semibold uppercase tracking-[0.3em] text-gray-400 animate-pulse"
                                data-lanyard-placeholder>
                                Loading 3D...
                            </div>
                            <noscript>
                                <figure class="w-full h-full flex items-center justify-center p-6">
                                    <img src="{{ asset('images/about-section.jpg') }}" alt="Produk parfum Qammaris"
                                        loading="lazy" decoding="async" class="max-h-full max-w-full object-contain" />
                                </figure>
                            </noscript>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    Untuk pengalaman terbaik, Anda bisa mencoba aroma langsung di toko.
                </p>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-4">Cerita Kami</p>
                <h2 class="font-mayluxa text-3xl md:text-4xl text-brand-black mb-6">Kenapa Qammaris hadir di Palu</h2>
                <div class="space-y-4 text-gray-600 leading-relaxed">
                    <p>
                        Ketertarikan kami pada parfum Middle East berawal dari pengalaman pribadi mencari aroma yang
                        sesuai karakter. Kami menyadari banyak pilihan yang menarik, namun sulit ditentukan tanpa mencoba.
                    </p>
                    <p>
                        Di Palu, opsi mencoba langsung masih terbatas. Qammaris hadir untuk menjembatani kebutuhan itu,
                        agar memilih parfum tidak hanya berdasarkan deskripsi online.
                    </p>
                    <p>
                        Kami membangun toko yang rapi dan nyaman, sehingga siapa pun bisa mencium langsung dan memahami
                        aromanya sebelum memutuskan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-brand-cream">
        <div class="container mx-auto px-6">
            <div class="flex items-center justify-between flex-wrap gap-4 mb-10">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">Kenapa Qammaris Ada</p>
                    <h2 class="font-mayluxa text-3xl md:text-4xl text-brand-black">Kami tahu parfum itu subjektif</h2>
                </div>
            </div>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <h3 class="font-semibold text-brand-black text-base mb-3">Parfum itu subjektif</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Aroma yang cocok untuk satu orang belum tentu cocok untuk orang lain. Itu sebabnya memilih parfum
                        perlu ruang dan waktu yang tepat.
                    </p>
                </div>
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <h3 class="font-semibold text-brand-black text-base mb-3">Harus bisa coba langsung</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Kami menyediakan pengalaman sniff agar pelanggan bisa menilai aroma dengan nyaman sebelum membeli.
                    </p>
                </div>
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <h3 class="font-semibold text-brand-black text-base mb-3">Tidak semua cocok blind buy</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Pilihan yang tepat sering datang dari pengalaman langsung, bukan sekadar review atau tren.
                    </p>
                </div>
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <h3 class="font-semibold text-brand-black text-base mb-3">Fokus kenyamanan, bukan hype</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Kami mengutamakan kesesuaian aroma dengan kebutuhan harian, bukan sekadar popularitas.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mb-10">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">Apa yang Kami Lakukan</p>
                <h2 class="font-mayluxa text-3xl md:text-4xl text-brand-black">Pendekatan sederhana, hasilnya jelas</h2>
            </div>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center mb-4">
                        <span class="text-xs font-bold text-brand-black">01</span>
                    </div>
                    <h3 class="font-semibold text-brand-black text-base mb-2">Kurasi brand Middle East</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Kami memilih brand dari Dubai dan Timur Tengah yang relevan dengan preferensi lokal.
                    </p>
                </div>
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center mb-4">
                        <span class="text-xs font-bold text-brand-black">02</span>
                    </div>
                    <h3 class="font-semibold text-brand-black text-base mb-2">Edukasi ringan</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Kami menjelaskan aroma dengan bahasa sederhana agar mudah dipahami tanpa menggurui.
                    </p>
                </div>
                <div class="bg-white border border-gray-100 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center mb-4">
                        <span class="text-xs font-bold text-brand-black">03</span>
                    </div>
                    <h3 class="font-semibold text-brand-black text-base mb-2">Bantu pilih yang cocok</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Tim kami siap memberi rekomendasi berdasarkan karakter, aktivitas, dan preferensi Anda.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-brand-cream">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mb-8">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">Komitmen Kami</p>
                <h2 class="font-mayluxa text-3xl md:text-4xl text-brand-black">Dasar kerja yang kami pegang</h2>
            </div>
            <div class="space-y-4">
                <div class="flex flex-col md:flex-row md:items-center gap-4 bg-white border border-gray-100 p-5">
                    <span class="text-xs font-bold uppercase tracking-widest text-brand-gold">Produk Original</span>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Produk berasal dari sumber resmi dengan kualitas yang dapat ditelusuri.
                    </p>
                </div>
                <div class="flex flex-col md:flex-row md:items-center gap-4 bg-white border border-gray-100 p-5">
                    <span class="text-xs font-bold uppercase tracking-widest text-brand-gold">Kurasi Selektif</span>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Koleksi disusun agar mudah dicoba dan relevan dengan kebutuhan pengguna.
                    </p>
                </div>
                <div class="flex flex-col md:flex-row md:items-center gap-4 bg-white border border-gray-100 p-5">
                    <span class="text-xs font-bold uppercase tracking-widest text-brand-gold">Pendekatan Personal</span>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Kami memberi ruang untuk eksplorasi agar setiap orang menemukan aroma yang pas.
                    </p>
                </div>
                <div class="flex flex-col md:flex-row md:items-center gap-4 bg-white border border-gray-100 p-5">
                    <span class="text-xs font-bold uppercase tracking-widest text-brand-gold">Transparan</span>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Informasi produk disampaikan apa adanya, tanpa klaim berlebihan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mb-8">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">Visual</p>
                <h2 class="font-mayluxa text-3xl md:text-4xl text-brand-black">Ruang & aktivitas Qammaris</h2>
            </div>
            <!-- Slot untuk foto toko / aktivitas / produk -->
            <div class="grid gap-6 md:grid-cols-3">
                <div class="h-48 md:h-56 bg-gray-100 border border-gray-200"></div>
                <div class="h-48 md:h-56 bg-gray-100 border border-gray-200"></div>
                <div class="h-48 md:h-56 bg-gray-100 border border-gray-200"></div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-brand-cream">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mb-8">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-gold mb-3">FAQ</p>
                <h2 class="font-mayluxa text-3xl md:text-4xl text-brand-black">Pertanyaan yang sering muncul</h2>
            </div>
            <div class="space-y-4">
                <div class="collapse collapse-plus bg-white border border-gray-200 rounded-none">
                    <input type="checkbox" />
                    <div class="collapse-title text-sm font-semibold text-brand-black">Bisa coba parfum di toko?</div>
                    <div class="collapse-content text-sm text-gray-600">
                        Tentu. Anda bisa mencoba aroma secara langsung sebelum memutuskan membeli.
                    </div>
                </div>
                <div class="collapse collapse-plus bg-white border border-gray-200 rounded-none">
                    <input type="checkbox" />
                    <div class="collapse-title text-sm font-semibold text-brand-black">Harus beli setelah coba?</div>
                    <div class="collapse-content text-sm text-gray-600">
                        Tidak. Anda bebas mencoba dulu hingga yakin dengan pilihan Anda.
                    </div>
                </div>
                <div class="collapse collapse-plus bg-white border border-gray-200 rounded-none">
                    <input type="checkbox" />
                    <div class="collapse-title text-sm font-semibold text-brand-black">Fokus parfum apa?</div>
                    <div class="collapse-content text-sm text-gray-600">
                        Kami fokus pada parfum Middle East/Dubai dengan berbagai karakter aroma.
                    </div>
                </div>
                <div class="collapse collapse-plus bg-white border border-gray-200 rounded-none">
                    <input type="checkbox" />
                    <div class="collapse-title text-sm font-semibold text-brand-black">Cocok untuk pemula?</div>
                    <div class="collapse-content text-sm text-gray-600">
                        Cocok. Tim kami membantu menjelaskan aroma agar mudah dipahami untuk pemula.
                    </div>
                </div>
                <div class="collapse collapse-plus bg-white border border-gray-200 rounded-none">
                    <input type="checkbox" />
                    <div class="collapse-title text-sm font-semibold text-brand-black">Bisa order via WhatsApp?</div>
                    <div class="collapse-content text-sm text-gray-600">
                        Bisa. Kami melayani konsultasi dan pemesanan via WhatsApp.
                    </div>
                </div>
                <div class="collapse collapse-plus bg-white border border-gray-200 rounded-none">
                    <input type="checkbox" />
                    <div class="collapse-title text-sm font-semibold text-brand-black">Lokasi toko di Palu?</div>
                    <div class="collapse-content text-sm text-gray-600">
                        Lokasi toko ada di Palu, Sulawesi Tengah. Detail alamat tersedia di halaman lokasi.
                    </div>
                </div>
            </div>
        </div>
    </section>

    </div>

    @push('scripts')
        @vite('resources/js/reactbits/about-lanyard-loader.js')
    @endpush
@endsection
