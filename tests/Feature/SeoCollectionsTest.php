<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO-03: permanent collection and brand pages on the normal catalog engine. */
class SeoCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private Brand $afnan;

    private Brand $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        cache()->flush();
        $this->afnan = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $this->other = Brand::create(['name' => 'Merek Lain', 'is_active' => true]);
        $this->product('Afnan Pria Satu', 'Pria', $this->afnan);
        $this->product('Afnan Pria Dua', 'Pria', $this->afnan);
        $this->product('Afnan Wanita', 'Wanita', $this->afnan);
        $this->product('Lain Pria', 'Pria', $this->other);
        $this->product('Lain Unisex', 'Unisex', $this->other);
    }

    public function test_gender_collection_is_an_indexable_catalog_fixed_to_one_gender(): void
    {
        $url = route('products.collection', 'parfum-pria');
        $html = $this->get('/koleksi/parfum-pria')->assertOk()
            ->assertSee('<title>Parfum Pria Original di Palu - Qammaris Perfumes</title>', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('Afnan Pria Satu')->assertSee('Lain Pria')
            ->assertDontSee('Afnan Wanita')->assertDontSee('Lain Unisex')
            ->assertSee('href="'.route('store.location').'">toko kami di Palu</a>', false)
            ->assertSee('href="'.route('products.collection', 'parfum-unisex').'">koleksi parfum unisex</a>', false)
            ->assertSee('Terlaris di sini:')
            ->assertSee('action="'.$url.'"', false)
            ->assertDontSee('name="gender"', false)
            ->assertSee('name="brand[]"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>Parfum Pria</h1>#', $html);
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Parfum Pria</a>#', $html);
        $this->assertStringContainsString('"name":"Parfum Pria","item":"'.$url.'"', $html);
        $this->assertStringContainsString('koleksi=parfum-pria', $html, 'product links remember the collection');
    }

    public function test_extra_filters_on_a_collection_stay_out_of_the_index(): void
    {
        $this->get('/koleksi/parfum-pria?sort=price_low')->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('products.collection', ['collection' => 'parfum-pria', 'sort' => 'price_low']).'">', false)
            ->assertDontSee('Terlaris di sini:');
        // The gender stays fixed even if the query tries to change it.
        $this->get('/koleksi/parfum-pria?gender=Wanita')->assertOk()->assertSee('Afnan Pria Satu')->assertDontSee('Afnan Wanita');
        $this->get('/koleksi/parfum-tidak-ada')->assertNotFound();
    }

    public function test_brand_page_shows_only_that_brand_and_needs_config_and_an_active_brand(): void
    {
        $this->get('/brand/afnan')->assertOk()
            ->assertSee('<title>Parfum Afnan Original di Palu - Qammaris Perfumes</title>', false)
            ->assertSee('Afnan adalah rumah parfum dari Dubai')
            ->assertSee('Afnan Pria Satu')->assertSee('Afnan Wanita')->assertDontSee('Lain Pria')
            ->assertDontSee('name="brand[]"', false)
            ->assertSee('name="gender"', false);

        $this->get('/brand/merek-lain')->assertNotFound();
        $this->afnan->update(['is_active' => false]);
        $this->get('/brand/afnan')->assertNotFound();
    }

    public function test_product_page_returns_to_its_collection_and_links_the_brand_page(): void
    {
        $product = Product::where('name', 'Afnan Pria Satu')->sole();

        $this->get(route('products.show', ['product' => $product->slug, 'koleksi' => 'parfum-pria']))->assertOk()
            ->assertSee('href="'.route('products.collection', 'parfum-pria').'" data-catalog-return', false)
            ->assertSee('href="'.route('products.brand', 'afnan').'"', false);
        $this->get(route('products.show', ['product' => $product->slug, 'koleksi' => 'brand-afnan']))
            ->assertSee('href="'.route('products.brand', 'afnan').'" data-catalog-return', false);
        $this->get(route('products.show', $product->slug))
            ->assertSee('href="'.route('products.index').'" data-catalog-return', false);
    }

    public function test_footer_sitemap_and_home_link_the_pages(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<a href="'.route('products.collection', 'parfum-wanita').'" class="footer-link">Parfum Wanita</a>', false)
            ->assertSee('<a href="'.route('products.brand', 'afnan').'" class="footer-link">Afnan</a>', false)
            ->assertSee('href="'.route('products.brand', 'afnan').'" class="brand-carousel__link"', false)
            ->assertDontSee(route('products.brand', 'rasasi'), false);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.route('products.collection', 'parfum-unisex').'</loc><lastmod>', $xml);
        $this->assertStringContainsString('<loc>'.route('products.brand', 'afnan').'</loc><lastmod>', $xml);
        $this->assertStringNotContainsString(route('products.brand', 'rasasi'), $xml, 'brands without products on sale stay out');
    }

    private function product(string $name, string $gender, Brand $brand): void
    {
        $product = Product::create([
            'brand_id' => $brand->id, 'name' => $name, 'description' => 'Deskripsi '.$name, 'base_price' => 150000,
            'gender' => $gender, 'is_active' => true, 'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(), 'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 150000, 'stock' => 0, 'is_active' => true]);
    }
}
