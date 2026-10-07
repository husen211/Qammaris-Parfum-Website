<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreInfo;
use App\Support\InquiryWhatsApp;
use App\Support\ProductCatalogState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::active()->orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();
        $catalogState = ProductCatalogState::fromRequest($request, $brands, $categories);

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

        $this->applySort($query, $catalogState->sort);

        $products = $query
            ->paginate(24)
            ->appends($catalogState->query(includePage: false));

        return view('products.index', compact('products', 'brands', 'categories', 'catalogState'));
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

        return view('products.show', compact('product', 'relatedProducts', 'catalogState', 'productInquiryUrl'));
    }

    private function applyAvailabilityFilter(Builder $query, string $availability): void
    {
        $query->effectiveAvailability($availability);
    }

    private function applySort(Builder $query, string $sort): void
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
        }

        $query->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('products.id');
    }
}
