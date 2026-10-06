<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ApplyQammarisAppAvailability
{
    public function __construct(private SyncQammarisAppPrice $prices) {}

    public function handle(Product $product, array $snapshot, bool $createdDraft = false): void
    {
        // Caller owns the transaction and locks the source snapshot before the product.
        $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
        if (DB::table('qammaris_app_changes')->where('external_id', $snapshot['id'])
            ->where('product_id', $product->id)->where('revision', '>=', $snapshot['revision'])->exists()) {
            return;
        }

        $fields = ['availability_status', 'availability_source', 'availability_checked_at',
            'qammaris_app_hidden', 'availability_restock_eta', 'base_price', 'compare_at_price'];
        $before = $product->only($fields);
        $before['offer'] = $product->variants()->first()?->only(['id', 'volume', 'price', 'stock', 'sku', 'is_active']);
        $before['catalog'] = $createdDraft ? null : ['id' => $product->id, 'publication_status' => $product->publication_status];
        $product->forceFill([
            'availability_status' => $snapshot['availability'],
            'availability_source' => 'qammaris_app',
            'availability_checked_at' => $snapshot['stock_status_at'],
            'qammaris_app_hidden' => $snapshot['hidden'],
            'availability_restock_eta' => $snapshot['restock_eta'],
        ])->save();
        $priceResult = $this->prices->handle($product, $snapshot);
        $after = $product->only($fields);
        $after['offer'] = $product->variants()->first()?->only(['id', 'volume', 'price', 'stock', 'sku', 'is_active']);
        $after['catalog'] = ['id' => $product->id, 'publication_status' => $product->publication_status];
        $after['price_sync'] = $priceResult;
        DB::table('qammaris_app_changes')->insert([
            'external_id' => $snapshot['id'], 'revision' => $snapshot['revision'],
            'product_id' => $product->id, 'actor' => 'qammaris_app',
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
