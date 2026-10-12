@extends('orders.task-layout')
@section('title', 'Tugas '.$order->code.' - Qammaris')

@use('App\Models\OnlineOrder')
@php
    $next = $order->nextStage();
    $fieldClass = 'mt-2 min-h-12 w-full border border-gray-300 bg-white px-3 py-3 text-base focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black';
    $buttonClass = 'flex min-h-12 w-full items-center justify-center px-3 text-center text-sm font-semibold [touch-action:manipulation]';
    $pickup = $order->fulfillment === 'pickup';
    $staffBooks = $order->courier_booked_by === 'staff' && ! $pickup;
    $courierName = OnlineOrder::COURIERS[$order->courier] ?? null;
    $shipping = $next === OnlineOrder::STAGE_SHIPPED;
    $stepHint = match (true) {
        $shipping => 'Tekan setelah driver/J&T sudah dipesan dan barang diserahkan atau siap dijemput.',
        $next === OnlineOrder::STAGE_COMPLETED && $pickup => 'Tekan setelah customer mengambil pesanannya.',
        $next === OnlineOrder::STAGE_COMPLETED => 'Customer juga bisa menandai sendiri. Tekan bila sudah dipastikan sampai.',
        default => null,
    };
    $canAdvanceCost = ! $pickup && $order->stage !== OnlineOrder::STAGE_CANCELLED
        && in_array($order->driver_funding, [null, 'staff_advance'], true) && $order->staff_reimbursed_at === null;
@endphp

@section('content')
<div class="mx-auto max-w-xl px-4 pb-16 pt-5">
    <p class="text-sm text-gray-600">Status: <strong class="text-brand-black">{{ $order->stageLabel() }}</strong></p>
    <h1 class="mt-1 font-mayluxa text-[1.75rem] leading-tight sm:text-3xl">Pesanan {{ $order->code }}</h1>

    @if (session('success'))
        <div role="status" class="mt-4 border border-emerald-200 bg-emerald-50 p-4 text-base text-emerald-900">{{ session('success') }}</div>
    @endif
    @if (session('error') || $errors->any())
        <div role="alert" class="mt-4 border border-red-200 bg-red-50 p-4 text-base text-red-800">
            {{ session('error') ?? 'Periksa isian yang ditandai.' }}
            @if ($errors->any())<ul class="mt-2 list-disc pl-5 text-sm">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
    @endif

    @if ($order->stage === OnlineOrder::STAGE_CANCELLED)
        <p class="mt-4 border-l-4 border-red-700 bg-red-50 p-4 text-base">Pesanan dibatalkan. Tidak perlu diproses.</p>
    @elseif (! $order->isClosed())
        <p class="mt-4 border-l-4 border-brand-gold bg-[#FAF8F3] p-4 text-base leading-7">
            @if ($pickup)
                Customer <strong>mengambil sendiri di toko</strong>. Siapkan pesanannya.
            @elseif ($staffBooks && $order->fulfillment === 'intercity')
                <strong>Tugas: request pickup {{ $courierName ?? 'J&T' }}</strong> ke alamat di bawah, lalu tandai Dikirim.
            @elseif ($staffBooks)
                <strong>Tugas: pesankan {{ $courierName ?? 'driver' }}</strong> ke lokasi penerima, lalu tandai Dikirim.
            @else
                Driver/kurir <strong>dipesan admin</strong>. Siapkan pesanan dan tandai Dikirim saat diserahkan.
            @endif
        </p>
    @endif

    @if ($staffStep && $next)
        <form method="POST" action="{{ route('orders.staff.advance', $token) }}" class="mt-5 space-y-4 border-2 border-brand-black p-4" data-staff-form>
            @csrf
            <input type="hidden" name="from" value="{{ $order->stage }}">
            <input type="hidden" name="to" value="{{ $next }}">
            <div>
                <label for="advance_staff_name" class="block text-base font-medium">Nama Anda</label>
                <input id="advance_staff_name" name="staff_name" value="{{ old('staff_name') }}" required maxlength="40" list="staff-names" autocomplete="name" data-staff-name class="{{ $fieldClass }}">
            </div>
            @if ($shipping)
                <div>
                    <label for="courier" class="block text-base font-medium">Kurir</label>
                    <select id="courier" name="courier" required class="{{ $fieldClass }}" data-courier>
                        <option value="">Pilih kurir</option>
                        @foreach (OnlineOrder::COURIERS as $value => $name)
                            <option value="{{ $value }}" @selected(old('courier', $order->courier ?? ($order->fulfillment === 'intercity' ? 'jnt' : null)) === $value)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-resi>
                    <label for="tracking_number" class="block text-base font-medium">Nomor resi <span class="font-normal text-gray-600">(wajib untuk J&T)</span></label>
                    <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" maxlength="40" autocapitalize="characters" autocomplete="off" class="{{ $fieldClass }}">
                </div>
            @endif
            <button type="submit" class="flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:bg-gray-500">Tandai: {{ $order->stageLabel($next) }}</button>
            @if ($stepHint)<p class="text-sm leading-6 text-gray-600">{{ $stepHint }}</p>@endif
        </form>
    @elseif (! $order->isClosed() && $order->stepIndex() < $order->stepIndex(OnlineOrder::STAGE_PAID))
        <p class="mt-5 border border-gray-300 p-4 text-base text-gray-700">Menunggu admin mengonfirmasi pembayaran. Belum perlu diproses.</p>
    @endif

    <section class="mt-6" aria-labelledby="items-title">
        <h2 id="items-title" class="text-sm font-semibold uppercase tracking-widest text-gray-600">Pesanan</h2>
        <ul class="mt-2 space-y-1 text-base">
            @foreach ($order->items as $item)
                <li class="break-words"><strong>{{ $item->quantity }}×</strong> {{ $item->label() }} <span class="text-sm text-gray-600">({{ $item->brand_name }})</span></li>
            @endforeach
        </ul>
        <p class="mt-2 text-base">Kemasan: <strong>{{ OnlineOrder::PACKAGING[$order->packaging] ?? 'belum dipilih' }}</strong></p>
        @if ($order->customer_note)<p class="mt-1 break-words text-base">Catatan customer: {{ $order->customer_note }}</p>@endif
        @if ($order->staff_note)<p class="mt-1 break-words text-base">Catatan admin: {{ $order->staff_note }}</p>@endif
    </section>

    <section class="mt-6 border-t border-gray-200 pt-5" aria-labelledby="recipient-title">
        <h2 id="recipient-title" class="text-sm font-semibold uppercase tracking-widest text-gray-600">Penerima</h2>
        <p class="mt-2 break-words text-lg font-medium">{{ $order->customer_name ?? 'Belum diisi customer' }}</p>
        <p class="text-base">{{ OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? '-' }}@if ($courierName && ! $pickup) · {{ $courierName }}@endif</p>
        @if ($order->address)<p class="mt-1 break-words text-base">{{ $order->address }}@if ($order->postcode) {{ $order->postcode }}@endif</p>@endif
        @if ($order->tracking_number)<p class="mt-1 text-base">Resi: <strong class="break-all">{{ $order->tracking_number }}</strong></p>@endif
        <div class="mt-3 grid grid-cols-2 gap-2">
            @if ($order->customer_phone)
                <a href="tel:{{ $order->customer_phone }}" class="{{ $buttonClass }} border border-brand-black active:bg-gray-100">Telepon</a>
            @endif
            @if ($customerChatUrl)
                <a href="{{ $customerChatUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $buttonClass }} border border-brand-black active:bg-gray-100">Chat WhatsApp</a>
            @endif
            @if ($order->location_url)
                <a href="{{ $order->location_url }}" target="_blank" rel="noopener noreferrer" class="{{ $buttonClass }} col-span-2 bg-[#0D3F33] text-white active:bg-[#0a2f26]">Buka lokasi di Maps</a>
            @elseif ($order->fulfillment === 'local_delivery')
                <p class="col-span-2 text-sm text-gray-600">Lokasi: sharelok diteruskan admin di grup.</p>
            @endif
        </div>
        @if ($order->customer_phone)<p class="mt-2 text-sm text-gray-600">HP: {{ $order->customer_phone }}</p>@endif
    </section>

    @if (! $pickup && $order->shipping_payer)
        <section class="mt-6 border-t border-gray-200 pt-5" aria-labelledby="fee-title">
            <h2 id="fee-title" class="text-sm font-semibold uppercase tracking-widest text-gray-600">Ongkir</h2>
            <p class="mt-2 text-base">{{ $order->shipping_fee !== null ? format_rupiah($order->shipping_fee).' · ' : '' }}{{ OnlineOrder::SHIPPING_PAYERS[$order->shipping_payer] }}</p>
            @if ($order->driver_funding)<p class="mt-1 text-base">Bayar driver: <strong>{{ OnlineOrder::DRIVER_FUNDING[$order->driver_funding] }}</strong></p>@endif
        </section>
    @endif

    @if ($order->staff_advance_amount !== null)
        <p class="mt-6 border border-gray-300 p-4 text-base">
            Talangan ongkir {{ format_rupiah($order->staff_advance_amount) }} oleh {{ $order->staff_advance_by }}:
            <strong>{{ $order->staff_reimbursed_at ? 'sudah diganti admin' : 'menunggu diganti admin' }}</strong>
        </p>
    @endif
    @if ($canAdvanceCost)
        <details class="mt-4 border border-gray-300 px-4 py-1" @if ($errors->has('amount')) open @endif>
            <summary class="flex min-h-12 cursor-pointer items-center text-base font-medium">{{ $order->staff_advance_amount === null ? 'Saya menalangi ongkir driver' : 'Ubah nominal talangan' }}</summary>
            <form method="POST" action="{{ route('orders.staff.advance-cost', $token) }}" class="mb-3 mt-2 space-y-4" data-staff-form>
                @csrf
                <div>
                    <label for="cost_staff_name" class="block text-base font-medium">Nama Anda</label>
                    <input id="cost_staff_name" name="staff_name" required maxlength="40" list="staff-names" autocomplete="name" data-staff-name class="{{ $fieldClass }}">
                </div>
                <div>
                    <label for="amount" class="block text-base font-medium">Nominal ongkir yang dibayar (Rp)</label>
                    <input id="amount" name="amount" value="{{ old('amount', $order->staff_advance_amount ? (int) $order->staff_advance_amount : null) }}" required inputmode="numeric" placeholder="11500" class="{{ $fieldClass }}">
                </div>
                <button type="submit" class="flex min-h-12 w-full items-center justify-center border border-brand-black px-4 text-sm font-semibold uppercase tracking-widest active:bg-gray-100 disabled:opacity-60">Catat talangan</button>
            </form>
        </details>
    @endif

    <section class="mt-8" aria-labelledby="timeline-title">
        <h2 id="timeline-title" class="text-sm font-semibold uppercase tracking-widest text-gray-600">Riwayat</h2>
        <div class="mt-4"><x-order-timeline :items="$timeline" /></div>
    </section>
    <datalist id="staff-names"></datalist>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const key = 'qammaris.staffNames';
    let names = [];
    try { names = JSON.parse(localStorage.getItem(key) || '[]'); } catch (error) { names = []; }
    const list = document.getElementById('staff-names');
    names.forEach((name) => list.append(new Option(name)));
    document.querySelectorAll('[data-staff-name]').forEach((input) => { if (!input.value && names[0]) input.value = names[0]; });

    const courier = document.querySelector('[data-courier]');
    const resi = document.querySelector('[data-resi]');
    if (courier && resi) {
        const sync = () => {
            const jnt = courier.value === 'jnt';
            resi.hidden = !jnt;
            resi.querySelector('input').required = jnt;
        };
        courier.addEventListener('change', sync);
        sync();
    }

    document.querySelectorAll('[data-staff-form]').forEach((form) => form.addEventListener('submit', () => {
        const name = form.querySelector('[data-staff-name]')?.value.trim();
        if (name) {
            try { localStorage.setItem(key, JSON.stringify([name, ...names.filter((item) => item !== name)].slice(0, 5))); } catch (error) {}
        }
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; button.textContent = 'Menyimpan…'; });
    }));
    window.addEventListener('pageshow', (event) => { if (event.persisted) window.location.reload(); });
})();
</script>
@endpush
