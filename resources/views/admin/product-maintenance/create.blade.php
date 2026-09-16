@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl">
    <nav class="mb-6 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('admin.products.index') }}" class="hover:text-gray-900">Products</a>
        <span class="mx-2" aria-hidden="true">/</span>
        <a href="{{ route('admin.product-imports.create') }}" class="hover:text-gray-900">Import Produk</a>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="font-medium text-gray-800">Maintenance</span>
    </nav>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-amber-700">Bandingkan dulu, belum ada perubahan data</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Preview bulk maintenance</h1>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-gray-600">Upload CSV hasil kurasi dari snapshot katalog. Sistem mencocokkan ID internal, mengecek stale data, lalu menunjukkan field yang berubah tanpa menulis ke katalog.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('admin.product-imports.catalog-snapshot') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Unduh snapshot terbaru</a>
            <a href="{{ route('admin.product-maintenance.template') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-gray-900 bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Unduh template maintenance</a>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-relaxed text-amber-900" role="note">
        <p class="font-bold">Preview ini tidak mempunyai tombol apply.</p>
        <p class="mt-1">Cell kosong mempertahankan nilai saat ini. Slug, publication, availability, media, dan external identity tidak dapat diubah lewat file ini.</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="maintenance-upload-title">
            <h2 id="maintenance-upload-title" class="text-lg font-bold text-gray-900">1. Upload file untuk preview</h2>
            <p class="mt-1 text-sm text-gray-500">CSV UTF-8, maksimum 5 MB dan 1.000 baris. File sumber tidak disimpan.</p>

            <form method="POST" action="{{ route('admin.product-maintenance.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="maintenance-file" class="block text-sm font-semibold text-gray-800">File CSV maintenance</label>
                    <input id="maintenance-file" name="maintenance_file" type="file" accept=".csv,text/csv" required
                        class="mt-2 block w-full rounded-lg border border-gray-300 bg-white p-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-800 hover:file:bg-gray-200 @error('maintenance_file') border-red-400 @enderror"
                        @error('maintenance_file') aria-describedby="maintenance-file-error" aria-invalid="true" @enderror>
                    @error('maintenance_file')
                        <p id="maintenance-file-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-black px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 sm:w-auto">Preview maintenance</button>
            </form>
        </section>

        <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="maintenance-rules-title">
            <h2 id="maintenance-rules-title" class="text-base font-bold text-gray-900">Guard utama</h2>
            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-gray-600">
                <li><span class="font-semibold text-gray-900">ID internal:</span> product harus sudah ada.</li>
                <li><span class="font-semibold text-gray-900">Timestamp + fingerprint:</span> wajib sama dengan snapshot.</li>
                <li><span class="font-semibold text-gray-900">Kosong:</span> berarti pertahankan nilai current.</li>
                <li><span class="font-semibold text-gray-900">Harga:</span> selalu berpasangan dengan ukuran.</li>
                <li><span class="font-semibold text-gray-900">Taksonomi:</span> wajib exact dan aktif.</li>
            </ul>
        </aside>
    </div>

    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="maintenance-contract-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="maintenance-contract-title" class="text-lg font-bold text-gray-900">2. Kontrak kolom maintenance</h2>
                <p class="mt-1 text-sm text-gray-500">Salin hanya field yang ingin diusulkan. Pertahankan ID dan timestamp dari snapshot.</p>
            </div>
            <span class="text-xs font-semibold text-gray-500">{{ count($headers) }} kolom tetap</span>
        </div>
        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Kolom</th><th class="px-4 py-3">Aturan ringkas</th></tr></thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @foreach($headers as $header)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-gray-900">{{ $header }}</td>
                            <td class="min-w-72 px-4 py-3">
                                @switch($header)
                                    @case('product_id') Wajib; ID internal dari snapshot @break
                                    @case('expected_updated_at') Wajib; timestamp ISO 8601 dari snapshot @break
                                    @case('expected_row_fingerprint') Wajib; fingerprint product + offer dari snapshot @break
                                    @case('harga') @case('ukuran_ml') Opsional, tetapi wajib diisi bersama @break
                                    @case('gender') Opsional; Unisex, Pria, atau Wanita @break
                                    @case('stok_snapshot') Opsional; bilangan bulat 0–999999 @break
                                    @case('terlaris') Opsional; ya/tidak, true/false, atau 1/0 @break
                                    @case('top_notes') @case('middle_notes') @case('base_notes') Opsional; pisahkan nilai dengan | @break
                                    @default Opsional; kosong berarti pertahankan nilai current
                                @endswitch
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if($preview)
        <section class="mt-6" aria-labelledby="maintenance-preview-title">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-emerald-700">Batch maintenance #{{ $batch->id }} tersimpan</p>
                    <h2 id="maintenance-preview-title" class="mt-1 text-2xl font-bold text-gray-900">3. Hasil preview read-only</h2>
                </div>
                <div class="space-y-1 font-mono text-[11px] text-gray-500 sm:text-right">
                    <p class="break-all">File: {{ $preview['fingerprint'] }}</p>
                    <p class="break-all">State: {{ $preview['catalog_state_fingerprint'] }}</p>
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
                <table class="min-w-[80rem] divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3">Baris</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Perubahan</th><th class="px-4 py-3">Masalah / recovery</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top text-gray-700">
                        @foreach($preview['rows'] as $row)
                            @php($changes = $row['changes'] ?? ($row['data']['_changes'] ?? []))
                            <tr>
                                <td class="px-4 py-4 font-semibold text-gray-900">{{ $row['line_number'] }}</td>
                                <td class="px-4 py-4">
                                    @php($statusClasses = ['valid' => 'bg-emerald-50 text-emerald-700', 'review' => 'bg-amber-50 text-amber-700', 'error' => 'bg-red-50 text-red-700'])
                                    @php($statusLabels = ['valid' => 'Valid', 'review' => 'Perlu review', 'error' => 'Error'])
                                    <span class="inline-flex rounded px-2 py-1 text-xs font-semibold {{ $statusClasses[$row['status']] }}">{{ $statusLabels[$row['status']] }}</span>
                                </td>
                                <td class="max-w-xs px-4 py-4">
                                    @if($row['matched_product'])
                                        <p class="font-semibold text-gray-900">#{{ $row['matched_product']['id'] }} {{ $row['matched_product']['name'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $row['matched_product']['publication_status'] }}</p>
                                    @else
                                        <p class="font-semibold text-red-700">ID {{ $row['data']['product_id'] ?? 'tidak valid' }}</p>
                                    @endif
                                </td>
                                <td class="min-w-[28rem] px-4 py-4">
                                    @if($changes)
                                        <ul class="space-y-3">
                                            @foreach($changes as $change)
                                                @php($currentValue = is_array($change['current']) ? implode(' | ', $change['current']) : $change['current'])
                                                @php($proposedValue = is_array($change['proposed']) ? implode(' | ', $change['proposed']) : $change['proposed'])
                                                <li>
                                                    <p class="text-xs font-bold text-gray-900">{{ $change['label'] }}</p>
                                                    <p class="mt-1 text-xs leading-relaxed text-gray-500">Current: {{ \Illuminate\Support\Str::limit((string) ($currentValue ?? 'Kosong'), 120) }}</p>
                                                    <p class="mt-1 text-xs leading-relaxed text-gray-900">Usulan: {{ \Illuminate\Support\Str::limit((string) ($proposedValue ?? 'Kosong'), 120) }}</p>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-xs text-gray-500">Tidak ada perubahan yang dapat diterapkan.</span>
                                    @endif
                                </td>
                                <td class="max-w-md px-4 py-4">
                                    @if($row['issues'])
                                        <ul class="space-y-2">
                                            @foreach($row['issues'] as $issue)
                                                <li class="text-xs leading-relaxed {{ $issue['severity'] === 'error' ? 'text-red-700' : 'text-amber-700' }}"><span class="font-semibold">{{ $issue['field'] }}:</span> {{ $issue['message'] }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-xs text-emerald-700">Siap untuk tahap apply terpisah.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700" role="status">
                <p class="font-bold">Preview selesai—katalog belum berubah.</p>
                <p class="mt-1">Tahap apply transactional akan dibuat sebagai backlog terpisah setelah kontrak preview ini terbukti aman.</p>
            </div>
        </section>
    @endif

    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="maintenance-history-title">
        <h2 id="maintenance-history-title" class="text-lg font-bold text-gray-900">Preview maintenance terbaru</h2>
        <p class="mt-1 text-sm text-gray-500">Riwayat ini terpisah dari batch import provider.</p>

        @if($recentBatches->isEmpty())
            <div class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">Belum ada batch maintenance tersimpan.</div>
        @else
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-[50rem] divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Batch</th><th class="px-4 py-3">File</th><th class="px-4 py-3">Actor</th><th class="px-4 py-3">Ringkasan</th><th class="px-4 py-3">Waktu</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @foreach($recentBatches as $recentBatch)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900"><a href="{{ route('admin.product-maintenance.create', ['batch' => $recentBatch->id]) }}" class="underline decoration-gray-300 underline-offset-2 hover:decoration-gray-900">#{{ $recentBatch->id }}</a></td>
                                <td class="max-w-xs px-4 py-3">{{ $recentBatch->source_filename }}</td>
                                <td class="px-4 py-3">{{ $recentBatch->actor?->name ?? 'Unknown' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $recentBatch->valid_rows }} valid · {{ $recentBatch->review_rows }} review · {{ $recentBatch->error_rows }} error</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $recentBatch->created_at?->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
