<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MoveProductImage
{
    public function __construct(private RecordProductAdminChange $audit) {}

    public function handle(Product $product, int $imageId, string $direction, ?User $actor = null): ProductImage
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Arah urutan foto tidak valid.');
        }

        return DB::transaction(function () use ($product, $imageId, $direction, $actor): ProductImage {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $before = $actor ? $this->audit->snapshot($lockedProduct) : [];
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
            if ($actor) {
                $this->audit->handle($lockedProduct, $actor, 'image_moved', $before, $imageId);
            }

            return $images->firstWhere('id', $imageId)->refresh();
        });
    }
}
