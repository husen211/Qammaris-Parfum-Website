<div class="flex items-center gap-1">
    <a href="{{ route('admin.products.edit', ['product' => $product->id, 'return_to' => $catalogReturnPath]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-black focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2" title="Edit" aria-label="Edit {{ $product->name }}">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
    </a>

    @if($product->publication_status === \App\Models\Product::PUBLICATION_PUBLISHED)
        <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Arsipkan produk ini? Produk akan disembunyikan dari katalog, tetapi data dan gambar tetap tersimpan.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-amber-50 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-700 focus:ring-offset-2" title="Arsipkan produk" aria-label="Arsipkan {{ $product->name }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M10 12h4m-9 8h14a2 2 0 002-2V8l-2-4H5L3 8v10a2 2 0 002 2z"/></svg>
            </button>
        </form>
    @elseif($product->publication_status === \App\Models\Product::PUBLICATION_ARCHIVED)
        <form action="{{ route('admin.products.restore', $product->id) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="return_to" value="{{ $catalogReturnPath }}">
            <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" title="Aktifkan kembali" aria-label="Aktifkan kembali {{ $product->name }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </form>
    @endif
</div>
