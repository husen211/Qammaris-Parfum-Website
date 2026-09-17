<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->brand = Brand::create([
            'name' => 'Target Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create([
            'name' => 'Eau de Parfum',
            'is_active' => true,
        ]);
    }

    public function test_combined_filters_use_normalized_state_and_active_offer_price(): void
    {
        $target = $this->createProduct('Target Perfume', [
            'gender' => 'Wanita',
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHour(),
            'base_price' => 999999,
        ], price: 150000);

        $this->createProduct('Wrong Audience', [
            'gender' => 'Pria',
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHour(),
        ], price: 150000);

        $otherBrand = Brand::create(['name' => 'Other Brand', 'is_active' => true]);
        $this->createProduct('Other Brand Product', [
            'brand_id' => $otherBrand->id,
            'gender' => 'Wanita',
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHour(),
        ], price: 150000);

        $response = $this->get(route('products.index', [
            'search' => '  Target   Brand  ',
            'brand' => [$this->brand->id, 999999, $this->brand->id],
            'category' => $this->category->id,
            'gender' => 'wanita',
            'price_min' => '100000',
            'price_max' => '200000',
            'availability' => Product::AVAILABILITY_AVAILABLE,
        ]));

        $response->assertOk();

        $products = $response->viewData('products');
        $state = $response->viewData('catalogState');

        $this->assertSame([$target->id], $products->pluck('id')->all());
        $this->assertSame('Target Brand', $state->search);
        $this->assertSame([$this->brand->id], $state->brandIds);
        $this->assertSame('Wanita', $state->gender);
        $this->assertSame(100000, $state->priceMin);
        $this->assertSame(200000, $state->priceMax);
        $this->assertSame(6, $state->activeFilterCount());
    }

    public function test_availability_filter_uses_effective_freshness_semantics(): void
    {
        $fresh = $this->createProduct('Fresh Available', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(35),
        ]);
        $stale = $this->createProduct('Stale Available', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(37),
        ]);
        $unknown = $this->createProduct('Unknown Availability', [
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'availability_checked_at' => null,
        ]);
        $soldOut = $this->createProduct('Sold Out Product', [
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now()->subDays(10),
        ]);

        $availableProducts = $this->get(route('products.index', ['availability' => 'available']))
            ->viewData('products');
        $unknownProducts = $this->get(route('products.index', ['availability' => 'unknown']))
            ->viewData('products');
        $soldOutProducts = $this->get(route('products.index', ['availability' => 'sold_out']))
            ->viewData('products');

        $this->assertSame([$fresh->id], $availableProducts->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$stale->id, $unknown->id], $unknownProducts->pluck('id')->all());
        $this->assertSame([$soldOut->id], $soldOutProducts->pluck('id')->all());
    }

    public function test_price_sort_uses_active_offer_and_keeps_missing_offer_last(): void
    {
        $expensive = $this->createProduct('Expensive Offer', ['base_price' => 1000], price: 300000);
        $cheap = $this->createProduct('Cheap Offer', ['base_price' => 900000], price: 100000);
        $middle = $this->createProduct('Middle Offer', ['base_price' => 2000], price: 200000);
        $missing = $this->createProduct('Missing Offer', ['base_price' => 500], price: null);

        $lowToHigh = $this->get(route('products.index', ['sort' => 'price_low']))->viewData('products');
        $highToLow = $this->get(route('products.index', ['sort' => 'price_high']))->viewData('products');

        $this->assertSame(
            [$cheap->id, $middle->id, $expensive->id, $missing->id],
            $lowToHigh->pluck('id')->all(),
        );
        $this->assertSame(
            [$expensive->id, $middle->id, $cheap->id, $missing->id],
            $highToLow->pluck('id')->all(),
        );
    }

    public function test_latest_and_popular_sorts_have_deterministic_tie_breakers(): void
    {
        $older = $this->createProduct('Older Product', [
            'published_at' => now()->subDay(),
            'view_count' => 5,
        ]);
        $firstTie = $this->createProduct('First Tie', [
            'published_at' => now(),
            'view_count' => 10,
        ]);
        $secondTie = $this->createProduct('Second Tie', [
            'published_at' => $firstTie->published_at,
            'view_count' => 10,
        ]);

        $latest = $this->get(route('products.index'))->viewData('products');
        $popular = $this->get(route('products.index', ['sort' => 'popular']))->viewData('products');

        $this->assertSame(
            [$secondTie->id, $firstTie->id, $older->id],
            $latest->pluck('id')->all(),
        );
        $this->assertSame(
            [$secondTie->id, $firstTie->id, $older->id],
            $popular->pluck('id')->all(),
        );
    }

    public function test_pagination_and_detail_links_preserve_only_allowlisted_context(): void
    {
        $products = collect();
        foreach (range(1, 25) as $index) {
            $products->push($this->createProduct(sprintf('Catalog Product %02d', $index), [
                'gender' => 'Unisex',
                'view_count' => $index,
                'availability_status' => Product::AVAILABILITY_UNKNOWN,
            ], price: 100000 + $index));
        }

        $query = [
            'search' => 'Catalog Product',
            'brand' => [$this->brand->id],
            'category' => $this->category->id,
            'gender' => 'Unisex',
            'price_min' => 100000,
            'price_max' => 200000,
            'availability' => 'unknown',
            'sort' => 'popular',
            'page' => 2,
            'ignored' => 'do-not-forward',
        ];

        $response = $this->get(route('products.index', $query));
        $response->assertOk();

        $paginator = $response->viewData('products');
        $state = $response->viewData('catalogState');
        $visibleProduct = $paginator->first();

        $this->assertSame(2, $paginator->currentPage());
        $this->assertCount(1, $paginator->items());
        $this->assertStringContainsString('search=Catalog%20Product', $paginator->previousPageUrl());
        $this->assertStringContainsString('sort=popular', $paginator->previousPageUrl());
        $this->assertStringNotContainsString('ignored', $paginator->previousPageUrl());
        $this->assertSame(2, $state->page);

        $detailUrl = route('products.show', array_merge(
            ['product' => $visibleProduct->slug],
            $state->query(),
        ));
        $response->assertSee($detailUrl);

        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertOk()
            ->assertSee(route('products.index', $state->query()));
    }

    public function test_invalid_query_values_are_ignored_and_both_sort_controls_use_default(): void
    {
        $inactiveBrand = Brand::create(['name' => 'Inactive Brand', 'is_active' => false]);
        $inactiveCategory = Category::create(['name' => 'Inactive Category', 'is_active' => false]);
        $this->createProduct('Visible Product');

        $response = $this->get(route('products.index', [
            'search' => ['not-a-string'],
            'brand' => [$inactiveBrand->id, 'invalid'],
            'category' => $inactiveCategory->id,
            'gender' => 'Semua',
            'price_min' => '-1',
            'price_max' => ['invalid'],
            'availability' => 'ready',
            'sort' => 'best',
            'page' => 'zero',
        ]));

        $response->assertOk();

        $state = $response->viewData('catalogState');
        $html = $response->getContent();

        $this->assertSame([], $state->query());
        $this->assertSame('latest', $state->sort);
        $this->assertSame(1, $state->page);
        $this->assertSame(2, substr_count($html, '<option value="latest" selected>Terbaru</option>'));
        $this->assertStringNotContainsString('Inactive Brand', $html);
        $this->assertStringNotContainsString('Inactive Category', $html);
    }

    private function createProduct(string $name, array $overrides = [], ?int $price = 100000): Product
    {
        $product = Product::create(array_merge([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => 'Deskripsi '.$name,
            'base_price' => $price ?? 50000,
            'gender' => 'Unisex',
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'view_count' => 0,
        ], $overrides));

        if ($price !== null) {
            ProductVariant::create([
                'product_id' => $product->id,
                'volume' => 100,
                'price' => $price,
                'sku' => null,
                'stock' => 0,
                'is_active' => true,
            ]);
        }

        return $product;
    }
}
