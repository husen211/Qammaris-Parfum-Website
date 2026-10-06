@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Produk dari Qammaris App</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">Produk baru masuk sebagai draft. Lengkapi foto, deskripsi, dan klasifikasi sebelum ditayangkan. Stok dan harga jual mengikuti aplikasi.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2">Semua produk website</a>
    </div>

    <section class="mt-6 border-y border-gray-200 py-4" aria-label="Status koneksi">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-600">
                <p class="font-semibold text-gray-900">{{ ! $configured ? 'Koneksi belum dikonfigurasi' : ($state?->last_error ? 'Sinkronisasi perlu diperiksa' : ($state?->last_synced_at ? 'Sinkronisasi terakhir berhasil' : 'Menunggu sinkronisasi pertama')) }}</p>
                @if($state?->last_synced_at)
                    <p class="mt-1">Sinkron terakhir: {{ \Carbon\Carbon::parse($state->last_synced_at)->format('d M Y H:i') }} · checkpoint {{ $state->checkpoint }}</p>
                @endif
                <p class="mt-1 text-xs">Webhook memperbarui data; rekonsiliasi berjalan tiap 30 menit. Koneksi terputus mempertahankan data terakhir.</p>
            </div>
            <form method="POST" action="{{ route('admin.app-products.sync') }}">
                @csrf
                <button @disabled(! $configured) class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">Sinkronkan sekarang</button>
            </form>
        </div>
    </section>

    <nav class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Kelompok produk aplikasi">
        @foreach(['all' => 'Semua', 'draft' => 'Draft', 'unlinked' => 'Perlu pasangan', 'price_review' => 'Periksa harga', 'hidden' => 'Disembunyikan aplikasi'] as $key => $label)
            <a href="{{ route('admin.app-products.index', ['status' => $key, 'search' => $search]) }}" @if($filter === $key) aria-current="page" @endif class="inline-flex min-h-11 items-center gap-2 border-b-2 {{ $filter === $key ? 'border-gray-900 font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900' }} focus-visible:outline-2 focus-visible:outline-offset-2">{{ $label }} <span class="tabular-nums">{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.app-products.index') }}" class="mt-5 flex flex-col gap-2 sm:flex-row">
        <input type="hidden" name="status" value="{{ $filter }}">
        <label for="app-product-search" class="sr-only">Cari produk aplikasi</label>
        <input id="app-product-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Cari nama, merek, SKU, atau UUID" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm focus:outline-2 focus:outline-offset-2">
        <button class="min-h-11 rounded-lg bg-gray-900 px-5 text-sm font-semibold text-white hover:bg-black">Cari</button>
        @if($search !== '')<a href="{{ route('admin.app-products.index', ['status' => $filter]) }}" class="inline-flex min-h-11 items-center justify-center px-3 text-sm underline">Reset</a>@endif
    </form>

    @if($rows->isEmpty())
        <div class="mt-6 border border-dashed border-gray-300 p-6 text-sm text-gray-600" role="status">
            {{ $counts['all'] === 0 ? 'Belum ada data aplikasi tersimpan. Setelah koneksi aktif, produk baru akan muncul di sini.' : 'Tidak ada produk pada pencarian atau kelompok ini.' }}
        </div>
    @else
        <p class="mt-4 text-xs text-gray-500">{{ $rows->total() }} produk · {{ $rows->firstItem() }}–{{ $rows->lastItem() }} ditampilkan</p>
        <div class="mt-3 divide-y divide-gray-200 border-y border-gray-200">
            @foreach($rows as $row)
                @php($source = $row['source'])
                @php($product = $row['product'])
                <article class="grid min-w-0 gap-3 py-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] lg:items-start">
                    <div class="min-w-0">
                        <h2 class="break-words text-base font-semibold text-gray-900">{{ $product?->name ?? $source['name'] }}</h2>
                        <p class="mt-1 break-all text-sm text-gray-600">{{ $source['brand'] ?: 'Merek belum diisi' }} · SKU {{ $source['sku'] ?: 'belum ada' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $source['source'] === 'app' ? 'Dibuat di aplikasi · perlu review Owner' : 'Sumber Majoo' }}</p>
                        <details class="mt-2 text-xs text-gray-500"><summary class="cursor-pointer">Identitas aplikasi</summary><p class="mt-2 break-all">{{ $source['id'] }} · revision {{ $source['revision'] }}</p></details>
                    </div>
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-gray-900">{{ $source['hidden'] ? 'Disembunyikan aplikasi' : ($product?->publication_status === 'published' ? 'Tayang' : ($product?->publication_status === 'archived' ? 'Diarsipkan website' : ($product ? 'Draft' : 'Belum dipasangkan'))) }}</p>
                        <p class="mt-1 text-gray-600">{{ ['available' => 'Tersedia', 'sold_out' => 'Habis', 'unknown' => 'Tanyakan ketersediaan'][$source['availability']] }}{{ $source['availability'] === 'sold_out' && $source['restock_eta'] ? ' · Restok segera' : '' }}</p>
                        <p class="mt-1 text-gray-600">Harga aplikasi: {{ $source['price'] > 0 && $source['price'] <= 99999999 ? 'Rp '.number_format($source['price'], 0, ',', '.') : 'belum valid' }}</p>
                        @if($row['price_review'])<p class="mt-1 text-xs text-amber-800">Periksa harga atau ukuran di aplikasi/editor. Harga terakhir tetap dipertahankan.</p>@endif
                        @if($row['blockers'])
                            <details class="mt-2 text-xs text-amber-800" open>
                                <summary class="cursor-pointer font-semibold">Belum siap tayang</summary>
                                <ul class="mt-2 list-disc space-y-1 pl-4">@foreach($row['blockers'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                            </details>
                        @elseif(! $product && ! $source['hidden'])
                            <p class="mt-2 text-xs text-amber-800">Pratinjau pasangan dahulu agar tidak membuat produk ganda.</p>
                        @endif
                    </div>
                    <div>
                        @if($product)
                            <a href="{{ route('admin.products.edit', ['product' => $product->id, 'return_to' => route('admin.app-products.index', ['status' => $filter, 'search' => $search, 'page' => $rows->currentPage()], false)]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2">{{ $product->publication_status === 'draft' ? 'Lengkapi draft' : 'Edit produk' }}</a>
                        @else
                            <span class="text-xs text-gray-500">{{ $source['hidden'] ? 'Tombstone dipertahankan' : 'Review pasangan diperlukan' }}</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-5">{{ $rows->links() }}</div>
    @endif

    <section class="mt-8 border-t border-gray-200 pt-6" aria-labelledby="prepare-drafts-title">
        <h2 id="prepare-drafts-title" class="text-lg font-semibold">Siapkan snapshot yang belum menjadi draft</h2>
        <p class="mt-2 max-w-2xl text-sm text-gray-600">Untuk data tersimpan sebelum alur draft otomatis aktif. Pratinjau mempertahankan UUID existing dan menahan calon pasangan ganda. Tidak ada publish otomatis.</p>
        <form method="POST" action="{{ route('admin.app-products.preview') }}" class="mt-4">
            @csrf
            <button @disabled($counts['all'] === 0) class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">Buat pratinjau draft</button>
        </form>
        @if($errors->any())<p class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>@endif
        @if($batch)
            @php($candidates = $batch->rows->whereIn('candidate_action', ['create', 'conflict']))
            <div class="mt-5 rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="font-semibold">Pratinjau #{{ $batch->id }} · {{ $batch->status === 'applied' ? 'selesai' : 'menunggu konfirmasi' }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $batch->rows->where('candidate_action', 'create')->count() }} calon draft · {{ $batch->rows->where('candidate_action', 'conflict')->count() }} perlu review · {{ $batch->rows->where('candidate_action', 'skip')->count() }} dipertahankan</p>
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($candidates as $candidate)
                        <li class="py-3"><span class="font-semibold">{{ $candidate->normalized_data['nama_produk'] }}</span><span class="ml-2 text-gray-500">{{ $candidate->candidate_action === 'create' ? 'Draft baru' : 'Ditahan: periksa pasangan atau nama' }}</span></li>
                    @endforeach
                </ul>
                @if($batch->status === 'previewed' && $batch->actor_id === auth()->id() && $batch->rows->where('candidate_action', 'create')->isNotEmpty())
                    <form method="POST" action="{{ route('admin.app-products.apply', $batch) }}" class="mt-4 space-y-4">
                        @csrf
                        <label class="flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1">Saya sudah memeriksa calon draft; produk ini belum akan ditayangkan.</label>
                        <button class="min-h-11 rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-black">Buat {{ $batch->rows->where('candidate_action', 'create')->count() }} draft</button>
                    </form>
                @endif
            </div>
        @endif
    </section>
    <p class="mt-8 text-sm text-gray-600">Foto/deskripsi dapat dilengkapi di editor atau melalui <a href="{{ route('admin.product-imports.create') }}" class="font-semibold underline">impor pelengkap CSV</a>. Foto disimpan sebagai media website, bukan hotlink.</p>
</div>
@endsection
