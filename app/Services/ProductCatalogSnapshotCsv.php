<?php

namespace App\Services;

use App\Models\Product;

class ProductCatalogSnapshotCsv
{
    public const VERSION = 'qammaris-catalog-snapshot-v1';

    public const HEADERS = [
        'snapshot_version',
        'product_id',
        'slug',
        'publication_status',
        'availability_status',
        'availability_effective',
        'availability_source',
        'availability_checked_at',
        'external_identities',
        'nama_produk',
        'deskripsi_produk',
        'harga',
        'brand',
        'gender',
        'stok_snapshot',
        'terlaris',
        'kategori',
        'ukuran_ml',
        'top_notes',
        'middle_notes',
        'base_notes',
        'active_image_count',
        'has_primary_image',
        'updated_at',
    ];

    public function __construct(private SpreadsheetSafeCell $safeCell) {}

    /** @param resource $stream */
    public function write($stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, escape: '');

        Product::query()
            ->with([
                'brand:id,name',
                'category:id,name',
                'variants:id,product_id,volume,price,stock,is_active',
                'externalIdentities:id,product_id,provider,external_product_id',
            ])
            ->withCount('images')
            ->withExists('primaryImage')
            ->lazyById(200)
            ->each(function (Product $product) use ($stream): void {
                fputcsv($stream, array_map(
                    fn (mixed $value): string => $this->safeCell->sanitize($value),
                    $this->row($product)
                ), escape: '');
            });
    }

    /** @return array<int, mixed> */
    private function row(Product $product): array
    {
        $offer = $product->variants
            ->where('is_active', true)
            ->sortBy([
                ['volume', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        return [
            self::VERSION,
            $product->getKey(),
            $product->slug,
            $product->publication_status,
            $product->availability_status,
            $product->effective_availability,
            $product->availability_source,
            $product->availability_checked_at?->toIso8601String(),
            $this->externalIdentities($product),
            $product->name,
            $product->description,
            $offer?->price,
            $product->brand?->name,
            $product->gender,
            $product->stock_quantity,
            $product->is_best_seller ? 'ya' : 'tidak',
            $product->category?->name,
            $offer?->volume,
            $this->notes($product, 'top'),
            $this->notes($product, 'middle'),
            $this->notes($product, 'base'),
            $product->images_count,
            $product->primary_image_exists ? 'ya' : 'tidak',
            $product->updated_at?->toIso8601String(),
        ];
    }

    private function externalIdentities(Product $product): string
    {
        return $product->externalIdentities
            ->sortBy([
                ['provider', 'asc'],
                ['external_product_id', 'asc'],
            ])
            ->map(fn ($identity): string => $identity->provider.':'.$identity->external_product_id)
            ->implode('|');
    }

    private function notes(Product $product, string $group): string
    {
        $notes = ($product->fragrance_notes ?? [])[$group] ?? [];

        return is_array($notes) ? implode('|', $notes) : (string) $notes;
    }
}
