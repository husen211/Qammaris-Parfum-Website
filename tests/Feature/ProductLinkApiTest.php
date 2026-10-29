<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/** WAIT-02: App product id → public product page URL for the Daftar Tunggu message. */
class ProductLinkApiTest extends TestCase
{
    use RefreshDatabase, SignsOrderApiRequests;

    private const LIVE = '5f0c7a8e-1b2c-4d3e-8f90-123456789abc';

    private const DRAFT = '6ebaa494-e345-4be6-8204-5e2c15050abb';

    private const HIDDEN = '36e06f89-f2c8-47b1-84b8-928211f28962';

    private const ARCHIVED = '0b1c2d3e-4f50-4617-8899-aabbccddeeff';

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableOrderApi();
        $brand = Brand::create(['name' => 'Mykonos', 'is_active' => true]);
        $make = function (string $uuid, string $name, array $state) use ($brand): Product {
            $product = Product::create(['brand_id' => $brand->id, 'name' => $name, 'base_price' => 100000, 'is_active' => true,
                'publication_status' => Product::PUBLICATION_PUBLISHED, 'published_at' => now()]);
            $product->forceFill($state)->save();
            $product->externalIdentities()->create(['provider' => ProductExternalIdentity::PROVIDER_QAMMARIS_APP, 'external_product_id' => $uuid]);

            return $product;
        };
        $make(self::LIVE, 'Mykonos Black Opera 15 ml', []);
        $make(self::DRAFT, 'Draft product', ['publication_status' => Product::PUBLICATION_DRAFT, 'is_active' => false, 'published_at' => null]);
        $make(self::HIDDEN, 'Hidden in App', ['qammaris_app_hidden' => true]);
        $make(self::ARCHIVED, 'Archived product', ['publication_status' => Product::PUBLICATION_ARCHIVED, 'is_active' => false, 'archived_at' => now()]);
    }

    public function test_only_products_a_customer_can_open_get_a_link_in_request_order(): void
    {
        $live = Product::where('name', 'Mykonos Black Opera 15 ml')->sole();
        $ids = implode(',', ['unknown-id', self::HIDDEN, self::LIVE, self::DRAFT, self::ARCHIVED, self::LIVE]);

        $this->orderApi('GET', '/product-links?app_product_ids='.$ids)->assertOk()
            ->assertExactJson(['data' => [['app_product_id' => self::LIVE, 'url' => route('products.show', $live->slug)]]])
            ->assertHeader('X-Qammaris-Request-Id');
        $this->get(route('products.show', $live->slug))->assertOk();
        $this->get(route('products.show', Product::where('name', 'Hidden in App')->sole()->slug))->assertNotFound();

        $this->orderApi('GET', '/product-links?app_product_ids='.self::DRAFT)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_requests_are_signed_and_ids_are_validated(): void
    {
        $this->getJson(self::API.'/product-links?app_product_ids='.self::LIVE)->assertUnauthorized();
        $this->orderApi('GET', '/product-links?app_product_ids='.self::LIVE, secret: 'wrong-secret')->assertUnauthorized();

        foreach (['', '?app_product_ids=', '?app_product_ids=a,,b', '?app_product_ids='.str_repeat('a', 129),
            '?app_product_ids='.implode(',', range(1, 51)), '?app_product_ids[]='.self::LIVE, '?app_product_ids=a%20b'] as $query) {
            $error = $this->assertApiError($this->orderApi('GET', '/product-links'.$query), 422, 'validation_failed');
            $this->assertObjectHasProperty('app_product_ids', $error->error->details->fields, $query);
        }
        $this->orderApi('GET', '/product-links?app_product_ids='.implode(',', range(1, 50)))->assertOk()->assertExactJson(['data' => []]);
    }
}
