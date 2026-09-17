@extends('layouts.app')

@section('title', 'Daftar Inquiry - Qammaris Perfumes')
@section('robots', 'noindex,nofollow')

@section('content')
    <section class="min-h-screen bg-white pb-20 pt-28 md:pt-32" aria-labelledby="inquiry-title">
        <div class="container mx-auto px-4 lg:px-20">
            <header class="mx-auto mb-10 max-w-2xl text-center md:mb-14">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-gold">Konfirmasi melalui WhatsApp</p>
                <h1 id="inquiry-title" class="mt-3 font-mayluxa text-4xl text-brand-black lg:text-5xl">Daftar Inquiry</h1>
                <p class="mx-auto mt-4 max-w-xl text-sm font-light leading-6 text-gray-500">
                    Kumpulkan parfum yang ingin ditanyakan. Stok dan harga terbaru tetap dikonfirmasi oleh admin; daftar ini bukan reservasi.
                </p>
            </header>

            @if (session('error'))
                <div class="mx-auto mb-8 max-w-4xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mx-auto mb-8 max-w-4xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Periksa kembali catatan inquiry sebelum melanjutkan.
                </div>
            @endif

            @if ($hasUnavailableItems)
                <div class="mx-auto max-w-2xl border border-amber-200 bg-amber-50 p-6 text-center md:p-8">
                    <h2 class="font-mayluxa text-2xl text-brand-black">Daftar perlu ditinjau</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Satu atau lebih produk sudah tidak tayang atau offer-nya berubah. Daftar lama tidak akan dikirim agar informasi yang diteruskan tidak keliru.
                    </p>
                    <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ route('products.index') }}" class="inline-flex min-h-12 items-center justify-center bg-brand-black px-6 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                            Kembali ke katalog
                        </a>
                        <form action="{{ route('cart.clear') }}" method="POST">
                            @csrf
                            <button type="submit" class="min-h-12 w-full border border-brand-black px-6 text-xs font-semibold uppercase tracking-widest text-brand-black hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                                Kosongkan daftar lama
                            </button>
                        </form>
                    </div>
                </div>
            @elseif ($items !== [])
                <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-16">
                    <section aria-labelledby="inquiry-items-title">
                        <div class="flex items-end justify-between gap-4 border-b border-gray-200 pb-4">
                            <div>
                                <h2 id="inquiry-items-title" class="font-mayluxa text-2xl text-brand-black">Produk yang ditanyakan</h2>
                                <p class="mt-1 text-xs text-gray-500">Jumlah menunjukkan minat, bukan stok yang direservasi.</p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold uppercase tracking-widest text-gray-400">{{ count($items) }} produk</span>
                        </div>

                        <div class="divide-y divide-gray-200">
                            @foreach ($items as $item)
                                @php
                                    $availabilityClass = match ($item['effective_availability']) {
                                        \App\Models\Product::AVAILABILITY_AVAILABLE => 'text-emerald-700',
                                        \App\Models\Product::AVAILABILITY_SOLD_OUT => 'text-gray-500',
                                        default => 'text-amber-700',
                                    };
                                @endphp
                                <article class="grid gap-5 py-6 sm:grid-cols-[6rem_minmax(0,1fr)_auto] sm:items-center" data-inquiry-row data-id="{{ $item['id'] }}">
                                    <a href="{{ $item['product_url'] }}" class="h-32 w-24 overflow-hidden bg-[#FAF8F3] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                                        <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" width="96" height="128" loading="lazy" decoding="async" class="h-full w-full object-contain">
                                    </a>

                                    <div class="min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">{{ $item['brand_name'] }}</p>
                                        <h3 class="mt-1 font-mayluxa text-xl leading-tight text-brand-black">
                                            <a href="{{ $item['product_url'] }}" class="hover:text-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">{{ $item['product_name'] }}</a>
                                        </h3>
                                        <p class="mt-2 text-sm text-gray-500">{{ $item['volume'] }} ml · {{ $item['formatted_unit_price'] }} per item</p>
                                        <p class="mt-2 text-xs font-semibold {{ $availabilityClass }}">{{ $item['availability_label'] }}</p>
                                        <button type="button" onclick="removeInquiryItem('{{ $item['id'] }}')"
                                            class="mt-4 min-h-11 text-xs font-semibold uppercase tracking-widest text-gray-500 underline decoration-gray-300 underline-offset-4 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                                            Hapus dari daftar
                                        </button>
                                    </div>

                                    <div class="flex items-end justify-between gap-5 sm:flex-col sm:items-end">
                                        <p class="text-lg font-semibold tabular-nums text-brand-black">{{ $item['formatted_price'] }}</p>
                                        <div>
                                            <span class="mb-2 block text-[10px] font-semibold uppercase tracking-widest text-gray-400">Jumlah minat</span>
                                            <div class="flex h-11 items-center border border-gray-300">
                                                <button type="button" onclick="updateInquiryQuantity('{{ $item['id'] }}', -1)" aria-label="Kurangi jumlah {{ $item['product_name'] }}" class="flex h-11 w-11 items-center justify-center text-lg text-gray-500 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-black">−</button>
                                                <input type="number" id="qty-{{ $item['id'] }}" value="{{ $item['quantity'] }}" min="1" max="99" aria-label="Jumlah {{ $item['product_name'] }}" class="h-11 w-11 border-none p-0 text-center text-sm font-semibold focus:ring-0" readonly>
                                                <button type="button" onclick="updateInquiryQuantity('{{ $item['id'] }}', 1)" aria-label="Tambah jumlah {{ $item['product_name'] }}" class="flex h-11 w-11 items-center justify-center text-lg text-brand-black hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-black">+</button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <p data-inquiry-feedback role="status" aria-live="polite" class="min-h-6 text-sm text-gray-600"></p>

                        <div class="mt-6 flex flex-col justify-between gap-4 border-t border-gray-200 pt-6 sm:flex-row sm:items-center">
                            <a href="{{ route('products.index') }}" class="inline-flex min-h-11 items-center text-xs font-semibold uppercase tracking-widest underline decoration-gray-300 underline-offset-4 hover:text-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                                Tambah produk lain
                            </a>
                            <form action="{{ route('cart.clear') }}" method="POST" onsubmit="return confirm('Kosongkan seluruh daftar inquiry?')">
                                @csrf
                                <button type="submit" class="min-h-11 text-xs font-semibold uppercase tracking-widest text-gray-500 hover:text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                                    Kosongkan daftar
                                </button>
                            </form>
                        </div>
                    </section>

                    <aside class="border border-gray-200 bg-[#FAF8F3] p-6 lg:sticky lg:top-28 lg:p-8" aria-labelledby="inquiry-summary-title">
                        <h2 id="inquiry-summary-title" class="font-mayluxa text-2xl text-brand-black">Ringkasan inquiry</h2>

                        <div class="mt-6 border-y border-gray-200 py-5">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Estimasi nilai produk</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">Berdasarkan harga yang tampil saat ini.</p>
                                </div>
                                <p class="shrink-0 text-xl font-semibold tabular-nums text-brand-black">{{ format_rupiah($estimateTotal) }}</p>
                            </div>
                        </div>

                        <form action="{{ route('cart.checkout') }}" method="POST" class="mt-6">
                            @csrf
                            <label for="customer_note" class="block text-[10px] font-semibold uppercase tracking-widest text-gray-500">Catatan untuk admin (opsional)</label>
                            <textarea id="customer_note" name="customer_note" rows="3" maxlength="200" aria-describedby="inquiry-note-help{{ $errors->has('customer_note') ? ' inquiry-note-error' : '' }}"
                                class="mt-2 w-full resize-none border border-gray-300 bg-white px-3 py-3 text-sm leading-6 focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black"
                                placeholder="Contoh: ingin cek ketersediaan di toko">{{ old('customer_note') }}</textarea>
                            <p id="inquiry-note-help" class="mt-2 text-xs leading-5 text-gray-500">Nama, nomor, dan alamat tidak diperlukan; percakapan dilanjutkan langsung di WhatsApp.</p>
                            @error('customer_note')
                                <p id="inquiry-note-error" class="mt-2 text-xs text-red-700">{{ $message }}</p>
                            @enderror

                            @if ($whatsappAvailable)
                                <button type="submit" class="mt-6 flex min-h-14 w-full items-center justify-center bg-brand-black px-5 text-xs font-semibold uppercase tracking-[0.16em] text-white hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                                    Tanyakan via WhatsApp
                                </button>
                            @else
                                <button type="button" disabled class="mt-6 flex min-h-14 w-full cursor-not-allowed items-center justify-center bg-gray-200 px-5 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                                    Kontak WhatsApp belum tersedia
                                </button>
                            @endif
                        </form>

                        <p class="mt-4 text-center text-xs leading-5 text-gray-500">
                            Admin akan mengonfirmasi stok dan harga terbaru. Mengirim inquiry tidak menyimpan stok atau membuat transaksi.
                        </p>
                    </aside>
                </div>
            @else
                <div class="mx-auto flex max-w-xl flex-col items-center justify-center py-12 text-center md:py-20">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full border border-gray-200 text-3xl text-gray-300" aria-hidden="true">?</div>
                    <h2 class="mt-6 font-mayluxa text-2xl text-brand-black">Daftar inquiry masih kosong</h2>
                    <p class="mt-3 max-w-md text-sm font-light leading-6 text-gray-500">Tambahkan parfum dari katalog untuk menanyakan stok dan harga terbaru kepada admin.</p>
                    <a href="{{ route('products.index') }}" class="mt-8 inline-flex min-h-12 items-center justify-center bg-brand-black px-8 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                        Lihat katalog
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
<script>
const inquiryFeedback = document.querySelector('[data-inquiry-feedback]');

function setInquiryFeedback(message, isError = false) {
    if (!inquiryFeedback) return;
    inquiryFeedback.textContent = message;
    inquiryFeedback.classList.toggle('text-red-700', isError);
    inquiryFeedback.classList.toggle('text-gray-600', !isError);
}

async function updateInquiryQuantity(itemId, change) {
    const input = document.getElementById(`qty-${itemId}`);
    if (!input) return;

    const previous = Number.parseInt(input.value, 10);
    const quantity = Math.min(Math.max(previous + change, 1), 99);
    if (quantity === previous) return;

    input.value = quantity;
    setInquiryFeedback('Memperbarui daftar…');

    try {
        const response = await fetch(`/cart/update/${encodeURIComponent(itemId)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ quantity }),
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Jumlah belum dapat diperbarui.');
        }

        window.location.reload();
    } catch (error) {
        input.value = previous;
        setInquiryFeedback(error.message || 'Jumlah belum dapat diperbarui. Coba lagi.', true);
    }
}

async function removeInquiryItem(itemId) {
    setInquiryFeedback('Menghapus produk dari daftar…');

    try {
        const response = await fetch(`/cart/remove/${encodeURIComponent(itemId)}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Produk belum dapat dihapus.');
        }

        window.location.reload();
    } catch (error) {
        setInquiryFeedback(error.message || 'Produk belum dapat dihapus. Coba lagi.', true);
    }
}
</script>
@endpush
