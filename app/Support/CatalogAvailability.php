<?php

namespace App\Support;

use App\Models\Product;

final class CatalogAvailability
{
    public static function label(Product $product): string
    {
        if ($product->availability_source !== 'qammaris_app') {
            return self::legacyLabel($product->effective_availability);
        }

        return match ($product->effective_availability) {
            Product::AVAILABILITY_AVAILABLE => 'Tersedia',
            Product::AVAILABILITY_SOLD_OUT => $product->availability_restock_eta
                ? 'Habis · Restok segera' : 'Habis',
            default => 'Tanyakan ketersediaan',
        };
    }

    public static function legacyLabel(string $availability): string
    {
        return match ($availability) {
            Product::AVAILABILITY_AVAILABLE => 'Tersedia saat diperiksa',
            Product::AVAILABILITY_SOLD_OUT => 'Sold out',
            default => 'Konfirmasi stok',
        };
    }
}
