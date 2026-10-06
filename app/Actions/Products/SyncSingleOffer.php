<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductVariant;

class SyncSingleOffer
{
    public function handle(Product $product, array $attributes): ProductVariant
    {
        $offer = $this->resolveOffer($product, $attributes['id'] ?? null);
        $sku = array_key_exists('sku', $attributes)
            ? $this->normalizeSku($attributes['sku'])
            : $offer->sku;

        $offer->fill([
            'volume' => $attributes['volume'],
            'price' => $attributes['price'],
            'stock' => $attributes['stock'] ?? 0,
            'sku' => $sku,
            'is_active' => true,
        ]);
        $offer->save();

        $product->forceFill(['base_price' => $offer->price])->save();

        return $offer;
    }

    private function resolveOffer(Product $product, mixed $offerId): ProductVariant
    {
        if ($offerId) {
            return $product->variants()->whereKey($offerId)->firstOrFail();
        }

        return $product->variants()->firstOrNew();
    }

    private function normalizeSku(mixed $sku): ?string
    {
        if (! is_string($sku)) {
            return null;
        }

        $sku = trim($sku);

        return $sku === '' ? null : $sku;
    }
}
