<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductMediaStorage;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttachProductImage
{
    public function __construct(private ProductMediaStorage $productMediaStorage) {}

    public function handle(Product $product, string $imagePath, bool $makePrimary = false): ProductImage
    {
        $canonicalPath = $this->productMediaStorage->normalizeProductPath($imagePath);

        if ($canonicalPath === null || $canonicalPath !== $imagePath) {
            throw new InvalidArgumentException('Metadata gambar baru membutuhkan object key lokal canonical.');
        }

        if (! $this->productMediaStorage->exists($canonicalPath)) {
            throw new DomainException('File gambar harus tersedia dan terverifikasi sebelum metadata dibuat.');
        }

        return DB::transaction(function () use ($product, $canonicalPath, $makePrimary) {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $images = $lockedProduct->images()->lockForUpdate()->get();

            if ($images->count() >= ProductImage::MAX_PER_PRODUCT) {
                throw new DomainException('Produk hanya boleh memiliki maksimum tiga gambar.');
            }

            $primaryCount = $images->where('is_primary', true)->count();

            if ($primaryCount > 1) {
                throw new DomainException('Primary image existing perlu direkonsiliasi sebelum gambar baru ditambahkan.');
            }

            $shouldBePrimary = $makePrimary || $primaryCount === 0;

            if ($shouldBePrimary && $primaryCount === 1) {
                $lockedProduct->images()->where('is_primary', true)->update(['is_primary' => false]);
            }

            return $lockedProduct->images()->create([
                'image_path' => $canonicalPath,
                'is_primary' => $shouldBePrimary,
                'sort_order' => $images->isEmpty() ? 0 : $images->max('sort_order') + 1,
            ]);
        });
    }
}
