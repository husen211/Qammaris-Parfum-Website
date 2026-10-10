@extends('layouts.admin')
@use('App\Actions\Orders\CreateOnlineOrder')
@use('App\Models\OnlineOrder')
@use('App\Models\OnlineOrderIssue')
@use('App\Support\OnlineOrderLabels')
@use('App\Support\Rupiah')

{{-- ORD-02d/ORD-03: V2 order page. Every form posts the revision it was rendered with; actions go through V2 operations only. --}}
@php
    $card = 'scroll-mt-20 rounded-xl border border-gray-200 bg-white p-4 sm:p-5';
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $primary = 'inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-black px-4 text-sm font-semibold text-white hover:bg-gray-800 disabled:bg-gray-400 sm:w-auto';
    $secondary = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:text-gray-400';
    $quiet = 'inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100';
    $danger = 'inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800 disabled:bg-gray-400 sm:w-auto';
    $chip = 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold';
    $time = fn ($value) => $value?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i');
    $rp = fn (int $cents) => Rupiah::format(Rupiah::decimal($cents));
    $open = in_array($order->lifecycle, ['draft', 'awaiting_customer', 'active'], true);
    $active = $order->lifecycle === 'active';
    $pending = $order->handover_status === 'pending';
    $keep = $order->keepState();
    $next = OnlineOrderLabels::nextStep($order, $queue, $openIssues);
    $hidden = fn (string $section) => '<input type="hidden" name="revision" value="'.$order->revision.'"><input type="hidden" name="_section" value="'.$section.'">';
    $linkFirst = $order->lifecycle === 'awaiting_customer';
@endphp

@section('content')
<div class="mx-auto max-w-6xl space-y-4" data-order-v2>
    <div>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pesanan Online</a>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">{{ $order->code }}</h1>
            <span @class([$chip,
                'bg-gray-900 text-white' => $order->lifecycle === 'active',
                'bg-amber-100 text-amber-900' => in_array($order->lifecycle, ['draft', 'awaiting_customer'], true),
                'bg-green-100 text-green-800' => $order->lifecycle === 'completed',
                'bg-red-100 text-red-800' => $order->lifecycle === 'cancelled'])>{{ OnlineOrderLabels::LIFECYCLE[$order->lifecycle] }}</span>
            @if ($queue && $order->lifecycle === 'active')<span class="{{ $chip }} bg-gray-100 text-gray-800">{{ OnlineOrderLabels::QUEUE[$queue] }}</span>@endif
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ $order->customer_name ?? 'Menunggu data customer' }} · {{ $rp($totalCents) }} · {{ CreateOnlineOrder::SOURCES[$order->source] ?? $order->source }}</p>
        <ul class="mt-2 flex flex-wrap gap-1.5" aria-label="Tanda pesanan">
            <li class="{{ $chip }} {{ $order->payment_status === 'paid' ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-900' }}">{{ OnlineOrderLabels::PAYMENT[$order->payment_status] }}</li>
            @if ($order->refund_status === 'needs_reconciliation')<li class="{{ $chip }} bg-red-100 text-red-800">Perlu rekonsiliasi</li>@endif
            @if ($openIssues > 0)<li class="{{ $chip }} bg-red-100 text-red-800">{{ $openIssues }} kendala</li>@endif
            @if ($keep === 'active')<li class="{{ $chip }} bg-blue-50 text-blue-900">Keep sampai {{ $time($order->keep_until) }}</li>@endif
            @if ($keep === 'expired')<li class="{{ $chip }} bg-red-100 text-red-800">Keep lewat batas</li>@endif
            @if ($overpaidCents > 0)<li class="{{ $chip }} bg-amber-50 text-amber-900">Lebih bayar {{ $rp($overpaidCents) }}</li>@endif
            @if ($flags['open_reimbursement'])<li class="{{ $chip }} bg-amber-50 text-amber-900">Talangan belum diganti</li>@endif
        </ul>
    </div>

    @if (session('order_notice.type') === 'conflict')
        <div role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"><strong>Pesanan berubah.</strong> {{ session('order_notice.message') }}</div>
    @endif

    {{-- ORD-02e: tasks held in Qammaris App. Only shown while someone holds one; Super Admin can free a stuck task. --}}
    @if ($order->claims->isNotEmpty() || session('order_notice.section') === 'klaim')
        <section id="klaim" class="{{ $card }}" aria-labelledby="klaim-title" data-claims>
            <h2 id="klaim-title" class="text-base font-semibold text-gray-900">Dipegang di Qammaris App</h2>
            @include('admin.orders.v2._notice', ['section' => 'klaim'])
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($order->claims->sortBy(fn ($claim) => array_search($claim->task, ['preparation', 'courier_booking', 'handover'], true)) as $claim)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span class="min-w-0"><span class="font-semibold">{{ ['preparation' => 'Packing', 'courier_booking' => 'Pesan kurir/J&T', 'handover' => 'Serah ke kurir'][$claim->task] }}</span>
                            · {{ $claim->holder_display_name }} <span class="text-gray-500">sejak {{ $time($claim->claimed_at) }}</span></span>
                        @can('orders.refund')
                            <details class="w-full sm:w-auto" @if (old('_section') === 'klaim' && old('task') === $claim->task) open @endif>
                                <summary class="{{ $quiet }} cursor-pointer">Lepas klaim</summary>
                                <form method="POST" action="{{ route('admin.orders.v2.claims.release', [$order, $claim->task]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf {!! $hidden('klaim') !!}<input type="hidden" name="task" value="{{ $claim->task }}">
                                    <label class="min-w-0 flex-1 text-xs font-medium text-gray-700">Alasan<input name="reason" required minlength="3" maxlength="200" value="{{ old('task') === $claim->task ? old('reason') : '' }}" class="{{ $input }}"></label>
                                    <button class="{{ $secondary }}" data-busy-label="Melepas…">Lepas</button>
                                </form>
                                @error('reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            </details>
                        @endcan
                    </li>
                @endforeach
            </ul>
            @if ($order->claims->isEmpty())<p class="mt-2 text-sm text-gray-600">Tidak ada tugas yang dipegang.</p>@endif
        </section>
    @endif

    {{--
        ORD-03 layout: the current step is open first with one primary action; the other steps stay collapsed with a
        one-line status; exceptions ("Ada masalah", "Opsi lanjutan") open only when something needs attention.
    --}}
    @php
        $isOpen = fn (string $section, bool $default = false) => $default || old('_section') === $section || session('order_notice.section') === $section;
        $stage = match (true) {
            ! $open => null,
            $order->lifecycle === 'draft' => 'konfirmasi',
            $order->lifecycle === 'awaiting_customer' => 'link',
            $openIssues > 0 => 'kendala',
            $remainingCents > 0 => 'pembayaran',
            $order->preparation_status !== 'packed' => 'packing',
            $pending => 'pengiriman',
            default => null,
        };
        $steps = array_values(array_unique(array_filter([$stage, 'pembayaran', 'packing', 'pengiriman', 'link'])));
        $stepView = fn (string $step) => $step === 'link' ? 'admin.orders.v2._links' : 'admin.orders.v2.sections.'.$step;
        // An open issue comes first; otherwise "Ada masalah" waits below the steps.
    @endphp

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
        <div class="min-w-0 space-y-3">
            @if (! $stage && $order->lifecycle !== 'cancelled')
                <p class="rounded-lg border-l-4 border-green-700 bg-white px-4 py-3 text-sm font-medium text-gray-900" data-next-step>{{ $order->lifecycle === 'completed' ? 'Pesanan selesai.' : 'Pesanan sudah diserahkan. Tidak ada langkah wajib.' }}</p>
            @endif
            @foreach ($steps as $step)
                @include($stepView($step), ['openSection' => $step === $stage || $isOpen($step)])
            @endforeach

            {{-- Customer change requests stay visible: they wait for a decision. --}}
            @if ($order->changeRequests->where('status', 'pending')->isNotEmpty() || $isOpen('perubahan'))
                <section id="perubahan" class="{{ $card }}" aria-labelledby="perubahan-title">
                    <h2 id="perubahan-title" class="text-base font-semibold text-gray-900">Permintaan perubahan dari customer</h2>
                    @include('admin.orders.v2._notice', ['section' => 'perubahan'])
                    @foreach ($order->changeRequests->sortByDesc('id') as $change)
                        <div class="mt-3 rounded-lg border p-3 text-sm {{ $change->status === 'pending' ? 'border-amber-300 bg-amber-50' : 'border-gray-200' }}">
                            <p class="text-xs text-gray-600">{{ $time($change->created_at) }} · {{ ['pending' => 'Menunggu ditinjau', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$change->status] }}</p>
                            <dl class="mt-2 space-y-1">
                                @foreach ($change->changes as $field => $value)
                                    <div><dt class="inline font-medium">{{ ['customer_name' => 'Nama', 'customer_phone' => 'HP', 'fulfillment' => 'Cara menerima', 'address' => 'Alamat', 'postcode' => 'Kode pos', 'location_url' => 'Lokasi', 'packaging' => 'Paperbag', 'customer_note' => 'Catatan'][$field] ?? $field }}:</dt>
                                        <dd class="inline break-words">{{ $field === 'fulfillment' ? (OnlineOrder::FULFILLMENTS[$value] ?? $value) : ($field === 'packaging' ? (OnlineOrder::PACKAGING[$value] ?? $value) : ($value ?? '—')) }}</dd></div>
                                @endforeach
                            </dl>
                            @if ($change->status === 'pending')
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <form method="POST" action="{{ route('admin.orders.v2.change-requests.decide', [$order, $change->id, 'approve']) }}" data-confirm="Terapkan perubahan ini ke pesanan?">
                                        @csrf {!! $hidden('perubahan') !!}<button class="{{ $secondary }} w-full" data-busy-label="…">Terapkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.orders.v2.change-requests.decide', [$order, $change->id, 'reject']) }}" class="flex gap-2">
                                        @csrf {!! $hidden('perubahan') !!}<input name="note" required minlength="5" maxlength="300" placeholder="Alasan untuk customer" aria-label="Alasan menolak" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-base"><button class="{{ $secondary }}" data-busy-label="…">Tolak</button>
                                    </form>
                                </div>
                                @if (array_key_exists('fulfillment', $change->changes))<p class="mt-1 text-xs text-gray-600">Ganti cara pengiriman dapat mengubah biaya: perlu Super Admin.</p>@endif
                            @elseif ($change->review_note)
                                <p class="mt-1 text-xs text-gray-600">{{ $change->review_note }}</p>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            @include('admin.orders.v2.sections.keep')
            @if ($stage !== 'kendala')
                @include('admin.orders.v2.sections.kendala')
            @endif
            @include('admin.orders.v2.sections.lanjutan')

            @can('orders.refund')
                @if ($received > 0 || $order->refund_status)
                    <details id="keuangan" class="{{ $card }}" @if ($isOpen('keuangan', in_array($order->refund_status, ['pending', 'partial', 'needs_reconciliation'], true))) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
                            <span><span class="block text-base font-semibold text-gray-900">Keuangan</span><span class="block text-sm text-gray-600">Refund dan rekonsiliasi (Super Admin)</span></span>
                            <span aria-hidden="true" class="text-gray-400">▾</span>
                        </summary>
                        @include('admin.orders.v2._money-panel')
                    </details>
                @endif
            @endcan
        </div>

        <div class="min-w-0 space-y-3">
            {{-- Items and totals stay visible: price and quantity are what staff check most. --}}
            <section id="ringkasan" class="{{ $card }}" aria-labelledby="items-title">
                <h2 id="items-title" class="text-base font-semibold text-gray-900">Produk</h2>
                <ul class="mt-2 divide-y divide-gray-100 text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="min-w-0"><span class="block font-medium text-gray-900">{{ $item->label() }}</span><span class="text-gray-500">{{ $item->quantity }} × {{ format_rupiah($item->unit_price) }}</span></span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ format_rupiah($item->lineTotal()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-2 space-y-1 border-t border-gray-100 pt-2 text-sm">
                    @if ($order->shipping_payer === 'added_to_transfer' && $order->shipping_fee !== null)<div class="flex justify-between"><dt class="text-gray-600">Ongkir</dt><dd class="tabular-nums">{{ format_rupiah($order->shipping_fee) }}</dd></div>@endif
                    @if ($order->approvedAdjustmentCents() !== 0)<div class="flex justify-between"><dt class="text-gray-600">Penyesuaian</dt><dd class="tabular-nums">{{ $rp($order->approvedAdjustmentCents()) }}</dd></div>@endif
                    <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ $rp($totalCents) }}</dd></div>
                    @if ($received > 0)<div class="flex justify-between"><dt class="text-gray-600">Sudah diterima</dt><dd class="tabular-nums">{{ $rp($received) }}</dd></div>@endif
                </dl>
                <p class="mt-1 text-sm text-gray-600">{{ OnlineOrder::PACKAGING[$order->packaging] ?? 'Paperbag belum dipilih' }}</p>
            </section>

            {{-- Editable recipient details. --}}
            <details id="detail" class="{{ $card }}" @if ($isOpen('detail')) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
                    <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Data penerima</span><span class="block truncate text-sm text-gray-600">{{ $order->customer_name ? $order->customer_name.' · '.$order->customer_phone : 'Belum diisi' }}</span></span>
                    <span aria-hidden="true" class="text-gray-400">▾</span>
                </summary>
                <form method="POST" action="{{ route('admin.orders.v2.details', $order) }}" class="mt-3 space-y-4">
                    @csrf @method('PATCH') {!! $hidden('detail') !!}
                    @include('admin.orders.v2._notice', ['section' => 'detail'])
                    @if ($customerChatUrl)<a href="{{ $customerChatUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center text-sm text-gray-700 underline underline-offset-4">Chat customer di WhatsApp</a>@endif
                    @include('admin.orders._customer-fields', ['order' => $order])
                    <label class="block text-sm font-medium text-gray-700">Link Google Maps <span class="font-normal text-gray-500">(opsional)</span>
                        <input name="location_url" type="url" inputmode="url" value="{{ old('location_url', $order->location_url) }}" maxlength="500" placeholder="https://maps.app.goo.gl/…" class="{{ $input }}">
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Catatan untuk staf<input name="staff_note" value="{{ old('staff_note', $order->staff_note) }}" maxlength="300" class="{{ $input }}"></label>
                    <input type="hidden" name="recorded_in_majoo" value="{{ $order->recorded_in_majoo ? 1 : 0 }}">
                    <button class="{{ $primary }} sm:w-full" data-busy-label="Menyimpan…">Simpan data penerima</button>
                </form>
            </details>

            {{-- Repeat customer. --}}
            <details id="pelanggan" class="{{ $card }}" @if ($isOpen('pelanggan')) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
                    <span><span class="block text-base font-semibold text-gray-900">Pelanggan langganan</span><span class="block text-sm text-gray-600">{{ $order->customer ? $order->customer->name : 'Belum dihubungkan' }}</span></span>
                    <span aria-hidden="true" class="text-gray-400">▾</span>
                </summary>
                @include('admin.orders.v2._notice', ['section' => 'pelanggan'])
                @if ($order->customer)
                    @if ($order->customer->addresses->isNotEmpty() && $open && $pending)
                        <form method="POST" action="{{ route('admin.orders.v2.customer.address.use', $order) }}" class="mt-3 space-y-2">
                            @csrf {!! $hidden('pelanggan') !!}
                            <p class="text-sm font-medium text-gray-700">Alamat tersimpan</p>
                            @foreach ($order->customer->addresses as $address)
                                <button name="customer_address_id" value="{{ $address->id }}" class="flex min-h-11 w-full items-start gap-2 rounded-lg border px-3 py-2 text-left text-sm {{ $order->customer_address_id === $address->id ? 'border-black' : 'border-gray-200 hover:bg-gray-50' }}" data-busy-label="Memakai…">
                                    <span class="min-w-0"><span class="block font-medium">{{ $address->label }} · {{ $address->type === 'intercity' ? 'Luar kota' : 'Dalam kota' }}</span><span class="block text-gray-600">{{ $address->address }} {{ $address->postcode }}</span></span>
                                </button>
                            @endforeach
                        </form>
                    @endif
                    @if ($order->address && in_array($order->fulfillment, ['local_delivery', 'intercity'], true) && ! $order->customer_address_id)
                        <form method="POST" action="{{ route('admin.orders.v2.customer.address', $order) }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf {!! $hidden('pelanggan') !!}
                            <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Simpan alamat ini sebagai<input name="label" required maxlength="40" placeholder="Rumah / Kantor" class="{{ $input }}"></label>
                            <button class="{{ $secondary }}" data-busy-label="…">Simpan alamat</button>
                        </form>
                    @endif
                @else
                    @foreach ($customerMatches as $match)
                        <form method="POST" action="{{ route('admin.orders.v2.customer', $order) }}" class="mt-2">
                            @csrf {!! $hidden('pelanggan') !!} <input type="hidden" name="customer_id" value="{{ $match->id }}">
                            <button class="{{ $secondary }} w-full justify-start" data-busy-label="…">Hubungkan ke {{ $match->name }} (nomor sama)</button>
                        </form>
                    @endforeach
                    @if ($order->customer_name && $order->customer_phone)
                        <form method="POST" action="{{ route('admin.orders.v2.customer', $order) }}" class="mt-2">
                            @csrf {!! $hidden('pelanggan') !!}
                            <button class="{{ $quiet }}" data-busy-label="…">Simpan sebagai pelanggan baru</button>
                        </form>
                    @endif
                @endif
            </details>

            @if ($order->lifecycle === 'cancelled')
                <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">Dibatalkan {{ $time($order->closed_at) }}: {{ $order->cancel_reason }}</p>
            @endif

            <details id="riwayat" class="{{ $card }}">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
                    <span class="text-base font-semibold text-gray-900">Riwayat</span><span aria-hidden="true" class="text-gray-400">▾</span>
                </summary>
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach ($order->events->reverse() as $event)
                        <li class="border-l-2 border-gray-200 pl-3">
                            <p class="text-gray-900"><span class="font-medium">{{ $event->actorLabel() }}</span> {{ OnlineOrderLabels::event($event) }}</p>
                            @if ($event->note)<p class="break-words text-gray-600">{{ $event->note }}</p>@endif
                            <p class="text-xs tabular-nums text-gray-500">{{ $time($event->created_at) }}</p>
                        </li>
                    @endforeach
                </ol>
            </details>
        </div>
    </div>
</div>
<p data-copy-feedback role="status" aria-live="polite" class="sr-only"></p>
@endsection

@push('scripts')
<script>
(() => {
    // Confirmation step for risky actions; a cancelled confirm leaves the form untouched (the PWA guard sees defaultPrevented).
    document.querySelectorAll('[data-order-v2] form').forEach((form) => form.addEventListener('submit', (event) => {
        const text = event.submitter?.dataset.confirm || form.dataset.confirm;
        if (text && !window.confirm(text)) event.preventDefault();
    }));
    const feedback = document.querySelector('[data-copy-feedback]');
    // Copy from a field (data-copy="#id") or a literal (data-copy-text). Fallback: a temporary selected text area.
    const copy = async (text) => {
        try { await navigator.clipboard.writeText(text); return true; } catch (error) {
            const area = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
            area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;font-size:16px';
            document.body.append(area); area.select();
            let copied = false;
            try { copied = document.execCommand('copy'); } catch (fallbackError) { copied = false; }
            area.remove();
            return copied;
        }
    };
    document.querySelectorAll('[data-copy], [data-copy-text]').forEach((button) => button.addEventListener('click', async () => {
        const text = button.dataset.copyText ?? document.querySelector(button.dataset.copy).value;
        const label = button.textContent;
        const copied = await copy(text);
        button.textContent = copied ? 'Tersalin ✓' : 'Gagal menyalin';
        feedback.textContent = copied ? 'Tersalin.' : 'Salin otomatis tidak tersedia di browser ini.';
        setTimeout(() => { button.textContent = label; }, 2500);
    }));
    // Packing: the single action is enabled once every line is ticked (the server still checks exact quantities).
    document.querySelectorAll('[data-pack-form]').forEach((form) => {
        const lines = [...form.querySelectorAll('[data-pack-line]')];
        const submit = form.querySelector('[data-pack-submit]');
        const sync = () => { submit.disabled = !lines.every((line) => line.checked); };
        lines.forEach((line) => line.addEventListener('change', sync));
        sync();
    });
    // A link or a returned form result (#section) opens its collapsed section.
    const openTarget = (id) => document.getElementById(id)?.closest('details')?.setAttribute('open', '');
    document.querySelectorAll('[data-open-section]').forEach((link) => link.addEventListener('click', () => openTarget(link.dataset.openSection)));
    if (location.hash.length > 1) openTarget(decodeURIComponent(location.hash.slice(1)));
    // Native share sheet (WhatsApp, Telegram, SMS, …) only where the browser supports it; never required.
    document.querySelectorAll('[data-share]').forEach((button) => {
        if (typeof navigator.share !== 'function') return;
        button.hidden = false;
        button.addEventListener('click', async () => {
            try { await navigator.share({ title: button.dataset.shareTitle, text: document.querySelector(button.dataset.share).value }); } catch (error) { /* closed by the user */ }
        });
    });
})();
</script>
@endpush
