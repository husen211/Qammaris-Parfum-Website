@extends('layouts.admin')
@use('App\Actions\Orders\CreateOnlineOrder')
@use('App\Models\OnlineOrder')
@use('App\Models\OnlineOrderIssue')
@use('App\Support\OnlineOrderLabels')
@use('App\Support\Rupiah')

{{-- ORD-02d: V2 order page. Every form posts the revision it was rendered with; actions go through V2 operations only. --}}
@php
    $card = 'scroll-mt-20 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5';
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $primary = 'inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-black px-4 text-sm font-semibold text-white hover:bg-gray-800 disabled:bg-gray-400 sm:w-auto';
    $secondary = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:text-gray-400';
    $quiet = 'inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100';
    $danger = 'inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800 disabled:bg-gray-400 sm:w-auto';
    $chip = 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold';
    $time = fn ($value) => $value?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i');
    $rp = fn (int $cents) => Rupiah::format(Rupiah::decimal($cents));
    $open = in_array($order->lifecycle, ['awaiting_customer', 'active'], true);
    $active = $order->lifecycle === 'active';
    $pending = $order->handover_status === 'pending';
    $keep = $order->keepState();
    $next = OnlineOrderLabels::nextStep($order, $queue, $openIssues);
    $hidden = fn (string $section) => '<input type="hidden" name="revision" value="'.$order->revision.'"><input type="hidden" name="_section" value="'.$section.'">';
    $linkFirst = $order->lifecycle === 'awaiting_customer';
@endphp

@section('content')
<div class="mx-auto max-w-6xl space-y-5" data-order-v2>
    <div>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pesanan Online</a>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">{{ $order->code }}</h1>
            <span @class([$chip,
                'bg-gray-900 text-white' => $order->lifecycle === 'active',
                'bg-amber-100 text-amber-900' => $order->lifecycle === 'awaiting_customer',
                'bg-green-100 text-green-800' => $order->lifecycle === 'completed',
                'bg-red-100 text-red-800' => $order->lifecycle === 'cancelled'])>{{ OnlineOrderLabels::LIFECYCLE[$order->lifecycle] }}</span>
            @if ($queue && $order->lifecycle === 'active')<span class="{{ $chip }} bg-gray-100 text-gray-800">{{ OnlineOrderLabels::QUEUE[$queue] }}</span>@endif
        </div>
        <p class="mt-1 text-sm text-gray-500">Dibuat {{ $time($order->created_at) }} · {{ CreateOnlineOrder::SOURCES[$order->source] ?? $order->source }} · {{ $order->customer_name ?? 'menunggu data customer' }}</p>
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
    @if ($next)
        <p class="rounded-lg border-l-4 border-black bg-white px-4 py-3 text-sm font-medium text-gray-900 shadow-sm" data-next-step>Berikutnya: {{ $next }}</p>
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

    {{-- contain: its scrolling chips never widen the page. --}}
    <nav aria-label="Bagian pesanan" class="-mx-4 overflow-x-auto px-4 [contain:inline-size] xl:hidden">
        <ul class="flex gap-2 whitespace-nowrap text-sm font-semibold">
            @foreach (['pembayaran' => 'Bayar', 'packing' => 'Packing', 'pengiriman' => 'Kirim', 'keep' => 'Keep', 'kendala' => 'Kendala'.($openIssues ? " ({$openIssues})" : ''), 'harga' => 'Harga', 'link' => 'Link & WA', 'pelanggan' => 'Pelanggan', 'detail' => 'Detail', 'riwayat' => 'Riwayat'] as $anchor => $label)
                <li><a href="#{{ $anchor }}" class="inline-flex min-h-10 items-center rounded-full border border-gray-300 bg-white px-3 text-gray-800 hover:bg-gray-50">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
        <div class="min-w-0 space-y-5">
            @if ($linkFirst)
                @include('admin.orders.v2._links')
            @endif

            {{-- Payment --}}
            <section id="pembayaran" class="{{ $card }}" aria-labelledby="pembayaran-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="pembayaran-title" class="text-lg font-semibold text-gray-900">Pembayaran</h2>
                    <span class="text-sm font-semibold tabular-nums">{{ $rp($received) }} / {{ $rp($totalCents) }}</span>
                </div>
                @include('admin.orders.v2._notice', ['section' => 'pembayaran'])
                @if ($order->payments->isEmpty())
                    <p class="mt-3 text-sm text-gray-600">Belum ada pembayaran tercatat.</p>
                @else
                    <ul class="mt-3 divide-y divide-gray-100 text-sm" aria-label="Catatan pembayaran">
                        @foreach ($order->payments as $entry)
                            @php($reversed = $order->payments->contains('reverses_id', $entry->id))
                            <li class="flex flex-wrap items-start justify-between gap-2 py-2 {{ $reversed ? 'text-gray-400 line-through' : '' }}">
                                <span class="min-w-0">
                                    <span class="block font-medium {{ $entry->basis === 'reversal' ? 'text-red-700' : 'text-gray-900' }}">
                                        {{ $entry->basis === 'reversal' ? 'Pembatalan entri' : ($entry->type === 'refund' ? 'Refund' : 'Pembayaran') }}
                                        @if ($entry->basis === 'reconciled')<span class="{{ $chip }} ml-1 bg-gray-100 text-gray-700">rekonsiliasi</span>@endif
                                    </span>
                                    <span class="block text-xs text-gray-500">{{ OnlineOrder::PAYMENT_METHODS[$entry->method] ?? '' }}{{ $entry->confirmation_source ? ' · '.OnlineOrderLabels::CONFIRMATION_SOURCES[$entry->confirmation_source] : '' }} · {{ $entry->recorder?->name }} · {{ $time($entry->created_at) }}</span>
                                    @if ($entry->note)<span class="block text-xs text-gray-500">{{ $entry->note }}</span>@endif
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums">{{ $entry->basis === 'reversal' ? '−' : '' }}{{ format_rupiah($entry->amount) }}</span>
                                @can('orders.refund')
                                    @if ($entry->basis !== 'reversal' && ! $reversed)
                                        <details class="w-full">
                                            <summary class="inline-flex min-h-9 cursor-pointer items-center text-xs font-semibold text-red-700">Batalkan entri ini…</summary>
                                            <form method="POST" action="{{ route('admin.orders.money.reverse', [$order, $entry->id]) }}" class="mt-2 flex flex-wrap items-end gap-2" data-confirm="Batalkan entri ini? Entri asli tetap terlihat.">
                                                @csrf {!! $hidden('pembayaran') !!}
                                                <label class="min-w-0 flex-1 text-xs font-medium text-gray-700">Alasan<input name="reason" required maxlength="300" class="{{ $input }}"></label>
                                                <button class="{{ $secondary }}" data-busy-label="Membatalkan…">Batalkan entri</button>
                                            </form>
                                        </details>
                                    @endif
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($order->lifecycle !== 'cancelled' && $remainingCents > 0)
                    <form method="POST" action="{{ route('admin.orders.v2.payments', $order) }}" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                        @csrf {!! $hidden('pembayaran') !!}
                        <p class="text-sm text-gray-700">Sisa tagihan: <strong class="tabular-nums">{{ $rp($remainingCents) }}</strong></p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">Nominal diterima (Rp)
                                <input name="amount" inputmode="numeric" required value="{{ old('_section') === 'pembayaran' ? old('amount') : (int) ($remainingCents / 100) }}" class="{{ $input }}">
                            </label>
                            <label class="text-sm font-medium text-gray-700">Metode
                                <select name="payment_method" required class="{{ $input }}">
                                    <option value="">Pilih…</option>
                                    @foreach (OnlineOrder::PAYMENT_METHODS as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', $order->payment_method) === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </label>
                        </div>
                        <fieldset>
                            <legend class="text-sm font-medium text-gray-700">Dikonfirmasi dari</legend>
                            <div class="mt-1 grid gap-2 sm:grid-cols-3">
                                @foreach (OnlineOrderLabels::CONFIRMATION_SOURCES as $value => $label)
                                    <label class="flex min-h-11 items-center gap-2 rounded-lg border border-gray-200 px-3 text-sm"><input type="radio" name="confirmation_source" value="{{ $value }}" required @checked(old('confirmation_source') === $value) class="h-4 w-4"> {{ $label }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="flex min-h-11 items-center gap-3 text-sm text-gray-700"><input type="checkbox" name="recorded_in_majoo" value="1" @checked($order->recorded_in_majoo) class="h-5 w-5"> Sudah dicatat di Majoo</label>
                        <button class="{{ $primary }}" data-busy-label="Mencatat…">{{ $received === 0 ? 'Tandai Lunas / catat pembayaran' : 'Catat pembayaran' }}</button>
                    </form>
                @elseif ($order->payment_status === 'paid')
                    <p class="mt-3 text-sm text-green-800">Lunas.{{ $order->recorded_in_majoo ? ' Tercatat di Majoo.' : '' }}</p>
                @endif
            </section>

            {{-- Preparation and packing --}}
            <section id="packing" class="{{ $card }}" aria-labelledby="packing-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="packing-title" class="text-lg font-semibold text-gray-900">Persiapan & packing</h2>
                    <span class="{{ $chip }} {{ $order->preparation_status === 'packed' ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ OnlineOrderLabels::PREPARATION[$order->preparation_status] }}</span>
                </div>
                @include('admin.orders.v2._notice', ['section' => 'packing'])
                @if (! $active)
                    <p class="mt-3 text-sm text-gray-600">{{ $order->lifecycle === 'awaiting_customer' ? 'Bisa dimulai setelah data customer lengkap.' : 'Pesanan sudah ditutup.' }}</p>
                @elseif ($order->preparation_status === 'packed')
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($order->items as $item)<li class="flex justify-between gap-3"><span>{{ $item->label() }}</span><span class="font-semibold">✓ {{ $item->quantity }}</span></li>@endforeach
                    </ul>
                @else
                    @if ($order->preparation_status === 'not_started')
                        <form method="POST" action="{{ route('admin.orders.v2.preparation', $order) }}" class="mt-3">
                            @csrf {!! $hidden('packing') !!}
                            <button class="{{ $secondary }} w-full sm:w-auto" data-busy-label="Menyimpan…">Mulai siapkan</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.orders.v2.pack', $order) }}" class="mt-4 space-y-3 border-t border-gray-100 pt-4" data-confirm="Konfirmasi packing? Jumlah harus sama dengan pesanan.">
                        @csrf {!! $hidden('packing') !!}
                        <p class="text-sm text-gray-700">Isi jumlah yang <strong>benar-benar masuk paket</strong> untuk setiap barang. Bila kurang, catat kendala stok, jangan konfirmasi packing.</p>
                        @foreach ($order->items as $index => $item)
                            <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 text-sm">
                                <span class="min-w-0"><span class="block font-medium text-gray-900">{{ $item->label() }}</span><span class="text-gray-500">Dipesan {{ $item->quantity }}</span></span>
                                <input type="number" name="packed[{{ $item->line_id }}]" min="0" max="99" required inputmode="numeric" value="{{ old('packed.'.$item->line_id) }}"
                                    aria-label="Jumlah {{ $item->label() }} yang dipacking" class="min-h-11 w-20 shrink-0 rounded-lg border border-gray-300 px-2 text-center text-base">
                            </label>
                        @endforeach
                        <button class="{{ $primary }}" data-busy-label="Mengonfirmasi…">Konfirmasi packing</button>
                    </form>
                @endif
            </section>

            {{-- Delivery / handover --}}
            <section id="pengiriman" class="{{ $card }}" aria-labelledby="pengiriman-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="pengiriman-title" class="text-lg font-semibold text-gray-900">{{ OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? 'Pengiriman' }}</h2>
                    <span class="{{ $chip }} {{ $pending ? 'bg-gray-100 text-gray-800' : 'bg-green-50 text-green-800' }}">{{ $pending ? 'Belum diserahkan' : 'Diserahkan ke '.(OnlineOrderLabels::HANDED_TO[$order->handed_to] ?? '-') }}</span>
                </div>
                @include('admin.orders.v2._notice', ['section' => 'pengiriman'])
                @if (! $order->fulfillment)
                    <p class="mt-3 text-sm text-gray-600">Cara menerima belum diisi.</p>
                @elseif (! $pending)
                    <p class="mt-3 text-sm text-gray-700">Diserahkan {{ $time($order->handed_over_at) }}.@if ($order->tracking_number) Resi: <span class="font-mono">{{ $order->tracking_number }}</span>.@endif</p>
                    @if ($order->fulfillment !== 'pickup')
                        @if ($order->delivery_status === 'delivered')
                            <p class="mt-2 text-sm text-green-800">Diterima customer {{ $time($order->delivered_at) }}.</p>
                        @elseif ($order->lifecycle !== 'cancelled')
                            <form method="POST" action="{{ route('admin.orders.v2.delivery', $order) }}" class="mt-3">
                                @csrf {!! $hidden('pengiriman') !!}
                                <button class="{{ $secondary }} w-full sm:w-auto" data-busy-label="Menyimpan…">Tandai sudah diterima customer</button>
                            </form>
                            <p class="mt-1 text-xs text-gray-500">Opsional. Customer juga bisa menandai dari link-nya.</p>
                        @endif
                    @endif
                @elseif ($order->fulfillment === 'pickup')
                    <p class="mt-3 text-sm text-gray-700">Customer mengambil di toko.</p>
                    @if ($active)
                        <form method="POST" action="{{ route('admin.orders.v2.handover', $order) }}" class="mt-3" data-confirm="Customer sudah mengambil pesanan?">
                            @csrf {!! $hidden('pengiriman') !!} <input type="hidden" name="handed_to" value="customer">
                            <button class="{{ $primary }}" @disabled($order->preparation_status !== 'packed') data-busy-label="Menyimpan…">Sudah diambil customer</button>
                        </form>
                        @if ($order->preparation_status !== 'packed')<p class="mt-1 text-xs text-gray-500">Konfirmasi packing dulu.</p>@endif
                    @endif
                @elseif ($order->fulfillment === 'local_delivery')
                    @if ($active)
                        <form method="POST" action="{{ route('admin.orders.v2.courier-responsibility', $order) }}" class="mt-3">
                            @csrf {!! $hidden('pengiriman') !!}
                            <fieldset>
                                <legend class="text-sm font-medium text-gray-700">Siapa memesan kurir?</legend>
                                <div class="mt-1 grid grid-cols-2 gap-2">
                                    @foreach (['store' => 'Toko', 'customer' => 'Customer sendiri'] as $value => $label)
                                        @php($selected = $order->courier_booking_responsibility === $value)
                                        <button name="responsibility" value="{{ $value }}" aria-pressed="{{ $selected ? 'true' : 'false' }}" @class([
                                            'inline-flex min-h-11 items-center justify-center rounded-lg border px-4 text-sm font-semibold',
                                            'border-black bg-gray-900 text-white' => $selected,
                                            'border-gray-300 bg-white text-gray-800 hover:bg-gray-50' => ! $selected,
                                        ])>{{ $selected ? '✓ ' : '' }}{{ $label }}</button>
                                    @endforeach
                                </div>
                            </fieldset>
                        </form>
                    @endif
                    @if ($order->courier_booking_responsibility === 'store')
                        <p class="mt-3 text-sm text-gray-700">Kurir: <strong>{{ OnlineOrderLabels::COURIER[$order->courier_status] }}</strong>{{ $order->courier_provider ? ' · '.OnlineOrderLabels::PROVIDERS[$order->courier_provider] : '' }}{{ $order->courier_reference ? ' · '.$order->courier_reference : '' }}</p>
                        @if ($active && $order->courier_status !== 'arrived')
                            <form method="POST" action="{{ route('admin.orders.v2.courier', $order) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                @csrf {!! $hidden('pengiriman') !!}
                                <label class="text-sm font-medium text-gray-700">Aplikasi kurir
                                    <select name="provider" required class="{{ $input }}">@foreach (OnlineOrderLabels::PROVIDERS as $value => $label)<option value="{{ $value }}" @selected(old('provider', $order->courier_provider) === $value)>{{ $label }}</option>@endforeach</select>
                                </label>
                                <label class="text-sm font-medium text-gray-700">Kode pesanan kurir <span class="font-normal text-gray-500">(opsional)</span>
                                    <input name="reference" maxlength="80" value="{{ old('reference', $order->courier_reference) }}" class="{{ $input }}">
                                </label>
                                <div class="grid grid-cols-2 gap-2 sm:col-span-2">
                                    @if ($order->courier_status === 'unassigned')<button name="status" value="requested" class="{{ $secondary }}" data-busy-label="Menyimpan…">Sudah dipesan</button>@endif
                                    <button name="status" value="arrived" class="{{ $secondary }}" data-busy-label="Menyimpan…">Kurir sudah tiba</button>
                                </div>
                            </form>
                        @endif
                    @elseif ($order->courier_booking_responsibility === 'customer')
                        <p class="mt-3 text-sm text-gray-700">Customer memesan kurir sendiri. Serahkan saat kurirnya datang.</p>
                    @endif
                    @if ($active)
                        <form method="POST" action="{{ route('admin.orders.v2.handover', $order) }}" class="mt-4 border-t border-gray-100 pt-4" data-confirm="Pesanan sudah diserahkan ke kurir?">
                            @csrf {!! $hidden('pengiriman') !!}
                            <input type="hidden" name="handed_to" value="{{ $order->courier_booking_responsibility === 'customer' ? 'customer_courier' : 'courier' }}">
                            <button class="{{ $primary }}" @disabled($order->preparation_status !== 'packed') data-busy-label="Menyimpan…">Sudah diserahkan ke {{ $order->courier_booking_responsibility === 'customer' ? 'kurir customer' : 'kurir' }}</button>
                            @if ($order->preparation_status !== 'packed')<p class="mt-1 text-xs text-gray-500">Konfirmasi packing dulu.</p>@endif
                        </form>
                    @endif
                @elseif ($order->fulfillment === 'intercity')
                    <ol class="mt-3 grid grid-cols-3 gap-1 text-center text-xs" aria-label="Langkah J&T">
                        @foreach (['pickup_requested' => 'Pickup diminta', 'qr_available' => 'QR tersedia', 'picked_up' => 'Dipickup'] as $step => $label)
                            @php($done = array_search($order->jnt_status, ['not_requested', 'pickup_requested', 'qr_available', 'picked_up'], true) >= array_search($step, ['not_requested', 'pickup_requested', 'qr_available', 'picked_up'], true))
                            <li class="rounded-lg px-1 py-2 {{ $done ? 'bg-gray-900 font-semibold text-white' : 'bg-gray-100 text-gray-600' }}" @if ($order->jnt_status === $step) aria-current="step" @endif>{{ $label }}</li>
                        @endforeach
                    </ol>
                    @if ($active)
                        <form method="POST" action="{{ route('admin.orders.v2.jnt', $order) }}" class="mt-3 space-y-3">
                            @csrf {!! $hidden('pengiriman') !!}
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                @if ($order->jnt_status === 'not_requested')<button name="status" value="pickup_requested" class="{{ $secondary }}" data-busy-label="Menyimpan…">Pickup sudah diminta</button>@endif
                                @if (in_array($order->jnt_status, ['not_requested', 'pickup_requested'], true))<button name="status" value="qr_available" class="{{ $secondary }}" data-busy-label="Menyimpan…">QR sudah ada</button>@endif
                                <button name="status" value="picked_up" class="{{ $secondary }}" @disabled($order->preparation_status !== 'packed') data-confirm="Paket sudah diambil J&T? Ini sekaligus mencatat penyerahan." data-busy-label="Menyimpan…">Sudah dipickup J&T</button>
                            </div>
                            <p class="text-xs text-gray-500">Jam layanan J&T 09.00–16.00 WITA. Request pickup bukan konfirmasi berhasil.</p>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.orders.v2.jnt', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3">
                        @csrf {!! $hidden('pengiriman') !!}
                        <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Nomor resi <span class="font-normal text-gray-500">(opsional, boleh menyusul)</span>
                            <input name="tracking_number" maxlength="40" value="{{ old('tracking_number', $order->tracking_number) }}" autocapitalize="characters" class="{{ $input }} font-mono">
                        </label>
                        <button class="{{ $secondary }}" data-busy-label="Menyimpan…">Simpan resi</button>
                    </form>
                @endif
            </section>

            {{-- Keep --}}
            <section id="keep" class="{{ $card }}" aria-labelledby="keep-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="keep-title" class="text-lg font-semibold text-gray-900">Keep</h2>
                    @if ($keep)<span @class([$chip, 'bg-blue-50 text-blue-900' => $keep === 'active', 'bg-red-100 text-red-800' => $keep === 'expired', 'bg-gray-100 text-gray-700' => in_array($keep, ['converted', 'released'], true)])>{{ ['active' => 'Aktif', 'expired' => 'Lewat batas', 'converted' => 'Jadi pesanan', 'released' => 'Dilepas'][$keep] }}</span>@endif
                </div>
                @include('admin.orders.v2._notice', ['section' => 'keep'])
                @if (in_array($keep, ['active', 'expired'], true))
                    <p class="mt-3 text-sm text-gray-800">Batas: <strong>{{ $time($order->keep_until) }}</strong> ({{ $order->keep_until->locale('id')->diffForHumans() }})</p>
                    @if ($keep === 'expired')<p class="mt-1 text-sm text-red-800">Lewat batas. Tidak dibatalkan otomatis — hubungi customer, lalu perpanjang atau lepas.</p>@endif
                    @if ($order->keep_stock_confirmed_at)
                        <p class="mt-2 text-sm text-green-800">Stok sudah dipisahkan {{ $time($order->keep_stock_confirmed_at) }}.</p>
                    @else
                        <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'stock']) }}" class="mt-3">
                            @csrf {!! $hidden('keep') !!}
                            <button class="{{ $primary }}" data-busy-label="Menyimpan…">Stok sudah saya pisahkan</button>
                        </form>
                    @endif
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'extend']) }}" class="flex items-end gap-2">
                            @csrf {!! $hidden('keep') !!}
                            <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Perpanjang
                                <select name="hours" class="{{ $input }}">@foreach ([6, 12, 24, 48] as $hours)<option value="{{ $hours }}" @selected($hours === 24)>{{ $hours }} jam dari sekarang</option>@endforeach</select>
                            </label>
                            <button class="{{ $secondary }}" data-busy-label="…">Perpanjang</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'release']) }}" class="flex items-end gap-2" data-confirm="Lepas keep? Stok boleh dijual lagi.">
                            @csrf {!! $hidden('keep') !!}
                            <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Alasan lepas<input name="reason" required minlength="5" maxlength="200" class="{{ $input }}"></label>
                            <button class="{{ $secondary }}" data-busy-label="…">Lepas</button>
                        </form>
                    </div>
                @elseif ($open && $pending && $order->payment_status === 'unpaid')
                    <p class="mt-3 text-sm text-gray-600">Customer minta barang disimpan dulu? Keep boleh sebelum bayar.</p>
                    <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'start']) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf {!! $hidden('keep') !!}
                        <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Lama keep
                            <select name="hours" class="{{ $input }}">@foreach ([6, 12, 24, 48, 72] as $hours)<option value="{{ $hours }}" @selected($hours === 24)>{{ $hours }} jam</option>@endforeach</select>
                        </label>
                        <button class="{{ $secondary }}" data-busy-label="Menyimpan…">Mulai keep</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-gray-600">{{ $keep === 'converted' ? 'Keep selesai karena pesanan sudah lunas/diserahkan.' : 'Keep hanya untuk pesanan yang belum dibayar dan belum diserahkan.' }}</p>
                @endif
            </section>

            {{-- Issues --}}
            <section id="kendala" class="{{ $card }}" aria-labelledby="kendala-title">
                <h2 id="kendala-title" class="text-lg font-semibold text-gray-900">Kendala</h2>
                @include('admin.orders.v2._notice', ['section' => 'kendala'])
                @if ($order->issues->isEmpty())
                    <p class="mt-3 text-sm text-gray-600">Tidak ada kendala.</p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($order->issues as $issue)
                            <li class="rounded-lg border p-3 text-sm {{ $issue->status === 'open' ? 'border-red-200 bg-red-50' : 'border-gray-200' }}">
                                <p class="font-semibold text-gray-900">{{ OnlineOrderIssue::TYPES[$issue->type] }} <span class="font-normal text-gray-500">· {{ $issue->opened_by_name }} · {{ $time($issue->created_at) }}</span></p>
                                <p class="mt-1 text-gray-800">{{ $issue->note }}@if ($issue->reported_quantity !== null) (ada {{ $issue->reported_quantity }})@endif</p>
                                @if ($issue->status === 'resolved')
                                    <p class="mt-1 text-green-800">Selesai {{ $time($issue->resolved_at) }}{{ $issue->resolution_note ? ': '.$issue->resolution_note : '' }}</p>
                                @else
                                    <form method="POST" action="{{ route('admin.orders.v2.issues.resolve', [$order, $issue->public_id]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                        @csrf {!! $hidden('kendala') !!}
                                        <label class="min-w-0 flex-1 text-xs font-medium text-gray-700">Penyelesaian <span class="font-normal text-gray-500">(opsional)</span><input name="note" maxlength="500" class="{{ $input }}"></label>
                                        <button class="{{ $secondary }}" data-busy-label="…">Tandai selesai</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($order->lifecycle !== 'cancelled' && config('orders_api.enabled'))
                    <p class="mt-3 text-sm text-gray-600" data-issue-app-only>Selama integrasi Qammaris App aktif, kendala baru dicatat dari Qammaris App. Kendala yang ada tetap bisa ditandai selesai di sini.</p>
                @elseif ($order->lifecycle !== 'cancelled')
                    <details class="mt-3" @if (old('_section') === 'kendala') open @endif>
                        <summary class="{{ $quiet }} cursor-pointer">+ Catat kendala</summary>
                        <form method="POST" action="{{ route('admin.orders.v2.issues', $order) }}" class="mt-2 grid gap-3 sm:grid-cols-2">
                            @csrf {!! $hidden('kendala') !!}
                            <label class="text-sm font-medium text-gray-700">Jenis
                                <select name="type" required class="{{ $input }}">@foreach (OnlineOrderIssue::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">Barang <span class="font-normal text-gray-500">(untuk kendala stok)</span>
                                <select name="line_id" class="{{ $input }}"><option value="">—</option>@foreach ($order->items as $item)<option value="{{ $item->line_id }}">{{ $item->label() }} (dipesan {{ $item->quantity }})</option>@endforeach</select>
                            </label>
                            <label class="text-sm font-medium text-gray-700 sm:col-span-2">Keterangan<input name="note" required maxlength="500" value="{{ old('note') }}" class="{{ $input }}"></label>
                            <label class="text-sm font-medium text-gray-700">Jumlah tersedia <span class="font-normal text-gray-500">(opsional)</span><input name="reported_quantity" type="number" min="0" max="99" inputmode="numeric" class="{{ $input }}"></label>
                            <div class="flex items-end"><button class="{{ $primary }}" data-busy-label="Mencatat…">Catat kendala</button></div>
                        </form>
                    </details>
                @endif
            </section>

            {{-- Price adjustments --}}
            <section id="harga" class="{{ $card }}" aria-labelledby="harga-title">
                <h2 id="harga-title" class="text-lg font-semibold text-gray-900">Penyesuaian harga</h2>
                @include('admin.orders.v2._notice', ['section' => 'harga'])
                @forelse ($order->adjustments as $adjustment)
                    <div class="mt-3 rounded-lg border border-gray-200 p-3 text-sm">
                        <p class="flex justify-between gap-2"><span class="font-medium">{{ $adjustment->reason }}</span><span class="shrink-0 font-semibold tabular-nums">{{ format_rupiah($adjustment->amount) }}</span></p>
                        <p class="mt-1 text-xs text-gray-500">{{ ['pending' => 'Menunggu persetujuan Super Admin', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$adjustment->status] }}{{ $adjustment->decision_note ? ' · '.$adjustment->decision_note : '' }}</p>
                        @if ($adjustment->status === 'pending')
                            @can('orders.approve-adjustment')
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <form method="POST" action="{{ route('admin.orders.v2.adjustments.decide', [$order, $adjustment->id, 'approve']) }}" data-confirm="Setujui perubahan harga ini?">
                                        @csrf {!! $hidden('harga') !!}<button class="{{ $secondary }} w-full" data-busy-label="…">Setujui</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.orders.v2.adjustments.decide', [$order, $adjustment->id, 'reject']) }}" class="flex gap-2">
                                        @csrf {!! $hidden('harga') !!}<input name="note" required minlength="5" maxlength="300" placeholder="Alasan tolak" aria-label="Alasan menolak" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-base"><button class="{{ $secondary }}" data-busy-label="…">Tolak</button>
                                    </form>
                                </div>
                            @endcan
                        @endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-gray-600">Belum ada penyesuaian.</p>
                @endforelse
                @if ($open)
                    <details class="mt-3" @if (old('_section') === 'harga') open @endif>
                        <summary class="{{ $quiet }} cursor-pointer">+ Ajukan penyesuaian</summary>
                        <form method="POST" action="{{ route('admin.orders.v2.adjustments', $order) }}" class="mt-2 grid gap-3 sm:grid-cols-3">
                            @csrf {!! $hidden('harga') !!}
                            <label class="text-sm font-medium text-gray-700">Jenis
                                <select name="direction" class="{{ $input }}"><option value="discount">Potongan</option><option value="surcharge">Tambahan</option></select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">Nominal (Rp)<input name="amount" inputmode="numeric" required value="{{ old('amount') }}" class="{{ $input }}"></label>
                            <label class="text-sm font-medium text-gray-700">Alasan<input name="reason" required minlength="5" maxlength="300" value="{{ old('reason') }}" class="{{ $input }}"></label>
                            <div class="sm:col-span-3"><button class="{{ $primary }}" data-busy-label="Mengajukan…">Ajukan</button>
                                <p class="mt-1 text-xs text-gray-500">Total berubah setelah disetujui Super Admin.</p></div>
                        </form>
                    </details>
                @endif
            </section>

            {{-- Customer change requests --}}
            @if ($order->changeRequests->isNotEmpty())
                <section id="perubahan" class="{{ $card }}" aria-labelledby="perubahan-title">
                    <h2 id="perubahan-title" class="text-lg font-semibold text-gray-900">Permintaan perubahan</h2>
                    @include('admin.orders.v2._notice', ['section' => 'perubahan'])
                    @foreach ($order->changeRequests->sortByDesc('id') as $change)
                        <div class="mt-3 rounded-lg border p-3 text-sm {{ $change->status === 'pending' ? 'border-amber-300 bg-amber-50' : 'border-gray-200' }}">
                            <p class="text-xs text-gray-600">Dari {{ $change->source === 'customer' ? 'customer' : 'admin' }} · {{ $time($change->created_at) }} · {{ ['pending' => 'Menunggu ditinjau', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$change->status] }}</p>
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

            @can('orders.refund')
                @if ($received > 0 || $order->refund_status)
                    @include('admin.orders.v2._money-panel')
                @endif
            @endcan
        </div>

        <div class="min-w-0 space-y-5">
            {{-- Items and totals --}}
            <section id="ringkasan" class="{{ $card }}" aria-labelledby="items-title">
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
                    @if ($order->shipping_payer === 'added_to_transfer' && $order->shipping_fee !== null)<div class="flex justify-between"><dt class="text-gray-600">Ongkir</dt><dd class="tabular-nums">{{ format_rupiah($order->shipping_fee) }}</dd></div>@endif
                    @if ($order->approvedAdjustmentCents() !== 0)<div class="flex justify-between"><dt class="text-gray-600">Penyesuaian</dt><dd class="tabular-nums">{{ $rp($order->approvedAdjustmentCents()) }}</dd></div>@endif
                    <div class="flex justify-between text-base font-semibold"><dt>Total customer</dt><dd class="tabular-nums">{{ $rp($totalCents) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Sudah diterima</dt><dd class="tabular-nums">{{ $rp($received) }}</dd></div>
                </dl>
                <p class="mt-1 text-sm text-gray-600">Paperbag: {{ OnlineOrder::PACKAGING[$order->packaging] ?? 'belum dipilih' }}</p>
            </section>

            @unless ($linkFirst)
                @include('admin.orders.v2._links')
            @endunless

            {{-- Repeat customer --}}
            <section id="pelanggan" class="{{ $card }}" aria-labelledby="pelanggan-title">
                <h2 id="pelanggan-title" class="text-lg font-semibold text-gray-900">Pelanggan</h2>
                @include('admin.orders.v2._notice', ['section' => 'pelanggan'])
                @if ($order->customer)
                    <p class="mt-2 text-sm text-gray-800"><strong>{{ $order->customer->name }}</strong> · 0{{ substr($order->customer->phone, 2) }}</p>
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
                        <form method="POST" action="{{ route('admin.orders.v2.customer.address', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3">
                            @csrf {!! $hidden('pelanggan') !!}
                            <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Simpan alamat pesanan ini sebagai<input name="label" required maxlength="40" placeholder="Rumah / Kantor" class="{{ $input }}"></label>
                            <button class="{{ $secondary }}" data-busy-label="…">Simpan alamat</button>
                        </form>
                    @endif
                @else
                    <p class="mt-2 text-sm text-gray-600">Belum dihubungkan. Pesanan tidak pernah digabung otomatis.</p>
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
            </section>

            {{-- Editable details --}}
            <form id="detail" method="POST" action="{{ route('admin.orders.v2.details', $order) }}" class="{{ $card }} space-y-4" aria-labelledby="details-title">
                @csrf @method('PATCH') {!! $hidden('detail') !!}
                <h2 id="details-title" class="text-lg font-semibold text-gray-900">Detail penerima</h2>
                @include('admin.orders.v2._notice', ['section' => 'detail'])
                @if ($customerChatUrl)<a href="{{ $customerChatUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center text-sm text-gray-700 underline underline-offset-4">Chat customer di WhatsApp</a>@endif
                @include('admin.orders._customer-fields', ['order' => $order])
                <label class="block text-sm font-medium text-gray-700">Link lokasi Google Maps <span class="font-normal text-gray-500">(opsional)</span>
                    <input name="location_url" type="url" inputmode="url" value="{{ old('location_url', $order->location_url) }}" maxlength="500" placeholder="https://maps.app.goo.gl/…" class="{{ $input }}">
                </label>
                <fieldset class="space-y-3 border-t border-gray-100 pt-3">
                    <legend class="pt-3 text-sm font-semibold text-gray-700">Ongkir</legend>
                    @cannot('orders.finance')<p class="rounded-lg bg-gray-50 p-2 text-xs text-gray-600">Ongkir dan pendanaan driver hanya dapat diubah Super Admin.</p>@endcannot
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">Ongkir (Rp)<input name="shipping_fee" @cannot('orders.finance') disabled @endcannot inputmode="numeric" value="{{ old('shipping_fee', $order->shipping_fee !== null ? (int) $order->shipping_fee : null) }}" class="{{ $input }} disabled:bg-gray-100"></label>
                        <label class="text-sm font-medium text-gray-700">Ongkir dibayar
                            <select name="shipping_payer" @cannot('orders.finance') disabled @endcannot class="{{ $input }} disabled:bg-gray-100"><option value="">Belum ditentukan</option>@foreach (OnlineOrder::SHIPPING_PAYERS as $value => $label)<option value="{{ $value }}" @selected(old('shipping_payer', $order->shipping_payer) === $value)>{{ $label }}</option>@endforeach</select>
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Toko bayar driver dengan
                        <select name="driver_funding" @cannot('orders.finance') disabled @endcannot class="{{ $input }} disabled:bg-gray-100"><option value="">Tidak perlu / belum ditentukan</option>@foreach (OnlineOrder::DRIVER_FUNDING as $value => $label)<option value="{{ $value }}" @selected(old('driver_funding', $order->driver_funding) === $value)>{{ $label }}</option>@endforeach</select>
                    </label>
                </fieldset>
                <label class="block text-sm font-medium text-gray-700">Catatan untuk staf<input name="staff_note" value="{{ old('staff_note', $order->staff_note) }}" maxlength="300" class="{{ $input }}"></label>
                <input type="hidden" name="recorded_in_majoo" value="{{ $order->recorded_in_majoo ? 1 : 0 }}">
                <button class="{{ $primary }} sm:w-full" data-busy-label="Menyimpan…">Simpan detail</button>
            </form>

            {{-- Cancel --}}
            @if ($open)
                <section id="batal" class="{{ $card }}" aria-labelledby="batal-title">
                    <h2 id="batal-title" class="text-lg font-semibold text-gray-900">Batalkan pesanan</h2>
                    @include('admin.orders.v2._notice', ['section' => 'batal'])
                    @if ($received > 0 && ! auth()->user()->can('orders.refund'))
                        <p class="mt-2 text-sm text-gray-600">Pesanan ini sudah ada pembayaran, jadi hanya Super Admin yang dapat membatalkan.</p>
                    @elseif (! auth()->user()->can('orders.cancel', $order))
                        <p class="mt-2 text-sm text-gray-600">Pesanan yang sudah ada pembayaran atau sudah diserahkan hanya dapat dibatalkan Super Admin.</p>
                    @else
                        <details class="mt-2" @if (old('_section') === 'batal') open @endif>
                            <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50">Batalkan…</summary>
                            <form method="POST" action="{{ route('admin.orders.v2.cancel', $order) }}" class="mt-2 space-y-3" data-confirm="Batalkan pesanan {{ $order->code }}?">
                                @csrf {!! $hidden('batal') !!}
                                <label class="block text-sm font-medium text-gray-700">Alasan<input name="reason" required minlength="5" maxlength="200" value="{{ old('reason') }}" class="{{ $input }}"></label>
                                @if ($received > 0)
                                    <label class="block text-sm font-medium text-gray-700">Nominal yang dikembalikan ke customer (Rp)
                                        <input name="refund_due" required inputmode="numeric" value="{{ old('refund_due') }}" placeholder="0 sampai {{ (int) ($received / 100) }}" class="{{ $input }}">
                                    </label>
                                    <p class="text-xs text-gray-600">Sudah diterima {{ $rp($received) }}. Tidak ada refund yang diasumsikan — tulis 0 bila tidak dikembalikan.</p>
                                @endif
                                <button class="{{ $danger }}" data-busy-label="Membatalkan…">Ya, batalkan pesanan</button>
                            </form>
                        </details>
                    @endif
                </section>
            @elseif ($order->lifecycle === 'cancelled')
                <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">Dibatalkan {{ $time($order->closed_at) }}: {{ $order->cancel_reason }}</p>
            @endif

            {{-- History --}}
            <section id="riwayat" class="{{ $card }}" aria-labelledby="riwayat-title">
                <h2 id="riwayat-title" class="text-lg font-semibold text-gray-900">Riwayat</h2>
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach ($order->events->reverse() as $event)
                        <li class="border-l-2 border-gray-200 pl-3">
                            <p class="text-gray-900"><span class="font-medium">{{ $event->actorLabel() }}</span> {{ OnlineOrderLabels::event($event) }}</p>
                            @if ($event->note)<p class="break-words text-gray-600">{{ $event->note }}</p>@endif
                            <p class="text-xs tabular-nums text-gray-500">{{ $time($event->created_at) }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
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
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        const field = document.querySelector(button.dataset.copy);
        const label = button.textContent;
        let copied = false;
        try { await navigator.clipboard.writeText(field.value); copied = true; } catch (error) {
            field.focus(); field.select();
            try { copied = document.execCommand('copy'); } catch (fallbackError) { copied = false; }
        }
        button.textContent = copied ? 'Tersalin ✓' : 'Teks sudah dipilih, tekan salin';
        feedback.textContent = copied ? 'Teks tersalin.' : 'Salin otomatis tidak tersedia; teks sudah dipilih.';
        setTimeout(() => { button.textContent = label; }, 2500);
    }));
})();
</script>
@endpush
