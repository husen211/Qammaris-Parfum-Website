<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveProduct
{
    public function __construct(private RecordProductAdminChange $audit) {}

    public function handle(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor): void {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($locked);
            $locked->markArchived();
            $this->audit->handle($locked, $actor, 'product_archived', $before);
        });
    }
}
