<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeBestSellersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_six_unique_eligible_products_rotate_daily_with_no_merchandising_writes(): void
    {
        $brand = Brand::create(['name' => 'Test Brand', 'is_active' => true]);
        $eligible = collect();
        foreach (range(1, 10) as $index) {
            $eligible->push($this->product('Best Seller '.$index, ['brand_id' => $brand->id]));
        }
        $this->product('Ordinary', ['is_best_seller' => false]);
        $this->product('Draft', ['publication_status' => Product::PUBLICATION_DRAFT]);
        $this->product('Archived', ['publication_status' => Product::PUBLICATION_ARCHIVED]);
        $this->product('Inactive', ['is_active' => false]);
        $this->product('Hidden')->forceFill(['qammaris_app_hidden' => true])->save();
        $before = Product::orderBy('id')->get()->toArray();

        $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Makassar'));
        $response = $this->get(route('home'));
        $response->assertOk()->assertDontSee('Rp 150.000')->assertSee('100 ml');
        $selection = $response->viewData('bestSellers');
        $first = $selection->pluck('id')->all();
        $this->assertCount(6, array_unique($first));
        foreach ($selection as $product) {
            $this->assertTrue($eligible->contains('id', $product->id));
            $this->assertTrue($product->relationLoaded('activeOffer'));
            $this->assertTrue($product->relationLoaded('primaryImage'));
            $response->assertSee(route('products.show', $product->slug));
        }
        $this->assertSame($first, $this->get(route('home'))->viewData('bestSellers')->pluck('id')->all());
        $seen = collect($first);
        foreach (range(1, 10) as $day) {
            $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Makassar')->addDays($day));
            $current = $this->get(route('home'))->viewData('bestSellers')->pluck('id')->all();
            $this->assertCount(6, array_unique($current));
            if ($day === 1) {
                $this->assertNotSame($first, $current);
                $this->assertSame(array_slice($first, 1), array_slice($current, 0, 5));
            }
            $seen = $seen->concat($current);
        }
        $this->assertEqualsCanonicalizing($eligible->pluck('id')->all(), $seen->unique()->all());
        $this->assertSame($before, Product::orderBy('id')->get()->toArray());
    }

    public function test_selection_changes_at_palu_midnight_and_not_utc_midnight(): void
    {
        foreach (range(1, 8) as $index) {
            $this->product('Daily '.$index);
        }
        $this->travelTo(Carbon::parse('2026-10-06 23:59:59', 'Asia/Makassar'));
        $before = $this->get(route('home'))->viewData('bestSellers')->pluck('id')->all();
        $this->travelTo(Carbon::parse('2026-10-07 00:00:00', 'Asia/Makassar'));
        $after = $this->get(route('home'))->viewData('bestSellers')->pluck('id')->all();
        $this->assertNotSame($before, $after);
        $this->travelTo(Carbon::parse('2026-10-07 08:00:00', 'Asia/Makassar'));
        $this->assertSame($after, $this->get(route('home'))->viewData('bestSellers')->pluck('id')->all());
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_small_cohort_keeps_sold_out_and_missing_media_with_local_fallback(): void
    {
        $soldOut = $this->product('Sold Out', ['availability_status' => Product::AVAILABILITY_SOLD_OUT]);
        $another = $this->product('Another');
        $response = $this->get(route('home'));
        $response->assertOk()->assertSee('Habis')->assertSee('Foto sedang dilengkapi');
        $response->assertSee(asset('images/product-placeholder.svg'));
        $response->assertDontSee('placehold.co');
        $this->assertEqualsCanonicalizing([$soldOut->id, $another->id], $response->viewData('bestSellers')->pluck('id')->all());
        $this->assertSame(2, substr_count($response->getContent(), 'data-product-card'));
        $this->assertSame(2, substr_count($response->getContent(), '<h3 class="catalog-card__name">'));
    }

    public function test_empty_selection_does_not_promote_unmarked_or_draft_products(): void
    {
        $this->product('Not Marked', ['is_best_seller' => false]);
        $this->product('Draft Only', ['publication_status' => Product::PUBLICATION_DRAFT]);
        $response = $this->get(route('home'));
        $response->assertOk()->assertSee('Pilihan produk terlaris sedang disiapkan.');
        $response->assertDontSee('data-product-card')->assertDontSee('data-best-seller-next');
        $this->assertCount(0, $response->viewData('bestSellers'));
    }

    private function product(string $name, array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name' => $name,
            'description' => 'Test description',
            'base_price' => 150000,
            'is_best_seller' => true,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'availability_source' => 'qammaris_app',
        ], $overrides));
        ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 150000, 'stock' => 0, 'is_active' => true]);

        return $product;
    }
}
