<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidProductImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImportPreviewRequest;
use App\Imports\Products\CanonicalProductCsv;
use App\Models\ProductImportBatch;
use App\Services\ProductImportBatchRecorder;
use App\Services\ProductImportPreviewer;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProductImportController extends Controller
{
    public function create(): View
    {
        return view('admin.product-imports.create', $this->viewData());
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
                ->with('actor:id,name')
                ->latest('id')
                ->limit(10)
                ->get(),
        ];
    }
}
