@extends('layouts.admin')

@php($time = fn ($value) => $value?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i'))
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <p class="text-sm font-medium text-gray-400">Integrasi</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Qammaris App — pesanan</h1>
        <p class="mt-1 text-sm text-gray-500">API v1 (baseline r4.1). Bila App bermasalah, pesanan tetap bisa ditangani di halaman Pesanan Online.</p>
    </div>

    <section class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Saklar</h2>
            <ul class="mt-2 space-y-1 text-sm">@foreach ($flags as $label => $on)<li class="flex justify-between gap-2"><span>{{ $label }}</span><span class="font-semibold {{ $on ? 'text-green-700' : 'text-gray-500' }}">{{ $on ? 'Aktif' : 'Mati' }}</span></li>@endforeach</ul>
            <p class="mt-2 text-xs text-gray-500">Tujuan webhook: {{ $webhookHost ?: '—' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Konfigurasi</h2>
            <ul class="mt-2 space-y-1 text-sm">@foreach ($configured as $label => $set)<li class="flex justify-between gap-2"><span>{{ $label }}</span><span class="font-semibold {{ $set ? 'text-green-700' : 'text-gray-500' }}">{{ $set ? 'Terisi' : 'Kosong' }}</span></li>@endforeach</ul>
            <p class="mt-2 text-xs text-gray-500">Nilai secret tidak pernah ditampilkan.</p>
        </div>
    </section>

    <dl class="grid grid-cols-3 gap-2 text-center text-sm">
        <div class="rounded-lg bg-white p-3 shadow-sm"><dt class="text-xs text-gray-500">Menunggu</dt><dd class="text-lg font-semibold">{{ $counts['pending'] }}</dd></div>
        <div class="rounded-lg bg-white p-3 shadow-sm"><dt class="text-xs text-gray-500">Gagal</dt><dd class="text-lg font-semibold {{ $counts['failed'] ? 'text-red-700' : '' }}">{{ $counts['failed'] }}</dd></div>
        <div class="rounded-lg bg-white p-3 shadow-sm"><dt class="text-xs text-gray-500">Terkirim 24 jam</dt><dd class="text-lg font-semibold">{{ $counts['delivered_24h'] }}</dd></div>
    </dl>

    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="failed-title">
        <h2 id="failed-title" class="text-lg font-semibold text-gray-900">Gagal setelah 24 jam</h2>
        @forelse ($failed as $event)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm" data-failed-event>
                <span class="min-w-0"><a href="{{ route('admin.orders.show', $event->online_order_id) }}" class="font-semibold underline">{{ $event->order?->code }}</a> · revisi {{ $event->revision }} · {{ $event->attempts }}× · {{ $event->last_error }}
                    <span class="block text-xs text-gray-600">Gagal {{ $time($event->failed_at) }} · {{ $event->event_id }}</span></span>
                <form method="POST" action="{{ route('admin.integrations.orders.resend', $event) }}">@csrf<button class="inline-flex min-h-11 items-center rounded-lg bg-black px-4 text-sm font-semibold text-white" data-busy-label="Mengirim…">Kirim ulang</button></form>
            </div>
        @empty
            <p class="mt-2 text-sm text-gray-600">Tidak ada event gagal.</p>
        @endforelse
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="pending-title">
        <h2 id="pending-title" class="text-lg font-semibold text-gray-900">Menunggu / dicoba ulang</h2>
        @forelse ($pending as $event)
            <p class="mt-2 text-sm text-gray-800">{{ $event->order?->code }} · revisi {{ $event->revision }} · {{ $event->attempts }}× · berikutnya {{ $time($event->next_attempt_at) }}@if ($event->last_error) · {{ $event->last_error }}@endif</p>
        @empty
            <p class="mt-2 text-sm text-gray-600">Tidak ada antrean.</p>
        @endforelse
    </section>
</div>
@endsection
