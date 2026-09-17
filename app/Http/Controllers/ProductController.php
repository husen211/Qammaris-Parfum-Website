<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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

        $query = Product::with(['brand', 'category', 'primaryImage'])
            ->withMin([
                'variants as variants_min_price' => fn ($variantQuery) => $variantQuery->where('is_active', true),
            ], 'price')
            ->published();

        if ($catalogState->search !== null) {
            $query->search($catalogState->search);
        }

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

        $this->applySort($query, $catalogState->sort);

        $products = $query
            ->paginate(24)
            ->appends($catalogState->query(includePage: false));

        return view('products.index', compact('products', 'brands', 'categories', 'catalogState'));
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($product->isPublished(), 404);

        $catalogState = ProductCatalogState::fromRequest(
            $request,
            Brand::active()->get(['id']),
            Category::active()->get(['id']),
        );

        $product->load([
            'brand',
            'category',
            'images',
            'variants' => fn ($variantQuery) => $variantQuery->where('is_active', true),
            'variants.product',
        ]);
        $product->incrementViewCount();

        $relatedProducts = Product::with(['brand', 'primaryImage'])
            ->withMin([
                'variants as variants_min_price' => fn ($variantQuery) => $variantQuery->where('is_active', true),
            ], 'price')
            ->published()
            ->where('brand_id', $product->brand_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'relatedProducts', 'catalogState'));
    }

    private function applyAvailabilityFilter(Builder $query, string $availability): void
    {
        if ($availability === Product::AVAILABILITY_AVAILABLE) {
            $query->where('availability_status', Product::AVAILABILITY_AVAILABLE)
                ->whereNotNull('availability_checked_at')
                ->where('availability_checked_at', '>=', now()->subHours(Product::AVAILABILITY_FRESH_HOURS));

            return;
        }

        if ($availability === Product::AVAILABILITY_SOLD_OUT) {
            $query->where('availability_status', Product::AVAILABILITY_SOLD_OUT);

            return;
        }

        $freshnessThreshold = now()->subHours(Product::AVAILABILITY_FRESH_HOURS);
        $query->where(function (Builder $availabilityQuery) use ($freshnessThreshold): void {
            $availabilityQuery
                ->whereNull('availability_status')
                ->orWhere('availability_status', Product::AVAILABILITY_UNKNOWN)
                ->orWhereNotIn('availability_status', [
                    Product::AVAILABILITY_UNKNOWN,
                    Product::AVAILABILITY_AVAILABLE,
                    Product::AVAILABILITY_SOLD_OUT,
                ])
                ->orWhere(function (Builder $staleAvailableQuery) use ($freshnessThreshold): void {
                    $staleAvailableQuery
                        ->where('availability_status', Product::AVAILABILITY_AVAILABLE)
                        ->where(function (Builder $checkedAtQuery) use ($freshnessThreshold): void {
                            $checkedAtQuery
                                ->whereNull('availability_checked_at')
                                ->orWhere('availability_checked_at', '<', $freshnessThreshold);
                        });
                });
        });
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

        $query->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('products.id');
    }
}
