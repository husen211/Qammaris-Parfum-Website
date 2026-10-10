@extends('layouts.app')
@section('title', 'Checkout - Qammaris Perfumes')
@section('robots', 'noindex,nofollow')
@push('meta')
    <meta name="referrer" content="no-referrer">
@endpush

@php
    $fieldClass = 'mt-2 min-h-12 w-full border border-gray-300 bg-white px-3 py-3 text-base text-brand-black focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black aria-[invalid=true]:border-red-700';
    $choiceClass = 'flex min-h-14 cursor-pointer items-start gap-3 border border-gray-300 bg-white p-4 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] active:bg-gray-50 has-[:checked]:border-brand-black has-[:checked]:bg-[#FAF8F3] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-black';
    $delivery = old('delivery');
    $isDelivery = in_array($delivery, ['local_delivery', 'intercity'], true);
    $canSubmit = $whatsappAvailable || $savesOrder;
    $submitLabel = match (true) {
        $savesOrder && $whatsappAvailable => 'Pesan & lanjut ke WhatsApp',
        $savesOrder => 'Pesan sekarang',
        $whatsappAvailable => 'Lanjut ke WhatsApp',
        default => 'Kontak belum tersedia',
    };
@endphp

@section('content')
<section class="min-h-screen bg-white px-4 pb-16 pt-28 md:pt-32" aria-labelledby="checkout-title">
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('cart.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm text-gray-600 hover:text-brand-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">← Kembali ke keranjang</a>
        <h1 id="checkout-title" class="mt-4 font-mayluxa text-3xl text-brand-black md:text-4xl">Checkout</h1>
        <p class="mt-3 max-w-xl text-sm leading-6 text-gray-600">
            {{ $savesOrder
                ? 'Lengkapi data penerima. Pesanan disimpan dengan nomor pesanan, lalu WhatsApp terbuka untuk konfirmasi stok, ongkir, dan pembayaran.'
                : 'Lengkapi data penerima. Rincian produk dan alamat akan disiapkan untuk dikirim ke WhatsApp Qammaris.' }}
        </p>
        @if (session('error') || $errors->any())
            <div role="alert" class="mt-6 border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ session('error') ?? 'Periksa data penerima yang ditandai di bawah.' }}
            </div>
        @endif
        <form action="{{ route('cart.checkout') }}" method="POST" data-order-checkout class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-12">
            @csrf
            <input type="hidden" name="checkout_quote" value="{{ $checkoutQuote }}">
            @if ($savesOrder)
                <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
            @endif
            <section aria-labelledby="recipient-title" class="min-w-0">
                <h2 id="recipient-title" class="font-mayluxa text-2xl text-brand-black">Data penerima</h2>
                <p class="mt-2 text-xs text-gray-500">{{ $savesOrder ? 'Nama dan nomor HP wajib diisi. Alamat hanya untuk pengiriman.' : 'Nama, nomor HP, dan alamat wajib diisi.' }}</p>
                <div class="mt-6 space-y-5">
                    @foreach (['customer_name' => ['Nama penerima', 'name', 'text', 100], 'customer_phone' => ['Nomor HP / WhatsApp', 'tel', 'tel', 25]] as $field => [$label, $autocomplete, $type, $maxlength])
                        <div>
                            <label for="{{ $field }}" class="block text-sm font-medium text-brand-black">{{ $label }} <span aria-hidden="true">*</span></label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" required maxlength="{{ $maxlength }}" autocomplete="{{ $autocomplete }}" @if ($type === 'tel') inputmode="tel" aria-describedby="phone-help{{ $errors->has($field) ? ' '.$field.'-error' : '' }}" @elseif ($errors->has($field)) aria-describedby="{{ $field }}-error" @endif aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" class="{{ $fieldClass }}">
                            @if ($type === 'tel') <p id="phone-help" class="mt-2 text-xs text-gray-500">Contoh format: 08… atau +62…</p> @endif
                            @error($field) <p id="{{ $field }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    @if ($savesOrder)
                        <fieldset @if ($errors->has('delivery')) aria-describedby="delivery-error" @endif>
                            <legend class="text-sm font-medium text-brand-black">Cara pengiriman <span aria-hidden="true">*</span></legend>
                            <div class="mt-3 space-y-3">
                                @foreach (config('orders.checkout_deliveries') as $value => $option)
                                    <label class="{{ $choiceClass }}">
                                        <input type="radio" name="delivery" value="{{ $value }}" @checked($delivery === $value) required class="mt-1 h-5 w-5 shrink-0 accent-brand-black" data-delivery>
                                        <span><span class="block text-base font-medium text-brand-black">{{ $option['label'] }}</span><span class="mt-0.5 block text-sm leading-5 text-gray-600">{{ $option['help'] }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('delivery') <p id="delivery-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </fieldset>
                    @endif

                    <div @if ($savesOrder) data-delivery-only @if (! $isDelivery) hidden @endif @endif class="space-y-5">
                        <div>
                            <label for="customer_address" class="block text-sm font-medium text-brand-black">Alamat lengkap <span aria-hidden="true">*</span></label>
                            <textarea id="customer_address" name="customer_address" rows="4" @if (! $savesOrder || $isDelivery) required @endif minlength="{{ $savesOrder ? 10 : 15 }}" maxlength="500" autocomplete="street-address" aria-describedby="address-help{{ $errors->has('customer_address') ? ' customer_address-error' : '' }}" aria-invalid="{{ $errors->has('customer_address') ? 'true' : 'false' }}" class="mt-2 w-full resize-y border border-gray-300 px-3 py-3 text-base leading-6 focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black">{{ old('customer_address') }}</textarea>
                            <p id="address-help" class="mt-2 text-xs leading-5 text-gray-500">{{ $savesOrder ? 'Isi jalan, nomor rumah, dan patokan. Untuk Kota Palu, kirim juga Sharelok di WhatsApp.' : 'Isi jalan, nomor rumah, kelurahan, kecamatan, kota/kabupaten, dan provinsi.' }}</p>
                            @error('customer_address') <p id="customer_address-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        @if ($savesOrder)
                            <div class="grid gap-5 sm:grid-cols-2">
                                @foreach (['subdistrict' => ['Kelurahan', 'address-level4'], 'district' => ['Kecamatan', 'address-level3']] as $field => [$label, $autocomplete])
                                    <div>
                                        <label for="{{ $field }}" class="block text-sm font-medium text-brand-black">{{ $label }} <span class="font-normal text-gray-500">(opsional)</span></label>
                                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="80" autocomplete="{{ $autocomplete }}" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @error($field) aria-describedby="{{ $field }}-error" @enderror class="{{ $fieldClass }}">
                                        @error($field) <p id="{{ $field }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div>
                            <label for="customer_postcode" class="block text-sm font-medium text-brand-black">Kode pos <span class="font-normal text-gray-500">(opsional)</span></label>
                            <input id="customer_postcode" name="customer_postcode" value="{{ old('customer_postcode') }}" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="postal-code" aria-invalid="{{ $errors->has('customer_postcode') ? 'true' : 'false' }}" @error('customer_postcode') aria-describedby="customer_postcode-error" @enderror class="mt-2 min-h-12 w-full border border-gray-300 px-3 py-3 text-base focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black sm:max-w-40">
                            @error('customer_postcode') <p id="customer_postcode-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($savesOrder)
                        <fieldset @if ($errors->has('packaging')) aria-describedby="packaging-error" @endif>
                            <legend class="text-sm font-medium text-brand-black">Paperbag <span aria-hidden="true">*</span></legend>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                @foreach (\App\Models\OnlineOrder::PACKAGING as $value => $title)
                                    <label class="{{ $choiceClass }} items-center">
                                        <input type="radio" name="packaging" value="{{ $value }}" @checked(old('packaging') === $value) required class="h-5 w-5 shrink-0 accent-brand-black">
                                        <span class="text-base text-brand-black">{{ $title }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('packaging') <p id="packaging-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </fieldset>
                        <fieldset aria-describedby="payment-help{{ $errors->has('payment_preference') ? ' payment_preference-error' : '' }}">
                            <legend class="text-sm font-medium text-brand-black">Pembayaran <span aria-hidden="true">*</span></legend>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                @foreach (config('orders.payment_preferences') as $value => $title)
                                    <label class="{{ $choiceClass }} items-center">
                                        <input type="radio" name="payment_preference" value="{{ $value }}" @checked(old('payment_preference') === $value) required class="h-5 w-5 shrink-0 accent-brand-black">
                                        <span class="text-base text-brand-black">{{ $title }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="payment-help" class="mt-2 text-xs leading-5 text-gray-500">Bayar setelah admin mengonfirmasi total dan ongkir di WhatsApp.</p>
                            @error('payment_preference') <p id="payment_preference-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                        </fieldset>
                    @endif

                    <div>
                        <label for="customer_note" class="block text-sm font-medium text-brand-black">Catatan pesanan <span class="font-normal text-gray-500">(opsional)</span></label>
                        <textarea id="customer_note" name="customer_note" rows="2" maxlength="300" aria-invalid="{{ $errors->has('customer_note') ? 'true' : 'false' }}" @error('customer_note') aria-describedby="customer_note-error" @enderror class="mt-2 w-full resize-y border border-gray-300 px-3 py-3 text-base focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black">{{ old('customer_note') }}</textarea>
                        @error('customer_note') <p id="customer_note-error" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
            <aside class="min-w-0 border border-gray-200 bg-[#FAF8F3] p-5 lg:sticky lg:top-28 lg:p-6" aria-labelledby="order-summary-title">
                <h2 id="order-summary-title" class="font-mayluxa text-2xl text-brand-black">Pesanan Anda</h2>
                <div class="mt-5 divide-y divide-gray-200">
                    @foreach ($items as $item)
                        <article class="flex gap-3 py-4 first:pt-0">
                            <img src="{{ $item['image'] }}" alt="" width="64" height="80" class="h-20 w-16 shrink-0 bg-white object-contain" loading="lazy" decoding="async">
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-medium leading-5 text-brand-black">{{ $item['product_name'] }}</p>
                                <p class="mt-1 text-xs text-gray-600">{{ $item['volume'] }} ml · {{ $item['quantity'] }} × {{ $item['formatted_unit_price'] }}</p>
                                <p class="mt-1 text-xs text-emerald-700">{{ $item['availability_label'] }}</p>
                                <p class="mt-2 text-sm font-semibold">{{ $item['formatted_price'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-5">
                    <span class="text-sm text-gray-600">Subtotal produk</span><strong class="text-xl tabular-nums">{{ format_rupiah($subtotal) }}</strong>
                </div>
                @if ($savesOrder)
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-700">
                        <span>Ongkir</span><span>Menunggu konfirmasi staf</span>
                    </div>
                @else
                    <p class="mt-3 text-xs leading-5 text-gray-600">Ongkir dan pembayaran dilanjutkan di WhatsApp.</p>
                @endif
                <button type="submit" @disabled(! $canSubmit) class="mt-6 flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-xs font-semibold uppercase tracking-widest text-white [touch-action:manipulation] hover:bg-gray-800 active:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600">{{ $submitLabel }}</button>
                @if ($savesOrder)
                    <p class="mt-3 text-xs leading-5 text-gray-600">Pesanan diproses setelah admin mengonfirmasi lewat WhatsApp. Data penerima disimpan hanya untuk mengurus pesanan ini dan hanya dapat dilihat tim Qammaris.</p>
                @else
                    <p class="mt-3 text-xs leading-5 text-gray-600">Data penerima diteruskan ke WhatsApp. Setelah terbuka, tekan Kirim untuk mengirim pesanan.</p>
                @endif
                <p data-order-feedback role="status" aria-live="polite" class="mt-2 min-h-5 text-xs text-gray-600"></p>
            </aside>
        </form>
    </div>
</section>
@endsection
@push('scripts')
<script>
const orderForm = document.querySelector('[data-order-checkout]');
const orderSubmit = orderForm?.querySelector('button[type="submit"]');
const orderFeedback = document.querySelector('[data-order-feedback]');
const initialOrderLabel = orderSubmit?.textContent;
let orderRetryTimer;
// ORD-04: the address block belongs to delivery only; pickup sends no address.
const deliveryOnly = orderForm?.querySelector('[data-delivery-only]');
const syncDelivery = () => {
    if (!deliveryOnly) return;
    const choice = orderForm.querySelector('[data-delivery]:checked')?.value ?? '';
    const delivers = choice === 'local_delivery' || choice === 'intercity';
    deliveryOnly.hidden = !delivers;
    orderForm.querySelector('#customer_address').required = delivers;
};
orderForm?.querySelectorAll('[data-delivery]').forEach((input) => input.addEventListener('change', syncDelivery));
syncDelivery();
orderForm?.addEventListener('submit', () => {
    // A repeated tap resends the same checkout key; the server answers with the same order.
    orderSubmit.disabled = true;
    orderSubmit.textContent = @json($savesOrder ? 'Menyimpan pesanan…' : 'Membuka WhatsApp…');
    orderFeedback.textContent = 'Menyiapkan rincian pesanan…';
    orderRetryTimer = window.setTimeout(() => {
        orderSubmit.disabled = false;
        orderSubmit.textContent = initialOrderLabel;
        orderFeedback.textContent = @json($savesOrder ? 'Belum ada balasan. Periksa koneksi, lalu coba lagi; pesanan tidak akan tercatat dua kali.' : 'WhatsApp belum terbuka? Periksa koneksi, lalu coba lagi.');
    }, 15000);
});
window.addEventListener('pageshow', () => {
    window.clearTimeout(orderRetryTimer);
    if (orderSubmit) {
        orderSubmit.disabled = {{ $canSubmit ? 'false' : 'true' }};
        orderSubmit.textContent = initialOrderLabel;
        orderFeedback.textContent = '';
    }
});
</script>
@endpush
