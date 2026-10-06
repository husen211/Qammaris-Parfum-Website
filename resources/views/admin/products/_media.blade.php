@php
    $imageCount = $product->images->count();
    $remainingSlots = max(0, \App\Models\ProductImage::MAX_PER_PRODUCT - $imageCount);
@endphp

<div class="bg-white p-5 sm:p-6 rounded-xl shadow-sm border border-gray-200">
    <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Foto Produk</h3>
            <p class="mt-1 text-xs leading-relaxed text-gray-500">Foto pertama tampil sebagai cover katalog. Maksimum tiga foto per produk.</p>
        </div>
        <span class="shrink-0 text-sm font-semibold text-gray-700">{{ $imageCount }}/{{ \App\Models\ProductImage::MAX_PER_PRODUCT }}</span>
    </div>

    @if($product->images->isEmpty())
        <div class="my-5 rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center">
            <p class="text-sm font-semibold text-gray-900">Belum ada foto produk</p>
            <p class="mt-1 text-xs text-gray-500">Upload foto pertama untuk menjadikannya cover secara otomatis.</p>
        </div>
    @else
        <div class="my-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach($product->images as $image)
                @php
                    $primaryFormId = 'primary-image-'.$image->id;
                    $moveUpFormId = 'move-up-image-'.$image->id;
                    $moveDownFormId = 'move-down-image-'.$image->id;
                    $archiveFormId = 'archive-image-'.$image->id;
                    $archiveBlocked = $editingPublished && $imageCount === 1;
                @endphp
                <article class="overflow-hidden rounded-lg border {{ $image->is_primary ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-gray-200' }} bg-white">
                    <div class="relative aspect-square bg-gray-100">
                        <img src="{{ $image->image_url }}" alt="Foto {{ $loop->iteration }} untuk {{ $product->name }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                        <span class="absolute left-2 top-2 rounded bg-white/95 px-2 py-1 text-xs font-semibold text-gray-800 shadow-sm">
                            {{ $image->is_primary ? 'Foto utama' : 'Foto tambahan' }}
                        </span>
                    </div>

                    <div class="space-y-3 p-3">
                        <div class="flex items-center justify-between gap-3 text-xs text-gray-500">
                            <span>Urutan {{ $loop->iteration }} dari {{ $imageCount }}</span>
                            @if($image->is_primary)<span class="font-semibold text-emerald-700">Cover katalog</span>@endif
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button type="submit" form="{{ $primaryFormId }}" @disabled($image->is_primary)
                                class="min-h-10 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400">
                                {{ $image->is_primary ? 'Sudah utama' : 'Jadikan utama' }}
                            </button>
                            <button type="submit" form="{{ $archiveFormId }}" @disabled($archiveBlocked)
                                class="min-h-10 rounded-lg border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400">
                                Arsipkan foto
                            </button>
                            <button type="submit" form="{{ $moveUpFormId }}" @disabled($loop->first)
                                class="min-h-10 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-300"
                                aria-label="Naikkan urutan foto {{ $loop->iteration }}">↑ Naik</button>
                            <button type="submit" form="{{ $moveDownFormId }}" @disabled($loop->last)
                                class="min-h-10 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-300"
                                aria-label="Turunkan urutan foto {{ $loop->iteration }}">↓ Turun</button>
                        </div>

                        @if($archiveBlocked)
                            <p class="text-xs leading-relaxed text-amber-700">Produk tayang harus mempunyai minimal satu foto. Upload pengganti terlebih dahulu.</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="border-t border-gray-100 pt-4">
        @if($remainingSlots > 0)
            <label for="new-images" class="block text-sm font-semibold text-gray-800">Tambah foto</label>
            <input id="new-images" type="file" name="new_images[]" accept="image/jpeg,image/png,image/webp" @if($remainingSlots > 1) multiple @endif
                class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 @error('new_images') rounded-lg border border-red-300 p-2 @enderror"
                @error('new_images') aria-describedby="new-images-error" aria-invalid="true" @enderror>
            <p class="mt-2 text-xs text-gray-500">Tersisa {{ $remainingSlots }} slot. PNG, JPG, atau WebP; maksimum 2 MB per foto.</p>
            @error('new_images')
                <p id="new-images-error" class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('new_images.*')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        @else
            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Galeri sudah penuh. Arsipkan salah satu foto sebelum menambahkan foto baru.
            </div>
        @endif

        <p class="mt-3 text-xs leading-relaxed text-gray-400">Foto yang diarsipkan keluar dari galeri aktif, tetapi file tetap disimpan untuk recovery.</p>
    </div>
</div>
