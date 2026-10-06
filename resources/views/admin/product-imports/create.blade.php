@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl">
    <nav class="mb-6 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('admin.products.index') }}" class="hover:text-gray-900">Products</a>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="font-medium text-gray-800">Import Produk</span>
    </nav>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-amber-700">Preview, review, lalu apply ke draft</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Import Produk</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">Upload CSV hasil kurasi Claude. Sistem menyimpan preview immutable; apply hanya membuat draft baru atau memperbarui draft existing yang aman.</p>
        </div>
        <div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end">
                <a href="{{ route('admin.product-maintenance.create') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
                    Preview maintenance
                </a>
                <a href="{{ route('admin.product-imports.template') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
                    Unduh template CSV
                </a>
                <a href="{{ route('admin.product-imports.catalog-snapshot') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-gray-900 bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
                    Unduh snapshot katalog
                </a>
            </div>
            <p class="mt-2 text-xs leading-relaxed text-gray-500 sm:text-right">Snapshot bersifat read-only dan tidak dapat langsung diimport.</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="upload-title">
            <h2 id="upload-title" class="text-lg font-bold text-gray-900">1. Pilih file untuk preview</h2>
            <p class="mt-1 text-sm text-gray-500">CSV UTF-8, maksimum 5 MB dan 1.000 baris. File sumber tidak disimpan; hanya fingerprint dan hasil preview terstruktur yang dicatat.</p>

            <form method="POST" action="{{ route('admin.product-imports.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="product-file" class="block text-sm font-semibold text-gray-800">File CSV Qammaris</label>
                    <input id="product-file" name="product_file" type="file" accept=".csv,text/csv" required
                        class="mt-2 block w-full rounded-lg border border-gray-300 bg-white p-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-800 hover:file:bg-gray-200 @error('product_file') border-red-400 @enderror"
                        @error('product_file') aria-describedby="product-file-error" aria-invalid="true" @enderror>
                    @error('product_file')
                        <p id="product-file-error" class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-black px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 sm:w-auto">
                    Preview file
                </button>
            </form>
        </section>

        <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="flow-title">
            <h2 id="flow-title" class="text-base font-bold text-gray-900">Alur file</h2>
            <ol class="mt-4 space-y-3 text-sm text-gray-600">
                <li><span class="font-semibold text-gray-900">1.</span> Export data produk dari Shopee.</li>
                <li><span class="font-semibold text-gray-900">2.</span> Claude merapikan ke template Qammaris.</li>
                <li><span class="font-semibold text-gray-900">3.</span> Admin upload dan review preview.</li>
                <li><span class="font-semibold text-gray-900">4.</span> Konfirmasi lalu apply ke draft.</li>
                <li><span class="font-semibold text-gray-900">5.</span> Akuisisi gambar ke storage Qammaris.</li>
            </ol>
            <div class="mt-5 rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">Jangan upload export Shopee mentah. Nama kolom dan urutannya harus sama dengan template.</div>
        </aside>
    </div>

    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="contract-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="contract-title" class="text-lg font-bold text-gray-900">2. Kontrak kolom</h2>
                <p class="mt-1 text-sm text-gray-500">Kode produk diperlakukan sebagai teks. Gunakan <code class="rounded bg-gray-100 px-1 py-0.5">|</code> untuk memisahkan beberapa fragrance notes.</p>
            </div>
            <span class="text-xs font-semibold text-gray-500">{{ count($headers) }} kolom tetap</span>
        </div>
        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">Kolom</th><th class="px-4 py-3">Aturan ringkas</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @foreach($headers as $header)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-gray-900">{{ $header }}</td>
                            <td class="min-w-72 px-4 py-3">
                                @switch($header)
                                    @case('provider') shopee atau majoo @break
                                    @case('kode_produk') Wajib, teks, maksimum 191 karakter @break
                                    @case('nama_produk') Wajib, maksimum 255 karakter @break
                                    @case('harga') Angka positif tanpa pemisah ribuan @break
                                    @case('gender') Unisex, Pria, atau Wanita @break
                                    @case('stok') Opsional, bilangan bulat 0–999999 @break
                                    @case('terlaris') ya/tidak, true/false, atau 1/0 @break
                                    @case('ukuran_ml') Bilangan bulat 1–10000 @break
                                    @case('top_notes') @case('middle_notes') @case('base_notes') Opsional; pisahkan nilai dengan | @break
                                    @case('foto_utama_url') @case('foto_2_url') @case('foto_3_url') Opsional; URL HTTPS sumber, diunduh terpisah setelah apply @break
                                    @default Teks hasil kurasi; field kosong ditandai perlu review
                                @endswitch
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if($preview)
        <section class="mt-6" aria-labelledby="preview-title">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-emerald-700">Batch audit #{{ $batch->id }} tersimpan</p>
                    <h2 id="preview-title" class="mt-1 text-2xl font-bold text-gray-900">3. Hasil preview</h2>
                </div>
                <div class="flex flex-col gap-3 sm:items-end">
                    <div class="space-y-1 font-mono text-[11px] text-gray-500 sm:text-right">
                        <p class="break-all">File: {{ $preview['fingerprint'] }}</p>
                        <p class="break-all">State: {{ $preview['catalog_state_fingerprint'] }}</p>
                    </div>
                    <a href="{{ route('admin.product-imports.report', $batch) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
                        Unduh laporan batch CSV
                    </a>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach([
                    ['label' => 'Total baris', 'value' => $preview['summary']['total'], 'class' => 'text-gray-900'],
                    ['label' => 'Valid', 'value' => $preview['summary']['valid'], 'class' => 'text-emerald-700'],
                    ['label' => 'Perlu review', 'value' => $preview['summary']['review'], 'class' => 'text-amber-700'],
                    ['label' => 'Error', 'value' => $preview['summary']['error'], 'class' => 'text-red-700'],
                ] as $metric)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium text-gray-500">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $metric['class'] }}">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>

            @if($preview['skipped_blank_rows'])
                <p class="mt-4 rounded-lg bg-gray-100 px-4 py-3 text-sm text-gray-600">Baris kosong dilewati: {{ implode(', ', $preview['skipped_blank_rows']) }}.</p>
            @endif

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-[92rem] divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Baris</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Aksi kandidat</th>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3">Harga / ukuran</th>
                            <th class="px-4 py-3">Brand / kategori</th>
                            <th class="px-4 py-3">Masalah</th>
                            <th class="px-4 py-3">Hasil apply</th>
                            <th class="px-4 py-3">Akuisisi gambar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top text-gray-700">
                        @foreach($preview['rows'] as $row)
                            <tr>
                                <td class="px-4 py-4 font-semibold text-gray-900">{{ $row['line_number'] }}</td>
                                <td class="px-4 py-4">
                                    @php($statusClasses = ['valid' => 'bg-emerald-50 text-emerald-700', 'review' => 'bg-amber-50 text-amber-700', 'error' => 'bg-red-50 text-red-700'])
                                    @php($statusLabels = ['valid' => 'Valid', 'review' => 'Perlu review', 'error' => 'Error'])
                                    <span class="inline-flex rounded px-2 py-1 text-xs font-semibold {{ $statusClasses[$row['status']] }}">{{ $statusLabels[$row['status']] }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-semibold text-gray-900">{{ ['create' => 'Buat draft', 'update' => 'Update draft', 'conflict' => 'Tahan'][ $row['action'] ] }}</span>
                                    @if($row['matched_product'])
                                        <p class="mt-1 text-xs text-gray-500">#{{ $row['matched_product']['id'] }} {{ $row['matched_product']['name'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <p class="max-w-xs font-semibold text-gray-900">{{ $row['data']['nama_produk'] ?? '—' }}</p>
                                    <p class="mt-1 font-mono text-xs text-gray-500">{{ $row['data']['provider'] ?? '—' }} / {{ $row['data']['kode_produk'] ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-4">{{ $row['data']['harga'] ?? '—' }}<br><span class="text-xs text-gray-500">{{ $row['data']['ukuran_ml'] ?? '—' }} ml</span></td>
                                <td class="px-4 py-4">{{ $row['data']['brand'] ?? '—' }}<br><span class="text-xs text-gray-500">{{ $row['data']['kategori'] ?? '—' }}</span></td>
                                <td class="max-w-md px-4 py-4">
                                    @if($row['issues'])
                                        <ul class="space-y-2">
                                            @foreach($row['issues'] as $issue)
                                                <li class="text-xs leading-relaxed {{ $issue['severity'] === 'error' ? 'text-red-700' : 'text-amber-700' }}"><span class="font-semibold">{{ $issue['field'] }}:</span> {{ $issue['message'] }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-xs text-emerald-700">Tidak ada masalah.</span>
                                    @endif
                                </td>
                                <td class="max-w-xs px-4 py-4">
                                    @php($applyStatus = $row['apply_status'] ?? 'pending')
                                    @php($applyLabels = [
                                        'pending' => 'Belum diterapkan',
                                        'created' => 'Draft dibuat',
                                        'updated' => 'Draft diperbarui',
                                        'blocked_error' => 'Ditahan: error',
                                        'blocked_protected' => 'Ditahan: protected',
                                    ])
                                    @php($applyClasses = [
                                        'pending' => 'text-gray-500',
                                        'created' => 'text-emerald-700',
                                        'updated' => 'text-emerald-700',
                                        'blocked_error' => 'text-red-700',
                                        'blocked_protected' => 'text-amber-700',
                                    ])
                                    <p class="text-xs font-semibold {{ $applyClasses[$applyStatus] ?? 'text-gray-500' }}">{{ $applyLabels[$applyStatus] ?? $applyStatus }}</p>
                                    @if($row['apply_message'] ?? null)
                                        <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ $row['apply_message'] }}</p>
                                    @endif
                                    @if($row['applied_product'] ?? null)
                                        <a href="{{ route('admin.products.edit', $row['applied_product']['id']) }}" class="mt-2 inline-flex text-xs font-semibold text-gray-900 underline decoration-gray-300 underline-offset-2 hover:decoration-gray-900">Buka draft #{{ $row['applied_product']['id'] }}</a>
                                    @endif
                                </td>
                                <td class="max-w-sm px-4 py-4">
                                    @php($imageStatusLabels = [
                                        null => 'Belum diminta',
                                        'queued' => 'Dalam antrean',
                                        'processing' => 'Sedang diproses',
                                        'completed' => 'Selesai',
                                        'completed_with_errors' => 'Perlu retry/review',
                                        'no_sources' => 'Tanpa URL sumber',
                                    ])
                                    @php($imageStatusClasses = [
                                        null => 'text-gray-500',
                                        'queued' => 'text-blue-700',
                                        'processing' => 'text-blue-700',
                                        'completed' => 'text-emerald-700',
                                        'completed_with_errors' => 'text-amber-700',
                                        'no_sources' => 'text-gray-500',
                                    ])
                                    @php($imageStatus = $row['image_acquisition_status'] ?? null)
                                    <p class="text-xs font-semibold {{ $imageStatusClasses[$imageStatus] ?? 'text-gray-500' }}">{{ $imageStatusLabels[$imageStatus] ?? $imageStatus }}</p>

                                    @if($row['image_acquisition_outcomes'] ?? [])
                                        <ul class="mt-2 space-y-2">
                                            @foreach($row['image_acquisition_outcomes'] ?? [] as $imageOutcome)
                                                @php($outcomeStatus = $imageOutcome['status'] ?? 'pending')
                                                @php($outcomeClasses = [
                                                    'stored' => 'text-emerald-700',
                                                    'failed' => 'text-red-700',
                                                    'blocked' => 'text-amber-700',
                                                    'skipped_duplicate' => 'text-gray-500',
                                                    'downloading' => 'text-blue-700',
                                                    'pending' => 'text-gray-500',
                                                ])
                                                <li class="text-xs leading-relaxed {{ $outcomeClasses[$outcomeStatus] ?? 'text-gray-500' }}">
                                                    <span class="font-semibold">{{ $imageOutcome['label'] ?? 'Gambar' }}:</span>
                                                    {{ [
                                                        'stored' => 'tersimpan',
                                                        'failed' => 'gagal',
                                                        'blocked' => 'ditahan',
                                                        'skipped_duplicate' => 'duplikat dilewati',
                                                        'downloading' => 'mengunduh',
                                                        'pending' => 'menunggu',
                                                    ][$outcomeStatus] ?? $outcomeStatus }}
                                                    @if($imageOutcome['message'] ?? null)
                                                        <span class="block text-gray-500">{{ $imageOutcome['message'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif(collect(['foto_utama_url', 'foto_2_url', 'foto_3_url'])->contains(fn ($field) => !empty($row['data'][$field])))
                                        <p class="mt-1 text-xs text-gray-500">Kandidat URL akan diproses setelah batch applied.</p>
                                    @else
                                        <p class="mt-1 text-xs text-gray-500">Tidak ada URL gambar.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($batch->status === \App\Models\ProductImportBatch::STATUS_PREVIEWED)
                <form method="POST" action="{{ route('admin.product-imports.apply', $batch) }}" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:p-5" aria-labelledby="apply-title">
                    @csrf
                    <h3 id="apply-title" class="text-base font-bold text-gray-900">4. Apply batch ke draft</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700">Baris valid dan perlu review akan diproses. Produk baru selalu menjadi draft. Produk existing published/archived serta baris error/conflict akan ditahan tanpa ditimpa.</p>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Apply tidak membuat taxonomy, tidak mengunduh URL gambar, tidak mengubah availability menjadi ready, dan tidak mempublikasikan produk.</p>

                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-lg border border-amber-200 bg-white p-3 text-sm text-gray-700">
                        <input name="confirm_apply" value="1" type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                        <span>Saya sudah mereview batch #{{ $batch->id }} dan menyetujui apply {{ $batch->valid_rows + $batch->review_rows }} kandidat ke draft.</span>
                    </label>
                    @error('confirm_apply')
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-black px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 sm:w-auto">
                        Apply batch #{{ $batch->id }}
                    </button>
                </form>
            @elseif($batch->status === \App\Models\ProductImportBatch::STATUS_APPLIED)
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                    <p class="font-bold">Batch sudah selesai diterapkan.</p>
                    <p class="mt-1">{{ $batch->applied_rows }} baris diterapkan dan {{ $batch->blocked_rows }} baris ditahan{{ $batch->appliedBy ? ' oleh '.$batch->appliedBy->name : '' }}.</p>
                </div>

                @if($protectedResolutions->isNotEmpty())
                    <section class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm sm:p-5" aria-labelledby="protected-resolution-title">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Keputusan manusia diperlukan</p>
                            <h3 id="protected-resolution-title" class="mt-1 text-lg font-bold text-gray-900">5. Review update produk published/archived</h3>
                            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-gray-700">Pilih hanya field yang memang ingin diterapkan. Status publikasi, availability, slug, identity, dan media tidak akan berubah.</p>
                        </div>

                        <div class="mt-4 space-y-4">
                            @foreach($protectedResolutions as $resolution)
                                @php($resolutionRow = $resolution['row'])
                                <article id="protected-resolution-{{ $resolutionRow->id }}" class="scroll-mt-6 rounded-xl border border-amber-200 bg-white p-4 sm:p-5">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500">Baris {{ $resolutionRow->line_number }} · {{ $resolutionRow->provider }} / {{ $resolutionRow->external_product_id }}</p>
                                            <h4 class="mt-1 text-base font-bold text-gray-900">{{ $resolution['product']->name }}</h4>
                                            <p class="mt-1 text-xs text-amber-700">Produk {{ $resolution['product']->publication_status }} dilindungi dari bulk overwrite.</p>
                                        </div>
                                        <a href="{{ route('admin.products.edit', $resolution['product']) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-gray-900 underline decoration-gray-300 underline-offset-2 hover:decoration-gray-900">Buka produk #{{ $resolution['product']->id }}</a>
                                    </div>

                                    @if($resolutionRow->resolution_status === \App\Actions\Products\ResolveProtectedProductImportRow::STATUS_RESOLVED)
                                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                                            <p class="font-bold">Sudah direview dan diterapkan manual.</p>
                                            <p class="mt-1">{{ count($resolutionRow->resolution_fields ?? []) }} field diterapkan{{ $resolutionRow->resolvedBy ? ' oleh '.$resolutionRow->resolvedBy->name : '' }} pada {{ $resolutionRow->resolved_at?->format('d M Y H:i') }}.</p>
                                            <p class="mt-1 text-xs">{{ $resolutionRow->resolution_message }}</p>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('admin.product-imports.resolve-protected', [$batch, $resolutionRow]) }}" class="mt-4">
                                            @csrf
                                            <fieldset>
                                                <legend class="text-sm font-bold text-gray-900">Bandingkan dan pilih field</legend>
                                                <div class="mt-3 space-y-3">
                                                    @foreach($resolution['fields'] as $field)
                                                        <label class="grid gap-3 rounded-lg border border-gray-200 p-3 {{ $field['available'] ? 'cursor-pointer hover:border-gray-400' : 'bg-gray-50 opacity-65' }} sm:grid-cols-[1.5rem_minmax(0,1fr)_minmax(0,1fr)]">
                                                            <input type="checkbox" name="fields[]" value="{{ $field['key'] }}" {{ $field['available'] ? '' : 'disabled' }} class="mt-1 h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                                                            <span class="min-w-0">
                                                                <span class="block text-xs font-bold uppercase tracking-wide text-gray-500">Saat ini · {{ $field['label'] }}</span>
                                                                <span class="mt-1 block break-words text-sm text-gray-800">{{ filled($field['current']) ? $field['current'] : 'Kosong' }}</span>
                                                            </span>
                                                            <span class="min-w-0 sm:border-l sm:border-gray-200 sm:pl-3">
                                                                <span class="block text-xs font-bold uppercase tracking-wide {{ $field['available'] ? 'text-amber-700' : 'text-gray-400' }}">Hasil import</span>
                                                                <span class="mt-1 block break-words text-sm font-medium text-gray-900">{{ filled($field['import']) ? $field['import'] : 'Tidak tersedia' }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>

                                            @if($errors->has('fields') || $errors->has('fields.*'))
                                                <p class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first('fields') ?: $errors->first('fields.*') }}</p>
                                            @endif

                                            <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-gray-700">
                                                <input name="confirm_resolution" value="1" type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                                                <span>Saya sudah membandingkan nilai dan menyetujui field terpilih diterapkan ke produk {{ $resolution['product']->publication_status }} ini.</span>
                                            </label>
                                            @error('confirm_resolution')
                                                <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                                            @enderror

                                            <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-black px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 sm:w-auto">
                                                Terapkan field terpilih
                                            </button>
                                        </form>
                                    @endif
                                </article>
                            @endforeach
                        </div>

                        @if($batch->rows->where('apply_status', \App\Models\ProductImportRow::APPLY_BLOCKED_ERROR)->isNotEmpty())
                            <p class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">Baris error/conflict struktural tidak dapat dipaksa dari halaman ini. Perbaiki CSV, lalu buat preview baru agar mapping tetap dapat diaudit.</p>
                        @endif
                    </section>
                @endif

                <section class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="image-acquisition-title">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 id="image-acquisition-title" class="text-base font-bold text-gray-900">{{ $protectedResolutions->isNotEmpty() ? '6' : '5' }}. Simpan gambar ke storage Qammaris</h3>
                            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-gray-600">Proses berjalan per produk melalui antrean. URL provider divalidasi, file diperiksa, lalu disimpan ke disk media aktif. Halaman publik tidak memakai hotlink.</p>
                        </div>
                        @if($imageAcquisition['queued'] || $imageAcquisition['processing'])
                            <a href="{{ route('admin.product-imports.create', ['batch' => $batch->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Refresh status</a>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs text-gray-500">Baris eligible</dt><dd class="mt-1 text-lg font-bold text-gray-900">{{ $imageAcquisition['eligible_rows'] }}</dd></div>
                        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs text-gray-500">URL sumber</dt><dd class="mt-1 text-lg font-bold text-gray-900">{{ $imageAcquisition['source_count'] }}</dd></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><dt class="text-xs text-emerald-700">Tersimpan</dt><dd class="mt-1 text-lg font-bold text-emerald-800">{{ $imageAcquisition['stored'] }}</dd></div>
                        <div class="rounded-lg bg-amber-50 p-3"><dt class="text-xs text-amber-700">Gagal/ditahan</dt><dd class="mt-1 text-lg font-bold text-amber-800">{{ $imageAcquisition['failed'] }}</dd></div>
                    </dl>

                    @if(!$imageAcquisition['has_sources'])
                        <p class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600">Batch ini tidak mempunyai URL gambar sumber pada baris yang berhasil diterapkan. Draft tetap dapat dilengkapi manual dari editor produk.</p>
                    @elseif($imageAcquisition['queued'] || $imageAcquisition['processing'])
                        <p class="mt-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status">Antrean sedang berjalan: {{ $imageAcquisition['queued'] }} menunggu dan {{ $imageAcquisition['processing'] }} diproses. Draft tidak dibatalkan bila gambar gagal.</p>
                    @else
                        <form method="POST" action="{{ route('admin.product-imports.images', $batch) }}" class="mt-4">
                            @csrf
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                                <input name="confirm_image_acquisition" value="1" type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                                <span>Saya menyetujui download kandidat dari host yang diizinkan ke storage Qammaris. Gambar berhasil tidak akan diproses ulang.</span>
                            </label>
                            @error('confirm_image_acquisition')
                                <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-black px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 sm:w-auto">
                                {{ $imageAcquisition['retryable'] ? 'Coba ulang gambar gagal' : 'Mulai akuisisi gambar' }}
                            </button>
                        </form>
                    @endif
                </section>
            @else
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
                    <p class="font-bold">Batch tidak dapat diterapkan.</p>
                    <p class="mt-1">{{ $batch->failure_message ?? 'Buat preview baru sebelum mencoba lagi.' }}</p>
                </div>
            @endif
        </section>
    @endif

    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="history-title">
        <div>
            <h2 id="history-title" class="text-lg font-bold text-gray-900">Preview terbaru</h2>
            <p class="mt-1 text-sm text-gray-500">Buka batch untuk melihat preview, hasil apply, dan baris yang ditahan.</p>
        </div>

        @if($recentBatches->isEmpty())
            <div class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">Belum ada batch preview tersimpan.</div>
        @else
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-[56rem] divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Batch</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Ringkasan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Waktu / laporan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @foreach($recentBatches as $recentBatch)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900"><a href="{{ route('admin.product-imports.create', ['batch' => $recentBatch->id]) }}" class="underline decoration-gray-300 underline-offset-2 hover:decoration-gray-900">#{{ $recentBatch->id }}</a></td>
                                <td class="px-4 py-3">
                                    <p class="max-w-xs truncate font-medium text-gray-900">{{ $recentBatch->source_filename }}</p>
                                    <p class="mt-1 font-mono text-[11px] text-gray-500">{{ substr($recentBatch->source_fingerprint, 0, 12) }}… · {{ number_format($recentBatch->source_size / 1024, 1, ',', '.') }} KB</p>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="text-emerald-700">{{ $recentBatch->valid_rows }} valid</span>
                                    <span class="mx-1 text-gray-300">·</span>
                                    <span class="text-amber-700">{{ $recentBatch->review_rows }} review</span>
                                    <span class="mx-1 text-gray-300">·</span>
                                    <span class="text-red-700">{{ $recentBatch->error_rows }} error</span>
                                </td>
                                <td class="px-4 py-3 text-xs font-semibold">
                                    @php($batchLabels = ['previewed' => 'Menunggu apply', 'applied' => 'Selesai', 'stale' => 'Stale', 'invalid' => 'Invalid', 'failed' => 'Gagal'])
                                    <span>{{ $batchLabels[$recentBatch->status] ?? $recentBatch->status }}</span>
                                    @if($recentBatch->status === 'applied')
                                        <p class="mt-1 font-normal text-gray-500">{{ $recentBatch->applied_rows }} applied · {{ $recentBatch->blocked_rows }} ditahan</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $recentBatch->actor?->name ?? 'Akun dihapus' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                    <p>{{ $recentBatch->created_at->format('d M Y H:i') }}</p>
                                    <a href="{{ route('admin.product-imports.report', $recentBatch) }}" class="mt-2 inline-flex min-h-11 items-center font-semibold text-gray-900 underline decoration-gray-300 underline-offset-2 hover:decoration-gray-900 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Unduh CSV</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
