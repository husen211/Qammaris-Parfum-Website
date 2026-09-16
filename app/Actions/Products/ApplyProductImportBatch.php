<?php

namespace App\Actions\Products;

use App\Imports\Products\CanonicalProductCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductImportPreviewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ApplyProductImportBatch
{
    public function __construct(
        private ProductImportPreviewer $previewer,
        private ProductImportPayloadHasher $payloadHasher,
        private MapExternalProductIdentity $mapExternalIdentity,
        private SyncSingleOffer $syncSingleOffer,
    ) {}

    public function handle(ProductImportBatch $batch, User $actor): ProductImportBatch
    {
        try {
            return DB::transaction(function () use ($batch, $actor): ProductImportBatch {
                $lockedBatch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());

                if ($lockedBatch->status === ProductImportBatch::STATUS_APPLIED) {
                    return $this->loadResult($lockedBatch);
                }

                if ($lockedBatch->status !== ProductImportBatch::STATUS_PREVIEWED) {
                    return $this->loadResult($lockedBatch);
                }

                $rows = $lockedBatch->rows()->lockForUpdate()->get();

                if ($lockedBatch->contract_version !== CanonicalProductCsv::VERSION
                    || ! $this->payloadsAreIntact($rows)) {
                    $lockedBatch->update([
                        'status' => ProductImportBatch::STATUS_INVALID,
                        'failed_at' => now(),
                        'failure_message' => 'Versi kontrak atau payload batch tidak lagi valid. Buat preview baru.',
                    ]);

                    return $this->loadResult($lockedBatch);
                }

                if (! hash_equals(
                    $lockedBatch->catalog_state_fingerprint,
                    $this->previewer->catalogStateFingerprint()
                )) {
                    $lockedBatch->update([
                        'status' => ProductImportBatch::STATUS_STALE,
                        'failed_at' => now(),
                        'failure_message' => 'Katalog berubah setelah preview. Upload ulang file untuk membuat preview baru.',
                    ]);

                    return $this->loadResult($lockedBatch);
                }

                $appliedRows = 0;
                $blockedRows = 0;
                $appliedAt = now();

                foreach ($rows as $row) {
                    if ($row->status === 'error' || $row->candidate_action === 'conflict') {
                        $this->blockRow(
                            $row,
                            ProductImportRow::APPLY_BLOCKED_ERROR,
                            'Baris mempunyai error/conflict dan tidak diterapkan.',
                            $appliedAt
                        );
                        $blockedRows++;

                        continue;
                    }

                    if ($row->candidate_action === 'create') {
                        $this->createDraft($row, $appliedAt);
                        $appliedRows++;

                        continue;
                    }

                    if ($row->candidate_action === 'update') {
                        if ($this->updateDraft($row, $appliedAt)) {
                            $appliedRows++;
                        } else {
                            $blockedRows++;
                        }

                        continue;
                    }

                    throw new RuntimeException('Candidate action import tidak dikenali.');
                }

                $lockedBatch->update([
                    'status' => ProductImportBatch::STATUS_APPLIED,
                    'applied_by' => $actor->getKey(),
                    'applied_at' => $appliedAt,
                    'failed_at' => null,
                    'applied_rows' => $appliedRows,
                    'blocked_rows' => $blockedRows,
                    'failure_message' => null,
                ]);

                return $this->loadResult($lockedBatch);
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            ProductImportBatch::query()
                ->whereKey($batch->getKey())
                ->where('status', ProductImportBatch::STATUS_PREVIEWED)
                ->update([
                    'status' => ProductImportBatch::STATUS_FAILED,
                    'failed_at' => now(),
                    'failure_message' => 'Apply gagal dan seluruh perubahan katalog dibatalkan. Buat preview baru sebelum mencoba lagi.',
                ]);

            throw new RuntimeException(
                'Apply gagal dan tidak ada perubahan katalog yang disimpan.',
                previous: $exception
            );
        }
    }

    private function payloadsAreIntact($rows): bool
    {
        return $rows->every(fn (ProductImportRow $row): bool => hash_equals(
            $row->payload_hash,
            $this->payloadHasher->hash(
                $row->normalized_data ?? [],
                $row->issues ?? [],
                $row->candidate_action
            )
        ));
    }

    private function createDraft(ProductImportRow $row, mixed $appliedAt): void
    {
        $data = $row->normalized_data;

        $existingIdentity = ProductExternalIdentity::query()
            ->where('provider', $row->provider)
            ->where('external_product_id', $row->external_product_id)
            ->lockForUpdate()
            ->first();

        if ($existingIdentity) {
            throw new RuntimeException('Identity baru sudah terhubung ke product lain.');
        }

        $product = Product::create([
            'brand_id' => $this->activeTaxonomyId(Brand::class, $data['brand'] ?? ''),
            'category_id' => $this->activeTaxonomyId(Category::class, $data['kategori'] ?? ''),
            'name' => $data['nama_produk'],
            'description' => $this->nullableText($data['deskripsi_produk'] ?? ''),
            'base_price' => null,
            'fragrance_notes' => $this->newFragranceNotes($data),
            'gender' => $this->nullableText($data['gender'] ?? ''),
            'is_best_seller' => (bool) ($data['terlaris'] ?? false),
            'is_active' => false,
            'publication_status' => Product::PUBLICATION_DRAFT,
            'published_at' => null,
            'archived_at' => null,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'stock_quantity' => $this->nullableInteger($data['stok'] ?? ''),
            'availability_source' => ($data['stok'] ?? '') === '' ? null : 'import:'.$row->provider,
            'availability_checked_at' => ($data['stok'] ?? '') === '' ? null : $appliedAt,
        ]);

        $this->mapExternalIdentity->handle($product, $row->provider, $row->external_product_id);
        $this->syncOfferWhenComplete($product, $data);

        $row->update([
            'apply_status' => ProductImportRow::APPLY_CREATED,
            'applied_product_id' => $product->getKey(),
            'apply_message' => 'Draft baru dibuat. URL gambar belum diunduh.',
            'before_snapshot' => null,
            'after_snapshot' => $this->snapshot($product),
            'applied_at' => $appliedAt,
        ]);
    }

    private function updateDraft(ProductImportRow $row, mixed $appliedAt): bool
    {
        $identity = ProductExternalIdentity::query()
            ->where('provider', $row->provider)
            ->where('external_product_id', $row->external_product_id)
            ->lockForUpdate()
            ->first();

        if (! $identity || $identity->product_id !== $row->matched_product_id) {
            throw new RuntimeException('Mapping product berubah setelah preview.');
        }

        $product = Product::query()->lockForUpdate()->findOrFail($identity->product_id);
        $before = $this->snapshot($product);

        if ($product->publication_status !== Product::PUBLICATION_DRAFT) {
            $this->blockRow(
                $row,
                ProductImportRow::APPLY_BLOCKED_PROTECTED,
                'Produk '.$product->publication_status.' dilindungi dan harus direview manual.',
                $appliedAt,
                $product,
                $before
            );

            return false;
        }

        $data = $row->normalized_data;
        $attributes = [
            'name' => $data['nama_produk'],
            'is_best_seller' => (bool) ($data['terlaris'] ?? false),
        ];

        $this->assignTextWhenPresent($attributes, 'description', $data['deskripsi_produk'] ?? '');
        $this->assignTextWhenPresent($attributes, 'gender', $data['gender'] ?? '');

        $brandId = $this->activeTaxonomyId(Brand::class, $data['brand'] ?? '');
        if ($brandId !== null) {
            $attributes['brand_id'] = $brandId;
        }

        $categoryId = $this->activeTaxonomyId(Category::class, $data['kategori'] ?? '');
        if ($categoryId !== null) {
            $attributes['category_id'] = $categoryId;
        }

        if (($data['stok'] ?? '') !== '') {
            $attributes['stock_quantity'] = (int) $data['stok'];
            $attributes['availability_source'] = 'import:'.$row->provider;
            $attributes['availability_checked_at'] = $appliedAt;
        }

        $notes = $this->mergedFragranceNotes($product, $data);
        if ($notes !== null) {
            $attributes['fragrance_notes'] = $notes;
        }

        $product->update($attributes);
        $this->syncOfferWhenComplete($product, $data);

        $row->update([
            'apply_status' => ProductImportRow::APPLY_UPDATED,
            'applied_product_id' => $product->getKey(),
            'apply_message' => 'Draft existing diperbarui; field CSV kosong dipertahankan.',
            'before_snapshot' => $before,
            'after_snapshot' => $this->snapshot($product),
            'applied_at' => $appliedAt,
        ]);

        return true;
    }

    private function syncOfferWhenComplete(Product $product, array $data): void
    {
        if (($data['harga'] ?? '') === '' || ($data['ukuran_ml'] ?? '') === '') {
            return;
        }

        $offer = $product->variants()->lockForUpdate()->first();

        $this->syncSingleOffer->handle($product, [
            'id' => $offer?->getKey(),
            'volume' => (int) $data['ukuran_ml'],
            'price' => $data['harga'],
            'stock' => ($data['stok'] ?? '') === '' ? ($offer?->stock ?? 0) : (int) $data['stok'],
        ]);
    }

    private function blockRow(
        ProductImportRow $row,
        string $status,
        string $message,
        mixed $appliedAt,
        ?Product $product = null,
        ?array $before = null
    ): void {
        $row->update([
            'apply_status' => $status,
            'applied_product_id' => $product?->getKey(),
            'apply_message' => $message,
            'before_snapshot' => $before,
            'after_snapshot' => $before,
            'applied_at' => $appliedAt,
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function activeTaxonomyId(string $model, mixed $name): ?int
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $model::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->value('id');
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === '' || $value === null ? null : (int) $value;
    }

    private function assignTextWhenPresent(array &$attributes, string $key, mixed $value): void
    {
        $value = $this->nullableText($value);

        if ($value !== null) {
            $attributes[$key] = $value;
        }
    }

    private function newFragranceNotes(array $data): ?array
    {
        $notes = [
            'top' => $data['top_notes'] ?? [],
            'middle' => $data['middle_notes'] ?? [],
            'base' => $data['base_notes'] ?? [],
        ];

        return collect($notes)->flatten()->isEmpty() ? null : $notes;
    }

    private function mergedFragranceNotes(Product $product, array $data): ?array
    {
        $notes = $product->fragrance_notes ?? ['top' => [], 'middle' => [], 'base' => []];
        $changed = false;

        foreach (['top' => 'top_notes', 'middle' => 'middle_notes', 'base' => 'base_notes'] as $group => $field) {
            if (($data[$field] ?? []) !== []) {
                $notes[$group] = $data[$field];
                $changed = true;
            }
        }

        return $changed ? $notes : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Product $product): array
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

    private function loadResult(ProductImportBatch $batch): ProductImportBatch
    {
        return $batch->fresh([
            'actor:id,name',
            'appliedBy:id,name',
            'rows.matchedProduct:id,name,publication_status',
            'rows.appliedProduct:id,name,publication_status',
        ]);
    }
}
