@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pesanan Online</a>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Buat pesanan</h1>
        <p class="mt-1 text-sm text-gray-500">Pilih produk yang sudah disepakati di chat. Harga mengikuti katalog saat ini dan dikunci untuk pesanan ini.</p>
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
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="products-title">
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
                        <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-gray-900">{{ $item['name'] }}</span><span class="block text-gray-500">{{ $item['brand'] }} · {{ $item['volume'] }} ml · {{ $item['price'] }}</span></span>
                        <label class="text-sm text-gray-600">Jumlah <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}" min="1" max="99" class="ml-1 min-h-11 w-20 rounded-lg border border-gray-300 px-2 text-sm"></label>
                        <button type="button" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50" data-remove>Hapus</button>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-gray-900">
                <input type="checkbox" name="fill_customer" value="1" @checked(old('fill_customer')) class="h-5 w-5" data-fill-toggle>
                Saya isi data customer sekarang (customer kesulitan membuka link)
            </label>
            <div class="mt-4" data-fill-fields @unless (old('fill_customer')) hidden @endunless>
                @include('admin.orders._customer-fields')
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="min-h-11 rounded-lg bg-black px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-400">Buat pesanan & link</button>
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
        const text = el('span', 'min-w-0 flex-1 text-sm');
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

    const toggle = form.querySelector('[data-fill-toggle]');
    toggle.addEventListener('change', () => { form.querySelector('[data-fill-fields]').hidden = !toggle.checked; });
    form.addEventListener('submit', (event) => {
        if (!selected.children.length) { event.preventDefault(); status.textContent = 'Pilih minimal satu produk.'; search.focus(); return; }
        form.querySelector('button[type="submit"]').disabled = true;
    });
})();
</script>
@endpush
