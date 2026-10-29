@extends('layouts.admin')

@section('content')
{{-- CAT-ADMIN-ONE-01: the one product list; App products and website products share it. --}}
@php($tab = $catalogContext['publication'] ?? '')
@php($tabs = ['' => ['Semua', $counts['all']], 'check' => ['Perlu dicek', $counts['check']], 'draft' => ['Belum tayang', $counts['draft']], 'published' => ['Tayang', $counts['published']], 'archived' => ['Diarsipkan', $counts['archived']]])
@php($select = 'appearance-none w-full bg-white border border-gray-300 text-gray-700 min-h-11 py-2 pl-3 pr-8 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-sm cursor-pointer')
@php($syncStale = ! $sync?->last_synced_at || $sync->last_error || \Carbon\Carbon::parse($sync->last_synced_at)->lt(now()->subMinutes(120)))
<div class="mb-4">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Produk</h1>
        <a href="{{ route('admin.products.create') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2">Tambah manual</a>
    </div>
    <p class="mt-1 max-w-2xl text-sm text-gray-600">Produk dari Qammaris App masuk otomatis. Lengkapi foto dan deskripsi, lalu tayangkan.</p>
</div>

<nav aria-label="Status produk" class="mb-4 flex gap-x-5 overflow-x-auto overflow-y-hidden whitespace-nowrap border-b border-gray-200 text-sm sm:flex-wrap sm:overflow-visible">
    @foreach($tabs as $key => [$label, $count])
        <a href="{{ route('admin.products.index', array_filter(array_merge(\Illuminate\Support\Arr::except($catalogContext, ['publication', 'page']), ['publication' => $key ?: null]))) }}" @if($tab === $key) aria-current="page" @endif
            class="-mb-px inline-flex min-h-11 items-center gap-1.5 border-b-2 {{ $tab === $key ? 'border-gray-900 font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900' }} focus-visible:outline-2 focus-visible:outline-offset-2">
            {{ $label }} <span class="tabular-nums {{ $key === 'check' && $count > 0 ? 'font-semibold text-amber-700' : '' }}">{{ $count }}</span>
        </a>
    @endforeach
</nav>

<form action="{{ route('admin.products.index') }}" method="GET" class="mb-4 space-y-2 rounded-lg border border-gray-200 bg-white p-3 sm:p-4">
    @if($tab !== '')<input type="hidden" name="publication" value="{{ $tab }}">@endif
    <div class="relative group">
        <label for="catalog-search" class="sr-only">Cari produk</label>
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
            <svg class="h-5 w-5 text-gray-400 transition-colors group-focus-within:text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <input id="catalog-search" type="search" name="search" value="{{ $catalogContext['search'] ?? '' }}" enterkeyhint="search"
            class="block min-h-11 w-full rounded-lg border border-gray-300 bg-white py-2 pl-11 pr-4 text-base placeholder-gray-400 transition-shadow focus:border-black focus:ring-2 focus:ring-black sm:text-sm"
            placeholder="Cari nama produk atau merek">
    </div>
    <div class="grid grid-cols-2 gap-2 md:grid-cols-[repeat(3,minmax(0,13rem))_auto] md:items-center">
        <div class="relative">
            <label for="catalog-availability" class="sr-only">Filter ketersediaan</label>
            <select id="catalog-availability" name="availability" onchange="this.form.requestSubmit()" class="{{ $select }}">
                <option value="">Semua stok</option>
                <option value="available" @selected(($catalogContext['availability'] ?? null) === 'available')>Tersedia</option>
                <option value="sold_out" @selected(($catalogContext['availability'] ?? null) === 'sold_out')>Sold out</option>
                <option value="unknown" @selected(($catalogContext['availability'] ?? null) === 'unknown')>Belum dikonfirmasi</option>
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div class="relative">
            <label for="catalog-brand" class="sr-only">Filter merek</label>
            <select id="catalog-brand" name="brand_id" onchange="this.form.requestSubmit()" class="{{ $select }}">
                <option value="">Semua merek</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected(($catalogContext['brand_id'] ?? null) === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div class="relative">
            <label for="catalog-sort" class="sr-only">Urutkan produk</label>
            <select id="catalog-sort" name="sort" onchange="this.form.requestSubmit()" class="{{ $select }}">
                <option value="latest" @selected(! isset($catalogContext['sort']))>Terbaru</option>
                <option value="name_asc" @selected(($catalogContext['sort'] ?? null) === 'name_asc')>Nama A–Z</option>
                <option value="name_desc" @selected(($catalogContext['sort'] ?? null) === 'name_desc')>Nama Z–A</option>
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        @if(isset($catalogContext['search']) || isset($catalogContext['brand_id']) || isset($catalogContext['availability']) || isset($catalogContext['sort']))
            <a href="{{ route('admin.products.index', array_filter(['publication' => $tab ?: null])) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-4 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-200 md:justify-self-start">Reset</a>
        @endif
    </div>
    <p class="text-sm text-gray-500">Menampilkan <span class="font-medium text-black">{{ $products->total() }}</span> produk</p>
</form>

@if($waiting->isNotEmpty())
    {{-- ORD-12: drafts created while OTW got their App price later; the Owner fills them once, new ones fill automatically. --}}
    <section class="mb-4 rounded-lg border border-gray-200 bg-white p-4" aria-labelledby="waiting-offers-title">
        <h2 id="waiting-offers-title" class="text-base font-semibold text-gray-900">{{ $waiting->count() }} draft sudah punya harga di aplikasi, tapi belum bisa dipesan</h2>
        <p class="mt-1 max-w-2xl text-sm text-gray-600">Ukuran diambil dari nama produk, harga dari aplikasi. Setelah diisi, produk langsung bisa dipilih di Pesanan Online dan tetap draft sampai Anda menayangkannya.</p>
        <details class="mt-2 text-sm">
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold">Lihat daftar</summary>
            <ul class="divide-y divide-gray-100">
                @foreach($waiting as $row)
                    <li class="flex justify-between gap-3 py-2"><span class="min-w-0 break-words">{{ $row['product']->name }}</span><span class="shrink-0 tabular-nums text-gray-600">{{ $row['size'] }} ml · {{ format_rupiah($row['price']) }}</span></li>
                @endforeach
            </ul>
        </details>
        <form method="POST" action="{{ route('admin.app-products.fill-offers') }}" class="mt-2">
            @csrf
            @foreach($waiting as $row)<input type="hidden" name="product_ids[]" value="{{ $row['product']->id }}">@endforeach
            <button class="min-h-11 rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-black focus-visible:outline-2 focus-visible:outline-offset-2">Isi ukuran &amp; harga {{ $waiting->count() }} draft</button>
        </form>
    </section>
@endif

@if($unlinked > 0)
    <p class="mb-4 border-l-2 border-amber-500 bg-white px-4 py-3 text-sm text-gray-700" role="status">
        {{ $unlinked }} produk di aplikasi belum masuk ke sini karena namanya mirip produk lain.
        <a href="{{ route('admin.app-products.index', ['status' => 'unlinked']) }}" class="font-semibold underline">Tinjau</a>
    </p>
@endif

@if($syncStale)
    <div class="mb-4 flex flex-col gap-2 border-l-2 border-amber-500 bg-white px-4 py-3 text-sm text-gray-700 sm:flex-row sm:items-center sm:justify-between" role="status">
        <p>Sinkron dengan Qammaris App perlu dicek{{ $sync?->last_synced_at ? ' · terakhir berhasil '.\Carbon\Carbon::parse($sync->last_synced_at)->format('d M Y H:i') : '' }}. Stok dan harga memakai data terakhir.</p>
        <form method="POST" action="{{ route('admin.app-products.sync') }}">
            @csrf
            <button class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50">Sinkronkan sekarang</button>
        </form>
    </div>
@endif

<div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
    <div>
        <table class="w-full">
            <thead class="hidden md:table-header-group">
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Produk</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Merek & jenis</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Ukuran & harga</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Ketersediaan</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($products as $product)
                @php($note = $rowNotes[$product->id])
                @php($offers = $product->variants->where('is_active', true))
                @php($priceText = $offers->count() === 1 && (float) $offers->first()->price > 0 ? $offers->first()->volume.' ml · '.$product->price_range : ($offers->isNotEmpty() ? $product->price_range : null))
                <tr class="hover:bg-gray-50 transition-colors group">
                    <td class="px-4 py-5 md:px-6 md:py-4">
                        <div class="flex items-start md:items-center">
                            <div class="h-14 w-14 flex-shrink-0 rounded-md border border-gray-200 overflow-hidden bg-gray-100">
                                @if($product->primaryImage)
                                    <img src="{{ $product->primaryImage->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition-transform duration-500">
                                @else
                                    <div class="flex items-center justify-center h-full text-xs text-gray-400">No Img</div>
                                @endif
                            </div>
                            <div class="ml-4 min-w-0 flex-1">
                                <div class="whitespace-normal text-sm font-bold text-gray-900 group-hover:text-gold transition-colors">{{ $product->name }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                    @include('admin.products._publication', ['product' => $product])
                                    <span class="text-gray-500">· {{ $note['from_app'] ? 'dari App' : 'manual' }}{{ $product->gender ? ' · '.$product->gender : '' }}</span>
                                    @if($product->is_best_seller)<span class="font-bold text-amber-600">· Terlaris</span>@endif
                                </div>
                                @if($note['check'])
                                    <p class="mt-1 text-xs font-medium text-amber-800">Perlu dicek: {{ $note['check'] }}</p>
                                @endif
                                @if($product->publication_status === \App\Models\Product::PUBLICATION_DRAFT)
                                    <p class="mt-1 text-xs {{ $note['missing'] ? 'text-gray-600' : 'font-medium text-emerald-700' }}">{{ $note['missing'] ? 'Kurang: '.implode(', ', $note['missing']) : 'Siap ditayangkan' }}</p>
                                @endif

                                <div class="mt-3 space-y-3 md:hidden">
                                    <div class="text-xs text-gray-500">
                                        <span class="font-medium text-gray-800">{{ $product->brand?->name ?? 'Merek belum diisi' }}</span>
                                        · {{ $product->category?->name ?? 'Kategori belum diisi' }}
                                    </div>
                                    <div class="font-mono text-sm font-medium {{ $priceText ? 'text-gray-700' : 'text-gray-400' }}">{{ $priceText ?? 'Belum ada ukuran & harga' }}</div>
                                    @include('admin.products._availability', ['product' => $product])
                                    @include('admin.products._actions', ['product' => $product, 'catalogReturnPath' => $catalogReturnPath])
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        <div class="text-sm text-gray-900 font-medium">{{ $product->brand?->name ?? 'Merek belum diisi' }}</div>
                        <div class="text-xs text-gray-500">{{ $product->category?->name ?? 'Kategori belum diisi' }}</div>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        <span class="text-sm font-mono font-medium {{ $priceText ? 'text-gray-700' : 'text-gray-400' }}">{{ $priceText ?? 'Belum ada ukuran & harga' }}</span>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        @include('admin.products._availability', ['product' => $product])
                    </td>
                    <td class="hidden px-6 py-4 text-right text-sm font-medium md:table-cell">
                        <div class="flex justify-end">
                            @include('admin.products._actions', ['product' => $product, 'catalogReturnPath' => $catalogReturnPath])
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 bg-gray-50">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            @if($tab === 'check' && ! isset($catalogContext['search']))
                                <p class="text-base font-medium text-gray-900">Tidak ada yang perlu dicek</p>
                                <p class="text-sm text-gray-500 mt-1">Semua produk dari aplikasi punya ukuran dan harga.</p>
                            @else
                                <p class="text-base font-medium text-gray-900">Produk tidak ditemukan</p>
                                <p class="text-sm text-gray-500 mt-1">Coba ubah pencarian atau filter.</p>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        {{ $products->links('pagination::tailwind') }}
    </div>
    @endif
</div>

<div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
    @if($sync?->last_synced_at)<span>Sinkron aplikasi terakhir {{ \Carbon\Carbon::parse($sync->last_synced_at)->format('d M Y H:i') }}</span>@endif
    <a href="{{ route('admin.app-products.index') }}" class="inline-flex min-h-11 items-center underline">Data aplikasi</a>
    <a href="{{ route('admin.product-imports.create') }}" class="inline-flex min-h-11 items-center underline">Impor CSV pelengkap</a>
</div>
@endsection
