@extends('layouts.app')
@use('App\Support\OnlineOrderStepper')
@section('title', 'Pesanan Saya - Qammaris Perfumes')
@section('robots', 'noindex,nofollow')
@section('minimal_chrome', '1')
@push('meta')
    <meta name="referrer" content="no-referrer">
@endpush

{{-- ORD-04: orders opened in this browser session (no login). Each one opens through its own secure link. --}}
@php
    $status = fn ($order) => match (true) {
        $order->lifecycle === 'cancelled' => 'Dibatalkan',
        $order->lifecycle === 'draft' => 'Menunggu konfirmasi admin',
        $order->lifecycle === 'awaiting_customer' => 'Menunggu data penerima',
        default => [
            'pembayaran' => 'Menunggu pembayaran', 'persiapan' => 'Sedang disiapkan', 'pengiriman' => $order->fulfillment === 'pickup' ? 'Siap diambil' : 'Siap dikirim',
            'selesai' => $order->fulfillment === 'pickup' || $order->delivery_status === 'delivered' ? 'Selesai' : 'Dalam pengiriman',
        ][OnlineOrderStepper::current($order) ?? 'selesai'] ?? 'Diproses',
    };
@endphp

@section('content')
<section class="bg-white px-4 pb-16 pt-6 md:pt-10" aria-labelledby="mine-title" data-order-page>
    <div class="mx-auto max-w-xl">
        <h1 id="mine-title" class="font-mayluxa text-3xl text-brand-black md:text-4xl">Pesanan Saya</h1>
        <p class="mt-2 text-sm leading-6 text-gray-600">Pesanan yang dibuka di browser ini. Tanpa akun: daftar ini hilang bila sesi browser berakhir. Simpan link pesanan Anda.</p>

        @if (session('success'))
            <div role="status" class="mt-6 border border-emerald-200 bg-emerald-50 p-4 text-base text-emerald-900">{{ session('success') }}</div>
        @endif

        @forelse ($entries as $entry)
            @php($order = $entry['order'])
            <a href="{{ route('orders.customer.show', $entry['token']) }}" class="mt-4 flex min-h-16 items-center justify-between gap-3 border border-gray-200 bg-white p-4 [touch-action:manipulation] hover:border-brand-black active:bg-gray-50" data-my-order>
                <span class="min-w-0">
                    <span class="block text-base font-semibold text-brand-black">{{ $order->code }}</span>
                    <span class="block text-sm text-gray-600">{{ $order->created_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y') }} · {{ $order->items->sum('quantity') }} barang · {{ format_rupiah($order->customerTotal()) }}</span>
                </span>
                <span class="shrink-0 text-right text-sm font-medium text-brand-black">{{ $status($order) }} <span aria-hidden="true">›</span></span>
            </a>
        @empty
            <div class="mt-8 border border-gray-200 bg-[#FAF8F3] p-5 text-center">
                <p class="text-base text-brand-black">Belum ada pesanan di browser ini.</p>
                <p class="mt-1 text-sm text-gray-600">Pesanan lama tetap bisa dibuka dari link yang dikirim Qammaris.</p>
                <a href="{{ route('products.index') }}" class="mt-4 inline-flex min-h-12 items-center justify-center bg-brand-black px-6 text-sm font-semibold uppercase tracking-widest text-white">Lihat katalog</a>
            </div>
        @endforelse

        @if ($entries->isNotEmpty())
            <form method="POST" action="{{ route('orders.customer.forget') }}" class="mt-8">
                @csrf
                <button class="inline-flex min-h-11 items-center text-sm text-gray-700 underline underline-offset-4">Hapus daftar ini dari perangkat (mis. HP orang lain)</button>
            </form>
        @endif
    </div>
</section>
@endsection
