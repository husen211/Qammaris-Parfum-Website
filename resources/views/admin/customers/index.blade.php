@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-sm font-medium text-gray-400">Penjualan</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Pelanggan</h1>
        <p class="mt-1 text-sm text-gray-500">Tersimpan otomatis dari pesanan online. Satu nomor WhatsApp = satu pelanggan.</p>
    </div>

    <form method="GET" action="{{ route('admin.customers.index') }}" role="search" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
        <label for="customer-search" class="sr-only">Cari pelanggan (nama atau nomor WhatsApp)</label>
        <input id="customer-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama atau nomor WhatsApp" enterkeyhint="search" autocomplete="off" class="min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10 sm:text-sm">
        @if ($search !== '')
            <p class="mt-2 flex flex-wrap items-center gap-x-3 text-sm text-gray-600">Hasil cari “{{ $search }}”. <a href="{{ route('admin.customers.index') }}" class="inline-flex min-h-11 items-center font-semibold text-gray-900 underline underline-offset-4">Hapus pencarian</a></p>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_7rem_10rem] gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-500 lg:grid">
            <span>Nama</span><span>WhatsApp</span><span class="text-right">Pesanan</span><span class="text-right">Pesanan terakhir</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($customers as $customer)
                <a href="{{ route('admin.customers.show', $customer) }}" class="grid gap-1 p-5 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_7rem_10rem] lg:items-center lg:gap-4" data-customer-row>
                    <span class="truncate font-semibold text-gray-900">{{ $customer->name }}</span>
                    <span class="text-sm tabular-nums text-gray-700">{{ '0'.substr($customer->phone, 2) }}</span>
                    <span class="text-sm text-gray-600 lg:text-right">{{ $customer->orders_count }} pesanan</span>
                    <span class="text-xs text-gray-500 lg:text-right">{{ $customer->orders_max_created_at ? \Illuminate\Support\Carbon::parse($customer->orders_max_created_at)->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y') : 'Belum ada' }}</span>
                </a>
            @empty
                <div class="px-5 py-14 text-center">
                    <p class="font-semibold text-gray-900">{{ $search !== '' ? 'Pelanggan tidak ditemukan' : 'Belum ada pelanggan' }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $search !== '' ? 'Coba nama atau nomor lain.' : 'Pelanggan tersimpan otomatis saat pesanan punya nama dan nomor WhatsApp.' }}</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($customers->hasPages())
        <div>{{ $customers->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
