<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ApplyProductImportBatch;
use App\Actions\Products\QueueProductImportImages;
use App\Actions\Products\ResolveProtectedProductImportRow;
use App\Exceptions\InvalidProductImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImportApplyRequest;
use App\Http\Requests\Admin\ProductImportImageAcquisitionRequest;
use App\Http\Requests\Admin\ProductImportPreviewRequest;
use App\Http\Requests\Admin\ProductImportResolutionRequest;
use App\Imports\Products\CanonicalProductCsv;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Services\ProductCatalogSnapshotCsv;
use App\Services\ProductImportBatchCsvReport;
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
                ->where('contract_version', CanonicalProductCsv::VERSION)
                ->with([
                    'actor:id,name',
                    'appliedBy:id,name',
                    'rows.matchedProduct:id,name,publication_status',
                    'rows.appliedProduct:id,brand_id,category_id,name,slug,description,fragrance_notes,gender,is_best_seller,publication_status,availability_status,stock_quantity',
                    'rows.imageAcquisitionRequestedBy:id,name',
                    'rows.resolvedBy:id,name',
                    'rows.appliedProduct.brand:id,name',
                    'rows.appliedProduct.category:id,name',
                    'rows.appliedProduct.variants:id,product_id,volume,price,stock',
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

    public function catalogSnapshot(ProductCatalogSnapshotCsv $snapshot): StreamedResponse
    {
        return response()->streamDownload(function () use ($snapshot): void {
            $stream = fopen('php://output', 'wb');
            $snapshot->write($stream);
            fclose($stream);
        }, 'qammaris-catalog-snapshot-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function report(
        ProductImportBatch $productImportBatch,
        ProductImportBatchCsvReport $report
    ): StreamedResponse {
        $this->assertImportBatch($productImportBatch);

        return response()->streamDownload(function () use ($productImportBatch, $report): void {
            $stream = fopen('php://output', 'wb');
            $report->write($productImportBatch, $stream);
            fclose($stream);
        }, sprintf(
            'qammaris-import-batch-%d-%s.csv',
            $productImportBatch->getKey(),
            $productImportBatch->created_at->format('Ymd-His')
        ), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
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
        $this->assertImportBatch($productImportBatch);

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
        $this->assertImportBatch($productImportBatch);

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

    public function resolveProtected(
        ProductImportResolutionRequest $request,
        ProductImportBatch $productImportBatch,
        ProductImportRow $productImportRow,
        ResolveProtectedProductImportRow $resolver
    ): RedirectResponse {
        $this->assertImportBatch($productImportBatch);

        try {
            $row = $resolver->handle(
                $productImportBatch,
                $productImportRow,
                $request->user(),
                $request->validated('fields')
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.product-imports.create', ['batch' => $productImportBatch->getKey()])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.product-imports.create', ['batch' => $productImportBatch->getKey()])
            ->with('success', sprintf(
                'Baris %d selesai direview: %d field diterapkan manual.',
                $row->line_number,
                count($row->resolution_fields ?? [])
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
                ->where('contract_version', CanonicalProductCsv::VERSION)
                ->with(['actor:id,name', 'appliedBy:id,name'])
                ->latest('id')
                ->limit(10)
                ->get(),
            'imageAcquisition' => $batch ? $this->imageAcquisitionSummary($batch) : null,
            'protectedResolutions' => $batch ? $this->protectedResolutionData($batch) : collect(),
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
                'resolution_status' => $row->resolution_status,
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

    private function protectedResolutionData(ProductImportBatch $batch)
    {
        return $batch->rows
            ->where('apply_status', ProductImportRow::APPLY_BLOCKED_PROTECTED)
            ->map(function (ProductImportRow $row): array {
                $product = $row->appliedProduct;
                $data = $row->normalized_data ?? [];
                $offer = $product?->variants->first();

                $fields = [
                    ['key' => 'name', 'label' => 'Nama', 'current' => $product?->name, 'import' => $data['nama_produk'] ?? null, 'available' => trim((string) ($data['nama_produk'] ?? '')) !== ''],
                    ['key' => 'description', 'label' => 'Deskripsi', 'current' => $product?->description, 'import' => $data['deskripsi_produk'] ?? null, 'available' => trim((string) ($data['deskripsi_produk'] ?? '')) !== ''],
                    ['key' => 'brand', 'label' => 'Brand', 'current' => $product?->brand?->name, 'import' => $data['brand'] ?? null, 'available' => trim((string) ($data['brand'] ?? '')) !== ''],
                    ['key' => 'category', 'label' => 'Kategori', 'current' => $product?->category?->name, 'import' => $data['kategori'] ?? null, 'available' => trim((string) ($data['kategori'] ?? '')) !== ''],
                    ['key' => 'gender', 'label' => 'Gender', 'current' => $product?->gender, 'import' => $data['gender'] ?? null, 'available' => trim((string) ($data['gender'] ?? '')) !== ''],
                    ['key' => 'is_best_seller', 'label' => 'Terlaris', 'current' => $product?->is_best_seller ? 'Ya' : 'Tidak', 'import' => ($data['terlaris'] ?? false) ? 'Ya' : 'Tidak', 'available' => true],
                    ['key' => 'stock_quantity', 'label' => 'Stok snapshot', 'current' => $product?->stock_quantity, 'import' => $data['stok'] ?? null, 'available' => ($data['stok'] ?? '') !== ''],
                    ['key' => 'fragrance_notes', 'label' => 'Fragrance notes', 'current' => $this->formatNotes($product?->fragrance_notes), 'import' => $this->formatImportNotes($data), 'available' => collect(['top_notes', 'middle_notes', 'base_notes'])->contains(fn (string $key): bool => ($data[$key] ?? []) !== [])],
                    ['key' => 'offer', 'label' => 'Harga + ukuran', 'current' => $offer ? sprintf('Rp %s · %s ml', number_format((float) $offer->price, 0, ',', '.'), $offer->volume) : 'Belum ada', 'import' => ($data['harga'] ?? '') !== '' && ($data['ukuran_ml'] ?? '') !== '' ? sprintf('Rp %s · %s ml', number_format((float) $data['harga'], 0, ',', '.'), $data['ukuran_ml']) : null, 'available' => ($data['harga'] ?? '') !== '' && ($data['ukuran_ml'] ?? '') !== ''],
                ];

                return [
                    'row' => $row,
                    'product' => $product,
                    'fields' => $fields,
                ];
            })->values();
    }

    private function formatNotes(?array $notes): string
    {
        if (! $notes) {
            return 'Belum ada';
        }

        return collect(['top', 'middle', 'base'])
            ->map(fn (string $group): string => ucfirst($group).': '.implode(', ', $notes[$group] ?? []))
            ->implode(' · ');
    }

    private function formatImportNotes(array $data): string
    {
        return collect(['top' => 'top_notes', 'middle' => 'middle_notes', 'base' => 'base_notes'])
            ->filter(fn (string $field): bool => ($data[$field] ?? []) !== [])
            ->map(fn (string $field, string $group): string => ucfirst($group).': '.implode(', ', $data[$field]))
            ->implode(' · ');
    }

    private function assertImportBatch(ProductImportBatch $batch): void
    {
        abort_unless($batch->contract_version === CanonicalProductCsv::VERSION, 404);
    }
}
