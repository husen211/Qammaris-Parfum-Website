<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreInfo;
use App\Support\BestSellerArrangement;
use App\Support\CatalogCollection;
use App\Support\InquiryWhatsApp;
use App\Support\ProductCatalogState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return $this->catalog($request, null);
    }

    /** SEO-03: /koleksi/{collection}, the catalog fixed to one Peruntukan value. */
    public function collection(Request $request, string $collection)
    {
        return $this->catalog($request, CatalogCollection::gender($collection) ?? abort(404));
    }

    /** SEO-03: /brand/{brand}, the catalog fixed to one configured brand. */
    public function brand(Request $request, Brand $brand)
    {
        return $this->catalog($request, CatalogCollection::brand($brand) ?? abort(404));
    }

    private function catalog(Request $request, ?CatalogCollection $collection)
    {
        $brands = Brand::active()->orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();
        $catalogState = ProductCatalogState::fromRequest($request, $brands, $categories);
        if ($collection) {
            $catalogState = $catalogState->lockedTo($collection->locked);
        }

        $query = Product::with(['brand', 'category', 'primaryImage', 'activeOffer'])
            ->withMin([
                'variants as variants_min_price' => fn ($variantQuery) => $variantQuery->where('is_active', true),
            ], 'price')
            ->published();

        if ($catalogState->brandIds !== []) {
            $query->whereIn('brand_id', $catalogState->brandIds);
        }

        if ($catalogState->categoryId !== null) {
            $query->byCategory($catalogState->categoryId);
        }

        if ($catalogState->gender !== null) {
            $query->where('gender', $catalogState->gender);
        }

        if ($catalogState->priceMin !== null || $catalogState->priceMax !== null) {
            $query->whereHas('variants', function (Builder $variantQuery) use ($catalogState): void {
                $variantQuery->where('is_active', true)
                    ->when(
                        $catalogState->priceMin !== null,
                        fn (Builder $priceQuery) => $priceQuery->where('price', '>=', $catalogState->priceMin)
                    )
                    ->when(
                        $catalogState->priceMax !== null,
                        fn (Builder $priceQuery) => $priceQuery->where('price', '<=', $catalogState->priceMax)
                    );
            });
        }

        if ($catalogState->availability !== null) {
            $this->applyAvailabilityFilter($query, $catalogState->availability);
        }

        if ($catalogState->search !== null) {
            $query->search($catalogState->search, $catalogState->sort === ProductCatalogState::DEFAULT_SORT);
        }

        $this->applySort($query, $catalogState->sort, $catalogState->search !== null);

        $products = $query
            ->paginate(24)
            ->appends($catalogState->query(includePage: false));

        return view('products.index', compact('products', 'brands', 'categories', 'catalogState', 'collection'));
    }

    public function show(Request $request, Product $product, InquiryWhatsApp $inquiryWhatsApp)
    {
        abort_unless($product->isPubliclyVisible(), 404);

        $catalogState = ProductCatalogState::fromRequest(
            $request,
            Brand::active()->get(['id']),
            Category::active()->get(['id']),
        );

        $product->load([
            'brand',
            'category',
            'primaryImage',
            'images',
            'activeOffer',
        ]);
        $product->incrementViewCount();

        $relatedProducts = Product::with(['brand', 'primaryImage', 'activeOffer'])
            ->published()
            ->where('brand_id', $product->brand_id)
            ->where('id', '!=', $product->id)
            ->orderByDesc('is_best_seller')
            ->orderByDesc('id')
            ->take(4)
            ->get();

        $storeInfo = StoreInfo::query()->first() ?? new StoreInfo;
        $productInquiryUrl = $product->activeOffer
            ? $inquiryWhatsApp->productUrl(
                $storeInfo->whatsapp_number,
                $product,
                $product->activeOffer,
                $product->effective_availability === Product::AVAILABILITY_SOLD_OUT ? 'restock' : 'stock',
            )
            : null;

        // SEO-03: "Kembali" returns to the collection page the product was opened from.
        $collection = CatalogCollection::fromContext($request->query('koleksi'));
        $catalogUrl = $collection ? $collection->url($catalogState->query()) : route('products.index', $catalogState->query());
        $brandPage = $product->brand ? CatalogCollection::brand($product->brand) : null;

        return view('products.show', compact('product', 'relatedProducts', 'catalogState', 'productInquiryUrl', 'catalogUrl', 'brandPage'));
    }

    private function applyAvailabilityFilter(Builder $query, string $availability): void
    {
        $query->effectiveAvailability($availability);
    }

    private function orderBestSellersForDiscovery(Builder $query): void
    {
        $prices = Product::query()->published()->bestSellers()
            ->withMin(['variants as variants_min_price' => fn ($variants) => $variants->where('is_active', true)], 'price')
            ->pluck('variants_min_price', 'id')
            ->all();
        $ids = BestSellerArrangement::order($prices, now());
        if ($ids === []) {
            return;
        }

        $cases = implode(' ', array_fill(0, count($ids), 'WHEN ? THEN ?'));
        $bindings = [];
        foreach ($ids as $position => $id) {
            array_push($bindings, $id, $position);
        }
        $query->orderByRaw("CASE products.id {$cases} ELSE ".count($ids).' END', $bindings);
    }

    private function applySort(Builder $query, string $sort, bool $searching = false): void
    {
        if ($sort === 'price_low' || $sort === 'price_high') {
            $query->orderByRaw('CASE WHEN variants_min_price IS NULL THEN 1 ELSE 0 END')
                ->orderBy('variants_min_price', $sort === 'price_low' ? 'asc' : 'desc')
                ->orderByDesc('products.id');

            return;
        }

        if ($sort === 'popular') {
            $query->orderByDesc('view_count')->orderByDesc('products.id');

            return;
        }

        if ($sort === ProductCatalogState::DEFAULT_SORT) {
            $query->orderByDesc('is_best_seller');
            // UX-02: best sellers in a daily, price-varied order (search keeps its relevance order).
            if (! $searching) {
                $this->orderBestSellersForDiscovery($query);
            }
        }

        $query->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('products.id');
    }
}
