<?php

namespace App\Actions\Products;

use App\Jobs\AcquireProductImportRowImages;
use App\Models\ProductImage;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ShopeeContentImageTarget;
use App\Services\ShopeeContentPreviewer;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class QueueProductImportImages
{
    /**
     * @return array{queued_rows: int, candidate_images: int}
     */
    public function handle(ProductImportBatch $batch, ?User $actor): array
    {
        $rowIds = [];
        $candidateImages = 0;

        DB::transaction(function () use ($batch, $actor, &$rowIds, &$candidateImages): void {
            $lockedBatch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());

            if ($lockedBatch->contract_version === ShopeeContentPreviewer::VERSION) {
                if (! $actor) {
                    throw new DomainException('Impor Shopee membutuhkan admin yang mengunggah file.');
                }
                app(ApplyShopeeContent::class)->assertBatch($lockedBatch, $actor);
            }

            if ($actor === null && (! in_array($lockedBatch->contract_version, [PrepareQammarisAppDrafts::VERSION, PairQammarisShopeeDrafts::VERSION], true) || $lockedBatch->actor_id !== null)) {
                throw new DomainException('Machine attribution is restricted to Owner-authorized Qammaris draft batches.');
            }

            if ($lockedBatch->contract_version === PairQammarisShopeeDrafts::VERSION) {
                app(PairQammarisShopeeDrafts::class)->assertEnvironment();
            }

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

                if ($lockedBatch->contract_version === ShopeeContentPreviewer::VERSION) {
                    app(ApplyShopeeContent::class)->assertPayload($row);
                    try {
                        app(ShopeeContentImageTarget::class)->assert($row, $row->appliedProduct);
                    } catch (DomainException $error) {
                        $outcomes = $this->prepareOutcomes($row);
                        foreach ($outcomes as &$outcome) {
                            if ($outcome['status'] === 'pending') {
                                $outcome['status'] = 'blocked';
                                $outcome['reason'] = 'target_conflict';
                                $outcome['message'] = $error->getMessage().' Upload ulang kedua file untuk pemeriksaan baru.';
                            }
                        }
                        unset($outcome);
                        $row->forceFill(['image_acquisition_status' => ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS,
                            'image_acquisition_outcomes' => $outcomes, 'image_acquisition_completed_at' => now(),
                            'image_acquisition_requested_by' => $actor->id, 'image_acquisition_requested_at' => now()])->save();

                        continue;
                    }
                }

                $outcomes = $this->prepareOutcomes($row);
                $candidateImages += count(array_filter(
                    $outcomes,
                    fn (array $outcome): bool => $outcome['status'] === 'pending'
                ));

                if ($outcomes === []) {
                    $row->forceFill([
                        'image_acquisition_status' => ProductImportRow::IMAGE_NO_SOURCES,
                        'image_acquisition_requested_by' => $actor?->getKey(),
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
                    'image_acquisition_requested_by' => $actor?->getKey(),
                    'image_acquisition_requested_at' => now(),
                    'image_acquisition_completed_at' => $hasPending ? null : now(),
                    'image_acquisition_outcomes' => $outcomes,
                ])->save();

                if ($hasPending) {
                    $rowIds[] = $row->getKey();
                }
            }
        });

        foreach ($rowIds as $index => $rowId) {
            try {
                $dispatch = AcquireProductImportRowImages::dispatch($rowId);
                if ($actor === null || $batch->contract_version === ShopeeContentPreviewer::VERSION) {
                    $dispatch->onConnection('database');
                }
                $dispatch->afterCommit();
                unset($dispatch);
            } catch (Throwable $error) {
                if ($batch->contract_version !== ShopeeContentPreviewer::VERSION) {
                    throw $error;
                }
                foreach (array_slice($rowIds, $index) as $pendingRowId) {
                    app(\App\Actions\Products\AcquireProductImportRowImages::class)->markUnexpectedFailure($pendingRowId);
                }
                throw new DomainException('Konten tersimpan, tetapi antrean foto belum tersedia. Tekan Coba unduh foto lagi.');
            }
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
