<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\FragranceQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductDomainStateTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->brand = Brand::create([
            'name' => 'Domain State Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create(['name' => 'EDP']);
    }

    public function test_additive_domain_columns_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('products', [
            'publication_status',
            'published_at',
            'archived_at',
            'availability_status',
            'stock_quantity',
            'availability_source',
            'availability_checked_at',
        ]));
    }

    public function test_publication_scope_requires_status_and_legacy_guard(): void
    {
        $published = $this->createProduct('Published Product');
        $this->createProduct('Draft Product', [
            'publication_status' => Product::PUBLICATION_DRAFT,
        ]);
        $this->createProduct('Archived Product', [
            'publication_status' => Product::PUBLICATION_ARCHIVED,
            'is_active' => false,
        ]);
        $this->createProduct('Compatibility Guard Product', [
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'is_active' => false,
        ]);

        $this->assertSame([$published->id], Product::published()->pluck('id')->all());
        $this->assertSame([$published->id], Product::active()->pluck('id')->all());
    }

    public function test_archive_and_restore_keep_identity_and_sync_compatibility_state(): void
    {
        $product = $this->createProduct('Transition Product');
        $identity = [$product->id, $product->slug];

        $product->markArchived();
        $product->refresh();

        $this->assertSame($identity, [$product->id, $product->slug]);
        $this->assertSame(Product::PUBLICATION_ARCHIVED, $product->publication_status);
        $this->assertFalse($product->is_active);
        $this->assertNotNull($product->archived_at);
        $this->assertFalse($product->isPublished());

        $product->markPublished();
        $product->refresh();

        $this->assertSame($identity, [$product->id, $product->slug]);
        $this->assertSame(Product::PUBLICATION_PUBLISHED, $product->publication_status);
        $this->assertTrue($product->is_active);
        $this->assertNull($product->archived_at);
        $this->assertNotNull($product->published_at);
        $this->assertTrue($product->isPublished());
    }

    public function test_available_status_expires_after_36_hours(): void
    {
        $product = $this->createProduct('Fresh Availability', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(35),
        ]);

        $this->assertSame(Product::AVAILABILITY_AVAILABLE, $product->effective_availability);

        $product->update(['availability_checked_at' => now()->subHours(37)]);
        $this->assertSame(Product::AVAILABILITY_UNKNOWN, $product->fresh()->effective_availability);

        $product->update(['availability_checked_at' => null]);
        $this->assertSame(Product::AVAILABILITY_UNKNOWN, $product->fresh()->effective_availability);
    }

    public function test_sold_out_does_not_expire_or_change_publication(): void
    {
        $product = $this->createProduct('Sold Out Product', [
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now()->subDays(30),
            'stock_quantity' => 0,
        ]);

        $this->assertSame(Product::AVAILABILITY_SOLD_OUT, $product->effective_availability);
        $this->assertTrue($product->isPublished());
        $this->assertSame(0, $product->stock_quantity);
    }

    public function test_draft_product_is_not_public_or_cart_eligible(): void
    {
        $draft = $this->createProduct('Private Draft Product', [
            'publication_status' => Product::PUBLICATION_DRAFT,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $draft->id,
            'volume' => 100,
            'price' => 175000,
            'sku' => 'DRAFT-001',
            'stock' => 5,
            'is_active' => true,
        ]);

        $this->get(route('products.show', $draft))->assertNotFound();
        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee($draft->name);
        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertNotFound();
    }

    public function test_fragrance_quiz_only_recommends_published_products(): void
    {
        $published = $this->createProduct('Published Recommendation', [
            'is_best_seller' => true,
        ]);
        $this->createProduct('Draft Recommendation', [
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_best_seller' => true,
        ]);

        $result = app(FragranceQuizService::class)->recommend([]);

        $this->assertSame([$published->id], $result['products']->pluck('id')->all());
    }

    private function createProduct(string $name, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => 'Domain state test product.',
            'base_price' => 175000,
            'gender' => 'Unisex',
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ], $overrides));
    }
}
