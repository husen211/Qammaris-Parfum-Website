@foreach($product->images as $image)
    <form id="primary-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.primary', ['product' => $product, 'productImage' => $image]) }}" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="{{ $catalogReturnPath }}">
    </form>
    <form id="move-up-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.move', ['product' => $product, 'productImage' => $image]) }}" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="{{ $catalogReturnPath }}">
        <input type="hidden" name="direction" value="up">
    </form>
    <form id="move-down-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.move', ['product' => $product, 'productImage' => $image]) }}" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="{{ $catalogReturnPath }}">
        <input type="hidden" name="direction" value="down">
    </form>
    <form id="archive-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.destroy', ['product' => $product, 'productImage' => $image]) }}" class="hidden"
        onsubmit="return confirm('Arsipkan foto ini dari galeri? File tetap disimpan untuk recovery.')">
        @csrf
        @method('DELETE')
        <input type="hidden" name="return_to" value="{{ $catalogReturnPath }}">
    </form>
@endforeach
