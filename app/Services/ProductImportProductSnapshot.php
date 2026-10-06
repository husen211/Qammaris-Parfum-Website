<?php

namespace App\Services;

use App\Models\Product;

class ProductImportProductSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function capture(Product $product): array
    {
        $product->refresh();
        $offer = $product->variants()->lockForUpdate()->first();

        return [
            'product' => [
                'id' => $product->getKey(),
                'brand_id' => $product->brand_id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'fragrance_notes' => $product->fragrance_notes,
                'gender' => $product->gender,
                'is_best_seller' => $product->is_best_seller,
                'is_active' => $product->is_active,
                'publication_status' => $product->publication_status,
                'availability_status' => $product->availability_status,
                'stock_quantity' => $product->stock_quantity,
                'availability_source' => $product->availability_source,
                'availability_checked_at' => $product->availability_checked_at?->toJSON(),
            ],
            'offer' => $offer ? [
                'id' => $offer->getKey(),
                'volume' => $offer->volume,
                'price' => $offer->price,
                'stock' => $offer->stock,
                'sku' => $offer->sku,
                'is_active' => $offer->is_active,
            ] : null,
        ];
    }
}
