@extends('layouts.admin')

{{--
    ORD-27 (Owner 2026-11-17) "Ubah pesanan": swap a sold-out product, change quantities, add or remove products, and
    change how the order is delivered, on one page. The summary shows what the change does to the total and what is
    left to pay or goes back; the server computes it again on save. Display fields (name/price) only re-render rows.
--}}
@use('App\Models\OnlineOrder')
@use('App\Support\Rupiah')
@section('content')
@php
    $card = 'rounded-xl border border-gray-200 bg-white p-4 sm:p-5';
    $field = 'mt-1 min-h-12 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $secondary = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:text-gray-400';
    $rows = old('items') ? array_values(old('items')) : $order->items->map(fn ($item) => [
        'line_id' => $item->line_id, 'variant_id' => $item->variant_id, 'quantity' => $item->quantity,
        'name' => $item->product_name, 'volume' => $item->volume, 'price' => (int) round((float) $item->unit_price),
    ])->all();
    $fulfillment = old('fulfillment', $order->fulfillment ?? 'pickup');
    $fee = old('shipping_fee', $order->shipping_fee !== null ? (string) (int) round((float) $order->shipping_fee) : '');
    $payer = old('shipping_payer', $order->shipping_payer ?? 'added_to_transfer');
    $config = [
        'packaging' => (int) round(Rupiah::minorUnits($order->packagingFee()) / 100),
        'adjustments' => (int) round($order->approvedAdjustmentCents() / 100),
        'paid' => (int) round($paidCents / 100),
        'before' => (int) round($totalCents / 100),
        'quoteUrl' => route('admin.orders.v2.change.quotes', $order),
    ];
@endphp
<div class="mx-auto max-w-3xl space-y-4">
    <div>
        <a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-gray-700 hover:text-black">← {{ $order->code }}</a>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Ubah pesanan</h1>
        <p class="mt-1 text-sm text-gray-600">Ganti produk, jumlah, atau cara pengiriman. Selisih harga dihitung otomatis.</p>
    </div>

    @if (session('change_error') || $errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            @if (session('change_error'))<p class="font-semibold">{{ session('change_error') }}</p>@endif
            @if ($errors->any())<ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
    @endif

    @unless ($changeable)
        <div class="{{ $card }} text-sm text-gray-700">
            <p class="font-semibold text-gray-900">Pesanan ini tidak dapat diubah lagi.</p>
            <p class="mt-1">{{ $order->handover_status !== 'pending' ? 'Barang sudah diserahkan ke kurir atau customer.' : ($order->biteshipBusy() ? 'Kurir Biteship masih dipesan. Batalkan kurir dulu di bagian Pengiriman.' : 'Pesanan sudah selesai atau dibatalkan.') }}</p>
            <a href="{{ route('admin.orders.show', $order) }}" class="{{ $secondary }} mt-3">Kembali ke pesanan</a>
        </div>
    @else
    <form method="POST" action="{{ route('admin.orders.v2.change', $order) }}" class="space-y-4" data-change-form data-config='@json($config)'>
        @csrf
        <input type="hidden" name="revision" value="{{ $order->revision }}">

        <section class="{{ $card }}" aria-labelledby="products-title">
            <h2 id="products-title" class="text-base font-semibold text-gray-900">1. Produk</h2>
            <ul class="mt-2 divide-y divide-gray-100" data-lines aria-label="Produk di pesanan">
                @foreach ($rows as $i => $row)
                    <li class="py-3" data-line>
                        @if (! empty($row['line_id']))<input type="hidden" name="items[{{ $i }}][line_id]" value="{{ $row['line_id'] }}">@endif
                        <input type="hidden" name="items[{{ $i }}][variant_id]" value="{{ $row['variant_id'] }}" data-variant>
                        <input type="hidden" name="items[{{ $i }}][name]" value="{{ $row['name'] }}"><input type="hidden" name="items[{{ $i }}][volume]" value="{{ $row['volume'] }}"><input type="hidden" name="items[{{ $i }}][price]" value="{{ $row['price'] }}" data-price>
                        <div class="flex items-center gap-3">
                            <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-gray-900">{{ $row['name'] }}</span><span class="block text-gray-500">{{ $row['volume'] ? $row['volume'].' ml · ' : '' }}{{ format_rupiah($row['price']) }}{{ empty($row['line_id']) ? ' · baru' : '' }}</span></span>
                            <label class="sr-only" for="qty-{{ $i }}">Jumlah {{ $row['name'] }}</label>
                            <input id="qty-{{ $i }}" type="number" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] }}" min="1" max="99" inputmode="numeric" required class="min-h-11 w-16 rounded-lg border border-gray-300 px-2 text-center text-base" data-qty>
                        </div>
                        <div class="mt-1 flex gap-2">
                            <button type="button" class="min-h-11 rounded-lg px-2 text-sm font-semibold text-gray-800 hover:bg-gray-100" data-swap aria-label="Ganti {{ $row['name'] }} dengan produk lain">Ganti</button>
                            <button type="button" class="min-h-11 rounded-lg px-2 text-sm font-semibold text-red-700 hover:bg-red-50" data-remove aria-label="Hapus {{ $row['name'] }}">Hapus</button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm text-amber-800" data-lines-empty hidden>Pesanan harus berisi minimal satu produk. Cari produk di bawah.</p>
            <label for="product-search" class="mt-3 block text-sm font-medium text-gray-700">Tambah atau ganti produk</label>
            <input id="product-search" type="search" autocomplete="off" enterkeyhint="search" placeholder="Cari nama, brand, atau ukuran" aria-describedby="product-search-status"
                class="{{ $field }}" data-search-url="{{ route('admin.orders.product-search') }}">
            <p id="product-search-status" role="status" aria-live="polite" class="mt-1 min-h-5 text-xs text-gray-500"></p>
            <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 empty:hidden" data-results></ul>
            <p class="mt-1 text-xs text-gray-500">Produk lama tetap dengan harganya saat dipesan; produk baru memakai harga katalog sekarang.</p>
        </section>

        <section class="{{ $card }}" aria-labelledby="shipping-title">
            <h2 id="shipping-title" class="text-base font-semibold text-gray-900">2. Pengiriman</h2>
            <fieldset class="mt-2">
                <legend class="sr-only">Cara menerima pesanan</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (['pickup' => ['Ambil di toko', 'Tanpa ongkir'], 'local_delivery' => ['Dalam kota', 'Kurir instan (GoSend, Grab, Maxim)'], 'intercity' => ['Luar kota', 'J&T']] as $value => [$label, $hint])
                        <label class="flex min-h-14 cursor-pointer flex-col justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm has-[:checked]:border-black has-[:checked]:bg-gray-900 has-[:checked]:text-white has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2">
                            <input type="radio" name="fulfillment" value="{{ $value }}" class="sr-only" @checked($fulfillment === $value) data-fulfillment>
                            <span class="font-semibold">{{ $label }}</span><span class="text-xs opacity-80">{{ $hint }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="mt-4 space-y-3" data-delivery-fields @if ($fulfillment === 'pickup') hidden @endif>
                <label class="block text-sm font-medium text-gray-700">Alamat lengkap
                    <textarea name="address" rows="2" maxlength="500" class="{{ $field }}">{{ old('address', $order->address) }}</textarea>
                </label>
                <div class="space-y-3" data-local-fields @if ($fulfillment !== 'local_delivery') hidden @endif>
                    @include('admin.orders._pin-field', ['pinPoint' => $order->deliveryPin(), 'urlInputId' => 'change-location-url'])
                    <label for="change-location-url" class="block text-sm font-medium text-gray-700">Sharelok / link Google Maps / koordinat</label>
                    <input id="change-location-url" name="location_url" type="text" inputmode="url" autocomplete="off" maxlength="500" class="{{ $field }} !mt-1"
                        value="{{ old('location_url', $order->location_url ?: (($pin = $order->deliveryPin()) ? 'https://www.google.com/maps?q='.$pin['latitude'].','.$pin['longitude'] : '')) }}" placeholder="Tempel Sharelok, atau -0.8951, 119.8594">
                </div>
                <label class="block text-sm font-medium text-gray-700" data-intercity-fields @if ($fulfillment !== 'intercity') hidden @endif>Kode pos
                    <input name="postcode" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" value="{{ old('postcode', $order->postcode) }}" class="{{ $field }}" data-postcode>
                </label>

                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-sm font-medium text-gray-700">Ongkir</p>
                    <input type="hidden" name="shipping_option" value="{{ old('shipping_option') }}" data-option>
                    @if ($pricesShipping)
                        <button type="button" class="{{ $secondary }} mt-2 w-full sm:w-auto" data-quote>Hitung ongkir otomatis</button>
                        <p class="mt-1 min-h-5 text-xs text-gray-600" role="status" aria-live="polite" data-quote-status></p>
                        <div class="mt-1 space-y-2" data-quote-options></div>
                    @endif
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Ongkir (Rp)
                            <input name="shipping_fee" inputmode="numeric" data-rupiah-input value="{{ $fee }}" placeholder="Kosongkan bila belum tahu" class="{{ $field }}" data-fee>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Dibayar
                            <select name="shipping_payer" class="{{ $field }}" data-payer>
                                @foreach (['added_to_transfer' => 'Masuk total pesanan', 'cash_to_driver' => 'Customer bayar ke driver', 'store_free' => 'Gratis (toko tanggung)'] as $value => $label)<option value="{{ $value }}" @selected($payer === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="{{ $card }}">
            <label class="block text-sm font-medium text-gray-700">Catatan perubahan <span class="font-normal text-gray-500">(opsional)</span>
                <input name="note" maxlength="200" value="{{ old('note') }}" placeholder="Contoh: stok habis, ganti varian" class="{{ $field }}">
            </label>
        </section>

        <section class="{{ $card }}" aria-labelledby="summary-title" data-summary>
            <h2 id="summary-title" class="text-base font-semibold text-gray-900">Ringkasan</h2>
            <dl class="mt-2 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-600">Total sebelumnya</dt><dd class="tabular-nums">{{ format_rupiah(Rupiah::decimal($totalCents)) }}</dd></div>
                <div class="flex justify-between text-base font-semibold"><dt>Total baru</dt><dd class="tabular-nums" data-new-total>{{ format_rupiah(Rupiah::decimal($totalCents)) }}</dd></div>
                @if ($paidCents > 0)<div class="flex justify-between"><dt class="text-gray-600">Sudah dibayar</dt><dd class="tabular-nums">{{ format_rupiah(Rupiah::decimal($paidCents)) }}</dd></div>@endif
            </dl>
            <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900" role="status" aria-live="polite" data-difference>Tidak ada selisih.</p>
            @if ($order->preparation_status !== 'not_started')<p class="mt-2 text-xs text-gray-600" data-repack-note hidden>Produk berubah: pesanan perlu di-packing ulang.</p>@endif
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <button class="min-h-12 w-full rounded-lg bg-black px-6 text-base font-semibold text-white hover:bg-gray-800 disabled:bg-gray-400 sm:w-auto" data-busy-label="Menyimpan…">Simpan perubahan</button>
                <a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Batal</a>
            </div>
        </section>
    </form>
    @endunless
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-change-form]');
    if (!form) return;
    const config = JSON.parse(form.dataset.config);
    const lines = form.querySelector('[data-lines]');
    const search = form.querySelector('#product-search');
    const status = form.querySelector('#product-search-status');
    const results = form.querySelector('[data-results]');
    const fee = form.querySelector('[data-fee]');
    const payer = form.querySelector('[data-payer]');
    const option = form.querySelector('[data-option]');
    const rupiah = (value) => 'Rp ' + Math.round(value).toLocaleString('id-ID').replace(/,/g, '.');
    const digits = (value) => Number(String(value || '').replace(/\D/g, '')) || 0;
    const el = (tag, className, text) => { const n = document.createElement(tag); if (className) n.className = className; if (text !== undefined) n.textContent = text; return n; };
    let counter = lines.children.length;
    let changedLines = false;
    const fulfillment = () => form.querySelector('[data-fulfillment]:checked')?.value;

    const renumber = () => [...lines.children].forEach((row, i) => row.querySelectorAll('input').forEach((input) => {
        input.name = input.name.replace(/^items\[\d+\]/, `items[${i}]`);
    }));
    const refresh = () => {
        let total = config.packaging + config.adjustments;
        lines.querySelectorAll('[data-line]').forEach((row) => { total += digits(row.querySelector('[data-price]').value) * Math.max(0, digits(row.querySelector('[data-qty]').value)); });
        if (fulfillment() !== 'pickup' && payer.value === 'added_to_transfer') total += digits(fee.value);
        form.querySelector('[data-new-total]').textContent = rupiah(total);
        const diff = total - config.paid;
        form.querySelector('[data-difference]').textContent = config.paid === 0
            ? (total === config.before ? 'Total tidak berubah. Belum ada pembayaran.' : `Total berubah ${total > config.before ? 'naik' : 'turun'} ${rupiah(Math.abs(total - config.before))}. Belum ada pembayaran.`)
            : diff > 0 ? `Customer perlu bayar lagi ${rupiah(diff)}.` : diff < 0 ? `Kembalikan ke customer ${rupiah(-diff)}.` : 'Tidak ada selisih pembayaran.';
        form.querySelector('[data-lines-empty]').hidden = lines.children.length > 0;
        const repack = form.querySelector('[data-repack-note]');
        if (repack) repack.hidden = !changedLines;
    };
    const dropQuote = () => { option.value = ''; form.querySelectorAll('[data-quote-options] input').forEach((input) => { input.checked = false; }); };

    const add = (item) => {
        const existing = [...lines.querySelectorAll('[data-variant]')].find((input) => input.value === String(item.variant_id));
        if (existing) { const qty = existing.closest('li').querySelector('[data-qty]'); qty.value = Math.min(99, digits(qty.value) + 1); qty.focus(); changedLines = true; dropQuote(); refresh(); return; }
        const i = counter++;
        const row = el('li', 'py-3'); row.dataset.line = '';
        const hidden = (name, value, flag) => { const input = el('input'); input.type = 'hidden'; input.name = `items[${i}][${name}]`; input.value = value ?? ''; if (flag) input.dataset[flag] = ''; return input; };
        const top = el('div', 'flex items-center gap-3');
        const text = el('span', 'min-w-0 flex-1 text-sm');
        text.append(el('span', 'block font-semibold text-gray-900', item.name), el('span', 'block text-gray-500', `${item.volume ? item.volume + ' ml · ' : ''}${item.price} · baru`));
        const label = el('label', 'sr-only', `Jumlah ${item.name}`); label.htmlFor = `qty-${i}`;
        const qty = el('input', 'min-h-11 w-16 rounded-lg border border-gray-300 px-2 text-center text-base');
        Object.assign(qty, { id: `qty-${i}`, type: 'number', name: `items[${i}][quantity]`, value: 1, min: 1, max: 99, inputMode: 'numeric', required: true }); qty.dataset.qty = '';
        top.append(text, label, qty);
        const actions = el('div', 'mt-1 flex gap-2');
        const remove = el('button', 'min-h-11 rounded-lg px-2 text-sm font-semibold text-red-700 hover:bg-red-50', 'Hapus');
        remove.type = 'button'; remove.dataset.remove = ''; remove.setAttribute('aria-label', `Hapus ${item.name}`);
        actions.append(remove);
        row.append(hidden('variant_id', item.variant_id, 'variant'), hidden('name', item.name), hidden('volume', item.volume), hidden('price', item.price_value, 'price'), top, actions);
        lines.append(row);
        changedLines = true; dropQuote(); renumber(); refresh();
        status.textContent = `${item.name} ditambahkan.`;
        search.value = ''; results.replaceChildren();
    };

    lines.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove], [data-swap]');
        if (!button) return;
        const row = button.closest('li');
        const name = row.querySelector('.font-semibold')?.textContent;
        row.remove(); changedLines = true; dropQuote(); renumber(); refresh();
        if (button.matches('[data-swap]')) { status.textContent = `Cari pengganti ${name}.`; search.focus(); }
        else status.textContent = `${name} dihapus.`;
    });
    lines.addEventListener('input', (event) => { if (event.target.matches('[data-qty]')) { changedLines = true; dropQuote(); refresh(); } });

    let timer; let controller;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        const term = search.value.trim();
        if (term.length < 2) { results.replaceChildren(); status.textContent = ''; return; }
        timer = setTimeout(async () => {
            controller?.abort(); controller = new AbortController();
            status.textContent = 'Mencari…';
            try {
                const url = new URL(search.dataset.searchUrl, window.location.origin); url.searchParams.set('q', term);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('search failed');
                const { items } = await response.json();
                results.replaceChildren(...items.map((item) => {
                    const li = el('li');
                    const button = el('button', 'flex min-h-14 w-full items-center gap-3 px-3 py-2 text-left hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none'); button.type = 'button';
                    const img = el('img', 'h-12 w-10 shrink-0 object-contain'); img.src = item.image; img.alt = '';
                    const text = el('span', 'min-w-0 flex-1 text-sm');
                    text.append(el('span', 'block font-semibold text-gray-900', item.name), el('span', 'block text-gray-500', `${item.brand} · ${item.volume} ml · ${item.availability}`));
                    button.append(img, text, el('span', 'shrink-0 text-sm font-semibold', item.price));
                    button.addEventListener('click', () => add(item));
                    li.append(button);
                    return li;
                }));
                status.textContent = items.length ? `${items.length} produk` : 'Produk tidak ditemukan.';
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Pencarian gagal. Periksa koneksi lalu ketik ulang.';
            }
        }, 250);
    });

    const syncDelivery = () => {
        const value = fulfillment();
        form.querySelector('[data-delivery-fields]').hidden = value === 'pickup';
        form.querySelector('[data-local-fields]').hidden = value !== 'local_delivery';
        form.querySelector('[data-intercity-fields]').hidden = value !== 'intercity';
        const driver = payer.querySelector('option[value="cash_to_driver"]');
        driver.disabled = value !== 'local_delivery';
        if (driver.disabled && payer.value === 'cash_to_driver') payer.value = 'added_to_transfer';
        refresh();
    };
    form.querySelectorAll('[data-fulfillment]').forEach((radio) => radio.addEventListener('change', () => { dropQuote(); form.querySelector('[data-quote-options]')?.replaceChildren(); syncDelivery(); }));
    fee.addEventListener('input', () => { dropQuote(); refresh(); });
    payer.addEventListener('change', refresh);
    form.querySelector('#change-location-url')?.addEventListener('input', dropQuote);
    form.querySelector('[data-postcode]')?.addEventListener('input', dropQuote);

    const quoteButton = form.querySelector('[data-quote]');
    quoteButton?.addEventListener('click', async () => {
        const quoteStatus = form.querySelector('[data-quote-status]');
        const box = form.querySelector('[data-quote-options]');
        const body = new FormData();
        body.append('_token', form.querySelector('[name=_token]').value);
        [...lines.children].forEach((row, i) => row.querySelectorAll('input[name$="[line_id]"], input[name$="[variant_id]"], input[name$="[quantity]"]').forEach((input) => body.append(input.name, input.value)));
        if (fulfillment() === 'local_delivery') {
            const point = (form.querySelector('#change-location-url').value.match(/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/) || []);
            if (!point[1]) { quoteStatus.textContent = 'Pasang titik antar di peta dulu (Sharelok pendek tidak berisi koordinat).'; return; }
            body.append('mode', 'instant'); body.append('latitude', point[1]); body.append('longitude', point[2]);
        } else {
            const postcode = form.querySelector('[data-postcode]').value.trim();
            if (!/^\d{5}$/.test(postcode)) { quoteStatus.textContent = 'Isi kode pos 5 angka dulu.'; return; }
            body.append('mode', 'jnt'); body.append('postcode', postcode);
        }
        quoteButton.disabled = true; quoteStatus.textContent = 'Menghitung ongkir…'; box.replaceChildren();
        try {
            const response = await fetch(config.quoteUrl, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.options?.length) {
                quoteStatus.textContent = { outside_area: 'Titik di luar jangkauan kurir instan Palu.', no_courier: 'Tidak ada kurir yang menjangkau tujuan ini. Isi ongkir manual.', cart: 'Produk berubah. Hitung lagi.' }[data.reason] || 'Biteship belum menjawab. Coba lagi, atau isi ongkir manual.';
                return;
            }
            quoteStatus.textContent = 'Pilih salah satu:';
            box.replaceChildren(...data.options.map((item) => {
                const label = el('label', 'flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-gray-300 px-3 py-2 text-sm has-[:checked]:border-black has-[:checked]:ring-1 has-[:checked]:ring-black');
                const radio = el('input'); radio.type = 'radio'; radio.name = 'quote_choice'; radio.value = item.id;
                radio.addEventListener('change', () => { option.value = item.id; fee.value = String(item.charged); payer.value = 'added_to_transfer'; refresh(); });
                const text = el('span', 'min-w-0 flex-1');
                text.append(el('span', 'block font-semibold text-gray-900', item.label), el('span', 'block text-xs text-gray-600', [item.eta, item.discount ? `harga kurir ${rupiah(item.price)}` : null].filter(Boolean).join(' · ')));
                label.append(radio, text, el('span', 'shrink-0 font-semibold tabular-nums', rupiah(item.charged)));
                return label;
            }));
        } catch { quoteStatus.textContent = 'Koneksi terputus. Coba lagi.'; }
        finally { quoteButton.disabled = false; }
    });

    form.addEventListener('submit', (event) => {
        if (!lines.children.length) { event.preventDefault(); form.querySelector('[data-lines-empty]').hidden = false; search.focus(); }
    });
    syncDelivery();
})();
</script>
@endpush
