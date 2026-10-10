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

        {{-- ORD-04: the order exists before WhatsApp opens. Opening WhatsApp is not a confirmation; the store confirms. --}}
        @if ($order->lifecycle === 'draft')
            <section class="mt-6 border-l-4 border-brand-gold bg-[#FAF8F3] p-4" aria-labelledby="next-title" data-checkout-next>
                <h2 id="next-title" class="text-base font-semibold text-brand-black">Langkah berikutnya: kirim pesan WhatsApp</h2>
                <p class="mt-1 text-sm leading-6 text-gray-700">Nomor pesanan Anda <strong class="text-brand-black">{{ $order->code }}</strong>. Kirim pesan ini supaya admin mengonfirmasi stok, ongkir, dan pembayaran. Pesanan belum diproses sebelum dikonfirmasi admin.</p>
                @if ($checkoutWhatsappUrl)
                    <a href="{{ $checkoutWhatsappUrl }}" rel="noopener noreferrer" data-checkout-whatsapp @if (session('checkout_placed')) data-auto-open="1" @endif class="mt-3 flex min-h-14 w-full items-center justify-center bg-[#0D3F33] px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-[#0a2f26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">{{ session('checkout_placed') ? 'Buka WhatsApp' : 'Buka WhatsApp lagi' }}</a>
                    <p class="mt-2 text-sm leading-6 text-gray-600">WhatsApp tidak terbuka atau pesan belum terkirim? Tekan tombol di atas lagi. Pesanan Anda tetap tersimpan.</p>
                @else
                    <p class="mt-2 text-sm leading-6 text-gray-700">Kontak WhatsApp toko belum tersedia. Pesanan Anda tetap tersimpan; simpan halaman ini dan hubungi toko.</p>
                @endif
                @if ($order->fulfillment === 'local_delivery')
                    <p class="mt-3 text-sm leading-6 text-gray-600">Pengiriman Kota Palu: kirim juga Sharelok di chat yang sama supaya kurir tepat sampai.</p>
                @endif
                <p class="mt-3 text-sm leading-6 text-gray-600">Simpan halaman ini untuk melihat status pesanan.</p>
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
            @if ($order->shipping_payer === 'added_to_transfer' && $order->shipping_fee !== null)
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700">
                    <span>Ongkir</span><span class="tabular-nums">{{ format_rupiah($order->shipping_fee) }}</span>
                </div>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-gray-600">Total pembayaran</span>
                    <strong class="text-lg tabular-nums">{{ format_rupiah($order->customerTotal()) }}</strong>
                </div>
            @elseif ($order->lifecycle === 'draft' && $order->fulfillment !== 'pickup')
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700">
                    <span>Ongkir</span><span>Menunggu konfirmasi staf</span>
                </div>
            @else
                <p class="mt-2 text-sm leading-6 text-gray-600">Ongkir dan pembayaran dikonfirmasi admin.</p>
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
                            'local_delivery' => ['Kirim dalam Kota Palu', 'Diantar kurir. Lokasi lewat Sharelok.'],
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
            @if ($order->stage === 'shipped' && $order->fulfillment !== 'pickup' && ! ($order->isV2() && $order->delivery_status === 'delivered'))
                <form method="POST" action="{{ route('orders.customer.received', $token) }}" class="mt-6 border-l-4 border-brand-gold bg-[#FAF8F3] p-4" data-received-form>
                    @csrf
                    <p class="text-base font-semibold text-brand-black">Pesanan sedang dikirim</p>
                    <p class="mt-1 text-sm leading-6 text-gray-700">Sudah sampai di tangan Anda? Tekan tombol di bawah supaya kami tahu.</p>
                    <button type="submit" class="mt-3 flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-sm font-semibold uppercase tracking-widest text-white [touch-action:manipulation] active:bg-gray-700 hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:bg-gray-500">Pesanan sudah saya terima</button>
                </form>
            @endif

            @if ($locationUrl && $order->lifecycle !== 'draft' && $order->stepIndex() < $order->stepIndex('shipped') && $order->stage !== 'cancelled')
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

        @if ($whatsappUrl)
            <p class="mt-10 text-sm text-gray-600">Ada pertanyaan? <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center text-brand-black underline underline-offset-4">Chat Qammaris di WhatsApp</a></p>
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
