<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\BestSellerArrangement;
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

    public function test_dropdown_forms_preserve_multiple_brands_and_filter_context_on_both_surfaces(): void
    {
        $otherBrand = Brand::create(['name' => 'Other & Brand', 'is_active' => true]);
        $response = $this->get(route('products.index', [
            'search' => 'perfume',
            'brand' => [$this->brand->id, $otherBrand->id],
            'category' => $this->category->id,
            'gender' => 'Unisex',
            'availability' => 'sold_out',
            'price_min' => 100000,
            'price_max' => 300000,
            'sort' => 'price_low',
            'page' => 2,
        ]))->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['//form[@id="desktopFilterForm"]', '//dialog[@id="mobileFilter"]/div/form'] as $formPath) {
            $this->assertCount(2, $xpath->query($formPath.'//input[@name="brand[]" and @checked]'));
            $this->assertCount(0, $xpath->query($formPath.'//input[@name="brand[]" and @data-catalog-autosubmit]'));
            $this->assertCount(0, $xpath->query($formPath.'//input[@name="page"]'));
            // UX-02: price fields show thousands separators; the submitted value is reduced to digits.
            foreach (['search' => 'perfume', 'sort' => 'price_low', 'price_min' => '100.000', 'price_max' => '300.000'] as $name => $value) {
                $this->assertSame($value, $xpath->query($formPath.'//input[@name="'.$name.'"]')->item(0)->getAttribute('value'));
            }
            foreach (['category' => (string) $this->category->id, 'gender' => 'Unisex', 'availability' => 'sold_out'] as $name => $value) {
                $this->assertSame($value, $xpath->query($formPath.'//select[@name="'.$name.'"]/option[@selected]')->item(0)->getAttribute('value'));
            }
        }
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
        $expensive = $this->createProduct('Expensive Offer', ['base_price' => 1000, 'is_best_seller' => true], price: 300000);
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
            'is_best_seller' => true,
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

        $latestResponse = $this->get(route('products.index', ['sort' => 'latest']));
        $latest = $latestResponse->viewData('products');
        $this->assertSame(['sort' => 'latest'], $latestResponse->viewData('catalogState')->query());
        $latestResponse->assertSee('name="sort" value="latest"', false);
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
        $this->assertSame('best_sellers', $state->sort);
        $this->assertSame(1, $state->page);
        $this->assertSame(2, substr_count($html, '<option value="best_sellers" selected>Terlaris dahulu</option>'));
        $this->assertStringNotContainsString('Inactive Brand', $html);
        $this->assertStringNotContainsString('Inactive Category', $html);
    }

    public function test_default_best_sellers_precede_newer_ordinary_products_across_pages_and_filters(): void
    {
        $ordinary = $this->createProduct('Catalog Ordinary', ['published_at' => now()]);
        $bestSellers = collect();
        foreach (range(1, 26) as $index) {
            $bestSellers->push($this->createProduct('Catalog Best Seller '.$index, [
                'is_best_seller' => true,
                'published_at' => now()->subDay(),
            ]));
        }
        $this->createProduct('Hidden Best Seller', ['is_best_seller' => true])->forceFill(['qammaris_app_hidden' => true])->save();
        $this->createProduct('Draft Best Seller', ['is_best_seller' => true, 'publication_status' => Product::PUBLICATION_DRAFT]);

        // UX-02: best sellers follow the daily price-varied arrangement, then ordinary products.
        $arranged = BestSellerArrangement::order($bestSellers->mapWithKeys(fn ($product) => [$product->id => 100000])->all(), now());
        $response = $this->get(route('products.index'));
        $response->assertOk();
        $this->assertSame([], $response->viewData('catalogState')->query());
        $this->assertSame(array_slice($arranged, 0, 24), $response->viewData('products')->pluck('id')->all());
        $this->assertStringNotContainsString('sort=', $response->viewData('products')->nextPageUrl());

        $secondPage = $this->get(route('products.index', ['page' => 2]));
        $this->assertSame([...array_slice($arranged, 24), $ordinary->id], $secondPage->viewData('products')->pluck('id')->all());
        $secondSlug = $bestSellers->firstWhere('id', $arranged[24])->slug;
        $detailUrl = route('products.show', ['product' => $secondSlug, 'page' => 2]);
        $secondPage->assertSee($detailUrl);
        $this->get($detailUrl)->assertOk()->assertSee(route('products.index', ['page' => 2]));

        $filtered = $this->get(route('products.index', ['search' => 'Catalog', 'brand' => [$this->brand->id]]));
        $this->assertSame($bestSellers->reverse()->take(24)->pluck('id')->values()->all(), $filtered->viewData('products')->pluck('id')->all());
    }

    public function test_default_order_uses_latest_then_id_within_each_group_and_null_dates_last(): void
    {
        // Best sellers lead in their arranged order; ordinary products keep latest-then-id with undated last.
        $bestSeller = $this->createProduct('Best Seller', ['is_best_seller' => true, 'published_at' => now()->subWeek()]);
        $older = $this->createProduct('Older Ordinary', ['published_at' => now()->subDay()]);
        $newer = $this->createProduct('New Ordinary');
        $tie = $this->createProduct('Tied Ordinary', ['published_at' => $newer->published_at]);
        $undated = $this->createProduct('Undated Ordinary', ['published_at' => null]);
        $this->assertSame([$bestSeller->id, $tie->id, $newer->id, $older->id, $undated->id], $this->get(route('products.index'))->viewData('products')->pluck('id')->all());
    }

    public function test_default_best_sellers_put_affordable_picks_first_without_sorting_by_price(): void
    {
        $prices = [189000, 199000, 215000, 229000, 245000, 265000, 275000, 289000, 299000, 325000, 345000, 375000, 410000, 450000, 520000, 620000, 700000, 780000];
        foreach ($prices as $index => $price) {
            $this->createProduct('Priced Best Seller '.$index, ['is_best_seller' => true], $price);
        }
        $this->createProduct('Cheap Ordinary', [], 100000);
        $shown = $this->get(route('products.index'))->viewData('products')->pluck('variants_min_price')->map(fn ($price) => (int) $price)->all();
        $firstSix = array_slice($shown, 0, 6);
        $this->assertCount(3, array_filter($firstSix, fn ($price) => $price <= 265000), 'affordable picks lead');
        $this->assertCount(1, array_filter($firstSix, fn ($price) => $price >= 410000), 'a premium pick is mixed in early');
        $sorted = array_slice($shown, 0, 18);
        sort($sorted);
        $this->assertNotSame($sorted, array_slice($shown, 0, 18), 'not ascending by price');
        $this->assertSame(100000, $shown[18], 'ordinary products follow the best sellers');

        // Other sorts are unchanged.
        $low = $this->get(route('products.index', ['sort' => 'price_low']))->viewData('products')->pluck('variants_min_price')->map(fn ($price) => (int) $price)->all();
        $this->assertSame([100000, 189000], array_slice($low, 0, 2));
    }

    public function test_price_filter_accepts_thousands_separators(): void
    {
        $this->createProduct('Under Range', [], 150000);
        $inside = $this->createProduct('Inside Range', [], 250000);
        $this->createProduct('Over Range', [], 1200000);
        $response = $this->get('/products?price_min=200.000&price_max=Rp%201.000.000');
        $state = $response->assertOk()->viewData('catalogState');
        $this->assertSame([200000, 1000000], [$state->priceMin, $state->priceMax]);
        $this->assertSame([$inside->id], $response->viewData('products')->pluck('id')->all());
        $response->assertSee('value="200.000"', false)->assertSee('value="1.000.000"', false)->assertSee('inputmode="numeric"', false);
        $this->assertSame(['price_min' => 200000, 'price_max' => 1000000], $state->query());
        // Malformed values are still ignored.
        $this->assertNull($this->get('/products?price_min=2.00.000')->viewData('catalogState')->priceMin);
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
