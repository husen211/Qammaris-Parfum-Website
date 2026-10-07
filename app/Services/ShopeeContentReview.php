<?php

namespace App\Services;

use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Models\ProductImportRow;
use Illuminate\Support\Collection;

class ShopeeContentReview
{
    public function __construct(private ShopeeContentPreviewer $preview,
        private EvaluateProductPublicationReadiness $readiness,
        private ImportedProductName $names, private ShopeeProductCopy $copy) {}

    /** One read-only summary shared by filtering, totals, and row rendering. */
    public function summarize(Collection $rows, Collection $products): Collection
    {
        $products = $products->keyBy('id');
        $targets = $rows->map(fn ($row) => $row->appliedProduct ?? $row->matchedProduct
            ?? $products->get($row->normalized_data['selected_product_id'] ?? null))->filter();
        $blockers = $this->readiness->forReview($targets);

        return $rows->mapWithKeys(function (ProductImportRow $row) use ($products, $blockers): array {
            $data = $row->normalized_data;
            $current = $row->appliedProduct ?? $row->matchedProduct;
            $product = $current ?? $products->get($data['selected_product_id'] ?? null);
            $applied = $row->apply_status === ProductImportRow::APPLY_UPDATED;
            $held = $row->apply_status === ProductImportRow::APPLY_BLOCKED_PROTECTED;
            $changes = $current && $row->apply_status === ProductImportRow::APPLY_PENDING && $row->matched_product_id
                && $this->preview->hasChanges($data, $current);
            $legacy = $row->apply_status === ProductImportRow::APPLY_PENDING
                && ($data['fingerprint_version'] ?? null) !== ShopeeContentPreviewer::FINGERPRINT_VERSION;
            $waiting = in_array($row->image_acquisition_status, [ProductImportRow::IMAGE_QUEUED, ProductImportRow::IMAGE_PROCESSING], true);
            $productBlockers = $product ? $blockers->get($product->id, []) : [];
            $complete = $current && $row->matched_product_id && $current->publication_status === 'published'
                && ! $changes && ! $held && ! $waiting
                && $row->image_acquisition_status !== ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS && $productBlockers === [];
            $ready = $row->appliedProduct?->publication_status === 'draft' && $applied && ! $waiting && $productBlockers === [];
            $sourceMl = $this->names->size($data['source']['name']);
            $websiteMl = $product?->variants->count() === 1 ? (int) $product->variants->first()->volume : null;
            $mismatch = $sourceMl !== null && $websiteMl !== null && $sourceMl !== $websiteMl;
            $photoProblems = collect($row->image_acquisition_outcomes ?? [])
                ->filter(fn ($outcome) => in_array($outcome['status'] ?? '', ['failed', 'blocked'], true));
            // Old outcomes have no reason code. Only the known cover dependency is retryable.
            $mediaReview = $applied && ! $waiting && ($photoProblems->contains(fn ($outcome) => ($outcome['status'] ?? '') === 'blocked'
                && (isset($outcome['reason']) ? $outcome['reason'] !== 'cover_pending'
                    : ($outcome['message'] ?? '') !== 'Foto sampul harus berhasil sebelum foto tambahan.'))
                || ($row->image_acquisition_status === ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS && $photoProblems->isEmpty()));
            $retryImages = $applied && $product && ! $waiting && ! $mediaReview
                && $row->image_acquisition_status === ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS
                && $photoProblems->isNotEmpty();
            $status = $applied ? ($product?->publication_status === 'published' ? 'Sudah terbit' : ($ready ? 'Siap terbit' : 'Masih draft'))
                : ($held ? 'Periksa ulang' : ($row->matched_product_id ? 'Produk dikenali' : ($mismatch ? 'Konfirmasi ukuran' : 'Pilih produk')));
            $labels = ['description' => 'Deskripsi', 'gender' => 'Peruntukan', 'category_id' => 'Kategori'];

            return [$row->id => [
                'product' => $product, 'applied' => $applied, 'held' => $held, 'waiting' => $waiting,
                'pending' => (bool) ($changes && ! $legacy), 'needs_refresh' => $legacy || $held,
                'complete' => (bool) $complete, 'ready' => $ready,
                'source_ml' => $sourceMl, 'website_ml' => $websiteMl, 'size_mismatch' => $mismatch,
                'blockers' => $applied ? $productBlockers : [], 'status' => $status,
                'status_class' => $applied ? 'text-emerald-700' : ($row->matched_product_id ? 'text-gray-700' : 'text-amber-800'),
                'field_labels' => collect(array_keys($data['fields']))->map(fn ($key) => $labels[$key] ?? $key)->join(', ') ?: 'tidak ada perubahan teks',
                'photo_count' => collect(['foto_utama_url', 'foto_2_url', 'foto_3_url'])->filter(fn ($key) => $data[$key] !== '')->count(),
                'description' => $data['fields']['description'] ?? ($this->copy->clean($data['source']['description']) ?: 'Belum ada deskripsi.'),
                'photo_problems' => $photoProblems->values()->all(), 'media_review' => $mediaReview, 'retry_images' => (bool) $retryImages,
            ]];
        });
    }
}
