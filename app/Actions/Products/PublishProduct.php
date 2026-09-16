<?php

namespace App\Actions\Products;

use App\Exceptions\ProductNotReadyForPublication;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class PublishProduct
{
    public function __construct(
        private EvaluateProductPublicationReadiness $evaluateReadiness
    ) {}

    public function handle(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $blockers = $this->evaluateReadiness->handle($lockedProduct);

            if ($blockers !== []) {
                throw new ProductNotReadyForPublication($blockers);
            }

            $lockedProduct->markPublished();

            return $lockedProduct->fresh();
        });
    }
}
