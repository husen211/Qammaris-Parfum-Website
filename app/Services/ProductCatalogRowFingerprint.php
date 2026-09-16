<?php

namespace App\Services;

use App\Models\Product;

class ProductCatalogRowFingerprint
{
    public function hash(Product $product): string
    {
        $product->loadMissing('variants');
        $offer = $product->variants->where('is_active', true)->sortBy('id')->first();

        return hash('sha256', json_encode([
            'product' => [
                'id' => $product->getKey(),
                'updated_at' => $product->updated_at?->toIso8601String(),
                'brand_id' => $product->brand_id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'description' => $product->description,
                'fragrance_notes' => $product->fragrance_notes,
                'gender' => $product->gender,
                'stock_quantity' => $product->stock_quantity,
                'is_best_seller' => $product->is_best_seller,
                'publication_status' => $product->publication_status,
                'availability_status' => $product->availability_status,
            ],
            'offer' => $offer ? [
                'id' => $offer->getKey(),
                'updated_at' => $offer->updated_at?->toIso8601String(),
                'volume' => $offer->volume,
                'price' => $offer->price,
                'is_active' => $offer->is_active,
            ] : null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
