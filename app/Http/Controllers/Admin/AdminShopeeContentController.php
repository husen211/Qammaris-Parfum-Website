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
use App\Support\SearchMatcher;
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
        $filter = in_array($request->query('filter'), ['work', 'all', 'complete', 'review', 'ready', 'images_failed'], true) ? $request->query('filter') : 'work';
        $search = SearchMatcher::term($request->query('search'));
        $ready = [];
        $pending = 0;
        $work = [];
        $complete = [];
        $needsRefresh = 0;
        if ($batch) {
            foreach ($batch->rows()->with(['matchedProduct.brand', 'matchedProduct.category', 'matchedProduct.variants', 'matchedProduct.images', 'matchedProduct.externalIdentities',
                'appliedProduct.brand', 'appliedProduct.category', 'appliedProduct.variants', 'appliedProduct.images', 'appliedProduct.externalIdentities'])->get() as $r) {
                $product = $r->appliedProduct ?? $r->matchedProduct;
                $changes = $product && $r->apply_status === 'pending' && $r->matched_product_id
                    && $preview->hasChanges($r->normalized_data, $product);
                $legacy = $r->apply_status === 'pending' && ($r->normalized_data['fingerprint_version'] ?? null) !== ShopeeContentPreviewer::FINGERPRINT_VERSION;
                if ($legacy || $r->apply_status === 'blocked_protected') {
                    $needsRefresh++;
                }
                $pending += $changes && ! $legacy ? 1 : 0;
                $isComplete = $product && $r->matched_product_id && $product->publication_status === 'published'
                    && ! $changes && $r->apply_status !== 'blocked_protected'
                    && ! in_array($r->image_acquisition_status, ['queued', 'processing', 'completed_with_errors'], true)
                    && app(EvaluateProductPublicationReadiness::class)->handle($product) === [];
                if ($isComplete) {
                    $complete[] = $r->id;
                } else {
                    $work[] = $r->id;
                }
                if ($r->appliedProduct?->publication_status === 'draft' && $r->apply_status === 'updated'
                    && ! in_array($r->image_acquisition_status, ['queued', 'processing'], true)
                    && app(EvaluateProductPublicationReadiness::class)->handle($r->appliedProduct) === []) {
                    $ready[] = $r;
                }
            }
            if ($filter === 'work') {
                $query->whereIn('id', $work);
            } elseif ($filter === 'complete') {
                $query->whereIn('id', $complete);
            } elseif ($filter === 'review') {
                $query->where(fn ($q) => $q->whereNull('matched_product_id')->orWhere('apply_status', 'blocked_protected'));
            } elseif ($filter === 'ready') {
                $query->whereIn('id', array_map(fn ($r) => $r->id, $ready));
            } elseif ($filter === 'images_failed') {
                $query->where('image_acquisition_status', 'completed_with_errors');
            }
            if ($search !== '') {
                SearchMatcher::constrain($query->getQuery(), $search, (clone $query)->reorder()->withoutEagerLoads()->get(['id', 'normalized_data']),
                    fn ($row) => [$row->normalized_data['source']['name'] ?? '']);
            }
        }
        $rows = $query?->paginate(25)->withQueryString();

        return view('admin.shopee-imports.index', ['batch' => $batch, 'rows' => $rows, 'products' => $batch ? $preview->products() : collect(),
            'pendingCount' => $pending, 'ready' => $ready, 'filter' => $filter, 'search' => $search,
            'workCount' => count($work), 'completeCount' => count($complete), 'needsRefresh' => $needsRefresh,
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
            $ready = $action->choose($productImportBatch, $productImportRow, $request->user(), (int) $request->validated('product_id'), $request->boolean('replace_description'),
                $request->boolean('confirm_size_mismatch') ? (int) $request->validated('confirmed_website_ml') : null);

            return $ready ? 'Pilihan disimpan. Periksa perubahan sebelum menerapkan.'
                : 'Produk pilihan tersimpan, tetapi ukuran berbeda. Buka baris tersebut untuk konfirmasi ukuran Shopee salah atau pilih produk lain.';
        });
    }

    public function refresh(ShopeeContentWriteRequest $request, ProductImportBatch $productImportBatch, ApplyShopeeContent $action)
    {
        return $this->perform($productImportBatch, function () use ($request, $productImportBatch, $action) {
            $count = $action->refresh($productImportBatch, $request->user());

            return $count.' baris diperiksa ulang. Produk belum diubah. Periksa perubahan di daftar, lalu terapkan produk yang diperlukan.';
        });
    }

    public function apply(ShopeeContentWriteRequest $request, ProductImportBatch $productImportBatch, ApplyShopeeContent $action)
    {
        return $this->perform($productImportBatch, function () use ($request, $productImportBatch, $action) {
            $count = $action->handle($productImportBatch, $request->user());
            $images = app(QueueProductImportImages::class)->handle($productImportBatch, $request->user());

            $blocked = $productImportBatch->rows()->where('apply_status', 'blocked_protected')->count();

            return $count.' produk dilengkapi; '.$images['queued_rows'].' produk masuk antrean foto. '
                .($blocked ? $blocked.' baris ditahan: tekan Periksa ulang perubahan. ' : '').'Produk belum diterbitkan otomatis.';
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
        $redirect = redirect()->route('admin.shopee-imports.index', ['batch' => $batch->id] + request()->only(['filter', 'search', 'page']));
        try {
            return $redirect->with('success', $operation());
        } catch (DomainException $error) {
            return $redirect->with('error', $error->getMessage());
        }
    }
}
