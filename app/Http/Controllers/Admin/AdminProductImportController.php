<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ApplyProductImportBatch;
use App\Actions\Products\QueueProductImportImages;
use App\Exceptions\InvalidProductImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImportApplyRequest;
use App\Http\Requests\Admin\ProductImportImageAcquisitionRequest;
use App\Http\Requests\Admin\ProductImportPreviewRequest;
use App\Imports\Products\CanonicalProductCsv;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Services\ProductImportBatchRecorder;
use App\Services\ProductImportPreviewer;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProductImportController extends Controller
{
    public function create(Request $request): View
    {
        $batchId = filter_var($request->query('batch'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $batch = $batchId === false
            ? null
            : ProductImportBatch::query()
                ->with([
                    'actor:id,name',
                    'appliedBy:id,name',
                    'rows.matchedProduct:id,name,publication_status',
                    'rows.appliedProduct:id,name,publication_status',
                    'rows.imageAcquisitionRequestedBy:id,name',
                ])
                ->findOrFail($batchId);

        return view('admin.product-imports.create', $this->viewData(
            $batch ? $this->previewFromBatch($batch) : null,
            $batch
        ));
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, CanonicalProductCsv::HEADERS, escape: '');
            fputcsv($stream, CanonicalProductCsv::EXAMPLE, escape: '');
            fclose($stream);
        }, 'template-import-produk-qammaris.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function preview(
        ProductImportPreviewRequest $request,
        ProductImportPreviewer $previewer,
        ProductImportBatchRecorder $batchRecorder
    ): View {
        try {
            $file = $request->file('product_file');
            $preview = $previewer->preview($file);
            $batch = $batchRecorder->record($request->user(), $file, $preview);
        } catch (InvalidProductImportFile $exception) {
            return view('admin.product-imports.create', $this->viewData())
                ->withErrors(['product_file' => $exception->getMessage()]);
        }

        return view('admin.product-imports.create', $this->viewData($preview, $batch));
    }

    public function apply(
        ProductImportApplyRequest $request,
        ProductImportBatch $productImportBatch,
        ApplyProductImportBatch $applyProductImportBatch
    ): RedirectResponse {
        try {
            $batch = $applyProductImportBatch->handle($productImportBatch, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.product-imports.create', ['batch' => $productImportBatch->getKey()])
                ->with('error', $exception->getMessage());
        }

        $redirect = redirect()->route('admin.product-imports.create', ['batch' => $batch->getKey()]);

        return match ($batch->status) {
            ProductImportBatch::STATUS_APPLIED => $redirect->with(
                'success',
                sprintf(
                    'Batch #%d selesai: %d baris diterapkan dan %d baris ditahan.',
                    $batch->getKey(),
                    $batch->applied_rows,
                    $batch->blocked_rows
                )
            ),
            ProductImportBatch::STATUS_STALE,
            ProductImportBatch::STATUS_INVALID,
            ProductImportBatch::STATUS_FAILED => $redirect->with('error', $batch->failure_message),
            default => $redirect->with('error', 'Batch ini tidak dapat diterapkan.'),
        };
    }

    public function acquireImages(
        ProductImportImageAcquisitionRequest $request,
        ProductImportBatch $productImportBatch,
        QueueProductImportImages $queueProductImportImages
    ): RedirectResponse {
        try {
            $result = $queueProductImportImages->handle($productImportBatch, $request->user());
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.product-imports.create', ['batch' => $productImportBatch->getKey()])
                ->with('error', $exception->getMessage());
        }

        $redirect = redirect()->route('admin.product-imports.create', ['batch' => $productImportBatch->getKey()]);

        if ($result['queued_rows'] === 0) {
            return $redirect->with('success', 'Tidak ada kandidat gambar baru yang perlu diproses.');
        }

        return $redirect->with('success', sprintf(
            '%d baris dengan %d kandidat gambar masuk antrean. Draft tetap aman bila salah satu gambar gagal.',
            $result['queued_rows'],
            $result['candidate_images']
        ));
    }

    /**
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>
     */
    private function viewData(?array $preview = null, ?ProductImportBatch $batch = null): array
    {
        return [
            'headers' => CanonicalProductCsv::HEADERS,
            'preview' => $preview,
            'batch' => $batch,
            'recentBatches' => ProductImportBatch::query()
                ->with(['actor:id,name', 'appliedBy:id,name'])
                ->latest('id')
                ->limit(10)
                ->get(),
            'imageAcquisition' => $batch ? $this->imageAcquisitionSummary($batch) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewFromBatch(ProductImportBatch $batch): array
    {
        return [
            'fingerprint' => $batch->source_fingerprint,
            'catalog_state_fingerprint' => $batch->catalog_state_fingerprint,
            'rows' => $batch->rows->map(fn ($row): array => [
                'line_number' => $row->line_number,
                'status' => $row->status,
                'action' => $row->candidate_action,
                'data' => $row->normalized_data,
                'matched_product' => $row->matchedProduct ? [
                    'id' => $row->matchedProduct->getKey(),
                    'name' => $row->matchedProduct->name,
                    'publication_status' => $row->matchedProduct->publication_status,
                ] : null,
                'issues' => $row->issues,
                'apply_status' => $row->apply_status,
                'apply_message' => $row->apply_message,
                'applied_product' => $row->appliedProduct ? [
                    'id' => $row->appliedProduct->getKey(),
                    'name' => $row->appliedProduct->name,
                    'publication_status' => $row->appliedProduct->publication_status,
                ] : null,
                'image_acquisition_status' => $row->image_acquisition_status,
                'image_acquisition_outcomes' => $row->image_acquisition_outcomes ?? [],
            ])->all(),
            'summary' => [
                'total' => $batch->total_rows,
                'valid' => $batch->valid_rows,
                'review' => $batch->review_rows,
                'error' => $batch->error_rows,
            ],
            'skipped_blank_rows' => $batch->skipped_blank_rows ?? [],
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function imageAcquisitionSummary(ProductImportBatch $batch): array
    {
        $eligibleRows = $batch->rows->filter(fn (ProductImportRow $row): bool => in_array(
            $row->apply_status,
            [ProductImportRow::APPLY_CREATED, ProductImportRow::APPLY_UPDATED],
            true
        ) && $row->applied_product_id !== null);
        $outcomes = $eligibleRows->flatMap(fn (ProductImportRow $row) => $row->image_acquisition_outcomes ?? []);
        $sourceCount = $eligibleRows->sum(function (ProductImportRow $row): int {
            return collect(['foto_utama_url', 'foto_2_url', 'foto_3_url'])
                ->filter(fn (string $field): bool => trim((string) ($row->normalized_data[$field] ?? '')) !== '')
                ->count();
        });

        return [
            'eligible_rows' => $eligibleRows->count(),
            'source_count' => $sourceCount,
            'queued' => $eligibleRows->where('image_acquisition_status', ProductImportRow::IMAGE_QUEUED)->count(),
            'processing' => $eligibleRows->where('image_acquisition_status', ProductImportRow::IMAGE_PROCESSING)->count(),
            'stored' => $outcomes->where('status', 'stored')->count(),
            'failed' => $outcomes->filter(fn (array $outcome): bool => in_array($outcome['status'] ?? null, ['failed', 'blocked'], true))->count(),
            'retryable' => $eligibleRows->where('image_acquisition_status', ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS)->isNotEmpty(),
            'has_sources' => $sourceCount > 0,
        ];
    }
}
