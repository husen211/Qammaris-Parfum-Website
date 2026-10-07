<?php

namespace App\Actions\Products;

use App\Exceptions\ProductNotReadyForPublication;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishProduct
{
    public function __construct(
        private EvaluateProductPublicationReadiness $evaluateReadiness,
        private RecordProductAdminChange $audit,
    ) {}

    public function handle(Product $product, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $actor): Product {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $before = $actor ? $this->audit->snapshot($lockedProduct) : [];
            $blockers = $this->evaluateReadiness->handle($lockedProduct);

            if ($blockers !== []) {
                throw new ProductNotReadyForPublication($blockers);
            }

            $lockedProduct->markPublished();
            if ($actor) {
                $this->audit->handle($lockedProduct, $actor, 'product_published', $before);
            }

            return $lockedProduct->fresh();
        });
    }
}
