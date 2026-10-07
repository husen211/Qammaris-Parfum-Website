@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Impor Shopee</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">Lengkapi produk dari Qammaris App dengan deskripsi dan foto Shopee. Harga dan ketersediaan tetap mengikuti aplikasi.</p>
        </div>
        <a href="{{ route('admin.product-imports.create') }}" class="inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4">Impor CSV lainnya</a>
    </div>

    @if($errors->any())
        <div role="alert" class="border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Impor belum dilanjutkan</p>
            <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="border border-gray-200 bg-white p-4 sm:p-6" aria-labelledby="upload-title">
        <h2 id="upload-title" class="text-lg font-semibold">1. Upload file Shopee</h2>
        <p class="mt-1 text-sm leading-relaxed text-gray-600">Download ekspor <strong>Informasi Dasar</strong> dan <strong>Media</strong> untuk pilihan produk yang sama. Tidak perlu diubah ke CSV. Maksimum 8 MB per file dan 1.000 produk.</p>
        <form method="POST" action="{{ route('admin.shopee-imports.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4" data-shopee-form>
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div class="min-w-0"><label for="basic-file" class="block text-sm font-medium">Informasi Dasar (.xlsx)</label><p id="basic-help" class="mb-2 mt-1 text-xs text-gray-500">Berisi kode produk, nama dan deskripsi.</p><input id="basic-file" name="basic_file" type="file" accept=".xlsx" required aria-describedby="basic-help" class="block w-full min-w-0 rounded border border-gray-300 bg-white p-2 text-sm focus:ring-2 focus:ring-black"></div>
                <div class="min-w-0"><label for="media-file" class="block text-sm font-medium">Media (.xlsx)</label><p id="media-help" class="mb-2 mt-1 text-xs text-gray-500">Berisi sampul dan foto produk 1–2.</p><input id="media-file" name="media_file" type="file" accept=".xlsx" required aria-describedby="media-help" class="block w-full min-w-0 rounded border border-gray-300 bg-white p-2 text-sm focus:ring-2 focus:ring-black"></div>
            </div>
            <button type="submit" class="min-h-11 rounded bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white focus:ring-2 focus:ring-black focus:ring-offset-2 disabled:opacity-50">Periksa file</button>
            <p class="text-xs text-gray-500">Pemeriksaan belum mengubah produk. Foto diunduh ke storage website setelah Anda menerapkan hasilnya.</p>
        </form>
    </section>

    @if($batch)
        <section aria-labelledby="results-title" class="space-y-4">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div><h2 id="results-title" class="text-lg font-semibold">2. Periksa produk</h2><p class="mt-1 text-sm text-gray-600">{{ $batch->total_rows }} produk · {{ $batch->valid_rows }} dikenali · {{ $batch->review_rows }} perlu dipilih</p></div>
                <a href="{{ route('admin.shopee-imports.index', ['batch' => $batch->id, 'page' => $rows->currentPage(), 'filter' => $filter, 'search' => $search]) }}" class="inline-flex min-h-11 items-center font-medium text-sm underline underline-offset-4">Muat ulang hasil</a>
            </div>
            <p class="text-sm text-gray-600">Deskripsi lama tetap dipakai, kecuali Anda memilih untuk menggantinya. Foto baru ditambahkan sampai maksimum tiga; sampul/foto lama tidak dihapus. Produk yang belum lengkap tetap draft.</p>
            <p class="text-sm font-medium">{{ $workCount }} produk perlu ditangani · {{ $completeCount }} sudah lengkap. Produk lengkap disembunyikan dari daftar utama; tetap tersedia lewat filter.</p>
            <form method="POST" action="{{ route('admin.shopee-imports.refresh', [$batch->id, 'filter' => $filter, 'search' => $search]) }}" class="border-l-2 border-gray-300 bg-white p-4" data-shopee-form>
                @csrf<input type="hidden" name="confirm" value="1">
                <p class="text-sm text-gray-600">{{ $needsRefresh ? $needsRefresh.' baris memakai pemeriksaan lama atau perlu diperiksa ulang. ' : '' }}Harga/stok berubah tidak membatalkan impor konten. Periksa ulang jika konten produk berubah; hasil baru tetap harus dilihat sebelum diterapkan.</p>
                <button type="submit" class="mt-2 min-h-11 rounded border border-gray-900 px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-black">Periksa ulang perubahan</button>
                <p class="mt-1 text-xs text-gray-500">Tidak mengubah produk, mengunduh foto, atau menerbitkan.</p>
            </form>
            <form method="GET" action="{{ route('admin.shopee-imports.index') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                <input type="hidden" name="batch" value="{{ $batch->id }}">
                <div><label for="source-search" class="sr-only">Cari produk di file</label><input id="source-search" type="search" name="search" value="{{ $search }}" placeholder="Cari produk di file" class="min-h-11 w-full min-w-0 rounded border border-gray-300 p-2.5 text-sm focus:ring-2 focus:ring-black"></div>
                <div><label for="source-filter" class="sr-only">Tampilkan hasil</label><select id="source-filter" name="filter" class="min-h-11 w-full rounded border border-gray-300 p-2.5 text-sm focus:ring-2 focus:ring-black"><option value="work" @selected($filter === 'work')>Perlu ditangani ({{ $workCount }})</option><option value="review" @selected($filter === 'review')>Perlu dipilih / diperiksa ulang</option><option value="ready" @selected($filter === 'ready')>Siap terbit</option><option value="images_failed" @selected($filter === 'images_failed')>Foto bermasalah</option><option value="complete" @selected($filter === 'complete')>Sudah lengkap ({{ $completeCount }})</option><option value="all" @selected($filter === 'all')>Semua hasil</option></select></div>
                <button type="submit" class="min-h-11 rounded border border-gray-900 px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-black">Tampilkan</button>
            </form>
            @if($rows->isEmpty())<p role="status" class="border border-gray-200 bg-white p-5 text-sm text-gray-600">Tidak ada produk untuk pencarian/filter ini. Pilih Semua hasil atau kosongkan pencarian.</p>@endif

            @foreach($rows as $row)
                @php
                    $data = $row->normalized_data;
                    $summary = $summaries->get($row->id);
                    $product = $summary['product'];
                    $applied = $summary['applied'];
                    $held = $summary['held'];
                    $sourceMl = $summary['source_ml'];
                    $websiteMl = $summary['website_ml'];
                    $mismatch = $summary['size_mismatch'];
                    $blockers = $summary['blockers'];
                @endphp
                <article class="min-w-0 border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="row-{{ $row->id }}-title">
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-between">
                        <div class="min-w-0"><h3 id="row-{{ $row->id }}-title" class="break-words font-semibold text-gray-900">{{ $data['source']['name'] }}</h3><p class="mt-1 text-xs text-gray-500">Kode Shopee {{ $row->external_product_id }}</p></div>
                        <p class="text-sm font-medium {{ $summary['status_class'] }}">{{ $summary['status'] }}</p>
                    </div>
                    @if($held)<p role="alert" class="mt-3 border-l-2 border-amber-600 bg-amber-50 p-3 text-sm text-amber-900">{{ $row->apply_message }}</p>@endif
                    @if($row->apply_status === 'skipped_no_changes')<p class="mt-2 text-sm text-gray-600">Sudah lengkap untuk impor ini; tidak ada perubahan konten atau unduhan ulang.</p>@endif
                    @if($product)
                        <p class="mt-3 break-words text-sm">Website: <strong>{{ $product->name }}</strong> · {{ $product->brand?->name }} @if($product->variants->first()) · {{ $product->variants->first()->volume }} ml @endif · Rp {{ number_format((float) $product->base_price, 0, ',', '.') }}</p>
                        @if($product->images->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-3" aria-label="Foto yang tersimpan di website">
                                @foreach($product->images as $image)<figure><img src="{{ $image->image_url }}" alt="{{ $product->name }} — {{ $image->is_primary ? 'sampul' : 'foto tambahan' }}" width="80" height="80" loading="lazy" class="h-20 w-20 border border-gray-200 bg-white object-contain p-1"><figcaption class="mt-1 text-xs text-gray-500">{{ $image->is_primary ? 'Sampul' : 'Foto tambahan' }}</figcaption></figure>@endforeach
                            </div>
                        @endif
                    @endif
                    @if(!$applied && $row->matched_product_id && $product)
                        <p class="mt-2 text-sm text-gray-600">Yang dilengkapi: {{ $summary['field_labels'] }}{{ $data['offer_ml'] ? ' · ukuran '.$data['offer_ml'].' ml' : '' }} · {{ $summary['photo_count'] }} foto baru.</p>
                        @if(isset($data['fields']['gender']))<p class="mt-1 text-sm text-gray-600">Peruntukan: {{ $data['fields']['gender'] }}</p>@endif
                        @if(isset($data['fields']['category_id']))<p class="mt-1 text-sm text-gray-600">Kategori: {{ $categories->get($data['fields']['category_id'])?->name }}</p>@endif
                    @endif
                    @if($data['issues'])<ul class="mt-3 space-y-1 text-sm text-gray-600">@foreach($data['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
                    @if($applied)
                        @if($summary['waiting'])<p class="mt-3 text-sm text-gray-600" role="status">Foto sedang diunduh. Muat ulang hasil sebentar lagi.</p>@endif
                        @foreach($summary['photo_problems'] as $outcome)
                            <p class="mt-2 text-sm text-red-700">{{ $outcome['message'] }}</p>
                        @endforeach
                        @if($summary['media_review'])
                            <div role="status" class="mt-3 border-l-2 border-amber-600 bg-amber-50 p-3 text-sm text-amber-900">
                                <p class="font-medium">Periksa foto sebelum melanjutkan</p>
                                <p class="mt-1">Mengulang unduhan tidak menyelesaikan perubahan produk atau foto. Periksa foto di editor; untuk usulan baru, upload kembali kedua ekspor Shopee. Foto yang tersimpan tetap dipertahankan.</p>
                            </div>
                        @elseif($summary['retry_images'])
                            <p class="mt-2 text-sm text-gray-600">Coba unduh foto lagi melalui tombol di bawah, atau upload manual di editor. Foto sampul diproses sebelum foto tambahan.</p>
                        @endif
                        @if($blockers)<p class="mt-3 text-sm text-amber-800">Belum lengkap: {{ implode(', ', array_values($blockers)) }}</p>@endif
                        @if($product)<a class="mt-2 inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4" href="{{ route('admin.products.edit', [$product->id, 'return_to' => route('admin.shopee-imports.index', ['batch' => $batch->id, 'page' => $rows->currentPage()], false)]) }}">Lengkapi / upload foto manual</a>@endif
                    @else
                        <details class="mt-3" @if($mismatch && !$row->matched_product_id) open @endif>
                            <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium underline underline-offset-4">{{ $product ? 'Periksa pilihan / ganti deskripsi' : 'Pilih produk website' }}</summary>
                            <form method="POST" action="{{ route('admin.shopee-imports.choose', [$batch->id, $row->id, 'filter' => $filter, 'search' => $search, 'page' => $rows->currentPage()]) }}" class="mt-2 space-y-3" data-shopee-form data-source-ml="{{ $sourceMl }}">
                                @csrf
                                <label for="target-{{ $row->id }}" class="block text-sm font-medium">Produk dari Qammaris App</label>
                                <label for="target-search-{{ $row->id }}" class="sr-only">Cari pilihan produk website</label><input id="target-search-{{ $row->id }}" type="search" placeholder="Ketik nama untuk mempersempit pilihan" data-target-search="target-{{ $row->id }}" class="min-h-11 w-full min-w-0 rounded border border-gray-300 p-2.5 text-sm focus:ring-2 focus:ring-black">
                                <select id="target-{{ $row->id }}" name="product_id" required class="min-h-11 w-full min-w-0 rounded border border-gray-300 p-2.5 text-sm focus:ring-2 focus:ring-black">
                                    <option value="">Pilih produk yang sesuai</option>
                                    @foreach($products as $candidate)<option value="{{ $candidate->id }}" data-volume="{{ $candidate->variants->count() === 1 ? $candidate->variants->first()->volume : '' }}" @selected(($row->matched_product_id ?? $data['selected_product_id'] ?? null) === $candidate->id)>{{ $candidate->name }} · {{ $candidate->brand?->name }}{{ $candidate->variants->first() ? ' · '.$candidate->variants->first()->volume.' ml' : '' }}</option>@endforeach
                                </select>
                                <div data-size-confirmation @if(!$mismatch) hidden @endif class="border-l-2 border-amber-600 bg-amber-50 p-3 text-sm text-amber-900">
                                    <p data-size-comparison>Shopee: {{ $sourceMl }} ml · Website: {{ $websiteMl }} ml.</p>
                                    <label class="mt-2 flex min-h-11 items-start gap-3"><input type="checkbox" name="confirm_size_mismatch" value="1" class="mt-1" @checked(($data['confirmed_website_ml'] ?? null) !== null)><span data-size-label>Ukuran di Shopee salah, gunakan {{ $websiteMl }} ml di website.</span></label>
                                    <input type="hidden" name="confirmed_website_ml" value="{{ $mismatch ? $websiteMl : '' }}">
                                    <p>Centang hanya jika foto memang produk yang sama. Ukuran/harga tetap. Deskripsi Shopee yang menyebut ukuran berbeda tidak diimpor; periksa di editor.</p>
                                </div>
                                <label class="flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" name="replace_description" value="1" class="mt-1" @checked($data['replace_description']) @disabled($mismatch)><span>Gunakan deskripsi Shopee untuk mengganti deskripsi website, hanya jika ukuran sama. Perubahan ditampilkan dahulu; belum disimpan ke produk.</span></label>
                                <button type="submit" class="min-h-11 rounded border border-gray-900 px-4 py-2 text-sm font-semibold focus:ring-2 focus:ring-black">Perbarui pilihan</button>
                            </form>
                        </details>
                        @if($product)<a class="mt-2 inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4" href="{{ route('admin.products.edit', [$product->id, 'return_to' => route('admin.shopee-imports.index', ['batch' => $batch->id, 'filter' => $filter, 'search' => $search, 'page' => $rows->currentPage()], false)]) }}">Buka editor produk</a>@endif
                    @endif
                    <details class="mt-2"><summary class="flex min-h-11 cursor-pointer items-center text-sm text-gray-600">Lihat deskripsi {{ isset($data['fields']['description']) ? 'yang akan dipakai' : 'dari Shopee' }}</summary><p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-gray-700">{{ $summary['description'] }}</p></details>
                </article>
            @endforeach
            {{ $rows->links() }}

            <section class="border-t border-gray-300 pt-5" aria-labelledby="apply-title">
                <h2 id="apply-title" class="text-lg font-semibold">3. Lengkapi produk</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $pendingCount }} produk dengan perubahan dari seluruh halaman akan dilengkapi. Produk lengkap tidak diubah. Baris yang belum dipilih atau berubah sejak pemeriksaan ditahan, tanpa membatalkan produk lain. Tidak ada publikasi otomatis.</p>
                <form method="POST" action="{{ route('admin.shopee-imports.apply', $batch->id) }}" class="mt-3 space-y-3" data-shopee-form>
                    @csrf
                    <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required @disabled($pendingCount === 0)><span>Saya sudah memeriksa {{ $pendingCount }} produk dan perubahan di atas.</span></label>
                    <button type="submit" @disabled($pendingCount === 0) class="min-h-11 rounded bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white focus:ring-2 focus:ring-black focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">Terapkan {{ $pendingCount }} produk</button>
                </form>
                @if($batch->status === 'applied' && $retryImageCount > 0)
                    <form method="POST" action="{{ route('admin.shopee-imports.images', $batch->id) }}" class="mt-4" data-shopee-form>@csrf<input type="hidden" name="confirm" value="1"><button type="submit" class="min-h-11 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-black">Coba unduh foto lagi</button><p class="mt-1 text-xs text-gray-500">{{ $retryImageCount }} produk dengan unduhan gagal dapat dicoba lagi. Foto yang sudah berhasil tidak diunduh ulang atau digandakan; foto yang perlu diperiksa tetap ditahan.</p></form>
                @endif
                @if($mediaReviewCount > 0)
                    <p class="mt-4 text-sm text-amber-800">{{ $mediaReviewCount }} produk perlu diperiksa fotonya di editor atau melalui upload kedua ekspor yang baru. Tombol Periksa ulang perubahan hanya memperbarui usulan konten yang belum selesai; tidak memulihkan foto yang tertahan pada hasil impor lama.</p>
                @endif
            </section>
            @if(count($ready))
                <section class="border-t border-gray-300 pt-5" aria-labelledby="publish-title">
                    <h2 id="publish-title" class="text-lg font-semibold">4. Terbitkan produk lengkap</h2>
                    <form method="POST" action="{{ route('admin.shopee-imports.publish', $batch->id) }}" class="mt-3 space-y-3" data-shopee-form>
                        @csrf
                        <fieldset><legend class="mb-2 text-sm text-gray-600">{{ count($ready) }} produk siap terbit. Pilih yang ingin ditampilkan di katalog.</legend>
                            <div class="mb-2 flex flex-wrap gap-2"><button type="button" data-select-ready="all" class="min-h-11 rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-black">Pilih semua</button><button type="button" data-select-ready="none" class="min-h-11 rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-black">Kosongkan pilihan</button></div>
                            <div class="max-h-72 overflow-y-auto border border-gray-200 p-3">
                            @foreach($ready as $r)<label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="rows[]" value="{{ $r->id }}"><span class="break-words">{{ $r->appliedProduct->name }}</span></label>@endforeach
                            </div>
                            <p class="mt-2 text-sm text-gray-600" role="status" aria-live="polite" data-ready-count>0 produk dipilih.</p>
                        </fieldset>
                        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required><span>Terbitkan hanya produk yang saya pilih.</span></label>
                        <button type="submit" class="min-h-11 rounded bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white focus:ring-2 focus:ring-black focus:ring-offset-2">Terbitkan pilihan</button>
                    </form>
                </section>
            @endif
        </section>
    @else
        <p class="text-sm text-gray-600">Belum ada hasil pemeriksaan. Upload kedua file untuk memulai. Foto manual tetap dapat ditambahkan lewat editor produk.</p>
    @endif

    @if($recent->isNotEmpty())
        <section aria-labelledby="history-title" class="border-t border-gray-200 pt-5"><h2 id="history-title" class="text-base font-semibold">Impor terakhir Anda</h2><ul class="mt-2 divide-y divide-gray-200">@foreach($recent as $item)<li><a href="{{ route('admin.shopee-imports.index', ['batch' => $item->id]) }}" class="flex min-h-11 flex-wrap items-center justify-between gap-2 py-3 text-sm underline underline-offset-4"><span>{{ $item->created_at->format('d M Y H:i') }} · {{ $item->total_rows }} produk</span><span>{{ $item->status === 'applied' ? 'Sudah diterapkan' : 'Menunggu pemeriksaan' }}</span></a></li>@endforeach</ul></section>
    @endif
    <p class="sr-only" role="status" aria-live="polite" data-shopee-status></p>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-shopee-form]').forEach(form => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.disabled = true;
            button.dataset.previousText = button.textContent;
            button.textContent = 'Sedang diproses…';
        });
        form.setAttribute('aria-busy', 'true');
        document.querySelector('[data-shopee-status]').textContent = 'Sedang memproses. Tunggu hasilnya.';
    });
});
document.querySelectorAll('form[data-source-ml]').forEach(form => {
    const select = form.querySelector('select[name="product_id"]');
    const box = form.querySelector('[data-size-confirmation]');
    const checkbox = form.querySelector('[name="confirm_size_mismatch"]');
    const volume = form.querySelector('[name="confirmed_website_ml"]');
    const updateSize = (reset) => {
        const websiteMl = Number(select.selectedOptions[0]?.dataset.volume || 0);
        const sourceMl = Number(form.dataset.sourceMl || 0);
        const differs = sourceMl > 0 && websiteMl > 0 && sourceMl !== websiteMl;
        box.hidden = !differs;
        checkbox.disabled = !differs;
        form.querySelector('[name="replace_description"]').disabled = differs;
        if (reset || !differs) checkbox.checked = false;
        volume.value = differs ? String(websiteMl) : '';
        box.querySelector('[data-size-comparison]').textContent = `Shopee: ${sourceMl} ml · Website: ${websiteMl} ml.`;
        box.querySelector('[data-size-label]').textContent = `Ukuran di Shopee salah, gunakan ${websiteMl} ml di website.`;
    };
    select.addEventListener('change', () => updateSize(true));
    updateSize(false);
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-previous-text]').forEach(button => {
        button.disabled = false;
        button.textContent = button.dataset.previousText;
        delete button.dataset.previousText;
    });
    document.querySelectorAll('[data-shopee-form]').forEach(form => form.removeAttribute('aria-busy'));
});
document.querySelectorAll('[data-select-ready]').forEach(button => {
    button.addEventListener('click', () => {
        button.form.querySelectorAll('input[name="rows[]"]').forEach(input => { input.checked = button.dataset.selectReady === 'all'; });
        updateReadyCount(button.form);
    });
});
function updateReadyCount(form) {
    const status = form.querySelector('[data-ready-count]');
    if (status) status.textContent = `${form.querySelectorAll('input[name="rows[]"]:checked').length} produk dipilih.`;
}
document.querySelectorAll('input[name="rows[]"]').forEach(input => input.addEventListener('change', () => updateReadyCount(input.form)));
</script>
@endpush
