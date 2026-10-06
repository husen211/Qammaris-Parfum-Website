<?php

namespace App\Actions\Products;

use App\Models\Product;

class SyncQammarisAppPrice
{
    public function __construct(private SyncSingleOffer $offers) {}

    public function handle(Product $product, array $source): string
    {
        if ($source['hidden']) {
            return 'hidden_retained';
        }
        $price = $source['price'];
        if (! is_int($price) || $price <= 0 || $price > 99999999) {
            return 'review_invalid_price';
        }
        $offer = $product->variants()->where('is_active', true)->lockForUpdate()->first();
        if (! $offer) {
            if ($product->publication_status === Product::PUBLICATION_DRAFT && ! $product->variants()->exists()) {
                $product->forceFill(['base_price' => $price])->save();
            }

            return 'review_missing_offer';
        }
        if ((float) $offer->price !== (float) $price || (float) $product->base_price !== (float) $price) {
            $this->offers->handle($product, [
                'id' => $offer->id, 'volume' => $offer->volume, 'price' => $price,
                'stock' => $offer->stock, 'sku' => $offer->sku,
            ]);
        }

        return 'synced';
    }
}
