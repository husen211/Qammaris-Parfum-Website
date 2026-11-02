{{-- CAT-MEDIA-PASTE-01: one photo picker for add and edit; Ctrl+V, drop or file. resources/js/ui/product-photo-paste.js --}}
<div data-photo-paste data-max-new="{{ $slots }}" data-existing="{{ $existing }}">
    <label for="{{ $inputId }}" class="block text-sm font-semibold text-gray-800">Tambah foto</label>
    <div data-photo-dropzone tabindex="0" aria-describedby="{{ $inputId }}-hint"
        class="mt-2 rounded-xl border-2 border-dashed border-gray-300 px-4 py-5 text-center transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-black data-[dragging]:border-black data-[dragging]:bg-gray-50 @error($errorKey) border-red-300 @enderror">
        <p class="text-sm font-semibold text-gray-900">Tempel foto dengan Ctrl+V</p>
        <p id="{{ $inputId }}-hint" class="mt-1 text-xs leading-relaxed text-gray-500">Di browser: klik kanan foto → <span class="font-medium text-gray-700">Copy image</span>, lalu tekan Ctrl+V di halaman ini. Bisa juga seret foto ke sini.</p>
        <label class="mt-3 inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 focus-within:ring-2 focus-within:ring-black">
            Pilih file
            <input id="{{ $inputId }}" type="file" name="{{ $inputName }}" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-photo-input
                @error($errorKey) aria-describedby="{{ $inputId }}-error" aria-invalid="true" @enderror>
        </label>
    </div>
    <p class="mt-2 text-xs text-gray-500">Tersisa <span data-photo-slots>{{ $slots }}</span> slot. Foto otomatis dirapikan: sisi terpanjang maks. 2000 px, di bawah 2 MB.</p>
    <p data-photo-status role="status" aria-live="polite" class="mt-1 min-h-4 text-xs text-gray-600"></p>
    @error($errorKey)
        <p id="{{ $inputId }}-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error($errorKey.'.*')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
    <ul data-photo-previews class="mt-3 grid grid-cols-3 gap-2 empty:hidden" aria-label="Foto baru yang belum disimpan"></ul>
</div>
