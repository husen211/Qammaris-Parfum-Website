<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ApplyQammarisAppAvailability
{
    public function handle(Product $product, array $snapshot): void
    {
        // Caller owns the transaction and locks the source snapshot before the product.
        $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
        if (DB::table('qammaris_app_changes')->where('external_id', $snapshot['id'])
            ->where('product_id', $product->id)->where('revision', '>=', $snapshot['revision'])->exists()) {
            return;
        }

        $fields = ['availability_status', 'availability_source', 'availability_checked_at',
            'qammaris_app_hidden', 'availability_restock_eta'];
        $before = $product->only($fields);
        $product->forceFill([
            'availability_status' => $snapshot['availability'],
            'availability_source' => 'qammaris_app',
            'availability_checked_at' => $snapshot['stock_status_at'],
            'qammaris_app_hidden' => $snapshot['hidden'],
            'availability_restock_eta' => $snapshot['restock_eta'],
        ])->save();
        DB::table('qammaris_app_changes')->insert([
            'external_id' => $snapshot['id'], 'revision' => $snapshot['revision'],
            'product_id' => $product->id, 'actor' => 'qammaris_app',
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($product->only($fields), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
