<?php

namespace App\Actions\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ProductImportProductSnapshot;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResolveProtectedProductImportRow
{
    public const STATUS_RESOLVED = 'resolved';

    public function __construct(
        private SyncSingleOffer $syncSingleOffer,
        private ProductImportProductSnapshot $snapshot,
    ) {}

    /** @param array<int, string> $fields */
    public function handle(ProductImportBatch $batch, ProductImportRow $row, User $actor, array $fields): ProductImportRow
    {
        return DB::transaction(function () use ($batch, $row, $actor, $fields): ProductImportRow {
            $lockedBatch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            $lockedRow = ProductImportRow::query()->lockForUpdate()->findOrFail($row->getKey());

            if ($lockedRow->batch_id !== $lockedBatch->getKey()) {
                throw new DomainException('Baris import tidak berasal dari batch ini.');
            }

            if ($lockedBatch->status !== ProductImportBatch::STATUS_APPLIED
                || $lockedRow->apply_status !== ProductImportRow::APPLY_BLOCKED_PROTECTED
                || ! $lockedRow->applied_product_id) {
                throw new DomainException('Hanya baris protected dari batch applied yang dapat diresolusi.');
            }

            if ($lockedRow->resolution_status === self::STATUS_RESOLVED) {
                return $lockedRow->fresh(['resolvedBy:id,name']);
            }

            $fields = array_values(array_unique($fields));
            if ($fields === []) {
                throw new DomainException('Pilih minimal satu field yang ingin diterapkan.');
            }

            $product = Product::query()->lockForUpdate()->findOrFail($lockedRow->applied_product_id);
            $before = $this->snapshot->capture($product);

            if ($before !== $lockedRow->before_snapshot) {
                throw new DomainException('Produk berubah setelah batch diterapkan. Review perubahan terbaru lalu buat preview baru.');
            }

            $publicationStatus = $product->publication_status;
            $availabilityStatus = $product->availability_status;
            $data = $lockedRow->normalized_data ?? [];
            $attributes = [];

            foreach ($fields as $field) {
                $this->assignField($field, $data, $attributes, $product, $lockedRow);
            }

            if ($attributes !== []) {
                $product->update($attributes);
            }

            if (in_array('offer', $fields, true)) {
                $this->syncOffer($product, $data);
            }

            $product->refresh();
            if ($product->publication_status !== $publicationStatus || $product->availability_status !== $availabilityStatus) {
                throw new DomainException('Resolusi mencoba mengubah status yang dilindungi.');
            }

            $after = $this->snapshot->capture($product);
            $lockedRow->update([
                'resolution_status' => self::STATUS_RESOLVED,
                'resolution_fields' => $fields,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now(),
                'resolution_message' => 'Field terpilih diterapkan manual; status publikasi dan availability dipertahankan.',
                'resolution_before_snapshot' => $before,
                'resolution_after_snapshot' => $after,
            ]);

            return $lockedRow->fresh(['resolvedBy:id,name']);
        }, 3);
    }

    private function assignField(string $field, array $data, array &$attributes, Product $product, ProductImportRow $row): void
    {
        match ($field) {
            'name' => $attributes['name'] = $this->requiredText($data['nama_produk'] ?? null, 'Nama produk'),
            'description' => $attributes['description'] = $this->requiredText($data['deskripsi_produk'] ?? null, 'Deskripsi'),
            'brand' => $attributes['brand_id'] = $this->activeTaxonomyId(Brand::class, $data['brand'] ?? null, 'Brand'),
            'category' => $attributes['category_id'] = $this->activeTaxonomyId(Category::class, $data['kategori'] ?? null, 'Kategori'),
            'gender' => $attributes['gender'] = $this->requiredText($data['gender'] ?? null, 'Gender'),
            'is_best_seller' => $attributes['is_best_seller'] = (bool) ($data['terlaris'] ?? false),
            'stock_quantity' => $this->assignStock($attributes, $data, $row),
            'fragrance_notes' => $attributes['fragrance_notes'] = $this->mergedNotes($product, $data),
            'offer' => null,
            default => throw new DomainException('Field resolusi tidak dikenali.'),
        };
    }

    private function assignStock(array &$attributes, array $data, ProductImportRow $row): void
    {
        if (($data['stok'] ?? '') === '') {
            throw new DomainException('Stok import kosong dan tidak dapat diterapkan.');
        }

        $attributes['stock_quantity'] = (int) $data['stok'];
        $attributes['availability_source'] = 'import:'.$row->provider;
        $attributes['availability_checked_at'] = now();
    }

    private function syncOffer(Product $product, array $data): void
    {
        if (($data['harga'] ?? '') === '' || ($data['ukuran_ml'] ?? '') === '') {
            throw new DomainException('Harga dan ukuran harus sama-sama tersedia untuk memperbarui offer.');
        }

        $offer = $product->variants()->lockForUpdate()->first();
        $this->syncSingleOffer->handle($product, [
            'id' => $offer?->getKey(),
            'volume' => (int) $data['ukuran_ml'],
            'price' => $data['harga'],
            'stock' => ($data['stok'] ?? '') === '' ? ($offer?->stock ?? 0) : (int) $data['stok'],
        ]);
    }

    private function mergedNotes(Product $product, array $data): array
    {
        $notes = $product->fragrance_notes ?? ['top' => [], 'middle' => [], 'base' => []];
        $changed = false;

        foreach (['top' => 'top_notes', 'middle' => 'middle_notes', 'base' => 'base_notes'] as $group => $field) {
            if (($data[$field] ?? []) !== []) {
                $notes[$group] = $data[$field];
                $changed = true;
            }
        }

        if (! $changed) {
            throw new DomainException('Fragrance notes import kosong dan tidak dapat diterapkan.');
        }

        return $notes;
    }

    /** @param class-string<Model> $model */
    private function activeTaxonomyId(string $model, mixed $name, string $label): int
    {
        $name = $this->requiredText($name, $label);
        $id = $model::query()->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');

        if (! $id) {
            throw new DomainException($label.' import belum tersedia sebagai taxonomy aktif.');
        }

        return (int) $id;
    }

    private function requiredText(mixed $value, string $label): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            throw new DomainException($label.' import kosong dan tidak dapat diterapkan.');
        }

        return $value;
    }
}
