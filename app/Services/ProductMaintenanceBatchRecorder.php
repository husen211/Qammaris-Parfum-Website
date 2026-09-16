<?php

namespace App\Services;

use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\ProductImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductMaintenanceBatchRecorder
{
    public function __construct(private ProductImportPayloadHasher $payloadHasher) {}

    /** @param array<string, mixed> $preview */
    public function record(User $actor, UploadedFile $file, array $preview): ProductImportBatch
    {
        $idempotencyKey = hash('sha256', implode('|', [
            ProductMaintenanceCsv::VERSION,
            $actor->getKey(),
            $preview['fingerprint'],
            $preview['catalog_state_fingerprint'],
        ]));

        return DB::transaction(function () use ($actor, $file, $preview, $idempotencyKey): ProductImportBatch {
            $batch = ProductImportBatch::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'actor_id' => $actor->getKey(),
                    'source_filename' => basename($file->getClientOriginalName()),
                    'source_size' => $file->getSize() ?: 0,
                    'source_fingerprint' => $preview['fingerprint'],
                    'contract_version' => ProductMaintenanceCsv::VERSION,
                    'catalog_state_fingerprint' => $preview['catalog_state_fingerprint'],
                    'status' => ProductImportBatch::STATUS_PREVIEWED,
                    'total_rows' => $preview['summary']['total'],
                    'valid_rows' => $preview['summary']['valid'],
                    'review_rows' => $preview['summary']['review'],
                    'error_rows' => $preview['summary']['error'],
                    'skipped_blank_rows' => $preview['skipped_blank_rows'],
                ]
            );

            if ($batch->wasRecentlyCreated) {
                $batch->rows()->createMany(array_map(
                    fn (array $row): array => [
                        'line_number' => $row['line_number'],
                        'status' => $row['status'],
                        'candidate_action' => $row['action'],
                        'matched_product_id' => $row['matched_product']['id'] ?? null,
                        'normalized_data' => $row['data'],
                        'issues' => $row['issues'],
                        'payload_hash' => $this->payloadHasher->hash(
                            $row['data'],
                            $row['issues'],
                            $row['action']
                        ),
                        'before_snapshot' => $row['current_snapshot'],
                    ],
                    $preview['rows']
                ));
            }

            return $batch->load('rows');
        });
    }
}
