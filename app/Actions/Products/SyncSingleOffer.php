<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Rupiah;
use DomainException;
use Illuminate\Support\Facades\DB;

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
            'price' => $this->price($product, $offer, $attributes['price']),
            'stock' => $attributes['stock'] ?? 0,
            'sku' => $sku,
            'is_active' => true,
        ]);
        $offer->save();

        $mirror = ['base_price' => $offer->price];
        if ($product->availability_source === 'qammaris_app' && $product->compare_at_price !== null
            && (float) $product->compare_at_price <= (float) $offer->price) {
            $mirror['compare_at_price'] = null;
        }
        $product->forceFill($mirror)->save();

        return $offer;
    }

    private function price(Product $product, ProductVariant $offer, mixed $requested): mixed
    {
        $uuid = $product->externalIdentities()->where('provider', 'qammaris_app')->value('external_product_id');
        if (! $uuid) {
            if (! is_scalar($requested) || ! preg_match(Rupiah::WHOLE_PRICE_PATTERN, (string) $requested)) {
                throw new DomainException('Harga jual harus rupiah bulat, tanpa pecahan atau pemisah ribuan.');
            }

            return $requested;
        }
        $raw = DB::table('qammaris_app_products')->where('id', $uuid)->value('snapshot');
        $source = $raw ? json_decode($raw, true, flags: JSON_THROW_ON_ERROR) : null;
        $price = $source['price'] ?? null;
        if ($source && ! $source['hidden'] && is_int($price) && $price > 0 && $price <= 99999999) {
            return $price;
        }
        // All form/import callers retain the last valid price when the source cannot supply one.
        $retained = $offer->exists ? $offer->price : $product->base_price;
        if ((float) $retained > 0 && (float) $retained <= 99999999.99) {
            return $retained;
        }
        throw new DomainException('Harga produk ini dikelola Qammaris App. Lengkapi harga di aplikasi dahulu.');
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
