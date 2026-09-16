<?php

namespace App\Actions\Products;

use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ProductCatalogRowFingerprint;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductMaintenancePreviewer;
use App\Services\ProductMaintenanceProductSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ApplyProductMaintenanceBatch
{
    public function __construct(
        private ProductImportPayloadHasher $payloadHasher,
        private ProductMaintenancePreviewer $previewer,
        private ProductCatalogRowFingerprint $rowFingerprint,
        private ProductMaintenanceProductSnapshot $snapshot,
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

                if ($lockedBatch->contract_version !== ProductMaintenanceCsv::VERSION
                    || ! $this->payloadsAreIntact($rows)) {
                    $this->failBatch(
                        $lockedBatch,
                        ProductImportBatch::STATUS_INVALID,
                        'Versi kontrak atau payload maintenance tidak lagi valid. Buat preview baru.'
                    );

                    return $this->loadResult($lockedBatch);
                }

                if (! hash_equals(
                    $lockedBatch->catalog_state_fingerprint,
                    $this->previewer->catalogStateFingerprint()
                )) {
                    $this->failBatch(
                        $lockedBatch,
                        ProductImportBatch::STATUS_STALE,
                        'Katalog berubah setelah preview. Unduh snapshot terbaru lalu buat preview baru.'
                    );

                    return $this->loadResult($lockedBatch);
                }

                $appliedRows = 0;
                $blockedRows = 0;
                $appliedAt = now();

                foreach ($rows as $row) {
                    if ($row->status === 'error' || $row->candidate_action !== 'update') {
                        $this->recordOutcome(
                            $row,
                            ProductImportRow::APPLY_BLOCKED_ERROR,
                            'Baris error/conflict tidak diterapkan.',
                            $appliedAt
                        );
                        $blockedRows++;

                        continue;
                    }

                    if ($row->status === 'review' || ($row->normalized_data['_changes'] ?? []) === []) {
                        $this->recordOutcome(
                            $row,
                            ProductImportRow::APPLY_SKIPPED_NO_CHANGES,
                            'Baris dilewati karena tidak mempunyai perubahan.',
                            $appliedAt,
                            $row->matched_product_id
                        );
                        $blockedRows++;

                        continue;
                    }

                    $this->applyRow($row, $appliedAt);
                    $appliedRows++;
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
                ->where('contract_version', ProductMaintenanceCsv::VERSION)
                ->where('status', ProductImportBatch::STATUS_PREVIEWED)
                ->update([
                    'status' => ProductImportBatch::STATUS_FAILED,
                    'failed_at' => now(),
                    'failure_message' => 'Apply maintenance gagal dan seluruh perubahan dibatalkan. Buat preview baru.',
                ]);

            throw new RuntimeException(
                'Apply maintenance gagal dan tidak ada perubahan katalog yang disimpan.',
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

    private function applyRow(ProductImportRow $row, mixed $appliedAt): void
    {
        $data = $row->normalized_data ?? [];
        $productId = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($productId === false || $row->matched_product_id !== $productId) {
            throw new RuntimeException('Product maintenance tidak lagi cocok dengan hasil preview.');
        }

        $product = Product::query()->lockForUpdate()->findOrFail($productId);
        $product->setRelation('variants', $product->variants()->lockForUpdate()->get());
        $before = $this->snapshot->capture($product);

        if ($before !== $row->before_snapshot
            || ! $this->timestampMatches($data['expected_updated_at'] ?? null, $product)
            || ! $this->fingerprintMatches($data['expected_row_fingerprint'] ?? null, $product)) {
            throw new RuntimeException('Produk berubah setelah preview maintenance dibuat.');
        }

        $publicationStatus = $product->publication_status;
        $availabilityStatus = $product->availability_status;
        $slug = $product->slug;
        $changes = collect($data['_changes'] ?? [])->pluck('field')->all();
        $attributes = [];

        foreach ($changes as $field) {
            $this->assignField($field, $data, $attributes, $product);
        }

        if ($attributes !== []) {
            $product->update($attributes);
        }

        if (in_array('offer', $changes, true)) {
            $this->syncOffer($product, $data);
        }

        $product->refresh();

        if ($product->publication_status !== $publicationStatus
            || $product->availability_status !== $availabilityStatus
            || $product->slug !== $slug) {
            throw new RuntimeException('Apply maintenance mencoba mengubah field yang dilindungi.');
        }

        $product->setRelation('variants', $product->variants()->get());
        $row->update([
            'apply_status' => ProductImportRow::APPLY_UPDATED,
            'applied_product_id' => $product->getKey(),
            'apply_message' => 'Perubahan maintenance diterapkan; field kosong dan status dilindungi dipertahankan.',
            'before_snapshot' => $before,
            'after_snapshot' => $this->snapshot->capture($product),
            'applied_at' => $appliedAt,
        ]);
    }

    private function assignField(string $field, array $data, array &$attributes, Product $product): void
    {
        match ($field) {
            'nama_produk' => $attributes['name'] = $this->requiredText($data['nama_produk'] ?? null, 'Nama produk'),
            'deskripsi_produk' => $attributes['description'] = $this->requiredText($data['deskripsi_produk'] ?? null, 'Deskripsi'),
            'brand' => $attributes['brand_id'] = $this->activeTaxonomyId(Brand::class, $data['brand'] ?? null, 'Brand'),
            'gender' => $attributes['gender'] = $this->controlledGender($data['gender'] ?? null),
            'stok_snapshot' => $attributes['stock_quantity'] = $this->stock($data['stok_snapshot'] ?? null),
            'terlaris' => $attributes['is_best_seller'] = $this->boolean($data['terlaris'] ?? null),
            'kategori' => $attributes['category_id'] = $this->activeTaxonomyId(Category::class, $data['kategori'] ?? null, 'Kategori'),
            'top_notes', 'middle_notes', 'base_notes' => $attributes['fragrance_notes'] = $this->mergedNotes($product, $data),
            'offer' => null,
            default => throw new RuntimeException('Field maintenance tidak dikenali.'),
        };
    }

    private function syncOffer(Product $product, array $data): void
    {
        if (! is_numeric($data['harga'] ?? null) || ! is_int($data['ukuran_ml'] ?? null)) {
            throw new RuntimeException('Harga dan ukuran maintenance tidak valid.');
        }

        $offer = $product->variants->where('is_active', true)->sortBy('id')->first();
        $this->syncSingleOffer->handle($product, [
            'id' => $offer?->getKey(),
            'volume' => $data['ukuran_ml'],
            'price' => $data['harga'],
            'stock' => $offer?->stock ?? 0,
        ]);
    }

    private function mergedNotes(Product $product, array $data): array
    {
        $notes = $product->fragrance_notes ?? ['top' => [], 'middle' => [], 'base' => []];

        foreach (['top' => 'top_notes', 'middle' => 'middle_notes', 'base' => 'base_notes'] as $group => $field) {
            if (($data[$field] ?? []) !== []) {
                $notes[$group] = $data[$field];
            }
        }

        return $notes;
    }

    /** @param class-string<Model> $model */
    private function activeTaxonomyId(string $model, mixed $name, string $label): int
    {
        $name = $this->requiredText($name, $label);
        $id = $model::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->lockForUpdate()
            ->value('id');

        if (! $id) {
            throw new RuntimeException($label.' maintenance tidak tersedia sebagai taxonomy aktif.');
        }

        return (int) $id;
    }

    private function requiredText(mixed $value, string $label): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            throw new RuntimeException($label.' maintenance kosong.');
        }

        return $value;
    }

    private function controlledGender(mixed $value): string
    {
        $value = $this->requiredText($value, 'Gender');

        if (! in_array($value, ['Unisex', 'Pria', 'Wanita'], true)) {
            throw new RuntimeException('Gender maintenance tidak valid.');
        }

        return $value;
    }

    private function stock(mixed $value): int
    {
        if (! is_int($value) || $value < 0 || $value > 999999) {
            throw new RuntimeException('Stok snapshot maintenance tidak valid.');
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new RuntimeException('Nilai terlaris maintenance tidak valid.');
        }

        return $value;
    }

    private function timestampMatches(mixed $value, Product $product): bool
    {
        if (! is_string($value) || $product->updated_at === null) {
            return false;
        }

        try {
            return CarbonImmutable::parse($value)->utc()
                ->equalTo($product->updated_at->toImmutable()->utc());
        } catch (Throwable) {
            return false;
        }
    }

    private function fingerprintMatches(mixed $value, Product $product): bool
    {
        return is_string($value)
            && preg_match('/^[a-f0-9]{64}$/', $value) === 1
            && hash_equals($this->rowFingerprint->hash($product), $value);
    }

    private function recordOutcome(
        ProductImportRow $row,
        string $status,
        string $message,
        mixed $appliedAt,
        ?int $productId = null
    ): void {
        $row->update([
            'apply_status' => $status,
            'applied_product_id' => $productId,
            'apply_message' => $message,
            'after_snapshot' => $row->before_snapshot,
            'applied_at' => $appliedAt,
        ]);
    }

    private function failBatch(ProductImportBatch $batch, string $status, string $message): void
    {
        $batch->update([
            'status' => $status,
            'failed_at' => now(),
            'failure_message' => $message,
        ]);
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
