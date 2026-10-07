<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Services\ProductMediaStorage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class EvaluateProductPublicationReadiness
{
    public function __construct(private ProductMediaStorage $productMediaStorage) {}

    /**
     * @return array<string, string>
     */
    public function handle(Product $product): array
    {
        $slugInUse = trim((string) $product->slug) !== ''
            && Product::query()->where('slug', $product->slug)->whereKeyNot($product->getKey())->exists();

        return $this->blockers($product, $slugInUse);
    }

    /** Read-only review snapshot; mutations must call handle() again. */
    public function forReview(Collection $products): Collection
    {
        $products = new EloquentCollection($products->unique('id')->values()->all());
        $products->loadMissing(['brand', 'category', 'variants', 'images']);
        $slugs = $products->pluck('slug')->filter(fn ($slug) => trim((string) $slug) !== '')->unique();
        $duplicates = $slugs->isEmpty() ? collect() : Product::query()->whereIn('slug', $slugs)
            ->select('slug')->groupBy('slug')->havingRaw('COUNT(*) > 1')->pluck('slug');

        return $products->mapWithKeys(fn (Product $product) => [
            $product->id => $this->blockers($product, $duplicates->contains($product->slug)),
        ]);
    }

    private function blockers(Product $product, bool $slugInUse): array
    {
        $product->loadMissing(['brand', 'category', 'variants', 'images']);
        $blockers = [];

        if (trim((string) $product->name) === '') {
            $blockers['name'] = 'Nama produk belum diisi.';
        }

        if (trim((string) $product->slug) === ''
            || $slugInUse) {
            $blockers['slug'] = 'Slug produk belum valid atau sudah digunakan.';
        }

        if (! $product->brand || ! $product->brand->is_active) {
            $blockers['brand'] = 'Brand belum dipilih atau sudah nonaktif.';
        }

        if (! $product->category || ! $product->category->is_active) {
            $blockers['category'] = 'Kategori belum dipilih atau sudah nonaktif.';
        }

        if (trim((string) $product->description) === '') {
            $blockers['description'] = 'Deskripsi produk belum diisi.';
        }

        if (! in_array($product->gender, ['Unisex', 'Pria', 'Wanita'], true)) {
            $blockers['gender'] = 'Gender/audience belum dipilih.';
        }

        $activeOffers = $product->variants->where('is_active', true);
        if ($activeOffers->count() !== 1) {
            $blockers['offer'] = 'Produk harus mempunyai tepat satu ukuran dan harga aktif.';
        } else {
            $offer = $activeOffers->first();

            if ((int) $offer->volume < 1) {
                $blockers['volume'] = 'Ukuran produk harus lebih dari 0 ml.';
            }

            if ((float) $offer->price <= 0) {
                $blockers['price'] = 'Harga jual harus lebih dari nol.';
            }
        }

        $primaryImages = $product->images->where('is_primary', true);
        if ($primaryImages->count() !== 1) {
            $blockers['primary_image'] = 'Produk harus mempunyai tepat satu foto utama.';
        } elseif (! $this->productMediaStorage->exists($primaryImages->first()->image_path)) {
            $blockers['primary_image_file'] = 'File foto utama tidak ditemukan di storage.';
        }

        return $blockers;
    }
}
