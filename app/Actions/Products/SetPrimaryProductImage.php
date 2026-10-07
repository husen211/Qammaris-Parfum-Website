<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class SetPrimaryProductImage
{
    public function __construct(private RecordProductAdminChange $audit) {}

    public function handle(Product $product, int $imageId, ?User $actor = null): ProductImage
    {
        return DB::transaction(function () use ($product, $imageId, $actor) {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $before = $actor ? $this->audit->snapshot($lockedProduct) : [];
            $images = $lockedProduct->images()->lockForUpdate()->get();
            $target = $images->firstWhere('id', $imageId);

            if (! $target) {
                throw (new ModelNotFoundException)->setModel(ProductImage::class, [$imageId]);
            }

            if ($target->is_primary && $images->where('is_primary', true)->count() === 1) {
                return $target;
            }

            $lockedProduct->images()->where('is_primary', true)->update(['is_primary' => false]);
            $target->forceFill(['is_primary' => true])->save();
            if ($actor) {
                $this->audit->handle($lockedProduct, $actor, 'image_primary_changed', $before, $imageId);
            }

            return $target->refresh();
        });
    }
}
