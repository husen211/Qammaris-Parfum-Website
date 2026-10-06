<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ApplyProductMaintenanceBatch;
use App\Exceptions\InvalidProductImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductMaintenanceApplyRequest;
use App\Http\Requests\Admin\ProductMaintenancePreviewRequest;
use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\ProductImportBatch;
use App\Services\ProductMaintenanceBatchRecorder;
use App\Services\ProductMaintenancePreviewer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProductMaintenanceController extends Controller
{
    public function create(Request $request): View
    {
        $batchId = filter_var($request->query('batch'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $batch = $batchId === false
            ? null
            : ProductImportBatch::query()
                ->where('contract_version', ProductMaintenanceCsv::VERSION)
                ->with([
                    'actor:id,name',
                    'appliedBy:id,name',
                    'rows.matchedProduct:id,name,publication_status',
                    'rows.appliedProduct:id,name,publication_status',
                ])
                ->findOrFail($batchId);

        return view('admin.product-maintenance.create', $this->viewData(
            $batch ? $this->previewFromBatch($batch) : null,
            $batch
        ));
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ProductMaintenanceCsv::HEADERS, escape: '');
            fputcsv($stream, ProductMaintenanceCsv::EXAMPLE, escape: '');
            fclose($stream);
        }, 'template-maintenance-produk-qammaris.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(
        ProductMaintenancePreviewRequest $request,
        ProductMaintenancePreviewer $previewer,
        ProductMaintenanceBatchRecorder $batchRecorder
    ): View {
        try {
            $file = $request->file('maintenance_file');
            $preview = $previewer->preview($file);
            $batch = $batchRecorder->record($request->user(), $file, $preview);
        } catch (InvalidProductImportFile $exception) {
            return view('admin.product-maintenance.create', $this->viewData())
                ->withErrors(['maintenance_file' => $exception->getMessage()]);
        }

        return view('admin.product-maintenance.create', $this->viewData($preview, $batch));
    }

    public function apply(
        ProductMaintenanceApplyRequest $request,
        ProductImportBatch $productImportBatch,
        ApplyProductMaintenanceBatch $applyProductMaintenanceBatch
    ) {
        $this->assertMaintenanceBatch($productImportBatch);

        try {
            $batch = $applyProductMaintenanceBatch->handle($productImportBatch, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.product-maintenance.create', ['batch' => $productImportBatch->getKey()])
                ->with('error', $exception->getMessage());
        }

        $message = match ($batch->status) {
            ProductImportBatch::STATUS_APPLIED => sprintf(
                'Apply maintenance selesai: %d baris diterapkan dan %d baris dilewati atau ditahan.',
                $batch->applied_rows,
                $batch->blocked_rows
            ),
            ProductImportBatch::STATUS_STALE,
            ProductImportBatch::STATUS_INVALID,
            ProductImportBatch::STATUS_FAILED => $batch->failure_message,
            default => 'Batch maintenance tidak dapat diterapkan.',
        };

        return redirect()
            ->route('admin.product-maintenance.create', ['batch' => $batch->getKey()])
            ->with($batch->status === ProductImportBatch::STATUS_APPLIED ? 'success' : 'error', $message);
    }

    /**
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>
     */
    private function viewData(?array $preview = null, ?ProductImportBatch $batch = null): array
    {
        return [
            'headers' => ProductMaintenanceCsv::HEADERS,
            'preview' => $preview,
            'batch' => $batch,
            'recentBatches' => ProductImportBatch::query()
                ->where('contract_version', ProductMaintenanceCsv::VERSION)
                ->with('actor:id,name')
                ->latest('id')
                ->limit(10)
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function previewFromBatch(ProductImportBatch $batch): array
    {
        return [
            'fingerprint' => $batch->source_fingerprint,
            'catalog_state_fingerprint' => $batch->catalog_state_fingerprint,
            'rows' => $batch->rows->map(function ($row): array {
                $data = $row->normalized_data ?? [];

                return [
                    'line_number' => $row->line_number,
                    'status' => $row->status,
                    'action' => $row->candidate_action,
                    'data' => $data,
                    'changes' => $data['_changes'] ?? [],
                    'apply_status' => $row->apply_status,
                    'apply_message' => $row->apply_message,
                    'matched_product' => $row->matchedProduct ? [
                        'id' => $row->matchedProduct->getKey(),
                        'name' => $row->matchedProduct->name,
                        'publication_status' => $row->matchedProduct->publication_status,
                    ] : null,
                    'issues' => $row->issues ?? [],
                ];
            })->all(),
            'summary' => [
                'total' => $batch->total_rows,
                'valid' => $batch->valid_rows,
                'review' => $batch->review_rows,
                'error' => $batch->error_rows,
            ],
            'skipped_blank_rows' => $batch->skipped_blank_rows ?? [],
        ];
    }

    private function assertMaintenanceBatch(ProductImportBatch $batch): void
    {
        abort_unless($batch->contract_version === ProductMaintenanceCsv::VERSION, 404);
    }
}
