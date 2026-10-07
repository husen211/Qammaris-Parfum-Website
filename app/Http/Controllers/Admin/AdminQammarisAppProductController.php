<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\PrepareQammarisAppDrafts;
use App\Http\Controllers\Controller;
use App\Jobs\SyncQammarisAppProducts;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Services\QammarisAppClient;
use App\Support\SearchMatcher;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminQammarisAppProductController extends Controller
{
    public function index(Request $request, QammarisAppClient $client, EvaluateProductPublicationReadiness $readiness)
    {
        $filter = in_array($request->query('status'), ['draft', 'unlinked', 'hidden', 'price_review'], true)
            ? $request->query('status') : 'all';
        $search = SearchMatcher::term($request->query('search'));
        $identities = ProductExternalIdentity::where('provider', 'qammaris_app')->get()->keyBy('external_product_id');
        $products = Product::with(['brand', 'category', 'variants', 'images'])->whereIn('id', $identities->pluck('product_id'))->get()->keyBy('id');
        $sources = DB::table('qammaris_app_products')->orderByDesc('updated_at')->orderBy('id')->get()->map(function ($row) use ($identities, $products) {
            $source = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $product = $products->get($identities->get($row->id)?->product_id);
            $offer = $product?->variants->firstWhere('is_active', true);
            $price = $source['price'];
            $priceReview = ! $source['hidden'] && (! is_int($price) || $price <= 0 || $price > 99999999
                || ($product && (! $offer || (float) $offer->price !== (float) $price)));

            return ['source' => $source, 'product' => $product, 'price_review' => $priceReview];
        });
        $counts = [
            'all' => $sources->count(),
            'draft' => $sources->filter(fn ($r) => ! $r['source']['hidden'] && $r['product']?->publication_status === 'draft')->count(),
            'unlinked' => $sources->filter(fn ($r) => ! $r['source']['hidden'] && ! $r['product'])->count(),
            'hidden' => $sources->filter(fn ($r) => $r['source']['hidden'])->count(),
            'price_review' => $sources->where('price_review', true)->count(),
        ];
        $filtered = $sources->filter(function ($r) use ($filter) {
            $matches = match ($filter) {
                'draft' => ! $r['source']['hidden'] && $r['product']?->publication_status === 'draft',
                'unlinked' => ! $r['source']['hidden'] && ! $r['product'],
                'hidden' => $r['source']['hidden'],
                'price_review' => $r['price_review'],
                default => true,
            };

            return $matches;
        })->values();
        if ($search !== '') {
            $filtered = SearchMatcher::filter($filtered, $search,
                fn ($r) => [$r['source']['name'], $r['source']['brand'], $r['product']?->name],
                fn ($r) => [$r['source']['sku'], $r['source']['id']]);
        }
        $pageInput = $request->query('page', 1);
        $page = max(1, min(is_scalar($pageInput) ? (int) $pageInput : 1, max(1, (int) ceil($filtered->count() / 25))));
        $rows = new LengthAwarePaginator($filtered->forPage($page, 25)->map(function ($r) use ($readiness) {
            $r['blockers'] = $r['product']?->publication_status === 'draft' ? $readiness->handle($r['product']) : [];

            return $r;
        }), $filtered->count(), 25, $page, ['path' => route('admin.app-products.index'), 'query' => array_filter(['status' => $filter, 'search' => $search])]);
        $batch = null;
        if ($request->filled('batch')) {
            $request->validate(['batch' => ['integer', 'min:1']]);
            $batch = ProductImportBatch::where('contract_version', PrepareQammarisAppDrafts::VERSION)
                ->with('rows')->findOrFail($request->integer('batch'));
        }
        $state = DB::table('qammaris_app_sync_states')->where('id', 'products')->first();

        return view('admin.app-products.index', compact('rows', 'filter', 'search', 'counts', 'batch', 'state') + ['configured' => $client->configured()]);
    }

    public function preview(Request $request, PrepareQammarisAppDrafts $drafts)
    {
        try {
            $batch = $drafts->preview([], $request->user());

            return redirect()->route('admin.app-products.index', ['batch' => $batch->id])->with('success', 'Pratinjau siap. Produk belum dibuat atau ditayangkan.');
        } catch (DomainException) {
            return back()->with('error', 'Belum ada snapshot aplikasi untuk dipratinjau.');
        }
    }

    public function apply(Request $request, ProductImportBatch $productImportBatch, PrepareQammarisAppDrafts $drafts)
    {
        abort_unless($productImportBatch->contract_version === PrepareQammarisAppDrafts::VERSION, 404);
        abort_unless($productImportBatch->actor_id === $request->user()->id, 403);
        $request->validate(['confirm' => ['accepted']]);
        try {
            $batch = $drafts->apply($productImportBatch->id, $request->user());

            return redirect()->route('admin.app-products.index', ['batch' => $batch->id, 'status' => 'draft'])
                ->with('success', $batch->applied_rows.' draft disiapkan. Produk existing dan pasangan ambigu tetap dipertahankan.');
        } catch (DomainException) {
            return redirect()->route('admin.app-products.index', ['batch' => $productImportBatch->id])
                ->with('error', 'Data berubah sejak pratinjau. Buat pratinjau baru sebelum melanjutkan.');
        }
    }

    public function sync(QammarisAppClient $client)
    {
        if (! $client->configured()) {
            return back()->with('error', 'Koneksi aplikasi belum dikonfigurasi.');
        }
        try {
            SyncQammarisAppProducts::dispatch();
        } catch (Throwable) {
            Log::warning('Qammaris admin sync dispatch failed.');

            return back()->with('error', 'Sinkronisasi belum masuk antrean. Coba lagi.');
        }

        return back()->with('success', 'Sinkronisasi masuk antrean. Muat ulang setelah worker selesai.');
    }
}
