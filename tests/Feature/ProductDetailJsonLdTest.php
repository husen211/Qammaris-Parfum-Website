<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailJsonLdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_product_structured_data_is_valid_and_script_safe(): void
    {
        $product = $this->createProduct([
            'name' => 'Perfume </script><script>productAttack()</script>',
            'description' => 'Description </script><script>descriptionAttack()</script>',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 175000,
            'sku' => 'JSONLD-001',
            'stock' => 10,
            'is_active' => true,
        ]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();
        $schema = $this->productSchemaFrom($html);

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('Product', $schema['@type']);
        $this->assertSame($product->name, $schema['name']);
        $this->assertSame('Brand', $schema['brand']['@type']);
        $this->assertSame('175000.00', $schema['offers']['price']);
        $this->assertArrayNotHasKey('availability', $schema['offers']);
        $this->assertStringNotContainsString('</script><script>productAttack()', $html);
        $this->assertStringNotContainsString('</script><script>descriptionAttack()', $html);
        $this->assertStringNotContainsString('<?php', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_product_without_an_active_offer_does_not_publish_a_guessed_price(): void
    {
        $product = $this->createProduct();

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();
        $schema = $this->productSchemaFrom($html);

        $this->assertArrayNotHasKey('offers', $schema);
        $this->assertStringNotContainsString('Rp 175.000', $html);
        $this->assertStringContainsString('Data sedang dilengkapi', $html);
    }

    private function createProduct(array $overrides = []): Product
    {
        $brand = Brand::create([
            'name' => 'JSON-LD Brand',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'EDP']);

        return Product::create(array_merge([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'JSON-LD Product',
            'description' => 'Product description.',
            'base_price' => 175000,
            'gender' => 'Unisex',
            'is_active' => true,
        ], $overrides));
    }

    private function productSchemaFrom(string $html): array
    {
        preg_match_all(
            '/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s',
            $html,
            $matches
        );

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (($decoded['@type'] ?? null) === 'Product') {
                return $decoded;
            }
        }

        $this->fail('Product JSON-LD schema was not found.');
    }
}
