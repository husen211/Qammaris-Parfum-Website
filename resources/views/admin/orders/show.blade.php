@extends('layouts.admin')
@use('App\Models\OnlineOrder')

@php
    $next = $order->nextStage();
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $card = 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm';
    $copyButton = 'inline-flex min-h-11 items-center rounded-lg bg-black px-4 text-sm font-semibold text-white hover:bg-gray-800';
    $secondary = 'inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50';
    $time = fn ($value) => $value?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i');
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pesanan Online</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $order->code }}</h1>
            @include('admin.orders._stage-badge', ['order' => $order])
            @if ($order->recorded_in_majoo)<span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Tercatat di Majoo</span>@endif
        </div>
        <p class="mt-1 text-sm text-gray-500">Dibuat {{ $time($order->created_at) }} · {{ $order->customer_name ?? 'menunggu data customer' }}</p>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Periksa kembali:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
        <div class="min-w-0 space-y-6">
            {{-- Next step --}}
            <section class="{{ $card }}" aria-labelledby="step-title">
                <h2 id="step-title" class="text-lg font-semibold text-gray-900">Status</h2>
                @if ($next && $next !== OnlineOrder::STAGE_DETAILS_RECEIVED)
                    <form method="POST" action="{{ route('admin.orders.advance', $order) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf @method('PATCH')
                        <input type="hidden" name="from" value="{{ $order->stage }}">
                        <input type="hidden" name="to" value="{{ $next }}">
                        @if ($next === OnlineOrder::STAGE_PAID)
                            <label class="text-sm font-medium text-gray-700">Metode pembayaran
                                <select name="payment_method" required class="{{ $input }} sm:w-48">
                                    <option value="">Pilih…</option>
                                    @foreach (OnlineOrder::PAYMENT_METHODS as $value => $label)<option value="{{ $value }}" @selected($order->payment_method === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </label>
                        @elseif ($next === OnlineOrder::STAGE_SHIPPED)
                            <label class="text-sm font-medium text-gray-700">Kurir
                                <select name="courier" required class="{{ $input }} sm:w-48">
                                    <option value="">Pilih…</option>
                                    @foreach (OnlineOrder::COURIERS as $value => $label)<option value="{{ $value }}" @selected(($order->courier ?? ($order->fulfillment === 'intercity' ? 'jnt' : null)) === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">Nomor resi <span class="font-normal text-gray-500">(wajib J&T)</span>
                                <input name="tracking_number" value="{{ $order->tracking_number }}" maxlength="40" class="{{ $input }} sm:w-56">
                            </label>
                        @endif
                        <button class="{{ $copyButton }}">Tandai: {{ $order->stageLabel($next) }}</button>
                    </form>
                    @if ($next === OnlineOrder::STAGE_SHIPPED)
                        <p class="mt-2 text-sm text-gray-500">Dikirim = driver/J&T sudah dipesan dan barang segera jalan.{{ $order->courier_booked_by === 'staff' ? ' Staf juga bisa menandai dari link tugas.' : '' }}</p>
                    @elseif ($next === OnlineOrder::STAGE_COMPLETED && $order->fulfillment !== 'pickup')
                        <p class="mt-2 text-sm text-gray-500">Customer bisa menandai sendiri dari link-nya; staf juga bisa.</p>
                    @endif
                @elseif ($order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER)
                    <p class="mt-2 text-sm text-gray-600">Menunggu customer mengisi link. Bila customer kesulitan, isi datanya di form Detail pesanan.</p>
                @elseif ($order->stage === OnlineOrder::STAGE_COMPLETED)
                    <p class="mt-2 text-sm text-gray-600">Pesanan selesai {{ $time($order->closed_at) }}.</p>
                @elseif ($order->stage === OnlineOrder::STAGE_CANCELLED)
                    <p class="mt-2 text-sm text-gray-600">Dibatalkan: {{ $order->cancel_reason }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                    @if (! in_array($order->stage, [OnlineOrder::STAGE_AWAITING_CUSTOMER, OnlineOrder::STAGE_DETAILS_RECEIVED], true) && auth()->user()->can('orders.finance'))
                        <form method="POST" action="{{ route('admin.orders.revert', $order) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="from" value="{{ $order->stage }}">
                            <button class="{{ $secondary }}">{{ $order->stage === OnlineOrder::STAGE_CANCELLED ? 'Pulihkan pesanan' : 'Koreksi: batalkan “'.$order->stageLabel().'”' }}</button>
                        </form>
                    @endif
                    @if (! $order->isClosed() && auth()->user()->can('orders.cancel', $order))
                        <details class="w-full sm:w-auto">
                            <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Batalkan pesanan…</summary>
                            <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                @csrf @method('PATCH')
                                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Alasan
                                    <input name="cancel_reason" required minlength="3" maxlength="200" class="{{ $input }}">
                                </label>
                                <button class="inline-flex min-h-11 items-center rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">Ya, batalkan</button>
                            </form>
                        </details>
                    @elseif (! $order->isClosed())
                        <p class="text-sm text-gray-500">Pesanan yang sudah dibayar atau diserahkan hanya dapat dibatalkan oleh Super Admin.</p>
                    @endif
                </div>
            </section>

            @can('orders.refund')
                @if ($order->refund_status)
                    @include('admin.orders.v2._money-panel')
                @endif
            @endcan

            {{-- Staff group message --}}
            <section class="{{ $card }}" aria-labelledby="group-title">
                <h2 id="group-title" class="text-lg font-semibold text-gray-900">Pesan untuk grup staf</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $order->fulfillment === 'pickup' ? 'Customer ambil di toko.' : ($order->courier_booked_by === 'staff' ? 'Mode: minta staf pesankan driver/J&T.' : 'Mode: admin yang pesan driver.') }}
                    Ubah di Detail pesanan → Pengiriman.
                </p>
                <label for="group-message" class="sr-only">Isi pesan grup</label>
                <textarea id="group-message" readonly rows="15" class="mt-3 w-full rounded-lg border border-gray-200 bg-gray-50 p-3 font-mono text-sm leading-6 text-gray-800">{{ $groupMessage }}</textarea>
                <div class="mt-3 flex flex-wrap gap-3">
                    <button type="button" class="{{ $copyButton }}" data-copy="#group-message">Salin untuk grup</button>
                    <a href="{{ $groupShareUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $secondary }}">Buka WhatsApp</a>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <label for="staff-link" class="block text-sm font-medium text-gray-700">Link tugas staf</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <input id="staff-link" readonly value="{{ $staffUrl }}" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 font-mono text-xs">
                        <button type="button" class="{{ $secondary }}" data-copy="#staff-link">Salin</button>
                        <form method="POST" action="{{ route('admin.orders.regenerate-link', $order) }}">
                            @csrf <input type="hidden" name="audience" value="staff">
                            <button class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-gray-600 hover:bg-gray-100">Buat link baru</button>
                        </form>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Siapa pun yang memegang link ini dapat menandai langkah pengiriman. Buat link baru bila tersebar di luar grup.</p>
                </div>
            </section>

            {{-- Customer link --}}
            <section class="{{ $card }}" aria-labelledby="customer-link-title">
                <h2 id="customer-link-title" class="text-lg font-semibold text-gray-900">Link untuk customer</h2>
                @if ($order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER && ! $order->customerLinkUsable())
                    <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Link sudah kedaluwarsa. Buat link baru lalu kirim ulang.</p>
                @elseif ($order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER)
                    <p class="mt-1 text-sm text-gray-500">Berlaku sampai {{ $time($order->customer_link_expires_at) }}. Setelah diisi, customer memakai link yang sama untuk melihat status.</p>
                @else
                    <p class="mt-1 text-sm text-gray-500">Customer memakai link ini untuk melihat status{{ $order->customerCanEdit() ? ' dan mengubah data sampai pembayaran dikonfirmasi' : '' }}.</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    <label for="customer-link" class="sr-only">Link customer</label>
                    <input id="customer-link" readonly value="{{ $customerUrl }}" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 font-mono text-xs">
                    <button type="button" class="{{ $secondary }}" data-copy="#customer-link">Salin link</button>
                </div>
                <label for="invite-message" class="mt-4 block text-sm font-medium text-gray-700">Pesan untuk customer</label>
                <textarea id="invite-message" readonly rows="7" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-6">{{ $inviteMessage }}</textarea>
                <div class="mt-3 flex flex-wrap gap-3">
                    <button type="button" class="{{ $copyButton }}" data-copy="#invite-message">Salin pesan</button>
                    <a href="{{ $inviteUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $secondary }}">{{ $order->customer_phone ? 'Kirim ke '.$order->customer_phone : 'Buka WhatsApp' }}</a>
                    <form method="POST" action="{{ route('admin.orders.regenerate-link', $order) }}">
                        @csrf <input type="hidden" name="audience" value="customer">
                        <button class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-gray-600 hover:bg-gray-100">Buat link baru</button>
                    </form>
                </div>
            </section>

            {{-- Timeline and activity --}}
            <section class="{{ $card }}" aria-labelledby="timeline-title">
                <h2 id="timeline-title" class="text-lg font-semibold text-gray-900">Perjalanan pesanan</h2>
                <div class="mt-4"><x-order-timeline :items="$timeline" /></div>
                <details class="mt-4 border-t border-gray-100 pt-4">
                    <summary class="min-h-11 cursor-pointer text-sm font-semibold text-gray-700">Riwayat aktivitas ({{ $order->events->count() }})</summary>
                    <ol class="mt-2 space-y-2 text-sm">
                        @foreach ($order->events->reverse() as $event)
                            <li class="flex flex-wrap gap-x-2 text-gray-700">
                                <span class="tabular-nums text-gray-500">{{ $time($event->created_at) }}</span>
                                <span class="font-medium">{{ $event->actorLabel() }}</span>
                                <span>{{ match ($event->kind) {
                                    'created' => 'membuat pesanan',
                                    'advance' => 'menandai '.$order->stageLabel($event->stage),
                                    'revert' => 'mengoreksi (membatalkan '.$order->stageLabel($event->stage).')',
                                    'cancel' => 'membatalkan pesanan',
                                    'details_updated' => 'mengubah detail',
                                    'link_regenerated' => 'membuat ulang link',
                                    'advance_recorded' => 'mencatat talangan ongkir',
                                    'reimbursed' => 'mengganti talangan',
                                    default => $event->kind,
                                } }}@if ($event->note): {{ $event->note }}@endif</span>
                            </li>
                        @endforeach
                    </ol>
                </details>
            </section>
        </div>

        <div class="min-w-0 space-y-6">
            {{-- Items and totals --}}
            <section class="{{ $card }}" aria-labelledby="items-title">
                <h2 id="items-title" class="text-lg font-semibold text-gray-900">Produk</h2>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="min-w-0"><span class="block font-medium text-gray-900">{{ $item->label() }}</span><span class="text-gray-500">{{ $item->brand_name }} · {{ $item->quantity }} × {{ format_rupiah($item->unit_price) }}</span></span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ format_rupiah($item->lineTotal()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-3 space-y-1 border-t border-gray-100 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">Subtotal</dt><dd class="tabular-nums">{{ format_rupiah($order->subtotal()) }}</dd></div>
                    @if ($order->shipping_fee !== null)
                        <div class="flex justify-between"><dt class="text-gray-600">Ongkir</dt><dd class="tabular-nums">{{ format_rupiah($order->shipping_fee) }}</dd></div>
                    @endif
                    <div class="flex justify-between text-base font-semibold"><dt>Ditagih ke customer</dt><dd class="tabular-nums">{{ format_rupiah($order->customerTotal()) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-gray-500">Harga dikunci saat pesanan dibuat; perubahan katalog tidak mengubah pesanan ini.</p>
            </section>

            @if ($order->staff_advance_amount !== null)
                <section class="rounded-xl border {{ $order->needsReimbursement() ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white' }} p-5 shadow-sm" aria-labelledby="advance-title">
                    <h2 id="advance-title" class="text-lg font-semibold text-gray-900">Talangan ongkir staf</h2>
                    <p class="mt-1 text-sm text-gray-800">{{ format_rupiah($order->staff_advance_amount) }} oleh {{ $order->staff_advance_by }}</p>
                    @if ($order->needsReimbursement())
                        @can('orders.finance')
                        <form method="POST" action="{{ route('admin.orders.reimburse', $order) }}" class="mt-3">
                            @csrf @method('PATCH')
                            <button class="{{ $copyButton }}">Tandai sudah diganti</button>
                        </form>
                        @else
                        <p class="mt-2 text-sm text-gray-600">Penggantian talangan ditandai oleh Super Admin.</p>
                        @endcan
                    @else
                        <p class="mt-1 text-sm text-green-800">Sudah diganti {{ $time($order->staff_reimbursed_at) }}</p>
                    @endif
                </section>
            @endif

            {{-- Editable details --}}
            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="{{ $card }} space-y-5" aria-labelledby="details-title">
                @csrf @method('PATCH')
                <input type="hidden" name="revision" value="{{ $order->revision }}">
                <h2 id="details-title" class="text-lg font-semibold text-gray-900">Detail pesanan</h2>
                @if ($order->customer_phone && $customerChatUrl)
                    <a href="{{ $customerChatUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center text-sm text-gray-700 underline underline-offset-4">Chat customer di WhatsApp</a>
                @endif
                <fieldset>
                    <legend class="text-sm font-semibold uppercase tracking-wider text-gray-500">Customer</legend>
                    <div class="mt-3">@include('admin.orders._customer-fields', ['order' => $order])</div>
                </fieldset>

                <fieldset class="space-y-4 border-t border-gray-100 pt-4">
                    <legend class="pt-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Pengiriman</legend>
                    @cannot('orders.finance')
                        <p id="finance-locked" class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">Ongkir dan pendanaan driver hanya dapat diubah oleh Super Admin.</p>
                    @endcannot
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Siapa pesan driver/J&T?</span>
                        <div class="mt-1 flex flex-wrap gap-4">
                            @foreach (OnlineOrder::BOOKERS as $value => $label)
                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="courier_booked_by" value="{{ $value }}" @checked(old('courier_booked_by', $order->courier_booked_by) === $value) class="h-4 w-4"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">Kurir
                            <select name="courier" class="{{ $input }}">
                                <option value="">Belum dipilih</option>
                                @foreach (OnlineOrder::COURIERS as $value => $label)<option value="{{ $value }}" @selected(old('courier', $order->courier) === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Nomor resi
                            <input name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" maxlength="40" class="{{ $input }}">
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Link lokasi Google Maps <span class="font-normal text-gray-500">(opsional, dari sharelok customer)</span>
                        <input name="location_url" type="url" inputmode="url" value="{{ old('location_url', $order->location_url) }}" maxlength="500" placeholder="https://maps.app.goo.gl/…" class="{{ $input }}">
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">Ongkir (Rp)
                            <input name="shipping_fee" @cannot('orders.finance') disabled aria-describedby="finance-locked" @endcannot inputmode="numeric" value="{{ old('shipping_fee', $order->shipping_fee !== null ? (int) $order->shipping_fee : null) }}" placeholder="11500" class="{{ $input }}">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Ongkir dibayar
                            <select name="shipping_payer" @cannot('orders.finance') disabled aria-describedby="finance-locked" @endcannot class="{{ $input }} disabled:bg-gray-100">
                                <option value="">Belum ditentukan</option>
                                @foreach (OnlineOrder::SHIPPING_PAYERS as $value => $label)<option value="{{ $value }}" @selected(old('shipping_payer', $order->shipping_payer) === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Toko bayar driver dengan
                        <select name="driver_funding" @cannot('orders.finance') disabled aria-describedby="finance-locked" @endcannot class="{{ $input }} disabled:bg-gray-100">
                            <option value="">Tidak perlu / belum ditentukan</option>
                            @foreach (OnlineOrder::DRIVER_FUNDING as $value => $label)<option value="{{ $value }}" @selected(old('driver_funding', $order->driver_funding) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </fieldset>

                <fieldset class="space-y-4 border-t border-gray-100 pt-4">
                    <legend class="pt-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Pembayaran & internal</legend>
                    <label class="block text-sm font-medium text-gray-700">Metode pembayaran
                        <select name="payment_method" class="{{ $input }}">
                            <option value="">Belum dipilih</option>
                            @foreach (OnlineOrder::PAYMENT_METHODS as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', $order->payment_method) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="recorded_in_majoo" value="1" @checked(old('recorded_in_majoo', $order->recorded_in_majoo)) class="h-5 w-5"> Sudah dicatat di Majoo
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Catatan untuk staf
                        <input name="staff_note" value="{{ old('staff_note', $order->staff_note) }}" maxlength="300" placeholder="Misalnya: titip ke satpam" class="{{ $input }}">
                    </label>
                </fieldset>
                <button class="{{ $copyButton }} w-full justify-center">Simpan detail</button>
            </form>
        </div>
    </div>
</div>
<p data-copy-feedback role="status" aria-live="polite" class="sr-only"></p>
@endsection

@push('scripts')
<script>
(() => {
    const feedback = document.querySelector('[data-copy-feedback]');
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        const field = document.querySelector(button.dataset.copy);
        const label = button.textContent;
        let copied = false;
        try {
            await navigator.clipboard.writeText(field.value);
            copied = true;
        } catch (error) {
            field.focus();
            field.select();
            try { copied = document.execCommand('copy'); } catch (fallbackError) { copied = false; }
        }
        button.textContent = copied ? 'Tersalin ✓' : 'Teks sudah dipilih, tekan Ctrl+C';
        feedback.textContent = copied ? 'Teks tersalin.' : 'Salin otomatis tidak tersedia; teks sudah dipilih.';
        setTimeout(() => { button.textContent = label; }, 2500);
    }));
})();
</script>
@endpush
