@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl">
    <nav class="mb-6 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('admin.products.index') }}" class="hover:text-gray-900">Products</a>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="font-medium text-gray-800">Import Produk</span>
    </nav>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-amber-700">Preview saja · belum menulis data</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Import Produk</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">Upload CSV hasil kurasi Claude. Sistem memeriksa struktur, data produk, taxonomy, dan mapping kode provider sebelum tahap apply tersedia.</p>
        </div>
        <a href="{{ route('admin.product-imports.template') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">
            Unduh template CSV
        </a>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="upload-title">
            <h2 id="upload-title" class="text-lg font-bold text-gray-900">1. Pilih file untuk preview</h2>
            <p class="mt-1 text-sm text-gray-500">CSV UTF-8, maksimum 5 MB dan 1.000 baris. File tidak disimpan setelah request selesai.</p>

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
                <li><span class="font-semibold text-gray-900">4.</span> Apply akan dibuat pada tahap berikutnya.</li>
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
                                    @case('foto_utama_url') @case('foto_2_url') @case('foto_3_url') Opsional; URL HTTPS sumber, belum diunduh @break
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
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-emerald-700">File berhasil dibaca</p>
                    <h2 id="preview-title" class="mt-1 text-2xl font-bold text-gray-900">3. Hasil preview</h2>
                </div>
                <p class="break-all font-mono text-[11px] text-gray-500">SHA-256: {{ $preview['fingerprint'] }}</p>
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
                <table class="min-w-[70rem] divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Baris</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Aksi kandidat</th>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3">Harga / ukuran</th>
                            <th class="px-4 py-3">Brand / kategori</th>
                            <th class="px-4 py-3">Masalah</th>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">Belum ada tombol apply. Preview ini tidak mengubah database atau media.</div>
        </section>
    @endif
</div>
@endsection
