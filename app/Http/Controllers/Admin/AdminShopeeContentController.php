<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ApplyShopeeContent;
use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\QueueProductImportImages;
use App\Exceptions\InvalidProductImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShopeeContentPreviewRequest;
use App\Http\Requests\Admin\ShopeeContentWriteRequest;
use App\Models\Category;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Services\ShopeeContentPreviewer;
use DomainException;
use Illuminate\Http\Request;

class AdminShopeeContentController extends Controller
{
    public function index(Request $request, ShopeeContentPreviewer $preview)
    {
        $batch = null;
        if ($request->filled('batch')) {
            abort_unless(filter_var($request->query('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), 404);
            $batch = ProductImportBatch::where('contract_version', ShopeeContentPreviewer::VERSION)
                ->where('actor_id', $request->user()->id)->findOrFail($request->query('batch'));
        }
        $query = $batch?->rows()->with(['matchedProduct.brand', 'matchedProduct.variants', 'matchedProduct.images', 'appliedProduct.brand',
            'appliedProduct.category', 'appliedProduct.variants', 'appliedProduct.images']);
        $filter = in_array($request->query('filter'), ['review', 'ready', 'images_failed'], true) ? $request->query('filter') : 'all';
        $search = is_string($request->query('search')) ? mb_substr(trim($request->query('search')), 0, 100) : '';
        $ready = [];
        $pending = 0;
        if ($batch) {
            foreach ($batch->rows()->with(['appliedProduct.brand', 'appliedProduct.category', 'appliedProduct.variants', 'appliedProduct.images'])->get() as $r) {
                if ($r->matched_product_id && $r->apply_status === 'pending') {
                    $pending++;
                }
                if ($r->appliedProduct?->publication_status === 'draft' && $r->apply_status === 'updated'
                    && ! in_array($r->image_acquisition_status, ['queued', 'processing'], true)
                    && app(EvaluateProductPublicationReadiness::class)->handle($r->appliedProduct) === []) {
                    $ready[] = $r;
                }
            }
            if ($filter === 'review') {
                $query->whereNull('matched_product_id');
            } elseif ($filter === 'ready') {
                $query->whereIn('id', array_map(fn ($r) => $r->id, $ready));
            } elseif ($filter === 'images_failed') {
                $query->where('image_acquisition_status', 'completed_with_errors');
            }
            if ($search !== '') {
                $query->where('normalized_data->source->name', 'like', '%'.$search.'%');
            }
        }
        $rows = $query?->paginate(25)->withQueryString();

        return view('admin.shopee-imports.index', ['batch' => $batch, 'rows' => $rows, 'products' => $batch ? $preview->products() : collect(),
            'pendingCount' => $pending, 'ready' => $ready, 'filter' => $filter, 'search' => $search,
            'categories' => Category::active()->get()->keyBy('id'),
            'recent' => ProductImportBatch::where('contract_version', ShopeeContentPreviewer::VERSION)->where('actor_id', $request->user()->id)->latest('id')->limit(8)->get()]);
    }

    public function preview(ShopeeContentPreviewRequest $request, ShopeeContentPreviewer $preview)
    {
        try {
            $batch = $preview->preview($request->user(), $request->file('basic_file'), $request->file('media_file'));
        } catch (InvalidProductImportFile $error) {
            return redirect()->route('admin.shopee-imports.index')->withErrors(['basic_file' => $error->getMessage()]);
        }

        return redirect()->route('admin.shopee-imports.index', ['batch' => $batch->id])->with('success', 'File terbaca. Periksa produk dan perubahan sebelum diterapkan.');
    }

    public function choose(ShopeeContentWriteRequest $request, ProductImportBatch $productImportBatch, ProductImportRow $productImportRow, ApplyShopeeContent $action)
    {
        abort_unless($productImportRow->batch_id === $productImportBatch->id, 404);

        return $this->perform($productImportBatch, function () use ($request, $productImportBatch, $productImportRow, $action) {
            $action->choose($productImportBatch, $productImportRow, $request->user(), (int) $request->validated('product_id'), $request->boolean('replace_description'));

            return 'Pilihan disimpan. Periksa perubahan yang diperbarui sebelum menerapkan.';
        });
    }

    public function apply(ShopeeContentWriteRequest $request, ProductImportBatch $productImportBatch, ApplyShopeeContent $action)
    {
        return $this->perform($productImportBatch, function () use ($request, $productImportBatch, $action) {
            $count = $action->handle($productImportBatch, $request->user());
            $images = app(QueueProductImportImages::class)->handle($productImportBatch, $request->user());

            return $count.' produk dilengkapi; '.$images['queued_rows'].' produk masuk antrean foto. Produk belum diterbitkan otomatis.';
        });
    }

    public function images(ShopeeContentWriteRequest $request, ProductImportBatch $productImportBatch)
    {
        return $this->perform($productImportBatch, function () use ($request, $productImportBatch) {
            $result = app(QueueProductImportImages::class)->handle($productImportBatch, $request->user());

            return $result['queued_rows'].' produk masuk antrean foto. Muat ulang hasil untuk melihat perkembangannya.';
        });
    }

    public function publish(ShopeeContentWriteRequest $request, ProductImportBatch $productImportBatch, ApplyShopeeContent $action)
    {
        return $this->perform($productImportBatch, function () use ($request, $productImportBatch, $action) {
            $count = $action->publish($productImportBatch, $request->user(), array_map('intval', $request->validated('rows')));

            return $count.' produk berhasil diterbitkan.';
        });
    }

    private function perform(ProductImportBatch $batch, callable $operation)
    {
        $redirect = redirect()->route('admin.shopee-imports.index', ['batch' => $batch->id]);
        try {
            return $redirect->with('success', $operation());
        } catch (DomainException $error) {
            return $redirect->with('error', $error->getMessage());
        }
    }
}
