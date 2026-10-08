@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-400">Penjualan</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Pesanan Online</h1>
            <p class="mt-1 text-sm text-gray-500">Pesanan dari WhatsApp: link data customer, pembayaran, pengiriman, dan tugas staf.</p>
        </div>
        <a href="{{ route('admin.orders.create') }}" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-black px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 sm:w-auto">+ Buat pesanan</a>
    </div>

    @if ($reimburseCount > 0 && $filter !== 'reimburse')
        <a href="{{ route('admin.orders.index', ['status' => 'reimburse']) }}" class="flex min-h-11 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 hover:bg-amber-100">
            {{ $reimburseCount }} talangan ongkir staf belum diganti →
        </a>
    @endif

    <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row">
        <label for="order-status" class="sr-only">Status</label>
        <select id="order-status" name="status" class="min-h-11 rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10">
            @foreach (\App\Http\Controllers\Admin\AdminOnlineOrderController::FILTERS as $value => $label)
                <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="order-search" class="sr-only">Cari pesanan</label>
        <input id="order-search" name="search" type="search" value="{{ $search }}" placeholder="Kode, nama, atau nomor HP…" class="min-h-11 flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10">
        <button class="min-h-11 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Tampilkan</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[7rem_minmax(0,1.2fr)_minmax(0,1.5fr)_10rem_9rem] gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-500 lg:grid">
            <span>Kode</span><span>Customer</span><span>Produk</span><span>Status</span><span class="text-right">Dibuat</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($orders as $order)
                <a href="{{ route('admin.orders.show', $order) }}" class="grid gap-2 p-5 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none lg:grid-cols-[7rem_minmax(0,1.2fr)_minmax(0,1.5fr)_10rem_9rem] lg:items-center lg:gap-4">
                    <span class="font-mono text-sm font-semibold text-gray-900">{{ $order->code }}</span>
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-gray-900">{{ $order->customer_name ?? 'Menunggu customer' }}</span>
                        <span class="block text-xs text-gray-500">{{ \App\Models\OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? '—' }}</span>
                    </span>
                    <span class="min-w-0 text-sm text-gray-700"><span class="line-clamp-2">{{ $order->itemSummary() }}</span></span>
                    <span class="flex flex-wrap gap-1">
                        @include('admin.orders._stage-badge', ['order' => $order])
                        @if ($order->needsReimbursement())<span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Talangan</span>@endif
                    </span>
                    <span class="text-xs text-gray-500 lg:text-right">{{ $order->created_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i') }}</span>
                </a>
            @empty
                <div class="px-5 py-14 text-center">
                    <p class="font-semibold text-gray-900">Belum ada pesanan</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $search !== '' || $filter !== 'active' ? 'Coba filter atau kata pencarian lain.' : 'Buat pesanan saat customer sudah fix order di WhatsApp.' }}</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($orders->hasPages())
        <div>{{ $orders->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
