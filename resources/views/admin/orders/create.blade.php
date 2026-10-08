@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pesanan Online</a>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Buat pesanan</h1>
        <p class="mt-1 text-sm text-gray-500">Pilih produk yang sudah disepakati di chat. Harga dikunci untuk pesanan ini.</p>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Periksa kembali:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-6" data-order-create>
        @csrf
        {{-- One token per opened form: a double tap or retry returns the first order instead of creating another. --}}
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="products-title">
            <h2 id="products-title" class="text-lg font-semibold text-gray-900">Produk</h2>
            <label for="product-search" class="mt-4 block text-sm font-medium text-gray-700">Cari produk</label>
            <input id="product-search" type="search" autocomplete="off" placeholder="Nama, brand, atau ukuran…" aria-describedby="product-search-status"
                class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10" data-search-url="{{ route('admin.orders.product-search') }}">
            <p id="product-search-status" role="status" aria-live="polite" class="mt-2 min-h-5 text-xs text-gray-500">Ketik minimal 2 huruf.</p>
            <ul class="mt-1 max-h-80 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 empty:hidden" data-results></ul>

            <h3 class="mt-6 text-sm font-semibold text-gray-900">Dipilih</h3>
            <p class="mt-1 text-sm text-gray-500" data-empty-selection @if ($selected->isNotEmpty()) hidden @endif>Belum ada produk dipilih.</p>
            <ul class="mt-2 divide-y divide-gray-100" data-selected>
                @foreach ($selected as $index => $item)
                    <li class="flex flex-wrap items-center gap-3 py-3" data-variant="{{ $item['variant_id'] }}">
                        <input type="hidden" name="items[{{ $index }}][variant_id]" value="{{ $item['variant_id'] }}">
                        <img src="{{ $item['image'] }}" alt="" class="h-12 w-10 shrink-0 object-contain">
                        <span class="min-w-[11rem] flex-1 text-sm"><span class="block font-semibold text-gray-900">{{ $item['name'] }}</span><span class="block text-gray-500">{{ $item['brand'] }} · {{ $item['volume'] }} ml · {{ $item['price'] }}</span></span>
                        <label class="text-sm text-gray-600">Jumlah <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}" min="1" max="99" class="ml-1 min-h-11 w-20 rounded-lg border border-gray-300 px-2 text-sm"></label>
                        <button type="button" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50" data-remove>Hapus</button>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="source-title">
            <h2 id="source-title" class="text-lg font-semibold text-gray-900">Pesanan dari</h2>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach (['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'manual' => 'Lainnya'] as $value => $label)
                    <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-gray-300 px-2 text-sm font-semibold has-[:checked]:border-black has-[:checked]:bg-black has-[:checked]:text-white">
                        <input type="radio" name="source" value="{{ $value }}" class="sr-only" @checked(old('source', 'whatsapp') === $value)> {{ $label }}
                    </label>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="customer-title" data-customer-picker data-search-url="{{ route('admin.orders.customer-search') }}">
            <h2 id="customer-title" class="text-lg font-semibold text-gray-900">Pelanggan</h2>
            <input type="hidden" name="customer_id" value="{{ old('customer_id') }}" data-customer-id>
            <div data-customer-chosen @unless (old('customer_id')) hidden @endunless class="mt-3 flex items-center justify-between gap-2 rounded-lg border border-black p-3 text-sm">
                <span data-customer-label>Pelanggan dipilih</span>
                <button type="button" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100" data-customer-clear>Ganti</button>
            </div>
            <div data-customer-search-box @if (old('customer_id')) hidden @endif>
                <label for="customer-search" class="mt-3 block text-sm font-medium text-gray-700">Pelanggan lama? Cari nama atau nomor WA</label>
                <input id="customer-search" type="search" autocomplete="off" placeholder="Minimal 3 huruf/angka" aria-describedby="customer-search-status"
                    class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10">
                <p id="customer-search-status" role="status" aria-live="polite" class="mt-2 min-h-5 text-xs text-gray-500">Kosongkan bila pelanggan baru.</p>
                <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 empty:hidden" data-customer-results></ul>
                <label class="mt-2 flex min-h-11 items-center gap-3 text-sm text-gray-800">
                    <input type="checkbox" name="new_customer" value="1" @checked(old('new_customer')) class="h-5 w-5"> Simpan sebagai pelanggan langganan baru
                </label>
            </div>
            <fieldset class="mt-3" data-address-choices hidden>
                <legend class="text-sm font-medium text-gray-700">Alamat tersimpan</legend>
                <div class="mt-1 space-y-2" data-address-list></div>
            </fieldset>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="recipient-title">
            <h2 id="recipient-title" class="text-lg font-semibold text-gray-900">Data penerima</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-3 text-sm has-[:checked]:border-black has-[:checked]:ring-1 has-[:checked]:ring-black">
                    <input type="radio" name="fill_customer" value="1" class="mt-0.5 h-5 w-5" @checked(old('fill_customer') === '1') data-fill-choice>
                    <span><span class="block font-semibold text-gray-900">Data sudah lengkap</span><span class="text-gray-600">Saya isi sekarang; customer tidak perlu membuka form.</span></span>
                </label>
                <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-3 text-sm has-[:checked]:border-black has-[:checked]:ring-1 has-[:checked]:ring-black">
                    <input type="radio" name="fill_customer" value="0" class="mt-0.5 h-5 w-5" @checked(old('fill_customer', '0') !== '1') data-fill-choice>
                    <span><span class="block font-semibold text-gray-900">Kirim link ke customer</span><span class="text-gray-600">Customer mengisi sendiri: penerimaan dan paperbag.</span></span>
                </label>
            </div>
            <div class="mt-4" data-fill-fields @unless (old('fill_customer') === '1') hidden @endunless>
                @include('admin.orders._customer-fields')
            </div>
        </section>

        <div class="sticky bottom-20 z-10 -mx-1 flex flex-wrap gap-3 rounded-xl bg-gray-50/95 p-1 md:static md:bottom-auto md:bg-transparent">
            <button type="submit" class="min-h-12 flex-1 rounded-lg bg-black px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-400 sm:flex-none" data-busy-label="Membuat pesanan…">Buat pesanan</button>
            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Batal</a>
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
        const row = el('li', 'flex flex-wrap items-center gap-3 py-3');
        row.dataset.variant = item.variant_id;
        const hidden = el('input'); hidden.type = 'hidden'; hidden.name = `items[${index}][variant_id]`; hidden.value = item.variant_id;
        const img = el('img', 'h-12 w-10 shrink-0 object-contain'); img.src = item.image; img.alt = '';
        // Name and price get the full row on a phone; quantity and delete wrap below.
        const text = el('span', 'min-w-[11rem] flex-1 text-sm');
        text.append(el('span', 'block font-semibold text-gray-900', item.name), el('span', 'block text-gray-500', `${item.brand} · ${item.volume} ml · ${item.price}`));
        const qtyLabel = el('label', 'text-sm text-gray-600', 'Jumlah ');
        const qty = el('input', 'ml-1 min-h-11 w-20 rounded-lg border border-gray-300 px-2 text-sm');
        Object.assign(qty, { type: 'number', name: `items[${index}][quantity]`, value: 1, min: 1, max: 99 });
        qtyLabel.append(qty);
        const remove = el('button', 'min-h-11 rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50', 'Hapus');
        remove.type = 'button'; remove.dataset.remove = '';
        row.append(hidden, img, text, qtyLabel, remove);
        selected.append(row);
        refreshEmpty();
        status.textContent = `${item.name} ditambahkan.`;
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
        if (term.length < 2) { results.replaceChildren(); status.textContent = 'Ketik minimal 2 huruf.'; return; }
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
                status.textContent = items.length ? `${items.length} produk ditemukan. Klik untuk menambahkan.` : 'Produk tidak ditemukan. Hanya produk tayang dengan harga yang muncul.';
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Pencarian gagal. Periksa koneksi lalu ketik ulang.';
            }
        }, 250);
    });

    const fillFields = form.querySelector('[data-fill-fields]');
    const showFill = (show) => { fillFields.hidden = !show; };
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
        customerId.value = ''; chosen.hidden = true; searchBox.hidden = false; addressBox.hidden = true; addressList.replaceChildren(); customerSearch.focus();
    });
    customerSearch.addEventListener('input', () => {
        clearTimeout(customerTimer);
        const term = customerSearch.value.trim();
        if (term.length < 3) { customerResults.replaceChildren(); customerStatus.textContent = 'Kosongkan bila pelanggan baru.'; return; }
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
                customerStatus.textContent = items.length ? `${items.length} pelanggan ditemukan.` : 'Tidak ditemukan. Lanjutkan sebagai pelanggan baru.';
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
