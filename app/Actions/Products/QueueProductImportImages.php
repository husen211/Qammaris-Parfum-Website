<?php

namespace App\Actions\Products;

use App\Jobs\AcquireProductImportRowImages;
use App\Models\ProductImage;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class QueueProductImportImages
{
    /**
     * @return array{queued_rows: int, candidate_images: int}
     */
    public function handle(ProductImportBatch $batch, User $actor): array
    {
        $rowIds = [];
        $candidateImages = 0;

        DB::transaction(function () use ($batch, $actor, &$rowIds, &$candidateImages): void {
            $lockedBatch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());

            if ($lockedBatch->status !== ProductImportBatch::STATUS_APPLIED) {
                throw new DomainException('Akuisisi gambar hanya tersedia untuk batch yang sudah selesai di-apply.');
            }

            $rows = $lockedBatch->rows()
                ->whereIn('apply_status', [ProductImportRow::APPLY_CREATED, ProductImportRow::APPLY_UPDATED])
                ->whereNotNull('applied_product_id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                if (in_array($row->image_acquisition_status, [ProductImportRow::IMAGE_QUEUED, ProductImportRow::IMAGE_PROCESSING], true)) {
                    continue;
                }

                $outcomes = $this->prepareOutcomes($row);
                $candidateImages += count(array_filter(
                    $outcomes,
                    fn (array $outcome): bool => $outcome['status'] === 'pending'
                ));

                if ($outcomes === []) {
                    $row->forceFill([
                        'image_acquisition_status' => ProductImportRow::IMAGE_NO_SOURCES,
                        'image_acquisition_requested_by' => $actor->getKey(),
                        'image_acquisition_requested_at' => now(),
                        'image_acquisition_completed_at' => now(),
                        'image_acquisition_outcomes' => [],
                    ])->save();

                    continue;
                }

                $hasPending = collect($outcomes)->contains('status', 'pending');

                $row->forceFill([
                    'image_acquisition_status' => $hasPending
                        ? ProductImportRow::IMAGE_QUEUED
                        : ProductImportRow::IMAGE_COMPLETED,
                    'image_acquisition_requested_by' => $actor->getKey(),
                    'image_acquisition_requested_at' => now(),
                    'image_acquisition_completed_at' => $hasPending ? null : now(),
                    'image_acquisition_outcomes' => $outcomes,
                ])->save();

                if ($hasPending) {
                    $rowIds[] = $row->getKey();
                }
            }
        });

        foreach ($rowIds as $rowId) {
            AcquireProductImportRowImages::dispatch($rowId)->afterCommit();
        }

        return [
            'queued_rows' => count($rowIds),
            'candidate_images' => $candidateImages,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prepareOutcomes(ProductImportRow $row): array
    {
        $existing = collect($row->image_acquisition_outcomes ?? [])->keyBy('slot');
        $seenUrls = [];
        $outcomes = [];

        foreach ([
            'foto_utama_url' => 'Foto utama',
            'foto_2_url' => 'Foto 2',
            'foto_3_url' => 'Foto 3',
        ] as $slot => $label) {
            $sourceUrl = trim((string) ($row->normalized_data[$slot] ?? ''));

            if ($sourceUrl === '') {
                continue;
            }

            $previous = $existing->get($slot);
            $storedImageExists = is_array($previous)
                && ($previous['status'] ?? null) === 'stored'
                && isset($previous['product_image_id'])
                && ProductImage::query()
                    ->whereKey($previous['product_image_id'])
                    ->where('product_id', $row->applied_product_id)
                    ->exists();

            if ($storedImageExists) {
                $outcomes[] = $previous;
                $seenUrls[$sourceUrl] = true;

                continue;
            }

            if (isset($seenUrls[$sourceUrl])) {
                $outcomes[] = [
                    'slot' => $slot,
                    'label' => $label,
                    'source_url' => $sourceUrl,
                    'status' => 'skipped_duplicate',
                    'message' => 'URL sama sudah dipakai pada slot sebelumnya.',
                ];

                continue;
            }

            $seenUrls[$sourceUrl] = true;
            $outcomes[] = [
                'slot' => $slot,
                'label' => $label,
                'source_url' => $sourceUrl,
                'status' => 'pending',
                'message' => null,
            ];
        }

        return $outcomes;
    }
}
