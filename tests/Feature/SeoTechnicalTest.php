<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** SEO-01: one host, indexable catalog pages only, honest structured data and a sitemap of canonical URLs. */
class SeoTechnicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'https://qammarisparfum.id']);
    }

    public function test_www_moves_permanently_to_the_app_url_host_with_path_and_query(): void
    {
        $this->get('http://www.qammarisparfum.id/products?page=2')
            ->assertStatus(301)->assertRedirect('https://qammarisparfum.id/products?page=2');
        $this->post('https://www.qammarisparfum.id/cart/add')->assertStatus(308);
        $this->get('https://qammarisparfum.id/products')->assertOk();
    }

    public function test_catalog_pages_are_self_canonical_and_filtered_views_are_not_indexed(): void
    {
        $this->product('Plain', Product::AVAILABILITY_AVAILABLE);

        $this->assertHead('/products', 'index,follow', route('products.index'));
        $this->assertHead('/products?page=2', 'index,follow', route('products.index', ['page' => 2]));
        $this->assertHead('/products?utm_source=ig', 'index,follow', route('products.index'));
        $this->assertHead('/products?sort=price_low', 'noindex,follow', route('products.index', ['sort' => 'price_low']));
        $this->assertHead('/products?availability=available&page=2', 'noindex,follow', route('products.index', ['availability' => 'available', 'page' => 2]));
        $this->assertHead('/products?search=oud', 'noindex,follow', route('products.index', ['search' => 'oud']));
    }

    public function test_product_offer_states_stock_like_the_page_and_has_a_breadcrumb(): void
    {
        $offers = [];
        foreach ([Product::AVAILABILITY_AVAILABLE, Product::AVAILABILITY_SOLD_OUT, Product::AVAILABILITY_UNKNOWN] as $state) {
            $product = $this->product('Stock '.$state, $state);
            $ld = $this->jsonLd($this->get(route('products.show', $product->slug))->assertOk()->getContent());
            $offers[$state] = $ld['Product']['offers'];
            $this->assertSame('https://schema.org/NewCondition', $offers[$state]['itemCondition']);
            $this->assertSame(
                ['Beranda', 'Katalog', $product->name],
                array_column($ld['BreadcrumbList']['itemListElement'], 'name'),
            );
        }

        $this->assertSame('https://schema.org/InStock', $offers[Product::AVAILABILITY_AVAILABLE]['availability']);
        $this->assertSame('https://schema.org/OutOfStock', $offers[Product::AVAILABILITY_SOLD_OUT]['availability']);
        $this->assertArrayNotHasKey('availability', $offers[Product::AVAILABILITY_UNKNOWN]);
    }

    public function test_location_page_defines_the_store_with_the_confirmed_nap(): void
    {
        $store = $this->jsonLd($this->get(route('store.location'))->assertOk()->assertSee('Siranindi')->getContent())['Store'];

        $this->assertSame(route('store.location').'#store', $store['@id']);
        $this->assertSame('Jl. Sis Aljufri, Siranindi, Kec. Palu Barat', $store['address']['streetAddress']);
        $this->assertSame('94111', $store['address']['postalCode']);
        $this->assertArrayNotHasKey('telephone', $store, 'ORD-07: no real store number outside production');
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame('+6285144924931', $this->jsonLd($this->get(route('store.location'))->getContent())['Store']['telephone']);
        $this->assertContains('https://www.instagram.com/qammaris', $store['sameAs']);
        $this->assertArrayNotHasKey('aggregateRating', $store);
    }

    public function test_sitemap_lists_only_indexable_pages_with_real_change_dates(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $product = $this->product('Dated', Product::AVAILABILITY_AVAILABLE);
        $this->travelTo('2026-10-25 10:00:00');

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString(route('quiz.index'), $xml);
        $this->assertStringContainsString('<url><loc>'.route('store.location').'</loc></url>', $xml);
        $this->assertStringContainsString('<url><loc>'.route('products.index').'</loc><lastmod>'.$product->updated_at->toAtomString().'</lastmod></url>', $xml);
        $this->assertStringNotContainsString(now()->toAtomString(), $xml);
    }

    public function test_cards_with_a_photo_carry_no_placeholder_text(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/with-photo.webp', 'photo');
        $withPhoto = $this->product('With Photo', Product::AVAILABILITY_AVAILABLE);
        ProductImage::create(['product_id' => $withPhoto->id, 'image_path' => 'products/with-photo.webp', 'is_primary' => true, 'sort_order' => 0]);
        $html = $this->get('/products')->assertOk()->getContent();
        $this->assertStringNotContainsString('Foto sedang dilengkapi', $html);

        $this->product('No Photo', Product::AVAILABILITY_AVAILABLE);
        $this->assertSame(1, substr_count($this->get('/products')->getContent(), '>Foto sedang dilengkapi</span>'));
    }

    private function assertHead(string $uri, string $robots, string $canonical): void
    {
        $this->get($uri)->assertOk()
            ->assertSee('<meta name="robots" content="'.$robots.'">', false)
            ->assertSee('<link rel="canonical" href="'.e($canonical).'">', false);
    }

    /** @return array<string, array> JSON-LD blocks keyed by @type */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

        return collect($blocks[1])->map(fn ($json) => json_decode($json, true))->keyBy('@type')->all();
    }

    private function product(string $name, string $availability): Product
    {
        $brand = Brand::firstOrCreate(['name' => 'SEO Brand'], ['is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'name' => $name,
            'description' => 'Deskripsi '.$name,
            'base_price' => 150000,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => $availability,
            'availability_source' => 'qammaris_app',
        ]);
        ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 150000, 'stock' => 0, 'is_active' => true]);

        return $product;
    }
}
