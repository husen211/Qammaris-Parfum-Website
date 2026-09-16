<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidProductImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImportPreviewRequest;
use App\Imports\Products\CanonicalProductCsv;
use App\Services\ProductImportPreviewer;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProductImportController extends Controller
{
    public function create(): View
    {
        return view('admin.product-imports.create', [
            'headers' => CanonicalProductCsv::HEADERS,
            'preview' => null,
        ]);
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
        ProductImportPreviewer $previewer
    ): View {
        try {
            $preview = $previewer->preview($request->file('product_file'));
        } catch (InvalidProductImportFile $exception) {
            return view('admin.product-imports.create', [
                'headers' => CanonicalProductCsv::HEADERS,
                'preview' => null,
            ])->withErrors(['product_file' => $exception->getMessage()]);
        }

        return view('admin.product-imports.create', [
            'headers' => CanonicalProductCsv::HEADERS,
            'preview' => $preview,
        ]);
    }
}
