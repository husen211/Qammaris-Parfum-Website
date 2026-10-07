<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ArchiveProductImage
{
    public function __construct(private RecordProductAdminChange $audit) {}

    public function handle(Product $product, int $imageId, ?User $actor = null): ProductImage
    {
        return DB::transaction(function () use ($product, $imageId, $actor): ProductImage {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $before = $actor ? $this->audit->snapshot($lockedProduct) : [];
            $images = $lockedProduct->images()->lockForUpdate()->get()->values();
            $target = $images->firstWhere('id', $imageId);

            if (! $target) {
                throw (new ModelNotFoundException)->setModel(ProductImage::class, [$imageId]);
            }

            if ($lockedProduct->isPublished() && $images->count() === 1) {
                throw new DomainException('Foto terakhir produk tayang tidak dapat diarsipkan. Tambahkan foto pengganti terlebih dahulu.');
            }

            $remaining = $images->reject(fn (ProductImage $image): bool => $image->is($target))->values();

            if ($target->is_primary) {
                $target->forceFill(['is_primary' => false])->save();

                if ($remaining->isNotEmpty()) {
                    $remaining->first()->forceFill(['is_primary' => true])->save();
                }
            }

            $target->delete();

            $remaining->each(function (ProductImage $image, int $index): void {
                if ($image->sort_order !== $index) {
                    $image->forceFill(['sort_order' => $index])->save();
                }
            });
            if ($actor) {
                $this->audit->handle($lockedProduct, $actor, 'image_archived', $before, $imageId);
            }

            return $target;
        });
    }
}
