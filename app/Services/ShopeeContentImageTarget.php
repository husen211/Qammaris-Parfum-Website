<?php

namespace App\Services;

use App\Actions\Products\ApplyShopeeContent;
use App\Models\Product;
use App\Models\ProductImportRow;
use DomainException;

class ShopeeContentImageTarget
{
    public function assert(ProductImportRow $row, Product $product): void
    {
        app(ApplyShopeeContent::class)->assertPayload($row);
        app(ShopeeContentPreviewer::class)->assertTarget($product, $row->external_product_id);
        if ($row->applied_product_id !== $product->id || $row->apply_status !== 'updated') {
            throw new DomainException('Produk foto tidak sesuai dengan hasil impor.');
        }
        $expected = collect($row->normalized_data['baseline_images']);
        foreach ($row->image_acquisition_outcomes ?? [] as $outcome) {
            if (($outcome['status'] ?? '') === 'stored') {
                $image = $product->images()->whereKey($outcome['product_image_id'] ?? 0)->first();
                if (! $image || $image->image_path !== $outcome['object_key']) {
                    throw new DomainException('Foto hasil impor telah diganti. Upload ulang untuk pemeriksaan baru.');
                }
                $expected->push($image->only(['id', 'image_path', 'is_primary', 'sort_order']));
            }
        }
        if ($expected->sortBy('id')->values()->all() !== app(ShopeeContentPreviewer::class)->images($product)) {
            throw new DomainException('Foto website berubah setelah diperiksa. Foto lama dipertahankan.');
        }
    }
}
