{{-- CAT-TEXT-PASTE-01: pasted marketplace text → notes, gender and size after confirmation. resources/js/ui/product-text-paste.js --}}
<div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-gray-500">
    <span>Tempel deskripsi dari Shopee/Tokopedia: notes, gender, dan ukuran bisa langsung dipisah.</span>
    <button type="button" data-text-paste-run class="inline-flex min-h-11 items-center font-semibold text-gray-700 underline">Pisahkan dari deskripsi</button>
</div>
<div data-text-paste-panel hidden class="mt-1 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm" role="region" aria-label="Hasil dari teks yang ditempel">
    <p class="font-semibold text-gray-900">Ditemukan dari teks</p>
    <dl data-text-paste-found class="mt-2 space-y-1 text-xs"></dl>
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" data-text-paste-apply class="min-h-11 rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-black focus-visible:outline-2 focus-visible:outline-offset-2">Isi ke kolom</button>
        <button type="button" data-text-paste-dismiss class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2">Abaikan</button>
    </div>
</div>
<p data-text-paste-status role="status" aria-live="polite" class="text-xs text-gray-600"></p>
