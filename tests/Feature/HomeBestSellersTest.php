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

    public function test_ten_unique_eligible_products_rotate_daily_with_no_merchandising_writes(): void
    {
        $brand = Brand::create(['name' => 'Test Brand', 'is_active' => true]);
        $eligible = collect();
        foreach (range(1, 14) as $index) {
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
        $this->assertCount(10, array_unique($first));
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
            $this->assertCount(10, array_unique($current));
            if ($day === 1) {
                $this->assertNotSame($first, $current);
                $this->assertSame(array_slice($first, 1), array_slice($current, 0, 9));
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

    public function test_brand_logo_falls_back_to_the_slugged_name_when_the_stored_slug_differs(): void
    {
        // UI-BRANDS-01: production stores Aoera under an old test slug; its mark is images/brands/aoera.webp.
        $aoera = Brand::create(['name' => 'Aoera', 'is_active' => true]);
        $aoera->forceFill(['slug' => 'p8-03-aoera-test'])->save();
        $this->product('Aoera Satu', ['brand_id' => $aoera->id]);

        $this->get(route('home'))->assertOk()->assertSee('src="'.asset('images/brands/aoera.webp').'"', false)->assertSee('alt="Aoera"', false);
    }

    public function test_rail_ends_with_full_catalog_link_and_distributor_section_lists_every_brand_on_sale(): void
    {
        // UX-02: logo for brands with a supplied mark, name otherwise; brands without published products are left out.
        $afnan = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $bali = Brand::create(['name' => 'Brand Tanpa Logo', 'is_active' => true]);
        $empty = Brand::create(['name' => 'Brand Tanpa Produk', 'is_active' => true]);
        $inactive = Brand::create(['name' => 'Brand Nonaktif', 'is_active' => false]);
        $this->product('Afnan Satu', ['brand_id' => $afnan->id]);
        $this->product('Bali Satu', ['brand_id' => $bali->id]);
        $this->product('Bali Dua', ['brand_id' => $bali->id]);
        $this->product('Draft Saja', ['brand_id' => $empty->id, 'publication_status' => Product::PUBLICATION_DRAFT]);
        $this->product('Nonaktif', ['brand_id' => $inactive->id, 'is_best_seller' => false]);

        $response = $this->get(route('home'))->assertOk();
        $brands = $response->viewData('distributorBrands');
        $this->assertSame(['Afnan', 'Brand Tanpa Logo'], $brands->pluck('name')->all(), 'logos first, then by product count');
        $response->assertSee('src="'.asset('images/brands/afnan.webp').'"', false)->assertSee('alt="Afnan"', false)
            ->assertSee('<span class="brand-carousel__name font-mayluxa">Brand Tanpa Logo</span>', false)
            ->assertDontSee('Brand Tanpa Produk')->assertDontSee('Brand Nonaktif')
            ->assertDontSee('animate-slide-pause')->assertSee('data-brand-carousel', false);

        // The rail ends with a link to the full catalog; the button under the section stays.
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'data-best-seller-catalog-end'));
        $this->assertGreaterThan(strrpos($html, 'data-product-card'), strpos($html, 'data-best-seller-catalog-end'));
        $this->assertSame(2, substr_count($html, 'Lihat Katalog Lengkap'));
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
