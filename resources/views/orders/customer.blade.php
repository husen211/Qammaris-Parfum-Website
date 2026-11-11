@extends('layouts.app')
@section('title', 'Pesanan '.$order->code.' - Qammaris Perfumes')
@section('robots', 'noindex,nofollow')
@section('minimal_chrome', '1')
@push('meta')
    <meta name="referrer" content="no-referrer">
@endpush

@php
    $fieldClass = 'mt-2 min-h-12 w-full border border-gray-300 bg-white px-3 py-3 text-base text-brand-black focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black aria-[invalid=true]:border-red-700';
    $choiceClass = 'flex min-h-14 cursor-pointer items-start gap-3 border border-gray-300 bg-white p-4 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] active:bg-gray-50 has-[:checked]:border-brand-black has-[:checked]:bg-[#FAF8F3] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-black';
    $fulfillment = old('fulfillment', $order->fulfillment);
    $describedBy = fn (string $field, ?string $help = null) => trim(($help ?? '').($errors->has($field) ? ' '.$field.'-error' : ''));
@endphp

@section('content')
<section class="bg-white px-4 pb-16 pt-6 md:pt-10" aria-labelledby="order-title" data-order-page>
    <div class="mx-auto max-w-xl">
        <p class="text-sm text-gray-600">Pesanan {{ $order->code }}</p>
        <h1 id="order-title" class="mt-1 font-mayluxa text-3xl text-brand-black md:text-4xl">
            {{ $editing ? ($order->stage === 'awaiting_customer' ? 'Lengkapi data pesanan' : 'Ubah data pesanan') : ($order->lifecycle === 'draft' ? 'Pesanan tersimpan' : 'Status pesanan') }}
        </h1>

        {{-- ORD-13: the customer keeps their own link (also in the WhatsApp chat and "Pesanan Saya"). --}}
        @unless ($editing)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border border-gray-200 bg-white px-3 py-3" data-copy-box data-order-link>
                <p class="min-w-0 flex-1 text-sm leading-5 text-gray-700"><span class="block font-semibold text-brand-black">Link pesanan Anda</span>Buka lagi kapan saja untuk cek status. Link ini juga ada di chat WhatsApp dan menu Pesanan Saya.</p>
                <button type="button" data-copy-account="{{ route('orders.customer.show', $token) }}" data-copy-done="Link pesanan disalin." class="inline-flex min-h-11 shrink-0 items-center gap-2 border border-brand-black bg-white px-3 text-xs font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    Salin link
                </button>
                <p data-copy-status role="status" aria-live="polite" class="w-full text-sm text-gray-600 empty:hidden"></p>
            </div>
        @endunless

        {{-- ORD-14: the driver booked through Biteship, from booking until the parcel is received. --}}
        @php($courierTrip = $order->isV2() && ($order->courier_booking['state'] ?? null) === 'active' && $order->delivery_status !== 'delivered' && $order->lifecycle !== 'cancelled' ? $order->courier_booking : null)
        @if ($courierTrip)
            <section class="mt-6 border border-gray-200 bg-white p-4" aria-labelledby="courier-title" data-customer-courier="{{ $courierTrip['status'] ?? '' }}">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ \App\Actions\Orders\OnlineOrderFulfillment::biteshipLabel($courierTrip['courier'] ?? null) }}</p>
                <h2 id="courier-title" class="mt-1 text-base font-semibold text-brand-black">{{ match ($courierTrip['status'] ?? null) {
                    'allocated', 'picking_up' => 'Driver menuju toko untuk mengambil pesanan',
                    'picked', 'in_transit' => 'Pesanan dalam perjalanan ke alamat Anda',
                    'dropping_off' => 'Driver hampir sampai',
                    'on_hold' => 'Pengiriman ditahan sementara. Admin akan menghubungi Anda.',
                    'return_in_transit', 'returned' => 'Pesanan dikembalikan ke toko. Admin akan menghubungi Anda.',
                    default => 'Mencari driver untuk pesanan Anda',
                } }}</h2>
                @if (! empty($courierTrip['driver_name']))
                    <p class="mt-1 text-sm text-gray-700" data-customer-driver>Driver {{ $courierTrip['driver_name'] }}@if (! empty($courierTrip['driver_plate'])) · <span class="font-mono">{{ $courierTrip['driver_plate'] }}</span>@endif</p>
                @endif
                @if (! empty($courierTrip['link']))
                    <a href="{{ $courierTrip['link'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 flex min-h-12 w-full items-center justify-center gap-2 border border-brand-black bg-white px-4 text-xs font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black" data-customer-track>
                        {{-- Lucide "map-pin"; docs/licenses/lucide.txt --}}
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                        Lacak driver
                    </a>
                @endif
                @if ($order->customer_phone)
                    <p class="mt-3 text-sm leading-6 text-gray-700" data-customer-driver-contact>Driver akan menghubungi nomor Anda, <span class="tabular-nums">{{ \App\Support\PhoneNumber::local($order->customer_phone) }}</span>. Pastikan HP aktif.@if ($whatsappUrl) Belum bisa terhubung dengan driver? <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-black underline underline-offset-2" data-customer-courier-help>Chat admin toko</a>.@endif</p>
                @endif
                <p class="mt-2 text-xs text-gray-500">Status diperbarui otomatis dari kurir. Muat ulang halaman untuk melihat yang terbaru.</p>
            </section>
        @endif

        {{-- ORD-04: receipt confirmation stays at the top while the parcel is on its way. --}}
        @if ($order->isV2() ? ($order->handover_status === 'handed_over' && $order->fulfillment !== 'pickup' && $order->delivery_status !== 'delivered' && $order->lifecycle !== 'cancelled') : ($order->stage === 'shipped' && $order->fulfillment !== 'pickup'))
            @php($jnt = $order->fulfillment === 'intercity')
            <form method="POST" action="{{ route('orders.customer.received', $token) }}" class="mt-6 border-l-4 border-brand-gold bg-[#FAF8F3] p-4" data-received-form>
                @csrf
                <p class="flex items-center gap-3 text-base font-semibold text-brand-black">
                    @if ($jnt)
                        {{-- Lucide "truck"; docs/licenses/lucide.txt --}}
                        <svg data-shipping-icon="jnt" class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                        <span>Dikirim lewat J&amp;T</span>
                    @else
                        {{-- Tabler Icons "motorbike" (MIT); docs/licenses/tabler-icons.txt --}}
                        <svg data-shipping-icon="motor" class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 16a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M16 16a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M7.5 14h5l4 -4h-10.5m1.5 4l4 -4"/><path d="M13 6h2l1.5 3l2 4"/></svg>
                        <span>Dalam pengiriman kurir dalam kota</span>
                    @endif
                </p>
                <p class="mt-1 text-sm leading-6 text-gray-700">Sudah sampai di tangan Anda?</p>
                <button type="submit" class="mt-3 flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-gray-700 hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:bg-gray-500">Pesanan sudah saya terima</button>
            </form>
        @endif

        {{-- ORD-04: the order exists before WhatsApp opens. Opening WhatsApp is not a confirmation; the store confirms. --}}
        @if ($order->lifecycle === 'draft' && ! $showPayment)
            <section class="mt-6 border-l-4 border-brand-gold bg-[#FAF8F3] p-4" aria-labelledby="next-title" data-checkout-next>
                @if ($waitingFor)
                    {{-- ORD-13: nothing to pay yet; staff check what "Tersedia" cannot promise (quantity) or price the ongkir. --}}
                    <div class="flex items-start gap-3" data-waiting>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-[#8A6A2E]" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="next-title" class="text-base font-semibold text-brand-black">{{ $waitingFor['stock']->isNotEmpty() ? 'Menunggu admin cek stok' : 'Menunggu admin menghitung ongkir' }}</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-700">
                                @if ($waitingFor['stock']->isNotEmpty())
                                    {{ $waitingFor['stock']->map(fn ($item) => $item->product_name.' × '.$item->quantity)->join(', ') }} perlu dicek jumlah stoknya.
                                @else
                                    Ongkir pengiriman dihitung admin.
                                @endif
                                Kirim pesanan ke WhatsApp supaya admin segera mengecek. Cara bayar muncul di halaman ini setelah dikonfirmasi.
                            </p>
                        </div>
                    </div>
                @else
                    <h2 id="next-title" class="text-base font-semibold text-brand-black">Langkah berikutnya: kirim pesan WhatsApp</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-700">Nomor pesanan Anda <strong class="text-brand-black">{{ $order->code }}</strong>. Kirim pesan ini supaya admin mengonfirmasi stok{{ $order->shipping_quote ? '' : ', ongkir,' }} dan pembayaran. Pesanan belum diproses sebelum dikonfirmasi admin.</p>
                @endif
                @if ($checkoutWhatsappUrl)
                    <a href="{{ $checkoutWhatsappUrl }}" rel="noopener noreferrer" data-checkout-whatsapp @if (session('checkout_placed')) data-auto-open="1" @endif class="mt-3 flex min-h-14 w-full items-center justify-center bg-[#0D3F33] px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-[#0a2f26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black gap-3" data-whatsapp-button><x-icon name="whatsapp" class="text-[1.2rem]" /> {{ $waitingFor ? 'Kirim pesanan ke WhatsApp' : 'Kirim ke WhatsApp' }}</a>
                    <p class="mt-2 text-sm leading-6 text-gray-600">WhatsApp tidak terbuka atau pesan belum terkirim? Tekan Kirim ke WhatsApp lagi. Pesanan Anda tetap tersimpan.</p>
                @else
                    {{-- ORD-07: no store number here (test environment): never fall back to the real store chat. --}}
                    <p class="mt-2 text-sm leading-6 text-gray-700">WhatsApp toko tidak tersedia di sini. Salin pesan berikut dan kirim ke admin Qammaris. Pesanan Anda tetap tersimpan.</p>
                    <label for="checkout-message" class="mt-3 block text-sm font-medium text-brand-black">Pesan untuk admin</label>
                    <textarea id="checkout-message" readonly rows="8" class="mt-1 w-full resize-none border border-gray-300 bg-white px-3 py-2 text-base leading-6 text-brand-black" data-checkout-message>{{ $checkoutMessage }}</textarea>
                    <button type="button" data-copy-checkout class="mt-2 flex min-h-12 w-full items-center justify-center border border-brand-black bg-white px-4 text-sm font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Salin pesan</button>
                    <p data-copy-feedback role="status" aria-live="polite" class="mt-1 min-h-5 text-sm text-gray-600"></p>
                @endif
                @if ($order->fulfillment === 'local_delivery' && ! $order->location_url)
                    <p class="mt-3 text-sm leading-6 text-gray-600">Pengiriman Dalam Kota: kirim juga Sharelok di chat yang sama supaya kurir tepat sampai.</p>
                @endif
                <p class="mt-3 text-sm leading-6 text-gray-600">Simpan halaman ini untuk melihat status pesanan.</p>
            </section>
        @endif

        @if ($showPayment)
            {{-- ORD-04/ORD-13: final total, verified accounts or the store QRIS; paying is confirmed by the store, never here. --}}
            @php($payChoice = old('payment_preference', $order->payment_preference === 'qris' && $qrisUrl ? 'qris' : 'transfer'))
            <section class="mt-6 border-l-4 border-brand-gold bg-[#FAF8F3] p-4" aria-labelledby="pay-title" data-customer-payment>
                <h2 id="pay-title" class="text-base font-semibold text-brand-black">{{ $order->lifecycle === 'draft' ? 'Langkah berikutnya: pembayaran' : 'Pembayaran' }}</h2>
                @if ($order->lifecycle === 'draft')
                    <p class="mt-1 text-sm leading-6 text-gray-700">Pesanan <strong class="text-brand-black">{{ $order->code }}</strong> sudah tersimpan dan produknya tersedia. Bayar sekarang, lalu kirim buktinya lewat WhatsApp; admin mengecek dan memproses pesanan.</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-gray-700">Total pembayaran</span>
                    <span class="flex items-center gap-2"><strong class="text-xl tabular-nums text-brand-black">{{ format_rupiah($order->customerTotal()) }}</strong>
                        <button type="button" data-copy-account="{{ (int) \App\Support\Rupiah::minorUnits($order->customerTotal()) / 100 }}" data-copy-done="Total disalin." class="inline-flex min-h-10 items-center border border-brand-black bg-white px-2.5 text-xs font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100">Salin</button></span>
                </div>
                @if ($receivedCents > 0)<p class="text-sm text-gray-700">Sudah diterima {{ format_rupiah(\App\Support\Rupiah::decimal($receivedCents)) }}.</p>@endif
                {{-- ORD-15 (Owner 2026-11-11): unpaid orders are cancelled automatically after the deadline. --}}
                @if ($receivedCents === 0 && config('orders.unpaid_cancel_hours') > 0 && $order->keep_status !== 'active')
                    <p class="mt-2 flex items-start gap-2 text-sm leading-6 text-gray-700" data-payment-deadline>
                        <svg aria-hidden="true" class="mt-1 h-4 w-4 shrink-0 text-[#8A6A2E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span>@if ($order->payment_due_at)Bayar sebelum <strong class="text-brand-black">{{ $order->payment_due_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('l, j M Y, H.i') }} WITA</strong>.@else Bayar dalam {{ config('orders.unpaid_cancel_hours') }} jam.@endif Lewat dari itu, pesanan dibatalkan otomatis.</span>
                    </p>
                @endif
                <form method="POST" action="{{ route('orders.customer.pay', $token) }}" class="mt-4" data-pay-form>
                    @csrf
                    <fieldset>
                        <legend class="text-sm font-medium text-brand-black">Pilih cara bayar</legend>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <label class="{{ $choiceClass }} items-center">
                                <input type="radio" name="payment_preference" value="transfer" @checked($payChoice === 'transfer') class="h-5 w-5 shrink-0 accent-brand-black" data-pay-choice>
                                <span class="text-base font-medium text-brand-black">Transfer bank</span>
                            </label>
                            <label class="{{ $choiceClass }} items-center {{ $qrisUrl ? '' : 'cursor-not-allowed opacity-60' }}">
                                <input type="radio" name="payment_preference" value="qris" @checked($payChoice === 'qris') @disabled(! $qrisUrl) class="h-5 w-5 shrink-0 accent-brand-black" data-pay-choice>
                                <span class="text-base font-medium text-brand-black">QRIS @unless ($qrisUrl)<span class="block text-xs font-normal text-gray-600">Segera tersedia</span>@endunless</span>
                            </label>
                        </div>
                        @error('payment_preference') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                    </fieldset>
                    <div class="mt-3" data-pay-panel="transfer">
                        @if ($paymentAccounts->isNotEmpty())
                            <p class="text-sm text-gray-700">Transfer ke rekening resmi Qammaris:</p>
                            <ul class="mt-2 space-y-2">
                                @foreach ($paymentAccounts as $account)
                                    <li class="border border-gray-200 bg-white p-3" data-payment-account>
                                        <div class="flex items-center gap-3">
                                            <x-bank-mark :bank="$account->bank_name" />
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm text-gray-600">{{ $account->bank_name }}</p>
                                                <p class="break-all font-mono text-lg font-semibold tracking-wide text-brand-black">{{ $account->account_number }}</p>
                                                <p class="text-sm text-gray-600">a.n. {{ $account->account_holder }}</p>
                                            </div>
                                        </div>
                                        <button type="button" data-copy-account="{{ preg_replace('/\D/', '', $account->account_number) }}" data-copy-done="Nomor rekening {{ $account->bank_name }} disalin." class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 border border-brand-black bg-white px-3 text-xs font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100">
                                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            Salin nomor rekening
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-700">Nomor rekening resmi dikirim admin lewat WhatsApp.</p>
                        @endif
                    </div>
                    @if ($qrisUrl)
                        <div class="mt-3" data-pay-panel="qris">
                            <p class="text-sm text-gray-700">Scan QRIS ini dengan aplikasi bank atau e-wallet, lalu masukkan total <strong class="text-brand-black">{{ format_rupiah($order->customerTotal()) }}</strong>.</p>
                            <img src="{{ $qrisUrl }}" alt="QRIS Qammaris Perfumes" class="mx-auto mt-3 w-full max-w-xs border border-gray-200 bg-white p-2" loading="lazy" decoding="async">
                            <a href="{{ $qrisUrl }}" target="_blank" rel="noopener" download class="mx-auto mt-2 flex min-h-11 max-w-xs items-center justify-center border border-brand-black bg-white px-3 text-xs font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100">Simpan gambar QRIS</a>
                        </div>
                    @endif
                    <p class="mt-3 text-sm leading-6 text-gray-700">Sudah bayar? Tekan tombol di bawah, lalu kirim foto atau screenshot bukti bayar di chat WhatsApp. Pesanan disiapkan setelah admin mengecek pembayaran.</p>
                    <button type="submit" class="mt-3 flex min-h-14 w-full items-center justify-center gap-3 bg-[#0D3F33] px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-[#0a2f26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black" data-proof-whatsapp data-whatsapp-button><x-icon name="whatsapp" class="text-[1.2rem]" /> Kirim bukti bayar ke WhatsApp</button>
                </form>
                @if (session('proof_copy') || ! $proofWhatsappUrl)
                    {{-- ORD-07: no store number in test environments: the proof message is shown for copying. --}}
                    <p class="mt-3 text-xs leading-5 text-gray-600">WhatsApp toko tidak tersedia di sini. Kirim pesan ini beserta bukti bayar ke admin:</p>
                    <textarea readonly rows="7" class="mt-1 w-full resize-none border border-gray-300 bg-white px-3 py-2 text-sm leading-6 text-brand-black" data-proof-message>{{ $proofMessage }}</textarea>
                @endif
                <p data-copy-account-feedback role="status" aria-live="polite" class="mt-1 min-h-5 text-sm text-gray-600"></p>
            </section>
        @endif

        @if (session('success'))
            <div role="status" class="mt-6 border border-emerald-200 bg-emerald-50 p-4 text-base text-emerald-900">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="mt-6 border border-red-200 bg-red-50 p-4 text-base text-red-800">{{ session('error') }}</div>
        @endif

        <section class="mt-6 border border-gray-200 bg-[#FAF8F3] p-4" aria-labelledby="items-title">
            <h2 id="items-title" class="text-sm font-semibold text-brand-black">Produk yang dipesan</h2>
            <ul class="mt-3 divide-y divide-gray-200">
                @foreach ($order->items as $item)
                    <li class="flex gap-3 py-3 first:pt-0 last:pb-0">
                        <img src="{{ $images[$item->product_id] ?? asset('images/product-placeholder.svg') }}" alt="" width="56" height="70" class="h-[70px] w-14 shrink-0 bg-white object-contain" loading="lazy" decoding="async">
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-base font-medium leading-6 text-brand-black">{{ $item->product_name }}</p>
                            <p class="mt-0.5 text-sm text-gray-600">{{ $item->brand_name }}@if ($item->volume) · {{ $item->volume }} ml @endif</p>
                            <p class="mt-1 text-sm text-gray-700">{{ $item->quantity }} × {{ format_rupiah($item->unit_price) }}</p>
                        </div>
                        <p class="shrink-0 text-base font-semibold tabular-nums">{{ format_rupiah($item->lineTotal()) }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3">
                <span class="text-sm text-gray-600">Subtotal produk</span>
                <strong class="text-lg tabular-nums">{{ format_rupiah($order->subtotal()) }}</strong>
            </div>
            @if (\App\Support\Rupiah::minorUnits($order->packagingFee()) > 0)
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700" data-paperbag-row>
                    <span>Paperbag</span><span class="tabular-nums">{{ format_rupiah($order->packagingFee()) }}</span>
                </div>
            @endif
            @if ($order->shipping_quote && $order->shipping_payer === 'added_to_transfer' && $order->shipping_fee !== null)
                @php($quote = $order->shipping_quote)
                <div class="mt-1 flex flex-wrap items-start justify-between gap-2 text-sm text-gray-700" data-ongkir-quote>
                    <span>Ongkir {{ $quote['label'] }}@if ($quote['discount'] > 0 && \App\Support\Rupiah::minorUnits((string) $order->shipping_fee) === $quote['charged'] * 100)<span class="block text-xs text-[#8A6A2E]">Potongan ongkir {{ $quote['percent'] }}% dari Qammaris</span>@endif</span>
                    <span class="text-right tabular-nums">@if ($quote['discount'] > 0)<s class="mr-1 text-xs text-gray-400">{{ format_rupiah($quote['price']) }}</s>@endif{{ format_rupiah($order->shipping_fee) }}</span>
                </div>
            @elseif ($order->shipping_payer === 'added_to_transfer' && $order->shipping_fee !== null)
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700">
                    <span>Ongkir</span><span class="tabular-nums">{{ format_rupiah($order->shipping_fee) }}</span>
                </div>
            @elseif ($order->fulfillment !== 'pickup' && $order->shipping_payer === 'cash_to_driver')
                {{-- ORD-10: paid to the driver on arrival, so it is not part of the total below. --}}
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700" data-ongkir-driver>
                    <span>Ongkir</span><span>{{ $order->shipping_fee !== null ? format_rupiah($order->shipping_fee).', dibayar langsung ke driver' : 'Dibayar langsung ke driver' }}</span>
                </div>
            @elseif ($order->fulfillment !== 'pickup' && $order->shipping_fee === null && $order->payment_status !== 'paid')
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700">
                    <span>Ongkir</span><span>Menunggu konfirmasi staf</span>
                </div>
            @endif
            @if (\App\Support\OnlineOrderStepper::totalIsFinal($order))
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-gray-600">Total pembayaran</span>
                    <strong class="text-lg tabular-nums">{{ format_rupiah($order->customerTotal()) }}</strong>
                </div>
            @else
                <p class="mt-2 text-sm leading-6 text-gray-600">Total final setelah ongkir dikonfirmasi admin.</p>
            @endif
        </section>

        @if ($editing)
            @if ($errors->any())
                <div role="alert" class="mt-6 border border-red-200 bg-red-50 p-4 text-base text-red-800" tabindex="-1" data-error-summary>
                    <p class="font-medium">Periksa kembali isian berikut:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $message) <li>{{ $message }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('orders.customer.submit', $token) }}" class="mt-8 space-y-7" data-order-form novalidate>
                @csrf
                <div>
                    <label for="customer_name" class="block text-base font-medium text-brand-black">Nama penerima</label>
                    <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name', $order->customer_name) }}" required maxlength="100" autocomplete="name" aria-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}" @if ($errors->has('customer_name')) aria-describedby="customer_name-error" @endif class="{{ $fieldClass }}">
                    @error('customer_name') <p id="customer_name-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="customer_phone" class="block text-base font-medium text-brand-black">Nomor HP / WhatsApp</label>
                    <input id="customer_phone" name="customer_phone" type="tel" inputmode="tel" value="{{ old('customer_phone', $order->customer_phone) }}" required maxlength="25" autocomplete="tel" aria-invalid="{{ $errors->has('customer_phone') ? 'true' : 'false' }}" aria-describedby="{{ $describedBy('customer_phone', 'phone-help') }}" class="{{ $fieldClass }}">
                    <p id="phone-help" class="mt-2 text-sm text-gray-600">Contoh: 0812 3456 7890</p>
                    @error('customer_phone') <p id="customer_phone-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <fieldset aria-describedby="{{ $describedBy('fulfillment') ?: null }}">
                    <legend class="text-base font-medium text-brand-black">Cara menerima pesanan</legend>
                    <div class="mt-3 space-y-3">
                        @foreach ([
                            'pickup' => ['Ambil di toko', 'Datang dan ambil sendiri di toko Qammaris.'],
                            'local_delivery' => ['Pengiriman Dalam Kota', 'Tiba 1–2 jam setelah pesanan diproses. Lokasi lewat Sharelok.'],
                            'intercity' => ['Kirim ke luar kota', 'Dikirim J&T ke alamat Anda.'],
                        ] as $value => [$title, $hint])
                            <label class="{{ $choiceClass }}">
                                <input type="radio" name="fulfillment" value="{{ $value }}" @checked($fulfillment === $value) required class="mt-1 h-5 w-5 shrink-0 accent-brand-black" data-fulfillment>
                                <span><span class="block text-base font-medium text-brand-black">{{ $title }}</span><span class="mt-0.5 block text-sm leading-5 text-gray-600">{{ $hint }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('fulfillment') <p id="fulfillment-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </fieldset>

                <div data-show-for="local_delivery intercity" @if ($fulfillment === 'pickup') hidden @endif>
                    <label for="address" class="block text-base font-medium text-brand-black">
                        <span data-address-label>{{ $fulfillment === 'intercity' ? 'Alamat lengkap' : 'Alamat / patokan' }}</span>
                        <span class="font-normal text-gray-600" data-address-optional @if ($fulfillment === 'intercity') hidden @endif>(opsional)</span>
                    </label>
                    <textarea id="address" name="address" rows="3" maxlength="500" autocomplete="street-address" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="{{ $describedBy('address', 'address-help') }}" class="{{ $fieldClass }} resize-y leading-6" @if ($fulfillment === 'intercity') required @endif>{{ old('address', $order->address) }}</textarea>
                    <p id="address-help" class="mt-2 text-sm leading-5 text-gray-600" data-address-help>
                        {{ $fulfillment === 'intercity' ? 'Jalan, nomor rumah, dan kota.' : 'Cukup patokan. Lokasi tepat lewat Sharelok.' }}
                    </p>
                    @error('address') <p id="address-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div data-show-for="intercity" @if ($fulfillment !== 'intercity') hidden @endif>
                    <label for="postcode" class="block text-base font-medium text-brand-black">Kode pos <span class="font-normal text-gray-600">(opsional)</span></label>
                    <input id="postcode" name="postcode" value="{{ old('postcode', $order->postcode) }}" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="postal-code" aria-invalid="{{ $errors->has('postcode') ? 'true' : 'false' }}" @if ($errors->has('postcode')) aria-describedby="postcode-error" @endif class="{{ $fieldClass }} max-w-40">
                    @error('postcode') <p id="postcode-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <fieldset>
                    <legend class="text-base font-medium text-brand-black">Paperbag</legend>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        @foreach (\App\Models\OnlineOrder::PACKAGING as $value => $title)
                            <label class="{{ $choiceClass }} items-center">
                                <input type="radio" name="packaging" value="{{ $value }}" @checked(old('packaging', $order->packaging) === $value) required class="h-5 w-5 shrink-0 accent-brand-black">
                                <span class="text-base text-brand-black">{{ $title }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('packaging') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </fieldset>

                @if (config('orders.simple_ux') && $order->payment_status === 'unpaid')
                    <fieldset aria-describedby="payment-help{{ $errors->has('payment_preference') ? ' payment_preference-error' : '' }}">
                        <legend class="text-base font-medium text-brand-black">Pembayaran</legend>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            @foreach (config('orders.payment_preferences') as $value => $title)
                                <label class="{{ $choiceClass }} items-center">
                                    <input type="radio" name="payment_preference" value="{{ $value }}" @checked(old('payment_preference', $order->payment_preference) === $value) required class="h-5 w-5 shrink-0 accent-brand-black">
                                    <span class="text-base text-brand-black">{{ $title }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p id="payment-help" class="mt-2 text-sm text-gray-600">Detail pembayaran dikirim admin.</p>
                        @error('payment_preference') <p id="payment_preference-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                {{-- ORD-10: only a local courier can be paid on arrival; J&T ongkir is always part of the payment. --}}
                @if ($order->customerSetsShippingPayment())
                    @php($shippingPayment = old('shipping_payment', $order->fulfillment === 'local_delivery' ? $order->shipping_payer : null))
                    <fieldset data-show-for="local_delivery" @if ($fulfillment !== 'local_delivery') hidden @endif aria-describedby="shipping-payment-help{{ $errors->has('shipping_payment') ? ' shipping_payment-error' : '' }}">
                        <legend class="text-base font-medium text-brand-black">Metode pembayaran ongkir</legend>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            @foreach (\App\Models\OnlineOrder::CUSTOMER_SHIPPING_PAYMENTS as $value => $title)
                                <label class="{{ $choiceClass }} items-center">
                                    <input type="radio" name="shipping_payment" value="{{ $value }}" @checked($shippingPayment === $value) class="h-5 w-5 shrink-0 accent-brand-black">
                                    <span class="text-base text-brand-black">{{ $title }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p id="shipping-payment-help" class="mt-2 text-sm text-gray-600">Bayar langsung ke driver: ongkir dibayar saat pesanan tiba dan tidak masuk total pembayaran.</p>
                        @error('shipping_payment') <p id="shipping_payment-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                <div>
                    <label for="customer_note" class="block text-base font-medium text-brand-black">Catatan <span class="font-normal text-gray-600">(opsional)</span></label>
                    <textarea id="customer_note" name="customer_note" rows="2" maxlength="300" placeholder="Misalnya: kirim sore hari" aria-invalid="{{ $errors->has('customer_note') ? 'true' : 'false' }}" @if ($errors->has('customer_note')) aria-describedby="customer_note-error" @endif class="{{ $fieldClass }} resize-y leading-6">{{ old('customer_note', $order->customer_note) }}</textarea>
                    @error('customer_note') <p id="customer_note-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <button type="submit" class="flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:cursor-wait disabled:bg-gray-500 hover:bg-gray-800">
                        {{ $order->stage === 'awaiting_customer' ? 'Kirim data pesanan' : ($requestsChange ? 'Kirim permintaan perubahan' : 'Simpan perubahan') }}
                    </button>
                    @if ($requestsChange)
                        <p class="mt-2 text-sm leading-6 text-gray-700">Pesanan sudah diproses, jadi perubahan akan ditinjau toko dulu sebelum dipakai.</p>
                    @endif
                    <p data-order-feedback role="status" aria-live="polite" class="mt-2 min-h-5 text-sm text-gray-600"></p>
                    @if ($order->stage !== 'awaiting_customer')
                        <a href="{{ route('orders.customer.show', $token) }}" class="mt-1 inline-flex min-h-11 items-center text-base text-gray-700 underline underline-offset-4">Batal, kembali ke status</a>
                    @endif
                </div>
            </form>
        @else

            {{-- ADM-01: also right after a website checkout (draft), when the customer is already in the WhatsApp chat. --}}
            @if ($order->fulfillment === 'local_delivery' && ! $order->location_url && $order->stepIndex() < $order->stepIndex('shipped') && $order->stage !== 'cancelled' && ! $locationUrl && \App\Models\StoreInfo::usesTestWhatsapp())
                <p class="mt-6 border border-dashed border-gray-300 p-3 text-xs leading-5 text-gray-600" data-location-test-note>Mode uji: tombol Kirim Sharelok tampil di website live. Di sini WhatsApp toko sengaja tidak aktif.</p>
            @endif
            @if ($locationUrl && $order->stepIndex() < $order->stepIndex('shipped') && $order->stage !== 'cancelled')
                <section class="mt-6 border-l-4 border-brand-gold bg-[#FAF8F3] p-4" aria-labelledby="location-title">
                    <h2 id="location-title" class="text-base font-semibold text-brand-black">Kirim Sharelok</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-700">Supaya kurir tepat sampai ke lokasi Anda.</p>
                    <a href="{{ $locationUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 flex min-h-14 w-full items-center justify-center bg-[#0D3F33] px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-[#0a2f26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">Kirim Sharelok</a>
                </section>
            @endif

            <section class="mt-8" aria-labelledby="timeline-title">
                <h2 id="timeline-title" class="font-mayluxa text-2xl text-brand-black">Perjalanan pesanan</h2>
                <div class="mt-5"><x-order-timeline :items="$timeline" /></div>
            </section>

            @if ($order->hasCustomerDetails())
                <section class="mt-8 border-t border-gray-200 pt-6" aria-labelledby="recipient-title">
                    <h2 id="recipient-title" class="text-base font-semibold text-brand-black">Data penerima</h2>
                    <dl class="mt-3 space-y-2 text-base">
                        <div><dt class="text-sm text-gray-600">Nama</dt><dd class="break-words">{{ $order->customer_name }}</dd></div>
                        <div><dt class="text-sm text-gray-600">Nomor HP</dt><dd>{{ $order->customer_phone }}</dd></div>
                        <div><dt class="text-sm text-gray-600">Cara menerima</dt><dd>{{ ($order->source === 'website' && $order->created_by === null ? (config('orders.checkout_deliveries')[$order->fulfillment]['label'] ?? null) : null) ?? \App\Models\OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? '-' }}</dd></div>
                        @if ($order->address)<div><dt class="text-sm text-gray-600">Alamat</dt><dd class="break-words">{{ implode(', ', array_filter([$order->address, $order->subdistrict, $order->district])) }}{{ $order->postcode ? ' '.$order->postcode : '' }}</dd></div>@endif
                        <div><dt class="text-sm text-gray-600">Paperbag</dt><dd>{{ \App\Models\OnlineOrder::PACKAGING[$order->packaging] ?? '-' }}</dd></div>
                        @if ($order->payment_preference)<div><dt class="text-sm text-gray-600">Pembayaran</dt><dd>{{ config('orders.payment_preferences')[$order->payment_preference] ?? $order->payment_preference }}</dd></div>@endif
                        @if ($order->fulfillment === 'local_delivery' && $order->shipping_payer)<div><dt class="text-sm text-gray-600">Metode pembayaran ongkir</dt><dd>{{ \App\Models\OnlineOrder::CUSTOMER_SHIPPING_PAYMENTS[$order->shipping_payer] ?? 'Gratis ongkir' }}</dd></div>@endif
                        @if ($order->customer_note)<div><dt class="text-sm text-gray-600">Catatan</dt><dd class="break-words">{{ $order->customer_note }}</dd></div>@endif
                    </dl>
                    @if ($pendingChange)
                        <p class="mt-4 border-l-4 border-brand-gold bg-[#FAF8F3] p-3 text-sm leading-6 text-gray-800">Permintaan perubahan Anda sedang ditinjau toko.</p>
                    @elseif ($canEdit)
                        <a href="{{ route('orders.customer.show', ['token' => $token, 'ubah' => 1]) }}" class="mt-4 inline-flex min-h-12 items-center border border-brand-black px-5 text-sm font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">{{ $requestsChange ? 'Ajukan perubahan' : 'Ubah data' }}</a>
                    @elseif ($order->lifecycle === 'draft')
                        <p class="mt-4 text-sm leading-6 text-gray-600">Ada yang perlu diubah? Sampaikan lewat WhatsApp saat konfirmasi pesanan.</p>
                    @else
                        <p class="mt-4 text-sm leading-6 text-gray-600">Data sudah dikunci setelah pembayaran dikonfirmasi. Untuk perubahan, hubungi kami lewat WhatsApp.</p>
                    @endif
                </section>
            @endif
        @endif

        {{-- ORD-04: a way back after reading the status (also in the header). --}}
        <nav aria-label="Lanjut dari halaman ini" class="mt-10 grid gap-3 sm:grid-cols-2" data-status-nav>
            <a href="{{ route('products.index') }}" class="flex min-h-12 items-center justify-center border border-brand-black px-4 text-sm font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100">Kembali ke Katalog</a>
            <a href="{{ route('orders.customer.mine') }}" class="flex min-h-12 items-center justify-center border border-gray-300 px-4 text-sm font-semibold uppercase tracking-widest text-brand-black [touch-action:manipulation] active:bg-gray-100">Pesanan Saya</a>
        </nav>

        @if ($whatsappUrl)
            <p class="mt-6 text-sm text-gray-600">Ada pertanyaan? <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 text-brand-black underline underline-offset-4" data-whatsapp-button><x-icon name="whatsapp" class="text-[1.2rem]" /> Chat Qammaris di WhatsApp</a></p>
        @endif
    </div>
</section>
@endsection

@unless ($editing)
@push('scripts')
<script>
// ORD-04: open WhatsApp once right after checkout (flash only); reloads and Back keep the page without reopening it.
(() => {
    const link = document.querySelector('[data-checkout-whatsapp][data-auto-open]');
    if (link) window.setTimeout(() => window.location.assign(link.href), 600);
})();
// ORD-13: show the chosen payment way only.
(() => {
    const form = document.querySelector('[data-pay-form]');
    if (!form) return;
    const sync = () => {
        const choice = form.querySelector('[data-pay-choice]:checked')?.value;
        form.querySelectorAll('[data-pay-panel]').forEach((panel) => { panel.hidden = panel.dataset.payPanel !== choice; });
    };
    form.querySelectorAll('[data-pay-choice]').forEach((input) => input.addEventListener('change', sync));
    sync();
})();
// ORD-04: copy an official account number.
document.querySelectorAll('[data-copy-account]').forEach((button) => button.addEventListener('click', async () => {
    const feedback = button.closest('[data-copy-box]')?.querySelector('[data-copy-status]') ?? document.querySelector('[data-copy-account-feedback]');
    try {
        await navigator.clipboard.writeText(button.dataset.copyAccount);
        feedback.textContent = button.dataset.copyDone ?? 'Nomor rekening disalin.';
    } catch {
        feedback.textContent = 'Salin otomatis tidak tersedia. Salin manual: ' + button.dataset.copyAccount;
    }
}));
// ORD-07: copy the order message where no store chat is available; the selected text is the fallback.
document.querySelector('[data-copy-checkout]')?.addEventListener('click', async () => {
    const field = document.querySelector('[data-checkout-message]');
    const feedback = document.querySelector('[data-copy-feedback]');
    field.focus();
    field.select();
    try {
        await navigator.clipboard.writeText(field.value);
        feedback.textContent = 'Pesan disalin.';
    } catch {
        feedback.textContent = document.execCommand('copy') ? 'Pesan disalin.' : 'Teks sudah dipilih. Salin secara manual.';
    }
});
document.querySelector('[data-received-form]')?.addEventListener('submit', (event) => {
    const button = event.currentTarget.querySelector('button');
    button.disabled = true;
    button.textContent = 'Menyimpan…';
});
</script>
@endpush
@endunless

@if ($editing)
@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-order-form]');
    if (!form) return;
    const address = form.querySelector('#address');
    const label = form.querySelector('[data-address-label]');
    const optional = form.querySelector('[data-address-optional]');
    const help = form.querySelector('[data-address-help]');
    const sync = () => {
        const choice = form.querySelector('[data-fulfillment]:checked')?.value ?? '';
        form.querySelectorAll('[data-show-for]').forEach((block) => {
            block.hidden = !block.dataset.showFor.split(' ').includes(choice);
        });
        const intercity = choice === 'intercity';
        address.required = intercity;
        label.textContent = intercity ? 'Alamat lengkap' : 'Alamat / patokan';
        optional.hidden = intercity;
        help.textContent = intercity
            ? 'Jalan, nomor rumah, dan kota.'
            : 'Cukup patokan. Lokasi tepat lewat Sharelok.';
    };
    form.querySelectorAll('[data-fulfillment]').forEach((input) => input.addEventListener('change', sync));
    sync();

    const submit = form.querySelector('button[type="submit"]');
    const feedback = form.querySelector('[data-order-feedback]');
    const initialLabel = submit.textContent;
    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.textContent = 'Mengirim…';
        feedback.textContent = 'Menyimpan data pesanan…';
    });
    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        submit.textContent = initialLabel;
        feedback.textContent = '';
    });
    document.querySelector('[data-error-summary]')?.focus();
})();
</script>
@endpush
@endif
