@extends('layouts.admin')

@section('content')
@php($card = 'rounded-xl border border-gray-200 bg-white p-4 sm:p-5')
@php($field = 'mt-1 min-h-12 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10')
<div class="mx-auto max-w-3xl space-y-4">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pesanan Online</a>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Buat pesanan</h1>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Periksa kembali:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ORD-03: product -> customer -> recipient data -> create. The button sits at the end of the form, never sticky. --}}
    <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-4" data-order-create>
        @csrf
        {{-- One token per opened form: a double tap or retry returns the first order instead of creating another. --}}
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">

        <section class="{{ $card }}" aria-labelledby="products-title">
            <h2 id="products-title" class="text-base font-semibold text-gray-900">1. Produk</h2>
            <label for="product-search" class="sr-only">Cari produk</label>
            <input id="product-search" type="search" autocomplete="off" enterkeyhint="search" placeholder="Cari nama, brand, atau ukuran" aria-describedby="product-search-status"
                class="{{ $field }}" data-search-url="{{ route('admin.orders.product-search') }}">
            <p id="product-search-status" role="status" aria-live="polite" class="mt-1 min-h-5 text-xs text-gray-500"></p>
            <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 empty:hidden" data-results></ul>
            <ul class="mt-2 divide-y divide-gray-100" data-selected aria-label="Produk dipilih">
                @foreach ($selected as $index => $item)
                    <li class="flex items-center gap-3 py-3" data-variant="{{ $item['variant_id'] }}">
                        <input type="hidden" name="items[{{ $index }}][variant_id]" value="{{ $item['variant_id'] }}">
                        <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-gray-900">{{ $item['name'] }}</span><span class="block text-gray-500">{{ $item['volume'] }} ml · {{ $item['price'] }}</span></span>
                        <label class="sr-only" for="qty-{{ $index }}">Jumlah {{ $item['name'] }}</label>
                        <input id="qty-{{ $index }}" type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}" min="1" max="99" inputmode="numeric" class="min-h-11 w-16 rounded-lg border border-gray-300 px-2 text-center text-base">
                        <button type="button" class="min-h-11 rounded-lg px-2 text-sm font-semibold text-red-700 hover:bg-red-50" data-remove aria-label="Hapus {{ $item['name'] }}">Hapus</button>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm text-gray-500" data-empty-selection @if ($selected->isNotEmpty()) hidden @endif>Belum ada produk.</p>
        </section>

        <section class="{{ $card }}" aria-labelledby="customer-title" data-customer-picker data-search-url="{{ route('admin.orders.customer-search') }}">
            <h2 id="customer-title" class="text-base font-semibold text-gray-900">2. Pelanggan</h2>
            <input type="hidden" name="customer_id" value="{{ old('customer_id') }}" data-customer-id>
            <div data-customer-chosen @unless (old('customer_id')) hidden @endunless class="mt-2 flex items-center justify-between gap-2 rounded-lg border border-black p-3 text-sm">
                <span data-customer-label>Pelanggan dipilih</span>
                <button type="button" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100" data-customer-clear>Ganti</button>
            </div>
            <div data-customer-search-box @if (old('customer_id')) hidden @endif>
                <label for="customer-search" class="sr-only">Cari pelanggan lama</label>
                <input id="customer-search" type="search" autocomplete="off" enterkeyhint="search" placeholder="Pelanggan lama: nama atau nomor" aria-describedby="customer-search-status" class="{{ $field }}">
                <p id="customer-search-status" role="status" aria-live="polite" class="mt-1 min-h-5 text-xs text-gray-500">Pelanggan baru? Lewati saja.</p>
                <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 empty:hidden" data-customer-results></ul>
            </div>
            <fieldset class="mt-2" data-address-choices hidden>
                <legend class="text-sm font-medium text-gray-700">Alamat tersimpan</legend>
                <div class="mt-1 space-y-2" data-address-list></div>
            </fieldset>
        </section>

        <section class="{{ $card }}" aria-labelledby="recipient-title">
            <h2 id="recipient-title" class="text-base font-semibold text-gray-900">3. Data penerima</h2>
            <div class="mt-2 grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="recipient-title">
                @foreach (['1' => 'Isi sekarang', '0' => 'Customer isi via link'] as $value => $label)
                    <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border border-gray-300 px-2 text-center text-sm font-semibold has-[:checked]:border-black has-[:checked]:bg-black has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-black/30">
                        <input type="radio" name="fill_customer" value="{{ $value }}" class="sr-only" @checked(old('fill_customer', '0') === (string) $value) data-fill-choice> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="mt-4 space-y-4" data-fill-fields @unless (old('fill_customer') === '1') hidden @endunless>
                @include('admin.orders._customer-fields')
                <label class="flex min-h-11 items-center gap-3 text-sm text-gray-800" data-new-customer @if (old('customer_id')) hidden @endif>
                    <input type="checkbox" name="new_customer" value="1" @checked(old('new_customer')) class="h-5 w-5"> Simpan sebagai pelanggan langganan
                </label>
            </div>
        </section>

        <details class="{{ $card }}" @if (old('source') && old('source') !== 'whatsapp') open @endif>
            <summary class="flex min-h-11 cursor-pointer items-center text-sm font-semibold text-gray-700">Opsi lanjutan</summary>
            <label for="source" class="mt-2 block text-sm font-medium text-gray-700">Pesanan dari</label>
            <select id="source" name="source" class="{{ $field }}">
                @foreach (['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'manual' => 'Lainnya'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('source', 'whatsapp') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </details>

        <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:items-center">
            <button type="submit" class="min-h-12 w-full rounded-lg bg-black px-6 text-base font-semibold text-white hover:bg-gray-800 disabled:bg-gray-400 sm:w-auto" data-busy-label="Membuat pesanan…">Buat pesanan</button>
            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-order-create]');
    const search = form.querySelector('#product-search');
    const status = form.querySelector('#product-search-status');
    const results = form.querySelector('[data-results]');
    const selected = form.querySelector('[data-selected]');
    const empty = form.querySelector('[data-empty-selection]');
    let counter = selected.children.length;
    let timer;
    let controller;

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const refreshEmpty = () => { empty.hidden = selected.children.length > 0; };

    const add = (item) => {
        const existing = selected.querySelector(`[data-variant="${item.variant_id}"] input[type="number"]`);
        if (existing) { existing.value = Math.min(99, Number(existing.value || 1) + 1); existing.focus(); return; }
        const index = counter++;
        const row = el('li', 'flex items-center gap-3 py-3');
        row.dataset.variant = item.variant_id;
        const hidden = el('input'); hidden.type = 'hidden'; hidden.name = `items[${index}][variant_id]`; hidden.value = item.variant_id;
        const text = el('span', 'min-w-0 flex-1 text-sm');
        text.append(el('span', 'block font-semibold text-gray-900', item.name), el('span', 'block text-gray-500', `${item.volume} ml · ${item.price}`));
        const qtyLabel = el('label', 'sr-only', `Jumlah ${item.name}`); qtyLabel.htmlFor = `qty-${index}`;
        const qty = el('input', 'min-h-11 w-16 rounded-lg border border-gray-300 px-2 text-center text-base');
        Object.assign(qty, { id: `qty-${index}`, type: 'number', name: `items[${index}][quantity]`, value: 1, min: 1, max: 99, inputMode: 'numeric' });
        const remove = el('button', 'min-h-11 rounded-lg px-2 text-sm font-semibold text-red-700 hover:bg-red-50', 'Hapus');
        remove.type = 'button'; remove.dataset.remove = ''; remove.setAttribute('aria-label', `Hapus ${item.name}`);
        row.append(hidden, text, qtyLabel, qty, remove);
        selected.append(row);
        refreshEmpty();
        status.textContent = `${item.name} ditambahkan.`;
        // Ready for the next product: clear the search so the list does not cover the form.
        search.value = ''; results.replaceChildren();
    };

    selected.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove]');
        if (!button) return;
        button.closest('li').remove();
        refreshEmpty();
        search.focus();
    });

    search.addEventListener('input', () => {
        clearTimeout(timer);
        const term = search.value.trim();
        if (term.length < 2) { results.replaceChildren(); status.textContent = ''; return; }
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            status.textContent = 'Mencari…';
            try {
                const url = new URL(search.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', term);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('search failed');
                const { items } = await response.json();
                results.replaceChildren(...items.map((item) => {
                    const li = el('li');
                    const button = el('button', 'flex min-h-14 w-full items-center gap-3 px-3 py-2 text-left hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none');
                    button.type = 'button';
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

    const fillFields = form.querySelector('[data-fill-fields]');
    const newCustomer = form.querySelector('[data-new-customer]');
    const showFill = (show) => { fillFields.hidden = !show; };
    const fillNow = () => { const radio = form.querySelector('[data-fill-choice][value="1"]'); radio.checked = true; showFill(true); };
    form.querySelectorAll('[data-fill-choice]').forEach((radio) => radio.addEventListener('change', () => showFill(radio.value === '1' && radio.checked)));

    // Repeat customer: explicit choice only; picking one fills name/number and offers saved addresses.
    const picker = form.querySelector('[data-customer-picker]');
    const customerSearch = picker.querySelector('#customer-search');
    const customerStatus = picker.querySelector('#customer-search-status');
    const customerResults = picker.querySelector('[data-customer-results]');
    const customerId = picker.querySelector('[data-customer-id]');
    const chosen = picker.querySelector('[data-customer-chosen]');
    const searchBox = picker.querySelector('[data-customer-search-box]');
    const addressBox = picker.querySelector('[data-address-choices]');
    const addressList = picker.querySelector('[data-address-list]');
    let customerTimer;
    let customerController;
    const setField = (name, value) => { const field = form.querySelector(`[name="${name}"]`); if (field && value !== undefined && value !== null) field.value = value; };
    const choose = (customer) => {
        customerId.value = customer.id;
        picker.querySelector('[data-customer-label]').textContent = `${customer.name} · ${customer.phone}`;
        chosen.hidden = false; searchBox.hidden = true; customerResults.replaceChildren();
        setField('customer_name', customer.name); setField('customer_phone', customer.phone);
        // A repeat customer's data is known: fill it here instead of sending a link.
        fillNow(); newCustomer.hidden = true; newCustomer.querySelector('input').checked = false;
        addressList.replaceChildren(...customer.addresses.map((address) => {
            const label = el('label', 'flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm has-[:checked]:border-black');
            const radio = el('input', 'mt-0.5 h-5 w-5'); Object.assign(radio, { type: 'radio', name: 'customer_address_id', value: address.id });
            radio.addEventListener('change', () => {
                setField('fulfillment', address.type === 'intercity' ? 'intercity' : 'local_delivery');
                setField('address', address.address); setField('postcode', address.postcode ?? '');
            });
            const text = el('span'); text.append(el('span', 'block font-semibold', address.label), el('span', 'block text-gray-600', `${address.address} ${address.postcode ?? ''}`));
            label.append(radio, text);
            return label;
        }));
        addressBox.hidden = customer.addresses.length === 0;
        customerStatus.textContent = `${customer.name} dipilih.`;
    };
    picker.querySelector('[data-customer-clear]').addEventListener('click', () => {
        customerId.value = ''; chosen.hidden = true; searchBox.hidden = false; addressBox.hidden = true; addressList.replaceChildren(); newCustomer.hidden = false; customerSearch.focus();
    });
    customerSearch.addEventListener('input', () => {
        clearTimeout(customerTimer);
        const term = customerSearch.value.trim();
        if (term.length < 3) { customerResults.replaceChildren(); customerStatus.textContent = 'Pelanggan baru? Lewati saja.'; return; }
        customerTimer = setTimeout(async () => {
            customerController?.abort();
            customerController = new AbortController();
            customerStatus.textContent = 'Mencari…';
            try {
                const url = new URL(picker.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', term);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: customerController.signal });
                if (!response.ok) throw new Error('search failed');
                const { items } = await response.json();
                customerResults.replaceChildren(...items.map((customer) => {
                    const li = el('li');
                    const button = el('button', 'flex min-h-12 w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none');
                    button.type = 'button';
                    button.append(el('span', 'font-semibold text-gray-900', customer.name), el('span', 'text-gray-500', `${customer.phone} · ${customer.addresses.length} alamat tersimpan`));
                    button.addEventListener('click', () => choose(customer));
                    li.append(button);
                    return li;
                }));
                customerStatus.textContent = items.length ? `${items.length} pelanggan` : 'Tidak ditemukan. Lanjutkan sebagai pelanggan baru.';
            } catch (error) {
                if (error.name !== 'AbortError') customerStatus.textContent = 'Pencarian gagal. Periksa koneksi lalu ketik ulang.';
            }
        }, 250);
    });
    form.addEventListener('submit', (event) => {
        if (!selected.children.length) { event.preventDefault(); status.textContent = 'Pilih minimal satu produk.'; search.focus(); return; }
        // The Admin PWA submit guard disables the button and shows the busy label.
    });
})();
</script>
@endpush
