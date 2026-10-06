<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MoveProductImage
{
    public function handle(Product $product, int $imageId, string $direction): ProductImage
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Arah urutan foto tidak valid.');
        }

        return DB::transaction(function () use ($product, $imageId, $direction): ProductImage {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $images = $lockedProduct->images()->lockForUpdate()->get()->values();
            $currentIndex = $images->search(fn (ProductImage $image): bool => $image->getKey() === $imageId);

            if ($currentIndex === false) {
                throw (new ModelNotFoundException)->setModel(ProductImage::class, [$imageId]);
            }

            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

            if ($targetIndex >= 0 && $targetIndex < $images->count()) {
                $current = $images[$currentIndex];
                $images[$currentIndex] = $images[$targetIndex];
                $images[$targetIndex] = $current;
            }

            $images->values()->each(function (ProductImage $image, int $index): void {
                if ($image->sort_order !== $index) {
                    $image->forceFill(['sort_order' => $index])->save();
                }
            });

            return $images->firstWhere('id', $imageId)->refresh();
        });
    }
}
