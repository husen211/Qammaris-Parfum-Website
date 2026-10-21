@extends('layouts.admin')

@php
    $card = 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5';
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10 sm:text-sm';
    $local = '0'.substr($customer->phone, 2);
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.customers.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-gray-900">← Pelanggan</a>
        <h1 class="mt-1 break-words text-3xl font-bold tracking-tight text-gray-900">{{ $customer->name }}</h1>
        <p class="mt-1 text-sm text-gray-600"><a href="https://wa.me/{{ $customer->phone }}" target="_blank" rel="noopener noreferrer" class="tabular-nums underline underline-offset-4">{{ $local }}</a> · {{ $orders->count() }} pesanan</p>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] lg:items-start">
        <section class="{{ $card }}" aria-labelledby="orders-title">
            <h2 id="orders-title" class="text-lg font-semibold text-gray-900">Riwayat pesanan</h2>
            <ul class="mt-3 divide-y divide-gray-100">
                @forelse ($orders as $order)
                    <li>
                        <a href="{{ route('admin.orders.show', $order) }}" class="flex min-h-11 items-start justify-between gap-4 py-3 hover:bg-gray-50" data-customer-order>
                            <span class="min-w-0 flex-1">
                                <span class="block font-mono text-sm font-semibold text-gray-900">{{ $order->code }}</span>
                                <span class="block truncate text-sm text-gray-600">{{ $order->itemSummary() }}</span>
                            </span>
                            <span class="shrink-0 text-right text-sm">
                                <span class="block font-semibold tabular-nums text-gray-900">{{ format_rupiah($order->customerTotal()) }}</span>
                                <span class="block text-xs text-gray-500">{{ \App\Support\OnlineOrderLabels::LIFECYCLE[$order->lifecycle] ?? $order->lifecycle }} · {{ $order->created_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y') }}</span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="py-3 text-sm text-gray-500">Belum ada pesanan.</li>
                @endforelse
            </ul>
        </section>

        <div class="space-y-6">
            <section class="{{ $card }}" aria-labelledby="data-title">
                <h2 id="data-title" class="text-lg font-semibold text-gray-900">Data pelanggan</h2>
                <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-3 space-y-3">
                    @csrf @method('PATCH')
                    <label class="block text-sm font-medium text-gray-700">Nama
                        <input name="name" value="{{ old('name', $customer->name) }}" required maxlength="100" class="{{ $input }}">
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Catatan <span class="font-normal text-gray-500">(opsional)</span>
                        <textarea name="note" rows="2" maxlength="300" class="{{ $input }}">{{ old('note', $customer->note) }}</textarea>
                    </label>
                    <p class="text-xs text-gray-500">Nomor WhatsApp mengikuti pesanan. Pesanan keluarga dengan nomor yang sama bisa dipindah ke pelanggan lain di halaman pesanannya.</p>
                    <button class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-900 hover:bg-gray-50" data-busy-label="Menyimpan…">Simpan</button>
                </form>
            </section>

            <section class="{{ $card }}" aria-labelledby="address-title">
                <h2 id="address-title" class="text-lg font-semibold text-gray-900">Alamat tersimpan</h2>
                <p class="mt-1 text-xs text-gray-500">Tersimpan otomatis saat pesanan diserahkan ke kurir.</p>
                <ul class="mt-3 space-y-3">
                    @forelse ($addresses as $address)
                        <li class="rounded-lg border border-gray-200 p-3" data-customer-address>
                            <p class="text-sm font-semibold text-gray-900">{{ $address->label }}</p>
                            <p class="mt-0.5 break-words text-sm text-gray-700">{{ $address->address }}{{ $address->postcode ? ' '.$address->postcode : '' }}</p>
                            <form method="POST" action="{{ route('admin.customers.addresses.archive', [$customer, $address]) }}" class="mt-1" data-confirm="Arsipkan alamat ini? Pesanan lama tetap menyimpan salinannya.">
                                @csrf
                                <button class="inline-flex min-h-11 items-center text-sm font-semibold text-gray-700 underline underline-offset-4">Arsipkan</button>
                            </form>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Belum ada alamat tersimpan.</li>
                    @endforelse
                </ul>
            </section>

            @can('customers.delete')
                <details class="{{ $card }} border-red-200" @if ($errors->has('confirm')) open @endif>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-semibold text-red-800">Hapus data pelanggan <span aria-hidden="true">▾</span></summary>
                    <p class="mt-2 text-sm text-gray-700">Nama, nomor WhatsApp, alamat, dan catatan dikosongkan di pelanggan ini dan semua pesanannya. Kode pesanan, barang, dan catatan uang tetap ada. Tidak bisa dibatalkan, dan hanya bila tidak ada pesanan yang masih berjalan.</p>
                    <form method="POST" action="{{ route('admin.customers.anonymize', $customer) }}" class="mt-3 space-y-3">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700">Ketik <strong>HAPUS</strong> untuk mengonfirmasi
                            <input name="confirm" autocomplete="off" autocapitalize="characters" required class="{{ $input }}">
                        </label>
                        <button class="min-h-11 w-full rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800" data-busy-label="Menghapus…">Hapus data pelanggan</button>
                    </form>
                </details>
            @endcan
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
// Confirmation for archiving; a cancelled confirm leaves the form untouched (the PWA guard sees defaultPrevented).
document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));
</script>
@endpush
