<?php

namespace App\Services;

use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;

class ProductImportBatchCsvReport
{
    public function __construct(private SpreadsheetSafeCell $safeCell) {}

    public const VERSION = 'qammaris-import-audit-v1';

    public const HEADERS = [
        'report_version',
        'batch_id',
        'batch_status',
        'source_filename',
        'source_fingerprint',
        'line_number',
        'row_status',
        'candidate_action',
        'provider',
        'kode_produk',
        'nama_produk',
        'apply_status',
        'apply_message',
        'product_id',
        'publication_status',
        'resolution_status',
        'resolution_fields',
        'image_status',
        'image_stored',
        'image_failed_or_blocked',
        'issues',
        'preview_actor',
        'previewed_at',
        'applied_actor',
        'applied_at',
        'resolved_actor',
        'resolved_at',
        'batch_failure_message',
    ];

    /** @param resource $stream */
    public function write(ProductImportBatch $batch, $stream): void
    {
        $batch->loadMissing(['actor:id,name', 'appliedBy:id,name']);
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, escape: '');

        $batch->rows()
            ->with([
                'appliedProduct:id,publication_status',
                'matchedProduct:id,publication_status',
                'resolvedBy:id,name',
            ])
            ->orderBy('line_number')
            ->get()
            ->each(function (ProductImportRow $row) use ($batch, $stream): void {
                fputcsv($stream, array_map(
                    fn (mixed $value): string => $this->safeCell->sanitize($value),
                    $this->row($batch, $row)
                ), escape: '');
            });
    }

    /** @return array<int, mixed> */
    private function row(ProductImportBatch $batch, ProductImportRow $row): array
    {
        $data = $row->normalized_data ?? [];
        $imageOutcomes = collect($row->image_acquisition_outcomes ?? []);
        $product = $row->appliedProduct ?? $row->matchedProduct;

        return [
            self::VERSION,
            $batch->getKey(),
            $batch->status,
            $batch->source_filename,
            $batch->source_fingerprint,
            $row->line_number,
            $row->status,
            $row->candidate_action,
            $row->provider,
            $row->external_product_id,
            $data['nama_produk'] ?? null,
            $row->apply_status,
            $row->apply_message,
            $product?->getKey(),
            $product?->publication_status,
            $row->resolution_status,
            implode('|', $row->resolution_fields ?? []),
            $row->image_acquisition_status,
            $imageOutcomes->where('status', 'stored')->count(),
            $imageOutcomes->filter(fn (array $outcome): bool => in_array(
                $outcome['status'] ?? null,
                ['failed', 'blocked'],
                true
            ))->count(),
            $this->issues($row->issues ?? []),
            $batch->actor?->name,
            $batch->created_at?->toIso8601String(),
            $batch->appliedBy?->name,
            $batch->applied_at?->toIso8601String(),
            $row->resolvedBy?->name,
            $row->resolved_at?->toIso8601String(),
            $batch->failure_message,
        ];
    }

    /** @param array<int, array<string, mixed>> $issues */
    private function issues(array $issues): string
    {
        return collect($issues)
            ->map(fn (array $issue): string => implode(':', array_filter([
                $issue['severity'] ?? null,
                $issue['field'] ?? null,
                $issue['message'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '')))
            ->implode(' | ');
    }
}
